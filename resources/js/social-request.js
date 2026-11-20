export async function socialRequest(url, { method = 'GET', data } = {}) {
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content };
    const multipart = data instanceof FormData;
    if (data && !multipart) headers['Content-Type'] = 'application/json';
    const response = await fetch(url, { method, headers, body: multipart ? data : data ? JSON.stringify(data) : undefined, cache: 'no-store' });
    if (response.status === 204) return null;
    const result = await response.json().catch(() => null);
    if (!response.ok || !result) {
        const validation = Object.values(result?.errors ?? {}).flat()[0];
        throw new Error(validation || result?.message || 'Não foi possível concluir a operação.');
    }
    return result;
}
