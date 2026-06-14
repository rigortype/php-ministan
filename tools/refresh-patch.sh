#!/usr/bin/env bash
#
# 遡及修正のあと、build-impls.sh が「逆適用に失敗」と言った *導入章* のパッチを
# dev/ の現状に合わせて再生成する。git 履歴は書き換えない。
#
# 使い方:  tools/refresh-patch.sh <章slug>      （例: 05-narrowing / 08-rules-and-levels / seasoned-01-configuration）
#
# 仕組み（循環を避け、既に取り込んだ過去の修正も保つ）:
#   一時 git リポジトリに dev/ を置き、現在の patches/ を上から **3way で逆適用** して各章ツリーを
#   復元する。指定章とその前章まで戻したら、両者の差分を patches/<章slug>.patch に書き出す。
#   下側（前章）も現行パッチ列から復元するので、別の章で入れた修正も正しく織り込まれる。
#   タグには一切依存しない（履歴書き換えゼロ）。
#
set -euo pipefail
cd "$(dirname "$0")/.."
REPO="$(pwd)"
slug="${1:?使い方: tools/refresh-patch.sh <章slug 例: 05-narrowing>}"

MAP=(
  "part-00:00-hello" "part-01:01-php-parser" "part-02:02-scope" "part-03:03-type-system"
  "part-04:04-type-inference" "part-05:05-narrowing" "part-06:06-reflection" "part-07:07-phpdoc"
  "part-08:08-rules-and-levels" "part-09:09-tooling" "seasoned-01:seasoned/01-configuration"
  "seasoned-02:seasoned/02-arrays" "seasoned-03:seasoned/03-generics" "seasoned-04:seasoned/04-control-flow"
  "seasoned-05:seasoned/05-byref-stubs" "seasoned-06:seasoned/06-performance" "seasoned-07:seasoned/07-precision"
)

idx=-1
for ((j=0; j<${#MAP[@]}; j++)); do
  d="${MAP[j]#*:}"
  [ "${d//\//-}" = "$slug" ] && { idx=$j; break; }
done
[ "$idx" -ge 1 ] || { echo "未知の章slug、または base(part-00) は対象外: $slug" >&2; exit 1; }

# 一時 git リポジトリに dev/ の現状を置く（vendor 等は除外）。3way 逆適用のため git 管理下にする。
g="$(mktemp -d)"
trap 'rm -rf "$g"' EXIT
cp -R dev "$g/dev"; [ -d examples ] && cp -R examples "$g/examples"
rm -rf "$g/dev/vendor" "$g/dev/composer.lock" "$g/dev/.phpunit.result.cache"
git -C "$g" init -q
git -C "$g" config user.email refresh@local; git -C "$g" config user.name refresh
git -C "$g" add -A; git -C "$g" commit -qm "top (dev/)"
git -C "$g" tag "ch-$((${#MAP[@]}-1))"

# 上から idx-1 章まで、各章のパッチを 3way 逆適用してツリーを復元・タグ付け。
for ((i=${#MAP[@]}-1; i>=idx; i--)); do
  d="${MAP[i]#*:}"; s="${d//\//-}"
  if ! git -C "$g" apply -R --3way -p1 "$REPO/patches/$s.patch" 2>"$g/.apply.err"; then
    cat "$g/.apply.err" >&2
    echo "ERROR: $s.patch の3way逆適用が衝突。$g に競合が残っています。手で解消し、" >&2
    echo "  git -C $g diff ch-$((i-1)) ch-$i -- dev examples > patches/$slug.patch を作ってください。" >&2
    exit 1
  fi
  git -C "$g" add -A; git -C "$g" commit -qm "$s reversed"
  git -C "$g" tag "ch-$((i-1))"
done

git -C "$g" diff "ch-$((idx-1))" "ch-$idx" -- dev examples > "patches/$slug.patch"
echo "refreshed patches/$slug.patch（現行パッチ列から ch-$((idx-1)) と ch-$idx を復元して差分化）"
echo "→ tools/build-impls.sh で impls/ を再生成し直してください。"