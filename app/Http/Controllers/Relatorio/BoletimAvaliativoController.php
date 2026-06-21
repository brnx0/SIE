<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Relatorio\Concerns\NotaLookups;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\CalculoNota;
use App\Support\FaltasAluno;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Boletim Avaliativo: agrupado por disciplina da grade (1 página por disciplina).
 * Por disciplina lista todos os alunos com a média de cada unidade (bimestre),
 * total de faltas na matéria, média final (M.F) e o resultado final.
 * (A regra de aprovação será definida futuramente — por ora "Aprovado (a)".)
 */
class BoletimAvaliativoController extends Controller
{
    use NotaLookups;

    private const TURNOS = ['M' => 'Matutino', 'T' => 'Vespertino', 'V' => 'Vespertino', 'N' => 'Noturno', 'INTEGRAL' => 'Integral'];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/BoletimAvaliativo/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id' => ['nullable', 'integer', 'exists:edu_disciplina,dis_id'],
        ]);

        $this->autorizarTurma((int) $data['tur_id']);

        $ano    = AnoLetivo::findOrFail($data['anl_id']);
        $escola = Escola::findOrFail($data['esc_id']);
        $turma  = Turma::with('serie:ser_id,ser_nome')->findOrFail($data['tur_id']);
        $disId  = ! empty($data['dis_id']) ? (int) $data['dis_id'] : null;

        // Cabeçalho da escola (endereço completo).
        $e = DB::table('edu_escola as esc')
            ->leftJoin('edu_bairro as b', 'b.bai_id', '=', 'esc.esc_bai_id')
            ->leftJoin('edu_municipio as m', 'm.mun_id', '=', 'esc.esc_mun_id')
            ->where('esc.esc_id', $data['esc_id'])
            ->first(['esc.esc_nome', 'esc.esc_logradouro', 'esc.esc_numero', 'esc.esc_cep', 'b.bai_nome', 'm.mun_nome', 'm.mun_uf']);

        $logradouro = trim(($e->esc_logradouro ?? '') . ($e->esc_numero ? ', ' . $e->esc_numero : ''));
        $endereco = implode(' - ', array_filter([
            $logradouro ?: null,
            $e->bai_nome ?? null,
            $e->mun_nome ? $e->mun_nome . ($e->mun_uf ? ' (' . $e->mun_uf . ')' : '') : null,
            $e->esc_cep ?: null,
        ]));

        // Unidades (bimestres) do ano — colunas.
        $unidades = DB::table('cfg_unidade')
            ->where('uni_anl_id', $data['anl_id'])
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_numero', 'uni_tipo'])
            ->map(fn ($u) => ['uni_id' => (int) $u->uni_id, 'label' => $this->unidadeLabel((int) $u->uni_numero, (string) $u->uni_tipo)])
            ->values();

        // Disciplinas da grade (todas, mesmo sem nota); ou só a filtrada.
        $disciplinas = DB::table('edu_grade_disciplinar as gd')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
            ->where('gd.grd_ser_id', $turma->tur_ser_id)
            ->where('gd.grd_anl_id', $data['anl_id'])
            ->where('gd.grd_fl_ativo', true)
            ->when($disId, fn ($q) => $q->where('d.dis_id', $disId))
            ->distinct()
            ->orderBy('gd.grd_ordem')
            ->orderBy('d.dis_nome')
            ->get(['d.dis_id', 'd.dis_nome', 'gd.grd_ordem']);

        // Roster: ativos + alunos que saíram (com situação de saída) → resultado "Transferido" etc.
        $alunos = Matricula::query()
            ->where('tma_tur_id', $turma->tur_id)
            ->where(function ($q) {
                $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                  ->orWhereNotNull('tma_tas_cod_saida');
            })
            ->whereNull('tma_deleted_at')
            ->with(['aluno:aln_id,aln_nome', 'situacaoSaida:tas_cod,tas_descricao'])
            ->get()
            ->filter(fn ($m) => $m->aluno)
            // Sem dedup por aluno: 2 matrículas (saída + nova) na mesma turma aparecem
            // ambas (ex.: aluno transferido e depois rematriculado), como no modelo.
            ->sortBy(fn ($m) => \Illuminate\Support\Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values();

        $turno = $turma->tur_turno
            ? (self::TURNOS[strtoupper((string) $turma->tur_turno)] ?? ucfirst(mb_strtolower((string) $turma->tur_turno)))
            : null;

        // Média de aprovação (numérica): por escola no ano letivo; senão a média geral do ano.
        $mediaEscola = DB::table('cfg_media_escola')
            ->where('mde_anl_id', $data['anl_id'])
            ->where('mde_esc_id', $data['esc_id'])
            ->value('mde_media');
        $mediaAprov = $mediaEscola !== null
            ? (float) $mediaEscola
            : ($ano->anl_media_geral !== null ? (float) $ano->anl_media_geral : null);

        $blocos = $disciplinas->map(function ($d) use ($turma, $unidades, $alunos, $mediaAprov) {
            $disId = (int) $d->dis_id;

            // Professor(es) da disciplina na turma.
            $professor = DB::table('edu_turma_professor as tp')
                ->join('edu_funcionario as f', 'f.fun_id', '=', 'tp.tup_fun_id')
                ->where('tp.tup_tur_id', $turma->tur_id)
                ->where('tp.tup_dis_id', $disId)
                ->whereNull('tp.tup_deleted_at')
                ->distinct()
                ->pluck('f.fun_nome')
                ->implode(', ');

            // Resultados por unidade (aln => result).
            $res = [];
            foreach ($unidades as $u) {
                $res[$u['uni_id']] = CalculoNota::resultado((int) $turma->tur_id, $disId, $u['uni_id']);
            }

            // Faltas totais na matéria (todas as unidades): aln => qtd (manual tem precedência).
            $faltas = FaltasAluno::totaisDisciplina((int) $turma->tur_id, $disId, $unidades->pluck('uni_id')->all());

            $linhas = $alunos->map(function (Matricula $m) use ($unidades, $res, $faltas, $mediaAprov) {
                $alnId = (int) $m->aluno->aln_id;
                $saiu = $m->tma_situacao !== Matricula::SITUACAO_ATIVA;

                $medias = [];
                $porUnidade = [];
                foreach ($unidades as $u) {
                    $r = $res[$u['uni_id']][$alnId] ?? ['tipo' => null, 'valor' => null, 'conceito' => null];
                    $porUnidade[$u['uni_id']] = $r;
                    $texto = $this->celula($r)['texto'];
                    $medias[$u['uni_id']] = ($saiu || $texto === '—') ? '' : $texto;
                }
                $consol = CalculoNota::consolidar($porUnidade);
                $mfTexto = $this->celula($consol)['texto'];

                // Resultado: saída => situação; numérica => compara com a média; senão placeholder.
                if ($saiu) {
                    $resultado = $m->situacaoSaida?->tas_descricao ?? 'Saída';
                } elseif ($consol['tipo'] === 'numerica' && $consol['valor'] !== null && $mediaAprov !== null) {
                    $resultado = (float) $consol['valor'] >= $mediaAprov ? 'Aprovado (a)' : 'Reprovado (a)';
                } else {
                    $resultado = 'Aprovado (a)';
                }

                return [
                    'aln_nome'  => $m->aluno->aln_nome,
                    'faltas'    => (int) ($faltas[$alnId] ?? 0),
                    'medias'    => $medias,
                    'mf'        => $saiu ? '-' : ($mfTexto === '—' ? '' : $mfTexto),
                    'resultado' => $resultado,
                ];
            });

            return [
                'dis_nome'   => $d->dis_nome,
                'professor'  => $professor ?: '—',
                'alunos'     => $linhas->values(),
            ];
        });

        $p = ParametroEntidade::first();

        return Inertia::render('relatorios/BoletimAvaliativo/Resultado', [
            'parametros' => $p ? [
                'estado'        => $p->par_msg_cab_estado,
                'entidade'      => $p->par_nome_entidade,
                'secretaria'    => $p->par_msg_cab_secretaria,
                'logomarca_url' => $p->par_logomarca_url,
            ] : null,
            'escola' => [
                'nome'     => $e->esc_nome,
                'endereco' => $endereco ?: null,
            ],
            'cabecalho' => [
                'anl_ano'         => $ano->anl_ano,
                'turma'           => trim(($turma->serie?->ser_nome ? $turma->serie->ser_nome . ' ' : '') . $turma->tur_nome),
                'turno'           => $turno,
                'media_aprovacao' => $mediaAprov,
            ],
            'unidades'    => $unidades,
            'disciplinas' => $blocos->values(),
            'esc_nome'    => $escola->esc_nome,
        ]);
    }
}
