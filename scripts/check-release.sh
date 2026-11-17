#!/usr/bin/env bash
#
# Run the release guards for a version: the checks release.yml makes before it
# publishes a tag.
#
# Usage: scripts/check-release.sh <tag> [<rev>]
#
#   release.yml:          scripts/check-release.sh "$GITHUB_REF_NAME"
#   release pull request: scripts/check-release.sh v1.2.3 HEAD
#
# <rev> is the commit to check and defaults to <tag>. Pass it to check a
# release pull request before the tag exists: a tag ruleset stops tags being
# moved, so a guard that only fails after the tag is pushed uses up the
# version. With <rev>, the tag must not exist yet.
#
# Checks, all against <rev>:
#   - <tag> is vX.Y.Z (a -suffix marks a pre-release).
#   - SmileIdentity\Version::VERSION in src/Version.php is the tag's version.
#   - CHANGELOG.md has a dated "## [X.Y.Z] - YYYY-MM-DD" heading.
#   - The major version matches the PRs labelled breaking
#     (scripts/check-release-breaking.sh).
#
# Needs full history and tags, and GH_TOKEN with pull-requests: read.

set -euo pipefail

if [ $# -lt 1 ] || [ $# -gt 2 ]; then
  echo "usage: scripts/check-release.sh <tag> [<rev>]" >&2
  exit 2
fi
TAG="$1"
REV="${2:-$1}"
HERE=$(cd "$(dirname "$0")" && pwd)

if [[ ! "$TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-.*)?$ ]]; then
  echo "::error::'$TAG' is not a vX.Y.Z tag."
  exit 1
fi
VERSION="${TAG#v}"

git rev-parse --verify --quiet "$REV^{commit}" >/dev/null || {
  echo "check-release: unknown revision '$REV'" >&2
  exit 2
}

FAILED=0

if [ $# -eq 2 ] && git rev-parse --verify --quiet "refs/tags/$TAG" >/dev/null; then
  echo "::error::Tag $TAG already exists, and tags cannot be moved. Release the next version instead."
  FAILED=1
fi

SRC_VERSION=$("$HERE/sdk-version.sh" "$REV")
if [ -z "$SRC_VERSION" ]; then
  echo "::error::Could not read SmileIdentity\\Version::VERSION from src/Version.php."
  FAILED=1
elif [ "$SRC_VERSION" != "$VERSION" ]; then
  echo "::error::The tag is $TAG but src/Version.php declares $SRC_VERSION. Run scripts/bump-version.sh before tagging."
  FAILED=1
else
  echo "src/Version.php declares $VERSION."
fi

# Escape the dots so 1.2.3 does not match 1x2y3.
VERSION_RE="${VERSION//./\\.}"
if ! git show "$REV:CHANGELOG.md" 2>/dev/null |
  grep -qE "^## \[$VERSION_RE\] - [0-9]{4}-[0-9]{2}-[0-9]{2}$"; then
  echo "::error::CHANGELOG.md has no dated '## [$VERSION] - YYYY-MM-DD' heading. Run scripts/bump-version.sh before tagging."
  FAILED=1
else
  echo "CHANGELOG.md has a dated heading for $VERSION."
fi

"$HERE/check-release-breaking.sh" "$TAG" "$REV" || FAILED=1

if [ "$FAILED" -ne 0 ]; then
  exit 1
fi
echo "$TAG passes the release guards."
