(function () {
    'use strict';
    function markdownPreview(editor, preview, value) {
        $.post(editor.data('preview-url'), {text: value}).done(function (html) {
            preview.html(html);
        });
    }
    function initEditor(root) {
        var textarea = root.find('textarea[name="description"]');
        if (!textarea.length || root.data('appdashboard-ready')) return;
        root.data('appdashboard-ready', true);
        var editor = root.find('.text-editor');
        var preview = $('<div class="appdashboard-live-markdown markdown" aria-live="polite"></div>');
        editor.find('.text-editor-view-mode').remove();
        editor.find('.text-editor-write-mode').after(preview);
        editor.find('.text-editor-toolbar a').first().remove();
        textarea.on('input', function () { markdownPreview(editor, preview, textarea.val()); });
        markdownPreview(editor, preview, textarea.val());
    }
    function init() {
        $('.task-form-container .js-text-editor').each(function () { initEditor($(this)); });
        $('.task-form-container .color-picker').each(function () {
            var select = $(this);
            if (select.data('appdashboard-color')) return;
            select.data('appdashboard-color', true);
            select.select2({minimumResultsForSearch: Infinity, templateResult: colorOption, templateSelection: colorOption});
        });
    }
    function colorOption(item) {
        if (!item.id) return item.text;
        return $('<span class="appdashboard-color-option"><span class="color-picker-square color-' + item.id + '"></span></span>');
    }
    if (window.KB && KB.on) KB.on('modal.afterRender', init);
    $(init);
}());
