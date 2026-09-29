#!/usr/bin/env python3
"""Cloud sync desktop client — stdlib only. See CLOUD_VIRTUAL_FOLDER_SDK.md"""
import argparse, base64, json, os, ssl, struct, sys, time, uuid, hashlib
from pathlib import Path
from urllib.request import Request, urlopen
class CloudWs:
    def __init__(self, url: str):
        self.url = url
        self.sock = None
        self._buf = b""
    def connect(self):
        from urllib.parse import urlparse
        import socket
        u = urlparse(self.url)
        host = u.hostname or "localhost"
        port = u.port or (443 if u.scheme == "wss" else 80)
        path = u.path or "/"
        self.sock = socket.create_connection((host, port), timeout=30)
        if u.scheme == "wss":
            self.sock = ssl.create_default_context().wrap_socket(self.sock, server_hostname=host)
        key = base64.b64encode(os.urandom(16)).decode()
        req = (
            f"GET {path} HTTP/1.1\r\nHost: {host}:{port}\r\nUpgrade: websocket\r\n"
            f"Connection: Upgrade\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Key: {key}\r\n\r\n"
        )
        self.sock.sendall(req.encode())
        resp = b""
        while b"\r\n\r\n" not in resp:
            resp += self.sock.recv(4096)
        if b"101" not in resp.split(b"\r\n", 1)[0]:
            raise RuntimeError("WebSocket handshake failed")
    def close(self):
        if self.sock:
            try: self.sock.close()
            except OSError: pass
            self.sock = None
    def _decode_frame(self):
        if len(self._buf) < 2: return None
        b2 = self._buf[1]
        masked = (b2 & 0x80) != 0
        length = b2 & 0x7F
        off = 2
        if length == 126:
            if len(self._buf) < 4: return None
            length = struct.unpack("!H", self._buf[2:4])[0]
            off = 4
        elif length == 127:
            if len(self._buf) < 10: return None
            length = struct.unpack("!Q", self._buf[2:10])[0]
            off = 10
        mask = b""
        if masked:
            if len(self._buf) < off + 4: return None
            mask = self._buf[off:off+4]
            off += 4
        if len(self._buf) < off + length: return None
        payload = self._buf[off:off+length]
        self._buf = self._buf[off+length:]
        if masked:
            payload = bytes(payload[i] ^ mask[i % 4] for i in range(len(payload)))
        return payload.decode("utf-8", errors="replace")
    def recv_json(self, timeout=120):
        self.sock.settimeout(timeout)
        while True:
            frame = self._decode_frame()
            if frame is not None:
                return json.loads(frame)
            chunk = self.sock.recv(65536)
            if not chunk: raise ConnectionError("socket closed")
            self._buf += chunk
    def send_json(self, msg):
        payload = json.dumps(msg, ensure_ascii=False).encode()
        header = bytearray([0x81])
        ln = len(payload)
        if ln < 126:
            header.append(ln)
        elif ln <= 65535:
            header.append(126)
            header.extend(struct.pack("!H", ln))
        else:
            header.append(127)
            header.extend(struct.pack("!Q", ln))
        self.sock.sendall(bytes(header) + payload)
    def call(self, op, **kw):
        rid = str(uuid.uuid4())
        self.send_json({"op": op, "req_id": rid, **kw})
        while True:
            msg = self.recv_json()
            if msg.get("req_id") == rid or msg.get("op") in ("event", "pong"):
                return msg
def bootstrap(base_url, token):
    url = base_url.rstrip("/") + "/sync_bootstrap.php"
    req = Request(url, headers={"X-Client-Auth": token})
    with urlopen(req, timeout=30) as r:
        return json.loads(r.read().decode())
def download_file(ws, path, chunk=262144):
    buf, off = b"", 0
    while True:
        r = ws.call("read", path=path, offset=off, length=chunk)
        if not r.get("ok", r.get("op") == "read_ok"):
            raise RuntimeError(r.get("message", r.get("error", "read failed")))
        buf += base64.b64decode(r["data_b64"])
        if r.get("eof"): return buf
        off += int(r.get("bytes", 0))
def upload_file(ws, path, data):
    b64 = base64.b64encode(data).decode()
    return ws.call("write", path=path, offset=0, truncate=True, close=True, data_b64=b64)
def pull_manifest(ws, cache_dir):
    offset = 0
    while True:
        page = ws.call("manifest_request", offset=offset, limit=500)
        for ent in page.get("entries", []):
            rel = ent["path"]
            dest = cache_dir / rel
            dest.parent.mkdir(parents=True, exist_ok=True)
            dest.write_bytes(download_file(ws, rel))
            print(f"  pull {rel}")
        if page.get("done"): break
        offset += len(page.get("entries", []))
def push_local(ws, cache_dir, state_file):
    state = {}
    if state_file.is_file():
        state = json.loads(state_file.read_text())
    for fp in cache_dir.rglob("*"):
        if not fp.is_file(): continue
        rel = str(fp.relative_to(cache_dir)).replace("\\", "/")
        mtime_ms = int(fp.stat().st_mtime * 1000)
        if state.get(rel) == mtime_ms: continue
        upload_file(ws, rel, fp.read_bytes())
        state[rel] = mtime_ms
        print(f"  push {rel}")
    state_file.write_text(json.dumps(state, indent=2))
def main():
    ap = argparse.ArgumentParser(description="Cloud virtual folder sync client")
    ap.add_argument("--base-url", required=True, help="https://host/apps/cloud")
    ap.add_argument("--token-file", default="token.txt")
    ap.add_argument("--cache", default="./CloudSync")
    ap.add_argument("--root", default="main")
    ap.add_argument("--base-path", default="")
    ap.add_argument("--mode", choices=["pull", "push", "sync", "watch"], default="sync")
    ap.add_argument("--interval", type=int, default=60)
    args = ap.parse_args()
    token = Path(args.token_file).read_text().strip()
    cache = Path(args.cache)
    cache.mkdir(parents=True, exist_ok=True)
    state_file = cache / ".cloud_sync_state.json"
    boot = bootstrap(args.base_url, token)
    if not boot.get("ok"):
        sys.exit(boot.get("error", "bootstrap failed"))
    ws_url = boot["ws_url"]
    if ws_url.startswith("ws://") and args.base_url.startswith("https"):
        ws_url = "wss://" + ws_url[5:]
    print(f"WS {ws_url}")
    ws = CloudWs(ws_url)
    ws.connect()
    auth = ws.call("auth", token=token, device_id=hashlib.sha256(os.uname().nodename.encode()).hexdigest()[:32],
                   root=args.root, base_path=args.base_path)
    if auth.get("op") != "auth_ok":
        sys.exit(auth.get("message", "auth failed"))
    print(f"OK user={auth.get('user')} mode={auth.get('sync_mode')}")
    def run_once():
        if args.mode in ("pull", "sync"):
            print("Pull...")
            pull_manifest(ws, cache)
        if args.mode in ("push", "sync"):
            print("Push...")
            push_local(ws, cache, state_file)
    try:
        if args.mode == "watch":
            while True:
                run_once()
                time.sleep(args.interval)
        else:
            run_once()
    finally:
        ws.close()
if __name__ == "__main__":
    main()
