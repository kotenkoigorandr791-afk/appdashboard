# Connect Health Connect (Android) to Steps

Sync step data automatically from your Android phone to AppDashboard.

---

## Prerequisites

- Android 14+ (Health Connect requires API 34+)
- AppDashboard account with `steps` app access
- Internet connection on phone

---

## Step 1: Install Health Connect

1. Open **Google Play Store** on your Android phone
2. Search **"Health Connect"** (by Google)
3. Install → Open → **Get started**
4. Grant **All permissions** when prompted

---

## Step 2: Connect in AppDashboard

### On Phone (Recommended)

1. Open browser on phone → `https://your-domain.com/apps/steps/`
2. Log in
3. Tap **Health Connect** card → **Connect**
4. System dialog: **"Allow AppDashboard to access Health Connect?"** → **Allow**
5. Select data types to share:
   - ✅ **Steps**
   - ✅ **Distance** (walking + running)
   - ✅ **Active calories**
   - ✅ **Exercise duration**
6. Tap **Allow all** → **Done**

### On Desktop (QR Code)

1. Desktop: `https://your-domain.com/apps/steps/` → **Health Connect** → **Show QR Code**
2. Phone: Open camera → Scan QR → Opens Health Connect permission screen
3. Grant permissions as above

---

## Step 3: Verify Sync

1. In AppDashboard Steps app: **Health Connect** card should show:
   - **Status**: Connected ✅
   - **Last sync**: Just now
   - **Device**: Your phone model
2. Walk around for a minute
4. Pull to refresh → Steps should update

---

## Auto-Sync Settings

Default: **Every 15 minutes on Wi-Fi**

Change in AppDashboard → Steps → **Settings**:

| Setting | Options | Recommendation |
|---------|---------|----------------|
| Auto-sync | On/Off | **On** |
| Sync interval | 5 min – 24 hours | **15 min** (balance battery/data) |
| Sync on Wi-Fi only | On/Off | **On** (saves mobile data) |
| Sync on charge only | On/Off | Optional (saves battery) |

---

## Manual Sync

**Anytime**: Open Steps app → Pull down to refresh → Triggers immediate sync

**From Notification** (Android 14+): Long-press AppDashboard notification → **Sync now**

---

## Conflict Resolution

When same day has data on **both** phone and web:

| Strategy | Behavior |
|----------|----------|
| **Merge** (default) | Steps = sum, Distance = max, Calories = recalculated |
| **Phone wins** | Use Health Connect data |
| **Web wins** | Keep manual entries |
| **Manual** | You choose per conflict |

**View conflicts**: Steps app → **Health Connect** → **Conflicts** (badge shows count)

**Resolve**: Tap conflict → Choose strategy → **Apply**

---

## Multiple Devices

Add steps from **multiple phones/tablets**:

1. Repeat Step 1–2 on each device
2. Each device appears in **Health Connect → Devices**
3. Data merges automatically (sum steps, max distance)
4. Disable a device: Toggle off in Devices list

---

## Data Privacy

| Data | Stored Where | Who Sees It |
|------|--------------|-------------|
| Raw steps | Your phone (Health Connect) | You only |
| Synced data | AppDashboard server | You (+ shared household) |
| Conflict logs | AppDashboard server | You + Admin (audit) |

**No data leaves your phone** except what you explicitly sync to your AppDashboard instance.

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| "Health Connect not found" | Install Health Connect app; Android 14+ required |
| "No permissions granted" | Open Health Connect app → Apps → AppDashboard → Enable all 4 toggles |
| Sync stuck "pending" | Open Health Connect app → **Sync now**; check phone internet |
| Steps not increasing | Verify phone has step counter (Settings → Sensors); check Health Connect → Data → Steps shows data |
| Duplicate entries | Conflicts → **Merge** (default); check `source` field in API |
| Battery drain | Increase sync interval; enable "Sync on Wi-Fi only"; disable "Sync on charge only" |
| "Device not certified" | Some custom ROMs fail Play Integrity; try installing Health Connect via Aurora Store |
| Data missing after phone reset | Re-install Health Connect → Re-grant permissions → Sync pulls from cloud backup (if enabled in Health Connect settings) |

---

## API Access (For Scripts)

```bash
# Get step data
curl -H "Authorization: Bearer $TOKEN" \
  "https://your-domain.com/public-api/v1/steps/data?date_from=2026-01-01&date_to=2026-01-31"

# Trigger sync
curl -X POST -H "Authorization: Bearer $TOKEN" \
  https://your-domain.com/public-api/v1/steps/health-connect/sync

# Check conflicts
curl -H "Authorization: Bearer $TOKEN" \
  https://your-domain.com/public-api/v1/steps/health-connect/conflicts
```

---

## Unlink / Reset

1. AppDashboard → Steps → **Health Connect** → **Disconnect**
2. Or: Health Connect app → Apps → AppDashboard → **Remove access**
3. To fully reset: Delete AppDashboard app data + reinstall