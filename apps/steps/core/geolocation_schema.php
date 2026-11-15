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
        // 003: manual trip management (duration, geometry visibility, links).
        $applied->execute([':version' => '003']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v3_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '003']);
        }
        // 004: location statistics summaries and versioned boundary data.
        $applied->execute([':version' => '004']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v4_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '004']);
        }
        // 005: import provenance, per-file links and import state.
        $applied->execute([':version' => '005']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v5_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '005']);
        }
        // 006: allow the same file to be imported again after removal/failure.
        $applied->execute([':version' => '006']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v6_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '006']);
        }
        // 007: failed files must not block a retry with identical bytes.
        $applied->execute([':version' => '007']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v7_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '007']);
        }
        // 008: durable export jobs and downloadable artifacts.
        $applied->execute([':version' => '008']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v8_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '008']);
        }
        // 009: hashed, expiring family invitations.
        $applied->execute([':version' => '009']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v9_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '009']);
        }
        // 010: Immich connection metadata and bounded asset matching.
        $applied->execute([':version' => '010']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v10_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '010']);
        }
        // 011: explicit family consent, scoped grants and revocation generations.
        $applied->execute([':version' => '011']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v11_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '011']);
        }
        // 012: durable notification outbox and signed webhook delivery state.
        $applied->execute([':version' => '012']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v12_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '012']);
        }
        // 013: poster and route-video jobs, snapshots and owned artifacts.
        $applied->execute([':version' => '013']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v13_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '013']);
        }
        // 014: private achievements, tenant preference and region boundaries.
        $applied->execute([':version' => '014']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v14_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '014']);
        }
        // 015: hashed, scoped tenant API keys.
        $applied->execute([':version' => '015']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v15_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '015']);
        }
        // 016: per-tenant privacy policy and redacted audit indexes.
        $applied->execute([':version' => '016']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v16_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '016']);
        }
        // 017: durable leased jobs, feature flags and high-volume indexes.
        $applied->execute([':version' => '017']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v17_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '017']);
        }
        // 018: read-path covering indexes for API lists and worker owner picks.
        $applied->execute([':version' => '018']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v18_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '018']);
        }
        // 019: explainable quality evidence, reversible corrections and
        // versioned derived matched geometry. Raw points stay canonical.
        $applied->execute([':version' => '019']);
        if (!$applied->fetchColumn()) {
            foreach (steps_geolocation_schema_v19_sql() as $sql) $pdo->exec($sql);
            $insert->execute([':version' => '019']);
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
function steps_geolocation_schema_v3_sql(): array
{
    return [
        "ALTER TABLE geo_trips ADD COLUMN IF NOT EXISTS duration_s INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE geo_trips ADD COLUMN IF NOT EXISTS geometry_visible BOOLEAN NOT NULL DEFAULT true",
        "UPDATE geo_trips SET duration_s = GREATEST(0, EXTRACT(EPOCH FROM (ended_at - started_at))::int) WHERE duration_s = 0",
        "CREATE TABLE IF NOT EXISTS geo_trip_places (trip_id UUID NOT NULL REFERENCES geo_trips(id) ON DELETE CASCADE, place_id UUID NOT NULL REFERENCES geo_places(id) ON DELETE CASCADE, user_email TEXT NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), PRIMARY KEY (trip_id, place_id))",
        "CREATE TABLE IF NOT EXISTS geo_trip_photos (trip_id UUID NOT NULL REFERENCES geo_trips(id) ON DELETE CASCADE, asset_id UUID NOT NULL REFERENCES geo_immich_assets(id) ON DELETE CASCADE, user_email TEXT NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), PRIMARY KEY (trip_id, asset_id))",
        "CREATE INDEX IF NOT EXISTS geo_trip_places_user_idx ON geo_trip_places(user_email, place_id)",
        "CREATE INDEX IF NOT EXISTS geo_trip_photos_user_idx ON geo_trip_photos(user_email, asset_id)",
    ];
}
function steps_geolocation_schema_v4_sql(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS geo_boundary_datasets (version TEXT NOT NULL, kind TEXT NOT NULL CHECK (kind IN ('country','city')), source TEXT NOT NULL DEFAULT '', feature_count INTEGER NOT NULL DEFAULT 0, imported_at TIMESTAMPTZ NOT NULL DEFAULT now(), PRIMARY KEY (version, kind))",
        "CREATE TABLE IF NOT EXISTS geo_boundary_areas (dataset_version TEXT NOT NULL, kind TEXT NOT NULL, code TEXT NOT NULL, name TEXT NOT NULL, parent_code TEXT NOT NULL DEFAULT '', geometry geometry(Geometry,4326) NOT NULL, PRIMARY KEY (dataset_version, kind, code), FOREIGN KEY (dataset_version, kind) REFERENCES geo_boundary_datasets(version, kind) ON DELETE CASCADE)",
        "CREATE INDEX IF NOT EXISTS geo_boundary_areas_geometry_idx ON geo_boundary_areas USING GIST(geometry)",
        "CREATE TABLE IF NOT EXISTS geo_statistics_daily (user_email TEXT NOT NULL, day DATE NOT NULL, timezone TEXT NOT NULL, processor_version TEXT NOT NULL, boundary_version TEXT NOT NULL DEFAULT '', point_count INTEGER NOT NULL DEFAULT 0, accepted_count INTEGER NOT NULL DEFAULT 0, flagged_count INTEGER NOT NULL DEFAULT 0, rejected_count INTEGER NOT NULL DEFAULT 0, distance_m DOUBLE PRECISION NOT NULL DEFAULT 0, active_seconds BIGINT NOT NULL DEFAULT 0, first_at TIMESTAMPTZ, last_at TIMESTAMPTZ, accuracy_avg DOUBLE PRECISION, accuracy_json JSONB NOT NULL DEFAULT '{}'::jsonb, sources_json JSONB NOT NULL DEFAULT '{}'::jsonb, country_code TEXT, country_name TEXT, city_name TEXT, updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), PRIMARY KEY (user_email, day, timezone))",
        "CREATE TABLE IF NOT EXISTS geo_statistics_cache (user_email TEXT NOT NULL, timezone TEXT NOT NULL, processor_version TEXT NOT NULL, boundary_version TEXT NOT NULL DEFAULT '', input_to TIMESTAMPTZ, payload_json JSONB NOT NULL DEFAULT '{}'::jsonb, computed_at TIMESTAMPTZ NOT NULL DEFAULT now(), PRIMARY KEY (user_email, timezone))",
    ];
}
function steps_geolocation_schema_v5_sql(): array
{
    return [
        "ALTER TABLE geo_imports ADD COLUMN IF NOT EXISTS format TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_imports ADD COLUMN IF NOT EXISTS device_id UUID",
        "ALTER TABLE geo_imports ADD COLUMN IF NOT EXISTS storage_dir TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_imports ADD COLUMN IF NOT EXISTS options_json JSONB NOT NULL DEFAULT '{}'::jsonb",
        "ALTER TABLE geo_imports ADD COLUMN IF NOT EXISTS error_json JSONB NOT NULL DEFAULT '{}'::jsonb",
        "ALTER TABLE geo_imports ADD COLUMN IF NOT EXISTS completed_at TIMESTAMPTZ",
        "ALTER TABLE geo_imports ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ",
        "ALTER TABLE geo_imports DROP CONSTRAINT IF EXISTS geo_imports_state_check",
        "ALTER TABLE geo_imports ADD CONSTRAINT geo_imports_state_check CHECK (state IN ('pending','processing','ready','failed','stale','deleted'))",
        "ALTER TABLE geo_import_files ADD COLUMN IF NOT EXISTS source_format TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_import_files ADD COLUMN IF NOT EXISTS size_bytes BIGINT NOT NULL DEFAULT 0",
        "ALTER TABLE geo_import_files ADD COLUMN IF NOT EXISTS point_count INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE geo_import_files ADD COLUMN IF NOT EXISTS accepted_count INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE geo_import_files ADD COLUMN IF NOT EXISTS duplicate_count INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE geo_import_files ADD COLUMN IF NOT EXISTS rejected_count INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE geo_import_files ADD COLUMN IF NOT EXISTS error_json JSONB NOT NULL DEFAULT '{}'::jsonb",
        "ALTER TABLE geo_import_files ADD COLUMN IF NOT EXISTS processed_at TIMESTAMPTZ",
        "ALTER TABLE geo_import_files ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ",
        "CREATE TABLE IF NOT EXISTS geo_point_sources (id UUID PRIMARY KEY, user_email TEXT NOT NULL, point_id UUID NOT NULL REFERENCES geo_location_points(id) ON DELETE CASCADE, namespace TEXT NOT NULL, event_id TEXT NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE (user_email, namespace, event_id))",
        "CREATE INDEX IF NOT EXISTS geo_point_sources_point_idx ON geo_point_sources(point_id)",
        "CREATE TABLE IF NOT EXISTS geo_import_points (import_file_id UUID NOT NULL REFERENCES geo_import_files(id) ON DELETE CASCADE, point_id UUID NOT NULL REFERENCES geo_location_points(id) ON DELETE CASCADE, user_email TEXT NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), PRIMARY KEY (import_file_id, point_id))",
        "CREATE INDEX IF NOT EXISTS geo_import_points_point_idx ON geo_import_points(point_id)",
    ];
}
function steps_geolocation_schema_v6_sql(): array
{
    return [
        "ALTER TABLE geo_import_files DROP CONSTRAINT IF EXISTS geo_import_files_user_email_checksum_key",
        "CREATE UNIQUE INDEX IF NOT EXISTS geo_import_files_owner_checksum_active_idx ON geo_import_files(user_email, checksum) WHERE deleted_at IS NULL AND state <> 'failed'",
    ];
}
function steps_geolocation_schema_v7_sql(): array
{
    return [
        "DROP INDEX IF EXISTS geo_import_files_owner_checksum_active_idx",
        "CREATE UNIQUE INDEX geo_import_files_owner_checksum_active_idx ON geo_import_files(user_email, checksum) WHERE deleted_at IS NULL AND state <> 'failed'",
    ];
}
function steps_geolocation_schema_v8_sql(): array
{
    return [
        "ALTER TABLE geo_exports ADD COLUMN IF NOT EXISTS options_json JSONB NOT NULL DEFAULT '{}'::jsonb",
        "ALTER TABLE geo_exports ADD COLUMN IF NOT EXISTS error_json JSONB NOT NULL DEFAULT '{}'::jsonb",
        "ALTER TABLE geo_exports ADD COLUMN IF NOT EXISTS started_at TIMESTAMPTZ",
        "ALTER TABLE geo_exports ADD COLUMN IF NOT EXISTS completed_at TIMESTAMPTZ",
        "ALTER TABLE geo_exports ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ",
        "ALTER TABLE geo_exports DROP CONSTRAINT IF EXISTS geo_exports_state_check",
        "ALTER TABLE geo_exports ADD CONSTRAINT geo_exports_state_check CHECK (state IN ('pending','processing','ready','failed','stale','deleted'))",
        "CREATE INDEX IF NOT EXISTS geo_exports_user_state_idx ON geo_exports(user_email, state, created_at DESC)",
        "ALTER TABLE geo_artifacts ADD COLUMN IF NOT EXISTS size_bytes BIGINT NOT NULL DEFAULT 0",
        "ALTER TABLE geo_artifacts ADD COLUMN IF NOT EXISTS format TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_artifacts ADD COLUMN IF NOT EXISTS filename TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_artifacts ADD COLUMN IF NOT EXISTS content_type TEXT NOT NULL DEFAULT 'application/octet-stream'",
        "ALTER TABLE geo_artifacts ADD COLUMN IF NOT EXISTS download_count INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE geo_artifacts ADD COLUMN IF NOT EXISTS last_downloaded_at TIMESTAMPTZ",
        "CREATE INDEX IF NOT EXISTS geo_artifacts_user_idx ON geo_artifacts(user_email, export_id)",
    ];
}
function steps_geolocation_schema_v9_sql(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS geo_family_invitations (id UUID PRIMARY KEY, group_id UUID NOT NULL REFERENCES geo_family_groups(id), inviter_email TEXT NOT NULL, invitee_email TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'member' CHECK (role IN ('admin','member')), token_hash TEXT NOT NULL UNIQUE, expires_at TIMESTAMPTZ NOT NULL, accepted_at TIMESTAMPTZ, accepted_by TEXT, cancelled_at TIMESTAMPTZ, created_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE INDEX IF NOT EXISTS geo_family_invitations_group_idx ON geo_family_invitations(group_id, created_at DESC)",
        "CREATE INDEX IF NOT EXISTS geo_family_invitations_invitee_idx ON geo_family_invitations(invitee_email, expires_at) WHERE accepted_at IS NULL AND cancelled_at IS NULL",
        "CREATE UNIQUE INDEX IF NOT EXISTS geo_family_invitations_pending_idx ON geo_family_invitations(group_id, invitee_email) WHERE accepted_at IS NULL AND cancelled_at IS NULL",
        "CREATE INDEX IF NOT EXISTS geo_family_members_user_idx ON geo_family_members(user_email, status)",
        "CREATE INDEX IF NOT EXISTS geo_family_groups_owner_idx ON geo_family_groups(owner_email)",
    ];
}
function steps_geolocation_schema_v10_sql(): array
{
    return [
        "ALTER TABLE geo_immich_connections ADD COLUMN IF NOT EXISTS options_json JSONB NOT NULL DEFAULT '{}'::jsonb",
        "ALTER TABLE geo_immich_connections ADD COLUMN IF NOT EXISTS last_tested_at TIMESTAMPTZ",
        "ALTER TABLE geo_immich_connections ADD COLUMN IF NOT EXISTS last_sync_at TIMESTAMPTZ",
        "ALTER TABLE geo_immich_connections ADD COLUMN IF NOT EXISTS last_error TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_immich_connections ADD COLUMN IF NOT EXISTS disconnected_at TIMESTAMPTZ",
        "ALTER TABLE geo_immich_assets ADD COLUMN IF NOT EXISTS state TEXT NOT NULL DEFAULT 'matched' CHECK (state IN ('matched','unmatched','unavailable','private','deleted','changed'))",
        "ALTER TABLE geo_immich_assets ADD COLUMN IF NOT EXISTS original_filename TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_immich_assets ADD COLUMN IF NOT EXISTS match_distance_m DOUBLE PRECISION",
        "ALTER TABLE geo_immich_assets ADD COLUMN IF NOT EXISTS match_time_delta_s INTEGER",
        "ALTER TABLE geo_immich_assets ADD COLUMN IF NOT EXISTS match_point_id UUID",
        "ALTER TABLE geo_immich_assets ADD COLUMN IF NOT EXISTS last_seen_at TIMESTAMPTZ",
        "ALTER TABLE geo_immich_assets ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT now()",
        "ALTER TABLE geo_immich_assets ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ",
        "CREATE INDEX IF NOT EXISTS geo_immich_assets_time_idx ON geo_immich_assets(user_email, captured_at DESC) WHERE deleted_at IS NULL",
        "CREATE INDEX IF NOT EXISTS geo_immich_assets_state_idx ON geo_immich_assets(user_email, state) WHERE deleted_at IS NULL",
    ];
}
function steps_geolocation_schema_v11_sql(): array
{
    return [
        "ALTER TABLE geo_location_grants ADD COLUMN IF NOT EXISTS consent_at TIMESTAMPTZ",
        "ALTER TABLE geo_location_grants ADD COLUMN IF NOT EXISTS paused_at TIMESTAMPTZ",
        "ALTER TABLE geo_location_grants ADD COLUMN IF NOT EXISTS precision_m DOUBLE PRECISION NOT NULL DEFAULT 0",
        "ALTER TABLE geo_location_grants ADD COLUMN IF NOT EXISTS reason TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_location_grants ADD COLUMN IF NOT EXISTS generation INTEGER NOT NULL DEFAULT 1",
        "ALTER TABLE geo_location_grants ADD COLUMN IF NOT EXISTS last_used_at TIMESTAMPTZ",
        "CREATE INDEX IF NOT EXISTS geo_location_grants_active_pair_idx ON geo_location_grants(owner_email, viewer_email) WHERE revoked_at IS NULL",
        "CREATE INDEX IF NOT EXISTS geo_location_grants_viewer_idx ON geo_location_grants(viewer_email) WHERE revoked_at IS NULL",
        // Grantees must be members of the granting group; direct grants stay valid.
        "CREATE INDEX IF NOT EXISTS geo_location_grants_group_idx ON geo_location_grants(family_group_id) WHERE family_group_id IS NOT NULL",
    ];
}
function steps_geolocation_schema_v12_sql(): array
{
    return [
        "ALTER TABLE geo_webhooks ADD COLUMN IF NOT EXISTS description TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_webhooks ADD COLUMN IF NOT EXISTS last_delivery_at TIMESTAMPTZ",
        "ALTER TABLE geo_webhooks ADD COLUMN IF NOT EXISTS failure_count INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE geo_webhook_deliveries ADD COLUMN IF NOT EXISTS idempotency_key TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_webhook_deliveries ADD COLUMN IF NOT EXISTS last_error TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_webhook_deliveries ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMPTZ",
        "ALTER TABLE geo_webhook_deliveries ADD COLUMN IF NOT EXISTS lease_until TIMESTAMPTZ",
        "CREATE UNIQUE INDEX IF NOT EXISTS geo_webhook_deliveries_idem_idx ON geo_webhook_deliveries(webhook_id, idempotency_key) WHERE idempotency_key <> ''",
        "CREATE TABLE IF NOT EXISTS geo_notification_outbox (id UUID PRIMARY KEY, user_email TEXT NOT NULL, event_type TEXT NOT NULL, severity TEXT NOT NULL DEFAULT 'info', title TEXT NOT NULL, message TEXT NOT NULL DEFAULT '', context_json JSONB NOT NULL DEFAULT '{}'::jsonb, dedupe_key TEXT NOT NULL DEFAULT '', state TEXT NOT NULL DEFAULT 'pending' CHECK (state IN ('pending','processing','ready','failed','stale')), attempts INTEGER NOT NULL DEFAULT 0, next_attempt_at TIMESTAMPTZ NOT NULL DEFAULT now(), lease_until TIMESTAMPTZ, delivered_at TIMESTAMPTZ, last_error TEXT NOT NULL DEFAULT '', created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE UNIQUE INDEX IF NOT EXISTS geo_notification_outbox_dedupe_idx ON geo_notification_outbox(user_email, dedupe_key) WHERE dedupe_key <> ''",
        "CREATE INDEX IF NOT EXISTS geo_notification_outbox_queue_idx ON geo_notification_outbox(state, next_attempt_at)",
        "CREATE INDEX IF NOT EXISTS geo_webhook_deliveries_queue_idx ON geo_webhook_deliveries(state, next_attempt_at)",
    ];
}
function steps_geolocation_schema_v13_sql(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS geo_render_jobs (id UUID PRIMARY KEY, user_email TEXT NOT NULL, kind TEXT NOT NULL CHECK (kind IN ('poster','video')), state TEXT NOT NULL CHECK (state IN ('pending','processing','ready','failed','cancelled')), processor_version TEXT NOT NULL, config_json JSONB NOT NULL DEFAULT '{}'::jsonb, snapshot_json JSONB NOT NULL DEFAULT '{}'::jsonb, progress INTEGER NOT NULL DEFAULT 0, error_code TEXT NOT NULL DEFAULT '', artifact_id UUID, cancel_requested BOOLEAN NOT NULL DEFAULT false, expires_at TIMESTAMPTZ, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE INDEX IF NOT EXISTS geo_render_jobs_owner_idx ON geo_render_jobs(user_email, created_at DESC)",
        "CREATE INDEX IF NOT EXISTS geo_render_jobs_queue_idx ON geo_render_jobs(state, created_at)",
    ];
}
function steps_geolocation_schema_v14_sql(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS geo_achievement_settings (user_email TEXT PRIMARY KEY, enabled BOOLEAN NOT NULL DEFAULT true, timezone TEXT NOT NULL DEFAULT 'UTC', updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE TABLE IF NOT EXISTS geo_achievements (user_email TEXT NOT NULL, rule_version TEXT NOT NULL, achievement_id TEXT NOT NULL, family TEXT NOT NULL, collection TEXT NOT NULL DEFAULT '', target DOUBLE PRECISION NOT NULL, progress DOUBLE PRECISION NOT NULL DEFAULT 0, earned BOOLEAN NOT NULL DEFAULT false, first_earned_at TIMESTAMPTZ, input_watermark TEXT NOT NULL DEFAULT '', reason TEXT NOT NULL DEFAULT '', details_json JSONB NOT NULL DEFAULT '{}'::jsonb, recalculated_at TIMESTAMPTZ NOT NULL DEFAULT now(), PRIMARY KEY (user_email, rule_version, achievement_id))",
        "CREATE INDEX IF NOT EXISTS geo_achievements_owner_idx ON geo_achievements(user_email, earned, recalculated_at DESC)",
        "ALTER TABLE geo_boundary_datasets DROP CONSTRAINT IF EXISTS geo_boundary_datasets_kind_check",
        "ALTER TABLE geo_boundary_datasets ADD CONSTRAINT geo_boundary_datasets_kind_check CHECK (kind IN ('country','region','city'))",
    ];
}
function steps_geolocation_schema_v15_sql(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS geo_api_keys (id UUID PRIMARY KEY, user_email TEXT NOT NULL, name TEXT NOT NULL DEFAULT '', key_prefix TEXT NOT NULL, key_hash TEXT NOT NULL UNIQUE, scopes_json JSONB NOT NULL DEFAULT '[]'::jsonb, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), rotated_at TIMESTAMPTZ, last_used_at TIMESTAMPTZ, revoked_at TIMESTAMPTZ, expires_at TIMESTAMPTZ)",
        "CREATE INDEX IF NOT EXISTS geo_api_keys_owner_idx ON geo_api_keys(user_email, revoked_at, created_at DESC)",
    ];
}
function steps_geolocation_schema_v16_sql(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS geo_privacy_settings (user_email TEXT PRIMARY KEY, summary_precision_decimals INTEGER NOT NULL DEFAULT 2 CHECK (summary_precision_decimals BETWEEN 0 AND 4), retention_days INTEGER NOT NULL DEFAULT 0 CHECK (retention_days BETWEEN 0 AND 3650), audit_retention_days INTEGER NOT NULL DEFAULT 365 CHECK (audit_retention_days BETWEEN 30 AND 3650), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE INDEX IF NOT EXISTS geo_audit_events_owner_time_idx ON geo_audit_events(user_email, created_at DESC)",
        "CREATE INDEX IF NOT EXISTS geo_audit_events_type_idx ON geo_audit_events(event_type, created_at DESC)",
    ];
}
function steps_geolocation_schema_v17_sql(): array
{
    return [
        "ALTER TABLE geo_processor_runs ADD COLUMN IF NOT EXISTS priority SMALLINT NOT NULL DEFAULT 50",
        "ALTER TABLE geo_processor_runs ADD COLUMN IF NOT EXISTS attempts INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE geo_processor_runs ADD COLUMN IF NOT EXISTS max_attempts INTEGER NOT NULL DEFAULT 5",
        "ALTER TABLE geo_processor_runs ADD COLUMN IF NOT EXISTS next_attempt_at TIMESTAMPTZ NOT NULL DEFAULT now()",
        "ALTER TABLE geo_processor_runs ADD COLUMN IF NOT EXISTS lease_until TIMESTAMPTZ",
        "ALTER TABLE geo_processor_runs ADD COLUMN IF NOT EXISTS heartbeat_at TIMESTAMPTZ",
        "ALTER TABLE geo_processor_runs ADD COLUMN IF NOT EXISTS cancel_requested BOOLEAN NOT NULL DEFAULT false",
        "ALTER TABLE geo_processor_runs ADD COLUMN IF NOT EXISTS worker_id TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_processor_runs ADD COLUMN IF NOT EXISTS correlation_id TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_processor_runs ADD COLUMN IF NOT EXISTS last_error TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE geo_processor_runs ADD COLUMN IF NOT EXISTS started_at TIMESTAMPTZ",
        "ALTER TABLE geo_processor_runs ADD COLUMN IF NOT EXISTS finished_at TIMESTAMPTZ",
        "ALTER TABLE geo_processor_runs DROP CONSTRAINT IF EXISTS geo_processor_runs_state_check",
        "ALTER TABLE geo_processor_runs ADD CONSTRAINT geo_processor_runs_state_check CHECK (state IN ('pending','processing','ready','failed','stale','cancelled'))",
        "CREATE INDEX IF NOT EXISTS geo_processor_runs_queue_idx ON geo_processor_runs(priority, next_attempt_at, created_at) WHERE state = 'pending'",
        "CREATE INDEX IF NOT EXISTS geo_processor_runs_lease_idx ON geo_processor_runs(lease_until) WHERE state = 'processing'",
        "CREATE INDEX IF NOT EXISTS geo_processor_runs_owner_kind_idx ON geo_processor_runs(user_email, kind, state, created_at DESC)",
        "CREATE INDEX IF NOT EXISTS geo_processor_runs_kind_state_idx ON geo_processor_runs(kind, state, created_at DESC)",
        "CREATE INDEX IF NOT EXISTS geo_processor_runs_created_idx ON geo_processor_runs(created_at)",
        "CREATE TABLE IF NOT EXISTS geo_feature_flags (user_email TEXT NOT NULL, flag TEXT NOT NULL, enabled BOOLEAN NOT NULL DEFAULT true, updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), PRIMARY KEY (user_email, flag))",
        "CREATE INDEX IF NOT EXISTS geo_geofence_events_lookup_idx ON geo_geofence_events(user_email, geofence_id, occurred_at DESC)",
        // Remove exact duplicate events produced by the pre-lease polling loop
        // in one linear pass (a self-join is quadratic on large backlogs),
        // then make exact duplicates impossible at the database level.
        "DELETE FROM geo_geofence_events WHERE id IN (SELECT id FROM (SELECT id, row_number() OVER (PARTITION BY user_email, geofence_id, event_type, occurred_at ORDER BY id) AS rn FROM geo_geofence_events) d WHERE rn > 1)",
        "CREATE UNIQUE INDEX IF NOT EXISTS geo_geofence_events_dedupe_idx ON geo_geofence_events(user_email, geofence_id, event_type, occurred_at)",
        "ANALYZE geo_processor_runs",
        "ANALYZE geo_geofence_events",
        "ANALYZE geo_location_points",
    ];
}
function steps_geolocation_schema_v18_sql(): array
{
    return [
        // API list pages order by source_at/id and select these columns; a
        // covering index removes heap fetches for the 50-row page.
        "CREATE INDEX IF NOT EXISTS geo_location_points_api_list_idx ON geo_location_points (user_email, source_at DESC, id DESC) INCLUDE (device_id, source_type, received_at, quality_state, accuracy_m, position)",
        // Worker owner selection and quality filters can stop after a few
        // distinct owners instead of grouping the whole points table.
        "CREATE INDEX IF NOT EXISTS geo_location_points_quality_owner_idx ON geo_location_points (quality_state, user_email)",
        "CREATE INDEX IF NOT EXISTS geo_geofences_active_owner_idx ON geo_geofences (user_email) WHERE is_active",
        "ANALYZE geo_location_points",
        "ANALYZE geo_geofences",
    ];
}
function steps_geolocation_schema_v19_sql(): array
{
    return [
        "ALTER TABLE geo_location_points ADD COLUMN IF NOT EXISTS quality_score DOUBLE PRECISION",
        "ALTER TABLE geo_location_points ADD COLUMN IF NOT EXISTS quality_severity TEXT NOT NULL DEFAULT 'ok'",
        "ALTER TABLE geo_location_points ADD COLUMN IF NOT EXISTS quality_version TEXT NOT NULL DEFAULT 'quality-1'",
        "ALTER TABLE geo_location_points ADD COLUMN IF NOT EXISTS quality_details JSONB NOT NULL DEFAULT '{}'::jsonb",
        "ALTER TABLE geo_location_points DROP CONSTRAINT IF EXISTS geo_location_points_quality_state_check",
        "ALTER TABLE geo_location_points ADD CONSTRAINT geo_location_points_quality_state_check CHECK (quality_state IN ('pending','accepted','suspect','flagged','rejected','excluded_by_user','private_redacted','not_enough_evidence'))",
        "CREATE TABLE IF NOT EXISTS geo_quality_corrections (id UUID PRIMARY KEY, user_email TEXT NOT NULL, scope_type TEXT NOT NULL CHECK (scope_type IN ('points','range','trip','import')), scope_json JSONB NOT NULL DEFAULT '{}'::jsonb, effects_json JSONB NOT NULL DEFAULT '[]'::jsonb, reason TEXT NOT NULL DEFAULT '', note TEXT NOT NULL DEFAULT '', previous_states_json JSONB NOT NULL DEFAULT '{}'::jsonb, state TEXT NOT NULL DEFAULT 'active' CHECK (state IN ('active','revoked')), created_at TIMESTAMPTZ NOT NULL DEFAULT now(), revoked_at TIMESTAMPTZ)",
        "CREATE INDEX IF NOT EXISTS geo_quality_corrections_owner_idx ON geo_quality_corrections(user_email, state, created_at DESC)",
        "CREATE TABLE IF NOT EXISTS geo_match_settings (user_email TEXT PRIMARY KEY, mode TEXT NOT NULL DEFAULT 'conservative' CHECK (mode IN ('disabled','conservative','review_required')), geometry_mode TEXT NOT NULL DEFAULT 'raw' CHECK (geometry_mode IN ('raw','improved','compare')), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE TABLE IF NOT EXISTS geo_match_networks (id UUID PRIMARY KEY, region_code TEXT NOT NULL DEFAULT '', kind TEXT NOT NULL CHECK (kind IN ('road','path','cycleway','rail','waterway','ferry','offroad')), name TEXT NOT NULL DEFAULT '', access TEXT NOT NULL DEFAULT 'public' CHECK (access IN ('public','permissive','private','restricted')), source TEXT NOT NULL DEFAULT '', version TEXT NOT NULL, geometry geometry(LineString,4326) NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE INDEX IF NOT EXISTS geo_match_networks_geometry_idx ON geo_match_networks USING GIST(geometry)",
        "CREATE INDEX IF NOT EXISTS geo_match_networks_region_idx ON geo_match_networks(region_code, kind, version)",
        "CREATE TABLE IF NOT EXISTS geo_matched_segments (id UUID PRIMARY KEY, user_email TEXT NOT NULL, trip_id UUID, point_ids UUID[] NOT NULL, started_at TIMESTAMPTZ NOT NULL, ended_at TIMESTAMPTZ NOT NULL, raw_geometry geometry(LineString,4326) NOT NULL, matched_geometry geometry(LineString,4326), mode TEXT NOT NULL DEFAULT 'none' CHECK (mode IN ('road','path','cycleway','rail','waterway','ferry','offroad','none')), confidence DOUBLE PRECISION NOT NULL DEFAULT 0, confidence_band TEXT NOT NULL DEFAULT 'none' CHECK (confidence_band IN ('high','medium','low','none')), reasons_json JSONB NOT NULL DEFAULT '[]'::jsonb, explanation TEXT NOT NULL DEFAULT '', network_version TEXT NOT NULL DEFAULT '', matcher_version TEXT NOT NULL, config_json JSONB NOT NULL DEFAULT '{}'::jsonb, state TEXT NOT NULL DEFAULT 'abstained' CHECK (state IN ('matched','abstained','stale')), created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now())",
        "CREATE UNIQUE INDEX IF NOT EXISTS geo_matched_segments_trip_version_idx ON geo_matched_segments(user_email, trip_id, matcher_version, started_at) WHERE trip_id IS NOT NULL",
        "CREATE INDEX IF NOT EXISTS geo_matched_segments_owner_time_idx ON geo_matched_segments(user_email, started_at DESC)",
        "CREATE INDEX IF NOT EXISTS geo_matched_segments_geometry_idx ON geo_matched_segments USING GIST(matched_geometry)",
        "ANALYZE geo_location_points",
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
