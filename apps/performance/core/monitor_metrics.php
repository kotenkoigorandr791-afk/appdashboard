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
        $db->exec("CREATE TABLE IF NOT EXISTS monitor_smart_snapshots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at INTEGER NOT NULL,
            device_path TEXT NOT NULL,
            model TEXT DEFAULT '',
            serial TEXT DEFAULT '',
            firmware TEXT DEFAULT '',
            capacity_bytes INTEGER,
            health_passed INTEGER,
            temperature_c INTEGER,
            power_on_hours INTEGER,
            power_cycle_count INTEGER,
            data_written_bytes INTEGER,
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
            $stmt->bindValue(':created_at', $now, SQLITE3_INTEGER);
            $stmt->bindValue(':device_path', (string)($device['device'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':model', (string)($device['model'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':serial', (string)($device['serial'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':firmware', (string)($device['firmware'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':capacity_bytes', isset($device['capacity_bytes']) && is_numeric($device['capacity_bytes']) ? (int)$device['capacity_bytes'] : null, SQLITE3_INTEGER);
            $stmt->bindValue(':health_passed', isset($device['health_passed']) ? ($device['health_passed'] ? 1 : 0) : null, SQLITE3_INTEGER);
            $stmt->bindValue(':temperature_c', isset($device['temperature_c']) && is_numeric($device['temperature_c']) ? (int)$device['temperature_c'] : null, SQLITE3_INTEGER);
            $stmt->bindValue(':power_on_hours', isset($device['power_on_hours']) && is_numeric($device['power_on_hours']) ? (int)$device['power_on_hours'] : null, SQLITE3_INTEGER);
            $stmt->bindValue(':power_cycle_count', isset($device['power_cycle_count']) && is_numeric($device['power_cycle_count']) ? (int)$device['power_cycle_count'] : null, SQLITE3_INTEGER);
            $stmt->bindValue(':data_written_bytes', isset($device['data_written_bytes']) && is_numeric($device['data_written_bytes']) ? (int)$device['data_written_bytes'] : null, SQLITE3_INTEGER);
            $stmt->bindValue(':wear_used_percent', isset($device['wear_used_percent']) && is_numeric($device['wear_used_percent']) ? (int)$device['wear_used_percent'] : null, SQLITE3_INTEGER);
            $stmt->bindValue(':life_remaining_percent', isset($device['life_remaining_percent']) && is_numeric($device['life_remaining_percent']) ? (int)$device['life_remaining_percent'] : null, SQLITE3_INTEGER);
            $stmt->bindValue(':reallocated_sectors', (int)($device['reallocated_sectors'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':reserved_block_count', (int)($device['reserved_block_count'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':program_fail_count', (int)($device['program_fail_count'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':erase_fail_count', (int)($device['erase_fail_count'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':runtime_bad_blocks', (int)($device['runtime_bad_blocks'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':uncorrectable_error_count', (int)($device['uncorrectable_error_count'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':crc_error_count', (int)($device['crc_error_count'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':error_log_count', (int)($device['error_log_count'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':completed_self_tests', (int)($device['completed_self_tests'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':status', (string)($device['status'] ?? 'unknown'), SQLITE3_TEXT);
            $stmt->bindValue(':messages_json', json_encode($device['messages'] ?? [], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
            $stmt->bindValue(':self_tests_json', json_encode($device['self_tests'] ?? [], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
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
        $stmt = $db->prepare("INSERT OR REPLACE INTO app_meta(key, value) VALUES('performance_monitor_modules', :value)");
        $stmt->bindValue(':value', json_encode($payload, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
        $stmt->execute();
        $db->exec("INSERT OR REPLACE INTO app_meta(key, value) VALUES('performance_monitor_modules_last', '" . time() . "')");
    } catch (Exception $e) {
        error_log('monitorSaveModulesToDb failed: ' . $e->getMessage());
    }
}

function monitorSaveDrivesToDb(array $drives): void {
    try {
        $db = getDB();
        if (!$db) return;
        $db->exec("CREATE TABLE IF NOT EXISTS app_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
        $stmt = $db->prepare("INSERT OR REPLACE INTO app_meta(key, value) VALUES('performance_monitor_drives', :value)");
        $stmt->bindValue(':value', json_encode(['drives' => $drives, 'updated_at' => time()], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
        $stmt->execute();
        $db->exec("INSERT OR REPLACE INTO app_meta(key, value) VALUES('performance_monitor_drives_last', '" . time() . "')");
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
        $stmt = $db->prepare("INSERT OR REPLACE INTO app_meta(key, value) VALUES('performance_monitor_smart', :value)");
        $stmt->bindValue(':value', json_encode($smart, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
        $stmt->execute();
        $db->exec("INSERT OR REPLACE INTO app_meta(key, value) VALUES('performance_monitor_smart_last', '" . time() . "')");
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

function monitorCollectMetricsPayload(bool $includeSmart = false, bool $includeDrives = false): array {
    $diskIO = getDiskIO();
    $smartDiagnostics = $includeSmart ? getSmartDiagnostics() : monitorLoadSmartFromDb();
    $drives = $includeDrives ? getDrives() : monitorLoadDrivesFromDb();
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
        'status' => 'ok',
        'context' => [
            'source' => 'apps/performance/bin/monitor_collector.php',
            'drives' => $drives,
            'smart' => $smartDiagnostics,
        ],
    ];
}

function monitorRunCollectorCycle(bool $includeSmart = false, bool $includeDrives = false, bool $includeModules = false, int $minIntervalSec = 60): ?int {
    $metrics = monitorCollectMetricsPayload($includeSmart, $includeDrives);
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
        $stmt->bindValue(':snapshot_id', $snapshotId, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while ($diskRow = $res->fetchArray(SQLITE3_ASSOC)) {
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
