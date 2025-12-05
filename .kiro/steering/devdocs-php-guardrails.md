# DevDocs PHP guardrails

- Cross-check PHP standard library calls on DevDocs (string/array/date/time, PDO/mysqli) for parameter order and return values; handle false/null explicitly.
- Use prepared statements; no dynamic SQL interpolation; escape outputs with `htmlspecialchars` and validate inputs.
- Prefer built-ins for filesystem, hashing, and randomness per DevDocs (`random_int`, `password_hash`); avoid outdated functions.
- Handle timezones and locale safely (`DateTimeImmutable`, `IntlDateFormatter`); do not mix timestamps and DateTime objects without conversion.
- Document minimum PHP version features used; provide fallbacks if code must run on older targets.
