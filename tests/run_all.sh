#!/usr/bin/env bash
set -euo pipefail

# Suppress deprecated session ini warnings that come from upstream defaults.
PHP_FLAGS="-d display_startup_errors=0 -d error_reporting=22527"

echo "Running queue tests..."
php ${PHP_FLAGS} "$(dirname "$0")/queue_test.php"

echo "Running tribe permission tests..."
php ${PHP_FLAGS} "$(dirname "$0")/tribe_permissions_test.php"

if [ -f "$(dirname "$0")/guides_test.php" ]; then
  echo "Running guides tests..."
  php ${PHP_FLAGS} "$(dirname "$0")/guides_test.php"
fi

echo "Done."
