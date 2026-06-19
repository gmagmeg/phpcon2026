<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
            'jit_enabled'    => $status['jit']['enabled'] ?? false,
            'jit'            => $status['jit'] ?? null,
            'memory_usage'   => $status['memory_usage'] ?? null,
            'cached_scripts' => $status['opcache_statistics']['num_cached_scripts'] ?? null,
            'hits'           => $status['opcache_statistics']['hits'] ?? null,
            'misses'         => $status['opcache_statistics']['misses'] ?? null,
        ],
        'directives'    => [
            'opcache.enable'           => ini_get('opcache.enable'),
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
