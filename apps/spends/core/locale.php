<?php
// ─── Language & theme ──────────────────────────────────────────────────────────
require_once dirname(__DIR__, 3) . '/project/i18n_helpers.php';
$language     = ap_normalize_i18n_lang($_GET['lang'] ?? 'en');
$theme        = $_GET['theme'] ?? 'light';
$primaryColor = $_GET['color'] ?? '#007bff';
$translations = ap_load_i18n_from_dir(__DIR__ . '/..', $language);
// Keys live in i18n/<lang>/*.json (chunked). No inline fallback dictionary:
// an inline copy silently shadows missing translations with English text, so
// gaps must surface as missing keys instead. Run scripts/i18n-audit.py to check.
$GLOBALS['_t_i18n'] = $translations;

$monthNames = [
    'en' => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    'ru' => ['Янв','Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт','Ноя','Дек'],
    'be' => ['Студ','Лют','Сак','Крас','Май','Чэр','Ліп','Жнів','Вер','Каст','Ліст','Снеж'],
    'uk' => ['Січ','Лют','Бер','Кві','Тра','Чер','Ліп','Сер','Вер','Жов','Лис','Груд'],
];
