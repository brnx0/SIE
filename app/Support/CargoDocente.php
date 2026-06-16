<?php

namespace App\Support;

use App\Models\Funcionario\Cargo;

/**
 * Fonte única para detecção de cargo docente (atua em sala de aula).
 *
 * Espelha a regra de FuncionarioLotacaoController::isCargoDocente() e de
 * AdmissaoLotacaoTab.vue. Novo código deve consumir este helper; os dois
 * espelhos legados podem migrar para cá futuramente (CLAUDE.md).
 */
final class CargoDocente
{
    /** Prefixos de nome de cargo considerados docentes. */
    public const PREFIXOS = ['Professor', 'Docente', 'Regente', 'Prof'];

    /** Nomes exatos de cargos que atuam em sala de aula. */
    public const EXATOS = [
        // Inclusão
        'Auxiliar de Desenvolvimento Infantil',
        'Auxiliar de Desenvolvimento Infantil – PNE',
        'Cuidador(a) de Educando com Necessidades Especiais',
        'Tradutor(a) Intérprete de LIBRAS',
        'Mediador(a)',
        'Estimulador(a)',
        'Educação Especial – Trabalho Diferenciado',

        // Educação infantil
        'Educadora de Desenvolvimento Infantil em Creche',
        'Monitor(a) de Creche',
        'Auxiliar de Creche',

        // Apoio pedagógico em sala
        'Assistente de Alfabetização',
        'Auxiliar de Ensino',
        'Auxiliar de Classe',
        'Monitor(a) Docente de Atividades',
        'Monitor(a) de Laboratório',
        'Instrutor(a)',
        'Instrutor(a) de Dança',
        'Instrutor(a) de Fanfarra',
        'Instrutor(a) de Música',
        'Instrutor(a) Profissionalizante',
        'Reforço Escolar',
        'Estagiário(a)',
        'Monitor(a)',
    ];

    /** Verifica se um nome de cargo é docente. */
    public static function matches(?string $nome): bool
    {
        if (! $nome) {
            return false;
        }

        foreach (self::PREFIXOS as $p) {
            if (str_starts_with($nome, $p)) {
                return true;
            }
        }

        return in_array($nome, self::EXATOS, true);
    }

    /**
     * IDs de todos os cargos (ativos e inativos) classificados como docentes.
     *
     * @return array<int>
     */
    public static function ids(): array
    {
        return Cargo::query()
            ->get(['crg_id', 'crg_nome'])
            ->filter(fn (Cargo $c) => self::matches($c->crg_nome))
            ->pluck('crg_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
