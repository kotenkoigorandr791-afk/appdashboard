#!/bin/sh
set -eu
ROOT="${1:-$(pwd)}"
GRADLE_FILE="$ROOT/apps/steps/steps-mobile-app/app/build.gradle.kts"
test -f "$GRADLE_FILE"
patch="$(sed -n 's/^val appVersionPatch = \([0-9][0-9]*\)$/\1/p' "$GRADLE_FILE" | head -n 1)"
test -n "$patch"
next="$((patch + 1))"
sed -i "s/^val appVersionPatch = $patch$/val appVersionPatch = $next/" "$GRADLE_FILE"
version="$(sed -n 's/^val appVersionMajor = \([0-9][0-9]*\)$/\1/p' "$GRADLE_FILE" | head -n 1).$(sed -n 's/^val appVersionMinor = \([0-9][0-9]*\)$/\1/p' "$GRADLE_FILE" | head -n 1).$next"
printf '%s\n' "$version"
