<?php
function isDriveMountVisible($mount) {
    return is_string($mount) && $mount !== '';
}

function isIgnoredDriveFsType($fsType) {
    static $ignored = [
        'autofs', 'binfmt_misc', 'bpf', 'cgroup2', 'configfs', 'debugfs', 'devpts', 'devtmpfs',
        'efivarfs', 'fusectl', 'hugetlbfs', 'mqueue', 'nsfs', 'overlay', 'proc', 'pstore',
        'securityfs', 'squashfs', 'sysfs', 'tmpfs', 'tracefs'
    ];
    return in_array(strtolower((string)$fsType), $ignored, true);
}

function isIgnoredDriveDeviceType($type): bool {
    return in_array(strtolower((string)$type), ['loop', 'rom'], true);
}

function isNetworkDriveFsType($fsType) {
    static $network = [
        'cifs', 'davfs', 'davfs2', 'fuse.sshfs', 'fuse.rclone', 'glusterfs', 'nfs', 'nfs4',
        'smb3', 'sshfs'
    ];
    return in_array(strtolower((string)$fsType), $network, true);
}

function classifyDriveTypeLabel($mount, $fsType, $source) {
    $mount = (string)$mount;
    $source = (string)$source;
    if (isNetworkDriveFsType($fsType)) {
        return 'Network Share';
    }
    if ($mount === '/') {
        return 'System Disk';
    }
    if ($mount === '/boot' || $mount === '/boot/efi') {
        return 'Boot Partition';
    }
    if (str_starts_with($source, '/dev/')) {
        return 'Mounted Disk';
    }
    return 'Mounted Filesystem';
}

function classifyBlockDriveTypeLabel(array $device, ?array $parent = null): string {
    $tran = strtolower((string)($device['tran'] ?? $parent['tran'] ?? ''));
    $rm = (int)($device['rm'] ?? $parent['rm'] ?? 0);
    $hotplug = (int)($device['hotplug'] ?? $parent['hotplug'] ?? 0);
    if ($tran === 'usb' || $rm === 1 || $hotplug === 1) {
        return 'External Disk';
    }
    if (($device['mountpoint'] ?? '') === '/' || in_array('/', (array)($device['mountpoints'] ?? []), true)) {
        return 'System Disk';
    }
    return 'Block Device';
}

function driveSortOrder($mount, $isNetwork) {
    if ($mount === '/') {
        return 0;
    }
    if ($mount === '/boot') {
        return 1;
    }
    if ($mount === '/boot/efi') {
        return 2;
    }
    return $isNetwork ? 20 : 10;
}

function smartFindPrimaryDiskByMount(array $devices, string $targetMount): ?array {
    foreach ($devices as $device) {
        $mounts = $device['mountpoints'] ?? [];
        if (is_array($mounts) && in_array($targetMount, $mounts, true)) {
            return $device;
        }
        $children = $device['children'] ?? [];
        if (is_array($children) && $children !== []) {
            $match = smartFindPrimaryDiskByMount($children, $targetMount);
            if ($match !== null) {
                if (($device['type'] ?? '') === 'disk') {
                    return $device;
                }
                return $match;
            }
        }
    }
    return null;
}

function smartResolveRootDevice(): ?string {
    static $cached = false;
    static $devicePath = null;
    if ($cached) {
        return $devicePath;
    }
    $cached = true;
    $json = @shell_exec('lsblk -J -o NAME,PATH,TYPE,MOUNTPOINTS 2>/dev/null');
    if (!is_string($json) || trim($json) === '') {
        return null;
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return null;
    }
    $match = smartFindPrimaryDiskByMount($decoded['blockdevices'] ?? [], '/');
    $devicePath = is_array($match) ? (string)($match['path'] ?? '') : '';
    return $devicePath !== '' ? $devicePath : null;
}

function smartAttributesByName(array $table): array {
    $map = [];
    foreach ($table as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = (string)($row['name'] ?? '');
        if ($name !== '') {
            $map[$name] = $row;
        }
        $id = isset($row['id']) ? (int)$row['id'] : null;
        if ($id !== null) {
            $map['id:' . $id] = $row;
        }
    }
    return $map;
}

function smartAttributeRow(array $attributes, string $name, ?int $id = null): ?array {
    if (isset($attributes[$name]) && is_array($attributes[$name])) {
        return $attributes[$name];
    }
    if ($id !== null) {
        $key = 'id:' . $id;
        if (isset($attributes[$key]) && is_array($attributes[$key])) {
            return $attributes[$key];
        }
    }
    return null;
}

function smartAttributeRaw(array $attributes, string $name, ?int $id = null): ?int {
    $row = smartAttributeRow($attributes, $name, $id);
    if ($row === null) {
        return null;
    }
    if (isset($row['raw']['value']) && is_numeric($row['raw']['value'])) {
        return (int)$row['raw']['value'];
    }
    return null;
}

function smartAttributeValue(array $attributes, string $name, ?int $id = null): ?int {
    $row = smartAttributeRow($attributes, $name, $id);
    if ($row === null || !isset($row['value']) || !is_numeric($row['value'])) {
        return null;
    }
    return (int)$row['value'];
}

function smartNormalizeStatus(array $smart): string {
    if (($smart['health_passed'] ?? null) === false) {
        return 'critical';
    }
    $criticalChecks = [
        (int)($smart['reallocated_sectors'] ?? 0),
        (int)($smart['reserved_block_count'] ?? 0),
        (int)($smart['program_fail_count'] ?? 0),
        (int)($smart['erase_fail_count'] ?? 0),
        (int)($smart['runtime_bad_blocks'] ?? 0),
        (int)($smart['uncorrectable_error_count'] ?? 0),
        (int)($smart['error_log_count'] ?? 0),
    ];
    foreach ($criticalChecks as $value) {
        if ($value > 0) {
            return 'critical';
        }
    }
    $temp = $smart['temperature_c'] ?? null;
    if (is_numeric($temp) && (int)$temp >= 60) {
        return 'critical';
    }
    $life = $smart['life_remaining_percent'] ?? null;
    if (is_numeric($life) && (int)$life <= 10) {
        return 'critical';
    }
    if ((int)($smart['crc_error_count'] ?? 0) > 0) {
        return 'warning';
    }
    if (is_numeric($temp) && (int)$temp >= 50) {
        return 'warning';
    }
    if (is_numeric($life) && (int)$life <= 25) {
        return 'warning';
    }
    if (($smart['health_passed'] ?? null) === true) {
        return 'good';
    }
    return 'unknown';
}

function smartCollectPhysicalDisks(): array {
    $devices = [];
    $scan = @shell_exec('sudo -n /usr/sbin/smartctl --scan-open 2>/dev/null');
    if (!is_string($scan) || trim($scan) === '') {
        $scan = @shell_exec('/usr/sbin/smartctl --scan-open 2>/dev/null');
    }
    if (is_string($scan) && trim($scan) !== '') {
        foreach (explode("\n", trim($scan)) as $line) {
            if (preg_match('/^(\/dev\/\S+)(?:\s+-d\s+(\S+))?/', trim($line), $m)) {
                $devices[$m[1] . '|' . ($m[2] ?? '')] = [
                    'path' => $m[1],
                    'type' => $m[2] ?? '',
                ];
            }
        }
    }

    $json = @shell_exec('lsblk -J -o NAME,PATH,TYPE,TRAN,RM,HOTPLUG 2>/dev/null');
    if (is_string($json) && trim($json) !== '') {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $walk = null;
            $walk = static function(array $items) use (&$walk, &$devices) {
                foreach ($items as $device) {
                    if (($device['type'] ?? '') === 'disk') {
                        $path = $device['path'] ?? '';
                        if ($path !== '' && str_starts_with($path, '/dev/')) {
                            $devices[$path . '|'] = ['path' => $path, 'type' => ''];
                        }
                    }
                    $children = $device['children'] ?? [];
                    if (is_array($children) && $children !== []) {
                        $walk($children);
                    }
                }
            };
            $walk($decoded['blockdevices'] ?? []);
        }
    }
    $deduped = [];
    foreach ($devices as $device) {
        $path = (string)($device['path'] ?? '');
        if ($path === '') {
            continue;
        }
        if (!isset($deduped[$path]) || (($deduped[$path]['type'] ?? '') === '' && ($device['type'] ?? '') !== '')) {
            $deduped[$path] = $device;
        }
    }
    return array_values($deduped);
}

function smartParseSmartctlOutput($device): ?array {
    $devicePath = is_array($device) ? (string)($device['path'] ?? '') : (string)$device;
    $deviceType = is_array($device) ? (string)($device['type'] ?? '') : '';
    if ($devicePath === '') {
        return null;
    }
    $typeArg = $deviceType !== '' ? ' -d ' . escapeshellarg($deviceType) : '';
    $command = 'sudo -n /usr/sbin/smartctl -j -a' . $typeArg . ' ' . escapeshellarg($devicePath) . ' 2>/dev/null';
    $rawOutput = @shell_exec($command);
    if ((!is_string($rawOutput) || trim($rawOutput) === '') && $deviceType !== '') {
        $rawOutput = @shell_exec('sudo -n /usr/sbin/smartctl -j -a ' . escapeshellarg($devicePath) . ' 2>/dev/null');
    }
    if (!is_string($rawOutput) || trim($rawOutput) === '') {
        return null;
    }

    $decoded = json_decode($rawOutput, true);
    if (!is_array($decoded)) {
        return null;
    }

    // Check exit status — bit 1 (value 2) means no SMART support
    $exitStatus = (int)($decoded['smartctl']['exit_status'] ?? 0);
    if (($exitStatus & 2) === 2) {
        return null;
    }

    $messages = [];
    foreach (($decoded['smartctl']['messages'] ?? []) as $message) {
        if (!is_array($message)) continue;
        $messages[] = (string)($message['string'] ?? '');
    }

    // Try ATA attributes first, then NVMe
    $attributes = smartAttributesByName($decoded['ata_smart_attributes']['table'] ?? []);
    $isNvme = empty($attributes) && isset($decoded['nvme_smart_health_information_log']);

    $writtenLbas = $isNvme ? null : smartAttributeRaw($attributes, 'Total_LBAs_Written', 241);
    $lifeRemaining = $isNvme ? null : smartAttributeValue($attributes, 'Wear_Leveling_Count', 177);
    if (!is_numeric($lifeRemaining) || $lifeRemaining < 0 || $lifeRemaining > 100) {
        $lifeRemaining = null;
    }

    // For NVMe, extract temperature and power-on from the NVMe log
    $nvmeLog = $decoded['nvme_smart_health_information_log'] ?? null;
    $temperatureC = null;
    if (isset($decoded['temperature']['current']) && is_numeric($decoded['temperature']['current'])) {
        $temperatureC = (int)$decoded['temperature']['current'];
    } elseif ($isNvme && isset($nvmeLog['temperature']) && is_numeric($nvmeLog['temperature'])) {
        $temperatureC = (int)$nvmeLog['temperature'];
    } else {
        $temperatureC = smartAttributeRaw($attributes, 'Airflow_Temperature_Cel', 190);
    }

    $powerOnHours = null;
    if (isset($decoded['power_on_time']['hours']) && is_numeric($decoded['power_on_time']['hours'])) {
        $powerOnHours = (int)$decoded['power_on_time']['hours'];
    } elseif ($isNvme && isset($nvmeLog['power_on_hours']) && is_numeric($nvmeLog['power_on_hours'])) {
        $powerOnHours = (int)$nvmeLog['power_on_hours'];
    } else {
        $powerOnHours = smartAttributeRaw($attributes, 'Power_On_Hours', 9);
    }

    $smart = [
        'available' => true,
        'device' => $devicePath,
        'device_type' => $deviceType,
        'model' => (string)($decoded['model_name'] ?? $decoded['model_family'] ?? ''),
        'serial' => (string)($decoded['serial_number'] ?? ''),
        'firmware' => (string)($decoded['firmware_version'] ?? ''),
        'capacity_bytes' => isset($decoded['user_capacity']['bytes']) && is_numeric($decoded['user_capacity']['bytes']) ? (int)$decoded['user_capacity']['bytes'] : null,
        'health_passed' => array_key_exists('passed', $decoded['smart_status'] ?? []) ? (bool)$decoded['smart_status']['passed'] : null,
        'temperature_c' => $temperatureC,
        'power_on_hours' => $powerOnHours,
        'power_cycle_count' => isset($decoded['power_cycle_count']) && is_numeric($decoded['power_cycle_count']) ? (int)$decoded['power_cycle_count'] : smartAttributeRaw($attributes, 'Power_Cycle_Count', 12),
        'data_written_bytes' => $writtenLbas !== null ? (int)($writtenLbas * 512) : null,
        'wear_used_percent' => $lifeRemaining !== null ? max(0, min(100, 100 - (int)$lifeRemaining)) : null,
        'life_remaining_percent' => $lifeRemaining !== null ? (int)$lifeRemaining : null,
        'wear_leveling_raw' => smartAttributeRaw($attributes, 'Wear_Leveling_Count', 177),
        'reallocated_sectors' => smartAttributeRaw($attributes, 'Reallocated_Sector_Ct', 5) ?? 0,
        'reserved_block_count' => smartAttributeRaw($attributes, 'Used_Rsvd_Blk_Cnt_Tot', 179) ?? 0,
        'program_fail_count' => smartAttributeRaw($attributes, 'Program_Fail_Cnt_Total', 181) ?? 0,
        'erase_fail_count' => smartAttributeRaw($attributes, 'Erase_Fail_Count_Total', 182) ?? 0,
        'runtime_bad_blocks' => smartAttributeRaw($attributes, 'Runtime_Bad_Block', 183) ?? 0,
        'uncorrectable_error_count' => smartAttributeRaw($attributes, 'Uncorrectable_Error_Cnt', 187) ?? 0,
        'crc_error_count' => smartAttributeRaw($attributes, 'CRC_Error_Count', 199) ?? 0,
        'error_log_count' => isset($decoded['ata_smart_error_log']['summary']['count']) && is_numeric($decoded['ata_smart_error_log']['summary']['count']) ? (int)$decoded['ata_smart_error_log']['summary']['count'] : 0,
        'completed_self_tests' => isset($decoded['ata_smart_self_test_log']['standard']['count']) && is_numeric($decoded['ata_smart_self_test_log']['standard']['count']) ? (int)$decoded['ata_smart_self_test_log']['standard']['count'] : 0,
        'messages' => array_values(array_filter($messages, static fn($m) => $m !== '')),
        'updated_at' => time(),
        'self_tests' => [],
    ];

    foreach (array_slice($decoded['ata_smart_self_test_log']['standard']['table'] ?? [], 0, 5) as $test) {
        if (!is_array($test)) continue;
        $smart['self_tests'][] = [
            'type' => (string)($test['type']['string'] ?? ''),
            'status' => (string)($test['status']['string'] ?? ''),
            'passed' => array_key_exists('passed', $test['status'] ?? []) ? (bool)$test['status']['passed'] : null,
            'remaining_percent' => isset($test['status']['remaining_percent']) && is_numeric($test['status']['remaining_percent']) ? (int)$test['status']['remaining_percent'] : null,
            'lifetime_hours' => isset($test['lifetime_hours']) && is_numeric($test['lifetime_hours']) ? (int)$test['lifetime_hours'] : null,
        ];
    }

    $smart['status'] = smartNormalizeStatus($smart);
    return $smart;
}

function getSmartDiagnostics(): array {
    $allDisks = smartCollectPhysicalDisks();

    // Fallback: if lsblk returned nothing, try root device
    if (empty($allDisks)) {
        $root = smartResolveRootDevice();
        if ($root !== null) {
            $allDisks = [$root];
        }
    }

    $devices = [];
    foreach ($allDisks as $device) {
        $smart = smartParseSmartctlOutput($device);
        if ($smart !== null) {
            $devices[] = $smart;
        }
    }

    return [
        'devices' => $devices,
        'updated_at' => time(),
    ];
}

function monitorEnsureSmartTable(): void {
    try {
        $db = getDB();
        if (!$db) return;
        monitorEnsureBaseSchema();
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_smart_snapshots (
            id BIGSERIAL PRIMARY KEY,
            created_at INTEGER NOT NULL,
            device_path TEXT NOT NULL,
            model TEXT DEFAULT '',
            serial TEXT DEFAULT '',
            firmware TEXT DEFAULT '',
            capacity_bytes BIGINT,
            health_passed INTEGER,
            temperature_c INTEGER,
            power_on_hours BIGINT,
            power_cycle_count INTEGER,
            data_written_bytes BIGINT,
            wear_used_percent INTEGER,
            life_remaining_percent INTEGER,
            reallocated_sectors INTEGER DEFAULT 0,
            reserved_block_count INTEGER DEFAULT 0,
            program_fail_count INTEGER DEFAULT 0,
            erase_fail_count INTEGER DEFAULT 0,
            runtime_bad_blocks INTEGER DEFAULT 0,
            uncorrectable_error_count INTEGER DEFAULT 0,
            crc_error_count INTEGER DEFAULT 0,
            error_log_count INTEGER DEFAULT 0,
            completed_self_tests INTEGER DEFAULT 0,
            status TEXT DEFAULT 'unknown',
            messages_json TEXT DEFAULT '[]',
            self_tests_json TEXT DEFAULT '[]'
        )");
        monitorEnsureColumn($db, 'monitor_smart_snapshots', 'capacity_bytes', 'BIGINT');
        monitorEnsureColumn($db, 'monitor_smart_snapshots', 'power_on_hours', 'BIGINT');
        monitorEnsureColumn($db, 'monitor_smart_snapshots', 'data_written_bytes', 'BIGINT');
        if (function_exists('app_db_is_pgsql') && app_db_is_pgsql()) {
            $db->exec("ALTER TABLE monitor_smart_snapshots ALTER COLUMN capacity_bytes TYPE BIGINT");
            $db->exec("ALTER TABLE monitor_smart_snapshots ALTER COLUMN power_on_hours TYPE BIGINT");
            $db->exec("ALTER TABLE monitor_smart_snapshots ALTER COLUMN data_written_bytes TYPE BIGINT");
        }
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_smart_device_time
            ON monitor_smart_snapshots(device_path, created_at)");
    } catch (Exception $e) {
        error_log('Failed to create monitor_smart_snapshots table: ' . $e->getMessage());
    }
}

function monitorStoreSmartSnapshots(array $smartDevices): void {
    if (empty($smartDevices)) return;
    try {
        $db = getDB();
        if (!$db) return;

        $now = time();
        $stmt = $db->prepare("INSERT INTO monitor_smart_snapshots
            (created_at, device_path, model, serial, firmware, capacity_bytes,
             health_passed, temperature_c, power_on_hours, power_cycle_count,
             data_written_bytes, wear_used_percent, life_remaining_percent,
             reallocated_sectors, reserved_block_count, program_fail_count,
             erase_fail_count, runtime_bad_blocks, uncorrectable_error_count,
             crc_error_count, error_log_count, completed_self_tests, status,
             messages_json, self_tests_json)
            VALUES(:created_at, :device_path, :model, :serial, :firmware, :capacity_bytes,
                   :health_passed, :temperature_c, :power_on_hours, :power_cycle_count,
                   :data_written_bytes, :wear_used_percent, :life_remaining_percent,
                   :reallocated_sectors, :reserved_block_count, :program_fail_count,
                   :erase_fail_count, :runtime_bad_blocks, :uncorrectable_error_count,
                   :crc_error_count, :error_log_count, :completed_self_tests, :status,
                   :messages_json, :self_tests_json)");

        foreach ($smartDevices as $device) {
            if (!is_array($device)) continue;
            $stmt->bindValue(':created_at', $now, PDO::PARAM_INT);
            $stmt->bindValue(':device_path', (string)($device['device'] ?? ''), PDO::PARAM_STR);
            $stmt->bindValue(':model', (string)($device['model'] ?? ''), PDO::PARAM_STR);
            $stmt->bindValue(':serial', (string)($device['serial'] ?? ''), PDO::PARAM_STR);
            $stmt->bindValue(':firmware', (string)($device['firmware'] ?? ''), PDO::PARAM_STR);
            $stmt->bindValue(':capacity_bytes', isset($device['capacity_bytes']) && is_numeric($device['capacity_bytes']) ? (int)$device['capacity_bytes'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':health_passed', isset($device['health_passed']) ? ($device['health_passed'] ? 1 : 0) : null, PDO::PARAM_INT);
            $stmt->bindValue(':temperature_c', isset($device['temperature_c']) && is_numeric($device['temperature_c']) ? (int)$device['temperature_c'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':power_on_hours', isset($device['power_on_hours']) && is_numeric($device['power_on_hours']) ? (int)$device['power_on_hours'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':power_cycle_count', isset($device['power_cycle_count']) && is_numeric($device['power_cycle_count']) ? (int)$device['power_cycle_count'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':data_written_bytes', isset($device['data_written_bytes']) && is_numeric($device['data_written_bytes']) ? (int)$device['data_written_bytes'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':wear_used_percent', isset($device['wear_used_percent']) && is_numeric($device['wear_used_percent']) ? (int)$device['wear_used_percent'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':life_remaining_percent', isset($device['life_remaining_percent']) && is_numeric($device['life_remaining_percent']) ? (int)$device['life_remaining_percent'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':reallocated_sectors', (int)($device['reallocated_sectors'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':reserved_block_count', (int)($device['reserved_block_count'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':program_fail_count', (int)($device['program_fail_count'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':erase_fail_count', (int)($device['erase_fail_count'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':runtime_bad_blocks', (int)($device['runtime_bad_blocks'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':uncorrectable_error_count', (int)($device['uncorrectable_error_count'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':crc_error_count', (int)($device['crc_error_count'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':error_log_count', (int)($device['error_log_count'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':completed_self_tests', (int)($device['completed_self_tests'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':status', (string)($device['status'] ?? 'unknown'), PDO::PARAM_STR);
            $stmt->bindValue(':messages_json', json_encode($device['messages'] ?? [], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            $stmt->bindValue(':self_tests_json', json_encode($device['self_tests'] ?? [], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            $stmt->execute();
        }
    } catch (Exception $e) {
        error_log('Failed to store SMART snapshots: ' . $e->getMessage());
    }
}

function runPowerShell($command) {
    return '';
}

/**
 * Get CPU load percentage using wmic command
 */
function getCpuUsage() {
    $stat1 = file('/proc/stat');
    preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat1[0], $m);
    $idle1 = (float)$m[4]; $total1 = array_sum(array_slice($m, 1));
    usleep(200000);
    $stat2 = file('/proc/stat');
    preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat2[0], $m);
    $idle2 = (float)$m[4]; $total2 = array_sum(array_slice($m, 1));
    $totalDiff = $total2 - $total1;
    $idleDiff = $idle2 - $idle1;
    return $totalDiff > 0 ? round(100.0 * (1.0 - $idleDiff / $totalDiff), 1) : 0.0;
}

/**
 * Get RAM usage using wmic command
 * Returns memory usage percentage
 */
function getMemoryUsage() {
    $meminfo = @file_get_contents('/proc/meminfo');
    if (!is_string($meminfo)) {
        return 0.0;
    }

    $values = [];
    foreach (explode("\n", $meminfo) as $line) {
        if (preg_match('/^([A-Za-z0-9_()]+):\s+(\d+)/', $line, $m)) {
            $values[$m[1]] = (float)$m[2];
        }
    }

    $total = $values['MemTotal'] ?? 0.0;
    if ($total <= 0) {
        return 0.0;
    }

    $free = $values['MemFree'] ?? 0.0;
    $buffers = $values['Buffers'] ?? 0.0;
    $cached = $values['Cached'] ?? 0.0;
    $sReclaimable = $values['SReclaimable'] ?? 0.0;

    $used = max(0.0, $total - $free - $buffers - $cached - $sReclaimable);
    return round(min(100.0, ($used / $total) * 100), 1);
}

/**
 * Get Virtual Memory (swap) usage using systeminfo command
 * Returns swap usage percentage
 */
function getSwapUsage() {
    $meminfo = @file_get_contents('/proc/meminfo');
    if (!is_string($meminfo)) {
        $free = @shell_exec('free -b 2>/dev/null');
        if (is_string($free) && preg_match('/^Swap:\s+(\d+)\s+(\d+)\s+(\d+)/m', $free, $m)) {
            $total = (float)$m[1];
            $used = (float)$m[2];
            return $total > 0 ? round(($used / $total) * 100, 1) : 0.0;
        }
        return 0.0;
    }
    preg_match('/SwapTotal:\s+(\d+)/', $meminfo, $m1);
    preg_match('/SwapFree:\s+(\d+)/', $meminfo, $m2);
    $total = isset($m1[1]) ? (float)$m1[1] : 0;
    $free = isset($m2[1]) ? (float)$m2[1] : 0;
    return $total > 0 ? round((($total - $free) / $total) * 100, 1) : 0.0;
}

/**
 * Get drive information using wmic command
 */
function getDrives() {
    $drives = [];
    $blockMeta = monitorCollectBlockDeviceMeta();
    $output = @shell_exec('findmnt -rn -b -o TARGET,SOURCE,FSTYPE,SIZE,USED,AVAIL 2>/dev/null');
    if ($output) {
        $seen = [];
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line), 6);
            if (count($parts) < 6) {
                continue;
            }
            [$mount, $source, $fsType, $sizeRaw, $usedRaw, $availRaw] = $parts;
            if (!isDriveMountVisible($mount) || isIgnoredDriveFsType($fsType)) {
                continue;
            }
            if ($sizeRaw === '' || !is_numeric($sizeRaw)) {
                continue;
            }
            $total = (float)$sizeRaw;
            if ($total <= 0) {
                continue;
            }
            if (str_starts_with($mount, '/var/lib/docker/') || str_starts_with($mount, '/run/docker/')) {
                continue;
            }
            $free = is_numeric($availRaw) ? (float)$availRaw : 0.0;
            $used = is_numeric($usedRaw) ? (float)$usedRaw : max(0.0, $total - $free);
            $key = $mount . '|' . $source;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $isNetwork = isNetworkDriveFsType($fsType);
            $drives[] = [
                'letter' => $mount,
                'source' => $source,
                'fs_type' => $fsType,
                'filesystem' => $fsType,
                'is_network' => $isNetwork,
                'type' => classifyDriveTypeLabel($mount, $fsType, $source),
                'total_space' => $total,
                'used_space' => $used,
                'free_space' => $free,
                'usage_percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0.0,
                'mounted' => true,
            ];
        }
    }

    foreach ($blockMeta as $device) {
        $mounts = array_values(array_filter((array)($device['mountpoints'] ?? []), static fn($m) => is_string($m) && $m !== ''));
        if ($mounts !== []) {
            continue;
        }
        $fsType = (string)($device['fstype'] ?? '');
        $isExternal = strtolower((string)($device['tran'] ?? $device['parent']['tran'] ?? '')) === 'usb'
            || (int)($device['rm'] ?? $device['parent']['rm'] ?? 0) === 1
            || (int)($device['hotplug'] ?? $device['parent']['hotplug'] ?? 0) === 1;
        if (isIgnoredDriveDeviceType($device['type'] ?? '')) {
            continue;
        }
        if ($fsType !== '' && isIgnoredDriveFsType($fsType)) {
            continue;
        }
        if ($fsType === '' && !$isExternal) {
            continue;
        }
        $path = (string)($device['path'] ?? '');
        if ($path === '') {
            continue;
        }
        $size = isset($device['size_bytes']) && is_numeric($device['size_bytes']) ? (float)$device['size_bytes'] : 0.0;
        $used = isset($device['fsused_bytes']) && is_numeric($device['fsused_bytes']) ? (float)$device['fsused_bytes'] : 0.0;
        $free = isset($device['fsavail_bytes']) && is_numeric($device['fsavail_bytes']) ? (float)$device['fsavail_bytes'] : max(0.0, $size - $used);
        $drives[] = [
            'letter' => $path,
            'source' => $path,
            'fs_type' => $fsType,
            'filesystem' => $fsType,
            'is_network' => false,
            'type' => classifyBlockDriveTypeLabel($device, $device['parent'] ?? null),
            'total_space' => $size,
            'used_space' => $used,
            'free_space' => $free,
            'usage_percent' => $size > 0 ? round(($used / $size) * 100, 1) : 0.0,
            'mounted' => false,
            'label' => (string)($device['label'] ?? ''),
            'uuid' => (string)($device['uuid'] ?? ''),
            'model' => (string)($device['model'] ?? $device['parent']['model'] ?? ''),
            'serial' => (string)($device['serial'] ?? $device['parent']['serial'] ?? ''),
        ];
    }

    usort($drives, static function ($a, $b) {
        $aMount = (string)($a['letter'] ?? '');
        $bMount = (string)($b['letter'] ?? '');
        $aOrder = driveSortOrder($aMount, (bool)($a['is_network'] ?? false));
        $bOrder = driveSortOrder($bMount, (bool)($b['is_network'] ?? false));
        if ($aOrder !== $bOrder) {
            return $aOrder <=> $bOrder;
        }
        return strcmp($aMount, $bMount);
    });

    return $drives;
}

function monitorCollectBlockDeviceMeta(): array {
    $json = @shell_exec('lsblk -J -b -o NAME,PATH,TYPE,SIZE,FSTYPE,FSVER,LABEL,UUID,MOUNTPOINTS,RM,HOTPLUG,TRAN,MODEL,SERIAL,FSAVAIL,FSUSED 2>/dev/null');
    if (!is_string($json) || trim($json) === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    $result = [];
    $walk = null;
    $walk = static function(array $items, ?array $parent = null) use (&$walk, &$result) {
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $item['parent'] = $parent;
            $item['size_bytes'] = isset($item['size']) && is_numeric($item['size']) ? (int)$item['size'] : 0;
            $item['fsavail_bytes'] = isset($item['fsavail']) && is_numeric($item['fsavail']) ? (int)$item['fsavail'] : null;
            $item['fsused_bytes'] = isset($item['fsused']) && is_numeric($item['fsused']) ? (int)$item['fsused'] : null;
            $result[] = $item;
            $children = $item['children'] ?? [];
            if (is_array($children) && $children !== []) {
                $walk($children, $item);
            }
        }
    };
    $walk($decoded['blockdevices'] ?? []);
    return $result;
}

function isPrimaryNetworkIface($iface) {
    if ($iface === '' || $iface === 'lo') {
        return false;
    }
    return (bool)preg_match('/^(eth|eno|enp|ens|enx|enm|wlan|wl|wwan|bond|usb|ppp)/i', $iface);
}
/**
 * Get Disk IO usage using wmic command
 * Returns disk read/write bytes per second
 */
function getDiskIO() {
    $diskData = [];
    $totalRead = 0;
    $totalWrite = 0;
    $stat1 = @file_get_contents('/proc/diskstats');
    usleep(500000);
    $stat2 = @file_get_contents('/proc/diskstats');
    if ($stat1 && $stat2) {
        $map1 = [];
        foreach (array_filter(explode("\n", trim($stat1))) as $l) {
            $p = preg_split('/\s+/', trim($l));
            if (count($p) >= 14) $map1[$p[2]] = $p;
        }
        foreach (array_filter(explode("\n", trim($stat2))) as $l) {
            $p = preg_split('/\s+/', trim($l));
            if (count($p) < 14) continue;
            $dev = $p[2];
            if (!preg_match('/^(sd[a-z]|nvme\d+n\d+|vd[a-z])$/', $dev)) continue;
            $r = isset($map1[$dev]) ? max(0, ((float)$p[5] - (float)$map1[$dev][5]) * 512 * 2) : 0;
            $w = isset($map1[$dev]) ? max(0, ((float)$p[9] - (float)$map1[$dev][9]) * 512 * 2) : 0;
            $totalRead += $r;
            $totalWrite += $w;
            $diskData[] = ['disk_name' => $dev, 'read_bytes' => $r, 'write_bytes' => $w];
        }
    }
    $totalActivity = ($totalRead + $totalWrite) / 1048576;
    $diskUsage = min(round($totalActivity / 10, 1), 100);
    return [
        'data' => $diskData,
        'usage_percent' => $diskUsage,
        'total_read' => $totalRead,
        'total_write' => $totalWrite
    ];
}

/**
 * Get network interfaces and traffic using wmic command
 * Format: Name, BytesReceivedPerSec, BytesSentPerSec
 */
function getNetworkData() {
    $networkData = [];
    $stat1 = @file_get_contents('/proc/net/dev');
    usleep(500000);
    $stat2 = @file_get_contents('/proc/net/dev');
    if ($stat1 && $stat2) {
        $map1 = [];
        foreach (array_filter(explode("\n", trim($stat1))) as $l) {
            if (!strpos($l, ':')) continue;
            list($iface, $data) = explode(':', $l, 2);
            $iface = trim($iface);
            $vals = preg_split('/\s+/', trim($data));
            $map1[$iface] = $vals;
        }
        foreach (array_filter(explode("\n", trim($stat2))) as $l) {
            if (!strpos($l, ':')) continue;
            list($iface, $data) = explode(':', $l, 2);
            $iface = trim($iface);
            if ($iface === 'lo' || !isset($map1[$iface]) || !isPrimaryNetworkIface($iface)) continue;
            $vals = preg_split('/\s+/', trim($data));
            $recv = max(0, ((float)($vals[0] ?? 0) - (float)($map1[$iface][0] ?? 0)) * 2);
            $sent = max(0, ((float)($vals[8] ?? 0) - (float)($map1[$iface][8] ?? 0)) * 2);
            $networkData[] = [
                'name' => $iface,
                'bytes_recv_rate' => $recv,
                'bytes_sent_rate' => $sent,
                'total_rate' => $recv + $sent,
                'speed' => 0
            ];
        }
    }
    return $networkData;
}

/**
 * Get system uptime using systeminfo command
 */
function getUptime() {
    $uptimeRaw = @file_get_contents('/proc/uptime');
    if ($uptimeRaw) {
        return (int)floor((float)explode(' ', $uptimeRaw)[0]);
    }
    return 0;
}

function monitorUptimeFromSeconds(?int $seconds): array {
    $seconds = max(0, (int)($seconds ?? 0));
    return [
        'days' => (int)floor($seconds / 86400),
        'hours' => (int)floor(($seconds % 86400) / 3600),
        'minutes' => (int)floor(($seconds % 3600) / 60),
    ];
}

function monitorNetworkFromAggregate(array $row): array {
    $recv = (int)($row['network_recv_bytes_per_sec'] ?? 0);
    $sent = (int)($row['network_sent_bytes_per_sec'] ?? 0);
    return [[
        'name' => 'aggregate',
        'bytes_recv_rate' => $recv,
        'bytes_sent_rate' => $sent,
        'total_rate' => $recv + $sent,
        'speed' => 0,
    ]];
}

function monitorCollectNginxModules(): array {
    $builtIn = [];
    $dynamicAvailable = [];
    $dynamicLoaded = [];
    $nginxV = @shell_exec('nginx -V 2>&1');
    if (is_string($nginxV) && preg_match_all('/--with-([a-z0-9_]+)_module(=dynamic)?/', $nginxV, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $name = $match[1] . '_module';
            if (($match[2] ?? '') === '=dynamic') {
                $dynamicAvailable[] = $name;
            } else {
                $builtIn[] = $name;
            }
        }
    }
    $nginxT = @shell_exec('nginx -T 2>/dev/null');
    if (is_string($nginxT) && preg_match_all('/load_module\s+([^;]+\.so)\s*;/', $nginxT, $loadMatches)) {
        foreach ($loadMatches[1] as $soPath) {
            $dynamicLoaded[] = basename((string)$soPath, '.so');
        }
    }
    $modulesDir = '/usr/lib/nginx/modules';
    if (is_dir($modulesDir)) {
        foreach (scandir($modulesDir) ?: [] as $file) {
            if (!str_ends_with($file, '.so')) {
                continue;
            }
            $dynamicAvailable[] = basename($file, '.so');
        }
    }
    $all = array_values(array_unique(array_merge($builtIn, $dynamicAvailable)));
    sort($all);
    $nginxModules = [];
    foreach ($all as $module) {
        $loaded = in_array($module, $builtIn, true)
            || in_array($module, $dynamicLoaded, true);
        $nginxModules[] = [
            'name' => $module,
            'status' => $loaded ? 'Loaded' : 'Not Loaded',
        ];
    }
    return $nginxModules;
}

function monitorCollectModulesLists(): array {
    $nginxModules = monitorCollectNginxModules();
    $loadedPhpExtensions = get_loaded_extensions();
    $allExtensions = array_unique(array_merge(
        ['Core', 'date', 'pcre', 'Reflection', 'SPL', 'standard', 'ctype', 'hash', 'json', 'libxml', 'tokenizer', 'xml', 'zip'],
        ['curl', 'gd', 'mbstring', 'mysqli', 'mysqlnd', 'openssl', 'PDO', 'pdo_mysql', 'session', 'SimpleXML', 'soap', 'sockets', 'xmlreader', 'xmlwriter', 'zlib', 'fileinfo', 'filter', 'iconv'],
        $loadedPhpExtensions
    ));
    sort($allExtensions);
    $phpModules = [];
    foreach ($allExtensions as $ext) {
        $loaded = in_array($ext, $loadedPhpExtensions, true) || extension_loaded(strtolower($ext)) || extension_loaded($ext);
        $phpModules[] = ['name' => $ext, 'status' => $loaded ? 'Loaded' : 'Not Loaded'];
    }
    return ['nginx_modules' => $nginxModules, 'php_modules' => $phpModules, 'last_modified' => time()];
}

function monitorSaveModulesToDb(): void {
    try {
        $db = getDB();
        if (!$db) {
            return;
        }
        $payload = monitorCollectModulesLists();
        $db->exec("CREATE TABLE IF NOT EXISTS app_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
        $stmt = $db->prepare("INSERT INTO app_meta(key, value) VALUES('performance_monitor_modules', :value) ON CONFLICT (key) DO UPDATE SET value=excluded.value");
        $stmt->bindValue(':value', json_encode($payload, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
        $stmt->execute();
        $db->exec("INSERT INTO app_meta(key, value) VALUES('performance_monitor_modules_last', '" . time() . "') ON CONFLICT (key) DO UPDATE SET value=excluded.value");
    } catch (Exception $e) {
        error_log('monitorSaveModulesToDb failed: ' . $e->getMessage());
    }
}

function monitorSaveDrivesToDb(array $drives): void {
    try {
        $db = getDB();
        if (!$db) return;
        $db->exec("CREATE TABLE IF NOT EXISTS app_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
        $stmt = $db->prepare("INSERT INTO app_meta(key, value) VALUES('performance_monitor_drives', :value) ON CONFLICT (key) DO UPDATE SET value=excluded.value");
        $stmt->bindValue(':value', json_encode(['drives' => $drives, 'updated_at' => time()], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
        $stmt->execute();
        $db->exec("INSERT INTO app_meta(key, value) VALUES('performance_monitor_drives_last', '" . time() . "') ON CONFLICT (key) DO UPDATE SET value=excluded.value");
    } catch (Exception $e) {
        error_log('monitorSaveDrivesToDb failed: ' . $e->getMessage());
    }
}

function monitorLoadDrivesFromDb(): array {
    try {
        $db = getDB();
        if (!$db) return [];
        $raw = $db->querySingle("SELECT value FROM app_meta WHERE key = 'performance_monitor_drives'");
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) && isset($decoded['drives']) && is_array($decoded['drives']) ? $decoded['drives'] : [];
    } catch (Exception $e) {
        error_log('monitorLoadDrivesFromDb failed: ' . $e->getMessage());
        return [];
    }
}

function monitorSaveSmartToDb(array $smart): void {
    try {
        $db = getDB();
        if (!$db) return;
        $db->exec("CREATE TABLE IF NOT EXISTS app_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
        $stmt = $db->prepare("INSERT INTO app_meta(key, value) VALUES('performance_monitor_smart', :value) ON CONFLICT (key) DO UPDATE SET value=excluded.value");
        $stmt->bindValue(':value', json_encode($smart, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
        $stmt->execute();
        $db->exec("INSERT INTO app_meta(key, value) VALUES('performance_monitor_smart_last', '" . time() . "') ON CONFLICT (key) DO UPDATE SET value=excluded.value");
    } catch (Exception $e) {
        error_log('monitorSaveSmartToDb failed: ' . $e->getMessage());
    }
}

function monitorLoadSmartFromDb(): array {
    try {
        $db = getDB();
        if (!$db) return ['devices' => [], 'updated_at' => 0];
        $raw = $db->querySingle("SELECT value FROM app_meta WHERE key = 'performance_monitor_smart'");
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : ['devices' => [], 'updated_at' => 0];
    } catch (Exception $e) {
        error_log('monitorLoadSmartFromDb failed: ' . $e->getMessage());
        return ['devices' => [], 'updated_at' => 0];
    }
}

function monitorLoadModulesFromDb(): array {
    try {
        $db = getDB();
        if (!$db) {
            return monitorCollectModulesLists();
        }
        $raw = $db->querySingle("SELECT value FROM app_meta WHERE key = 'performance_monitor_modules'");
        if (!is_string($raw) || $raw === '') {
            return monitorCollectModulesLists();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return monitorCollectModulesLists();
        }
        if (!isset($decoded['nginx_modules']) && isset($decoded['apache_modules'])) {
            return monitorCollectModulesLists();
        }
        return $decoded;
    } catch (Exception $e) {
        error_log('monitorLoadModulesFromDb failed: ' . $e->getMessage());
        return monitorCollectModulesLists();
    }
}

function monitorGetDbSafe() {
    try {
        return getDB();
    } catch (Throwable $e) {
        error_log('monitor db unavailable: ' . $e->getMessage());
        return null;
    }
}

function monitorTableColumns($db, string $table): array {
    $columns = [];
    $res = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name=$table");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $columns[(string)$row['name']] = true;
    }
    return $columns;
}

function monitorEnsureColumn($db, string $table, string $column, string $definition): void {
    $columns = monitorTableColumns($db, $table);
    if (!isset($columns[$column])) {
        $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

function monitorEnsureBaseSchema(): void {
    try {
        $db = monitorGetDbSafe();
        if (!$db) return;
        $db->busyTimeout(5000);
        $db->exec("CREATE TABLE IF NOT EXISTS app_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_system_snapshots (
            id BIGSERIAL PRIMARY KEY,
            created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            cpu_percent REAL NOT NULL DEFAULT 0,
            memory_percent REAL NOT NULL DEFAULT 0,
            swap_percent REAL NOT NULL DEFAULT 0,
            disk_io_percent REAL NOT NULL DEFAULT 0,
            disk_read_bytes_per_sec BIGINT NOT NULL DEFAULT 0,
            disk_write_bytes_per_sec BIGINT NOT NULL DEFAULT 0,
            network_recv_bytes_per_sec BIGINT NOT NULL DEFAULT 0,
            network_sent_bytes_per_sec BIGINT NOT NULL DEFAULT 0,
            uptime_seconds BIGINT,
            status TEXT NOT NULL DEFAULT 'ok',
            context_json TEXT NOT NULL DEFAULT '{}'
        )");
        monitorEnsureColumn($db, 'monitor_system_snapshots', 'disk_read_bytes_per_sec', 'BIGINT NOT NULL DEFAULT 0');
        monitorEnsureColumn($db, 'monitor_system_snapshots', 'disk_write_bytes_per_sec', 'BIGINT NOT NULL DEFAULT 0');
        monitorEnsureColumn($db, 'monitor_system_snapshots', 'network_recv_bytes_per_sec', 'BIGINT NOT NULL DEFAULT 0');
        monitorEnsureColumn($db, 'monitor_system_snapshots', 'network_sent_bytes_per_sec', 'BIGINT NOT NULL DEFAULT 0');
        monitorEnsureColumn($db, 'monitor_system_snapshots', 'uptime_seconds', 'BIGINT');
        if (function_exists('app_db_is_pgsql') && app_db_is_pgsql()) {
            $db->exec("ALTER TABLE monitor_system_snapshots ALTER COLUMN disk_read_bytes_per_sec TYPE BIGINT");
            $db->exec("ALTER TABLE monitor_system_snapshots ALTER COLUMN disk_write_bytes_per_sec TYPE BIGINT");
            $db->exec("ALTER TABLE monitor_system_snapshots ALTER COLUMN network_recv_bytes_per_sec TYPE BIGINT");
            $db->exec("ALTER TABLE monitor_system_snapshots ALTER COLUMN network_sent_bytes_per_sec TYPE BIGINT");
            $db->exec("ALTER TABLE monitor_system_snapshots ALTER COLUMN uptime_seconds TYPE BIGINT");
        }
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_system_snapshots_created ON monitor_system_snapshots(created_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_system_snapshots_status ON monitor_system_snapshots(status, created_at)");
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_disk_snapshots (
            id BIGSERIAL PRIMARY KEY,
            snapshot_id INTEGER NOT NULL,
            drive_letter TEXT NOT NULL,
            total_bytes BIGINT NOT NULL DEFAULT 0,
            free_bytes BIGINT NOT NULL DEFAULT 0,
            used_bytes BIGINT NOT NULL DEFAULT 0,
            used_percent REAL NOT NULL DEFAULT 0,
            FOREIGN KEY (snapshot_id) REFERENCES monitor_system_snapshots(id) ON DELETE CASCADE
        )");
        monitorEnsureColumn($db, 'monitor_disk_snapshots', 'total_bytes', 'BIGINT NOT NULL DEFAULT 0');
        monitorEnsureColumn($db, 'monitor_disk_snapshots', 'free_bytes', 'BIGINT NOT NULL DEFAULT 0');
        monitorEnsureColumn($db, 'monitor_disk_snapshots', 'used_bytes', 'BIGINT NOT NULL DEFAULT 0');
        if (function_exists('app_db_is_pgsql') && app_db_is_pgsql()) {
            $db->exec("ALTER TABLE monitor_disk_snapshots ALTER COLUMN total_bytes TYPE BIGINT");
            $db->exec("ALTER TABLE monitor_disk_snapshots ALTER COLUMN free_bytes TYPE BIGINT");
            $db->exec("ALTER TABLE monitor_disk_snapshots ALTER COLUMN used_bytes TYPE BIGINT");
        }
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_disk_snapshots_snapshot ON monitor_disk_snapshots(snapshot_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_disk_snapshots_drive_created ON monitor_disk_snapshots(drive_letter, snapshot_id)");
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_metric_hourly (
            metric_hour INTEGER NOT NULL,
            metric TEXT NOT NULL,
            app_id TEXT NOT NULL DEFAULT '',
            min_value REAL NOT NULL DEFAULT 0,
            avg_value REAL NOT NULL DEFAULT 0,
            max_value REAL NOT NULL DEFAULT 0,
            sample_count INTEGER NOT NULL DEFAULT 0,
            updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            PRIMARY KEY (metric_hour, metric, app_id)
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_metric_hourly_metric ON monitor_metric_hourly(metric, metric_hour)");
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_alert_rules (
            id BIGSERIAL PRIMARY KEY,
            name TEXT NOT NULL UNIQUE,
            metric TEXT NOT NULL,
            operator TEXT NOT NULL,
            threshold REAL NOT NULL,
            duration_sec INTEGER NOT NULL DEFAULT 300,
            severity TEXT NOT NULL DEFAULT 'warning',
            enabled INTEGER NOT NULL DEFAULT 1,
            notify_channels TEXT NOT NULL DEFAULT '[]',
            created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_alerts (
            id BIGSERIAL PRIMARY KEY,
            rule_id INTEGER,
            opened_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            closed_at INTEGER,
            severity TEXT NOT NULL DEFAULT 'warning',
            status TEXT NOT NULL DEFAULT 'open',
            title TEXT NOT NULL,
            message TEXT NOT NULL DEFAULT '',
            value REAL,
            context_json TEXT NOT NULL DEFAULT '{}'
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_app_events (
            id BIGSERIAL PRIMARY KEY,
            created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            level TEXT NOT NULL DEFAULT 'info',
            app_id TEXT NOT NULL DEFAULT '',
            event_type TEXT NOT NULL DEFAULT '',
            user_email TEXT,
            ip_address TEXT NOT NULL DEFAULT '',
            user_agent TEXT NOT NULL DEFAULT '',
            message TEXT NOT NULL DEFAULT '',
            context_json TEXT NOT NULL DEFAULT '{}'
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_app_events_created ON monitor_app_events(created_at)");
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_http_requests (
            id BIGSERIAL PRIMARY KEY,
            created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            method TEXT NOT NULL DEFAULT '',
            path TEXT NOT NULL DEFAULT '',
            app_id TEXT NOT NULL DEFAULT '',
            user_email TEXT,
            ip_address TEXT NOT NULL DEFAULT '',
            status_code INTEGER NOT NULL DEFAULT 200,
            duration_ms INTEGER NOT NULL DEFAULT 0,
            response_bytes BIGINT NOT NULL DEFAULT 0,
            error_message TEXT NOT NULL DEFAULT '',
            context_json TEXT NOT NULL DEFAULT '{}'
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_http_requests_created ON monitor_http_requests(created_at)");
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_errors (
            id BIGSERIAL PRIMARY KEY,
            created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            level TEXT NOT NULL DEFAULT 'error',
            app_id TEXT NOT NULL DEFAULT '',
            user_email TEXT,
            file TEXT NOT NULL DEFAULT '',
            line INTEGER,
            message TEXT NOT NULL DEFAULT '',
            trace_hash TEXT NOT NULL DEFAULT '',
            context_json TEXT NOT NULL DEFAULT '{}',
            resolved INTEGER NOT NULL DEFAULT 0,
            resolved_at INTEGER
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_errors_created ON monitor_errors(created_at)");
    } catch (Exception $e) {
        error_log('monitorEnsureBaseSchema failed: ' . $e->getMessage());
    }
}

function monitorEnsureUptimeSchema(): void {
    try {
        monitorEnsureBaseSchema();
        $db = monitorGetDbSafe();
        if (!$db) return;
        $db->busyTimeout(5000);
        $db->exec("CREATE TABLE IF NOT EXISTS app_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_uptime_checks (
            id BIGSERIAL PRIMARY KEY,
            name TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT 'http',
            target TEXT NOT NULL DEFAULT '',
            method TEXT NOT NULL DEFAULT 'GET',
            expected_status INTEGER NOT NULL DEFAULT 200,
            keyword TEXT NOT NULL DEFAULT '',
            interval_sec INTEGER NOT NULL DEFAULT 60,
            timeout_sec INTEGER NOT NULL DEFAULT 10,
            retries INTEGER NOT NULL DEFAULT 1,
            docker_container_id TEXT NOT NULL DEFAULT '',
            docker_container_name TEXT NOT NULL DEFAULT '',
            enabled INTEGER NOT NULL DEFAULT 1,
            auto_discovered INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
        )");
        monitorEnsureColumn($db, 'monitor_uptime_checks', 'method', "TEXT NOT NULL DEFAULT 'GET'");
        monitorEnsureColumn($db, 'monitor_uptime_checks', 'expected_status', 'INTEGER NOT NULL DEFAULT 200');
        monitorEnsureColumn($db, 'monitor_uptime_checks', 'keyword', "TEXT NOT NULL DEFAULT ''");
        monitorEnsureColumn($db, 'monitor_uptime_checks', 'interval_sec', 'INTEGER NOT NULL DEFAULT 60');
        monitorEnsureColumn($db, 'monitor_uptime_checks', 'timeout_sec', 'INTEGER NOT NULL DEFAULT 10');
        monitorEnsureColumn($db, 'monitor_uptime_checks', 'retries', 'INTEGER NOT NULL DEFAULT 1');
        monitorEnsureColumn($db, 'monitor_uptime_checks', 'docker_container_id', "TEXT NOT NULL DEFAULT ''");
        monitorEnsureColumn($db, 'monitor_uptime_checks', 'docker_container_name', "TEXT NOT NULL DEFAULT ''");
        monitorEnsureColumn($db, 'monitor_uptime_checks', 'enabled', 'INTEGER NOT NULL DEFAULT 1');
        monitorEnsureColumn($db, 'monitor_uptime_checks', 'auto_discovered', 'INTEGER NOT NULL DEFAULT 0');
        monitorEnsureColumn($db, 'monitor_uptime_checks', 'created_at', 'INTEGER NOT NULL DEFAULT 0');
        monitorEnsureColumn($db, 'monitor_uptime_checks', 'updated_at', 'INTEGER NOT NULL DEFAULT 0');
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_uptime_checks_type_enabled ON monitor_uptime_checks(type, enabled)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_uptime_checks_docker ON monitor_uptime_checks(docker_container_id)");
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_uptime_results (
            id BIGSERIAL PRIMARY KEY,
            check_id INTEGER NOT NULL,
            checked_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            status TEXT NOT NULL DEFAULT 'unknown',
            latency_ms INTEGER,
            http_status INTEGER,
            message TEXT NOT NULL DEFAULT '',
            response_excerpt TEXT NOT NULL DEFAULT '',
            meta_json TEXT NOT NULL DEFAULT '{}',
            FOREIGN KEY (check_id) REFERENCES monitor_uptime_checks(id) ON DELETE CASCADE
        )");
        monitorEnsureColumn($db, 'monitor_uptime_results', 'latency_ms', 'INTEGER');
        monitorEnsureColumn($db, 'monitor_uptime_results', 'http_status', 'INTEGER');
        monitorEnsureColumn($db, 'monitor_uptime_results', 'response_excerpt', "TEXT NOT NULL DEFAULT ''");
        monitorEnsureColumn($db, 'monitor_uptime_results', 'meta_json', "TEXT NOT NULL DEFAULT '{}'");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_uptime_results_check_time ON monitor_uptime_results(check_id, checked_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_uptime_results_status_time ON monitor_uptime_results(status, checked_at)");
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_uptime_logs (
            id BIGSERIAL PRIMARY KEY,
            check_id INTEGER NOT NULL,
            check_name TEXT NOT NULL DEFAULT '',
            check_type TEXT NOT NULL DEFAULT '',
            target TEXT NOT NULL DEFAULT '',
            started_at INTEGER NOT NULL,
            finished_at INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT 'unknown',
            latency_ms INTEGER,
            http_status INTEGER,
            message TEXT NOT NULL DEFAULT '',
            meta_json TEXT NOT NULL DEFAULT '{}',
            FOREIGN KEY (check_id) REFERENCES monitor_uptime_checks(id) ON DELETE CASCADE
        )");
        monitorEnsureColumn($db, 'monitor_uptime_logs', 'check_name', "TEXT NOT NULL DEFAULT ''");
        monitorEnsureColumn($db, 'monitor_uptime_logs', 'check_type', "TEXT NOT NULL DEFAULT ''");
        monitorEnsureColumn($db, 'monitor_uptime_logs', 'target', "TEXT NOT NULL DEFAULT ''");
        monitorEnsureColumn($db, 'monitor_uptime_logs', 'latency_ms', 'INTEGER');
        monitorEnsureColumn($db, 'monitor_uptime_logs', 'http_status', 'INTEGER');
        monitorEnsureColumn($db, 'monitor_uptime_logs', 'meta_json', "TEXT NOT NULL DEFAULT '{}'");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_uptime_logs_check_time ON monitor_uptime_logs(check_id, started_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_uptime_logs_status_time ON monitor_uptime_logs(status, started_at)");
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_docker_snapshots (
            id BIGSERIAL PRIMARY KEY,
            created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            container_id TEXT NOT NULL,
            container_name TEXT NOT NULL DEFAULT '',
            image TEXT NOT NULL DEFAULT '',
            state TEXT NOT NULL DEFAULT '',
            health TEXT NOT NULL DEFAULT '',
            cpu_percent REAL NOT NULL DEFAULT 0,
            memory_percent REAL NOT NULL DEFAULT 0,
            memory_text TEXT NOT NULL DEFAULT '',
            network_io TEXT NOT NULL DEFAULT '',
            block_io TEXT NOT NULL DEFAULT '',
            pids INTEGER NOT NULL DEFAULT 0
        )");
        monitorEnsureColumn($db, 'monitor_docker_snapshots', 'health', "TEXT NOT NULL DEFAULT ''");
        monitorEnsureColumn($db, 'monitor_docker_snapshots', 'cpu_percent', 'REAL NOT NULL DEFAULT 0');
        monitorEnsureColumn($db, 'monitor_docker_snapshots', 'memory_percent', 'REAL NOT NULL DEFAULT 0');
        monitorEnsureColumn($db, 'monitor_docker_snapshots', 'memory_text', "TEXT NOT NULL DEFAULT ''");
        monitorEnsureColumn($db, 'monitor_docker_snapshots', 'network_io', "TEXT NOT NULL DEFAULT ''");
        monitorEnsureColumn($db, 'monitor_docker_snapshots', 'block_io', "TEXT NOT NULL DEFAULT ''");
        monitorEnsureColumn($db, 'monitor_docker_snapshots', 'pids', 'INTEGER NOT NULL DEFAULT 0');
        $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_docker_snapshots_container_time ON monitor_docker_snapshots(container_id, created_at)");
    } catch (Exception $e) {
        error_log('monitorEnsureUptimeSchema failed: ' . $e->getMessage());
    }
}

function monitorDockerCommand(string $args): ?string {
    $bins = [];
    $envBin = getenv('MONITOR_DOCKER_BIN');
    if (is_string($envBin) && trim($envBin) !== '') {
        $bins[] = trim($envBin);
    }
    foreach (['/usr/bin/docker', '/usr/local/bin/docker', 'docker'] as $bin) {
        $bins[] = $bin;
    }
    $commands = [];
    foreach (array_values(array_unique($bins)) as $bin) {
        $commands[] = $bin . ' ' . $args;
        $commands[] = 'sudo -n ' . $bin . ' ' . $args;
    }
    foreach ($commands as $command) {
        $output = @shell_exec($command . ' 2>/dev/null');
        if (is_string($output) && trim($output) !== '') {
            return $output;
        }
    }
    return null;
}

function formatBytesHuman(float $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

function monitorDockerSocketPaths(): array {
    $candidates = [];
    $envSockets = getenv('MONITOR_DOCKER_SOCKETS');
    if (is_string($envSockets) && $envSockets !== '') {
        foreach (explode(',', $envSockets) as $socket) {
            $candidates[] = trim($socket);
        }
    }
    $envSocket = getenv('MONITOR_DOCKER_SOCKET');
    if (is_string($envSocket) && $envSocket !== '') {
        $candidates[] = $envSocket;
    }
    $dockerHost = getenv('DOCKER_HOST');
    if (is_string($dockerHost) && str_starts_with($dockerHost, 'unix://')) {
        $candidates[] = substr($dockerHost, 7);
    }
    $candidates[] = '/var/run/docker.sock';
    $candidates[] = '/run/docker.sock';
    $candidates[] = '/run/docker-desktop.sock';
    $candidates[] = '/host/var/run/docker.sock';
    $paths = [];
    foreach ($candidates as $path) {
        if (is_string($path) && $path !== '' && is_readable($path)) {
            $paths[$path] = true;
        }
    }
    return array_keys($paths);
}

function monitorDockerSocketPath(): ?string {
    $paths = monitorDockerSocketPaths();
    return $paths[0] ?? null;
}

function monitorDockerApiRequest(string $path, ?string $socketPath = null): ?array {
    if (!function_exists('curl_init') || !defined('CURLOPT_UNIX_SOCKET_PATH')) {
        return null;
    }
    $dockerHost = getenv('DOCKER_HOST');
    $ch = null;
    if (is_string($dockerHost) && preg_match('#^tcp://([^:]+):(\d+)$#', $dockerHost, $m)) {
        $ch = curl_init('http://' . $m[1] . ':' . $m[2] . $path);
    } else {
        $socket = $socketPath ?: monitorDockerSocketPath();
        if ($socket === null) {
            return null;
        }
        $ch = curl_init('http://localhost' . $path);
        curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $socket);
    }
    if (!$ch) return null;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!is_string($raw) || $raw === '' || $code < 200 || $code >= 300) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function monitorDockerApiContainers(): array {
    $sources = monitorDockerSocketPaths();
    if ($sources === [] && is_string(getenv('DOCKER_HOST')) && str_starts_with((string)getenv('DOCKER_HOST'), 'tcp://')) {
        $sources = [null];
    }
    $containers = [];
    foreach ($sources as $source) {
        $rows = monitorDockerApiRequest('/containers/json', $source);
        if (!is_array($rows)) continue;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $id = (string)($row['Id'] ?? '');
            if ($id === '' || isset($containers[$id])) continue;
            $names = $row['Names'] ?? [];
            $name = is_array($names) && isset($names[0]) ? ltrim((string)$names[0], '/') : substr($id, 0, 12);
            $inspect = monitorDockerApiRequest('/containers/' . rawurlencode($id) . '/json', $source) ?? [];
            $health = strtolower((string)($inspect['State']['Health']['Status'] ?? ''));
            $containers[$id] = [
                'id' => $id,
                'short_id' => substr($id, 0, 12),
                'name' => $name !== '' ? $name : substr($id, 0, 12),
                'image' => (string)($row['Image'] ?? ''),
                'state' => strtolower((string)($row['State'] ?? '')),
                'status_text' => (string)($row['Status'] ?? ''),
                'health' => $health,
                'ports' => json_encode($row['Ports'] ?? [], JSON_UNESCAPED_UNICODE),
                'created_at' => isset($row['Created']) ? date('Y-m-d H:i:s', (int)$row['Created']) : '',
                'docker_socket' => (string)($source ?? getenv('DOCKER_HOST') ?: ''),
            ];
        }
    }
    return array_values($containers);
}

function monitorCollectDockerContainers(): array {
    $apiContainers = monitorDockerApiContainers();
    if ($apiContainers !== []) {
        return $apiContainers;
    }
    $raw = monitorDockerCommand("ps --no-trunc --format '{{json .}}'");
    if ($raw === null) return [];
    $containers = [];
    foreach (explode("\n", trim($raw)) as $line) {
        $row = json_decode($line, true);
        if (!is_array($row)) continue;
        $id = (string)($row['ID'] ?? '');
        if ($id === '') continue;
        $name = ltrim((string)($row['Names'] ?? ''), '/');
        $state = strtolower((string)($row['State'] ?? ''));
        $status = (string)($row['Status'] ?? '');
        $inspectRaw = monitorDockerCommand('inspect ' . escapeshellarg($id));
        $inspect = [];
        if (is_string($inspectRaw)) {
            $decoded = json_decode($inspectRaw, true);
            $inspect = is_array($decoded[0] ?? null) ? $decoded[0] : [];
        }
        $health = strtolower((string)($inspect['State']['Health']['Status'] ?? ''));
        $containers[] = [
            'id' => $id,
            'short_id' => substr($id, 0, 12),
            'name' => $name !== '' ? $name : substr($id, 0, 12),
            'image' => (string)($row['Image'] ?? ''),
            'state' => $state,
            'status_text' => $status,
            'health' => $health,
            'ports' => (string)($row['Ports'] ?? ''),
            'created_at' => (string)($row['CreatedAt'] ?? ''),
        ];
    }
    return $containers;
}

function monitorDockerApiCpuPercent(array $stats): float {
    $cpuDelta = (float)(($stats['cpu_stats']['cpu_usage']['total_usage'] ?? 0) - ($stats['precpu_stats']['cpu_usage']['total_usage'] ?? 0));
    $systemDelta = (float)(($stats['cpu_stats']['system_cpu_usage'] ?? 0) - ($stats['precpu_stats']['system_cpu_usage'] ?? 0));
    $online = (float)($stats['cpu_stats']['online_cpus'] ?? count($stats['cpu_stats']['cpu_usage']['percpu_usage'] ?? []));
    if ($cpuDelta <= 0 || $systemDelta <= 0 || $online <= 0) {
        return 0.0;
    }
    return round(($cpuDelta / $systemDelta) * $online * 100, 2);
}

function monitorDockerApiStats(array $containers): array {
    $stats = [];
    foreach ($containers as $container) {
        $id = (string)($container['id'] ?? '');
        if ($id === '') continue;
        $row = monitorDockerApiRequest('/containers/' . rawurlencode($id) . '/stats?stream=false', (string)($container['docker_socket'] ?? '') ?: null);
        if (!is_array($row)) continue;
        $memUsage = (float)($row['memory_stats']['usage'] ?? 0);
        $memLimit = (float)($row['memory_stats']['limit'] ?? 0);
        $rx = 0;
        $tx = 0;
        foreach (($row['networks'] ?? []) as $net) {
            if (!is_array($net)) continue;
            $rx += (int)($net['rx_bytes'] ?? 0);
            $tx += (int)($net['tx_bytes'] ?? 0);
        }
        $blockRead = 0;
        $blockWrite = 0;
        foreach (($row['blkio_stats']['io_service_bytes_recursive'] ?? []) as $io) {
            if (!is_array($io)) continue;
            $op = strtolower((string)($io['op'] ?? ''));
            if ($op === 'read') $blockRead += (int)($io['value'] ?? 0);
            if ($op === 'write') $blockWrite += (int)($io['value'] ?? 0);
        }
        $stats[$id] = [
            'cpu_percent' => monitorDockerApiCpuPercent($row),
            'memory' => formatBytesHuman($memUsage) . ' / ' . formatBytesHuman($memLimit),
            'memory_percent' => $memLimit > 0 ? round(($memUsage / $memLimit) * 100, 2) : 0,
            'network_io' => formatBytesHuman($rx) . ' / ' . formatBytesHuman($tx),
            'block_io' => formatBytesHuman($blockRead) . ' / ' . formatBytesHuman($blockWrite),
            'pids' => (int)($row['pids_stats']['current'] ?? 0),
        ];
    }
    return $stats;
}

function monitorCollectDockerStats(): array {
    $raw = monitorDockerCommand("stats --no-stream --no-trunc --format '{{json .}}'");
    if ($raw === null) return [];
    $stats = [];
    foreach (explode("\n", trim($raw)) as $line) {
        $row = json_decode($line, true);
        if (!is_array($row)) continue;
        $id = (string)($row['ID'] ?? '');
        if ($id === '') continue;
        $stats[$id] = [
            'cpu_percent' => (float)str_replace('%', '', (string)($row['CPUPerc'] ?? '0')),
            'memory' => (string)($row['MemUsage'] ?? ''),
            'memory_percent' => (float)str_replace('%', '', (string)($row['MemPerc'] ?? '0')),
            'network_io' => (string)($row['NetIO'] ?? ''),
            'block_io' => (string)($row['BlockIO'] ?? ''),
            'pids' => (int)($row['PIDs'] ?? 0),
        ];
    }
    return $stats;
}

function monitorSyncDockerChecks(array $containers): void {
    try {
        monitorEnsureUptimeSchema();
        $db = monitorGetDbSafe();
        if (!$db) return;
        $stmt = $db->prepare("INSERT INTO monitor_uptime_checks
            (name, type, target, interval_sec, timeout_sec, retries, docker_container_id, docker_container_name, enabled, auto_discovered, created_at, updated_at)
            VALUES(:name, 'docker', :target, 60, 10, 1, :container_id, :container_name, 1, 1, :now, :now)");
        $now = time();
        $currentIds = [];
        foreach ($containers as $container) {
            if (!is_array($container)) continue;
            $id = (string)($container['id'] ?? '');
            if ($id === '') continue;
            $currentIds[] = "'" . AppDatabase::escapeString($id) . "'";
            $exists = (int)$db->querySingle("SELECT COUNT(*) FROM monitor_uptime_checks WHERE docker_container_id = '" . AppDatabase::escapeString($id) . "'");
            if ($exists > 0) continue;
            $name = 'Docker: ' . (string)($container['name'] ?? substr($id, 0, 12));
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':target', (string)($container['name'] ?? $id), PDO::PARAM_STR);
            $stmt->bindValue(':container_id', $id, PDO::PARAM_STR);
            $stmt->bindValue(':container_name', (string)($container['name'] ?? ''), PDO::PARAM_STR);
            $stmt->bindValue(':now', $now, PDO::PARAM_INT);
            $stmt->execute();
        }
        if ($currentIds !== []) {
            $db->exec("DELETE FROM monitor_uptime_checks WHERE type = 'docker' AND auto_discovered = 1 AND docker_container_id NOT IN (" . implode(',', $currentIds) . ")");
        }
    } catch (Exception $e) {
        error_log('monitorSyncDockerChecks failed: ' . $e->getMessage());
    }
}

function monitorReadUptimeChecks(bool $enabledOnly = false): array {
    monitorEnsureUptimeSchema();
    $db = monitorGetDbSafe();
    if (!$db) return [];
    $sql = 'SELECT * FROM monitor_uptime_checks';
    if ($enabledOnly) $sql .= ' WHERE enabled = 1';
    $sql .= ' ORDER BY auto_discovered DESC, name ASC';
    $res = $db->query($sql);
    $checks = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $checks[] = $row;
    }
    return $checks;
}

function monitorCheckHttp(array $check): array {
    $target = (string)($check['target'] ?? '');
    $timeout = max(1, (int)($check['timeout_sec'] ?? 10));
    $method = strtoupper((string)($check['method'] ?? 'GET'));
    $expected = (int)($check['expected_status'] ?? 200);
    $keyword = (string)($check['keyword'] ?? '');
    if (!function_exists('curl_init')) {
        return ['status' => 'down', 'message' => 'curl_unavailable'];
    }
    $ch = curl_init($target);
    if (!$ch) return ['status' => 'down', 'message' => 'curl_init_failed'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_NOBODY => $method === 'HEAD',
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'AppDashboard Performance Monitor',
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $latency = (int)round(((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME)) * 1000);
    curl_close($ch);
    if ($body === false) {
        return ['status' => 'down', 'latency_ms' => $latency, 'http_status' => $http, 'message' => $err ?: 'request_failed'];
    }
    $okStatus = $expected > 0 ? $http === $expected : $http >= 200 && $http < 400;
    $okKeyword = $keyword === '' || strpos((string)$body, $keyword) !== false;
    return [
        'status' => $okStatus && $okKeyword ? 'up' : 'down',
        'latency_ms' => $latency,
        'http_status' => $http,
        'message' => $okStatus ? ($okKeyword ? 'ok' : 'keyword_missing') : 'unexpected_status',
        'response_excerpt' => substr((string)$body, 0, 500),
    ];
}

function monitorCheckTcp(array $check): array {
    $target = (string)($check['target'] ?? '');
    $timeout = max(1, (int)($check['timeout_sec'] ?? 10));
    $host = $target;
    $port = 0;
    if (str_contains($target, ':')) {
        [$host, $portRaw] = explode(':', $target, 2);
        $port = (int)$portRaw;
    }
    if ($host === '' || $port <= 0) return ['status' => 'down', 'message' => 'invalid_target'];
    $start = microtime(true);
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $latency = (int)round((microtime(true) - $start) * 1000);
    if (is_resource($fp)) {
        fclose($fp);
        return ['status' => 'up', 'latency_ms' => $latency, 'message' => 'ok'];
    }
    return ['status' => 'down', 'latency_ms' => $latency, 'message' => $errstr ?: ('errno_' . $errno)];
}

function monitorCheckPing(array $check): array {
    $target = escapeshellarg((string)($check['target'] ?? ''));
    if ($target === "''") return ['status' => 'down', 'message' => 'invalid_target'];
    $timeout = max(1, (int)($check['timeout_sec'] ?? 10));
    $output = @shell_exec('ping -c 1 -W ' . (int)$timeout . ' ' . $target . ' 2>&1');
    $latency = null;
    if (is_string($output) && preg_match('/time=([0-9.]+)\s*ms/', $output, $m)) {
        $latency = (int)round((float)$m[1]);
    }
    return [
        'status' => is_string($output) && preg_match('/1 (packets )?received|1 received/', $output) ? 'up' : 'down',
        'latency_ms' => $latency,
        'message' => $latency !== null ? 'ok' : trim(substr((string)$output, 0, 160)),
    ];
}

function monitorCheckDns(array $check): array {
    $target = (string)($check['target'] ?? '');
    if ($target === '') return ['status' => 'down', 'message' => 'invalid_target'];
    $start = microtime(true);
    $records = @dns_get_record($target, DNS_A + DNS_AAAA + DNS_CNAME);
    $latency = (int)round((microtime(true) - $start) * 1000);
    return [
        'status' => is_array($records) && count($records) > 0 ? 'up' : 'down',
        'latency_ms' => $latency,
        'message' => is_array($records) && count($records) > 0 ? 'ok' : 'no_records',
        'meta' => ['records' => array_slice(is_array($records) ? $records : [], 0, 5)],
    ];
}

function monitorCheckDocker(array $check, array $containersById): array {
    $id = (string)($check['docker_container_id'] ?? '');
    $container = $containersById[$id] ?? null;
    if (!$container) {
        return ['status' => 'down', 'message' => 'container_missing'];
    }
    $state = strtolower((string)($container['state'] ?? ''));
    $health = strtolower((string)($container['health'] ?? ''));
    $up = $state === 'running' && ($health === '' || $health === 'healthy');
    $message = $health !== '' ? $state . '/' . $health : $state;
    return ['status' => $up ? 'up' : 'down', 'message' => $message, 'meta' => $container];
}

function monitorRunOneUptimeCheck(array $check, array $containersById): array {
    $type = strtolower((string)($check['type'] ?? 'http'));
    if ($type === 'http' || $type === 'https' || $type === 'keyword') return monitorCheckHttp($check);
    if ($type === 'tcp' || $type === 'port') return monitorCheckTcp($check);
    if ($type === 'ping') return monitorCheckPing($check);
    if ($type === 'dns') return monitorCheckDns($check);
    if ($type === 'docker') return monitorCheckDocker($check, $containersById);
    return ['status' => 'unknown', 'message' => 'unsupported_type'];
}

function monitorStoreUptimeResult(int $checkId, array $result): void {
    $db = monitorGetDbSafe();
    if (!$db) return;
    $stmt = $db->prepare("INSERT INTO monitor_uptime_results
        (check_id, checked_at, status, latency_ms, http_status, message, response_excerpt, meta_json)
        VALUES(:check_id, :checked_at, :status, :latency_ms, :http_status, :message, :response_excerpt, :meta_json)");
    $stmt->bindValue(':check_id', $checkId, PDO::PARAM_INT);
    $stmt->bindValue(':checked_at', time(), PDO::PARAM_INT);
    $stmt->bindValue(':status', (string)($result['status'] ?? 'unknown'), PDO::PARAM_STR);
    $stmt->bindValue(':latency_ms', isset($result['latency_ms']) && is_numeric($result['latency_ms']) ? (int)$result['latency_ms'] : null, PDO::PARAM_INT);
    $stmt->bindValue(':http_status', isset($result['http_status']) && is_numeric($result['http_status']) ? (int)$result['http_status'] : null, PDO::PARAM_INT);
    $stmt->bindValue(':message', substr((string)($result['message'] ?? ''), 0, 500), PDO::PARAM_STR);
    $stmt->bindValue(':response_excerpt', substr((string)($result['response_excerpt'] ?? ''), 0, 500), PDO::PARAM_STR);
    $stmt->bindValue(':meta_json', json_encode($result['meta'] ?? [], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
    $stmt->execute();
}

function monitorStoreUptimeLog(array $check, array $result, int $startedAt, int $finishedAt): void {
    $db = monitorGetDbSafe();
    if (!$db) return;
    $stmt = $db->prepare("INSERT INTO monitor_uptime_logs
        (check_id, check_name, check_type, target, started_at, finished_at, status, latency_ms, http_status, message, meta_json)
        VALUES(:check_id, :check_name, :check_type, :target, :started_at, :finished_at, :status, :latency_ms, :http_status, :message, :meta_json)");
    $stmt->bindValue(':check_id', (int)($check['id'] ?? 0), PDO::PARAM_INT);
    $stmt->bindValue(':check_name', (string)($check['name'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':check_type', (string)($check['type'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':target', (string)($check['target'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':started_at', $startedAt, PDO::PARAM_INT);
    $stmt->bindValue(':finished_at', $finishedAt, PDO::PARAM_INT);
    $stmt->bindValue(':status', (string)($result['status'] ?? 'unknown'), PDO::PARAM_STR);
    $stmt->bindValue(':latency_ms', isset($result['latency_ms']) && is_numeric($result['latency_ms']) ? (int)$result['latency_ms'] : null, PDO::PARAM_INT);
    $stmt->bindValue(':http_status', isset($result['http_status']) && is_numeric($result['http_status']) ? (int)$result['http_status'] : null, PDO::PARAM_INT);
    $stmt->bindValue(':message', substr((string)($result['message'] ?? ''), 0, 500), PDO::PARAM_STR);
    $stmt->bindValue(':meta_json', json_encode($result['meta'] ?? [], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
    $stmt->execute();
}

function monitorRunUptimeChecks(array $containers): void {
    monitorEnsureUptimeSchema();
    monitorSyncDockerChecks($containers);
    $containersById = [];
    foreach ($containers as $container) {
        if (is_array($container) && isset($container['id'])) {
            $containersById[(string)$container['id']] = $container;
        }
    }
    foreach (monitorReadUptimeChecks(true) as $check) {
        $interval = max(10, (int)($check['interval_sec'] ?? 60));
        $db = monitorGetDbSafe();
        if (!$db) continue;
        $last = (int)$db->querySingle('SELECT checked_at FROM monitor_uptime_results WHERE check_id = ' . (int)$check['id'] . ' ORDER BY checked_at DESC LIMIT 1');
        if ($last > 0 && (time() - $last) < $interval) continue;
        $startedAt = time();
        $result = monitorRunOneUptimeCheck($check, $containersById);
        $finishedAt = time();
        monitorStoreUptimeResult((int)$check['id'], $result);
        monitorStoreUptimeLog($check, $result, $startedAt, $finishedAt);
    }
}

function monitorStoreDockerSnapshots(array $containers): void {
    monitorEnsureUptimeSchema();
    $db = monitorGetDbSafe();
    if (!$db || $containers === []) return;
    $stmt = $db->prepare("INSERT INTO monitor_docker_snapshots
        (created_at, container_id, container_name, image, state, health, cpu_percent, memory_percent, memory_text, network_io, block_io, pids)
        VALUES(:created_at, :container_id, :container_name, :image, :state, :health, :cpu_percent, :memory_percent, :memory_text, :network_io, :block_io, :pids)");
    $now = time();
    foreach ($containers as $container) {
        if (!is_array($container)) continue;
        $stats = is_array($container['stats'] ?? null) ? $container['stats'] : [];
        $stmt->bindValue(':created_at', $now, PDO::PARAM_INT);
        $stmt->bindValue(':container_id', (string)($container['id'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':container_name', (string)($container['name'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':image', (string)($container['image'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':state', (string)($container['state'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':health', (string)($container['health'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':cpu_percent', (float)($stats['cpu_percent'] ?? 0), PDO::PARAM_STR);
        $stmt->bindValue(':memory_percent', (float)($stats['memory_percent'] ?? 0), PDO::PARAM_STR);
        $stmt->bindValue(':memory_text', (string)($stats['memory'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':network_io', (string)($stats['network_io'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':block_io', (string)($stats['block_io'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':pids', (int)($stats['pids'] ?? 0), PDO::PARAM_INT);
        $stmt->execute();
    }
}

function monitorFetchUptimePayload(int $historyLimit = 24): array {
    monitorEnsureUptimeSchema();
    $db = monitorGetDbSafe();
    if (!$db) return ['checks' => [], 'summary' => ['up' => 0, 'down' => 0, 'unknown' => 0, 'total' => 0]];
    $checks = [];
    $res = $db->query("SELECT c.*, r.status AS last_status, r.latency_ms AS last_latency_ms, r.http_status AS last_http_status,
        r.message AS last_message, r.checked_at AS last_checked_at
        FROM monitor_uptime_checks c
        LEFT JOIN monitor_uptime_results r ON r.id = (
            SELECT id FROM monitor_uptime_results WHERE check_id = c.id ORDER BY checked_at DESC LIMIT 1
        )
        ORDER BY c.auto_discovered DESC, c.name ASC");
    $summary = ['up' => 0, 'down' => 0, 'unknown' => 0, 'total' => 0];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $status = (string)($row['last_status'] ?? 'unknown');
        if (!isset($summary[$status])) $status = 'unknown';
        $summary[$status]++;
        $summary['total']++;
        $hist = [];
        $histStmt = $db->prepare('SELECT checked_at, status, latency_ms FROM monitor_uptime_results WHERE check_id = :id ORDER BY checked_at DESC LIMIT :limit');
        $histStmt->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
        $histStmt->bindValue(':limit', $historyLimit, PDO::PARAM_INT);
        $histRes = $histStmt->execute();
        while ($h = $histRes->fetch(PDO::FETCH_ASSOC)) {
            $hist[] = $h;
        }
        $row['history'] = array_reverse($hist);
        $checks[] = $row;
    }
    return ['checks' => $checks, 'summary' => $summary];
}

function monitorBuildDockerChecksFromContainers(array $containers): array {
    $checks = [];
    $summary = ['up' => 0, 'down' => 0, 'unknown' => 0, 'total' => 0];
    foreach ($containers as $idx => $container) {
        if (!is_array($container)) continue;
        $id = (string)($container['id'] ?? '');
        if ($id === '') continue;
        $state = strtolower((string)($container['state'] ?? ''));
        $health = strtolower((string)($container['health'] ?? ''));
        $status = $state === 'running' && ($health === '' || $health === 'healthy') ? 'up' : 'down';
        $message = $health !== '' ? $state . '/' . $health : ($state !== '' ? $state : 'unknown');
        $summary[$status]++;
        $summary['total']++;
        $checks[] = [
            'id' => -($idx + 1),
            'name' => 'Docker: ' . (string)($container['name'] ?? substr($id, 0, 12)),
            'type' => 'docker',
            'target' => (string)($container['name'] ?? $id),
            'docker_container_id' => $id,
            'docker_container_name' => (string)($container['name'] ?? ''),
            'enabled' => 1,
            'auto_discovered' => 1,
            'last_status' => $status,
            'last_latency_ms' => null,
            'last_http_status' => null,
            'last_message' => $message,
            'last_checked_at' => time(),
            'history' => [['checked_at' => time(), 'status' => $status, 'latency_ms' => null]],
        ];
    }
    return ['checks' => $checks, 'summary' => $summary];
}

function monitorFetchUptimePayloadForContainers(array $containers, int $historyLimit = 24): array {
    monitorSyncDockerChecks($containers);
    $payload = monitorFetchUptimePayload($historyLimit);
    $hasDocker = false;
    foreach (($payload['checks'] ?? []) as $check) {
        if (is_array($check) && (string)($check['type'] ?? '') === 'docker') {
            $hasDocker = true;
            break;
        }
    }
    if (!$hasDocker && $containers !== []) {
        return monitorBuildDockerChecksFromContainers($containers);
    }
    return $payload;
}

function monitorFetchDockerHistory(string $containerId, string $period = '24h'): array {
    monitorEnsureUptimeSchema();
    $db = monitorGetDbSafe();
    if (!$db || $containerId === '') {
        return ['points' => [], 'period' => $period];
    }
    $periodSeconds = [
        '1h' => 3600,
        '6h' => 21600,
        '24h' => 86400,
        '7d' => 604800,
        '30d' => 2592000,
    ][$period] ?? 86400;
    $since = time() - $periodSeconds;
    $limit = max(120, min(3000, (int)ceil($periodSeconds / 60) + 60));
    $stmt = $db->prepare("SELECT created_at, cpu_percent, memory_percent, pids, state, health
        FROM monitor_docker_snapshots
        WHERE container_id = :container_id AND created_at >= :since
        ORDER BY created_at DESC
        LIMIT :limit");
    $stmt->bindValue(':container_id', $containerId, PDO::PARAM_STR);
    $stmt->bindValue(':since', $since, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $res = $stmt->execute();
    $points = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $points[] = [
            'created_at' => (int)($row['created_at'] ?? 0),
            'cpu_percent' => (float)($row['cpu_percent'] ?? 0),
            'memory_percent' => (float)($row['memory_percent'] ?? 0),
            'pids' => (int)($row['pids'] ?? 0),
            'state' => (string)($row['state'] ?? ''),
            'health' => (string)($row['health'] ?? ''),
        ];
    }
    return ['points' => array_reverse($points), 'period' => $period];
}

function monitorSaveUptimeCheckFromInput(array $input): array {
    monitorEnsureUptimeSchema();
    $db = monitorGetDbSafe();
    if (!$db) return ['ok' => false, 'error' => 'db_unavailable'];
    $id = (int)($input['id'] ?? 0);
    $name = trim((string)($input['name'] ?? ''));
    $type = strtolower(trim((string)($input['type'] ?? 'http')));
    $target = trim((string)($input['target'] ?? ''));
    if ($name === '' || $target === '') return ['ok' => false, 'error' => 'name_target_required'];
    if (!in_array($type, ['http', 'https', 'keyword', 'tcp', 'port', 'ping', 'dns', 'docker'], true)) $type = 'http';
    $now = time();
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE monitor_uptime_checks SET name=:name, type=:type, target=:target, method=:method,
            expected_status=:expected_status, keyword=:keyword, interval_sec=:interval_sec, timeout_sec=:timeout_sec,
            retries=:retries, enabled=:enabled, updated_at=:updated_at WHERE id=:id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    } else {
        $stmt = $db->prepare("INSERT INTO monitor_uptime_checks
            (name, type, target, method, expected_status, keyword, interval_sec, timeout_sec, retries, enabled, auto_discovered, created_at, updated_at)
            VALUES(:name, :type, :target, :method, :expected_status, :keyword, :interval_sec, :timeout_sec, :retries, :enabled, 0, :updated_at, :updated_at)");
    }
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':type', $type, PDO::PARAM_STR);
    $stmt->bindValue(':target', $target, PDO::PARAM_STR);
    $stmt->bindValue(':method', strtoupper((string)($input['method'] ?? 'GET')), PDO::PARAM_STR);
    $stmt->bindValue(':expected_status', max(0, (int)($input['expected_status'] ?? 200)), PDO::PARAM_INT);
    $stmt->bindValue(':keyword', (string)($input['keyword'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':interval_sec', max(10, (int)($input['interval_sec'] ?? 60)), PDO::PARAM_INT);
    $stmt->bindValue(':timeout_sec', max(1, (int)($input['timeout_sec'] ?? 10)), PDO::PARAM_INT);
    $stmt->bindValue(':retries', max(1, (int)($input['retries'] ?? 1)), PDO::PARAM_INT);
    $stmt->bindValue(':enabled', !empty($input['enabled']) ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':updated_at', $now, PDO::PARAM_INT);
    $stmt->execute();
    return ['ok' => true, 'id' => $id > 0 ? $id : (int)$db->lastInsertRowID()];
}

function monitorDeleteUptimeCheck(int $id): array {
    monitorEnsureUptimeSchema();
    $db = monitorGetDbSafe();
    if (!$db) return ['ok' => false, 'error' => 'db_unavailable'];
    $stmt = $db->prepare('DELETE FROM monitor_uptime_checks WHERE id = :id AND auto_discovered = 0');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return ['ok' => true];
}

function monitorCollectMetricsPayload(bool $includeSmart = false, bool $includeDrives = false): array {
    $diskIO = getDiskIO();
    $smartDiagnostics = $includeSmart ? getSmartDiagnostics() : monitorLoadSmartFromDb();
    $drives = $includeDrives ? getDrives() : monitorLoadDrivesFromDb();
    $dockerContainers = monitorCollectDockerContainers();
    $dockerStats = monitorDockerApiStats($dockerContainers);
    if ($dockerStats === []) {
        $dockerStats = monitorCollectDockerStats();
    }
    foreach ($dockerContainers as &$container) {
        $container['stats'] = $dockerStats[$container['id']] ?? null;
    }
    unset($container);
    if ($includeSmart) {
        monitorSaveSmartToDb($smartDiagnostics);
    }
    if ($includeDrives) {
        monitorSaveDrivesToDb($drives);
    }
    return [
        'cpu_usage' => (float)getCpuUsage(),
        'memory_usage' => (float)getMemoryUsage(),
        'swap_usage' => (float)getSwapUsage(),
        'disk_usage' => (float)($diskIO['usage_percent'] ?? 0),
        'disk_read_bytes_per_sec' => (int)($diskIO['total_read'] ?? 0),
        'disk_write_bytes_per_sec' => (int)($diskIO['total_write'] ?? 0),
        'network_data' => getNetworkData(),
        'uptime' => getUptime(),
        'drives' => $drives,
        'smart' => $smartDiagnostics,
        'docker_containers' => $dockerContainers,
        'uptime_monitoring' => monitorFetchUptimePayloadForContainers($dockerContainers),
        'status' => 'ok',
        'context' => [
            'source' => 'apps/performance/bin/monitor_collector.php',
            'drives' => $drives,
            'smart' => $smartDiagnostics,
            'docker_containers' => $dockerContainers,
        ],
    ];
}

function monitorRunCollectorCycle(bool $includeSmart = false, bool $includeDrives = false, bool $includeModules = false, int $minIntervalSec = 60): ?int {
    monitorEnsureBaseSchema();
    $metrics = monitorCollectMetricsPayload($includeSmart, $includeDrives);
    monitorRunUptimeChecks(is_array($metrics['docker_containers'] ?? null) ? $metrics['docker_containers'] : []);
    monitorStoreDockerSnapshots(is_array($metrics['docker_containers'] ?? null) ? $metrics['docker_containers'] : []);
    $snapshotId = monitorRecordSystemSnapshot($metrics, $minIntervalSec);
    $smartDevices = is_array($metrics['smart']) ? ($metrics['smart']['devices'] ?? []) : [];
    if (!empty($smartDevices)) {
        monitorEnsureSmartTable();
        monitorStoreSmartSnapshots($smartDevices);
    }
    if ($includeModules) {
        monitorSaveModulesToDb();
    }
    monitorRetentionCleanup();
    return $snapshotId;
}

function monitorEmptyDisplayPayload(): array {
    $modules = monitorLoadModulesFromDb();
    return [
        'cpu_usage' => 0,
        'memory_usage' => 0,
        'disk_usage' => 0,
        'swap_usage' => 0,
        'network_data' => [],
        'uptime' => ['days' => 0, 'hours' => 0, 'minutes' => 0],
        'drives' => [],
        'smart' => ['devices' => [], 'updated_at' => 0],
        'docker_containers' => [],
        'uptime_monitoring' => monitorFetchUptimePayload(),
        'nginx_modules' => $modules['nginx_modules'] ?? [],
        'php_modules' => $modules['php_modules'] ?? [],
        'monitor_snapshot_id' => null,
        'timestamp' => time(),
        'server_time' => date('Y-m-d H:i:s'),
        'status' => 'no_data',
        'data_age_sec' => null,
    ];
}

function monitorFetchDisplayPayloadFromDb(): array {
    try {
        monitorEnsureBaseSchema();
        $db = getDB();
        if (!$db) {
            return monitorEmptyDisplayPayload();
        }
        $row = $db->querySingle('SELECT * FROM monitor_system_snapshots ORDER BY created_at DESC LIMIT 1', true);
        if (!is_array($row) || !isset($row['id'])) {
            return monitorEmptyDisplayPayload();
        }
        $snapshotId = (int)$row['id'];
        $context = json_decode((string)($row['context_json'] ?? '{}'), true);
        if (!is_array($context)) {
            $context = [];
        }
        $drives = is_array($context['drives'] ?? null) ? $context['drives'] : [];
        if ($drives === []) {
            $drives = monitorLoadDrivesFromDb();
        }
        $hasContextDrives = $drives !== [];
        $stmt = $db->prepare('SELECT drive_letter, total_bytes, free_bytes, used_bytes, used_percent
            FROM monitor_disk_snapshots WHERE snapshot_id = :snapshot_id ORDER BY drive_letter ASC');
        $stmt->bindValue(':snapshot_id', $snapshotId, PDO::PARAM_INT);
        $res = $stmt->execute();
        while ($diskRow = $res->fetch(PDO::FETCH_ASSOC)) {
            $total = (int)($diskRow['total_bytes'] ?? 0);
            $free = (int)($diskRow['free_bytes'] ?? 0);
            $used = (int)($diskRow['used_bytes'] ?? max(0, $total - $free));
            if (!$hasContextDrives) {
                $drives[] = [
                    'letter' => (string)($diskRow['drive_letter'] ?? ''),
                    'source' => '',
                    'fs_type' => '',
                    'filesystem' => '',
                    'total_space' => $total,
                    'free_space' => $free,
                    'used_space' => $used,
                    'usage_percent' => (float)($diskRow['used_percent'] ?? 0),
                    'mounted' => true,
                ];
            }
        }
        $networkData = $context['network_data'] ?? monitorNetworkFromAggregate($row);
        if (!is_array($networkData)) {
            $networkData = monitorNetworkFromAggregate($row);
        }
        $smart = $context['smart'] ?? null;
        if (!is_array($smart)) {
            $smart = monitorLoadSmartFromDb();
        }
        if (empty($smart['devices'])) {
            $cachedSmart = monitorLoadSmartFromDb();
            if (!empty($cachedSmart['devices'])) {
                $smart = $cachedSmart;
            }
        }
        $modules = monitorLoadModulesFromDb();
        $dockerContainers = monitorCollectDockerContainers();
        if ($dockerContainers === [] && is_array($context['docker_containers'] ?? null)) {
            $dockerContainers = $context['docker_containers'];
        }
        $createdAt = (int)($row['created_at'] ?? time());
        return [
            'cpu_usage' => (float)($row['cpu_percent'] ?? 0),
            'memory_usage' => (float)($row['memory_percent'] ?? 0),
            'disk_usage' => (float)($row['disk_io_percent'] ?? 0),
            'swap_usage' => (float)($row['swap_percent'] ?? 0),
            'network_data' => $networkData,
            'uptime' => monitorUptimeFromSeconds(isset($row['uptime_seconds']) ? (int)$row['uptime_seconds'] : null),
            'drives' => $drives,
            'smart' => $smart,
            'docker_containers' => $dockerContainers,
            'uptime_monitoring' => monitorFetchUptimePayloadForContainers($dockerContainers),
            'nginx_modules' => $modules['nginx_modules'] ?? [],
            'php_modules' => $modules['php_modules'] ?? [],
            'monitor_snapshot_id' => $snapshotId,
            'timestamp' => $createdAt,
            'server_time' => date('Y-m-d H:i:s', $createdAt),
            'status' => (string)($row['status'] ?? 'ok'),
            'data_age_sec' => time() - $createdAt,
        ];
    } catch (Exception $e) {
        error_log('monitorFetchDisplayPayloadFromDb failed: ' . $e->getMessage());
        return monitorEmptyDisplayPayload();
    }
}
