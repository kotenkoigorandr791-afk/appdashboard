<div id="onlyoffice-editor" class="onlyoffice-viewer" style="width:100%;height:100%;position:relative;"></div>
            <script>
            <?php if(isset($docConfig)): ?>
            window.onlyofficeConfig = <?php echo json_encode($docConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            window.onlyofficeTheme = '<?php echo $uiTheme; ?>';
            window.onlyofficeColor = '<?php echo $color; ?>';
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.getElementById('onlyoffice-editor');
                container.innerHTML = '<div class="onlyoffice-loading"><div class="spinner"></div><p>Loading ONLYOFFICE viewer...</p></div>';
                if (typeof DocsAPI !== 'undefined') { 
                    new DocsAPI.DocEditor('onlyoffice-editor', window.onlyofficeConfig); 
                }
                else { 
                    let checkInterval = setInterval(() => { 
                        if (typeof DocsAPI !== 'undefined') { 
                            clearInterval(checkInterval); 
                            new DocsAPI.DocEditor('onlyoffice-editor', window.onlyofficeConfig); 
                        } 
                    }, 500); 
                }
            });
            <?php endif; ?>
            </script>
