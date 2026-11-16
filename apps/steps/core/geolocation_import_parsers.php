<?php
/**
 * File format parsers (task 19).
 *
 * Parsers stream points as canonical event drafts: no parser emits or requires
 * a transport label. Google Timeline activity segments are intentionally
 * ignored in favour of place visits. Every draft carries a stable namespace and
 * event id so re-importing an overlapping export cannot duplicate points.
 */
final class StepsGeoImportParsers
{
    public const FORMATS = [
        'gpx' => 'GPX',
        'tcx' => 'TCX',
        'strava_gpx' => 'Strava GPX',
        'strava_tcx' => 'Strava TCX',
        'geojson' => 'GeoJSON',
        'google_timeline' => 'Google Timeline / Takeout',
        'owntracks' => 'OwnTracks',
        'immich' => 'Immich metadata',
        'csv' => 'CSV (latitude, longitude, time)',
        'exif' => 'EXIF GPS (JPEG)',
    ];

    /** @return array{format:string,parser:string}|null */
    public static function detect(string $path, string $filename = ''): ?array
    {
        if (!is_file($path) || filesize($path) === 0) return null;
        $fh = fopen($path, 'rb');
        if (!$fh) return null;
        $head = (string)fread($fh, 65536);
        fclose($fh);
        $extension = strtolower(pathinfo($filename !== '' ? $filename : $path, PATHINFO_EXTENSION));
        if (str_starts_with($head, "\xFF\xD8\xFF")) return ['format' => 'exif', 'parser' => 'exif'];
        if (stripos($head, '<gpx') !== false) return ['format' => stripos($head, 'strava') !== false ? 'strava_gpx' : 'gpx', 'parser' => 'gpx'];
        if (stripos($head, 'TrainingCenterDatabase') !== false) return ['format' => stripos($head, 'strava') !== false ? 'strava_tcx' : 'tcx', 'parser' => 'tcx'];
        if (str_contains($head, '"timelineObjects"')) return ['format' => 'google_timeline', 'parser' => 'google_timeline'];
        if (str_contains($head, 'latitudeE7')) return ['format' => 'google_timeline', 'parser' => 'google_timeline'];
        if (str_contains($head, '"_type"') && str_contains($head, '"location"')) return ['format' => 'owntracks', 'parser' => 'owntracks'];
        if (str_contains($head, '"exifInfo"') || str_contains($head, '"immich_asset_id"')) return ['format' => 'immich', 'parser' => 'immich'];
        if (str_contains($head, 'FeatureCollection') || preg_match('/"type"\s*:\s*"Feature"/', $head)) return ['format' => 'geojson', 'parser' => 'geojson'];
        if (in_array($extension, ['gpx', 'tcx'], true)) return ['format' => $extension, 'parser' => $extension];
        if ($extension === 'ndjson' || $extension === 'jsonl') return ['format' => 'owntracks', 'parser' => 'owntracks'];
        $firstLine = strtok($head, "\r\n");
        if (is_string($firstLine) && preg_match('/lat.*lon|lon.*lat/i', $firstLine)) return ['format' => 'csv', 'parser' => 'csv'];
        if ($extension === 'json' && (str_starts_with(ltrim($head), '[') || str_starts_with(ltrim($head), '{'))) {
            return ['format' => 'geojson', 'parser' => 'geojson'];
        }
        return null;
    }

    public static function stableEventId(string $namespace, string $at, float $lat, float $lon): string
    {
        $hash = hash('sha256', $namespace . '|' . $at . '|' . number_format($lat, 7, '.', '') . '|' . number_format($lon, 7, '.', ''));
        return substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-5' . substr($hash, 13, 3) . '-a' . substr($hash, 17, 3) . '-' . substr($hash, 20, 12);
    }

    private static function draft(string $namespace, int $timestamp, float $lat, float $lon, array $extra = [], string $eventKey = ''): array
    {
        $at = gmdate('c', $timestamp);
        $eventId = $eventKey !== '' ? $eventKey : self::stableEventId($namespace, $at, $lat, $lon);
        return array_merge([
            'source_namespace' => $namespace,
            'source_event_id' => $eventId,
            'source_at' => $at,
            'latitude' => $lat,
            'longitude' => $lon,
            'accuracy_m' => null,
            'altitude_m' => null,
            'dedupe_nearby' => true,
        ], $extra);
    }

    /** @return Generator<int,array<string,mixed>> */
    public static function parse(string $parser, string $path): Generator
    {
        switch ($parser) {
            case 'gpx': yield from self::parseGpx($path); return;
            case 'tcx': yield from self::parseTcx($path); return;
            case 'geojson': yield from self::parseGeoJson($path); return;
            case 'google_timeline': yield from self::parseGoogleTimeline($path); return;
            case 'owntracks': yield from self::parseOwnTracks($path); return;
            case 'immich': yield from self::parseImmich($path); return;
            case 'csv': yield from self::parseCsv($path); return;
            case 'exif': yield from self::parseExif($path); return;
            default: throw new RuntimeException('unsupported_format');
        }
    }

    /** Stream a top-level JSON array without loading the whole file. */
    private static function jsonArray(string $path): Generator
    {
        $fh = fopen($path, 'rb');
        if (!$fh) return;
        try {
            $buffer = '';
            $depth = 0;
            $started = false;
            $inString = false;
            $escape = false;
            $objectStart = null;
            while (!feof($fh)) {
                $chunk = fread($fh, 8192);
                if ($chunk === false) break;
                $buffer .= $chunk;
                $offset = 0;
                $length = strlen($buffer);
                while ($offset < $length) {
                    $char = $buffer[$offset];
                    if ($escape) { $escape = false; $offset++; continue; }
                    if ($char === '\\' && $inString) { $escape = true; $offset++; continue; }
                    if ($char === '"') { $inString = !$inString; $offset++; continue; }
                    if ($inString) { $offset++; continue; }
                    if ($char === '{' || $char === '[') {
                        if (!$started) {
                            if ($char !== '[') return;
                            $started = true;
                            $depth = 0;
                        }
                        $depth++;
                        if ($depth === 2) $objectStart = $offset;
                    } elseif ($char === '}' || $char === ']') {
                        $depth--;
                        if ($started && $depth === 1 && $char === '}' && $objectStart !== null) {
                            $object = substr($buffer, $objectStart, $offset - $objectStart + 1);
                            $decoded = json_decode($object, true);
                            if (is_array($decoded)) yield $decoded;
                            $buffer = substr($buffer, $offset + 1);
                            $offset = 0;
                            $length = strlen($buffer);
                            $objectStart = null;
                            continue;
                        }
                    } elseif ($char === ',' && $started && $depth === 1) {
                        $buffer = substr($buffer, $offset + 1);
                        $offset = 0;
                        $length = strlen($buffer);
                        $objectStart = null;
                        continue;
                    }
                    $offset++;
                }
                if ($started && $depth <= 0) break;
            }
        } finally {
            fclose($fh);
        }
    }

    /** @return Generator<int,array<string,mixed>> */
    private static function parseGpx(string $path): Generator
    {
        $reader = new XMLReader();
        if (!$reader->open($path, null, LIBXML_NONET)) throw new RuntimeException('invalid_gpx');
        $name = '';
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) continue;
                if (!in_array($reader->name, ['trkpt', 'wpt', 'rtept'], true)) continue;
                $lat = (float)$reader->getAttribute('lat');
                $lon = (float)$reader->getAttribute('lon');
                $xml = $reader->readOuterXML();
                $point = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
                if ($point === false) continue;
                $time = trim((string)$point->time);
                $timestamp = $time !== '' ? strtotime($time) : false;
                if ($timestamp === false) continue;
                $accuracy = null;
                if (isset($point->hdop) && is_numeric((string)$point->hdop)) $accuracy = (float)$point->hdop * 5.0;
                if (isset($point->extensions)) {
                    foreach ($point->extensions->children() as $child) {
                        if (strtolower($child->getName()) === 'accuracy' && is_numeric((string)$child)) $accuracy = (float)$child;
                    }
                }
                yield self::draft('gpx', $timestamp, $lat, $lon, [
                    'accuracy_m' => $accuracy,
                    'altitude_m' => isset($point->ele) && is_numeric((string)$point->ele) ? (float)$point->ele : null,
                ]);
            }
        } finally {
            $reader->close();
        }
    }

    /** @return Generator<int,array<string,mixed>> */
    private static function parseTcx(string $path): Generator
    {
        $reader = new XMLReader();
        if (!$reader->open($path, null, LIBXML_NONET)) throw new RuntimeException('invalid_tcx');
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'Trackpoint') continue;
                $xml = $reader->readOuterXML();
                $point = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
                if ($point === false) continue;
                $timestamp = strtotime(trim((string)$point->Time));
                if ($timestamp === false) continue;
                $position = $point->Position;
                if ($position === null) continue;
                $lat = (float)$position->LatitudeDegrees;
                $lon = (float)$position->LongitudeDegrees;
                if ($lat == 0.0 && $lon == 0.0) continue;
                yield self::draft('tcx', $timestamp, $lat, $lon, [
                    'altitude_m' => isset($point->AltitudeMeters) && is_numeric((string)$point->AltitudeMeters) ? (float)$point->AltitudeMeters : null,
                ]);
            }
        } finally {
            $reader->close();
        }
    }

    /** @return Generator<int,array<string,mixed>> */
    private static function parseGeoJson(string $path): Generator
    {
        $raw = file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) throw new RuntimeException('invalid_geojson');
        $features = ($data['type'] ?? '') === 'FeatureCollection' ? ($data['features'] ?? []) : [$data];
        foreach ($features as $feature) {
            if (!is_array($feature)) continue;
            $geometry = $feature['geometry'] ?? null;
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            if (!is_array($geometry)) continue;
            $type = (string)($geometry['type'] ?? '');
            $coordinates = $geometry['coordinates'] ?? null;
            if ($type === 'Point' && is_array($coordinates) && count($coordinates) >= 2) {
                $timestamp = self::firstTimestamp($props);
                if ($timestamp === false) continue;
                yield self::draft('geojson', $timestamp, (float)$coordinates[1], (float)$coordinates[0], [
                    'accuracy_m' => isset($props['accuracy']) && is_numeric($props['accuracy']) ? (float)$props['accuracy'] : null,
                ]);
            } elseif ($type === 'LineString' && is_array($coordinates)) {
                $times = $props['coordTimes'] ?? $props['times'] ?? [];
                foreach ($coordinates as $index => $pair) {
                    if (!is_array($pair) || count($pair) < 2) continue;
                    $timestamp = isset($times[$index]) ? strtotime((string)$times[$index]) : false;
                    if ($timestamp === false) continue;
                    yield self::draft('geojson', $timestamp, (float)$pair[1], (float)$pair[0]);
                }
            }
        }
    }

    private static function firstTimestamp(array $props)
    {
        foreach (['time', 'timestamp', 'date', 'datetime', 'startTime', 'startTimestamp'] as $key) {
            if (!isset($props[$key])) continue;
            $value = $props[$key];
            if (is_numeric($value) && strlen((string)$value) > 12) return (int)round((float)$value / 1000);
            $timestamp = strtotime((string)$value);
            if ($timestamp !== false) return $timestamp;
        }
        return false;
    }

    /** @return Generator<int,array<string,mixed>> */
    private static function parseGoogleTimeline(string $path): Generator
    {
        $head = (string)file_get_contents($path, false, null, 0, 65536);
        if (str_contains($head, '"timelineObjects"')) {
            $data = json_decode((string)file_get_contents($path), true);
            if (!is_array($data)) throw new RuntimeException('invalid_google_timeline');
            foreach (($data['timelineObjects'] ?? []) as $object) {
                $visit = $object['placeVisit'] ?? null;
                if (!is_array($visit)) continue;
                $location = $visit['location'] ?? null;
                if (!is_array($location)) continue;
                $lat = isset($location['latitudeE7']) ? (float)$location['latitudeE7'] / 1e7 : (float)($location['latitude'] ?? 0);
                $lon = isset($location['longitudeE7']) ? (float)$location['longitudeE7'] / 1e7 : (float)($location['longitude'] ?? 0);
                $start = $visit['duration']['startTimestamp'] ?? $visit['duration']['startTimestampMs'] ?? null;
                if ($lat == 0.0 || $lon == 0.0 || $start === null) continue;
                $timestamp = is_numeric($start) ? (strlen((string)$start) > 12 ? (int)round((float)$start / 1000) : (int)$start) : strtotime((string)$start);
                if ($timestamp === false) continue;
                $eventKey = isset($location['placeId']) ? 'place:' . $location['placeId'] . ':' . $timestamp : '';
                yield self::draft('google_timeline', (int)$timestamp, $lat, $lon, [], $eventKey);
            }
            return;
        }
        foreach (self::jsonArray($path) as $record) {
            if (!isset($record['latitudeE7'], $record['longitudeE7'], $record['timestamp'])) continue;
            $timestamp = strtotime((string)$record['timestamp']);
            if ($timestamp === false) continue;
            yield self::draft('google_timeline', $timestamp, (float)$record['latitudeE7'] / 1e7, (float)$record['longitudeE7'] / 1e7, [
                'accuracy_m' => isset($record['accuracy']) && is_numeric($record['accuracy']) ? (float)$record['accuracy'] : null,
                'altitude_m' => isset($record['altitude']) && is_numeric($record['altitude']) ? (float)$record['altitude'] : null,
            ]);
        }
    }

    /** @return Generator<int,array<string,mixed>> */
    private static function parseOwnTracks(string $path): Generator
    {
        $fh = fopen($path, 'rb');
        if (!$fh) return;
        try {
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if ($line === '') continue;
                $record = json_decode($line, true);
                if (!is_array($record) || ($record['_type'] ?? '') !== 'location') continue;
                if (!isset($record['lat'], $record['lon'], $record['tst'])) continue;
                yield self::draft('owntracks', (int)$record['tst'], (float)$record['lat'], (float)$record['lon'], [
                    'accuracy_m' => isset($record['acc']) && is_numeric($record['acc']) ? (float)$record['acc'] : null,
                    'altitude_m' => isset($record['alt']) && is_numeric($record['alt']) ? (float)$record['alt'] : null,
                ]);
            }
        } finally {
            fclose($fh);
        }
    }

    /** @return Generator<int,array<string,mixed>> */
    private static function parseImmich(string $path): Generator
    {
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) throw new RuntimeException('invalid_immich');
        $assets = $data['assets'] ?? $data;
        if (isset($assets['exifInfo'])) $assets = [$assets];
        foreach ($assets as $asset) {
            if (!is_array($asset)) continue;
            $exif = $asset['exifInfo'] ?? $asset;
            if (!is_array($exif)) continue;
            $lat = $exif['latitude'] ?? $exif['gpsLatitude'] ?? null;
            $lon = $exif['longitude'] ?? $exif['gpsLongitude'] ?? null;
            $time = $exif['dateTimeOriginal'] ?? $exif['dateTimeOriginal'] ?? $asset['fileCreatedAt'] ?? $asset['createdAt'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lon) || $time === null) continue;
            $timestamp = is_numeric($time) ? (int)$time : strtotime((string)$time);
            if ($timestamp === false || ((float)$lat == 0.0 && (float)$lon == 0.0)) continue;
            $assetId = (string)($asset['id'] ?? $asset['assetId'] ?? $asset['immich_asset_id'] ?? '');
            yield self::draft('immich', $timestamp, (float)$lat, (float)$lon, [
                'accuracy_m' => null,
            ], $assetId !== '' ? 'asset:' . $assetId : '');
        }
    }

    /** @return Generator<int,array<string,mixed>> */
    private static function parseCsv(string $path): Generator
    {
        $fh = fopen($path, 'rb');
        if (!$fh) return;
        try {
            $header = fgetcsv($fh, 0, ',', '"', '\\');
            if (!is_array($header)) return;
            $map = [];
            foreach ($header as $index => $name) $map[strtolower(trim((string)$name))] = $index;
            $latKey = $map['latitude'] ?? $map['lat'] ?? null;
            $lonKey = $map['longitude'] ?? $map['lon'] ?? $map['lng'] ?? null;
            $timeKey = $map['time'] ?? $map['timestamp'] ?? $map['datetime'] ?? $map['date'] ?? null;
            if ($latKey === null || $lonKey === null || $timeKey === null) throw new RuntimeException('invalid_csv');
            while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
                if (!isset($row[$latKey], $row[$lonKey], $row[$timeKey])) continue;
                $timestamp = strtotime((string)$row[$timeKey]);
                if ($timestamp === false) continue;
                yield self::draft('csv', $timestamp, (float)$row[$latKey], (float)$row[$lonKey], [
                    'accuracy_m' => isset($map['accuracy']) && isset($row[$map['accuracy']]) && is_numeric($row[$map['accuracy']]) ? (float)$row[$map['accuracy']] : null,
                    'altitude_m' => isset($map['altitude']) && isset($row[$map['altitude']]) && is_numeric($row[$map['altitude']]) ? (float)$row[$map['altitude']] : null,
                ]);
            }
        } finally {
            fclose($fh);
        }
    }

    /** @return Generator<int,array<string,mixed>> */
    private static function parseExif(string $path): Generator
    {
        $data = file_get_contents($path);
        if (!is_string($data) || !str_starts_with($data, "\xFF\xD8")) return;
        $length = strlen($data);
        $offset = 2;
        $tiff = null;
        while ($offset + 4 < $length) {
            if (ord($data[$offset]) !== 0xFF) break;
            $marker = ord($data[$offset + 1]);
            if ($marker === 0xD8 || $marker === 0x01) { $offset += 2; continue; }
            if ($offset + 4 > $length) break;
            $size = (unpack('n', substr($data, $offset + 2, 2))[1] ?? 0);
            if ($marker === 0xE1) {
                $segment = substr($data, $offset + 4, $size - 2);
                if (str_starts_with($segment, "Exif\0\0")) { $tiff = substr($segment, 6); break; }
            }
            $offset += 2 + $size;
        }
        if ($tiff === null) return;
        $gps = self::parseExifGps($tiff);
        if ($gps === null) return;
        $timestamp = $gps['timestamp'] ?? false;
        if ($timestamp === false) return;
        yield self::draft('exif', (int)$timestamp, $gps['latitude'], $gps['longitude'], [
            'altitude_m' => $gps['altitude'] ?? null,
        ], $gps['event_key'] ?? '');
    }

    /** Minimal TIFF/EXIF reader so imports do not require ext-exif. */
    private static function parseExifGps(string $tiff): ?array
    {
        $len = strlen($tiff);
        if ($len < 8) return null;
        $byteOrder = substr($tiff, 0, 2);
        if ($byteOrder !== 'II' && $byteOrder !== 'MM') return null;
        $little = $byteOrder === 'II';
        $u16 = static fn(string $s, int $o): int => $little ? (unpack('v', substr($s, $o, 2))[1] ?? 0) : (unpack('n', substr($s, $o, 2))[1] ?? 0);
        $u32 = static fn(string $s, int $o): int => $little ? (unpack('V', substr($s, $o, 4))[1] ?? 0) : (unpack('N', substr($s, $o, 4))[1] ?? 0);
        if ($u16($tiff, 2) !== 42) return null;
        $ifd0 = $u32($tiff, 4);
        $readIfd = static function (int $offset) use ($tiff, $len, $u16, $u32): array {
            if ($offset <= 0 || $offset + 2 > $len) return [];
            $count = $u16($tiff, $offset);
            $entries = [];
            for ($i = 0; $i < $count; $i++) {
                $entry = $offset + 2 + $i * 12;
                if ($entry + 12 > $len) break;
                $tag = $u16($tiff, $entry);
                $type = $u16($tiff, $entry + 2);
                $num = $u32($tiff, $entry + 4);
                $valueOffset = $entry + 8;
                $entries[$tag] = ['type' => $type, 'count' => $num, 'offset' => $valueOffset];
            }
            return $entries;
        };
        $value = static function (array $entry) use ($tiff, $len, $u16, $u32, $little): string {
            $sizes = [1 => 1, 2 => 1, 3 => 2, 4 => 4, 5 => 8, 7 => 1, 9 => 4, 10 => 8];
            $size = ($sizes[$entry['type']] ?? 1) * max(1, (int)$entry['count']);
            $offset = $size <= 4 ? $entry['offset'] : $u32($tiff, $entry['offset']);
            if ($offset < 0 || $offset + $size > $len) return '';
            return substr($tiff, $offset, $size);
        };
        $ascii = static fn(array $entry): string => rtrim(str_replace("\0", '', $value($entry)), " \0");
        $rational = static function (array $entry) use ($value, $u32): float {
            $raw = $value($entry);
            if (strlen($raw) < 8) return 0.0;
            $num = $u32($raw, 0);
            $den = $u32($raw, 4);
            return $den === 0 ? 0.0 : $num / $den;
        };
        $rationals = static function (array $entry) use ($value, $u32): array {
            $raw = $value($entry);
            $out = [];
            $count = (int)$entry['count'];
            for ($i = 0; $i < $count; $i++) {
                if (strlen($raw) < ($i + 1) * 8) break;
                $num = $u32($raw, $i * 8);
                $den = $u32($raw, $i * 8 + 4);
                $out[] = $den === 0 ? 0.0 : $num / $den;
            }
            return $out;
        };
        $dms = static function (array $entry) use ($rationals): float {
            $parts = $rationals($entry);
            return (float)($parts[0] ?? 0) + (float)($parts[1] ?? 0) / 60 + (float)($parts[2] ?? 0) / 3600;
        };

        $ifd0Entries = $readIfd($ifd0);
        $gpsIfd = isset($ifd0Entries[0x8825]) ? $u32($tiff, $ifd0Entries[0x8825]['offset']) : 0;
        $exifIfd = isset($ifd0Entries[0x8769]) ? $u32($tiff, $ifd0Entries[0x8769]['offset']) : 0;
        $gpsEntries = $readIfd($gpsIfd);
        if (!isset($gpsEntries[2], $gpsEntries[4])) return null;
        $lat = $dms($gpsEntries[2]);
        $lon = $dms($gpsEntries[4]);
        if (isset($gpsEntries[1]) && strtoupper($ascii($gpsEntries[1])) === 'S') $lat = -$lat;
        if (isset($gpsEntries[3]) && strtoupper($ascii($gpsEntries[3])) === 'W') $lon = -$lon;
        $altitude = isset($gpsEntries[6]) ? $rational($gpsEntries[6]) : null;
        if (isset($gpsEntries[5]) && $ascii($gpsEntries[5]) === '1' && $altitude !== null) $altitude = -$altitude;

        $timestamp = false;
        if (isset($gpsEntries[29], $gpsEntries[7])) {
            $date = $ascii($gpsEntries[29]);
            $parts = $rationals($gpsEntries[7]);
            $time = sprintf('%02d:%02d:%02d', (int)($parts[0] ?? 0), (int)($parts[1] ?? 0), (int)($parts[2] ?? 0));
            $timestamp = strtotime(trim($date . ' ' . $time . ' UTC'));
        }
        if ($timestamp === false && $exifIfd > 0) {
            $exifEntries = $readIfd($exifIfd);
            foreach ([0x9003, 0x9004] as $tag) {
                if (isset($exifEntries[$tag])) {
                    $timestamp = strtotime($ascii($exifEntries[$tag]) . ' UTC');
                    if ($timestamp !== false) break;
                }
            }
        }
        return ['latitude' => $lat, 'longitude' => $lon, 'altitude' => $altitude, 'timestamp' => $timestamp];
    }
}
