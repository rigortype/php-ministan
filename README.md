# php-ministan

> Writing a PHP static analyzer: Step by Step, from one syntax error.

**ministan** は、[PHPStan](https://github.com/phpstan/phpstan) のエッセンスを最小核に蒸留し、
[nikic/php-parser](https://github.com/nikic/PHP-Parser) を基盤に**一から再構築する**ためのチュートリアルです。
[chibivue](https://github.com/chibivue-land/chibivue) と
[chibirigor](https://github.com/rigortype/chibirigor) のモデルに倣い、
「動くものを少しずつ育てる」段階的アプローチで、PHP の静的解析・型チェッカー・型推論器を作ります。

📖 **本を読む → [`book/ja/`](book/ja/README.md)**（まえがき・二部構成の地図・読み方はこちら）

- 対象: **PHP 8.3+**
- 基盤: **nikic/php-parser ^5**、PHPDoc は **phpstan/phpdoc-parser**（Part 7〜）
- CLI: `ministan analyse <file>`（解析）/ `ministan annotate <file>`（推論型の表示・Part 4〜）

## 設計哲学（non-rejecting）

chibirigor 流に、**構文エラーでないコードは受理する**。不明な型は `mixed` に縮退させ、
偽陽性を出さない。これが PHPStan のレベル段階制と `mixed` の意味論にそのまま接続します。

## 構成

```
book/ja/        オンラインブック本文（Part 0–9 + Seasoned）
dev/            ライブ実装ツリー（git 履歴で章ごとに育てる本体）
examples/       解析対象のサンプル PHP
impls/NN-*/     各章の完成スナップショット（tools/build-impls.sh が git タグから生成）
tools/          スナップショット生成スクリプト
```

執筆・開発は単一の `dev/` ツリーで行い、章境界を git タグ（`part-00`…`part-09`,
`seasoned-01`…）で刻みます。読者向けの `impls/NN-*`（自己完結した composer プロジェクト）は、
その履歴から **`tools/build-impls.sh`** で機械生成した成果物です（再生成も同スクリプトで）。
詳細は [WORKFLOW.md](WORKFLOW.md)。

```console
# ライブ最新で動かす
$ cd dev && composer install && cd ..
$ dev/bin/ministan analyse examples/hello.php

# 任意の章のスナップショットで動かす（自己完結）
$ cd impls/02-scope && composer install
$ ./bin/ministan analyse examples/with-var-dump.php
```

## カリキュラム

### The Little ministan（基礎編）

| Part | テーマ | 完成する機能 |
|------|--------|--------------|
| 0 | 全体像と Hello World | 解析パイプラインを通し、構文エラーを報告する |
| 1 | PHP-Parser と AST | 構文ベースのルール（`Rule` インターフェイス） |
| 2 | Scope と変数追跡 | 未定義変数の使用検出 |
| 3 | 型システムの基礎 | `Type` インターフェイスと定数型 |
| 4 | 型推論 | `annotate` で推論型を表示 |
| 5 | Union と絞り込み | `instanceof`/`is_*` による narrowing |
| 6 | リフレクション | 戻り値推論・未定義メンバ検出 |
| 7 | PHPDoc | `@param`/`@return`/`@var` を型に変換 |
| 8 | ルールとレベル | 型互換チェックとレベル 0–max |
| 9 | ツール化 | 設定・整形・baseline・CI 出力 |

### The Seasoned ministan（応用編）

| 章 | テーマ | 完成する機能 |
|----|--------|--------------|
| S1 | 設定と拡張 | NEON 設定・ignoreErrors・カスタムルール |
| S2 | 配列を深める | constant array shape・配列アクセス・foreach 要素型 |
| S3 | ジェネリクス | `@template`・`GenericObjectType`・型パラメータ置換 |
| S4 | 制御フローと高度な narrowing | 早期 return・`assert`・`match` の腕 |
| S5 | 参照渡しとスタブ | by-ref 出力引数・スタブによるシグネチャ補完 |
| S6 | パフォーマンス | 結果キャッシュ |
| S7 | 推論と検査の精度向上 | match 式結果型・union 部分型吸収・ループ型ワイドニング・名前付き引数照合 |

## License

This repository contains two kinds of material, each covered by its own
copyright and license.

### Prose and figures

The book text, figures, and other documentation under `book/ja/`.

Copyright © 2026 USAMI Kenta. Licensed under
[Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0)](https://creativecommons.org/licenses/by-sa/4.0/);
see [`LICENSE`](LICENSE) for the full text.

[![CC BY-SA 4.0](cc-by-sa.svg)](https://creativecommons.org/licenses/by-sa/4.0/)

> php-ministan © 2026 USAMI Kenta is licensed under [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/).

### Source code

The code under `dev/`, `impls/`, and `examples/` is a derivative work of
[phpstan/phpstan](https://github.com/phpstan/phpstan) and
[phpstan/phpstan-src](https://github.com/phpstan/phpstan-src), distributed
under their original MIT License:

```
MIT License

Copyright (c) 2016 Ondřej Mirtes
Copyright (c) 2025 PHPStan s.r.o.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
