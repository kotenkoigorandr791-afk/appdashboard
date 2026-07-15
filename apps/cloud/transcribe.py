import os
import sys
import threading
import time
SCRIPT_DIR = os.path.dirname(__file__)
CACHE_DIR = os.path.join(SCRIPT_DIR, "transcription_uploads", ".cache")
os.makedirs(CACHE_DIR, exist_ok=True)
os.environ.setdefault("HOME", CACHE_DIR)
os.environ.setdefault("XDG_CACHE_HOME", CACHE_DIR)
os.environ.setdefault("MPLCONFIGDIR", CACHE_DIR)
os.environ.setdefault("TORCH_HOME", CACHE_DIR)
import whisper
sys.path.insert(0, os.path.join(SCRIPT_DIR, '..', '..', 'lib'))
import pg_sqlite_compat as sqlite3

BASE_DIR = os.path.realpath(os.path.join(SCRIPT_DIR, "..", ".."))
DB_LOCK = threading.Lock()

def safe_message(message):
    import re
    message = str(message)
    message = re.sub(r"\b[A-Za-z]:[\\/][^\s\"'<>)]*", "[hidden-path]", message)
    return re.sub(r"(^|[\s(])/(?:[^/\s\"'<>)]*/)*[^/\s\"'<>)]*", r"\1[hidden-path]", message)

def db_connection():
    return sqlite3.connect()

def ensure_tables():
    return

def update_progress(progress_id, percentage, message="", transcription="", status="processing", error_text=""):
    with DB_LOCK:
        with db_connection() as conn:
            conn.execute(
                """
                UPDATE cloud_transcription_jobs
                SET percentage = ?,
                    message = ?,
                    transcription = CASE WHEN ? <> '' THEN ? ELSE transcription END,
                    status = ?,
                    error_text = ?,
                    updated_at = strftime('%s','now')
                WHERE progress_id = ?
                """,
                (int(percentage), message, transcription, transcription, status, error_text, progress_id),
            )

def run_progress_pulse(stop_event, progress_id):
    progress = 25
    while not stop_event.wait(2):
        progress = min(progress + 2, 90)
        update_progress(progress_id, progress, "Transcribing audio...")

def transcribe_audio(input_file, language, progress_id, user_name, audio_path):
    try:
        input_file = os.path.abspath(input_file)
        safe_name = os.path.basename(input_file)

        if not os.path.exists(input_file):
            raise Exception(f"Input file not found: {safe_name}")

        # Verify file is readable
        try:
            with open(input_file, 'rb') as f:
                f.read(1)
        except Exception as e:
            raise Exception(f"Cannot read input file {safe_name}: {safe_message(e)}")

        current_filename = os.path.basename(input_file)
        update_progress(progress_id, 0, current_filename)
        update_progress(progress_id, 5, "Loading Whisper model...")

        # Set FFmpeg path explicitly
        pass  # PATH is configured system-wide on Linux

        model = whisper.load_model("tiny", download_root=CACHE_DIR)
        update_progress(progress_id, 15, "Preparing audio...")

        if not os.path.exists(input_file):
            raise Exception(f"Audio file disappeared: {safe_name}")

        update_progress(progress_id, 20, "Transcribing audio...")
        pulse_stop = threading.Event()
        pulse_thread = threading.Thread(target=run_progress_pulse, args=(pulse_stop, progress_id), daemon=True)
        pulse_thread.start()

        # Use raw string for Windows path
        result = model.transcribe(input_file, language=language, fp16=False)
        pulse_stop.set()
        pulse_thread.join(timeout=1)
        update_progress(progress_id, 95, "Saving result...")
        transcription = result.get("text", "")
        update_progress(progress_id, 100, "Completed", transcription, "completed", "")
        print("Transcription completed successfully!")
    except Exception as e:
        error_msg = f"Error: {safe_message(e)}"
        print(error_msg, file=sys.stderr)
        update_progress(progress_id, 0, error_msg, "", "error", error_msg)
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) != 6:
        print("Usage: python transcribe.py <input_audio> <language> <progress_id> <user> <audio_path>", file=sys.stderr)
        sys.exit(1)
    ensure_tables()
    input_file = sys.argv[1]
    language = sys.argv[2]
    progress_id = sys.argv[3]
    user_name = sys.argv[4]
    audio_path = sys.argv[5]

    # Debug logging
    print(f"Input file: {os.path.basename(input_file)}", file=sys.stderr)
    print(f"File exists: {os.path.exists(input_file)}", file=sys.stderr)
    print(f"File size: {os.path.getsize(input_file) if os.path.exists(input_file) else 'N/A'}", file=sys.stderr)

    # Check FFmpeg
    import shutil
    ffmpeg_path = shutil.which('ffmpeg')
    print(f"FFmpeg available: {'yes' if ffmpeg_path else 'no'}", file=sys.stderr)

    transcribe_audio(input_file, language, progress_id, user_name, audio_path)
