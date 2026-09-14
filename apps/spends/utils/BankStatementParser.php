<?php
declare(strict_types=1);

/**
 * PHP wrapper for bankstatementparser Python library
 * Supports multiple formats: CAMT, PAIN.001, CSV, OFX, QFX, MT940, PDF
 */
final class BankStatementParser
{
    private const PYTHON_VENV = '/var/www/AppDashboard/apps/spends/utils/.venv';
    private const PYTHON_BIN = self::PYTHON_VENV . '/bin/python3';
    private const PARSER_SCRIPT = __DIR__ . '/parser_bridge.py';
    
    public const MAX_BYTES = 67108864; // 64MB (increased from 15MB)
    
    private static ?bool $initialized = null;
    
    /**
     * Ensure Python environment is initialized
     */
    private static function ensureInitialized(): void
    {
        if (self::$initialized !== null) {
            return;
        }
        
        // Check if venv exists
        if (!is_dir(self::PYTHON_VENV)) {
            exec('python3 -m venv ' . escapeshellarg(self::PYTHON_VENV) . ' 2>&1', $output, $ret);
            if ($ret !== 0) {
                throw new RuntimeException('Failed to create Python venv: ' . implode("\n", $output));
            }
        }
        
        // Check if bankstatementparser is installed
        $checkCmd = self::PYTHON_BIN . ' -c "import bankstatementparser" 2>&1';
        exec($checkCmd, $output, $ret);
        
        if ($ret !== 0) {
            // Install bankstatementparser with all extras
            $pipCmd = self::PYTHON_BIN . ' -m pip install --quiet "bankstatementparser[excel,hybrid,hybrid-plus,hybrid-vision,enrichment]" 2>&1';
            exec($pipCmd, $pipOutput, $pipRet);
            if ($pipRet !== 0) {
                throw new RuntimeException('Failed to install bankstatementparser: ' . implode("\n", $pipOutput));
            }
        }
        
        self::$initialized = true;
    }
    
    /**
     * Parse bank statement file
     * 
     * @param string $filePath Path to statement file
     * @param array $options Additional options
     * @return array Parsed transactions and metadata
     */
    public static function parse(string $filePath, array $options = []): array
    {
        self::ensureInitialized();
        
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: $filePath");
        }
        
        if (filesize($filePath) > self::MAX_BYTES) {
            throw new InvalidArgumentException('File too large (max 64MB)');
        }
        
        // Create temporary output file for JSON result
        $outputFile = tempnam(sys_get_temp_dir(), 'bsp_result_');
        
        $cmd = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg(self::PYTHON_BIN),
            escapeshellarg(self::PARSER_SCRIPT),
            escapeshellarg($filePath),
            escapeshellarg($outputFile)
        );
        
        exec($cmd, $output, $ret);
        
        if ($ret !== 0) {
            @unlink($outputFile);
            throw new RuntimeException('Parser failed: ' . implode("\n", $output));
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
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON from parser: ' . json_last_error_msg());
        }
        
        return $result;
    }
    
    /**
     * Detect statement format
     * 
     * @param string $filePath Path to statement file
     * @return string Format name (csv, camt, ofx, qfx, mt940, pdf, unknown)
     */
    public static function detectFormat(string $filePath): string
    {
        self::ensureInitialized();
        
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: $filePath");
        }
        
        $cmd = sprintf(
            '%s -c "from bankstatementparser import detect_statement_format; print(detect_statement_format(%s))" 2>&1',
            escapeshellarg(self::PYTHON_BIN),
            escapeshellarg($filePath)
        );
        
        exec($cmd, $output, $ret);
        
        if ($ret !== 0 || empty($output)) {
            return 'unknown';
        }
        
        return trim($output[0]);
    }
    
    /**
     * Convert parsed result to legacy format compatible with BankStatementImport
     * 
     * @param array $parsed Result from parse()
     * @param array $storeNames Existing store names for matching
     * @param string $baseCurrency Base currency for the user
     * @return array Compatible format for existing code
     */
    public static function toLegacyFormat(array $parsed, array $storeNames = [], string $baseCurrency = 'PLN'): array
    {
        $transactions = [];
        $groups = [];
        $duplicateCount = 0;
        $seenSignatures = [];
        
        foreach ($parsed['transactions'] as $tx) {
            $date = $tx['date'] ?? null;
            $amount = $tx['amount'] ?? 0;
            $description = $tx['description'] ?? '';
            $currency = $tx['currency'] ?? $baseCurrency;
            
            if (!$date) {
                continue;
            }
            
            // Determine if income or expense
            $isIncome = $amount > 0;
            $absAmount = abs($amount);
            
            // Extract merchant name
            $merchantRaw = self::extractMerchant($tx);
            
            // Find best matching store
            $storeDefault = self::bestStoreMatch($merchantRaw, $storeNames);
            
            // Create signature for deduplication
            $signature = md5($date . '|' . $merchantRaw . '|' . $absAmount);
            
            if (isset($seenSignatures[$signature])) {
                $duplicateCount++;
                continue;
            }
            
            $seenSignatures[$signature] = true;
            
            // Create transaction in legacy format
            $transaction = [
                'date' => $date,
                'income' => $isIncome,
                'amount' => $absAmount,
                'currency' => $currency,
                'merchant_raw' => $merchantRaw,
                'category' => $tx['category'] ?? '',
                'store_default' => $storeDefault,
                'group_key' => $date . '|' . $storeDefault,
                'signature' => $signature,
                'confidence' => $tx['confidence'] ?? 1.0,
                'source_method' => $tx['source_method'] ?? 'deterministic',
            ];
            
            $transactions[] = $transaction;
            
            // Group by date + store
            $groupKey = $transaction['group_key'];
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'date' => $date,
                    'store' => $storeDefault,
                    'items' => [],
                    'total' => 0,
                ];
            }
            
            $groups[$groupKey]['items'][] = $transaction;
            $groups[$groupKey]['total'] += ($isIncome ? $absAmount : -$absAmount);
        }
        
        return [
            'status' => 'success',
            'transactions' => $transactions,
            'groups' => array_values($groups),
            'duplicate_count' => $duplicateCount,
            'row_count' => count($parsed['transactions']),
            'confidence' => $parsed['confidence'] ?? 1.0,
            'source_format' => $parsed['source_format'] ?? 'unknown',
            'source_method' => $parsed['source_method'] ?? 'deterministic',
            'verification' => $parsed['verification'] ?? null,
        ];
    }
    
    /**
     * Extract merchant name from transaction
     */
    private static function extractMerchant(array $tx): string
    {
        $description = $tx['description'] ?? '';
        $creditor = $tx['creditor_name'] ?? '';
        $debtor = $tx['debtor_name'] ?? '';
        $category = $tx['category'] ?? '';
        
        // Priority: creditor > debtor > description > category
        if (!empty($creditor) && $creditor !== '***REDACTED***') {
            return self::normalizeMerchant($creditor);
        }
        
        if (!empty($debtor) && $debtor !== '***REDACTED***') {
            return self::normalizeMerchant($debtor);
        }
        
        if (!empty($description)) {
            return self::normalizeMerchant($description);
        }
        
        if (!empty($category)) {
            return self::normalizeMerchant($category);
        }
        
        return 'Unknown';
    }
    
    /**
     * Normalize merchant name
     */
    private static function normalizeMerchant(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name);
        
        // Remove common prefixes
        $name = preg_replace('/^(Payment to|Transfer to|From|To|Payment|Transfer)\s+/i', '', $name);
        
        // Truncate at 100 chars
        if (mb_strlen($name, 'UTF-8') > 100) {
            $name = mb_substr($name, 0, 100, 'UTF-8');
        }
        
        return $name;
    }
    
    /**
     * Find best matching store from existing store names
     */
    private static function bestStoreMatch(string $label, array $storeNames): string
    {
        if (empty($storeNames)) {
            return $label;
        }
        
        $label = mb_strtolower($label, 'UTF-8');
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($storeNames as $storeName) {
            $store = mb_strtolower($storeName, 'UTF-8');
            
            // Exact match
            if ($label === $store) {
                return $storeName;
            }
            
            // Contains match
            if (mb_strpos($label, $store) !== false || mb_strpos($store, $label) !== false) {
                $score = max(mb_strlen($store, 'UTF-8'), mb_strlen($label, 'UTF-8'));
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $storeName;
                }
            }
            
            // Levenshtein similarity (only for short strings)
            if (strlen($label) < 100 && strlen($store) < 100) {
                $dist = levenshtein($label, $store);
                $maxLen = max(strlen($label), strlen($store));
                $similarity = 1 - ($dist / $maxLen);
                
                if ($similarity > 0.7 && $similarity > ($bestScore / 100)) {
                    $bestScore = (int)($similarity * 100);
                    $bestMatch = $storeName;
                }
            }
        }
        
        return $bestMatch ?? $label;
    }
}
