#!/bin/bash

echo "=== MCP Direct API Test (Restricted Sandbox) ==="
echo ""

# Test Cloud
echo "Test 1: Cloud List"
curl -X POST http://localhost/public-api/shell \
  -H "Content-Type: application/json" \
  -d '{"action":"cloud_list"}' 2>/dev/null
echo ""
echo ""

# Test Steps
echo "Test 2: Steps List"
curl -X POST http://localhost/public-api/shell \
  -H "Content-Type: application/json" \
  -d '{"action":"steps_list"}' 2>/dev/null
echo ""
echo ""

# Test Spends
echo "Test 3: Spends List"
curl -X POST http://localhost/public-api/shell \
  -H "Content-Type: application/json" \
  -d '{"action":"spends_list"}' 2>/dev/null
echo ""
echo ""

# Test File operations from sandbox
echo "Test 4: Files List (sandbox directory)"
curl -X POST http://localhost/public-api/shell \
  -H "Content-Type: application/json" \
  -d '{"action":"files_list","path":"/tmp"}' 2>/dev/null
echo ""
echo ""

echo "=== Security Context Test ==="
echo ""
echo "Current user in container:"
docker compose exec appdashboard bash -c "whoami"
echo ""

echo "Home directory:"
docker compose exec appdashboard bash -c "echo ~"
echo ""

echo "Files in /tmp:"
docker compose exec appdashboard bash -c "ls -la /tmp 2>&1 | head -10"
echo ""

echo "=== All tests completed ==="