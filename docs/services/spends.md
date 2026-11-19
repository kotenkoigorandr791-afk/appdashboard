# Spends — Expense Tracking

**Web UI**: `/apps/spends/` **API Base**: `/public-api/v1/spends` **Auth**: Bearer + `spends` app access

---

## User Functions

| Feature | Description |
|---------|-------------|
| **Expense Entry** | Quick add: amount, category, date, account, note, tags, receipt photo |
| **Categories** | Hierarchical (parent → sub); custom icons, colors, monthly budgets |
| **Accounts/Wallets** | Cash, credit cards, bank accounts, crypto, custom; balance tracking |
| **Recurring Expenses** | Subscriptions, bills, rent; flexible schedules (daily/weekly/monthly/custom cron) |
| **Multi-Currency** | 160+ currencies; auto-exchange rates (ECB, OpenExchange); per-expense currency |
| **Reports & Charts** | By category, account, tag, time; pie, bar, line, Sankey; export CSV/PDF |
| **Sharing** | Household/family sharing; per-member visibility; shared budgets |
| **Receipts** | Photo upload (camera/gallery); OCR for amount/merchant/date (optional) |
| **Templates** | Save frequent expenses as templates for one-tap entry |
| **Import/Export** | CSV, JSON, QIF, OFX; bank statement import (MT940, CAMT.053) |

---

## API Endpoints

### Expenses

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/expenses` | List expenses. Query: `date_from`, `date_to`, `category_id`, `account_id`, `tag`, `search`, `min_amount`, `max_amount`, `currency`, `recurring_id`, `cursor`, `limit`, `sort`, `order` |
| `POST` | `/expenses` | Create expense. Body: `amount`, `currency`, `date`, `category_id`, `account_id`, `note?`, `tags[]?`, `receipt_file?`, `is_recurring_instance?` |
| `GET` | `/expenses/{id}` | Full expense with category, account, receipt URLs |
| `PATCH` | `/expenses/{id}` | Update expense |
| `DELETE` | `/expenses/{id}` | Delete expense |
| `POST` | `/expenses/bulk` | Bulk create. Body: `expenses[]` |
| `POST` | `/expenses/{id}/duplicate` | Duplicate as new entry (today's date) |
| `GET` | `/expenses/recurring/instances` | Upcoming recurring instances. Query: `days_ahead` |

### Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/categories` | List categories (tree or flat). Query: `parent_id`, `include_budget` |
| `POST` | `/categories` | Create category. Body: `name`, `icon`, `color`, `parent_id?`, `budget_monthly?`, `budget_currency?` |
| `GET` | `/categories/{id}` | Category with subcategories & budget status |
| `PATCH` | `/categories/{id}` | Update category |
| `DELETE` | `/categories/{id}` | Delete (moves children to parent or root) |
| `GET` | `/categories/{id}/budget` | Budget vs actual for period. Query: `month` (YYYY-MM) |

### Accounts

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/accounts` | List accounts with current balance |
| `POST` | `/accounts` | Create account. Body: `name`, `type` (cash/credit/bank/crypto/custom), `currency`, `initial_balance`, `icon?`, `color?`, `include_in_total?` |
| `GET` | `/accounts/{id}` | Account details + balance history |
| `PATCH` | `/accounts/{id}` | Update account |
| `DELETE` | `/accounts/{id}` | Delete (requires zero balance or transfer) |
| `POST` | `/accounts/{id}/adjust` | Manual balance adjustment. Body: `amount`, `date`, `note` |
| `GET` | `/accounts/{id}/balance-history` | Daily balances. Query: `date_from`, `date_to` |

### Recurring Expenses

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/recurring` | List recurring rules |
| `POST` | `/recurring` | Create rule. Body: `name`, `amount`, `currency`, `category_id`, `account_id`, `schedule` (cron or preset), `start_date`, `end_date?`, `tags[]?`, `note?` |
| `GET` | `/recurring/{id}` | Rule details + next occurrences |
| `PATCH` | `/recurring/{id}` | Update rule |
| `DELETE` | `/recurring/{id}` | Delete rule (keeps past instances) |
| `POST` | `/recurring/{id}/skip` | Skip next occurrence |
| `POST` | `/recurring/{id}/generate` | Manually generate next instance |

### Reports

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/reports/summary` | Totals by period. Query: `period` (day/week/month/year/custom), `date`, `group_by` (category/account/tag/currency) |
| `GET` | `/reports/by-category` | Category breakdown. Query: `date_from`, `date_to`, `account_id`, `include_subcategories` |
| `GET` | `/reports/by-account` | Account inflow/outflow. Query: `date_from`, `date_to` |
| `GET` | `/reports/by-tag` | Tag breakdown |
| `GET` | `/reports/cashflow` | Net flow over time. Query: `interval` (day/week/month), `date_from`, `date_to` |
| `GET` | `/reports/budget` | Budget vs actual (all categories). Query: `month` |
| `GET` | `/reports/net-worth` | Assets - liabilities over time. Query: `interval`, `date_from`, `date_to` |

### Exchange Rates

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/exchange-rates` | Current rates. Query: `base` (default: user currency), `symbols` |
| `POST` | `/exchange-rates/refresh` | Force update from provider |
| `GET` | `/exchange-rates/history` | Historical rate. Query: `base`, `symbol`, `date` |

### Sharing (Household)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/shares` | List shared households |
| `POST` | `/shares` | Invite user. Body: `email`, `role` (viewer/editor/admin) |
| `PATCH` | `/shares/{id}` | Update role / revoke |
| `DELETE` | `/shares/{id}` | Remove member |

### Export / Import

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/export` | Export data. Query: `format` (csv/json/qif/ofx), `date_from`, `date_to`, `account_id`, `category_id` |
| `POST` | `/import` | Import file. Multipart: `file`, `format`, `account_id?`, `skip_duplicates?`, `dry_run?` |
| `POST` | `/import/preview` | Preview import (dry run). Returns parsed rows + detected issues |

---

## Web Routes

| Path | Description |
|------|-------------|
| `/apps/spends/` | Dashboard: quick add, charts, recent expenses, budget bars, account balances |

---

## MCP Tools (AI Assistant)

| Tool | Description |
|------|-------------|
| `list_expenses` | List expenses with filters |
| `get_expenses` | Get expense details |
| `get_categories` | List categories with budgets |

---

## Recurring Schedule Presets

| Preset | Cron Equivalent |
|--------|-----------------|
| `daily` | `0 0 * * *` |
| `weekly` | `0 0 * * 1` (Mondays) |
| `biweekly` | `0 0 * * 1/2` |
| `monthly` | `0 0 1 * *` (1st) |
| `quarterly` | `0 0 1 */3 *` |
| `yearly` | `0 0 1 1 *` |
| `custom` | User-defined cron |

---

## Currency & Exchange Rates

- **Base currency**: User's primary currency (from profile)
- **Providers**: ECB (free, daily), OpenExchangeRates (free tier), Fixer.io, manual
- **Auto-update**: Daily at 00:00 UTC (configurable)
- **Per-expense currency**: Stored in original currency + converted amount in base currency
- **Historical rates**: Used for past expenses (rate on expense date)

---

## Receipt OCR (Optional)

Enable in Admin → Configuration → Spends → **OCR Provider**:

| Provider | Cost | Accuracy | Setup |
|----------|------|----------|-------|
| Tesseract (local) | Free | Medium | Built-in |
| Google Cloud Vision | Pay-per-use | High | API key |
| AWS Textract | Pay-per-use | High | AWS credentials |
| Azure Form Recognizer | Pay-per-use | High | Azure key |

Extracted fields: `amount`, `currency`, `date`, `merchant`, `category_suggestion`

---

## Configuration (Admin)

Admin → Configuration → **Spends**:

| Setting | Description |
|---------|-------------|
| Default currency | User's base currency (ISO 4217) |
| Exchange rate provider | `ecb`, `openexchangerates`, `fixer`, `manual` |
| Exchange rate API key | For paid providers |
| Auto-update rates | Enable + time |
| OCR provider | As above |
| OCR API key | For cloud providers |
| Max receipt size | Default: 10 MB |
| Default budget period | `monthly` (can be weekly/quarterly/yearly) |
| Shared household enabled | Allow users to create shares |

---

## Data Model (Key Fields)

```json
// Expense
{
  "id": 123,
  "amount": 25.50,
  "currency": "EUR",
  "amount_base": 27.80,        // converted to user's base currency
  "exchange_rate": 1.09,
  "date": "2026-01-15",
  "category_id": 5,
  "account_id": 2,
  "note": "Lunch with team",
  "tags": ["work", "food"],
  "receipt_urls": ["https://.../receipt_abc.jpg"],
  "recurring_id": null,
  "is_recurring_instance": false,
  "created_at": "2026-01-15T13:30:00Z"
}

// Category
{
  "id": 5,
  "name": "Food",
  "icon": "utensils",
  "color": "#e74c3c",
  "parent_id": null,
  "budget_monthly": 500,
  "budget_currency": "EUR"
}
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Exchange rates not updating | Check provider API key & quota; `POST /exchange-rates/refresh`; check logs |
| Recurring expense not generated | Verify cron schedule; check `start_date` not in future; run `POST /recurring/{id}/generate` manually |
| Budget not showing | Ensure category has `budget_monthly` set; check `budget_currency` matches |
| CSV import fails | Use UTF-8 encoding; check date format (YYYY-MM-DD); required columns: `date`, `amount`, `category` |
| Receipt OCR not working | Verify provider credentials; check file size < limit; try Tesseract local first |
| Shared expenses not visible | Check share role (`viewer` sees all, `editor` can add); verify user accepted invite |
| Balance incorrect | Check `include_in_total` on accounts; run `POST /accounts/{id}/adjust` to correct |
| Multi-currency reports wrong | Ensure exchange rates exist for all expense dates; check `amount_base` field |