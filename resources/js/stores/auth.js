import { defineStore } from 'pinia';
import http from '../api/http';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        ready: false,
    }),
    getters: {
        isAuthenticated: (state) => state.user !== null,
        isTeacher: (state) => state.user?.role === 'teacher',
        isStudent: (state) => state.user?.role === 'student',
    },
    actions: {
        async init() {
            if (this.ready) {
                return;
            }
            try {
                const { data } = await http.get('/api/user');
                this.user = data.data;
            } catch {
                this.user = null;
            } finally {
                this.ready = true;
            }
        },
        async csrf() {
            await http.get('/sanctum/csrf-cookie');
        },
        async register(payload) {
            await this.csrf();
            const { data } = await http.post('/api/register', payload);
            this.user = data.data;
            this.ready = true;
        },
        async login(payload) {
            await this.csrf();
            const { data } = await http.post('/api/login', payload);
            this.user = data.data;
            this.ready = true;
        },
        async logout() {
            try {
                await http.post('/api/logout');
            } finally {
                this.user = null;
            }
        },
    },
});
