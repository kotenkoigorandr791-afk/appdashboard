<?php
require_once __DIR__ . '/../../../lib/app_db.php';
function steps_geolocation_migrate(AppDatabase $db): void {
    $pdo = $db->getPdo();
    $pdo->exec('SELECT pg_advisory_lock(710102)');
    try {
        $pdo->beginTransaction();
        $pdo->exec('CREATE EXTENSION IF NOT EXISTS postgis');
        $version = (string)$pdo->query('SELECT postgis_full_version()')->fetchColumn();
        if ($version === '') throw new RuntimeException('PostGIS extension is unavailable');
        $pdo->exec("CREATE TABLE IF NOT EXISTS geo_schema_migrations (version TEXT PRIMARY KEY, applied_at TIMESTAMPTZ NOT NULL DEFAULT now())");
        $applied = $pdo->prepare('SELECT 1 FROM geo_schema_migrations WHERE version = :version');
        $insert = $pdo->prepare('INSERT INTO geo_schema_migrations(version) VALUES (:version)');
        $applied->execute([':version' => '001']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '001']);
        }
        // 002: presentation settings for the map layer (tile provider, layers).
        $applied->execute([':version' => '002']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v2_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '002']);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally {
        $pdo->exec('SELECT pg_advisory_unlock(710102)');
    }
}function steps_geolocation_schema_v2_sql(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS geo_map_preferences (user_email TEXT PRIMARY KEY, provider TEXT NOT NULL DEFAULT 'osm', layers_json JSONB NOT NULL DEFAULT '[]'::jsonb, updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
    ];
}
function steps_geolocation_schema_sql(): array
{
    return [
        "CREATE TABLE geo_devices (id UUID PRIMARY KEY, user_email TEXT NOT NULL, name TEXT NOT NULL, platform TEXT NOT NULL DEFAULT '', app_version TEXT NOT NULL DEFAULT '', source_type TEXT NOT NULL CHECK (source_type IN ('mobile','import','api')), status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','paused','revoked','deleted')), credential_hash TEXT, last_seen_at TIMESTAMPTZ, last_upload_at TIMESTAMPTZ, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE (user_email, id))",
        "CREATE TABLE geo_location_points (id UUID PRIMARY KEY, user_email TEXT NOT NULL, device_id UUID, client_event_id UUID, device_sequence BIGINT, source_type TEXT NOT NULL CHECK (source_type IN ('mobile','import','api')), source_at TIMESTAMPTZ NOT NULL, source_offset_minutes SMALLINT, received_at TIMESTAMPTZ NOT NULL DEFAULT now(), position geometry(Point,4326) NOT NULL, accuracy_m DOUBLE PRECISION, altitude_m DOUBLE PRECISION, speed_mps DOUBLE PRECISION, bearing_degrees DOUBLE PRECISION, quality_state TEXT NOT NULL DEFAULT 'pending' CHECK (quality_state IN ('pending','accepted','flagged','rejected')), quality_reason TEXT NOT NULL DEFAULT '', correction_json JSONB NOT NULL DEFAULT '{}'::jsonb, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), CONSTRAINT geo_location_points_position_valid CHECK (ST_SRID(position) = 4326 AND ST_IsValid(position) AND ST_Y(position) BETWEEN -90 AND 90 AND ST_X(position) BETWEEN -180 AND 180), CONSTRAINT geo_location_points_device_owner FOREIGN KEY (user_email, device_id) REFERENCES geo_devices(user_email, id))",
        "CREATE TABLE geo_processor_runs (id UUID PRIMARY KEY, user_email TEXT NOT NULL, kind TEXT NOT NULL, state TEXT NOT NULL CHECK (state IN ('pending','processing','ready','failed','stale')), processor_version TEXT NOT NULL, input_from TIMESTAMPTZ, input_to TIMESTAMPTZ, details_json JSONB NOT NULL DEFAULT '{}'::jsonb, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE TABLE geo_trips (id UUID PRIMARY KEY, user_email TEXT NOT NULL, started_at TIMESTAMPTZ NOT NULL, ended_at TIMESTAMPTZ NOT NULL, geometry geometry(LineString,4326), distance_m DOUBLE PRECISION NOT NULL DEFAULT 0, title TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '', state TEXT NOT NULL DEFAULT 'ready' CHECK (state IN ('pending','processing','ready','failed','stale','archived')), boundary_reason TEXT NOT NULL DEFAULT '', processor_version TEXT NOT NULL DEFAULT '', override_json JSONB NOT NULL DEFAULT '{}'::jsonb, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), deleted_at TIMESTAMPTZ)",
        "CREATE TABLE geo_stops (id UUID PRIMARY KEY, user_email TEXT NOT NULL, started_at TIMESTAMPTZ NOT NULL, ended_at TIMESTAMPTZ, position geometry(Point,4326) NOT NULL, radius_m DOUBLE PRECISION NOT NULL, confidence DOUBLE PRECISION, state TEXT NOT NULL DEFAULT 'ready' CHECK (state IN ('pending','processing','ready','failed','stale')), override_json JSONB NOT NULL DEFAULT '{}'::jsonb, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), deleted_at TIMESTAMPTZ)",
        "CREATE TABLE geo_places (id UUID PRIMARY KEY, user_email TEXT NOT NULL, name TEXT NOT NULL, tag TEXT NOT NULL, position geometry(Point,4326) NOT NULL, radius_m DOUBLE PRECISION NOT NULL CHECK (radius_m > 0), color TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '', timezone TEXT NOT NULL DEFAULT 'UTC', is_active BOOLEAN NOT NULL DEFAULT true, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), deleted_at TIMESTAMPTZ, CONSTRAINT geo_places_position_valid CHECK (ST_SRID(position) = 4326 AND ST_IsValid(position)))",
        "CREATE TABLE geo_visits (id UUID PRIMARY KEY, user_email TEXT NOT NULL, place_id UUID, stop_id UUID, arrived_at TIMESTAMPTZ NOT NULL, departed_at TIMESTAMPTZ, confidence DOUBLE PRECISION, state TEXT NOT NULL DEFAULT 'ready' CHECK (state IN ('pending','processing','ready','failed','stale')), override_json JSONB NOT NULL DEFAULT '{}'::jsonb, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), FOREIGN KEY (place_id) REFERENCES geo_places(id), FOREIGN KEY (stop_id) REFERENCES geo_stops(id))",
        "CREATE TABLE geo_geofences (id UUID PRIMARY KEY, user_email TEXT NOT NULL, place_id UUID, name TEXT NOT NULL, geometry geometry(Geometry,4326) NOT NULL, is_active BOOLEAN NOT NULL DEFAULT true, notification_json JSONB NOT NULL DEFAULT '{}'::jsonb, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), FOREIGN KEY (place_id) REFERENCES geo_places(id))",
        "CREATE TABLE geo_geofence_events (id UUID PRIMARY KEY, user_email TEXT NOT NULL, geofence_id UUID NOT NULL REFERENCES geo_geofences(id), point_id UUID, event_type TEXT NOT NULL CHECK (event_type IN ('enter','exit')), occurred_at TIMESTAMPTZ NOT NULL, confidence DOUBLE PRECISION, created_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE TABLE geo_imports (id UUID PRIMARY KEY, user_email TEXT NOT NULL, source_type TEXT NOT NULL, state TEXT NOT NULL CHECK (state IN ('pending','processing','ready','failed','stale')), progress_json JSONB NOT NULL DEFAULT '{}'::jsonb, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE TABLE geo_import_files (id UUID PRIMARY KEY, import_id UUID NOT NULL REFERENCES geo_imports(id), user_email TEXT NOT NULL, filename TEXT NOT NULL, checksum TEXT NOT NULL, storage_path TEXT NOT NULL DEFAULT '', state TEXT NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE (user_email, checksum))",
        "CREATE TABLE geo_exports (id UUID PRIMARY KEY, user_email TEXT NOT NULL, format TEXT NOT NULL CHECK (format IN ('json','geojson','gpx','backup')), scope_json JSONB NOT NULL DEFAULT '{}'::jsonb, state TEXT NOT NULL CHECK (state IN ('pending','processing','ready','failed','stale')), created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE TABLE geo_artifacts (id UUID PRIMARY KEY, user_email TEXT NOT NULL, export_id UUID, kind TEXT NOT NULL, storage_path TEXT NOT NULL, checksum TEXT NOT NULL, expires_at TIMESTAMPTZ, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), deleted_at TIMESTAMPTZ, FOREIGN KEY (export_id) REFERENCES geo_exports(id))",
        "CREATE TABLE geo_family_groups (id UUID PRIMARY KEY, owner_email TEXT NOT NULL, name TEXT NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE TABLE geo_family_members (group_id UUID NOT NULL REFERENCES geo_family_groups(id), user_email TEXT NOT NULL, role TEXT NOT NULL CHECK (role IN ('owner','admin','member')), status TEXT NOT NULL CHECK (status IN ('invited','active','removed','left')), invited_at TIMESTAMPTZ, accepted_at TIMESTAMPTZ, removed_at TIMESTAMPTZ, PRIMARY KEY (group_id, user_email))",
        "CREATE TABLE geo_location_grants (id UUID PRIMARY KEY, owner_email TEXT NOT NULL, viewer_email TEXT NOT NULL, family_group_id UUID REFERENCES geo_family_groups(id), access_level TEXT NOT NULL CHECK (access_level IN ('none','current','recent','range','places')), starts_at TIMESTAMPTZ, ends_at TIMESTAMPTZ, range_from TIMESTAMPTZ, range_to TIMESTAMPTZ, revoked_at TIMESTAMPTZ, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE TABLE geo_immich_connections (id UUID PRIMARY KEY, user_email TEXT NOT NULL UNIQUE, server_url_ciphertext TEXT NOT NULL, credential_ciphertext TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'active', created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE TABLE geo_immich_assets (id UUID PRIMARY KEY, user_email TEXT NOT NULL, connection_id UUID NOT NULL REFERENCES geo_immich_connections(id), immich_asset_id TEXT NOT NULL, captured_at TIMESTAMPTZ, position geometry(Point,4326), match_json JSONB NOT NULL DEFAULT '{}'::jsonb, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE (connection_id, immich_asset_id))",
        "CREATE TABLE geo_notification_preferences (user_email TEXT PRIMARY KEY, preferences_json JSONB NOT NULL DEFAULT '{}'::jsonb, updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE TABLE geo_webhooks (id UUID PRIMARY KEY, user_email TEXT NOT NULL, url_ciphertext TEXT NOT NULL, secret_ciphertext TEXT NOT NULL, event_types JSONB NOT NULL DEFAULT '[]'::jsonb, is_active BOOLEAN NOT NULL DEFAULT true, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE TABLE geo_webhook_deliveries (id UUID PRIMARY KEY, webhook_id UUID NOT NULL REFERENCES geo_webhooks(id), user_email TEXT NOT NULL, event_type TEXT NOT NULL, state TEXT NOT NULL CHECK (state IN ('pending','processing','ready','failed','stale')), attempts INTEGER NOT NULL DEFAULT 0, next_attempt_at TIMESTAMPTZ, response_code INTEGER, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE TABLE geo_audit_events (id UUID PRIMARY KEY, user_email TEXT NOT NULL, actor_email TEXT NOT NULL, event_type TEXT NOT NULL, context_json JSONB NOT NULL DEFAULT '{}'::jsonb, created_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE INDEX geo_location_points_user_time_idx ON geo_location_points(user_email, source_at DESC)",
        "CREATE INDEX geo_location_points_device_time_idx ON geo_location_points(user_email, device_id, source_at DESC)",
        "CREATE UNIQUE INDEX geo_location_points_event_idx ON geo_location_points(user_email, device_id, client_event_id) WHERE client_event_id IS NOT NULL",
        "CREATE UNIQUE INDEX geo_location_points_sequence_idx ON geo_location_points(user_email, device_id, device_sequence) WHERE device_sequence IS NOT NULL",
        "CREATE INDEX geo_location_points_position_idx ON geo_location_points USING GIST(position)",
        "CREATE INDEX geo_trips_user_time_idx ON geo_trips(user_email, started_at DESC) WHERE deleted_at IS NULL",
        "CREATE INDEX geo_trips_geometry_idx ON geo_trips USING GIST(geometry)",
        "CREATE INDEX geo_places_position_idx ON geo_places USING GIST(position) WHERE deleted_at IS NULL",
        "CREATE INDEX geo_geofences_geometry_idx ON geo_geofences USING GIST(geometry) WHERE is_active",
        "CREATE INDEX geo_immich_assets_position_idx ON geo_immich_assets USING GIST(position)",
        "CREATE INDEX geo_location_grants_owner_viewer_idx ON geo_location_grants(owner_email, viewer_email) WHERE revoked_at IS NULL",
    ];
}
