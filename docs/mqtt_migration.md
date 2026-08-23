# Smart Home MQTT Migration

## Step 0 Baseline

Reviewed files:
- `apps/smart_home/core/app.php`
- `apps/smart_home/ws_server.php`
- `apps/smart_home/SmartHomeWsHandler.php`
- `apps/smart_home/Smart Home SDK.md`
- `Dockerfile`
- `supervisord.conf`

Current runtime:
- Ratchet server runs from `apps/smart_home/ws_server.php` as `smart-home-ws` under supervisord.
- `SMART_HOME_WS_BIND=127.0.0.1`, `SMART_HOME_WS_PORT=8766`.
- Event loop calls `SmartHomeWsHandler::tick()` every 0.2 seconds.
- `tick()` deletes expired WS/dashboard tokens, deletes expired queue rows, calls `processControlQueue()`, `heartbeatTick()`, and `reconcileTunnelStatus()`.
- Heartbeat sends `ping` after 30 seconds without traffic, checks every 5 seconds, closes and marks offline after 90 seconds without traffic.
- `reconcileTunnelStatus()` runs at startup and then every 30 seconds.
- Dashboard-origin command pending window is 5 seconds.
- `smarthome_control_queue` TTL is 120 seconds.
- Local HTTP/GPIO fast-path is implemented outside the broker path and must remain unchanged.

Current database tables:
- `smarthome_settings`
- `smarthome_devices`
- `smarthome_token_index`
- `smarthome_groups`
- `smarthome_group_members`
- `smarthome_monitor_logs`
- `smarthome_params_history`
- `smarthome_floor_plans`
- `smarthome_ws_tokens`
- `smarthome_control_queue`
- `smarthome_control_results`
- `smarthome_errors`

Current device types:
- `pending`
- `generic_sensor`
- `smart_torch`
- `smart_lamp`
- `smart_socket`
- `sonoff_relay`
- custom rows are also possible; current DB contains `deds_lamp`.

Current WebSocket messages:
- Device/dashboard auth: `auth`
- Device state: `state`
- Server/dashboard state fanout: `state_update`
- Dashboard/device control request: `control`
- Server/device command: `command`
- Command response: `ack`
- Heartbeat: `ping`, `pong`
- Dashboard route tunnel requests: `route_list`, `route_file`, `route_delete`, `route_rename`
- Device route tunnel responses: `route_list_result`, `route_list_end`, `route_file_begin`, `route_file_chunk`, `route_file_end`, `route_file_error`, `route_action_result`
- Errors: `error`, `route_error`

Current auth:
- Devices authenticate with `device_token`.
- `smarthome_token_index.token` resolves to `user_email` and `device_id`.
- Dashboard WS sessions use short-lived `smarthome_ws_tokens`.

MQTT target topics:
- `home/<device_id>/set`: server/dashboard to device command, QoS 1.
- `home/<device_id>/state`: device telemetry/state, QoS 1, retained.
- `home/<device_id>/status`: `online`/`offline`, retained, Last Will for `offline`.

MQTT payload compatibility:
- Keep JSON payloads close to current WS payloads.
- `state` payload should keep `type`, `local_url`, `definition`, `controls`, `telemetry`, and `events`.
- `set` payload should keep `type: "command"`, `control_id`, `value`, and full `controls` map.
- `status` payload may be raw `online`/`offline` first; backend can later accept JSON if needed.

Baseline query results on 2026-08-23:
- Last 7 days `smarthome_monitor_logs`: `control=8`, `device_deleted=7`, `device_created=6`, `member_added=5`, `group_created=5`.
- Last 7 days `smarthome_errors`: `control_ws=8`.
- Last 7 days error message aggregate: `Device tunnel not connected (matched=0)=8`.
- Current `smarthome_control_queue`: `0` pending rows, no completed rows available for latency calculation.
- Current device statuses: `offline=8`.
- Current device types in DB: `deds_lamp=1`, `generic_sensor=2`, `smart_lamp=2`, `smart_torch=1`, `sonoff_relay=2`.
- Existing logs do not contain enough completed queue/result rows to compute reliable command-to-execution latency.

Step 0 verification commands:
```sh
docker exec appdashboard-db psql -U appdashboard -d appdashboard -c "SELECT event_type, count(*) FROM smarthome_monitor_logs WHERE created_at::timestamptz >= now() - interval '7 days' GROUP BY event_type ORDER BY count(*) DESC;"
docker exec appdashboard-db psql -U appdashboard -d appdashboard -c "SELECT source, count(*) FROM smarthome_errors WHERE created_at::timestamptz >= now() - interval '7 days' GROUP BY source ORDER BY count(*) DESC;"
docker exec appdashboard-db psql -U appdashboard -d appdashboard -c "SELECT status, count(*) FROM smarthome_devices GROUP BY status ORDER BY status;"
docker exec appdashboard-db psql -U appdashboard -d appdashboard -c "SELECT type, count(*) FROM smarthome_devices GROUP BY type ORDER BY type;"
```

Next step:
- Add Mosquitto and `mosquitto-clients` to the container image.
- Add Mosquitto config and supervisord process without disabling the current WebSocket server.

## Implemented Migration Path

Mosquitto runs in the same container as an additional supervisord process.

Listeners:
- `127.0.0.1:1883`: backend PHP worker/API only.
- `127.0.0.1:9001`: MQTT over WebSocket behind nginx.
- Public browser/device WebSocket URL: `ws://<host>/mqtt` on port 80.

Auth/ACL:
- `apps/smart_home/mqtt_auth_sync.php` generates `/etc/mosquitto/smarthome.passwd` and `/etc/mosquitto/smarthome.acl`.
- Device MQTT username is `device_id`.
- Device MQTT password is the existing `smarthome_token_index.token`.
- Device ACL is limited to `home/<device_id>/#`.
- Backend user is `appdashboard_backend`; its password is generated into `smarthome_mqtt_auth`.
- Backend ACL is `home/#`.

Backend:
- `apps/smart_home/mqtt_worker.php` connects to `127.0.0.1:1883` with stable client id `appdashboard-smarthome-backend` and persistent session.
- Subscribes to `home/+/state` and `home/+/status` with QoS 1.
- `state` updates controls, telemetry, status, `local_url`, and writes telemetry history.
- `status` updates `smarthome_devices.status`.
- HTTP control delivery keeps local HTTP fast-path first.
- Devices with `smarthome_devices.transport = 'mqtt'` receive commands through `home/<device_id>/set` with QoS 1.
- Devices with default `transport = 'ws'` continue to use the old WebSocket path.

Runtime processes:
- `mosquitto`
- `smart-home-mqtt-auth`
- `smart-home-mqtt-worker`
- existing `smart-home-ws`

Verification:
```sh
docker exec appdashboard php /var/www/AppDashboard/apps/smart_home/mqtt_auth_sync.php --once
docker exec appdashboard supervisorctl status mosquitto smart-home-mqtt-auth smart-home-mqtt-worker smart-home-ws
docker exec appdashboard mosquitto_sub -h 127.0.0.1 -p 1883 -u appdashboard_backend -P "$(docker exec appdashboard php -r 'require "/var/www/AppDashboard/lib/app_db.php"; $db=app_main_db(); echo $db->querySingle("SELECT password FROM smarthome_mqtt_auth WHERE username='\''appdashboard_backend'\''");')" -t 'home/test/status' -C 1
docker exec appdashboard mosquitto_pub -h 127.0.0.1 -p 1883 -u appdashboard_backend -P "$(docker exec appdashboard php -r 'require "/var/www/AppDashboard/lib/app_db.php"; $db=app_main_db(); echo $db->querySingle("SELECT password FROM smarthome_mqtt_auth WHERE username='\''appdashboard_backend'\''");')" -t 'home/test/status' -m online -r
```
