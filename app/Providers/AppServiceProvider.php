<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\LearningConcept;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());

        $this->registerRouteBindings();

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('password-change', function (Request $request) {
            return Limit::perMinute(5)->by('password|'.($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(10)->by('register|'.$request->ip());
        });

        RateLimiter::for('join-course', function (Request $request) {
            return Limit::perMinute(10)->by('join|'.($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('forum-write', function (Request $request) {
            return Limit::perMinute(20)->by('forum|'.($request->user()?->id ?? $request->ip()));
        });
    }

    /**
     * Route binding dengan scope visibleTo (non-anggota mendapat 404).
     *
     * Sengaja di sini, bukan di routes/api.php: berkas rute tidak dieksekusi saat
     * `route:cache` aktif (production), sehingga binding di sana hilang (UT-11).
     */
    private function registerRouteBindings(): void
    {
        Route::bind('course', function ($value) {
            $user = request()->user();
            abort_unless($user, 401);

            return Course::visibleTo($user)->findOrFail($value);
        });

        Route::bind('concept', function ($value) {
            $user = request()->user();
            abort_unless($user, 401);

            return LearningConcept::visibleTo($user)->findOrFail($value);
        });

        Route::bind('lesson', function ($value) {
            $user = request()->user();
            abort_unless($user, 401);

            return Lesson::visibleTo($user)->findOrFail($value);
        });

        Route::bind('quiz', function ($value) {
            $user = request()->user();
            abort_unless($user, 401);

            return Quiz::visibleTo($user)->findOrFail($value);
        });

        Route::bind('question', function ($value) {
            $user = request()->user();
            abort_unless($user, 401);

            return Question::whereHas('quiz', fn ($q) => $q->visibleTo($user))->findOrFail($value);
        });

        Route::bind('attempt', function ($value) {
            $user = request()->user();
            abort_unless($user, 401);

            return QuizAttempt::visibleTo($user)->findOrFail($value);
        });

        Route::bind('thread', function ($value) {
            $user = request()->user();
            abort_unless($user, 401);

            return ForumThread::visibleTo($user)->findOrFail($value);
        });

        Route::bind('post', function ($value) {
            $user = request()->user();
            abort_unless($user, 401);

            return ForumPost::visibleTo($user)->findOrFail($value);
        });
    }
}
