<div id="docsGrid" class="docs-grid">
    <?php if (empty($docsFiles)): ?>
        <div class="empty-state" style="grid-column: 1/-1;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
            </svg>
            <p data-i18n="no_docs_found"><?php echo htmlspecialchars(t('no_docs_found', 'No documentation files found')); ?></p>
        </div>
    <?php else: ?>
        <?php foreach ($docsFiles as $index => $doc): ?>
            <div class="doc-tile" onclick="openDoc('<?php echo htmlspecialchars($doc['filename']); ?>')" data-filename="<?php echo htmlspecialchars($doc['filename']); ?>">
                <?php if ($index < 3): ?>
                    <span class="doc-tile-badge">Popular</span>
                <?php endif; ?>
                <div class="doc-tile-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                </div>
                <div class="doc-tile-title"><?php echo htmlspecialchars($doc['title']); ?></div>
                <div class="doc-tile-desc"><?php echo htmlspecialchars($doc['description']); ?></div>
                <div class="doc-tile-meta">
                    <div class="doc-tile-meta-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        <span><?php echo htmlspecialchars($doc['readTime']); ?> <?php echo htmlspecialchars(t('minutes', 'min')); ?> read</span>
                    </div>
                    <div class="doc-tile-meta-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <span>Updated <?php echo date('M d, Y', $doc['modified']); ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

