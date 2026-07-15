import os
import sys
import time
import shutil
import subprocess
SCRIPT_DIR = os.path.dirname(__file__)
CACHE_DIR = os.path.join(SCRIPT_DIR, "tts_output", ".cache")
os.makedirs(CACHE_DIR, exist_ok=True)
os.environ.setdefault("HOME", CACHE_DIR)
os.environ.setdefault("XDG_CACHE_HOME", CACHE_DIR)
os.environ.setdefault("MPLCONFIGDIR", CACHE_DIR)
from gtts import gTTS
sys.path.insert(0, os.path.join(SCRIPT_DIR, '..', '..', 'lib'))
import pg_sqlite_compat as sqlite3

# Set PATH if not available (for Windows service/PHP execution)
if 'PATH' not in os.environ:
    os.environ['PATH'] = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'

BASE_DIR = os.path.realpath(os.path.join(SCRIPT_DIR, "..", ".."))

def safe_message(message):
    import re
    message = str(message)
    message = re.sub(r"\b[A-Za-z]:[\\/][^\s\"'<>)]*", "[hidden-path]", message)
    return re.sub(r"(^|[\s(])/(?:[^/\s\"'<>)]*/)*[^/\s\"'<>)]*", r"\1[hidden-path]", message)

def db_connection():
    return sqlite3.connect()

def update_progress(progress_id, percentage, message="", audio_url="", status="processing", error_text=""):
    try:
        conn = db_connection()
        conn.execute(
            """
            UPDATE cloud_tts_jobs
            SET percentage = ?,
                message = ?,
                audio_url = CASE WHEN ? <> '' THEN ? ELSE audio_url END,
                status = ?,
                error_text = ?,
                updated_at = strftime('%s','now')
            WHERE progress_id = ?
            """,
            (int(percentage), message, audio_url, audio_url, status, error_text, progress_id),
        )
        conn.commit()
        conn.close()
        print(f"Progress updated: {percentage}% - {message}", file=sys.stderr)
    except Exception as e:
        print(f"Failed to update progress: {e}", file=sys.stderr)

def wait_for_readable(path, retries=20, delay=0.25):
    last_error = None
    for _ in range(retries):
        try:
            with open(path, "rb"):
                return
        except OSError as e:
            last_error = e
            time.sleep(delay)
    if last_error:
        raise last_error

def finalize_audio_file(source_path, target_path, retries=20, delay=0.25):
    last_error = None
    for _ in range(retries):
        try:
            os.replace(source_path, target_path)
            return
        except OSError as e:
            last_error = e
            time.sleep(delay)
    for _ in range(retries):
        try:
            shutil.copyfile(source_path, target_path)
            return
        except OSError as e:
            last_error = e
            time.sleep(delay)
    raise Exception(f"Failed to finalize audio file: {safe_message(last_error)}")

def find_ffmpeg():
    ffmpeg = shutil.which("ffmpeg")
    if ffmpeg:
        return ffmpeg
    for path in ["/usr/bin/ffmpeg", "/usr/local/bin/ffmpeg"]:
        if os.path.isfile(path):
            return path
    return None

def adjust_speed_with_ffmpeg(input_file, output_file, speed):
    ffmpeg_bin = find_ffmpeg()
    if not ffmpeg_bin:
        raise Exception("ffmpeg not found")
    command = [
        ffmpeg_bin,
        "-y",
        "-i",
        input_file,
        "-filter:a",
        f"atempo={speed}",
        "-vn",
        output_file,
    ]
    result = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    if result.returncode != 0:
        raise Exception(f"ffmpeg atempo failed: {safe_message(result.stderr[-500:])}")

def generate_tts(text_file, output_file, language, speed, progress_id):
    try:
        update_progress(progress_id, 10, "Reading text file...")

        # Read text from file
        with open(text_file, 'r', encoding='utf-8') as f:
            text = f.read()

        if not text.strip():
            raise Exception("Text file is empty")

        update_progress(progress_id, 20, "Initializing TTS engine...")
        update_progress(progress_id, 30, "Generating speech...")
        tts = gTTS(text=text, lang=language, slow=False)
        temp_file = output_file.replace('.mp3', '_temp.mp3')
        if abs(speed - 1.0) > 0.01:
            tts.save(temp_file)
            wait_for_readable(temp_file)
        else:
            tts.save(output_file)
            wait_for_readable(output_file)
        update_progress(progress_id, 70, "Adjusting audio speed...")
        if abs(speed - 1.0) > 0.01:
            try:
                adjust_speed_with_ffmpeg(temp_file, output_file, speed)
                wait_for_readable(output_file)
                try:
                    os.remove(temp_file)
                except OSError as e:
                    print(f"Warning: Could not delete temp file {os.path.basename(temp_file)}: {e}", file=sys.stderr)
            except Exception as e:
                print(f"Speed adjustment failed: {safe_message(e)}, using original speed", file=sys.stderr)
                wait_for_readable(temp_file)
                finalize_audio_file(temp_file, output_file)
        else:
            wait_for_readable(output_file)

        update_progress(progress_id, 90, "Finalizing...")

        # Get relative URL for the audio file
        audio_filename = os.path.basename(output_file)
        audio_url = f"tts_output/{audio_filename}"

        update_progress(progress_id, 100, "Completed", audio_url, "completed", "")

        print(f"TTS generation completed successfully! Audio saved to: {os.path.basename(output_file)}")

    except Exception as e:
        error_msg = f"Error: {safe_message(e)}"
        print(error_msg, file=sys.stderr)
        update_progress(progress_id, 0, error_msg, "", "error", error_msg)
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) != 6:
        print("Usage: python tts_generate.py <text_file> <output_file> <language> <speed> <progress_id>", file=sys.stderr)
        sys.exit(1)

    text_file = sys.argv[1]
    output_file = sys.argv[2]
    language = sys.argv[3]
    speed = float(sys.argv[4])
    progress_id = sys.argv[5]

    print(f"Starting TTS generation...", file=sys.stderr)
    print(f"Text file: {os.path.basename(text_file)}", file=sys.stderr)
    print(f"Output file: {os.path.basename(output_file)}", file=sys.stderr)
    print(f"Language: {language}", file=sys.stderr)
    print(f"Speed: {speed}", file=sys.stderr)
    print(f"Progress ID: {progress_id}", file=sys.stderr)

    generate_tts(text_file, output_file, language, speed, progress_id)
