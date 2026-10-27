#!/usr/bin/env python3
"""
Load generator for the AppDashboard stack.

No dedicated load tool (k6/wrk/ab) is available in this environment, so this
script drives concurrent users against the real nginx -> php-fpm -> postgres
path and reports latency percentiles, throughput and error rates.

Usage:
  python3 deploy/scripts/loadtest.py --base http://127.0.0.1 --scenario mixed --users 20 --duration 30
"""
from __future__ import annotations

import argparse
import json
import os
import statistics
import sys
import threading
import time
from dataclasses import dataclass, field
from typing import Callable

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry


@dataclass
class Endpoint:
    name: str
    path: str
    weight: int = 1
    expect_status: int = 200


@dataclass
class Result:
    name: str
    status: int
    elapsed_ms: float
    error: str = ""
    bytes: int = 0


@dataclass
class Stats:
    results: list[Result] = field(default_factory=list)
    lock: threading.Lock = field(default_factory=threading.Lock)

    def add(self, r: Result) -> None:
        with self.lock:
            self.results.append(r)


SCENARIOS: dict[str, list[Endpoint]] = {
    # Static-ish public pages, the most common user activity.
    "browse": [
        Endpoint("home", "/", weight=3),
        Endpoint("login", "/login", weight=1),
        Endpoint("recipes_page", "/apps/recipes/", weight=2),
    ],
    # JSON API endpoints of the recipes app.
    "api": [
        Endpoint("get_products", "/apps/recipes/?get_products=1", weight=4),
        Endpoint("get_recipes", "/apps/recipes/?get_recipes=1", weight=3),
        Endpoint("get_recipe_full", "/apps/recipes/?get_recipe_full=1&recipe_id=borscht", weight=2),
    ],
    # Everything a logged-in user does, mixed.
    "mixed": [
        Endpoint("home", "/", weight=3),
        Endpoint("recipes_page", "/apps/recipes/", weight=3),
        Endpoint("get_products", "/apps/recipes/?get_products=1", weight=3),
        Endpoint("get_recipes", "/apps/recipes/?get_recipes=1", weight=2),
        Endpoint("get_recipe_full", "/apps/recipes/?get_recipe_full=1&recipe_id=borscht", weight=2),
        Endpoint("login", "/login", weight=1),
    ],
}


def make_session() -> requests.Session:
    s = requests.Session()
    retry = Retry(total=0, connect=0, read=0, status=0)
    adapter = HTTPAdapter(pool_connections=64, pool_maxsize=64, max_retries=retry)
    s.mount("http://", adapter)
    s.mount("https://", adapter)
    s.headers.update({"User-Agent": "appdashboard-loadtest/1.0", "Connection": "keep-alive"})
    return s


def pick(endpoints: list[Endpoint]) -> Endpoint:
    total = sum(e.weight for e in endpoints)
    r = random_below(total)
    upto = 0
    for e in endpoints:
        upto += e.weight
        if r < upto:
            return e
    return endpoints[-1]


def random_below(n: int) -> int:
    # Avoid importing random globally for the hot path clarity.
    import random

    return random.randrange(n)


def worker(base: str, endpoints: list[Endpoint], duration: float, stats: Stats, stop: threading.Event) -> None:
    session = make_session()
    deadline = time.perf_counter() + duration
    while not stop.is_set() and time.perf_counter() < deadline:
        ep = pick(endpoints)
        url = base + ep.path
        started = time.perf_counter()
        try:
            resp = session.get(url, timeout=30, allow_redirects=True)
            elapsed = (time.perf_counter() - started) * 1000
            stats.add(Result(ep.name, resp.status_code, elapsed, "", len(resp.content)))
        except Exception as exc:  # noqa: BLE001
            elapsed = (time.perf_counter() - started) * 1000
            stats.add(Result(ep.name, 0, elapsed, type(exc).__name__))
    session.close()


def percentile(values: list[float], pct: float) -> float:
    if not values:
        return 0.0
    ordered = sorted(values)
    k = (len(ordered) - 1) * pct / 100.0
    f = int(k)
    c = min(f + 1, len(ordered) - 1)
    if f == c:
        return ordered[f]
    return ordered[f] + (ordered[c] - ordered[f]) * (k - f)


def run_phase(base: str, endpoints: list[Endpoint], users: int, duration: float) -> dict:
    stats = Stats()
    stop = threading.Event()
    threads = [
        threading.Thread(target=worker, args=(base, endpoints, duration, stats, stop), daemon=True)
        for _ in range(users)
    ]
    started = time.perf_counter()
    for t in threads:
        t.start()
    for t in threads:
        t.join()
    elapsed = time.perf_counter() - started

    latencies = [r.elapsed_ms for r in stats.results]
    errors = [r for r in stats.results if r.status != 200]
    blocked = [r for r in stats.results if r.status == 403]
    wafs = [r for r in stats.results if r.status == 429]
    by_endpoint: dict[str, list[float]] = {}
    by_status: dict[str, int] = {}
    for r in stats.results:
        by_endpoint.setdefault(r.name, []).append(r.elapsed_ms)
        by_status[str(r.status)] = by_status.get(str(r.status), 0) + 1

    total = len(stats.results)
    return {
        "users": users,
        "duration_s": round(elapsed, 2),
        "requests": total,
        "rps": round(total / elapsed, 2) if elapsed else 0,
        "errors": len(errors),
        "error_rate_pct": round(100.0 * len(errors) / total, 2) if total else 0,
        "status_counts": by_status,
        "waf_blocked_403": len(blocked),
        "rate_limited_429": len(wafs),
        "latency_ms": {
            "min": round(min(latencies), 2) if latencies else 0,
            "p50": round(percentile(latencies, 50), 2),
            "p90": round(percentile(latencies, 90), 2),
            "p95": round(percentile(latencies, 95), 2),
            "p99": round(percentile(latencies, 99), 2),
            "max": round(max(latencies), 2) if latencies else 0,
            "mean": round(statistics.fmean(latencies), 2) if latencies else 0,
        },
        "by_endpoint_p95": {
            name: round(percentile(v, 95), 2) for name, v in sorted(by_endpoint.items())
        },
        "error_samples": [
            {"endpoint": e.name, "status": e.status, "error": e.error}
            for e in errors[:5]
        ],
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--base", default="http://127.0.0.1")
    parser.add_argument("--scenario", default="mixed", choices=sorted(SCENARIOS))
    parser.add_argument("--users", type=int, default=10)
    parser.add_argument("--duration", type=float, default=20)
    parser.add_argument("--ramp", default="", help="Comma separated user counts to test sequentially")
    parser.add_argument("--out", default="")
    args = parser.parse_args()

    endpoints = SCENARIOS[args.scenario]
    ramp = [int(x) for x in args.ramp.split(",") if x.strip()] if args.ramp else [args.users]

    all_results = []
    for users in ramp:
        print(f"--- {args.scenario} | users={users} | duration={args.duration}s ---", flush=True)
        res = run_phase(args.base, endpoints, users, args.duration)
        all_results.append(res)
        lat = res["latency_ms"]
        print(
            f"    rps={res['rps']} p50={lat['p50']}ms p95={lat['p95']}ms p99={lat['p99']}ms "
            f"max={lat['max']}ms errors={res['errors']} ({res['error_rate_pct']}%)",
            flush=True,
        )
        if res["error_samples"]:
            print(f"    error samples: {res['error_samples']}", flush=True)
        time.sleep(3)

    if args.out:
        with open(args.out, "w", encoding="utf-8") as fh:
            json.dump({"scenario": args.scenario, "results": all_results}, fh, indent=2)
        print(f"written: {args.out}")

    print(json.dumps(all_results, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
