# スライドテンプレート ガイド

新しいプレゼンを作るための共通テンプレートです。`style.css`（デザインシステム）と `index.html`（各レイアウトの雛形）が含まれます。

## 起動

```bash
npm install   # 初回のみ
npm start     # gulp serve で dist/ をビルドして配信（http://localhost:8000 など）
```

`dist/` はビルド生成物（gitignore 済み）なので、`npm start` 実行で自動生成されます。

## フォント

`index.html` の `<head>` で次の 2 書体を読み込み済みです。

- 本文・欧文: **Nunito**
- 見出し・丸ゴシック: **M PLUS Rounded 1c**
- 日本語フォールバック: Noto Sans JP → Hiragino → Yu Gothic → Meiryo
- コード: SF Mono / Menlo / Consolas（等幅フォールバック）

## 色（デザイントークン）

`style.css` の `:root` で定義。値を変えればデッキ全体の配色が切り替わります。

| 変数 | 既定値 | 用途 |
| --- | --- | --- |
| `--accent` | `#134aa9` | アクセント（リンク・強調・帯） |
| `--text` | `#111111` | 本文 |
| `--base-red` | `#b91c1c` | 警告・NG |
| `--heading-navy` | `#1a2f4a` | 見出し |

## レイアウトの早見表

`index.html` に全パターンの実例が入っています。不要なものは削除して使ってください。

| やりたいこと | マークアップ |
| --- | --- |
| タイトル表紙 | `<section class="title-slide" data-background-image="...">` |
| 目次（自動生成） | `<section id="toc"><ol id="toc-list"></ol>`（h2 を自動収集） |
| 章区切り（帯見出し） | `<section class="section-divider"><h1>…</h1>` |
| 章つなぎ（本文大きめ） | `<section class="lead-bridge">` |
| ゴール / 箇条書き強調 | `<section class="goal-slide">` + `<ul class="emph-middle-list">` |
| 結論を大きく中央表示 | `<section class="center-punch">` + `<p class="answer">` |
| 図 + キャプション | `<figure class="slide-figure">` |
| 図を横並び | `<div class="figure-row">` / `<div class="compare-row">` |
| 左テキスト・右画像 | `<div class="text-image-row">` + `.ti-text` / `.ti-image` |
| 2カラムグリッド | `<div class="columns">` + `.col` |
| 比較表 | `<table class="compare-table">`（注目行に `class="highlight"`） |
| OK / NG | `<div class="checklist">` + `.ok` / `.ng` + `.mark` |
| プロフィール | `<div class="profile profile--stacked">` |

## 文字強調ユーティリティ

| クラス | 効果 |
| --- | --- |
| `.key` | アクセント色（**太字を使わない強調**） |
| `.emph-1_2x` / `.emph-1_5x` / `.emph-2x` | 文字サイズ 1.2 / 1.5 / 2 倍 |
| `.under-line` | 下線 |
| `.muted` / `.small` / `.small-font` | 弱める / 小さく |

## ルール（重要）

> 本文中では太字（bold）を使わない。見出し（h1〜h3）の太字は可。

強調したいときは太字ではなく `.key`（色）や `.emph-*`（サイズ）を使ってください。

> メリットは青（`--accent`）、デメリットは赤（`--base-red`）で表す。

良い/効く側は `.ok`（青）、悪い/効かない側は `.ng`（赤）を使ってください（`.checklist` / `.legend` 共通）。

## PDF 化

`?print-pdf` を付けて開くと PDF 用の上書き（`style.css` 末尾）が効きます。box-shadow 除去・スライド番号の黒字化・タイトルスライドの位置固定を含みます。
