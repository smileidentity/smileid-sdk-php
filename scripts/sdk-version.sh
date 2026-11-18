#!/usr/bin/env bash
#
# Print SmileIdentity\Version::VERSION from src/Version.php at <rev>
# (default HEAD). Prints nothing if it cannot be read.
#
# Usage: scripts/sdk-version.sh [<rev>]

set -euo pipefail

REV="${1:-HEAD}"
git show "$REV:src/Version.php" 2>/dev/null |
  sed -n "s/.*const VERSION *= *'\([^']*\)'.*/\1/p" | head -n 1
