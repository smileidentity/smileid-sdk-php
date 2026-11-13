#!/usr/bin/env bash
#
# Print the newest stable release tag (vX.Y.Z) that is an ancestor of <ref>,
# including <ref> itself. Pre-release tags such as v1.2.0-rc.1 are skipped.
# Prints nothing (and exits 0) if there is no such tag.
#
# Usage: scripts/previous-release-tag.sh <ref>
#
# To find the release before tag vX.Y.Z, pass "vX.Y.Z^".

set -euo pipefail

if [ $# -ne 1 ]; then
  echo "usage: scripts/previous-release-tag.sh <ref>" >&2
  exit 2
fi

REF="$1"
git rev-parse --verify --quiet "$REF^{commit}" >/dev/null || {
  echo "previous-release-tag: unknown ref '$REF'" >&2
  exit 2
}

while :; do
  TAG=$(git describe --tags --abbrev=0 --match 'v[0-9]*' "$REF" 2>/dev/null) || exit 0
  # The commit may carry several tags (say v2.0.0-rc.1 and v2.0.0); take the
  # highest stable one.
  for t in $(git tag --points-at "$TAG" --sort=-v:refname); do
    if [[ "$t" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
      echo "$t"
      exit 0
    fi
  done
  # Not stable; keep looking behind it. Stop at a root commit.
  git rev-parse --verify --quiet "$TAG^^{commit}" >/dev/null || exit 0
  REF="$TAG^"
done
