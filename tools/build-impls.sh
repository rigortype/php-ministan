#!/usr/bin/env bash
#
# 各章タグ（part-00..09, seasoned-01..07）の dev/ ツリーを、読者向けスナップショット
# impls/NN-* として機械生成する。WORKFLOW.md の「再構成」手順の実体。
#
# 使い方:  tools/build-impls.sh
# 生成物:  impls/ 以下（既存は作り直す）。vendor/composer.lock は元から git 管理外なので含まれない。
#
set -euo pipefail
cd "$(dirname "$0")/.."

# タグ : 出力ディレクトリ（impls/ 配下）
MAP=(
  "part-00:00-hello"
  "part-01:01-php-parser"
  "part-02:02-scope"
  "part-03:03-type-system"
  "part-04:04-type-inference"
  "part-05:05-narrowing"
  "part-06:06-reflection"
  "part-07:07-phpdoc"
  "part-08:08-rules-and-levels"
  "part-09:09-tooling"
  "seasoned-01:seasoned/01-configuration"
  "seasoned-02:seasoned/02-arrays"
  "seasoned-03:seasoned/03-generics"
  "seasoned-04:seasoned/04-control-flow"
  "seasoned-05:seasoned/05-byref-stubs"
  "seasoned-06:seasoned/06-performance"
  "seasoned-07:seasoned/07-precision"
)

rm -rf impls
for entry in "${MAP[@]}"; do
  tag="${entry%%:*}"
  dir="${entry#*:}"
  dest="impls/$dir"
  mkdir -p "$dest"

  # 解析器パッケージ本体（先頭の dev/ を剥がして展開）。
  git archive "$tag" dev | tar -x --strip-components=1 -C "$dest"

  # その章で動かすサンプル入力（examples/ サブディレクトリのまま）。
  if git cat-file -e "$tag:examples" 2>/dev/null; then
    git archive "$tag" examples | tar -x -C "$dest"
  fi

  echo "built $dest  <-  $tag"
done

echo
echo "done. 各章は自己完結: cd impls/<dir> && composer install && ./bin/ministan analyse examples/hello.php"
