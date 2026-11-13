#!/usr/bin/env bash
#
# Check that a release tag's major version matches the breaking PRs it ships.
#
# Usage: scripts/check-release-breaking.sh <tag>
#
# The release contains the commits in PREV..TAG, where PREV is the newest
# stable tag that is an ancestor of TAG (excluding TAG). Each of those commits
# is mapped to the PRs it belongs to, and PRs labelled `breaking` are counted.
#
# - Breaking PRs and the major version did not go up: fail.
# - Major version went up and no breaking PRs: fail, except 0.x to 1.0.0,
#   which stabilises the API rather than breaking it.
#
# Needs full history and tags, and GH_TOKEN with pull-requests: read.
# Set RELEASE_CHECK_PR_LOOKUP to a command that takes a commit SHA and prints
# "#<number> <title>" for each breaking PR containing it (used by the tests).

set -euo pipefail

if [ $# -ne 1 ]; then
  echo "usage: scripts/check-release-breaking.sh <tag>" >&2
  exit 2
fi
TAG="$1"
HERE=$(cd "$(dirname "$0")" && pwd)

if [[ ! "$TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-.*)?$ ]]; then
  echo "check-release-breaking: '$TAG' is not a vX.Y.Z tag" >&2
  exit 2
fi
git rev-parse --verify --quiet "$TAG^{commit}" >/dev/null || {
  echo "check-release-breaking: unknown tag '$TAG'" >&2
  exit 2
}

PREV=""
if git rev-parse --verify --quiet "$TAG^^{commit}" >/dev/null; then
  PREV=$("$HERE/previous-release-tag.sh" "$TAG^")
fi
if [ -z "$PREV" ]; then
  echo "No earlier stable release tag behind $TAG, so there is no version to compare with."
  exit 0
fi

default_lookup() {
  gh api "repos/$GITHUB_REPOSITORY/commits/$1/pulls" \
    --jq '.[] | select(.merged_at != null) | select(any(.labels[]; .name == "breaking")) | "#\(.number) \(.title)"'
}

LOOKUP="${RELEASE_CHECK_PR_LOOKUP:-}"
BREAKING=""
for sha in $(git log --format=%H "$PREV..$TAG"); do
  if [ -n "$LOOKUP" ]; then
    found=$("$LOOKUP" "$sha")
  else
    found=$(default_lookup "$sha")
  fi
  if [ -n "$found" ]; then
    BREAKING=$(printf '%s\n%s\n' "$BREAKING" "$found")
  fi
done
BREAKING=$(printf '%s\n' "$BREAKING" | { grep -v '^$' || true; } | sort -u)

PREV_VERSION="${PREV#v}"
NEW_VERSION="${TAG#v}"
PREV_MAJOR="${PREV_VERSION%%.*}"
NEW_MAJOR="${NEW_VERSION%%.*}"

if [ -n "$BREAKING" ]; then
  echo "PRs labelled breaking in $PREV..$TAG:"
  echo "$BREAKING"
else
  echo "No PRs labelled breaking in $PREV..$TAG."
fi

if [ -n "$BREAKING" ] && [ "$NEW_MAJOR" -le "$PREV_MAJOR" ]; then
  echo "::error::PRs labelled breaking are in this release, so $TAG must be a new major version (greater than $PREV_MAJOR)."
  exit 1
fi
if [ -z "$BREAKING" ] && [ "$NEW_MAJOR" -gt "$PREV_MAJOR" ]; then
  if [ "$PREV_MAJOR" -eq 0 ] && [ "$NEW_MAJOR" -eq 1 ]; then
    echo "$TAG stabilises the 0.x API; a breaking PR is not required."
    exit 0
  fi
  echo "::error::$TAG is a major version bump from $PREV, but no PR labelled breaking is in this release. Label the breaking PR, or release a minor or patch version."
  exit 1
fi
echo "$TAG is consistent with the breaking PRs since $PREV."
