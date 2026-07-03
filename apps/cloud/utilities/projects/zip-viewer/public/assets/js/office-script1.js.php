            window.addEventListener('message', function(event) {
                if (event.data.type === 'closeOnlyOfficeEditor') {
                    window.location.href = '<?= buildUrl(['view' => null, 'path' => $currentPath]) ?>';
                }
            });
            
            function initOfficeEditor() {
                const config = <?php echo json_encode($docConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                try {
                    new DocsAPI.DocEditor('office-editor', config);
                } catch (error) {
                    console.error('Failed to load ONLYOFFICE editor:', error);
                    document.getElementById('office-editor').innerHTML = `
                        <div class="loading">
                            <h3><?= htmlspecialchars($i18n['error_loading']) ?></h3>
                            <p>${error.message}</p>
                            <button class="btn" onclick="location.reload()"><?= htmlspecialchars($i18n['retry']) ?></button>
                        </div>
                    `;
                }
            }
            
            if (typeof DocsAPI !== 'undefined') {
                initOfficeEditor();
            } else {
                document.getElementById('office-editor').innerHTML = `
                    <div class="loading">
                        <div class="spinner"></div>
                        <p><?= htmlspecialchars($i18n['loading_editor']) ?></p>
                    </div>
                `;
                
                let checkInterval = setInterval(() => {
                    if (typeof DocsAPI !== 'undefined') {
                        clearInterval(checkInterval);
                        initOfficeEditor();
                    }
                }, 500);
            }
        