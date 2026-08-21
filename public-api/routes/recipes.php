<?php
chdir(APP_ROOT . '/apps/recipes');
require_once APP_ROOT . '/apps/recipes/core/db.php';
require_once APP_ROOT . '/apps/recipes/core/schema.php';
require_once APP_ROOT . '/apps/recipes/core/translate.php';
require_once APP_ROOT . '/apps/recipes/core/image.php';
require_once APP_ROOT . '/apps/recipes/core/init.php';
require_once APP_ROOT . '/apps/recipes/models/Recipe.php';
require APP_ROOT . '/apps/recipes/controllers/get_handlers.php';
require APP_ROOT . '/apps/recipes/controllers/post_handlers.php';
