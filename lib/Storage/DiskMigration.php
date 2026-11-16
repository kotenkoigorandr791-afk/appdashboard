<?php
/**
 * Resumable, verified disk evacuation.
 *
 * Data is copied file-by-file to explicit target disks (never through /storage,
 * which could pick the source again), verified by size + SHA-256, and only then
 * is the source branch removed from the pool. Original files stay on the source
 * unless the operator explicitly enables deletion after verification.
 */
declare(strict_types=1);

final class DiskMigration
{
    private const CHUNK = 1048576;

    /** @return array<string,mixed> */
    public static function precheck(int $diskId, bool $deepConflicts = true): array
    {
        StorageDb::ensureSchema();
        $disk = StorageDb::diskById($diskId);
        if ($disk === null) throw new InvalidArgumentException('disk_not_found');
        $source = rtrim((string)$disk['mount_path'], '/');
        $mounts = DiskDetector::parseMountInfoFile(storage_mountinfo_path());
        if (DiskDetector::findMount($source, $mounts) === null) {
            throw new RuntimeException('source_disk_not_mounted');
        }
        if (!is_readable($source)) throw new RuntimeException('source_not_readable');

        $settings = storage_settings();
        $targets = self::availableTargets($diskId, $settings);
        if ($targets === []) throw new RuntimeException('no_target_disks');

        $totalBytes = 0;
        $totalFiles = 0;
        $totalDirs = 0;
        $conflicts = [];
        $warnings = [];
        $targetByIndex = $targets;
        foreach (self::scanSource($source) as $entry) {
            if ($entry['type'] === 'dir') { $totalDirs++; continue; }
            $totalFiles++;
            $totalBytes += $entry['size'];
            foreach ($targets as $target) {
                $path = rtrim((string)$target['mount_path'], '/') . '/' . $entry['rel_path'];
                if (!file_exists($path) && !is_link($path)) continue;
                $same = false;
                if ($entry['type'] === 'link') {
                    $same = is_link($path) && readlink($path) === $entry['link_target'];
                } elseif (is_file($path)) {
                    $same = filesize($path) === $entry['size'];
                    if ($same && $deepConflicts && $entry['size'] > 0) {
                        $srcHash = self::hashFile($source . '/' . $entry['rel_path']);
                        $dstHash = self::hashFile($path);
                        $same = $srcHash !== '' && $srcHash === $dstHash;
                    }
                }
                if (!$same) {
                    $conflicts[] = [
                        'rel_path' => $entry['rel_path'],
                        'size' => $entry['size'],
                        'target' => (string)$target['name'],
                        'target_path' => $path,
                    ];
                    if (count($conflicts) >= 100) break 2;
                }
            }
        }

        $safety = max(1048576, (int)ceil($totalBytes * 0.02));
        $required = $totalBytes + $safety;
        $usable = 0;
        foreach ($targets as &$target) {
            $target['usable_bytes'] = max(0, (int)$target['free_bytes'] - (int)$target['reserved_bytes']);
            $usable += $target['usable_bytes'];
        }
        unset($target);

        return [
            'ok' => $usable >= $required && $conflicts === [],
            'disk' => $disk,
            'source_bytes' => $totalBytes,
            'source_files' => $totalFiles,
            'source_dirs' => $totalDirs,
            'required_bytes' => $required,
            'reserve_percent' => (int)$settings['reserve_percent'],
            'min_free_bytes' => (int)$settings['min_free_gb'] * 1024 ** 3,
            'total_usable_bytes' => $usable,
            'shortfall_bytes' => max(0, $required - $usable),
            'enough_space' => $usable >= $required,
            'targets' => $targets,
            'conflicts' => $conflicts,
            'conflicts_count' => count($conflicts),
            'warnings' => $warnings,
            'can_start' => $usable >= $required && $conflicts === [],
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function availableTargets(int $sourceDiskId, array $settings): array
    {
        $out = [];
        foreach (StorageDb::disks() as $disk) {
            if ((int)$disk['id'] === $sourceDiskId) continue;
            if ((string)$disk['status'] !== 'online') continue;
            $path = rtrim((string)$disk['mount_path'], '/');
            if (!is_dir($path) || !is_writable($path)) continue;
            if (DiskDetector::findMount($path, DiskDetector::parseMountInfoFile(storage_mountinfo_path())) === null) continue;
            $total = @disk_total_space($path);
            $free = @disk_free_space($path);
            if ($total === false || $free === false) continue;
            $reserved = max((int)$settings['min_free_gb'] * 1024 ** 3, (int)ceil((int)$total * ((int)$settings['reserve_percent'] / 100)));
            $out[] = [
                'id' => (int)$disk['id'],
                'name' => (string)$disk['name'],
                'uuid' => (string)$disk['uuid'],
                'mount_path' => $path,
                'total_bytes' => (int)$total,
                'free_bytes' => (int)$free,
                'reserved_bytes' => $reserved,
                'usable_bytes' => max(0, (int)$free - $reserved),
            ];
        }
        usort($out, static fn($a, $b) => $b['usable_bytes'] <=> $a['usable_bytes']);
        return $out;
    }

    /** @return Generator<int,array<string,mixed>> */
    private static function scanSource(string $source): Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $node) {
            /** @var SplFileInfo $node */
            $path = $node->getPathname();
            $rel = ltrim(substr($path, strlen($source)), '/');
            if ($rel === '') continue;
            $top = explode('/', $rel)[0];
            if ($top === DiskDetector::MARKER_FILE || $top === 'lost+found' || str_starts_with($top, '.appdashboard-')) continue;
            if ($node->isLink()) {
                yield ['rel_path' => $rel, 'type' => 'link', 'size' => 0, 'link_target' => (string)@readlink($path), 'mtime' => (int)@filemtime($path)];
            } elseif ($node->isDir()) {
                yield ['rel_path' => $rel, 'type' => 'dir', 'size' => 0, 'mtime' => (int)@filemtime($path)];
            } elseif ($node->isFile()) {
                yield ['rel_path' => $rel, 'type' => 'file', 'size' => (int)$node->getSize(), 'mtime' => (int)$node->getMTime()];
            }
        }
    }

    /**
     * Create the migration record and hand the actual work to a CLI worker.
     *
     * @return array<string,mixed>
     */
    public static function start(int $diskId): array
    {
        StorageDb::ensureSchema();
        $active = StorageDb::activeMigration();
        if ($active !== null) throw new RuntimeException('migration_already_running');
        $disk = StorageDb::diskById($diskId);
        if ($disk === null) throw new InvalidArgumentException('disk_not_found');
        if ((string)$disk['status'] !== 'online') throw new RuntimeException('disk_not_online');
        $precheck = self::precheck($diskId);
        if (!$precheck['enough_space']) throw new RuntimeException('insufficient_space');
        if ($precheck['conflicts_count'] > 0) throw new RuntimeException('conflicts_require_resolution');

        $id = bin2hex(random_bytes(8));
        $metadata = [
            'targets' => $precheck['targets'],
            'reserve_percent' => $precheck['reserve_percent'],
            'pre_backup' => 'pending',
            'post_backup' => 'pending',
            'phase_started_at' => time(),
        ];
        StorageDb::createMigration($id, $diskId, $metadata);
        $count = 0;
        foreach (self::scanSource(rtrim((string)$disk['mount_path'], '/')) as $entry) {
            StorageDb::addMigrationFile($id, $entry['rel_path'], (int)$entry['size'], $entry['type']);
            if (++$count > 2000000) throw new RuntimeException('source_too_large');
        }
        StorageDb::updateMigration($id, [
            'status' => 'checking',
            'total_files' => $precheck['source_files'],
            'total_bytes' => $precheck['source_bytes'],
            'progress' => 0.0,
        ]);
        StorageDb::addEvent('migration_started', 'Safe removal started for ' . $disk['name'], $diskId, [
            'files' => $precheck['source_files'],
            'bytes' => $precheck['source_bytes'],
        ], $id);
        StorageManager::beginMaintenance($id);
        $spawn = StorageManager::spawnWorker($id);
        if (!$spawn['ok']) {
            StorageDb::updateMigration($id, ['status' => 'failed', 'error' => 'worker_start_failed: ' . $spawn['error']]);
            StorageManager::endMaintenance($id);
            throw new RuntimeException('worker_start_failed: ' . $spawn['error']);
        }
        return ['migration' => StorageDb::migration($id)];
    }

    /** Worker entry point. */
    public static function run(string $id): void
    {
        $lock = @fopen(storage_state_dir() . '/migration-' . $id . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return;
        }
        try {
            $migration = StorageDb::migration($id);
            if ($migration === null) return;
            if (in_array((string)$migration['status'], ['completed', 'failed'], true)) return;
            StorageManager::beginMaintenance($id);
            if ((int)$migration['started_at'] === 0) {
                StorageDb::updateMigration($id, ['started_at' => time()]);
            }
            self::execute($id);
        } catch (Throwable $e) {
            self::fail($id, 'worker_error: ' . $e->getMessage());
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    private static function execute(string $id): void
    {
        $migration = StorageDb::migration($id);
        if ($migration === null) return;
        $order = ['pending' => 0, 'checking' => 1, 'preparing' => 2, 'backup_running' => 3, 'migrating' => 4, 'verifying' => 5, 'removing_branch' => 6, 'completed' => 7];
        $status = (string)$migration['status'];
        if (in_array($status, ['paused', 'failed'], true)) {
            // Retry transient file errors. Conflicts are retried only after the
            // operator resolved them on disk; the copy step still refuses to
            // overwrite differing content.
            foreach (StorageDb::migrationFiles($id, ['error', 'conflict', 'copying']) as $file) {
                StorageDb::updateMigrationFile((int)$file['id'], ['status' => 'pending', 'error' => '']);
            }
            StorageDb::updateMigration($id, ['status' => 'checking', 'error' => '']);
            $status = 'checking';
        }
        $rank = $order[$status] ?? 0;

        if ($rank <= 1) {
            if (!self::phaseCheck($id)) return;
            $rank = 2;
        }
        if ($rank <= 2) {
            if (!self::phasePrepare($id)) return;
            $rank = 3;
        }
        if ($rank <= 3) {
            if (!self::phaseBackup($id)) return;
            $rank = 4;
        }
        if ($rank <= 4) {
            $result = self::phaseCopy($id);
            if ($result !== 'ok') return;
            $rank = 5;
        }
        if ($rank <= 5) {
            if (!self::phaseVerify($id)) return;
            $rank = 6;
        }
        if ($rank <= 6) {
            if (!self::phaseRemoveBranch($id)) return;
        }
        self::complete($id);
    }

    private static function phaseCheck(string $id): bool
    {
        StorageDb::updateMigration($id, ['status' => 'checking']);
        $migration = StorageDb::migration($id);
        if ($migration === null) return false;
        $disk = StorageDb::diskById((int)$migration['source_disk_id']);
        if ($disk === null) {
            self::fail($id, 'source_disk_missing', 'paused');
            return false;
        }
        $source = rtrim((string)$disk['mount_path'], '/');
        if (DiskDetector::findMount($source, DiskDetector::parseMountInfoFile(storage_mountinfo_path())) === null) {
            self::fail($id, 'source_disk_not_mounted', 'paused');
            return false;
        }
        if (self::isCancelled($id)) {
            self::fail($id, 'cancelled_by_user', 'paused');
            return false;
        }
        return true;
    }

    private static function phasePrepare(string $id): bool
    {
        StorageDb::updateMigration($id, ['status' => 'preparing']);
        $migration = StorageDb::migration($id);
        if ($migration === null) return false;
        $disk = StorageDb::diskById((int)$migration['source_disk_id']);
        if ($disk === null) return self::fail($id, 'source_disk_missing', 'paused');
        // Targets must still be online and writable.
        foreach ((array)($migration['metadata']['targets'] ?? []) as $target) {
            $path = (string)($target['mount_path'] ?? '');
            if ($path === '' || !is_dir($path) || !is_writable($path)) {
                return self::fail($id, 'target_disk_unavailable: ' . $path, 'paused');
            }
        }
        StorageDb::updateDisk((int)$disk['id'], ['status' => 'migrating']);
        storage_event('migration_prepared', 'Migrating files off ' . $disk['name'], (int)$disk['id'], [], $id);
        return true;
    }

    private static function phaseBackup(string $id): bool
    {
        $migration = StorageDb::migration($id);
        if ($migration === null) return false;
        $metadata = (array)($migration['metadata'] ?? []);
        if ((string)($metadata['pre_backup'] ?? '') === 'done') return true;
        if ((string)($metadata['pre_backup'] ?? '') === 'skipped') return true;
        StorageDb::updateMigration($id, ['status' => 'backup_running']);
        $settings = storage_settings();
        if (empty($settings['backup_required'])) {
            $metadata['pre_backup'] = 'skipped';
            StorageDb::updateMigration($id, ['metadata' => $metadata]);
            return true;
        }
        $result = StorageBackupManager::runBackup('pre_migration');
        if (!$result['ok']) {
            return self::fail($id, 'pre_migration_backup_failed: ' . $result['error'], 'failed');
        }
        $metadata['pre_backup'] = 'done';
        $metadata['pre_backup_job'] = $result['job_id'];
        StorageDb::updateMigration($id, ['metadata' => $metadata, 'status' => 'preparing']);
        return true;
    }

    /** @return 'ok'|'stopped' */
    private static function phaseCopy(string $id): string
    {
        StorageDb::updateMigration($id, ['status' => 'migrating']);
        $migration = StorageDb::migration($id);
        if ($migration === null) return 'stopped';
        $disk = StorageDb::diskById((int)$migration['source_disk_id']);
        if ($disk === null) { self::fail($id, 'source_disk_missing', 'paused'); return 'stopped'; }
        $source = rtrim((string)$disk['mount_path'], '/');
        $targets = [];
        foreach ((array)($migration['metadata']['targets'] ?? []) as $target) {
            $targets[(int)$target['id']] = $target;
        }
        if ($targets === []) { self::fail($id, 'no_target_disks', 'paused'); return 'stopped'; }
        $planned = [];
        $doneBytes = (int)$migration['migrated_bytes'];
        $doneFiles = (int)$migration['migrated_files'];
        $startedAt = time();
        $lastWrite = 0;
        $errors = 0;

        $files = StorageDb::migrationFiles($id, ['pending', 'copying', 'error']);
        foreach ($files as $file) {
            StorageManager::heartbeat($id);
            if (self::isCancelled($id)) {
                self::fail($id, 'cancelled_by_user', 'paused');
                return 'stopped';
            }
            $rel = (string)$file['rel_path'];
            $type = (string)($file['file_type'] ?? 'file');
            $srcPath = $source . '/' . $rel;

            if ($type === 'dir') {
                $target = self::targetFor($file, $targets, 0, $planned);
                if ($target === null) { self::fail($id, 'no_space_left', 'paused'); return 'stopped'; }
                $dst = rtrim((string)$target['mount_path'], '/') . '/' . $rel;
                if (!is_dir($dst) && !@mkdir($dst, 0775, true) && !is_dir($dst)) {
                    self::fileError($file, $id, 'mkdir_failed: ' . $rel);
                    self::fail($id, 'copy_failed: mkdir ' . $rel, 'failed');
                    return 'stopped';
                }
                StorageDb::updateMigrationFile((int)$file['id'], ['status' => 'done', 'target_disk_id' => (int)$target['id']]);
                continue;
            }

            if (!file_exists($srcPath) && !is_link($srcPath)) {
                self::fail($id, 'source_file_missing: ' . $rel, 'paused');
                return 'stopped';
            }

            $size = $type === 'link' ? 0 : (int)@filesize($srcPath);
            $target = self::targetFor($file, $targets, $size, $planned);
            if ($target === null) { self::fail($id, 'no_space_left', 'paused'); return 'stopped'; }
            $dst = rtrim((string)$target['mount_path'], '/') . '/' . $rel;
            $parent = dirname($dst);
            if (!is_dir($parent) && !@mkdir($parent, 0775, true) && !is_dir($parent)) {
                self::fileError($file, $id, 'mkdir_failed');
                self::fail($id, 'copy_failed: mkdir ' . dirname($rel), 'failed');
                return 'stopped';
            }

            if ($type === 'link') {
                $linkTarget = (string)@readlink($srcPath);
                if (is_link($dst)) {
                    if (readlink($dst) === $linkTarget) {
                        StorageDb::updateMigrationFile((int)$file['id'], ['status' => 'skipped', 'target_disk_id' => (int)$target['id'], 'checksum' => hash('sha256', $linkTarget)]);
                        $doneFiles++;
                        continue;
                    }
                    StorageDb::updateMigrationFile((int)$file['id'], ['status' => 'conflict', 'target_disk_id' => (int)$target['id'], 'error' => 'symlink_conflict']);
                    self::fail($id, 'conflict_requires_resolution: ' . $rel, 'paused');
                    return 'stopped';
                }
                if (!@symlink($linkTarget, $dst)) {
                    self::fileError($file, $id, 'symlink_failed');
                    self::fail($id, 'copy_failed: symlink ' . $rel, 'failed');
                    return 'stopped';
                }
                StorageDb::updateMigrationFile((int)$file['id'], ['status' => 'done', 'target_disk_id' => (int)$target['id'], 'checksum' => hash('sha256', $linkTarget)]);
                $doneFiles++;
                continue;
            }

            // Existing target: identical content is safe to reuse; anything else
            // is a conflict that requires an operator decision.
            if (file_exists($dst)) {
                $srcHash = self::hashFile($srcPath);
                $dstHash = is_file($dst) ? self::hashFile($dst) : '';
                if ($srcHash !== '' && $srcHash === $dstHash && filesize($dst) === $size) {
                    StorageDb::updateMigrationFile((int)$file['id'], ['status' => 'skipped', 'target_disk_id' => (int)$target['id'], 'checksum' => $srcHash]);
                    $doneBytes += $size;
                    $doneFiles++;
                    StorageDb::addEvent('migration_duplicate', 'Identical file already exists: ' . $rel, (int)$disk['id'], ['target' => $target['name']], $id);
                    continue;
                }
                StorageDb::updateMigrationFile((int)$file['id'], ['status' => 'conflict', 'target_disk_id' => (int)$target['id'], 'error' => 'content_differs']);
                storage_event('migration_conflict', 'Conflicting file requires resolution: ' . $rel, (int)$disk['id'], ['target' => $target['name']], $id);
                self::fail($id, 'conflict_requires_resolution: ' . $rel, 'paused');
                return 'stopped';
            }

            $tmp = $dst . '.appdashboard-part-' . bin2hex(random_bytes(4));
            $copy = self::copyFile($srcPath, $tmp);
            if (!$copy['ok']) {
                @unlink($tmp);
                self::fileError($file, $id, $copy['error']);
                self::fail($id, 'copy_failed: ' . $rel . ': ' . $copy['error'], 'failed');
                return 'stopped';
            }
            if ((int)$copy['size'] !== $size) {
                @unlink($tmp);
                self::fileError($file, $id, 'size_changed');
                self::fail($id, 'source_file_changed: ' . $rel, 'paused');
                return 'stopped';
            }
            if (!@rename($tmp, $dst)) {
                @unlink($tmp);
                self::fileError($file, $id, 'rename_failed');
                self::fail($id, 'copy_failed: rename ' . $rel, 'failed');
                return 'stopped';
            }
            @touch($dst, (int)@filemtime($srcPath));
            @chmod($dst, (int)@fileperms($srcPath) & 0777);
            $verify = self::hashFile($dst);
            if ($verify === '' || $verify !== $copy['checksum']) {
                self::fileError($file, $id, 'verification_failed');
                self::fail($id, 'copy_verification_failed: ' . $rel, 'failed');
                return 'stopped';
            }
            StorageDb::updateMigrationFile((int)$file['id'], [
                'status' => 'done',
                'target_disk_id' => (int)$target['id'],
                'checksum' => $copy['checksum'],
                'size_bytes' => $size,
            ]);
            $doneBytes += $size;
            $doneFiles++;
            $planned[(int)$target['id']] = (int)($planned[(int)$target['id']] ?? 0) + $size;

            $now = time();
            if ($now !== $lastWrite) {
                $lastWrite = $now;
                $elapsed = max(1, $now - $startedAt);
                StorageDb::updateMigration($id, [
                    'migrated_bytes' => $doneBytes,
                    'migrated_files' => $doneFiles,
                    'progress' => (int)$migration['total_bytes'] > 0 ? min(99.0, $doneBytes / (int)$migration['total_bytes'] * 100) : 0.0,
                    'current_file' => $rel,
                    'speed_bytes' => (float)($doneBytes / $elapsed),
                    'error_count' => $errors,
                ]);
            }
        }

        StorageDb::updateMigration($id, [
            'migrated_bytes' => $doneBytes,
            'migrated_files' => $doneFiles,
            'progress' => 99.0,
            'current_file' => '',
        ]);
        return 'ok';
    }

    private static function phaseVerify(string $id): bool
    {
        StorageDb::updateMigration($id, ['status' => 'verifying', 'current_file' => '']);
        $migration = StorageDb::migration($id);
        if ($migration === null) return false;
        $disk = StorageDb::diskById((int)$migration['source_disk_id']);
        if ($disk === null) return self::fail($id, 'source_disk_missing', 'paused');
        $source = rtrim((string)$disk['mount_path'], '/');
        $files = StorageDb::migrationFiles($id, ['done', 'skipped']);
        $byRel = [];
        foreach ($files as $file) $byRel[(string)$file['rel_path']] = $file;
        $failures = 0;
        foreach ($files as $file) {
            StorageManager::heartbeat($id);
            $target = StorageDb::diskById((int)$file['target_disk_id']);
            if ($target === null) { $failures++; continue; }
            $dst = rtrim((string)$target['mount_path'], '/') . '/' . (string)$file['rel_path'];
            $type = (string)($file['file_type'] ?? 'file');
            if ($type === 'dir') {
                if (!is_dir($dst)) $failures++;
                continue;
            }
            if ($type === 'link') {
                if (!is_link($dst) || readlink($dst) !== (string)@readlink($source . '/' . $file['rel_path'])) $failures++;
                continue;
            }
            if (!is_file($dst) || (int)@filesize($dst) !== (int)$file['size_bytes']) { $failures++; continue; }
            $hash = self::hashFile($dst);
            if ($hash === '' || $hash !== (string)$file['checksum']) $failures++;
        }

        // Source must not have changed or gained files while we copied.
        $seen = [];
        foreach (self::scanSource($source) as $entry) {
            $seen[$entry['rel_path']] = $entry;
            if (!isset($byRel[$entry['rel_path']])) {
                if ($entry['type'] === 'dir') continue;
                return self::fail($id, 'source_changed: new file ' . $entry['rel_path'], 'paused');
            }
            $row = $byRel[$entry['rel_path']];
            if ($entry['type'] === 'file' && (int)$row['size_bytes'] !== (int)$entry['size']) {
                return self::fail($id, 'source_changed: size ' . $entry['rel_path'], 'paused');
            }
        }
        foreach ($byRel as $rel => $row) {
            if ((string)($row['file_type'] ?? 'file') !== 'dir' && !isset($seen[$rel])) {
                return self::fail($id, 'source_changed: removed ' . $rel, 'paused');
            }
        }

        if ($failures > 0) {
            return self::fail($id, 'verification_failed: ' . $failures . ' file(s)', 'failed');
        }
        storage_event('migration_verified', 'All files verified on target disks', (int)$disk['id'], ['files' => count($files)], $id);
        return true;
    }

    private static function phaseRemoveBranch(string $id): bool
    {
        StorageDb::updateMigration($id, ['status' => 'removing_branch']);
        $migration = StorageDb::migration($id);
        if ($migration === null) return false;
        $disk = StorageDb::diskById((int)$migration['source_disk_id']);
        if ($disk === null) return self::fail($id, 'source_disk_missing', 'paused');
        $source = rtrim((string)$disk['mount_path'], '/');
        StorageDb::updateDisk((int)$disk['id'], ['status' => 'removing']);
        $requestedAt = time();
        StorageManager::requestReconcile();
        $deadline = time() + 180;
        while (time() < $deadline) {
            sleep(2);
            StorageManager::heartbeat($id);
            $branches = MergerFSManager::runtimeBranches(storage_root());
            if ($branches !== null) {
                $normalized = array_map(static fn($b) => rtrim($b, '/'), $branches);
                if (!in_array($source, $normalized, true)) break;
            } else {
                $reconcile = self::readJsonFile(storage_state_dir() . '/last-reconcile.json');
                $normalized = array_map(static fn($b) => rtrim((string)$b, '/'), (array)($reconcile['branches'] ?? []));
                if (!empty($reconcile['ok']) && (int)($reconcile['at'] ?? 0) >= $requestedAt && !in_array($source, $normalized, true)) break;
            }
        }
        $branches = MergerFSManager::runtimeBranches(storage_root());
        if ($branches !== null && in_array($source, array_map(static fn($b) => rtrim($b, '/'), $branches), true)) {
            StorageDb::updateDisk((int)$disk['id'], ['status' => 'online']);
            return self::fail($id, 'source_branch_still_active', 'paused');
        }
        if ($branches === null) {
            // Older mergerfs builds have no readable runtime interface. The only
            // acceptable proof is a successful watcher reconcile after our
            // request that no longer lists this branch.
            $reconcile = self::readJsonFile(storage_state_dir() . '/last-reconcile.json');
            $reconciledBranches = array_map(static fn($b) => rtrim((string)$b, '/'), (array)($reconcile['branches'] ?? []));
            $proof = !empty($reconcile['ok'])
                && (int)($reconcile['at'] ?? 0) >= $requestedAt
                && in_array((string)($reconcile['action'] ?? ''), ['mounted', 'remounted', 'runtime_update'], true)
                && !in_array($source, $reconciledBranches, true);
            if (!$proof) {
                StorageDb::updateDisk((int)$disk['id'], ['status' => 'online']);
                return self::fail($id, 'branch_removal_unverified', 'paused');
            }
        }

        // Verify every migrated path through the merged view after removal.
        $failures = 0;
        foreach (StorageDb::migrationFiles($id, ['done', 'skipped']) as $file) {
            $merged = rtrim(storage_root(), '/') . '/' . (string)$file['rel_path'];
            $type = (string)($file['file_type'] ?? 'file');
            if ($type === 'dir') { if (!is_dir($merged)) $failures++; continue; }
            if ($type === 'link') { if (!is_link($merged)) $failures++; continue; }
            if (!is_file($merged) || (int)@filesize($merged) !== (int)$file['size_bytes']) $failures++;
        }
        if ($failures > 0) {
            StorageDb::updateDisk((int)$disk['id'], ['status' => 'error']);
            return self::fail($id, 'merged_view_verification_failed: ' . $failures, 'failed');
        }

        if (!empty(storage_settings()['delete_source_after_migration'])) {
            self::deleteSourceData($source, $id);
        }

        StorageDb::updateDisk((int)$disk['id'], ['status' => 'ready_to_remove']);
        storage_event('disk_ready_to_remove', 'Disk ' . $disk['name'] . ' is ready to remove', (int)$disk['id'], [], $id);
        return true;
    }

    private static function deleteSourceData(string $source, string $id): void
    {
        // Only reached after a successful verification and branch removal.
        foreach (StorageDb::migrationFiles($id, ['done', 'skipped']) as $file) {
            if ((string)($file['file_type'] ?? 'file') === 'dir') continue;
            $path = $source . '/' . (string)$file['rel_path'];
            if (is_link($path) || is_file($path)) @unlink($path);
        }
        $dirs = StorageDb::migrationFiles($id, ['done', 'skipped']);
        usort($dirs, static fn($a, $b) => strlen((string)$b['rel_path']) <=> strlen((string)$a['rel_path']));
        foreach ($dirs as $file) {
            if ((string)($file['file_type'] ?? 'file') !== 'dir') continue;
            @rmdir($source . '/' . (string)$file['rel_path']);
        }
    }

    private static function complete(string $id): void
    {
        $migration = StorageDb::migration($id);
        if ($migration === null) return;
        $metadata = (array)($migration['metadata'] ?? []);
        $metadata['phase_started_at'] = time();
        $metadata['post_backup'] = 'pending';
        StorageDb::updateMigration($id, [
            'status' => 'completed',
            'progress' => 100.0,
            'completed_at' => time(),
            'current_file' => '',
            'speed_bytes' => 0.0,
            'metadata' => $metadata,
        ]);
        storage_event('migration_completed', 'Disk migration completed; disk is ready to remove', (int)$migration['source_disk_id'], [], $id);
        StorageManager::endMaintenance($id);

        if (!empty(storage_settings()['backup_required'])) {
            $result = StorageBackupManager::runBackup('post_migration');
            $metadata['post_backup'] = $result['ok'] ? 'done' : 'failed';
            $metadata['post_backup_error'] = $result['error'];
            StorageDb::updateMigration($id, ['metadata' => $metadata]);
            if (!$result['ok']) {
                storage_event('backup_failed', 'Post-migration backup failed: ' . $result['error'], (int)$migration['source_disk_id'], [], $id);
            }
        }
        MigrationRecovery::notify();
    }

    private static function targetFor(array $file, array $targets, int $size, array $planned): ?array
    {
        $preferred = (int)($file['target_disk_id'] ?? 0);
        if ($preferred > 0 && isset($targets[$preferred])) {
            $target = $targets[$preferred];
            $usable = (int)$target['usable_bytes'] - (int)($planned[$preferred] ?? 0);
            if ($usable >= $size) return $target;
        }
        $best = null;
        $bestFree = -1;
        foreach ($targets as $target) {
            $usable = (int)$target['usable_bytes'] - (int)($planned[(int)$target['id']] ?? 0);
            if ($usable < $size) continue;
            if ($usable > $bestFree) {
                $bestFree = $usable;
                $best = $target;
            }
        }
        return $best;
    }

    private static function fileError(array $file, string $migrationId, string $error): void
    {
        StorageDb::updateMigrationFile((int)$file['id'], ['status' => 'error', 'error' => $error]);
    }

    /** @return array{ok:bool,size:int,checksum:string,error:string} */
    private static function copyFile(string $src, string $dst): array
    {
        $in = @fopen($src, 'rb');
        if ($in === false) return ['ok' => false, 'size' => 0, 'checksum' => '', 'error' => 'open_source_failed'];
        $out = @fopen($dst, 'wb');
        if ($out === false) { fclose($in); return ['ok' => false, 'size' => 0, 'checksum' => '', 'error' => 'open_target_failed']; }
        $hash = hash_init('sha256');
        $size = 0;
        $ok = true;
        while (!feof($in)) {
            $chunk = fread($in, self::CHUNK);
            if ($chunk === false) { $ok = false; break; }
            if ($chunk === '') continue;
            hash_update($hash, $chunk);
            $written = fwrite($out, $chunk);
            if ($written !== strlen($chunk)) { $ok = false; break; }
            $size += $written;
        }
        @fflush($out);
        if (function_exists('fsync')) @fsync($out);
        fclose($in);
        fclose($out);
        if (!$ok) return ['ok' => false, 'size' => $size, 'checksum' => '', 'error' => 'read_write_failed'];
        return ['ok' => true, 'size' => $size, 'checksum' => hash_final($hash), 'error' => ''];
    }

    private static function hashFile(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) return '';
        $fp = @fopen($path, 'rb');
        if ($fp === false) return '';
        $hash = hash_init('sha256');
        while (!feof($fp)) {
            $chunk = fread($fp, self::CHUNK);
            if ($chunk === false) { fclose($fp); return ''; }
            if ($chunk !== '') hash_update($hash, $chunk);
        }
        fclose($fp);
        return hash_final($hash);
    }

    private static function isCancelled(string $id): bool
    {
        $current = StorageDb::migration($id);
        return $current !== null && (int)($current['cancel_requested'] ?? 0) === 1;
    }

    /** @return array<string,mixed> */
    private static function readMergerfsState(): array
    {
        return self::readJsonFile(storage_state_dir() . '/mergerfs.json');
    }

    /** @return array<string,mixed> */
    private static function readJsonFile(string $path): array
    {
        if (!is_readable($path)) return [];
        $data = json_decode((string)@file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private static function fail(string $id, string $error, string $mode = 'failed'): bool
    {
        $migration = StorageDb::migration($id);
        if ($migration === null) return false;
        $status = $mode === 'paused' ? 'paused' : 'failed';
        $fields = ['status' => $status, 'error' => substr($error, 0, 4000), 'current_file' => ''];
        if ($status === 'failed') $fields['completed_at'] = time();
        StorageDb::updateMigration($id, $fields);
        $diskId = (int)$migration['source_disk_id'];
        $disk = StorageDb::diskById($diskId);
        if ($disk !== null && in_array((string)$disk['status'], ['migrating', 'removing'], true)) {
            // Never leave a source looking removable after a failed migration.
            StorageDb::updateDisk($diskId, ['status' => 'online']);
        }
        storage_event($status === 'paused' ? 'migration_paused' : 'migration_failed', $error, $diskId, [], $id);
        StorageManager::endMaintenance($id);
        return false;
    }

    /** Resume a paused or failed migration from its last checkpoint. */
    public static function resume(string $id): array
    {
        StorageDb::ensureSchema();
        $migration = StorageDb::migration($id);
        if ($migration === null) throw new InvalidArgumentException('migration_not_found');
        if ((string)$migration['status'] === 'completed') throw new RuntimeException('migration_already_completed');
        $active = StorageDb::activeMigration();
        if ($active !== null && (string)$active['id'] !== $id) throw new RuntimeException('migration_already_running');
        $disk = StorageDb::diskById((int)$migration['source_disk_id']);
        if ($disk === null) throw new RuntimeException('source_disk_missing');
        $source = rtrim((string)$disk['mount_path'], '/');
        if (DiskDetector::findMount($source, DiskDetector::parseMountInfoFile(storage_mountinfo_path())) === null) {
            throw new RuntimeException('source_disk_not_mounted');
        }
        StorageDb::updateMigration($id, ['status' => 'checking', 'error' => '', 'cancel_requested' => 0]);
        StorageManager::beginMaintenance($id);
        $spawn = StorageManager::spawnWorker($id);
        if (!$spawn['ok']) {
            StorageDb::updateMigration($id, ['status' => 'paused', 'error' => 'worker_start_failed: ' . $spawn['error']]);
            StorageManager::endMaintenance($id);
            throw new RuntimeException('worker_start_failed: ' . $spawn['error']);
        }
        storage_event('migration_resumed', 'Migration resumed from checkpoint', (int)$disk['id'], [], $id);
        return StorageDb::migration($id) ?? $migration;
    }

    public static function cancel(string $id): void
    {
        $migration = StorageDb::migration($id);
        if ($migration === null) throw new InvalidArgumentException('migration_not_found');
        if (in_array((string)$migration['status'], ['completed', 'failed'], true)) return;
        StorageDb::updateMigration($id, ['cancel_requested' => 1]);
        storage_event('migration_cancel_requested', 'Cancellation requested', (int)$migration['source_disk_id'], [], $id);
    }
}
