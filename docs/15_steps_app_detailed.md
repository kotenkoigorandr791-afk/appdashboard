# Steps Application - Detailed Documentation

**Path:** `apps/steps/`  
**Total Lines of Code:** ~3,400  
**Main Entry:** `index.php` (3,317 lines)

---

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Core Components](#core-components)
4. [Database Schema](#database-schema)
5. [Features](#features)
6. [API Reference](#api-reference)
7. [Frontend Architecture](#frontend-architecture)
8. [OCR Integration](#ocr-integration)

---

## Overview

The Steps application is an **activity and steps tracking** system with goal setting, distance calculation, calorie tracking, and OCR support for screenshot processing.

### Key Statistics

| Metric | Value |
|--------|-------|
| Total PHP Files | 3 |
| Total JSON Files | 5+ |
| Languages Supported | 4 (EN, UK, RU, BE) |
| Main File Size | 3,317 lines |
| OCR Support | Tesseract |

---

## File Structure

```
apps/steps/
├── index.php                          # Main activity tracking interface (3,317 lines)
├── name.json                          # App name translations
├── translations.json                  # UI translations
├── icon.png                           # Application icon
├── default_profile.jpg                # Default profile image
├── Thumbs.db                          # Windows thumbnail cache
├── php_errors.log                     # PHP error log
│
├── Icons
│   ├── steps.png                      # Steps counter icon
│   ├── distance.png                   # Distance tracking icon
│   ├── goal.png                       # Goal setting icon
│   └── kkal.png                       # Calories tracking icon
│
├── upload_sessions/                   # Upload session tracking
│   └── {session_id}.json              # Session metadata
│
└── utils/                             # Utility functions including OCR
    └── bin/                           # Tesseract OCR binaries
        ├── tesseract.exe              # Tesseract OCR executable
        └── tessdata/                  # Tesseract language data
```

---

## Core Components

### 1. Main Interface (`index.php`)

**Purpose:** Steps tracking dashboard with calendar, charts, and goal management.

**Key Functions:**

```php
// Database connection (shared)
function getDB(): ?SQLite3 {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_FILE);
        $db->enableExceptions(true);
        $db->busyTimeout(3000);
    }
    return $db;
}

// User authentication
function get_current_user_from_sessions(): string
function getUserRoleByEmail(string $email): int

// Per-user database
function getUserDB(string $userDir): ?SQLite3 {
    static $userDb = null;
    if ($userDb !== null) return $userDb;
    
    $dbPath = "$userDir/user_data.sqlite3";
    try {
        $userDb = new SQLite3($dbPath);
        $userDb->enableExceptions(true);
        $userDb->busyTimeout(3000);
        $userDb->exec('PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;');
        
        // Create steps table
        $userDb->exec('
            CREATE TABLE IF NOT EXISTS steps (
                date  TEXT PRIMARY KEY,
                steps INTEGER NOT NULL DEFAULT 0
            )
        ');
        $userDb->exec('CREATE INDEX IF NOT EXISTS idx_steps_date ON steps(date)');
        $userDb->exec('CREATE INDEX IF NOT EXISTS idx_steps_steps ON steps(steps)');
        
        // Create settings table
        $userDb->exec('
            CREATE TABLE IF NOT EXISTS settings (
                key   TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )
        ');
        
        return $userDb;
    } catch (Exception $e) {
        error_log("User DB init failed: " . $e->getMessage());
        return null;
    }
}

// Date utilities
function csvDateToISO(string $raw): ?string {
    // dd-mm-yy → YYYY-MM-DD
    $parts = explode('-', trim($raw));
    if (count($parts) !== 3) return null;
    [$d, $m, $y] = $parts;
    if (!is_numeric($d) || !is_numeric($m) || !is_numeric($y)) return null;
    $fullYear = (int)$y < 70 ? "20$y" : "19$y";
    return "$fullYear-$m-$d";
}

function isoToCSVDate(string $iso): string {
    // YYYY-MM-DD → dd-mm-yy
    return (new DateTime($iso))->format('d-m-y');
}

// Settings
function loadUserSettings(SQLite3 $db, array $defaults): array {
    $settings = $defaults;
    try {
        $result = $db->query('SELECT key, value FROM settings');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
    } catch (Exception $e) {
        error_log("Load settings failed: " . $e->getMessage());
    }
    
    $settings['goal_steps']    = (int)($settings['goal_steps']    ?? $defaults['goal_steps']);
    $settings['height']        = (int)($settings['height']        ?? $defaults['height']);
    $settings['weight']        = (int)($settings['weight']        ?? $defaults['weight']);
    $settings['stride_length'] = (int)($settings['stride_length'] ?? $defaults['stride_length']);
    $settings['birth_date']    = $settings['birth_date'] ?? $defaults['birth_date'];
    
    // Calculate age
    try {
        $bd = new DateTime($settings['birth_date']);
        $settings['age'] = (new DateTime())->diff($bd)->y;
    } catch (Exception $e) {
        $settings['age'] = 30;
    }
    
    return $settings;
}

function saveUserSetting(SQLite3 $db, string $key, string $value): void {
    $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:k, :v)');
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $stmt->bindValue(':v', $value, SQLITE3_TEXT);
    $stmt->execute();
}

// Steps operations
function upsertStep(SQLite3 $db, string $date, int $steps): void {
    $stmt = $db->prepare('INSERT OR REPLACE INTO steps (date, steps) VALUES (:d, :s)');
    $stmt->bindValue(':d', $date, SQLITE3_TEXT);
    $stmt->bindValue(':s', $steps, SQLITE3_INTEGER);
    $stmt->execute();
}

function loadStepsRange(SQLite3 $db, string $from, string $to): array {
    $steps = [];
    try {
        $stmt = $db->prepare(
            'SELECT date, steps FROM steps WHERE date >= :from AND date <= :to ORDER BY date ASC'
        );
        $stmt->bindValue(':from', $from, SQLITE3_TEXT);
        $stmt->bindValue(':to',   $to,   SQLITE3_TEXT);
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $steps[$row['date']] = ['date' => $row['date'], 'steps' => (int)$row['steps']];
        }
    } catch (Exception $e) {
        error_log("loadStepsRange failed: " . $e->getMessage());
    }
    return $steps;
}

function getStepsDateBounds(SQLite3 $db): array {
    try {
        $result = $db->query('SELECT MIN(date) as min_date, MAX(date) as max_date FROM steps');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return [
            'min_date' => $row['min_date'] ?? null,
            'max_date' => $row['max_date'] ?? null
        ];
    } catch (Exception $e) {
        return ['min_date' => null, 'max_date' => null];
    }
}

// Calculations
function calculateDistance(int $steps, float $strideLengthCm): float {
    // Distance in km
    return ($steps * $strideLengthCm) / 100000;
}

function calculateCalories(int $steps, float $weightKg, float $strideLengthCm): float {
    // Approximate calories burned walking
    // MET value for walking ≈ 3.5
    // Distance in km
    $distance = calculateDistance($steps, $strideLengthCm);
    // Time in hours (assuming 5 km/h walking speed)
    $timeHours = $distance / 5;
    // Calories = MET × weight × time
    return 3.5 * $weightKg * $timeHours;
}

function calculateGoalProgress(int $steps, int $goalSteps): float {
    return min(100, ($steps / $goalSteps) * 100);
}
```

**URL Parameters:**
```php
$lang   = $_GET['lang']   ?? 'en';
$theme  = $_GET['theme']  ?? 'light';
$color  = $_GET['color']  ?? '#85b503';
```

---

## Database Schema

### Per-User Database (`user_data.sqlite3`)

Each user has their own PostgreSQL database with the following schema:

#### `steps` Table
```sql
CREATE TABLE steps (
    date  TEXT PRIMARY KEY,
    steps INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX idx_steps_date ON steps(date);
CREATE INDEX idx_steps_steps ON steps(steps);

-- Example data:
-- ('2024-01-15', 10000)
-- ('2024-01-16', 8500)
-- ('2024-01-17', 12000)
```

#### `settings` Table
```sql
CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

-- Example data:
-- ('goal_steps', '10000')
-- ('height', '170')
-- ('weight', '70')
-- ('stride_length', '70')
-- ('birth_date', '1994-01-01')
```

---

## Features

### 1. Steps Tracking

**Daily Entry:**
- Manual steps entry
- CSV import from fitness trackers
- JSON import from apps
- Screenshot OCR processing

**Data Display:**
- Daily steps count
- Weekly/monthly totals
- Goal progress percentage
- Historical trends

### 2. Goal Setting

**Goal Types:**
- Daily step goal (default: 10,000)
- Weekly step goal
- Monthly step goal

**Progress Tracking:**
- Visual progress bars
- Goal achievement badges
- Streak counter (consecutive days meeting goal)

### 3. Distance Calculation

**Formula:**
```
Distance (km) = Steps × Stride Length (cm) / 100,000
```

**Stride Length:**
- Auto-calculated from height: `height × 0.415`
- Manual override option
- Different values for walking/running

### 4. Calorie Tracking

**Formula:**
```
Calories = MET × Weight (kg) × Time (hours)
```

Where:
- MET (Metabolic Equivalent) for walking ≈ 3.5
- Time = Distance / Walking Speed (5 km/h average)

### 5. Calendar View

**Features:**
- Monthly calendar display
- Color-coded days (by goal achievement)
- Hover tooltip with details
- Quick navigation

**Color Coding:**
- 🟢 Green: Goal met (≥100%)
- 🟡 Yellow: Partial (50-99%)
- 🔴 Red: Low activity (<50%)
- ⚪ Gray: No data

### 6. Charts & Statistics

**Charts:**
- Daily steps (bar chart)
- Weekly trend (line chart)
- Monthly comparison (bar chart)
- Goal progress (gauge chart)

**Statistics:**
- Average daily steps
- Total steps this month
- Best day record
- Goal achievement rate
- Total distance
- Total calories burned

### 7. Data Import

**Supported Formats:**
- CSV (date, steps)
- JSON (array of records)
- Excel (.xlsx)
- Fitness tracker exports

**CSV Format:**
```csv
date,steps
15-01-24,10000
16-01-24,8500
17-01-24,12000
```

### 8. OCR Processing

**Features:**
- Upload screenshot from fitness app
- Tesseract OCR extracts steps count
- Automatic date detection
- Manual verification before import

---

## API Reference

### Steps Endpoints

#### Get Steps Data
```http
GET /index.php?action=get_steps&start=2024-01-01&end=2024-01-31

Response:
{
    "success": true,
    "steps": [
        {"date": "2024-01-15", "steps": 10000},
        {"date": "2024-01-16", "steps": 8500},
        {"date": "2024-01-17", "steps": 12000}
    ]
}
```

#### Add/Update Steps
```http
POST /index.php?action=save_steps
Content-Type: application/json

{
    "date": "2024-01-15",
    "steps": 10000
}
```

#### Delete Steps
```http
POST /index.php?action=delete_steps
Content-Type: application/json

{
    "date": "2024-01-15"
}
```

---

### Settings Endpoints

#### Get Settings
```http
GET /index.php?action=get_settings

Response:
{
    "success": true,
    "settings": {
        "goal_steps": 10000,
        "height": 170,
        "weight": 70,
        "stride_length": 70,
        "birth_date": "1994-01-01",
        "age": 30
    }
}
```

#### Update Settings
```http
POST /index.php?action=update_settings
Content-Type: application/json

{
    "goal_steps": 12000,
    "weight": 72
}
```

---

### Import Endpoints

#### Import CSV
```http
POST /index.php?action=import_csv
Content-Type: multipart/form-data

file: {binary}
date_format: "dd-mm-yy"

Response:
{
    "success": true,
    "imported_rows": 30,
    "skipped_rows": 2,
    "errors": []
}
```

#### Import JSON
```http
POST /index.php?action=import_json
Content-Type: multipart/form-data

file: {binary}

Response:
{
    "success": true,
    "imported_rows": 30
}
```

#### OCR Processing
```http
POST /index.php?action=process_ocr
Content-Type: multipart/form-data

image: {binary}
language: "en"

Response:
{
    "success": true,
    "extracted_data": {
        "date": "2024-01-15",
        "steps": 10523,
        "confidence": 0.95
    }
}
```

---

## Frontend Architecture

### Main JavaScript Modules

```javascript
// Steps Manager
class StepsManager {
    constructor() {
        this.steps = {};
        this.settings = {};
        this.currentDate = new Date();
        this.chart = null;
    }
    
    async loadSettings() {
        const response = await fetch('?action=get_settings');
        const data = await response.json();
        this.settings = data.settings;
        this.renderSettings();
    }
    
    async loadSteps(startDate, endDate) {
        const response = await fetch(`?action=get_steps&start=${startDate}&end=${endDate}`);
        const data = await response.json();
        
        this.steps = {};
        data.steps.forEach(s => {
            this.steps[s.date] = s.steps;
        });
        
        this.renderCalendar();
        this.renderChart();
        this.renderStats();
    }
    
    renderCalendar() {
        const container = document.getElementById('calendar');
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDay = firstDay.getDay();
        const totalDays = lastDay.getDate();
        
        let html = `
            <div class="calendar-header">
                <button onclick="stepsManager.prevMonth()">◀</button>
                <h3>${firstDay.toLocaleDateString('en', { month: 'long', year: 'numeric' })}</h3>
                <button onclick="stepsManager.nextMonth()">▶</button>
            </div>
            <div class="calendar-grid">
                <div class="weekday">Sun</div>
                <div class="weekday">Mon</div>
                <div class="weekday">Tue</div>
                <div class="weekday">Wed</div>
                <div class="weekday">Thu</div>
                <div class="weekday">Fri</div>
                <div class="weekday">Sat</div>
        `;
        
        // Empty cells for days before first day
        for (let i = 0; i < startDay; i++) {
            html += '<div class="day empty"></div>';
        }
        
        // Day cells
        for (let day = 1; day <= totalDays; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const steps = this.steps[dateStr] || 0;
            const progress = (steps / this.settings.goal_steps) * 100;
            const colorClass = progress >= 100 ? 'achieved' : progress >= 50 ? 'partial' : 'low';
            
            html += `
                <div class="day ${colorClass}" onclick="stepsManager.editDay('${dateStr}')">
                    <div class="day-number">${day}</div>
                    <div class="day-steps">${steps.toLocaleString()}</div>
                    <div class="day-progress">${Math.min(100, progress).toFixed(0)}%</div>
                </div>
            `;
        }
        
        html += '</div>';
        container.innerHTML = html;
    }
    
    renderChart() {
        const ctx = document.getElementById('steps-chart').getContext('2d');
        
        const dates = Object.keys(this.steps).sort();
        const data = dates.map(d => this.steps[d]);
        
        if (this.chart) {
            this.chart.destroy();
        }
        
        this.chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dates.map(d => new Date(d).toLocaleDateString('en', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'Steps',
                    data: data,
                    backgroundColor: data.map(v => v >= this.settings.goal_steps ? '#4a90e2' : '#e0e0e0'),
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => (value / 1000).toFixed(0) + 'K'
                        }
                    }
                }
            }
        });
    }
    
    renderStats() {
        const dates = Object.keys(this.steps);
        const values = dates.map(d => this.steps[d]);
        
        const total = values.reduce((a, b) => a + b, 0);
        const avg = values.length > 0 ? Math.round(total / values.length) : 0;
        const max = Math.max(...values, 0);
        const maxDate = values.indexOf(max) >= 0 ? dates[values.indexOf(max)] : 'N/A';
        const achievedDays = values.filter(v => v >= this.settings.goal_steps).length;
        const achievementRate = dates.length > 0 ? (achievedDays / dates.length * 100).toFixed(1) : 0;
        
        // Calculate distance and calories
        const totalDistance = (total * this.settings.stride_length) / 100000;
        const totalCalories = (totalDistance / 5) * 3.5 * this.settings.weight;
        
        document.getElementById('stat-total').textContent = total.toLocaleString();
        document.getElementById('stat-average').textContent = avg.toLocaleString();
        document.getElementById('stat-max').textContent = `${max.toLocaleString()} (${new Date(maxDate).toLocaleDateString()})`;
        document.getElementById('stat-achievement').textContent = `${achievementRate}%`;
        document.getElementById('stat-distance').textContent = totalDistance.toFixed(1) + ' km';
        document.getElementById('stat-calories').textContent = Math.round(totalCalories) + ' kcal';
    }
    
    async editDay(date) {
        const steps = this.steps[date] || 0;
        const newSteps = prompt(`Enter steps for ${date}:`, steps);
        
        if (newSteps !== null) {
            const parsed = parseInt(newSteps);
            if (!isNaN(parsed) && parsed >= 0) {
                await fetch('?action=save_steps', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ date, steps: parsed })
                });
                
                this.steps[date] = parsed;
                this.renderCalendar();
                this.renderChart();
                this.renderStats();
            }
        }
    }
    
    prevMonth() {
        this.currentDate.setMonth(this.currentDate.getMonth() - 1);
        this.loadStepsForCurrentMonth();
    }
    
    nextMonth() {
        this.currentDate.setMonth(this.currentDate.getMonth() + 1);
        this.loadStepsForCurrentMonth();
    }
    
    loadStepsForCurrentMonth() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        const start = `${year}-${String(month + 1).padStart(2, '0')}-01`;
        const end = new Date(year, month + 1, 0).toISOString().split('T')[0];
        
        this.loadSteps(start, end);
    }
    
    renderSettings() {
        document.getElementById('setting-goal').value = this.settings.goal_steps;
        document.getElementById('setting-height').value = this.settings.height;
        document.getElementById('setting-weight').value = this.settings.weight;
        document.getElementById('setting-stride').value = this.settings.stride_length;
    }
    
    async saveSettings() {
        const settings = {
            goal_steps: parseInt(document.getElementById('setting-goal').value),
            height: parseFloat(document.getElementById('setting-height').value),
            weight: parseFloat(document.getElementById('setting-weight').value),
            stride_length: parseFloat(document.getElementById('setting-stride').value)
        };
        
        await fetch('?action=update_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(settings)
        });
        
        this.settings = { ...this.settings, ...settings };
        this.renderCalendar();
        this.renderChart();
        this.renderStats();
    }
}

// Initialize
const stepsManager = new StepsManager();

document.addEventListener('DOMContentLoaded', () => {
    stepsManager.loadSettings();
    stepsManager.loadStepsForCurrentMonth();
});
```

---

## OCR Integration

### Tesseract OCR Processing

```php
function processScreenshotOCR($imagePath, $language = 'en'): array {
    $tesseractPath = __DIR__ . '/utils/bin/tesseract.exe';
    $tessDataPath = __DIR__ . '/utils/bin/tessdata';
    
    // Set environment
    putenv("TESSDATA_PREFIX={$tessDataPath}");
    
    // Run OCR
    $command = escapeshellcmd($tesseractPath) . 
               ' ' . escapeshellarg($imagePath) . 
               ' stdout' .
               ' --tessdata-dir ' . escapeshellarg($tessDataPath) .
               ' -l ' . escapeshellarg($language);
    
    $output = shell_exec($command);
    
    // Parse output for steps number
    // Look for patterns like "10,523 steps" or "Steps: 10523"
    if (preg_match('/(\d[\d,\s]*)\s*steps/i', $output, $matches)) {
        $steps = (int)str_replace([',', ' '], '', $matches[1]);
        
        // Try to extract date
        $date = extractDateFromOCR($output);
        
        return [
            'success' => true,
            'steps' => $steps,
            'date' => $date ?? date('Y-m-d'),
            'raw_text' => $output,
            'confidence' => 0.9
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Could not extract steps from image',
        'raw_text' => $output
    ];
}

function extractDateFromOCR(string $text): ?string {
    // Try various date formats
    $patterns = [
        '/(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})/',  // DD/MM/YYYY or MM/DD/YYYY
        '/(\d{4})[\/.-](\d{1,2})[\/.-](\d{1,2})/',    // YYYY/MM/DD
        '/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{1,2}),?\s+(\d{4})/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            // Parse and return ISO date
            // Implementation depends on pattern matched
            return date('Y-m-d');
        }
    }
    
    return null;
}
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Calendar not loading | Database empty | Add first steps entry |
| OCR not working | Tesseract not found | Check utils/bin path |
| Chart not displaying | JavaScript error | Check browser console |
| Settings not saving | Database locked | Check WAL files |

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-02  
**Maintained By:** ServerKherson Development Team
