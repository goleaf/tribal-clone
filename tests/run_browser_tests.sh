#!/bin/bash

# Browser Test Runner Script
# Starts a local PHP server and opens the visual test page

echo "=== Browser Test Suite Runner ==="
echo ""

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed or not in PATH"
    exit 1
fi

echo "✓ PHP found: $(php -v | head -n 1)"
echo ""

# Run CLI tests first
echo "Running CLI tests..."
php tests/browser_comprehensive_test.php
CLI_EXIT=$?

if [ $CLI_EXIT -eq 0 ]; then
    echo ""
    echo "✓ All CLI tests passed!"
else
    echo ""
    echo "⚠ Some CLI tests failed. Check output above."
fi

echo ""
echo "---"
echo ""

# Check if server is already running
if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null 2>&1; then
    echo "✓ Server already running on port 8000"
else
    echo "Starting PHP development server on port 8000..."
    php -S localhost:8000 > /dev/null 2>&1 &
    SERVER_PID=$!
    echo "✓ Server started (PID: $SERVER_PID)"
    
    # Save PID for cleanup
    echo $SERVER_PID > /tmp/tribal_test_server.pid
    
    # Wait for server to start
    sleep 2
fi

echo ""
echo "Opening visual test page in browser..."
echo "URL: http://localhost:8000/tests/browser_visual_test.php"
echo ""

# Open in default browser (macOS)
if command -v open &> /dev/null; then
    open "http://localhost:8000/tests/browser_visual_test.php"
elif command -v xdg-open &> /dev/null; then
    xdg-open "http://localhost:8000/tests/browser_visual_test.php"
else
    echo "Please open this URL manually: http://localhost:8000/tests/browser_visual_test.php"
fi

echo ""
echo "Press Ctrl+C to stop the server when done testing"
echo ""

# Keep script running
if [ -f /tmp/tribal_test_server.pid ]; then
    wait $(cat /tmp/tribal_test_server.pid)
fi
