<?php

use App\Performance\HeavyWelcomePage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * 通常の Welcome ページと同じ Blade を返しつつ、36 個の具象依存を
 * Laravel のサービスコンテナで解決する計測用エンドポイント。
 *
 *   GET /perf/dependency-heavy
 */
Route::get('/perf/dependency-heavy', function (HeavyWelcomePage $page) {
    return response()
        ->view('welcome')
        ->header('X-Benchmark-Checksum', (string) $page->checksum());
});

/**
 * OPcache のファイル数上限を確実に超えるための計測用エンドポイント。
 * bench/thin_gen.php で /tmp/opcache-pressure に生成した PHP ファイルを読み込む。
 *
 *   GET /perf/opcache-pressure?files=400
 */
Route::get('/perf/opcache-pressure', function () {
    $files = max(1, min(1000, request()->integer('files', 400)));
    $directory = '/tmp/opcache-pressure';
    $sum = 0;

    for ($i = 0; $i < $files; $i++) {
        $file = sprintf('%s/thin_%05d.php', $directory, $i);

        abort_unless(is_file($file), 503, 'Run bench/thin_gen.php before this endpoint.');
        require $file;

        $function = "thin_fn_{$i}";
        $sum = $function($sum);
    }

    return response()
        ->view('welcome')
        ->header('X-Benchmark-Pressure-Files', (string) $files)
        ->header('X-Benchmark-Checksum', (string) $sum);
});

/**
 * OPcache / JIT の有効状態を JSON で確認する検証用エンドポイント。
 *   GET /perf/status
 */
Route::get('/perf/status', function () {
    $status = function_exists('opcache_get_status') ? opcache_get_status(false) : null;
    $config = function_exists('opcache_get_configuration') ? opcache_get_configuration() : null;

    return response()->json([
        'php_version'   => PHP_VERSION,
        'sapi'          => PHP_SAPI,
        'opcache'       => [
            'enabled'        => $status['opcache_enabled'] ?? false,
            'cache_full'     => $status['cache_full'] ?? null,
            'jit_enabled'    => $status['jit']['enabled'] ?? false,
            'jit'            => $status['jit'] ?? null,
            'memory_usage'   => $status['memory_usage'] ?? null,
            'cached_scripts' => $status['opcache_statistics']['num_cached_scripts'] ?? null,
            'cached_keys'    => $status['opcache_statistics']['num_cached_keys'] ?? null,
            'max_cached_keys' => $status['opcache_statistics']['max_cached_keys'] ?? null,
            'hits'           => $status['opcache_statistics']['hits'] ?? null,
            'misses'         => $status['opcache_statistics']['misses'] ?? null,
            'oom_restarts'   => $status['opcache_statistics']['oom_restarts'] ?? null,
            'hash_restarts'  => $status['opcache_statistics']['hash_restarts'] ?? null,
        ],
        'directives'    => [
            'opcache.enable'           => ini_get('opcache.enable'),
            'opcache.memory_consumption' => ini_get('opcache.memory_consumption'),
            'opcache.interned_strings_buffer' => ini_get('opcache.interned_strings_buffer'),
            'opcache.max_accelerated_files' => ini_get('opcache.max_accelerated_files'),
            'opcache.jit'              => ini_get('opcache.jit'),
            'opcache.jit_buffer_size'  => ini_get('opcache.jit_buffer_size'),
            'opcache.validate_timestamps' => ini_get('opcache.validate_timestamps'),
        ],
        'raw_configuration' => $config['directives'] ?? null,
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

/**
 * phpinfo() を丸ごと確認したい場合。
 *   GET /perf/phpinfo
 */
Route::get('/perf/phpinfo', function () {
    ob_start();
    phpinfo();
    return response(ob_get_clean());
});
