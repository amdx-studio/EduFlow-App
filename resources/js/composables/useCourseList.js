import { onMounted, ref } from 'vue';
import http from '../api/http';

// Daftar kelas untuk sidebar. Memakai endpoint yang sudah ada (API-06); bila gagal, bagian dihilangkan tanpa pesan.
export function useCourseList() {
    const courses = ref([]);
    onMounted(async () => {
        try {
            const { data } = await http.get('/api/courses');
            courses.value = Array.isArray(data.data) ? data.data : [];
        } catch {
            courses.value = [];
        }
    });
    return { courses };
}
