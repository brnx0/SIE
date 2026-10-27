<?php

namespace App\Http\Controllers\Api\V1\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\DiarioAvaliacao;
use App\Models\Diario\DiarioNota;
use App\Models\Diario\InstrumentoAvaliativo;
use App\Models\Diario\NotaManual;
use App\Models\Matricula\Matricula;
use App\Support\CalculoNota;
use App\Support\Diario\DiarioEscritaGuard as Guard;
use App\Support\Diario\RegrasParametrizacaoAvaliacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Notas do Diário regular — API v1 (app do professor).
 *
 * Espelha App\Http\Controllers\Diario\NotaController, com o mesmo escopo estrito
 * do FrequenciaController: só a lotação real do professor, sem bypass de admin.
 *
 * Regras preservadas do web (NÃO reimplementar diferente):
 *  · soma dos valores das avaliações REGULARES do contexto ≤ 10 (recuperação fora);
 *  · nota ≤ ava_valor;
 *  · exclusão mútua numérica × conceitual por (aluno, turma, disciplina) — atravessa
 *    todas as unidades;
 *  · o MODO do conceito vem do ANO da UNIDADE (cfg_unidade.uni_anl_id →
 *    cfg_ano_letivo.anl_conceito_modo), não da turma;
 *  · avaliação com data futura entra no contexto mas bloqueia lançamento;
 *  · qualquer escrita invalida o override manual da secretaria (edu_nota_manual).
 */
class NotasController extends Controller
{
    /** Contexto completo de (turma, disciplina, unidade, tipo). */
    public function contexto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'uni_id' => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'tipo'   => ['nullable', 'string', 'in:numerica,conceitual'],
        ]);

        $turId = (int) $data['tur_id'];
        $disId = (int) $data['dis_id'];
        $uniId = (int) $data['uni_id'];
        $tipo  = $data['tipo'] ?? DiarioAvaliacao::TIPO_NUMERICA;
        $funId = (int) $request->user()->fun_id;

        Guard::assertRegenciaDisciplina($funId, $turId, $disId);

        // A série habilita o tipo; o ano define COMO lançar. Dimensões ortogonais.
        if (! in_array($tipo, $this->tiposSerie($turId), true)) {
            return response()->json([
                'tipo_disponivel' => false,
                'periodo_aberto'  => false,
                'turma_aberta'    => Guard::turmaAberta($turId),
                'avaliacoes'      => [],
                'alunos'          => [],
            ]);
        }

        $avaliacoes = DiarioAvaliacao::query()
            ->where('ava_tur_id', $turId)
            ->where('ava_dis_id', $disId)
            ->where('ava_uni_id', $uniId)
            ->where('ava_tipo', $tipo)
            ->orderBy('ava_fl_recuperacao')
            ->orderBy('ava_dt')
            ->orderBy('ava_id')
            ->get(['ava_id', 'ava_iav_id', 'ava_descricao', 'ava_dt', 'ava_valor', 'ava_fl_recuperacao']);

        $nomesInstrumento = InstrumentoAvaliativo::pluck('iav_nome', 'iav_id');
        $hoje = now()->toDateString();

        $avaliacoesOut = $avaliacoes->map(fn ($a) => [
            'ava_id'             => (int) $a->ava_id,
            'ava_iav_id'         => (int) $a->ava_iav_id,
            'iav_nome'           => $nomesInstrumento[$a->ava_iav_id] ?? null,
            'ava_descricao'      => $a->ava_descricao,
            'ava_dt'             => $a->ava_dt ? Carbon::parse($a->ava_dt)->toDateString() : null,
            'ava_valor'          => $a->ava_valor !== null ? (float) $a->ava_valor : null,
            'ava_fl_recuperacao' => (bool) $a->ava_fl_recuperacao,
            // Futura = existe no contexto, mas não aceita lançamento.
            'futura'             => $a->ava_dt ? Carbon::parse($a->ava_dt)->toDateString() > $hoje : false,
        ])->values();

        $notaMap = DiarioNota::whereIn('nta_ava_id', $avaliacoes->pluck('ava_id'))
            ->get(['nta_ava_id', 'nta_aln_id', 'nta_valor', 'nta_cnc_id'])
            ->groupBy('nta_aln_id');

        // Quem já tem nota do tipo OPOSTO nesta turma+disciplina. UMA query para
        // a turma toda — calcular por aluno dentro do map custaria N queries.
        $bloqueados = $this->alunosComNotaDeOutroTipo($turId, $disId, $tipo);

        $alunos = $this->alunosDaTurma($turId)->map(function ($m) use ($avaliacoes, $notaMap, $bloqueados) {
            $alnId = (int) $m->aluno->aln_id;
            $notas = [];
            $doAluno = $notaMap->get($alnId);

            foreach ($avaliacoes as $a) {
                $n = $doAluno?->firstWhere('nta_ava_id', $a->ava_id);
                $notas[(string) $a->ava_id] = [
                    'valor'  => $n && $n->nta_valor !== null ? (float) $n->nta_valor : null,
                    'cnc_id' => $n && $n->nta_cnc_id !== null ? (int) $n->nta_cnc_id : null,
                ];
            }

            return [
                'aln_id'           => $alnId,
                'aln_nome'         => $m->aluno->aln_nome,
                'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                'dt_saida'         => $m->tma_dt_saida?->toDateString(),
                // Já tem nota do tipo OPOSTO nesta turma+disciplina (qualquer unidade).
                'bloqueado'        => isset($bloqueados[$alnId]),
                'notas'            => $notas,
            ];
        })->values();

        $param = RegrasParametrizacaoAvaliacao::contexto($uniId, $request->user()->isAdmin());

        return response()->json([
            'tipo_disponivel' => true,
            'tipo'            => $tipo,
            'periodo_aberto'  => Guard::periodoAberto($uniId),
            'turma_aberta'    => Guard::turmaAberta($turId),
            // 'faixa' = professor digita número; 'conceito' = escolhe o conceito.
            'modo'            => $this->conceitoModo($uniId),
            // Parametrizados da unidade (com valor_param) ou todos os ativos.
            'instrumentos'    => $param['instrumentos'],
            'parametrizacao'  => $param['parametrizacao'],
            'conceitos'       => DB::table('cfg_conceito')
                ->orderBy('cnc_peso')
                ->get(['cnc_id', 'cnc_sigla', 'cnc_descricao', 'cnc_limite_inferior', 'cnc_limite_superior', 'cnc_peso'])
                ->map(fn ($c) => [
                    'cnc_id'              => (int) $c->cnc_id,
                    'cnc_sigla'           => $c->cnc_sigla,
                    'cnc_descricao'       => $c->cnc_descricao,
                    // Vêm como string do cast decimal:2 — normalizados aqui.
                    'cnc_limite_inferior' => (float) $c->cnc_limite_inferior,
                    'cnc_limite_superior' => (float) $c->cnc_limite_superior,
                    'cnc_peso'            => (int) $c->cnc_peso,
                ]),
            'avaliacoes'      => $avaliacoesOut,
            'alunos'          => $alunos,
            // Soma já usada pelas regulares — o app mostra "Disponível: X de 10".
            'soma_regulares'  => $this->somaRegulares($turId, $disId, $uniId, $tipo),
            // Média/conceito por aluno, calculada pela fonte canônica do sistema.
            'resultados'      => CalculoNota::resultado($turId, $disId, $uniId),
        ]);
    }

    /** Cria uma avaliação. */
    public function storeAvaliacao(Request $request): JsonResponse
    {
        $data = $this->validarAvaliacao($request);

        $turId = (int) $data['ava_tur_id'];
        $disId = (int) $data['ava_dis_id'];
        $uniId = (int) $data['ava_uni_id'];
        $tipo  = $data['ava_tipo'];
        $funId = (int) $request->user()->fun_id;

        Guard::assertRegenciaDisciplina($funId, $turId, $disId);
        Guard::assertTurmaAberta($turId);
        $this->assertTipoSerie($turId, $tipo);
        Guard::assertPeriodoAberto($uniId);
        // NOTA: o web não valida a DATA da avaliação contra o período — só exige
        // que HOJE esteja dentro dele. Mantido igual de propósito: um app mais
        // restritivo que o desktop recusaria dados que o sistema aceita, e o
        // professor não entenderia a diferença. Se a rede quiser essa trava, ela
        // deve entrar nos dois lados (aqui e no NotaController web).
        $ehRecuperacao = $this->instrumentoRecuperacao((int) $data['ava_iav_id']);
        $valorInput = RegrasParametrizacaoAvaliacao::aplicar(
            $uniId, (int) $data['ava_iav_id'], $data['ava_valor'] ?? null, $request->user()->isAdmin(),
        );
        $valor = $this->resolverValor($tipo, $uniId, $valorInput);

        if ($valor !== null && ! $ehRecuperacao) {
            $this->assertSomaValores($turId, $disId, $uniId, $tipo, $valor, null);
        }

        $avaliacao = DiarioAvaliacao::create([
            'ava_esc_id'         => $data['ava_esc_id'],
            'ava_anl_id'         => $data['ava_anl_id'],
            'ava_tur_id'         => $turId,
            'ava_dis_id'         => $disId,
            'ava_uni_id'         => $uniId,
            'ava_iav_id'         => $data['ava_iav_id'],
            'ava_tipo'           => $tipo,
            'ava_descricao'      => $data['ava_descricao'] ?? null,
            'ava_dt'             => $data['ava_dt'],
            'ava_valor'          => $valor,
            'ava_fl_recuperacao' => $ehRecuperacao,
            'ava_user_id'        => (int) $request->user()->id,
        ]);

        return response()->json(['ok' => true, 'ava_id' => (int) $avaliacao->ava_id], 201);
    }

    /** Edita uma avaliação. O TIPO é imutável (espelha o web). */
    public function updateAvaliacao(Request $request, DiarioAvaliacao $avaliacao): JsonResponse
    {
        $data = $this->validarAvaliacao($request);

        $turId = (int) $avaliacao->ava_tur_id;
        $disId = (int) $avaliacao->ava_dis_id;
        $uniId = (int) $avaliacao->ava_uni_id;
        $tipo  = $avaliacao->ava_tipo; // ignora o do payload, como no web
        $funId = (int) $request->user()->fun_id;

        Guard::assertRegenciaDisciplina($funId, $turId, $disId);
        Guard::assertTurmaAberta($turId);
        Guard::assertPeriodoAberto($uniId);

        $ehRecuperacao = $this->instrumentoRecuperacao((int) $data['ava_iav_id']);
        $valorInput = RegrasParametrizacaoAvaliacao::aplicar(
            $uniId, (int) $data['ava_iav_id'], $data['ava_valor'] ?? null,
            $request->user()->isAdmin(), $avaliacao->ava_iav_id ? (int) $avaliacao->ava_iav_id : null,
        );
        $valor = $this->resolverValor($tipo, $uniId, $valorInput);

        if ($valor !== null && ! $ehRecuperacao) {
            $this->assertSomaValores($turId, $disId, $uniId, $tipo, $valor, (int) $avaliacao->ava_id);
        }

        $avaliacao->update([
            'ava_iav_id'         => $data['ava_iav_id'],
            'ava_descricao'      => $data['ava_descricao'] ?? null,
            'ava_dt'             => $data['ava_dt'],
            'ava_valor'          => $valor,
            'ava_fl_recuperacao' => $ehRecuperacao,
        ]);

        $this->invalidarMediaManual($turId, $disId, $uniId, null);

        return response()->json(['ok' => true]);
    }

    /** Exclui a avaliação e, em transação, as notas dela. */
    public function destroyAvaliacao(Request $request, DiarioAvaliacao $avaliacao): JsonResponse
    {
        $turId = (int) $avaliacao->ava_tur_id;
        $disId = (int) $avaliacao->ava_dis_id;
        $uniId = (int) $avaliacao->ava_uni_id;
        $funId = (int) $request->user()->fun_id;

        Guard::assertRegenciaDisciplina($funId, $turId, $disId);
        Guard::assertTurmaAberta($turId);
        Guard::assertPeriodoAberto($uniId);

        DB::transaction(function () use ($avaliacao, $turId, $disId, $uniId) {
            DiarioNota::where('nta_ava_id', $avaliacao->ava_id)->delete();
            $avaliacao->delete();
            $this->invalidarMediaManual($turId, $disId, $uniId, null);
        });

        return response()->json(['ok' => true]);
    }

    /** Autosave de UMA célula (aluno × avaliação). */
    public function salvarNota(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nta_ava_id' => ['required', 'integer', 'exists:edu_diario_avaliacao,ava_id'],
            'nta_aln_id' => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'nta_valor'  => ['nullable', 'numeric', 'min:0'],
            'nta_cnc_id' => ['nullable', 'integer', 'exists:cfg_conceito,cnc_id'],
        ]);

        $avaliacao = DiarioAvaliacao::findOrFail($data['nta_ava_id']);
        $turId = (int) $avaliacao->ava_tur_id;
        $disId = (int) $avaliacao->ava_dis_id;
        $uniId = (int) $avaliacao->ava_uni_id;
        $alnId = (int) $data['nta_aln_id'];
        $funId = (int) $request->user()->fun_id;

        Guard::assertRegenciaDisciplina($funId, $turId, $disId);
        Guard::assertTurmaAberta($turId);
        Guard::assertPeriodoAberto($uniId);

        if ($avaliacao->ava_dt && Carbon::parse($avaliacao->ava_dt)->startOfDay()->gt(now()->startOfDay())) {
            abort(422, 'Não é possível lançar notas em uma avaliação com data futura.');
        }

        $this->assertSemSaidaNaData($turId, $alnId, Carbon::parse($avaliacao->ava_dt)->toDateString());
        $this->assertSemOutroTipo($turId, $disId, $alnId, $avaliacao->ava_tipo);

        $usaConceito = $avaliacao->ava_tipo === DiarioAvaliacao::TIPO_CONCEITUAL
            && $this->conceitoModo($uniId) === 'conceito';

        if ($usaConceito) {
            $valores = ['nta_cnc_id' => $data['nta_cnc_id'] ?? null, 'nta_valor' => null];
        } else {
            // `nta_valor` pode não vir na chave — sempre normalizar (o web quebra aqui).
            $valor = $data['nta_valor'] ?? null;
            if ($valor !== null && $avaliacao->ava_valor !== null && (float) $valor > (float) $avaliacao->ava_valor) {
                throw ValidationException::withMessages([
                    'nta_valor' => 'O máximo desta avaliação é ' . (float) $avaliacao->ava_valor . '.',
                ]);
            }
            $valores = ['nta_valor' => $valor, 'nta_cnc_id' => null];
        }

        $nota = DiarioNota::updateOrCreate(
            ['nta_ava_id' => $avaliacao->ava_id, 'nta_aln_id' => $alnId],
            $valores,
        );

        // Mexer na nota derruba o override manual da secretaria daquele aluno.
        $this->invalidarMediaManual($turId, $disId, $uniId, $alnId);

        return response()->json([
            'ok'          => true,
            'nta_id'      => (int) $nota->nta_id,
            // Devolve o resultado recalculado para o app não reimplementar a regra.
            'resultados'  => CalculoNota::resultado($turId, $disId, $uniId),
        ]);
    }

    // ============ Helpers ============

    private function validarAvaliacao(Request $request): array
    {
        return $request->validate([
            'ava_esc_id'    => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'ava_anl_id'    => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'ava_tur_id'    => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'ava_dis_id'    => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'ava_uni_id'    => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'ava_iav_id'    => ['required', 'integer', 'exists:edu_instrumento_avaliativo,iav_id'],
            'ava_tipo'      => ['required', 'string', 'in:numerica,conceitual'],
            'ava_descricao' => ['nullable', 'string', 'max:150'],
            'ava_dt'        => ['required', 'date'],
            'ava_valor'     => ['nullable', 'numeric', 'min:0.01', 'max:10'],
        ]);
    }

    /** Tipos habilitados pela SÉRIE da turma (json em edu_serie.ser_tipo_avaliacao). */
    private function tiposSerie(int $turId): array
    {
        $bruto = DB::table('edu_turma as t')
            ->join('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->where('t.tur_id', $turId)
            ->value(DB::raw('s.ser_tipo_avaliacao::text'));

        $tipos = json_decode((string) $bruto, true);

        return is_array($tipos) ? $tipos : [];
    }

    private function assertTipoSerie(int $turId, string $tipo): void
    {
        abort_unless(
            in_array($tipo, $this->tiposSerie($turId), true),
            422,
            'Esta série não possui o tipo de avaliação selecionado configurado.',
        );
    }

    /** Modo do conceito vem do ano da UNIDADE — não da turma. */
    private function conceitoModo(int $uniId): string
    {
        $modo = DB::table('cfg_unidade as u')
            ->join('cfg_ano_letivo as a', 'a.anl_id', '=', 'u.uni_anl_id')
            ->where('u.uni_id', $uniId)
            ->value('a.anl_conceito_modo');

        return $modo === 'conceito' ? 'conceito' : 'faixa';
    }

    private function instrumentoRecuperacao(int $iavId): bool
    {
        $i = InstrumentoAvaliativo::find($iavId);
        abort_unless($i && $i->iav_fl_ativo, 422, 'Instrumento avaliativo inválido ou inativo.');

        return (bool) $i->iav_fl_recuperacao;
    }

    /**
     * Numérica sempre usa valor; conceitual só no modo 'faixa'. No modo
     * 'conceito' o valor é forçado a NULL (e a soma ≤ 10 não se aplica).
     */
    private function resolverValor(string $tipo, int $uniId, $valor): ?float
    {
        $usaValor = $tipo === DiarioAvaliacao::TIPO_NUMERICA
            || ($tipo === DiarioAvaliacao::TIPO_CONCEITUAL && $this->conceitoModo($uniId) === 'faixa');

        if (! $usaValor) {
            return null;
        }

        abort_if($valor === null || $valor === '' || (float) $valor <= 0, 422, 'Informe o valor da avaliação.');

        return (float) $valor;
    }

    private function somaRegulares(int $turId, int $disId, int $uniId, string $tipo, ?int $excetoAvaId = null): float
    {
        return (float) DiarioAvaliacao::query()
            ->where('ava_tur_id', $turId)
            ->where('ava_dis_id', $disId)
            ->where('ava_uni_id', $uniId)
            ->where('ava_tipo', $tipo)
            ->where('ava_fl_recuperacao', false)
            ->when($excetoAvaId, fn ($q) => $q->where('ava_id', '!=', $excetoAvaId))
            ->sum('ava_valor');
    }

    /** Soma das regulares ≤ 10 — mensagem idêntica à do diário web. */
    private function assertSomaValores(int $turId, int $disId, int $uniId, string $tipo, float $novo, ?int $excetoAvaId): void
    {
        $soma = $this->somaRegulares($turId, $disId, $uniId, $tipo, $excetoAvaId);

        if (round($soma + $novo, 2) > 10) {
            throw ValidationException::withMessages([
                'ava_valor' => 'A soma dos valores das avaliações não pode passar de 10. Disponível: ' . round(10 - $soma, 2) . '.',
            ]);
        }
    }

    /** Consulta base da exclusão mútua numérica × conceitual. */
    private function queryNotasDeOutroTipo(int $turId, int $disId, string $tipo)
    {
        $outro = $tipo === DiarioAvaliacao::TIPO_NUMERICA
            ? DiarioAvaliacao::TIPO_CONCEITUAL
            : DiarioAvaliacao::TIPO_NUMERICA;

        return DiarioNota::query()
            ->join('edu_diario_avaliacao as a', 'a.ava_id', '=', 'edu_diario_nota.nta_ava_id')
            ->where('a.ava_tur_id', $turId)
            ->where('a.ava_dis_id', $disId)
            ->where('a.ava_tipo', $outro)
            ->whereNull('a.ava_deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('edu_diario_nota.nta_valor')
                    ->orWhereNotNull('edu_diario_nota.nta_cnc_id');
            });
    }

    /** @return array<int,bool> aln_id => true, em UMA query. */
    private function alunosComNotaDeOutroTipo(int $turId, int $disId, string $tipo): array
    {
        return array_flip(
            $this->queryNotasDeOutroTipo($turId, $disId, $tipo)
                ->distinct()
                ->pluck('edu_diario_nota.nta_aln_id')
                ->map(fn ($v) => (int) $v)
                ->all(),
        );
    }

    private function temNotaDeOutroTipo(int $turId, int $disId, int $alnId, string $tipo): bool
    {
        return $this->queryNotasDeOutroTipo($turId, $disId, $tipo)
            ->where('edu_diario_nota.nta_aln_id', $alnId)
            ->exists();
    }

    private function assertSemOutroTipo(int $turId, int $disId, int $alnId, string $tipo): void
    {
        if (! $this->temNotaDeOutroTipo($turId, $disId, $alnId, $tipo)) {
            return;
        }

        $atual = $tipo === DiarioAvaliacao::TIPO_NUMERICA ? 'numérica' : 'conceitual';
        $outro = $tipo === DiarioAvaliacao::TIPO_NUMERICA ? 'conceitual' : 'numérica';

        throw ValidationException::withMessages([
            'nta_valor' => "Aluno já possui notas como {$outro} nesta matéria. Remova-as para lançar como {$atual}.",
        ]);
    }

    /**
     * Zera o override manual da secretaria preservando as faltas; se sobrar
     * vazio, remove a linha. Sem $alnId, invalida o contexto inteiro.
     */
    private function invalidarMediaManual(int $turId, int $disId, int $uniId, ?int $alnId): void
    {
        // Pelo MODEL, não por DB::table: NotaManual é Auditable, e um update
        // direto na tabela apagaria o override da secretaria sem deixar rastro
        // em activity_log — a secretaria não saberia por que a média sumiu.
        NotaManual::query()
            ->where('nmn_tur_id', $turId)
            ->where('nmn_dis_id', $disId)
            ->where('nmn_uni_id', $uniId)
            ->when($alnId, fn ($x) => $x->where('nmn_aln_id', $alnId))
            ->get()
            ->each(function (NotaManual $m) {
                $m->update(['nmn_media' => null, 'nmn_cnc_id' => null]);
                // Sem faltas, a linha não guarda mais nada — remove.
                if ($m->nmn_faltas === null) {
                    $m->delete();
                }
            });
    }

    /** Ativos + quem saiu (menos remanejados), ordenados por nome. */
    private function alunosDaTurma(int $turId)
    {
        return Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where(function ($q) {
                $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                    ->orWhere(fn ($s) => $s->whereNotNull('tma_tas_cod_saida')->where('tma_tas_cod_saida', '!=', Matricula::TAS_REMANEJADO));
            })
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->unique(fn ($m) => $m->aluno->aln_id)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values();
    }
}
