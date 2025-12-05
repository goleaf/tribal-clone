# Test Suite Documentation

Comprehensive testing suite for the Tribal Clone browser game.

## Quick Start

Run all tests:
```bash
./tests/run_all_tests.sh
```

## Test Files

### 1. Comprehensive Browser Test (`browser_comprehensive_test.php`)
CLI-based test that verifies:
- All PHP files exist and have valid syntax
- All image assets are present and readable
- File sizes are correct

**Run:**
```bash
php tests/browser_comprehensive_test.php
```

**Tests:** 69 checks (49 images + 20 routes)

### 2. Automated Route Test (`automated_route_test.php`)
HTTP-based test that verifies:
- Public routes return HTTP 200
- Authenticated routes redirect properly (HTTP 302)
- Images load via HTTP
- Server is responsive

**Run:**
```bash
php tests/automated_route_test.php
```

**Tests:** 21 HTTP requests

### 3. Visual Browser Test (`browser_visual_test.php`)
Interactive web page for manual testing:
- Visual image loading verification
- Route accessibility checks
- AJAX endpoint testing
- Real-time statistics

**Access:**
```bash
./tests/run_browser_tests.sh
```

Then open: http://localhost:8000/tests/browser_visual_test.php

### 4. Test Runner Scripts

**`run_all_tests.sh`** - Runs all automated tests
```bash
./tests/run_all_tests.sh
```

**`run_browser_tests.sh`** - Starts server and opens visual test page
```bash
./tests/run_browser_tests.sh
```

## Test Coverage

### Routes Tested (20)
- Authentication: login, register
- Game pages: game.php, game_wap.php, intel.php
- Map: map.php
- Buildings: upgrade, cancel
- Units: recruit, queue
- Research: panel
- Combat: attack
- Player: profile, rename
- AJAX: resources, proxy
- Static: help, guides, terms

### Images Tested (49)
- Resource icons (4)
- Building icons (14)
- Map icons (13)
- Map terrain tiles (8)
- Report icons (4)
- Other assets (6)

## Requirements

- PHP 8.4+
- Local server running on port 8000
- curl (for HTTP tests)

## Test Results

Current status: **100% passing**
- File system tests: 69/69 ✅
- HTTP route tests: 21/21 ✅

See `TEST_RESULTS.md` for detailed results.

## CI/CD Integration

Add to your CI pipeline:
```yaml
test:
  script:
    - php -S localhost:8000 &
    - sleep 2
    - ./tests/run_all_tests.sh
```

## Troubleshooting

**Server not running:**
```bash
php -S localhost:8000
```

**Port 8000 in use:**
```bash
lsof -ti:8000 | xargs kill -9
```

**Permission denied:**
```bash
chmod +x tests/*.sh
```
