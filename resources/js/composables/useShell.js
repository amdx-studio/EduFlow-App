import { ref } from 'vue';

// Keadaan bersama untuk kerangka aplikasi (AppShell). Bukan Pinia store: hanya jembatan tampilan.
export const activeCourse = ref(null);
export const breadcrumbs = ref([]);

// Item navigasi kelas per peran; urutan dan nama mengikuti aplikasi (§5.2).
export function courseNavItems(course, role) {
    if (!course) {
        return [];
    }
    const base = `/courses/${course.id}`;
    const teacher = role === 'teacher';
    const student = role === 'student';
    const list = [{ key: 'lessons', label: 'Materi', icon: 'book-open', to: `${base}/lessons` }];
    if (teacher) {
        list.push({ key: 'concepts', label: 'Konsep', icon: 'lightbulb', to: `${base}/concepts` });
    }
    list.push({ key: 'quizzes', label: 'Kuis', icon: 'clipboard', to: `${base}/quizzes` });
    if (student) {
        list.push({ key: 'remediation', label: 'Remedial Saya', icon: 'life-buoy', to: `${base}/remediation` });
    }
    if (teacher) {
        list.push({ key: 'students', label: 'Siswa', icon: 'users', to: `${base}/students` });
        list.push({ key: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard', to: `${base}/dashboard` });
    }
    list.push({ key: 'forum', label: 'Forum', icon: 'messages-square', to: `${base}/forum` });
    return list;
}
