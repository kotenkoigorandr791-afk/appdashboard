#!/usr/bin/env python3
import os
import sys
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'lib'))
from pg_db import get_conn, dict_cursor
import psycopg2

def adapt_sql(sql: str) -> str:
    return sql.replace('?', '%s').replace("strftime('%s','now')", 'extract(epoch from now())::bigint')

def get_db():
    return get_conn()

def ensure_schema():
    with get_db() as db:
        cur = db.cursor()
        cur.execute("""
            CREATE TABLE IF NOT EXISTS tg_bot_sessions (
                chat_id TEXT PRIMARY KEY,
                email TEXT NOT NULL,
                authorized_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
                telegram_user_id TEXT NOT NULL DEFAULT ''
            )
        """)
        cur.execute("ALTER TABLE tg_bot_sessions ADD COLUMN IF NOT EXISTS telegram_user_id TEXT NOT NULL DEFAULT ''")
        db.commit()
