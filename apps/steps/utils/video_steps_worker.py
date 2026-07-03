# -*- coding: utf-8 -*-
from __future__ import annotations
import json
import os
import re
import sys
import time
import subprocess
import shutil
from collections import Counter
from datetime import datetime, timedelta
from statistics import median
from typing import Any
np: Any = None
cv2: Any = None
pytesseract: Any = None

MIN_STEPS = 100
MAX_STEPS = 80000
MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']


def _atomic_write(path, data) -> None:
    d = path + '.tmp'
    if isinstance(data, str):
        with open(d, 'w', encoding='utf-8', newline='\n') as f:
            f.write(data)
    else:
        with open(d, 'wb') as f:
            f.write(data)
    os.replace(d, path)


def _write_state(path, obj: dict) -> None:
    _atomic_write(path, json.dumps(obj, ensure_ascii=False, indent=0))


def _write_state_sqlite(db_path: str, job_id: str, obj: dict) -> bool:
    import sqlite3
    payload = json.dumps(obj, ensure_ascii=False)
    t = int(time.time())
    err_last: str | None = None
    for attempt in range(60):
        try:
            conn = sqlite3.connect(db_path, timeout=30.0)
            try:
                conn.execute('PRAGMA busy_timeout=30000')
                conn.execute(
                    'INSERT INTO video_job_state (id, job_id, state_json, updated_at) VALUES (1, ?, ?, ?) '
                    'ON CONFLICT(id) DO UPDATE SET job_id=excluded.job_id, state_json=excluded.state_json, updated_at=excluded.updated_at',
                    (job_id, payload, t),
                )
                conn.commit()
                return True
            finally:
                conn.close()
        except sqlite3.Error as err:
            err_last = str(err)
            if isinstance(err, sqlite3.OperationalError):
                time.sleep(0.03 * min(attempt + 1, 25))
            else:
                break
    print(f'[video_steps] sqlite write failed ({err_last}) db={db_path}', file=sys.stderr, flush=True)
    return False


def _write_pending_video_confirm(cfg: dict, job_id: str, state_done: dict) -> None:
    sqlite_p = cfg.get('user_sqlite_path')
    urd = cfg.get('user_results_dir')
    user_dir = None
    if isinstance(sqlite_p, str) and sqlite_p.strip():
        user_dir = os.path.dirname(os.path.normpath(sqlite_p.strip()))
    elif isinstance(urd, str) and urd.strip():
        user_dir = os.path.normpath(os.path.join(urd.strip(), '..'))
    if not user_dir or not os.path.isdir(user_dir):
        return
    path = os.path.join(user_dir, 'pending_video_confirm.json')
    res = state_done.get('results')
    prv = state_done.get('ocr_preview')
    payload = {
        'job_id': job_id,
        'output_basename': state_done.get('output_basename') or '',
        'results': res if isinstance(res, list) else [],
        'ocr_preview': prv if isinstance(prv, list) else [],
    }
    try:
        _atomic_write(path, json.dumps(payload, ensure_ascii=False, indent=0))
    except OSError:
        pass


def _write_state_job(job_dir: str, obj: dict, cfg: dict) -> None:
    jbid = os.path.basename(os.path.normpath(job_dir))
    o = dict(obj)
    o.setdefault('job_id', jbid)
    jp = os.path.join(job_dir, 'state.json')
    p = cfg.get('user_sqlite_path')
    if isinstance(p, str) and p.strip():
        pn = os.path.normpath(p.strip())
        par = os.path.dirname(pn)
        if par and os.path.isdir(par):
            if _write_state_sqlite(pn, jbid, o):
                return
        else:
            print(f'[video_steps] sqlite parent missing, skip sqlite: {pn}', file=sys.stderr, flush=True)
    try:
        _write_state(jp, o)
    except OSError as err:
        print(f'[video_steps] state.json write failed ({err}) path={jp}', file=sys.stderr, flush=True)


def _read_json(path) -> dict:
    with open(path, 'r', encoding='utf-8') as f:
        return json.load(f)
def _is_cancelled(job_dir: str) -> bool:
    return os.path.isfile(os.path.join(job_dir, 'cancel.flag'))
def _cleanup_job_dir(job_dir: str) -> None:
    try:
        shutil.rmtree(job_dir)
    except OSError:
        pass


def clean_ocr(s: str) -> str:
    s = re.sub(r'\s+', ' ', s)
    s = s.strip()
    s = re.sub(r'\.{3,}', ' ', s)
    cy = '\u0410-\u044f\u0401\u0451\u0490\u0491\u0406\u0456\u0407\u0457'
    s = re.sub(rf'([a-zA-Z{cy}])(\d)', r'\1 \2', s)
    s = re.sub(rf'(\d)([a-zA-Z{cy}])', r'\1 \2', s)
    return s


def _parse_steps_digits(raw: str) -> int | None:
    n = re.sub(
        r'[^0-9]',
        '',
        raw.replace('O', '0').replace('o', '0').replace('l', '1').replace('I', '1').replace('|', '1'),
    )
    if not n.isdigit():
        return None
    v = int(n)
    if MIN_STEPS <= v <= MAX_STEPS:
        return v
    return None


def _looks_like_split_four_digit_salad(raw: str) -> bool:
    s = raw.strip()
    if s.count(' ') < 3:
        return False
    if not re.match(r'^[\d\sOolIi|,.]+$', s):
        return False
    return bool(re.search(r'\d\s+\d\s+\d\s+\d', s))


def find_steps_data(text: str) -> int | None:
    t = clean_ocr(text)

    def _bare_gather() -> list[int]:
        out: list[int] = []
        for xm in re.finditer(r'\b([1-9]\d{3,6})\b', t):
            v = int(xm.group(1))
            if 1750 <= v <= 2099:
                continue
            if MIN_STEPS <= v <= MAX_STEPS:
                out.append(v)
        return out

    m_fit = re.search(r'(?:Арг|Arg|арг)\s+\d{4}\s+([\d\sOolIi|,\.]{5,17})', t, re.IGNORECASE | re.UNICODE)
    if not m_fit:
        m_fit = re.search(r'(?:Арг|Arg|арг)\s+\d{1,4}\s+([\d\sOolIi|,\.]{5,17})', t, re.IGNORECASE | re.UNICODE)
    if m_fit:
        pv = _parse_steps_digits(m_fit.group(1))
        if pv is not None:
            return pv

    bd = _bare_gather()
    if bd:
        hi_g = [v for v in bd if v >= 7500]
        if hi_g:
            return Counter(hi_g).most_common(1)[0][0]

    ym_pat = (
        r'\b(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+'
        r'(20\d{2})\s+([\dOolIi|,\.\s]{5,22})'
    )
    m_mo = re.search(ym_pat, t, re.IGNORECASE | re.UNICODE)
    if m_mo:
        pv = _parse_steps_digits(m_mo.group(2))
        if pv is not None:
            return pv
    for ym in re.finditer(r'\b20\d{2}\s+([\dOolIi|,\.\s]{5,17})', t):
        pv = _parse_steps_digits(ym.group(1))
        if pv is not None:
            return pv



    stw = '(?:steps?|шага?|шагов|Шаг(?:а|ов)?|кроків?|[Кк]рок)'
    nump = r'([\d\sOolIi|,\.]{2,17})'
    for pat in (
        rf'[Tt]otal\s+{nump}\s*(?:{stw})?\b',
        rf'[Tt]otal\s+{nump}\b',
        rf'(?:Всього|Усього|Всего|Разом)\s+{nump}(?:\s+{stw})?\b',
        r'[Tt]ot[aA]1\s*([\d\sOolIi|,\.]{2,17})',
        rf'[Tt]ot[aA]l?\s+{nump}\b',
    ):
        m = re.search(pat, t, re.IGNORECASE | re.UNICODE)
        if m:
            pv = _parse_steps_digits(m.group(1))
            if pv is not None:
                return pv

    for m in re.finditer(rf'{nump}\s+{stw}\b', t, re.IGNORECASE | re.UNICODE):
        grp = m.group(1).strip()
        if _looks_like_split_four_digit_salad(grp):
            continue
        pv = _parse_steps_digits(m.group(1))
        if pv is not None:
            return pv

    if bd:
        md_g = [v for v in bd if MIN_STEPS <= v < 7500]
        return Counter(md_g if md_g else bd).most_common(1)[0][0]
    return None


def _norm_mon(t: str) -> str:
    t = t[:1].upper() + t[1:3].lower() if len(t) >= 3 else t.capitalize()
    for m in MONTHS:
        if t.lower().startswith(m.lower()[:3]):
            return m
    return t


_MON_EN_REGEX = (
    '(?:January|February|March|April|June|July|August|September|October|November|December|'
    'Jan\\.?|Feb\\.?|Mar\\.?|Apr\\.?|May\\.?|Jun\\.?|Jul\\.?|Aug\\.?|Sep\\.?|Sept\\.?|Oct\\.?|Nov\\.?|Dec\\.?)'
)


def extract_chart_pairs_from_ocr(raw_text: str, year: int) -> list[tuple[str, int]]:
    t = clean_ocr(raw_text)
    tmp: dict[str, tuple[int, int]] = {}

    def _put(canonical: str, steps: int, pri: int) -> None:
        if steps < MIN_STEPS or steps > MAX_STEPS:
            return
        old = tmp.get(canonical)
        if old is None or pri < old[1]:
            tmp[canonical] = (steps, pri)

    pat_tf = re.compile(
        rf'(?:[Tt]otal|[Tt]ot\s*[Aa]?\s*[Ll]?)\s+([\d\sOolIi|,\.]{{4,22}})\s+({_MON_EN_REGEX})\s+(\d{{1,2}})(?!\d)',
        re.I,
    )
    for xm in pat_tf.finditer(t):
        pv = _parse_steps_digits(xm.group(1))
        mon = _norm_mon(xm.group(2))
        dd = int(xm.group(3))
        if pv is None or mon not in MONTHS or dd < 1 or dd > 31:
            continue
        _put(f'{mon} {dd}, {year}', pv, pri=2)

    pat_dm = re.compile(
        rf'\b({_MON_EN_REGEX})\s+(\d{{1,2}})(?!\d)\s*[,.\-:]*\s*([\d\sOolIi|,\.]{{4,22}})',
        re.I,
    )
    for xm in pat_dm.finditer(t):
        mon = _norm_mon(xm.group(1))
        dd = int(xm.group(2))
        pv = _parse_steps_digits(xm.group(3))
        if pv is None or mon not in MONTHS or dd < 1 or dd > 31:
            continue
        _put(f'{mon} {dd}, {year}', pv, pri=4)
    pat_merged = re.compile(
        rf'(?:[Ss]teps\s+)?[Tt]otal\s+([\d\sOolIi|,\.]{{4,22}})\s+({_MON_EN_REGEX})\s+([1-9])(?=\d{{4}}\b)\d{{4}}\b',
        re.I,
    )
    for xm in pat_merged.finditer(t):
        pv = _parse_steps_digits(xm.group(1))
        mon = _norm_mon(xm.group(2))
        dd = int(xm.group(3))
        if pv is None or mon not in MONTHS or dd < 1 or dd > 31:
            continue
        _put(f'{mon} {dd}, {year}', pv, pri=1)

    if not tmp:
        for xm in re.finditer(
            r'([\d\sOolIi|,\.]{4,22})\s+([а-яёіїА-ЯЁІЇґҐ]{3,})\s+(\d{1,2})\b',
            t,
            re.UNICODE,
        ):
            pv = _parse_steps_digits(xm.group(1))
            mon = _cy_month_token(xm.group(2))
            dd = int(xm.group(3))
            if pv is None or not mon or mon not in MONTHS or dd < 1 or dd > 31:
                continue
            _put(f'{mon} {dd}, {year}', pv, pri=6)
        for xm in re.finditer(
            r'\b([а-яёіїА-ЯЁІЇґҐ]{3,})\s+(\d{1,2})\b\s*[,.\-:]*\s*([\d\sOolIi|,\.]{4,22})',
            t,
            re.UNICODE,
        ):
            pv = _parse_steps_digits(xm.group(3))
            mon = _cy_month_token(xm.group(1))
            dd = int(xm.group(2))
            if pv is None or not mon or mon not in MONTHS or dd < 1 or dd > 31:
                continue
            _put(f'{mon} {dd}, {year}', pv, pri=8)

    return [(dk, tup[0]) for dk, tup in sorted(tmp.items(), key=lambda kv: _parse_calendar_date(kv[0]) or datetime(2000, 1, 1))]


def _first_header_total_steps(text: str) -> int | None:
    s = clean_ocr(text)
    m = re.search(r'(?:[Ss]teps\s+)?[Tt]otal\s+([\d\sOolIi|,\.]{4,22})\b', s, re.I)
    if m:
        v = _parse_steps_digits(m.group(1))
        if v is not None:
            return v
    m2 = re.search(r'\b[Ss]teps\s+(\d{4,6})(?!\s*/)\b', s)
    if m2:
        return _parse_steps_digits(m2.group(1))
    return None


def _pick_display_pair(mega: str, pairs: list[tuple[str, int]]) -> tuple[str, int] | None:
    if not pairs:
        return None
    tot = _first_header_total_steps(mega)
    if tot is None:
        tot = find_steps_data(mega)
    if tot is not None:
        for dk, sv in pairs:
            if sv == tot:
                return (dk, sv)
        tol = max(45, int(tot * 0.018))
        best: tuple[str, int] | None = None
        bestd = 10**9
        for dk, sv in pairs:
            d = abs(sv - tot)
            if d <= tol and d < bestd:
                bestd = d
                best = (dk, sv)
        if best is not None:
            return best
    return pairs[-1]


def _parse_calendar_date(d: str) -> datetime | None:
    if not d or not isinstance(d, str):
        return None
    t = d.strip().rstrip('.').strip()
    if not t:
        return None
    for fmt in ('%b %d, %Y', '%d %b, %Y', '%b %d %Y', '%B %d, %Y', '%d %B, %Y', '%Y-%m-%d'):
        try:
            return datetime.strptime(t[:96], fmt)
        except ValueError:
            pass
    m = re.match(r'^([A-Za-z]{3,9})\s+(\d{1,2})\s*,\s*(\d{4})', t)
    if m:
        mon = _norm_mon(m.group(1))
        dd, yy = int(m.group(2)), int(m.group(3))
        if mon in MONTHS and 1 <= dd <= 31:
            try:
                return datetime.strptime(f'{mon} {dd}, {yy}', '%b %d, %Y')
            except ValueError:
                pass
    m2 = re.match(r'^(\d{4})-(\d{1,2})-(\d{1,2})', t.replace(' ', ''))
    if m2:
        try:
            yy, mo, da = int(m2.group(1)), int(m2.group(2)), int(m2.group(3))
            return datetime(yy, mo, da)
        except ValueError:
            pass
    return None


_CY_MONTH = sorted(
    [
        ('вересня', 'Sep'),
        ('жовтня', 'Oct'),
        ('листопада', 'Nov'),
        ('грудня', 'Dec'),
        ('січня', 'Jan'),
        ('лютого', 'Feb'),
        ('березня', 'Mar'),
        ('квітня', 'Apr'),
        ('травня', 'May'),
        ('червня', 'Jun'),
        ('липня', 'Jul'),
        ('серпня', 'Aug'),
        ('сентября', 'Sep'),
        ('ноября', 'Nov'),
        ('февраля', 'Feb'),
        ('января', 'Jan'),
        ('апреля', 'Apr'),
        ('июня', 'Jun'),
        ('июля', 'Jul'),
        ('августа', 'Aug'),
        ('октября', 'Oct'),
        ('декабря', 'Dec'),
        ('марта', 'Mar'),
        ('мая', 'May'),
        ('січ', 'Jan'),
        ('лют', 'Feb'),
        ('бер', 'Mar'),
        ('кві', 'Apr'),
        ('тра', 'May'),
        ('лип', 'Jul'),
        ('вер', 'Sep'),
        ('жов', 'Oct'),
        ('лис', 'Nov'),
        ('гру', 'Dec'),
        ('янв', 'Jan'),
        ('фев', 'Feb'),
        ('мар', 'Mar'),
        ('апр', 'Apr'),
        ('май', 'May'),
        ('мая', 'May'),
        ('июн', 'Jun'),
        ('июл', 'Jul'),
        ('авг', 'Aug'),
        ('сен', 'Sep'),
        ('окт', 'Oct'),
        ('ноя', 'Nov'),
        ('дек', 'Dec'),
    ],
    key=lambda x: len(x[0]),
    reverse=True,
)


def _cy_month_token(tok: str) -> str | None:
    low = ''.join(c for c in tok.lower().strip('.') if c.isalpha() or ord(c) > 127)
    if len(low) < 2:
        return None
    for pref, mon in _CY_MONTH:
        if low.startswith(pref):
            return mon
        if pref.startswith(low) and len(low) >= 3:
            return mon
    return None


def find_date_data(text: str, year: int) -> str | None:
    t = clean_ocr(text)
    m = re.search(r'\b(\d{4})-(\d{1,2})-(\d{1,2})\b', t)
    if m:
        try:
            y, mo, da = int(m.group(1)), int(m.group(2)), int(m.group(3))
            if 1 <= mo <= 12 and 1 <= da <= 31 and 1990 <= y <= 2099:
                return f'{MONTHS[mo - 1]} {da}, {y}'
        except (ValueError, IndexError):
            pass
    cy_px = (
        r'(\d{1,2})\s*[,.]\s*([а-яёіїА-ЯЁІЇґҐ]{3,17})',
        r'([а-яёіїА-ЯЁІЇґҐ]{3,17})\s*[,.]\s*(\d{1,2})',
        r'(\d{1,2})\s+([а-яёіїА-ЯЁІЇґҐ]{3,17})',
        r'([а-яёіїА-ЯЁІЇґҐ]{3,17})\s+(\d{1,2})\b',
    )
    for idx, px in enumerate(cy_px):
        for gm in re.finditer(px, t):
            a1, b1 = gm.group(1), gm.group(2)
            if idx % 2 == 0:
                dd, mx = int(a1), _cy_month_token(b1)
            else:
                mx, dd = _cy_month_token(a1), int(b1)
            if mx and mx in MONTHS and 1 <= dd <= 31:
                return f'{mx} {dd}, {year}'
    m_my = re.search(rf'\b({_MON_EN_REGEX})\s+(20\d{{2}})\b', t, re.I)
    if m_my:
        mon = _norm_mon(m_my.group(1))
        if mon in MONTHS:
            mo_ix = MONTHS.index(mon) + 1
            yy_cap = int(m_my.group(2))
            yy_u = year if abs(yy_cap - year) <= 1 else yy_cap
            mos = str(mo_ix) if mo_ix >= 10 else rf'0?{mo_ix}'
            sm0 = re.search(rf'\b(?:{mos})\s*/\s*(\d{{1,2}})(?!\d)\b', t)
            if sm0:
                dd0 = int(sm0.group(1))
                if 1 <= dd0 <= 31:
                    return f'{mon} {dd0}, {yy_u}'
    pats = [
        r'([A-Za-z]{3,})\s*[,]?\s*(\d{1,2})(?!\d)',
        r'(\d{1,2})(?!\d)\s*[,]?\s*([A-Za-z]{3,})',
    ]
    for pat in pats:
        for fm in re.finditer(pat, t, re.IGNORECASE):
            a, b = fm.group(1), fm.group(2)
            if a and a[0].isalpha():
                mon = _norm_mon(a)
                day = int(b) if b.isdigit() else 0
            else:
                day = int(a) if a.isdigit() else 0
                mon = _norm_mon(b)
            if mon in MONTHS and 1 <= day <= 31:
                return f'{mon} {day}, {year}'
    m = re.search(r'(\d{1,2})[./\-](\d{1,2})[./\-](\d{2,4})', t)
    if m:
        try:
            da, mo = int(m.group(1)), int(m.group(2))
            ys = m.group(3)
            y = int('20' + ys) if len(ys) == 2 else int(ys)
            if mo > 12 and da <= 12:
                da, mo = mo, da
            if 1 <= mo <= 12 and 1 <= da <= 31 and 1990 <= y <= 2099:
                return f'{MONTHS[mo - 1]} {da}, {y}'
        except (ValueError, IndexError):
            pass
    slash_hits: list[tuple[int, int]] = []
    for sm in re.finditer(r'\b(\d{1,2})\s*/\s*(\d{1,2})\b', t):
        a, b = int(sm.group(1)), int(sm.group(2))
        if a <= 12 and b <= 31:
            mo, dd = a, b
        elif b <= 12 and a <= 31:
            mo, dd = b, a
        else:
            continue
        if 1 <= mo <= 12 and 1 <= dd <= 31:
            slash_hits.append((mo, dd))
    if slash_hits:
        mo_mode = Counter(m for m, _ in slash_hits).most_common(1)[0][0]
        days_same = sorted(d for m, d in slash_hits if m == mo_mode)
        if days_same:
            sdd = days_same[len(days_same) // 2]
            return f'{MONTHS[mo_mode - 1]} {sdd}, {year}'
    return None


def ocr_image(tessdata: str, lang: str, im: Any) -> str:
    lng = (lang or 'eng').strip()
    low = lng.lower()
    wl = ',' not in lng and '+' not in lng and '_' not in lng and low in {'eng', 'en', '', 'english'}
    base = f'--tessdata-dir {tessdata} --oem 1 '
    wt = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ/,-.'
    def px(ps: int) -> str:
        return base + (f'--psm {ps} -c tessedit_char_whitelist={wt}' if wl else f'--psm {ps}')
    t1 = pytesseract.image_to_string(im, lang=lng, config=px(6)) or ''
    t2 = pytesseract.image_to_string(im, lang=lng, config=px(4)) or ''
    t3 = pytesseract.image_to_string(im, lang=lng, config=px(11)) or ''
    t4 = pytesseract.image_to_string(im, lang=lng, config=base + '--psm 3') or ''
    return t1 + ' ' + t2 + ' ' + t3 + ' ' + t4


def _scale_bgr_max_side(bgr: Any, cv2_mod: Any, max_side: int = 760) -> Any:
    h, w = bgr.shape[:2]
    m = max(h, w)
    if m <= max_side:
        return bgr
    k = float(max_side) / float(m)
    return cv2_mod.resize(bgr, (max(16, int(w * k)), max(16, int(h * k))), interpolation=cv2_mod.INTER_AREA)


def _fast_gray_pair(bgr_s: Any, cv2_mod: Any, np_mod: Any) -> list[Any]:
    gray = cv2_mod.cvtColor(bgr_s, cv2_mod.COLOR_BGR2GRAY)
    clahe = cv2_mod.createCLAHE(clipLimit=2.2, tileGridSize=(10, 10))
    g1 = np_mod.asarray(clahe.apply(gray))
    bk = max(31, min(g1.shape[0], g1.shape[1]) // 42 | 1)
    at = cv2_mod.adaptiveThreshold(
        g1, 255, cv2_mod.ADAPTIVE_THRESH_GAUSSIAN_C, cv2_mod.THRESH_BINARY, bk, 4,
    )
    return [g1, np_mod.asarray(at)]


def _tess_gray_fast(
    im_gray: Any, tessdata: str, lang: str, pytesseract_mod: Any, np_mod: Any, cv2_mod: Any,
) -> str:
    if im_gray is None or getattr(im_gray, 'size', 0) < 320:
        return ''
    lng = (lang or 'eng').strip()
    wlset = lng.lower().split('+')[0] in {'eng', 'en', ''}
    wt = '-c tessedit_char_whitelist=0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ,/,-.: '
    g = np_mod.asarray(im_gray).copy()
    mh, mw = g.shape[:2]
    ms = max(mh, mw, 1)
    if ms > 860:
        k = 760.0 / ms
        g = cv2_mod.resize(g, (max(24, int(mw * k)), max(24, int(mh * k))), interpolation=cv2_mod.INTER_AREA)
        mh, mw = g.shape[:2]
        ms = max(mh, mw, 1)
    elif ms < 420:
        k = 560.0 / ms
        g = cv2_mod.resize(g, (max(48, int(mw * k)), max(48, int(mh * k))), interpolation=cv2_mod.INTER_CUBIC)
    parts: list[str] = []
    base = '--tessdata-dir {} --oem 3'.format(tessdata)
    for psm in (11, 6):
        cf = base + (' --psm ' + str(psm))
        cf = cf + (' ' + wt if wlset else '')
        try:
            parts.append(pytesseract_mod.image_to_string(g, lang=lng, config=cf) or '')
        except Exception:
            pass
    return ' '.join(parts)


def _frame_mega_and_pairs(
    bgr, year: int, ocr_lang: str, tessdata: str, np, cv2, pytesseract,
) -> tuple[str, list[tuple[str, int]]]:
    b = _scale_bgr_max_side(bgr, cv2, 760)
    parts = []
    for g_im in _fast_gray_pair(b, cv2, np):
        s = _tess_gray_fast(g_im, tessdata, ocr_lang, pytesseract, np, cv2)
        if s.strip():
            parts.append(s)
    mega = clean_ocr(' '.join(parts))
    pairs = extract_chart_pairs_from_ocr(mega, year)
    return mega, pairs


def _ffprobe_duration(ffmpeg_bin: str, video: str) -> float:
    d = os.path.dirname(ffmpeg_bin)
    probe = 'ffprobe'
    if os.path.isdir(d):
        for n in ('ffprobe', 'ffprobe.exe'):
            p = os.path.join(d, n)
            if os.path.isfile(p):
                probe = p
                break
    if probe == 'ffprobe':
        w = shutil.which('ffprobe')
        if w:
            probe = w
    try:
        r = subprocess.run(
            [probe, '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', video],
            capture_output=True, text=True, timeout=60,
        )
        if r.returncode == 0 and r.stdout.strip():
            return float(r.stdout.strip())
    except (ValueError, OSError, subprocess.TimeoutExpired):
        pass
    return 1e9


def _ffprobe_video_size(ffmpeg_bin: str, video: str) -> tuple[int, int] | None:
    d = os.path.dirname(ffmpeg_bin)
    probe = 'ffprobe'
    if os.path.isdir(d):
        for n in ('ffprobe', 'ffprobe.exe'):
            p = os.path.join(d, n)
            if os.path.isfile(p):
                probe = p
                break
    if probe == 'ffprobe':
        wq = shutil.which('ffprobe')
        if wq:
            probe = wq
    try:
        r = subprocess.run(
            [probe, '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height', '-of', 'csv=p=0:s=x', video],
            capture_output=True, text=True, timeout=60,
        )
        if r.returncode == 0 and r.stdout.strip():
            parts = [p.strip() for p in r.stdout.strip().replace('x', ',').split(',') if p.strip()]
            if len(parts) >= 2:
                ww, hh = int(parts[0]), int(parts[1])
                if ww > 0 and hh > 0:
                    return ww, hh
    except (ValueError, OSError, subprocess.TimeoutExpired, IndexError):
        pass
    return None


def _clamp_crop(crop: dict | None, in_w: int, in_h: int) -> dict | None:
    if not crop or int(crop.get('width', 0) or 0) <= 0 or int(crop.get('height', 0) or 0) <= 0:
        return crop
    w, h = int(crop['width']), int(crop['height'])
    x, y = int(crop['x']), int(crop['y'])
    x = max(0, min(x, max(0, in_w - 1)))
    y = max(0, min(y, max(0, in_h - 1)))
    w = max(2, min(w, in_w - x))
    h = max(2, min(h, in_h - y))
    if w < 4 or h < 4:
        return None
    return {'x': x, 'y': y, 'width': w, 'height': h}


def _step_only_plateau(bare: list[tuple[int, float]], jump: int = 420, gap_s: float = 22.0) -> list[list[tuple[int, float]]]:
    if not bare:
        return []
    sr = sorted(bare, key=lambda z: float(z[1]))
    buckets: list[list[tuple[int, float]]] = []
    cur: list[tuple[int, float]] = [sr[0]]
    for s, tv in sr[1:]:
        lm = median([float(v[0]) for v in cur])
        prv_t = cur[-1][1]
        if abs(float(int(s)) - lm) >= float(jump) or (tv - prv_t) > gap_s:
            buckets.append(cur)
            cur = [(s, tv)]
        else:
            cur.append((s, tv))
    if cur:
        buckets.append(cur)
    return buckets


def _extras_from_steps_only(year: int, clusters: list[list[tuple[int, float]]], reserved_dmy: set[str]) -> list[dict]:
    if not clusters:
        return []
    out: list[dict] = []
    base_dt = datetime(int(year), 1, 1)
    seen_dmy = set(reserved_dmy)
    for i, grp in enumerate(clusters):
        ctr = Counter(int(x[0]) for x in grp)
        chosen = ctr.most_common(1)[0][0]
        t_sec = round(float(median([float(z[1]) for z in grp])), 3)
        off = min(i, 366)
        fd = base_dt + timedelta(days=off)
        dk = f'{MONTHS[fd.month - 1]} {fd.day}, {fd.year}'
        dmy_raw = fd.strftime('%d-%m-%y')
        dmy = dmy_raw
        k = 0
        while dmy in seen_dmy:
            k += 1
            fd2 = base_dt + timedelta(days=min(off + k * 7, 400))
            dmy = fd2.strftime('%d-%m-%y')
            dk = f'{MONTHS[fd2.month - 1]} {fd2.day}, {fd2.year}'
        seen_dmy.add(dmy)
        out.append({
            'date': dk, 'dmy': dmy, 'steps': chosen, 't_sec': t_sec, 't_display': _fmt_ts(t_sec),
            'frame': '', 'raw_text': '(chart steps only)',
        })
    def _sk(item: dict):
        pd = _parse_calendar_date(item.get('date') or '')
        return pd or datetime(2000, 1, 1)
    out.sort(key=_sk)
    return out


def extract_frames(ffmpeg: str, video: str, out_pat: str, crop: dict | None, fps: float, t_start: float | None = None, t_end: float | None = None, mild_eq: bool = False) -> bool:
    vf = f'fps={fps}'
    if crop and int(crop.get('width', 0) or 0) > 0 and int(crop.get('height', 0) or 0) > 0:
        w, h, x, y = int(crop['width']), int(crop['height']), int(crop['x']), int(crop['y'])
        vf = f"crop={w}:{h}:{x}:{y},{vf}"
    if mild_eq:
        vf += ',scale=iw:ih:flags=lanczos,eq=contrast=1.07:brightness=-0.03'
    else:
        vf += ',scale=iw:ih:flags=lanczos,eq=contrast=1.17:brightness=-0.06'
    cmd = [ffmpeg, '-y', '-nostdin', '-hide_banner', '-loglevel', 'error', '-i', video]
    if t_start is not None and t_start > 1e-3:
        cmd.extend(['-ss', str(t_start)])
    if t_end is not None and t_start is not None and t_end > t_start + 0.01:
        cmd.extend(['-t', str(t_end - t_start)])
    cmd.extend(['-vsync', 'vfr', '-vf', vf, '-qscale:v', '1', out_pat])
    r = subprocess.run(cmd, capture_output=True, text=True)
    return r.returncode == 0


def _fmt_ts(t: float) -> str:
    if t < 0:
        t = 0.0
    s = int(t)
    sub = t - s
    ms = f"{int(sub * 1000):03d}" if sub else '000'
    m, se = divmod(s, 60)
    h, m = divmod(m, 60)
    if h:
        return f"{h:d}:{m:02d}:{se:02d}.{ms}"
    return f"{m:d}:{se:02d}.{ms}"


def _merge_time_intervals(pairs: list[tuple[float, float]]) -> list[tuple[float, float]]:
    if not pairs:
        return []
    s = sorted(pairs, key=lambda x: (x[0], x[1]))
    o = [list(s[0])]
    for a, b in s[1:]:
        if a <= o[-1][1] + 0.02:
            o[-1][1] = max(o[-1][1], b)
        else:
            o.append([a, b])
    return [(float(a), float(b)) for a, b in o]


def _ocr_vote_from_bgr(
    bgr, year: int, ocr_lang: str, tessdata: str, np, cv2, pytesseract,
) -> tuple[int | None, str | None, str]:
    mega, pairs = _frame_mega_and_pairs(bgr, year, ocr_lang, tessdata, np, cv2, pytesseract)
    st_ret, dt_ret = None, None
    if pairs:
        pick = _pick_display_pair(mega, pairs)
        dk, sv = pick if pick else pairs[-1]
        st_ret, dt_ret = sv, dk
    if st_ret is None:
        st_ret = find_steps_data(mega)
    if dt_ret is None:
        dt_ret = find_date_data(mega, year)
    return st_ret, dt_ret, mega


def _ocr_one_file(
    path: str, fn: str, t_sec: float, year: int, ocr_lang: str, tessdata: str,
    date_steps: dict[str, list[int]], date_pairs: dict[str, list[tuple[int, float]]], first_t: dict[str, float], all_text: dict,
    carry_date: list,
    step_only_reads: list,
    np, cv2, pytesseract,
) -> None:
    bgr = cv2.imread(path, cv2.IMREAD_COLOR)
    if bgr is None:
        return
    mega, pairs = _frame_mega_and_pairs(bgr, year, ocr_lang, tessdata, np, cv2, pytesseract)
    st1 = dt1 = None
    if pairs:
        pick = _pick_display_pair(mega, pairs)
        dk, sv = pick if pick else pairs[-1]
        carry_date[0] = dk
        date_steps.setdefault(dk, []).append(sv)
        date_pairs.setdefault(dk, []).append((sv, t_sec))
        first_t[dk] = min(first_t.get(dk, 1e18), t_sec)
        dt1, st1 = dk, sv
    else:
        st1 = find_steps_data(mega)
        dt1 = find_date_data(mega, year)
        if dt1:
            carry_date[0] = dt1
        eff_dt = dt1 or carry_date[0]
        if st1 and eff_dt:
            date_steps.setdefault(eff_dt, []).append(st1)
            date_pairs.setdefault(eff_dt, []).append((st1, t_sec))
            first_t[eff_dt] = min(first_t.get(eff_dt, 1e18), t_sec)
        elif st1:
            step_only_reads.append((st1, float(t_sec)))
    disp_dt = dt1 if dt1 else carry_date[0]
    all_text[fn] = {'raw': mega, 'steps': st1, 'date': disp_dt or '', 't_sec': t_sec}


def _build_ocr_preview(all_text: dict, limit: int = 36, raw_max: int = 380) -> list:
    pairs = sorted(all_text.items(), key=lambda kv: float(kv[1].get('t_sec', 0)))
    n = len(pairs)
    if n == 0:
        return []
    if n <= limit:
        idxs = list(range(n))
    else:
        idxs_set = set()
        for i in range(limit):
            idxs_set.add(min(n - 1, int(round(i * (n - 1) / max(limit - 1, 1)))))
        idxs = sorted(idxs_set)
    out = []
    for j in idxs:
        fk, v = pairs[j]
        ts = float(v.get('t_sec', 0))
        rw = str(v.get('raw') or '')[:raw_max]
        out.append({
            'frame': fk,
            't_sec': round(ts, 3),
            't_display': _fmt_ts(ts),
            'steps': v.get('steps'),
            'date': v.get('date') or '',
            'raw': rw,
        })
    return out


def _build_merged_list(date_steps: dict, date_pairs: dict, all_text: dict) -> list[dict]:
    merged = []
    for d, sl in date_steps.items():
        if not sl:
            continue
        c = max(set(sl), key=sl.count)
        pts = date_pairs.get(d, [])
        t_cand = [t for s, t in pts if s == c]
        t_sec = (sum(t_cand) / len(t_cand)) if t_cand else (min((t for _, t in pts), default=0.0) if pts else 0.0)
        p_dt = _parse_calendar_date(d)
        dmy = p_dt.strftime('%d-%m-%y') if p_dt else None
        merged.append({
            'date': d, 'dmy': dmy, 'steps': c, 't_sec': round(float(t_sec), 3), 't_display': _fmt_ts(t_sec), 'frame': '', 'raw_text': '',
        })
    for m in merged:
        for k, v in all_text.items():
            if v.get('date') == m['date'] and v.get('steps') == m['steps']:
                m['frame'] = k
                m['raw_text'] = (v.get('raw') or '')[:200]
                break

    def _sk(item: dict):
        pd = _parse_calendar_date(item.get('date') or '')
        return pd or datetime(2000, 1, 1)
    merged.sort(key=_sk)
    return merged


def _gaps_to_refine(merged: list[dict], first_t: dict) -> list[tuple[float, float]]:
    have = set()
    for m in merged:
        pd = _parse_calendar_date(m.get('date') or '')
        if pd:
            have.add(pd.date())
    order = sorted([m for m in merged if m.get('date')], key=lambda m: first_t.get(m['date'], 0.0))
    raw: list[tuple[float, float]] = []
    for i in range(len(order) - 1):
        a, b = order[i], order[i + 1]
        da = _parse_calendar_date(a.get('date') or '')
        db = _parse_calendar_date(b.get('date') or '')
        if not da or not db:
            continue
        if da >= db:
            continue
        n = (db - da).days
        if n <= 1:
            continue
        miss = False
        for k in range(1, n):
            mid = (da + timedelta(days=k)).date()
            if mid not in have:
                miss = True
                break
        if not miss:
            continue
        ta = first_t.get(a['date'], 0.0)
        tb = first_t.get(b['date'], 0.0)
        if tb <= ta + 0.02:
            continue
        raw.append((ta, tb))
    return _merge_time_intervals(raw)


def run_job(job_dir: str) -> None:
    global np, cv2, pytesseract
    job_dir = os.path.abspath(job_dir)
    cfg_path = os.path.join(job_dir, 'job_config.json')
    try:
        cfg0 = _read_json(cfg_path)
    except Exception:
        cfg0 = {}
    try:
        import numpy as _np
        import cv2 as _cv2
        import pytesseract as _pt
        np, cv2, pytesseract = _np, _cv2, _pt
    except ImportError as e:
        _write_state_job(job_dir, {
            'status': 'error', 'message': f'import: {e!s}', 'progress': 0, 'total': 0, 'results': None,
        }, cfg0)
        return
    cfg = _read_json(cfg_path)
    jbid = os.path.basename(os.path.normpath(job_dir))
    if _is_cancelled(job_dir):
        _write_state_job(job_dir, {'status': 'cancelled', 'message': 'cancelled', 'progress': 0, 'processed': 0, 'total': 0, 'results': None, 'job_id': jbid}, cfg)
        _cleanup_job_dir(job_dir)
        return
    _write_state_job(job_dir, {
        'status': 'running', 'progress': 0.1, 'message': 'python', 'processed': 0, 'total': 0, 'job_id': jbid,
    }, cfg)
    pytesseract.pytesseract.tesseract_cmd = cfg['tesseract_exe']
    ocr_lang = cfg.get('ocr_lang', 'eng')
    year = int(cfg.get('current_year', 2026))
    crop = cfg.get('crop')
    crop = crop if isinstance(crop, dict) else {}
    if crop and not int(crop.get('width', 0) or 0):
        crop = None
    fps = float(cfg.get('fps', 4.0))
    fps_refine = float(cfg.get('fps_refine', 5.0))
    ffmpeg = cfg['ffmpeg']
    video = os.path.join(job_dir, 'input.mp4')
    if not os.path.isfile(video):
        for ext in ['.webm', '.mkv', '.mov', '.m4v']:
            alt = os.path.join(job_dir, 'input' + ext)
            if os.path.isfile(alt):
                video = alt
                break
    sz_probe = _ffprobe_video_size(ffmpeg, video)
    if crop and sz_probe:
        c0 = crop
        crop = _clamp_crop(crop, sz_probe[0], sz_probe[1])
        if crop is None:
            print(f'[video_steps] crop out of bounds on {sz_probe[0]}x{sz_probe[1]}, ignoring crop', file=sys.stderr, flush=True)
        elif c0 != crop:
            print(f'[video_steps] crop clamped from {c0} to {crop}', file=sys.stderr, flush=True)
    mild_eq = bool(crop and min(int(crop['width']), int(crop['height'])) < 1000)
    vid_dur = _ffprobe_duration(ffmpeg, video)
    frames_dir = os.path.join(job_dir, 'frames')
    os.makedirs(frames_dir, exist_ok=True)
    pat = os.path.join(frames_dir, 'f_%05d.png')
    _write_state_job(job_dir, {'status': 'running', 'progress': 0, 'message': 'ffmpeg', 'processed': 0, 'total': 0}, cfg)
    if not extract_frames(ffmpeg, video, pat, crop, fps, None, None, mild_eq):
        _write_state_job(job_dir, {'status': 'error', 'message': 'ffmpeg failed', 'progress': 0, 'total': 0, 'results': None}, cfg)
        return
    fns = sorted(f for f in os.listdir(frames_dir) if f.endswith('.png'))
    total = len(fns)
    if total == 0:
        _write_state_job(job_dir, {'status': 'error', 'message': 'no frames', 'progress': 0, 'total': 0, 'results': None}, cfg)
        return
    _write_state_job(job_dir, {
        'status': 'running', 'progress': 5.0, 'message': f'OCR {total} frames', 'processed': 0, 'total': total,
    }, cfg)
    t0 = time.time()
    date_steps: dict[str, list[int]] = {}
    date_pairs: dict[str, list[tuple[int, float]]] = {}
    first_t: dict[str, float] = {}
    all_text: dict = {}
    tessdata = cfg['tessdata_dir']
    invf = 1.0 / fps
    carry_date = [None]
    step_only_reads: list[tuple[int, float]] = []
    for i, fn in enumerate(fns):
        if _is_cancelled(job_dir):
            _write_state_job(job_dir, {'status': 'cancelled', 'message': 'cancelled', 'progress': 0, 'processed': i, 'total': total, 'results': None, 'job_id': jbid}, cfg)
            _cleanup_job_dir(job_dir)
            return
        t_sec = i * invf
        p = os.path.join(frames_dir, fn)
        _ocr_one_file(p, fn, t_sec, year, ocr_lang, tessdata, date_steps, date_pairs, first_t, all_text, carry_date, step_only_reads, np, cv2, pytesseract)
        eta = 0.0
        if i > 0:
            dt_ = (time.time() - t0) / (i + 1) * (total - i - 1)
            eta = round(dt_, 1)
        pr = round(5 + 45 * (i + 1) / max(total, 1), 1)
        _write_state_job(job_dir, {'status': 'running', 'progress': pr, 'message': f'frame {i+1}/{total}', 'processed': i + 1, 'total': total, 'eta_sec': eta}, cfg)
    if not date_steps:
        cr = clean_ocr(' '.join((v.get('raw') or '') for v in all_text.values()))
        gd = find_date_data(cr, year)
        if gd:
            for v in sorted(all_text.values(), key=lambda x: float(x.get('t_sec', 0))):
                stv = v.get('steps')
                if not isinstance(stv, int):
                    continue
                tv = float(v.get('t_sec', 0))
                date_steps.setdefault(gd, []).append(stv)
                date_pairs.setdefault(gd, []).append((stv, tv))
                first_t[gd] = min(first_t.get(gd, 1e18), tv)
    merged = _build_merged_list(date_steps, date_pairs, all_text)
    g_frames_total = 0
    for rep in range(1):
        gap_ivs = _gaps_to_refine(merged, first_t)
        n_g = len(gap_ivs)
        if n_g == 0:
            break
        gidx = 0
        for t1, t2 in gap_ivs:
            if _is_cancelled(job_dir):
                _write_state_job(job_dir, {'status': 'cancelled', 'message': 'cancelled', 'progress': 0, 'processed': 0, 'total': max(n_g, 1), 'results': None, 'job_id': jbid}, cfg)
                _cleanup_job_dir(job_dir)
                return
            t1 = max(0.0, t1 - 0.15)
            t2 = min(vid_dur - 0.01, t2 + 0.15) if vid_dur < 1e8 else t2 + 0.15
            seg = t2 - t1
            if seg < 0.15:
                continue
            fpr = fps_refine * (1.25 if rep == 0 else 1.6)
            if seg > 120:
                fpr = min(fpr, 2.5)
            elif seg > 60:
                fpr = min(fpr, 3.5)
            elif seg > 25:
                fpr = min(fpr, 4.5)
            gidx += 1
            _write_state_job(job_dir, {
                'status': 'running',
                'progress': round(50 + 40 * (gidx - 0.5) / max(n_g, 1) if rep == 0 else 70 + 20 * (gidx) / max(n_g, 1), 1),
                'message': f'gap r{rep+1} {gidx}/{n_g} {fpr:.1f}fps {t1:.1f}s-{t2:.1f}s',
                'processed': gidx,
                'total': max(n_g, 1),
            }, cfg)
            if os.path.isdir(frames_dir):
                try:
                    shutil.rmtree(frames_dir)
                except OSError:
                    pass
            os.makedirs(frames_dir, exist_ok=True)
            if not extract_frames(ffmpeg, video, pat, crop, fpr, t1, t2, mild_eq):
                continue
            fns2 = sorted(f for f in os.listdir(frames_dir) if f.endswith('.png'))
            g_frames_total += len(fns2)
            inv2 = 1.0 / fpr
            nj = max(len(fns2), 1)
            for j, fn in enumerate(fns2):
                if _is_cancelled(job_dir):
                    _write_state_job(job_dir, {'status': 'cancelled', 'message': 'cancelled', 'progress': 0, 'processed': j, 'total': nj, 'results': None, 'job_id': jbid}, cfg)
                    _cleanup_job_dir(job_dir)
                    return
                tsec = t1 + j * inv2
                p = os.path.join(frames_dir, fn)
                _ocr_one_file(p, fn, tsec, year, ocr_lang, tessdata, date_steps, date_pairs, first_t, all_text, carry_date, step_only_reads, np, cv2, pytesseract)
                if rep == 0:
                    pr_gap = 50.0 + 40.0 * ((gidx - 1) + (j + 1) / nj) / max(n_g, 1)
                else:
                    pr_gap = 70.0 + 20.0 * ((gidx - 1) + (j + 1) / nj) / max(n_g, 1)
                pr_gap = min(89.9, round(pr_gap, 1))
                _write_state_job(job_dir, {
                    'status': 'running',
                    'progress': pr_gap,
                    'message': f'frame {j + 1}/{nj} gap {gidx}/{n_g} r{rep + 1}',
                    'processed': j + 1,
                    'total': nj,
                }, cfg)
    merged = _build_merged_list(date_steps, date_pairs, all_text)
    if step_only_reads:
        rsv_pre = {str(mm.get('dmy') or '') for mm in merged if isinstance(mm.get('dmy'), str) and mm.get('dmy')}
        ex = _extras_from_steps_only(year, _step_only_plateau(step_only_reads), rsv_pre)
        if ex:
            merged.extend(ex)

    def _msort_sort(it):
        pd = _parse_calendar_date(it.get('date') or '')
        tk = pd or datetime(2000, 1, 1)
        return (tk, float(it.get('t_sec') or 0))

    merged.sort(key=_msort_sort)
    _write_state_job(job_dir, {'status': 'running', 'progress': 90.0, 'message': 'merge', 'processed': 1, 'total': 1}, cfg)
    total_frames_ocr = total + g_frames_total
    lines = []
    for m in merged:
        pd = _parse_calendar_date(m.get('date') or '')
        if pd:
            lines.append(f"{pd.strftime('%d-%m-%y')},{m['steps']},{m.get('t_sec', 0)}\n")
    out_dir = cfg['user_results_dir']
    os.makedirs(out_dir, exist_ok=True)
    ts = time.strftime('%Y%m%d_%H%M%S')
    out_name = f'steps_data_{ts}.txt'
    out_path = os.path.join(out_dir, out_name)
    _atomic_write(out_path, ''.join(lines))
    ubase = os.path.basename(os.path.normpath(os.path.join(out_dir, '..')))
    if os.path.isdir(frames_dir):
        try:
            shutil.rmtree(frames_dir)
        except OSError:
            pass
    done_payload = {
        'status': 'done',
        'progress': 100.0,
        'message': 'ok',
        'processed': total_frames_ocr,
        'total': max(total_frames_ocr, 1),
        'output_file': out_path.replace('\\', '/'),
        'output_basename': out_name,
        'output_rel': f'../users/{ubase}/results/{out_name}',
        'results': merged,
        'ocr_preview': _build_ocr_preview(all_text),
    }
    _write_state_job(job_dir, done_payload, cfg)
    _write_pending_video_confirm(cfg, jbid, done_payload)
    _cleanup_job_dir(job_dir)


def main() -> int:
    if len(sys.argv) < 2:
        print('usage: video_steps_worker.py <job_dir>', file=sys.stderr)
        return 2
    job_dir = os.path.abspath(sys.argv[1])
    try:
        run_job(job_dir)
    except Exception as e:
        try:
            cfx = _read_json(os.path.join(job_dir, 'job_config.json'))
        except Exception:
            cfx = {}
        _write_state_job(job_dir, {'status': 'error', 'message': str(e)[:500], 'progress': 0, 'total': 0, 'results': None}, cfx)
        raise
    return 0


if __name__ == '__main__':
    sys.exit(main() or 0)
