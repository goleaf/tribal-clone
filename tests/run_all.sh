#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Suppress deprecated session ini warnings that come from upstream defaults.
PHP_FLAGS="-d display_startup_errors=0 -d error_reporting=22527"

echo "Resetting log files..."
php "${SCRIPT_DIR}/clear_logs.php"

echo "Running queue tests..."
php ${PHP_FLAGS} "${SCRIPT_DIR}/queue_test.php"

echo "Running tribe permission tests..."
php ${PHP_FLAGS} "${SCRIPT_DIR}/tribe_permissions_test.php"

if [ -f "${SCRIPT_DIR}/guides_test.php" ]; then
  echo "Running guides tests..."
  php ${PHP_FLAGS} "${SCRIPT_DIR}/guides_test.php"
fi

echo "Done."
