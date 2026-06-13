# 執筆・再構成ワークフロー

このリポジトリは「章ごとの完成スナップショットを手で維持する」方式を**採らない**。
代わりに、単一のライブツリーを git 履歴で育て、読者向けスナップショットは履歴から
**機械生成**する。これにより、コードの重複維持コストをゼロにする。

## 役割

| パス | 役割 |
|------|------|
| `dev/` | **ライブ実装。**唯一の真実。ここをインクリメンタルに育てる |
| `book/ja/` | 本文。`dev/` の現在地を解説する |
| `examples/` | 解析対象のサンプル（章をまたいで共有） |
| `impls/NN-*/` | **生成物。**読者が各章で動かすためのスナップショット。手で書かない |

## 開発サイクル（執筆中）

1. `dev/` でその章の機能を実装し、本文 `book/ja/NN-*.md` を書く
2. スナップショットのことは**考えない**
3. 章が一区切りしたら、章境界を git に刻む:

   ```console
   $ git -C . add -A
   $ git commit -m "Part N: <テーマ>"
   $ git tag part-NN
   ```

「初期状態＋パッチ列」は、この commit 列（`part-00 → part-01 → …`）そのもの。

## 再構成（執筆後・バッチ）

各タグの `dev/`（＋その時点の `examples/`）を `impls/NN-*/` として書き出す。
この生成は **`tools/build-impls.sh`** に集約済み:

```console
$ tools/build-impls.sh        # impls/ を全タグから作り直す（既存は破棄して再生成）
```

中身は単純で、各タグについて:

```console
$ git archive part-02 dev | tar -x --strip-components=1 -C impls/02-scope
$ git archive part-02 examples | tar -x -C impls/02-scope
```

`vendor/`・`composer.lock` は元から git 管理外なので含まれない（読者は各章で
`composer install`）。`impls/` は**生成物**なので手で編集しない ―― 直したいときは
`dev/` を直し、タグを打ち直してから再生成する。

> 章間の差分（読者に見せるパッチ）が欲しければ `git diff part-01 part-02 -- dev` で取れる。
