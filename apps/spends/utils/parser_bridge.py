#!/usr/bin/env python3
"""
Bridge script between PHP and the parsing stack.

Usage: parser_bridge.py <input_file> <output_file>

Formats:
  * CSV  -> universal built-in engine (pure stdlib). Auto-detects
    encoding (UTF-8/UTF-16/CP1250/CP1251), delimiter, decimal style,
    header/headerless layouts and maps date/amount/merchant columns by
    header keywords (EN/DE/FR/ES/PL/UK/RU/BE/CS...) plus content
    scoring. Works for any bank without per-bank configuration.
  * CAMT / PAIN.001 / OFX / QFX / MT940 / PDF -> bankstatementparser
    (deterministic path, no LLM).
"""

import csv
import io
import json
import re
import sys
import unicodedata
from datetime import datetime, date
from decimal import Decimal, InvalidOperation
from pathlib import Path

# ------------------------------------------------------------------
# Encoding detection
# ------------------------------------------------------------------

_BOMS = (
    ("utf-8-sig", b"\xef\xbb\xbf"),
    ("utf-32-le", b"\xff\xfe\x00\x00"),
    ("utf-32-be", b"\x00\x00\xfe\xff"),
    ("utf-16-le", b"\xff\xfe"),
    ("utf-16-be", b"\xfe\xff"),
)


def decode_bytes(raw: bytes) -> str:
    """Decode raw statement bytes to text: BOM -> UTF-8 -> CP125x/Latin."""
    for enc, bom in _BOMS:
        if raw.startswith(bom):
            try:
                return raw.decode(enc)
            except UnicodeDecodeError:
                break
    try:
        return raw.decode("utf-8")
    except UnicodeDecodeError:
        pass
    # Try Windows / Central-European / Cyrillic legacy codepages.
    for enc in ("cp1250", "cp1251", "cp1252", "cp1254", "latin-1"):
        try:
            text = raw.decode(enc)
            # latin-1 never fails; accept it only if mostly printable.
            if enc == "latin-1":
                printable = sum(
                    1 for ch in text if ch.isprintable() or ch in "\r\n\t"
                )
                if len(text) and printable / len(text) < 0.97:
                    continue
            return text
        except UnicodeDecodeError:
            continue
    return raw.decode("utf-8", errors="replace")


# ------------------------------------------------------------------
# Numbers and dates
# ------------------------------------------------------------------

_AMOUNT_RE = re.compile(
    r"""^\s*
    (?P<neg>[-–−])?\s*
    (?:\+(?=\s*\d))?                               # optional leading '+'
    (?P<num1>\d{1,3}(?:[ \u00a0]\d{3})*|\d+)          # int part (with nbsp groups)
    (?:(?P<sep>[.,])(?P<frac>\d+))?                    # decimal sep
    (?:\s*(?P<cur>[A-Za-z]{3}|₴|\$|€|zł|PLN|UAH|USD|EUR|GBP|CHF|CZK|SEK|NOK|DKK))?
    \s*$""",
    re.VERBOSE,
)


def parse_amount(cell: str):
    """Parse an amount cell -> Decimal or None.

    Handles: -1 234,56 | 1,234.56 | -562.39 | +1200,00 | 1 234,56 PLN.
    """
    if cell is None:
        return None
    s = unicodedata.normalize("NFKC", str(cell))
    s = s.replace("\u2212", "-").replace("\u2013", "-").replace("\u2014", "-")
    s = s.strip().strip('"')
    if not s or s in {"—", "–", "-", "‐"}:
        return None
    m = _AMOUNT_RE.match(s)
    if not m:
        return None
    neg = bool(m.group("neg"))
    int_part = m.group("num1").replace(" ", "").replace("\u00a0", "")
    sep, frac = m.group("sep"), m.group("frac")
    try:
        if sep and frac is not None:
            num = f"{int_part}.{frac}"
        else:
            num = int_part
        val = Decimal(num)
    except InvalidOperation:
        return None
    if neg:
        val = -abs(val)
    return val


_DATE_FORMATS = (
    "%d.%m.%Y %H:%M:%S",
    "%d.%m.%Y",
    "%d.%m.%y",
    "%d/%m/%Y %H:%M:%S",
    "%d/%m/%Y",
    "%d-%m-%Y",
    "%Y-%m-%d %H:%M:%S",
    "%Y-%m-%d",
    "%Y/%m/%d",
    "%d.%m.%Y %H:%M",
    "%Y.%m.%d",
    "%d %b %Y",
    "%d %B %Y",
    "%b %d, %Y",
    "%B %d, %Y",
    "%m/%d/%Y",
)


def parse_date(cell: str):
    """Parse a date cell -> datetime.date or None."""
    if cell is None:
        return None
    s = unicodedata.normalize("NFKC", str(cell)).strip().strip('"')
    if not s or len(s) > 32:
        return None
    # ISO datetime with T
    s2 = s.replace("T", " ")
    for fmt in _DATE_FORMATS:
        try:
            dt = datetime.strptime(s2, fmt)
            if dt.year < 1990 or dt.year > 2100:
                return None
            return dt.date()
        except ValueError:
            continue
    # ISO timestamps with timezone/microseconds
    m = re.match(r"^(\d{4})-(\d{2})-(\d{2})", s2)
    if m:
        try:
            return date(int(m.group(1)), int(m.group(2)), int(m.group(3)))
        except ValueError:
            return None
    return None


def is_date_cell(cell: str) -> bool:
    return parse_date(cell) is not None


def is_amount_cell(cell: str) -> bool:
    return parse_amount(cell) is not None


# ------------------------------------------------------------------
# CSV layout detection
# ------------------------------------------------------------------


def detect_delimiter(text: str):
    """Detect CSV delimiter from the first non-empty line(s)."""
    sample = text[:64 * 1024]
    lines = [ln for ln in sample.splitlines() if ln.strip()][:5]
    if not lines:
        return ","
    candidates = [",", ";", "\t", "|"]
    best, best_score = ",", -1.0
    for cand in candidates:
        try:
            rows = list(csv.reader(lines, delimiter=cand))
        except Exception:
            continue
        if len(rows) < 2:
            score = float(len(rows[0])) if rows else 0.0
        else:
            counts = [len(r) for r in rows]
            avg = sum(counts) / len(counts)
            consistency = 1.0 - (
                (max(counts) - min(counts)) / max(max(counts), 1)
            )
            score = avg * (0.5 + 0.5 * max(0.0, consistency))
        if score > best_score:
            best, best_score = cand, score
    if best_score <= 1.0:
        return ","
    return best


def sniff_rows(text: str, delimiter: str):
    """Parse CSV rows, skipping leading garbage lines if needed."""
    reader = csv.reader(io.StringIO(text), delimiter=delimiter)
    rows = []
    for row in reader:
        cells = [(c or "").strip() for c in row]
        if any(cells):
            rows.append(cells)
    return rows


def looks_like_header(row) -> bool:
    """A header row has mostly non-numeric, non-date cells."""
    if not row:
        return False
    named = 0
    for c in row:
        if not c:
            continue
        if is_date_cell(c) or is_amount_cell(c):
            return False
        named += 1
    return named >= max(2, len([c for c in row if c]) // 2)


# ------------------------------------------------------------------
# Column identification (header keywords + content scoring)
# ------------------------------------------------------------------

# Keyword groups are matched against normalized (lowercase, latin-folded)
# ASCII keywords and against raw lowercase Cyrillic headers.
DATE_KEYS = {
    # EN
    "date", "transactiondate", "bookingdate", "valuedate", "posteddate",
    "datetransaction", "datebooking",
    # DE
    "datum", "buchung", "buchungstag", "buchungsdatum", "wertstellung",
    "operationsdatum", "transaktionsdatum",
    # FR / ES
    "dateoperation", "datevaleur", "fecha", "fechaoperacion", "fechavalor",
    # PL
    "data", "dataoperacji", "datazaksiegowania", "datatransakcji",
    "datawaluty", "dzien", "datai",
    # UK
    "дата", "датаоперації", "датачасоперації", "датачас",
    # RU
    "датаоперации",
}
AMOUNT_KEYS = {
    # EN
    "amount", "transactionamount", "amountin", "value", "sum",
    # DE / FR / ES
    "betrag", "montant", "importe", "summe",
    # PL
    "kwota", "kwotatransakcji", "kwotawwalucie", "wartosc",
    # UK
    "сума", "сумаввалюти", "сумаввалютикартки", "сумаввалютиоперації",
    # RU
    "сумма", "суммаоперации", "суммаввалюте", "суммаввалютекарты",
}
DESCRIPTION_KEYS = {
    # EN
    "description", "details", "detail", "memo", "narrative", "notes",
    "comment", "purpose", "transactiontype", "type",
    # DE / FR / ES
    "beschreibung", "verwendungszweck", "libelle", "concepto",
    "descripcion", "buchungstext",
    # PL
    "opis", "opisoperacji", "opistransakcji", "tytul", "tytulem",
    "opisszczegolowy", "komentarz",
    # UK
    "деталі", "деталіоперації", "опис", "описоперації", "призначення",
    "призначенняплатежу", "коментар",
    # RU
    "описание", "назначениеплатежа", "назначение", "комментарий",
}
MERCHANT_KEYS = {
    # EN
    "merchant", "payee", "counterparty", "contragent", "creditor",
    "debtor", "seller", "vendor", "shop", "store",
    # DE
    "empfaenger", "zahlungsempfaenger", "beguenstigter",
    "zahlungspflichtiger", "auftraggeber",
    # PL
    "miejsce", "miejscetransakcji", "nadawca", "odbiorca", "sklep",
    "nazwa",
    # UK
    "контрагент", "отримувач", "платник", "покупець", "продавець",
    "магазин",
    # RU
    "отправитель", "получатель", "плательщик", "магазин",
}
CATEGORY_KEYS = {
    "category", "kategoria", "categorie", "categoria", "kategorie",
    "typ", "type", "gatunek", "rubric", "mcc",
    "категорія", "категория", "категоріяоперації", "рубрика",
}
CURRENCY_KEYS = {
    "currency", "ccy", "curr", "iso", "waehrung", "devise", "divisa",
    "moneda", "waluta",
    "валюта", "валютаоперації", "валютаоперации",
}
BALANCE_KEYS = {
    "balance", "runningbalance", "balanceafter", "saldo", "solde",
    # PL
    "saldopooperacji",
    # UK
    "залишок", "залишокпісляоперації", "баланс",
    # RU
    "остаток", "остатокпослеоперации", "сальдо",
}
CREDIT_KEYS = {
    # EN / DE / ES / PL
    "credit", "creditamount", "deposit", "inflow", "haben", "abono",
    "ingreso", "przychod", "przychodzacy", "kredit",
    # UK
    "кредит", "прихід", "надходження", "зарахування", "дохід",
    "поповнення",
    # RU
    "приход", "пополнение",
}
DEBIT_KEYS = {
    # EN / DE / ES / PL
    "debit", "debitamount", "withdrawal", "outflow", "soll", "cargo",
    "adeudo", "rozchod", "wydatek", "debet",
    # UK
    "дебет", "видаток", "списання", "витрата", "витрати",
    # RU
    "расход", "списание",
}
TIME_KEYS = {
    "time", "czas", "uhrzeit", "heure", "hora", "godzina",
    "час", "часоперації", "время", "времяоперации",
}


def _latin_fold(name: str) -> str:
    folded = unicodedata.normalize("NFKD", name).encode(
        "ascii", "ignore"
    ).decode("ascii")
    return re.sub(r"[^a-z0-9]+", "", folded.lower())


def _cyr_ok(name: str) -> bool:
    return any("\u0400" <= ch <= "\u04FF" for ch in name)


def header_score(keywords: set, header: str) -> float:
    """Score how well a header matches a keyword group (0..1)."""
    h = header.strip().lower()
    if not h:
        return 0.0
    lf = _latin_fold(h)
    best = 0.0
    for k in keywords:
        if not k:
            continue
        if k.isascii():
            if lf == k:
                return 1.0
            if lf.startswith(k) or lf.endswith(k):
                best = max(best, 0.85)
            elif k in lf and len(k) >= 4:
                best = max(best, 0.7)
        elif _cyr_ok(h):
            hn = re.sub(r"[\s\u00a0\.\-_:,'\"`()]+", "", h)
            if hn == k:
                return 1.0
            if hn.startswith(k) or hn.endswith(k) or k in hn:
                best = max(best, 0.8)
    return best


def _score_col(rows, col, predicate) -> float:
    ok = 0
    total = 0
    for row in rows:
        if col >= len(row):
            continue
        c = row[col]
        if c == "":
            continue
        total += 1
        if predicate(c):
            ok += 1
    return (ok / total) if total else 0.0


def analyze_columns(header, rows):
    """Return dict with column indices for the statement."""
    n_cols = max((len(r) for r in rows), default=len(header))
    n_cols = max(n_cols, len(header))

    date_scores = {}
    amount_scores = {}
    text_scores = {}

    for i in range(n_cols):
        h = header[i] if i < len(header) else ""
        ds = _score_col(rows, i, is_date_cell)
        as_ = _score_col(rows, i, is_amount_cell)
        ts = _score_col(
            rows, i,
            lambda c: (
                len(c) >= 3
                and not is_amount_cell(c)
                and not is_date_cell(c)
            ),
        )
        date_scores[i] = ds
        amount_scores[i] = as_
        text_scores[i] = ts

    # --- date column ---
    date_idx = None
    best = 0.0
    for i in range(n_cols):
        h = header[i] if i < len(header) else ""
        hs = header_score(DATE_KEYS, h)
        if hs <= 0:
            continue
        sc = hs * 0.6 + date_scores[i] * 0.4
        if sc > best:
            best, date_idx = sc, i
    if date_idx is None:
        for i in range(n_cols):
            sc = date_scores[i]
            if sc > best and sc >= 0.6:
                best, date_idx = sc, i

    # --- time column (optional; informational) ---
    time_idx = None
    for i in range(n_cols):
        h = header[i] if i < len(header) else ""
        if i == date_idx:
            continue
        hs = header_score(TIME_KEYS, h)
        vals = [r[i] for r in rows[:50] if i < len(r) and r[i]]
        looks_time = bool(vals) and all(
            re.fullmatch(r"\d{1,2}:\d{2}(:\d{2})?", v) for v in vals
        )
        if hs >= 0.7 and looks_time:
            time_idx = i
            break

    # --- credit/debit pair (detect BEFORE single amount so that
    # "Debit Amount" / "Credit Amount" headers are not stolen by the
    # generic amount matcher) ---
    credit_idx = debit_idx = None
    for i in range(n_cols):
        h = header[i] if i < len(header) else ""
        if header_score(CREDIT_KEYS, h) >= 0.8 and amount_scores[i] >= 0.4:
            credit_idx = i
        if header_score(DEBIT_KEYS, h) >= 0.8 and amount_scores[i] >= 0.4:
            debit_idx = i
    if credit_idx is not None and credit_idx == debit_idx:
        debit_idx = None
    pair_claimed = {c for c in (credit_idx, debit_idx) if c is not None}

    # --- amount column: signed single column (not a debit/credit one) ---
    amount_idx = None
    best = 0.0
    for i in range(n_cols):
        if i in pair_claimed:
            continue
        h = header[i] if i < len(header) else ""
        if header_score(CREDIT_KEYS | DEBIT_KEYS, h) >= 0.8:
            continue
        hs = header_score(AMOUNT_KEYS, h)
        if hs <= 0:
            continue
        sc = hs * 0.5 + amount_scores[i] * 0.5
        if sc > best:
            best, amount_idx = sc, i
    if amount_idx is None:
        # Content-only: any column with many signed/decimal numbers that
        # is not the date column and not an id-like all-integer column.
        for i in range(n_cols):
            if i == date_idx or i in pair_claimed:
                continue
            h = header[i] if i < len(header) else ""
            if header_score(
                BALANCE_KEYS | CATEGORY_KEYS | CURRENCY_KEYS
                | CREDIT_KEYS | DEBIT_KEYS, h
            ) > 0.5:
                continue
            vals = [
                r[i] for r in rows[:200] if i < len(r) and r[i] != ""
            ]
            if len(vals) < 3:
                continue
            amts = [parse_amount(v) for v in vals]
            amts = [a for a in amts if a is not None]
            if len(amts) / len(vals) < 0.8:
                continue
            signed = any(a < 0 for a in amts)
            decimalish = any(
                a != a.to_integral_value() for a in amts
            )
            if signed or decimalish:
                sc = amount_scores[i]
                if sc > best:
                    best, amount_idx = sc, i
    # fallback: content-only single amount even without sign
    if amount_idx is None:
        for i in range(n_cols):
            if i == date_idx or i in pair_claimed:
                continue
            h = header[i] if i < len(header) else ""
            if header_score(
                BALANCE_KEYS | CURRENCY_KEYS | CATEGORY_KEYS
                | CREDIT_KEYS | DEBIT_KEYS, h
            ) > 0.5:
                continue
            if amount_scores[i] >= 0.8:
                amount_idx = i
                break

    # --- description / merchant ---
    desc_idx = None
    best = 0.0
    for i in range(n_cols):
        h = header[i] if i < len(header) else ""
        hs = header_score(DESCRIPTION_KEYS, h)
        if hs <= 0:
            continue
        sc = hs * 0.7 + text_scores[i] * 0.3
        if sc > best:
            best, desc_idx = sc, i
    if desc_idx is None:
        for i in range(n_cols):
            if i in (date_idx, time_idx, amount_idx, credit_idx, debit_idx):
                continue
            h = header[i] if i < len(header) else ""
            if header_score(
                BALANCE_KEYS | CURRENCY_KEYS | CATEGORY_KEYS, h
            ) > 0.5:
                continue
            if text_scores[i] >= 0.7:
                desc_idx = i
                break

    merch_idx = None
    best = 0.0
    for i in range(n_cols):
        h = header[i] if i < len(header) else ""
        hs = header_score(MERCHANT_KEYS, h)
        if hs <= 0:
            continue
        sc = hs * 0.7 + text_scores[i] * 0.3
        if sc > best:
            best, merch_idx = sc, i

    # --- category ---
    cat_idx = None
    for i in range(n_cols):
        h = header[i] if i < len(header) else ""
        if header_score(CATEGORY_KEYS, h) >= 0.8:
            cat_idx = i
            break

    # --- currency ---
    cur_idx = None
    for i in range(n_cols):
        h = header[i] if i < len(header) else ""
        if header_score(CURRENCY_KEYS, h) >= 0.8:
            cur_idx = i
            break

    # --- balance (informational; excluded from text picks) ---
    balance_idx = None
    for i in range(n_cols):
        h = header[i] if i < len(header) else ""
        if header_score(BALANCE_KEYS, h) >= 0.8:
            balance_idx = i
            break

    # Reject weak picks: a description column that never has text, or a
    # date column that is mostly non-dates.
    if date_idx is not None and date_scores[date_idx] < 0.3:
        date_idx = None
    if desc_idx is not None and text_scores[desc_idx] < 0.2:
        desc_idx = None

    return {
        "date": date_idx,
        "time": time_idx,
        "amount": amount_idx,
        "credit": credit_idx,
        "debit": debit_idx,
        "description": desc_idx,
        "merchant": merch_idx,
        "category": cat_idx,
        "currency": cur_idx,
        "balance": balance_idx,
        "date_score": round(date_scores.get(date_idx, 0.0), 3),
        "amount_score": round(max(
            amount_scores.get(amount_idx, 0.0),
            amount_scores.get(credit_idx, 0.0),
        ), 3),
    }


# ------------------------------------------------------------------
# Row extraction
# ------------------------------------------------------------------


def _row_empty(row) -> bool:
    return all(c == "" for c in row)


def _cell(row, idx):
    if idx is None or idx >= len(row):
        return ""
    return row[idx]


def extract_transactions(rows, cols):
    """Build normalized transaction dicts from parsed CSV rows."""
    txs = []
    for row in rows:
        if _row_empty(row):
            continue
        d = parse_date(_cell(row, cols["date"]))
        if d is None:
            # Rows without a parseable date are skipped.
            continue

        currency = (_cell(row, cols["currency"]) or "").strip().upper()
        currency = re.sub(r"[^A-Z]{3,}", "", currency) or ""

        if cols["credit"] is not None or cols["debit"] is not None:
            cr = parse_amount(_cell(row, cols["credit"]))
            db = parse_amount(_cell(row, cols["debit"]))
            if (cr is None or cr == 0) and (db is None or db == 0):
                continue
            cr = abs(cr) if cr else Decimal("0")
            db = abs(db) if db else Decimal("0")
            if cr >= db:
                sign, value = 1, cr
            else:
                sign, value = -1, db
        elif cols["amount"] is not None:
            amt = parse_amount(_cell(row, cols["amount"]))
            if amt is None or amt == 0:
                continue
            sign = 1 if amt > 0 else -1
            value = abs(amt)
        else:
            continue

        desc = (_cell(row, cols["description"]) or "").strip()
        merch = (_cell(row, cols["merchant"]) or "").strip()
        if not merch and desc:
            merch = desc
        if not merch:
            merch = (_cell(row, cols["category"]) or "").strip() or "—"
        cat = (_cell(row, cols["category"]) or "").strip()

        txs.append(
            {
                "date": d.isoformat(),
                "amount": float(value),
                "sign": sign,
                "currency": currency,
                "description": desc,
                "merchant_raw": merch[:100],
                "category": cat,
            }
        )
    return txs


# ------------------------------------------------------------------
# Layout handling (header vs headerless CSV)
# ------------------------------------------------------------------


def parse_csv_universal(text: str):
    """Parse CSV text of any bank into normalized transactions.

    Returns (transactions, meta) where meta describes detection.
    """
    delimiter = detect_delimiter(text)
    rows = sniff_rows(text, delimiter)
    if not rows:
        raise ValueError("empty_csv")

    header_idx = None
    for i, row in enumerate(rows[:10]):
        if looks_like_header(row):
            header_idx = i
            break

    if header_idx is None:
        # Headerless: synthesize header from column positions.
        n_cols = max(len(r) for r in rows)
        header = [f"col_{i}" for i in range(n_cols)]
        data_rows = rows
    else:
        header = rows[header_idx]
        data_rows = rows[header_idx + 1:]

    cols = analyze_columns(header, data_rows)
    txs = extract_transactions(data_rows, cols)

    columns = {}
    for k in ("date", "amount", "credit", "debit", "description",
              "merchant", "category", "currency", "balance"):
        v = cols.get(k)
        if v is None:
            columns[k] = None
        elif header_idx is not None and v < len(header):
            columns[k] = header[v]
        else:
            columns[k] = v

    meta = {
        "engine": "universal-csv",
        "delimiter": delimiter,
        "layout": "header" if header_idx is not None else "headerless",
        "columns": columns,
        "column_scores": {
            "date": cols["date_score"],
            "amount": cols["amount_score"],
        },
    }
    return txs, meta


# ------------------------------------------------------------------
# bankstatementparser path (CAMT / PAIN.001 / OFX / QFX / MT940 / PDF)
# ------------------------------------------------------------------


def parse_via_bsp(path: str):
    from bankstatementparser.hybrid import smart_ingest

    result = smart_ingest(path)
    txs = []
    confidences = []
    for tx in result.transactions:
        tx_date = tx.booking_date or tx.value_date
        amount = float(tx.amount) if tx.amount is not None else 0.0
        txs.append(
            {
                "date": tx_date.isoformat() if tx_date else None,
                "amount": abs(amount),
                "sign": 1 if amount >= 0 else -1,
                "currency": (tx.currency or "").upper(),
                "description": tx.description or "",
                "merchant_raw": (
                    getattr(tx, "counterparty", None)
                    or tx.description
                    or "—"
                )[:100],
                "category": tx.category or "",
            }
        )
        if tx.confidence is not None:
            confidences.append(float(tx.confidence))
    meta = {
        "engine": "bankstatementparser",
        "source_method": result.source_method,
        "source_format": result.source_format,
    }
    return txs, meta, confidences


# ------------------------------------------------------------------
# Legacy JSON output (consumed by BankStatementParser.php)
# ------------------------------------------------------------------


def build_legacy_output(txs, meta):
    transactions = []
    groups = {}
    seen = set()
    duplicate_count = 0

    for tx in txs:
        d = tx.get("date")
        if not d:
            continue
        value = Decimal(str(tx.get("amount", 0))).quantize(Decimal("0.01"))
        sign = tx.get("sign", 1 if value >= 0 else -1)
        merchant = (tx.get("merchant_raw") or "").strip() or "—"
        currency = (tx.get("currency") or "").upper()
        category = tx.get("category") or ""

        signature = f"{d}|{merchant}|{value}|{'i' if sign > 0 else 'e'}"
        if signature in seen:
            duplicate_count += 1
            continue
        seen.add(signature)

        is_income = sign > 0
        gkey = f"{'in' if is_income else 'ex'}:{merchant}|{category}"
        transactions.append(
            {
                "date": d,
                "income": is_income,
                "amount": float(value),
                "currency": currency,
                "merchant_raw": merchant,
                "category": category,
                "store_default": merchant,
                "group_key": gkey,
                "signature": signature,
                "confidence": 1.0,
                "source_method": meta.get("source_method", "deterministic"),
            }
        )

        g = groups.setdefault(
            gkey,
            {
                "key": gkey,
                "label": f"{merchant}{' · ' + category if category else ''}",
                "income": is_income,
                "count": 0,
                "total": 0.0,
                "dates": [],
            },
        )
        g["count"] += 1
        g["total"] += float(value)
        if d not in g["dates"] and len(g["dates"]) < 40:
            g["dates"].append(d)

    out = {
        "status": "success",
        "transactions": transactions,
        "groups": list(groups.values()),
        "duplicate_count": duplicate_count,
        "row_count": len(transactions),
        "confidence": 1.0,
        "source_format": meta.get("source_format", "csv"),
        "source_method": meta.get("source_method", "deterministic"),
        "engine": meta.get("engine", "unknown"),
        "detection": {
            k: meta[k]
            for k in ("delimiter", "layout", "columns", "column_scores",
                      "source_method", "source_format")
            if k in meta
        },
    }
    return out


# ------------------------------------------------------------------
# Main
# ------------------------------------------------------------------


def main():
    if len(sys.argv) != 3:
        print(
            "Usage: parser_bridge.py <input_file> <output_file>",
            file=sys.stderr,
        )
        sys.exit(1)

    input_file = sys.argv[1]
    output_file = sys.argv[2]

    path = Path(input_file)
    if not path.exists():
        print(f"Error: Input file not found: {input_file}", file=sys.stderr)
        sys.exit(1)

    suffix = path.suffix.lower()

    try:
        confidences = []
        if suffix in {".xml", ".ofx", ".qfx", ".mt940", ".sta", ".pdf"}:
            txs, meta, confidences = parse_via_bsp(str(path))
            out = build_legacy_output(txs, meta)
        elif suffix == ".csv":
            raw = path.read_bytes()
            text = decode_bytes(raw)
            txs, meta = parse_csv_universal(text)
            out = build_legacy_output(txs, meta)
        else:
            # Unknown extension: try BSP first, then universal CSV.
            try:
                txs, meta, confidences = parse_via_bsp(str(path))
                out = build_legacy_output(txs, meta)
            except Exception:
                raw = path.read_bytes()
                text = decode_bytes(raw)
                txs, meta = parse_csv_universal(text)
                out = build_legacy_output(txs, meta)

        out["confidence"] = (
            sum(confidences) / len(confidences) if confidences else 1.0
        )

        with open(output_file, "w", encoding="utf-8") as f:
            json.dump(
                out, f, ensure_ascii=False, indent=2, default=str
            )
        print(
            f"Successfully parsed {len(out['transactions'])} transactions",
            file=sys.stderr,
        )
        sys.exit(0)

    except Exception as e:
        print(f"Error parsing file: {e}", file=sys.stderr)
        import traceback

        traceback.print_exc(file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
