import axios from 'axios';

const http = axios.create({
    baseURL: '',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

let unauthorizedHandler = null;
let forbiddenHandler = null;

export function setUnauthorizedHandler(fn) {
    unauthorizedHandler = fn;
}

http.interceptors.response.use(
    (response) => response,
    async (error) => {
        const config = error.config;
        const status = error.response?.status;

        // 419: token CSRF kedaluwarsa. Ambil ulang cookie lalu ulangi sekali.
        if (status === 419 && config && !config._csrfRetried) {
            config._csrfRetried = true;
            await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
            return http(config);
        }

        // 401: sesi habis. Pengecekan awal (/api/user) dan login ditangani pemanggilnya.
        const url = config?.url ?? '';
        if (status === 401 && unauthorizedHandler && url !== '/api/user' && url !== '/api/login') {
            unauthorizedHandler();
        }

        // 403: bisa berarti sesi di tab ini sudah berganti akun. Pemanggil memeriksa ulang pengguna.
        if (status === 403 && forbiddenHandler && url !== '/api/user') {
            forbiddenHandler();
        }

        return Promise.reject(error);
    },
);

export function setForbiddenHandler(fn) {
    forbiddenHandler = fn;
}

export default http;
