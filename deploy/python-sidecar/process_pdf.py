#!/usr/bin/env python3
import re, sys
from PIL import Image, ImageDraw, ImageFont
src, out, font_path = sys.argv[1], sys.argv[2], sys.argv[3]
markdown = open(src, 'r', encoding='utf-8', errors='replace').read().replace('\r\n', '\n').replace('\r', '\n')
bold_path = font_path.replace('DejaVuSans.ttf', 'DejaVuSans-Bold.ttf')
mono_path = font_path.replace('DejaVuSans.ttf', 'DejaVuSansMono.ttf')
font = ImageFont.truetype(font_path, 28)
bold = ImageFont.truetype(bold_path, 28)
mono = ImageFont.truetype(mono_path, 24)
fonts = {
    'p': font,
    'bold': bold,
    'h1': ImageFont.truetype(bold_path, 46),
    'h2': ImageFont.truetype(bold_path, 38),
    'h3': ImageFont.truetype(bold_path, 32),
    'code': mono,
}
width, height = 1240, 1754
margin = 90
max_w = width - margin * 2
pages = []
img = Image.new('RGB', (width, height), 'white')
draw = ImageDraw.Draw(img)
y = margin
def text_width(s, f):
    return draw.textlength(s, font=f)
def strip_inline(s):
    s = re.sub(r'!\[([^\]]*)\]\([^)]+\)', r'\1', s)
    s = re.sub(r'\[([^\]]+)\]\([^)]+\)', r'\1', s)
    s = re.sub(r'`([^`]*)`', r'\1', s)
    s = re.sub(r'(\*\*|__)(.*?)\1', r'\2', s)
    s = re.sub(r'(\*|_)(.*?)\1', r'\2', s)
    s = re.sub(r'~~(.*?)~~', r'\1', s)
    return s
def wrap_line(line, f, max_width=max_w):
    line = strip_inline(line).strip()
    if not line:
        return ['']
    words = line.split()
    out = []
    cur = ''
    for word in words:
        cand = word if not cur else cur + ' ' + word
        if text_width(cand, f) <= max_width:
            cur = cand
            continue
        if cur:
            out.append(cur)
        cur = word
        while cur and text_width(cur, f) > max_width:
            lo, hi = 1, len(cur)
            while lo < hi:
                mid = (lo + hi + 1) // 2
                if text_width(cur[:mid], f) <= max_width:
                    lo = mid
                else:
                    hi = mid - 1
            out.append(cur[:lo])
            cur = cur[lo:]
    if cur:
        out.append(cur)
    return out
def new_page():
    global img, draw, y
    pages.append(img)
    img = Image.new('RGB', (width, height), 'white')
    draw = ImageDraw.Draw(img)
    y = margin
def ensure(h):
    if y + h > height - margin:
        new_page()
def draw_lines(lines, f, x=margin, fill=(20, 20, 20), line_h=None, before=0, after=12):
    global y
    if line_h is None:
        line_h = int(f.size * 1.45)
    if before:
        ensure(before)
        y += before
    for line in lines:
        ensure(line_h)
        draw.text((x, y), line, fill=fill, font=f)
        y += line_h
    y += after
def parse_table_row(row):
    row = row.strip()
    if row.startswith('|'):
        row = row[1:]
    if row.endswith('|'):
        row = row[:-1]
    return [strip_inline(c.strip()) for c in row.split('|')]
def is_table_sep(line):
    return bool(re.match(r'^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$', line))
def draw_table(rows):
    global y
    if not rows:
        return
    cols = max(len(r) for r in rows)
    col_w = max_w / cols
    pad = 10
    for ri, row in enumerate(rows):
        f = fonts['bold'] if ri == 0 else fonts['p']
        wrapped = [wrap_line(row[ci] if ci < len(row) else '', f, int(col_w - pad * 2)) for ci in range(cols)]
        row_h = max(44, max(len(c) for c in wrapped) * int(f.size * 1.35) + pad * 2)
        ensure(row_h)
        if ri == 0:
            draw.rectangle([margin, y, margin + max_w, y + row_h], fill=(238, 240, 243))
        for ci, cell_lines in enumerate(wrapped):
            x0 = int(margin + ci * col_w)
            x1 = int(margin + (ci + 1) * col_w)
            draw.rectangle([x0, y, x1, y + row_h], outline=(150, 150, 150), width=2)
            ty = y + pad
            for line in cell_lines:
                draw.text((x0 + pad, ty), line, font=f, fill=(20, 20, 20))
                ty += int(f.size * 1.35)
        y += row_h
    y += 18
def flush_paragraph(buf):
    if buf:
        draw_lines(wrap_line(' '.join(buf), fonts['p']), fonts['p'], after=14)
lines = markdown.split('\n')
i = 0
para = []
in_code = False
code_lines = []
while i < len(lines):
    raw = lines[i]
    if raw.strip().startswith('```'):
        flush_paragraph(para)
        para = []
        if in_code:
            for code in code_lines or ['']:
                draw_lines(wrap_line(code, fonts['code'], max_w - 24), fonts['code'], x=margin + 12, fill=(45, 45, 45), line_h=34, after=0)
            y += 14
            code_lines = []
            in_code = False
        else:
            in_code = True
        i += 1
        continue
    if in_code:
        code_lines.append(raw)
        i += 1
        continue
    if raw.strip() == '':
        flush_paragraph(para)
        para = []
        y += 8
        i += 1
        continue
    if re.match(r'^\s{0,3}(-{3,}|\*{3,}|_{3,})\s*$', raw):
        flush_paragraph(para)
        para = []
        ensure(24)
        draw.line([margin, y + 10, margin + max_w, y + 10], fill=(180, 180, 180), width=2)
        y += 28
        i += 1
        continue
    if i + 1 < len(lines) and '|' in raw and is_table_sep(lines[i + 1]):
        flush_paragraph(para)
        para = []
        table = [parse_table_row(raw)]
        i += 2
        while i < len(lines) and '|' in lines[i] and lines[i].strip():
            table.append(parse_table_row(lines[i]))
            i += 1
        draw_table(table)
        continue
    m = re.match(r'^\s{0,3}(#{1,6})\s+(.+?)\s*#*\s*$', raw)
    if m:
        flush_paragraph(para)
        para = []
        level = len(m.group(1))
        f = fonts['h1'] if level == 1 else fonts['h2'] if level == 2 else fonts['h3']
        draw_lines(wrap_line(m.group(2), f), f, before=8 if y > margin else 0, after=16)
        i += 1
        continue
    m = re.match(r'^(\s*)([-*+])\s+(.+)$', raw)
    if m:
        flush_paragraph(para)
        para = []
        indent = min(90, len(m.group(1)) * 12)
        wrapped = wrap_line('• ' + m.group(3), fonts['p'], max_w - indent)
        draw_lines(wrapped, fonts['p'], x=margin + indent, after=2)
        i += 1
        continue
    m = re.match(r'^(\s*)(\d+)[.)]\s+(.+)$', raw)
    if m:
        flush_paragraph(para)
        para = []
        indent = min(90, len(m.group(1)) * 12)
        wrapped = wrap_line(m.group(2) + '. ' + m.group(3), fonts['p'], max_w - indent)
        draw_lines(wrapped, fonts['p'], x=margin + indent, after=2)
        i += 1
        continue
    m = re.match(r'^\s{0,3}>\s?(.*)$', raw)
    if m:
        flush_paragraph(para)
        para = []
        wrapped = wrap_line(m.group(1), fonts['p'], max_w - 30)
        draw.line([margin, y, margin, y + max(40, len(wrapped) * 40)], fill=(160, 160, 160), width=4)
        draw_lines(wrapped, fonts['p'], x=margin + 22, fill=(70, 70, 70), after=10)
        i += 1
        continue
    para.append(raw.strip())
    i += 1
flush_paragraph(para)
if in_code and code_lines:
    for code in code_lines:
        draw_lines(wrap_line(code, fonts['code'], max_w - 24), fonts['code'], x=margin + 12, fill=(45, 45, 45), line_h=34, after=0)
if not pages or y > margin:
    pages.append(img)
pages[0].save(out, 'PDF', resolution=150, save_all=True, append_images=pages[1:])
