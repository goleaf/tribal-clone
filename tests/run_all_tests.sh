#!/bin/bash

# Comprehensive Test Runner
# Runs all browser and route tests

# Don't exit on error - we want to run all tests

echo "╔════════════════════════════════════════════════════════════╗"
echo "║         TRIBAL CLONE - COMPREHENSIVE TEST SUITE           ║"
echo "╔════════════════════════════════════════════════════════════╗"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Track results
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to run a test
run_test() {
    local test_name=$1
    local test_command=$2
    
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "Running: $test_name"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    eval "$test_command" 2>&1 | grep -v "Warning:" | grep -v "Deprecated:"
    local exit_code=${PIPESTATUS[0]}
    
    if [ $exit_code -eq 0 ]; then
        echo -e "${GREEN}✓ $test_name PASSED${NC}"
        ((PASSED_TESTS++))
    else
        echo -e "${RED}✗ $test_name FAILED${NC}"
        ((FAILED_TESTS++))
    fi
    
    ((TOTAL_TESTS++))
}

# Check PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ PHP is not installed${NC}"
    exit 1
fi

echo -e "${GREEN}✓ PHP found: $(php -v | head -n 1)${NC}"
echo ""

# Check if server is running
if ! lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null 2>&1; then
    echo -e "${YELLOW}⚠ Starting PHP development server...${NC}"
    php -S localhost:8000 > /dev/null 2>&1 &
    SERVER_PID=$!
    echo $SERVER_PID > /tmp/tribal_test_server.pid
    sleep 2
    echo -e "${GREEN}✓ Server started (PID: $SERVER_PID)${NC}"
else
    echo -e "${GREEN}✓ Server already running on port 8000${NC}"
fi

echo ""

# Run tests
run_test "File System Tests" "php tests/browser_comprehensive_test.php"
run_test "HTTP Route Tests" "php tests/automated_route_test.php"

# Generate summary
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║                      FINAL SUMMARY                         ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "Total Test Suites: $TOTAL_TESTS"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$FAILED_TESTS${NC}"

if [ $FAILED_TESTS -eq 0 ]; then
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║              🎉 ALL TESTS PASSED! 🎉                       ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo "Visual test page available at:"
    echo "http://localhost:8000/tests/browser_visual_test.php"
    echo ""
    exit 0
else
    echo ""
    echo -e "${RED}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║              ❌ SOME TESTS FAILED ❌                       ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    exit 1
fi
