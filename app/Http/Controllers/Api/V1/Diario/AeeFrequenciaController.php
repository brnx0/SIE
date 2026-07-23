<?php

namespace App\Http\Controllers\Api\V1\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\AeeConteudo;
use App\Models\Diario\AeeFrequencia;
use App\Models\Diario\DiarioPlanoAee;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\DiaNaoLetivo;
use App\Support\Diario\DiarioAeeGuard as Guard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Frequência do AEE — API v1 (app do professor).
 *
 * Espelha App\Http\Controllers\Diario\AeeFrequenciaController. A chamada do AEE
 * é por DIA de atendimento (os dias da semana em `tur_dias_funcionamento`), não
 * por tempo de aula: não há disciplina, não há sábado letivo e não existe aula
 * "não executada". Um registro de conteúdo por (turma, data).
 *
 * PARIDADE ESTRITA COM O WEB é a regra deste arquivo. As travas, a ordem delas e
 * as mensagens vêm do DiarioAeeGuard, que copiou os textos do controller web.
 * Onde o web é permissivo, a v1 é permissiva também — os pontos em que isso
 * chama a atenção estão comentados no corpo dos métodos, de propósito, para que
 * ninguém os "conserte" achando que é esquecimento.
 *
 * Duas diferenças deliberadas em relação ao web, já vigentes no resto da v1:
 *
 *  1. SEM BYPASS DE ADMIN e sem modo supervisão. O web libera `isGestorGlobal()`
 *     e deixa perfis de consulta lerem qualquer turma AEE; aqui o acesso é
 *     sempre pela regência do professor logado. Quem supervisiona usa o web.
 *  2. A role já foi checada pelo middleware `app.access:professor_aee` no
 *     routes/api.php — por isso não há `abortIfNotProfessorAee` aqui.
 *
 * A única adaptação permitida é de FORMATO para o cliente móvel: o app manda
 * texto puro onde o web manda HTML de editor rico. Nenhuma regra muda por isso.
 */
class AeeFrequenciaController extends Controller
{
    /** Índice do dia da semana → código gravado em `tur_dias_funcionamento`. */
    private const DOW = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];

    /**
     * Cache por request de (tur_esc_id, tur_anl_id) usado na desnormalização do
     * upsert. O lote grava N alunos da MESMA turma: sem isto, uma consulta
     * idêntica a `edu_turma` por aluno dentro da transação.
     *
     * @var array<int, object>
     */
    private array $turmaDesnorm = [];

    /**
     * Contexto completo de uma (turma AEE, unidade): tudo que a tela de chamada precisa.
     *
     * LEITURA É LIVRE, igual ao web: só valem os guards de pertencimento
     * (turma de AEE + regência). Turma fechada ou período fora da janela NÃO
     * bloqueiam a consulta — o app usa `turma_aberta`/`periodo_aberto` para
     * renderizar a tela em modo somente-leitura.
     */
    public function contexto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id' => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
        ]);

        $turId = (int) $data['tur_id'];
        $uniId = (int) $data['uni_id'];
        $funId = (int) $request->user()->fun_id;

        Guard::assertTurmaAee($turId);
        Guard::assertRegenciaAee($funId, $turId);

        $turma = DB::table('edu_turma as t')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->where('t.tur_id', $turId)
            ->first([
                't.tur_id', 't.tur_nome', 't.tur_aee_sala',
                't.tur_hora_inicio', 't.tur_hora_fim', 'e.esc_nome',
            ]);

        $dias = Guard::diasDaTurma($turId);

        $atendimentos = DB::table('edu_turma_atendimento_aee as ta')
            ->join('cfg_atendimento_aee as a', 'a.ate_id', '=', 'ta.tat_ate_id')
            ->where('ta.tat_tur_id', $turId)
            ->orderBy('a.ate_descricao')
            ->get(['a.ate_id', 'a.ate_descricao'])
            ->map(fn ($a) => ['ate_id' => (int) $a->ate_id, 'ate_descricao' => $a->ate_descricao])
            ->values();

        $periodo = Guard::janela($uniId);

        // Alunos: ativos + os que já saíram. Quem saiu continua na lista porque
        // o professor ainda precisa lançar o que é ANTERIOR à saída; o bloqueio
        // por data é do salvarPresenca, não daqui. Remanejado sai da lista.
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
            // Ordenação por nome sem acento e em minúsculas: o collation do
            // Postgres colocaria "Ávila" depois de "Zuza".
            ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values()
            ->map(fn ($m) => [
                'aln_id'           => (int) $m->aluno->aln_id,
                'aln_nome'         => $m->aluno->aln_nome,
                'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                'dt_saida'         => $m->tma_dt_saida?->toDateString(),
            ]);

        // ARMADILHA: o recorte é por `afr_uni_id`, NÃO pelo intervalo de datas
        // da janela. É assim no web e continua assim aqui — uma linha gravada
        // com a unidade "errada" some da tela em vez de aparecer na unidade a
        // que a data pertence. Paridade deliberada.
        //
        // AUSÊNCIA DE LINHA = PRESENTE. O AEE só materializa o registro depois
        // que o professor toca na célula; a tela assume presença por padrão.
        $presencas = DB::table('edu_diario_aee_frequencia')
            ->where('afr_tur_id', $turId)
            ->where('afr_uni_id', $uniId)
            ->whereNull('afr_deleted_at')
            ->get(['afr_aln_id', 'afr_dt', 'afr_fl_presente'])
            ->map(fn ($r) => [
                'aln_id'   => (int) $r->afr_aln_id,
                'dt'       => Carbon::parse($r->afr_dt)->toDateString(),
                'presente' => (bool) $r->afr_fl_presente,
            ])
            ->values();

        // Conteúdo/metodologia por dia — AEE não tem disciplina, então é 1 por data.
        $conteudos = AeeConteudo::query()
            ->where('dac_tur_id', $turId)
            ->where('dac_uni_id', $uniId)
            ->get(['dac_dt', 'dac_conteudo', 'dac_metodologia', 'dac_fl_plano_executado', 'dac_dae_id'])
            ->map(fn ($r) => [
                'dt'              => Carbon::parse($r->dac_dt)->toDateString(),
                'conteudo'        => $r->dac_conteudo,
                'metodologia'     => $r->dac_metodologia,
                'plano_executado' => (bool) $r->dac_fl_plano_executado,
                'dae_id'          => $r->dac_dae_id !== null ? (int) $r->dac_dae_id : null,
            ])
            ->values();

        // Planos AEE elegíveis da turma — base do "planejamento executado".
        // O web só filtra por autor quando o usuário não é gestor global; como
        // aqui não existe gestor, o filtro por `dae_user_id` é sempre aplicado.
        // O casamento plano↔data é feito no cliente; o servidor manda a janela
        // de cada plano. Nomes traduzidos: objetivo→conteudo, estrategias→metodologia.
        $planos = DiarioPlanoAee::query()
            ->where('dae_tur_id', $turId)
            ->whereIn('dae_status', [DiarioPlanoAee::STATUS_PENDENTE, DiarioPlanoAee::STATUS_APROVADO])
            ->where('dae_user_id', (int) $request->user()->id)
            ->orderBy('dae_dt_inicio')
            ->get(['dae_id', 'dae_dt_inicio', 'dae_dt_fim', 'dae_objetivo', 'dae_estrategias'])
            ->map(fn ($p) => [
                'dae_id'      => (int) $p->dae_id,
                'dt_inicio'   => Carbon::parse($p->dae_dt_inicio)->toDateString(),
                'dt_fim'      => Carbon::parse($p->dae_dt_fim)->toDateString(),
                'conteudo'    => $p->dae_objetivo,
                'metodologia' => $p->dae_estrategias,
            ])
            ->values();

        return response()->json([
            'turma' => [
                'tur_id'       => (int) $turId,
                'tur_nome'     => $turma->tur_nome ?? null,
                'esc_nome'     => $turma->esc_nome ?? null,
                'tur_aee_sala' => $turma->tur_aee_sala ?? null,
                // time(0) do Postgres vem "08:00:00"; o app quer "08:00".
                'hora_inicio'  => $this->hhmm($turma->tur_hora_inicio ?? null),
                'hora_fim'     => $this->hhmm($turma->tur_hora_fim ?? null),
                'dias'         => $dias,
            ],
            'atendimentos'  => $atendimentos,
            'periodo'        => $periodo,
            'periodo_aberto' => Guard::periodoAberto($uniId),
            'turma_aberta'   => Guard::turmaAberta($turId),
            // Turma AEE sem dias cadastrados trava TODO lançamento no
            // assertDiaDeAtendimento. Sinalizar aqui deixa o app explicar o
            // problema (falta cadastro na secretaria) em vez de só colher 422.
            'sem_dias_definidos' => $dias === [],
            // Cruzamento dias-da-semana × janela feito no servidor: no web isso
            // vive no Vue, e duplicar a regra no cliente móvel garantiria que as
            // duas cópias divergissem com o tempo.
            'dias_atendimento'   => $periodo ? Guard::datasDeAtendimento($turId, $periodo) : [],
            'alunos'             => $alunos,
            'presencas'          => $presencas,
            'conteudos'          => $conteudos,
            'planos'             => $planos,
        ]);
    }

    /**
     * Agenda AEE do dia: as turmas do professor que atendem no dia da semana de `dt`.
     *
     * NÃO existe equivalente no web — é a aba "Hoje" do app, espelhando a forma
     * da `agenda` do FrequenciaController regular. Aqui a linha é a TURMA (o AEE
     * não tem tempos de aula), e não há sábado letivo: o AEE atende nos dias
     * cadastrados na turma, ponto.
     */
    public function agenda(Request $request): JsonResponse
    {
        $data = $request->validate(['dt' => ['nullable', 'date']]);
        $dt = Carbon::parse($data['dt'] ?? now())->toDateString();
        $funId = (int) $request->user()->fun_id;

        $dia = self::DOW[(int) Carbon::parse($dt)->dayOfWeek];

        // Regência AEE = vínculo SEM disciplina. É o que distingue o AEE do
        // vínculo por disciplina do diário regular.
        $turmas = $funId === 0 ? collect() : DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->where('tp.tup_fun_id', $funId)
            ->whereNull('tp.tup_dis_id')
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'AEE')
            ->distinct()
            ->orderBy('t.tur_hora_inicio')
            ->orderBy('t.tur_nome')
            ->get([
                't.tur_id', 't.tur_nome', 't.tur_situacao', 't.tur_anl_id',
                't.tur_aee_sala', 't.tur_hora_inicio', 't.tur_hora_fim', 'e.esc_nome',
                DB::raw('t.tur_dias_funcionamento::text as dias'),
            ])
            // Filtro do dia da semana em PHP: a coluna é `json` (não `jsonb`),
            // então o operador de contenção do Postgres não se aplica. O conjunto
            // de turmas AEE de um professor é pequeno — o custo é irrelevante.
            ->filter(function ($t) use ($dia) {
                $lista = json_decode((string) $t->dias, true);

                return is_array($lista) && in_array($dia, $lista, true);
            })
            ->values();

        // Dia não letivo (feriado/recesso) não é dia de atendimento: as turmas
        // cujo ano marca ESTA data como não letiva saem da agenda, sem pendência.
        $anosNaoLetivo = DiaNaoLetivo::anosComDiaNaoLetivo(
            $turmas->pluck('tur_anl_id')->map(fn ($v) => (int) $v)->unique()->all(),
            $dt,
        );
        // Só "é dia não letivo" se havia atendimento e TODO ele caiu por isso.
        $ehDiaNaoLetivo = $turmas->isNotEmpty()
            && $turmas->every(fn ($t) => in_array((int) $t->tur_anl_id, $anosNaoLetivo, true));
        $turmas = $turmas
            ->reject(fn ($t) => in_array((int) $t->tur_anl_id, $anosNaoLetivo, true))
            ->values();

        // Tudo o que o loop consultaria por linha, resolvido em lote (sem N+1).
        $turIds = $turmas->pluck('tur_id')->unique();

        $unidadesPorAno = $turIds->isEmpty() ? collect() : DB::table('cfg_unidade')
            ->whereIn('uni_anl_id', $turmas->pluck('tur_anl_id')->unique())
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_anl_id', 'uni_tipo', 'uni_numero', 'uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao'])
            ->groupBy('uni_anl_id');

        $contagens = $turIds->isEmpty() ? collect() : DB::table('edu_diario_aee_frequencia')
            ->whereIn('afr_tur_id', $turIds)
            ->whereDate('afr_dt', $dt)
            ->whereNull('afr_deleted_at')
            ->groupBy('afr_tur_id')
            ->selectRaw('afr_tur_id, SUM(CASE WHEN afr_fl_presente THEN 1 ELSE 0 END) AS p, SUM(CASE WHEN afr_fl_presente THEN 0 ELSE 1 END) AS f')
            ->get()
            ->keyBy('afr_tur_id');

        $totaisAlunos = $turIds->isEmpty() ? collect() : DB::table('edu_turma_aluno')
            ->whereIn('tma_tur_id', $turIds)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->groupBy('tma_tur_id')
            ->selectRaw('tma_tur_id, COUNT(DISTINCT tma_aln_id) AS total')
            ->pluck('total', 'tma_tur_id');

        $atendimentos = $turIds->isEmpty() ? collect() : DB::table('edu_turma_atendimento_aee as ta')
            ->join('cfg_atendimento_aee as a', 'a.ate_id', '=', 'ta.tat_ate_id')
            ->whereIn('ta.tat_tur_id', $turIds)
            ->orderBy('a.ate_descricao')
            ->get(['ta.tat_tur_id', 'a.ate_descricao'])
            ->groupBy('tat_tur_id');

        $hoje = now()->toDateString();

        $itens = $turmas->map(function ($t) use ($dt, $hoje, $unidadesPorAno, $contagens, $totaisAlunos, $atendimentos) {
            // Unidade que CONTÉM a data (com extensão), dentro do ano da turma.
            $uni = ($unidadesPorAno[$t->tur_anl_id] ?? collect())->first(function ($u) use ($dt) {
                $ini = Carbon::parse($u->uni_dt_inicio)->toDateString();
                $fim = Carbon::parse($u->uni_dt_fim)->addDays((int) $u->uni_dias_extensao)->toDateString();

                return $dt >= $ini && $dt <= $fim;
            });

            $turmaAberta = $t->tur_situacao === 'ABERTA';

            // Mesma regra do Guard::periodoAberto (HOJE dentro da janela, não a
            // data consultada), calculada aqui para não ir ao banco por linha.
            $periodoAberto = false;
            if ($uni) {
                $ini = Carbon::parse($uni->uni_dt_inicio)->toDateString();
                $fim = Carbon::parse($uni->uni_dt_fim)->addDays((int) $uni->uni_dias_extensao)->toDateString();
                $periodoAberto = $hoje >= $ini && $hoje <= $fim;
            }

            $contagem = $contagens[$t->tur_id] ?? null;
            $presentes = (int) ($contagem->p ?? 0);
            $faltas = (int) ($contagem->f ?? 0);

            // NÃO existe 'nao_executada' no AEE — o atendimento acontece ou não
            // acontece, e a ausência de linha basta para chamar de pendente.
            if (! $uni || ! $turmaAberta || ! $periodoAberto) {
                $estado = 'fora_da_janela';
            } elseif ($presentes + $faltas > 0) {
                $estado = 'feita';
            } else {
                $estado = 'pendente';
            }

            return [
                'tur_id'       => (int) $t->tur_id,
                'tur_nome'     => $t->tur_nome,
                // O app precisa do ano para montar o seletor de período na chamada.
                'tur_anl_id'   => (int) $t->tur_anl_id,
                'esc_nome'     => $t->esc_nome,
                'tur_aee_sala' => $t->tur_aee_sala,
                'hora_inicio'  => $this->hhmm($t->tur_hora_inicio),
                'hora_fim'     => $this->hhmm($t->tur_hora_fim),
                'uni_id'       => $uni ? (int) $uni->uni_id : null,
                'uni_label'    => $uni ? $uni->uni_numero . 'º ' . $uni->uni_tipo : null,
                'turma_aberta'   => $turmaAberta,
                'periodo_aberto' => $periodoAberto,
                'estado'         => $estado,
                'presentes'      => $presentes,
                'faltas'         => $faltas,
                'total_alunos'   => (int) ($totaisAlunos[$t->tur_id] ?? 0),
                'atendimentos'   => ($atendimentos[$t->tur_id] ?? collect())
                    ->map(fn ($a) => $a->ate_descricao)
                    ->values(),
            ];
        })->values();

        return response()->json([
            'dt'     => $dt,
            'dia'    => $dia,
            'eh_dia_nao_letivo' => $ehDiaNaoLetivo,
            'turmas' => $itens,
            'resumo' => [
                'total'       => $itens->count(),
                'registradas' => $itens->where('estado', 'feita')->count(),
            ],
        ]);
    }

    /**
     * Presença/ausência de 1 aluno num dia de atendimento (autosave por célula).
     *
     * A ORDEM das travas é a do web e não deve ser reorganizada: ela define qual
     * mensagem o professor vê quando mais de uma condição falha ao mesmo tempo.
     */
    public function salvarPresenca(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'   => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'   => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'aln_id'   => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'dt'       => ['required', 'date'],
            'presente' => ['required', 'boolean'],
        ]);

        $turId = (int) $data['tur_id'];
        $uniId = (int) $data['uni_id'];
        $funId = (int) $request->user()->fun_id;

        Guard::assertTurmaAee($turId);
        Guard::assertRegenciaAee($funId, $turId);
        Guard::assertTurmaAberta($turId);
        Guard::assertPeriodoAberto($uniId);
        Guard::assertDataNoPeriodo($uniId, $data['dt']);
        Guard::assertDiaDeAtendimento($turId, $data['dt']);
        $this->assertSemSaidaNaData($turId, (int) $data['aln_id'], $data['dt']);

        // PARIDADE DELIBERADA: o web não verifica se o aluno tem matrícula NESTA
        // turma — `aln_id` só precisa existir em edu_aluno, e a checagem de saída
        // acima simplesmente não encontra linha para um aluno de fora. A v1
        // replica a brecha em vez de endurecer a regra por conta própria.
        $this->upsert($request, $data, (int) $data['aln_id'], (bool) $data['presente']);

        return response()->json(['ok' => true]);
    }

    /**
     * Marca todos os alunos elegíveis da turma num dia (lote ✓/✕).
     *
     * Mesmas travas do salvarPresenca MENOS a de saída: aqui ela não barra o
     * lançamento, vira FILTRO da lista (quem já tinha saído antes da data fica
     * de fora, em vez de derrubar o lote inteiro com 422).
     */
    public function salvarLote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'   => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'   => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'dt'       => ['required', 'date'],
            'presente' => ['required', 'boolean'],
        ]);

        $turId = (int) $data['tur_id'];
        $uniId = (int) $data['uni_id'];
        $funId = (int) $request->user()->fun_id;

        Guard::assertTurmaAee($turId);
        Guard::assertRegenciaAee($funId, $turId);
        Guard::assertTurmaAberta($turId);
        Guard::assertPeriodoAberto($uniId);
        Guard::assertDataNoPeriodo($uniId, $data['dt']);
        Guard::assertDiaDeAtendimento($turId, $data['dt']);

        // Ativos + os que ainda não tinham saído na data do lote.
        $alnIds = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->whereNull('tma_deleted_at')
            ->where(function ($q) use ($data) {
                $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                    ->orWhere(function ($w) use ($data) {
                        $w->whereNotNull('tma_tas_cod_saida')->whereDate('tma_dt_saida', '>=', $data['dt']);
                    });
            })
            ->pluck('tma_aln_id')
            ->unique();

        DB::transaction(function () use ($alnIds, $request, $data) {
            foreach ($alnIds as $alnId) {
                $this->upsert($request, $data, (int) $alnId, (bool) $data['presente']);
            }
        });

        // `total` é informação a MAIS que o web (que responde só `ok`): o app
        // mostra "N alunos marcados". Não muda regra nenhuma.
        return response()->json(['ok' => true, 'total' => $alnIds->count()]);
    }

    /**
     * Conteúdo/metodologia do dia (turma + data). Se "planejamento executado",
     * espelha (snapshot) o plano AEE elegível que cobre a data.
     *
     * Travas iguais às do salvarPresenca menos a de saída: o conteúdo é da
     * TURMA, não de um aluno.
     */
    public function salvarConteudo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'          => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'          => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'dt'              => ['required', 'date'],
            // Texto puro vindo do app onde o web manda HTML do editor rico —
            // é adaptação de FORMATO, o limite de tamanho é o mesmo do web.
            'conteudo'        => ['nullable', 'string', 'max:5000'],
            'metodologia'     => ['nullable', 'string', 'max:5000'],
            'plano_executado' => ['boolean'],
            'dae_id'          => ['nullable', 'integer'],
        ]);

        $turId = (int) $data['tur_id'];
        $uniId = (int) $data['uni_id'];
        $funId = (int) $request->user()->fun_id;

        Guard::assertTurmaAee($turId);
        Guard::assertRegenciaAee($funId, $turId);
        Guard::assertTurmaAberta($turId);
        Guard::assertPeriodoAberto($uniId);
        Guard::assertDataNoPeriodo($uniId, $data['dt']);
        Guard::assertDiaDeAtendimento($turId, $data['dt']);

        $executado   = (bool) ($data['plano_executado'] ?? false);
        $conteudo    = $data['conteudo'] ?? null;
        $metodologia = $data['metodologia'] ?? null;
        $daeId       = null;

        if ($executado) {
            // Travado: o texto vem do PLANO (snapshot), nunca do cliente. O que
            // o app tenha mandado em conteudo/metodologia é descartado aqui.
            $plano       = $this->planoExecutavel($request, $turId, $data['dt'], isset($data['dae_id']) ? (int) $data['dae_id'] : null);
            $daeId       = (int) $plano->dae_id;
            $conteudo    = $plano->dae_objetivo;
            $metodologia = $plano->dae_estrategias;
        }

        // Chave (turma, data): sem disciplina, o AEE tem 1 conteúdo por dia.
        //
        // PARIDADE DELIBERADA: o upsert não confere quem gravou antes. Se dois
        // professores tiverem regência na mesma turma AEE, o segundo sobrescreve
        // o conteúdo do primeiro — exatamente como no web.
        AeeConteudo::updateOrCreate(
            ['dac_tur_id' => $turId, 'dac_dt' => $data['dt']],
            [
                'dac_user_id'            => (int) $request->user()->id,
                'dac_uni_id'             => $uniId,
                'dac_conteudo'           => $conteudo,
                'dac_metodologia'        => $metodologia,
                'dac_fl_plano_executado' => $executado,
                'dac_dae_id'             => $daeId,
            ],
        );

        return response()->json([
            'ok'              => true,
            'plano_executado' => $executado,
            'dae_id'          => $daeId,
            'conteudo'        => $conteudo,
            'metodologia'     => $metodologia,
        ]);
    }

    // ============ Helpers ============

    /**
     * Grava a presença de 1 aluno. `afr_esc_id`/`afr_anl_id` são COPIADOS da
     * turma (colunas NOT NULL de desnormalização, usadas nos relatórios) —
     * o cliente não os envia.
     */
    private function upsert(Request $request, array $data, int $alnId, bool $presente): void
    {
        $turId = (int) $data['tur_id'];

        // `??=` e não `??`: a turma existe (assertTurmaAee já passou), então o
        // valor cacheado nunca é null e a consulta roda uma vez por request.
        $turma = $this->turmaDesnorm[$turId] ??= DB::table('edu_turma')
            ->where('tur_id', $turId)
            ->first(['tur_esc_id', 'tur_anl_id']);

        AeeFrequencia::updateOrCreate(
            ['afr_tur_id' => $data['tur_id'], 'afr_aln_id' => $alnId, 'afr_dt' => $data['dt']],
            [
                'afr_user_id'     => (int) $request->user()->id,
                'afr_esc_id'      => $turma->tur_esc_id,
                'afr_anl_id'      => $turma->tur_anl_id,
                'afr_uni_id'      => $data['uni_id'],
                'afr_fl_presente' => $presente,
            ],
        );
    }

    /**
     * Plano AEE PRÓPRIO, elegível (pendente/aprovado) e que cobre a data.
     *
     * O web só dispensa o filtro por autor para gestor global; como a v1 não tem
     * gestor, `dae_user_id` é sempre o do professor logado. `dae_id` é opcional:
     * serve para desempatar quando dois planos cobrem a mesma data — sem ele
     * vence o de início mais antigo, igual ao web.
     */
    private function planoExecutavel(Request $request, int $turId, string $dt, ?int $daeId): object
    {
        $d = Carbon::parse($dt)->toDateString();

        $plano = DiarioPlanoAee::query()
            ->where('dae_tur_id', $turId)
            ->whereIn('dae_status', [DiarioPlanoAee::STATUS_PENDENTE, DiarioPlanoAee::STATUS_APROVADO])
            ->whereDate('dae_dt_inicio', '<=', $d)
            ->whereDate('dae_dt_fim', '>=', $d)
            ->where('dae_user_id', (int) $request->user()->id)
            ->when($daeId, fn ($q) => $q->where('dae_id', $daeId))
            ->orderBy('dae_dt_inicio')
            ->first(['dae_id', 'dae_objetivo', 'dae_estrategias']);

        abort_unless($plano, 422, 'Não há planejamento AEE (pendente/aprovado) para esta data.');

        return $plano;
    }

    /** "08:00:00" (time(0) do Postgres) → "08:00", que é o que o app exibe. */
    private function hhmm(?string $hora): ?string
    {
        return $hora ? substr($hora, 0, 5) : null;
    }
}
