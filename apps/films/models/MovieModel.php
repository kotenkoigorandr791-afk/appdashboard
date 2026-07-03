<?php
function loadMovies() {
    return file_exists(MOVIES_FILE) ? json_decode(file_get_contents(MOVIES_FILE), true) ?: [] : [];
}

function saveMovies($movies) {
    return file_put_contents(MOVIES_FILE, json_encode($movies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadConfig() {
    $config = file_exists(CONFIG_FILE) ? json_decode(file_get_contents(CONFIG_FILE), true) ?: ['hidden_movies' => [], 'video_settings' => ['seek_step' => 10, 'volume_step' => 10]] : ['hidden_movies' => [], 'video_settings' => ['seek_step' => 10, 'volume_step' => 10]];
    return $config;
}

function saveConfig($config) {
    return file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getVisibleMovies() {
    $movies = loadMovies();
    $config = loadConfig();
    $hiddenMovies = $config['hidden_movies'] ?? [];
    return array_filter($movies, function($movie) use ($hiddenMovies) {
        return !in_array($movie['id'], $hiddenMovies);
    });
}

function loadComments($movieId) {
    $commentFile = COMMENTS_DIR . $movieId . '.json';
    return file_exists($commentFile) ? json_decode(file_get_contents($commentFile), true) ?: [] : [];
}

function saveComments($movieId, $comments) {
    $commentFile = COMMENTS_DIR . $movieId . '.json';
    return file_put_contents($commentFile, json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function calculateOverallRating($baseRating, $userRatings) {
    if (empty($userRatings)) {
        return $baseRating;
    }
    $averageUserRating = array_sum($userRatings) / count($userRatings);
    return ($baseRating * 0.5) + ($averageUserRating * 0.5);
}
