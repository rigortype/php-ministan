# 図版（SVG）ガイド — 視覚言語・カラーマップ・検証

両エディション共通の図版規約。全体の作業規約は [/CLAUDE.md](../CLAUDE.md)。

## 置き場・命名

- `book/<edition>/figures/`（`book/ja/figures/`・`book/en/figures/`）。
- 命名: 基礎編 `NN-slug.svg`、応用編 `sN-slug.svg`。各図 **light ＋ `-dark` の2版**。
- 図キャプションは図に内蔵（自前で持つ）ので、本文側に markdown キャプションは付けない。

## 視覚言語

- フォント: 本文系は `Inter` 系、コード/識別子は等幅 `JetBrains Mono`、キャプションは
  `Source Serif 4` italic。`viewBox` 幅は **720**（既存図に合わせる）。
- 矢印 marker は `id="arr"` の三角（`fill="context-stroke"`）。罫線 `─`/`―` は使わない。
- text 内の `<`/`>` は `&lt;`/`&gt;`。約物は本文同様（日本語版 ` —— `／英語版 ` — `）。
- **配色は意味で固定**: 型=青・不適合=赤・絞り込み=緑・Maybe/型変数=琥珀。

## light → dark カラーマップ

dark は light から機械生成（Python の `re.sub` で一括。hex は相互非包含で安全）。**新規図**はこの対応で:

| 役割 | light | dark |
|------|-------|------|
| 背景 | `#F6F4EE` | `#1A1D23` |
| 箱 | `#FFFFFF` | `#22262E` |
| 枠 | `#C9C4B7` | `#454A54` |
| 主文字 | `#0E1116` | `#F4F0E2` |
| 淡文字 | `#585F6B` | `#B6BAC2` |
| 青（箱/線） | `#E1ECF8` / `#2D5C9E` | `#1F2C3F` / `#8AB6E6` |
| 緑 | `#E1EEDD` / `#2E6F3F` | `#1F2F25` / `#86C28E` |
| 琥珀 | `#F4E6C7` / `#8A5F12` | `#3A2E1C` / `#E4B25A` |
| 赤 | `#F1E2DE` / `#7A2A2A` | `#3A2622` / `#E08C84` |
| 強調箱 | `#EFEBE0` / `#2A2F38` | `#2A2E37` / `#E6E1D2` |

> 既存図は赤などに微差がある（例 `00-pipeline` は light `#F8E5E2`/`#B5302B`・dark `#3A2422`/`#ED8B82`）。
> **既存図を改変するときは、その図の実際の色をそのまま使う**（上表は新規図の基準）。

## 英語版の図 ＝ JP からテキストのみ英訳

英語版の図は日本語版の同名 SVG を基に、**テキストノードだけ英訳し色・座標は流用**する（light は
light、dark は dark をそれぞれ複製）。注意点:

- 英語は日本語より**横長になりがち**。箱幅に対する text のはみ出しに注意（必要なら文言を短く）。
- `aria-label`/`role="img"` は英語に。フォントスタックの日本語フォントフォールバックは外してよい。
- **HTML/SVG 属性は straight quote**（本文の curly 一括変換から保護する。[/CLAUDE.md](../CLAUDE.md)）。

## 埋め込み（`<picture>`）

図は自前キャプションを持つので **`<picture>`＋`alt` のみ**（markdown キャプションなし）。GitHub で
light/dark を切り替える:

```html
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="../figures/NN-slug-dark.svg">
  <img src="../figures/NN-slug.svg" alt="…（図の内容を説明）">
</picture>
```

- パスは**両編とも** `../figures/...`（`book/<ed>/<vol>/NN.md` ＝深さ3）。
- **属性は必ず straight quote**（`media="…"`/`srcset="…"`/`src="…"`/`alt="…"`）。curly 化すると画像が
  読み込めない。

## 検証（必ず）

```console
$ inkscape --export-type=png --export-width=1000 -o /tmp/out.png in.svg   # PNG 化 → 必ず目視
$ xmllint --noout in.svg
$ grep -c '─\|―' in.svg                 # 0
$ grep -oP '\S——|——\S' in.svg          # JP 図: 前後スペース欠落＝0（行末ソフトラップ除く）
```

目視では**重なり・はみ出し・tofu（豆腐＝字形欠落）**を確認する。

## 既知の落とし穴

- **箱の裏にラベルが隠れる**: 帯ラベルを箱と同じ x・近い y に置くと、後から描く箱に塗り潰されて
  見えなくなる（`00-pipeline` の下段ラベルで遭遇。日本語は短く完全に隠れ、英語は長くはみ出した）。
  ラベルは箱の描画範囲の外へ置くか、不要なら削る。
