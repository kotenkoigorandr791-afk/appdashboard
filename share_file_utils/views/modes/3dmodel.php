<div class="model-viewer-container">
                <canvas id="model-canvas"></canvas>
                <div class="model-info" id="model-info">3D Model • <?php echo strtoupper(pathinfo($fileName, PATHINFO_EXTENSION)); ?></div>
                <div class="model-controls">
                    <button class="control-btn" onclick="window.modelControls?.rotateLeft()" title="<?php echo htmlspecialchars($translations['rotate_left'] ?? 'Rotate Left'); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                            <path d="M3 3v5h5"/>
                        </svg>
                        <span><?php echo htmlspecialchars($translations['rotate_left'] ?? 'Rotate'); ?></span>
                    </button>
                    <button class="control-btn" onclick="window.modelControls?.rotateRight()" title="<?php echo htmlspecialchars($translations['rotate_right'] ?? 'Rotate Right'); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/>
                            <path d="M21 3v5h-5"/>
                        </svg>
                        <span><?php echo htmlspecialchars($translations['rotate_right'] ?? 'Rotate'); ?></span>
                    </button>
                    <button class="control-btn" onclick="window.modelControls?.resetView()" title="<?php echo htmlspecialchars($translations['reset_zoom'] ?? 'Reset View'); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 8v4l3 3"/>
                        </svg>
                        <span><?php echo htmlspecialchars($translations['reset_zoom'] ?? 'Reset'); ?></span>
                    </button>
                    <button class="control-btn" onclick="window.modelControls?.toggleAutoRotate()" title="Auto Rotate">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74"/>
                            <path d="M21 3v5h-5"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <span>Auto</span>
                    </button>
                </div>
            </div>
