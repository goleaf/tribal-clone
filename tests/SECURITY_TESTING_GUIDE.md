# Security Testing Guide for Property-Based Tests

## Overview
This guide documents security considerations and testing recommendations for the property-based test suite, specifically `building_manager_property_test.php`.

## Security Fixes Implemented

### 1. Database Protection (CRITICAL)
**Issue**: Tests could run against production database
**Fix**: Added database path validation
```php
if (!defined('DB_PATH') || strpos(DB_PATH, 'test') === false) {
    die("ERROR: This test must run against a test database only...");
}
```
**Test**: Verify test fails when DB_PATH doesn't contain 'test'

### 2. Test Data Isolation (HIGH)
**Issue**: Tests reused production data and left orphaned records
**Fix**: 
- Always create isolated test users/villages with unique IDs
- Use negative coordinates (-1000 to -10999) to avoid collision
- Track all created entities for cleanup
- Register shutdown function to clean up on exit

**Test**: 
```bash
# Before test
sqlite3 data/tribal_wars_test.sqlite "SELECT COUNT(*) FROM users WHERE username LIKE 'test_%';"

# Run test
php tests/building_manager_property_test.php

# After test - should be same count
sqlite3 data/tribal_wars_test.sqlite "SELECT COUNT(*) FROM users WHERE username LIKE 'test_%';"
```

### 3. Ownership Validation (MEDIUM)
**Issue**: Tests didn't verify ownership before operations
**Fix**: Added ownership checks in property functions
```php
$stmt = $conn->prepare("SELECT user_id FROM villages WHERE id = ?");
$stmt->bind_param("i", $villageId);
$stmt->execute();
$ownerCheck = $stmt->get_result()->fetch_assoc();
if (!$ownerCheck || $ownerCheck['user_id'] != $userId) {
    return "SECURITY: Ownership validation failed";
}
```

### 4. Cryptographic Security (LOW)
**Issue**: Weak test passwords using predictable values
**Fix**: Use `random_bytes()` for password generation
```php
$password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
```

## SQL Injection Testing

### Current Status: ✓ SECURE
All queries use prepared statements with proper parameter binding.

### Test Payloads
Add these tests to verify SQL injection protection:

```php
// Test 1: SQL injection in building name
$maliciousBuilding = "sawmill' OR '1'='1";
$result = $queueManager->enqueueBuild($villageId, $maliciousBuilding, $userId);
// Expected: Should fail gracefully, not execute injection

// Test 2: SQL injection in village ID
$maliciousVillageId = "1 OR 1=1";
$result = $queueManager->enqueueBuild($maliciousVillageId, 'sawmill', $userId);
// Expected: Type error or safe failure

// Test 3: SQL injection in user ID
$maliciousUserId = "1 UNION SELECT * FROM users";
$result = $queueManager->enqueueBuild($villageId, 'sawmill', $maliciousUserId);
// Expected: Type error or safe failure
```

### Recommended Test File
Create `tests/security_sql_injection_test.php`:
```php
<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/managers/BuildingQueueManager.php';

// Test SQL injection attempts
$testCases = [
    "sawmill' OR '1'='1",
    "sawmill'; DROP TABLE villages; --",
    "sawmill' UNION SELECT * FROM users WHERE '1'='1",
];

foreach ($testCases as $payload) {
    $result = $queueManager->enqueueBuild(1, $payload, 1);
    assert(!$result['success'], "SQL injection payload should not succeed: $payload");
}
```

## Authorization Testing

### Test Unauthorized Access
Create `tests/security_authorization_test.php`:

```php
<?php
// Test 1: User A tries to upgrade User B's village
$userA = createTestUser();
$userB = createTestUser();
$villageB = createTestVillage($userB);

$result = $queueManager->enqueueBuild($villageB, 'sawmill', $userA);
assert(!$result['success'], "User A should not upgrade User B's village");
assert($result['error_code'] === 'ERR_UNAUTHORIZED', "Should return unauthorized error");

// Test 2: Verify village ownership before resource deduction
$resourcesBefore = getVillageResources($villageB);
$result = $queueManager->enqueueBuild($villageB, 'sawmill', $userA);
$resourcesAfter = getVillageResources($villageB);
assert($resourcesBefore === $resourcesAfter, "Resources should not change on unauthorized attempt");
```

## XSS/Output Encoding Testing

### Current Status: N/A (Test file has no user-facing output)

### For AJAX Endpoints
When testing AJAX endpoints that return data to users:

```php
// Test XSS in building names
$xssPayload = "<script>alert('XSS')</script>";
// Ensure output is encoded with htmlspecialchars()

// Test XSS in error messages
$result = $queueManager->enqueueBuild($villageId, $xssPayload, $userId);
$message = $result['message'];
assert(strpos($message, '<script>') === false, "Error messages should be encoded");
```

## CSRF Testing

### Current Status: N/A (Test file doesn't handle HTTP requests)

### For AJAX Endpoints
Add to `ajax/buildings/*.php` tests:

```php
// Test 1: Missing CSRF token
$_POST['building'] = 'sawmill';
unset($_POST['csrf_token']);
// Expected: Request should be rejected

// Test 2: Invalid CSRF token
$_POST['csrf_token'] = 'invalid_token_12345';
// Expected: Request should be rejected

// Test 3: Expired CSRF token
$_POST['csrf_token'] = generateExpiredToken();
// Expected: Request should be rejected
```

## Error Leakage Testing

### Test Error Messages
```php
// Test 1: Database error should not leak details
// Simulate DB connection failure
$result = $queueManager->enqueueBuild($villageId, 'sawmill', $userId);
assert(!isset($result['stack_trace']), "Should not leak stack traces");
assert(!isset($result['sql_query']), "Should not leak SQL queries");

// Test 2: Error messages should be generic
assert(
    !preg_match('/database|mysql|sqlite|table|column/i', $result['message']),
    "Error messages should not leak database details"
);
```

## Rate Limiting Testing

### Recommended Implementation
Create `tests/security_rate_limit_test.php`:

```php
<?php
// Test rapid-fire requests
$villageId = createTestVillage();
$userId = createTestUser();

$successCount = 0;
for ($i = 0; $i < 100; $i++) {
    $result = $queueManager->enqueueBuild($villageId, 'sawmill', $userId);
    if ($result['success']) {
        $successCount++;
    }
}

// Should have rate limiting after N requests
assert($successCount < 100, "Rate limiting should prevent 100 consecutive requests");
```

## Session Security Testing

### Test Session Fixation
```php
// Test 1: Session ID should regenerate on login
$sessionBefore = session_id();
// Perform login
$sessionAfter = session_id();
assert($sessionBefore !== $sessionAfter, "Session ID should change on login");

// Test 2: Session should timeout
$_SESSION['last_activity'] = time() - 3600; // 1 hour ago
// Next request should invalidate session
```

## File Upload Security (If Applicable)

### Not applicable to current test file
If file uploads are added:
- Validate MIME types
- Check file extensions
- Scan for malicious content
- Store outside webroot
- Limit file sizes

## Recommended Test Execution

### Pre-Test Checklist
```bash
# 1. Verify test database
echo $DB_PATH | grep -q "test" || echo "WARNING: Not using test database"

# 2. Backup test database
cp data/tribal_wars_test.sqlite data/tribal_wars_test.sqlite.backup

# 3. Run tests
php tests/building_manager_property_test.php

# 4. Verify cleanup
sqlite3 data/tribal_wars_test.sqlite "SELECT COUNT(*) FROM users WHERE username LIKE 'test_prop%';"
# Should return 0

# 5. Check for orphaned data
sqlite3 data/tribal_wars_test.sqlite "SELECT COUNT(*) FROM villages WHERE x_coord < -1000;"
# Should return 0
```

### CI/CD Integration
```yaml
# .github/workflows/security-tests.yml
name: Security Tests
on: [push, pull_request]
jobs:
  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - name: Run Security Tests
        run: |
          php tests/building_manager_property_test.php
          php tests/security_sql_injection_test.php
          php tests/security_authorization_test.php
```

## Severity Ratings

### Critical
- SQL Injection vulnerabilities
- Authentication bypass
- Remote code execution

### High
- Authorization failures
- Data leakage
- Session hijacking

### Medium
- XSS vulnerabilities
- CSRF vulnerabilities
- Information disclosure

### Low
- Weak password policies in tests
- Missing rate limiting
- Verbose error messages

## Next Steps

1. **Immediate**: Run existing property tests to verify security fixes
2. **Short-term**: Create `security_sql_injection_test.php` and `security_authorization_test.php`
3. **Medium-term**: Add CSRF protection to all AJAX endpoints
4. **Long-term**: Implement comprehensive security test suite with automated scanning

## References

- [OWASP Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [SQLite Security](https://www.sqlite.org/security.html)
- DevDocs PHP Security: https://devdocs.io/php/security
