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

        $headers = ['Matrícula', 'Nome', 'CPF', 'Idade'];

        $rows = $linhas->map(fn ($l) => [
            $l['nr_matricula'],
            $l['nome'],
            $l['cpf'],
            $l['idade'],
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
        $dtCorte   = $anoLetivo->anl_dt_corte;

        $alunos = Matricula::query()
            ->with(['aluno:aln_id,aln_nome,aln_nr_matricula,aln_cpf,aln_dt_nascimento'])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereHas('turma', fn ($q) => $q
                ->where('tur_esc_id', $data['esc_id'])
                ->where('tur_anl_id', $anoLetivo->anl_id))
            ->get()
            ->map(fn (Matricula $m) => $m->aluno)
            ->filter()
            ->unique('aln_id')
            ->sortBy(fn ($a) => $this->chaveOrdenacao($a->aln_nome), SORT_STRING)
            ->values();

        $linhas = $alunos->map(fn ($a) => [
            'aln_id'       => $a->aln_id,
            'nr_matricula' => $a->aln_nr_matricula,
            'nome'         => $a->aln_nome,
            'cpf'          => $a->aln_cpf,
            'idade'        => $this->idade($a->aln_dt_nascimento, $dtCorte),
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

    /** Idade em anos completos na data de corte do ano em exercício (ou hoje, se sem corte). */
    private function idade($dtNascimento, $dtCorte): ?int
    {
        if (! $dtNascimento) {
            return null;
        }

        $nasc = Carbon::parse($dtNascimento);
        $ref  = $dtCorte ? Carbon::parse($dtCorte) : Carbon::now();

        $idade = $ref->year - $nasc->year;
        if ($ref->format('m-d') < $nasc->format('m-d')) {
            $idade--;
        }

        return $idade;
    }
}
