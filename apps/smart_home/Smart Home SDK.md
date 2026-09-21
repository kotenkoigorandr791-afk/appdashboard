# Smart Home SDK

This document describes the device integration contract for the Smart Home service. Use it as the SDK specification for firmware, gateways, and bridge services that need to create devices, keep them synchronized, receive commands, and avoid duplicate or broken registrations.

## Integration Model

Smart Home stores devices in `smarthome_devices` and maps every physical device token through `smarthome_token_index`.

A device is not accepted by sync endpoints until its `device_token` has been registered from the dashboard with `save_device`. After that, the device can synchronize over REST with `device_sync` and, optionally, over WebSocket for real-time commands.

The stable identity is:

| Field | Owner | Purpose |
|---|---|---|
| `device_token` | Device/installer | Secret pairing token entered in dashboard and sent by device |
| `device_id` | Server | Internal record id returned after registration |
| `user_email` | Server session | Owner account that registered the token |
| `controls[].id` | Device definition | Stable command ids used by dashboard and firmware |
| `params[].id` | Device definition | Stable telemetry ids shown in dashboard |

Do not change `device_token`, control ids, or parameter ids after a device is deployed unless the migration is intentional.

## Base Endpoints

REST API base path:

```text
https://<host>/apps/smart_home/?api=<action>
```

WebSocket endpoint:

```text
wss://<host>/smart-home-ws
```

Local WebSocket service defaults:

```text
SMART_HOME_WS_BIND=127.0.0.1
SMART_HOME_WS_PORT=8766
```

## Required Pairing Flow

1. Generate or provision a unique `device_token` on the physical device.
2. In the dashboard, create a device with `save_device` and the same token.
3. The server creates a `smarthome_devices` row and inserts `device_token -> user_email, device_id` into `smarthome_token_index`.
4. The physical device sends `device_sync` or WebSocket `auth` with `device_token`.
5. The server resolves the token, marks the device online, applies the device definition if provided, and returns the authoritative controls map.

If step 2 is skipped, REST sync returns `404 Unknown token` and WebSocket auth returns `Unknown device token`.

## Device Registration

Dashboard request:

```http
POST /apps/smart_home/?api=save_device
Content-Type: application/json
```

```json
{
  "device_token": "device-secret-token",
  "name": "Kitchen Lamp",
  "type": "pending",
  "room": "Kitchen",
  "category": "lighting"
}
```

Creation rules:

| Case | Result |
|---|---|
| `id` is omitted and `device_token` is empty | Request fails: `Device token required` |
| `device_token` is new | Device row is created with an auto-generated `device_id` |
| `device_token` already belongs to the same user | Request succeeds and returns current device lists |
| `device_token` belongs to another user | Request fails: `Token already registered` |
| `type` is omitted | Device is created as `pending` |
| `group_id` is provided and user can manage the group | Device is assigned to that group |

Use `type: "pending"` when the physical device will send its real definition on first sync.

## Device Definition

Devices can send a `definition` during REST `device_sync` or WebSocket `auth` / `state`.

```json
{
  "type": "smart_lamp",
  "icon": "lamp",
  "sections": [
    { "id": "light", "label": "Light" },
    { "id": "status", "label": "Status" }
  ],
  "controls": [
    {
      "id": "power",
      "label": "Power",
      "type": "switch",
      "value": false,
      "primary": true,
      "section": "light"
    },
    {
      "id": "brightness",
      "label": "Brightness",
      "type": "range",
      "value": 60,
      "min": 0,
      "max": 100,
      "step": 1,
      "primary": true,
      "section": "light"
    },
    {
      "id": "mode",
      "label": "Mode",
      "type": "select",
      "value": "warm",
      "options": ["warm", "cool", "night"],
      "primary": false,
      "section": "light"
    }
  ],
  "params": [
    {
      "id": "signal",
      "label": "Wi-Fi RSSI",
      "value": "-63 dBm",
      "section": "status"
    },
    {
      "id": "firmware",
      "label": "Firmware",
      "value": "1.0.0",
      "section": "status"
    },
    {
      "id": "temperature_chart",
      "label": "Temperature trend",
      "type": "chart",
      "unit": "°C",
      "value": [20.1, 20.5, 21.2],
      "section": "status"
    }
  ],
  "files": [
    {
      "id": "config",
      "label": "server-config.json",
      "kind": "config"
    }
  ]
}
```

Definition rules:

| Field | Required | Notes |
|---|---:|---|
| `type` | Recommended | Replaces current device type when provided |
| `icon` | Optional | Applied unless user locked a custom icon |
| `sections` | Optional | Adds `section_label` to matching controls and params |
| `controls` | Optional | Replaces control schema when non-empty |
| `params` | Optional | Replaces parameter schema when non-empty |
| `files` | Optional | Replaces route/config file metadata when non-empty |

Control schema rules:

| Control type | Stored value normalization |
|---|---|
| `switch` | Boolean |
| `range` | Integer |
| `select` | String |
| Other | String |

When a definition omits a control or param `value`, the server preserves the existing value for the same id. This is why ids must remain stable.

## Chart Parameters

A param with `type: "chart"` carries a time-series rendered as a line chart on the dashboard. The value is an array of numeric points; the device decides how many points to send on every update.

```json
{
  "id": "temperature_chart",
  "label": "Last 24h temperature",
  "type": "chart",
  "unit": "°C",
  "value": [20.1, 20.5, 21.2, 19.8, 22.0, 21.5]
}
```

Chart param rules:

| Rule | Behavior |
|---|---|
| `type` | Must be exactly `"chart"` |
| `value` | Array of numbers; count is chosen by the device |
| `unit` | Optional suffix rendered next to the vertical scale |
| Non-numeric points | Filtered out by the server |
| Missing/invalid value | Stored as an empty array, dashboard shows "No data" |
| Scale labels and tick divisions | Computed automatically by the dashboard (nice round step, 1/2/5 × 10ⁿ) |

Accepted value forms in `telemetry`:

```json
{ "temperature_chart": [20.1, 20.5, 21.2] }
```

or a JSON string:

```json
{ "temperature_chart": "[20.1, 20.5, 21.2]" }
```

Chart param values are preserved as arrays through REST `device_sync`, WebSocket `state`, and the MQTT `state` topic. They are excluded from `smarthome_params_history` (scalar param history only).

## REST Synchronization

Device request:

```http
POST /apps/smart_home/?api=device_sync
Content-Type: application/json
```

```json
{
  "device_token": "device-secret-token",
  "name": "Kitchen Lamp",
  "local_url": "http://192.168.1.50",
  "definition": {
    "type": "smart_lamp",
    "icon": "lamp",
    "controls": [
      { "id": "power", "label": "Power", "type": "switch", "value": false, "primary": true }
    ],
    "params": [
      { "id": "signal", "label": "Wi-Fi RSSI", "value": "-63 dBm" }
    ]
  },
  "controls": {
    "power": false
  },
  "telemetry": {
    "signal": "-63 dBm"
  },
  "events": [
    {
      "source": "button",
      "control_id": "power",
      "control_label": "Power",
      "value": false,
      "uptime_ms": 123456
    }
  ]
}
```

Server response:

```json
{
  "ok": true,
  "device_id": "kitchen-lamp-a1b2c3",
  "device_name": "Kitchen Lamp",
  "controls": {
    "power": true
  },
  "apply_controls": true,
  "server_time": "2026-07-22T12:00:00+00:00"
}
```

REST sync behavior:

| Input | Server behavior |
|---|---|
| Valid `device_token` | Resolves `device_id` and owner |
| `definition` | Applies schema, type, icon, controls, params, and files |
| `name` | Updates device name when non-empty |
| `local_url` | Updates local URL when non-empty |
| `telemetry` | Updates matching `params[].value` by param id |
| `controls` | Updates matching `controls[].value` unless that control is pending from dashboard |
| `events` | Records monitor log entries |
| Any successful sync | Marks device `online`, clears queued controls for the device, returns authoritative controls |

The device must always apply the returned `controls` object to hardware. Treat the response as the server-authoritative desired state.

Recommended interval:

| Device capability | Interval |
|---|---:|
| REST only, mains powered | 30-60 seconds |
| REST only, battery powered | 2-10 minutes or event-driven |
| WebSocket connected | REST as fallback/health sync every 1-5 minutes |

## WebSocket Synchronization

MQTT is the preferred realtime transport for new firmware. The legacy WebSocket transport remains available during migration for devices whose `smarthome_devices.transport` is still `ws`.

## MQTT Synchronization

Broker endpoints:

| Client | Endpoint |
|---|---|
| Firmware/browser MQTT over WebSocket | `ws://<host>/mqtt` |
| Backend inside container | `127.0.0.1:1883` |

Auth:

| Field | Value |
|---|---|
| `client_id` | `device_id` |
| username | `device_id` |
| password | existing device token |

Topics:

| Topic | Direction | QoS | Retained |
|---|---|---:|---|
| `home/<device_id>/set` | server/dashboard to device | 1 | no |
| `home/<device_id>/state` | device to server/dashboard | 1 | yes |
| `home/<device_id>/status` | device to server/dashboard | 1 | yes |

Last Will:

```text
topic: home/<device_id>/status
payload: offline
qos: 1
retain: true
```

On successful MQTT connect, publish:

```text
topic: home/<device_id>/status
payload: online
qos: 1
retain: true
```

State payload should keep the current WebSocket state shape:

```json
{
  "type": "state",
  "local_url": "http://192.168.1.50",
  "controls": {
    "power": true
  },
  "telemetry": {
    "signal": "-60 dBm"
  }
}
```

Command payload on `home/<device_id>/set` keeps the current command shape:

```json
{
  "type": "command",
  "control_id": "power",
  "value": true,
  "controls": {
    "power": true
  }
}
```

Device connects to:

```text
wss://<host>/smart-home-ws
```

Device authentication:

```json
{
  "type": "auth",
  "role": "device",
  "device_token": "device-secret-token",
  "name": "Kitchen Lamp",
  "local_url": "http://192.168.1.50",
  "definition": {
    "type": "smart_lamp",
    "icon": "lamp",
    "controls": [
      { "id": "power", "label": "Power", "type": "switch", "value": false, "primary": true }
    ],
    "params": [
      { "id": "signal", "label": "Wi-Fi RSSI", "value": "-63 dBm" }
    ]
  }
}
```

Successful response:

```json
{
  "type": "connected",
  "device_id": "kitchen-lamp-a1b2c3",
  "power": false
}
```

After WebSocket auth:

| Case | Result |
|---|---|
| Token is missing | `error: Missing device token` |
| Token is unknown | `error: Unknown device token` |
| Device row is missing | `error: Device record not found` |
| Same device opens a second connection | Older device connection is closed |
| `definition` is present | Definition is applied |
| Auth succeeds | Device is marked `online` and dashboards receive `state_update` |

Device state message:

```json
{
  "type": "state",
  "local_url": "http://192.168.1.50",
  "controls": {
    "power": true
  },
  "telemetry": {
    "signal": "-60 dBm"
  },
  "events": [
    {
      "source": "button",
      "control_id": "power",
      "control_label": "Power",
      "value": true,
      "uptime_ms": 130000
    }
  ]
}
```

Server command message:

```json
{
  "type": "command",
  "control_id": "power",
  "value": true,
  "controls": {
    "power": true
  }
}
```

The device must apply both `control_id`/`value` and the full `controls` map. The full map is the desired state for all controls.

Heartbeat:

| Message | Behavior |
|---|---|
| Device sends `{"type":"ping"}` | Server replies with `pong` |
| Server sends `{"type":"ping","ts":...}` | Device should reply with `pong` |
| No traffic for more than 90 seconds | Server closes connection and marks device offline on close |

## Dashboard Commands

Dashboard control request:

```http
POST /apps/smart_home/?api=control
Content-Type: application/json
```

```json
{
  "device_id": "kitchen-lamp-a1b2c3",
  "control_id": "power",
  "value": true
}
```

Server behavior:

| Delivery path | Behavior |
|---|---|
| Local GPIO/local HTTP supported and available | Command may be delivered directly |
| WebSocket device connected | Command is pushed instantly as `command` |
| Device not connected | Command remains queued up to 120 seconds |
| Unknown `control_id` | Request fails with `Unknown control` |

After a dashboard command, the server immediately updates the stored control value. For a short pending window, device-origin control reports for the same control are ignored so older physical state does not overwrite the dashboard command.

## Control Queue

The control queue is stored in `smarthome_control_queue`.

| Case | Result |
|---|---|
| Command is queued | Device can receive it through WebSocket processing |
| Queue item expires after 120 seconds | Result becomes `Device control timeout` |
| Device sync succeeds | Queue entries for that device are cleared |
| Device reports controls while a control is pending | Pending control ids are not overwritten |

REST-only firmware must poll `device_sync` and apply the response controls. WebSocket firmware should still apply every `command` immediately.

## Offline and Reconnect Cases

| Case | Server behavior | Device requirement |
|---|---|---|
| WebSocket disconnects normally | Device status becomes `offline` | Reconnect with same auth payload |
| WebSocket times out | Server logs tunnel timeout and closes connection | Reply to pings and reconnect |
| Device reconnects while old connection exists | Old connection is closed | Keep one active connection per token |
| REST sync resumes after outage | Device becomes `online` | Send current telemetry and controls |
| Dashboard changed controls while device was offline | Response `controls` contains desired state | Apply returned controls before reporting success |

## Route/File Tunnel Messages

Devices that expose route maps or files can use the WebSocket tunnel.

Dashboard-to-device message types:

| Type | Purpose |
|---|---|
| `route_list` | Request file list |
| `route_file` | Request file contents |
| `route_delete` | Delete file |
| `route_rename` | Rename file |

Device-to-dashboard message types:

| Type | Purpose |
|---|---|
| `route_list_result` | Partial list response |
| `route_list_end` | End of list |
| `route_file_begin` | File transfer metadata |
| `route_file_chunk` | File content chunk |
| `route_file_end` | File transfer complete |
| `route_file_error` | File transfer failed |
| `route_action_result` | Delete/rename result |

Every route message should include the dashboard-provided `request_id`.

## Error Handling

REST errors:

| HTTP status | Error | Meaning |
|---:|---|---|
| 400 | `Missing token` | `device_token` was omitted |
| 404 | `Unknown token` | Token was not registered with `save_device` |
| 404 | `Device not found` | Token index points to a missing device row |
| 400 | `Unknown control` | Dashboard requested an unknown control id |

WebSocket errors:

| Error | Meaning |
|---|---|
| `Invalid payload` | Message was not valid JSON object |
| `Unauthenticated` | Non-auth message sent before auth |
| `Unknown role` | Auth role was not `device` or `dashboard` |
| `Missing device token` | Device auth omitted token |
| `Unknown device token` | Token is not registered |
| `Device record not found` | Registered device row is missing |

## Firmware Implementation Checklist

1. Store a unique `device_token` in non-volatile memory.
2. Require the installer to add that token in the dashboard before sync.
3. Send `definition` on first boot and after firmware/schema changes.
4. Keep `controls[].id` and `params[].id` stable across firmware versions.
5. Send current hardware state in `controls`.
6. Send telemetry values in `telemetry` using param ids from the definition.
7. Always apply server response `controls` when `apply_controls` is true.
8. For WebSocket, authenticate once, respond to `ping`, apply every `command`, and reconnect on close.
9. Do not create multiple WebSocket connections for the same physical device.
10. Back off reconnect attempts after repeated failures.

## Minimal REST Device Loop

```json
{
  "device_token": "device-secret-token",
  "definition": {
    "type": "smart_socket",
    "icon": "smart-plug",
    "controls": [
      { "id": "power", "label": "Power", "type": "switch", "value": false, "primary": true }
    ],
    "params": [
      { "id": "voltage", "label": "Voltage", "value": "230 V" },
      { "id": "load", "label": "Load", "value": "0 W" }
    ]
  },
  "controls": {
    "power": false
  },
  "telemetry": {
    "voltage": "230 V",
    "load": "0 W"
  }
}
```

Required device-side response handling:

```text
if response.ok and response.apply_controls:
  for each control_id, desired_value in response.controls:
    apply desired_value to hardware control_id
    store desired_value as local state
```

## Built-In Device Types

| Type | Default icon | Typical controls |
|---|---|---|
| `pending` | `smart-device` | Empty until device sends definition |
| `generic_sensor` | `sensor` | `telemetry_enabled`, `sample_interval` |
| `smart_torch` | `torch` | `relay_1`, `relay_2` |
| `smart_lamp` | `lamp` | `power`, `brightness`, `mode` |
| `smart_socket` | `smart-plug` | `power`, `auto_off_minutes` |
| `sonoff_relay` | `light-bulb` | `relay_1` |

Use custom definitions when the device does not match a built-in template.
