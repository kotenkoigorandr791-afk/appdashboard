#!/usr/bin/env python3
import glob
import os
import shutil
import subprocess
import sys
VIDEO_DEFAULT = '/var/www/AppDashboard/apps/steps/screen-20260503-092204-1777789309102.mp4'
OUT_DEFAULT = '/var/www/AppDashboard/apps/steps/ocr_diagnostic_report.md'


def main() -> int:
    vid = sys.argv[1] if len(sys.argv) > 1 else VIDEO_DEFAULT
    out_path = sys.argv[2] if len(sys.argv) > 2 else OUT_DEFAULT
    if not os.path.isfile(vid):
        print('no video', vid, file=sys.stderr)
        return 2
    here = os.path.dirname(os.path.abspath(__file__))
    sys.path.insert(0, here)
    import video_steps_worker as vsw
    ffmpeg = shutil.which('ffmpeg') or 'ffmpeg'
    tessbin = shutil.which('tesseract') or 'tesseract'
    tessdata = os.environ.get('TESSDATA_PREFIX', '').rstrip('/') or '/usr/share/tesseract-ocr/5/tessdata'
    td = tessdata.replace('/tessdata', '').rstrip('/')
    cand = ['/usr/share/tesseract-ocr/5/tessdata', '/usr/share/tesseract-ocr/4/tessdata', td + '/tessdata']
    tessdata_dir = next((d for d in cand if os.path.isdir(d)), cand[0])
    year = 2026
    ocr_lang = os.environ.get('OCR_DIAG_LANG', 'eng')
    fps = float(os.environ.get('OCR_DIAG_FPS', '4'))
    max_frames = int(os.environ.get('OCR_DIAG_MAX_FRAMES', '0'))
    mh = int(os.environ.get('OCR_DIAG_SCALE_H', '960'))
    work = os.path.join(os.path.dirname(out_path) or '.', '_diag_work')
    shutil.rmtree(work, ignore_errors=True)
    os.makedirs(work, exist_ok=True)
    pat = os.path.join(work, 'f_%05d.png')
    vf = f'fps={fps},scale=-2:{mh}:flags=lanczos'
    r = subprocess.run(
        [ffmpeg, '-y', '-nostdin', '-hide_banner', '-loglevel', 'error', '-i', vid, '-an', '-sn', '-vsync', 'vfr', '-vf', vf, '-qscale:v', '2', pat],
        capture_output=True,
        text=True,
        timeout=120,
    )
    if r.returncode != 0:
        open(out_path, 'w', encoding='utf-8').write('# OCR diagnostic\n\nffmpeg failed:\n' + (r.stderr or '')[:2000])
        return 1
    fns = sorted(glob.glob(os.path.join(work, 'f_*.png')))
    rows = []
    for i, p in enumerate(fns):
        if max_frames > 0 and i >= max_frames:
            break
        t_sec = round(i / fps, 3)
        try:
            raw = subprocess.run(
                [tessbin, p, 'stdout', '--tessdata-dir', tessdata_dir, '-l', ocr_lang, '--oem', '1', '--psm', '6'],
                capture_output=True,
                text=True,
                timeout=120,
            ).stdout or ''
        except Exception as e:
            raw = f'[tesseract error: {e}]'
        mega = vsw.clean_ocr(raw)
        st = vsw.find_steps_data(mega) if mega else None
        dt = vsw.find_date_data(mega, year) if mega else None
        rows.append({
            't_display': vsw._fmt_ts(t_sec),
            'steps': st,
            'date': dt,
            'raw_len': len(mega or ''),
            'raw_excerpt': (mega or '')[:500].replace('\n', ' ').strip(),
        })
    nf = len(fns)
    n_proc = len(rows)
    lines = [
        '# Отчёт OCR (FFmpeg + tesseract CLI, без OpenCV)',
        '',
        f'**Видео:** `{vid}`',
        f'**FFmpeg:** `{ffmpeg}`',
        f'**Tesseract:** `{tessbin}`',
        f'**tessdata:** `{tessdata_dir}`',
        f'**Язык:** `{ocr_lang}`',
        f'**fps:** {fps}, **max высота кадра:** {mh}px',
        f'**Кадров извлечено:** {nf}, **в отчёте строк:** {n_proc}' + (f' (лимит OCR_DIAG_MAX_FRAMES={max_frames})' if max_frames > 0 else ''),
        '',
        '## Сводка',
        f'- Кадров с распознанными **шагами** (find_steps_data): **{sum(1 for r in rows if r.get("steps") is not None)}**',
        f'- Кадров с **датой** (find_date_data): **{sum(1 for r in rows if r.get("date"))}**',
        f'- Кадров с пустым текстом после clean_ocr: **{sum(1 for r in rows if not (r.get("raw_excerpt") or "").strip())}**',
        '',
        '## По кадрам',
        '',
        '| t | steps | date | len | фрагмент raw |',
        '|---|-------|------|-----|--------------|',
    ]
    for r in rows:
        frag = (r.get('raw_excerpt') or '')[:140].replace('|', '/')
        lines.append(
            f"| {r['t_display']} | {r.get('steps')!s} | {r.get('date') or '—'} | {r['raw_len']} | {frag} |"
        )
    lines.extend([
        '',
        '## Замечание',
        '',
        '`find_steps_data` / `find_date_data` те же функции, что использует видеоворкер после OCR. Извлечение кадра упрощено (масштаб + один режим `--psm 6` у tesseract без цепочки препроцессора OpenCV), поэтому цифры могут немного отличаться от пайплайна в `video_steps_worker.py`.',
    ])
    os.makedirs(os.path.dirname(out_path) or '.', exist_ok=True)
    with open(out_path, 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines))
    shutil.rmtree(work, ignore_errors=True)
    print(out_path, flush=True)
    return 0


if __name__ == '__main__':
    sys.exit(main())
