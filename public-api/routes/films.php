<?php
define('MOVIES_FILE', APP_ROOT . '/apps/films/movies.json');
define('CONFIG_FILE', APP_ROOT . '/apps/films/config.json');
define('UPLOAD_DIR', APP_ROOT . '/apps/films/uploads/');
define('COMMENTS_DIR', APP_ROOT . '/apps/films/comments/');
define('CHUNK_SIZE', 1024 * 1024);
require_once APP_ROOT . '/apps/films/core/db.php';
require_once APP_ROOT . '/apps/films/core/session.php';
require_once APP_ROOT . '/apps/films/core/helpers.php';
require_once APP_ROOT . '/apps/films/core/upload_errors.php';
require_once APP_ROOT . '/apps/films/models/MovieModel.php';
require APP_ROOT . '/apps/films/controllers/ApiController.php';
