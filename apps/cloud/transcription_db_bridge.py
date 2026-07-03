import json
import os
import sys
import time
import base64
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', 'lib'))
import pg_sqlite_compat as sqlite3

def conn():
    return sqlite3.connect()

def ensure_tables(db):
    pass

def get_progress(db, progress_id):
    row = db.execute("SELECT progress_id,percentage,message,transcription,error_text,status,updated_at FROM cloud_transcription_jobs WHERE progress_id = %s LIMIT 1", (progress_id,)).fetchone()
    if not row:
        return {"found": False}
    updated_at = int(row["updated_at"] or 0)
    stale_seconds = max(0, int(time.time()) - updated_at)
    is_error = (row["status"] == "error") or ("Error:" in (row["message"] or "")) or ("Error:" in (row["error_text"] or ""))
    percentage = int(row["percentage"] or 0)
    return {"found": True, "progress_id": row["progress_id"], "percentage": percentage, "progress": percentage, "message": row["message"] or "", "transcription": row["transcription"] or "", "timestamp": updated_at, "stale_seconds": stale_seconds, "status": row["status"] or "processing", "error": row["error_text"] or "", "isProcessing": (not is_error and percentage < 100 and stale_seconds < 120), "isActive": (not is_error and percentage < 100 and stale_seconds < 120)}

def start_job(db, payload):
    db.execute("INSERT INTO cloud_transcription_jobs (progress_id,user_name,audio_path,language,status,percentage,message,transcription,error_text,started_at,updated_at) VALUES (%s, %s, %s, %s, 'processing', 0, 'Preparing startup...', '', '', extract(epoch from now())::bigint, extract(epoch from now())::bigint)", (payload.get("progress_id", ""), payload.get("user", "default"), payload.get("audio_path", ""), payload.get("language", "pl")))
    return {"success": True}

def find_active(db, payload):
    row = db.execute("SELECT progress_id,percentage,message,transcription,updated_at FROM cloud_transcription_jobs WHERE user_name = %s AND status = 'processing' AND percentage < 100 ORDER BY updated_at DESC LIMIT 1", (payload.get("user", "default"),)).fetchone()
    if not row:
        return {"success": True, "active_session": None}
    stale_seconds = max(0, int(time.time()) - int(row["updated_at"] or 0))
    if stale_seconds >= 120:
        return {"success": True, "active_session": None}
    return {"success": True, "active_session": {"progress_id": row["progress_id"], "progress": int(row["percentage"] or 0), "message": row["message"] or "", "transcription": row["transcription"] or ""}}

def stop_job(db, payload):
    progress_id = payload.get("progress_id", "")
    if not progress_id:
        return {"success": False, "error": "No progress_id provided"}
    db.execute("UPDATE cloud_transcription_jobs SET status = 'stopped', error_text = 'Stopped by user', updated_at = extract(epoch from now())::bigint WHERE progress_id = %s", (progress_id,))
    return {"success": True}

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No action provided"}))
        return
    action = sys.argv[1]
    payload = {}
    if len(sys.argv) > 2:
        try:
            raw_payload = sys.argv[2]
            if raw_payload.startswith("b64:"):
                raw_payload = base64.b64decode(raw_payload[4:]).decode("utf-8")
            payload = json.loads(raw_payload)
        except Exception:
            payload = {}
    try:
        with conn() as db:
            ensure_tables(db)
            if action == "ensure_tables":
                out = {"success": True}
            elif action == "start_job":
                out = start_job(db, payload)
            elif action == "get_progress":
                out = get_progress(db, payload.get("progress_id", ""))
            elif action == "find_active":
                out = find_active(db, payload)
            elif action == "stop_job":
                out = stop_job(db, payload)
            elif action == "list_tables":
                rows = db.execute("SELECT tablename AS name FROM pg_catalog.pg_tables WHERE schemaname='public' AND tablename = 'cloud_transcription_jobs' ORDER BY tablename").fetchall()
                out = {"success": True, "tables": [r["name"] for r in rows]}
            else:
                out = {"success": False, "error": "Unknown action"}
            print(json.dumps(out, ensure_ascii=False).encode('utf-8').decode('utf-8'))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}, ensure_ascii=False))

if __name__ == "__main__":
    main()
