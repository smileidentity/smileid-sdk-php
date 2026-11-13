#!/usr/bin/env bash
#
# Compare the public API with the latest v* release tag and report breaks.
#
# Usage: scripts/check-api-compat.sh <base-ref>
#
# Uses roave/backward-compatibility-check, installed separately in
# tools/backward-compatibility-check so it does not share dependencies with
# the SDK:
#
#   composer install -d tools/backward-compatibility-check
#
# The check compares the newest stable vX.Y.Z tag reachable from <base-ref>
# with HEAD. Breaks that are
# already on <base-ref> (merged but not yet released) are reported as a
# warning, not a failure, so a pull request only fails for breaks it adds.
# Those merged breaks still need a major release; release.yml enforces that.
#
# Exit codes: 0 no new breaks (or no release tag yet), 3 new breaks, other
# values for errors. Needs full history and tags (fetch-depth: 0).

set -euo pipefail

if [ $# -ne 1 ]; then
  echo "usage: scripts/check-api-compat.sh <base-ref>" >&2
  exit 2
fi
BASE="$1"
ROOT=$(cd "$(dirname "$0")/.." && pwd)
BC="$ROOT/tools/backward-compatibility-check/vendor/bin/roave-backward-compatibility-check"

if [ ! -x "$BC" ]; then
  echo "check-api-compat: run 'composer install -d tools/backward-compatibility-check' first" >&2
  exit 2
fi

cd "$ROOT"

# The newest stable release tag that is an ancestor of the base branch.
TAG=$("$ROOT/scripts/previous-release-tag.sh" "$BASE")
if [ -z "$TAG" ]; then
  echo "::notice title=api-compat::No v* release tag yet, so there is no public API to compare with."
  exit 0
fi

BASE_SHA=$(git rev-parse --verify "$BASE^{commit}")
HEAD_SHA=$(git rev-parse --verify "HEAD^{commit}")

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

# compare <to-ref> <out-file>: writes one "[BC] ..." line per break.
compare() {
  local status=0
  echo "Comparing $TAG with $1 ..." >&2
  "$BC" --from="$TAG" --to="$1" --format=markdown >"$WORK/raw.md" 2>"$WORK/log.txt" || status=$?
  if [ "$status" -ne 0 ] && [ "$status" -ne 3 ]; then
    cat "$WORK/log.txt" >&2
    echo "check-api-compat: roave-backward-compatibility-check failed with exit code $status" >&2
    exit "$status"
  fi
  sed -n 's/^ *- \(\[BC\] .*\)$/\1/p' "$WORK/raw.md" | sort -u >"$2"
}

compare "$HEAD_SHA" "$WORK/head.txt"

if [ ! -s "$WORK/head.txt" ]; then
  echo "No breaking changes to the public API since $TAG."
  exit 0
fi

if [ "$BASE_SHA" = "$HEAD_SHA" ]; then
  : >"$WORK/base.txt"
else
  compare "$BASE_SHA" "$WORK/base.txt"
fi

comm -23 "$WORK/head.txt" "$WORK/base.txt" >"$WORK/new.txt"
comm -12 "$WORK/head.txt" "$WORK/base.txt" >"$WORK/existing.txt"

if [ -s "$WORK/existing.txt" ]; then
  echo "::warning title=api-compat::$BASE already has unreleased breaking changes since $TAG. The next release must be a major version."
  echo "Breaking changes already on $BASE since $TAG:"
  sed 's/^/  /' "$WORK/existing.txt"
fi

if [ -s "$WORK/new.txt" ]; then
  echo "Breaking changes added by this change since $TAG:"
  sed 's/^/  /' "$WORK/new.txt"
  exit 3
fi

echo "This change adds no breaking changes to the public API since $TAG."
