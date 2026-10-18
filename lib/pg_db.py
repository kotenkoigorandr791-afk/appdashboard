import os
import psycopg2
import psycopg2.extras

def load_db_config():
    path = os.environ.get('APP_DB_CONFIG', '/opt/AppDashboard/data/db.env')
    cfg = {'host': '127.0.0.1', 'port': '5432', 'name': 'appdashboard', 'user': 'appdashboard', 'password': ''}
    if os.path.isfile(path):
        with open(path, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith('#') or '=' not in line:
                    continue
                k, v = line.split('=', 1)
                k = k.strip().lower()
                v = v.strip()
                if k == 'db_host': cfg['host'] = v
                elif k == 'db_port': cfg['port'] = v
                elif k == 'db_name': cfg['name'] = v
                elif k == 'db_user': cfg['user'] = v
                elif k == 'db_password': cfg['password'] = v
    for env_key, cfg_key in (
        ('DB_HOST', 'host'),
        ('DB_PORT', 'port'),
        ('DB_NAME', 'name'),
        ('DB_USER', 'user'),
        ('DB_PASSWORD', 'password'),
    ):
        val = os.environ.get(env_key)
        if val:
            cfg[cfg_key] = val
    return cfg

def get_conn():
    c = load_db_config()
    conn = psycopg2.connect(host=c['host'], port=c['port'], dbname=c['name'], user=c['user'], password=c['password'])
    conn.autocommit = False
    return conn

def dict_cursor(conn):
    return conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)
