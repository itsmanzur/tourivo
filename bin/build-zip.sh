#!/usr/bin/env bash
#
# Tourivo Build & Packaging Utility
# Usage: ./bin/build-zip.sh
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

echo "==> Executing Tourivo Distribution Builder..."
php "$SCRIPT_DIR/build-package.php"
