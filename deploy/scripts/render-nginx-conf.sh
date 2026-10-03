#!/usr/bin/env python3
import os
import sys
from pathlib import Path

def main() -> int:
    if len(sys.argv) != 3:
        print("usage: render-nginx-conf.sh <template> <output>", file=sys.stderr)
        return 1
    template = Path(sys.argv[1])
    output = Path(sys.argv[2])
    if not template.is_file():
        print(f"nginx template not found: {template}", file=sys.stderr)
        return 1

    base = (os.environ.get("BASE_DOMAIN") or "localhost").strip().lower()
    if base in ("localhost", "127.0.0.1") or base.endswith(".local"):
        default_scheme = "http"
    else:
        default_scheme = "https"
    scheme = (os.environ.get("APP_PUBLIC_SCHEME") or default_scheme).strip().lower() or default_scheme
    ai_host = (os.environ.get("AI_PUBLIC_HOST") or f"ai.{base}").strip()
    ai_url = (os.environ.get("AI_PUBLIC_URL") or f"{scheme}://{ai_host}").rstrip("/")
    # Prefer explicit upstream; default to local OmniRoute port (prod: same host; docker: set OMNIROUTE_UPSTREAM).
    omni_upstream = (os.environ.get("OMNIROUTE_UPSTREAM") or "http://127.0.0.1:20128").rstrip("/")
    gitea_upstream = (os.environ.get("GITEA_INTERNAL_URL") or "http://gitea:3000").rstrip("/")
    immich_upstream = (os.environ.get("IMMICH_INTERNAL_URL") or "http://immich-server:2283").rstrip("/")
    dawarich_upstream = (os.environ.get("DAWARICH_INTERNAL_URL") or "http://dawarich:3000").rstrip("/")
    app_upstream = (os.environ.get("APP_UPSTREAM") or "127.0.0.1:8080").strip()
    gitea_proxy_host = (os.environ.get("GITEA_PROXY_HOST") or f"git.{base}").strip()
    immich_proxy_host = (os.environ.get("IMMICH_PROXY_HOST") or f"photo.{base}").strip()
    dawarich_proxy_host = (os.environ.get("DAWARICH_PROXY_HOST") or f"dawarich.{base}").strip()
    app_url = (os.environ.get("APP_PUBLIC_BASE_URL") or f"{scheme}://{base}").rstrip("/")
    app_url_http = f"http://{base}"
    repl = {
        "__AI_HOST_ESC__": ai_host.replace(".", r"\."),
        "__BASE_DOMAIN_ESC__": base.replace(".", r"\."),
        "__AI_HOST__": ai_host,
        "__BASE_DOMAIN__": base,
        "__OMNIROUTE_UPSTREAM__": omni_upstream,
        "__GITEA_UPSTREAM__": gitea_upstream,
        "__IMMICH_UPSTREAM__": immich_upstream,
        "__DAWARICH_UPSTREAM__": dawarich_upstream,
        "__APP_UPSTREAM__": app_upstream,
        "__GITEA_PROXY_HOST__": gitea_proxy_host,
        "__IMMICH_PROXY_HOST__": immich_proxy_host,
        "__DAWARICH_PROXY_HOST__": dawarich_proxy_host,
        "__APP_PUBLIC_URL_HTTP__": app_url_http,
        "__APP_PUBLIC_URL__": app_url,
        "__AI_PUBLIC_URL__": ai_url,
    }
    text = template.read_text(encoding="utf-8")
    for key, value in repl.items():
        text = text.replace(key, value)
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(text, encoding="utf-8")
    os.chmod(output, 0o644)
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
