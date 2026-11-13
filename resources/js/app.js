import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import http, { setForbiddenHandler, setUnauthorizedHandler } from './api/http';
import { useAuthStore } from './stores/auth';
import { useToastStore } from './stores/toast';

const pinia = createPinia();

// 401 dari endpoint terlindungi: sesi habis. Bersihkan user lalu arahkan ke login.
setUnauthorizedHandler(() => {
    const auth = useAuthStore(pinia);
    const toast = useToastStore(pinia);
    const current = router.currentRoute.value;

    auth.user = null;
    if (current.name !== 'login' && current.name !== 'register') {
        toast.error('Sesi Anda telah berakhir. Silakan masuk lagi.');
        router.push({ name: 'login', query: { redirect: current.fullPath } });
    }
});

// Beberapa akun di satu profil browser berbagi cookie sesi. Tab lama bisa masih menampilkan
// akun sebelumnya padahal server sudah melihat akun lain. Periksa ulang saat tab kembali aktif
// atau saat menerima 403, lalu muat ulang bila pengguna berbeda.
let verifying = false;
const identity = (u) => u?.id ?? u?.email ?? u?.name;

async function verifySession() {
    const auth = useAuthStore(pinia);
    if (verifying || !auth.ready || !auth.user) {
        return;
    }
    verifying = true;
    try {
        const { data } = await http.get('/api/user');
        if (identity(data.data) !== identity(auth.user)) {
            window.location.reload();
        }
    } catch (error) {
        const current = router.currentRoute.value;
        if (error.response?.status === 401 && current.name !== 'login' && current.name !== 'register') {
            auth.user = null;
            useToastStore(pinia).error('Sesi Anda telah berakhir. Silakan masuk lagi.');
            router.push({ name: 'login', query: { redirect: current.fullPath } });
        }
    } finally {
        verifying = false;
    }
}

setForbiddenHandler(verifySession);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        verifySession();
    }
});
window.addEventListener('focus', verifySession);

createApp(App).use(pinia).use(router).mount('#app');
