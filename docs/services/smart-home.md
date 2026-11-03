# Smart Home — IoT & Automation

**Web UI**: `/apps/smart_home/` **API Base**: `/public-api/v1/smart_home` **Auth**: Bearer + `smart_home` app access

---

## User Functions

| Feature | Description |
|---------|-------------|
| **Device Dashboard** | Unified control for lights, switches, sensors, climate, covers, media players, locks, vacuums |
| **Real-Time Updates** | WebSocket pushes state changes instantly (no polling) |
| **Room/Area Grouping** | Organize devices by room, floor, zone; filter dashboard |
| **Scenes** | Save/restore device states (e.g., "Movie Night", "Good Morning"); one-tap activate |
| **Automations** | Trigger → Condition → Action rules (time, state, sensor, webhook) |
| **MQTT Integration** | Connect external MQTT brokers; auto-discover devices (Home Assistant, Tasmota, ESPHome) |
| **Device Templates** | Pre-configured capability sets for common device types |
| **Load Testing** | Simulate device traffic for performance validation |
| **Backup/Restore** | Export/import device configs, scenes, automations |

---

## Device Capabilities (Standardized)

| Capability | Commands | State Attributes |
|------------|----------|------------------|
| `switch` | `turn_on`, `turn_off`, `toggle` | `on: boolean` |
| `light` | + `set_brightness`, `set_color`, `set_color_temp` | `brightness: 0-100`, `color: {h,s}`, `color_temp: kelvin` |
| `climate` | `set_mode` (heat/cool/auto/off), `set_target_temp`, `set_fan_mode` | `mode`, `target_temp`, `current_temp`, `humidity`, `fan_mode` |
| `cover` | `open`, `close`, `stop`, `set_position` | `position: 0-100`, `tilt: 0-100` |
| `sensor` | — | `value`, `unit`, `device_class` (temperature, humidity, motion, battery, etc.) |
| `media_player` | `play`, `pause`, `stop`, `volume_set`, `volume_up`, `volume_down`, `media_next`, `media_prev`, `select_source` | `state`, `volume`, `source`, `media_title`, `media_artist` |
| `lock` | `lock`, `unlock`, `open` | `locked: boolean`, `jammed: boolean` |
| `vacuum` | `start`, `pause`, `return_to_base`, `set_fan_speed` | `status`, `battery`, `fan_speed` |
| `camera` | `stream` (RTSP/WebRTC) | `stream_url`, `motion_detected` |

---

## API Endpoints

### Devices

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/devices` | List all devices. Query: `room_id`, `type`, `capability`, `online` |
| `GET` | `/devices/{id}` | Full device: capabilities, state, attributes, room, MQTT topics |
| `POST` | `/devices/{id}/action` | Execute command. Body: `capability`, `command`, `params` |
| `POST` | `/devices/batch-action` | Multiple devices. Body: `actions: [{device_id, capability, command, params}]` |
| `GET` | `/devices/{id}/history` | State history. Query: `from`, `to`, `capability`, `limit` |
| `POST` | `/devices` | Add manual device. Body: `name`, `type`, `room_id`, `capabilities[]`, `config` |
| `PATCH` | `/devices/{id}` | Update device: `name`, `room_id`, `enabled`, `config` |
| `DELETE` | `/devices/{id}` | Remove device |

### Rooms / Areas

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/rooms` | List rooms with device counts |
| `POST` | `/rooms` | Create room. Body: `name`, `icon?`, `floor?`, `parent_id?` (nested) |
| `PATCH` | `/rooms/{id}` | Update room |
| `DELETE` | `/rooms/{id}` | Delete room (devices → unassigned) |

### Scenes

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/scenes` | List scenes |
| `POST` | `/scenes` | Create scene. Body: `name`, `icon?`, `device_states: [{device_id, capability, state}]` |
| `GET` | `/scenes/{id}` | Scene details |
| `POST` | `/scenes/{id}/activate` | Activate scene (sends commands to all devices) |
| `PATCH` | `/scenes/{id}` | Update scene |
| `DELETE` | `/scenes/{id}` | Delete scene |

### Automations

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/automations` | List automations |
| `POST` | `/automations` | Create automation. Body: `name`, `enabled`, `trigger`, `conditions[]`, `actions[]` |
| `GET` | `/automations/{id}` | Automation details |
| `PATCH` | `/automations/{id}` | Update (incl. `enabled`) |
| `DELETE` | `/automations/{id}` | Delete |
| `POST` | `/automations/{id}/test` | Test-run automation (dry-run) |

**Trigger types**: `time` (cron), `state_change` (device), `mqtt_message`, `webhook`, `sunrise/sunset`
**Conditions**: `device_state`, `time_range`, `expression` (JMESPath)
**Actions**: `device_action`, `scene_activate`, `mqtt_publish`, `webhook`, `notify`

### MQTT

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/mqtt/status` | Broker connection status, client ID, subscribed topics |
| `POST` | `/mqtt/publish` | Publish message. Body: `topic`, `payload`, `qos`, `retain` |
| `GET` | `/mqtt/topics` | List subscribed topics with last message |
| `POST` | `/mqtt/subscribe` | Subscribe to topic. Body: `topic`, `qos` |
| `DELETE` | `/mqtt/subscribe/{topic}` | Unsubscribe |
| `POST` | `/mqtt/discover` | Trigger Home Assistant / Tasmota / ESPHome discovery |

### WebSocket (Real-Time)

```
wss://your-domain.com/public-api/v1/smart_home/ws?token=<access_token>
```

**Messages**:

```json
// Server → Client: device state changed
{ "type": "device_update", "device_id": 42, "capability": "light", "state": { "on": true, "brightness": 80 } }

// Server → Client: device added/removed
{ "type": "device_added", "device": { ... } }
{ "type": "device_removed", "device_id": 42 }

// Server → Client: scene activated
{ "type": "scene_activated", "scene_id": 5 }

// Client → Server: ping (every 30s)
{ "type": "ping" }

// Client → Server: subscribe to specific devices (optional filter)
{ "type": "subscribe", "device_ids": [1,2,3] }
```

**Reconnection**: Exponential backoff 1s→2s→4s→8s→max 30s. Resend `subscribe` after reconnect.

### Load Testing

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/load-test` | Run load test. Query: `device_count`, `messages_per_sec`, `duration_sec` |
| `GET` | `/load-test/results` | Last test results: latency percentiles, throughput, errors |

---

## Web Routes

| Path | Description |
|------|-------------|
| `/apps/smart_home/` | Dashboard: room grid, device cards, scene buttons, automation list |

---

## MCP Tools (AI Assistant)

| Tool | Description |
|------|-------------|
| `list_devices` | List devices with optional room/type filter |
| `control_device` | Send command to device |
| `list_scenes` | List scenes |
| `activate_scene` | Activate scene by name/ID |
| `get_device_state` | Current state of single device |

---

## MQTT Integration Details

### Supported Auto-Discovery

| Protocol | Discovery Topic | Device Types |
|----------|-----------------|--------------|
| Home Assistant | `homeassistant/{component}/{device_id}/config` | All |
| Tasmota | `tasmota/discovery/{device_id}/config` | Switch, Light, Sensor |
| ESPHome | `esphome/{device_id}/config` | All |

### Topic Conventions (Manual Devices)

```
# State (device → broker)
smart_home/{device_id}/{capability}/state

# Command (broker → device)
smart_home/{device_id}/{capability}/set

# Example: light brightness
smart_home/living_room_lamp/light/state    → {"on":true,"brightness":80}
smart_home/living_room_lamp/light/set      ← {"brightness":50}
```

### Bridge Mode

Connect external broker (Mosquitto, EMQX):

1. Admin → Configuration → Smart Home → **MQTT Bridge**
2. Enter broker host, port, username, password
3. Enable bridge → topics synced bidirectionally

---

## Configuration (Admin)

Admin → Configuration → **Smart Home**:

| Setting | Description |
|---------|-------------|
| Default MQTT broker | Built-in (Mosquitto in Docker) or external |
| MQTT bridge | Enable + remote broker details |
| Device timeout | Mark offline after N seconds no update (default: 300) |
| History retention | Days to keep state history (default: 30) |
| WebSocket heartbeat | Ping interval seconds (default: 30) |
| Max concurrent WebSocket connections | Per user (default: 5) |
| Automation engine | `builtin` (PHP) or `external` (Node-RED webhook) |

---

## Automation Examples

**Motion Light (Auto-off after 5 min)**:
```json
{
  "name": "Hallway Motion Light",
  "trigger": { "type": "state_change", "device_id": 12, "capability": "sensor", "attribute": "motion", "to": true },
  "conditions": [{ "type": "time_range", "from": "22:00", "to": "06:00" }],
  "actions": [
    { "type": "device_action", "device_id": 5, "capability": "light", "command": "turn_on", "params": {"brightness": 30} },
    { "type": "delay", "seconds": 300 },
    { "type": "device_action", "device_id": 5, "capability": "light", "command": "turn_off" }
  ]
}
```

**Sunset Scene**:
```json
{
  "name": "Evening Lights",
  "trigger": { "type": "sunset", "offset_minutes": -30 },
  "actions": [
    { "type": "scene_activate", "scene_id": 3 }
  ]
}
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Device shows offline | Check MQTT broker logs; verify device publishes to `state` topic; increase timeout |
| WebSocket disconnects | Check nginx proxy `proxy_read_timeout` > 300s; ensure `proxy_http_version 1.1` |
| Automation not firing | Check trigger conditions; test with `/automations/{id}/test`; view logs in Admin → Audit |
| MQTT bridge not syncing | Verify broker allows anonymous/bridge client; check topic prefixes match |
| State not updating in UI | Open browser devtools → Network → WS → check `device_update` messages; hard refresh |
| Load test fails | Reduce `device_count` or `messages_per_sec`; check CPU/memory in `docker stats` |
| Home Assistant devices not discovered | Ensure `homeassistant` discovery prefix matches; restart HA MQTT integration |