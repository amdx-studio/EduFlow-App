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

        return Promise.reject(error);
    },
);

export default http;
