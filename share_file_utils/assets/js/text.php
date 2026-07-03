<?php if ($fileType === 'text' && $fileContent !== false && $fileContent !== 'FILE_TOO_LARGE'): ?>
<script>
<?php
$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$isMarkdown = ($extension === 'md');
?>
<?php if ($isMarkdown): ?>
const isMarkdownFile = true;
const fileContent = <?php echo json_encode($fileContent, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
function initMarkdownPreview() {
    const markedAvailable = (typeof window.markedLib !== 'undefined') || (typeof window.marked !== 'undefined');
    if (markedAvailable && typeof renderMarkdown === 'function') {
        const element = document.getElementById('markdown-content');
        if (element) renderMarkdown(fileContent);
    }
}
let markedLoadAttempts = 0;
const maxMarkedLoadAttempts = 15;
function waitForMarkedAndRender() {
    markedLoadAttempts++;
    const markedAvailable = (typeof window.markedLib !== 'undefined') || (typeof window.marked !== 'undefined');
    if (markedAvailable) initMarkdownPreview();
    else if (markedLoadAttempts < maxMarkedLoadAttempts) setTimeout(waitForMarkedAndRender, 300);
    else {
        const element = document.getElementById('markdown-content');
        if (element) element.innerHTML = '<div style="padding:40px;text-align:center;color:#ff6b6b;"><h3>Failed to load Markdown library</h3></div>';
    }
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', waitForMarkedAndRender);
else waitForMarkedAndRender();
window.markdownContentReady = function() {};
window.initMonacoForMarkdown = function() {
<?php else: ?>
const isMarkdownFile = false;
<?php endif; ?>
require(['vs/editor/editor.main'], function() {
    const fileContent = <?php echo json_encode($fileContent, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
    const fileName = <?php echo json_encode($fileName); ?>;
    const languageMap = {
        'js': 'javascript', 'jsx': 'javascript', 'ts': 'typescript', 'tsx': 'typescript',
        'html': 'html', 'htm': 'html', 'css': 'css', 'php': 'php', 'py': 'python',
        'json': 'json', 'xml': 'xml', 'md': 'markdown', 'sql': 'sql', 'java': 'java',
        'cpp': 'cpp', 'c': 'c', 'cs': 'csharp', 'm': 'objective-c', 'yaml': 'yaml',
        'yml': 'yaml', 'ini': 'ini', 'conf': 'ini', 'sh': 'shell', 'bash': 'shell',
        'bat': 'bat', 'cmd': 'bat', 'ps1': 'powershell', 'vue': 'vue', 'tex': 'latex',
        'latex': 'latex', 'txt': 'plaintext', 'log': 'log', 'csv': 'plaintext'
    };
    const extension = fileName.split('.').pop().toLowerCase();
    const language = languageMap[extension] || 'plaintext';
    window.editor = monaco.editor.create(document.getElementById('monaco-editor'), {
        value: fileContent,
        language: language,
        theme: '<?php echo $theme === 'dark' ? 'vs-dark' : 'vs'; ?>',
        readOnly: true,
        minimap: { enabled: true, size: 'fill', showSlider: 'always' },
        scrollBeyondLastLine: false,
        wordWrap: 'on',
        fontSize: 14,
        lineNumbers: 'on',
        folding: true,
        lineDecorationsWidth: 10,
        lineNumbersMinChars: 3,
        automaticLayout: true,
        domReadOnly: <?php echo (($fileData['disable_copy'] ?? 0) === 1 || ($fileData['view_only'] ?? 0) === 1) ? 'true' : 'false'; ?>,
        selectionClipboard: <?php echo (($fileData['disable_copy'] ?? 0) === 1 || ($fileData['view_only'] ?? 0) === 1) ? 'false' : 'true'; ?>,
        overviewRulerLanes: 0,
        hideCursorInOverviewRuler: true,
        scrollbar: {
            vertical: 'hidden', horizontal: 'hidden', useShadows: false,
            verticalHasArrows: false, horizontalHasArrows: false,
            verticalScrollbarSize: 10, horizontalScrollbarSize: 10, arrowSize: 14
        }
    });
    window.editor.addAction({
        id: 'copy-all',
        label: 'Copy All Content',
        keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyC],
        run: function() { copyAllContent(); }
    });
});
<?php if ($isMarkdown): ?>
};
<?php endif; ?>
</script>
<?php endif; ?>
