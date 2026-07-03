import argparse
import glob
import json
import os
import shutil
import sys
import tempfile
import time


def main() -> int:
    pa = argparse.ArgumentParser(description='Diagnostic OCR report for steps video.')
    pa.add_argument('video', help='Path to mp4/webm...')
    pa.add_argument('--out', '-o', default='', help='Write report to this UTF-8 file')
    pa.add_argument('--fps', type=float, default=4.0)
    pa.add_argument('--year', type=int, default=2026)
    pa.add_argument('--lang', default='eng')
    args = pa.parse_args()
    vd = os.path.abspath(args.video)
    if not os.path.isfile(vd):
        print('missing file', vd, file=sys.stderr)
        return 2

    ffmpeg = shutil.which('ffmpeg') or '/usr/bin/ffmpeg'
    tess = shutil.which('tesseract') or '/usr/bin/tesseract'
    td = '/usr/share/tesseract-ocr/5/tessdata'
    if not os.path.isdir(td):
        tp = os.environ.get('TESSDATA_PREFIX')
        if tp:
            cand = os.path.join(tp.strip('/').rstrip('\\'), 'tessdata') if tp else ''
            if cand and os.path.isdir(cand):
                td = cand

    jd = tempfile.mkdtemp(prefix='diag_steps_')
    try:
        ext = '.mp4'
        b = os.path.basename(vd)
        e = os.path.splitext(b)[1].lower()
        if e in ('.webm', '.mkv', '.mov', '.m4v', '.mp4'):
            ext = e
        vin = os.path.join(jd, 'input' + ext)
        shutil.copy2(vd, vin)

        utils_dir = os.path.dirname(os.path.abspath(__file__))
        if utils_dir not in sys.path:
            sys.path.insert(0, utils_dir)
        import numpy as np
        import cv2
        import pytesseract as pt
        import video_steps_worker as vsw
        vsw.np = np
        vsw.cv2 = cv2
        vsw.pytesseract = pt
        pt.pytesseract.tesseract_cmd = tess

        from video_steps_worker import (
            _build_ocr_preview,
            _extras_from_steps_only,
            _ffprobe_duration,
            _ffprobe_video_size,
            _step_only_plateau,
            extract_frames,
            find_date_data,
            find_steps_data,
            clean_ocr,
            _ocr_vote_from_bgr,
            _fmt_ts,
        )

        sz = _ffprobe_video_size(ffmpeg, vin) or (-1, -1)
        dur = _ffprobe_duration(ffmpeg, vin)
        crop = None
        mild_eq = False
        pat = os.path.join(jd, 'frames', 'f_%05d.png')
        os.makedirs(os.path.dirname(pat), exist_ok=True)
        t0 = time.time()
        if not extract_frames(ffmpeg, vin, pat, crop, args.fps, None, None, mild_eq):
            body = '# Steps OCR diagnostic report\n\n**ffmpeg extract_frames returned false**\n'
            print(body)
            if args.out:
                with open(args.out, 'w', encoding='utf-8') as f:
                    f.write(body)
            return 3
        fns = sorted(f for f in os.listdir(os.path.dirname(pat)) if f.endswith('.png'))
        fps = args.fps
        inv = 1.0 / fps
        lines = []
        lines.append('# Steps OCR diagnostic report')
        lines.append('')
        lines.append('| Field | Value |')
        lines.append('| --- | --- |')
        lines.append('| Video | `{}` |'.format(vd))
        lines.append('| Resolution | {} x {} |'.format(sz[0], sz[1]))
        lines.append('| Duration (ffprobe), s | {} |'.format(round(dur, 3) if dur < 1e8 else 'n/a'))
        lines.append('| Frames extracted | {} |'.format(len(fns)))
        lines.append('| fps | {} |'.format(fps))
        lines.append('| tesseract | `{}` |'.format(tess))
        lines.append('| tessdata | `{}` |'.format(td))
        lines.append('| OCR language | `{}` |'.format(args.lang))
        lines.append('| current_year | {} |'.format(args.year))
        lines.append('| Elapsed extract+OCR | {:.1f} s |'.format(time.time() - t0))
        lines.append('')

        all_rows = []
        nonempty_mega = 0
        st_found = 0
        dt_found = 0
        for i, fn in enumerate(fns):
            p = os.path.join(os.path.dirname(pat), fn)
            t_sec = round(i * inv, 4)
            bgr = cv2.imread(p, cv2.IMREAD_COLOR)
            if bgr is None:
                continue
            stv, dtv, mega = _ocr_vote_from_bgr(bgr, args.year, args.lang, td, np, cv2, pt)
            cm = clean_ocr(mega) if mega else ''
            if cm.strip():
                nonempty_mega += 1
            if stv is not None:
                st_found += 1
            if dtv is not None:
                dt_found += 1
            fs = find_steps_data(cm)
            fd = find_date_data(cm, args.year)
            all_rows.append({
                'frame': fn, 't_sec': t_sec, 't_display': _fmt_ts(t_sec),
                'vote_steps': stv, 'vote_date': dtv,
                'reparse_steps': fs, 'reparse_date': fd,
                'raw_len': len(mega or ''),
                'raw_excerpt': (mega or '')[:900],
                'mega_full': mega or '',
            })

        fake_all = {}
        for r in all_rows:
            fake_all[r['frame']] = {
                'raw': r.get('mega_full'),
                'steps': r['vote_steps'],
                'date': r['vote_date'],
                't_sec': r['t_sec'],
            }
        prv = _build_ocr_preview(fake_all, limit=999, raw_max=500)

        lines.append('## Summary')
        lines.append('')
        lines.append('- Frames OCR non-empty aggregated text (mega): **{}** / {}'.format(nonempty_mega, len(all_rows)))
        lines.append('- Frames with vote **steps**: **{}**'.format(st_found))
        lines.append('- Frames with vote **date**: **{}**'.format(dt_found))
        uniq_st = sorted({r['vote_steps'] for r in all_rows if r['vote_steps'] is not None})
        uniq_rp = sorted({r['reparse_steps'] for r in all_rows if r['reparse_steps'] is not None})
        lines.append('- Distinct voted step values: `{}`'.format(uniq_st))
        lines.append('- Distinct find_steps_data on excerpt: `{}`'.format(uniq_rp))
        lines.append('')

        merged_lines = ['## Merge simulation (like worker `_ocr_one_file`)', '']
        if all_rows:
            carry = None
            so = []
            ds = {}
            dp = {}
            ft = {}
            for r in all_rows:
                st = r['vote_steps']
                dt = r['vote_date']
                if dt:
                    carry = dt
                ed = dt or carry
                if st is not None and ed:
                    ds.setdefault(ed, []).append(st)
                    dp.setdefault(ed, []).append((st, float(r['t_sec'])))
                    ft[ed] = min(ft.get(ed, 1e18), float(r['t_sec']))
                elif st is not None:
                    so.append((st, float(r['t_sec'])))
            merged_lines.append('- **date_steps** bucket keys: {}'.format(json.dumps(list(ds.keys()), ensure_ascii=False) if ds else '(none)'))
            merged_lines.append('- **step_only_reads** count: {}'.format(len(so)))
            if so:
                ex = _extras_from_steps_only(args.year, _step_only_plateau(so), set())
                merged_lines.append('- synthetic rows from step_only plateau (JSON): {}'.format(json.dumps(ex, ensure_ascii=False)[:4000]))
            merged_lines.append('')
        lines.extend(merged_lines)

        lines.append('## Per-frame (all {})'.format(len(all_rows)))
        lines.append('')
        for r in all_rows:
            lines.append('### {} @ {}'.format(r['frame'], r['t_display']))
            lines.append('- vote_steps: `{}` vote_date: `{}`'.format(r['vote_steps'], r['vote_date']))
            lines.append('- re-parse mega: steps `{}` date `{}`'.format(r['reparse_steps'], r['reparse_date']))
            lines.append('')
            lines.append('```')
            lines.append((r['raw_excerpt'] or '(empty)').replace('```', "'''"))
            lines.append('```')
            lines.append('')

        lines.append('## OCR preview sampling (same as UI layout)')
        lines.append('')
        for it in prv:
            lines.append('- **{}** · date `{}` · steps `{}`'.format(it.get('t_display'), it.get('date'), it.get('steps')))
            rex = str(it.get('raw') or '')[:400].replace('\n', ' ')
            if rex.strip():
                lines.append('  - raw: `{}`'.format(rex.replace('`', "'")))

        out = '\n'.join(lines)
        print(out)
        if args.out:
            with open(args.out, 'w', encoding='utf-8') as f:
                f.write(out)
    finally:
        shutil.rmtree(jd, ignore_errors=True)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
