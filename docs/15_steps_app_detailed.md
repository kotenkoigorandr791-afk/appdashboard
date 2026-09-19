# Steps (Steps Counter) Application - Detailed Documentation

**Path:** `apps/steps/`  
**Type:** Full Application  
**Description:** Pedometer and activity tracker with webhook support for mobile app sync.

---

## Overview

Steps is an activity tracker that allows users to log daily steps, set goals, and track their walking activity. Includes a mobile companion app with webhook-based sync.

---

## File Structure

```
apps/steps/
├── index.php                     # Main dashboard
├── auth_common.php               # Shared authentication helpers
├── auth_app.php                  # App-specific authentication
├── auth_web.php                  # Web authentication handlers
├── background_dark.jpg           # Dark theme background
├── background_dark_thumb.jpg     # Dark theme thumbnail
├── background_light.jpg          # Light theme background
├── background_light_thumb.jpg    # Light theme thumbnail
├── steps-mobile-app/             # Mobile app source (Flutter/React Native)
└── steps.webp                    # App icon
```

## Key Features

### Step Counting
- Daily step goal tracking
- Historical step data (daily/weekly/monthly)
- Goal completion statistics
- Streak tracking and achievements
- Multi-device sync

### Goals & Challenges
- Set daily/weekly step goals
- Custom challenges and competitions
- Achievement badges
- Leaderboards (optional)

### Webhook Sync
- Webhook endpoint for mobile app: `/public-api/steps`
- `/api/steps` alias for `/public-api/steps`
- Real-time step sync between devices
- Conflict resolution for concurrent submissions

### Data Storage
Per-user SQLite databases:

```
apps/steps/data/
├── user_{id}_steps.db
├── user_{id}_goals.db
└── user_{id}_settings.db
```

### Mobile Integration
- `/api/steps` endpoint for mobile app sync
- Webhook delivery with retry logic
- Device token registration
- Offline step queuing with background sync

## API Endpoints

### Steps (via public-api)
- `GET /public-api/steps` - Get step history
- `POST /public-api/steps` - Submit steps
- `GET /public-api/steps/stats` - Get step statistics
- `POST /public-api/steps/webhook` - Webhook endpoint
- `GET /public-api/steps/goals` - Get goals
- `POST /public-api/steps/goals` - Set goal

### Settings
- `GET /api/steps/settings` - Get user settings
- `PUT /api/steps/settings` - Update settings

## Security Features

- Per-user database isolation
- Webhook signature verification
- Rate limiting on step submissions
- Auth token validation for mobile app

## Performance

- SQLite for fast local storage
- Background sync queue
- Data compression for transmission

## Internationalization

Supports all 6 interface languages (en, uk, ru, be, pl, cs).

## Deployment Notes

- SQLite write permissions for `data/` directory
- Webhook endpoint accessible to mobile devices
- Background task processing for sync
- HTTPS required for webhook delivery
