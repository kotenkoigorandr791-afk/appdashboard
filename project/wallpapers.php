<?php

function ap_wallpaper_catalog(): array {
    return [
        'default' => ['path' => null, 'i18n' => 'wallpaper_default'],
        'photo-mountains' => ['path' => 'photos/mountains.jpg', 'i18n' => 'wallpaper_photo_mountains'],
        'photo-forest' => ['path' => 'photos/forest.jpg', 'i18n' => 'wallpaper_photo_forest'],
        'photo-lake' => ['path' => 'photos/lake.jpg', 'i18n' => 'wallpaper_photo_lake'],
        'photo-ocean' => ['path' => 'photos/ocean.jpg', 'i18n' => 'wallpaper_photo_ocean'],
        'photo-desert' => ['path' => 'photos/desert.jpg', 'i18n' => 'wallpaper_photo_desert'],
        'photo-city-day' => ['path' => 'photos/city-day.jpg', 'i18n' => 'wallpaper_photo_city_day'],
        'photo-city-night' => ['path' => 'photos/city-night.jpg', 'i18n' => 'wallpaper_photo_city_night'],
        'photo-tropical' => ['path' => 'photos/tropical.jpg', 'i18n' => 'wallpaper_photo_tropical'],
        'photo-valley' => ['path' => 'photos/valley.jpg', 'i18n' => 'wallpaper_photo_valley'],
        'photo-coast' => ['path' => 'photos/coast.jpg', 'i18n' => 'wallpaper_photo_coast'],
        'photo-autumn' => ['path' => 'photos/autumn.jpg', 'i18n' => 'wallpaper_photo_autumn'],
        'photo-snow' => ['path' => 'photos/snow.jpg', 'i18n' => 'wallpaper_photo_snow'],
        'photo-bridge' => ['path' => 'photos/bridge.jpg', 'i18n' => 'wallpaper_photo_bridge'],
    ];
}

function ap_allowed_wallpapers(): array {
    return array_keys(ap_wallpaper_catalog());
}

function ap_wallpaper_asset_url(string $id): string {
    $catalog = ap_wallpaper_catalog();
    if ($id === 'default' || !isset($catalog[$id]) || empty($catalog[$id]['path'])) {
        return '';
    }
    return '/project/assets/wallpapers/' . $catalog[$id]['path'];
}

function ap_wallpaper_preview_style(string $id): string {
    $url = ap_wallpaper_preview_url($id);
    if ($url === '') {
        return '';
    }
    return "background-image:url('" . str_replace("'", "%27", $url) . "');background-size:cover;background-position:center;";
}

function ap_wallpaper_preview_url(string $id): string {
    $catalog = ap_wallpaper_catalog();
    if ($id === 'default' || !isset($catalog[$id]) || empty($catalog[$id]['path'])) {
        return '';
    }
    $path = $catalog[$id]['path'];
    $pathParts = explode('/', $path);
    $filename = end($pathParts);
    $filenameWithoutExt = preg_replace('/\\.[^.]+$/', '', $filename);
    return '/project/assets/wallpapers/previews/photos/photo-' . $filenameWithoutExt . '.webp';
}
