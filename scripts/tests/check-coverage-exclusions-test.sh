#!/usr/bin/env bash
#
# Tests for scripts/check-coverage-exclusions.sh. Builds a throwaway git
# repository; never touches the real one.
#
# Usage: scripts/tests/check-coverage-exclusions-test.sh

# The fixtures are PHP source, so their $ signs are meant literally.
# shellcheck disable=SC2016

set -euo pipefail

HERE=$(cd "$(dirname "$0")" && pwd)
SCRIPT="$(cd "$HERE/.." && pwd)/check-coverage-exclusions.sh"
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
mkdir -p scripts src/Generated
cp "$SCRIPT" scripts/
printf '<?php\n$a = 1;\n' >src/A.php
printf '<phpunit>\n    <source>\n    </source>\n</phpunit>\n' >phpunit.xml.dist
git add -A
git commit -q -m base

# expect <description> <should-fail 0|1> [expected hit line...]
expect() {
  local desc="$1" should_fail="$2" status=0
  shift 2
  git add -A
  git commit -q -m change
  bash scripts/check-coverage-exclusions.sh HEAD~1 >"$WORK/out" 2>&1 || status=$?
  if [ "$should_fail" = 1 ] && [ "$status" -ne 1 ]; then
    fail "$desc: expected exit 1, got $status: $(cat "$WORK/out")"
  elif [ "$should_fail" = 0 ] && [ "$status" -ne 0 ]; then
    fail "$desc: expected exit 0, got $status: $(cat "$WORK/out")"
  else
    local hit ok=1
    for hit in "$@"; do
      grep -qxF "$hit" "$WORK/out" || ok=0
    done
    if [ "$ok" = 1 ]; then pass "$desc"; else fail "$desc: missing hit in: $(cat "$WORK/out")"; fi
  fi
}

printf '<?php\n$a = 1;\n// @codeCoverageIgnore\n$b = 2; // nocov\n' >src/A.php
expect "comment markers are reported with file and line" 1 \
  "src/A.php:3: // @codeCoverageIgnore" "src/A.php:4: \$b = 2; // nocov"

printf '@Generated\nclass B {}\n' >src/Generated/B.java
expect "@Generated inside generated code is allowed" 0

printf '@lombok.Generated\nclass C {}\n' >src/C.java
expect "@Generated outside generated code is blocked" 1 "src/C.java:1: @lombok.Generated"

printf '<phpunit>\n    <source>\n        <exclude>\n            <directory>src/Generated/Foo</directory>\n        </exclude>\n    </source>\n</phpunit>\n' >phpunit.xml.dist
expect "a PHPUnit <exclude> is blocked" 1 "phpunit.xml.dist:3:         <exclude>"

printf '<excludes>\n' >pom.xml
expect "a JaCoCo excludes entry is blocked" 1 "pom.xml:1: <excludes>"

# An added line whose text starts with "++ " shows as "+++ " in the diff. It
# must not be read as a file header that switches the current file.
printf '<?php\n$a = 1;\n++ $i;\n$c = 3; // nocov\n' >src/A.php
expect "an added '++ ' line does not switch the file" 1 "src/A.php:4: \$c = 3; // nocov"

printf '<?php\n$a = 1;\n$d = 4;\n' >src/A.php
expect "a clean change passes" 0

if [ "$FAILURES" -gt 0 ]; then
  echo "$FAILURES test(s) failed" >&2
  exit 1
fi
echo "all check-coverage-exclusions tests passed"
