<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/lib/files.php';
require __DIR__ . '/lib/data.php';
require __DIR__ . '/handlers.php';
require __DIR__ . '/views/layout_head.php';
?>
<body<?php echo $isFullscreen ? ' class="fullscreen-mode"' : ''; ?>>
<div class="content-wrapper<?php echo $isFullscreen ? ' fullscreen' : ''; ?><?php echo $isFolder ? ' with-sidebar' : ''; ?>">
<?php include __DIR__ . '/views/folder_sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/views/header.php'; ?>
<?php include __DIR__ . '/views/viewer.php'; ?>
</div>
</div>
<?php include __DIR__ . '/views/modals.php'; ?>
<?php include __DIR__ . '/views/layout_foot.php'; ?>
