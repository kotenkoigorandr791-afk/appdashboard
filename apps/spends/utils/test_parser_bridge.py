#!/usr/bin/env python3
"""Test suite: parser_bridge.py against statements of various banks."""
import json
import os
import subprocess
import sys
import tempfile

BRIDGE = os.path.join(os.path.dirname(__file__), "parser_bridge.py")

CASES = []


def case(name, content, encoding="utf-8", expect_rows=None,
         expect_income=None, expect_expense=None, expect_total_in=None,
         expect_total_out=None, expect_merchants=None, expect_groups=None):
    CASES.append(dict(name=name, content=content, encoding=encoding,
                      expect_rows=expect_rows, expect_income=expect_income,
                      expect_expense=expect_expense,
                      expect_total_in=expect_total_in,
                      expect_total_out=expect_total_out,
                      expect_merchants=expect_merchants or [],
                      expect_groups=expect_groups))


# 1. Monobank (UA) — semicolon-free, quoted headers, em-dash placeholders
case("monobank_ua", '''"Дата i час операції","Деталі операції",MCC,"Сума в валюті картки (UAH)","Сума в валюті операції",Валюта,Курс,"Сума комісій (UAH)","Сума кешбеку (UAH)","Залишок після операції"
"16.09.2026 12:50:48",АТБ,5411,-562.39,-562.39,UAH,—,—,5.62,100325.41
"16.09.2026 12:22:07","Термінал mono",4829,2000.0,2000.0,UAH,—,—,—,100887.8
"15.09.2026 12:46:51",Фора,5411,-177.12,-177.12,UAH,—,—,1.32,98887.8
''', expect_rows=3, expect_income=1, expect_expense=2,
     expect_total_in=2000.0, expect_total_out=739.51,
     expect_merchants=["АТБ", "Термінал mono", "Фора"])

# 2. PKO BP style (PL) — semicolon delimiter, comma decimals, operator column
case("pko_bp_pl", '''Data operacji;Data księgowania;Opis operacji;Kwota;Saldo po operacji
2026-09-10;2026-09-10;BIEDRONKA 1234 WARSZAWA;-45,20;1500,00
2026-09-09;2026-09-09;PRZELEW PRZYCHODZĄCY KOWALSKI;+1200,00;1545,20
2026-09-08;2026-09-08;ORLEN PETROL STATION;-150,00;345,20
''', expect_rows=3, expect_income=1, expect_expense=2,
     expect_total_in=1200.0, expect_total_out=195.20)

# 3. German bank — comma decimal, dot thousands, Empfänger/Zahlungspflichtiger
case("german_bank", '''Buchungstag;Wertstellung;Zahlungspflichtiger;Zahlungsempfänger;Buchungstext;Verwendungszweck;Betrag;Währung;Saldo
01.09.2026;01.09.2026;;REWE SAGT DANKE;Lastschrift;Einkauf;-56,78;EUR;2000,00
02.09.2026;02.09.2026;MAX MUSTERMANN;;Gutschrift;Miete September;850,00;EUR;2056,78
03.09.2026;03.09.2026;;AMAZON EU;Lastschrift;Bestellung 123;-99,99;EUR;1956,79
''', expect_rows=3, expect_income=1, expect_expense=2,
     expect_total_in=850.0, expect_total_out=156.77)

# 4. English bank — debit/credit split columns
case("uk_debit_credit", '''Transaction Date,Transaction Type,Description,Debit Amount,Credit Amount,Balance
10/09/2026,DEB,TESCO STORES 3456,23.45,,
09/09/2026,FPI,SALARY ACME CORP,,2500.00,
08/09/2026,DEB,NETFLIX.COM,10.99,,
''', expect_rows=3, expect_income=1, expect_expense=2,
     expect_total_in=2500.0, expect_total_out=34.44)

# 5. Headerless CSV (some banks export without headers)
case("headerless", '''2026-09-10;-45.20;GROCERY STORE
2026-09-09;1200.00;INVOICE PAYMENT
2026-09-08;-12.00;COFFEE SHOP
''', expect_rows=3, expect_income=1, expect_expense=2,
     expect_total_in=1200.0, expect_total_out=57.20)

# 6. cp1250-encoded Polish statement
case("cp1250_pl", '''Data operacji;Opis operacji;Kwota
2026-09-10;ŁADUNKI I ZABEZPIECZENIA SKLEP;-19,99
2026-09-09;PRZELEW PRZYCHODZĄCY;500,00
''', encoding="cp1250", expect_rows=2, expect_income=1, expect_expense=1,
     expect_total_in=500.0, expect_total_out=19.99)

# 7. Privat24 (UA) style with separate date/time and Дебет/Кредит
case("privat24_ua", '''"Дата","Час","Деталі","Дебет","Кредит","Залишок"
"10.09.2026","12:30:00","Супермаркет Сільпо","298,50","0,00","5000,00"
"09.09.2026","09:15:00","Заробітна плата","0,00","15000,00","5298,50"
"08.09.2026","18:00:00","Аптека","150,25","0,00","12000,00"
''', expect_rows=3, expect_income=1, expect_expense=2,
     expect_total_in=15000.0, expect_total_out=448.75)


def run_bridge(content, encoding="utf-8"):
    with tempfile.NamedTemporaryFile("wb", suffix=".csv", delete=False) as f:
        f.write(content.encode(encoding))
        src = f.name
    out = src + ".json"
    try:
        proc = subprocess.run(
            [sys.executable, BRIDGE, src, out],
            capture_output=True, text=True, timeout=60,
        )
        if proc.returncode != 0:
            raise RuntimeError(f"bridge failed: {proc.stderr}")
        with open(out, encoding="utf-8") as f:
            return json.load(f)
    finally:
        for p in (src, out):
            if os.path.exists(p):
                os.unlink(p)


def approx(a, b, eps=0.02):
    return abs(a - b) <= eps


failures = []
for c in CASES:
    try:
        d = run_bridge(c["content"], c["encoding"])
        txs = d["transactions"]
        inc = [t for t in txs if t["income"]]
        exp = [t for t in txs if not t["income"]]
        tin = round(sum(t["amount"] for t in inc), 2)
        tout = round(sum(t["amount"] for t in exp), 2)

        def chk(cond, msg):
            if not cond:
                raise AssertionError(msg)

        if c["expect_rows"] is not None:
            chk(len(txs) == c["expect_rows"],
                f"rows {len(txs)} != {c['expect_rows']}")
        if c["expect_income"] is not None:
            chk(len(inc) == c["expect_income"],
                f"income {len(inc)} != {c['expect_income']}")
        if c["expect_expense"] is not None:
            chk(len(exp) == c["expect_expense"],
                f"expense {len(exp)} != {c['expect_expense']}")
        if c["expect_total_in"] is not None:
            chk(approx(tin, c["expect_total_in"]),
                f"total_in {tin} != {c['expect_total_in']}")
        if c["expect_total_out"] is not None:
            chk(approx(tout, c["expect_total_out"]),
                f"total_out {tout} != {c['expect_total_out']}")
        for m in c["expect_merchants"]:
            chk(any(t["merchant_raw"] == m for t in txs),
                f"merchant {m!r} not found")
        print(f"PASS {c['name']}: rows={len(txs)} in={tin} out={tout} "
              f"groups={len(d['groups'])} det={json.dumps(d.get('detection', {}), ensure_ascii=False)[:110]}")
    except Exception as e:
        failures.append(c["name"])
        print(f"FAIL {c['name']}: {e}")

print()
if failures:
    print("FAILED:", ", ".join(failures))
    sys.exit(1)
print(f"All {len(CASES)} cases passed.")
