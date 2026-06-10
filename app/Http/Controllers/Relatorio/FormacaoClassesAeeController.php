<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FormacaoClassesAeeController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/FormacaoClassesAee/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => $user->isAdmin()
                ? Escola::where('esc_fl_ativo', true)->orderBy('esc_nome')->get(['esc_id', 'esc_nome'])
                : Escola::where('esc_id', $user->esc_id)->get(['esc_id', 'esc_nome']),
            'userEscola'  => $user->isAdmin() ? null : ['esc_id' => $user->esc_id, 'esc_nome' => $user->escola?->esc_nome],
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);

        $anoLetivo = AnoLetivo::findOrFail($data['anl_id']);
        $escola    = Escola::findOrFail($data['esc_id']);
        $parametros = ParametroEntidade::with('municipio:mun_id,mun_nome,mun_uf')->first();

        $turmas = Turma::query()
            ->with([
                'matriculas' => fn ($q) => $q
                    ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                    ->with([
                        'aluno:aln_id,aln_dt_nascimento',
                        'aluno.saude:als_id,als_aln_id,als_fl_pcd',
                    ]),
            ])
            ->where('tur_esc_id', $data['esc_id'])
            ->where('tur_anl_id', $data['anl_id'])
            ->where('tur_modalidade', Turma::MODALIDADE_AEE)
            ->where('tur_situacao', 'ABERTA')
            ->get();

        $dtCorte = $anoLetivo->anl_dt_corte;

        $alnIds = $turmas->pluck('matriculas')->flatten(1)->pluck('tma_aln_id')->unique()->all();
        $totalPorAluno = DB::table('edu_turma_aluno')
            ->whereIn('tma_aln_id', $alnIds)
            ->whereNull('tma_deleted_at')
            ->select('tma_aln_id', DB::raw('COUNT(*) as total'))
            ->groupBy('tma_aln_id')
            ->pluck('total', 'tma_aln_id');

        $turmas = $turmas
            ->sortBy(fn ($t) => [$t->tur_turno, $t->tur_aee_sala ?? '', $t->tur_nome])
            ->values();

        $linhas = $turmas->map(function (Turma $t, $i) use ($totalPorAluno, $dtCorte) {
            $cap = (int) ($t->tur_capacidade ?? 0);
            $exp = (int) ($t->tur_qt_expansao ?? 0);

            $novos = 0; $rede = 0; $pcd = 0;
            $idadeMin = null; $idadeMax = null;

            foreach ($t->matriculas as $m) {
                $totalAluno = (int) ($totalPorAluno[$m->tma_aln_id] ?? 1);
                if ($totalAluno <= 1) $novos++;
                else $rede++;

                if ($m->aluno?->saude?->als_fl_pcd) $pcd++;

                $nasc = $m->aluno?->aln_dt_nascimento;
                if ($nasc && $dtCorte) {
                    $idade = $this->calcularIdade($nasc, $dtCorte);
                    if ($idadeMin === null || $idade < $idadeMin) $idadeMin = $idade;
                    if ($idadeMax === null || $idade > $idadeMax) $idadeMax = $idade;
                }
            }

            $idadeTexto = '—';
            if ($idadeMin !== null && $idadeMax !== null) {
                $idadeTexto = $idadeMin === $idadeMax ? (string) $idadeMin : "{$idadeMin} a {$idadeMax}";
            }

            return [
                'num'         => $i + 1,
                'turno'       => $t->tur_turno,
                'turma'       => $t->tur_nome,
                'sala'        => $t->tur_aee_sala ?: '—',
                'pcd'         => $pcd,
                'idade'       => $idadeTexto,
                'capacidade'  => $cap,
                'expansao'    => $exp,
                'rede'        => $rede,
                'novos'       => $novos,
                'vagas'       => max(0, $cap + $exp - $rede - $novos),
            ];
        });

        return Inertia::render('relatorios/FormacaoClassesAee/Resultado', [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'endereco'           => $parametros->par_endereco,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'filtros' => [
                'anl_ano'  => $anoLetivo->anl_ano,
                'esc_nome' => $escola->esc_nome,
            ],
            'linhas' => $linhas,
            'total'  => $linhas->count(),
        ]);
    }

    protected function calcularIdade($nascimento, $referencia): int
    {
        $nasc = $nascimento instanceof Carbon ? $nascimento : Carbon::parse($nascimento);
        $ref  = $referencia instanceof Carbon ? $referencia : Carbon::parse($referencia);
        $idade = $ref->year - $nasc->year;
        if ($ref->format('m-d') < $nasc->format('m-d')) $idade--;
        return $idade;
    }
}
