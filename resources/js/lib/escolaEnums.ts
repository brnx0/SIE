import type {
    DepAdministrativa,
    FormaOcupacao,
    LocalizacaoDif,
    ProprietarioImovel,
    SituacaoFunc,
} from '@/types/escola';

export const ZONAS: { value: 'U' | 'R'; label: string }[] = [
    { value: 'U', label: 'Urbana' },
    { value: 'R', label: 'Rural' },
];

export const LOCALIZACAO_DIF: { value: LocalizacaoDif; label: string }[] = [
    { value: 0, label: 'Não se aplica' },
    { value: 1, label: 'Área de assentamento' },
    { value: 2, label: 'Terra indígena' },
    { value: 3, label: 'Área remanescente de quilombos' },
    { value: 4, label: 'Unidade de uso sustentável' },
    { value: 5, label: 'Área protegida (estação ecológica, parque etc.)' },
    { value: 6, label: 'Área onde se localiza comunidade tradicional' },
    { value: 7, label: 'Área de difícil acesso' },
];

export const DEP_ADMINISTRATIVA: { value: DepAdministrativa; label: string }[] = [
    { value: 1, label: 'Federal' },
    { value: 2, label: 'Estadual' },
    { value: 3, label: 'Municipal' },
    { value: 4, label: 'Privada' },
];

export const PROPRIETARIO_IMOVEL: { value: ProprietarioImovel; label: string }[] = [
    { value: 1, label: 'Federal' },
    { value: 2, label: 'Estadual' },
    { value: 3, label: 'Municipal' },
    { value: 4, label: 'Outros' },
];

export const FORMA_OCUPACAO: { value: FormaOcupacao; label: string }[] = [
    { value: 1, label: 'Próprio' },
    { value: 2, label: 'Alugado' },
    { value: 3, label: 'Cedido' },
    { value: 4, label: 'Outros' },
];

export const SITUACAO_FUNC: { value: SituacaoFunc; label: string }[] = [
    { value: 1, label: 'Em atividade' },
    { value: 2, label: 'Paralisada' },
    { value: 3, label: 'Extinta' },
];

export const TURNOS_ESCOLARES: string[] = [
    'Matutino',
    'Vespertino',
    'Noturno',
    'Integral',
];
