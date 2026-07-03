<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers.php';

function handleAlbumActions(int $userRole, string $app, string $lang, string $theme, string $color): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_album'])) {
        $newAlbum = [
            'id' => uniqid(),
            'name' => trim($_POST['album_name'] ?? ''),
            'url' => trim($_POST['album_url'] ?? ''),
            'preview' => '',
            'created' => date('Y-m-d H:i:s'),
            'min_role' => (int) ($_POST['min_role'] ?? 0)
        ];
        if ($newAlbum['name'] === '' || $newAlbum['url'] === '') {
            header("Location: ?app=$app&lang=$lang&theme=$theme&color=" . urlencode($color));
            exit;
        }

        $previewsDir = __DIR__ . '/../previews';
        if (!is_dir($previewsDir)) @mkdir($previewsDir, 0755, true);
        if (!empty($_FILES['preview_file']['tmp_name'])) {
            $previewData = file_get_contents($_FILES['preview_file']['tmp_name']);
            $previewPath = 'previews/' . $newAlbum['id'] . '.jpg';
            file_put_contents(__DIR__ . '/../' . $previewPath, $previewData);
            $newAlbum['preview'] = $previewPath;
        } elseif (!empty($_POST['preview_base64'])) {
            $previewPath = 'previews/' . $newAlbum['id'] . '.jpg';
            $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $_POST['preview_base64']);
            file_put_contents(__DIR__ . '/../' . $previewPath, base64_decode($base64));
            $newAlbum['preview'] = $previewPath;
        }
        addGalleryAlbum($newAlbum);
        header("Location: ?app=$app&lang=$lang&theme=$theme&color=" . urlencode($color));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_album']) && $userRole >= 1) {
        $albumId = trim($_POST['album_id'] ?? '');
        $album = $albumId !== '' ? getGalleryAlbumById($albumId) : null;
        if (!$album) {
            header("Location: ?app=$app&lang=$lang&theme=$theme&color=" . urlencode($color));
            exit;
        }

        $name = trim($_POST['edit_album_name'] ?? '');
        $url = trim($_POST['edit_album_url'] ?? '');
        if ($name === '' || $url === '') {
            header("Location: ?app=$app&lang=$lang&theme=$theme&color=" . urlencode($color));
            exit;
        }

        $createdInput = trim($_POST['edit_album_created'] ?? '');
        $created = $album['created'];
        if ($createdInput !== '') {
            $date = DateTime::createFromFormat('Y-m-d\TH:i', $createdInput);
            if ($date) {
                $created = $date->format('Y-m-d H:i:s');
            }
        }

        $previewPath = $album['preview'];
        if (!empty($_POST['edit_preview_base64'])) {
            $previewsDir = __DIR__ . '/../previews';
            if (!is_dir($previewsDir)) @mkdir($previewsDir, 0755, true);
            $newPreviewPath = 'previews/' . $albumId . '.jpg';
            $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $_POST['edit_preview_base64']);
            $decoded = base64_decode($base64, true);
            if ($decoded !== false) {
                file_put_contents(__DIR__ . '/../' . $newPreviewPath, $decoded);
                $previewPath = $newPreviewPath;
            }
        }

        updateGalleryAlbum([
            'id' => $albumId,
            'name' => $name,
            'url' => $url,
            'preview' => $previewPath,
            'created' => $created,
            'min_role' => (int) ($_POST['edit_min_role'] ?? $album['min_role']),
        ]);
        header("Location: ?app=$app&lang=$lang&theme=$theme&color=" . urlencode($color));
        exit;
    }

    if (isset($_GET['delete_album']) && $userRole >= 1) {
        $albumId = $_GET['delete_album'];
        $deletedAlbum = deleteGalleryAlbum($albumId);
        if ($deletedAlbum && !empty($deletedAlbum['preview']) && file_exists(__DIR__ . '/../' . $deletedAlbum['preview'])) {
            @unlink(__DIR__ . '/../' . $deletedAlbum['preview']);
        }
        header("Location: ?app=$app&lang=$lang&theme=$theme&color=" . urlencode($color));
        exit;
    }

    if (isset($_GET['toggle_hide_album']) && $userRole >= 1) {
        $albumId = $_GET['toggle_hide_album'];
        toggleGalleryAlbumHidden($albumId);
        header("Location: ?app=$app&lang=$lang&theme=$theme&color=" . urlencode($color));
        exit;
    }

    if (isset($_GET['view_album'])) {
        $albumId = $_GET['view_album'];
        $views = incrementGalleryAlbumViews($albumId);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            echo json_encode(['success' => true, 'views' => $views]);
            exit;
        }
    }
}

function filterAlbumsForUser(array $albums, array $hiddenAlbums, int $userRole): array {
    $filtered = [];
    foreach ($albums as $album) {
        $isHidden = isset($hiddenAlbums[$album['id']]);
        $minRole = $album['min_role'] ?? 0;
        if ($userRole >= 1 || (!$isHidden && $userRole >= $minRole)) {
            $filtered[] = $album;
        }
    }
    return $filtered;
}
