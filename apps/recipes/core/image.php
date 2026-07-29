<?php
function recipes_gd_open_image(string $path): array {
    $info = @getimagesize($path);
    if ($info === false) return [null, null];
    $im = null;
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($path); break;
        case IMAGETYPE_PNG: $im = @imagecreatefrompng($path); break;
        case IMAGETYPE_GIF: $im = @imagecreatefromgif($path); break;
        case IMAGETYPE_WEBP: $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null; break;
    }
    return [$im, $info];
}

function recipes_image_resampled_copy($src, int $maxSide = 1600)
{
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w < 1 || $h < 1) {
        return null;
    }
    $nw = $w;
    $nh = $h;
    if ($w > $maxSide || $h > $maxSide) {
        $r = min($maxSide / $w, $maxSide / $h);
        $nw = max(1, (int)round($w * $r));
        $nh = max(1, (int)round($h * $r));
    }
    $dst = imagecreatetruecolor($nw, $nh);
    if (!$dst) {
        return null;
    }
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $tr = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $tr);
    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagesavealpha($dst, true);
    return $dst;
}

function recipes_image_save_optimized_with_python(string $data, string $dstDir, string $basename, int $maxSide): ?string
{
    $script = __DIR__ . '/image_optimize.py';
    if (!is_file($script)) {
        return null;
    }
    if (!is_dir($dstDir) && !mkdir($dstDir, 0777, true)) {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'recipes_img_');
    if ($tmp === false) {
        return null;
    }
    if (@file_put_contents($tmp, $data) === false) {
        @unlink($tmp);
        return null;
    }
    $base = rtrim($dstDir, '/') . '/' . $basename;
    $cmd = 'python3 ' . escapeshellarg($script) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($base) . ' ' . escapeshellarg((string)$maxSide);
    $lines = [];
    $code = 1;
    @exec($cmd, $lines, $code);
    @unlink($tmp);
    if ($code !== 0 || empty($lines)) {
        return null;
    }
    $name = trim((string)end($lines));
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
        return null;
    }
    return is_file(rtrim($dstDir, '/') . '/' . $name) ? $name : null;
}

function recipes_image_save_optimized_blob(string $data, string $dstDir, string $basename = 'image', int $maxSide = 1600): ?string
{
    if ($data === '') {
        return null;
    }
    $info = @getimagesizefromstring($data);
    if ($info === false || empty($info['mime']) || strpos((string)$info['mime'], 'image/') !== 0) {
        return null;
    }
    if (!is_dir($dstDir) && !mkdir($dstDir, 0777, true)) {
        return null;
    }
    if (extension_loaded('gd')) {
        $src = @imagecreatefromstring($data);
        if ($src) {
            $dst = recipes_image_resampled_copy($src, $maxSide);
            imagedestroy($src);
            if ($dst) {
                if (function_exists('imagewebp')) {
                    $name = $basename . '.webp';
                    $ok = @imagewebp($dst, rtrim($dstDir, '/') . '/' . $name, 78);
                    imagedestroy($dst);
                    return ($ok && is_file(rtrim($dstDir, '/') . '/' . $name)) ? $name : null;
                }
                $name = $basename . '.jpg';
                $white = imagecreatetruecolor(imagesx($dst), imagesy($dst));
                if ($white) {
                    $bg = imagecolorallocate($white, 255, 255, 255);
                    imagefilledrectangle($white, 0, 0, imagesx($white), imagesy($white), $bg);
                    imagecopy($white, $dst, 0, 0, 0, 0, imagesx($dst), imagesy($dst));
                    $ok = @imagejpeg($white, rtrim($dstDir, '/') . '/' . $name, 82);
                    imagedestroy($white);
                    imagedestroy($dst);
                    return ($ok && is_file(rtrim($dstDir, '/') . '/' . $name)) ? $name : null;
                }
                imagedestroy($dst);
            }
        }
    }
    $pythonName = recipes_image_save_optimized_with_python($data, $dstDir, $basename, $maxSide);
    if ($pythonName !== null) {
        return $pythonName;
    }
    if (!in_array((string)$info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        return null;
    }
    $ext = 'jpg';
    if ($info['mime'] === 'image/png') $ext = 'png';
    elseif ($info['mime'] === 'image/gif') $ext = 'gif';
    elseif ($info['mime'] === 'image/webp') $ext = 'webp';
    elseif ($info['mime'] === 'image/avif') $ext = 'avif';
    $name = $basename . '.' . $ext;
    return @file_put_contents(rtrim($dstDir, '/') . '/' . $name, $data) !== false ? $name : null;
}

function recipes_image_save_optimized_upload(array $file, string $dstDir, string $basename = 'image', int $maxSide = 1600): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    $data = @file_get_contents($file['tmp_name']);
    if ($data === false) {
        return null;
    }
    return recipes_image_save_optimized_blob($data, $dstDir, $basename, $maxSide);
}

function recipes_write_product_preview_webp(string $srcPath, string $dstPath): bool {
    if (!extension_loaded('gd')) return false;
    [$src, $info] = recipes_gd_open_image($srcPath);
    if (!$src) return false;
    $w = imagesx($src);
    $h = imagesy($src);
    $max = RECIPES_PRODUCT_PREVIEW_MAX_SIDE;
    $nw = $w;
    $nh = $h;
    if ($w > $max || $h > $max) {
        $r = min($max / $w, $max / $h);
        $nw = max(1, (int)round($w * $r));
        $nh = max(1, (int)round($h * $r));
    }
    $dst = imagecreatetruecolor($nw, $nh);
    if (!$dst) { imagedestroy($src); return false; }
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $tr = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $tr);
    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagesavealpha($dst, true);
    imagedestroy($src);
    $ok = function_exists('imagewebp') ? @imagewebp($dst, $dstPath, 82) : false;
    imagedestroy($dst);
    return $ok && is_file($dstPath);
}
function recipes_product_preview_refresh(string $products_dir, string $product_id, ?string $main_image): ?string {
    $dir = __DIR__ . '/../' . $products_dir . '/' . $product_id;
    $prev = $dir . '/' . RECIPES_PRODUCT_PREVIEW_FILE;
    if (is_file($prev)) @unlink($prev);
    if ($main_image === null || $main_image === '') return null;
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $main_image)) return null;
    $src = $dir . '/' . $main_image;
    if (!is_readable($src)) return null;
    if (!recipes_write_product_preview_webp($src, $prev)) return null;
    return RECIPES_PRODUCT_PREVIEW_FILE;
}
function recipes_save_product_json(string $products_dir, array $row): void {
    $id = $row['id'];
    $path = __DIR__ . '/../' . $products_dir . '/' . $id . '/product.json';
    if (!is_dir(dirname($path))) return;
    $data = [
        'id' => $id,
        'name' => [
            'en' => $row['name_en'] ?? '',
            'uk' => $row['name_uk'] ?? '',
            'ru' => $row['name_ru'] ?? '',
            'be' => $row['name_be'] ?? '',
        ],
        'category' => $row['category'] ?? '',
        'image' => $row['image'] ?? null,
        'image_preview' => $row['image_preview'] ?? null,
    ];
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function recipes_load_product_row($db, string $product_id): ?array {
    try {
        $stmt = $db->prepare("SELECT * FROM " . RECIPES_DB_TABLE_PREFIX . "products WHERE id = :id");
        $stmt->bindValue(':id', $product_id, PDO::PARAM_STR);
        $r = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    } catch (Exception $e) {
        return null;
    }
}
function recipes_backfill_product_previews(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();
    if (!$db) return;
    recipes_ensure_products_image_preview_column($db);
    try {
        $chk = $db->query("SELECT value FROM " . RECIPES_DB_TABLE_PREFIX . "config WHERE key = 'product_previews_v1'")->fetch(PDO::FETCH_ASSOC);
        if ($chk && ($chk['value'] ?? '') === '1') return;
    } catch (Exception $e) {
        return;
    }
    $pd = 'products';
    try {
        $q = $db->query("SELECT id FROM " . RECIPES_DB_TABLE_PREFIX . "products");
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $full = recipes_load_product_row($db, $row['id']);
            if (!$full) continue;
            $main = $full['image'] ?? '';
            $preview_name = ($main !== '') ? recipes_product_preview_refresh($pd, $full['id'], $main) : null;
            $stmt = $db->prepare("UPDATE " . RECIPES_DB_TABLE_PREFIX . "products SET image_preview = :p WHERE id = :id");
            $stmt->bindValue(':p', $preview_name, $preview_name === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id', $full['id'], PDO::PARAM_STR);
            $stmt->execute();
            $full['image_preview'] = $preview_name;
            recipes_save_product_json($pd, $full);
        }
        $ins = $db->prepare("INSERT INTO " . RECIPES_DB_TABLE_PREFIX . "config (key, value, updated_at) VALUES ('product_previews_v1', '1', CURRENT_TIMESTAMP) ON CONFLICT (key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at");
        $ins->execute();
    } catch (Exception $e) {
        error_log('product preview backfill: ' . $e->getMessage());
    }
}
function recipes_product_thumb_public_path(array $p, string $products_dir): string {
    $id = $p['id'];
    $pv = $p['image_preview'] ?? '';
    if ($pv !== '' && is_file(__DIR__ . '/../' . $products_dir . '/' . $id . '/' . $pv)) {
        return $products_dir . '/' . $id . '/' . $pv;
    }
    if (!empty($p['image'])) return $products_dir . '/' . $id . '/' . $p['image'];
    return 'placeholder.jpg';
}

function recipes_normalize_product_name(string $name): string {
    $name = trim($name);
    $name = preg_replace('/\s+/u', ' ', $name);
    return mb_strtolower($name, 'UTF-8');
}

function recipes_product_name_exists($db, array $names): bool {
    if (!$db) return false;
    $normalized = [];
    foreach ($names as $name) {
        $n = recipes_normalize_product_name((string)$name);
        if ($n !== '') $normalized[] = $n;
    }
    $normalized = array_values(array_unique($normalized));
    if (!$normalized) return false;

    try {
        $conditions = [];
        foreach (['name_en', 'name_uk', 'name_ru', 'name_be'] as $column) {
            foreach ($normalized as $idx => $name) {
                $conditions[] = "LOWER(TRIM($column)) = :name_$idx";
            }
        }
        $stmt = $db->prepare("SELECT id FROM " . RECIPES_DB_TABLE_PREFIX . "products WHERE " . implode(' OR ', $conditions) . " LIMIT 1");
        foreach ($normalized as $idx => $name) {
            $stmt->bindValue(":name_$idx", $name, PDO::PARAM_STR);
        }
        $result = $stmt->execute();
        return (bool)$result->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Product duplicate check failed: " . $e->getMessage());
        return false;
    }
}

// Run migration on app load
migrateJSONToSQLite();
recipes_backfill_product_previews();
