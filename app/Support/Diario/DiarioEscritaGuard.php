<?php

namespace App\Support\Diario;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * As 3 travas universais de escrita do Diário, num só lugar.
 *
 *   1. Turma ABERTA
 *   2. Período (unidade) aberto — hoje dentro de [inicio, fim + extensão]
 *   3. Data lançada dentro do período (e aluno sem saída anterior — este último
 *      continua no Controller base, que já o expõe a todos os diários)
 *
 * Hoje a mesma regra está replicada nos controllers web (Falta, Nota,
 * Diagnostico, Aee...). A API v1 consome daqui; a migração dos controllers web
 * para este guard é dívida técnica registrada, e deve ser feita sem alterar
 * comportamento — as mensagens abaixo são idênticas às do FaltaController.
 */
class DiarioEscritaGuard
{
    public static function turmaAberta(int $turId): bool
    {
        return DB::table('edu_turma')->where('tur_id', $turId)->value('tur_situacao') === 'ABERTA';
    }

    public static function assertTurmaAberta(int $turId): void
    {
        abort_unless(
            self::turmaAberta($turId),
            422,
            'A turma não está aberta. O lançamento só é permitido com a turma aberta.',
        );
    }

    /** Janela efetiva do período: fim já com a extensão somada. */
    public static function janela(int $uniId): ?array
    {
        $uni = DB::table('cfg_unidade')
            ->where('uni_id', $uniId)
            ->first(['uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao']);

        if (! $uni || ! $uni->uni_dt_inicio || ! $uni->uni_dt_fim) {
            return null;
        }

        return [
            'dt_inicio' => Carbon::parse($uni->uni_dt_inicio)->toDateString(),
            'dt_fim'    => Carbon::parse($uni->uni_dt_fim)->addDays((int) $uni->uni_dias_extensao)->toDateString(),
        ];
    }

    public static function periodoAberto(int $uniId): bool
    {
        $j = self::janela($uniId);
        if (! $j) {
            return false;
        }
        $hoje = now()->toDateString();

        return $hoje >= $j['dt_inicio'] && $hoje <= $j['dt_fim'];
    }

    public static function assertPeriodoAberto(int $uniId): void
    {
        abort_unless(
            self::periodoAberto($uniId),
            422,
            'Fora do período de lançamento. A edição só é permitida dentro do período selecionado (incluindo a extensão).',
        );
    }

    public static function assertDataNoPeriodo(int $uniId, string $dt): void
    {
        $j = self::janela($uniId);
        abort_unless($j, 422, 'Período inválido.');

        $d = Carbon::parse($dt)->toDateString();
        abort_unless($d >= $j['dt_inicio'] && $d <= $j['dt_fim'], 422, 'A data está fora do período selecionado.');
    }

    /**
     * Tempo do quadro de horário que pertence ao professor E à turma.
     * Diferente do diário web, aqui NÃO há bypass de admin: o app é do
     * professor e escreve sempre em nome da própria lotação.
     */
    public static function slotDoProfessor(int $funId, int $turId, int $trhId): object
    {
        $slot = DB::table('edu_turma_horario')
            ->where('trh_id', $trhId)
            ->where('trh_tur_id', $turId)
            ->whereNull('trh_deleted_at')
            ->first(['trh_id', 'trh_fun_id', 'trh_dis_id', 'trh_dia', 'trh_tempo']);

        abort_unless($slot, 404, 'Tempo não encontrado nesta turma.');
        abort_unless(
            $funId && (int) $slot->trh_fun_id === $funId,
            403,
            'Você não está lotado neste tempo.',
        );

        return $slot;
    }

    /**
     * REGÊNCIA (edu_turma_professor) — é assim que o web autoriza Notas,
     * Diagnóstico e Parecer.
     *
     * Não confundir com o quadro de horário: há escolas com regência lançada e
     * sem horário montado, e nesses casos exigir o horário bloquearia no app um
     * professor que o sistema web deixa lançar.
     */
    public static function assertRegenciaDisciplina(int $funId, int $turId, int $disId): void
    {
        abort_unless($funId, 403, 'Seu usuário não possui vínculo de funcionário.');

        $leciona = DB::table('edu_turma_professor')
            ->where('tup_fun_id', $funId)
            ->where('tup_tur_id', $turId)
            ->where('tup_dis_id', $disId)
            ->whereNull('tup_deleted_at')
            ->exists();

        abort_unless($leciona, 403, 'Você não leciona esta disciplina nesta turma.');
    }

    /**
     * QUADRO DE HORÁRIO — só para o que é por TEMPO (frequência), onde a regra
     * do sistema é mesmo o slot da grade.
     */
    public static function assertDisciplinaDoProfessor(int $funId, int $turId, int $disId): void
    {
        $tem = $funId && DB::table('edu_turma_horario')
            ->where('trh_tur_id', $turId)
            ->where('trh_dis_id', $disId)
            ->where('trh_fun_id', $funId)
            ->whereNull('trh_deleted_at')
            ->exists();

        abort_unless($tem, 403, 'Você não leciona esta disciplina nesta turma.');
    }

    /** Regência em QUALQUER disciplina da turma — para consultas da turma inteira. */
    public static function assertRegenciaTurma(int $funId, int $turId): void
    {
        abort_unless($funId, 403, 'Seu usuário não possui vínculo de funcionário.');

        $tem = DB::table('edu_turma_professor')
            ->where('tup_fun_id', $funId)
            ->where('tup_tur_id', $turId)
            ->whereNull('tup_deleted_at')
            ->exists();

        abort_unless($tem, 403, 'Você não leciona nesta turma.');
    }

    /** O professor precisa estar lotado em algum tempo da turma. */
    public static function assertLotadoNaTurma(int $funId, int $turId): void
    {
        $tem = $funId && DB::table('edu_turma_horario')
            ->where('trh_tur_id', $turId)
            ->where('trh_fun_id', $funId)
            ->whereNull('trh_deleted_at')
            ->exists();

        abort_unless($tem, 403, 'Você não está lotado nesta turma.');
    }
}
