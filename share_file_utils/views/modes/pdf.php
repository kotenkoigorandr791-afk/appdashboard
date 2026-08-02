<div class="pdf-viewer-container" id="pdf-viewer-container">
                <!-- Loading progress -->
                <div class="progress-container" id="pdf-progress-container">
                    <div class="progress-bar" id="pdf-progress-bar"></div>
                </div>
                <div class="progress-text" id="pdf-progress-text">0%</div>
                
                <!-- PDF control panel -->
                <div class="pdf-controls" id="pdf-controls">
                    <div class="toolbar-group">
                        <button class="control-btn" id="pdf-prev" title="Previous Page"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button>
                        <span id="pdf-page-info">
                            <input type="text" id="pdf-page-input" value="1"> / <span id="pdf-page-count">1</span>
                        </span>
                        <button class="control-btn" id="pdf-next" title="Next Page"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></button>
                    </div>
                    
                    <div class="toolbar-group">
                        <button class="control-btn" id="pdf-zoom-out" title="Zoom Out"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg></button>
                        <div class="zoom-select-container">
                            <select id="pdf-zoom-select" class="zoom-select">
                                <option value="0.25">25%</option>
                                <option value="0.5">50%</option>
                                <option value="0.75">75%</option>
                                <option value="1" selected>100%</option>
                                <option value="1.25">125%</option>
                                <option value="1.5">150%</option>
                                <option value="1.75">175%</option>
                                <option value="2">200%</option>
                                <option value="2.5">250%</option>
                                <option value="3">300%</option>
                            </select>
                            <svg class="zoom-select-arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                        <button class="control-btn" id="pdf-zoom-in" title="Zoom In"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></button>
                        <button class="control-btn" id="pdf-fit" title="Fit to Width"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8l4 4-4 4"/><path d="M2 12h20"/><path d="M6 8l-4 4 4 4"/></svg></button>
                    </div>
                    
                    <div class="toolbar-group">
                        <button class="control-btn" id="pdf-rotate-left" title="Rotate Left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>
                        <button class="control-btn" id="pdf-rotate-right" title="Rotate Right"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg></button>
                    </div>
                </div>
                
                <!-- Container for PDF pages -->
                <div class="pdf-viewer" id="pdf-viewer"></div>
                
                <!-- Loading indicator -->
                <div class="pdf-loading" id="pdf-loading">
                    <div class="spinner"></div>
                    <p><?php echo htmlspecialchars($translations['binary_file'] ?? 'Loading PDF...'); ?></p>
                </div>
            </div>
