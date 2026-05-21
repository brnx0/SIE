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
