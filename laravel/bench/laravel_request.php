<?php

declare(strict_types=1);

/**
 * Laravel 1 リクエスト分の「ブート＋ハンドル」を計測する単発スクリプト。
 *
 * §2「OPcache が効くケース（Laravel）」用。約 500 ファイル/クラスのロード・
 * コンパイルを含む 1 リクエストにかかる時間を、プロセス 1 回ぶんとして出力する。
 *
 * PHPBench の Laravel ベンチ（StrBench 等）は setUp() でブートを計測外に出すため、
 * 「毎リクエストのコンパイルコスト」は測れない。そこをあえて計測するのが本スクリプト。
 *
 * OPcache の効果（プロセス跨ぎのコンパイル結果再利用＝FPM 相当）は、
 * opcache.file_cache を有効にして "別プロセスで再実行" することで観測する。
 * → 実際の OFF/ON 切替と 30 回平均は bench/laravel_boot_bench.sh が行う。
 *
 * 計測範囲は環境変数 MODE で切替:
 *   MODE=boot    … autoload + アプリ boot（kernel bootstrap）まで。コンパイルコストが支配的。
 *   MODE=request … boot に加えてルーティング + Blade レンダリングまで（既定）。
 *
 * 使い方:
 *   php bench/laravel_request.php               # request モードで経過ミリ秒を1行出力
 *   MODE=boot php bench/laravel_request.php     # boot のみ計測
 *   SHOW_FILES=1 php bench/laravel_request.php  # included/declared を STDERR に出す
 */

$mode = getenv('MODE') ?: 'request';

$t0 = hrtime(true);

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$status = null;
if ($mode === 'boot') {
    // HTTP カーネルの bootstrap まで（config/プロバイダ登録・boot を含む）。
    $kernel->bootstrap();
} else {
    $request = \Illuminate\Http\Request::create('/', 'GET');
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $status = $response->getStatusCode();
}

$t1 = hrtime(true);

// 計測値（ミリ秒）を1行だけ stdout に出す。runner 側で集計する。
fwrite(STDOUT, number_format(($t1 - $t0) / 1e6, 3) . "\n");

if (getenv('SHOW_FILES')) {
    fwrite(STDERR, sprintf(
        "mode=%s status=%s included=%d declared=%d\n",
        $mode,
        $status ?? '-',
        count(get_included_files()),
        count(get_declared_classes()),
    ));
}
