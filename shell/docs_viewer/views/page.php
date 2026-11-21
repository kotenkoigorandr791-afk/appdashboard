<?php
$docAssets = '/project/assets/docs_viewer';
$docCss = ['base', 'header', 'grid', 'viewer', 'content', 'responsive'];
$docJs = ['i18n', 'view', 'links', 'search', 'init'];
$docVer = static function (string $path): int { return (int)@filemtime(dirname(__DIR__, 3) . $path); };
$accentHover = sprintf('#%02x%02x%02x', max(0, hexdec(substr($color, 1, 2)) - 30), max(0, hexdec(substr($color, 3, 2)) - 30), max(0, hexdec(substr($color, 5, 2)) - 30));
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($language); ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('documentation', 'Documentation')); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>:root{--accent:<?php echo htmlspecialchars($color); ?>;--accent-hover:<?php echo $accentHover; ?>;--accent-dim:<?php echo htmlspecialchars($color); ?>2e}</style>
<?php foreach ($docCss as $f): $p = "/project/assets/docs_viewer/css/$f.css"; ?>
    <link rel="stylesheet" href="<?php echo $p . '?v=' . $docVer($p); ?>">
<?php endforeach; ?>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
    <main class="doc-container">
<?php include __DIR__ . '/grid.php'; ?>
<?php include __DIR__ . '/viewer.php'; ?>
    </main>
    <script src="/assets/simple-markdown.js"></script>
    <script>var docsData = <?php echo json_encode($docsFiles); ?>; var translations = <?php echo json_encode($translations); ?>;</script>
<?php foreach ($docJs as $f): ?>
    <script src="<?php echo "$docAssets/js/$f.js"; ?>"></script>
<?php endforeach; ?>
</body>
</html>
