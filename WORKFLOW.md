# 執筆・再構成ワークフロー

このリポジトリは「章ごとの完成スナップショットを手で維持する」方式を**採らない**。
代わりに、単一のライブツリーを育て、読者向けスナップショットは**章間パッチ列**から
**機械生成**する。これにより、コードの重複維持コストをゼロにする。

> **2026-06 にモデルを変更**: 以前は「章タグ（part-00..）の `dev/` を `git archive` で書き出す」
> 方式だったが、**過去の章を直すとタグの打ち直し＝履歴の書き換え（rebase・force-push）が必要**だった。
> 現在は **`patches/` の章間差分を `dev/` から逆適用** して `impls/` を生成する。遡及修正は
> 「`dev/` を直す＋導入章のパッチを `refresh-patch.sh` で更新」だけで済み、**git 履歴は書き換えない**。

## 役割

| パス | 役割 |
|------|------|
| `dev/` | **ライブ実装。**唯一の真実（＝最終章の完成形）。ここをインクリメンタルに育てる |
| `book/ja/` | 本文。`dev/` の現在地を解説する。**`impls/` には含めない**（読者は HEAD の本文を読む） |
| `examples/` | 解析対象のサンプル（章をまたいで共有） |
| `patches/` | **章間の前進差分**（`patches/series` が順序）。`impls/` 生成の入力。版管理する |
| `impls/NN-*/` | **生成物。**読者が各章で動かすためのスナップショット。手で書かない |

## モデル（reverse-diff・dev/ が唯一の真実）

- `patches/<章>.patch` は「前章 → その章」の前進差分。並びは `patches/series`。
- `impls/` は **`dev/`（＝最終章）を起点に、各章のパッチを上から逆適用** して一段ずつ前章へ
  巻き戻しながら書き出す。`dev/` の現状がそのまま末尾章に、最初のパッチを逆適用し終えた状態が
  先頭章（`00-hello`）になる。
- 不変条件: **`dev/` ＝ パッチ列を全部畳んだ最終形**。`tools/build-impls.sh` はこれを前提に動く。

## 開発サイクル（新章を書く）

1. `dev/` でその章の機能を実装し、本文 `book/ja/NN-*.md` を書く（スナップショットは考えない）。
2. 章が一区切りしたら、コミットし、**その章のパッチを記録**する:

   ```console
   $ git add <個別ファイル>            # -A は避ける（並行 WIP を巻き込まない）
   $ git commit -m "Part N: <テーマ>"
   $ git tag part-NN                   # タグは「不変の読み取り専用アンカー」として残す（打ち直さない）
   $ git diff part-(N-1) part-NN -- dev examples > patches/NN-<slug>.patch
   $ echo "NN-<slug>.patch" >> patches/series
   ```

   タグは打つが、**二度と動かさない**（パッチ生成・refresh の参照点として読むだけ）。
3. `tools/build-impls.sh` で `impls/` を再生成。

## 遡及修正（過去の章のコードを直す）← 履歴書き換え不要

1. **`dev/` を直す**（最終形を1回直すだけ）。必要なら `cd dev && ./vendor/bin/phpunit` と
   dogfood `php dev/bin/ministan analyse dev/src` を確認。
2. `tools/build-impls.sh` を回す。直した行が**ある章のパッチが導入する行**だと、その章の
   逆適用でこう止まる:

   ```
   ERROR: patches/05-narrowing.patch の逆適用に失敗しました。
     → tools/refresh-patch.sh '05-narrowing' でこの章のパッチを dev/ に合わせて更新してください。
   ```

3. 言われた章のパッチを更新して、再生成:

   ```console
   $ tools/refresh-patch.sh 05-narrowing      # 現行パッチ列から前後章を復元し、差分を取り直す
   $ tools/build-impls.sh                      # impls/ を再生成
   ```

   `refresh-patch.sh` は現行 `patches/` を3wayで逆適用して章ツリーを復元するので、**別の章で
   入れた過去の修正も保たれる**。複数章にまたがる修正なら、**下の章から順に** refresh する。
4. 直したファイルが**本文のコードブロックに引用**されていれば、その本文も併せて直す
   （本文は `impls/` 非対象なので HEAD で正しくしておけば足りる）。

## ツール

| コマンド | 役割 |
|----------|------|
| `tools/build-impls.sh` | `dev/` ＋ `patches/` から `impls/` を全再生成（逆適用）。既存 `impls/` は作り直す |
| `tools/refresh-patch.sh <章slug>` | 遡及修正後、衝突した導入章のパッチを `dev/` に合わせて再生成（履歴は触らない） |

`vendor/`・`composer.lock` は git 管理外なので `impls/` に含まれない（読者は各章で
`composer install`）。`impls/` は**生成物**なので手で編集しない。

> 章間の差分（読者に見せるパッチ）は `patches/<章>.patch` そのもの。歴史的な参照は
> `git diff part-01 part-02 -- dev` でも取れる（タグは不変アンカーとして残してある）。
