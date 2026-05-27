export const ESCOLARIDADES = [
    { value: 1, label: 'Ensino Fundamental Incompleto' },
    { value: 2, label: 'Ensino Fundamental Completo' },
    { value: 3, label: 'Ensino Médio Incompleto' },
    { value: 4, label: 'Ensino Médio Completo' },
    { value: 5, label: 'Ensino Superior Incompleto' },
    { value: 6, label: 'Ensino Superior Completo' },
    { value: 7, label: 'Pós-graduação' },
    { value: 8, label: 'Mestrado / Doutorado' },
] as const;

export const ESTADOS_CIVIS = [
    { value: 1, label: 'Solteiro(a)' },
    { value: 2, label: 'Casado(a)' },
    { value: 3, label: 'Divorciado(a)' },
    { value: 4, label: 'Viúvo(a)' },
    { value: 5, label: 'União Estável' },
] as const;

export const NACIONALIDADES = [
    'Brasileira',
    'Estrangeira',
    'Naturalizado(a)',
] as const;

export const RELIGIOES = [
    'Católica',
    'Evangélica',
    'Espírita',
    'Umbanda',
    'Candomblé',
    'Budista',
    'Islâmica',
    'Judaica',
    'Sem religião',
    'Outra',
] as const;

export const FONTES_TRANSPORTE = [
    'Municipal',
    'Estadual',
    'Federal',
    'Privativo',
    'Outros',
] as const;

export const MODELOS_CERTIDAO = [
    'Novo',
    'Antigo',
] as const;

export const TIPOS_CERTIDAO_CIVIL = [
    'Nascimento',
    'Casamento',
] as const;

export const VINCULOS = [
    'Comissionado',
    'Concursado',
    'Contratado CLT',
    'Contrato Temporário',
    'Contrato Terceirizado',
    'Função Gratificada',
] as const;

export const SITUACOES_FUNCIONAIS = [
    'Aposentadoria',
    'Cedido(a)',
    'Exonerado',
    'Licença com Vencimento',
    'Licença Médica',
    'Licença sem Vencimento',
    'Óbito',
    'Permuta',
    'Remanejado(a)',
    'Término de Contrato',
    'Transferido(a)',
] as const;

export const CRITERIOS_ACESSO = [
    'Concurso Público',
    'Concurso público específico para o cargo de gestor escolar',
    'Exclusivamente por indicação/escolha da gestão',
    'Exclusivamente por processo eleitoral com a participação da comunidade escolar',
    'Processo seletivo qualificado e eleição com a participação da comunidade escolar',
    'Ser proprietário(a) ou sócio(a)-proprietário(a) da escola',
] as const;

export const FUNCOES_SALA_AULA = [
    'Docente',
    'Auxiliar/assistente educacional',
    'Guia-Intérprete de Libras',
    'Tradutor-intérprete de Libras',
    'Monitor de atividade complementar',
    'Docente tutor (de módulo ou disciplina)',
    'Docente Titular (de módulo ou disciplina) – EAD',
    'Profissional de apoio escolar para aluno(a)s com deficiência (Lei 13.146/2015)',
] as const;
