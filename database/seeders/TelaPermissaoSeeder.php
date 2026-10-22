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
        'nutricionista',
        'almoxarife',
        // Núcleo de Atendimento entra por ÚLTIMO de propósito: toda linha que não
        // chega no índice 13 cai no `?? 0` do run(), então o perfil nasce sem
        // acesso a nada e só as telas liberadas abaixo recebem o 14º valor (2).
        'nucleo_atendimento',
    ];

    /**
     * Matriz de permissões por tela.
     * [rota, nome, grupo, ordem, [níveis na ordem de self::ROLES]]
     * Nível: 0 = sem acesso, 1 = ver+editar, 2 = só visualizar.
     *
     * Linha mais curta que self::ROLES = as roles do fim ficam sem acesso (0).
     * É o que mantém as telas acadêmicas fechadas para nutricionista/almoxarife
     * sem precisar repetir dois zeros em cada uma das dezenas de linhas.
     */
    private const MATRIZ = [
        ['/dashboard', 'Painel', 'Visão geral', 1, [2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 0, 0, 2]],

        ['/alunos', 'Alunos', 'Gestão', 2, [1, 1, 0, 0, 2, 2, 1, 1, 2, 0, 2, 0, 0, 2]],
        ['/funcionarios', 'Funcionários', 'Gestão', 3, [1, 1, 0, 0, 2, 2, 1, 1, 2, 0, 2]],
        ['/segmentos', 'Segmentos', 'Gestão', 4, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/series', 'Séries', 'Gestão', 5, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/disciplinas', 'Disciplinas', 'Gestão', 6, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/grade-disciplinar', 'Grade Disciplinar', 'Gestão', 7, [1, 1, 0, 0, 2, 0, 0, 0, 0, 0, 0]],
        ['/escolas', 'Escolas', 'Gestão', 8, [1, 1, 0, 0, 0, 2, 0, 0, 2, 0, 2]],
        ['/turmas', 'Turmas', 'Gestão', 9, [1, 1, 0, 0, 0, 2, 2, 2, 2, 0, 2, 0, 0, 2]],
        ['/turmas-aee', 'Turmas AEE', 'Gestão', 10, [1, 1, 0, 0, 0, 2, 2, 2, 2, 0, 2, 0, 0, 2]],
        ['/turmas-atividade', 'Turmas Atividades', 'Gestão', 11, [1, 1, 0, 0, 0, 2, 2, 2, 2, 0, 0, 0, 0, 2]],
        ['/atendimentos-aee', 'Atendimentos AEE', 'Gestão', 12, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/atividades', 'Atividades', 'Gestão', 13, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],

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
        // Nomenclaturas da avaliação diagnóstica (rede inteira): a coordenação pedagógica edita.
        ['/diario/termos-diagnostico', 'Termos da Avaliação Diagnóstica', 'Diário', 23, [1, 1, 0, 0, 1, 0, 0, 0, 0, 0, 0]],
        // Instrumentos e valores das avaliações por unidade (rede inteira): coordenação pedagógica e administração.
        ['/diario/parametrizacao-avaliacoes', 'Parametrização de Avaliações', 'Diário', 23, [1, 1, 0, 0, 1, 0, 0, 0, 0, 0, 0]],
        ['/diario/planos', 'Planos de Aula', 'Diário', 24, [1, 1, 1, 0, 2, 2, 0, 0, 2, 0, 0]],
        ['/diario/planos-aee', 'Planos de Aula AEE', 'Diário', 25, [1, 2, 0, 1, 0, 2, 0, 0, 2, 0, 0]],
        ['/relatorios-diario', 'Relatórios Diário', 'Diário', 26, [1, 1, 2, 2, 2, 2, 0, 2, 2, 0, 0, 0, 0, 2]],

        // Relatórios do Diário: uma tela por relatório, e não o /relatorios genérico.
        // Todos moram sob /relatorios/*, então sem estas linhas eles resolveriam (por
        // prefixo mais longo) para a tela /relatorios — onde Professor, Professor AEE
        // e Coordenador são nível 0 e levavam 403 nos cards que a tela /relatorios-diario
        // já lhes mostrava. Dar nível na /relatorios resolveria em uma linha só, mas
        // arrastaria junto os relatórios do grupo "central" (Ficha de Matrícula,
        // Declaração de Matrícula, Alunos por Escola...), que não são desses perfis.
        // Os demais perfis mantêm os níveis do /relatorios — ninguém perde acesso.
        ['/relatorios/parecer-descritivo', 'Parecer Descritivo', 'Diário', 47, [1, 1, 2, 2, 2, 2, 2, 2, 2, 0, 2, 0, 0, 2]],
        ['/relatorios/boletim', 'Boletim do Aluno', 'Diário', 48, [1, 1, 2, 2, 2, 2, 2, 2, 2, 0, 2, 0, 0, 2]],
        ['/relatorios/notas-por-unidade', 'Notas por Unidade', 'Diário', 49, [1, 1, 2, 2, 2, 2, 2, 2, 2, 0, 2]],
        ['/relatorios/mapa-notas-disciplina', 'Mapa de Notas', 'Diário', 50, [1, 1, 2, 2, 2, 2, 2, 2, 2, 0, 2]],
        ['/relatorios/boletim-avaliativo', 'Boletim Avaliativo', 'Diário', 51, [1, 1, 2, 2, 2, 2, 2, 2, 2, 0, 2]],
        ['/relatorios/conteudo-ministrado', 'Conteúdo Ministrado', 'Diário', 52, [1, 1, 2, 2, 2, 2, 2, 2, 2, 0, 2]],
        ['/relatorios/frequencia-mensal', 'Diário de Frequência Mensal', 'Diário', 53, [1, 1, 2, 2, 2, 2, 2, 2, 2, 0, 2]],
        ['/relatorios/diagnostico', 'Avaliações por Diagnóstico', 'Diário', 54, [1, 1, 2, 2, 2, 2, 2, 2, 2, 0, 2, 0, 0, 2]],
        ['/relatorios/desempenho-aee', 'Desempenho AEE', 'Diário', 55, [1, 1, 2, 2, 2, 2, 2, 2, 2, 0, 2, 0, 0, 2]],

        ['/coordenador/planos', 'Validação de Planos', 'Pedagógico', 27, [1, 2, 0, 0, 1, 1, 0, 0, 1, 0, 0]],
        ['/coordenador-interno/planos-aee', 'Validação de Planos AEE', 'Pedagógico', 28, [1, 2, 0, 0, 0, 1, 0, 0, 1, 0, 0]],
        ['/relatorios-pedagogico', 'Relatórios Pedagógico', 'Pedagógico', 29, [1, 1, 0, 0, 2, 2, 0, 0, 2, 0, 0]],

        ['/secretaria/acessos-professores', 'Acessos de Professores', 'Secretaria Escolar', 30, [1, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0]],
        ['/secretaria/lancamento-manual', 'Notas e Faltas', 'Secretaria Escolar', 31, [1, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0]],
        ['/secretaria/justificativas-falta', 'Justificativa de Falta', 'Secretaria Escolar', 32, [1, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0]],
        ['/secretaria/historico-escolar', 'Histórico Escolar', 'Secretaria Escolar', 56, [1, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0]],
        ['/secretaria/renovacao-matricula', 'Renovação de Matrícula', 'Secretaria Escolar', 33, [1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0]],
        ['/secretaria/motivos-baixa-frequencia', 'Motivo Baixa Frequência', 'Secretaria Escolar', 34, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        // Vive no menu Secretaria Escolar (não mais em Relatórios) — grupo acompanha.
        ['/relatorios-secretaria', 'Relatórios da Secretaria', 'Secretaria Escolar', 46, [1, 1, 0, 0, 0, 0, 2, 2, 0, 0, 0]],

        ['/encerramento-turmas', 'Encerramento de Turmas', 'Ano Letivo', 35, [1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0]],
        ['/duplicar-turmas', 'Duplicar Turmas', 'Ano Letivo', 36, [1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0]],
        ['/confirmacao-renovacao', 'Confirmação de Renovação', 'Ano Letivo', 37, [1, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0]],

        ['/relatorios', 'Central de Relatórios', 'Relatórios', 39, [1, 1, 0, 0, 0, 2, 2, 2, 2, 0, 2]],
        ['/relatorios-escola', 'Relatórios Gerais', 'Relatórios', 40, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 2]],
        // Tela própria (senão /relatorios/* herdaria a /relatorios): mesmos perfis do hub Relatórios Gerais.
        ['/relatorios/indices-aprovacao', 'Índices de Aprovação e Reprovação', 'Relatórios', 40, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 2]],

        // Censo — menu próprio (crescerá com mais funcionalidades do Educacenso).
        // matricula_censo (índice 10) é o perfil que opera o censo.
        ['/relatorios-censo', 'Relatórios do Censo', 'Censo', 63, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 2]],

        // Alimentação Escolar — ordem das roles:
        // admin, gestor_secretaria, professor, professor_aee, coordenador, coordenador_interno,
        // diretor, secretaria_escola, pedagogico, monitor, matricula_censo, nutricionista, almoxarife
        // Estoque. A escola opera o próprio estoque (retirada, consulta) — o
        // filtro de qual estoque ela enxerga é do controller, via lotação.
        // Ordem das roles: admin, gestor_secretaria, professor, professor_aee,
        // coordenador, coordenador_interno, diretor, secretaria_escola,
        // pedagogico, monitor, matricula_censo, nutricionista, almoxarife
        // Visitas técnicas: a nutrição programa, realiza e atesta. A escola só
        // consulta as visitas que recebeu.
        // Ordem das roles: admin, gestor_secretaria, professor, professor_aee,
        // coordenador, coordenador_interno, diretor, secretaria_escola,
        // pedagogico, monitor, matricula_censo, nutricionista, almoxarife
        ['/alimentacao/visitas', 'Visitas Técnicas', 'Alimentação Escolar', 34, [1, 0, 0, 0, 0, 0, 2, 2, 0, 0, 0, 1, 0]],
        ['/alimentacao/perguntas', 'Perguntas do Questionário', 'Alimentação Escolar', 35, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0]],
        ['/alimentacao/tipos-visita', 'Tipos de Visita Técnica', 'Alimentação Escolar', 36, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0]],
        ['/alimentacao/tipos-questionario', 'Tipos de Questionário', 'Alimentação Escolar', 37, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0]],

        // A escola registra a própria preparação; a nutrição acompanha e edita.
        // A demanda é planejamento: nutricionista monta, escola consulta.
        ['/alimentacao/preparacoes', 'Controle Diário da Preparação', 'Alimentação Escolar', 38, [1, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 1, 2]],
        ['/alimentacao/demandas', 'Demanda de Merenda', 'Alimentação Escolar', 39, [1, 0, 0, 0, 0, 0, 2, 2, 0, 0, 0, 1, 2]],

        ['/alimentacao/estoque', 'Consulta de Estoque', 'Alimentação Escolar', 40, [1, 0, 0, 0, 0, 0, 2, 2, 0, 0, 0, 2, 1]],
        ['/alimentacao/recebimentos', 'Recebimento de Itens', 'Alimentação Escolar', 41, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 1]],
        ['/alimentacao/transferencias', 'Saída para Escolas', 'Alimentação Escolar', 42, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1]],
        ['/alimentacao/retiradas', 'Retirada de Itens', 'Alimentação Escolar', 43, [1, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 1, 1]],

        // Contratos: o almoxarife opera; o nutricionista consulta (precisa saber o
        // que está contratado para planejar cardápio), mas não edita.
        ['/alimentacao/contratos', 'Contratos', 'Alimentação Escolar', 47, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 1]],
        ['/alimentacao/empenhos', 'Empenhos', 'Alimentação Escolar', 48, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1]],
        ['/alimentacao/ordens-fornecimento', 'Ordens de Fornecimento', 'Alimentação Escolar', 49, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 1]],

        ['/alimentacao/itens', 'Itens', 'Alimentação Escolar', 50, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1]],
        ['/alimentacao/fornecedores', 'Fornecedores', 'Alimentação Escolar', 51, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 1]],
        ['/alimentacao/pratos', 'Pratos', 'Alimentação Escolar', 52, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2]],
        ['/alimentacao/cardapios', 'Cardápio Semanal', 'Alimentação Escolar', 53, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2]],
        ['/alimentacao/unidades-medida', 'Unidades de Medida', 'Alimentação Escolar', 54, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1]],
        ['/alimentacao/categorias-alimento', 'Categorias de Alimento', 'Alimentação Escolar', 55, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2]],
        ['/alimentacao/tipos-item', 'Tipos de Item', 'Alimentação Escolar', 56, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1]],
        ['/alimentacao/marcas', 'Marcas', 'Alimentação Escolar', 57, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 1]],
        ['/alimentacao/tipos-retirada', 'Tipos de Retirada', 'Alimentação Escolar', 58, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1]],

        // Relatórios da alimentação. Uma tela por relatório, pelo mesmo motivo dos
        // relatórios do Diário: todos moram sob /relatorios/*, então sem estas
        // linhas eles resolveriam (por prefixo mais longo) para a tela /relatorios,
        // onde nutricionista e almoxarife são nível 0 — justamente os perfis que
        // vivem destes relatórios. Diretor e secretaria entram como visualização:
        // o controller já prende cada um ao estoque da própria escola, via lotação.
        // Ordem das roles: admin, gestor_secretaria, professor, professor_aee,
        // coordenador, coordenador_interno, diretor, secretaria_escola,
        // pedagogico, monitor, matricula_censo, nutricionista, almoxarife
        ['/relatorios-alimentacao', 'Relatórios da Alimentação', 'Alimentação Escolar', 59, [1, 0, 0, 0, 0, 0, 2, 2, 0, 0, 0, 2, 2]],
        ['/relatorios/protocolo-distribuicao', 'Protocolo da Central de Distribuição', 'Alimentação Escolar', 60, [1, 0, 0, 0, 0, 0, 2, 2, 0, 0, 0, 2, 2]],
        ['/relatorios/itens-vencimento', 'Itens Próximos do Vencimento', 'Alimentação Escolar', 61, [1, 0, 0, 0, 0, 0, 2, 2, 0, 0, 0, 2, 2]],
        ['/relatorios/retirada-alimentos', 'Retirada de Alimentos', 'Alimentação Escolar', 62, [1, 0, 0, 0, 0, 0, 2, 2, 0, 0, 0, 2, 2]],
        // Transporte Escolar — Fase 1 (cadastros). Edição: admin e gestor_secretaria.
        ['/transporte/veiculos', 'Veículos (Transporte)', 'Transporte', 60, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/transporte/usuarios', 'Usuários do Transporte', 'Transporte', 64, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        // Fase 2 (rotas).
        ['/transporte/rotas', 'Rotas (Transporte)', 'Transporte', 65, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        // Fase 3 (diário de bordo).
        ['/transporte/diario-bordo', 'Diário de Bordo (Transporte)', 'Transporte', 66, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        // Fase 5 (simulador de rotas).
        ['/transporte/simulador', 'Simulador de Rotas (Transporte)', 'Transporte', 67, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        // Relatórios do transporte.
        ['/transporte/relatorios/frota', 'Situação da Frota (Transporte)', 'Transporte', 68, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/transporte/relatorios/ocorrencias', 'Ocorrências (Transporte)', 'Transporte', 69, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],

        ['/users', 'Usuários', 'Sistema', 41, [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/parametros', 'Parâmetros', 'Sistema', 42, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/permissoes', 'Permissões de Telas', 'Sistema', 43, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/auditoria', 'Auditoria', 'Sistema', 44, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
        ['/censo/importacao', 'Importar Censo', 'Sistema', 45, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
    ];

    /**
     * Telas que saíram do sistema. Como o seeder só faz updateOrCreate, sem isso
     * a linha continuaria em cfg_tela e apareceria na tela de Permissão apontando
     * para uma rota que não existe mais. Lista explícita (não um prune de tudo que
     * não está na MATRIZ) para não apagar tela que só não foi mapeada ainda.
     */
    private const ROTAS_REMOVIDAS = [
        '/relatorios-encerramento',
        '/transporte/instituicoes', // instituições de ensino passaram a usar edu_escola
    ];

    public function run(): void
    {
        Tela::query()->whereIn('tla_rota', self::ROTAS_REMOVIDAS)->delete();

        foreach (self::MATRIZ as [$rota, $nome, $grupo, $ordem, $niveis]) {
            // Metadados da tela são sempre atualizados (nome/grupo/ordem).
            $tela = Tela::updateOrCreate(
                ['tla_rota' => $rota],
                ['tla_nome' => $nome, 'tla_grupo' => $grupo, 'tla_ordem' => $ordem],
            );

            // A MATRIZ é a fonte de verdade: sincroniza (sobrescreve) os níveis.
            foreach (self::ROLES as $i => $role) {
                TelaPermissao::updateOrCreate(
                    ['tlp_tla_id' => $tela->tla_id, 'tlp_role' => $role],
                    ['tlp_nivel' => $niveis[$i] ?? 0],
                );
            }
        }

        PermissaoTela::clearCache();
    }
}
