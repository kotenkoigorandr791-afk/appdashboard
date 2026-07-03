<?php
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $input = !empty($_POST) ? $_POST : json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['action'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid request: Missing action']);
            exit;
        }
        
        switch ($input['action']) {
            case 'save_position':
                try {
                    $filePath = $input['filePath'] ?? '';
                    $position = $input['position'] ?? 0;
                    
                    if (empty($filePath)) {
                        throw new Exception('File path is required.');
                    }
                    
                    $decodedPath = urldecode($filePath);
                    $filename = basename($decodedPath);
                    $relativePath = UPLOAD_DIR . $filename;
                    
                    $movies = loadMovies();
                    $movieFound = false;
                    
                    foreach ($movies as &$movie) {
                        if (isset($movie['file_path']) && 
                            ($movie['file_path'] === $filePath || 
                             $movie['file_path'] === $relativePath ||
                             basename($movie['file_path']) === $filename)) {
                            $movie['lastPosition'] = $position;
                            $movieFound = true;
                            break;
                        }
                    }
                    
                    if ($movieFound && saveMovies($movies)) {
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Movie not found for path: ' . $filePath]);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            case 'save_video_settings':
                try {
                    $seekStep = intval($input['seek_step'] ?? 10);
                    $volumeStep = intval($input['volume_step'] ?? 10);
                    
                    if ($seekStep < 1 || $seekStep > 60) {
                        throw new Exception('Seek step must be between 1 and 60 seconds');
                    }
                    if ($volumeStep < 1 || $volumeStep > 50) {
                        throw new Exception('Volume step must be between 1 and 50 percent');
                    }
                    
                    $config = loadConfig();
                    $config['video_settings'] = [
                        'seek_step' => $seekStep,
                        'volume_step' => $volumeStep
                    ];
                    
                    if (saveConfig($config)) {
                        echo json_encode(['success' => true, 'settings' => $config['video_settings']]);
                    } else {
                        throw new Exception('Failed to save video settings');
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            case 'get_video_settings':
                $config = loadConfig();
                echo json_encode(['success' => true, 'settings' => $config['video_settings']]);
                exit;
                
            case 'add_movie':
                // Проверка уровня доступа: требуется уровень 1 или выше
                global $user_access_level;
                if ($user_access_level < 1) {
                    echo json_encode(['success' => false, 'error' => 'Access denied. You need level 1 or higher to add movies.']);
                    exit;
                }
                
                try {
                    $title = trim($input['title'] ?? '');
                    $year = intval($input['year'] ?? 0);
                    $category = trim($input['category'] ?? '');
                    $rating = intval($input['rating'] ?? 0);
                    $source_lang = $input['source_lang'] ?? 'en';
                    $isLocal = filter_var($input['isLocal'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    
                    if (empty($title) || empty($category)) {
                        throw new Exception('Title and category are required');
                    }
                    
                    $link = '';
                    $filePath = '';
                    
                    if ($isLocal && isset($_FILES['movieFile'])) {
                        $file = $_FILES['movieFile'];
                        error_log("File upload attempt: name=" . ($file['name'] ?? 'N/A') . ", type=" . ($file['type'] ?? 'N/A') . ", size=" . ($file['size'] ?? 'N/A') . ", error=" . $file['error']);
                        
                        if ($file['error'] !== UPLOAD_ERR_OK) {
                            throw new Exception('File upload error: ' . getUploadErrorMessage($file['error']));
                        }
                        
                        $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg'];
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);
                        
                        if (!in_array($mimeType, $allowedTypes)) {
                            throw new Exception('Invalid file type. Only video files (MP4, WebM, OGG) are allowed for movies.');
                        }
                        
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = uniqid('movie_') . '.' . $ext;
                        $filePath = UPLOAD_DIR . $filename;
                        
                        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                            throw new Exception('Failed to move uploaded file. Check directory permissions or disk space.');
                        }
                        
                        $creationDate = date('Y-m-d H:i:s', filectime($filePath));
                    } else {
                        $link = trim($input['link'] ?? '');
                        if (empty($link) && !$isLocal) {
                            throw new Exception('Link is required for non-local movies');
                        }
                        $creationDate = date('Y-m-d H:i:s');
                    }
                    
                    $translations = [];
                    $languages = ['en', 'ru', 'uk', 'be'];
                    
                    foreach ($languages as $target_lang) {
                        $translations[$target_lang] = $target_lang === $source_lang ? $title : translate_text($title, $source_lang, $target_lang);
                    }
                    
                    $movie = [
                        'id' => uniqid(),
                        'title' => $translations,
                        'year' => $year,
                        'link' => $link,
                        'file_path' => $filePath,
                        'is_local' => $isLocal,
                        'category' => $category,
                        'rating' => $rating,
                        'user_ratings' => [],
                        'overall_rating' => $rating,
                        'date_added' => $creationDate,
                        'lastPosition' => 0
                    ];
                    
                    $movies = loadMovies();
                    array_unshift($movies, $movie);
                    
                    if (saveMovies($movies)) {
                        echo json_encode(['success' => true, 'movie' => $movie]);
                    } else {
                        throw new Exception('Failed to save movie to JSON file');
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            case 'get_movies':
                $movies = array_values(getVisibleMovies());
                foreach ($movies as &$movie) {
                    $comments = loadComments($movie['id']);
                    $userRatings = array_column($comments, 'rating');
                    $movie['overall_rating'] = calculateOverallRating($movie['rating'], $userRatings);
                }
                echo json_encode(['success' => true, 'movies' => $movies, 'user_access_level' => $GLOBALS['user_access_level']]);
                exit;
                
            case 'get_all_movies':
                $movies = loadMovies();
                $config = loadConfig();
                $hiddenMovies = $config['hidden_movies'] ?? [];
                $moviesWithVisibility = array_map(function($movie) use ($hiddenMovies) {
                    $movie['visible'] = !in_array($movie['id'], $hiddenMovies);
                    $comments = loadComments($movie['id']);
                    $userRatings = array_column($comments, 'rating');
                    $movie['overall_rating'] = calculateOverallRating($movie['rating'], $userRatings);
                    $movie['comments'] = $comments;
                    return $movie;
                }, $movies);
                echo json_encode(['success' => true, 'movies' => $moviesWithVisibility, 'user_access_level' => $GLOBALS['user_access_level']]);
                exit;
                
            case 'toggle_visibility':
                // Проверка уровня доступа: требуется уровень 2 или выше
                global $user_access_level;
                if ($user_access_level < 2) {
                    echo json_encode(['success' => false, 'error' => 'Access denied. You need level 2 or higher to manage movie visibility.']);
                    exit;
                }
                
                try {
                    $movieId = $input['movie_id'] ?? '';
                    $visible = $input['visible'] ?? false;
                    
                    $config = loadConfig();
                    $hiddenMovies = $config['hidden_movies'] ?? [];
                    
                    if ($visible) {
                        $hiddenMovies = array_filter($hiddenMovies, fn($id) => $id !== $movieId);
                    } else {
                        if (!in_array($movieId, $hiddenMovies)) {
                            $hiddenMovies[] = $movieId;
                        }
                    }
                    
                    $config['hidden_movies'] = array_values($hiddenMovies);
                    
                    if (saveConfig($config)) {
                        echo json_encode(['success' => true]);
                    } else {
                        throw new Exception('Failed to save config');
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            case 'delete_movie':
                // Проверка уровня доступа: требуется уровень 2 или выше
                global $user_access_level;
                if ($user_access_level < 2) {
                    echo json_encode(['success' => false, 'error' => 'Access denied. You need level 2 or higher to delete movies.']);
                    exit;
                }
                
                try {
                    $movieId = $input['movie_id'] ?? '';
                    $movies = loadMovies();
                    
                    $movieToDelete = null;
                    foreach ($movies as $movie) {
                        if ($movie['id'] === $movieId) {
                            $movieToDelete = $movie;
                            break;
                        }
                    }
                    
                    $movies = array_filter($movies, fn($movie) => $movie['id'] !== $movieId);
                    
                    if ($movieToDelete && ($movieToDelete['is_local'] ?? false) && !empty($movieToDelete['file_path']) && file_exists($movieToDelete['file_path'])) {
                        unlink($movieToDelete['file_path']);
                    }
                    
                    $commentFile = COMMENTS_DIR . $movieId . '.json';
                    if (file_exists($commentFile)) {
                        unlink($commentFile);
                    }
                    
                    $config = loadConfig();
                    $hiddenMovies = $config['hidden_movies'] ?? [];
                    $config['hidden_movies'] = array_values(array_filter($hiddenMovies, fn($id) => $id !== $movieId));
                    
                    if (saveMovies(array_values($movies)) && saveConfig($config)) {
                        echo json_encode(['success' => true]);
                    } else {
                        throw new Exception('Failed to delete movie');
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            case 'add_comment':
                try {
                    $movieId = $input['movie_id'] ?? '';
                    $userName = trim($input['user_name'] ?? '');
                    $commentText = trim($input['comment_text'] ?? '');
                    $rating = intval($input['rating'] ?? 0);
                    
                    if (empty($movieId) || empty($userName) || empty($commentText)) {
                        throw new Exception('Movie ID, user name and comment text are required');
                    }
                    
                    if ($rating < 1 || $rating > 5) {
                        throw new Exception('Rating must be between 1 and 5');
                    }
                    
                    $comments = loadComments($movieId);
                    
                    $newComment = [
                        'id' => uniqid(),
                        'user_name' => $userName,
                        'comment' => $commentText,
                        'rating' => $rating,
                        'date' => date('Y-m-d H:i:s')
                    ];
                    
                    $comments[] = $newComment;
                    
                    if (saveComments($movieId, $comments)) {
                        $movies = loadMovies();
                        foreach ($movies as &$movie) {
                            if ($movie['id'] === $movieId) {
                                $userRatings = array_column($comments, 'rating');
                                $movie['overall_rating'] = calculateOverallRating($movie['rating'], $userRatings);
                                break;
                            }
                        }
                        saveMovies($movies);
                        
                        echo json_encode(['success' => true, 'comment' => $newComment]);
                    } else {
                        throw new Exception('Failed to save comment');
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            case 'delete_comment':
                // Проверка уровня доступа: требуется уровень 2 или выше
                global $user_access_level;
                if ($user_access_level < 2) {
                    echo json_encode(['success' => false, 'error' => 'Access denied. You need level 2 or higher to delete comments.']);
                    exit;
                }
                
                try {
                    $movieId = $input['movie_id'] ?? '';
                    $commentId = $input['comment_id'] ?? '';
                    
                    if (empty($movieId) || empty($commentId)) {
                        throw new Exception('Movie ID and comment ID are required');
                    }
                    
                    $comments = loadComments($movieId);
                    $comments = array_filter($comments, fn($comment) => $comment['id'] !== $commentId);
                    
                    if (saveComments($movieId, array_values($comments))) {
                        $movies = loadMovies();
                        foreach ($movies as &$movie) {
                            if ($movie['id'] === $movieId) {
                                $userRatings = array_column($comments, 'rating');
                                $movie['overall_rating'] = calculateOverallRating($movie['rating'], $userRatings);
                                break;
                            }
                        }
                        saveMovies($movies);
                        
                        echo json_encode(['success' => true]);
                    } else {
                        throw new Exception('Failed to delete comment');
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            case 'get_comments':
                try {
                    $movieId = $input['movie_id'] ?? '';
                    if (empty($movieId)) {
                        throw new Exception('Movie ID is required');
                    }
                    $comments = loadComments($movieId);
                    echo json_encode(['success' => true, 'comments' => $comments, 'user_access_level' => $GLOBALS['user_access_level']]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
                exit;
        }
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
