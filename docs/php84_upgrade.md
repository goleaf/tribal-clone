# PHP 8.4 Upgrade Notes

This project targets PHP 8.4+. Use the runtime guard `PhpVersionGuard::assertCompatible()` and the helper script `tests/php_84_compat_check.php` to verify environments before deploys.

## Quick checklist
- PHP >= 8.4.0 (see `PHP_VERSION_ID`)
- Extensions: pdo, pdo_sqlite, sqlite3, mysqli, mbstring, json, curl, ctype, filter, openssl
- Session INI: `session.sid_length` and `session.sid_bits_per_character` are deprecated; set them only if required by legacy configs.

Run:
```bash
php tests/php_84_compat_check.php
```

