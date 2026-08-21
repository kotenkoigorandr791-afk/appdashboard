<?php
$parts = public_api_parse_path();
if (($parts[1] ?? '') === 'utils' && str_starts_with((string)($parts[2] ?? ''), 'expense_ocr_app')) {
    require APP_ROOT . '/apps/spends/utils/expense_ocr_app.php';
    exit;
}
require_once APP_ROOT . '/apps/spends/core/db.php';
require_once APP_ROOT . '/apps/spends/core/user_db.php';
require_once APP_ROOT . '/apps/spends/core/auth.php';
require_once APP_ROOT . '/apps/spends/core/locale.php';
require_once APP_ROOT . '/apps/spends/core/user_state.php';
require APP_ROOT . '/apps/spends/controllers/Handlers.php';
