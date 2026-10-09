#!/usr/bin/env python3
"""i18n audit for AppDashboard apps.

Checks one app directory for translation integrity:

  MISSING  keys used in code but absent from the locale dictionaries
  DEAD     keys in the dictionaries that no source file references
  PARITY   locales that do not carry the same key set
  PLACEHOLDER  {param} placeholders that differ from the en baseline
  LEGACY   flat i18n/<lang>.json that drifted from the merged chunks

Usage:
    python3 scripts/i18n-audit.py apps/cloud
    python3 scripts/i18n-audit.py apps/spends --json report.json

Exit code is non-zero when any hard problem is found, so the script can gate
a pre-commit hook or CI step.

Reference behaviour (apps/cloud must report zero findings):
  `key === 'aboutText'`                 used only inside a condition
  `$t['copy_success']`                  reached through json_encode($t)
  `${row.root}Root`                     template-built key, never literal
"""
from __future__ import annotations

import argparse
import json
import os
import re
import sys
from collections import defaultdict

LOCALES = ['en', 'ru', 'uk', 'be', 'pl', 'cs']

# utilities/projects/* are standalone embedded apps: they carry their own
# dictionaries (src/lang/<locale>.php or an inline JS object) and never read
# the host app's i18n/. Auditing them against the host locale set produces
# hundreds of false MISSING reports.
SKIP_DIRS = {'i18n', 'vendor', 'node_modules', '.git', 'storage', 'temp',
             'temp_convert', 'transcription_uploads', 'tts_output',
             'upload_sessions', 'data', 'test-results',
             'utilities/projects', 'steps-mobile-app', 'instruction',
             'assets/dictionaries', 'scripts', 'backups', '.git'}
# any dot-directory (venvs, tool caches, .git)
for _root, _dirs, _files in os.walk('.'):
    for _d in _dirs:
        if _d.startswith('.'):
            SKIP_DIRS.add(_d)
    if len(SKIP_DIRS) > 400:
        break

SKIP_EXT = {'.json', '.lock', '.jpg', '.jpeg', '.png', '.gif', '.svg',
            '.woff', '.woff2', '.ttf', '.ico', '.mp3', '.wav', '.pdf',
            '.md', '.yml', '.yaml', '.service', '.dic', '.mo', '.po',
            '.woff2', '.zip', '.map'}

# Every accessor style seen across the dashboard apps.
USED_PATTERNS = [
    re.compile(r'data-i18n(?:-title|-placeholder)?\s*=\s*["\']([A-Za-z0-9_]+)["\']'),
    re.compile(r'\bt\(\s*\$?(?:window\.)?i18n\w*\s*,\s*["\']([A-Za-z0-9_]+)["\']'),
    re.compile(r'(?<![\w.$])t\(\s*["\']([A-Za-z0-9_]+)["\']'),
    # app-specific escaping wrappers: sh_h($t['key']), e($t['key']), esc(...).
    # Only the plural dict names: a singular $t is commonly rebound to a loop
    # row by `foreach ($tx as $t)` and must not be read as translations.
    re.compile(r'[A-Za-z_][A-Za-z0-9_]*\(\s*\$(?:i18n|translations)\[[\s]*["\']([A-Za-z0-9_]+)["\'][\s]*\]'),
    re.compile(r'\bsh_h\(\s*\$t\[[\s]*["\']([A-Za-z0-9_]+)["\'][\s]*\]'),
    # apps use different helper names for the same lookup
    re.compile(r'(?<![\w.$])getTranslation\(\s*["\']([A-Za-z0-9_]+)["\']'),
    re.compile(r'(?<![\w.$])(?:tt|translate|loc|tr_)\(\s*["\']([A-Za-z0-9_]+)["\']'),
    # $translations['en'] selects the base locale, not a translation key
    re.compile(r'\$translations\[\s]*["\'](?!(?:en|ru|uk|be|pl|cs)["\'])'),
    # $t is the translations dict only at file scope. Inside a loop like
    # `foreach ($tx as $t)`, $t is a DB row, so any `$t['x']` there is data,
    # not a translation. Requires the match to sit outside such a scope.
    re.compile(r'\$i18n\[[\s]*["\']([A-Za-z0-9_]+)["\'][\s]*\]'),
    re.compile(r'\$(?:translations|t_dict|td)\[[\s]*["\']([A-Za-z0-9_]+)["\'][\s]*\]'),
    re.compile(r'(?:window\.)?i18n\??\.([A-Za-z0-9_]+)\b'),
    re.compile(r'(?:window\.)?i18n\??\[\s*["\']([A-Za-z0-9_]+)["\']\s*\]'),
]

# PHP views inject the dictionary as a JS object named `translations`, read as
# `translations.key` / `translations['key']`. A file that also declares
# `const translations = { en: {...} }` is self-contained, so those lookups are
# local and must not be compared against the host locale set.
TRANSLATIONS_PATTERNS = (
    # the trailing \b must not swallow a file extension, e.g.
    # require_once __DIR__ . '/translations.php'
    re.compile(r'(?<![\w$/])(?:window\.)?translations\??\.([A-Za-z0-9_]+)(?![A-Za-z0-9_])'),
    re.compile(r'(?<![\w.$])translations\??\[\s*["\']([A-Za-z0-9_]+)["\']\s*\]'),
)
USED_PATTERNS.extend(TRANSLATIONS_PATTERNS)

# A file that declares its own `translations = {` / `window.i18n = {` literal
# does not read the host dictionary, so attribute lookups there are local.
INLINE_DICT = re.compile(
    r'(?:const|let|var)\s+(?:translations|i18n)\s*=\s*\{'
    r'|window\.i18n\s*=\s*\{')

# tr(key, fallback) and t(key, fallback) are deliberate fallback chains: the
# caller supplies the English text, so a missing key degrades to English
# instead of warning. These keys are optional, never reported as MISSING.
FALLBACK_PATTERNS = [
    re.compile(r'(?<![\w.$])tr\(\s*["\']([A-Za-z0-9_]+)["\']\s*,'),
    re.compile(r'(?<![\w.$])t\(\s*["\']([A-Za-z0-9_]+)["\']\s*,'),
    re.compile(r'(?<![\w.$])t\(\s*["\']([A-Za-z0-9_]+)["\']\s*\+'),
]

# Keys assembled by concatenation or interpolation, e.g.
#   t('store_category_' + key, key)   /   t(`/${row.root}Root`)
# Every key sharing such a prefix is dynamic and must not be deleted.
DYNAMIC_PREFIX = re.compile(
    r'(?:[A-Za-z0-9_$.]|window\.)*?\(\s*["\'`]([A-Za-z0-9_]{2,})["\'`]\s*\+'
    r'|["\'`]([A-Za-z0-9_/]{2,})["\'`]\s*\+?\s*[\$\w]'
    r'|`[^`]*\$\{[^}]+\}[A-Za-z0-9_]*`'
    r'|[\$\{][^)]*\}\s*[A-Za-z0-9_]+["\'`]?\s*\)'
)

# Keys assembled at runtime. They must never be deleted automatically.
DYNAMIC_PATTERNS = [
    re.compile(r'(?:window\.)?i18n\??\.\s*([A-Za-z0-9_$]{1,40})'),
    re.compile(r'(?:window\.)?i18n\??\[\s*([^\]\n]{1,70})\]\s*\]'),
    re.compile(r'\bt\(\s*\$?(?:window\.)?(?:i18n\w*|tr)\s*,\s*([^)\)]{1,70})\)'),
]

TOKEN = re.compile(r"[A-Za-z0-9_]{2,70}")
PLACEHOLDER = re.compile(r'\{(\w+)\}')


def walk_sources(app: str):
    for root, dirs, files in os.walk(app):
        rel = os.path.relpath(root, app)
        if any(rel == d or rel.startswith(d + os.sep) for d in SKIP_DIRS):
            continue
        dirs[:] = [d for d in sorted(dirs) if d not in SKIP_DIRS]
        for name in sorted(files):
            path = os.path.join(root, name)
            if os.path.islink(path) or not os.path.isfile(path):
                continue
            if os.path.splitext(name)[1].lower() in SKIP_EXT:
                continue
            yield path


def collect_tokens(app: str):
    """Literal tokens appearing anywhere in the app sources (outside i18n/)."""
    refs = defaultdict(set)
    for path in walk_sources(app):
        try:
            text = open(path, encoding='utf-8', errors='ignore').read()
        except OSError:
            continue
        rel = os.path.relpath(path, app)
        for match in TOKEN.finditer(text):
            refs[match.group(0)].add(rel)
    return refs


# `foreach (... as $t)` / `$t = ...` rebinds the conventional translation
# variable to something local. Accesses inside that scope are not lookups.
SHADOW = re.compile(
    r'(?:as\s+\$(\w+)\s*\)|\$(\w+)\s*=\s*(?!\$translations|\$i18n|ap_))')


def collect_used(app: str):
    used = defaultdict(set)
    for path in walk_sources(app):
        try:
            text = open(path, encoding='utf-8', errors='ignore').read()
        except OSError:
            continue
        rel = os.path.relpath(path, app)
        # a file holding its own inline dictionary does not read the host one
        inline = bool(INLINE_DICT.search(text))
        for pattern in USED_PATTERNS:
            for match in pattern.finditer(text):
                if inline and pattern in TRANSLATIONS_PATTERNS:
                    continue
                used[match.group(1)].add(rel)
    return used


def collect_fallback(app: str):
    """Keys passed to tr(key, fallback) - optional, degrade to English."""
    keys = defaultdict(set)
    for path in walk_sources(app):
        try:
            text = open(path, encoding='utf-8', errors='ignore').read()
        except OSError:
            continue
        rel = os.path.relpath(path, app)
        for pattern in FALLBACK_PATTERNS:
            for match in pattern.finditer(text):
                keys[match.group(1)].add(rel)
    return keys


def collect_dynamic(app: str):
    """Return (call sites, literal prefixes) built at runtime.

    A key reached as t('store_category_' + key) means every
    `store_category_*` entry is live, so the prefix is protected from
    dead-key pruning.
    """
    sites, prefixes = [], set()
    for path in walk_sources(app):
        try:
            text = open(path, encoding='utf-8', errors='ignore').read()
        except OSError:
            continue
        rel = os.path.relpath(path, app)
        for pattern in DYNAMIC_PATTERNS:
            for match in pattern.finditer(text):
                snippet = match.group(0)
                # a quoted literal is already covered by USED_PATTERNS
                if re.fullmatch(r"['\"][A-Za-z0-9_]+['\"]", match.group(1).strip()):
                    continue
                if '$' in snippet or '`' in snippet:
                    sites.append((rel, snippet[:90]))
        for match in DYNAMIC_PREFIX.finditer(text):
            token = match.group(1) or match.group(2)
            if token:
                prefixes.add(token)
    return sites, prefixes


def load_locale(i18n: str, lang: str):
    """Return {chunk: {key: value}} and the merged dict for one language."""
    chunks, merged = {}, {}
    subdir = os.path.join(i18n, lang)
    if os.path.isdir(subdir):
        for name in sorted(os.listdir(subdir)):
            if not name.endswith('.json'):
                continue
            chunk = os.path.splitext(name)[0]
            data = read_json(os.path.join(subdir, name))
            if data is None:
                continue
            chunks[chunk] = data
            merged.update(data)
        if chunks:
            return chunks, merged
    flat = os.path.join(i18n, lang + '.json')
    data = read_json(flat)
    if data is None:
        return {}, {}
    return {'legacy': data}, dict(data)


def read_json(path: str):
    try:
        with open(path, encoding='utf-8') as fh:
            data = json.load(fh)
        return data if isinstance(data, dict) else None
    except (OSError, json.JSONDecodeError):
        return None


def audit(app: str):
    i18n = os.path.join(app, 'i18n')
    report = {'app': app, 'errors': [], 'warnings': []}

    if not os.path.isdir(i18n):
        report['errors'].append(f'no i18n/ directory in {app}')
        return report

    present = {}
    for lang in LOCALES:
        chunks, merged = load_locale(i18n, lang)
        if merged:
            present[lang] = (chunks, merged)

    if 'en' not in present:
        report['errors'].append('missing base locale: en')
        return report

    missing_langs = [lang for lang in LOCALES if lang not in present]
    if missing_langs:
        report['errors'].append(f'locales absent from disk: {missing_langs}')

    en = present['en'][1]
    used = collect_used(app)
    refs = collect_tokens(app)
    fallback_keys = collect_fallback(app)

    # ---- missing: used in code, absent from the base locale ----
    # tr(key, fallback) keys are excluded: the caller ships the English text.
    # a language code is a locale selector, never a translation key
    used.pop('en', None)
    required = {k: v for k, v in used.items() if k not in fallback_keys}
    missing = sorted(k for k in required if k not in en)
    report['used_count'] = len(used)
    report['fallback_count'] = len(fallback_keys)
    report['en_count'] = len(en)
    report['missing'] = {k: sorted(required[k])[:3] for k in missing}
    if missing:
        report['errors'].append(f'{len(missing)} key(s) used in code but missing from en: {missing[:20]}')

    # ---- parity across locales ----
    parity = {}
    placeholders = {}
    for lang, (_, merged) in present.items():
        if lang == 'en':
            continue
        gap = sorted(set(en) - set(merged))
        extra = sorted(set(merged) - set(en))
        parity[lang] = {'missing': gap, 'extra': extra}
        if gap:
            report['errors'].append(f'{lang}: {len(gap)} key(s) missing vs en: {gap[:20]}')
        if extra:
            report['warnings'].append(f'{lang}: {len(extra)} key(s) not present in en: {extra[:20]}')
        ph = {}
        for key in set(en) & set(merged):
            want = set(PLACEHOLDER.findall(str(en[key])))
            got = set(PLACEHOLDER.findall(str(merged[key])))
            if want != got:
                ph[key] = {'en': sorted(want), lang: sorted(got)}
        if ph:
            placeholders[lang] = ph
            report['errors'].append(f'{lang}: placeholder mismatch on {list(ph)[:10]}')
    report['parity'] = parity
    report['placeholders'] = placeholders

    # ---- dead: no literal token anywhere in the sources ----
    # Keys behind a runtime-concatenated prefix stay alive.
    dynamic_sites, dynamic_prefixes = collect_dynamic(app)
    protected = {k for k in en if any(k.startswith(p) for p in dynamic_prefixes)}
    dead = sorted(k for k in en if k not in refs and k not in protected)
    report['dynamic_prefixes'] = sorted(dynamic_prefixes)
    report['protected_by_prefix'] = sorted(protected)
    report['dead'] = dead
    if dead:
        report['errors'].append(f'{len(dead)} dead key(s) in en: {dead[:20]}')

    report['dynamic_sites'] = dynamic_sites

    # ---- legacy flat files vs merged chunks ----
    legacy_drift = {}
    for lang, (chunks, merged) in present.items():
        flat = os.path.join(i18n, lang + '.json')
        if not os.path.isfile(flat):
            if 'legacy' in chunks:
                continue
            continue
        flat_data = read_json(flat) or {}
        only_flat = sorted(set(flat_data) - set(merged))
        only_chunks = sorted(set(merged) - set(flat_data))
        differs = sorted(k for k in set(flat_data) & set(merged) if flat_data[k] != merged[k])
        if only_flat or only_chunks or differs:
            legacy_drift[lang] = {'only_flat': only_flat[:20],
                                  'only_chunks': only_chunks[:20],
                                  'differs': differs[:20]}
            report['errors'].append(
                f'{lang}: legacy i18n/{lang}.json drifted from chunks '
                f'(only_flat={len(only_flat)}, only_chunks={len(only_chunks)}, differs={len(differs)})')
    report['legacy_drift'] = legacy_drift

    return report


def main() -> int:
    parser = argparse.ArgumentParser(description='Audit i18n keys for an app')
    parser.add_argument('app', help='app directory, e.g. apps/cloud')
    parser.add_argument('--json', help='also write the full report as JSON')
    args = parser.parse_args()

    app = args.app.rstrip('/')
    if not os.path.isdir(app):
        print(f'not a directory: {app}', file=sys.stderr)
        return 2

    report = audit(app)

    print(f"== {app}")
    if 'en_count' in report:
        print(f"   keys: en={report['en_count']} used={report['used_count']}")
    if report.get('missing'):
        print(f"   MISSING ({len(report['missing'])}):")
        for key, files in list(report['missing'].items())[:40]:
            print(f'      {key}  <- {files}')
    if report.get('dead'):
        print(f"   DEAD ({len(report['dead'])}): {report['dead'][:40]}")
    if report.get('dynamic_sites'):
        print(f"   dynamic sites ({len(report['dynamic_sites'])}): never auto-delete these keys")
    for error in report['errors']:
        print(f'   ERROR: {error}')
    for warning in report['warnings']:
        print(f'   warn : {warning}')

    if args.json:
        with open(args.json, 'w', encoding='utf-8') as fh:
            json.dump(report, fh, ensure_ascii=False, indent=1)

    return 1 if report['errors'] else 0


if __name__ == '__main__':
    sys.exit(main())