#!/usr/bin/env bash
#
# Tests for scripts/previous-release-tag.sh and scripts/check-release-breaking.sh.
# Builds a throwaway git repository; never touches the real one.
#
# Usage: scripts/tests/release-tags-test.sh

# The `check && pass || fail` lines are deliberate: pass and fail always return 0.
# shellcheck disable=SC2015

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

expect_eq() { # <description> <expected> <actual>
  if [ "$2" = "$3" ]; then pass "$1"; else fail "$1 (expected '$2', got '$3')"; fi
}

REPO="$WORK/repo"
mkdir -p "$REPO"
cd "$REPO"
git init -q -b main
git config user.name test
git config user.email test@example.com
git config commit.gpgsign false
git config tag.gpgsign false

# commit <message>: an empty commit; prints its SHA.
commit() {
  git commit -q --allow-empty -m "$1"
  git rev-parse HEAD
}

# Breaking PRs are marked by the commit message "breaking: #<n>". The fake
# lookup reads it back, standing in for the GitHub commits/<sha>/pulls API.
cat >"$WORK/lookup.sh" <<'EOF'
#!/usr/bin/env bash
git log -1 --format=%s "$1" | sed -n 's/^breaking: \(#[0-9]*\)$/\1 a breaking PR/p'
EOF
chmod +x "$WORK/lookup.sh"
export RELEASE_CHECK_PR_LOOKUP="$WORK/lookup.sh"

release_check() {
  "$SCRIPTS/check-release-breaking.sh" "$1" >"$WORK/out" 2>&1
}

# History on main:
#   c0 v0.9.0 - c1 v1.0.0 - c2 (breaking #1) v2.0.0 - c3 v2.1.0-rc.1
#   - c4 v2.1.0 - c5 (breaking #2) - c6 - c7 v3.0.0
# Maintenance branch from v1.0.0: m1 v1.0.1, m2 (breaking #3) v1.0.2.
commit c0 >/dev/null && git tag v0.9.0
commit c1 >/dev/null && git tag v1.0.0
commit "breaking: #1" >/dev/null && git tag v2.0.0
commit c3 >/dev/null && git tag v2.1.0-rc.1
commit c4 >/dev/null && git tag v2.1.0
commit "breaking: #2" >/dev/null
commit c6 >/dev/null
commit c7 >/dev/null && git tag v3.0.0
git checkout -q -b maint v1.0.0
commit m1 >/dev/null && git tag v1.0.1
commit "breaking: #3" >/dev/null && git tag v1.0.2
git checkout -q main
commit after >/dev/null
git tag v4.0.0-rc.1 # pre-release on main's tip

# --- previous-release-tag.sh ------------------------------------------------

expect_eq "newest stable ancestor of main skips a pre-release on the tip" v3.0.0 "$("$SCRIPTS/previous-release-tag.sh" main)"
expect_eq "previous tag of v2.1.0 skips v2.1.0-rc.1" v2.0.0 "$("$SCRIPTS/previous-release-tag.sh" 'v2.1.0^')"
expect_eq "a ref that is a stable tag returns that tag" v2.1.0 "$("$SCRIPTS/previous-release-tag.sh" v2.1.0)"
expect_eq "maintenance branch ignores the higher tags on main" v1.0.2 "$("$SCRIPTS/previous-release-tag.sh" maint)"
expect_eq "previous tag of v1.0.1 is v1.0.0, not v3.0.0" v1.0.0 "$("$SCRIPTS/previous-release-tag.sh" 'v1.0.1^')"
git tag v2.0.0-rc.9 v2.0.0
expect_eq "a commit with a pre-release and a stable tag returns the stable one" v2.0.0 "$("$SCRIPTS/previous-release-tag.sh" v2.0.0)"
git tag -d v2.0.0-rc.9 >/dev/null

# --- check-release-breaking.sh ----------------------------------------------

release_check v0.9.0 && pass "first release has nothing to compare with" || fail "first release: $(cat "$WORK/out")"
release_check v1.0.0 && pass "0.x to 1.0.0 needs no breaking PR" || fail "0.x to 1.0.0: $(cat "$WORK/out")"
release_check v2.0.0 && pass "major bump with a breaking PR passes" || fail "v2.0.0: $(cat "$WORK/out")"
release_check v2.1.0 && pass "minor after a major does not count the major's breaking PR again" || fail "v2.1.0: $(cat "$WORK/out")"
git tag v2.1.1 'v3.0.0^'
if release_check v2.1.1; then fail "patch containing a breaking PR passed"; else
  grep -q "#2" "$WORK/out" && pass "patch containing a breaking PR fails and names it" || fail "v2.1.1 output: $(cat "$WORK/out")"
fi
git tag -d v2.1.1 >/dev/null
release_check v3.0.0 && pass "major bump counts only its own commits" || fail "v3.0.0: $(cat "$WORK/out")"
release_check v1.0.1 && pass "backport tag sees only its own commits" || fail "v1.0.1: $(cat "$WORK/out")"
if release_check v1.0.2; then fail "backport patch with a breaking PR passed"; else pass "backport patch with a breaking PR fails"; fi

git tag v5.0.0 v1.0.1
if release_check v5.0.0; then fail "major bump without a breaking PR passed"; else pass "major bump without a breaking PR fails"; fi
git tag -d v5.0.0 >/dev/null

if [ "$FAILURES" -gt 0 ]; then
  echo "$FAILURES test(s) failed" >&2
  exit 1
fi
echo "all release tag tests passed"
