<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * UT-11: perilaku routing yang hanya muncul di production.
 *
 * - Route::bind (dan scope visibleTo di dalamnya) harus tetap terdaftar saat rute di-cache.
 *   Test lain memuat routes/api.php secara normal, jadi tidak menangkap kasus ini.
 * - Permintaan API tak terautentikasi tanpa header Accept: json harus 401, bukan 500.
 */
class ProductionRoutingTest extends TestCase
{
    /** @var array<int, string> */
    private const BINDERS = ['course', 'concept', 'lesson', 'quiz', 'question', 'attempt', 'thread', 'post'];

    public function test_route_binders_are_registered_when_routes_are_cached(): void
    {
        $defaultCache = base_path('bootstrap/cache/routes-v7.php');

        $this->assertFileDoesNotExist($defaultCache, 'Bersihkan dulu: php artisan optimize:clear');

        $suffix = bin2hex(random_bytes(4));
        $relative = 'storage/framework/eduflow-routes-'.$suffix.'.php';
        $cacheFile = base_path($relative);
        $probeFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'eduflow-probe-'.$suffix.'.php';

        $probe = <<<'PHP'
<?php
$root = $argv[1];
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$router = $app->make('router');
$out = ['routes_cached' => $app->routesAreCached()];
foreach (explode(',', $argv[2]) as $name) {
    $out[$name] = $router->getBindingCallback($name) !== null;
}
echo json_encode($out);
PHP;

        try {
            file_put_contents($probeFile, $probe);

            $env = ['APP_ROUTES_CACHE' => $relative];

            $build = new Process([PHP_BINARY, 'artisan', 'route:cache'], base_path(), $env);
            $build->setTimeout(120);
            $build->run();

            $this->assertTrue($build->isSuccessful(), 'route:cache gagal: '.$build->getErrorOutput().$build->getOutput());
            $this->assertFileExists($cacheFile, 'route:cache tidak menulis ke APP_ROUTES_CACHE');

            $check = new Process([PHP_BINARY, $probeFile, base_path(), implode(',', self::BINDERS)], base_path(), $env);
            $check->setTimeout(120);
            $check->run();

            $this->assertTrue($check->isSuccessful(), 'pemeriksa gagal: '.$check->getErrorOutput().$check->getOutput());

            $result = json_decode($check->getOutput(), true);

            $this->assertIsArray($result, 'keluaran pemeriksa bukan JSON: '.$check->getOutput());
            $this->assertTrue($result['routes_cached'], 'pemeriksa tidak berjalan dengan rute ter-cache');

            $missing = array_values(array_filter(
                self::BINDERS,
                fn (string $name) => ($result[$name] ?? false) !== true
            ));

            $this->assertSame([], $missing, 'Route::bind hilang saat rute di-cache');
        } finally {
            @unlink($cacheFile);
            @unlink($probeFile);
            @unlink($defaultCache);
        }
    }

    public function test_unauthenticated_api_request_without_json_accept_header_gets_401(): void
    {
        $response = $this->get('/api/user');

        $response->assertUnauthorized();
        $response->assertJson(['message' => 'Unauthenticated.']);
    }
}
