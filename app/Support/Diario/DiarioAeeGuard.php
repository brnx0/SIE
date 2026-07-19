<?php

namespace App\Support\Diario;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Travas de escrita do diário AEE, para a API v1 do app.
 *
 * POR QUE NÃO REUSAR O DiarioEscritaGuard: o AEE do web não usa aquele guard —
 * AeeFrequenciaController e AeeAvaliacaoController têm cópias privadas das
 * travas com mensagens PRÓPRIAS, mais curtas. Como a decisão do projeto é
 * paridade estrita com o web, as mensagens aqui são as do AEE, copiadas
 * literalmente de app/Http/Controllers/Diario/AeeFrequenciaController.php.
 * Repetir a lógica é o preço de repetir o texto.
 *
 * O que é genuinamente diferente do diário regular:
 *  - o vínculo do professor é a regência SEM disciplina (`tup_dis_id IS NULL`);
 *  - a data lançável não vem do quadro de horário, e sim dos dias da semana da
 *    turma (`tur_dias_funcionamento`) cruzados com a janela do período;
 *  - não existe sábado letivo nem aula não executada.
 *
 * SEM BYPASS DE ADMIN: o web libera `isGestorGlobal()`, mas o app é do
 * professor — mesma política já adotada no diário regular da v1.
 */
final class DiarioAeeGuard
{
    /** Índice do dia da semana → código gravado em `tur_dias_funcionamento`. */
    private const DOW = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];

    /** A turma precisa ser de AEE — 422 igual ao web. */
    public static function assertTurmaAee(int $turId): void
    {
        $ehAee = DB::table('edu_turma')->where('tur_id', $turId)->value('tur_modalidade') === 'AEE';

        abort_unless($ehAee, 422, 'Turma não é de AEE.');
    }

    /**
     * Regência AEE: linha em `edu_turma_professor` com `tup_dis_id` NULO.
     * É isso que distingue o vínculo de AEE do vínculo por disciplina.
     */
    public static function assertRegenciaAee(int $funId, int $turId): void
    {
        $tem = $funId > 0 && DB::table('edu_turma_professor')
            ->where('tup_tur_id', $turId)
            ->where('tup_fun_id', $funId)
            ->whereNull('tup_dis_id')
            ->whereNull('tup_deleted_at')
            ->exists();

        abort_unless($tem, 403, 'Você não atende esta turma de AEE.');
    }

    public static function turmaAberta(int $turId): bool
    {
        return DB::table('edu_turma')->where('tur_id', $turId)->value('tur_situacao') === 'ABERTA';
    }

    public static function assertTurmaAberta(int $turId): void
    {
        abort_unless(self::turmaAberta($turId), 422, 'A turma não está aberta.');
    }

    /** Janela do período JÁ com a extensão somada, ou null se a unidade não tem datas. */
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

    /** HOJE dentro da janela — é o que libera o lançamento, não a data digitada. */
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
        abort_unless(self::periodoAberto($uniId), 422, 'Fora do período de lançamento (inclui a extensão).');
    }

    public static function assertDataNoPeriodo(int $uniId, string $dt): void
    {
        $j = self::janela($uniId);
        abort_unless($j, 422, 'Período inválido.');

        $d = Carbon::parse($dt)->toDateString();

        abort_unless($d >= $j['dt_inicio'] && $d <= $j['dt_fim'], 422, 'A data está fora do período selecionado.');
    }

    /** Dias da semana em que a turma atende. `[]` quando não cadastrado ou JSON inválido. */
    public static function diasDaTurma(int $turId): array
    {
        $raw = DB::table('edu_turma')->where('tur_id', $turId)->value('tur_dias_funcionamento');

        $dias = json_decode((string) $raw, true);

        return is_array($dias) ? array_values($dias) : [];
    }

    /** A data precisa cair num dia de atendimento da turma. */
    public static function assertDiaDeAtendimento(int $turId, string $dt): void
    {
        $dias = self::diasDaTurma($turId);
        $code = self::DOW[(int) Carbon::parse($dt)->dayOfWeek] ?? null;

        abort_unless($code && in_array($code, $dias, true), 422, 'A data não é um dia de atendimento desta turma.');
    }

    /**
     * Expande os dias da semana da turma nas datas concretas dentro da janela.
     *
     * Fica no servidor de propósito: no web esse cruzamento é feito no Vue
     * (AeeFrequenciaPanel), e duplicar a regra no cliente móvel garantiria que
     * as duas cópias divergissem.
     *
     * @return list<array{dt: string, dia: string}>
     */
    public static function datasDeAtendimento(int $turId, array $janela): array
    {
        $dias = self::diasDaTurma($turId);

        if (! $dias) {
            return [];
        }

        $datas = [];
        $cursor = Carbon::parse($janela['dt_inicio']);
        $fim = Carbon::parse($janela['dt_fim']);

        while ($cursor->lte($fim)) {
            $code = self::DOW[(int) $cursor->dayOfWeek] ?? null;

            if ($code && in_array($code, $dias, true)) {
                $datas[] = ['dt' => $cursor->toDateString(), 'dia' => $code];
            }

            $cursor->addDay();
        }

        return $datas;
    }
}
