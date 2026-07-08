<?php

namespace Database\Seeders;

use App\Models\Parametro\Tela;
use App\Models\Parametro\TelaPermissao;
use App\Support\PermissaoTela;
use Illuminate\Database\Seeder;

class TelaPermissaoSeeder extends Seeder
{
    /**
     * Ordem das roles nas linhas da matriz abaixo.
     */
    private const ROLES = [
        'admin',
        'gestor_secretaria',
        'professor',
        'professor_aee',
        'coordenador',
        'coordenador_interno',
        'diretor',
        'secretaria_escola',
        'pedagogico',
        'monitor',
        'matricula_censo',
    ];

    /**
     * Matriz de permissões por tela.
     * [rota, nome, grupo, ordem, [níveis na ordem de self::ROLES]]
     * Nível: 0 = sem acesso, 1 = ver+editar, 2 = só visualizar.
     */
    private const MATRIZ = [
        ['/dashboard', 'Painel', 'Visão geral', 1, [2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2]],

        ['/alunos', 'Alunos', 'Gestão', 2, [1, 1, 0, 0, 2, 2, 1, 1, 2, 0, 2]],
        ['/funcionarios', 'Funcionários', 'Gestão', 3, [1, 1, 0, 0, 2, 2, 1, 1, 2, 0, 2]],
        ['/segmentos', 'Segmentos', 'Gestão', 4, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/series', 'Séries', 'Gestão', 5, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/disciplinas', 'Disciplinas', 'Gestão', 6, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/grade-disciplinar', 'Grade Disciplinar', 'Gestão', 7, [1, 1, 0, 0, 2, 0, 0, 0, 0, 0, 0]],
        ['/escolas', 'Escolas', 'Gestão', 8, [1, 1, 0, 0, 0, 2, 0, 0, 2, 0, 2]],
        ['/turmas', 'Turmas', 'Gestão', 9, [1, 1, 0, 0, 0, 2, 2, 2, 2, 0, 2]],
        ['/turmas-aee', 'Turmas AEE', 'Gestão', 10, [1, 1, 0, 0, 0, 2, 2, 2, 2, 0, 2]],
        ['/turmas-atividade', 'Turmas Atividades', 'Gestão', 11, [1, 1, 0, 0, 0, 2, 2, 2, 2, 0, 0]],
        ['/atendimentos-aee', 'Atendimentos AEE', 'Gestão', 12, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/atividades', 'Atividades', 'Gestão', 13, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],

        ['/matriculas', 'Matrículas', 'Matrículas', 14, [1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 1]],
        ['/matriculas-aee', 'Matrículas AEE', 'Matrículas', 15, [1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0]],
        ['/movimentacoes', 'Movimentar Aluno', 'Matrículas', 16, [1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0]],
        ['/remanejamento-turmas', 'Troca entre Turmas', 'Matrículas', 17, [1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0]],
        ['/matriculas/segunda-via', '2ª Via Comprovante', 'Matrículas', 18, [1, 1, 0, 0, 0, 0, 2, 2, 0, 0, 2]],

        ['/diario', 'Diário de Classe', 'Diário', 19, [1, 2, 1, 0, 0, 0, 0, 2, 0, 0, 0]],
        ['/diario-aee', 'Diário AEE', 'Diário', 20, [1, 1, 0, 1, 0, 0, 0, 2, 0, 0, 0]],
        ['/diario-atividade', 'Diário Atividade', 'Diário', 21, [1, 1, 0, 0, 0, 0, 0, 2, 0, 1, 0]],
        ['/diario/instrumentos-avaliativos', 'Instrumentos Avaliativos', 'Diário', 22, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/sabados-letivos', 'Sábados Letivos', 'Diário', 23, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/diario/planos', 'Planos de Aula', 'Diário', 24, [1, 2, 1, 0, 2, 2, 0, 0, 2, 0, 0]],
        ['/diario/planos-aee', 'Planos de Aula AEE', 'Diário', 25, [1, 2, 0, 1, 0, 2, 0, 0, 2, 0, 0]],
        ['/relatorios-diario', 'Relatórios Diário', 'Diário', 26, [1, 1, 2, 2, 2, 2, 0, 2, 2, 0, 0]],

        ['/coordenador/planos', 'Validação de Planos', 'Pedagógico', 27, [1, 2, 0, 0, 1, 1, 0, 0, 1, 0, 0]],
        ['/coordenador-interno/planos-aee', 'Validação de Planos AEE', 'Pedagógico', 28, [1, 2, 0, 0, 0, 1, 0, 0, 1, 0, 0]],
        ['/relatorios-pedagogico', 'Relatórios Pedagógico', 'Pedagógico', 29, [1, 1, 0, 0, 2, 2, 0, 0, 2, 0, 0]],

        ['/secretaria/acessos-professores', 'Acessos de Professores', 'Secretaria Escolar', 30, [1, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0]],
        ['/secretaria/lancamento-manual', 'Notas e Faltas', 'Secretaria Escolar', 31, [1, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0]],
        ['/secretaria/justificativas-falta', 'Justificativa de Falta', 'Secretaria Escolar', 32, [1, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0]],
        ['/secretaria/renovacao-matricula', 'Renovação de Matrícula', 'Secretaria Escolar', 33, [1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0]],
        ['/secretaria/motivos-baixa-frequencia', 'Motivo Baixa Frequência', 'Secretaria Escolar', 34, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],

        ['/encerramento-turmas', 'Encerramento de Turmas', 'Ano Letivo', 35, [1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0]],
        ['/duplicar-turmas', 'Duplicar Turmas', 'Ano Letivo', 36, [1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0]],
        ['/confirmacao-renovacao', 'Confirmação de Renovação', 'Ano Letivo', 37, [1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0]],
        ['/relatorios-encerramento', 'Relatórios Encerramento', 'Ano Letivo', 38, [1, 1, 0, 0, 0, 2, 2, 2, 2, 0, 0]],

        ['/relatorios', 'Central de Relatórios', 'Relatórios', 39, [1, 1, 0, 0, 0, 2, 2, 2, 2, 0, 0]],
        ['/relatorios-escola', 'Relatórios Gerais', 'Relatórios', 40, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],

        ['/users', 'Usuários', 'Sistema', 41, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/parametros', 'Parâmetros', 'Sistema', 42, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/permissoes', 'Permissões de Telas', 'Sistema', 43, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/auditoria', 'Auditoria', 'Sistema', 44, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/censo/importacao', 'Importar Censo', 'Sistema', 45, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 1]],
    ];

    public function run(): void
    {
        foreach (self::MATRIZ as [$rota, $nome, $grupo, $ordem, $niveis]) {
            // Metadados da tela são sempre atualizados (nome/grupo/ordem).
            $tela = Tela::updateOrCreate(
                ['tla_rota' => $rota],
                ['tla_nome' => $nome, 'tla_grupo' => $grupo, 'tla_ordem' => $ordem],
            );

            // Níveis: só na criação. NÃO sobrescreve edições feitas pela tela de
            // Permissões — o banco é a fonte de verdade após o seed inicial.
            foreach (self::ROLES as $i => $role) {
                TelaPermissao::firstOrCreate(
                    ['tlp_tla_id' => $tela->tla_id, 'tlp_role' => $role],
                    ['tlp_nivel' => $niveis[$i]],
                );
            }
        }

        PermissaoTela::clearCache();
    }
}
