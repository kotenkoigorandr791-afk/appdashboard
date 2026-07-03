    <style>
        :root { --primary-color: <?php echo $color; ?>; --secondary-color: #34a853; --text-color: #333; --bg-color: #f5f5f5; --card-bg: #fff; --sidebar-bg: #fff; --header-bg: #fff; --shadow: 0 2px 10px rgba(0,0,0,0.1); --border: 1px solid #e0e0e0; --sidebar-width: 280px; }
        .dark-theme { --primary-color: <?php echo $color; ?>; --secondary-color: #81c995; --text-color: #e0e0e0; --bg-color: #1e1e1e; --card-bg: #2d2d2d; --sidebar-bg: #2d2d2d; --header-bg: #2d2d2d; --shadow: 0 2px 10px rgba(0,0,0,0.3); --border: 1px solid #444; }
        .dark-theme ::-webkit-scrollbar { width: 8px; height: 8px; }
        .dark-theme ::-webkit-scrollbar-track { background: #2d2d2d; }
        .dark-theme ::-webkit-scrollbar-thumb { background: #555; border-radius: 4px; }
        .dark-theme ::-webkit-scrollbar-thumb:hover { background: #777; }
        * { margin: 0; padding: 0; box-sizing: border-box; transition: all 0.3s ease; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-color); color: var(--text-color); overflow-x: hidden; <?php if ($theme === 'dark') echo 'class="dark-theme"'; ?> }
        header { background-color: var(--header-bg); padding: 0.5rem 1rem; box-shadow: var(--shadow); display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .header-left { display: flex; align-items: center; gap: 1rem; position: relative; z-index: 10; }
        .header-right { display: flex; align-items: center; gap: 1rem; position: relative; z-index: 10; }
        .user-role { padding: 0.25rem 0.75rem; background: var(--primary-color); color: white; border-radius: 4px; font-size: 0.85rem; font-weight: 500; }
        .menu-toggle { cursor: pointer; font-size: 1.5rem; color: var(--primary-color); }
        .info-toggle { cursor: pointer; font-size: 1.5rem; color: var(--primary-color); }
        .search-container { position: relative; width: 200px; }
        .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--primary-color); }
        #searchBar { width: 100%; padding: 0.5rem 0.5rem 0.5rem 2rem; border: var(--border); border-radius: 4px; background-color: var(--card-bg); color: var(--text-color); font-size: 0.9rem; }
        .sidebar { position: fixed; top: 0; right: -100%; width: var(--sidebar-width); height: 100vh; background-color: var(--sidebar-bg); box-shadow: var(--shadow); padding: 1rem; z-index: 101; overflow-y: auto; transition: right 0.3s; }
        .sidebar.active { right: 0; }
        .content { position: relative; z-index: 10; }
        .sidebar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .close-btn { color: var(--primary-color); cursor: pointer; font-size: 1.5rem; }
        .settings-group { margin-bottom: 1.5rem; }
        .settings-group h4 { margin-bottom: 0.5rem; }
        select, input[type="text"], input[type="number"], input[type="range"], input[type="color"] { width: 100%; padding: 0.5rem; border: var(--border); border-radius: 4px; background-color: var(--card-bg); color: var(--text-color); margin-bottom: 0.5rem; font-size: 1rem; }
        input[type="range"] { -webkit-appearance: none; background: var(--card-bg); border: var(--border); height: 8px; border-radius: 4px; }
        input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; width: 16px; height: 16px; border-radius: 50%; background: var(--primary-color); cursor: pointer; }
        input[type="range"]::-moz-range-thumb { width: 16px; height: 16px; border-radius: 50%; background: var(--primary-color); cursor: pointer; border: none; }
        input[type="color"] { height: 40px; padding: 2px; }
        .btn { background-color: var(--primary-color); color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.9rem; width: 100%; margin-top: 0.5rem; }
        .btn:hover { opacity: 0.9; }
        .site-table { width: 100%; border-collapse: collapse; }
        .site-table th, .site-table td { padding: 0.5rem; border: var(--border); text-align: left; vertical-align: top; }
        .site-table th { background: var(--primary-color); color: white; }
        .site-table tr:hover { background: var(--bg-color); }
        .site-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .site-actions button { background: none; border: none; cursor: pointer; font-size: 1rem; padding: 0.3rem; }
        .content { padding: 1rem; margin-right: 0; width: 100%; transition: margin-right 0.3s, width 0.3s; }
        .content.shifted { margin-right: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); }
        .viewer-container { display: flex; gap: 1rem; height: calc(100vh - 120px); }
        .site-list { width: 250px; background: var(--card-bg); border: var(--border); border-radius: 8px; overflow-y: auto; box-shadow: var(--shadow); }
        .site-item { padding: 1rem; border-bottom: var(--border); cursor: pointer; transition: background-color 0.3s; display: flex; align-items: center; gap: 0.5rem; }
        .site-item img { width: 16px; height: 16px; object-fit: contain; }
        .site-item:hover { background-color: var(--bg-color); }
        .site-item.active { background-color: var(--primary-color); color: white; }
        .site-item:last-child { border-bottom: none; }
        .viewer-frame { flex: 1; background: var(--card-bg); border: var(--border); border-radius: 8px; overflow: hidden; box-shadow: var(--shadow); }
        .frame-header { background-color: var(--primary-color); color: white; padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; }
        .frame-actions { display: flex; gap: 0.5rem; }
        .frame-actions button { background: rgba(255, 255, 255, 0.2); border: none; color: white; padding: 0.25rem 0.5rem; border-radius: 3px; cursor: pointer; font-size: 0.9rem; }
        .frame-actions button:hover { background: rgba(255, 255, 255, 0.3); }
        .viewer-iframe { width: 100%; height: calc(100% - 50px); border: none; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 102; justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; position: relative; }
        .close-modal { position: absolute; top: 1rem; right: 1rem; font-size: 1.5rem; cursor: pointer; color: var(--primary-color); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.3rem; }
        .fullscreen-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: var(--bg-color); z-index: 103; }
        .fullscreen-modal.active { display: block; }
        .fullscreen-modal-header { 
                    background-color: var(--primary-color); 
                    color: white; 
                    padding: 0.75rem 1rem; 
                    display: flex; 
                    justify-content: space-between; 
                    align-items: center; 
                    position: sticky; 
                    top: 0; 
                    z-index: 104; 
                }
                .fullscreen-modal-header span {
                    font-weight: bold;
                    flex: 1;
                    text-align: center;
                }

                .site-item.active {
            background-color: var(--primary-color);
            color: white;
            position: relative;
        }

        .site-item:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: -2px;
        }
        .fullscreen-modal-iframe { width: 100%; height: calc(100% - 50px); border: none; }
        .fullscreen-modal-actions { display: flex; gap: 0.5rem; }
        .fullscreen-modal-actions button { background: rgba(255, 255, 255, 0.2); border: none; color: white; padding: 0.25rem 0.5rem; border-radius: 3px; cursor: pointer; font-size: 0.9rem; }
        .fullscreen-modal-actions button:hover { background: rgba(255, 255, 255, 0.3); }
        
        /* Internet Network Background Animation - Spider Web */
        .network-animation-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }
        
        .network-canvas {
            width: 100%;
            height: 100%;
            display: block;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 0;
        }
        
        /* Enhanced fullscreen modal with iframe expansion */
        .fullscreen-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: transparent;
            z-index: 103;
            pointer-events: none;
        }
        
        .fullscreen-modal.active {
            display: block;
            pointer-events: auto;
        }
        
        .fullscreen-modal-header {
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: absolute;
            top: -60px;
            left: 0;
            width: 100%;
            z-index: 104;
            transition: top 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .fullscreen-modal.active .fullscreen-modal-header {
            top: 0;
        }
        
        .fullscreen-modal-header span {
            font-weight: bold;
            flex: 1;
            text-align: center;
        }
        
        .fullscreen-modal-iframe {
            width: 100%;
            height: 100%;
            border: none;
            position: absolute;
            top: 50px;
            left: 0;
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .fullscreen-modal.active .fullscreen-modal-iframe {
            top: 0;
            opacity: 1;
        }
        .action-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 105; justify-content: center; align-items: center; }
        .action-modal.active { display: flex; }
        .action-modal-content { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; width: 90%; max-width: 400px; max-height: 90vh; overflow-y: auto; position: relative; }
        .action-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: var(--border); }
        .action-modal-title { font-size: 1.2rem; font-weight: bold; color: var(--primary-color); }
        .action-modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--primary-color); }
        .action-modal-body { margin-bottom: 1rem; }
        .action-modal-footer { display: flex; gap: 0.5rem; justify-content: flex-end; }
        .action-modal-btn { padding: 0.5rem 1rem; border-radius: 4px; border: none; cursor: pointer; font-size: 0.9rem; }
        .action-modal-btn.primary { background-color: var(--primary-color); color: white; }
        .action-modal-btn.secondary { background-color: var(--card-bg); color: var(--text-color); border: var(--border); }
        .action-modal-btn:hover { opacity: 0.9; }
        .info-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 106; justify-content: center; align-items: center; }
        .info-modal.active { display: flex; }
        .info-modal-content { background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; position: relative; }
        .info-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: var(--border); }
        .info-modal-title { font-size: 1.5rem; font-weight: bold; color: var(--primary-color); }
        .info-modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--primary-color); }
        .info-modal-body ul { margin-left: 1.5rem; margin-bottom: 1rem; }
        .info-modal-body li { margin-bottom: 0.5rem; }
        .site-actions i.fa-edit { color: #4CAF50; }
        .site-actions i.fa-trash { color: #f44336; }
        .site-actions i.fa-eye, .site-actions i.fa-eye-slash { color: #2196F3; }
        .dark-theme .site-actions i.fa-edit { color: #81c995; }
        .dark-theme .site-actions i.fa-trash { color: #e57373; }
        .dark-theme .site-actions i.fa-eye, .dark-theme .site-actions i.fa-eye-slash { color: #64b5f6; }
        .token-badge {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8rem;
            margin-left: 5px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-bottom: 0;
        }
        .token-input-group {
            display: flex;
            gap: 0.5rem;
        }
        .token-input-group input {
            flex: 1;
        }
        .token-input-group button {
            width: auto;
            margin-top: 0;
        }
        .hidden {
            display: none !important;
        }
        @media (max-width: 768px) {
            .viewer-container { 
                flex-direction: column; 
                height: auto; 
            } 
            .site-list { 
                width: 100%; 
                height: 200px; 
                order: 2;
                margin-top: 1rem;
            } 
            .viewer-frame { 
                height: 60vh;
                width: 100%;
            } 
            .viewer-iframe {
                height: 47vh;
            }
            .header-left h1 {
                margin-left: 40px;
                font-size: 1.5rem;
            }
            .header-right { 
                flex-wrap: wrap; 
                gap: 0.5rem; 
                justify-content: flex-end;
            } 
            .search-container { 
                width: 150px; 
            }
            .content {
                padding: 0.5rem;
            }
            .site-item {
                padding: 0.75rem;
                font-size: 0.9rem;
            }
            .frame-actions button {
                padding: 0.4rem 0.8rem;
                font-size: 1rem;
                min-width: 44px;
                min-height: 44px;
            }
        }
        @media (min-width: 769px) {
            .header-left h1 {
                margin-left: 60px;
                font-size: 2rem;
            }
        }
        .frame-actions i,
        .fullscreen-modal-actions i,
        .header-right i {
            color: inherit;
        }
    </style>
