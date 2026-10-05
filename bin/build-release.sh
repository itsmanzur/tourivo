#!/usr/bin/env bash
#
# Tourivo Release Distribution Builder
# Usage: ./bin/build-release.sh
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

echo "==> Building Tourivo release package..."
php "$SCRIPT_DIR/build-package.php"

echo "==> Release build completed successfully."
