#!/usr/bin/env python3
import os
import re
import sys
import json
import time
import logging
import subprocess
import socket
import hashlib
import tempfile
from datetime import datetime, timezone
from typing import Optional
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'lib'))
import pg_sqlite_compat as sqlite3
import psycopg2

import bcrypt
import requests

DB_PATH = '/opt/AppDashboard/data/db.env'
POLL_TIMEOUT = 30
POLL_INTERVAL = 2
TOKEN_CACHE_TTL = 60
LOG_FILE = os.environ.get('BOT_LOG_FILE', '/var/log/bot/bot.log')
BOT_SERVICE_PHP = os.path.join(os.path.dirname(__file__), 'bot_service.php')

_token_cache: dict = {'value': '', 'ts': 0}

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s %(levelname)s %(message)s',
    handlers=[
        logging.FileHandler(LOG_FILE),
        logging.StreamHandler(sys.stdout),
    ]
)
log = logging.getLogger(__name__)

_state: dict = {}

LABEL_TO_CMD = {
    'monitoring': '/menu_monitoring',
    'errors': '/menu_errors',
    'users': '/menu_users',
    'database': '/menu_database',
    'back to menu': '/menu_back_main',
    'back to users': '/menu_back_users',
    'full statistics': '/stats',
    'quick status': '/status',
    'uptime charts': '/uptime_charts',
    'uptime графики': '/uptime_charts',
    'графики uptime': '/uptime_charts',
    'графики аптайма': '/uptime_charts',
    'графики мониторинга': '/uptime_charts',
    '💾 backup': '/backup_status',
    '🐳 docker': '/docker_status',
    'latest errors': '/errors_recent',
    'top errors': '/errors_top',
    'users list': '/users_list',
    'find user': '/user_find',
    'add user': '/user_add',
    'change role': '/user_change_role',
    'toggle verify': '/user_toggle_verify',
    'reset password': '/user_reset_password',
    'delete user': '/user_delete',
    'db statistics': '/db_stats',
    'tables list': '/db_tables',
    'vacuum': '/db_vacuum',
    'role: guest': '/set_role_0',
    'role: manager': '/set_role_1',
    'role: admin': '/set_role_2',
    'verified: yes': '/set_verified_1',
    'verified: no': '/set_verified_0',
    'confirm delete': '/confirm_delete_user',
    'cancel': '/cancel',
    'help': '/help',
    'logout': '/logout',
    'status': '/status',
}


def _label_key(text: str) -> str:
    return re.sub(r'\s+', ' ', re.sub(r'[^\w/]+', ' ', text or '', flags=re.UNICODE)).strip().lower()


COMMAND_ALIASES = {k.strip().lower(): v for k, v in LABEL_TO_CMD.items()}
COMMAND_ALIASES.update({_label_key(k): v for k, v in LABEL_TO_CMD.items()})


def get_db():
    db = sqlite3.connect()
    return db


def ensure_schema() -> None:
    with get_db() as db:
        db.execute("""
            CREATE TABLE IF NOT EXISTS tg_bot_sessions (
                chat_id      TEXT PRIMARY KEY,
                email        TEXT NOT NULL,
                authorized_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
                telegram_user_id TEXT NOT NULL DEFAULT ''
            )
        """)
        db.commit()
        try:
            db.execute("ALTER TABLE tg_bot_sessions ADD COLUMN telegram_user_id TEXT NOT NULL DEFAULT ''")
        except psycopg2.Error:
            db.rollback()
        db.execute("""
            CREATE TABLE IF NOT EXISTS tg_bot_link_tokens (
                token_hash TEXT PRIMARY KEY,
                email TEXT NOT NULL,
                created_by TEXT NOT NULL DEFAULT '',
                created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
                expires_at INTEGER NOT NULL,
                used_at INTEGER,
                used_chat_id TEXT NOT NULL DEFAULT ''
            )
        """)
        db.commit()
        for sql in (
            "ALTER TABLE tg_bot_link_tokens ADD COLUMN created_by TEXT NOT NULL DEFAULT ''",
            "ALTER TABLE tg_bot_link_tokens ADD COLUMN used_at INTEGER",
            "ALTER TABLE tg_bot_link_tokens ADD COLUMN used_chat_id TEXT NOT NULL DEFAULT ''",
        ):
            try:
                db.execute(sql)
                db.commit()
            except psycopg2.Error:
                db.rollback()


def get_token() -> str:
    now = time.time()
    if now - _token_cache['ts'] < TOKEN_CACHE_TTL and _token_cache['value']:
        return _token_cache['value']
    try:
        with get_db() as db:
            row = db.execute("SELECT value FROM config WHERE key='tg_bot_token' LIMIT 1").fetchone()
            token = str(row['value']).strip() if row and row['value'] else ''
        _token_cache['value'] = token
        _token_cache['ts'] = now
        return token
    except Exception as e:
        log.error(f'get_token: {e}')
        return _token_cache['value']


def get_session(chat_id: str) -> Optional[str]:
    try:
        with get_db() as db:
            row = db.execute(
                "SELECT email FROM tg_bot_sessions WHERE chat_id=? LIMIT 1", (chat_id,)
            ).fetchone()
            return str(row['email']) if row else None
    except Exception:
        return None


def get_session_role(chat_id: str) -> Optional[int]:
    try:
        with get_db() as db:
            row = db.execute(
                """SELECT u.role FROM tg_bot_sessions s
                   INNER JOIN users u ON u.email = s.email
                   WHERE s.chat_id=? LIMIT 1""",
                (chat_id,),
            ).fetchone()
            return int(row['role']) if row else None
    except Exception:
        return None


def add_session(chat_id: str, email: str, telegram_user_id: str = '') -> None:
    with get_db() as db:
        db.execute(
            "INSERT OR REPLACE INTO tg_bot_sessions (chat_id, email, authorized_at, telegram_user_id) VALUES (?,?,?,?)",
            (chat_id, email, int(time.time()), telegram_user_id),
        )
        db.commit()


def redeem_link_token(raw_token: str, chat_id: str, telegram_user_id: Optional[int]) -> Optional[str]:
    normalized = re.sub(r'\s+', '', raw_token.strip().upper())
    if not re.fullmatch(r'[A-F0-9]{8}-?[A-F0-9]{8}', normalized):
        return None
    if '-' not in normalized:
        normalized = normalized[:8] + '-' + normalized[8:]
    token_hash = hashlib.sha256(normalized.encode('utf-8')).hexdigest()
    now = int(time.time())
    uid = str(telegram_user_id) if telegram_user_id is not None else ''
    try:
        with get_db() as db:
            row = db.execute(
                """SELECT t.email,u.verified,u.role FROM tg_bot_link_tokens t
                   INNER JOIN users u ON u.email=t.email
                   WHERE t.token_hash=? AND t.used_at IS NULL AND t.expires_at>=? LIMIT 1""",
                (token_hash, now),
            ).fetchone()
            if not row or not int(row['verified']) or int(row['role']) < 2:
                return None
            email = str(row['email'])
            db.execute(
                "UPDATE tg_bot_link_tokens SET used_at=?, used_chat_id=? WHERE token_hash=?",
                (now, chat_id, token_hash),
            )
            db.execute(
                "INSERT OR REPLACE INTO tg_bot_sessions (chat_id, email, authorized_at, telegram_user_id) VALUES (?,?,?,?)",
                (chat_id, email, now, uid),
            )
            db.commit()
            return email
    except Exception as e:
        log.error(f'redeem_link_token: {e}')
        return None


def sync_telegram_user_id(chat_id: str, telegram_user_id: Optional[int]) -> None:
    if telegram_user_id is None:
        return
    uid = str(telegram_user_id)
    try:
        with get_db() as db:
            db.execute(
                "UPDATE tg_bot_sessions SET telegram_user_id=? WHERE chat_id=?",
                (uid, chat_id),
            )
            db.commit()
    except Exception as e:
        log.warning(f'sync_telegram_user_id: {e}')


def remove_session(chat_id: str) -> None:
    with get_db() as db:
        db.execute("DELETE FROM tg_bot_sessions WHERE chat_id=?", (chat_id,))
        db.commit()


def verify_user_credentials(email: str, password: str) -> Optional[int]:
    try:
        with get_db() as db:
            row = db.execute(
                "SELECT password, verified, role FROM users WHERE email=? LIMIT 1", (email,)
            ).fetchone()
        if not row:
            return None
        if not int(row['verified']):
            return None
        if not bcrypt.checkpw(password.encode('utf-8'), str(row['password']).encode('utf-8')):
            return None
        return int(row['role'])
    except Exception as e:
        log.error(f'verify_user_credentials: {e}')
        return None


def get_live_stats() -> dict:
    cpu = _cpu()
    mem = _mem()
    swap = _swap()
    uptime_s = _uptime_s()
    disks = _disks()
    net = _net()
    return {
        'cpu': cpu,
        'mem': mem,
        'swap': swap,
        'disks': disks,
        'net': net,
        'uptime': _fmt_uptime(uptime_s),
    }


def _cpu() -> float:
    try:
        def _read():
            with open('/proc/stat') as f:
                return list(map(int, f.readline().split()[1:8]))
        v1 = _read()
        time.sleep(0.4)
        v2 = _read()
        idle1, total1 = v1[3], sum(v1)
        idle2, total2 = v2[3], sum(v2)
        td = total2 - total1
        return round(100.0 * (1.0 - (idle2 - idle1) / td), 1) if td else 0.0
    except Exception:
        return 0.0


def _mem() -> tuple:
    try:
        info = open('/proc/meminfo').read()
        total = int(re.search(r'MemTotal:\s+(\d+)', info).group(1)) * 1024
        avail = int(re.search(r'MemAvailable:\s+(\d+)', info).group(1)) * 1024
        used = total - avail
        pct = round(used / total * 100, 1) if total else 0.0
        return (pct, used, total)
    except Exception:
        return (0.0, 0, 0)


def _swap() -> tuple:
    try:
        info = open('/proc/meminfo').read()
        total = int(re.search(r'SwapTotal:\s+(\d+)', info).group(1)) * 1024
        free = int(re.search(r'SwapFree:\s+(\d+)', info).group(1)) * 1024
        used = total - free
        pct = round(used / total * 100, 1) if total else 0.0
        return (pct, used, total)
    except Exception:
        return (0.0, 0, 0)


def _uptime_s() -> Optional[int]:
    try:
        return int(float(open('/proc/uptime').read().split()[0]))
    except Exception:
        return None


def _fmt_uptime(s: Optional[int]) -> str:
    if s is None:
        return 'N/A'
    return f'{s//86400}d {(s%86400)//3600:02d}h {(s%3600)//60:02d}m'


def _disks() -> list:
    try:
        out = subprocess.check_output(
            ['df', '-B1', '--output=target,size,used,avail,pcent', '-x', 'tmpfs', '-x', 'devtmpfs'],
            text=True, timeout=5
        )
        result = []
        for line in out.strip().split('\n')[1:]:
            parts = line.split()
            if len(parts) >= 5:
                result.append({
                    'mount': parts[0],
                    'total': int(parts[1]),
                    'used': int(parts[2]),
                    'free': int(parts[3]),
                    'pct': float(parts[4].rstrip('%')),
                })
        return result
    except Exception:
        return []


def _net() -> list:
    try:
        def _read():
            m = {}
            for line in open('/proc/net/dev').readlines()[2:]:
                if ':' not in line:
                    continue
                iface, data = line.split(':', 1)
                iface = iface.strip()
                vals = data.split()
                m[iface] = (int(vals[0]), int(vals[8]))
            return m
        s1 = _read()
        time.sleep(0.5)
        s2 = _read()
        result = []
        for iface in s2:
            if iface == 'lo' or iface not in s1:
                continue
            recv = max(0, s2[iface][0] - s1[iface][0]) * 2
            sent = max(0, s2[iface][1] - s1[iface][1]) * 2
            result.append({'iface': iface, 'recv': recv, 'sent': sent})
        return result
    except Exception:
        return []


def _fmt_bytes(b: int) -> str:
    for unit in ['B', 'KB', 'MB', 'GB', 'TB']:
        if b < 1024:
            return f'{b:.1f} {unit}'
        b = b // 1024
    return f'{b:.1f} PB'


def _bar(pct: float, w: int = 10) -> str:
    filled = round(min(max(pct, 0), 100) / 100 * w)
    return '█' * filled + '░' * (w - filled)


def _esc(s: str) -> str:
    return s.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')


def build_stats_message(s: dict) -> str:
    cpu = s['cpu']
    mem_pct, mem_used, mem_total = s['mem']
    swap_pct, swap_used, swap_total = s['swap']
    disks = s['disks']
    nets = s['net']
    uptime = s['uptime']

    lines = [
        '<b>Server Statistics</b>',
        '<code>────────────────────────</code>',
        '',
        f'<b>Uptime:</b> <code>{_esc(uptime)}</code>',
        '',
        f'<b>CPU Load</b>',
        f'<code>{_bar(cpu)}  {cpu:5.1f}%</code>',
        '',
        f'<b>Memory</b>',
        f'<code>{_bar(mem_pct)}  {mem_pct:5.1f}%  {_esc(_fmt_bytes(mem_used))} / {_esc(_fmt_bytes(mem_total))}</code>',
    ]

    if swap_total > 0:
        lines += [
            '',
            f'<b>Swap</b>',
            f'<code>{_bar(swap_pct)}  {swap_pct:5.1f}%  {_esc(_fmt_bytes(swap_used))} / {_esc(_fmt_bytes(swap_total))}</code>',
        ]

    if disks:
        lines += ['', '<b>Disk Usage</b>']
        for d in disks[:6]:
            mount = _esc(d['mount'])[:18]
            lines.append(
                f'<code>{mount:<18s} {_bar(d["pct"], 8)}  {d["pct"]:4.0f}%  '
                f'{_esc(_fmt_bytes(d["used"]))} / {_esc(_fmt_bytes(d["total"]))}</code>'
            )

    if nets:
        lines += ['', '<b>Network (per sec)</b>']
        for n in nets[:4]:
            lines.append(
                f'<code>{_esc(n["iface"]):<10s}  ↓ {_esc(_fmt_bytes(n["recv"]))}  ↑ {_esc(_fmt_bytes(n["sent"]))}</code>'
            )

    lines += [
        '',
        f'<i>Collected at {_fmt_datetime(int(time.time()))}</i>',
    ]
    return '\n'.join(lines)


def limited_reply_keyboard() -> dict:
    return {'keyboard': [[{'text': 'Status'}], [{'text': 'Logout'}]], 'resize_keyboard': True}


def get_chat_state(chat_id: str) -> dict:
    state = _state.get(chat_id)
    if not isinstance(state, dict):
        state = {}
        _state[chat_id] = state
    return state


def set_admin_menu(chat_id: str, menu: str) -> dict:
    state = get_chat_state(chat_id)
    state['menu'] = menu
    return state


def clear_admin_step(chat_id: str) -> dict:
    state = get_chat_state(chat_id)
    state.pop('step', None)
    state.pop('draft', None)
    return state


def admin_reply_keyboard(chat_id: str, is_root: bool = False, menu: Optional[str] = None) -> dict:
    current = menu or get_chat_state(chat_id).get('menu') or 'admin_main'
    if current == 'admin_monitoring':
        rows = [
            [{'text': 'Full statistics'}, {'text': 'Quick status'}],
            [{'text': 'Uptime charts'}],
            [{'text': '💾 Backup'}, {'text': '🐳 Docker'}],
            [{'text': 'Back to menu'}],
        ]
    elif current == 'admin_errors':
        rows = [
            [{'text': 'Latest errors'}, {'text': 'Top errors'}],
            [{'text': 'Back to menu'}],
        ]
    elif current == 'admin_users':
        rows = [
            [{'text': 'Users list'}, {'text': 'Find user'}],
            [{'text': 'Add user'}],
            [{'text': 'Back to menu'}],
        ]
    elif current == 'admin_user_actions':
        rows = [
            [{'text': 'Change role'}, {'text': 'Toggle verify'}],
            [{'text': 'Reset password'}, {'text': 'Delete user'}],
            [{'text': 'Back to users'}],
        ]
    elif current == 'admin_role_select':
        rows = [
            [{'text': 'Role: Guest'}, {'text': 'Role: Manager'}],
            [{'text': 'Role: Admin'}],
            [{'text': 'Cancel'}],
        ]
    elif current == 'admin_verified_select':
        rows = [
            [{'text': 'Verified: Yes'}, {'text': 'Verified: No'}],
            [{'text': 'Cancel'}],
        ]
    elif current == 'admin_delete_confirm':
        rows = [
            [{'text': 'Confirm delete'}],
            [{'text': 'Cancel'}],
        ]
    elif current == 'admin_database':
        rows = [
            [{'text': 'DB statistics'}, {'text': 'Tables list'}],
        ]
        if is_root:
            rows.append([{'text': 'VACUUM'}])
        rows.append([{'text': 'Back to menu'}])
    else:
        rows = [
            [{'text': 'Monitoring'}, {'text': 'Errors'}],
            [{'text': 'Users'}, {'text': 'Database'}],
            [{'text': 'Help'}, {'text': 'Logout'}],
        ]
    return {'keyboard': rows, 'resize_keyboard': True}


def remove_reply_keyboard() -> dict:
    return {'remove_keyboard': True}


def smart_logs_table_exists(db: sqlite3.Connection) -> bool:
    row = db.execute(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name='smart_app_logs' LIMIT 1"
    ).fetchone()
    return row is not None


def fetch_smart_logs_recent(limit: int = 12) -> list:
    with get_db() as db:
        if not smart_logs_table_exists(db):
            return []
        return db.execute(
            """SELECT app_id, level, script, source_file, source_line, message, hit_count, first_seen, last_seen
               FROM smart_app_logs ORDER BY last_seen DESC LIMIT ?""",
            (limit,),
        ).fetchall()


def fetch_smart_logs_top(limit: int = 12) -> list:
    with get_db() as db:
        if not smart_logs_table_exists(db):
            return []
        return db.execute(
            """SELECT app_id, level, script, source_file, source_line, message, hit_count, first_seen, last_seen
               FROM smart_app_logs ORDER BY hit_count DESC, last_seen DESC LIMIT ?""",
            (limit,),
        ).fetchall()


def _fmt_ts(ts: Optional[int]) -> str:
    if ts is None:
        return '—'
    try:
        return _fmt_unix_local(int(ts))
    except Exception:
        return '—'


def build_errors_recent_message(rows: list) -> str:
    if not rows:
        return '<b>Latest errors</b>\n<code>────────────────────────</code>\n\n<i>No entries yet.</i>'
    lines = ['<b>Latest errors</b>', '<code>────────────────────────</code>', '']
    for r in rows:
        app = _esc(str(r['app_id'] or '—'))
        lvl = _esc(str(r['level'] or ''))
        hits = int(r['hit_count'] or 1)
        script = _esc(str(r['script'] or '')[:48])
        sf = _esc(str(r['source_file'] or '')[-48:])
        sl = r['source_line']
        sl_s = str(sl) if sl is not None else '—'
        msg = _esc(str(r['message'] or '')[:280])
        lines.append(f'<b>{app}</b> · <code>{lvl}</code> · ×{hits}')
        lines.append(f'<code>{sf}:{sl_s}</code>')
        if script:
            lines.append(f'<code>{script}</code>')
        lines.append(f'<i>{msg}</i>')
        lines.append(f'<i>last {_fmt_ts(r["last_seen"])} · first {_fmt_ts(r["first_seen"])}</i>')
        lines.append('')
    lines.append(f'<i>{_fmt_datetime(int(time.time()))}</i>')
    return '\n'.join(lines)


def build_errors_top_message(rows: list) -> str:
    if not rows:
        return '<b>Top errors</b>\n<code>────────────────────────</code>\n\n<i>No entries yet.</i>'
    lines = ['<b>Top errors</b>', '<code>────────────────────────</code>', '']
    for r in rows:
        app = _esc(str(r['app_id'] or '—'))
        lvl = _esc(str(r['level'] or ''))
        hits = int(r['hit_count'] or 1)
        sf = _esc(str(r['source_file'] or '')[-48:])
        sl = r['source_line']
        sl_s = str(sl) if sl is not None else '—'
        msg = _esc(str(r['message'] or '')[:280])
        lines.append(f'×<b>{hits}</b> · <code>{lvl}</code> · <b>{app}</b>')
        lines.append(f'<code>{sf}:{sl_s}</code>')
        lines.append(f'<i>{msg}</i>')
        lines.append(f'<i>last {_fmt_ts(r["last_seen"])}</i>')
        lines.append('')
    lines.append(f'<i>{_fmt_datetime(int(time.time()))}</i>')
    return '\n'.join(lines)


def tg_send_chunks(token: str, chat_id: str, text: str, parse_mode: str = 'HTML', reply_markup: Optional[dict] = None) -> None:
    max_len = 3800
    first = True
    pos = 0
    while pos < len(text):
        chunk = text[pos : pos + max_len]
        pos += max_len
        mk = reply_markup if first else None
        tg_send(token, chat_id, chunk, parse_mode=parse_mode, reply_markup=mk)
        first = False



def _fmt_unix_local(ts: Optional[int]) -> str:
    if ts is None:
        return '—'
    try:
        return time.strftime('%d.%m.%Y %H:%M:%S', time.localtime(int(ts)))
    except Exception:
        return '—'


def _fmt_datetime(value) -> str:
    if value is None or value == '':
        return '—'
    if isinstance(value, (int, float)) or (isinstance(value, str) and value.strip().isdigit()):
        ts = int(float(value))
        if ts > 10_000_000_000:
            ts //= 1000
        return _fmt_unix_local(ts)
    raw = str(value).strip()
    for candidate in (raw, raw.replace('Z', '+00:00')):
        try:
            dt = datetime.fromisoformat(candidate)
            if dt.tzinfo is None:
                dt = dt.replace(tzinfo=timezone.utc)
            return dt.astimezone().strftime('%d.%m.%Y %H:%M:%S')
        except Exception:
            pass
    for fmt in ('%Y%m%dT%H%M%SZ', '%Y%m%dT%H%M%S', '%Y-%m-%d %H:%M:%S'):
        try:
            return datetime.strptime(raw, fmt).astimezone().strftime('%d.%m.%Y %H:%M:%S')
        except Exception:
            pass
    return raw


def _strip_emoji_prefix(text: str) -> str:
    return re.sub(r'^[^\w<]+\s*', '', text or '').strip()


def _run_command_lines(cmd: list[str], timeout: int = 15) -> list[str]:
    out = subprocess.check_output(cmd, text=True, timeout=timeout, stderr=subprocess.STDOUT)
    return [line.rstrip('\n') for line in out.splitlines()]


def _php_backup_credentials() -> dict:
    try:
        out = subprocess.check_output(['php', BOT_SERVICE_PHP, 'backup-credentials'], text=True, timeout=10)
        data = json.loads(out.strip() or '{}')
        if isinstance(data, dict):
            return {'host': str(data.get('host') or ''), 'token': str(data.get('token') or '')}
    except Exception as e:
        log.warning('backup credentials read failed: %s', e)
    return {'host': '', 'token': ''}


def get_backup_monitor_data() -> dict:
    creds = _php_backup_credentials()
    host = creds.get('host') or 'http://localhost:8200'
    token = creds.get('token') or ''
    result = {
        'configured': bool(token),
        'host': host,
        'server_state': None,
        'backups': [],
        'error': '',
    }
    if not token:
        result['error'] = 'Duplicati token is not configured.'
        return result
    headers = {
        'Authorization': f'Bearer {token}',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    }
    try:
        state_resp = requests.get(f'{host}/api/v1/serverstate', headers=headers, timeout=10)
        state_resp.raise_for_status()
        result['server_state'] = state_resp.json()
        backups_resp = requests.get(f'{host}/api/v1/backups', headers=headers, timeout=15)
        backups_resp.raise_for_status()
        payload = backups_resp.json()
        if isinstance(payload, list):
            result['backups'] = payload
        else:
            result['error'] = 'Unexpected Duplicati /backups response.'
    except Exception as e:
        result['error'] = str(e)
    return result


def build_backup_monitor_message(data: dict) -> str:
    lines = ['<b>💾 Backup Monitor</b>', '<code>────────────────────────</code>', '']
    lines.append(f'<b>Host:</b> <code>{_esc(str(data.get("host") or "—"))}</code>')
    if not data.get('configured'):
        lines += ['', '<i>Duplicati API token is not configured.</i>']
        return '\n'.join(lines)
    state = data.get('server_state') or {}
    if state:
        program_state = _esc(str(state.get('ProgramState') or '—'))
        icon = _esc(str(state.get('SuggestedStatusIcon') or '—'))
        warning = 'yes' if state.get('HasWarning') else 'no'
        error = 'yes' if state.get('HasError') else 'no'
        lines += [
            f'<b>State:</b> <code>{program_state}</code>',
            f'<b>Icon:</b> <code>{icon}</code>',
            f'<b>Warnings:</b> <code>{warning}</code> · <b>Errors:</b> <code>{error}</code>',
        ]
    if data.get('error'):
        lines += ['', f'<i>Could not load backup jobs: {_esc(str(data["error"])[:250])}</i>']
        return '\n'.join(lines)
    backups = data.get('backups') or []
    lines += ['', f'<b>Jobs:</b> <code>{len(backups)}</code>']
    if not backups:
        lines += ['', '<i>No backup jobs found in Duplicati.</i>']
        return '\n'.join(lines)
    active = 0
    for item in backups:
        if item.get('ActiveRunId') is not None:
            active += 1
    lines.append(f'<b>Active now:</b> <code>{active}</code>')
    lines.append('')
    for item in backups[:10]:
        backup = item.get('Backup') or {}
        meta = backup.get('Metadata') or {}
        name = _esc(str(backup.get('Name') or 'Unnamed'))
        target = _esc(str(backup.get('TargetURL') or '—')[:54])
        status = 'Running' if item.get('ActiveRunId') is not None else _strip_emoji_prefix(str(meta.get('LastResult') or meta.get('ParsedResult') or 'Idle'))
        schedule = item.get('Schedule') or {}
        repeat = _esc(str(schedule.get('Repeat') or 'manual'))
        last_run = _esc(_fmt_datetime(meta.get('LastBackupDate') or meta.get('LastBackupStarted')))
        lines.append(f'<b>{name}</b> · <code>{_esc(status)}</code>')
        lines.append(f'<code>{target}</code>')
        lines.append(f'<i>schedule {repeat} · last {last_run}</i>')
        lines.append('')
    return '\n'.join(lines).rstrip()


def _font(size: int, bold: bool = False):
    from PIL import ImageFont
    try:
        family = 'DejaVuSans-Bold.ttf' if bold else 'DejaVuSans.ttf'
        return ImageFont.truetype('/usr/share/fonts/truetype/dejavu/' + family, size)
    except Exception:
        return ImageFont.load_default()


def _text(draw, pos, text: str, font, fill: str, max_width: int, max_lines: int = 3) -> int:
    words = []
    for word in str(text).split():
        if draw.textlength(word, font=font) <= max_width:
            words.append(word)
            continue
        part = ''
        for ch in word:
            test = part + ch
            if draw.textlength(test, font=font) <= max_width or not part:
                part = test
            else:
                words.append(part)
                part = ch
        if part:
            words.append(part)
    lines, current = [], ''
    for word in words or ['']:
        test = (current + ' ' + word).strip()
        if draw.textlength(test, font=font) <= max_width or not current:
            current = test
        else:
            lines.append(current)
            current = word
    if current:
        lines.append(current)
    x, y = pos
    line_h = font.size + 7 if hasattr(font, 'size') else 22
    for line in lines[:max_lines]:
        draw.text((x, y), line, font=font, fill=fill)
        y += line_h
    return y


def _backup_sources(backup: dict) -> str:
    sources = backup.get('Sources') or backup.get('SourcePaths') or []
    if isinstance(sources, list):
        return '; '.join(str(s) for s in sources if str(s).strip()) or '—'
    return str(sources or '—')


def render_backup_report_png(data: dict) -> Optional[str]:
    try:
        from PIL import Image, ImageDraw
    except Exception as e:
        log.error(f'Pillow import failed: {e}')
        return None
    backups = data.get('backups') or []
    width = 1200
    item_h = 220
    height = max(520, 260 + min(len(backups), 10) * item_h)
    img = Image.new('RGB', (width, height), '#101418')
    draw = ImageDraw.Draw(img)
    title_font = _font(30, True)
    head_font = _font(20, True)
    font = _font(17)
    small = _font(14)
    draw.text((32, 26), 'Backup Monitor Report', fill='#f8fafc', font=title_font)
    draw.text((32, 70), _fmt_datetime(int(time.time())), fill='#94a3b8', font=font)
    state = data.get('server_state') or {}
    program_state = str(state.get('ProgramState') or '—')
    has_warning = 'yes' if state.get('HasWarning') else 'no'
    has_error = 'yes' if state.get('HasError') else 'no'
    active = sum(1 for item in backups if item.get('ActiveRunId') is not None)
    summary = [
        ('Host', str(data.get('host') or '—')),
        ('State', program_state),
        ('Jobs', f'{len(backups)} total, {active} active'),
        ('Warnings', has_warning),
        ('Errors', has_error),
    ]
    x = 32
    y = 118
    for label, value in summary:
        draw.rounded_rectangle((x, y, x + 210, y + 76), radius=10, fill='#151b22', outline='#263241')
        draw.text((x + 16, y + 13), label, fill='#94a3b8', font=small)
        draw.text((x + 16, y + 39), value[:20], fill='#e5e7eb', font=font)
        x += 226
    if data.get('error'):
        draw.text((32, 230), 'Could not load backup jobs', fill='#f87171', font=head_font)
        _text(draw, (32, 266), str(data.get('error')), font, '#fecaca', width - 64)
    elif not backups:
        draw.text((32, 240), 'No backup jobs found in Duplicati.', fill='#cbd5e1', font=head_font)
    else:
        y = 230
        for item in backups[:10]:
            backup = item.get('Backup') or {}
            meta = backup.get('Metadata') or {}
            schedule = item.get('Schedule') or {}
            name = str(backup.get('Name') or 'Unnamed')
            status = 'Running' if item.get('ActiveRunId') is not None else _strip_emoji_prefix(str(meta.get('LastResult') or meta.get('ParsedResult') or 'Idle'))
            color = '#22c55e' if status.lower() in ('success', 'idle') else '#f59e0b'
            if 'fail' in status.lower() or 'error' in status.lower():
                color = '#ef4444'
            draw.rounded_rectangle((32, y, width - 32, y + 196), radius=10, fill='#151b22', outline='#263241')
            draw.ellipse((52, y + 24, 68, y + 40), fill=color)
            draw.text((82, y + 17), name[:70], fill='#f3f4f6', font=head_font)
            draw.text((width - 245, y + 20), status[:22], fill=color, font=font)
            rows = [
                ('Target', str(backup.get('TargetURL') or '—')),
                ('Sources', _backup_sources(backup)),
                ('Schedule', str(schedule.get('Repeat') or 'manual')),
                ('Last backup', _fmt_datetime(meta.get('LastBackupDate') or meta.get('LastBackupStarted'))),
                ('Next run', _fmt_datetime(schedule.get('NextRun') or item.get('NextRun') or meta.get('NextBackupDate'))),
            ]
            yy = y + 58
            for label, value in rows:
                draw.text((54, yy), label + ':', fill='#94a3b8', font=small)
                yy = _text(draw, (160, yy - 2), value, small, '#d1d5db', width - 220, 2)
            y += item_h
    tmp = tempfile.NamedTemporaryFile(prefix='appdashboard-backup-', suffix='.png', delete=False)
    tmp.close()
    img.save(tmp.name, 'PNG')
    return tmp.name


def send_backup_report(token: str, chat_id: str, kb: dict) -> None:
    data = get_backup_monitor_data()
    path = render_backup_report_png(data)
    if not path:
        tg_send_chunks(token, chat_id, build_backup_monitor_message(data), reply_markup=kb)
        return
    try:
        tg_send_photo(token, chat_id, path, 'Backup monitor report', reply_markup=kb)
    finally:
        try:
            os.unlink(path)
        except OSError:
            pass



def _php_docker_config() -> dict:
    try:
        out = subprocess.check_output(['php', BOT_SERVICE_PHP, 'docker-config'], text=True, timeout=10)
        data = json.loads(out.strip() or '{}')
        if isinstance(data, dict):
            return {
                'host': str(data.get('host') or ''),
                'domain': str(data.get('domain') or ''),
                'token': str(data.get('token') or ''),
                'username': str(data.get('username') or ''),
                'password': str(data.get('password') or ''),
            }
    except Exception as e:
        log.warning('docker config read failed: %s', e)
    return {'host': '', 'domain': '', 'token': '', 'username': '', 'password': ''}


def _portainer_base_candidates(cfg: dict) -> list[str]:
    candidates = []
    machine = socket.gethostname().strip()
    for candidate in [
        str(cfg.get('host') or '').strip(),
        ('http://' + str(cfg.get('domain') or '').strip()) if str(cfg.get('domain') or '').strip() else '',
        ('http://' + machine + ':9000') if machine else '',
        'http://localhost:9000',
        'http://127.0.0.1:9000',
    ]:
        if not candidate:
            continue
        candidate = candidate.rstrip('/')
        if not re.match(r'^https?://', candidate, re.I):
            candidate = 'http://' + candidate
        if not re.search(r':\d+$', re.sub(r'^https?://', '', candidate, flags=re.I)):
            candidate += ':9000'
        if candidate not in candidates:
            candidates.append(candidate)
    return candidates


def _save_docker_token(token: str) -> None:
    if not token:
        return
    try:
        with get_db() as db:
            db.execute("INSERT INTO config(key, value) VALUES('docker_token', ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value", (token,))
            db.commit()
    except Exception as e:
        log.warning('save docker token failed: %s', e)


def _resolve_portainer_base_and_token() -> tuple[str, str, dict, str]:
    cfg = _php_docker_config()
    token = str(cfg.get('token') or '').strip()
    username = str(cfg.get('username') or '').strip()
    password = str(cfg.get('password') or '').strip()
    last_error = ''
    for base in _portainer_base_candidates(cfg):
        try:
            status_resp = requests.get(base + '/api/status', timeout=5)
            if status_resp.status_code != 200:
                continue
            if token:
                test = requests.get(base + '/api/endpoints', headers={'Authorization': f'Bearer {token}', 'Accept': 'application/json'}, timeout=8)
                if test.status_code == 200:
                    return base, token, cfg, ''
            if username and password:
                auth = requests.post(base + '/api/auth', json={'username': username, 'password': password}, timeout=10)
                if auth.status_code == 200:
                    new_token = str(auth.json().get('jwt') or '').strip()
                    if new_token:
                        _save_docker_token(new_token)
                        cfg['token'] = new_token
                        return base, new_token, cfg, ''
                last_error = f'{base} auth HTTP {auth.status_code}'
        except Exception as e:
            last_error = f'{base}: {e}'
    return '', token, cfg, last_error


def _docker_cli_containers() -> tuple[list[dict], str]:
    try:
        lines = _run_command_lines(['docker', 'ps', '-a', '--format', '{{.Names}}|{{.Status}}|{{.Image}}'], timeout=15)
        containers = []
        for line in lines:
            if not line.strip():
                continue
            parts = line.split('|', 2)
            if len(parts) != 3:
                continue
            containers.append({'name': parts[0], 'status': parts[1], 'image': parts[2]})
        return containers, ''
    except Exception as e:
        return [], str(e)


def _docker_portainer_containers() -> tuple[list[dict], str, dict]:
    info = {'version': '', 'endpoint_id': None, 'endpoint_name': '', 'status_code': None, 'base_url': ''}
    try:
        base_url, token, _cfg, resolve_error = _resolve_portainer_base_and_token()
        if not base_url:
            return [], resolve_error or 'No working Portainer base URL found.', info
        info['base_url'] = base_url
        headers = {'Authorization': f'Bearer {token}', 'Content-Type': 'application/json', 'Accept': 'application/json'}
        status_resp = requests.get(base_url + '/api/status', timeout=8)
        if status_resp.ok:
            status_data = status_resp.json()
            info['version'] = str(status_data.get('Version') or '')
        endpoints_resp = requests.get(base_url + '/api/endpoints', headers=headers, timeout=10)
        info['status_code'] = endpoints_resp.status_code
        if endpoints_resp.status_code != 200:
            return [], f'Portainer API HTTP {endpoints_resp.status_code}', info
        endpoints = endpoints_resp.json()
        if not isinstance(endpoints, list) or not endpoints:
            return [], 'No Portainer endpoints found.', info
        ep = endpoints[0]
        ep_id = ep.get('Id')
        info['endpoint_id'] = ep_id
        info['endpoint_name'] = str(ep.get('Name') or '')
        containers_resp = requests.get(f'{base_url}/api/endpoints/{ep_id}/docker/containers/json?all=1', headers=headers, timeout=15)
        if containers_resp.status_code != 200:
            return [], f'Portainer containers API HTTP {containers_resp.status_code}', info
        payload = containers_resp.json()
        containers = []
        if isinstance(payload, list):
            for item in payload:
                names = item.get('Names') or []
                raw_name = names[0] if names else item.get('Id', 'unknown')
                containers.append({
                    'name': str(raw_name).lstrip('/'),
                    'status': str(item.get('Status') or item.get('State') or 'unknown'),
                    'image': str(item.get('Image') or '—'),
                })
        return containers, '', info
    except Exception as e:
        return [], str(e), info


def get_docker_monitor_data() -> dict:
    result = {
        'service_active': False,
        'service_since': '',
        'service_detail': '',
        'containers': [],
        'source': '',
        'engine_error': '',
        'portainer_info': {},
    }
    try:
        lines = _run_command_lines(['systemctl', 'status', 'docker.service', '--no-pager', '-l'], timeout=10)
        result['service_detail'] = '\n'.join(lines[:12])
        for line in lines[:8]:
            stripped = line.strip()
            if stripped.startswith('Active:'):
                result['service_active'] = 'active (running)' in stripped.lower()
                result['service_since'] = stripped.split(' since ', 1)[1] if ' since ' in stripped else stripped
                break
    except Exception as e:
        result['engine_error'] = str(e)
    containers, cli_error = _docker_cli_containers()
    if containers:
        result['containers'] = containers
        result['source'] = 'docker-cli'
        return result
    portainer_containers, portainer_error, portainer_info = _docker_portainer_containers()
    result['portainer_info'] = portainer_info
    if portainer_containers:
        result['containers'] = portainer_containers
        result['source'] = 'portainer-api'
        return result
    errors = []
    if cli_error:
        errors.append(f'docker cli: {cli_error}')
    if portainer_error:
        errors.append(f'portainer: {portainer_error}')
    result['engine_error'] = '; '.join(errors)[:500]
    return result


def build_docker_monitor_message(data: dict) -> str:
    lines = ['<b>🐳 Docker Monitor</b>', '<code>────────────────────────</code>', '']
    service_state = 'running' if data.get('service_active') else 'inactive'
    lines.append(f'<b>Engine:</b> <code>{service_state}</code>')
    if data.get('service_since'):
        lines.append(f'<i>{_esc(str(data["service_since"])[:120])}</i>')
    containers = data.get('containers') or []
    if containers:
        running = sum(1 for c in containers if 'up' in str(c.get('status', '')).lower() or 'running' in str(c.get('status', '')).lower())
        stopped = len(containers) - running
        lines += ['', f'<b>Source:</b> <code>{_esc(str(data.get("source") or "—"))}</code>', f'<b>Containers:</b> <code>{len(containers)}</code> · running <code>{running}</code> · stopped <code>{stopped}</code>', '']
        for item in containers[:10]:
            name = _esc(str(item.get('name') or 'unknown'))
            status = _esc(str(item.get('status') or 'unknown'))
            image = _esc(str(item.get('image') or '—')[:52])
            lines.append(f'<b>{name}</b> · <code>{status}</code>')
            lines.append(f'<code>{image}</code>')
            lines.append('')
        return '\n'.join(lines).rstrip()
    lines += ['', '<i>Container list is not available for the bot process.</i>']
    if data.get('engine_error'):
        lines.append(f'<i>{_esc(str(data["engine_error"])[:320])}</i>')
    portainer_info = data.get('portainer_info') or {}
    if portainer_info.get('version'):
        lines.append(f'<b>Portainer:</b> <code>{_esc(str(portainer_info["version"]))}</code>')
    lines.append('<i>Docker engine status is shown, but container listing needs valid API access or docker.sock permissions.</i>')
    return '\n'.join(lines)


def monitor_table_exists(name: str) -> bool:
    if not re.fullmatch(r'[A-Za-z_][A-Za-z0-9_]*', name):
        return False
    with get_db() as db:
        row = db.execute(
            f"SELECT 1 FROM sqlite_master WHERE type='table' AND name='{name}' LIMIT 1"
        ).fetchone()
        return row is not None


def fetch_uptime_chart_data(hours: int = 24, limit: int = 6) -> list[dict]:
    if not monitor_table_exists('monitor_uptime_checks') or not monitor_table_exists('monitor_uptime_results'):
        return []
    since = int(time.time()) - hours * 3600
    checks = []
    with get_db() as db:
        rows = db.execute(
            """SELECT c.id,c.name,c.type,c.target
               FROM monitor_uptime_checks c
               WHERE c.enabled=1 AND EXISTS (
                   SELECT 1 FROM monitor_uptime_results r WHERE r.check_id=c.id AND r.checked_at>=?
               )
               ORDER BY c.auto_discovered ASC, c.name ASC
               LIMIT ?""",
            (since, limit),
        ).fetchall()
        for row in rows:
            hist = db.execute(
                """SELECT checked_at,status,latency_ms
                   FROM monitor_uptime_results
                   WHERE check_id=? AND checked_at>=?
                   ORDER BY checked_at ASC""",
                (row['id'], since),
            ).fetchall()
            if len(hist) > 240:
                step = max(1, len(hist) // 240)
                hist = hist[::step]
            checks.append({
                'name': str(row['name'] or 'Unnamed'),
                'type': str(row['type'] or ''),
                'target': str(row['target'] or ''),
                'history': hist,
            })
    return checks


def render_uptime_chart_png(checks: list[dict], hours: int = 24) -> Optional[str]:
    if not checks:
        return None
    try:
        from PIL import Image, ImageDraw, ImageFont
    except Exception as e:
        log.error(f'Pillow import failed: {e}')
        return None
    width = 1200
    panel_h = 150
    top = 70
    height = top + len(checks) * panel_h + 40
    img = Image.new('RGB', (width, height), '#101418')
    draw = ImageDraw.Draw(img)
    try:
        font_title = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 26)
        font = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 18)
        font_small = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 14)
    except Exception:
        font_title = font = font_small = ImageFont.load_default()
    draw.text((28, 22), f'Uptime monitoring, last {hours}h', fill='#f3f4f6', font=font_title)
    now = int(time.time())
    since = now - hours * 3600
    for idx, check in enumerate(checks):
        y = top + idx * panel_h
        x0, y0, x1, y1 = 28, y + 38, width - 28, y + 116
        hist = list(check.get('history') or [])
        up = sum(1 for h in hist if str(h['status']) == 'up')
        availability = round(up / len(hist) * 100, 2) if hist else 0
        latest = hist[-1] if hist else {}
        title = f"{check['name'][:44]}  {availability:.2f}%"
        draw.text((28, y + 8), title, fill='#e5e7eb', font=font)
        meta = f"{check['type']} {check['target'][:70]}  last: {latest.get('status', 'unknown')}"
        draw.text((width - 520, y + 12), meta, fill='#9ca3af', font=font_small)
        draw.rectangle((x0, y0, x1, y1), outline='#334155', fill='#151b22')
        for i in range(1, 4):
            gy = y0 + int((y1 - y0) * i / 4)
            draw.line((x0, gy, x1, gy), fill='#24303b')
        latencies = [int(h['latency_ms'] or 0) for h in hist if h['latency_ms'] is not None and int(h['latency_ms'] or 0) >= 0]
        max_latency = max(latencies) if latencies else 1
        max_latency = max(max_latency, 100)
        points = []
        for h in hist:
            ts = int(h['checked_at'])
            x = x0 + int((max(min(ts, now), since) - since) / max(1, now - since) * (x1 - x0))
            status = str(h['status'])
            if status != 'up':
                draw.line((x, y0, x, y1), fill='#ef4444', width=3)
                continue
            latency = int(h['latency_ms'] or 0)
            py = y1 - int(min(latency, max_latency) / max_latency * (y1 - y0 - 8)) - 4
            points.append((x, py))
            draw.rectangle((x - 2, y1 + 9, x + 2, y1 + 18), fill='#22c55e')
        if len(points) > 1:
            draw.line(points, fill='#38bdf8', width=3)
        for x, py in points[-20:]:
            draw.ellipse((x - 3, py - 3, x + 3, py + 3), fill='#7dd3fc')
        draw.text((x0, y1 + 24), time.strftime('%H:%M', time.localtime(since)), fill='#9ca3af', font=font_small)
        draw.text((x1 - 52, y1 + 24), time.strftime('%H:%M', time.localtime(now)), fill='#9ca3af', font=font_small)
        draw.text((x1 - 120, y0 + 6), f'max {max_latency} ms', fill='#9ca3af', font=font_small)
    tmp = tempfile.NamedTemporaryFile(prefix='appdashboard-uptime-', suffix='.png', delete=False)
    tmp.close()
    img.save(tmp.name, 'PNG')
    return tmp.name


def send_uptime_charts(token: str, chat_id: str, kb: dict) -> None:
    checks = fetch_uptime_chart_data()
    if not checks:
        tg_send(token, chat_id, 'No uptime monitoring history available.', parse_mode='', reply_markup=kb)
        return
    path = render_uptime_chart_png(checks)
    if not path:
        tg_send(token, chat_id, 'Could not render uptime chart image.', parse_mode='', reply_markup=kb)
        return
    try:
        tg_send_photo(token, chat_id, path, 'Uptime monitoring charts', reply_markup=kb)
    finally:
        try:
            os.unlink(path)
        except OSError:
            pass


def build_status_message(s: dict) -> str:
    cpu = s['cpu']
    mem_pct = s['mem'][0]
    swap_pct = s['swap'][0]
    uptime = s['uptime']

    def _indicator(pct: float) -> str:
        if pct >= 85:
            return 'CRITICAL'
        if pct >= 70:
            return 'WARNING'
        return 'OK'

    lines = [
        '<b>Quick status</b>',
        '<code>────────────────────────</code>',
        '',
        f'<b>Uptime:</b> <code>{_esc(uptime)}</code>',
        f'<b>CPU:</b>    <code>{cpu:5.1f}%  {_indicator(cpu)}</code>',
        f'<b>Memory:</b> <code>{mem_pct:5.1f}%  {_indicator(mem_pct)}</code>',
    ]
    if s['swap'][2] > 0:
        lines.append(f'<b>Swap:</b>   <code>{swap_pct:5.1f}%  {_indicator(swap_pct)}</code>')

    max_disk = max((d['pct'] for d in s['disks']), default=0.0)
    if max_disk > 0:
        lines.append(f'<b>Disk:</b>   <code>{max_disk:5.1f}%  {_indicator(max_disk)}</code>')

    lines += [
        '',
        f'<i>{_fmt_datetime(int(time.time()))}</i>',
    ]
    return '\n'.join(lines)


HELP_ADMIN = (
    '<b>Server bot</b>\n'
    '<code>────────────────────────</code>\n\n'
    '<b>Administrator</b>\n'
    'Monitoring → Full statistics, Quick status, 💾 Backup, 🐳 Docker\n'
    'Monitoring → Uptime charts sends uptime monitoring graphs as PNG\n'
    'Errors → Latest errors, Top errors\n'
    'Users → user management inside the bot\n'
    'Database → DB statistics, tables list, VACUUM for root\n'
    'Help · Logout'
)

HELP_LIMITED = (
    '<b>Server bot</b>\n'
    '<code>────────────────────────</code>\n\n'
    'Your account is not an administrator.\n'
    'Only <b>Status</b> and <b>Logout</b> are available.'
)

DENIED_NON_ADMIN = (
    '<b>Access denied.</b>\n\n'
    'For your role only <b>Status</b> and <b>Logout</b> are allowed.'
)


def tg_send(token: str, chat_id: str, text: str, parse_mode: str = 'HTML', reply_markup: Optional[dict] = None) -> None:
    url = f'https://api.telegram.org/bot{token}/sendMessage'
    data: dict = {
        'chat_id': chat_id,
        'text': text,
        'disable_web_page_preview': True,
    }
    if parse_mode:
        data['parse_mode'] = parse_mode
    if reply_markup is not None:
        data['reply_markup'] = json.dumps(reply_markup)
    try:
        r = requests.post(url, data=data, timeout=15)
        resp = r.json()
        if not resp.get('ok'):
            log.warning(
                'tg_send non-ok chat=%s code=%s desc=%s',
                chat_id,
                resp.get('error_code'),
                resp.get('description'),
            )
    except Exception as e:
        log.error(f'tg_send error: {e}')


def tg_send_photo(token: str, chat_id: str, path: str, caption: str = '', reply_markup: Optional[dict] = None) -> None:
    url = f'https://api.telegram.org/bot{token}/sendPhoto'
    data = {'chat_id': chat_id, 'caption': caption}
    if reply_markup is not None:
        data['reply_markup'] = json.dumps(reply_markup)
    try:
        with open(path, 'rb') as f:
            r = requests.post(url, data=data, files={'photo': f}, timeout=30)
        resp = r.json()
        if not resp.get('ok'):
            log.warning(
                'tg_send_photo non-ok chat=%s code=%s desc=%s',
                chat_id,
                resp.get('error_code'),
                resp.get('description'),
            )
    except Exception as e:
        log.error(f'tg_send_photo error: {e}')


def tg_get_updates(token: str, offset: int) -> list:
    url = f'https://api.telegram.org/bot{token}/getUpdates'
    try:
        r = requests.get(
            url,
            params={'offset': offset, 'timeout': POLL_TIMEOUT, 'allowed_updates': '["message"]'},
            timeout=POLL_TIMEOUT + 10
        )
        return r.json().get('result', [])
    except Exception as e:
        log.error(f'getUpdates error: {e}')
        return []


def normalize_tg_command(raw: str) -> str:
    part = raw.split()[0].lower() if raw else ''
    if '@' in part:
        part = part.split('@', 1)[0]
    return part


def resolve_command(text: str) -> str:
    t = text.strip()
    sc = normalize_tg_command(t)
    if sc.startswith('/'):
        return sc
    return COMMAND_ALIASES.get(t.lower(), '') or COMMAND_ALIASES.get(_label_key(t), '')


def role_name(role: int) -> str:
    return {0: 'guest', 1: 'manager', 2: 'admin'}.get(int(role), f'role-{role}')


def is_root_email(email: str) -> bool:
    return email.strip().lower() == 'root@root'


def is_valid_email(email: str) -> bool:
    return bool(re.match(r'^[^@\s]+@[^@\s]+\.[^@\s]+$', email or ''))


def get_user_by_email(email: str) -> Optional[sqlite3.Row]:
    try:
        with get_db() as db:
            return db.execute(
                """SELECT email, name, role, verified, language, theme, color, created_at
                   FROM users WHERE email=? LIMIT 1""",
                (email,),
            ).fetchone()
    except Exception as e:
        log.error(f'get_user_by_email: {e}')
        return None


def list_users(limit: int = 40) -> list:
    with get_db() as db:
        return db.execute(
            """SELECT email, name, role, verified, language, theme, created_at
               FROM users
               ORDER BY role DESC, email ASC
               LIMIT ?""",
            (limit,),
        ).fetchall()


def create_user(
    email: str,
    password: str,
    name: str,
    role: int,
    verified: int,
    language: str = 'en',
    theme: str = 'light',
    color: str = '#007bff',
) -> tuple[bool, str]:
    if not is_valid_email(email):
        return False, 'Invalid email format.'
    if len(password) < 8:
        return False, 'Password must be at least 8 characters.'
    if get_user_by_email(email):
        return False, 'User already exists.'
    display_name = (name or '').strip() or email.split('@', 1)[0]
    password_hash = bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')
    try:
        with get_db() as db:
            db.execute(
                """INSERT INTO users(email, name, password, photo, role, verified, language, theme, color)
                   VALUES (?, ?, ?, 'default_avatar.png', ?, ?, ?, ?, ?)""",
                (email, display_name, password_hash, int(role), int(verified), language, theme, color),
            )
            db.commit()
        return True, 'User created.'
    except Exception as e:
        log.error(f'create_user: {e}')
        return False, 'Failed to create user.'


def update_user_role(email: str, role: int) -> tuple[bool, str]:
    try:
        with get_db() as db:
            cur = db.execute("UPDATE users SET role=? WHERE email=?", (int(role), email))
            db.commit()
        return (cur.rowcount > 0, 'Role updated.' if cur.rowcount > 0 else 'User not found.')
    except Exception as e:
        log.error(f'update_user_role: {e}')
        return False, 'Failed to update role.'


def update_user_verified(email: str, verified: int) -> tuple[bool, str]:
    try:
        with get_db() as db:
            cur = db.execute("UPDATE users SET verified=? WHERE email=?", (int(verified), email))
            db.commit()
        return (cur.rowcount > 0, 'Verification updated.' if cur.rowcount > 0 else 'User not found.')
    except Exception as e:
        log.error(f'update_user_verified: {e}')
        return False, 'Failed to update verification.'


def update_user_password(email: str, password: str) -> tuple[bool, str]:
    if len(password) < 8:
        return False, 'Password must be at least 8 characters.'
    password_hash = bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')
    try:
        with get_db() as db:
            cur = db.execute("UPDATE users SET password=? WHERE email=?", (password_hash, email))
            db.commit()
        return (cur.rowcount > 0, 'Password updated.' if cur.rowcount > 0 else 'User not found.')
    except Exception as e:
        log.error(f'update_user_password: {e}')
        return False, 'Failed to update password.'


def delete_user(email: str, current_email: str) -> tuple[bool, str]:
    if email.strip().lower() == current_email.strip().lower():
        return False, 'You cannot delete the account currently signed into the bot.'
    try:
        with get_db() as db:
            cur = db.execute("DELETE FROM users WHERE email=?", (email,))
            db.execute("DELETE FROM tg_bot_sessions WHERE email=?", (email,))
            db.commit()
        return (cur.rowcount > 0, 'User deleted.' if cur.rowcount > 0 else 'User not found.')
    except Exception as e:
        log.error(f'delete_user: {e}')
        return False, 'Failed to delete user.'


def fetch_table_counts(limit: int = 30) -> list:
    with get_db() as db:
        tables = db.execute(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name ASC"
        ).fetchall()
        out = []
        for row in tables[:limit]:
            name = str(row['name'])
            safe = name.replace('"', '""')
            try:
                count = int(db.execute(f'SELECT COUNT(*) AS cnt FROM "{safe}"').fetchone()['cnt'])
            except Exception:
                count = -1
            out.append((name, count))
        return out


def get_db_stats() -> dict:
    with get_db() as db:
        size = os.path.getsize(DB_PATH) if os.path.exists(DB_PATH) else 0
        modified = int(os.path.getmtime(DB_PATH)) if os.path.exists(DB_PATH) else 0
        page_count = int(db.execute('PRAGMA page_count').fetchone()[0])
        page_size = int(db.execute('PRAGMA page_size').fetchone()[0])
        free_pages = int(db.execute('PRAGMA freelist_count').fetchone()[0])
        table_count = int(
            db.execute(
                "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
            ).fetchone()[0]
        )
        return {
            'file_size': size,
            'modified': modified,
            'page_count': page_count,
            'page_size': page_size,
            'free_pages': free_pages,
            'table_count': table_count,
        }


def run_db_vacuum() -> tuple[bool, str]:
    try:
        with get_db() as db:
            db.execute('VACUUM')
            db.commit()
        return True, 'VACUUM complete.'
    except Exception as e:
        log.error(f'run_db_vacuum: {e}')
        return False, 'VACUUM failed.'


def build_users_list_message(rows: list) -> str:
    if not rows:
        return '<b>Users list</b>\n<code>────────────────────────</code>\n\n<i>No users found.</i>'
    lines = ['<b>Users list</b>', '<code>────────────────────────</code>', '']
    for row in rows:
        email = _esc(str(row['email']))
        name = _esc(str(row['name'] or '—'))
        role = role_name(int(row['role']))
        verified = 'yes' if int(row['verified'] or 0) else 'no'
        lang = _esc(str(row['language'] or '—'))
        theme = _esc(str(row['theme'] or '—'))
        lines.append(f'<b>{email}</b> · <code>{_esc(role)}</code> · verified <code>{verified}</code>')
        lines.append(f'<i>{name}</i> · lang <code>{lang}</code> · theme <code>{theme}</code>')
        lines.append('')
    lines.append('<i>Use Find user to manage a specific account.</i>')
    return '\n'.join(lines)


def build_user_message(row: sqlite3.Row) -> str:
    email = _esc(str(row['email']))
    name = _esc(str(row['name'] or '—'))
    role = _esc(role_name(int(row['role'] or 0)))
    verified = 'yes' if int(row['verified'] or 0) else 'no'
    language = _esc(str(row['language'] or '—'))
    theme = _esc(str(row['theme'] or '—'))
    color = _esc(str(row['color'] or '—'))
    created = _esc(str(row['created_at'] or '—'))
    return (
        '<b>User</b>\n'
        '<code>────────────────────────</code>\n\n'
        f'<b>Email:</b> <code>{email}</code>\n'
        f'<b>Name:</b> <code>{name}</code>\n'
        f'<b>Role:</b> <code>{role}</code>\n'
        f'<b>Verified:</b> <code>{verified}</code>\n'
        f'<b>Language:</b> <code>{language}</code>\n'
        f'<b>Theme:</b> <code>{theme}</code>\n'
        f'<b>Color:</b> <code>{color}</code>\n'
        f'<b>Created:</b> <code>{created}</code>'
    )


def build_db_stats_message(stats: dict) -> str:
    modified = _fmt_ts(stats['modified'])
    return (
        '<b>Database statistics</b>\n'
        '<code>────────────────────────</code>\n\n'
        f'<b>File size:</b> <code>{_esc(_fmt_bytes(int(stats["file_size"])))}</code>\n'
        f'<b>Modified:</b> <code>{modified}</code>\n'
        f'<b>Tables:</b> <code>{int(stats["table_count"])}</code>\n'
        f'<b>Pages:</b> <code>{int(stats["page_count"])}</code>\n'
        f'<b>Page size:</b> <code>{_esc(_fmt_bytes(int(stats["page_size"])))}</code>\n'
        f'<b>Free pages:</b> <code>{int(stats["free_pages"])}</code>'
    )


def build_tables_list_message(rows: list) -> str:
    if not rows:
        return '<b>Tables list</b>\n<code>────────────────────────</code>\n\n<i>No tables found.</i>'
    lines = ['<b>Tables list</b>', '<code>────────────────────────</code>', '']
    for name, count in rows:
        count_text = str(count) if count >= 0 else 'n/a'
        lines.append(f'<code>{_esc(name):<28s}</code> <b>{count_text}</b>')
    return '\n'.join(lines)


def prompt_find_user(token: str, chat_id: str, email: str) -> None:
    set_admin_menu(chat_id, 'admin_users')
    state = get_chat_state(chat_id)
    state['step'] = 'admin_find_user_email'
    tg_send(
        token,
        chat_id,
        'Send the email of the user you want to manage.',
        parse_mode='',
        reply_markup=admin_reply_keyboard(chat_id, is_root_email(email), 'admin_users'),
    )


def show_selected_user(token: str, chat_id: str, admin_email: str, user_email: str) -> None:
    row = get_user_by_email(user_email)
    if not row:
        set_admin_menu(chat_id, 'admin_users')
        state = get_chat_state(chat_id)
        state.pop('selected_user_email', None)
        tg_send(
            token,
            chat_id,
            'User not found.',
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root_email(admin_email), 'admin_users'),
        )
        return
    state = clear_admin_step(chat_id)
    state['selected_user_email'] = str(row['email'])
    state['selected_user_name'] = str(row['name'] or '')
    set_admin_menu(chat_id, 'admin_user_actions')
    tg_send(
        token,
        chat_id,
        build_user_message(row),
        reply_markup=admin_reply_keyboard(chat_id, is_root_email(admin_email), 'admin_user_actions'),
    )


def handle_admin_workflow_input(
    token: str,
    chat_id: str,
    admin_email: str,
    text: str,
) -> bool:
    state = get_chat_state(chat_id)
    step = state.get('step', '')
    if not step:
        return False
    cmd = resolve_command(text)
    if cmd == '/cancel':
        clear_admin_step(chat_id)
        fallback_menu = 'admin_users' if step.startswith('admin_') else 'admin_main'
        set_admin_menu(chat_id, fallback_menu)
        tg_send(
            token,
            chat_id,
            'Action cancelled.',
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root_email(admin_email), fallback_menu),
        )
        return True

    if step == 'admin_find_user_email':
        if cmd:
            return False
        show_selected_user(token, chat_id, admin_email, text.strip())
        return True

    if step == 'admin_add_user_email':
        if cmd:
            return False
        email = text.strip().lower()
        if not is_valid_email(email):
            tg_send(token, chat_id, 'Invalid email. Send a valid email address.', parse_mode='')
            return True
        state['draft'] = {'email': email}
        state['step'] = 'admin_add_user_password'
        tg_send(token, chat_id, 'Send the password for the new user. Minimum 8 characters.', parse_mode='')
        return True

    if step == 'admin_add_user_password':
        if cmd:
            return False
        if len(text) < 8:
            tg_send(token, chat_id, 'Password must be at least 8 characters. Send another password.', parse_mode='')
            return True
        state.setdefault('draft', {})['password'] = text
        state['step'] = 'admin_add_user_name'
        tg_send(token, chat_id, 'Send display name for the user, or "-" to use the email prefix.', parse_mode='')
        return True

    if step == 'admin_add_user_name':
        if cmd:
            return False
        name = '' if text.strip() == '-' else text.strip()
        state.setdefault('draft', {})['name'] = name
        state['step'] = 'admin_add_user_role'
        set_admin_menu(chat_id, 'admin_role_select')
        tg_send(
            token,
            chat_id,
            'Choose the role for the new user.',
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root_email(admin_email), 'admin_role_select'),
        )
        return True

    if step == 'admin_add_user_role':
        if cmd not in ('/set_role_0', '/set_role_1', '/set_role_2'):
            tg_send(token, chat_id, 'Use the role buttons below.', parse_mode='')
            return True
        state.setdefault('draft', {})['role'] = int(cmd.rsplit('_', 1)[-1])
        state['step'] = 'admin_add_user_verified'
        set_admin_menu(chat_id, 'admin_verified_select')
        tg_send(
            token,
            chat_id,
            'Choose whether the new user is verified.',
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root_email(admin_email), 'admin_verified_select'),
        )
        return True

    if step == 'admin_add_user_verified':
        if cmd not in ('/set_verified_1', '/set_verified_0'):
            tg_send(token, chat_id, 'Use the verification buttons below.', parse_mode='')
            return True
        draft = state.get('draft') or {}
        verified = int(cmd.rsplit('_', 1)[-1])
        ok, msg = create_user(
            draft.get('email', ''),
            draft.get('password', ''),
            draft.get('name', ''),
            int(draft.get('role', 0)),
            verified,
        )
        clear_admin_step(chat_id)
        set_admin_menu(chat_id, 'admin_users')
        tg_send(
            token,
            chat_id,
            msg,
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root_email(admin_email), 'admin_users'),
        )
        if ok:
            show_selected_user(token, chat_id, admin_email, draft.get('email', ''))
        return True

    if step == 'admin_change_role':
        if cmd not in ('/set_role_0', '/set_role_1', '/set_role_2'):
            tg_send(token, chat_id, 'Use the role buttons below.', parse_mode='')
            return True
        user_email = str(state.get('selected_user_email') or '')
        ok, msg = update_user_role(user_email, int(cmd.rsplit('_', 1)[-1]))
        clear_admin_step(chat_id)
        if not ok:
            set_admin_menu(chat_id, 'admin_users')
            tg_send(
                token,
                chat_id,
                msg,
                parse_mode='',
                reply_markup=admin_reply_keyboard(chat_id, is_root_email(admin_email), 'admin_users'),
            )
            return True
        show_selected_user(token, chat_id, admin_email, user_email)
        tg_send(
            token,
            chat_id,
            msg,
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root_email(admin_email), 'admin_user_actions'),
        )
        return True

    if step == 'admin_reset_password':
        if cmd:
            return False
        user_email = str(state.get('selected_user_email') or '')
        ok, msg = update_user_password(user_email, text)
        clear_admin_step(chat_id)
        if not ok:
            set_admin_menu(chat_id, 'admin_user_actions')
            tg_send(
                token,
                chat_id,
                msg,
                parse_mode='',
                reply_markup=admin_reply_keyboard(chat_id, is_root_email(admin_email), 'admin_user_actions'),
            )
            return True
        tg_send(
            token,
            chat_id,
            msg,
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root_email(admin_email), 'admin_user_actions'),
        )
        return True

    if step == 'admin_delete_user_confirm':
        if cmd != '/confirm_delete_user':
            tg_send(token, chat_id, 'Use Confirm delete or Cancel.', parse_mode='')
            return True
        user_email = str(state.get('selected_user_email') or '')
        ok, msg = delete_user(user_email, admin_email)
        clear_admin_step(chat_id)
        set_admin_menu(chat_id, 'admin_users')
        state.pop('selected_user_email', None)
        state.pop('selected_user_name', None)
        tg_send(
            token,
            chat_id,
            msg,
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root_email(admin_email), 'admin_users'),
        )
        return True

    return False


def dispatch_admin_command(
    token: str,
    chat_id: str,
    admin_email: str,
    cmd: str,
    kb: dict,
) -> None:
    is_root = is_root_email(admin_email)
    state = get_chat_state(chat_id)
    if cmd in ('/start', '/help'):
        clear_admin_step(chat_id)
        set_admin_menu(chat_id, 'admin_main')
        tg_send(token, chat_id, HELP_ADMIN, reply_markup=kb)
    elif cmd == '/menu_monitoring':
        clear_admin_step(chat_id)
        set_admin_menu(chat_id, 'admin_monitoring')
        tg_send(
            token,
            chat_id,
            'Monitoring group. Choose the metrics you want to view. A couple of quick health checks are included too.',
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_monitoring'),
        )
    elif cmd == '/menu_errors':
        clear_admin_step(chat_id)
        set_admin_menu(chat_id, 'admin_errors')
        tg_send(
            token,
            chat_id,
            'Errors group. Choose which error report to open.',
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_errors'),
        )
    elif cmd == '/menu_users':
        clear_admin_step(chat_id)
        set_admin_menu(chat_id, 'admin_users')
        tg_send(
            token,
            chat_id,
            'Users group. You can list users, find one user, or add a new account.',
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_users'),
        )
    elif cmd == '/menu_database':
        clear_admin_step(chat_id)
        set_admin_menu(chat_id, 'admin_database')
        tg_send(
            token,
            chat_id,
            'Database group. Choose a database action.',
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_database'),
        )
    elif cmd == '/menu_back_main':
        clear_admin_step(chat_id)
        set_admin_menu(chat_id, 'admin_main')
        tg_send(
            token,
            chat_id,
            'Main admin menu.',
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_main'),
        )
    elif cmd == '/menu_back_users':
        clear_admin_step(chat_id)
        set_admin_menu(chat_id, 'admin_users')
        state.pop('selected_user_email', None)
        state.pop('selected_user_name', None)
        tg_send(
            token,
            chat_id,
            'Users group.',
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_users'),
        )
    elif cmd == '/logout':
        remove_session(chat_id)
        _state.pop(chat_id, None)
        tg_send(
            token,
            chat_id,
            'You have been signed out.',
            parse_mode='',
            reply_markup=remove_reply_keyboard(),
        )
    elif cmd == '/stats':
        set_admin_menu(chat_id, 'admin_monitoring')
        admin_kb = admin_reply_keyboard(chat_id, is_root, 'admin_monitoring')
        tg_send(token, chat_id, 'Collecting metrics…', parse_mode='', reply_markup=admin_kb)
        tg_send_chunks(token, chat_id, build_stats_message(get_live_stats()), reply_markup=admin_kb)
    elif cmd == '/status':
        menu = 'admin_monitoring' if state.get('menu') != 'admin_main' else 'admin_main'
        tg_send(token, chat_id, build_status_message(get_live_stats()), reply_markup=admin_reply_keyboard(chat_id, is_root, menu))
    elif cmd == '/uptime_charts':
        set_admin_menu(chat_id, 'admin_monitoring')
        admin_kb = admin_reply_keyboard(chat_id, is_root, 'admin_monitoring')
        tg_send(token, chat_id, 'Rendering uptime charts…', parse_mode='', reply_markup=admin_kb)
        send_uptime_charts(token, chat_id, admin_kb)
    elif cmd == '/backup_status':
        set_admin_menu(chat_id, 'admin_monitoring')
        admin_kb = admin_reply_keyboard(chat_id, is_root, 'admin_monitoring')
        tg_send(token, chat_id, 'Rendering backup report…', parse_mode='', reply_markup=admin_kb)
        send_backup_report(token, chat_id, admin_kb)
    elif cmd == '/docker_status':
        set_admin_menu(chat_id, 'admin_monitoring')
        admin_kb = admin_reply_keyboard(chat_id, is_root, 'admin_monitoring')
        tg_send(token, chat_id, 'Checking Docker status…', parse_mode='', reply_markup=admin_kb)
        tg_send_chunks(token, chat_id, build_docker_monitor_message(get_docker_monitor_data()), reply_markup=admin_kb)
    elif cmd == '/errors_recent':
        set_admin_menu(chat_id, 'admin_errors')
        admin_kb = admin_reply_keyboard(chat_id, is_root, 'admin_errors')
        tg_send(token, chat_id, 'Loading error log…', parse_mode='', reply_markup=admin_kb)
        tg_send_chunks(token, chat_id, build_errors_recent_message(fetch_smart_logs_recent()), reply_markup=admin_kb)
    elif cmd == '/errors_top':
        set_admin_menu(chat_id, 'admin_errors')
        admin_kb = admin_reply_keyboard(chat_id, is_root, 'admin_errors')
        tg_send(token, chat_id, 'Loading error log…', parse_mode='', reply_markup=admin_kb)
        tg_send_chunks(token, chat_id, build_errors_top_message(fetch_smart_logs_top()), reply_markup=admin_kb)
    elif cmd == '/users_list':
        set_admin_menu(chat_id, 'admin_users')
        tg_send_chunks(
            token,
            chat_id,
            build_users_list_message(list_users()),
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_users'),
        )
    elif cmd == '/user_find':
        prompt_find_user(token, chat_id, admin_email)
    elif cmd == '/user_add':
        clear_admin_step(chat_id)
        state['step'] = 'admin_add_user_email'
        set_admin_menu(chat_id, 'admin_users')
        tg_send(
            token,
            chat_id,
            'Send the email for the new user.',
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_users'),
        )
    elif cmd == '/user_change_role':
        if not state.get('selected_user_email'):
            prompt_find_user(token, chat_id, admin_email)
            return
        state['step'] = 'admin_change_role'
        set_admin_menu(chat_id, 'admin_role_select')
        tg_send(
            token,
            chat_id,
            f'Choose a new role for <code>{_esc(str(state.get("selected_user_email")))}</code>.',
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_role_select'),
        )
    elif cmd == '/user_toggle_verify':
        if not state.get('selected_user_email'):
            prompt_find_user(token, chat_id, admin_email)
            return
        row = get_user_by_email(str(state.get('selected_user_email')))
        if not row:
            prompt_find_user(token, chat_id, admin_email)
            return
        new_verified = 0 if int(row['verified'] or 0) else 1
        ok, msg = update_user_verified(str(row['email']), new_verified)
        if not ok:
            tg_send(token, chat_id, msg, parse_mode='', reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_user_actions'))
            return
        show_selected_user(token, chat_id, admin_email, str(row['email']))
        tg_send(token, chat_id, msg, parse_mode='', reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_user_actions'))
    elif cmd == '/user_reset_password':
        if not state.get('selected_user_email'):
            prompt_find_user(token, chat_id, admin_email)
            return
        state['step'] = 'admin_reset_password'
        set_admin_menu(chat_id, 'admin_user_actions')
        tg_send(
            token,
            chat_id,
            f'Send a new password for <code>{_esc(str(state.get("selected_user_email")))}</code>.',
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_user_actions'),
        )
    elif cmd == '/user_delete':
        if not state.get('selected_user_email'):
            prompt_find_user(token, chat_id, admin_email)
            return
        state['step'] = 'admin_delete_user_confirm'
        set_admin_menu(chat_id, 'admin_delete_confirm')
        tg_send(
            token,
            chat_id,
            f'Confirm deletion of <code>{_esc(str(state.get("selected_user_email")))}</code>.',
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_delete_confirm'),
        )
    elif cmd == '/db_stats':
        set_admin_menu(chat_id, 'admin_database')
        tg_send(
            token,
            chat_id,
            build_db_stats_message(get_db_stats()),
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_database'),
        )
    elif cmd == '/db_tables':
        set_admin_menu(chat_id, 'admin_database')
        tg_send_chunks(
            token,
            chat_id,
            build_tables_list_message(fetch_table_counts()),
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_database'),
        )
    elif cmd == '/db_vacuum':
        if not is_root:
            tg_send(
                token,
                chat_id,
                'VACUUM is available only for root@root.',
                parse_mode='',
                reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_database'),
            )
            return
        ok, msg = run_db_vacuum()
        tg_send(
            token,
            chat_id,
            msg,
            parse_mode='',
            reply_markup=admin_reply_keyboard(chat_id, is_root, 'admin_database'),
        )
    else:
        tg_send(
            token,
            chat_id,
            'Unknown command. Use the keyboard or /help.',
            reply_markup=kb,
        )


def dispatch_limited_command(token: str, chat_id: str, cmd: str, kb: dict) -> None:
    if cmd in ('/start', '/help'):
        tg_send(token, chat_id, HELP_LIMITED, reply_markup=kb)
    elif cmd == '/logout':
        remove_session(chat_id)
        _state.pop(chat_id, None)
        tg_send(
            token,
            chat_id,
            'You have been signed out.',
            parse_mode='',
            reply_markup=remove_reply_keyboard(),
        )
    elif cmd == '/status':
        tg_send(token, chat_id, build_status_message(get_live_stats()), reply_markup=kb)
    else:
        tg_send(token, chat_id, DENIED_NON_ADMIN, reply_markup=kb)


def handle_update(token: str, update: dict) -> None:
    msg = update.get('message') or update.get('edited_message')
    if not msg:
        return
    chat_id = str(msg['chat']['id'])
    text = msg.get('text', '').strip()
    if not text:
        return
    tg_uid = msg.get('from', {}).get('id')

    email = get_session(chat_id)

    if email:
        role = get_session_role(chat_id)
        if role is None:
            remove_session(chat_id)
            _state.pop(chat_id, None)
            tg_send(token, chat_id, 'Session invalid. Send any message to sign in.', parse_mode='')
            return
        sync_telegram_user_id(chat_id, tg_uid)
        is_admin = role >= 2
        if is_admin and handle_admin_workflow_input(token, chat_id, email, text):
            return
        cmd = resolve_command(text)
        kb = admin_reply_keyboard(chat_id, is_root_email(email)) if is_admin else limited_reply_keyboard()
        if not cmd:
            tg_send(
                token,
                chat_id,
                'Unknown input. Use the buttons below.',
                reply_markup=kb,
            )
            return
        if is_admin:
            dispatch_admin_command(token, chat_id, email, cmd, kb)
        else:
            allowed_limited = {'/start', '/help', '/status', '/logout'}
            if cmd not in allowed_limited:
                tg_send(token, chat_id, DENIED_NON_ADMIN, reply_markup=kb)
                return
            dispatch_limited_command(token, chat_id, cmd, kb)
        return

    linked_email = redeem_link_token(text, chat_id, tg_uid)
    if linked_email:
        clear_admin_step(chat_id)
        set_admin_menu(chat_id, 'admin_main')
        tg_send(
            token,
            chat_id,
            '<b>Telegram linked.</b>\n\nAccount: <code>' + _esc(linked_email) + '</code>',
            reply_markup=admin_reply_keyboard(chat_id, is_root_email(linked_email), 'admin_main'),
        )
        return
    tg_send(
        token,
        chat_id,
        '<b>Send a one-time binding code.</b>\n\nGenerate it in the server admin panel: Settings → Telegram Bot → Generate code.',
    )


def main() -> None:
    log.info(f'Starting bot. DB: {DB_PATH}')
    ensure_schema()
    log.info('Bot admin actions are handled inside the bot via grouped keyboards.')
    offset = 0
    retry_delay = 5

    while True:
        token = get_token()
        if not token:
            log.warning('Bot token is not configured. Retrying in 30 seconds.')
            time.sleep(30)
            continue

        try:
            updates = tg_get_updates(token, offset)
            retry_delay = 5
            for update in updates:
                try:
                    handle_update(token, update)
                except Exception as e:
                    log.error(f'handle_update error (update_id={update.get("update_id")}): {e}')
                offset = update['update_id'] + 1
            if not updates:
                time.sleep(POLL_INTERVAL)
        except Exception as e:
            log.error(f'Polling error: {e}. Retrying in {retry_delay}s.')
            time.sleep(retry_delay)
            retry_delay = min(retry_delay * 2, 60)


if __name__ == '__main__':
    main()
