#!/bin/sh
set -eu
REPO="${1:-$(git rev-parse --show-toplevel)}"
TMP="$(mktemp -d "${TMPDIR:-/tmp}/appdashboard-deploy.XXXXXX")"
cleanup() {
  git -C "$REPO" worktree remove --force "$TMP" >/dev/null 2>&1 || true
}
trap cleanup EXIT
git -C "$REPO" fetch origin main
git -C "$REPO" worktree add --detach "$TMP" origin/main
version="$("$TMP/deploy/scripts/bump-stepmonitor-version.sh" "$TMP")"
git -C "$TMP" add apps/steps/steps-mobile-app/app/build.gradle.kts
git -C "$TMP" commit -m "StepMonitor: bump version to $version"
git -C "$TMP" push origin HEAD:main
printf 'Triggered main deploy with StepMonitor %s\n' "$version"
