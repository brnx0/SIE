export async function apiFetch(input: RequestInfo, init?: RequestInit): Promise<Response> {
    const res = await fetch(input, init);

    if (res.status === 419) {
        alert('Sua sessão expirou. Você será redirecionado para o login.');
        window.location.href = '/login';
        return Promise.reject(new Error('Session expired'));
    }

    if (res.status === 401) {
        window.location.href = '/login';
        return Promise.reject(new Error('Unauthenticated'));
    }

    return res;
}
