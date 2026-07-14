@/Users/n-tsuchida/.codex/RTK.md

# ベンチマーク結果の扱い

- 計測結果は GitHub Actions の x86_64 環境で取得した値を使い、ローカル環境では計測しない。
- `InlinedInternalBench` の `strlen` / `count` の結果をスライドで使う場合は、同じ JIT 設定の基準ループを差し引いた値を使う。
  - `benchStrlen` / `benchStrlenUnqualified` から `benchStrlenBaseline` を差し引く。
  - `benchCount` / `benchCountUnqualified` から `benchCountBaseline` を差し引く。
- 丸める前の計測値で差し引き、最後にスライドの表示精度へ丸める。
- スライドには「基準ループ差引後の推定値」であることを明記する。
