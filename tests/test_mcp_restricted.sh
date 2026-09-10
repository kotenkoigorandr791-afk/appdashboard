#!/bin/bash

echo "=== Test MCP Tools for Restricted Sandbox ==="
echo ""

# Test 1: Cloud operations
echo "Test 1: Cloud - List files"
echo '{"action":"cloud_list"}' | docker compose exec appdashboard php -r "
require_once 'project/bootstrap.php';
require_once 'appdashboard-mcp/dist/index.js';
echo shellAction('cloud_list');
"
echo ""

# Test 2: Steps operations
echo "Test 2: Steps - List steps"
echo '{"action":"steps_list"}' | docker compose exec -T appdashboard php -r "
require_once 'project/bootstrap.php';
require_once 'appdashboard-mcp/dist/index.js';
echo shellAction('steps_list');
"
echo ""

# Test 3: Spends operations
echo "Test 3: Spends - List expenses"
echo '{"action":"spends_list"}' | docker compose exec -T appdashboard php -r "
require_once 'project/bootstrap.php';
require_once 'appdashboard-mcp/dist/index.js';
echo shellAction('spends_list');
"
echo ""

# Test 4: Sandbox operations
echo "Test 4: Sandbox - List scripts"
echo '{"action":"sandbox_list_scripts"}' | docker compose exec -T appdashboard php -r "
require_once 'project/bootstrap.php';
require_once 'appdashboard-mcp/dist/index.js';
echo shellAction('sandbox_list_scripts');
"
echo ""

# Test 5: List files from within sandbox
echo "Test 5: File operations - List files in sandbox directory"
echo '{"action":"files_list","path":"/tmp"}' | docker compose exec -T appdashboard php -r "
require_once 'project/bootstrap.php';
require_once 'appdashboard-mcp/dist/index.js';
echo shellAction('files_list', {path: '/tmp'});
"
echo ""

echo "=== All MCP tests completed ==="