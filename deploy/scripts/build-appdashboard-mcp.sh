#!/bin/sh
set -euo pipefail
ROOT="${1:-}"
if [ -z "$ROOT" ]; then
  ROOT="$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)"
fi
MCP_DIR="$ROOT/appdashboard-mcp"
OUT_DIR="$ROOT/downloads"
if [ ! -f "$MCP_DIR/package.json" ]; then
  echo "appdashboard-mcp not found at $MCP_DIR" >&2
  exit 1
fi
command -v node >/dev/null 2>&1 || { echo "node is required" >&2; exit 1; }
command -v npm >/dev/null 2>&1 || { echo "npm is required" >&2; exit 1; }
mkdir -p "$OUT_DIR"
cd "$MCP_DIR"
npm ci
npm run build
chmod +x dist/index.js 2>/dev/null || true
VERSION="$(node -p "require('./package.json').version")"
EXPECTED="appdashboard-mcp-${VERSION}.tgz"
rm -f "$OUT_DIR/$EXPECTED" "$OUT_DIR/appdashboard-mcp.tgz"
# Quiet pack to destination; filename is deterministic from package.json
npm pack --silent --pack-destination "$OUT_DIR" >/dev/null
SRC="$OUT_DIR/$EXPECTED"
if [ ! -f "$SRC" ]; then
  echo "npm pack did not produce $SRC" >&2
  ls -la "$OUT_DIR" >&2 || true
  exit 1
fi
cp -f "$SRC" "$OUT_DIR/appdashboard-mcp.tgz"
node <<EOF
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const pkg = require('./package.json');
const tgz = path.join('$OUT_DIR', 'appdashboard-mcp.tgz');
const buf = fs.readFileSync(tgz);
const st = fs.statSync(tgz);
fs.writeFileSync(path.join('$OUT_DIR', 'appdashboard-mcp.json'), JSON.stringify({
  name: pkg.name,
  version: pkg.version,
  file: 'appdashboard-mcp.tgz',
  size: st.size,
  built_at: Math.floor(Date.now() / 1000),
  sha256: crypto.createHash('sha256').update(buf).digest('hex')
}, null, 2) + '\n');
EOF
echo "Built MCP package: $OUT_DIR/appdashboard-mcp.tgz (v$VERSION)"
