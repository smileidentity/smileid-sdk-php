#!/usr/bin/env bash
#
# Fail if the changes since <base-ref> add a coverage exclusion.
#
# Usage: scripts/check-coverage-exclusions.sh <base-ref>
#
# Scans only lines added in `git diff <base-ref>...HEAD` for:
#   nocov, pragma: no cover, c8 ignore, istanbul ignore, @codeCoverageIgnore,
#   #[coverage(off)], JaCoCo @Generated annotations outside generated code, and
#   new excludes entries in JaCoCo build config (pom.xml, *.gradle, *.gradle.kts).
#
# Prints each hit as <file>:<line>: <text> and exits 1 if there are any.
# This file is skipped, because it has to name the markers it looks for.

set -euo pipefail

if [ $# -ne 1 ]; then
  echo "usage: scripts/check-coverage-exclusions.sh <base-ref>" >&2
  exit 2
fi
BASE="$1"
SELF="scripts/check-coverage-exclusions.sh"

git rev-parse --verify --quiet "$BASE^{commit}" >/dev/null || {
  echo "check-coverage-exclusions: unknown ref '$BASE'" >&2
  exit 2
}

HITS=$(
  git diff --unified=0 --no-color --no-ext-diff "$BASE"...HEAD |
    awk -v self="$SELF" '
      /^\+\+\+ / {
        file = substr($0, 5)
        sub(/^b\//, "", file)
        next
      }
      /^@@ / {
        # @@ -a,b +c,d @@
        split($3, plus, ",")
        line = substr(plus[1], 2) + 0
        next
      }
      /^\+/ {
        text = substr($0, 2)
        hit = 0
        if (file != self && file != "/dev/null") {
          lower = tolower(text)
          if (lower ~ /nocov/ || lower ~ /pragma:[[:space:]]*no[[:space:]]+cover/ ||
              lower ~ /c8[[:space:]]+ignore/ || lower ~ /istanbul[[:space:]]+ignore/ ||
              text ~ /@codeCoverageIgnore/ || text ~ /#\[coverage\(off\)\]/) {
            hit = 1
          }
          # JaCoCo skips classes annotated @Generated; only generated code may use it.
          if (text ~ /@([A-Za-z_.]*\.)?Generated([^A-Za-z_]|$)/ && tolower(file) !~ /(^|\/)generated\//) {
            hit = 1
          }
          # New exclusions in JaCoCo build config.
          if (file ~ /(^|\/)pom\.xml$/ || file ~ /\.gradle(\.kts)?$/) {
            if (text ~ /<exclude(s)?>/ || text ~ /(^|[^A-Za-z])excludes([^A-Za-z]|$)/) {
              hit = 1
            }
          }
        }
        if (hit) {
          printf "%s:%d: %s\n", file, line, text
        }
        line++
        next
      }
    '
)

if [ -n "$HITS" ]; then
  echo "Coverage exclusions added since $BASE:"
  echo "$HITS"
  exit 1
fi
echo "No coverage exclusions added since $BASE."
