import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { setUnauthorizedHandler } from './api/http';
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

createApp(App).use(pinia).use(router).mount('#app');
