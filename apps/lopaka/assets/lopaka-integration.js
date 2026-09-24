/* Lopaka <-> dashboard integration.
 * Reads URL params passed by the dashboard iframe:
 *   ?lang=ru|en|uk|be|pl|cs  &theme=light|dark|auto|device  &color=%23rrggbb
 * Fully self-contained, no external deps.
 */
(function () {
    'use strict';

    var params = new URLSearchParams(window.location.search);

    /* ---------- Theme ---------- */
    var DARK = 'lopaka';        // upstream dark theme name
    var LIGHT = 'lopaka-light'; // our light theme override

    function systemPrefersDark() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function applyTheme(theme) {
        var t = (theme || 'dark').toLowerCase();
        var effective;
        if (t === 'light') effective = 'light';
        else if (t === 'dark') effective = 'dark';
        else effective = systemPrefersDark() ? 'dark' : 'light'; // auto / device
        var name = effective === 'light' ? LIGHT : DARK;
        document.documentElement.setAttribute('data-theme', name);
        document.documentElement.setAttribute('data-theme-mode', effective);
        return effective;
    }

    applyTheme(params.get('theme'));

    // live follow for auto/device
    var themeParam = (params.get('theme') || 'dark').toLowerCase();
    if (themeParam === 'auto' || themeParam === 'device') {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        var onChange = function () { applyTheme(themeParam); };
        if (mq.addEventListener) mq.addEventListener('change', onChange);
        else if (mq.addListener) mq.addListener(onChange);
    }

    /* ---------- Accent color ---------- */
    function normalizeColor(raw) {
        if (!raw) return null;
        var c = decodeURIComponent(raw).trim();
        if (/^#[0-9a-f]{6}$/i.test(c)) return c;
        if (/^#[0-9a-f]{3}$/i.test(c)) {
            return '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3];
        }
        var m = c.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/i);
        if (m) {
            var hex = function (n) { return ('0' + Math.max(0, Math.min(255, +n)).toString(16)).slice(-2); };
            return '#' + hex(m[1]) + hex(m[2]) + hex(m[3]);
        }
        return null;
    }

    var accent = normalizeColor(params.get('color'));
    if (accent) {
        var root = document.documentElement;
        function applyAccent() {
            root.style.setProperty('--accent-color', accent);
            root.style.setProperty('--primary-color', accent); // legacy var used by older styles
            // Recompute daisyUI oklch vars so Tailwind/daisyUI classes (btn-primary, text-primary, checkbox, etc.) follow the accent too.
            var rgb = hexToRgb(accent);
            var ok = srgbToOklch(rgb);
            ['--p', '--a'].forEach(function (v) { root.style.setProperty(v, ok.l + ' ' + ok.c + ' ' + ok.h); });
            ['--pc', '--ac'].forEach(function (v) { root.style.setProperty(v, (ok.l * 0.19) + ' ' + (ok.c * 0.2) + ' ' + ok.h); });
            // focus rings / selection
            root.style.setProperty('--fallback-p', accent);
            root.style.setProperty('--fallback-a', accent);
        }
        applyAccent();
        window.addEventListener('DOMContentLoaded', applyAccent);
        setTimeout(applyAccent, 500);
        setTimeout(applyAccent, 2000);
    }

    /* ---------- Color math helpers ---------- */
    function hexToRgb(hex) {
        var h = hex.replace('#', '');
        return {
            r: parseInt(h.substr(0, 2), 16) / 255,
            g: parseInt(h.substr(2, 2), 16) / 255,
            b: parseInt(h.substr(4, 2), 16) / 255
        };
    }

    // sRGB (linear) -> OKLCH, matches CSS Color 4 conversion used by daisyUI
    function srgbToOklch(c) {
        var lr = c.r <= 0.04045 ? c.r / 12.92 : Math.pow((c.r + 0.055) / 1.055, 2.4);
        var lg = c.g <= 0.04045 ? c.g / 12.92 : Math.pow((c.g + 0.055) / 1.055, 2.4);
        var lb = c.b <= 0.04045 ? c.b / 12.92 : Math.pow((c.b + 0.055) / 1.055, 2.4);

        var l = Math.cbrt(0.4122214708 * lr + 0.5363325363 * lg + 0.0514459929 * lb);
        var m = Math.cbrt(0.2119034982 * lr + 0.6806995451 * lg + 0.1073969566 * lb);
        var s = Math.cbrt(0.0883024619 * lr + 0.2817188376 * lg + 0.6299787005 * lb);

        var L = 0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s;
        var A = 1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s;
        var B = 0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s;

        var C = Math.sqrt(A * A + B * B);
        var H = (Math.atan2(B, A) * 180) / Math.PI;
        if (H < 0) H += 360;
        // clamp lightness so accent stays visible on both themes
        var Lc = Math.min(0.93, Math.max(0.5, L));
        return { l: +(Lc * 100).toFixed(4), c: +C.toFixed(5), h: +H.toFixed(4) };
    }

    /* ---------- Language ---------- */
    var lang = (params.get('lang') || '').toLowerCase();
    if (lang) {
        document.documentElement.setAttribute('lang', lang);
        window.__LOPAKA_LANG__ = lang;
    }
})();
