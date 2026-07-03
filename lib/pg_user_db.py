import re
import sys
import os
sys.path.insert(0, os.path.dirname(__file__))
from pg_db import get_conn

def user_schema_id(app_prefix: str, email: str) -> str:
    raw = (app_prefix + '_' + re.sub(r'[^a-zA-Z0-9._+-]+', '_', email)).lower()
    raw = re.sub(r'_+', '_', raw.strip('_'))
    return raw[:55]

def connect_user_schema(app_prefix: str, email: str):
    schema = user_schema_id(app_prefix, email)
    conn = get_conn()
    cur = conn.cursor()
    cur.execute(f'SET search_path TO "{schema.replace(chr(34), chr(34)+chr(34))}", public')
    cur.close()
    return conn
