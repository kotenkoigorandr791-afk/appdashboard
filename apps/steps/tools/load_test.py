#!/usr/bin/env python3
"""Load test for the NexSteps mobile API and the dashboard web UI.

Usage:
    LOADTEST_TOKEN=... python3 apps/steps/tools/load_test.py \
        --base http://127.0.0.1 --host-header 10.0.2.2 \
        --device-id 0123456789abcdef0123456789abcdef \
        --out apps/steps/tools/load_test_report.json

The token must belong to a throwaway account. Read scenarios use the same
endpoints the Android app calls; the ingest scenario writes steps for a
dedicated test date range so real data is never touched.
"""
from __future__ import annotations

import argparse
import json
import os
import statistics
import sys
import threading
import time
from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor
from datetime import date, timedelta

import requests

SCENARIOS = ("web_home", "web_login", "api_range", "api_metrics", "api_profile", "api_ingest")


def build_scenarios(base: str, header_host: str, device: str, token: str):
    auth = {
        "Authorization": f"Bearer {token}",
        "X-Device-Id": device,
        "Accept": "application/json",
    }
    if header_host:
        auth["Host"] = header_host
    web_headers = {"Host": header_host} if header_host else {}
    today = date.today()
    start = (today - timedelta(days=29)).isoformat()
    end = today.isoformat()
    test_day = date(1985, 1, 1)
    ingest_body = {
        "timestamp": int(time.time()),
        "app_version": "1.10.1-loadtest",
        "steps": [{"date": test_day.isoformat(), "count": 1234}],
    }
    return {
        "web_home": lambda: requests.get(base + "/", headers=web_headers, allow_redirects=False, timeout=30),
        "web_login": lambda: requests.get(base + "/login", headers=web_headers, timeout=30),
        "api_range": lambda: requests.get(
            base + f"/api/steps?from={start}&to={end}", headers=auth, timeout=30
        ),
        "api_metrics": lambda: requests.get(
            base + f"/api/steps?from={start}&to={end}&metrics=1", headers=auth, timeout=30
        ),
        "api_profile": lambda: requests.get(base + "/api/steps/profile", headers=auth, timeout=30),
        "api_ingest": lambda: requests.post(
            base + "/api/steps", headers={**auth, "Content-Type": "application/json"},
            json=ingest_body, timeout=30,
        ),
    }


def run_level(call, concurrency: int, duration: float, stop: threading.Event):
    latencies = []
    codes = defaultdict(int)
    errors = []
    lock = threading.Lock()

    def worker():
        local_lat = []
        local_codes = defaultdict(int)
        local_err = []
        while not stop.is_set():
            t0 = time.perf_counter()
            try:
                r = call()
                code = r.status_code
            except Exception as exc:  # network/timeout
                code = -1
                local_err.append(type(exc).__name__ + ": " + str(exc)[:120])
            local_lat.append((time.perf_counter() - t0) * 1000.0)
            local_codes[code] += 1
        with lock:
            latencies.extend(local_lat)
            for k, v in local_codes.items():
                codes[k] += v
            errors.extend(local_err[:5])

    start = time.perf_counter()
    threads = [threading.Thread(target=worker, daemon=True) for _ in range(concurrency)]
    for t in threads:
        t.start()
    time.sleep(duration)
    stop.set()
    for t in threads:
        t.join(timeout=35)
    elapsed = time.perf_counter() - start

    latencies.sort()

    def pct(p):
        if not latencies:
            return 0.0
        idx = min(len(latencies) - 1, int(len(latencies) * p / 100.0))
        return round(latencies[idx], 1)

    return {
        "concurrency": concurrency,
        "requests": len(latencies),
        "rps": round(len(latencies) / elapsed, 1) if elapsed else 0,
        "avg_ms": round(statistics.fmean(latencies), 1) if latencies else 0,
        "p50_ms": pct(50),
        "p95_ms": pct(95),
        "p99_ms": pct(99),
        "max_ms": round(latencies[-1], 1) if latencies else 0,
        "status": dict(sorted(codes.items())),
        "errors": errors,
    }


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="http://127.0.0.1")
    ap.add_argument("--host-header", default="")
    ap.add_argument("--device-id", required=True)
    ap.add_argument("--duration", type=float, default=8.0)
    ap.add_argument("--levels", default="1,5,10,25")
    ap.add_argument("--only", default="")
    ap.add_argument("--out", default="")
    args = ap.parse_args()

    token = os.environ.get("LOADTEST_TOKEN", "")
    if not token:
        print("LOADTEST_TOKEN is required", file=sys.stderr)
        return 2

    calls = build_scenarios(args.base, args.host_header, args.device_id, token)
    scenarios = [s for s in SCENARIOS if not args.only or s in args.only.split(",")]
    levels = [int(x) for x in args.levels.split(",") if x.strip()]
    report = {"base": args.base, "generated_at": time.strftime("%Y-%m-%dT%H:%M:%S"), "results": {}}

    for name in scenarios:
        print(f"== {name}", flush=True)
        rows = []
        for level in levels:
            stop = threading.Event()
            row = run_level(calls[name], level, args.duration, stop)
            rows.append(row)
            print(json.dumps(row), flush=True)
        report["results"][name] = rows

    if args.out:
        with open(args.out, "w", encoding="utf-8") as fh:
            json.dump(report, fh, indent=2)
        print("wrote " + args.out)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
