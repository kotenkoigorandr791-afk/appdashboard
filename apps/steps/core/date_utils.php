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

// Получение года из даты в формате YYYY-MM-DD
function getYearFromISODate(string $isoDate): ?int {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $isoDate, $matches)) return null;
    return (int)$matches[1];
}

// Получение месяца из даты в формате YYYY-MM-DD
function getMonthFromISODate(string $isoDate): ?int {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $isoDate, $matches)) return null;
    return (int)$matches[2];
}

// Получение текущего года
function getCurrentYear(): int {
    return (int)(new DateTime())->format('Y');
}

// Получение первого дня года
function getFirstDayOfYear(int $year): string {
    return "{$year}-01-01";
}

// Получение последнего дня года
function getLastDayOfYear(int $year): string {
    return "{$year}-12-31";
}

// Получение первого дня месяца
function getFirstDayOfMonth(int $year, int $month): string {
    return sprintf("%04d-%02d-01", $year, $month);
}

// Получение последнего дня месяца
function getLastDayOfMonth(int $year, int $month): string {
    return date("Y-m-t", strtotime(sprintf("%04d-%02d-01", $year, $month)));
}

