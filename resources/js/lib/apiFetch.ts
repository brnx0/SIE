import { triggerSessionExpired } from '@/lib/session';

export async function apiFetch(input: RequestInfo, init?: RequestInit): Promise<Response> {
    const res = await fetch(input, init);

    // 419 = token CSRF/sessão expirada · 401 = não autenticado.
    if (res.status === 419 || res.status === 401) {
        triggerSessionExpired();
        return Promise.reject(new Error('Session expired'));
    }

    return res;
}
