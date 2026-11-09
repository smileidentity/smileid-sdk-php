#!/usr/bin/env bash
#
# Tests for scripts/bump-version.sh. Runs against throwaway fixture trees, so
# it never touches the real repository files.
#
# Usage: scripts/tests/bump-version-test.sh

# The `check && pass || fail` lines are deliberate: pass and fail always return 0.
# shellcheck disable=SC2015

set -euo pipefail

HERE=$(cd "$(dirname "$0")" && pwd)
SCRIPT="$HERE/../bump-version.sh"
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

FAILURES=0
pass() { echo "ok   - $1"; }
fail() {
  echo "FAIL - $1" >&2
  FAILURES=$((FAILURES + 1))
}

# make_tree <dir> <version> <unreleased body>
make_tree() {
  local dir="$1" version="$2" body="$3"
  mkdir -p "$dir/src" "$dir/tests" "$dir/example"
  cat >"$dir/src/Version.php" <<EOF
<?php

declare(strict_types=1);

namespace SmileIdentity;

final class Version
{
    public const VERSION = '$version';
}
EOF
  cat >"$dir/tests/VersionTest.php" <<EOF
<?php

final class VersionTest extends TestCase
{
    public function testVersionConstant(): void
    {
        \$this->assertSame('$version', Version::VERSION);
    }
}
EOF
  cat >"$dir/example/composer.json" <<EOF
{
    "repositories": [
        {
            "options": {
                "versions": {
                    "smileid/usesmileid": "$version"
                }
            }
        }
    ]
}
EOF
  {
    printf '# Changelog\n\n## [Unreleased]\n\n'
    if [ -n "$body" ]; then printf '%s\n\n' "$body"; fi
    printf '## [%s] - 2026-01-01\n\n### Added\n\n- Earlier things.\n\n' "$version"
    printf '[Unreleased]: https://github.com/smileidentity/smileid-sdk-php/compare/v%s...HEAD\n' "$version"
    printf '[%s]: https://github.com/smileidentity/smileid-sdk-php/releases/tag/v%s\n' "$version" "$version"
  } >"$dir/CHANGELOG.md"
}

run_bump() {
  BUMP_VERSION_ROOT="$1" BUMP_VERSION_DATE=2026-11-09 bash "$SCRIPT" "$2"
}

# --- Each part bumps the right number --------------------------------------

for case in "major 13.0.0" "minor 12.3.0" "patch 12.2.1"; do
  # shellcheck disable=SC2086 # split "part expected" into $1 and $2
  set -- $case
  dir="$WORK/$1"
  make_tree "$dir" 12.2.0 $'### Fixed\n\n- A fix.'
  if out=$(run_bump "$dir" "$1") && [ "$out" = "$2" ]; then
    pass "$1 bump prints $2"
  else
    fail "$1 bump printed '${out:-}' (expected $2)"
  fi
  grep -qF "public const VERSION = '$2';" "$dir/src/Version.php" && pass "$1 updates src/Version.php" || fail "$1 did not update src/Version.php"
  grep -qF "assertSame('$2', Version::VERSION)" "$dir/tests/VersionTest.php" && pass "$1 updates tests/VersionTest.php" || fail "$1 did not update tests/VersionTest.php"
  grep -qF "\"smileid/usesmileid\": \"$2\"" "$dir/example/composer.json" && pass "$1 updates example/composer.json" || fail "$1 did not update example/composer.json"
done

# --- Changelog shape --------------------------------------------------------

dir="$WORK/changelog"
make_tree "$dir" 12.2.0 $'### Added\n\n- A new thing.'
run_bump "$dir" minor >/dev/null
expected=$(
  cat <<'EOF'
# Changelog

## [Unreleased]

## [12.3.0] - 2026-11-09

### Added

- A new thing.

## [12.2.0] - 2026-01-01

### Added

- Earlier things.

[Unreleased]: https://github.com/smileidentity/smileid-sdk-php/compare/v12.3.0...HEAD
[12.3.0]: https://github.com/smileidentity/smileid-sdk-php/compare/v12.2.0...v12.3.0
[12.2.0]: https://github.com/smileidentity/smileid-sdk-php/releases/tag/v12.2.0
EOF
)
if [ "$(cat "$dir/CHANGELOG.md")" = "$expected" ]; then
  pass "changelog gets a dated heading, an empty Unreleased section and new links"
else
  fail "changelog content differs:"
  diff <(echo "$expected") "$dir/CHANGELOG.md" >&2 || true
fi

# --- Running it again fails: nothing left unreleased -----------------------

before=$(cat "$dir/src/Version.php" "$dir/CHANGELOG.md")
if run_bump "$dir" minor >/dev/null 2>"$WORK/err"; then
  fail "second run succeeded although Unreleased is empty"
else
  grep -q "nothing to release" "$WORK/err" && pass "second run fails with 'nothing to release'" || fail "second run failed with an unexpected message: $(cat "$WORK/err")"
fi
[ "$before" = "$(cat "$dir/src/Version.php" "$dir/CHANGELOG.md")" ] && pass "failed run leaves files unchanged" || fail "failed run changed files"

# --- Bad input --------------------------------------------------------------

dir="$WORK/bad-part"
make_tree "$dir" 12.2.0 '- Something.'
run_bump "$dir" huge >/dev/null 2>&1 && fail "accepted an unknown part" || pass "rejects an unknown part"

dir="$WORK/bad-version"
make_tree "$dir" 12.2.0 '- Something.'
sed "s/12.2.0/12.2.0-beta.1/" "$dir/src/Version.php" >"$dir/v" && mv "$dir/v" "$dir/src/Version.php"
run_bump "$dir" patch >/dev/null 2>&1 && fail "accepted a non x.y.z version" || pass "rejects a version that is not x.y.z"

dir="$WORK/mismatch"
make_tree "$dir" 12.2.0 '- Something.'
sed "s/12.2.0/12.1.0/" "$dir/example/composer.json" >"$dir/c" && mv "$dir/c" "$dir/example/composer.json"
before=$(cat "$dir/src/Version.php")
run_bump "$dir" patch >/dev/null 2>&1 && fail "accepted a mismatched example/composer.json" || pass "rejects a mismatched example/composer.json"
[ "$before" = "$(cat "$dir/src/Version.php")" ] && pass "mismatch leaves src/Version.php unchanged" || fail "mismatch changed src/Version.php"

dir="$WORK/no-unreleased"
make_tree "$dir" 12.2.0 '- Something.'
sed "s/^## \[Unreleased\]$/## Unreleased/" "$dir/CHANGELOG.md" >"$dir/c" && mv "$dir/c" "$dir/CHANGELOG.md"
run_bump "$dir" patch >/dev/null 2>&1 && fail "accepted a changelog without '## [Unreleased]'" || pass "rejects a changelog without '## [Unreleased]'"

# --- The real repository is in the shape the script expects -----------------
# Copy it so the check cannot modify the working tree. An empty Unreleased
# section is a valid state, so accept that failure too.

dir="$WORK/repo"
ROOT=$(cd "$HERE/../.." && pwd)
mkdir -p "$dir/src" "$dir/tests" "$dir/example"
cp "$ROOT/src/Version.php" "$dir/src/"
cp "$ROOT/tests/VersionTest.php" "$dir/tests/"
cp "$ROOT/example/composer.json" "$dir/example/"
cp "$ROOT/CHANGELOG.md" "$dir/"
if run_bump "$dir" patch >/dev/null 2>"$WORK/err" || grep -q "nothing to release" "$WORK/err"; then
  pass "repository files are in the expected shape"
else
  fail "repository files are not in the expected shape: $(cat "$WORK/err")"
fi

if [ "$FAILURES" -gt 0 ]; then
  echo "$FAILURES test(s) failed" >&2
  exit 1
fi
echo "all bump-version tests passed"
