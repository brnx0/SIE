import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SystemParams {
    nome_pessoa_caixa_alta: boolean;
    nome_escola_caixa_alta: boolean;
    alertar_homonimos: boolean;
    alertar_acentos_nomes: boolean;
    validar_idade_serie: boolean;
    gerar_matricula_auto: boolean;
    validar_carga_prof: boolean;
    cpf_obrigatorio: boolean;
    fardamento_obrigatorio: boolean;
    tipo_validacao_carga: 'bloquear' | 'avisar';
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash?: { success?: string | null; error?: string | null };
    params?: SystemParams;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role?: string;
    phone?: string | null;
    active?: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface PaginatedLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginatedLink[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
}

export type BreadcrumbItemType = BreadcrumbItem;
