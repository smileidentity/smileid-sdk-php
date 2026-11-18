#!/usr/bin/env bash
#
# Tests for how scripts/check-api-compat.sh reads Roave's output. A fake
# roave-backward-compatibility-check stands in for the real one, so the tests
# need no PHP. Builds a throwaway git repository; never touches the real one.
#
# Usage: scripts/tests/check-api-compat-test.sh

set -euo pipefail

HERE=$(cd "$(dirname "$0")" && pwd)
SCRIPTS=$(cd "$HERE/.." && pwd)
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

FAILURES=0
pass() { echo "ok   - $1"; }
fail() {
  echo "FAIL - $1" >&2
  FAILURES=$((FAILURES + 1))
}

mkdir "$WORK/repo"
cd "$WORK/repo"
git init -q -b main
git config user.name test
git config user.email test@example.com
git config commit.gpgsign false
git config tag.gpgsign false
mkdir scripts
cp "$SCRIPTS/check-api-compat.sh" "$SCRIPTS/previous-release-tag.sh" scripts/
git add -A
git commit -q -m base
git tag v1.0.0
git commit -q --allow-empty -m change

# The fake prints FAKE_MD to stdout and FAKE_LOG to stderr, then exits with
# FAKE_STATUS, like Roave with --format=markdown.
cat >"$WORK/fake-bc" <<'EOF'
#!/usr/bin/env bash
printf '%b' "$FAKE_MD"
printf '%b' "$FAKE_LOG" >&2
exit "$FAKE_STATUS"
EOF
chmod +x "$WORK/fake-bc"
export BC_CHECK_BIN="$WORK/fake-bc"

# expect <description> <expected exit code> <status> <markdown> <log>
expect() {
  local status=0
  FAKE_STATUS="$3" FAKE_MD="$4" FAKE_LOG="$5" scripts/check-api-compat.sh main >"$WORK/out" 2>&1 || status=$?
  if [ "$status" = "$2" ]; then pass "$1"; else fail "$1 (expected exit $2, got $status): $(cat "$WORK/out")"; fi
}

EMPTY='# Added\n\n# Changed\n\n# Removed\n\n# Skipped\n'
TWO='# Added\n\n# Changed\n - [BC] Method a() of class A visibility reduced\n\n# Removed\n - [BC] Method A#b() was removed\n\n# Skipped\n'

expect "no breaks passes" 0 0 "$EMPTY" 'No backwards-incompatible changes detected\n'
expect "breaks that match the reported count fail as breaks" 3 3 "$TWO" '2 backwards-incompatible changes detected\n'
expect "one break, singular wording" 3 3 '# Changed\n - [BC] Method A#b() was removed\n' '1 backwards-incompatible change detected\n'
expect "breaks reported but no [BC] lines parsed is an error" 2 3 '# Changed\n * BC: something new\n' '1 backwards-incompatible change detected\n'
expect "fewer [BC] lines than reported is an error" 2 3 "$TWO" '3 backwards-incompatible changes detected\n'
expect "breaks without a count in the log is an error" 2 3 "$TWO" ''
expect "exit 0 without the no-changes message is an error" 2 0 "$EMPTY" ''
expect "exit 0 with [BC] lines is an error" 2 0 "$TWO" 'No backwards-incompatible changes detected\n'
expect "a Roave crash is an error" 1 1 '' 'boom\n'

if [ "$FAILURES" -gt 0 ]; then
  echo "$FAILURES test(s) failed" >&2
  exit 1
fi
echo "all check-api-compat tests passed"
