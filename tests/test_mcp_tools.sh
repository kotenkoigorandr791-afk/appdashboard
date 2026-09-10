#!/bin/bash

echo "=== Тест MCP Tools (Cloud, Steps, Spends) ==="
echo ""

# Cloud tools
echo "1. Запрос списка файлов в облаке..."
echo '{"action":"cloud_list"}' | docker compose exec -T appdashboard php -r "
require_once 'project/bootstrap.php';
require_once 'appdashboard-mcp/dist/index.js';
echo shellAction('cloud_list');
"

echo ""
echo "2. Получение списка шагов..."
echo '{"action":"steps_list"}' | docker compose exec -T appdashboard php -r "
require_once 'project/bootstrap.php';
require_once 'appdashboard-mcp/dist/index.js';
echo shellAction('steps_list');
"

echo ""
echo "3. Запрос списка расходов..."
echo '{"action":"spends_list"}' | docker compose exec -T appdashboard php -r "
require_once 'project/bootstrap.php';
require_once 'appdashboard-mcp/dist/index.js';
echo shellAction('spends_list');
"

echo ""
echo "=== Тест директорий и путей ==="
echo ""

echo "4. Проверка текущего пользователя..."
whoami

echo ""
echo "5. Список файлов в /tmp..."
ls -la /tmp | head -20

echo ""
echo "6. Проверка прав доступа к /opt..."
ls -la /opt

echo ""
echo "7. Проверка прав доступа к /root..."
ls -la /root 2>&1

echo ""
echo "8. Проверка файлов текущего пользователя..."
ls -la /home/$(whoami)/ | head -20

echo ""
echo "=== Тест доступных скриптов ==="
echo ""

echo "9. Список доступных скриптов..."
echo '{"action":"sandbox_list_scripts"}' | docker compose exec -T appdashboard php -r "
require_once 'project/bootstrap.php';
require_once 'appdashboard-mcp/dist/index.js';
echo shellAction('sandbox_list_scripts');
"

echo ""
echo "=== Все тесты завершены ==="