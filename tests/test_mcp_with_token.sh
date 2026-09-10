#!/bin/bash

# Create test token via API
echo "Creating test MCP token..."
TOKEN_RESPONSE=$(curl -X POST http://localhost/main \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=generate_mcp_token" \
  -d "mcp_token_name=test_user" \
  -d "ajax=1" \
  -d "generate_mcp_token=1" 2>/dev/null)

echo "Token Response: $TOKEN_RESPONSE"

# Extract token
TOKEN=$(echo $TOKEN_RESPONSE | grep -o '"token":"[^"]*"' | sed 's/"token":"//;s/"$//')

if [ -z "$TOKEN" ]; then
  echo "Failed to create token"
  exit 1
fi

echo "Token created: ${TOKEN:0:20}..."
echo ""

# Now test with token
echo "=== Testing MCP with Token ==="
echo ""

# Test Cloud
echo "Test 1: Cloud List"
curl -X POST http://localhost/public-api/shell \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"action":"cloud_list"}' 2>/dev/null
echo ""
echo ""

# Test Steps
echo "Test 2: Steps List"
curl -X POST http://localhost/public-api/shell \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"action":"steps_list"}' 2>/dev/null
echo ""
echo ""

# Test Spends
echo "Test 3: Spends List"
curl -X POST http://localhost/public-api/shell \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"action":"spends_list"}' 2>/dev/null
echo ""
echo ""

# Test File operations from sandbox
echo "Test 4: Files List (sandbox directory)"
curl -X POST http://localhost/public-api/shell \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"action":"files_list","path":"/tmp"}' 2>/dev/null
echo ""
echo ""

echo "=== Testing Security Context ==="
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

echo "=== Testing Access Restrictions ==="
echo ""

echo "Access to /root:"
docker compose exec appdashboard bash -c "ls -la /root 2>&1"
echo ""

echo "Access to /opt:"
docker compose exec appdashboard bash -c "ls -la /opt 2>&1 | head -10"
echo ""

echo "Access to /etc/passwd:"
docker compose exec appdashboard bash -c "ls -la /etc/passwd"
echo ""

echo "=== All tests completed ==="