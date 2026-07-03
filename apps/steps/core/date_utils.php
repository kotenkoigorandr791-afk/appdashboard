<?php
// ─── Утилиты дат ─────────────────────────────────────────────────────────────
// dd-mm-yy → YYYY-MM-DD
function csvDateToISO(string $raw): ?string {
    $parts = explode('-', trim($raw));
    if (count($parts) !== 3) return null;
    [$d, $m, $y] = $parts;
    if (!is_numeric($d) || !is_numeric($m) || !is_numeric($y)) return null;
    $fullYear = (int)$y < 70 ? "20$y" : "19$y";
    return "$fullYear-$m-$d";
}

// YYYY-MM-DD → dd-mm-yy
function isoToCSVDate(string $iso): string {
    return (new DateTime($iso))->format('d-m-y');
}

