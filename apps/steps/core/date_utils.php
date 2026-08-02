<?php
// Date utilities
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

// Get year from date in YYYY-MM-DD format
function getYearFromISODate(string $isoDate): ?int {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $isoDate, $matches)) return null;
    return (int)$matches[1];
}

// Get month from date in YYYY-MM-DD format
function getMonthFromISODate(string $isoDate): ?int {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $isoDate, $matches)) return null;
    return (int)$matches[2];
}

// Get current year
function getCurrentYear(): int {
    return (int)(new DateTime())->format('Y');
}

// Get first day of year
function getFirstDayOfYear(int $year): string {
    return "{$year}-01-01";
}

// Get last day of year
function getLastDayOfYear(int $year): string {
    return "{$year}-12-31";
}

// Get first day of month
function getFirstDayOfMonth(int $year, int $month): string {
    return sprintf("%04d-%02d-01", $year, $month);
}

// Get last day of month
function getLastDayOfMonth(int $year, int $month): string {
    return date("Y-m-t", strtotime(sprintf("%04d-%02d-01", $year, $month)));
}

