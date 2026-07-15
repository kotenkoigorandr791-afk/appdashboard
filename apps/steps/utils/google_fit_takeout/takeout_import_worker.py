# -*- coding: utf-8 -*-
from __future__ import annotations
import csv
import json
import os
import re
import shutil
import sys
import time
import zipfile
from datetime import datetime, timezone
from typing import Any

try:
    import psycopg2
except ImportError:
    psycopg2 = None


def _load_pg_config() -> dict[str, str]:
    cfg = {"host": "127.0.0.1", "port": "5432", "name": "appdashboard", "user": "appdashboard", "password": ""}
    for path in (
        os.environ.get("APP_DB_CONFIG", ""),
        "/opt/AppDashboard/data/db.env",
        "/opt/docker/appdashboard/code/data/db.env",
        os.path.normpath(os.path.join(os.path.dirname(__file__), "../../../../data/db.env")),
    ):
        if not path or not os.path.isfile(path):
            continue
        with open(path, "r", encoding="utf-8", errors="replace") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                k, v = k.strip().lower(), v.strip()
                if k == "db_host":
                    cfg["host"] = v
                elif k == "db_port":
                    cfg["port"] = v
                elif k == "db_name":
                    cfg["name"] = v
                elif k == "db_user":
                    cfg["user"] = v
                elif k == "db_password":
                    cfg["password"] = v
        break
    return cfg


def _pg_connect():
    if psycopg2 is None:
        return None
    pg = _load_pg_config()
    try:
        return psycopg2.connect(
            host=pg["host"],
            port=pg["port"],
            dbname=pg["name"],
            user=pg["user"],
            password=pg["password"],
            connect_timeout=5,
        )
    except Exception:
        return None


def _schema_sql(schema: str) -> str:
    return '"' + schema.replace('"', '""') + '"'


def _ensure_fit_job_table(conn, schema: str) -> None:
    s = _schema_sql(schema)
    with conn.cursor() as cur:
        cur.execute(f"CREATE TABLE IF NOT EXISTS {s}.fit_takeout_job_state (id INTEGER PRIMARY KEY CHECK (id = 1), job_id TEXT NOT NULL DEFAULT '', state_json TEXT NOT NULL DEFAULT '{{}}', updated_at BIGINT NOT NULL DEFAULT 0)")
        cur.execute(f"INSERT INTO {s}.fit_takeout_job_state (id, job_id, state_json, updated_at) VALUES (1, '', '{{}}', 0) ON CONFLICT (id) DO NOTHING")
    conn.commit()


def _write_state_sqlite(db_path: str, job_id: str, obj: dict) -> None:
    import sqlite3
    tab = "fit_takeout_job_state"
    payload = json.dumps(obj, ensure_ascii=False)
    t = int(time.time())
    for attempt in range(60):
        try:
            conn = sqlite3.connect(db_path, timeout=30.0)
            try:
                conn.execute("PRAGMA busy_timeout=30000")
                conn.execute(
                    f"INSERT INTO {tab} (id, job_id, state_json, updated_at) VALUES (1, ?, ?, ?) "
                    "ON CONFLICT(id) DO UPDATE SET job_id=excluded.job_id, state_json=excluded.state_json, updated_at=excluded.updated_at",
                    (job_id, payload, t),
                )
                conn.commit()
                return
            finally:
                conn.close()
        except sqlite3.OperationalError:
            time.sleep(0.03 * min(attempt + 1, 25))


def _write_state(job_dir: str, cfg: dict, job_id: str, obj: dict) -> None:
    try:
        with open(os.path.join(job_dir, "state.json"), "w", encoding="utf-8") as f:
            json.dump(obj, f, ensure_ascii=False)
    except OSError:
        pass
    payload = json.dumps(obj, ensure_ascii=False)
    t = int(time.time())
    schema = str(cfg.get("user_schema") or "").strip()
    if schema and psycopg2 is not None:
        conn = _pg_connect()
        if conn is not None:
            try:
                _ensure_fit_job_table(conn, schema)
                with conn.cursor() as cur:
                    cur.execute(
                        f"INSERT INTO {_schema_sql(schema)}.fit_takeout_job_state (id, job_id, state_json, updated_at) VALUES (1, %s, %s, %s) "
                        "ON CONFLICT (id) DO UPDATE SET job_id = EXCLUDED.job_id, state_json = EXCLUDED.state_json, updated_at = EXCLUDED.updated_at",
                        (job_id, payload, t),
                    )
                conn.commit()
                conn.close()
                return
            except Exception:
                try:
                    conn.close()
                except Exception:
                    pass
    db_path = str(cfg.get("user_sqlite_path") or "").strip()
    if db_path and os.path.isfile(db_path):
        _write_state_sqlite(db_path, job_id, obj)


def _read_json(path: str) -> Any:
    with open(path, "r", encoding="utf-8", errors="replace") as f:
        return json.load(f)
def _is_cancelled(job_dir: str) -> bool:
    return os.path.isfile(os.path.join(job_dir, "cancel.flag"))
def _cleanup_job_dir(job_dir: str) -> None:
    try:
        shutil.rmtree(job_dir)
    except OSError:
        pass


def _norm_key(s: str) -> str:
    return re.sub(r"\s+", " ", s.strip().lower())


def _nanos_to_day_utc(n: Any) -> str | None:
    try:
        v = int(str(n).strip())
    except (ValueError, TypeError):
        return None
    sec = v / 1_000_000_000.0
    t = datetime.fromtimestamp(sec, tz=timezone.utc)
    return t.strftime("%Y-%m-%d")


def _parse_fit_json_obj(obj: Any, day_steps: dict[str, int], mode: str) -> None:
    if not isinstance(obj, dict):
        return
    dtp = (obj.get("dataTypeName") or obj.get("Data Source") or obj.get("dataType") or "")
    sdt = str(dtp).lower()
    if "step" not in sdt and "com.google" not in sdt and "intVal" not in json.dumps(obj)[:2000].lower():
        if "Data Points" not in obj and "dataPoints" not in obj and "point" not in obj:
            return
    points = None
    for k in ("Data Points", "dataPoints", "point", "points"):
        if k in obj and isinstance(obj[k], list):
            points = obj[k]
            break
    if points is None:
        for v in obj.values():
            if isinstance(v, list) and v and isinstance(v[0], dict):
                a0 = v[0]
                if any(x in a0 for x in ("startTimeNanos", "startTime", "intVal", "value", "fitValue")):
                    points = v
                    break
    if not points or not isinstance(points, list):
        for k, v2 in obj.items():
            if isinstance(v2, dict) and (k in ("Data Points",) or (isinstance(v2.get("Data Points"), list))):
                _parse_fit_json_obj({**v2, "Data Source": obj.get("Data Source", dtp)}, day_steps, mode)
        return
    is_delta = "delta" in sdt
    is_cum = "cumulative" in sdt or (not is_delta and "step_count" in sdt and "delta" not in sdt and "cumulative" in sdt)
    is_delta = is_delta and "cumulative" not in sdt
    for p in points:
        if not isinstance(p, dict):
            continue
        n0 = p.get("startTimeNanos") or p.get("startTime") or p.get("rawTimestampNanos")
        day = _nanos_to_day_utc(n0) if n0 is not None else None
        if not day and p.get("date"):
            try:
                s = str(p["date"])
                m = re.match(r"^(\d{4})-(\d{2})-(\d{2})", s)
                if m:
                    day = f"{m.group(1)}-{m.group(2)}-{m.group(3)}"
            except (ValueError, TypeError, AttributeError):
                pass
        val = 0
        for fvkey in ("fitValue", "value", "values"):
            fv = p.get(fvkey)
            if isinstance(fv, list) and fv:
                for it in fv:
                    if not isinstance(it, dict):
                        continue
                    w = it.get("value")
                    if isinstance(w, dict) and w.get("intVal") is not None:
                        try:
                            val = max(val, int(w["intVal"]))
                        except (ValueError, TypeError):
                            pass
            elif isinstance(fv, dict) and fv.get("intVal") is not None:
                try:
                    val = int(fv["intVal"])
                except (ValueError, TypeError):
                    pass
        if p.get("intVal") is not None:
            try:
                val = int(p["intVal"])
            except (ValueError, TypeError):
                pass
        if not day or val < 0:
            continue
        if is_cum and not is_delta:
            if val > day_steps.get(day, 0):
                day_steps[day] = val
        elif is_delta or "delta" in sdt or "com.google.step_count.delta" in sdt:
            day_steps[day] = day_steps.get(day, 0) + int(val)
        else:
            day_steps[day] = day_steps.get(day, 0) + int(val)


def _walk_json_files(root: str) -> list[str]:
    out = []
    for dirpath, _, files in os.walk(root):
        for fn in files:
            if fn.lower().endswith(".json") and "metadata" not in fn.lower() and "session" not in fn.lower()[:10]:
                out.append(os.path.join(dirpath, fn))
    return out


def _try_parse_file(path: str, day_steps: dict[str, int], seen: set[str]) -> bool:
    key = str(os.path.getsize(path)) + path[-80:]
    if key in seen:
        return False
    try:
        data = _read_json(path)
    except (OSError, ValueError, json.JSONDecodeError, UnicodeError):
        return False
    n0 = len(day_steps)
    if isinstance(data, list):
        for it in data:
            if isinstance(it, dict):
                _parse_fit_json_obj(it, day_steps, "L")
    elif isinstance(data, dict):
        if "buckets" in data or "dataset" in data or "Data Points" in data or "dataTypeName" in str(data)[:200]:
            _parse_fit_json_obj(data, day_steps, "D")
    return len(day_steps) > n0


def _parse_csv_file(path: str, day_steps: dict[str, int]) -> None:
    for enc in ("utf-8", "utf-8-sig", "cp1251", "latin-1"):
        n0 = len(day_steps)
        try:
            with open(path, "r", encoding=enc, errors="replace", newline="") as f:
                rd = csv.reader(f)
                hdr = next(rd, None)
                if not hdr or len(hdr) < 2:
                    continue
                hlow = [_norm_key(x) for x in hdr]
                idx_d = 0
                idx_s = 1
                for i, h in enumerate(hlow):
                    if h in ("date", "day", "дата", "day index") or ("date" in h and "end" not in h):
                        idx_d = i
                for i, h in enumerate(hlow):
                    if "step" in h or h.startswith("шаг") or h.startswith("крок") or h.startswith("krok"):
                        if "distance" in h or "kcal" in h or "кал" in h or "серд" in h or "heart" in h:
                            continue
                        idx_s = i
                for row in rd:
                    if len(row) <= max(idx_d, idx_s):
                        continue
                    ds = (row[idx_d] or "").strip()
                    st_raw = re.sub(r"\D", "", (row[idx_s] or ""))
                    if not st_raw.isdigit():
                        continue
                    step = int(st_raw)
                    m = re.match(r"^(\d{4})-(\d{2})-(\d{2})", ds)
                    if m:
                        dday = f"{m.group(1)}-{m.group(2)}-{m.group(3)}"
                    else:
                        m2 = re.match(r"^(\d{1,2})[./-](\d{1,2})[./-](\d{2,4})$", ds)
                        if m2:
                            a, b, c = m2.group(1), m2.group(2), m2.group(3)
                            cy = int(c) if len(c) > 2 else (2000 + int(c) if int(c) < 70 else 1900 + int(c))
                            dday = f"{cy:04d}-{int(b):02d}-{int(a):02d}"
                        else:
                            continue
                    day_steps[dday] = day_steps.get(dday, 0) + step
        except OSError:
            continue
        if len(day_steps) > n0:
            return
def _parse_daily_activity_metrics(root: str) -> dict[str, int]:
    out: dict[str, int] = {}
    base = os.path.join(root, "Takeout", "Fit", "Daily activity metrics")
    if not os.path.isdir(base):
        return out
    for fn in os.listdir(base):
        if not fn.lower().endswith(".csv"):
            continue
        m = re.match(r"^(\d{4})-(\d{2})-(\d{2})\.csv$", fn)
        if not m:
            continue
        day = f"{m.group(1)}-{m.group(2)}-{m.group(3)}"
        p = os.path.join(base, fn)
        total = 0
        for enc in ("utf-8", "utf-8-sig", "cp1251", "latin-1"):
            try:
                with open(p, "r", encoding=enc, errors="replace", newline="") as f:
                    rd = csv.reader(f)
                    hdr = next(rd, None)
                    if not hdr:
                        continue
                    idx = -1
                    for i, h in enumerate(hdr):
                        hn = _norm_key(str(h))
                        if hn in ("step count", "steps", "шаги", "кроки") or ("step" in hn and "distance" not in hn):
                            idx = i
                            break
                    if idx < 0:
                        continue
                    for row in rd:
                        if len(row) <= idx:
                            continue
                        raw = (row[idx] or "").strip().replace(" ", "")
                        if raw == "":
                            continue
                        if re.match(r"^-?\d+(\.\d+)?$", raw):
                            v = int(float(raw))
                            if v > 0:
                                total += v
                break
            except OSError:
                continue
        if total > 0:
            out[day] = total
    return out
def _load_day_steps_snapshot(path: str) -> dict[str, int]:
    try:
        with open(path, "r", encoding="utf-8") as f:
            obj = json.load(f)
    except (OSError, ValueError, json.JSONDecodeError):
        return {}
    if not isinstance(obj, dict):
        return {}
    out: dict[str, int] = {}
    for k, v in obj.items():
        ks = str(k)
        if not re.match(r"^\d{4}-\d{2}-\d{2}$", ks):
            continue
        try:
            iv = int(v)
        except (ValueError, TypeError):
            continue
        if iv > 0:
            out[ks] = iv
    return out
def _save_day_steps_snapshot(path: str, day_steps: dict[str, int]) -> None:
    with open(path, "w", encoding="utf-8") as f:
        json.dump(day_steps, f, ensure_ascii=False, separators=(",", ":"))
def _count_conflict_days_sqlite(db_path: str, day_steps: dict[str, int]) -> int:
    import sqlite3
    if not day_steps:
        return 0
    days = sorted(day_steps.keys())
    if not days:
        return 0
    n = 0
    for attempt in range(20):
        try:
            conn = sqlite3.connect(db_path, timeout=30.0)
            try:
                conn.execute("PRAGMA busy_timeout=30000")
                ph = ",".join(["?"] * len(days))
                cur = conn.execute(f"SELECT date, steps FROM steps WHERE date IN ({ph})", days)
                rows = cur.fetchall()
                for d, s in rows:
                    if d in day_steps and int(s) != int(day_steps[d]):
                        n += 1
                return n
            finally:
                conn.close()
        except sqlite3.OperationalError:
            time.sleep(0.05 * (attempt + 1))
    return 0


def _count_conflict_days(cfg: dict, day_steps: dict[str, int]) -> int:
    schema = str(cfg.get("user_schema") or "").strip()
    if schema and psycopg2 is not None and day_steps:
        conn = _pg_connect()
        if conn is not None:
            try:
                days = sorted(day_steps.keys())
                ph = ",".join(["%s"] * len(days))
                with conn.cursor() as cur:
                    cur.execute(f"SELECT date, steps FROM {_schema_sql(schema)}.steps WHERE date IN ({ph})", days)
                    n = 0
                    for d, s in cur.fetchall():
                        if d in day_steps and int(s) != int(day_steps[d]):
                            n += 1
                conn.close()
                return n
            except Exception:
                try:
                    conn.close()
                except Exception:
                    pass
    db_path = str(cfg.get("user_sqlite_path") or "").strip()
    if not db_path or not os.path.isfile(db_path):
        return 0
    return _count_conflict_days_sqlite(db_path, day_steps)


def _import_steps(cfg: dict, day_steps: dict[str, int], mode: str) -> int:
    if not day_steps:
        return 0
    schema = str(cfg.get("user_schema") or "").strip()
    if schema and psycopg2 is not None:
        conn = _pg_connect()
        if conn is not None:
            try:
                s = _schema_sql(schema)
                rows = [(d, v) for d, v in sorted(day_steps.items()) if v > 0]
                with conn.cursor() as cur:
                    if mode == "keep" or mode == "new_only":
                        cur.executemany(f"INSERT INTO {s}.steps (date, steps) VALUES (%s, %s) ON CONFLICT (date) DO NOTHING", rows)
                    else:
                        cur.executemany(f"INSERT INTO {s}.steps (date, steps) VALUES (%s, %s) ON CONFLICT (date) DO UPDATE SET steps = EXCLUDED.steps", rows)
                conn.commit()
                conn.close()
                return len(rows)
            except Exception:
                try:
                    conn.close()
                except Exception:
                    pass
    db_path = str(cfg.get("user_sqlite_path") or "").strip()
    if not db_path:
        return 0
    return _import_steps_sqlite(db_path, day_steps, mode)


def _import_steps_sqlite(db_path: str, day_steps: dict[str, int], mode: str) -> int:
    import sqlite3
    if not day_steps:
        return 0
    n = 0
    for attempt in range(30):
        try:
            conn = sqlite3.connect(db_path, timeout=30.0)
            try:
                conn.execute("PRAGMA busy_timeout=30000")
                sql = "INSERT OR REPLACE INTO steps (date, steps) VALUES (?, ?)"
                if mode == "keep":
                    sql = "INSERT OR IGNORE INTO steps (date, steps) VALUES (?, ?)"
                conn.executemany(
                    sql,
                    [(d, s) for d, s in sorted(day_steps.items()) if s > 0],
                )
                conn.commit()
                n = len([x for x in day_steps.values() if x > 0])
                return n
            finally:
                conn.close()
        except sqlite3.OperationalError:
            time.sleep(0.05 * (attempt + 1))
    return 0


def run_job(job_dir: str) -> None:
    jdir = os.path.abspath(job_dir)
    cfgp = os.path.join(jdir, "job_config.json")
    with open(cfgp, "r", encoding="utf-8") as f:
        cfg = json.load(f)
    jbid = os.path.basename(os.path.normpath(jdir))
    schema = str(cfg.get("user_schema") or "").strip()
    db_path = os.path.normpath(str(cfg.get("user_sqlite_path", "")))
    if not schema and not os.path.isfile(db_path):
        _write_state(jdir, cfg, jbid, {"status": "error", "message": "no user db", "progress": 0, "imported": 0, "job_id": jbid})
        return
    zip_p = os.path.join(jdir, "takeout.zip")
    if not os.path.isfile(zip_p):
        _write_state(jdir, cfg, jbid, {"status": "error", "message": "no zip", "progress": 0, "imported": 0, "job_id": jbid})
        return
    if _is_cancelled(jdir):
        _write_state(jdir, cfg, jbid, {"status": "cancelled", "message": "cancelled", "progress": 0, "imported": 0, "total_days": 0, "job_id": jbid})
        _cleanup_job_dir(jdir)
        return
    ext = os.path.join(jdir, "extracted")
    mode = str(cfg.get("conflict_mode", "ask")).strip().lower()
    if mode not in ("ask", "keep", "overwrite", "new_only"):
        mode = "ask"
    snap = os.path.join(jdir, "parsed_day_steps.json")
    day_steps = _load_day_steps_snapshot(snap)
    if not day_steps:
        if os.path.isdir(ext):
            for root, _, files in os.walk(ext, topdown=False):
                for name in files:
                    try:
                        os.remove(os.path.join(root, name))
                    except OSError:
                        pass
                try:
                    os.rmdir(root)
                except OSError:
                    pass
        os.makedirs(ext, exist_ok=True)
        _write_state(jdir, cfg, jbid, {"status": "running", "progress": 2.0, "message": "unzip", "imported": 0, "total_days": 0, "job_id": jbid})
        try:
            with zipfile.ZipFile(zip_p, "r") as zf:
                zf.extractall(ext)
        except (zipfile.BadZipFile, OSError) as e:
            _write_state(jdir, cfg, jbid, {"status": "error", "message": str(e)[:200], "progress": 0, "imported": 0, "job_id": jbid})
            return
        _write_state(jdir, cfg, jbid, {"status": "running", "progress": 8.0, "message": "parse", "imported": 0, "total_days": 0, "job_id": jbid})
        day_steps = _parse_daily_activity_metrics(ext)
        if not day_steps:
            jsons = [p for p in _walk_json_files(ext) if "fit" in p.lower() or "d." in os.path.basename(p) or "step" in p.lower() or "activity" in p.lower()]
            if not jsons:
                jsons = _walk_json_files(ext)
            nfiles = max(len(jsons), 1)
            seen: set[str] = set()
            for i, path in enumerate(jsons):
                if _is_cancelled(jdir):
                    _write_state(jdir, cfg, jbid, {"status": "cancelled", "message": "cancelled", "progress": 0, "imported": 0, "total_days": len(day_steps), "job_id": jbid})
                    _cleanup_job_dir(jdir)
                    return
                _try_parse_file(path, day_steps, seen)
                pr = 8.0 + 80.0 * (i + 1) / nfiles
                _write_state(jdir, cfg, jbid, {"status": "running", "progress": min(88.0, pr), "message": f"file {i+1}/{nfiles}", "imported": 0, "total_days": len(day_steps), "job_id": jbid})
            for dirpath, _, files in os.walk(ext):
                if _is_cancelled(jdir):
                    _write_state(jdir, cfg, jbid, {"status": "cancelled", "message": "cancelled", "progress": 0, "imported": 0, "total_days": len(day_steps), "job_id": jbid})
                    _cleanup_job_dir(jdir)
                    return
                for fn in files:
                    if not fn.lower().endswith(".csv"):
                        continue
                    low = fn.lower()
                    if "step" in low or "day" in low or "d." in low or "daily" in low or "дневн" in low or "krok" in low:
                        _parse_csv_file(os.path.join(dirpath, fn), day_steps)
            if not day_steps:
                for dirpath, _, files in os.walk(ext):
                    for fn in files:
                        if fn.lower().endswith(".csv"):
                            _parse_csv_file(os.path.join(dirpath, fn), day_steps)
        if day_steps:
            _save_day_steps_snapshot(snap, day_steps)
    if not day_steps:
        _write_state(jdir, cfg, jbid, {"status": "error", "message": "no step data in archive", "progress": 0, "imported": 0, "total_days": 0, "job_id": jbid})
        return
    if _is_cancelled(jdir):
        _write_state(jdir, cfg, jbid, {"status": "cancelled", "message": "cancelled", "progress": 0, "imported": 0, "total_days": len(day_steps), "job_id": jbid})
        _cleanup_job_dir(jdir)
        return
    if mode == "ask":
        conf = _count_conflict_days(cfg, day_steps)
        if conf > 0:
            _write_state(jdir, cfg, jbid, {"status": "awaiting_conflict", "message": "conflict_prompt", "progress": 92.0, "imported": 0, "total_days": len(day_steps), "conflict_days": conf, "job_id": jbid})
            return
        mode = "overwrite"
    nimp = _import_steps(cfg, day_steps, "overwrite" if mode == "overwrite" else "keep")
    if nimp < 1:
        nimp = len([1 for s in day_steps.values() if s > 0])
    _write_state(jdir, cfg, jbid, {"status": "done", "message": "ok", "progress": 100.0, "imported": nimp, "total_days": len(day_steps), "job_id": jbid})
    try:
        os.remove(zip_p)
    except OSError:
        pass
    _cleanup_job_dir(jdir)


def main() -> int:
    if len(sys.argv) < 2:
        print("usage: takeout_import_worker.py <job_dir>", file=sys.stderr)
        return 2
    job_dir = os.path.abspath(sys.argv[1])
    try:
        run_job(job_dir)
    except Exception as e:
        try:
            cfx = _read_json(os.path.join(job_dir, "job_config.json")) if os.path.isfile(os.path.join(job_dir, "job_config.json")) else {}
        except (OSError, ValueError, json.JSONDecodeError):
            cfx = {}
        jid = os.path.basename(os.path.normpath(job_dir))
        _write_state(job_dir, cfx, jid, {"status": "error", "message": str(e)[:500], "progress": 0, "imported": 0, "job_id": jid})
        raise
    return 0


if __name__ == "__main__":
    sys.exit(main() or 0)
