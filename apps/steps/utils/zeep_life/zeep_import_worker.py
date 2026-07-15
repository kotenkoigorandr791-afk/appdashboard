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
from typing import Any

try:
    import pyzipper
except ImportError:
    pyzipper = None

try:
    import psycopg2
    import psycopg2.extras
except ImportError:
    psycopg2 = None


def _load_pg_config() -> dict[str, str]:
    cfg = {"host": "127.0.0.1", "port": "5432", "name": "appdashboard", "user": "appdashboard", "password": ""}
    for path in (
        os.environ.get("APP_DB_CONFIG", ""),
        "/opt/AppDashboard/data/db.env",
        "/opt/docker/appdashboard/code/data/db.env",
        os.path.normpath(os.path.join(os.path.dirname(__file__), "../../../../data/db.env")),
        os.path.normpath(os.path.join(os.path.dirname(__file__), "../../../data/db.env")),
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


def _pg_connect(cfg: dict):
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


def _ensure_zeep_job_table(conn, schema: str) -> None:
    s = _schema_sql(schema)
    with conn.cursor() as cur:
        cur.execute(f"CREATE TABLE IF NOT EXISTS {s}.zeep_job_state (id INTEGER PRIMARY KEY CHECK (id = 1), job_id TEXT NOT NULL DEFAULT '', state_json TEXT NOT NULL DEFAULT '{{}}', updated_at BIGINT NOT NULL DEFAULT 0)")
        cur.execute(f"INSERT INTO {s}.zeep_job_state (id, job_id, state_json, updated_at) VALUES (1, '', '{{}}', 0) ON CONFLICT (id) DO NOTHING")
    conn.commit()


def _write_state(job_dir: str, cfg: dict, job_id: str, obj: dict) -> None:
    payload = json.dumps(obj, ensure_ascii=False)
    t = int(time.time())
    try:
        with open(os.path.join(job_dir, "state.json"), "w", encoding="utf-8") as f:
            f.write(payload)
    except OSError:
        pass
    schema = str(cfg.get("user_schema") or "").strip()
    if schema and psycopg2 is not None:
        conn = _pg_connect(cfg)
        if conn is not None:
            try:
                _ensure_zeep_job_table(conn, schema)
                with conn.cursor() as cur:
                    cur.execute(
                        f"INSERT INTO {_schema_sql(schema)}.zeep_job_state (id, job_id, state_json, updated_at) VALUES (1, %s, %s, %s) "
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
        import sqlite3
        for attempt in range(20):
            try:
                conn = sqlite3.connect(db_path, timeout=30.0)
                try:
                    conn.execute("CREATE TABLE IF NOT EXISTS zeep_job_state (id INTEGER PRIMARY KEY CHECK (id = 1), job_id TEXT NOT NULL DEFAULT '', state_json TEXT NOT NULL DEFAULT '{}', updated_at INTEGER NOT NULL DEFAULT 0)")
                    conn.execute("INSERT OR IGNORE INTO zeep_job_state (id, job_id, state_json, updated_at) VALUES (1, '', '{}', 0)")
                    conn.execute(
                        "INSERT INTO zeep_job_state (id, job_id, state_json, updated_at) VALUES (1, ?, ?, ?) "
                        "ON CONFLICT(id) DO UPDATE SET job_id=excluded.job_id, state_json=excluded.state_json, updated_at=excluded.updated_at",
                        (job_id, payload, t),
                    )
                    conn.commit()
                    return
                finally:
                    conn.close()
            except sqlite3.OperationalError:
                time.sleep(0.03 * min(attempt + 1, 25))


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


def _parse_activity_csv(path: str, day_steps: dict[str, int]) -> None:
    for enc in ("utf-8-sig", "utf-8", "cp1251", "latin-1"):
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
                    if h == "date" or h.startswith("date"):
                        idx_d = i
                for i, h in enumerate(hlow):
                    if h == "steps" or (h.startswith("step") and "distance" not in h):
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
                    if not m:
                        continue
                    dday = f"{m.group(1)}-{m.group(2)}-{m.group(3)}"
                    if step >= 0:
                        day_steps[dday] = step
        except OSError:
            continue
        if len(day_steps) > n0:
            return


def _norm_password(password: str) -> str:
    s = str(password or "")
    for ch in ("\ufeff", "\u200b", "\u200c", "\u200d", "\u2060"):
        s = s.replace(ch, "")
    return s.strip()


def _extract_zip(zip_p: str, ext: str, password: str) -> None:
    password = _norm_password(password)
    pwd_b = password.encode("utf-8") if password else None
    if pyzipper is None:
        raise ValueError("pyzipper not installed")
    try:
        with pyzipper.AESZipFile(zip_p, "r") as zf:
            if pwd_b:
                zf.setpassword(pwd_b)
            zf.extractall(ext, pwd=pwd_b)
        return
    except RuntimeError as e:
        msg = str(e).lower()
        if "password" in msg or "bad password" in msg or "incorrect" in msg:
            raise ValueError("bad password") from e
        raise
    except zipfile.BadZipFile as e:
        raise ValueError("bad zip") from e


def _find_activity_csvs(root: str) -> list[str]:
    out: list[str] = []
    for dirpath, _, files in os.walk(root):
        rel = os.path.relpath(dirpath, root).replace("\\", "/").upper()
        if "ACTIVITY" in rel and "ACTIVITY_MINUTE" not in rel and "ACTIVITY_STAGE" not in rel:
            for fn in files:
                if fn.upper().startswith("ACTIVITY") and fn.lower().endswith(".csv"):
                    out.append(os.path.join(dirpath, fn))
    if not out:
        for dirpath, _, files in os.walk(root):
            for fn in files:
                low = fn.lower()
                if low.startswith("activity") and low.endswith(".csv") and "minute" not in low and "stage" not in low:
                    out.append(os.path.join(dirpath, fn))
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
        if iv >= 0:
            out[ks] = iv
    return out


def _save_day_steps_snapshot(path: str, day_steps: dict[str, int]) -> None:
    with open(path, "w", encoding="utf-8") as f:
        json.dump(day_steps, f, ensure_ascii=False, separators=(",", ":"))


def _count_conflict_days(cfg: dict, day_steps: dict[str, int]) -> int:
    schema = str(cfg.get("user_schema") or "").strip()
    if schema and psycopg2 is not None and day_steps:
        conn = _pg_connect(cfg)
        if conn is not None:
            try:
                days = sorted(day_steps.keys())
                ph = ",".join(["%s"] * len(days))
                with conn.cursor() as cur:
                    cur.execute(f'SELECT date, steps FROM {_schema_sql(schema)}.steps WHERE date IN ({ph})', days)
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
    if not db_path or not day_steps:
        return 0
    import sqlite3
    days = sorted(day_steps.keys())
    for attempt in range(20):
        try:
            conn = sqlite3.connect(db_path, timeout=30.0)
            try:
                ph = ",".join(["?"] * len(days))
                cur = conn.execute(f"SELECT date, steps FROM steps WHERE date IN ({ph})", days)
                n = 0
                for d, s in cur.fetchall():
                    if d in day_steps and int(s) != int(day_steps[d]):
                        n += 1
                return n
            finally:
                conn.close()
        except sqlite3.OperationalError:
            time.sleep(0.05 * (attempt + 1))
    return 0


def _import_steps(cfg: dict, day_steps: dict[str, int], mode: str) -> int:
    if not day_steps:
        return 0
    schema = str(cfg.get("user_schema") or "").strip()
    if schema and psycopg2 is not None:
        conn = _pg_connect(cfg)
        if conn is not None:
            try:
                s = _schema_sql(schema)
                rows = [(d, v) for d, v in sorted(day_steps.items()) if v >= 0]
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
    import sqlite3
    for attempt in range(30):
        try:
            conn = sqlite3.connect(db_path, timeout=30.0)
            try:
                sql = "INSERT OR REPLACE INTO steps (date, steps) VALUES (?, ?)"
                if mode == "keep" or mode == "new_only":
                    sql = "INSERT OR IGNORE INTO steps (date, steps) VALUES (?, ?)"
                conn.executemany(sql, [(d, s) for d, s in sorted(day_steps.items()) if s >= 0])
                conn.commit()
                return len([x for x in day_steps.values() if x >= 0])
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
    pwd = _norm_password(str(cfg.get("archive_password", "") or ""))
    schema = str(cfg.get("user_schema") or "").strip()
    db_path = os.path.normpath(str(cfg.get("user_sqlite_path", "")))
    has_db = bool(schema) or (db_path and os.path.isfile(db_path))
    if not has_db:
        _write_state(jdir, cfg, jbid, {"status": "error", "message": "no user db", "progress": 0, "imported": 0, "job_id": jbid})
        return
    zip_p = os.path.join(jdir, "zeep.zip")
    if not os.path.isfile(zip_p):
        _write_state(jdir, cfg, jbid, {"status": "error", "message": "no zip", "progress": 0, "imported": 0, "job_id": jbid})
        return
    if _is_cancelled(jdir):
        _write_state(jdir, cfg, jbid, {"status": "cancelled", "message": "cancelled", "progress": 0, "imported": 0, "total_days": 0, "job_id": jbid})
        _cleanup_job_dir(jdir)
        return
    _write_state(jdir, cfg, jbid, {"status": "running", "progress": 0.5, "message": "queued", "imported": 0, "total_days": 0, "job_id": jbid})
    ext = os.path.join(jdir, "extracted")
    mode = str(cfg.get("conflict_mode", "ask")).strip().lower()
    if mode not in ("ask", "keep", "overwrite", "new_only", "preview"):
        mode = "ask"
    snap = os.path.join(jdir, "parsed_day_steps.json")
    day_steps = _load_day_steps_snapshot(snap)
    if not day_steps:
        if os.path.isdir(ext):
            shutil.rmtree(ext, ignore_errors=True)
        os.makedirs(ext, exist_ok=True)
        _write_state(jdir, cfg, jbid, {"status": "running", "progress": 2.0, "message": "unzip", "imported": 0, "total_days": 0, "job_id": jbid})
        try:
            _extract_zip(zip_p, ext, pwd)
        except ValueError as e:
            msg = str(e)
            if msg == "bad password":
                err = "bad password"
            elif msg == "pyzipper not installed":
                err = "pyzipper not installed"
            elif msg == "bad zip":
                err = "bad zip"
            else:
                err = msg[:200]
            _write_state(jdir, cfg, jbid, {"status": "error", "message": err, "progress": 0, "imported": 0, "job_id": jbid})
            return
        except (zipfile.BadZipFile, OSError) as e:
            _write_state(jdir, cfg, jbid, {"status": "error", "message": str(e)[:200], "progress": 0, "imported": 0, "job_id": jbid})
            return
        _write_state(jdir, cfg, jbid, {"status": "running", "progress": 8.0, "message": "parse", "imported": 0, "total_days": 0, "job_id": jbid})
        csvs = _find_activity_csvs(ext)
        nfiles = max(len(csvs), 1)
        for i, path in enumerate(csvs):
            if _is_cancelled(jdir):
                _write_state(jdir, cfg, jbid, {"status": "cancelled", "message": "cancelled", "progress": 0, "imported": 0, "total_days": len(day_steps), "job_id": jbid})
                _cleanup_job_dir(jdir)
                return
            _parse_activity_csv(path, day_steps)
            pr = 8.0 + 80.0 * (i + 1) / nfiles
            _write_state(jdir, cfg, jbid, {"status": "running", "progress": min(88.0, pr), "message": f"file {i+1}/{nfiles}", "imported": 0, "total_days": len(day_steps), "job_id": jbid})
        if day_steps:
            _save_day_steps_snapshot(snap, day_steps)
    if not day_steps:
        _write_state(jdir, cfg, jbid, {"status": "error", "message": "no step data in archive", "progress": 0, "imported": 0, "total_days": 0, "job_id": jbid})
        return
    if mode == "preview":
        _write_state(jdir, cfg, jbid, {"status": "awaiting_confirm", "message": "preview", "progress": 90.0, "imported": 0, "total_days": len(day_steps), "job_id": jbid})
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
    imp_mode = "overwrite" if mode == "overwrite" else ("keep" if mode == "keep" else "new_only")
    nimp = _import_steps(cfg, day_steps, imp_mode)
    if nimp < 1:
        nimp = len([1 for s in day_steps.values() if s >= 0])
    _write_state(jdir, cfg, jbid, {"status": "done", "message": "ok", "progress": 100.0, "imported": nimp, "total_days": len(day_steps), "job_id": jbid})
    try:
        os.remove(zip_p)
    except OSError:
        pass
    _cleanup_job_dir(jdir)


def main() -> int:
    if len(sys.argv) < 2:
        print("usage: zeep_import_worker.py <job_dir>", file=sys.stderr)
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
        _write_state(job_dir, cfx if isinstance(cfx, dict) else {}, jid, {"status": "error", "message": str(e)[:500], "progress": 0, "imported": 0, "job_id": jid})
        raise
    return 0


if __name__ == "__main__":
    sys.exit(main() or 0)
