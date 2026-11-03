export function fieldErrors(error) {
    return error?.response?.data?.errors ?? {};
}

export function errorMessage(error, fallback = 'Terjadi kesalahan. Coba lagi.') {
    if (!error?.response) {
        return 'Tidak dapat terhubung ke server.';
    }
    return error.response.data?.message || fallback;
}
