import { defineStore } from 'pinia';

let nextId = 1;

export const useToastStore = defineStore('toast', {
    state: () => ({ items: [] }),
    actions: {
        push(message, type = 'success', duration = 4000) {
            const id = nextId++;
            this.items.push({ id, message, type });
            setTimeout(() => this.dismiss(id), duration);
        },
        success(message) {
            this.push(message, 'success');
        },
        error(message) {
            this.push(message, 'error', 6000);
        },
        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    },
});
