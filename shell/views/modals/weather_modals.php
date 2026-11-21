<div id="weatherModalOverlay" class="weather-modal-overlay" onclick="closeWeatherModal(event)">
    <div class="weather-modal" onclick="event.stopPropagation()">
        <button type="button" class="weather-modal-close" onclick="closeWeatherModal()" aria-label="<?php echo my_htmlspecialchars($translations['close'] ?? 'Close'); ?>">&times;</button>
        <button type="button" class="weather-settings-btn" id="weatherSettingsBtn" onclick="openWeatherSettings()" title="<?php echo my_htmlspecialchars($translations['weather_settings'] ?? 'Settings'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings-icon lucide-settings"><path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
        <button type="button" class="weather-wind-chart-btn" id="weatherWindChartBtn" onclick="toggleWindChart()" title="<?php echo my_htmlspecialchars($translations['weather_wind_chart_btn'] ?? 'Show wind chart'); ?>" style="display:none;">
            <svg id="weatherWindChartIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/></svg>
        </button>
        <div id="weatherModalContent">
            <div class="weather-loading" style="display:flex;justify-content:center;padding:40px 0;">
                <div class="weather-loading-spinner"></div>
                <span><?php echo my_htmlspecialchars($translations['weather_loading_forecast'] ?? 'Loading forecast...'); ?></span>
            </div>
        </div>
    </div>
</div>

<div id="weatherSettingsModalOverlay" class="weather-modal-overlay" onclick="closeWeatherSettingsModal(event)" style="display: none;">
    <div class="weather-modal weather-settings-modal" onclick="event.stopPropagation()">
        <button type="button" class="weather-modal-close" onclick="closeWeatherSettingsModal()" aria-label="<?php echo my_htmlspecialchars($translations['close'] ?? 'Close'); ?>">&times;</button>
        <div id="weatherSettingsModalContent">
            <div class="weather-settings-header">
                <h3><?php echo my_htmlspecialchars($translations['weather_location_settings'] ?? 'Location settings'); ?></h3>
                <div class="location-switch" id="weatherLocationSwitch">
                    <div class="slider"></div>
                    <button class="active" onclick="setWeatherLocationType('current')">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/><circle cx="12" cy="12" r="4"/></svg>
                        <?php echo my_htmlspecialchars($translations['weather_use_current_location'] ?? 'Use current location'); ?>
                    </button>
                    <button onclick="setWeatherLocationType('city')">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                        <?php echo my_htmlspecialchars($translations['weather_use_selected_city'] ?? 'Use selected city'); ?>
                    </button>
                </div>
            </div>
         <div id="weatherCitySelection" style="display: none;">
             <div class="weather-location-selectors">
                 <div class="weather-selector">
                     <div class="weather-selector-title"><?php echo my_htmlspecialchars($translations['weather_select_country'] ?? 'Select country'); ?></div>
                     <div class="custom-select">
                         <div class="custom-select-selected" tabindex="0" role="combobox" aria-expanded="false" onclick="toggleCustomSelect('weatherCountryCustomSelect')" onkeydown="handleCustomSelectKey(event, 'weatherCountryCustomSelect', 'weatherCountrySelect')">
                             <span><?php echo my_htmlspecialchars($translations['weather_select_country'] ?? 'Select country'); ?></span>
                         </div>
                         <div id="weatherCountryCustomSelect" class="custom-select-options">
                             <div class="custom-select-option" data-value="" onclick="selectCustomOption(this, 'weatherCountryCustomSelect', 'weatherCountrySelect')">
                                 <?php echo my_htmlspecialchars($translations['weather_select_country'] ?? 'Select country'); ?>
                             </div>
                         </div>
                     </div>
                     <select id="weatherCountrySelect" class="weather-select-hidden">
                         <option value=""><?php echo my_htmlspecialchars($translations['weather_select_country'] ?? 'Select country'); ?></option>
                     </select>
                 </div>
                 <div class="weather-selector">
                     <div class="weather-selector-title"><?php echo my_htmlspecialchars($translations['weather_select_city'] ?? 'Select city'); ?></div>
                     <div class="custom-select">
                         <div class="custom-select-selected" tabindex="0" role="combobox" aria-expanded="false" onclick="toggleCustomSelect('weatherCityCustomSelect')" onkeydown="handleCustomSelectKey(event, 'weatherCityCustomSelect', 'weatherCitySelect')">
                             <span><?php echo my_htmlspecialchars($translations['weather_select_city'] ?? 'Select city'); ?></span>
                         </div>
                         <div id="weatherCityCustomSelect" class="custom-select-options">
                             <div class="custom-select-option" data-value="" onclick="selectCustomOption(this, 'weatherCityCustomSelect', 'weatherCitySelect')">
                                 <?php echo my_htmlspecialchars($translations['weather_select_city'] ?? 'Select city'); ?>
                             </div>
                         </div>
                     </div>
                     <select id="weatherCitySelect" class="weather-select-hidden" disabled>
                         <option value=""><?php echo my_htmlspecialchars($translations['weather_select_city'] ?? 'Select city'); ?></option>
                     </select>
                 </div>
             </div>
         </div>
            <div class="weather-settings-actions">
                <button type="button" class="weather-settings-cancel" onclick="closeWeatherSettingsModal()"><?php echo my_htmlspecialchars($translations['weather_cancel'] ?? 'Cancel'); ?></button>
                <button type="button" class="weather-settings-save" onclick="saveWeatherSettings()"><?php echo my_htmlspecialchars($translations['weather_save'] ?? 'Save'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
var weatherStore = { city: '', lat: null, lon: null, current: null, daily: null };
var windChartVisible = false;
var WT = (typeof T !== 'undefined') ? T : {};

function wt(key, fallback) {
    return (WT && WT[key]) ? WT[key] : fallback;
}
function getDailyWindSeries(daily) {
    if (!daily) return null;
    return daily.wind_speed_10m_max || daily.windspeed_10m_max || null;
}
function weatherForecastUrl(lat, lon) {
    return 'https://api.open-meteo.com/v1/forecast?latitude=' + lat + '&longitude=' + lon + '&current=temperature_2m,weather_code,wind_speed_10m&daily=weathercode,temperature_2m_max,temperature_2m_min,precipitation_sum,wind_speed_10m_max&timezone=auto&forecast_days=7';
}
function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str == null ? '' : String(str)));
    return d.innerHTML;
}
function weekdayNames() {
    return [
        wt('weather_weekday_0', 'Sunday'),
        wt('weather_weekday_1', 'Monday'),
        wt('weather_weekday_2', 'Tuesday'),
        wt('weather_weekday_3', 'Wednesday'),
        wt('weather_weekday_4', 'Thursday'),
        wt('weather_weekday_5', 'Friday'),
        wt('weather_weekday_6', 'Saturday')
    ];
}
function getWeatherDesc(code) {
    if (code === 0) return wt('weather_desc_clear', 'Clear');
    if (code === 1) return wt('weather_desc_mainly_clear', 'Mainly clear');
    if (code === 2) return wt('weather_desc_partly_cloudy', 'Partly cloudy');
    if (code === 3) return wt('weather_desc_overcast', 'Overcast');
    if (code >= 45 && code <= 48) return wt('weather_desc_fog', 'Fog');
    if (code >= 51 && code <= 57) return wt('weather_desc_drizzle', 'Drizzle');
    if (code >= 61 && code <= 67) return wt('weather_desc_rain', 'Rain');
    if (code >= 71 && code <= 77) return wt('weather_desc_snow', 'Snow');
    if (code >= 80 && code <= 82) return wt('weather_desc_showers', 'Rain showers');
    if (code >= 95) return wt('weather_desc_thunderstorm', 'Thunderstorm');
    return wt('weather_desc_cloudy', 'Cloudy');
}
function formatPrecip(value) {
    if (!value || value <= 0) return wt('weather_precip_none', 'No rain');
    return wt('weather_precip_mm', '%s mm').replace('%s', value);
}

    var weatherIconBase = '/project/assets/weather-icons/animated/';
    function weatherIconName(code) {
        if (code === 0) return 'clear-day';
        if (code >= 1 && code <= 2) return 'cloudy-2-day';
        if (code === 3) return 'cloudy';
        if (code >= 45 && code <= 48) return 'fog';
        if (code >= 51 && code <= 57) return 'rainy-1';
        if (code >= 61 && code <= 67) return 'rainy-3';
        if (code >= 71 && code <= 77) return 'snowy-3';
        if (code >= 80 && code <= 82) return 'rainy-2';
        if (code >= 85 && code <= 86) return 'snowy-2';
        if (code >= 95) return 'thunderstorms';
        return 'cloudy';
    }
    function weatherSvg(code) {
        return '<img src="' + weatherIconBase + weatherIconName(code) + '.svg" alt="" loading="lazy" decoding="async">';
    }
    function weatherSvgSmall(code) {
        return weatherSvg(code);
    }

var modalOverlay = document.getElementById('weatherModalOverlay');
var modalContent = document.getElementById('weatherModalContent');
var weatherGeoNoticeShown = false;

function getGeoPermissionState() {
    return new Promise(function(resolve) {
        if (!navigator.permissions || typeof navigator.permissions.query !== 'function') {
            resolve('unsupported');
            return;
        }
        try {
            navigator.permissions.query({ name: 'geolocation' }).then(function(status) {
                resolve(status && status.state ? status.state : 'unsupported');
            }).catch(function() { resolve('unsupported'); });
        } catch (e) {
            resolve('unsupported');
        }
    });
}
window.apWeatherGetGeoPermissionState = getGeoPermissionState;

window.openWeatherSettings = openWeatherSettings;
window.closeWeatherSettingsModal = closeWeatherSettingsModal;
window.setWeatherLocationType = setWeatherLocationType;
window.saveWeatherSettings = saveWeatherSettings;
window.toggleWindChart = toggleWindChart;
window.onCountryChange = onCountryChangeCustom;
window.onCityChange = onCityChangeCustom;
window.onCountryChangeCustom = onCountryChangeCustom;
window.onCityChangeCustom = onCityChangeCustom;
window.toggleCustomSelect = toggleCustomSelect;
window.selectCustomOption = selectCustomOption;
window.handleCustomSelectKey = handleCustomSelectKey;

var settingsModalOverlay = document.getElementById('weatherSettingsModalOverlay');
var settingsModalContent = document.getElementById('weatherSettingsModalContent');
var weatherLocationSwitch = document.getElementById('weatherLocationSwitch');
var weatherCitySelection = document.getElementById('weatherCitySelection');

var weatherSettings = {
    use_current_location: true,
    selected_country: null,
    selected_country_code: null,
    selected_region: null,
    selected_city: null,
    selected_city_label: null,
    selected_lat: null,
    selected_lon: null,
    countries: [],
    cities: [],
    current_step: 'country'
};
var weatherCountriesLoading = false;
var weatherCountriesLoaded = false;
var customSelectTypeahead = { id: null, text: '', ts: 0 };

function setWeatherLocationType(type) {
    const switcher = document.getElementById('weatherLocationSwitch');
    const buttons = switcher.querySelectorAll('button');
    switcher.classList.toggle('city', type === 'city');
    weatherSettings.use_current_location = type === 'current';

    buttons.forEach((button, index) => {
        button.classList.toggle(
            'active',
            type === 'current' ? index === 0 : index === 1
        );
    });

    weatherCitySelection.style.display = type === 'city' ? 'block' : 'none';
    if (type === 'city') {
        if (weatherCountriesLoaded) renderCountriesForSelect();
        else loadCountriesForSelect();
    }
}



function weatherFetchJson(url, options) {
    return fetch(url, options).then(response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    });
}

function customSelectLabel(selectId) {
    const options = document.getElementById(selectId);
    const wrap = options ? options.closest('.custom-select') : null;
    return wrap ? wrap.querySelector('.custom-select-selected span') : null;
}

function customSelectTrigger(selectId) {
    const options = document.getElementById(selectId);
    const wrap = options ? options.closest('.custom-select') : null;
    return wrap ? wrap.querySelector('.custom-select-selected') : null;
}

function positionCustomSelect(selectId) {
    const options = document.getElementById(selectId);
    const trigger = customSelectTrigger(selectId);
    if (!options || !trigger) return;
    const rect = trigger.getBoundingClientRect();
    const gap = 8;
    const width = Math.min(rect.width, window.innerWidth - 32);
    options.style.width = width + 'px';
    options.style.left = Math.max(16, Math.min(rect.left, window.innerWidth - width - 16)) + 'px';
    options.style.top = Math.min(rect.bottom + gap, window.innerHeight - 80) + 'px';
}

function setCustomSelectOpen(selectId, open) {
    const options = document.getElementById(selectId);
    const trigger = customSelectTrigger(selectId);
    if (!options) return;
    if (open) {
        positionCustomSelect(selectId);
        options.classList.add('show');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        setActiveCustomOption(selectId, currentCustomOptionIndex(selectId));
    } else {
        options.classList.remove('show');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
    }
}

function customSelectableOptions(selectId) {
    const options = document.getElementById(selectId);
    return options ? Array.from(options.querySelectorAll('.custom-select-option')) : [];
}

function currentCustomOptionIndex(selectId) {
    const items = customSelectableOptions(selectId);
    const selected = items.findIndex(item => item.dataset.value === (document.getElementById(selectId === 'weatherCountryCustomSelect' ? 'weatherCountrySelect' : 'weatherCitySelect') || {}).value);
    return selected >= 0 ? selected : 0;
}

function setActiveCustomOption(selectId, index) {
    const items = customSelectableOptions(selectId);
    if (!items.length) return;
    const next = Math.max(0, Math.min(index, items.length - 1));
    items.forEach((item, i) => item.classList.toggle('active', i === next));
    items[next].scrollIntoView({ block: 'nearest' });
}

function activeCustomOption(selectId) {
    const items = customSelectableOptions(selectId);
    return items.find(item => item.classList.contains('active')) || items[currentCustomOptionIndex(selectId)] || null;
}

function loadCountriesForSelect() {
    if (weatherCountriesLoading) return;
    const countrySelect = document.getElementById('weatherCountrySelect');
    if (!countrySelect) return;
    weatherCountriesLoading = true;
    countrySelect.innerHTML = '<option value="">' + escHtml(wt('weather_loading', 'Loading...')) + '</option>';
    countrySelect.disabled = true;
    weatherFetchJson('/project/weather_settings.php?action=countries')
        .then(data => {
            if (!data.countries || !data.countries.length) throw new Error('No countries');
            weatherSettings.countries = data.countries;
            weatherCountriesLoaded = true;
            renderCountriesForSelect();
        })
        .catch(() => {
            countrySelect.innerHTML = '<option value="">' + escHtml(wt('weather_fetch_error', 'Failed to load countries')) + '</option>';
        })
        .finally(() => {
            weatherCountriesLoading = false;
            countrySelect.disabled = false;
        });
}

function renderCountriesForSelect() {
    const countrySelect = document.getElementById('weatherCountrySelect');
    const customSelectOptions = document.getElementById('weatherCountryCustomSelect');
    if (!countrySelect || !customSelectOptions) return;
    countrySelect.innerHTML = '<option value="">' + escHtml(wt('weather_select_country', 'Select country')) + '</option>';
    customSelectOptions.innerHTML = '<div class="custom-select-option" data-value="" onclick="selectCustomOption(this, \'weatherCountryCustomSelect\', \'weatherCountrySelect\')">' + escHtml(wt('weather_select_country', 'Select country')) + '</div>';
    
    weatherSettings.countries.forEach(country => {
        const option = document.createElement('option');
        option.value = country.iso2;
        option.dataset.name = country.name;
        option.selected = weatherSettings.selected_country_code === country.iso2;
        option.innerHTML = `<img src="${country.flag}" alt="${country.iso2.toUpperCase()} flag" style="width: 20px; margin-right: 8px;" /> ${country.name}`;
        countrySelect.appendChild(option);
        
        const customOption = document.createElement('div');
        customOption.className = 'custom-select-option';
        customOption.dataset.value = country.iso2;
        customOption.innerHTML = `<img src="${country.flag}" alt="${country.iso2.toUpperCase()} flag" style="width: 20px; margin-right: 8px;" /> ${country.name}`;
        customOption.onclick = function() { selectCustomOption(this, 'weatherCountryCustomSelect', 'weatherCountrySelect'); };
        customSelectOptions.appendChild(customOption);
    });
    
    const selectedCountry = countrySelect.querySelector('option:checked');
    if (selectedCountry && selectedCountry.value) {
        const customSelected = document.querySelector('#weatherCountryCustomSelect .custom-select-option[data-value="' + selectedCountry.value + '"]');
        if (customSelected) {
            const label = customSelectLabel('weatherCountryCustomSelect');
            if (label) label.innerHTML = customSelected.innerHTML;
        }
    }
    
    loadCitiesForSelect();
}

function onCountryChangeCustom(select) {
    const option = select.options[select.selectedIndex];
    weatherSettings.selected_country = option.dataset.name || null;
    weatherSettings.selected_country_code = select.value || null;
    weatherSettings.selected_city = null;
    weatherSettings.selected_city_label = null;
    weatherSettings.selected_lat = null;
    weatherSettings.selected_lon = null;
    
    const customSelected = document.querySelector('#weatherCountryCustomSelect .custom-select-option[data-value="' + select.value + '"]');
    const countryLabel = customSelectLabel('weatherCountryCustomSelect');
    if (customSelected) {
        if (countryLabel) countryLabel.innerHTML = customSelected.innerHTML;
    } else if (countryLabel) {
        countryLabel.textContent = option ? option.textContent : wt('weather_select_country', 'Select country');
    }
    
    loadCitiesForSelect();
}

function loadCitiesForSelect() {
    const citySelect = document.getElementById('weatherCitySelect');
    const customSelectOptions = document.getElementById('weatherCityCustomSelect');
    const cityLabel = customSelectLabel('weatherCityCustomSelect');
    if (!citySelect) return;
    citySelect.innerHTML = '<option value="">' + escHtml(wt('weather_select_city', 'Select city')) + '</option>';
    if (customSelectOptions) customSelectOptions.innerHTML = '<div class="custom-select-option" data-value="" onclick="selectCustomOption(this, \'weatherCityCustomSelect\', \'weatherCitySelect\')">' + escHtml(wt('weather_select_city', 'Select city')) + '</div>';
    if (cityLabel) cityLabel.textContent = wt('weather_select_city', 'Select city');
    citySelect.disabled = !weatherSettings.selected_country_code;
    if (!weatherSettings.selected_country_code) return;
    citySelect.innerHTML = '<option value="">' + escHtml(wt('weather_loading', 'Loading...')) + '</option>';
    weatherFetchJson('/project/weather_settings.php?action=country_cities&country=' + encodeURIComponent(weatherSettings.selected_country))
        .then(data => {
            citySelect.innerHTML = '<option value="">' + escHtml(wt('weather_select_city', 'Select city')) + '</option>';
            (data.cities || []).forEach(name => {
                const option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                option.selected = name === weatherSettings.selected_city;
                citySelect.appendChild(option);
                if (customSelectOptions) {
                    const customOption = document.createElement('div');
                    customOption.className = 'custom-select-option';
                    customOption.dataset.value = name;
                    customOption.textContent = name;
                    customOption.onclick = function() { selectCustomOption(this, 'weatherCityCustomSelect', 'weatherCitySelect'); };
                    customSelectOptions.appendChild(customOption);
                }
            });
            if (weatherSettings.selected_city && cityLabel) cityLabel.textContent = weatherSettings.selected_city_label || weatherSettings.selected_city;
            citySelect.disabled = false;
        })
        .catch(() => {
            citySelect.innerHTML = '<option value="">' + escHtml(wt('weather_fetch_error', 'Failed to load cities')) + '</option>';
        });
}

function onCityChangeCustom(select) {
    const option = select.options[select.selectedIndex];
    if (!option || !option.value) {
        weatherSettings.selected_city = null;
        weatherSettings.selected_city_label = null;
        weatherSettings.selected_lat = null;
        weatherSettings.selected_lon = null;
        const label = customSelectLabel('weatherCityCustomSelect');
        if (label) label.textContent = wt('weather_select_city', 'Select city');
        return;
    }
    select.disabled = true;
    
    const customSelected = document.querySelector('#weatherCityCustomSelect .custom-select-option[data-value="' + option.value + '"]');
    if (customSelected) {
        const label = customSelectLabel('weatherCityCustomSelect');
        if (label) label.textContent = customSelected.textContent;
    }
    
    weatherFetchJson('/project/weather_settings.php?action=coords&city=' + encodeURIComponent(option.value.split('|')[0]) + '&country=' + encodeURIComponent(weatherSettings.selected_country) + '&country_code=' + encodeURIComponent(weatherSettings.selected_country_code))
        .then(city => {
            weatherSettings.selected_city = city.name;
            weatherSettings.selected_city_label = city.label || city.name;
            weatherSettings.selected_country = city.country || weatherSettings.selected_country;
            weatherSettings.selected_country_code = city.country_code || weatherSettings.selected_country_code;
            weatherSettings.selected_lat = city.lat;
            weatherSettings.selected_lon = city.lon;
        })
        .catch(() => {
            select.value = '';
        })
        .finally(() => {
            select.disabled = false;
        });
}

function openWeatherSettings() {
    if (!settingsModalOverlay) return;

    weatherFetchJson('/project/weather_settings.php')
        .then(data => {
            weatherSettings.use_current_location = data.use_current_location !== false;
            weatherSettings.selected_country = data.selected_country;
            weatherSettings.selected_country_code = data.selected_country_code;
            weatherSettings.selected_region = data.selected_region;
            weatherSettings.selected_city = data.selected_city;
            weatherSettings.selected_lat = data.lat;
            weatherSettings.selected_lon = data.lon;

            setWeatherLocationType(weatherSettings.use_current_location ? 'current' : 'city');
        })
        .catch(() => {
            weatherSettings.current_step = 'country';
            setWeatherLocationType('city');
        });

    settingsModalOverlay.style.display = 'flex';
    settingsModalOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function toggleCustomSelect(selectId) {
    const options = document.getElementById(selectId);
    if (!options) return;
    const open = !options.classList.contains('show');
    document.querySelectorAll('.custom-select-options').forEach(el => {
        if (el.id !== selectId) {
            setCustomSelectOpen(el.id, false);
        }
    });
    setCustomSelectOpen(selectId, open);
}

function selectCustomOption(option, selectId, hiddenSelectId) {
    const select = document.getElementById(selectId);
    const hiddenSelect = document.getElementById(hiddenSelectId);
    if (!select || !hiddenSelect) return;
    
    hiddenSelect.value = option.dataset.value;
    if (hiddenSelectId === 'weatherCountrySelect') {
        onCountryChangeCustom(hiddenSelect);
    } else if (hiddenSelectId === 'weatherCitySelect') {
        onCityChangeCustom(hiddenSelect);
    }
    const selectedDisplay = customSelectLabel(selectId);
    if (selectedDisplay) {
        selectedDisplay.innerHTML = option.innerHTML;
    }
    setCustomSelectOpen(selectId, false);
}

function handleCustomSelectKey(e, selectId, hiddenSelectId) {
    const options = document.getElementById(selectId);
    if (!options) return;
    const isOpen = options.classList.contains('show');
    const items = customSelectableOptions(selectId);
    const activeIndex = Math.max(0, items.findIndex(item => item.classList.contains('active')));
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!isOpen) {
            setCustomSelectOpen(selectId, true);
            return;
        }
        setActiveCustomOption(selectId, activeIndex + (e.key === 'ArrowDown' ? 1 : -1));
        return;
    }
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (!isOpen) {
            setCustomSelectOpen(selectId, true);
            return;
        }
        const active = activeCustomOption(selectId);
        if (active) selectCustomOption(active, selectId, hiddenSelectId);
        return;
    }
    if (e.key === 'Escape') {
        setCustomSelectOpen(selectId, false);
        return;
    }
    if (e.key && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
        e.preventDefault();
        const ch = e.key.toLowerCase();
        const now = Date.now();
        const sameSelect = customSelectTypeahead.id === selectId;
        const within = sameSelect && (now - customSelectTypeahead.ts < 900);
        const prev = sameSelect ? (customSelectTypeahead.text || '') : '';
        let query;
        let start;
        if (within && prev === ch) {
            query = ch;
            start = activeIndex + 1;
        } else if (within && prev) {
            query = prev + ch;
            start = 0;
        } else {
            query = ch;
            start = 0;
        }
        customSelectTypeahead.id = selectId;
        customSelectTypeahead.text = query;
        customSelectTypeahead.ts = now;
        if (!isOpen) setCustomSelectOpen(selectId, true);
        const matchable = items.filter(item => (item.dataset.value || '') !== '');
        if (!matchable.length) return;
        const from = Math.max(0, matchable.indexOf(items[activeIndex]));
        const ordered = start > 0
            ? matchable.slice(from + 1).concat(matchable.slice(0, from + 1))
            : matchable.slice();
        const found = ordered.find(item => item.textContent.trim().toLowerCase().startsWith(query));
        if (found) setActiveCustomOption(selectId, items.indexOf(found));
    }
}

window.addEventListener('click', function(e) {
    if (!e.target.closest || !e.target.closest('.custom-select')) {
        document.querySelectorAll('.custom-select-options').forEach(el => {
            setCustomSelectOpen(el.id, false);
        });
    }
});

window.addEventListener('resize', function() {
    document.querySelectorAll('.custom-select-options.show').forEach(el => positionCustomSelect(el.id));
});

window.addEventListener('scroll', function() {
    document.querySelectorAll('.custom-select-options.show').forEach(el => positionCustomSelect(el.id));
}, true);

function closeWeatherSettingsModal(e) {
    if (e && e.target !== settingsModalOverlay && e.target !== e.currentTarget) return;
    if (settingsModalOverlay) {
        settingsModalOverlay.style.display = 'none';
        settingsModalOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function saveWeatherSettings() {
    if (!weatherSettings.use_current_location && (!weatherSettings.selected_city || weatherSettings.selected_lat == null || weatherSettings.selected_lon == null)) {
        const citySelect = document.getElementById('weatherCitySelect');
        if (citySelect) citySelect.focus();
        if (typeof showTempNotification === 'function') showTempNotification(wt('weather_select_city', 'Select city'), 'info');
        return;
    }
    const settings = weatherSettings.use_current_location ? {
        use_current_location: true,
        selected_country: null,
        selected_country_code: null,
        selected_region: null,
        selected_city: null,
        lat: null,
        lon: null
    } : {
        use_current_location: false,
        selected_country: weatherSettings.selected_country,
        selected_country_code: weatherSettings.selected_country_code,
        selected_region: null,
        selected_city: weatherSettings.selected_city,
        lat: weatherSettings.selected_lat,
        lon: weatherSettings.selected_lon
    };

    weatherFetchJson('/project/weather_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(settings)
    })
    .then(data => {
        if (data.success) {
            closeWeatherSettingsModal();
            if (!weatherSettings.use_current_location && weatherSettings.selected_city) {
                weatherStore.lat = data.lat;
                weatherStore.lon = data.lon;
                weatherStore.city = weatherSettings.selected_city;
                window.apWeatherRefresh(weatherStore.lat, weatherStore.lon, weatherSettings.selected_city);
                // Save settings to localStorage so they don't reset on page reload
                try {
                    localStorage.setItem('ap_weather_data', JSON.stringify({
                        ts: Date.now(),
                        lat: data.lat,
                        lon: data.lon,
                        city: weatherSettings.selected_city,
                        temp: weatherStore.current?.temperature_2m,
                        code: weatherStore.current?.weather_code,
                        wind: weatherStore.current?.wind_speed_10m,
                        daily: weatherStore.daily
                    }));
                } catch(e) {}
            } else if (weatherSettings.use_current_location) {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(pos) {
                        weatherStore.lat = pos.coords.latitude;
                        weatherStore.lon = pos.coords.longitude;
                        window.apWeatherRefresh(weatherStore.lat, weatherStore.lon, null, true);
                    }, function(err) {
                        if (err && err.code === err.PERMISSION_DENIED) {
                            if (window.apWeatherShowPermError) window.apWeatherShowPermError(wt('weather_denied', 'Location access denied. Enable it to see local weather.'));
                            return;
                        }
                        fetch('https://ipwho.is/').then(function(r){ return r.json(); }).then(function(data) {
                            if (data && data.success !== false && data.latitude != null && data.longitude != null) {
                                weatherStore.lat = data.latitude;
                                weatherStore.lon = data.longitude;
                                window.apWeatherRefresh(weatherStore.lat, weatherStore.lon, data.city || null, !data.city);
                                return;
                            }
                            if (window.apWeatherShowPermError) window.apWeatherShowPermError(wt('weather_timeout', 'Location request timed out'));
                        }).catch(function() {
                            if (window.apWeatherShowPermError) window.apWeatherShowPermError(wt('weather_timeout', 'Location request timed out'));
                        });
                    }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
                }
            }
        }
    })
    .catch(() => {
        closeWeatherSettingsModal();
    });
}

























function renderWeatherGeoPrompt(message) {
    modalContent.innerHTML = '<div class="weather-geo-prompt">' +
        '<div>' + escHtml(message || wt('weather_denied_msg', 'Please allow location access in your browser settings to see local weather')) + '</div>' +
        '<button type="button" class="weather-location-enable" onclick="window.apRequestWeatherLocationFromModal()">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>' +
            '<span>' + escHtml(wt('weather_allow_location', 'Allow location')) + '</span>' +
        '</button>' +
    '</div>';
}

function notifyWeatherGeoUnavailable(message) {
    if(weatherGeoNoticeShown) return;
    weatherGeoNoticeShown = true;
    if(typeof showTempNotification === 'function') {
        showTempNotification(message || wt('weather_denied', 'Location access denied. Enable it to see local weather'), 'info');
    }
}

window.apRequestWeatherLocationFromModal = function() {
    function applyPos(lat, lon, city) {
        weatherStore.lat = lat;
        weatherStore.lon = lon;
        if (city) weatherStore.city = city;
        if (window.apWeatherRefresh) window.apWeatherRefresh(lat, lon, city || null, !city);
        showWeatherModal();
    }
    function ipFallback(failMsg) {
        fetch('https://ipwho.is/').then(function(r){ return r.json(); }).then(function(data) {
            if (data && data.success !== false && data.latitude != null && data.longitude != null) {
                applyPos(data.latitude, data.longitude, data.city || '');
                return;
            }
            renderWeatherGeoPrompt(failMsg);
            notifyWeatherGeoUnavailable(failMsg);
        }).catch(function() {
            renderWeatherGeoPrompt(failMsg);
            notifyWeatherGeoUnavailable(failMsg);
        });
    }
    if(!navigator.geolocation) {
        ipFallback(wt('weather_no_geo', 'Geolocation not supported'));
        return;
    }
    modalContent.innerHTML = '<div style="text-align:center;padding:40px 0;opacity:0.7">' + escHtml(wt('weather_loading_forecast', 'Loading forecast...')) + '</div>';
    navigator.geolocation.getCurrentPosition(function(pos) {
        applyPos(pos.coords.latitude, pos.coords.longitude, null);
    }, function(err) {
        if (err && err.code === err.PERMISSION_DENIED) {
            renderWeatherGeoPrompt(wt('weather_settings_hint', 'Go to browser site settings and allow location'));
            notifyWeatherGeoUnavailable(wt('weather_denied', 'Location access denied. Enable it to see local weather.'));
            return;
        }
        ipFallback(err && err.code === err.TIMEOUT ? wt('weather_timeout', 'Location request timed out') : wt('weather_no_geo', 'Could not determine location'));
    }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
};

function showWeatherModal() {
    if(!modalOverlay) return;
    if(weatherStore.daily && weatherStore.daily.time) {
        if(!getDailyWindSeries(weatherStore.daily) && weatherStore.lat != null && weatherStore.lon != null) {
            weatherStore.daily = null;
        } else {
            showModalWithData();
            return;
        }
    }
    if(weatherStore.lat == null || weatherStore.lon == null) {
        modalOverlay.style.display = 'flex';
        modalOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        if (!navigator.geolocation) {
            renderWeatherGeoPrompt(wt('weather_no_geo', 'Geolocation not supported'));
            return;
        }
        // Missing coordinates alone do not mean access was denied — ask the
        // browser for the real permission state first.
        getGeoPermissionState().then(function(state) {
            if (state === 'denied') {
                renderWeatherGeoPrompt(wt('weather_denied_msg', 'Please allow location access in your browser settings to see local weather'));
            } else {
                renderWeatherGeoPrompt(wt('weather_perm_denied', 'Please allow location access'));
            }
        });
        return;
    }
    modalContent.innerHTML = '<div style="text-align:center;padding:40px 0;opacity:0.7">' + escHtml(wt('weather_loading_forecast', 'Loading forecast...')) + '</div>';
    modalOverlay.style.display = 'flex';
    modalOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    var url = weatherForecastUrl(weatherStore.lat, weatherStore.lon);
    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if(data && data.daily) {
                weatherStore.daily = data.daily;
                showModalWithData();
            } else {
                modalContent.innerHTML = '<div style="text-align:center;padding:40px;opacity:0.6">' + escHtml(wt('weather_no_forecast', 'No forecast data available')) + '</div>';
            }
        })
        .catch(function() {
            modalContent.innerHTML = '<div style="text-align:center;padding:40px;opacity:0.6">' + escHtml(wt('weather_fetch_forecast_error', 'Failed to load forecast')) + '</div>';
        });
}

function showModalWithData() {
    modalOverlay.style.display = 'flex';
    modalOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    windChartVisible = false;
    var windBtn = document.getElementById('weatherWindChartBtn');
    if (windBtn) {
        windBtn.style.display = getDailyWindSeries(weatherStore.daily) ? 'flex' : 'none';
        windBtn.title = wt('weather_wind_chart_btn', 'Show wind chart');
    }
    renderWeeklyForecast();
}

window.closeWeatherModal = function(e) {
    if(e && e.target !== modalOverlay && e.target !== e.currentTarget) return;
    if(modalOverlay) {
        modalOverlay.style.display = 'none';
        modalOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
};

document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') {
        if(modalOverlay && modalOverlay.classList.contains('active')) {
            window.closeWeatherModal();
        }
        if(settingsModalOverlay && settingsModalOverlay.classList.contains('active')) {
            window.closeWeatherSettingsModal();
        }
    }
});

function getDayName(dateStr) {
    var d = new Date(dateStr + 'T12:00:00');
    var today = new Date();
    today.setHours(12,0,0,0);
    var diff = Math.round((d.getTime() - today.getTime()) / 86400000);
    if(diff === 0) return wt('weather_today', 'Today');
    if(diff === 1) return wt('weather_tomorrow', 'Tomorrow');
    return weekdayNames()[d.getDay()];
}

function formatDate(dateStr) {
    var d = new Date(dateStr + 'T12:00:00');
    return d.getDate();
}

function renderWeeklyForecast() {
    var daily = weatherStore.daily;
    var current = weatherStore.current;
    var city = weatherStore.city || '';
    if(!daily || !daily.time) {
        modalContent.innerHTML = '<div style="text-align:center;padding:40px;opacity:0.6">' + escHtml(wt('weather_no_forecast', 'No forecast data available')) + '</div>';
        return;
    }
    var curCode = current ? (current.weather_code != null ? current.weather_code : current.weathercode) : (daily.weathercode ? daily.weathercode[0] : 0);
    var curTemp = current ? Math.round(current.temperature_2m != null ? current.temperature_2m : current.temperature) : Math.round(daily.temperature_2m_max[0]);
    var curWind = current ? Math.round(current.wind_speed_10m != null ? current.wind_speed_10m : current.windspeed) : null;
    var curDesc = getWeatherDesc(curCode);
    var currentLine = curTemp + '\u00b0C \u00b7 ' + curDesc;
    if (curWind != null) currentLine += ' \u00b7 ' + wt('weather_wind', 'Wind') + ' ' + curWind + ' km/h';

    var html = '<div class="weather-modal-header">' +
        '<div class="weather-modal-header-icon">' + weatherSvg(curCode) + '</div>' +
        '<div class="weather-modal-header-info">' +
            '<div class="weather-modal-title">' + escHtml(wt('weather_forecast_title', '7-day forecast')) + '</div>' +
            '<div class="weather-modal-city">' + escHtml(city) + '</div>' +
            '<div class="weather-modal-current">' + escHtml(currentLine) + '</div>' +
        '</div>' +
    '</div>' +
    '<div class="weather-modal-body">' +
    '<div class="weather-weekly">';

    for(var i = 0; i < daily.time.length; i++) {
        var dateStr = daily.time[i];
        var wCode = daily.weathercode[i];
        var dayName = getDayName(dateStr);
        var dayDate = formatDate(dateStr);
        var desc = getWeatherDesc(wCode);
        var high = Math.round(daily.temperature_2m_max[i]);
        var low = Math.round(daily.temperature_2m_min[i]);
        var precip = daily.precipitation_sum ? Math.round(daily.precipitation_sum[i] * 10) / 10 : 0;
        var precipLabel = formatPrecip(precip);
        html += '<div class="weather-day-row">' +
            '<div class="weather-day-name">' + escHtml(dayName) + '</div>' +
            '<div class="weather-day-date">' + dayDate + '</div>' +
            '<div class="weather-day-icon-wrap">' + weatherSvgSmall(wCode) + '</div>' +
            '<div class="weather-day-desc">' + escHtml(desc) + '</div>' +
            '<div class="weather-day-temps">' +
                '<span class="weather-day-temp-high">' + high + '&deg;</span>' +
                '<span class="weather-day-temp-low">' + low + '&deg;</span>' +
            '</div>' +
            '<div class="weather-day-precip' + (precip > 0 ? '' : ' is-dry') + '" title="' + escHtml(wt('weather_precip', 'Precipitation')) + '">' +
                (precip > 0 ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#2563eb" stroke="none"><path d="M12 2C7.58 2 4 5.58 4 10c0 3.5 2.33 6.35 5.5 7.5L12 22l2.5-4.5C17.67 16.35 20 13.5 20 10c0-4.42-3.58-8-8-8z"/></svg>' : '') +
                escHtml(precipLabel) +
            '</div>' +
        '</div>';
    }
    html += '</div>' +
        '<div class="weather-chart-wrapper">' +
            '<div class="weather-chart-title">' + escHtml(wt('weather_chart_title', 'Temperature trend')) + '</div>' +
            '<div class="weather-chart-container">' +
                '<canvas id="weatherChartCanvas" width="640" height="220"></canvas>' +
            '</div>' +
            '<div class="weather-chart-legend">' +
                '<div class="weather-chart-legend-item"><span class="weather-chart-legend-dot" style="background:#ef4444"></span> ' + escHtml(wt('weather_high', 'High')) + '</div>' +
                '<div class="weather-chart-legend-item"><span class="weather-chart-legend-dot" style="background:#3b82f6"></span> ' + escHtml(wt('weather_low', 'Low')) + '</div>' +
            '</div>' +
        '</div>' +
    '</div>';
    modalContent.innerHTML = html;
    setTimeout(function() { drawTemperatureChart(daily); }, 50);
}

function drawTemperatureChart(daily) {
    var canvas = document.getElementById('weatherChartCanvas');
    if(!canvas || !daily || !daily.time || daily.time.length === 0) return;
    var rect = canvas.parentElement ? canvas.parentElement.getBoundingClientRect() : null;
    var cssW = 640;
    var cssH = window.matchMedia('(max-width: 500px)').matches ? 190 : 220;
    if (rect && rect.width > 0) {
        cssW = Math.max(320, Math.round(rect.width));
    }
    var dpr = Math.max(1, window.devicePixelRatio || 1);
    canvas.style.width = cssW + 'px';
    canvas.style.height = cssH + 'px';
    canvas.width = Math.round(cssW * dpr);
    canvas.height = Math.round(cssH * dpr);
    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    var W = cssW;
    var H = cssH;
    var pad = { top: 24, right: 16, bottom: 72, left: 42 };
    var chartW = W - pad.left - pad.right;
    var chartH = H - pad.top - pad.bottom;
    var n = daily.time.length;
    var highs = [];
    var lows = [];
    for(var i = 0; i < n; i++) {
        highs.push(daily.temperature_2m_max[i]);
        lows.push(daily.temperature_2m_min[i]);
    }
    var allTemps = highs.concat(lows);
    var minTemp = Math.min.apply(null, allTemps);
    var maxTemp = Math.max.apply(null, allTemps);
    var range = maxTemp - minTemp;
    if(range < 2) { minTemp -= 1; maxTemp += 1; range = 2; }
    var padding = range * 0.12;
    minTemp -= padding;
    maxTemp += padding;
    range = maxTemp - minTemp;
    function yFromTemp(t) { return pad.top + chartH - ((t - minTemp) / range) * chartH; }
    function xFromIdx(idx) { return n <= 1 ? pad.left + chartW / 2 : pad.left + (idx / (n - 1)) * chartW; }
    ctx.clearRect(0, 0, W, H);
    var bg = getComputedStyle(document.body).getPropertyValue('--group-block-bg').trim() || '#ffffff';
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);
    var textColor = getComputedStyle(document.body).getPropertyValue('--text-color').trim() || '#64748b';
    var gridColor = 'rgba(127,127,127,0.12)';
    ctx.strokeStyle = gridColor;
    ctx.lineWidth = 1;
    ctx.setLineDash([4, 4]);
    var gridSteps = 4;
    for(var g = 0; g <= gridSteps; g++) {
        var y = pad.top + (g / gridSteps) * chartH;
        ctx.beginPath();
        ctx.moveTo(pad.left, y);
        ctx.lineTo(W - pad.right, y);
        ctx.stroke();
        var tempVal = maxTemp - (g / gridSteps) * range;
        ctx.fillStyle = textColor;
        ctx.font = '10px sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        ctx.fillText(Math.round(tempVal) + '\u00b0', pad.left - 6, y);
    }
    ctx.setLineDash([]);
    function drawCurvedLine(data, color, fillColor) {
        ctx.beginPath();
        for(var i = 0; i < n; i++) {
            var x = xFromIdx(i);
            var y = yFromTemp(data[i]);
            if(i === 0) ctx.moveTo(x, y);
            else {
                var prevX = xFromIdx(i - 1);
                var prevY = yFromTemp(data[i - 1]);
                ctx.bezierCurveTo((prevX + x) / 2, prevY, (prevX + x) / 2, y, x, y);
            }
        }
        if(fillColor) {
            ctx.lineTo(xFromIdx(n - 1), pad.top + chartH);
            ctx.lineTo(xFromIdx(0), pad.top + chartH);
            ctx.closePath();
            ctx.fillStyle = fillColor;
            ctx.fill();
            ctx.beginPath();
            for(var j = 0; j < n; j++) {
                var x2 = xFromIdx(j);
                var y2 = yFromTemp(data[j]);
                if(j === 0) ctx.moveTo(x2, y2);
                else {
                    var px2 = xFromIdx(j - 1);
                    var py2 = yFromTemp(data[j - 1]);
                    ctx.bezierCurveTo((px2 + x2) / 2, py2, (px2 + x2) / 2, y2, x2, y2);
                }
            }
        }
        ctx.strokeStyle = color;
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';
        ctx.stroke();
    }
    drawCurvedLine(lows, '#3b82f6', 'rgba(59,130,246,0.10)');
    drawCurvedLine(highs, '#ef4444', 'rgba(239,68,68,0.08)');
    function drawPointLabel(x, y, text, color, above) {
        ctx.fillStyle = color;
        ctx.font = 'bold 10px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = above ? 'bottom' : 'top';
        ctx.fillText(text, x, y + (above ? -10 : 10));
    }
    for(var i = 0; i < n; i++) {
        var x = xFromIdx(i);
        var yHigh = yFromTemp(highs[i]);
        var yLow = yFromTemp(lows[i]);
        ctx.beginPath();
        ctx.arc(x, yHigh, 4, 0, Math.PI * 2);
        ctx.fillStyle = '#fff';
        ctx.fill();
        ctx.strokeStyle = '#ef4444';
        ctx.lineWidth = 2.5;
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(x, yLow, 4, 0, Math.PI * 2);
        ctx.fillStyle = '#fff';
        ctx.fill();
        ctx.strokeStyle = '#3b82f6';
        ctx.lineWidth = 2.5;
        ctx.stroke();
        drawPointLabel(x, yHigh, Math.round(highs[i]) + '\u00b0', '#ef4444', true);
        drawPointLabel(x, yLow, Math.round(lows[i]) + '\u00b0', '#3b82f6', false);
    }
    ctx.fillStyle = textColor;
    ctx.font = '10px sans-serif';
    for(var d = 0; d < n; d++) {
        var label = getDayName(daily.time[d]);
        var lx = xFromIdx(d);
        var ly = H - pad.bottom + 10;
        ctx.save();
        ctx.translate(lx, ly);
        ctx.rotate(-0.55);
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        ctx.fillText(label, 0, 0);
        ctx.restore();
    }
}
function toggleWindChart() {
    var btn = document.getElementById('weatherWindChartBtn');
    if (!windChartVisible) {
        windChartVisible = true;
        drawWindChart(weatherStore.daily);
        if (btn) btn.title = wt('weather_wind_chart_back', 'Back to forecast');
    } else {
        windChartVisible = false;
        renderWeeklyForecast();
        if (btn) btn.title = wt('weather_wind_chart_btn', 'Show wind chart');
    }
}
function drawWindChart(daily) {
    var windSeries = getDailyWindSeries(daily);
    if (!daily || !daily.time || !windSeries) return;
    var n = daily.time.length;
    var winds = [];
    var precips = [];
    for (var i = 0; i < n; i++) {
        winds.push(windSeries[i] != null ? windSeries[i] : 0);
        precips.push(daily.precipitation_sum && daily.precipitation_sum[i] != null ? daily.precipitation_sum[i] : 0);
    }
    var bg = getComputedStyle(document.body).getPropertyValue('--group-block-bg').trim() || '#ffffff';
    var textColor = getComputedStyle(document.body).getPropertyValue('--text-color').trim() || '#64748b';
    var gridColor = 'rgba(127,127,127,0.12)';
    var lineColor = '#10b981';
    var fillColor = 'rgba(16,185,129,0.10)';
    var precipColor = 'rgba(59,130,246,0.25)';
    var minW = Math.min.apply(null, winds);
    var maxW = Math.max.apply(null, winds);
    var range = maxW - minW;
    if (range < 2) { minW = Math.max(0, minW - 1); maxW = maxW + 1; range = maxW - minW; }
    var padVal = range * 0.15;
    minW = Math.max(0, minW - padVal);
    maxW += padVal;
    range = maxW - minW;
    var maxP = Math.max.apply(null, precips);
    if (maxP <= 0) maxP = 1;
    var vw = 640, vh = 300;
    var pad = { top: 40, right: 20, bottom: 70, left: 60 };
    var cW = vw - pad.left - pad.right;
    var cH = vh - pad.top - pad.bottom;
    function yP(v) { return pad.top + cH - ((v - minW) / range) * cH; }
    function xP(idx) { var step = cW / n; return n <= 1 ? pad.left + cW / 2 : pad.left + step / 2 + idx * step; }
    var title = wt('weather_wind_chart_title', 'Wind speed');
    var unit = wt('weather_wind_speed_unit', 'km/h');
    var precipUnit = wt('weather_precip_mm', '%s mm').replace('%s', '');
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + vw + ' ' + vh + '" style="width:100%;height:auto;display:block;font-family:sans-serif;">';
    svg += '<rect width="' + vw + '" height="' + vh + '" fill="' + bg + '" rx="12"/>';
    svg += '<text x="' + (vw / 2) + '" y="24" text-anchor="middle" fill="' + textColor + '" font-size="14" font-weight="600">' + escHtml(title) + '</text>';
    var gridSteps = 4;
    for (var g = 0; g <= gridSteps; g++) {
        var gy = pad.top + (g / gridSteps) * cH;
        var gVal = maxW - (g / gridSteps) * range;
        svg += '<line x1="' + pad.left + '" y1="' + gy + '" x2="' + (vw - pad.right) + '" y2="' + gy + '" stroke="' + gridColor + '" stroke-width="1" stroke-dasharray="4,4"/>';
        svg += '<text x="' + (pad.left - 6) + '" y="' + gy + '" text-anchor="end" dominant-baseline="middle" fill="' + textColor + '" font-size="10">' + Math.round(gVal) + '</text>';
    }
    var barW = cW / n * 0.45;
    for (var i = 0; i < n; i++) {
        var bx = xP(i);
        var barH = (precips[i] / maxP) * cH;
        var by = pad.top + cH - barH;
        svg += '<rect x="' + (bx - barW / 2) + '" y="' + by + '" width="' + barW + '" height="' + barH + '" fill="' + precipColor + '" rx="3"/>';
        if (precips[i] > 0) {
            svg += '<text x="' + bx + '" y="' + (by - 4) + '" text-anchor="middle" fill="rgba(59,130,246,0.6)" font-size="9">' + precips[i].toFixed(1) + '</text>';
        }
    }
    var pts = [];
    for (var i = 0; i < n; i++) {
        pts.push({ x: xP(i), y: yP(winds[i]) });
    }
    var fillPath = 'M' + pts[0].x + ',' + pts[0].y;
    for (var i = 1; i < n; i++) {
        var cx1 = (pts[i - 1].x + pts[i].x) / 2;
        fillPath += ' C' + cx1 + ',' + pts[i - 1].y + ' ' + cx1 + ',' + pts[i].y + ' ' + pts[i].x + ',' + pts[i].y;
    }
    fillPath += ' L' + pts[n - 1].x + ',' + (pad.top + cH) + ' L' + pts[0].x + ',' + (pad.top + cH) + ' Z';
    svg += '<path d="' + fillPath + '" fill="' + fillColor + '"/>';
    var linePath = 'M' + pts[0].x + ',' + pts[0].y;
    for (var i = 1; i < n; i++) {
        var cx1 = (pts[i - 1].x + pts[i].x) / 2;
        linePath += ' C' + cx1 + ',' + pts[i - 1].y + ' ' + cx1 + ',' + pts[i].y + ' ' + pts[i].x + ',' + pts[i].y;
    }
    svg += '<path d="' + linePath + '" fill="none" stroke="' + lineColor + '" stroke-width="3" stroke-linejoin="round"/>';
    for (var i = 0; i < n; i++) {
        svg += '<circle cx="' + pts[i].x + '" cy="' + pts[i].y + '" r="4" fill="#fff" stroke="' + lineColor + '" stroke-width="2.5"/>';
        svg += '<text x="' + pts[i].x + '" y="' + (pts[i].y - 10) + '" text-anchor="middle" fill="' + lineColor + '" font-size="10" font-weight="bold">' + Math.round(winds[i]) + '</text>';
        var dayLabel = getDayName(daily.time[i]);
        svg += '<text x="' + pts[i].x + '" y="' + (pad.top + cH + 18) + '" text-anchor="middle" fill="' + textColor + '" font-size="10">' + escHtml(dayLabel) + '</text>';
    }
    var legendY = vh - 12;
    svg += '<circle cx="' + (vw / 2 - 80) + '" cy="' + legendY + '" r="4" fill="' + lineColor + '"/>';
    svg += '<text x="' + (vw / 2 - 72) + '" y="' + (legendY + 4) + '" fill="' + textColor + '" font-size="10">' + escHtml(unit) + '</text>';
    svg += '<rect x="' + (vw / 2 + 10) + '" y="' + (legendY - 5) + '" width="12" height="10" fill="' + precipColor + '" rx="2"/>';
    svg += '<text x="' + (vw / 2 + 28) + '" y="' + (legendY + 4) + '" fill="' + textColor + '" font-size="10">' + escHtml(precipUnit) + '</text>';
    svg += '</svg>';
    modalContent.innerHTML = '<div style="padding:72px 8px 16px 8px;">' + svg + '</div>';
}
window.toggleWindChart = toggleWindChart;
    function initMobileTopBarRotation() {
        var topBar = document.getElementById('top-bar');
        var title = document.getElementById('top-bar-title');
        var weather = document.getElementById('weatherWidget');
        if (!topBar || !title || !weather) return;
        var mq = window.matchMedia('(max-width: 600px)');
        var timer = null;
        function stop() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
            topBar.classList.remove('mobile-info-show-weather');
        }
        function sync() {
            if (!mq.matches) {
                stop();
                return;
            }
            if (timer) return;
            topBar.classList.remove('mobile-info-show-weather');
            timer = setInterval(function(){
                topBar.classList.toggle('mobile-info-show-weather');
            }, 5000);
        }
        sync();
        if (typeof mq.addEventListener === 'function') mq.addEventListener('change', sync);
        else if (typeof mq.addListener === 'function') mq.addListener(sync);
    }

    function initWeather() {
        var widgets = document.querySelectorAll('.weather-widget-instance');
        if (widgets.length === 0) return;
        var cacheKey = 'ap_weather_data';
        widgets.forEach(function(w) {
            w.style.cursor = 'pointer';
            w.title = wt('weather_click_forecast', 'Click to see 7-day forecast');
            w.addEventListener('click', function(e) {
                showWeatherModal();
            });
        });
        function clearPermError() {
            widgets.forEach(function(widget) {
                var permEl = widget.querySelector('.weather-perm');
                var iconEl = widget.querySelector('.weather-icon');
                var tempEl = widget.querySelector('.weather-temp');
                var locSepEl = widget.querySelector('.weather-location-sep');
                var locEl = widget.querySelector('.weather-location');
                var windEl = widget.querySelector('.weather-wind');
                var pinEl = widget.querySelector('.weather-pin-icon');
                if(permEl) { permEl.style.display = 'none'; permEl.removeAttribute('title'); }
                if(iconEl) iconEl.style.display = '';
                if(tempEl) tempEl.style.display = '';
                if(locSepEl) locSepEl.style.display = '';
                if(locEl) locEl.style.display = '';
                if(windEl) windEl.style.display = '';
                if(pinEl) pinEl.style.display = '';
            });
        }
        function applyToWidgets(iconHtml, tempStr, locStr, windStr) {
            clearPermError();
            widgets.forEach(function(widget) {
                var iconEl = widget.querySelector('.weather-icon');
                var tempEl = widget.querySelector('.weather-temp');
                var locEl = widget.querySelector('.weather-location');
                var windEl = widget.querySelector('.weather-wind');
                if(iconEl && iconHtml !== null) iconEl.innerHTML = iconHtml;
                if(tempEl && tempStr !== null) tempEl.textContent = tempStr;
                if(locEl && locStr !== null) locEl.textContent = locStr;
                if(windEl && windStr !== null) windEl.textContent = windStr;
            });
        }
        function showPermError(message) {
            widgets.forEach(function(widget) {
                var permEl = widget.querySelector('.weather-perm');
                var iconEl = widget.querySelector('.weather-icon');
                var tempEl = widget.querySelector('.weather-temp');
                var locSepEl = widget.querySelector('.weather-location-sep');
                var locEl = widget.querySelector('.weather-location');
                var windEl = widget.querySelector('.weather-wind');
                var pinEl = widget.querySelector('.weather-pin-icon');
                if(permEl) {
                    permEl.style.display = 'block';
                    permEl.textContent = message;
                    permEl.title = message;
                }
                if(iconEl) iconEl.style.display = 'none';
                if(tempEl) tempEl.style.display = 'none';
                if(locSepEl) locSepEl.style.display = 'none';
                if(locEl) locEl.style.display = 'none';
                if(windEl) windEl.style.display = 'none';
                if(pinEl) pinEl.style.display = 'none';
            });
        }
        function saveToCache(temp, code, wind, city, lat, lng, daily) {
            try {
                localStorage.setItem(cacheKey, JSON.stringify({
                    ts: Date.now(),
                    temp: temp,
                    code: code,
                    wind: wind,
                    city: city || '',
                    lat: lat,
                    lon: lng,
                    daily: daily || null
                }));
            } catch(e) {}
        }
        function fetchWeather(lat, lng) {
            fetch(weatherForecastUrl(lat, lng))
                .then(function(r){ return r.json(); })
                .then(function(data) {
                    var c = data.current;
                    if (!c) return;
                    var temp = Math.round(c.temperature_2m);
                    var code = c.weather_code;
                    var wind = Math.round(c.wind_speed_10m);
                    if (data.daily) weatherStore.daily = data.daily;
                    weatherStore.current = c;
                    applyToWidgets(weatherSvg(code), temp + '\u00b0C', null, wind + ' km/h');
                    var locStr = '';
                    var sampleLoc = widgets[0].querySelector('.weather-location');
                    if(sampleLoc) locStr = sampleLoc.textContent;
                    saveToCache(temp, code, wind, locStr, lat, lng, data.daily || null);
                })
                .catch(function() {
                    widgets.forEach(function(w) { w.style.display = 'none'; });
                });
        }
        function fetchCity(lat, lng) {
            var locale = document.documentElement.lang || 'en';
            fetch('https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=' + lat + '&longitude=' + lng + '&localityLanguage=' + locale)
                .then(function(r){ return r.json(); })
                .then(function(data) {
                    var city = data.city || data.locality || data.principalSubdivision || '';
                    applyToWidgets(null, null, city, null);
                    weatherStore.city = city;
                    try {
                        var cur = JSON.parse(localStorage.getItem(cacheKey));
                        if (cur && cur.ts) {
                            cur.city = city;
                            weatherStore.city = city;
                            localStorage.setItem(cacheKey, JSON.stringify(cur));
                        }
                    } catch(e){}
                })
                .catch(function() {
                    applyToWidgets(null, null, '', null);
                });
        }
        function success(pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            weatherStore.lat = lat;
            weatherStore.lon = lng;
            fetchWeather(lat, lng);
            fetchCity(lat, lng);
            fetch('/project/weather_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    use_current_location: true,
                    selected_country: null,
                    selected_country_code: null,
                    selected_region: null,
                    selected_city: null,
                    lat: null,
                    lon: null
                })
            }).catch(function(){});
        }
        function fallbackFromIp(failMessage) {
            fetch('https://ipwho.is/')
                .then(function(r){ return r.json(); })
                .then(function(data) {
                    if (data && data.success !== false && data.latitude != null && data.longitude != null) {
                        success({ coords: { latitude: data.latitude, longitude: data.longitude } });
                        if (data.city) applyToWidgets(null, null, data.city, null);
                        return;
                    }
                    // Network/IP-lookup failure is not a permission denial: show a
                    // neutral notice instead of the widget "denied" state.
                    notifyWeatherGeoUnavailable(failMessage || wt('weather_timeout', 'Location request timed out'));
                })
                .catch(function() {
                    notifyWeatherGeoUnavailable(failMessage || wt('weather_timeout', 'Location request timed out'));
                });
        }
        function error(err) {
            if (err && err.code === err.PERMISSION_DENIED) {
                showPermError(wt('weather_denied', 'Location access denied. Enable it to see local weather.'));
                return;
            }
            var msg = (err && err.code === err.TIMEOUT)
                ? wt('weather_timeout', 'Location request timed out')
                : wt('weather_no_geo', 'Could not determine location');
            fallbackFromIp(msg);
        }
        function requestGeo() {
            if (!navigator.geolocation) {
                fallbackFromIp(wt('weather_no_geo', 'Geolocation not supported'));
                return;
            }
            navigator.geolocation.getCurrentPosition(success, error, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
        }
        function applyCached(cached) {
            if (cached.lat != null && cached.lon != null) {
                weatherStore.lat = cached.lat;
                weatherStore.lon = cached.lon;
            }
            if (cached.city) weatherStore.city = cached.city;
            if (cached.daily && cached.daily.time) weatherStore.daily = cached.daily;
            weatherStore.current = {
                temperature_2m: cached.temp,
                weather_code: cached.code,
                wind_speed_10m: cached.wind
            };
            applyToWidgets(weatherSvg(cached.code), (cached.temp || '--') + '\u00b0C', cached.city || '', cached.wind != null ? cached.wind + ' km/h' : null);
        }
        window.apWeatherShowPermError = showPermError;
        window.apWeatherRequestGeo = requestGeo;
        window.apWeatherRefresh = function(lat, lng, city, resolveCity) {
            if (lat == null || lng == null) return;
            weatherStore.lat = lat;
            weatherStore.lon = lng;
            if (city !== null) {
                weatherStore.city = city || '';
                applyToWidgets(null, null, weatherStore.city, null);
            }
            fetchWeather(lat, lng);
            if (resolveCity) fetchCity(lat, lng);
        };
        try {
            var cachedBoot = JSON.parse(localStorage.getItem(cacheKey));
            if (cachedBoot && cachedBoot.ts && Date.now() - cachedBoot.ts < 3600000 && cachedBoot.lat != null && cachedBoot.lon != null) {
                applyCached(cachedBoot);
            }
        } catch(e) {}
        fetch('/project/weather_settings.php')
            .then(function(response) { return response.json(); })
            .then(function(settings) {
                if (!settings.use_current_location && settings.selected_city && settings.lat != null && settings.lon != null) {
                    weatherStore.city = settings.selected_city;
                    weatherStore.lat = settings.lat;
                    weatherStore.lon = settings.lon;
                    fetchWeather(weatherStore.lat, weatherStore.lon);
                    applyToWidgets(null, null, settings.selected_city, null);
                    return;
                }
                if (weatherStore.lat != null && weatherStore.lon != null) return;
                getGeoPermissionState().then(function(state) {
                    if (state === 'denied') {
                        showPermError(wt('weather_denied', 'Location access denied. Enable it to see local weather.'));
                        return;
                    }
                    // 'prompt' still triggers the browser permission dialog.
                    requestGeo();
                });
            })
            .catch(function() {
                if (weatherStore.lat != null && weatherStore.lon != null) return;
                getGeoPermissionState().then(function(state) {
                    if (state === 'denied') {
                        showPermError(wt('weather_denied', 'Location access denied. Enable it to see local weather.'));
                        return;
                    }
                    requestGeo();
                });
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){
            initMobileTopBarRotation();
            initWeather();
        });
    } else {
        initMobileTopBarRotation();
        initWeather();
    }
})();
</script>
