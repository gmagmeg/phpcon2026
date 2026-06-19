<?php

declare(strict_types=1);

/**
 * 「薄い自作スクリプト」側の計測対象（§2 / Laravel の対比）。
 *
 * 極小ファイル（関数 1 個）を FILES 個 require して合算するだけ。Laravel が 1 リクエストで
 * 約 500 ファイルをコンパイルするのに対し、こちらは元々コンパイル対象が小さいため、
 * OPcache の OFF/ON でほとんど差が出ない（＝効果が薄い）ことを示す。
 *
 * 計測方式は bench/laravel_request.php と同一: hrtime で 1 プロセス分の処理時間を測り、
 * ミリ秒を 1 行だけ stdout に出す。OPcache OFF/ON の切替と 30 回平均、ファイル数スイープは
 * bench/thin_bench.sh が行う。
 *
 * 使い方:
 *   FILES=1   PAYLOAD_DIR=/tmp/thin_payload php bench/thin_script.php   # 薄いスクリプト
 *   FILES=500 PAYLOAD_DIR=/tmp/thin_payload php bench/thin_script.php   # 大量ファイル
 */

$files = (int) (getenv('FILES') ?: 1);
$dir   = getenv('PAYLOAD_DIR') ?: (__DIR__ . '/thin_payload');

$t0 = hrtime(true);

$sum = 0;
for ($i = 0; $i < $files; $i++) {
    // ここで各ファイルの「ソース→OPCODE」変換が走る（OFF は毎回 / ON は file_cache 再利用）。
    require sprintf('%s/thin_%05d.php', $dir, $i);
    $fn = "thin_fn_{$i}";
    $sum = $fn($sum);
}

$t1 = hrtime(true);

fwrite(STDOUT, number_format(($t1 - $t0) / 1e6, 3) . "\n");

if (getenv('SHOW_FILES')) {
    fwrite(STDERR, sprintf(
        "FILES=%d included=%d declared=%d sum=%d\n",
        $files,
        count(get_included_files()),
        count(get_declared_classes()),
        $sum,
    ));
}
