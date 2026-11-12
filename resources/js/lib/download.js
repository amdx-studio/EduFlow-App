import http from '../api/http';
import { errorMessage } from './errors';

function filenameFrom(header, fallback) {
    if (!header) {
        return fallback;
    }
    const star = /filename\*=UTF-8''([^;]+)/i.exec(header);
    if (star) {
        try {
            return decodeURIComponent(star[1]);
        } catch {
            // lanjut ke bentuk biasa
        }
    }
    const plain = /filename="?([^";]+)"?/i.exec(header);
    return plain ? plain[1] : fallback;
}

// Galat dari respons blob: isinya JSON, jadi dibaca dulu agar pesan API tampil.
async function readError(error) {
    const status = error?.response?.status;
    if (status === 429) {
        return 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.';
    }
    const blob = error?.response?.data;
    if (blob instanceof Blob) {
        let body = {};
        try {
            body = JSON.parse(await blob.text());
        } catch {
            body = {};
        }
        return errorMessage({ response: { ...error.response, data: body } });
    }
    return errorMessage(error);
}

// Unduh berkas lewat axios (sesi yang sama) agar 403/404 jadi pesan, bukan halaman JSON.
export async function downloadFile(url, fallbackName = 'unduhan.csv') {
    let response;
    try {
        response = await http.get(url, { responseType: 'blob' });
    } catch (e) {
        throw new Error(await readError(e));
    }
    const name = filenameFrom(response.headers['content-disposition'], fallbackName);
    const href = URL.createObjectURL(response.data);
    const a = document.createElement('a');
    a.href = href;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(href), 1000);
    return name;
}