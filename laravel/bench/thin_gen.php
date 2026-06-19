<?php

declare(strict_types=1);

/**
 * 「薄いスクリプト」計測用の極小ファイルを N 個生成する。
 *
 * 各ファイルは数行（関数 1 個）だけ＝1 ファイルあたりのコンパイル対象が小さいことを再現する。
 * Laravel の約 500 ファイルに対し、ファイル数（＝コンパイル総量）を振って
 * 「OPcache の効果はコード量に比例する」ことを示すための土台。
 *
 * 使い方:
 *   php bench/thin_gen.php <count> <outdir>
 */

$count = (int) ($argv[1] ?? 1);
$dir   = $argv[2] ?? null;

if ($dir === null) {
    fwrite(STDERR, "usage: php thin_gen.php <count> <outdir>\n");
    exit(1);
}

if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
    fwrite(STDERR, "failed to create dir: {$dir}\n");
    exit(1);
}

// 既存の生成物を掃除（ファイル数を変えて測り直すため）
foreach (glob($dir . '/thin_*.php') ?: [] as $f) {
    @unlink($f);
}

for ($i = 0; $i < $count; $i++) {
    $code = "<?php\nfunction thin_fn_{$i}(int \$x): int { return \$x + {$i}; }\n";
    file_put_contents(sprintf('%s/thin_%05d.php', $dir, $i), $code);
}

fwrite(STDERR, "generated {$count} files in {$dir}\n");
