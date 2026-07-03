# Map Location Sharing API Documentation

## Overview

The Location Sharing API allows you to send your GPS location from any device to the Map application on your server. This is useful for tracking your device's location and viewing it on the map.

The server keeps only the latest 50 location messages per user. When a new report arrives, older entries are deleted automatically.

## Authentication

All API endpoints use token-based authentication. You need to generate a token first from the Map application interface.

### Generating a Token

1. Open the Map application on your server
2. Click the **⚙️ Settings** button in the toolbar
3. Scroll down to the **"Location Sharing"** section
4. Click **"Generate Token"**
5. Copy the generated token (valid for 365 days)

---

## API Endpoints

Base URL: `https://your-server.com/map/api`

### 1. Generate Token

Generates a new authentication token (requires user to be logged in).

**Endpoint:** `POST /map/api/token`

**Authentication:** Session-based (must be logged in)

**Request:**
```http
POST /map/api/token
Content-Type: application/json
```

**Response:**
```json
{
  "success": true,
  "token": "a1b2c3d4e5f6...",
  "expires_at": "2027-04-06 12:00:00"
}
```

**Example:**
```bash
curl -X POST "https://your-server.com/map/api/token" \
  -H "Cookie: PHPSESSID=your_session_id"
```

---

### 2. Get Your Tokens

Retrieves all your active tokens (requires user to be logged in).

**Endpoint:** `GET /map/api/tokens`

**Authentication:** Session-based (must be logged in)

**Request:**
```http
GET /map/api/tokens
```

**Response:**
```json
{
  "success": true,
  "tokens": [
    {
      "token": "a1b2c3d4e5f6...",
      "created_at": "2026-04-06 12:00:00",
      "expires_at": "2027-04-06 12:00:00",
      "is_active": 1
    }
  ]
}
```

**Example:**
```bash
curl -X GET "https://your-server.com/map/api/tokens" \
  -H "Cookie: PHPSESSID=your_session_id"
```

---

### 3. Send Location

Sends your current location to the server.

**Endpoint:** `POST /map/api/location`

**Authentication:** Token-based (via query parameter or POST data)

For telemetry devices, JSON is the recommended format because it lets the server store `device_name`, `device_type`, `speed`, `altitude`, `satellites`, and nested `telemetry` fields separately.

#### Example 3.1: Send Coordinates Only

```bash
curl -X POST "https://your-server.com/map/api/location?token=YOUR_TOKEN" \
  -d "latitude=46.478272" \
  -d "longitude=30.732691"
```

**Response:**
```json
{
  "success": true,
  "id": 123
}
```

#### Example 3.2: Send Coordinates with Accuracy

```bash
curl -X POST "https://your-server.com/map/api/location?token=YOUR_TOKEN" \
  -d "latitude=46.478272" \
  -d "longitude=30.732691" \
  -d "accuracy=15.5"
```

**Response:**
```json
{
  "success": true,
  "id": 124
}
```

#### Example 3.3: Send Coordinates with Message

```bash
curl -X POST "https://your-server.com/map/api/location?token=YOUR_TOKEN" \
  -d "latitude=46.478272" \
  -d "longitude=30.732691" \
  -d "message=I'm at the office"
```

**Response:**
```json
{
  "success": true,
  "id": 125
}
```

#### Example 3.4: Send Coordinates, Message, and Photo

```bash
curl -X POST "https://your-server.com/map/api/location?token=YOUR_TOKEN" \
  -F "latitude=46.478272" \
  -F "longitude=30.732691" \
  -F "message=Check out this view!" \
  -F "photo=@/path/to/your/photo.jpg"
```

**Response:**
```json
{
  "success": true,
  "id": 126,
  "photo_path": "/apps/map/location_photos/photo_123abc.jpg"
}
```

#### Example 3.5: Send Bike Telemetry as JSON

```bash
curl -X POST "https://your-server.com/map/api/location" \
  -H "Content-Type: application/json" \
  -d '{
    "token": "YOUR_TOKEN",
    "device_name": "Bike ESP",
    "device_type": "bike_esp8266",
    "latitude": 46.478272,
    "longitude": 30.732691,
    "speed": 24.7,
    "altitude": 132.4,
    "satellites": 9,
    "message": "Bike status report",
    "telemetry": {
      "ina_voltage": 12.3,
      "height": 128.8,
      "pressure_mmhg": 752.1,
      "bme_temp_c": 19.6,
      "distance_km": 37.4,
      "wifi_ssid": "MyHotspot"
    }
  }'
```

This payload will populate the dedicated fields shown in the web UI:

- `device_name`
- `device_type`
- `speed`
- `altitude`
- `satellites`
- `telemetry` (extra cards in the telemetry section)

---

### 4. Get Location Data

Retrieves location data for a specific token.

**Endpoint:** `GET /map/api/location`

**Authentication:** Token-based

#### Example 4.1: Get Latest Location

```bash
curl -X GET "https://your-server.com/map/api/location?token=YOUR_TOKEN"
```

**Response:**
```json
{
  "success": true,
  "locations": [
    {
      "latitude": 46.478272,
      "longitude": 30.732691,
      "accuracy": 15.5,
      "message": "I'm at the office",
      "device_name": "Bike ESP",
      "device_type": "bike_esp8266",
      "speed": 24.7,
      "altitude": 132.4,
      "satellites": 9,
      "telemetry": {
        "ina_voltage": 12.3,
        "height": 128.8
      },
      "photo_path": "/apps/map/location_photos/photo_123abc.jpg",
      "timestamp": "2026-04-06 14:30:00"
    }
  ]
}
```

#### Example 4.2: Get Last 5 Locations

```bash
curl -X GET "https://your-server.com/map/api/location?token=YOUR_TOKEN&limit=5"
```

**Response:**
```json
{
  "success": true,
  "locations": [
    {
      "latitude": 46.478272,
      "longitude": 30.732691,
      "accuracy": 15.5,
      "message": "Latest location",
      "photo_path": null,
      "timestamp": "2026-04-06 14:30:00"
    },
    {
      "latitude": 46.477123,
      "longitude": 30.731456,
      "accuracy": 20.0,
      "message": "Previous location",
      "photo_path": "/apps/map/location_photos/photo_prev.jpg",
      "timestamp": "2026-04-06 13:00:00"
    }
  ]
}
```

`limit` is capped at `50`, which matches the per-user history retention shown on the site.

---

### 5. Delete Token

Deletes a specific token (requires user to be logged in).

**Endpoint:** `DELETE /map/api/token/{token}`

**Authentication:** Session-based (must be logged in)

**Request:**
```http
DELETE /map/api/token/a1b2c3d4e5f6...
```

**Response:**
```json
{
  "success": true
}
```

**Example:**
```bash
curl -X DELETE "https://your-server.com/map/api/token/YOUR_TOKEN" \
  -H "Cookie: PHPSESSID=your_session_id"
```

---

## Usage Examples

### From Android Phone (using Termux)

```bash
# Install required packages
pkg install curl termux-api

# Get GPS location and send to server
LOCATION=$(termux-location)
LAT=$(echo $LOCATION | grep -o '"latitude":[0-9.]*' | cut -d: -f2)
LON=$(echo $LOCATION | grep -o '"longitude":[0-9.]*' | cut -d: -f2)

curl -X POST "https://your-server.com/map/api/location?token=YOUR_TOKEN" \
  -d "latitude=$LAT" \
  -d "longitude=$LON" \
  -d "message=Location from Android"
```

### From iPhone (using Shortcuts app)

Create a shortcut with these actions:
1. Get Current Location
2. Get details of location (extract latitude and longitude)
3. Make REST call to `https://your-server.com/map/api/location?token=YOUR_TOKEN`
   - Method: POST
   - Body: `latitude={lat}&longitude={lon}&message=From iPhone`

### From Linux Desktop

```bash
#!/bin/bash
# send_location.sh

# Get location from IP geolocation
LOCATION=$(curl -s http://ip-api.com/json)
LAT=$(echo $LOCATION | grep -o '"lat":[0-9.]*' | cut -d: -f2)
LON=$(echo $LOCATION | grep -o '"lon":[0-9.]*' | cut -d: -f2)

# Send to server
curl -X POST "https://your-server.com/map/api/location?token=YOUR_TOKEN" \
  -d "latitude=$LAT" \
  -d "longitude=$LON" \
  -d "message=Location from desktop"

echo "Location sent: $LAT, $LON"
```

Make it executable and run:
```bash
chmod +x send_location.sh
./send_location.sh
```

### From Python Script

```python
import requests
import json

# Your token
TOKEN = "YOUR_TOKEN"

# Location data
location = {
    "latitude": 46.478272,
    "longitude": 30.732691,
    "message": "Sent from Python script"
}

# Send location
response = requests.post(
    f"https://your-server.com/map/api/location?token={TOKEN}",
    data=location
)

print(response.json())

# Or send with photo
with open("photo.jpg", "rb") as f:
    response = requests.post(
        f"https://your-server.com/map/api/location?token={TOKEN}",
        data={
            "latitude": 46.478272,
            "longitude": 30.732691,
            "message": "With photo"
        },
        files={"photo": f}
    )
    print(response.json())
```

### From JavaScript (Browser)

```javascript
// Get browser location and send to server
navigator.geolocation.getCurrentPosition(async (position) => {
    const { latitude, longitude, accuracy } = position.coords;
    
    const response = await fetch('/map/api/location?token=YOUR_TOKEN', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            latitude: latitude,
            longitude: longitude,
            accuracy: accuracy,
            message: 'From browser'
        })
    });
    
    const data = await response.json();
    console.log('Location sent:', data);
});
```

### Automated Script (Cron Job)

Create a script `auto_location.sh`:
```bash
#!/bin/bash
LOCATION=$(curl -s http://ip-api.com/json)
LAT=$(echo $LOCATION | grep -o '"lat":[0-9.]*' | cut -d: -f2)
LON=$(echo $LOCATION | grep -o '"lon":[0-9.]*' | cut -d: -f2)

curl -s -X POST "https://your-server.com/map/api/location?token=YOUR_TOKEN" \
  -d "latitude=$LAT" \
  -d "longitude=$LON" \
  -d "message=Auto location" > /dev/null
```

Add to crontab (runs every hour):
```bash
0 * * * * /path/to/auto_location.sh
```

---

## Error Responses

### Invalid Token
```json
{
  "error": "Invalid or expired token"
}
```
HTTP Status: 401

### Missing Parameters
```json
{
  "error": "Latitude and longitude required"
}
```
HTTP Status: 400

### Authentication Required
```json
{
  "error": "Authentication required"
}
```
HTTP Status: 401

---

## Security Notes

1. **Token Security**: Keep your token secret. If compromised, delete it and generate a new one.
2. **Token Expiry**: Tokens are valid for 365 days by default.
3. **Photo Uploads**: Only JPG, JPEG, PNG, GIF, and WEBP formats are allowed.
4. **Rate Limiting**: No rate limiting is currently implemented.
5. **HTTPS**: Always use HTTPS to encrypt data in transit.

---

## Viewing Locations Data

To view your latest location:

1. Open the Map application
2. Click the **🎯 Locate-Fixed** button in the toolbar
3. You will see:
   - Map with your location marked
   - Latitude and longitude coordinates
   - Accuracy radius
   - Any message you sent
   - Photos (if uploaded)

---

## Troubleshooting

### Location not showing on map
- Verify your token is correct and not expired
- Check that latitude and longitude are valid numbers
- Ensure your server is accessible and the API endpoint is correct

### Photo not uploading
- Check file format (JPG, JPEG, PNG, GIF, WEBP only)
- Verify file size is within upload limits
- Check that `location_photos/` directory has write permissions

### Token generation fails
- Ensure you are logged into the application
- Check browser console for JavaScript errors
- Verify your session is active and not expired

---

## Support

For issues or questions, contact the server administrator.
