<?php
function renderViewRoute($id) {
    global $lang, $theme, $color, $lang_array, $view_mode, $display_mode;
    
    $routes = getRoutes();
    $route = null;
    foreach ($routes as $r) {
        if ($r['id'] === $id) {
            $route = $r;
            break;
        }
    }
    
    if (!$route) {
        return '<p>Route not found</p>';
    }
    
    ob_start(); ?>
    <!DOCTYPE html>
    <html lang="<?= $lang ?>" data-theme="<?= $theme ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($route['name']) ?></title>
        <link href="leaflet/leaflet.css" rel="stylesheet">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        <style>
            :root { --primary-color: <?= $color ?>; --bg-color: <?= $theme === 'dark' ? '#1a1a1a' : '#ffffff' ?>; --text-color: <?= $theme === 'dark' ? '#ffffff' : '#000000' ?>; }
            body, html { margin: 0; padding: 0; height: 100%; width: 100%; overflow: hidden; background-color: var(--bg-color); color: var(--text-color); }
            #map { width: 100%; height: 100%; }
            .osm-dark-tiles { filter: invert(1) hue-rotate(180deg) brightness(0.95) contrast(0.9); }
            .header { position: absolute; top: 10px; right: 10px; z-index: 1000; background: rgba(0,0,0,0.7); padding: 10px 15px; border-radius: 22px; color: white; text-align: right; display: flex; align-items: center; gap: 10px; }
            .btn { background: var(--primary-color); color: white; padding: 10px 14px; text-decoration: none; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px; font-size: 14px; border: none; cursor: pointer; }
            .btn:disabled { opacity: 0.6; cursor: not-allowed; }
            .btn-icon { width: 16px; height: 16px; fill: currentColor; }
            .map-controls { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 1000; background: rgba(0,0,0,0.7); padding: 8px 12px; border-radius: 22px; color: white; display: flex; justify-content: center; align-items: center; }
            .map-type-btn { background: var(--primary-color); color: white; border: none; padding: 6px 12px; margin: 0 3px; border-radius: 999px; cursor: pointer; font-size: 12px; }
            .map-type-btn.active { background: #ff6b35; }
            .leaflet-top.leaflet-left { display: none; }
            .leaflet-bottom.leaflet-right .leaflet-control-zoom { margin-bottom: 70px; margin-right: 10px; }
            .satellite-info { position: absolute; bottom: 20px; left: 10px; z-index: 1000; background: rgba(0,0,0,0.62); color: rgba(255,255,255,0.9); padding: 5px 11px; border-radius: 12px; font-size: 11px; max-width: 380px; line-height: 1.45; display: none; pointer-events: none; }
            @media (max-width: 768px) { .header { padding: 8px 10px; } .btn { padding: 6px 10px; font-size: 12px; } .map-controls { padding: 6px 8px; } .map-type-btn { padding: 4px 8px; font-size: 11px; } }
            @media (max-width: 480px) { .header { top: 5px; right: 5px; padding: 6px 8px; } .btn { padding: 5px 8px; font-size: 10px; } .btn-icon { width: 14px; height: 14px; } .map-controls { bottom: 5px; } .map-type-btn { padding: 3px 6px; font-size: 10px; } }
        </style>
    </head>
    <body>
        <div class="header">
            <h3 style="margin: 0;"><?= htmlspecialchars($route['name']) ?></h3>
            <button class="btn" id="screenshot-btn">
                <svg class="btn-icon" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                <?= $lang_array['download'] ?>
            </button>
            <a href="?lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="btn"><svg class="btn-icon" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg> <?= $lang_array['back'] ?></a>
        </div>
        <div class="map-controls">
            <button class="map-type-btn active" data-type="streets"><?= $lang_array['streets'] ?></button>
            <button class="map-type-btn" data-type="satellite"><?= $lang_array['satellite'] ?></button>
        </div>
        <div class="satellite-info" id="satellite-info"></div>
        <div id="map"></div>
        <script src="leaflet/leaflet.js"></script>
        <script>
            const points = <?= json_encode($route['points']) ?>;
            const map = L.map("map", { attributionControl: false, zoomControl: false }).setView(points[0], 14);
            L.control.zoom({ position: 'bottomright' }).addTo(map);
            
            const streetsLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'<?= $theme === 'dark' ? ", { className: 'osm-dark-tiles' }" : '' ?>);
            const satelliteBase = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}');
            const satelliteLabels = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}');
            streetsLayer.addTo(map);
            const polyline = L.polyline(points, { color: '<?= $color ?>', weight: 4, opacity: 0.9, smoothFactor: 1 }).addTo(map);
            const markerA = L.marker(points[0], { icon: L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }) }).addTo(map);
            markerA.bindPopup("<?= $lang_array['start'] ?>", { closeButton: false, autoClose: false, closeOnClick: false });
            const markerB = L.marker(points[points.length - 1], { icon: L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }) }).addTo(map);
            markerB.bindPopup("<?= $lang_array['end'] ?>", { closeButton: false, autoClose: false, closeOnClick: false });
            map.fitBounds(polyline.getBounds());
            
            let isSatellite = false;
            let esriAttribCache = null;
            const satInfoEl = document.getElementById('satellite-info');

            async function fetchEsriAttrib() {
                if (esriAttribCache) return esriAttribCache;
                try {
                    const r = await fetch('https://static.arcgis.com/attribution/World_Imagery');
                    esriAttribCache = await r.json();
                } catch(e) { esriAttribCache = { contributors: [] }; }
                return esriAttribCache;
            }

            async function updateSatelliteInfo() {
                if (!isSatellite) { satInfoEl.style.display = 'none'; return; }
                satInfoEl.style.display = 'block';
                satInfoEl.textContent = '…';
                const data = await fetchEsriAttrib();
                const bounds = map.getBounds();
                const zoom = Math.round(map.getZoom());
                const texts = new Set();
                for (const c of (data.contributors || [])) {
                    for (const area of (c.coverageAreas || [])) {
                        if (zoom >= area.zoomMin && zoom <= area.zoomMax) {
                            const b = area.bbox;
                            if (bounds.intersects([[b[0], b[1]], [b[2], b[3]]])) {
                                texts.add(c.attribution);
                                break;
                            }
                        }
                    }
                }
                satInfoEl.textContent = texts.size ? [...texts].join(' · ') : 'Esri World Imagery';
            }

            map.on('moveend zoomend', updateSatelliteInfo);

            document.querySelectorAll('.map-type-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.map-type-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    const type = btn.dataset.type;
                    isSatellite = type === 'satellite';
                    map.eachLayer(layer => { if (layer instanceof L.TileLayer) map.removeLayer(layer); });
                    if (isSatellite) {
                        satelliteBase.addTo(map);
                        satelliteLabels.addTo(map);
                    } else {
                        streetsLayer.addTo(map);
                    }
                    polyline.addTo(map);
                    markerA.addTo(map);
                    markerB.addTo(map);
                    updateSatelliteInfo();
                });
            });

            document.getElementById('screenshot-btn').addEventListener('click', async function() {
                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<svg class="btn-icon" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z" opacity=".3"/><path d="M12 4a8 8 0 0 1 8 8h2a10 10 0 0 0-10-10z"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.8s" repeatCount="indefinite"/></path></svg>';
                const overlays = document.querySelectorAll('.header, .map-controls, .leaflet-control-container, .satellite-info');
                overlays.forEach(el => el.style.visibility = 'hidden');
                try {
                    const canvas = await html2canvas(document.getElementById('map'), { useCORS: true, allowTaint: false, scale: window.devicePixelRatio || 1, logging: false });
                    const link = document.createElement('a');
                    link.download = <?= json_encode($route['name']) ?> + '.png';
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                } catch(e) {
                    alert(<?php echo json_encode($lang_array['screenshot_failed'] ?? 'Screenshot failed:'); ?> + ' ' + e.message);
                } finally {
                    overlays.forEach(el => el.style.visibility = '');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            });
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function renderAllRoutesMap() {
    global $lang, $theme, $color, $lang_array, $view_mode, $display_mode;
    
    $routes = getRoutes();
    
    if (empty($routes)) {
        return '<p>' . htmlspecialchars($lang_array['no_routes_available']) . '</p>';
    }
    
    $colors = ['#78a300', '#ff5733', '#33a1ff', '#ff33a1', '#a333ff', '#33ffa1', '#ffa533', '#3353ff', '#ff3333', '#33ff57'];
    
    ob_start(); ?>
    <!DOCTYPE html>
    <html lang="<?= $lang ?>" data-theme="<?= $theme ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $lang_array['view_all_map'] ?></title>
        <link href="leaflet/leaflet.css" rel="stylesheet">
        <style>
            :root { --primary-color: <?= $color ?>; --bg-color: <?= $theme === 'dark' ? '#1a1a1a' : '#ffffff' ?>; --text-color: <?= $theme === 'dark' ? '#ffffff' : '#000000' ?>; }
            body, html { margin: 0; padding: 0; height: 100%; width: 100%; overflow: hidden; background-color: var(--bg-color); color: var(--text-color); }
            #map { width: 100%; height: 100%; }
            .osm-dark-tiles { filter: invert(1) hue-rotate(180deg) brightness(0.95) contrast(0.9); }
            .header { position: absolute; top: 10px; right: 10px; z-index: 1000; background: rgba(0,0,0,0.7); padding: 10px 15px; border-radius: 22px; color: white; text-align: right; display: flex; align-items: center; gap: 10px; }
            .btn { background: var(--primary-color); color: white; padding: 10px 14px; text-decoration: none; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px; font-size: 14px; }
            .btn-icon { width: 16px; height: 16px; fill: currentColor; }
            .route-popup { max-width: 200px; }
            .route-popup h4 { margin: 0 0 5px 0; color: var(--primary-color); }
            .route-popup .view-link { display: inline-block; margin-top: 5px; padding: 5px 10px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 999px; font-size: 12px; }
            
            .map-controls { 
                position: absolute; 
                bottom: 20px; 
                left: 50%; 
                transform: translateX(-50%); 
                z-index: 1000; 
                background: rgba(0,0,0,0.7); 
                padding: 8px 12px; 
                border-radius: 22px; 
                color: white; 
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .map-type-btn { background: var(--primary-color); color: white; border: none; padding: 6px 12px; margin: 0 3px; border-radius: 999px; cursor: pointer; font-size: 12px; }
            .map-type-btn.active { background: #ff6b35; }
            @media (max-width: 768px) { .header { padding: 8px 10px; } .btn { padding: 6px 10px; font-size: 12px; } .map-controls { padding: 6px 8px; } .map-type-btn { padding: 4px 8px; font-size: 11px; } }
            @media (max-width: 480px) { .header { top: 5px; right: 5px; padding: 6px 8px; } .btn { padding: 5px 8px; font-size: 10px; } .btn-icon { width: 14px; height: 14px; } .map-controls { top: 5px; left: 5px; padding: 5px 7px; } .map-type-btn { padding: 3px 6px; font-size: 10px; } }
        </style>
    </head>
    <body>
        <div class="header">
            <h3 style="margin: 0;"><?= $lang_array['view_all_map'] ?> (<?= count($routes) ?> routes)</h3>
            <a href="?lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="btn"><svg class="btn-icon" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg> <?= $lang_array['back'] ?></a>
        </div>
        <div class="map-controls">
            <button class="map-type-btn active" data-type="streets"><?= $lang_array['streets'] ?></button>
            <button class="map-type-btn" data-type="satellite"><?= $lang_array['satellite'] ?></button>
        </div>
        <div id="map"></div>
        <script src="leaflet/leaflet.js"></script>
        <script>
            const routes = <?= json_encode($routes) ?>;
            const colors = <?= json_encode($colors) ?>;
            const langTexts = <?= json_encode($lang_array) ?>;
            function t(key, fallback = '') {
                if (langTexts[key] !== undefined && langTexts[key] !== null) return langTexts[key];
                console.warn('[i18n] Missing translation key: "' + key + '"');
                return fallback || key;
            }
            
            const map = L.map("map", { attributionControl: false }).setView([50.0, 30.0], 3);
            
            const streetsLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'<?= $theme === 'dark' ? ", { className: 'osm-dark-tiles' }" : '' ?>);
            const satelliteBase = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}');
            const satelliteLabels = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}');
            streetsLayer.addTo(map);
            
            const routeLayers = [];
            const bounds = [];
            
            routes.forEach((route, index) => {
                if (route.points && route.points.length > 0) {
                    const routeColor = colors[index % colors.length];
                    
                    const polyline = L.polyline(route.points, { 
                        color: routeColor, 
                        weight: 3, 
                        opacity: 0.7,
                        smoothFactor: 1 
                    }).addTo(map);
                    
                    bounds.push(L.polyline(route.points).getBounds());
                    
                    const popupContent = `
                        <div class="route-popup">
                            <h4>${route.name}</h4>
                            <p>Points: ${route.points.length}</p>
                            <a href="?action=view&id=${route.id}&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="view-link">
                                ${langTexts.view}
                            </a>
                        </div>
                    `;
                    
                    polyline.bindPopup(popupContent);
                    
                    polyline.on('mouseover', function(e) {
                        this.setStyle({ weight: 5, opacity: 1 });
                    });
                    
                    polyline.on('mouseout', function(e) {
                        this.setStyle({ weight: 3, opacity: 0.7 });
                    });
                    
                    polyline.on('click', function(e) {
                        this.openPopup();
                    });
                    
                    routeLayers.push(polyline);
                }
            });
            
            if (bounds.length > 0) {
                const allBounds = new L.LatLngBounds(bounds);
                map.fitBounds(allBounds.pad(0.1));
            }
            
            document.querySelectorAll('.map-type-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.map-type-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    
                    const type = btn.dataset.type;
                    map.eachLayer(layer => {
                        if (layer instanceof L.TileLayer) {
                            map.removeLayer(layer);
                        }
                    });
                    
                    if (type === 'satellite') {
                        satelliteBase.addTo(map);
                        satelliteLabels.addTo(map);
                    } else {
                        streetsLayer.addTo(map);
                    }
                    routeLayers.forEach(polyline => {
                        polyline.addTo(map);
                    });
                });
            });
            
            map.on('popupopen', function(e) {
                const popup = e.popup;
                const container = popup._container;
                const viewLink = container.querySelector('.view-link');
                if (viewLink) {
                    viewLink.addEventListener('click', function(event) {
                        event.preventDefault();
                        window.location.href = this.href;
                    });
                }
            });
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
