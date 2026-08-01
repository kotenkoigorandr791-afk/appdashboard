#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
command -v graphify >/dev/null
GRAPHIFY_FORCE="${GRAPHIFY_FORCE:-1}" graphify update "$ROOT" --force
