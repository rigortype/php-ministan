#!/usr/bin/env bash
#
# 読者向けスナップショット impls/NN-* を、パッチ列 patches/*.patch から機械生成する。
# WORKFLOW.md の「再構成」手順の実体。
#
# モデル: dev/（ライブツリー＝最終章の完成形）を起点に、各章のパッチを **逆適用** して
#         一段ずつ前の章へ巻き戻しながらスナップショットを書き出す。タグ（git 履歴）には
#         依存しない ―― だから遡及修正は「dev/ を直し、導入章のパッチを更新する」だけで済み、
#         履歴の書き換え（rebase・再タグ・force-push）は不要。
#
# 使い方:  tools/build-impls.sh
# 生成物:  impls/ 以下（既存は作り直す）。vendor/composer.lock は含めない（読者は composer install）。
#
set -euo pipefail
cd "$(dirname "$0")/.."
REPO="$(pwd)"

# 章順（tag ラベル : impls 出力ディレクトリ）。先頭 part-00 が base（逆適用の終端＝パッチ無し）。
# パッチ名は出力ディレクトリの slug（'/' を '-' に）に対応: wonderland/05-narrowing -> patches/wonderland-05-narrowing.patch
MAP=(
  "part-00:wonderland/00-hello"
  "part-01:wonderland/01-php-parser"
  "part-02:wonderland/02-scope"
  "part-03:wonderland/03-type-system"
  "part-04:wonderland/04-type-inference"
  "part-05:wonderland/05-narrowing"
  "part-06:wonderland/06-reflection"
  "part-07:wonderland/07-phpdoc"
  "part-08:wonderland/08-rules-and-levels"
  "part-09:wonderland/09-tooling"
  "seasoned-01:looking-glass/01-configuration"
  "seasoned-02:looking-glass/02-arrays"
  "seasoned-03:looking-glass/03-generics"
  "seasoned-04:looking-glass/04-control-flow"
  "seasoned-05:looking-glass/05-byref-stubs"
  "seasoned-06:looking-glass/06-performance"
  "seasoned-07:looking-glass/07-precision"
)

# dev/ の現在地を作業コピーへ（vendor/lock/cache は生成物なので除外）。
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
cp -R dev "$work/dev"
[ -d examples ] && cp -R examples "$work/examples"
rm -rf "$work/dev/vendor" "$work/dev/composer.lock" "$work/dev/.phpunit.result.cache"

rm -rf impls

# 末尾（最終章＝dev/ の現状）から先頭へ。各章を書き出してから、その章のパッチを逆適用して前章へ。
for ((i=${#MAP[@]}-1; i>=0; i--)); do
  entry="${MAP[i]}"
  tag="${entry%%:*}"
  dir="${entry#*:}"
  slug="${dir//\//-}"
  dest="impls/$dir"
  mkdir -p "$dest"

  cp -R "$work/dev/." "$dest/"
  [ -d "$work/examples" ] && cp -R "$work/examples" "$dest/examples"
  echo "built $dest  <-  $tag"

  if [ "$i" -gt 0 ]; then
    patch="patches/${slug}.patch"
    if [ ! -f "$patch" ]; then
      echo "ERROR: $patch が無い。patches/series と MAP を確認。" >&2
      exit 1
    fi
    if ! ( cd "$work" && git apply -R -p1 "$REPO/$patch" ); then
      echo >&2
      echo "ERROR: $patch の逆適用に失敗しました。" >&2
      echo "  dev/ の変更が、この章のパッチが導入する行と衝突しています（遡及修正の典型）。" >&2
      echo "  → tools/refresh-patch.sh '${slug}' でこの章のパッチを dev/ に合わせて更新してください。" >&2
      exit 1
    fi
  fi
done

echo
echo "done. 各章は自己完結: cd impls/<dir> && composer install && ./bin/ministan analyse examples/hello.php"