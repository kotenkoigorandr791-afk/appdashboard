import re
import psycopg2.extras
from pg_db import get_conn

def _adapt_sql(sql: str) -> str:
    sql = sql.replace('?', '%s')
    sql = re.sub(r"strftime\s*\(\s*'%s'\s*,\s*'now'\s*\)", "extract(epoch from now())::bigint", sql, flags=re.I)
    sql = re.sub(r"datetime\s*\(\s*'now'\s*,\s*'localtime'\s*\)", "to_char(now(), 'YYYY-MM-DD HH24:MI:SS')", sql, flags=re.I)
    if re.search(r"INSERT\s+OR\s+IGNORE", sql, re.I):
        sql = re.sub(r"INSERT\s+OR\s+IGNORE", "INSERT", sql, flags=re.I)
        if "ON CONFLICT" not in sql.upper():
            sql = sql.rstrip().rstrip(';') + " ON CONFLICT DO NOTHING"
    if re.search(r"INSERT\s+OR\s+REPLACE", sql, re.I):
        sql = re.sub(r"INSERT\s+OR\s+REPLACE", "INSERT", sql, flags=re.I)
    if "sqlite_master" in sql:
        m = re.search(r"SELECT\s+1\s+FROM\s+sqlite_master\s+WHERE\s+type\s*=\s*'table'\s+AND\s+name\s*=\s*'([^']+)'\s+LIMIT\s+1", sql, re.I)
        if m:
            t = m.group(1).replace("'", "''")
            return f"SELECT 1 FROM pg_catalog.pg_tables WHERE schemaname='public' AND tablename='{t}' LIMIT 1"
        m = re.search(r"SELECT\s+COUNT\s*\(\*\)\s+FROM\s+sqlite_master\s+WHERE\s+type\s*=\s*'table'\s+AND\s+name\s+NOT\s+LIKE\s+'sqlite_%'", sql, re.I)
        if m:
            return "SELECT COUNT(*) FROM pg_catalog.pg_tables WHERE schemaname='public'"
        m = re.search(r"SELECT\s+name\s+FROM\s+sqlite_master\s+WHERE\s+type\s*=\s*'table'\s+AND\s+name\s+NOT\s+LIKE\s+'sqlite_%'\s+ORDER\s+BY\s+name\s+ASC", sql, re.I)
        if m:
            return "SELECT tablename AS name FROM pg_catalog.pg_tables WHERE schemaname='public' ORDER BY tablename ASC"
        sql = re.sub(r"SELECT name FROM sqlite_master WHERE type='table'", "SELECT tablename AS name FROM pg_catalog.pg_tables WHERE schemaname='public'", sql, flags=re.I)
    if re.match(r"PRAGMA\s+page_count\b", sql.strip(), re.I):
        return "SELECT 1 AS page_count"
    if re.match(r"PRAGMA\s+page_size\b", sql.strip(), re.I):
        return "SELECT 8192 AS page_size"
    if re.match(r"PRAGMA\s+freelist_count\b", sql.strip(), re.I):
        return "SELECT 0 AS freelist_count"
    m = re.match(r"PRAGMA\s+table_info\s*\(\s*([^\)]+)\s*\)", sql.strip(), re.I)
    if m:
        table = m.group(1).strip().strip("'\"")
        table = table.replace("'", "''")
        return (
            "SELECT (ordinal_position - 1)::int AS cid, column_name AS name, udt_name AS type, "
            "CASE WHEN is_nullable='NO' THEN 1 ELSE 0 END AS notnull, column_default AS dflt_value, 0 AS pk "
            f"FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = '{table}' "
            "ORDER BY ordinal_position"
        )
    if sql.strip().upper().startswith('VACUUM'):
        return 'VACUUM ANALYZE'
    m = re.search(r'\bINSERT\s+INTO\s+"?(\w+)"?', sql, re.I)
    if m and re.search(r'\bON\s+CONFLICT\b', sql, re.I):
        tbl = m.group(1)
        sql = re.sub(r'\bROUND\s*\(\s*amount\s*\+\s*excluded\.amount\s*,', rf'ROUND(({tbl}.amount+excluded.amount)::numeric,', sql, flags=re.I)
    sql = re.sub(r'\bROUND\s*\(\s*([^(),]+(?:\([^)]*\))?)\s*,\s*(\d+)\s*\)', r'ROUND((\1)::numeric, \2)', sql, flags=re.I)
    return sql

class PgCursor:
    def __init__(self, cur):
        self._cur = cur
    def fetchone(self):
        return self._cur.fetchone()
    def fetchall(self):
        return self._cur.fetchall()

def connect_user(app_prefix: str, email: str) -> 'PgConnection':
    from pg_user_db import user_schema_id
    schema = user_schema_id(app_prefix, email).replace('"', '""')
    conn = get_conn()
    with conn.cursor() as cur:
        cur.execute(f'SET search_path TO "{schema}", public')
    return PgConnection(conn)

class PgConnection:
    def __init__(self, conn=None):
        self._conn = conn if conn is not None else get_conn()
        self.row_factory = psycopg2.extras.RealDictCursor
    def execute(self, sql, params=()):
        sql = _adapt_sql(sql)
        cur = self._conn.cursor(cursor_factory=self.row_factory)
        cur.execute(sql, params or None)
        return PgCursor(cur)
    def executemany(self, sql, params):
        sql = _adapt_sql(sql)
        cur = self._conn.cursor(cursor_factory=self.row_factory)
        cur.executemany(sql, params)
        return PgCursor(cur)
    def commit(self):
        self._conn.commit()
    def rollback(self):
        self._conn.rollback()
    def close(self):
        self._conn.close()
    def __enter__(self):
        return self
    def __exit__(self, exc_type, exc, tb):
        if exc_type:
            self._conn.rollback()
        else:
            self._conn.commit()
        self.close()

Connection = PgConnection
Row = psycopg2.extras.RealDictRow

def connect(*args, **kwargs):
    return PgConnection()
