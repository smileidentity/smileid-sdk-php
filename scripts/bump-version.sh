#!/usr/bin/env bash
#
# Bump the SDK version and cut the changelog's Unreleased section.
#
# Usage: scripts/bump-version.sh <major|minor|patch>
#
# Updates:
#   - src/Version.php           (release.yml checks the tag against this)
#   - tests/VersionTest.php     (asserts the VERSION constant)
#   - example/composer.json     (path repository version pin)
#   - CHANGELOG.md              (moves Unreleased to "## [x.y.z] - YYYY-MM-DD"
#                                and updates the compare links)
#
# Prints the new version on stdout. Fails without changing anything if there
# is nothing unreleased or a file is not in the expected shape.
#
# Run from anywhere; paths are resolved from the repository root. Set
# BUMP_VERSION_ROOT to operate on another tree (used by the tests) and
# BUMP_VERSION_DATE to fix the release date.

set -euo pipefail

die() {
  echo "bump-version: $*" >&2
  exit 1
}

[ $# -eq 1 ] || die "usage: scripts/bump-version.sh <major|minor|patch>"
PART="$1"
case "$PART" in
  major | minor | patch) ;;
  *) die "unknown part '$PART'; expected major, minor or patch" ;;
esac

ROOT="${BUMP_VERSION_ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
DATE="${BUMP_VERSION_DATE:-$(date -u +%Y-%m-%d)}"
REPO_URL="https://github.com/smileidentity/smileid-sdk-php"

VERSION_FILE="$ROOT/src/Version.php"
VERSION_TEST="$ROOT/tests/VersionTest.php"
EXAMPLE_COMPOSER="$ROOT/example/composer.json"
CHANGELOG="$ROOT/CHANGELOG.md"

for f in "$VERSION_FILE" "$VERSION_TEST" "$EXAMPLE_COMPOSER" "$CHANGELOG"; do
  [ -f "$f" ] || die "missing $f"
done

# --- Current version -------------------------------------------------------

CURRENT=$(sed -n "s/^[[:space:]]*public const VERSION = '\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)';[[:space:]]*\$/\1/p" "$VERSION_FILE")
[ -n "$CURRENT" ] || die "could not read a plain x.y.z VERSION constant from src/Version.php"
[ "$(printf '%s\n' "$CURRENT" | wc -l | tr -d ' ')" = "1" ] || die "found more than one VERSION constant in src/Version.php"

IFS=. read -r MAJOR MINOR PATCH <<EOF
$CURRENT
EOF

case "$PART" in
  major) NEW="$((MAJOR + 1)).0.0" ;;
  minor) NEW="$MAJOR.$((MINOR + 1)).0" ;;
  patch) NEW="$MAJOR.$MINOR.$((PATCH + 1))" ;;
esac

# --- Validate every file before writing any of them ------------------------

grep -qF "assertSame('$CURRENT', Version::VERSION)" "$VERSION_TEST" \
  || die "tests/VersionTest.php does not assert '$CURRENT'"
grep -qF "\"smileid/usesmileid\": \"$CURRENT\"" "$EXAMPLE_COMPOSER" \
  || die "example/composer.json does not pin smileid/usesmileid to \"$CURRENT\""
grep -qF "## [$NEW]" "$CHANGELOG" && die "CHANGELOG.md already has a '## [$NEW]' heading"

[ "$(grep -c '^## \[Unreleased\][[:space:]]*$' "$CHANGELOG")" = "1" ] \
  || die "CHANGELOG.md must have exactly one '## [Unreleased]' heading"

# Lines between "## [Unreleased]" and the next "## " heading (or the link
# reference block) that are not blank.
UNRELEASED_BODY=$(awk '
  /^## \[Unreleased\][[:space:]]*$/ { inside = 1; next }
  inside && /^## / { exit }
  inside && /^\[[^]]+\]: / { exit }
  inside && NF { print }
' "$CHANGELOG")
[ -n "$UNRELEASED_BODY" ] || die "the Unreleased section of CHANGELOG.md is empty; nothing to release"

UNRELEASED_LINK="[Unreleased]: $REPO_URL/compare/v$CURRENT...HEAD"
grep -qxF "$UNRELEASED_LINK" "$CHANGELOG" \
  || die "CHANGELOG.md has no '$UNRELEASED_LINK' link"

# --- Write ------------------------------------------------------------------

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

sed "s/public const VERSION = '$CURRENT';/public const VERSION = '$NEW';/" "$VERSION_FILE" >"$TMP/Version.php"
sed "s/assertSame('$CURRENT', Version::VERSION)/assertSame('$NEW', Version::VERSION)/" "$VERSION_TEST" >"$TMP/VersionTest.php"
sed "s/\"smileid\/usesmileid\": \"$CURRENT\"/\"smileid\/usesmileid\": \"$NEW\"/" "$EXAMPLE_COMPOSER" >"$TMP/composer.json"

awk -v new="$NEW" -v date="$DATE" -v ulink="$UNRELEASED_LINK" \
  -v nulink="[Unreleased]: $REPO_URL/compare/v$NEW...HEAD" \
  -v vlink="[$NEW]: $REPO_URL/compare/v$CURRENT...v$NEW" '
  /^## \[Unreleased\][[:space:]]*$/ {
    print
    print ""
    print "## [" new "] - " date
    next
  }
  $0 == ulink {
    print nulink
    print vlink
    next
  }
  { print }
' "$CHANGELOG" >"$TMP/CHANGELOG.md"

cp "$TMP/Version.php" "$VERSION_FILE"
cp "$TMP/VersionTest.php" "$VERSION_TEST"
cp "$TMP/composer.json" "$EXAMPLE_COMPOSER"
cp "$TMP/CHANGELOG.md" "$CHANGELOG"

echo "$NEW"
