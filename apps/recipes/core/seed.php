<?php
/**
 * Idempotent seeding of the default recipes data set.
 *
 * A fresh deployment starts with an empty database: the schema is created
 * on the first request, but without any content. This module applies the
 * bundled seed data (products, recipes, steps and config) so the app is
 * usable right after installation.
 *
 * The seed is applied with ON CONFLICT DO NOTHING inside a transaction, so
 * running it again on an existing installation never overwrites user edits
 * and a failure never leaves the data half written.
 */

if (!defined('RECIPES_SEED_SQL_FILE')) {
    define('RECIPES_SEED_SQL_FILE', __DIR__ . '/seed.sql');
}

if (!function_exists('recipes_seed_split_statements')) {
    /**
     * Split a SQL script into single statements.
     *
     * The script is scanned character by character so that semicolons inside
     * string literals do not split a statement. A doubled quote ('') is an
     * escaped quote and does not end the literal.
     *
     * @return string[]
     */
    function recipes_seed_split_statements(string $sql): array
    {
        // First strip the line comments. They are only recognised outside of
        // string literals, so the script is scanned line by line while
        // keeping track of the quote state.
        $withoutComments = '';
        $inString = false;
        $length = strlen($sql);
        $lineStart = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inString) {
                if ($char === "'") {
                    // A doubled quote is an escaped quote, not the end.
                    if ($i + 1 < $length && $sql[$i + 1] === "'") {
                        $i++;
                        continue;
                    }
                    $inString = false;
                }
                continue;
            }

            if ($char === "'") {
                $inString = true;
                continue;
            }

            // A "--" starts a comment that runs to the end of the line.
            if ($char === '-' && $i + 1 < $length && $sql[$i + 1] === '-') {
                // Copy the text before the comment, drop the comment itself.
                $withoutComments .= substr($sql, $lineStart, $i - $lineStart);
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $lineStart = $i;
                continue;
            }
        }
        $withoutComments .= substr($sql, $lineStart);

        // Now split on the semicolons that are not inside a string literal.
        $statements = [];
        $current = '';
        $inString = false;
        $length = strlen($withoutComments);

        for ($i = 0; $i < $length; $i++) {
            $char = $withoutComments[$i];

            if ($inString) {
                $current .= $char;
                if ($char === "'") {
                    if ($i + 1 < $length && $withoutComments[$i + 1] === "'") {
                        $current .= $withoutComments[++$i];
                        continue;
                    }
                    $inString = false;
                }
                continue;
            }

            if ($char === "'") {
                $inString = true;
                $current .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($current);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $statement = trim($current);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        // Drop the transaction control of the script, the seed is run inside
        // its own transaction.
        $result = [];
        foreach ($statements as $statement) {
            if (preg_match('/^(BEGIN|COMMIT|ROLLBACK)\b/i', $statement)) {
                continue;
            }
            $result[] = $statement;
        }

        return $result;
    }
}

if (!function_exists('recipes_seed_table_is_empty')) {
    function recipes_seed_table_is_empty($db, string $table): bool
    {
        try {
            $count = $db->querySingle('SELECT COUNT(*) FROM ' . $table);
            return (int)$count === 0;
        } catch (Throwable $e) {
            error_log('recipes seed: cannot inspect ' . $table . ': ' . $e->getMessage());
            return true;
        }
    }
}

if (!function_exists('recipes_seed_apply')) {
    /**
     * Apply the bundled seed data.
     *
     * Seeding only happens while the target tables are still empty, so an
     * installation that already has content is never modified. Each table
     * is checked separately, which keeps a partially seeded database from
     * blocking the remaining tables.
     *
     * @return bool True when the seed was applied.
     */
    function recipes_seed_apply($db = null): bool
    {
        if ($db === null) {
            if (!function_exists('getDB')) {
                return false;
            }
            $db = getDB();
        }
        if (!$db) {
            return false;
        }

        $seedFile = RECIPES_SEED_SQL_FILE;
        if (!is_file($seedFile) || !is_readable($seedFile)) {
            error_log('recipes seed: seed file not readable: ' . $seedFile);
            return false;
        }
        $sql = (string)file_get_contents($seedFile);
        if (trim($sql) === '') {
            return false;
        }

        $tables = [
            'recipes_products',
            'recipes_data',
            'recipes_steps',
            'recipes_config',
        ];

        // Only seed tables that are still empty.
        $pending = [];
        foreach ($tables as $table) {
            if (recipes_seed_table_is_empty($db, $table)) {
                $pending[] = $table;
            }
        }
        if (!$pending) {
            return false;
        }

        $statements = recipes_seed_split_statements($sql);
        if (!$statements) {
            return false;
        }

        $applied = false;
        try {
            $db->exec('BEGIN');
        } catch (Throwable $e) {
            error_log('recipes seed: cannot start transaction: ' . $e->getMessage());
            return false;
        }

        try {
            foreach ($statements as $statement) {
                // The seed may only contain INSERT statements, so that no
                // schema or data outside the known tables is touched.
                if (!preg_match('/^INSERT\s+INTO\s+([a-z0-9_]+)/i', $statement, $m)) {
                    error_log('recipes seed: skipped unexpected statement: ' . substr($statement, 0, 80));
                    continue;
                }
                // Only run the statements for the tables that still need seeding.
                $target = strtolower($m[1]);
                if (!in_array($target, $pending, true)) {
                    continue;
                }
                $db->exec($statement);
                $applied = true;
            }
            $db->exec('COMMIT');
        } catch (Throwable $e) {
            error_log('recipes seed: failed to apply seed: ' . $e->getMessage());
            try {
                $db->exec('ROLLBACK');
            } catch (Throwable $rollbackError) {
                error_log('recipes seed: rollback failed: ' . $rollbackError->getMessage());
            }
            return false;
        }

        return $applied;
    }
}

if (!function_exists('recipes_seed_ensure')) {
    /**
     * Ensure the default products and recipes exist.
     *
     * Safe to call on every request: it does nothing once the tables have
     * content.
     */
    function recipes_seed_ensure($db = null): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            recipes_seed_apply($db);
        } catch (Throwable $e) {
            error_log('recipes seed: ' . $e->getMessage());
        }
    }
}
