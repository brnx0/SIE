<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Support\UserAccess;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AlunosPorEscolaController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();
        $anoExercicio = AnoLetivo::where('anl_fl_em_exercicio', true)->first(['anl_id', 'anl_ano']);

        return Inertia::render('relatorios/AlunosPorEscola/Form', [
            'anoExercicio' => $anoExercicio,
            'escolas'      => UserAccess::escolasVisiveis($user),
            'userEscola'   => UserAccess::escolaDefault($user),
            'isAdmin'      => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        ['parametros' => $parametros, 'filtros' => $filtros, 'linhas' => $linhas] = $this->montar($request);

        return Inertia::render('relatorios/AlunosPorEscola/Resultado', [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
            'total'      => $linhas->count(),
        ]);
    }

    public function exportarXlsx(Request $request): \Illuminate\Http\Response
    {
        ['linhas' => $linhas] = $this->montar($request);

        $headers = ['Nome', 'Turma', 'CPF', 'Sexo', 'Endereço', 'Deficiência', 'Data de Nascimento', 'Nome da Mãe'];

        $rows = $linhas->map(fn ($l) => [
            $l['nome'],
            $l['turma'],
            $l['cpf'],
            $l['sexo'],
            $l['endereco'],
            $l['deficiencia'],
            $l['dt_nascimento'],
            $l['mae'],
        ])->all();

        return XlsxExport::download('alunos_por_escola_' . now()->format('Ymd_His') . '.xlsx', $headers, $rows);
    }

    /**
     * Executa a query e monta linhas/filtros/parametros (compartilhado entre tela e export).
     *
     * Sempre para o ano letivo em exercício, alunos com matrícula ATIVA na escola,
     * um registro por aluno (dedup) e em ordem alfabética.
     *
     * @return array{parametros: array|null, filtros: array, linhas: \Illuminate\Support\Collection}
     */
    private function montar(Request $request): array
    {
        $data = $request->validate([
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);

        $anoLetivo = AnoLetivo::where('anl_fl_em_exercicio', true)->firstOrFail();
        $escola    = Escola::findOrFail($data['esc_id']);

        $linhas = Matricula::query()
            ->with([
                'aluno:aln_id,aln_nome,aln_nr_matricula,aln_cpf,aln_dt_nascimento,aln_sexo,'
                    . 'aln_logradouro,aln_numero,aln_complemento,aln_bairro,aln_cidade,aln_uf,'
                    . 'aln_filiacao_1,aln_filiacao_1_tipo,aln_filiacao_2,aln_filiacao_2_tipo',
                'aluno.saude:als_id,als_aln_id,als_fl_pcd',
                'turma:tur_id,tur_nome',
            ])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereHas('turma', fn ($q) => $q
                ->where('tur_esc_id', $data['esc_id'])
                ->where('tur_anl_id', $anoLetivo->anl_id))
            ->get()
            ->filter(fn (Matricula $m) => $m->aluno !== null)
            // Um registro por aluno; mantém a turma da primeira matrícula ativa encontrada.
            ->unique(fn (Matricula $m) => $m->aluno->aln_id)
            ->sortBy(fn (Matricula $m) => $this->chaveOrdenacao($m->aluno->aln_nome), SORT_STRING)
            ->values()
            ->map(fn (Matricula $m) => [
                'aln_id'         => $m->aluno->aln_id,
                'nome'           => $m->aluno->aln_nome,
                'turma'          => $m->turma?->tur_nome,
                'cpf'            => $m->aluno->aln_cpf,
                'sexo'           => $m->aluno->aln_sexo,
                'endereco'       => $this->endereco($m->aluno),
                'deficiencia'    => $m->aluno->saude?->als_fl_pcd ? 'Sim' : 'Não',
                'dt_nascimento'  => $m->aluno->aln_dt_nascimento
                    ? Carbon::parse($m->aluno->aln_dt_nascimento)->format('d/m/Y')
                    : null,
                'mae'            => $this->nomeMae($m->aluno),
            ]);

        $parametros = ParametroEntidade::first();

        return [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'endereco'           => $parametros->par_endereco,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'filtros' => [
                'anl_id'   => $anoLetivo->anl_id,
                'anl_ano'  => $anoLetivo->anl_ano,
                'esc_id'   => $escola->esc_id,
                'esc_nome' => $escola->esc_nome,
            ],
            'linhas' => $linhas,
        ];
    }

    /** Chave de ordenação alfabética insensível a acento/caixa. */
    private function chaveOrdenacao(?string $nome): string
    {
        $s = mb_strtolower($nome ?? '', 'UTF-8');
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);

        return $t !== false ? $t : $s;
    }

    /** Endereço em linha única: "Logradouro, nº, compl. - Bairro - Cidade/UF". */
    private function endereco($aluno): ?string
    {
        $rua = trim(implode(', ', array_filter([
            $aluno->aln_logradouro,
            $aluno->aln_numero,
            $aluno->aln_complemento,
        ], fn ($v) => $v !== null && $v !== '')));

        $cidadeUf = $aluno->aln_cidade && $aluno->aln_uf
            ? "{$aluno->aln_cidade}/{$aluno->aln_uf}"
            : ($aluno->aln_cidade ?: $aluno->aln_uf);

        $partes = array_filter([$rua, $aluno->aln_bairro, $cidadeUf], fn ($v) => $v !== null && $v !== '');

        return $partes ? implode(' - ', $partes) : null;
    }

    /** Nome da mãe: filiação marcada como MAE; sem marcação, assume a filiação 1. */
    private function nomeMae($aluno): ?string
    {
        if ($aluno->aln_filiacao_1_tipo === 'MAE') {
            return $aluno->aln_filiacao_1;
        }

        if ($aluno->aln_filiacao_2_tipo === 'MAE') {
            return $aluno->aln_filiacao_2;
        }

        return $aluno->aln_filiacao_1_tipo === null ? $aluno->aln_filiacao_1 : null;
    }
}
