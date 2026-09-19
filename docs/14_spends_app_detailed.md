# Spends (Expense Tracker) Application - Detailed Documentation

**Path:** `apps/spends/`  
**Type:** Full Application  
**Description:** Personal finance tracker with categories and reports.

---

## Overview

Spends is a personal finance tracker that allows users to log expenses, categorize spending, and track financial habits over time.

---

## File Structure

```
apps/spends/
├── index.php                     # Main entry point
├── controllers/                  # MVC controllers
│   ├── SpendController.php
│   └── CategoryController.php
├── core/                         # Core models, services, helpers
│   ├── SpendModel.php
│   ├── CategoryModel.php
│   └── Database.php
├── data/                         # User data files
├── backups/                      # Automated backups
└── default_profile.jpg           # Default profile image
```

## Key Features

### Expense Logging
- Add expenses with amount, category, date, notes
- Recurring expenses (daily/weekly/monthly)
- Bulk entry
- Split transactions
- Attach receipts

### Categories
- Pre-defined categories (food, transport, entertainment, etc.)
- Custom categories and subcategories
- Category budgets and limits
- Spending trend analysis

### Reports & Analytics
- Daily/weekly/monthly spending summaries
- Category breakdown charts
- Comparison to previous periods
- Budget vs actual spending
- Export reports (CSV, PDF)

### Data Storage
Per-user SQLite databases:

```
apps/spends/data/
├── user_{id}_expenses.db
├── user_{id}_categories.db
└── user_{id}_settings.db
```

### Security Features
- Per-user SQLite database isolation
- Read-only backups
- Input validation and sanitization

## API Endpoints

### Expenses
- `GET /api/spends` - List expenses (with filters)
- `POST /api/spends` - Add new expense
- `PUT /api/spends/{id}` - Update expense
- `DELETE /api/spends/{id}` - Delete expense
- `GET /api/spends/summary` - Get spending summary

### Categories
- `GET /api/categories` - List categories
- `POST /api/categories` - Create category

## Internationalization

Supports all 6 interface languages (en, uk, ru, be, pl, cs).

## Deployment Notes

- SQLite write permissions for `data/` directory
- Backup directory `backups/` needs sufficient space
