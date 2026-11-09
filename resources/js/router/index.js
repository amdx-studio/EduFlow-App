import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import AppLayout from '../components/AppLayout.vue';
import LoginPage from '../pages/LoginPage.vue';
import RegisterPage from '../pages/RegisterPage.vue';
import CoursesPage from '../pages/CoursesPage.vue';
import ForbiddenPage from '../pages/ForbiddenPage.vue';
import NotFoundPage from '../pages/NotFoundPage.vue';
import CourseLayout from '../components/CourseLayout.vue';
import CourseLessonsPage from '../pages/CourseLessonsPage.vue';
import CourseConceptsPage from '../pages/CourseConceptsPage.vue';
import LessonReadPage from '../pages/LessonReadPage.vue';
import LessonEditorPage from '../pages/LessonEditorPage.vue';
import QuizzesIndexPage from '../pages/QuizzesIndexPage.vue';
import QuizCreatePage from '../pages/QuizCreatePage.vue';
import QuizPage from '../pages/QuizPage.vue';
import AttemptPage from '../pages/AttemptPage.vue';
import AttemptResultPage from '../pages/AttemptResultPage.vue';
import CourseStudentsPage from '../pages/CourseStudentsPage.vue';
import RemediationPage from '../pages/RemediationPage.vue';
import ComparisonPage from '../pages/ComparisonPage.vue';
import DashboardPage from '../pages/DashboardPage.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/courses' },
        { path: '/login', name: 'login', component: LoginPage, meta: { guest: true } },
        { path: '/register', name: 'register', component: RegisterPage, meta: { guest: true } },
        {
            path: '/',
            component: AppLayout,
            meta: { requiresAuth: true },
            children: [
                { path: 'courses', name: 'courses', component: CoursesPage },
                {
                    path: 'courses/:courseId',
                    component: CourseLayout,
                    children: [
                        {
                            path: '',
                            redirect: (to) => ({ name: 'course-lessons', params: { courseId: to.params.courseId } }),
                        },
                        { path: 'lessons', name: 'course-lessons', component: CourseLessonsPage },
                        { path: 'lessons/new', name: 'lesson-new', component: LessonEditorPage, meta: { roles: ['teacher'] } },
                        { path: 'lessons/:lessonId/edit', name: 'lesson-edit', component: LessonEditorPage, meta: { roles: ['teacher'] } },
                        { path: 'lessons/:lessonId', name: 'lesson-read', component: LessonReadPage },
                        { path: 'concepts', name: 'course-concepts', component: CourseConceptsPage, meta: { roles: ['teacher'] } },
                        { path: 'quizzes', name: 'course-quizzes', component: QuizzesIndexPage },
                        { path: 'quizzes/new', name: 'quiz-new', component: QuizCreatePage, meta: { roles: ['teacher'] } },
                        { path: 'quizzes/:quizId', name: 'quiz-builder', component: QuizPage },
                        { path: 'quizzes/:quizId/comparison', name: 'quiz-comparison', component: ComparisonPage },
                        { path: 'students', name: 'course-students', component: CourseStudentsPage, meta: { roles: ['teacher'] } },
                        { path: 'remediation', name: 'course-remediation', component: RemediationPage, meta: { roles: ['student'] } },
                        { path: 'dashboard', name: 'course-dashboard', component: DashboardPage, meta: { roles: ['teacher'] } },
                    ],
                },
                { path: 'attempts/:attemptId/result', name: 'attempt-result', component: AttemptResultPage },
                { path: 'attempts/:attemptId', name: 'attempt', component: AttemptPage, meta: { roles: ['student'] } },
                { path: 'forbidden', name: 'forbidden', component: ForbiddenPage },
                // Contoh rute berperan (dipakai di fase berikutnya):
                // { path: 'teacher/...', meta: { roles: ['teacher'] }, ... }
                { path: ':pathMatch(.*)*', name: 'not-found', component: NotFoundPage },
            ],
        },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    await auth.init();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'courses' };
    }

    const roles = to.meta.roles;
    if (roles && !roles.includes(auth.user?.role)) {
        return { name: 'forbidden' };
    }

    return true;
});

export default router;
