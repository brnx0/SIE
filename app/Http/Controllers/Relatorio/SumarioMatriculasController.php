<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Serie\Serie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SumarioMatriculasController extends Controller
{
    public function form(): Response
    {
        return Inertia::render('relatorios/SumarioMatriculas/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
        ]);

        $anoLetivo  = AnoLetivo::findOrFail($data['anl_id']);
        $parametros = ParametroEntidade::with('municipio:mun_id,mun_nome,mun_uf')->first();

        // Colunas: todas séries ativas, ordenadas por seg_ordem, ser_ordem_no_segmento
        $series = Serie::query()
            ->with('segmento:seg_id,seg_nome_reduzido,seg_ordem')
            ->where('ser_fl_ativo', true)
            ->get()
            ->sortBy(fn ($s) => [$s->segmento?->seg_ordem ?? 9999, $s->ser_ordem_no_segmento ?? 9999])
            ->values()
            ->map(fn ($s) => [
                'ser_id'   => $s->ser_id,
                'ser_nome' => $s->ser_nome,
                'seg_nome' => $s->segmento?->seg_nome_reduzido,
            ]);

        // Linhas: todas as escolas ativas
        $escolas = Escola::where('esc_fl_ativo', true)
            ->orderBy('esc_nome')
            ->get(['esc_id', 'esc_nome']);

        // Counts: pivot escola x série em UMA query
        $rows = DB::table('edu_turma_aluno as tma')
            ->join('edu_turma as tur', 'tma.tma_tur_id', '=', 'tur.tur_id')
            ->where('tma.tma_situacao', 'ATIVA')
            ->where('tur.tur_anl_id', $anoLetivo->anl_id)
            ->where('tur.tur_modalidade', 'REGULAR')
            ->whereNull('tma.tma_deleted_at')
            ->whereNull('tur.tur_deleted_at')
            ->select('tur.tur_esc_id', 'tur.tur_ser_id', DB::raw('COUNT(*) as total'))
            ->groupBy('tur.tur_esc_id', 'tur.tur_ser_id')
            ->get();

        // Mapa rápido [esc_id][ser_id] = total
        $mapa = [];
        foreach ($rows as $r) {
            $mapa[$r->tur_esc_id][$r->tur_ser_id] = (int) $r->total;
        }

        $linhas = $escolas->map(function (Escola $e) use ($series, $mapa) {
            $valores = $series->map(fn ($s) => $mapa[$e->esc_id][$s['ser_id']] ?? 0)->all();
            return [
                'esc_id'   => $e->esc_id,
                'esc_nome' => $e->esc_nome,
                'valores'  => $valores,
                'total'    => array_sum($valores),
            ];
        });

        // Totais por coluna
        $totaisColuna = [];
        foreach ($series as $idx => $s) {
            $totaisColuna[$idx] = $linhas->sum(fn ($l) => $l['valores'][$idx]);
        }
        $totalGeral = array_sum($totaisColuna);

        return Inertia::render('relatorios/SumarioMatriculas/Resultado', [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'endereco'           => $parametros->par_endereco,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'filtros' => [
                'anl_ano' => $anoLetivo->anl_ano,
            ],
            'series'       => $series,
            'linhas'       => $linhas,
            'totaisColuna' => array_values($totaisColuna),
            'totalGeral'   => $totalGeral,
        ]);
    }
}
