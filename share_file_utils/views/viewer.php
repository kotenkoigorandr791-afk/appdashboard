<div class="viewer-container">
<?php
$modeFile = SFU_DIR . '/views/modes/';
if ($fileType === 'protected') {
    include $modeFile . 'protected.php';
} elseif ($fileType === 'image') {
    include $modeFile . 'image.php';
} elseif ($fileType === 'video') {
    include $modeFile . 'video.php';
} elseif ($fileType === 'audio') {
    include $modeFile . 'audio.php';
} elseif ($fileType === '3dmodel') {
    include $modeFile . '3dmodel.php';
} elseif ($fileType === 'pdf') {
    include $modeFile . 'pdf.php';
} elseif ($fileType === 'office') {
    include $modeFile . 'office.php';
} elseif ($fileType === 'text' && $fileContent !== false && $fileContent !== 'FILE_TOO_LARGE') {
    include $modeFile . 'text.php';
} elseif ($fileContent === 'FILE_TOO_LARGE') {
    include $modeFile . 'too_large.php';
} elseif ($fileType === 'archive') {
    include $modeFile . 'archive.php';
} else {
    include $modeFile . 'fallback.php';
}
?>
</div>
