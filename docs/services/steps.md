# Steps — Step Counter & Health Connect Sync

**Web UI**: `/apps/steps/` **API Base**: `/public-api/v1/steps` **Auth**: Bearer + `steps` app access

---

## User Functions

| Feature | Description |
|---------|-------------|
| **Step Recording** | Manual entry + automatic sync from Android Health Connect |
| **Health Connect Sync** | Bidirectional sync with Android Health Connect (steps, distance, calories, active time) |
| **Conflict Resolution** | Detect & resolve conflicts (local vs cloud vs device) with merge/choose strategy |
| **Data Backup/Restore** | Export all data (JSON/CSV); restore from backup file |
| **Statistics & Charts** | Daily/weekly/monthly/yearly views; trends, averages, goal progress |
| **Profile & Goals** | Daily step goal, stride length, weight, height, birth date for calorie calc |
| **Settings** | Units (metric/imperial), auto-sync interval, notification preferences, privacy |
| **Multi-Device** | Multiple Android devices per account; unified timeline |

---

## API Endpoints

### Step Data

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/data` | Get step entries. Query: `date_from`, `date_to`, `aggregation` (raw/hourly/daily/weekly/monthly), `source` (manual/health_connect/merged), `cursor`, `limit` |
| `POST` | `/data` | Add manual entry. Body: `date` (YYYY-MM-DD), `steps`, `distance_m?`, `calories?`, `active_minutes?`, `source: "manual"` |
| `PATCH` | `/data/{id}` | Update entry. Body: any field above |
| `DELETE` | `/data/{id}` | Delete entry |
| `GET` | `/data/summary` | Aggregated summary. Query: `period` (day/week/month/year), `date` (anchor) |

### Health Connect (Android)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/health-connect/status` | Sync status: `connected`, `last_sync`, `pending_count`, `error?` |
| `POST` | `/health-connect/sync` | Trigger manual sync (pull from device) |
| `POST` | `/health-connect/push` | Push local data to Health Connect |
| `GET` | `/health-connect/conflicts` | List unresolved conflicts. Query: `cursor`, `limit` |
| `GET` | `/health-connect/conflicts/{id}` | Conflict details: `local`, `remote`, `merged` proposal |
| `POST` | `/health-connect/conflicts/{id}/resolve` | Resolve. Body: `strategy` (`local`, `remote`, `merge`, `manual`), `manual_data?` |
| `POST` | `/health-connect/conflicts/resolve-all` | Bulk resolve. Body: `default_strategy` |
| `GET` | `/health-connect/permissions` | Required Android permissions status |
| `POST` | `/health-connect/permissions/request` | Generate deep link to request permissions on device |

### Backups

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/backups` | List backups. Query: `cursor`, `limit` |
| `POST` | `/backups` | Create backup. Body: `include_conflicts?`, `date_from?`, `date_to?` |
| `GET` | `/backups/{id}` | Backup metadata: `size`, `record_count`, `date_range`, `checksum` |
| `GET` | `/backups/{id}/download` | Download backup file (JSON) |
| `DELETE` | `/backups/{id}` | Delete backup |
| `POST` | `/backups/restore` | Restore from uploaded file. Multipart: `file`, `strategy` (`merge`, `replace`, `skip_existing`) |
| `POST` | `/backups/restore/{id}` | Restore from existing backup ID |

### Profile & Settings

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/profile` | Get profile: `daily_goal`, `stride_length_cm`, `weight_kg`, `height_cm`, `birth_date`, `sex` |
| `PATCH` | `/profile` | Update profile |
| `GET` | `/settings` | Get settings: `units` (metric/imperial), `auto_sync_interval_min`, `notifications_enabled`, `sync_on_wifi_only`, `privacy_mode` |
| `PATCH` | `/settings` | Update settings |

### Statistics

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/stats/overview` | Current streak, best streak, total steps, avg/day, this week/month vs last |
| `GET` | `/stats/trends` | Time series. Query: `metric` (steps/distance/calories/active_min), `period` (7d/30d/90d/1y/max), `interval` (day/week/month) |
| `GET` | `/stats/heatmap` | GitHub-style contribution heatmap. Query: `year` |
| `GET` | `/stats/goals` | Goal completion rate. Query: `period` |
| `GET` | `/stats/comparison` | Compare periods. Query: `metric`, `period_a`, `period_b` |

---

## Web Routes

| Path | Description |
|------|-------------|
| `/apps/steps/` | Dashboard: today's ring, charts, calendar heatmap, Health Connect status, settings |

---

## MCP Tools (AI Assistant)

| Tool | Description |
|------|-------------|
| `get_steps` | Get step data for date range |
| `get_stats` | Get statistics overview |

---

## Health Connect Integration (Android)

### Setup

1. Install **Health Connect** app (Google Play)
2. In AppDashboard: `/apps/steps/` → **Health Connect** → **Connect**
3. Grant permissions: **Steps**, **Distance**, **Active Calories**, **Exercise Duration**
4. Enable **Auto-sync** (default: every 15 min on Wi-Fi)

### Data Flow

```
Android Device (Health Connect) ←→ AppDashboard Server ←→ Other Devices/Web
                    ↑                    ↑
              Local cache           Conflict detection
              (offline support)       (merge strategies)
```

### Conflict Scenarios

| Scenario | Detection | Default Resolution |
|----------|-----------|-------------------|
| Same day edited on phone + web | Timestamp + source mismatch | `merge` (sum steps, max distance) |
| Phone offline > 24h, then sync | Gap in server data | `remote` (phone wins) |
| Manual entry duplicates HC entry | Same date + source `manual` vs `health_connect` | `skip_existing` on restore |
| Calorie formula differs | Server uses Mifflin-St Jeor; HC uses device algorithm | `server` (authoritative) |

### Merge Strategies

- **`local`**: Keep server version
- **`remote`**: Keep Health Connect version
- **`merge`**: Steps = sum, Distance = max, Calories = server recalc, Active min = max
- **`manual`**: User specifies exact values

---

## Data Model

```json
{
  "id": 123,
  "date": "2026-01-15",
  "steps": 8432,
  "distance_m": 6200,
  "calories": 320,
  "active_minutes": 45,
  "source": "health_connect",  // manual | health_connect | merged
  "device_id": "android-abc123",
  "created_at": "2026-01-15T20:30:00Z",
  "updated_at": "2026-01-15T20:30:00Z",
  "conflict_id": null
}
```

---

## Configuration (Admin)

Admin → Configuration → **Steps**:

| Setting | Description |
|---------|-------------|
| Default daily goal | Steps (default: 10,000) |
| Calorie formula | `mifflin` (default), `harris_benedict`, `katch_mcardle` |
| Max auto-sync interval | Min minutes between auto-syncs (default: 15) |
| Conflict auto-resolve | Default strategy for new conflicts |
| Data retention | Days to keep raw hourly data (default: 365; aggregated kept forever) |
| Export formats | Enable JSON, CSV, GPX |

---

## Android App Integration (Deep Links)

| Action | Deep Link |
|--------|-----------|
| Request permissions | `appdashboard://health-connect/permissions` |
| Trigger sync | `appdashboard://health-connect/sync` |
| View conflicts | `appdashboard://health-connect/conflicts` |
| Open today | `appdashboard://steps/today` |

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Health Connect not connecting | Ensure Health Connect app installed & updated; grant all 4 permissions; check Android 14+ |
| Sync stuck "pending" | Open Health Connect app → AppDashboard → **Sync now**; check server logs for API errors |
| Conflicts not appearing | Run `GET /health-connect/conflicts`; check `last_sync` timestamp; force sync |
| Steps not counting | Verify phone has step counter sensor; check Health Connect → Apps → AppDashboard has **Steps** permission |
| Backup restore duplicates | Use `strategy: "skip_existing"` or `merge`; check `conflict_id` on restored entries |
| Calories seem wrong | Update profile (weight, height, birth date, sex); verify formula in Admin → Config |
| Auto-sync not working | Check `sync_on_wifi_only` setting; verify WorkManager not battery-optimized; check server `auto_sync_interval_min` |
| Data loss after phone reset | Restore from latest backup (`/backups/latest/download`); re-enable Health Connect permissions |