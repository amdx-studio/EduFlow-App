import { errorMessage } from './errors';

// 429 dari Laravel berbahasa Inggris; ganti dengan pesan yang ramah (BR-022).
export function forumErrorMessage(error) {
    if (error?.response?.status === 429) {
        return 'Terlalu banyak posting. Tunggu sebentar lalu coba lagi.';
    }
    return errorMessage(error);
}

export function roleLabel(role) {
    return role === 'teacher' ? 'Guru' : 'Siswa';
}
