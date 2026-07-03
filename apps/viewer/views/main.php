<body>
    <!-- Spider Web Background Animation -->
    <canvas class="network-canvas" id="networkCanvas"></canvas>
    
    <header>
        <div class="header-left">
            <h1 id="headerTitle">Web Dashboard</h1>
        </div>
        <div class="header-right">
            <div class="user-role" id="userRoleLabel">Guest</div>
            <i class="fas fa-info-circle info-toggle" id="infoToggle" title="Information"></i>
            <i class="fas fa-bars menu-toggle" id="menuToggle" title="Menu"></i>
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchBar" placeholder="Search sites...">
            </div>
        </div>
    </header>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2 id="settingsTitle">Settings</h2>
            <i class="fas fa-times close-btn" id="closeSidebar"></i>
        </div>
        
        <div class="settings-group <?php echo ($userRole < 2) ? 'hidden' : ''; ?>">
            <h4 id="tokenManagementTitle">Token Management</h4>
            <button class="btn" id="manageTokenBtn">Manage Token</button>
        </div>
        
        <div class="settings-group <?php echo ($userRole < 1) ? 'hidden' : ''; ?>">
            <h4 id="addSiteTitle">Add site</h4>
            <button class="btn" id="addSiteBtn">Add site</button>
        </div>
        <div class="settings-group">
            <h4 id="sitesTitle">Sites</h4>
            <table class="site-table">
                <thead>
                    <tr>
                        <th id="siteNameHeader">Name</th>
                        <th id="actionsHeader">Actions</th>
                    </tr>
                </thead>
                <tbody id="siteTableBody"></tbody>
            </table>
        </div>
    </div>

    <div class="content" id="content">
        <div class="viewer-container">
            <div class="site-list" id="siteList"></div>
            
            <div class="viewer-frame">
                <div class="frame-header">
                    <span id="currentSiteTitle">Select a site</span>
                    <div class="frame-actions">
                        <button id="refreshBtn" title="Refresh"><i class="fas fa-sync-alt"></i></button>
                        <button id="openInModal" title="Open in full screen"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                <iframe class="viewer-iframe" id="viewerIframe" src="about:blank"></iframe>
            </div>
        </div>
    </div>

    <div class="modal" id="addSiteModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 id="modalTitle">Add site</h3>
            <div class="form-group">
                <label id="siteNameLabel">Site name:</label>
                <input type="text" id="siteName" placeholder="e.g., My Router">
            </div>
            <div class="form-group">
                <label id="siteUrlLabel">Site URL:</label>
                <input type="text" id="siteUrl" placeholder="192.168.1.1 or http://192.168.1.100">
            </div>
            <div class="form-group">
                <label id="sitePriorityLabel">Priority:</label>
                <input type="number" id="sitePriority" min="0" max="10" value="0">
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="useTokenCheckbox">
                <label for="useTokenCheckbox" id="useTokenLabel">Add token to URL</label>
                <span class="token-badge" id="tokenBadge">Token</span>
            </div>
            <button class="btn" id="saveSiteBtn">Save site</button>
        </div>
    </div>

    <div class="modal" id="tokenModal">
        <div class="modal-content">
            <span class="close-modal" id="closeTokenModal">&times;</span>
            <h3 id="tokenModalTitle">Manage Token</h3>
            <div class="form-group">
                <label id="currentTokenLabel">Current Token:</label>
                <input type="text" id="currentTokenInput" readonly>
            </div>
            <div class="form-group">
                <label id="newTokenLabel">New Token:</label>
                <div class="token-input-group">
                    <input type="text" id="newTokenInput" placeholder="Enter new token">
                    <button class="btn" id="updateTokenBtn">Update</button>
                </div>
            </div>
            <button class="btn" id="generateTokenBtn">Generate New Token</button>
        </div>
    </div>

    <div class="info-modal" id="infoModal">
        <div class="info-modal-content">
            <div class="info-modal-header">
                <span class="info-modal-title" id="infoModalTitle">Information</span>
                <button class="info-modal-close" id="closeInfoModal">&times;</button>
            </div>
            <div class="info-modal-body" id="infoModalBody">
                <p id="infoModalDescription">Dashboard functionality description.</p>
            </div>
        </div>
    </div>

    <div class="fullscreen-modal" id="fullscreenModal">
        <div class="fullscreen-modal-header">
            <span id="fullscreenSiteTitle">Select a site</span>
            <div class="fullscreen-modal-actions">
                <button id="closeFullscreenModal" title="Close"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <iframe class="fullscreen-modal-iframe" id="fullscreenIframe" src="about:blank"></iframe>
    </div>

    <div class="action-modal" id="actionModal">
        <div class="action-modal-content">
            <div class="action-modal-header">
                <span class="action-modal-title" id="actionModalTitle">Confirm</span>
                <button class="action-modal-close" id="closeActionModal">&times;</button>
            </div>
            <div class="action-modal-body" id="actionModalBody">
                Are you sure?
            </div>
            <div class="action-modal-footer">
                <button class="action-modal-btn secondary" id="actionModalCancel">Cancel</button>
                <button class="action-modal-btn primary" id="actionModalConfirm">Confirm</button>
            </div>
        </div>
    </div>
