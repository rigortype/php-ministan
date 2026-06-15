# CLAUDE.md — php-ministan 作業ガイド

このリポジトリで作業する人・AI のための**中核ガイド**。まずここを読む。詳細は各専門ドキュメント
（末尾の「ドキュメント地図」）へ。本書は CC BY-SA 4.0（文章・図）／MIT（コード）。

## これは何か

**ministan** は [PHPStan](https://github.com/phpstan/phpstan) のエッセンスを最小核に蒸留し、
[nikic/php-parser](https://github.com/nikic/PHP-Parser) を基盤に PHP 静的解析器を **一から育てる**
チュートリアル本＋実装（[chibivue](https://github.com/chibivue-land/chibivue) /
[chibirigor](https://github.com/rigortype/chibirigor) のモデル）。二部構成:

- **ministan in PHP's Wonderland**（基礎編・Part 0–9）= パース→スコープ→型推論→絞り込み→ルール→報告。
- **ministan Through PHP's Looking-Glass**（応用編・S1–S7）= 配列・ジェネリクス・制御フロー等の肉付け。

読者向け本は `book/ja/`（原典・日本語）と `book/en/`（英語・トランスクリエーション、進行中）。
実装は `dev/`（ライブ）と `impls/`（章スナップショット・**生成物**）。PHP 8.3+。

## 壊してはいけない軸（最重要）

- **non-rejecting**: 構文エラーでないコードは受理。分からない型は `mixed` に縮退。確信が持てなければ黙る。
  **偽陽性を出さない**のが最優先。「もっと網羅的に／厳しく検出せよ」「偽陽性を恐れず厳しく」は
  **軸の否定＝禁じ手**。
- **最小核の教材**: PHPStan が本物の副読本。機能パリティは目的でない。**意図的な簡略化は欠陥ではない**
  （各章末「まとめ／簡略化」に記録済み）。「PHPStan 並みの網羅性／精度を足せ」ではない。
- **dogfood が品質の柱**: ministan が自分自身を偽陽性ゼロで解析できること。
- 型理論は前提にしない（やさしい基礎編／形式は応用編で少しだけ）。TAPL 等は任意の副読本。

## リポジトリ構成

| パス | 役割 |
|------|------|
| `book/ja/` | 読者向け本・**日本語（原典）**。`README`(まえがき)・`glossary`・`wonderland/NN`・`looking-glass/NN`・`figures/`・`.reviews/`(査読台帳・gitignore) |
| `book/en/` | 読者向け本・**英語（トランスクリエーション）**。同構成＋`STYLE.md`（英語版執筆規約） |
| `dev/` | **ライブ実装＝唯一の真実**（最終章の完成形）。ここをインクリメンタルに育てる |
| `impls/<vol>/NN-*/` | 各章の完成スナップショット（**生成物・手で編集しない**）。自己完結 composer プロジェクト |
| `examples/` | 解析対象のサンプル PHP（章をまたいで共有） |
| `patches/` | 章間の前進差分（`patches/series` が順序）。`impls/` 生成の入力 |
| `tools/` | `build-impls.sh`（生成）・`refresh-patch.sh`（遡及修正） |
| `.claude/skills/ministan-review/` | 多観点レビュー・スキル（後述） |

- `impls/` は `dev/`＋`patches/` の **reverse-diff** で機械生成。**手で編集しない。**
  生成・遡及修正の全手順は **[WORKFLOW.md](WORKFLOW.md)**（dev/ を直す→`build-impls.sh`→衝突章を
  `refresh-patch.sh`→再生成。git 履歴は書き換えない。タグ `part-NN`/`seasoned-NN` は不変アンカー）。

## 2つのエディション

- `book/ja/` = 原典。`book/en/` = **トランスクリエーション**（逐語訳でなく英語ネイティブに再執筆）。
  両編は**同一コードベース**（`dev/`・`impls/`）を指す＝言語別コードフォークは無い。
- **コードの正典は英語**（識別子・型名・CLI 出力・コメント／docblock すべて英語。移行済み）。
  各編は**本文中の抜粋コメントだけ**自言語に翻訳（JP=日本語・EN=英語）。
- 日本語版だけが**日本語限定の型システム入門書**（遠藤『型システムのしくみ』）への参考書ガイドを持つ。
  英語版は TAPL＋トピック別英語資料に限り、対応が無い箇所は正直に明記する。
- 執筆規約 → **[book/ja/STYLE.md](book/ja/STYLE.md)**（日本語版）・**[book/en/STYLE.md](book/en/STYLE.md)**（英語版）。

## 約物（全角ダッシュ）の罠 — 実害あり・必読

- 表記: **日本語版＝2倍ダーシ ` —— `**（U+2014 を2つ・前後スペース）。**英語版＝単一 em dash ` — `**
  （U+2014 を1つ・前後スペース）。`―`（U+2015）は使わない。
- **`perl -CSD` で置換しないこと**: `-e` 内のリテラル多バイトがバイト列扱いで文字化けし、shell 経由の
  `\\1` が制御文字 `\x01` 化して**捕捉文字を消す破損**を起こす（実際に S4/S7 破損→revert で復旧）。
- 正しいやり方は **Python**（置換は必ずラムダで。`'\\1...'` 文字列は octal 化で壊れる）:
  ```python
  import re
  s = s.replace('―', '—')                                       # U+2015 → U+2014
  s = re.sub(r'(\S)——(\S)', lambda m: m.group(1)+' —— '+m.group(2), s)  # JP: 無スペースに前後スペース
  ```
- **引用符の curly 一括変換は code span と HTML 属性を保護する**（`<picture>` の `src="…"`/`alt="…"` が
  curly 化すると画像が壊れる）。Python で、バックティック span と `<...>` タグを除外してから変換。
- 検証: `grep -ro '―'`=0／片側欠落 `grep -roP '(?<! )——|——(?! )'`=0（JP）・`grep -oP '\S—|—\S'`=0（EN・
  code/HTML 除く）／`grep -lP '[\x00-\x08]'`=なし。行末・行頭のソフトラップ（改行が空白になる）は許容。

## コードリンク方針

本文のコードリンクは **その章のスナップショット** `impls/<vol>/NN-*` を指す（ライブ `dev/` ではない）。
各章は自分の到達点を記述するため、**HEAD の `dev/` と本文が食い違っても、それは正しい**
（snapshot drift ではない）。深さは `book/<ed>/<vol>/NN.md`（深さ3）から `../../../impls/<vol>/NN-*`、
図は `../figures/`、用語集は `../glossary.md`。

## 図版

SVG・light/dark の2版・`<picture>` で埋め込み・**inkscape で必ず目視**。視覚言語・light→dark カラーマップ・
埋め込み・検証・既知の落とし穴は **[docs/figures.md](docs/figures.md)**。英語版の図は JP 図から
**テキストのみ英訳**（色はそのまま流用）。

## 散文編集後の検証（毎回）

1. **リンク実在**: 本文の `](path)` が実在するか（未執筆章への前方リンクは進行中なら許容）。
2. **約物**: 上記「罠」の検証 grep。
3. **コード/例を変えたときだけ**: `cd dev && ./vendor/bin/phpunit` ＋ dogfood
   `php dev/bin/ministan analyse dev/src`（緑＝[OK]）。**散文だけの編集はテスト不要**（`dev/` 不変なら自明）。
4. 図を足したら inkscape で PNG 化→目視（[docs/figures.md](docs/figures.md)）。

## コミットの流儀

- **論理単位ごと**・**日本語メッセージ**。
- **push はユーザーが言うまでしない**（現在の作業ブランチは `master`）。
- `git add <個別ファイル>`（**`-A` 禁止**＝並行セッションの WIP を巻き込まない）。

## 査読（多観点レビュー）

`.claude/skills/ministan-review`。**4レイヤー（真→伝→読→整、レイヤー内並列・間順次、編集/校正は必ず最後）**。
日本語版バッテリー＋**英語版モード**（read-feel 最優先の英語レンズ ⑭⑮⑯）。**収束指摘（≥2 が独立に
同じ箇所）＝本物の穴**を優先反映。軸を壊す指摘（機能パリティ／網羅性要求）は禁じ手。所見は
`book/<ed>/.reviews/_<lens>-review.md`（gitignore）。

## 一次資料（read-only・絶対に編集しない／環境依存パス）

- 実 PHPStan 実装: `/Users/megurine/repo/php/phpstan-src`（`src/Analyser`・`src/Type`・`src/Rules`・`conf/config.levelN.neon`）
- PHPStan 仕様（ドキュメント）: `/Users/megurine/repo/site/phpstan`（`website/src/**`・`website/errors/*`）
- 用語・カタカナ台帳（姉妹本 rigor）: `/Users/megurine/repo/site/rigor.typedduck.fail/docs/ja/`

## ドキュメント地図

- [WORKFLOW.md](WORKFLOW.md) — `dev/`→`impls/` の生成・遡及修正（patch / reverse-diff モデル）
- [book/ja/STYLE.md](book/ja/STYLE.md) — 日本語版 執筆規約（用語・カタカナ長音・参考書メモ）
- [book/en/STYLE.md](book/en/STYLE.md) — 英語版 執筆規約（transcreation・用語・約物・参考書再設計）
- [docs/figures.md](docs/figures.md) — 図版の視覚言語・カラーマップ・埋め込み・検証
- `.claude/skills/ministan-review/SKILL.md` — 多観点レビューの方法論（凍結）
