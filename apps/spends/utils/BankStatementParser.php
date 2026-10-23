<?php
declare(strict_types=1);

/**
 * Bridge to the Python statement parser (parser_bridge.py).
 *
 * All parsing happens in Python:
 *   - CSV  -> universal built-in engine (any bank, auto-detected
 *     encoding/delimiter/columns, EN/DE/FR/ES/PL/UK/RU/... headers)
 *   - CAMT / PAIN.001 / OFX / QFX / MT940 -> bankstatementparser
 *
 * No PHP-side CSV parsing, no automatic package installation.
 */
final class BankStatementParser
{
    private const PARSER_SCRIPT = __DIR__ . '/parser_bridge.py';

    /** Preferred interpreter inside the parser venv (if present). */
    private const VENV_PYTHON = __DIR__ . '/.venv-parser/bin/python3';

    public const MAX_BYTES = 16777216;

    /**
     * Resolve the Python interpreter used for parsing.
     * Order: dedicated parser venv, system python3 (used inside the
     * Docker image where bankstatementparser is preinstalled).
     */
    public static function pythonBin(): string
    {
        if (is_file(self::VENV_PYTHON) && is_executable(self::VENV_PYTHON)) {
            return self::VENV_PYTHON;
        }
        return 'python3';
    }

    /**
     * Parse bank statement file into the legacy JSON structure.
     *
     * @return array{status:string,transactions:list<array>,groups:list<array>,
     *   duplicate_count:int,row_count:int,confidence:float,
     *   source_format:string,source_method:string,engine:string,
     *   detection:array|null,verification:array|null,warnings:list<string>}
     */
    public static function parse(string $filePath, array $options = []): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: $filePath");
        }
        if (filesize($filePath) > self::MAX_BYTES) {
            throw new InvalidArgumentException('File too large (max 64MB)');
        }

        $sidecarHelper = dirname(__DIR__, 3) . '/lib/py_sidecar.php';
        if (is_file($sidecarHelper)) {
            require_once $sidecarHelper;
        }
        if (function_exists('py_sidecar_enabled') && py_sidecar_enabled()) {
            $res = py_sidecar('parse-bank-statement', [], $filePath, 'file', 90);
            if ($res['code'] < 200 || $res['code'] >= 300 || !is_array($res['body'])) {
                $detail = is_array($res['body']) ? json_encode($res['body']) : ($res['error'] ?: $res['raw']);
                throw new RuntimeException('Parser failed: ' . substr((string)$detail, 0, 500));
            }
            return $res['body'];
        }

        $outputFile = tempnam(sys_get_temp_dir(), 'bsp_result_');
        if ($outputFile === false) {
            throw new RuntimeException('Failed to create temp output file');
        }

        $cmd = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg(self::pythonBin()),
            escapeshellarg(self::PARSER_SCRIPT),
            escapeshellarg($filePath),
            escapeshellarg($outputFile)
        );

        $output = [];
        exec($cmd, $output, $ret);

        if ($ret !== 0) {
            @unlink($outputFile);
            throw new RuntimeException(
                'Parser failed: ' . implode("\n", array_slice($output, 0, 20))
            );
        }

        if (!file_exists($outputFile)) {
            throw new RuntimeException('Parser did not produce output file');
        }

        $json = file_get_contents($outputFile);
        unlink($outputFile);

        if ($json === false) {
            throw new RuntimeException('Failed to read parser output');
        }

        $result = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
            throw new RuntimeException(
                'Invalid JSON from parser: ' . json_last_error_msg()
            );
        }

        return $result;
    }

    /**
     * Detect statement format (informational).
     */
    public static function detectFormat(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($ext, ['csv', 'xml', 'ofx', 'qfx', 'mt940', 'sta', 'pdf'], true)
            ? $ext
            : 'unknown';
    }

    /**
     * Map parser output to the structure consumed by the bank import UI.
     *
     * @param array $parsed result of parse()
     * @param array $storeNames existing store names for best-match hints
     */
    public static function toLegacyFormat(
        array $parsed,
        array $storeNames = [],
        string $baseCurrency = 'USD'
    ): array {
        $transactions = [];
        $groups = [];
        $duplicateCount = 0;
        $seen = [];

        foreach ($parsed['transactions'] ?? [] as $tx) {
            $date = $tx['date'] ?? null;
            if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            $amount = (float) ($tx['amount'] ?? 0);
            if ($amount <= 0.0) {
                continue;
            }
            $isIncome = (bool) ($tx['income'] ?? false);
            $merchantRaw = trim((string) ($tx['merchant_raw'] ?? ''));
            if ($merchantRaw === '') {
                $merchantRaw = '—';
            }
            $amount = round($amount, 2);
            $storeDefault = self::bestStoreMatch($merchantRaw, $storeNames);
            $signature = (string) ($tx['signature'] ?? md5(
                $date . '|' . $merchantRaw . '|' . $amount . '|' . ($isIncome ? 'i' : 'e')
            ));
            if (isset($seen[$signature])) {
                $duplicateCount++;
                continue;
            }
            $seen[$signature] = true;

            $groupKey = (string) ($tx['group_key'] ?? (($isIncome ? 'in:' : 'ex:') . $merchantRaw));

            $transactions[] = [
                'date' => $date,
                'income' => $isIncome,
                'amount' => $amount,
                'currency' => (string) ($tx['currency'] ?? ''),
                'merchant_raw' => $merchantRaw,
                'category' => (string) ($tx['category'] ?? ''),
                'store_default' => $storeDefault,
                'group_key' => $groupKey,
                'signature' => $signature,
                'confidence' => (float) ($tx['confidence'] ?? 1.0),
                'source_method' => (string) ($tx['source_method'] ?? 'deterministic'),
            ];

            if (!isset($groups[$groupKey])) {
                $label = $merchantRaw;
                $cat = (string) ($tx['category'] ?? '');
                if ($cat !== '') {
                    $label .= ' · ' . $cat;
                }
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'label' => $label,
                    'income' => $isIncome,
                    'count' => 0,
                    'total' => 0.0,
                    'dates' => [],
                ];
            }
            $groups[$groupKey]['count']++;
            $groups[$groupKey]['total'] += $amount;
            if (!in_array($date, $groups[$groupKey]['dates'], true) && count($groups[$groupKey]['dates']) < 40) {
                $groups[$groupKey]['dates'][] = $date;
            }
        }

        foreach ($groups as &$g) {
            sort($g['dates']);
            $g['total'] = round($g['total'], 2);
        }
        unset($g);

        $detection = $parsed['detection'] ?? null;

        return [
            'status' => 'success',
            'transactions' => $transactions,
            'groups' => array_values($groups),
            'duplicate_count' => $duplicateCount,
            'row_count' => count($transactions),
            'confidence' => (float) ($parsed['confidence'] ?? 1.0),
            'source_format' => (string) ($parsed['source_format'] ?? 'unknown'),
            'source_method' => (string) ($parsed['source_method'] ?? 'deterministic'),
            'engine' => (string) ($parsed['engine'] ?? 'unknown'),
            'detection' => is_array($detection) ? $detection : null,
            'verification' => $parsed['verification'] ?? null,
        ];
    }

    /**
     * Find best matching user store for a merchant label (hint for UI).
     */
    private static function bestStoreMatch(string $label, array $storeNames): string
    {
        if ($storeNames === []) {
            return $label;
        }
        $norm = static function (string $s): string {
            $s = trim($s);
            if (function_exists('mb_strtolower')) {
                $s = mb_strtolower($s, 'UTF-8');
            } else {
                $s = strtolower($s);
            }
            return (string) preg_replace('/\s+/u', ' ', $s);
        };
        $nk = $norm($label);
        foreach ($storeNames as $sn) {
            if ($norm((string) $sn) === $nk) {
                return (string) $sn;
            }
        }
        $best = null;
        $bestPct = 0.0;
        foreach ($storeNames as $sn) {
            $pct = 0.0;
            similar_text($nk, $norm((string) $sn), $pct);
            if ($pct > $bestPct) {
                $bestPct = $pct;
                $best = (string) $sn;
            }
        }
        return ($best !== null && $bestPct >= 40.0) ? $best : $label;
    }
}
