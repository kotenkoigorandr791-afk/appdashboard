# Spends Application - Detailed Documentation

**Path:** `apps/spends/`  
**Total Lines of Code:** ~2,700  
**Main Entry:** `index.php` (2,629 lines)

---

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Core Components](#core-components)
4. [Database Schema](#database-schema)
5. [Features](#features)
6. [API Reference](#api-reference)
7. [Frontend Architecture](#frontend-architecture)
8. [Data Migration](#data-migration)

---

## Overview

The Spends application is a **personal finance and expense tracking** system with per-user PostgreSQL databases, Excel import support, and data backups.

### Key Statistics

| Metric | Value |
|--------|-------|
| Total PHP Files | 5+ |
| Total JSON Files | 10+ |
| Languages Supported | 4 (EN, UK, RU, BE) |
| Main File Size | 2,629 lines |
| Database Type | Per-user SQLite |

---

## File Structure

```
apps/spends/
├── index.php                          # Main expense tracking interface (2,629 lines)
├── name.json                          # App name translations
├── translations.json                  # UI translations
├── global_settings.json               # Global application settings
├── icon.png                           # Application icon
├── default_profile.jpg                # Default profile image
├── table_settings.png                 # Table settings visual guide
├── Thumbs.db                          # Windows thumbnail cache
├── php_errors.log                     # PHP error log
├── расходы 2023-2024.xlsx             # Sample Excel import file
│
├── backups/                           # Data backups
│   └── {timestamp}_backup.sqlite3     # Per-user database backups
│
├── upload_sessions/                   # Resumable upload tracking
│   └── {session_id}.json              # Session metadata
│
└── utils/                             # Utility functions
    └── ...                            # Helper scripts
```

---

## Core Components

### 1. Main Interface (`index.php`)

**Purpose:** Expense tracking with table view, charts, and data management.

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

// Per-user database path
function getUserDBPath(string $userEmail): string {
    $safe = preg_replace('/[@.]/', '_', $userEmail);
    $dir = APPS_DATA_DIR . $safe;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir . '/data.sqlite3';
}

// Per-user database connection
function getUserSQLite(string $userEmail): ?SQLite3 {
    static $cache = [];
    if (isset($cache[$userEmail])) return $cache[$userEmail];
    
    $path = getUserDBPath($userEmail);
    $isNew = !file_exists($path);
    
    try {
        $udb = new SQLite3($path);
        $udb->enableExceptions(true);
        $udb->busyTimeout(5000);
        $udb->exec('PRAGMA journal_mode=WAL');
        $udb->exec('PRAGMA foreign_keys=ON');
        
        // Create tables
        $udb->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )
        ");
        
        $udb->exec("
            CREATE TABLE IF NOT EXISTS stores (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                visible INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0
            )
        ");
        
        $udb->exec("
            CREATE TABLE IF NOT EXISTS expenses (
                date TEXT NOT NULL,
                store TEXT NOT NULL,
                amount REAL NOT NULL DEFAULT 0,
                PRIMARY KEY (date, store)
            )
        ");
        
        $udb->exec("
            CREATE TABLE IF NOT EXISTS table_settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )
        ");
        
        $cache[$userEmail] = $udb;
        return $udb;
    } catch (Exception $e) {
        error_log("UserDB open failed: " . $e->getMessage());
        return null;
    }
}

// Expense operations
function addExpense(SQLite3 $db, string $date, string $store, float $amount): bool
function deleteExpense(SQLite3 $db, string $date, string $store): bool
function getExpenses(SQLite3 $db, string $startDate, string $endDate): array
function getTotalsByStore(SQLite3 $db, string $startDate, string $endDate): array
function getMonthlyTotals(SQLite3 $db, int $year, int $month): array

// Store operations
function addStore(SQLite3 $db, string $id, string $name, int $sortOrder): bool
function updateStore(SQLite3 $db, string $id, string $name, bool $visible): bool
function deleteStore(SQLite3 $db, string $id): bool
function getStores(SQLite3 $db): array

// Settings operations
function dbSetSetting(SQLite3 $db, string $key, $value): void
function dbGetSetting(SQLite3 $db, string $key, $default = null)
function dbSetTableSetting(SQLite3 $db, string $key, $value): void
function dbGetTableSetting(SQLite3 $db, string $key, $default = null)
```

**URL Parameters:**
```php
$lang   = $_GET['lang']   ?? 'en';
$theme  = $_GET['theme']  ?? 'light';
$color  = $_GET['color']  ?? '#85b503';
```

---

## Database Schema

### Per-User Database (`data.sqlite3`)

Each user has their own PostgreSQL database with the following schema:

#### `settings` Table
```sql
CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);

-- Example data:
-- ('monthly_budget', '1000')
-- ('currency', 'USD')
```

#### `stores` Table
```sql
CREATE TABLE stores (
    id         TEXT PRIMARY KEY,
    name       TEXT NOT NULL,
    visible    INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0
);

-- Example data:
-- ('store_001', 'Grocery Store', 1, 0)
-- ('store_002', 'Gas Station', 1, 1)
```

#### `expenses` Table
```sql
CREATE TABLE expenses (
    date   TEXT NOT NULL,
    store  TEXT NOT NULL,
    amount REAL NOT NULL DEFAULT 0,
    PRIMARY KEY (date, store)
);

CREATE INDEX idx_expenses_date ON expenses(date);
CREATE INDEX idx_expenses_store ON expenses(store);

-- Example data:
-- ('2024-01-15', 'store_001', 50.25)
-- ('2024-01-15', 'store_002', 30.00)
-- ('2024-01-16', 'store_001', 75.50)
```

#### `table_settings` Table
```sql
CREATE TABLE table_settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);

-- Example data:
-- ('sort_by', 'date_asc')
-- ('rows_per_page', '25')
-- ('start_date', '2024-01-01')
```

---

## Features

### 1. Expense Entry

**Table-Based Entry:**
- Date-based rows
- Store-based columns
- Inline editing
- Auto-save
- Keyboard navigation

**Entry Modes:**
- Daily entry (single date, multiple stores)
- Bulk entry (multiple dates)
- Excel import

### 2. Store Management

**Features:**
- Add/Edit/Delete stores
- Reorder stores (drag & drop)
- Show/Hide stores
- Store color coding
- Store icons (optional)

### 3. Data Visualization

**Charts:**
- Monthly spending by store (pie chart)
- Daily spending trend (line chart)
- Category comparison (bar chart)

**Statistics:**
- Total spent this month
- Average daily spending
- Budget remaining
- Top spending categories

### 4. Excel Import/Export

**Import:**
- Upload Excel file (.xlsx)
- Map columns to stores
- Preview before import
- Duplicate detection

**Export:**
- Export to Excel
- Export to CSV
- Date range selection
- Include/exclude hidden stores

### 5. Data Backup

**Automatic Backups:**
- Daily backup before import
- Backup stored in `backups/` folder
- Timestamped backup files

**Manual Backup:**
- Create backup on demand
- Download backup file
- Restore from backup

### 6. Table Customization

**Settings:**
- Sort order (date asc/desc)
- Rows per page (10, 25, 50, 100)
- Date range filter
- Column visibility
- Number format (decimal places)

---

## API Reference

### Expense Endpoints

#### Get Expenses
```http
GET /index.php?action=get_expenses&start=2024-01-01&end=2024-01-31

Response:
{
    "success": true,
    "expenses": [
        {
            "date": "2024-01-15",
            "store": "store_001",
            "amount": 50.25
        },
        {
            "date": "2024-01-15",
            "store": "store_002",
            "amount": 30.00
        }
    ]
}
```

#### Add Expense
```http
POST /index.php?action=add_expense
Content-Type: application/json

{
    "date": "2024-01-15",
    "store": "store_001",
    "amount": 50.25
}
```

#### Update Expense
```http
POST /index.php?action=update_expense
Content-Type: application/json

{
    "date": "2024-01-15",
    "store": "store_001",
    "amount": 55.00
}
```

#### Delete Expense
```http
POST /index.php?action=delete_expense
Content-Type: application/json

{
    "date": "2024-01-15",
    "store": "store_001"
}
```

---

### Store Endpoints

#### Get Stores
```http
GET /index.php?action=get_stores

Response:
{
    "success": true,
    "stores": [
        {
            "id": "store_001",
            "name": "Grocery Store",
            "visible": true,
            "sort_order": 0
        },
        {
            "id": "store_002",
            "name": "Gas Station",
            "visible": true,
            "sort_order": 1
        }
    ]
}
```

#### Add Store
```http
POST /index.php?action=add_store
Content-Type: application/json

{
    "name": "New Store"
}
```

#### Update Store
```http
POST /index.php?action=update_store
Content-Type: application/json

{
    "id": "store_001",
    "name": "Updated Name",
    "visible": false
}
```

#### Delete Store
```http
POST /index.php?action=delete_store
Content-Type: application/json

{
    "id": "store_001"
}
```

#### Reorder Stores
```http
POST /index.php?action=reorder_stores
Content-Type: application/json

{
    "order": ["store_002", "store_001", "store_003"]
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
        "monthly_budget": 1000,
        "currency": "USD",
        "table_settings": {
            "sort_by": "date_asc",
            "rows_per_page": 25,
            "start_date": "2024-01-01"
        }
    }
}
```

#### Update Settings
```http
POST /index.php?action=update_settings
Content-Type: application/json

{
    "monthly_budget": 1500,
    "currency": "EUR"
}
```

#### Update Table Settings
```http
POST /index.php?action=update_table_settings
Content-Type: application/json

{
    "sort_by": "date_desc",
    "rows_per_page": 50
}
```

---

### Import/Export Endpoints

#### Import Excel
```http
POST /index.php?action=import_excel
Content-Type: multipart/form-data

file: {binary}
mapping: {"A": "store_001", "B": "store_002"}

Response:
{
    "success": true,
    "imported_rows": 50,
    "skipped_rows": 2,
    "errors": []
}
```

#### Export Data
```http
POST /index.php?action=export
Content-Type: application/json

{
    "format": "xlsx",
    "start_date": "2024-01-01",
    "end_date": "2024-01-31",
    "include_hidden": false
}

Response:
{
    "success": true,
    "download_url": "downloads/export_20240115.xlsx"
}
```

#### Create Backup
```http
POST /index.php?action=create_backup

Response:
{
    "success": true,
    "backup_file": "backups/20240115_103000_backup.sqlite3"
}
```

#### Restore Backup
```http
POST /index.php?action=restore_backup
Content-Type: application/json

{
    "backup_file": "backups/20240115_103000_backup.sqlite3"
}
```

---

## Frontend Architecture

### Main JavaScript Modules

```javascript
// Expense Table Manager
class ExpenseTable {
    constructor() {
        this.expenses = [];
        this.stores = [];
        this.startDate = null;
        this.endDate = null;
        this.currentPage = 1;
        this.rowsPerPage = 25;
    }
    
    async loadData(startDate, endDate) {
        this.startDate = startDate;
        this.endDate = endDate;
        
        const [expensesRes, storesRes] = await Promise.all([
            fetch(`?action=get_expenses&start=${startDate}&end=${endDate}`),
            fetch('?action=get_stores')
        ]);
        
        const expensesData = await expensesRes.json();
        const storesData = await storesRes.json();
        
        this.expenses = expensesData.expenses;
        this.stores = storesData.stores;
        
        this.render();
    }
    
    render() {
        this.renderTable();
        this.renderStats();
    }
    
    renderTable() {
        const container = document.getElementById('expense-table');
        const visibleStores = this.stores.filter(s => s.visible);
        
        let html = `
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        ${visibleStores.map(s => `<th>${s.name}</th>`).join('')}
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        // Group expenses by date
        const byDate = {};
        this.expenses.forEach(e => {
            if (!byDate[e.date]) byDate[e.date] = {};
            byDate[e.date][e.store] = e.amount;
        });
        
        // Sort dates
        const dates = Object.keys(byDate).sort();
        
        dates.forEach(date => {
            const dayTotal = visibleStores.reduce((sum, s) => {
                return sum + (byDate[date][s.id] || 0);
            }, 0);
            
            html += `
                <tr data-date="${date}">
                    <td>${this.formatDate(date)}</td>
                    ${visibleStores.map(s => `
                        <td class="expense-cell" data-store="${s.id}">
                            <input type="number" 
                                   value="${byDate[date][s.id] || ''}" 
                                   onchange="expenseTable.updateExpense('${date}', '${s.id}', this.value)"
                                   placeholder="0.00"
                                   step="0.01">
                        </td>
                    `).join('')}
                    <td class="day-total">${dayTotal.toFixed(2)}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
    }
    
    async updateExpense(date, storeId, amount) {
        if (amount === '') {
            await fetch('?action=delete_expense', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ date, store: storeId })
            });
        } else {
            await fetch('?action=add_expense', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    date,
                    store: storeId,
                    amount: parseFloat(amount)
                })
            });
        }
        
        // Reload data
        this.loadData(this.startDate, this.endDate);
    }
    
    renderStats() {
        const total = this.expenses.reduce((sum, e) => sum + e.amount, 0);
        const days = this.getDaysBetween(this.startDate, this.endDate);
        const dailyAvg = total / days;
        
        document.getElementById('total-spent').textContent = total.toFixed(2);
        document.getElementById('daily-average').textContent = dailyAvg.toFixed(2);
        document.getElementById('days-count').textContent = days;
    }
    
    formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString();
    }
    
    getDaysBetween(start, end) {
        const startDate = new Date(start);
        const endDate = new Date(end);
        const diffTime = Math.abs(endDate - startDate);
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    }
}

// Store Manager
class StoreManager {
    constructor() {
        this.stores = [];
    }
    
    async loadStores() {
        const response = await fetch('?action=get_stores');
        const data = await response.json();
        this.stores = data.stores;
        this.render();
    }
    
    render() {
        const container = document.getElementById('stores-list');
        container.innerHTML = this.stores.map((store, index) => `
            <div class="store-item" data-id="${store.id}">
                <span class="drag-handle">☰</span>
                <input type="text" value="${store.name}" 
                       onchange="storeManager.updateStore('${store.id}', this.value)">
                <label>
                    <input type="checkbox" ${store.visible ? 'checked' : ''}
                           onchange="storeManager.toggleVisibility('${store.id}', this.checked)">
                    Visible
                </label>
                <button onclick="storeManager.deleteStore('${store.id}')">Delete</button>
            </div>
        `).join('');
        
        // Enable drag and drop
        this.initDragAndDrop();
    }
    
    async addStore(name) {
        const response = await fetch('?action=add_store', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        });
        
        const data = await response.json();
        if (data.success) {
            this.loadStores();
        }
    }
    
    async updateStore(id, name) {
        await fetch('?action=update_store', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, name })
        });
    }
    
    async toggleVisibility(id, visible) {
        await fetch('?action=update_store', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, visible })
        });
    }
    
    async deleteStore(id) {
        if (confirm('Are you sure? This will delete all expenses for this store.')) {
            await fetch('?action=delete_store', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            this.loadStores();
        }
    }
    
    initDragAndDrop() {
        // Implement drag and drop reordering
    }
}

// Initialize
const expenseTable = new ExpenseTable();
const storeManager = new StoreManager();

document.addEventListener('DOMContentLoaded', () => {
    // Load initial data (current month)
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    const end = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
    
    expenseTable.loadData(start, end);
    storeManager.loadStores();
});
```

---

## Data Migration

### Migration from Files to SQLite

The app supports migration from legacy file-based storage:

```php
function migrateExistingFiles($userEmail, SQLite3 $udb) {
    $safe = preg_replace('/[@.]/', '_', $userEmail);
    $userDir = APPS_DATA_DIR . $safe;
    
    // 1. Migrate user_settings.json → settings table
    $settingsFile = $userDir . '/user_settings.json';
    if (file_exists($settingsFile)) {
        $js = json_decode(file_get_contents($settingsFile), true) ?: [];
        dbSetSetting($udb, 'monthly_budget', $js['monthly_budget'] ?? 100);
        dbSetSetting($udb, 'currency', $js['currency'] ?? 'USD');
        
        if (!empty($js['stores']) && is_array($js['stores'])) {
            $ord = 0;
            foreach ($js['stores'] as $s) {
                if (!is_array($s) || empty($s['name'])) continue;
                $sid = $s['id'] ?? (string)time() . rand(100, 999);
                $vis = isset($s['visible']) ? (int)(bool)$s['visible'] : 1;
                
                $stmt = $udb->prepare('INSERT OR IGNORE INTO stores (id, name, visible, sort_order) VALUES (:id,:n,:v,:o)');
                $stmt->bindValue(':id', $sid);
                $stmt->bindValue(':n', $s['name']);
                $stmt->bindValue(':v', $vis, SQLITE3_INTEGER);
                $stmt->bindValue(':o', $ord++, SQLITE3_INTEGER);
                $stmt->execute();
            }
        }
    }
    
    // 2. Migrate Spends.csv → expenses table
    $csvFile = $userDir . '/Spends.csv';
    if (file_exists($csvFile)) {
        $fh = fopen($csvFile, 'r');
        $headers = fgetcsv($headers);
        
        if ($headers) {
            $csvStores = array_slice($headers, 1); // First column is date
            
            $udb->exec('BEGIN');
            
            while ($row = fgetcsv($fh)) {
                $date = $row[0];
                for ($i = 1; $i < count($row); $i++) {
                    if (!empty($row[$i]) && is_numeric($row[$i])) {
                        $stmt = $udb->prepare('INSERT OR REPLACE INTO expenses (date, store, amount) VALUES (:d, :s, :a)');
                        $stmt->bindValue(':d', $date, SQLITE3_TEXT);
                        $stmt->bindValue(':s', $csvStores[$i - 1], SQLITE3_TEXT);
                        $stmt->bindValue(':a', (float)$row[$i], SQLITE3_FLOAT);
                        $stmt->execute();
                    }
                }
            }
            
            $udb->exec('COMMIT');
        }
        
        fclose($fh);
    }
}
```

---

## Security

### Input Validation

```php
function validateExpense($date, $storeId, $amount): array {
    $errors = [];
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $errors[] = 'Invalid date format';
    }
    
    // Validate amount
    if (!is_numeric($amount) || $amount < 0) {
        $errors[] = 'Invalid amount';
    }
    
    if ($amount > 999999.99) {
        $errors[] = 'Amount too large';
    }
    
    return ['valid' => empty($errors), 'errors' => $errors];
}
```

### SQL Injection Prevention

All database queries use prepared statements:

```php
$stmt = $udb->prepare('INSERT INTO expenses (date, store, amount) VALUES (:d, :s, :a)');
$stmt->bindValue(':d', $date, SQLITE3_TEXT);
$stmt->bindValue(':s', $storeId, SQLITE3_TEXT);
$stmt->bindValue(':a', $amount, SQLITE3_FLOAT);
$stmt->execute();
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Table not loading | Database locked | Check WAL files, restart Apache |
| Excel import fails | Wrong format | Use sample file as template |
| Stores not showing | Visibility off | Check store visibility settings |
| Backup not created | Permission denied | Check `backups/` folder permissions |

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-02  
**Maintained By:** ServerKherson Development Team
