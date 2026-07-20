<?php

namespace App\Http\Controllers\Api\V1\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\AeeAvaliacao;
use App\Models\Matricula\Matricula;
use App\Support\Diario\DiarioAeeGuard as Guard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Avaliação descritiva do AEE — API v1 (app do professor).
 *
 * Espelha App\Http\Controllers\Diario\AeeAvaliacaoController.
 *
 * O modelo de dados é um HISTÓRICO, não um campo: são N registros DATADOS por
 * aluno dentro da unidade (diferente do parecer do diário regular, que é um
 * texto único por aluno/unidade). Por isso o contrato expõe duas listas
 * independentes — `alunos` e `avaliacoes` — e não um texto embutido no aluno.
 *
 * ÚNICA adaptação em relação ao web: o FORMATO do texto. O web usa editor rico
 * e grava HTML (renderizado com v-html); o app manda e recebe TEXTO PURO. A
 * conversão acontece nas duas pontas — ver `paraTextoPuro()` na leitura e o
 * `nl2br(e(...))` na gravação. Nenhuma REGRA muda: onde o web é permissivo,
 * esta API é permissiva do mesmo jeito, e cada uma dessas brechas está
 * comentada como paridade deliberada no ponto onde ela apareceria.
 *
 * SEM BYPASS DE ADMIN e sem checagem de role: a rota já está gateada por
 * `app.access:professor_aee` (ver routes/api.php).
 */
class AeeAvaliacoesController extends Controller
{
    /**
     * Limite REAL cobrado pelo web — 2500 caracteres de TEXTO, não de HTML.
     * A validação `max:20000` do payload é só uma barreira de tamanho bruto.
     */
    private const MAX_TEXTO = 2500;

    /** Contexto da tela: alunos da turma + todo o histórico da unidade. */
    public function contexto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id' => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
        ]);

        $turId = (int) $data['tur_id'];
        $uniId = (int) $data['uni_id'];

        // Leitura exige apenas o vínculo: turma/período fechados ainda podem ser
        // CONSULTADOS — só o lançamento é travado (igual ao web).
        Guard::assertTurmaAee($turId);
        Guard::assertRegenciaAee((int) $request->user()->fun_id, $turId);

        $userId = (int) $request->user()->id;

        $avaliacoes = AeeAvaliacao::query()
            ->where('dav_tur_id', $turId)
            ->where('dav_uni_id', $uniId)
            ->orderByDesc('dav_dt')
            ->orderByDesc('dav_id')
            ->get(['dav_id', 'dav_user_id', 'dav_aln_id', 'dav_dt', 'dav_descricao'])
            ->map(fn ($a) => [
                'dav_id' => (int) $a->dav_id,
                'aln_id' => (int) $a->dav_aln_id,
                'dt'     => Carbon::parse($a->dav_dt)->toDateString(),
                'texto'  => $this->paraTextoPuro($a->dav_descricao),
                // INFORMATIVO para a UI (marcar "seu registro"), NUNCA trava:
                // o web deixa qualquer professor da turma editar e excluir o
                // registro de outro, e a v1 replica isso — ver salvar/destroy.
                'minha'  => (int) $a->dav_user_id === $userId,
            ]);

        // Contagem por aluno derivada da lista já carregada — a alternativa
        // (um count por aluno) seria N+1 puro para o mesmo resultado.
        $totais = $avaliacoes->countBy('aln_id');

        // Mesma seleção/ordenação do web: ativos + quem saiu sem ser remanejado,
        // deduplicado por aluno e ordenado por nome sem acento/caixa.
        $alunos = Matricula::query()
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
            ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values()
            ->map(fn ($m) => [
                'aln_id'           => (int) $m->aluno->aln_id,
                'aln_nome'         => $m->aluno->aln_nome,
                'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                'total'            => (int) ($totais[(int) $m->aluno->aln_id] ?? 0),
            ]);

        return response()->json([
            'periodo_aberto' => Guard::periodoAberto($uniId),
            'turma_aberta'   => Guard::turmaAberta($turId),
            'limite'         => self::MAX_TEXTO,
            'alunos'         => $alunos,
            'avaliacoes'     => $avaliacoes->values(),
        ]);
    }

    /** Upsert: com `dav_id` edita o registro, sem `dav_id` cria um novo. */
    public function salvar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dav_id' => ['nullable', 'integer', 'exists:edu_diario_aee_avaliacao,dav_id'],
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id' => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'aln_id' => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'dt'     => ['required', 'date'],
            'texto'  => ['required', 'string', 'max:20000'],
        ], [], [
            'aln_id' => 'aluno',
            'dt'     => 'data',
            'texto'  => 'descrição',
        ]);

        // O cliente já manda texto puro: aqui basta o trim. O web chega ao mesmo
        // valor tirando as tags do HTML do editor antes de medir.
        $texto = trim($data['texto']);

        // Mensagens LITERAIS do web; só a chave muda ('texto' em vez de
        // 'descricao'), acompanhando o nome do campo no contrato do app.
        if ($texto === '') {
            throw ValidationException::withMessages(['texto' => 'A descrição é obrigatória.']);
        }
        if (mb_strlen($texto) > self::MAX_TEXTO) {
            throw ValidationException::withMessages(['texto' => 'A descrição excede o limite de ' . self::MAX_TEXTO . ' caracteres.']);
        }

        $turId = (int) $data['tur_id'];
        $uniId = (int) $data['uni_id'];

        Guard::assertTurmaAee($turId);
        Guard::assertRegenciaAee((int) $request->user()->fun_id, $turId);
        Guard::assertTurmaAberta($turId);
        Guard::assertPeriodoAberto($uniId);
        Guard::assertDataNoPeriodo($uniId, $data['dt']);

        // PARIDADE DELIBERADA (1/3): o web NÃO verifica que `aln_id` pertence à
        // turma — qualquer aluno existente é aceito. Não endurecer aqui.
        //
        // PARIDADE DELIBERADA (2/3): o web também NÃO aplica a trava de saída do
        // aluno (a data pode ser posterior ao `tma_dt_saida`), ao contrário da
        // frequência e do parecer. Mantido igual.
        //
        // A data também NÃO passa por assertDiaDeAtendimento: avaliação
        // descritiva não é atendimento, qualquer data do período serve.

        $turma = DB::table('edu_turma')->where('tur_id', $turId)->first(['tur_esc_id', 'tur_anl_id']);

        // Texto puro → HTML mínimo. `e()` escapa antes do nl2br, então o que o
        // professor digitar não vira marcação: o web renderiza com v-html e
        // continuaria exibindo as quebras de linha corretamente, sem injeção.
        $descricao = nl2br(e($texto));

        if (! empty($data['dav_id'])) {
            // PARIDADE DELIBERADA (3/3): o filtro é `dav_id` + `dav_tur_id`, sem
            // comparar `dav_user_id` — no web um professor da turma edita o
            // registro escrito por outro, e a v1 faz o mesmo.
            $av = AeeAvaliacao::where('dav_id', $data['dav_id'])
                ->where('dav_tur_id', $turId)
                ->firstOrFail();

            // Escola/ano/turma não são reescritos na edição — igual ao web.
            $av->update([
                'dav_aln_id'    => (int) $data['aln_id'],
                'dav_uni_id'    => $uniId,
                'dav_dt'        => $data['dt'],
                'dav_descricao' => $descricao,
            ]);
        } else {
            $av = AeeAvaliacao::create([
                'dav_user_id'   => (int) $request->user()->id,
                'dav_esc_id'    => $turma->tur_esc_id,
                'dav_anl_id'    => $turma->tur_anl_id,
                'dav_tur_id'    => $turId,
                'dav_uni_id'    => $uniId,
                'dav_aln_id'    => (int) $data['aln_id'],
                'dav_dt'        => $data['dt'],
                'dav_descricao' => $descricao,
            ]);
        }

        // O web devolve só `ok`; o app precisa do id para não duplicar o registro
        // no próximo salvamento do mesmo formulário.
        return response()->json(['ok' => true, 'dav_id' => (int) $av->dav_id]);
    }

    /** Exclusão (soft delete) de um registro do histórico. */
    public function destroy(Request $request, AeeAvaliacao $avaliacao): JsonResponse
    {
        $turId = (int) $avaliacao->dav_tur_id;

        Guard::assertTurmaAee($turId);
        Guard::assertRegenciaAee((int) $request->user()->fun_id, $turId);
        Guard::assertTurmaAberta($turId);
        Guard::assertPeriodoAberto((int) $avaliacao->dav_uni_id);

        // Paridade: sem checagem de autoria. Atender a turma basta para excluir
        // o registro de qualquer colega — é exatamente o que o web permite.
        $avaliacao->delete();

        return response()->json(['ok' => true]);
    }

    // ============ Helpers ============

    /**
     * HTML gravado (editor rico do web ou `nl2br` desta API) → texto puro legível.
     * O app não renderiza HTML: exibir a marcação crua seria ilegível.
     */
    private function paraTextoPuro(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        // O `<br />` do nl2br vem colado no "\n" original; consumir os dois de
        // uma vez evita que cada quebra do professor volte dobrada para o app.
        $texto = preg_replace('/<br\s*\/?>\r?\n?/i', "\n", $html);
        $texto = preg_replace('/<\/p\s*>/i', "\n", (string) $texto);
        // Sem isto, uma lista escrita no editor rico do web (<li>a</li><li>b</li>)
        // chegaria ao app como "ab", sem separação entre os itens.
        $texto = preg_replace('/<\/li\s*>/i', "\n", (string) $texto);

        // strip_tags ANTES do decode: decodificando primeiro, um "&lt;" digitado
        // pelo professor viraria "<" e o strip_tags comeria o texto seguinte.
        $texto = html_entity_decode(strip_tags((string) $texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($texto);
    }
}
