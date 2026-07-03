<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
// 3mf-viewer.php - Viewer for 3MF files using API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

function cloud_hex_to_rgba(string $hex, float $alpha): string {
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        $hex = '85b503';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $a = max(0, min(1, $alpha));
    return sprintf('rgba(%d, %d, %d, %.3F)', $r, $g, $b, $a);
}

// Get parameters
$filePath = $_GET['file'] ?? '';
$root = $_GET['root'] ?? 'main';
$lang = $_GET['lang'] ?? 'en';
$theme = $_GET['theme'] ?? 'dark';
$accentColor = $_GET['accentColor'] ?? '#85b503';
$sceneColor = $_GET['sceneColor'] ?? ($theme === 'light' ? '#f4f6f8' : '#525150');
$partColor = $_GET['partColor'] ?? ($theme === 'light' ? '#2e2e2d' : '#d9e0e8');
$modelExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

if (empty($filePath)) {
    die('File path is required');
}

// Вместо прямого доступа к файлам, используем API
$apiUrl = 'api.php?action=raw&path=' . urlencode($filePath) . '&root=' . urlencode($root);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>3MF Viewer - <?= htmlspecialchars(basename($filePath)) ?></title>
    <style>
        :root {
            color-scheme: <?= $theme === 'light' ? 'light' : 'dark' ?>;
            --viewer-bg: <?= htmlspecialchars($sceneColor, ENT_QUOTES) ?>;
            --panel-bg: <?= $theme === 'light' ? 'rgba(255,255,255,0.84)' : 'rgba(18,22,28,0.84)' ?>;
            --panel-border: <?= $theme === 'light' ? 'rgba(32,37,44,0.12)' : 'rgba(255,255,255,0.12)' ?>;
            --panel-text: <?= $theme === 'light' ? '#18202a' : '#f3f6fa' ?>;
            --panel-muted: <?= $theme === 'light' ? '#5e6a78' : '#b8c0cb' ?>;
            --panel-shadow: <?= $theme === 'light' ? '0 12px 30px rgba(35, 43, 55, 0.14)' : '0 12px 30px rgba(0, 0, 0, 0.35)' ?>;
            --accent: <?= htmlspecialchars($accentColor, ENT_QUOTES) ?>;
            --accent-soft: <?= htmlspecialchars(cloud_hex_to_rgba($accentColor, 0.52), ENT_QUOTES) ?>;
            --accent-border: <?= htmlspecialchars(cloud_hex_to_rgba($accentColor, 0.74), ENT_QUOTES) ?>;
            --accent-contrast: <?= $theme === 'light' ? '#ffffff' : '#0f141a' ?>;
            --button-bg: <?= $theme === 'light' ? 'rgba(255,255,255,0.92)' : 'rgba(20,25,31,0.92)' ?>;
            --button-hover: <?= $theme === 'light' ? 'rgba(255,255,255,1)' : 'rgba(28,34,42,0.98)' ?>;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body, html { 
            width: 100vw; 
            height: 100vh; 
            overflow: hidden; 
            background: var(--viewer-bg);
            color: var(--panel-text);
            font-family: Arial, sans-serif;
        }
        #viewer { 
            width: 100%;
            height: 100%;
            display: block;
            outline: none;
        }
        .overlay-card {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 100;
            background: var(--panel-bg);
            color: var(--panel-text);
            padding: 20px 22px;
            border-radius: 16px;
            border: 1px solid var(--panel-border);
            box-shadow: var(--panel-shadow);
            backdrop-filter: blur(12px);
            min-width: 260px;
            max-width: min(420px, calc(100vw - 32px));
        }
        .spinner {
            width: 40px;
            height: 40px;
            margin: 0 auto 15px;
            border: 4px solid rgba(255,255,255,0.22);
            border-top: 4px solid var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        .overlay-title {
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .overlay-message {
            color: var(--panel-muted);
            line-height: 1.55;
            font-size: 14px;
        }
        .viewer-toolbar {
            position: absolute;
            right: 16px;
            bottom: 16px;
            z-index: 60;
            display: flex;
            gap: 10px;
        }
        .elevation-legend {
            position: absolute;
            left: 16px;
            top: 16px;
            z-index: 55;
            display: none;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
            padding: 12px;
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            border-radius: 14px;
            box-shadow: var(--panel-shadow);
            backdrop-filter: blur(12px);
            overflow: hidden;
        }
        .elevation-legend.is-visible {
            display: flex;
        }
        .elevation-legend-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--panel-muted);
        }
        .elevation-legend-scale {
            position: relative;
            display: flex;
            align-items: stretch;
            gap: 10px;
        }
        .elevation-legend-bar {
            width: 14px;
            height: 180px;
            border-radius: 999px;
            background: linear-gradient(180deg, #ff3b30 0%, #ffd000 18%, #00c98d 38%, #00a6ff 58%, #3e63ff 78%, #7a3cff 100%);
            border: 1px solid var(--panel-border);
        }
        .elevation-legend-labels {
            min-width: 64px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 600;
            color: var(--panel-text);
            line-height: 1.2;
        }
        .elevation-legend-labels-top {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .elevation-legend-tooltip {
            position: absolute;
            left: 22px;
            min-width: 64px;
            padding: 6px 8px;
            border-radius: 10px;
            background: var(--button-bg);
            border: 1px solid var(--panel-border);
            box-shadow: var(--panel-shadow);
            color: var(--panel-text);
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            pointer-events: none;
            opacity: 0;
            transform: translateY(-50%);
            transition: opacity .12s ease;
            white-space: nowrap;
            z-index: 2;
        }
        .elevation-legend-tooltip.is-visible {
            opacity: 1;
        }
        .viewer-btn {
            appearance: none;
            border: 1px solid var(--panel-border);
            border-radius: 12px;
            background: var(--button-bg);
            color: var(--panel-text);
            width: 46px;
            height: 46px;
            padding: 0;
            cursor: pointer;
            box-shadow: var(--panel-shadow);
            backdrop-filter: blur(12px);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color .18s ease, border-color .18s ease, transform .18s ease, color .18s ease;
        }
        .viewer-btn:hover {
            background: var(--button-hover);
            transform: translateY(-1px);
        }
        .viewer-btn.is-active {
            background: var(--accent-soft);
            color: var(--accent-contrast);
            border-color: var(--accent-border);
        }
        .viewer-btn svg {
            width: 20px;
            height: 20px;
            pointer-events: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="overlay-card" id="loading">
        <div class="spinner"></div>
        <div class="overlay-title" id="loading-title">Loading 3D model...</div>
    </div>
    <div class="elevation-legend" id="elevation-legend" aria-hidden="true">
        <div class="elevation-legend-title" id="elevation-legend-title"></div>
        <div class="elevation-legend-scale">
            <div class="elevation-legend-bar"></div>
            <div class="elevation-legend-labels">
                <div class="elevation-legend-labels-top">
                    <div id="elevation-legend-max">0</div>
                </div>
                <div id="elevation-legend-min">0</div>
            </div>
            <div class="elevation-legend-tooltip" id="elevation-legend-tooltip">0</div>
        </div>
    </div>
    <div class="viewer-toolbar">
        <button type="button" class="viewer-btn" id="toggle-grid"></button>
        <button type="button" class="viewer-btn" id="toggle-view-mode"></button>
    </div>
    <canvas id="viewer"></canvas>

    <script type="importmap">
    {
        "imports": {
            "three": "https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js",
            "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/"
        }
    }
    </script>

    <script type="module">
    import * as THREE from 'three';
    import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
    import { ThreeMFLoader } from 'three/addons/loaders/3MFLoader.js';
    import { STLLoader } from 'three/addons/loaders/STLLoader.js';
    import { OBJLoader } from 'three/addons/loaders/OBJLoader.js';
    import WebGL from 'three/addons/capabilities/WebGL.js';

    const filePath = '<?= $apiUrl ?>';
    const fileName = '<?= htmlspecialchars(basename($filePath), ENT_QUOTES) ?>';
    const modelExt = '<?= htmlspecialchars($modelExt, ENT_QUOTES) ?>';
    const lang = '<?= htmlspecialchars(in_array($lang, ['en', 'ru', 'uk', 'be', 'pl', 'cs'], true) ? $lang : 'en', ENT_QUOTES) ?>';
    const fallbackSceneColor = '<?= $sceneColor ?>';
    const fallbackPartColor = '<?= $partColor ?>';
    const theme = '<?= htmlspecialchars($theme === 'light' ? 'light' : 'dark', ENT_QUOTES) ?>';
    const GRID_VISIBILITY_STORAGE_KEY = 'cloud.3dViewer.gridVisible';
    const CONTRAST_STORAGE_KEY = 'cloud.3dViewer.contrastMode';
    const DEFAULT_GRID_VISIBLE = true;
    const DEFAULT_CONTRAST_MODE = 'high';

    // Переменные
    let scene, camera, renderer, controls, model, gridHelper, gridLabelsGroup, modelBoundsSphere, modelHeightRange = null;
    let ambientLight, mainLight, fillLight, backLight, rimLight, lowerFillLight;
    const visualState = {
        displayMode: 'shadows'
    };

    const translations = {
        en: {
            loadingModel: 'Loading 3D model...',
            loadingFailed: 'Failed to load model.',
            unsupportedFormat: 'This 3D format is not supported by the viewer.',
            webglUnavailable: 'WebGL is unavailable in the current browser or environment.',
            webglCheck: 'Check whether hardware acceleration is enabled and WebGL is not blocked by browser policies.',
            webglCreateFailed: 'Failed to create a WebGL context.',
            webglCreateHint: 'Usually this means WebGL is disabled, unavailable through the GPU/driver, or blocked by the environment.',
            showGrid: 'Show grid',
            hideGrid: 'Hide grid',
            shadows: 'Shadows',
            zebra: 'Zebra',
            elevation: 'Elevation',
            modeCycle: 'View mode',
            elevationScale: 'Elevation'
        },
        ru: {
            loadingModel: 'Загрузка 3D-модели...',
            loadingFailed: 'Не удалось загрузить модель.',
            unsupportedFormat: 'Этот 3D-формат не поддерживается просмотрщиком.',
            webglUnavailable: 'WebGL недоступен в текущем браузере или окружении.',
            webglCheck: 'Проверьте, включено ли аппаратное ускорение, и не запрещён ли WebGL политиками браузера.',
            webglCreateFailed: 'Не удалось создать WebGL-контекст.',
            webglCreateHint: 'Обычно это означает, что WebGL отключён, недоступен через GPU/драйвер или заблокирован окружением.',
            showGrid: 'Показать сетку',
            hideGrid: 'Скрыть сетку',
            shadows: 'Тени',
            zebra: 'Зебра',
            elevation: 'Высоты',
            modeCycle: 'Режим просмотра',
            elevationScale: 'Высота'
        },
        uk: {
            loadingModel: 'Завантаження 3D-моделі...',
            loadingFailed: 'Не вдалося завантажити модель.',
            unsupportedFormat: 'Цей 3D-формат не підтримується переглядачем.',
            webglUnavailable: 'WebGL недоступний у поточному браузері або середовищі.',
            webglCheck: 'Перевірте, чи ввімкнено апаратне прискорення і чи не заблоковано WebGL політиками браузера.',
            webglCreateFailed: 'Не вдалося створити WebGL-контекст.',
            webglCreateHint: 'Зазвичай це означає, що WebGL вимкнено, недоступний через GPU/драйвер або заблокований середовищем.',
            showGrid: 'Показати сітку',
            hideGrid: 'Сховати сітку',
            shadows: 'Тіні',
            zebra: 'Зебра',
            elevation: 'Висоти',
            modeCycle: 'Режим перегляду',
            elevationScale: 'Висота'
        },
        be: {
            loadingModel: 'Загрузка 3D-мадэлі...',
            loadingFailed: 'Не ўдалося загрузіць мадэль.',
            unsupportedFormat: 'Гэты 3D-фармат не падтрымліваецца праглядальнікам.',
            webglUnavailable: 'WebGL недаступны ў гэтым браўзеры або асяроддзі.',
            webglCheck: 'Праверце, ці ўключана апаратнае паскарэнне і ці не заблакаваны WebGL палітыкамі браўзера.',
            webglCreateFailed: 'Не ўдалося стварыць WebGL-кантэкст.',
            webglCreateHint: 'Звычайна гэта азначае, што WebGL адключаны, недаступны праз GPU/драйвер або заблакаваны асяроддзем.',
            showGrid: 'Паказаць сетку',
            hideGrid: 'Схаваць сетку',
            shadows: 'Цені',
            zebra: 'Зебра',
            elevation: 'Вышыні',
            modeCycle: 'Рэжым прагляду',
            elevationScale: 'Вышыня'
        }
    };

    const text = translations[lang] || translations.en;
    const gridIcon = `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect width="18" height="18" x="3" y="3" rx="2"></rect>
            <path d="M3 9h18"></path>
            <path d="M3 15h18"></path>
            <path d="M9 3v18"></path>
            <path d="M15 3v18"></path>
        </svg>
    `;
    const modeIcon = `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"></path>
            <path d="M12 22V12"></path>
            <polyline points="3.29 7 12 12 20.71 7"></polyline>
            <path d="m7.5 4.27 9 5.15"></path>
        </svg>
    `;

    function readSharedSetting(key, fallback) {
        try {
            const value = window.localStorage.getItem(key);
            if (value === null) {
                window.localStorage.setItem(key, String(fallback));
                return fallback;
            }
            if (typeof fallback === 'boolean') {
                return value === 'true';
            }
            return value;
        } catch (error) {
            return fallback;
        }
    }

    function writeSharedSetting(key, value) {
        try {
            window.localStorage.setItem(key, String(value));
        } catch (error) {
            console.warn('Failed to persist 3D viewer setting:', key, error);
        }
    }

    const viewerState = {
        gridVisible: readSharedSetting(GRID_VISIBILITY_STORAGE_KEY, DEFAULT_GRID_VISIBLE),
        contrastMode: readSharedSetting(CONTRAST_STORAGE_KEY, DEFAULT_CONTRAST_MODE)
    };

    function getViewerPalette() {
        if (viewerState.contrastMode === 'high') {
            return theme === 'light'
                ? {
                    scene: '#f4f6f8',
                    part: '#2e2e2d',
                    gridPrimary: 0x23303f,
                    gridSecondary: 0x748295,
                    gridOpacity: 0.82,
                    label: '#243140'
                }
                : {
                    scene: '#525150',
                    part: '#dbe2ea',
                    gridPrimary: 0xf2f6fb,
                    gridSecondary: 0x93a1b3,
                    gridOpacity: 0.68,
                    label: '#eff4fa'
                };
        }

        return {
            scene: fallbackSceneColor,
            part: fallbackPartColor,
            gridPrimary: theme === 'light' ? 0x6c7682 : 0xd8e0ea,
            gridSecondary: theme === 'light' ? 0x9fa8b4 : 0x6f7885,
            gridOpacity: theme === 'light' ? 0.46 : 0.34,
            label: theme === 'light' ? '#445160' : '#dbe4ee'
        };
    }

    const viewerPalette = getViewerPalette();

    function showError(message) {
        const loading = document.getElementById('loading');
        loading.innerHTML = '<div class="overlay-message" style="color: #ff7b72;">' + message + '</div>';
        loading.style.display = 'block';
    }

    function updateGridButton() {
        const button = document.getElementById('toggle-grid');
        const isVisible = !!gridHelper ? gridHelper.visible : viewerState.gridVisible;
        const label = isVisible ? text.hideGrid : text.showGrid;
        button.innerHTML = gridIcon;
        button.classList.toggle('is-active', isVisible);
        button.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    }

    function updateModeButtons() {
        const modeBtn = document.getElementById('toggle-view-mode');
        const modeLabels = {
            shadows: text.shadows,
            zebra: text.zebra,
            elevation: text.elevation
        };
        const currentLabel = modeLabels[visualState.displayMode] || text.modeCycle;

        modeBtn.innerHTML = modeIcon;
        modeBtn.classList.add('is-active');
        modeBtn.setAttribute('title', `${text.modeCycle}: ${currentLabel}`);
        modeBtn.setAttribute('aria-label', `${text.modeCycle}: ${currentLabel}`);
    }

    function updateElevationLegend() {
        const legend = document.getElementById('elevation-legend');
        const legendTitle = document.getElementById('elevation-legend-title');
        const legendMax = document.getElementById('elevation-legend-max');
        const legendMin = document.getElementById('elevation-legend-min');
        const tooltip = document.getElementById('elevation-legend-tooltip');
        const bar = legend.querySelector('.elevation-legend-bar');
        const isVisible = visualState.displayMode === 'elevation' && !!modelHeightRange;

        legend.classList.toggle('is-visible', isVisible);
        legend.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
        legendTitle.textContent = text.elevationScale;

        if (!modelHeightRange) {
            legendMax.textContent = '0';
            legendMin.textContent = '0';
            tooltip.textContent = '0';
            return;
        }

        legendMax.textContent = `${Math.round(modelHeightRange.max)}`;
        legendMin.textContent = `${Math.round(modelHeightRange.min)}`;

        if (!bar.dataset.boundHover) {
            bar.addEventListener('mouseenter', () => {
                tooltip.classList.add('is-visible');
            });
            bar.addEventListener('mouseleave', () => {
                tooltip.classList.remove('is-visible');
            });
            bar.addEventListener('mousemove', (event) => {
                if (!modelHeightRange) return;
                const rect = bar.getBoundingClientRect();
                const y = Math.min(Math.max(event.clientY - rect.top, 0), rect.height);
                const t = 1 - (y / rect.height);
                const value = modelHeightRange.min + (modelHeightRange.max - modelHeightRange.min) * t;
                tooltip.textContent = `${Math.round(value)}`;
                tooltip.style.top = `${Math.min(Math.max(y, 14), rect.height - 14)}px`;
            });
            bar.dataset.boundHover = 'true';
        }
    }

    function updateLightingRig() {
        const reliefMode = visualState.displayMode === 'shadows' || visualState.displayMode === 'zebra';
        if (ambientLight) ambientLight.intensity = reliefMode ? 0.92 : 0.68;
        if (mainLight) mainLight.intensity = reliefMode ? 1.6 : 1.0;
        if (fillLight) fillLight.intensity = reliefMode ? 0.95 : 0.4;
        if (backLight) backLight.intensity = reliefMode ? 0.82 : 0.3;
        if (rimLight) rimLight.intensity = reliefMode ? 0.72 : 0.18;
        if (lowerFillLight) lowerFillLight.intensity = reliefMode ? 0.48 : 0.14;
    }

    function createZebraMaterial() {
        const material = new THREE.MeshPhongMaterial({
            color: 0xffffff,
            shininess: 18,
            side: THREE.DoubleSide
        });

        material.onBeforeCompile = (shader) => {
            shader.uniforms.uMinY = { value: modelHeightRange?.min ?? 0 };
            shader.uniforms.uMaxY = { value: modelHeightRange?.max ?? 1 };
            shader.uniforms.uStripeA = { value: new THREE.Color('#f5f4ef') };
            shader.uniforms.uStripeB = { value: new THREE.Color('#76736b') };

            material.userData.shader = shader;

            shader.vertexShader = shader.vertexShader
                .replace(
                    '#include <common>',
                    `#include <common>
                    varying vec3 vWorldPosition;`
                )
                .replace(
                    '#include <begin_vertex>',
                    `#include <begin_vertex>
                    vec4 cloudWorldPosition = modelMatrix * vec4(transformed, 1.0);
                    vWorldPosition = cloudWorldPosition.xyz;`
                );

            shader.fragmentShader = shader.fragmentShader
                .replace(
                    '#include <common>',
                    `#include <common>
                    varying vec3 vWorldPosition;
                    uniform float uMinY;
                    uniform float uMaxY;
                    uniform vec3 uStripeA;
                    uniform vec3 uStripeB;`
                )
                .replace(
                    'vec4 diffuseColor = vec4( diffuse, opacity );',
                    `float span = max(uMaxY - uMinY, 0.000001);
                    float t = clamp((vWorldPosition.y - uMinY) / span, 0.0, 1.0);
                    float stripes = step(0.5, fract(t * 14.0));
                    vec3 zebraColor = mix(uStripeA, uStripeB, stripes);
                    vec4 diffuseColor = vec4(zebraColor, opacity);`
                );
        };

        material.customProgramCacheKey = () => 'cloud-zebra-phong-v1';
        return material;
    }

    function ensureMeshColorAttribute(mesh, mode) {
        const geometry = mesh.geometry;
        if (!geometry?.attributes?.position) return;

        const position = geometry.attributes.position;
        let colors = null;
        if (!geometry.boundingBox) {
            geometry.computeBoundingBox();
        }
        const box = geometry.boundingBox;

        if (!box) return;

        const minY = box.min.y;
        const maxY = box.max.y;
        const spanY = Math.max(maxY - minY, 1e-6);
        colors = new Float32Array(position.count * 3);

        for (let i = 0; i < position.count; i += 1) {
            const y = position.getY(i);
            const t = (y - minY) / spanY;
            let color = null;

            if (mode === 'elevation') {
                color = new THREE.Color();
                color.setHSL(t * 0.76, 0.92, theme === 'light' ? 0.5 : 0.56);
            } else {
                continue;
            }

            colors[i * 3] = color.r;
            colors[i * 3 + 1] = color.g;
            colors[i * 3 + 2] = color.b;
        }

        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
    }

    function applyVisualMode() {
        if (!model) return;

        model.traverse((child) => {
            if (!child.isMesh) return;

            const useShadows = visualState.displayMode === 'shadows';
            child.castShadow = useShadows;
            child.receiveShadow = useShadows;

            if (visualState.displayMode === 'shadows') {
                child.material = child.userData.baseMaterial;
            } else if (visualState.displayMode === 'zebra') {
                if (!child.userData.zebraMaterial) {
                    child.userData.zebraMaterial = createZebraMaterial();
                }
                const shader = child.userData.zebraMaterial.userData.shader;
                if (shader && modelHeightRange) {
                    shader.uniforms.uMinY.value = modelHeightRange.min;
                    shader.uniforms.uMaxY.value = modelHeightRange.max;
                }
                child.material = child.userData.zebraMaterial;
            } else if (visualState.displayMode === 'elevation') {
                ensureMeshColorAttribute(child, visualState.displayMode);
                if (!child.userData.elevationMaterial) {
                    child.userData.elevationMaterial = new THREE.MeshPhongMaterial({
                        vertexColors: true,
                        shininess: 18,
                        side: THREE.DoubleSide
                    });
                }
                child.material = child.userData.elevationMaterial;
            }
        });

        if (renderer) {
            renderer.shadowMap.enabled = visualState.displayMode === 'shadows';
        }
        updateLightingRig();
        updateModeButtons();
        updateElevationLegend();
    }

    function createModelObject(loaded) {
        if (modelExt === 'stl') {
            const geometry = loaded;
            if (geometry?.computeVertexNormals) {
                geometry.computeVertexNormals();
            }
            return new THREE.Mesh(
                geometry,
                new THREE.MeshPhysicalMaterial({
                    color: viewerPalette.part,
                    roughness: 0.28,
                    metalness: 0.02,
                    clearcoat: 0.22,
                    clearcoatRoughness: 0.24
                })
            );
        }

        return loaded;
    }

    function getLoader() {
        switch (modelExt) {
            case '3mf':
                return new ThreeMFLoader();
            case 'stl':
                return new STLLoader();
            case 'obj':
                return new OBJLoader();
            default:
                return null;
        }
    }

    function createRenderer(canvas) {
        const rendererOptionsList = [
            {
                canvas,
                antialias: true,
                alpha: false,
                powerPreference: 'default'
            },
            {
                canvas,
                antialias: false,
                alpha: false,
                powerPreference: 'default',
                stencil: false,
                depth: true,
                preserveDrawingBuffer: false
            }
        ];

        let lastError = null;

        for (const options of rendererOptionsList) {
            try {
                const instance = new THREE.WebGLRenderer(options);
                instance.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
                instance.setSize(window.innerWidth, window.innerHeight, false);
                instance.outputColorSpace = THREE.SRGBColorSpace;
                instance.toneMapping = THREE.ACESFilmicToneMapping;
                instance.toneMappingExposure = 1.2;
                instance.shadowMap.enabled = true;
                instance.shadowMap.type = THREE.PCFSoftShadowMap;
                return instance;
            } catch (error) {
                lastError = error;
                console.warn('WebGL renderer initialization failed with options:', options, error);
            }
        }

        throw lastError || new Error('WebGL renderer initialization failed');
    }

    // Инициализация
    function init() {
        const canvas = document.getElementById('viewer');

        if (!WebGL.isWebGL2Available()) {
            const reason = WebGL.getWebGLErrorMessage()?.textContent?.trim();
            showError(
                text.webglUnavailable +
                (reason ? '<br><br>' + reason : '') +
                '<br><br>' + text.webglCheck
            );
            canvas.style.display = 'none';
            document.querySelector('.viewer-toolbar').style.display = 'none';
            return false;
        }

        // Сцена
        scene = new THREE.Scene();
        scene.background = new THREE.Color(viewerPalette.scene);
        
        // Камера с улучшенными настройками
        camera = new THREE.PerspectiveCamera(
            60,
            window.innerWidth / window.innerHeight,
            0.01,
            1000
        );

        try {
            renderer = createRenderer(canvas);
        } catch (error) {
            console.error('Error creating WebGL renderer:', error);
            showError(
                text.webglCreateFailed +
                '<br><br>' + (error?.message || 'unknown error') +
                '<br><br>' + text.webglCreateHint
            );
            canvas.style.display = 'none';
            document.querySelector('.viewer-toolbar').style.display = 'none';
            return false;
        }

        // Освещение для лучшей детализации
        setupLighting();

        // Загрузка модели
        loadModel();

        // Обработчики событий
        window.addEventListener('resize', onWindowResize);
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                window.close();
            }
        });
        document.getElementById('toggle-grid').addEventListener('click', () => {
            if (!gridHelper) return;
            viewerState.gridVisible = !gridHelper.visible;
            applyGridVisibility(viewerState.gridVisible);
            writeSharedSetting(GRID_VISIBILITY_STORAGE_KEY, viewerState.gridVisible);
            updateGridButton();
            renderer.render(scene, camera);
        });
        document.getElementById('toggle-view-mode').addEventListener('click', () => {
            const order = ['shadows', 'zebra', 'elevation'];
            const currentIndex = order.indexOf(visualState.displayMode);
            visualState.displayMode = order[(currentIndex + 1) % order.length];
            applyVisualMode();
        });
        window.addEventListener('storage', (event) => {
            if (event.key === GRID_VISIBILITY_STORAGE_KEY) {
                const nextVisible = event.newValue === null ? DEFAULT_GRID_VISIBLE : event.newValue === 'true';
                viewerState.gridVisible = nextVisible;
                applyGridVisibility(nextVisible);
                updateGridButton();
            }
            if (event.key === CONTRAST_STORAGE_KEY && event.newValue) {
                viewerState.contrastMode = event.newValue;
            }
        });
        updateGridButton();
        updateModeButtons();
        updateElevationLegend();

        // Фокус на canvas
        canvas.setAttribute('tabindex', '0');
        canvas.focus();

        return true;
    }

    function setupLighting() {
        // Ambient light - мягкое окружающее освещение
        ambientLight = new THREE.AmbientLight(0xffffff, 0.92);
        scene.add(ambientLight);
        
        // Основной directional light
        mainLight = new THREE.DirectionalLight(0xffffff, 1.6);
        mainLight.position.set(10, 15, 10);
        mainLight.castShadow = true;
        
        // Настройки теней для четкости
        mainLight.shadow.mapSize.width = 2048;
        mainLight.shadow.mapSize.height = 2048;
        mainLight.shadow.camera.near = 0.5;
        mainLight.shadow.camera.far = 50;
        mainLight.shadow.camera.left = -20;
        mainLight.shadow.camera.right = 20;
        mainLight.shadow.camera.top = 20;
        mainLight.shadow.camera.bottom = -20;
        
        scene.add(mainLight);

        // Заполняющий свет
        fillLight = new THREE.DirectionalLight(0xffffff, 0.95);
        fillLight.position.set(-10, 5, -5);
        scene.add(fillLight);

        // Контровой свет
        backLight = new THREE.DirectionalLight(0xffffff, 0.82);
        backLight.position.set(0, 5, -10);
        scene.add(backLight);

        rimLight = new THREE.DirectionalLight(0xffffff, 0.72);
        rimLight.position.set(12, 3, -12);
        scene.add(rimLight);

        lowerFillLight = new THREE.DirectionalLight(0xffffff, 0.48);
        lowerFillLight.position.set(-6, -8, 7);
        scene.add(lowerFillLight);
    }

    function loadModel() {
        const loader = getLoader();

        if (!loader) {
            showError(text.unsupportedFormat);
            document.querySelector('.viewer-toolbar').style.display = 'none';
            return;
        }
        
        loader.load(filePath, 
            // onLoad
            (loaded) => {
                model = createModelObject(loaded);
                setupModel(model);
                setupControls(); // Настраиваем контролы ПОСЛЕ загрузки модели
                autoZoomToFit(); // АВТОМАТИЧЕСКИЙ ЗУМ ПОД РАЗМЕР МОДЕЛИ
                document.getElementById('loading').style.display = 'none';
                document.title = fileName + ' - 3D Viewer';
            },
            // onProgress
            () => {},
            // onError
            (error) => {
                console.error('Error loading 3D file:', error);
                showError(text.loadingFailed + '<br><br>' + (error?.message || 'unknown error'));
            }
        );
    }

    function setupModel(object) {
        // Применяем цвет ко всем материалам
        object.traverse((child) => {
            if (child.isMesh) {
                // Создаем новый материал с улучшенными настройками для четкости
                const newMaterial = new THREE.MeshPhysicalMaterial({
                    color: viewerPalette.part,
                    roughness: 0.52,
                    metalness: 0.0,
                    clearcoat: 0.08,
                    clearcoatRoughness: 0.42,
                    reflectivity: 0.12
                });
                
                child.userData.baseMaterial = newMaterial;
                child.material = newMaterial;
                child.castShadow = visualState.displayMode === 'shadows';
                child.receiveShadow = visualState.displayMode === 'shadows';
                
                // Улучшаем геометрию для лучшего отображения
                if (child.geometry) {
                    child.geometry.computeVertexNormals();
                    child.geometry.computeBoundingBox();
                }
            }
        });

        // Центрирование модели
        const box = new THREE.Box3().setFromObject(object);
        const center = box.getCenter(new THREE.Vector3());
        
        // Смещаем модель так, чтобы ее центр был в (0,0,0)
        object.position.sub(center);

        scene.add(object);
        updateGridPosition();
        const updatedBox = new THREE.Box3().setFromObject(object);
        modelBoundsSphere = updatedBox.getBoundingSphere(new THREE.Sphere());
        modelHeightRange = {
            min: updatedBox.min.y,
            max: updatedBox.max.y
        };
        applyVisualMode();

    }

    function enforceCameraOutsideModel() {
        if (!camera || !controls || !modelBoundsSphere) return;

        const safeRadius = modelBoundsSphere.radius * 1.001;
        const offset = camera.position.clone().sub(modelBoundsSphere.center);
        let distance = offset.length();

        if (distance >= safeRadius) {
            return;
        }

        if (distance < 1e-6) {
            offset.copy(camera.position.clone().sub(controls.target));
            distance = offset.length();
        }
        if (distance < 1e-6) {
            offset.set(1, 0.65, 1);
            distance = offset.length();
        }

        offset.normalize().multiplyScalar(safeRadius);
        camera.position.copy(modelBoundsSphere.center).add(offset);
        camera.updateMatrixWorld();
    }

    function pickMillimeterStep(maxDim) {
        const idealStep = Math.max(maxDim / 12, 1);
        const candidates = [1, 2, 5];

        for (let exponent = 0; exponent < 8; exponent += 1) {
            const base = Math.pow(10, exponent);
            for (const candidate of candidates) {
                const step = candidate * base;
                if (step >= idealStep) {
                    return step;
                }
            }
        }

        return 1000;
    }

    function createGridLabelMesh(textValue, worldScale = 1, rotationZ = 0) {
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        const fontSize = 20;
        const paddingX = 8;
        const paddingY = 5;
        context.font = `600 ${fontSize}px Arial, sans-serif`;
        const metrics = context.measureText(textValue);
        canvas.width = Math.ceil(metrics.width + paddingX * 2);
        canvas.height = fontSize + paddingY * 2;

        context.font = `600 ${fontSize}px Arial, sans-serif`;
        context.fillStyle = theme === 'light' ? 'rgba(255,255,255,0.88)' : 'rgba(14,18,24,0.86)';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.strokeStyle = theme === 'light' ? 'rgba(35,48,63,0.18)' : 'rgba(255,255,255,0.12)';
        context.lineWidth = 2;
        context.strokeRect(1, 1, canvas.width - 2, canvas.height - 2);
        context.fillStyle = viewerPalette.label;
        context.textBaseline = 'middle';
        context.fillText(textValue, paddingX, canvas.height / 2);

        const texture = new THREE.CanvasTexture(canvas);
        texture.colorSpace = THREE.SRGBColorSpace;
        const aspectRatio = canvas.width / canvas.height;
        const labelHeight = Math.max(worldScale * 0.32, 2.2);
        const labelWidth = labelHeight * aspectRatio;
        const geometry = new THREE.PlaneGeometry(labelWidth, labelHeight);
        const material = new THREE.MeshBasicMaterial({
            map: texture,
            transparent: true,
            depthTest: true,
            depthWrite: false,
            side: THREE.DoubleSide
        });
        const mesh = new THREE.Mesh(geometry, material);
        mesh.rotation.x = -Math.PI / 2;
        mesh.rotation.z = rotationZ;
        mesh.renderOrder = 3;
        return mesh;
    }

    function createGridLabels(center, gridSize, step, y) {
        if (gridLabelsGroup) {
            scene.remove(gridLabelsGroup);
        }

        gridLabelsGroup = new THREE.Group();
        const labelStep = step * 5;
        const labelOffset = Math.max(step * 0.18, 0.65);
        const half = gridSize / 2;
        const tickMinor = Math.max(step * 0.22, 0.8);
        const tickMajor = Math.max(step * 0.48, 1.8);
        const labelLift = Math.max(step * 0.002, 0.02);
        const tickPoints = [];

        for (let distance = 0; distance <= gridSize + step * 0.5; distance += step) {
            const x = center.x - half + distance;
            const z = center.z - half + distance;
            const isMajor = Math.round(distance / step) % 5 === 0;
            const tickSize = isMajor ? tickMajor : tickMinor;

            tickPoints.push(x, y, center.z + half, x, y, center.z + half + tickSize);
            tickPoints.push(center.x - half, y, z, center.x - half - tickSize, y, z);
        }

        const tickGeometry = new THREE.BufferGeometry();
        tickGeometry.setAttribute('position', new THREE.Float32BufferAttribute(tickPoints, 3));
        const tickMaterial = new THREE.LineBasicMaterial({
            color: viewerPalette.gridPrimary,
            transparent: true,
            opacity: Math.min(1, viewerPalette.gridOpacity + 0.12)
        });
        const tickLines = new THREE.LineSegments(tickGeometry, tickMaterial);
        gridLabelsGroup.add(tickLines);

        for (let distance = 0; distance <= gridSize + step * 0.5; distance += labelStep) {
            const x = center.x - half + distance;
            const z = center.z - half + distance;
            const labelText = `${Math.round(distance)}`;

            const xLabel = createGridLabelMesh(labelText, step, 0);
            xLabel.position.set(x, y + labelLift, center.z + half + tickMajor * 0.5);
            gridLabelsGroup.add(xLabel);

            const zLabel = createGridLabelMesh(labelText, step, 0);
            zLabel.position.set(center.x - half - tickMajor * 0.5 - labelOffset, y + labelLift, z);
            gridLabelsGroup.add(zLabel);
        }

        scene.add(gridLabelsGroup);
    }

    function applyGridVisibility(isVisible) {
        if (gridHelper) {
            gridHelper.visible = isVisible;
        }
        if (gridLabelsGroup) {
            gridLabelsGroup.visible = isVisible;
        }
    }

    function updateGridPosition() {
        if (!model) return;

        const box = new THREE.Box3().setFromObject(model);
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z, 1);
        const step = pickMillimeterStep(maxDim);
        const halfGridSteps = Math.max(8, Math.ceil((maxDim * 0.9) / step));
        const gridSize = halfGridSteps * step * 2;
        const divisions = Math.max(16, Math.round(gridSize / step));
        const gridY = box.min.y - Math.max(maxDim * 0.0025, 0.002);

        if (gridHelper) {
            scene.remove(gridHelper);
        }
        if (gridLabelsGroup) {
            scene.remove(gridLabelsGroup);
        }

        gridHelper = new THREE.GridHelper(
            gridSize,
            divisions,
            viewerPalette.gridPrimary,
            viewerPalette.gridSecondary
        );
        gridHelper.position.set(center.x, gridY, center.z);
        gridHelper.material.transparent = true;
        gridHelper.material.opacity = viewerPalette.gridOpacity;
        scene.add(gridHelper);
        createGridLabels(center, gridSize, step, gridY + Math.max(step * 0.04, 0.8));
        applyGridVisibility(viewerState.gridVisible);
        updateGridButton();
    }

    function setupControls() {
        // Инициализируем контролы ПОСЛЕ загрузки модели
        controls = new OrbitControls(camera, renderer.domElement);
        
        // Центр вращения
        controls.target.set(0, 0, 0);
        
        // Настройки плавности
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;
        
        // СКОРОСТИ УПРАВЛЕНИЯ
        controls.rotateSpeed = 1.0;
        controls.zoomSpeed = 1.2;
        controls.panSpeed = 2.0;
        
        // Ограничения - УВЕЛИЧИВАЕМ минимальную дистанцию для возможности приближения
        controls.minDistance = 0.02;
        controls.maxDistance = 1000;  // УВЕЛИЧИЛИ максимальную дистанцию
        
        controls.maxPolarAngle = Math.PI;
        controls.screenSpacePanning = true;
        controls.keyPanSpeed = 20.0;
        
    }

    // ФУНКЦИЯ АВТОМАТИЧЕСКОГО ЗУМА ПОД РАЗМЕР МОДЕЛИ
    function autoZoomToFit() {
        if (!model) return;
        
        const box = new THREE.Box3().setFromObject(model);
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        
        const maxDim = Math.max(size.x, size.y, size.z);
        const fov = camera.fov * (Math.PI / 180);
        
        // Рассчитываем оптимальное расстояние для камеры
        let cameraDistance = Math.abs((maxDim * 0.8) / Math.tan(fov / 2));
        
        cameraDistance *= 1.25;
        camera.near = Math.max(maxDim / 2000, 0.01);
        camera.far = Math.max(maxDim * 40, 5000);
        camera.updateProjectionMatrix();
        
        // Устанавливаем позицию камеры
        camera.position.set(center.x + cameraDistance, center.y + cameraDistance * 0.6, center.z + cameraDistance);
        camera.lookAt(center);
        
        // Обновляем контролы
        controls.target.copy(center);
        controls.minDistance = Math.max((modelBoundsSphere?.radius || (maxDim * 0.5)) * 0.003, 0.01);
        controls.maxDistance = Math.max(maxDim * 12, 1000);
        controls.update();
        
    }

    function onWindowResize() {
        if (!camera || !renderer) return;

        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight, false);
        
        // При изменении размера окна пересчитываем зум
        if (model) {
            autoZoomToFit();
        }
        
        renderer.render(scene, camera);
    }

    // Анимация
    function animate() {
        requestAnimationFrame(animate);

        if (!renderer || !scene || !camera) {
            return;
        }
        
        // Обновляем элементы управления
        if (controls) {
            controls.update();
        }
        enforceCameraOutsideModel();
        
        // Рендерим сцену
        renderer.render(scene, camera);
    }

    // Запуск приложения
    document.getElementById('loading-title').textContent = text.loadingModel;

    if (init()) {
        animate();
    }
    </script>
</body>
</html>
