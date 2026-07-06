import { triggerSessionExpired } from '@/lib/session';

/** Token XSRF do cookie (Laravel rotaciona a cada resposta). */
function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
}

export async function apiFetch(input: RequestInfo, init: RequestInit = {}): Promise<Response> {
    const method = (init.method ?? 'GET').toUpperCase();
    const mutating = method !== 'GET' && method !== 'HEAD';

    const res = await fetch(input, {
        // Envia cookies de sessão e, em mutações, o token CSRF — evita 419 falso.
        credentials: 'same-origin',
        ...init,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            ...(mutating ? { 'X-XSRF-TOKEN': xsrfToken() } : {}),
            ...(init.headers ?? {}),
        },
    });

    // 419 = token CSRF/sessão expirada · 401 = não autenticado.
    if (res.status === 419 || res.status === 401) {
        triggerSessionExpired();
        return Promise.reject(new Error('Session expired'));
    }

    return res;
}
