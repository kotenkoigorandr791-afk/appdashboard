<?php
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $isMarkdown = ($extension === 'md');
            ?>
            <?php if ($isMarkdown): ?>
            <div class="markdown-view-toggle">
                <button id="preview-mode-btn" class="active" onclick="switchToPreview()" title="Preview">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                    </svg>
                </button>
                <button id="editor-mode-btn" onclick="switchToEditor()" title="Editor">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                    </svg>
                </button>
            </div>
            <div class="markdown-preview-container" id="markdown-preview">
                <div class="markdown-preview-content" id="markdown-content"></div>
            </div>
            <?php endif; ?>
            <div class="monaco-container" id="monaco-editor" style="<?php echo $isMarkdown ? 'display:none;' : ''; ?>"></div>
