<?php
header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: public, max-age=86400');

$icons = [
    'light-bulb' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 21h4"/><path d="M8.8 14.5c-1.15-1.02-1.8-2.36-1.8-4.1a5 5 0 1 1 10 0c0 1.73-.65 3.08-1.8 4.1-.52.47-.86 1.07-1 1.75H9.8c-.14-.68-.48-1.28-1-1.75Z"/></svg>',
    'kettle' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12a7 7 0 0 1 14 0v2a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4v-2Z"/><path d="M19 12h1.5a2 2 0 0 0 0-4H18"/><path d="M9 18v2"/><path d="M12 18v2"/><path d="M15 18v2"/><path d="M9 6.2c0-1 .5-1.8 1.2-2.5"/><path d="M14.8 6.2c0-1-.5-1.8-1.2-2.5"/></svg>',
    'smart-plug' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3v4"/><path d="M15 3v4"/><path d="M7 9h10v3a5 5 0 0 1-10 0V9Z"/><path d="M12 17v4"/><path d="M9.5 21h5"/></svg>',
    'torch' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h8l1.6 4.5H6.4L8 3Z"/><path d="M7 7.5h10v2H7z"/><path d="M9 9.5v8a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2v-8"/><path d="M12 13.5v3"/><path d="M10.5 15h3"/></svg>',
    'sensor' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5a7 7 0 1 1-7 7"/><path d="M12 9a3 3 0 1 1-3 3"/><circle cx="12" cy="12" r="1.3"/><path d="M19 5 5 19"/></svg>',
    'security' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6.5v5.6c0 4.5 2.9 8.6 7 9.9 4.1-1.3 7-5.4 7-9.9V6.5L12 3Z"/><path d="M12 9.2a1.8 1.8 0 1 0 0 3.6"/><path d="M12 12.8v3.5"/></svg>',
    'climate' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.5v19"/><path d="M12 7a2.5 2.5 0 1 0 2.5 2.5V17a3.5 3.5 0 1 1-5 0"/><path d="M12 14.5V17"/></svg>',
    'fan' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1.8"/><path d="M12.7 4.5c2.2 0 3.8 2.3 2.9 4.2L14.2 11"/><path d="M19.1 14c1.1 1.9-.4 4.8-2.7 4.8h-2.8"/><path d="M8.8 18.8c-1.1 1.9-4.4 2-5.5.1l-1.3-2.4"/><path d="M4.9 10.2C3.8 8.3 5.3 5.4 7.6 5.4h2.8"/></svg>',
    'lamp' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 10.5h9L15 6H9l-1.5 4.5Z"/><path d="M12 3.5V6"/><path d="M12 10.5V18"/><path d="M9 20h6"/></svg>',
    'smart-device' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="3"/><path d="M9 20h6"/><path d="M12 16v4"/><circle cx="12" cy="10" r="2.2"/></svg>',
];

$name = basename($_GET['icon'] ?? 'smart-device');
$name = preg_replace('/[^a-zA-Z0-9\-]/', '', $name);
$icon = $icons[$name] ?? $icons['smart-device'];
echo $icon;
