<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Matricula\Matricula;
use App\Models\Turma\Turma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatriculaTurmaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $anlId    = (int) $request->input('anl_id');
        $escId    = (int) $request->input('esc_id');
        $segId    = (int) $request->input('seg_id');
        $serId    = (int) $request->input('ser_id');
        $semestre = (int) $request->input('semestre');

        if (! $anlId || ! $escId) {
            return response()->json([]);
        }

        $turmas = Turma::query()
            ->with([
                'escola:esc_id,esc_nome,esc_cd_escola',
                'serie:ser_id,ser_nome,ser_idade',
                'segmento:seg_id,seg_nome_reduzido',
                'anoLetivo:anl_id,anl_ano,anl_dt_corte',
            ])
            ->where('tur_esc_id', $escId)
            ->where('tur_anl_id', $anlId)
            ->when($segId, fn ($q) => $q->where('tur_seg_id', $segId))
            ->when($serId, fn ($q) => $q->where('tur_ser_id', $serId))
            ->when($semestre, fn ($q) => $q->where('tur_semestre', $semestre))
            ->withCount([
                'matriculas as total_matriculados' => fn ($q) => $q->where('tma_situacao', Matricula::SITUACAO_ATIVA),
            ])
            ->orderBy('tur_semestre')
            ->orderBy('tur_ser_id')
            ->orderBy('tur_turno')
            ->orderBy('tur_nome')
            ->get();

        return response()->json($turmas->map(fn (Turma $t) => [
            'tur_id'              => $t->tur_id,
            'tur_nome'            => $t->tur_nome,
            'tur_turno'           => $t->tur_turno,
            'tur_semestre'        => $t->tur_semestre,
            'tur_situacao'        => $t->tur_situacao,
            'tur_modalidade'      => $t->tur_modalidade,
            'tur_capacidade'      => $t->tur_capacidade,
            'total_matriculados'  => $t->total_matriculados,
            'vagas_disponiveis'   => $t->tur_capacidade !== null
                ? max(0, $t->tur_capacidade - $t->total_matriculados)
                : null,
            'escola'              => $t->escola ? ['esc_id' => $t->escola->esc_id, 'esc_nome' => $t->escola->esc_nome, 'esc_cd_escola' => $t->escola->esc_cd_escola] : null,
            'serie'               => $t->serie ? ['ser_id' => $t->serie->ser_id, 'ser_nome' => $t->serie->ser_nome, 'ser_idade' => $t->serie->ser_idade] : null,
            'segmento'            => $t->segmento ? ['seg_id' => $t->segmento->seg_id, 'seg_nome_reduzido' => $t->segmento->seg_nome_reduzido] : null,
            'ano_letivo'          => $t->anoLetivo ? ['anl_id' => $t->anoLetivo->anl_id, 'anl_ano' => $t->anoLetivo->anl_ano, 'anl_dt_corte' => $t->anoLetivo->anl_dt_corte?->format('Y-m-d')] : null,
        ]));
    }
}
