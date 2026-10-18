#!/bin/sh
# Auto-install missing TinyTeX packages / TFMs, then retry the real engine.
set -eu

ENGINE="$(basename "$0")"
BINDIR_FILE="${TINYTEX_BINDIR_FILE:-/etc/appdashboard/tinytex-bindir}"
if [ -f "$BINDIR_FILE" ]; then
  BINDIR="$(cat "$BINDIR_FILE")"
else
  BINDIR="$(dirname "$(readlink -f /opt/TinyTeX/bin/*/xelatex 2>/dev/null | head -n1)" 2>/dev/null || true)"
fi
REAL="${BINDIR}/${ENGINE}"
TLMGR="${BINDIR}/tlmgr"
MKTEXTFM="${BINDIR}/mktextfm"
MAX_TRIES="${LATEX_AUTO_INSTALL_TRIES:-8}"

if [ ! -x "$REAL" ]; then
  echo "latex-engine-wrap: real engine not found: $REAL" >&2
  exit 127
fi

# latexmk cleanup (-c/-C) and help should never trigger package installs.
case " $* " in
  *" -c "*|*" -C "*|*" -h "*|*" --help "*|*" -v "*|*" --version "*)
    exec "$REAL" "$@"
    ;;
esac

extract_missing_files() {
  grep -Eho "File \`[^']+\.(sty|cls|tex|def|fd|cfg|clo|ldf|bbx|cbx|lbx|bst)' not found" \
    | sed -E "s/^File \`//; s/'\. not found$//; s/' not found$//" \
    | sort -u
  grep -Eho "I can't find file \`[^']+'" \
    | sed -E "s/^I can't find file \`//; s/'$//" \
    | grep -E '\.(sty|cls|tex|def|fd|cfg|clo|ldf)$' \
    | sort -u
}

extract_missing_tfms() {
  # Font T2A/cmr/bx/n/14.4=labx1440 at 14.4pt not loadable: Metric (TFM) file not found.
  grep -Eho 'Font [^ =]+=[A-Za-z0-9_-]+ at .* not loadable: Metric \(TFM\) file not found' \
    | sed -E 's/.*=([A-Za-z0-9_-]+) at .*/\1/' \
    | sort -u
  grep -Eho '! Font \\[^ =]+=[A-Za-z0-9_-]+ not loadable: Metric \(TFM\) file not found' \
    | sed -E 's/.*=([A-Za-z0-9_-]+) not loadable.*/\1/' \
    | sort -u
}

resolve_package() {
  file="$1"
  base="$(basename "$file")"
  name="${base%.*}"
  if [ -x "$TLMGR" ]; then
    hit="$("$TLMGR" search --file --global "/$base" 2>/dev/null | awk -F: 'NF>=1 && $1 !~ /^tlmgr/ {print $1; exit}')"
    if [ -n "$hit" ]; then
      echo "$hit"
      return 0
    fi
  fi
  echo "$name"
}

try=0
LOG="$(mktemp)"
trap 'rm -f "$LOG"' EXIT INT TERM

while [ "$try" -lt "$MAX_TRIES" ]; do
  set +e
  "$REAL" "$@" >"$LOG" 2>&1
  rc=$?
  set -e
  cat "$LOG"

  if [ "$rc" -eq 0 ]; then
    exit 0
  fi

  if [ "${LATEX_AUTO_INSTALL:-1}" != "1" ]; then
    exit "$rc"
  fi

  fixed=0

  missing_tfms="$(extract_missing_tfms <"$LOG" || true)"
  if [ -n "$missing_tfms" ] && [ -x "$MKTEXTFM" ]; then
    for tfm in $missing_tfms; do
      echo "latex-engine-wrap: missing TFM $tfm -> mktextfm" >&2
      if "$MKTEXTFM" "$tfm" >/dev/null 2>&1; then
        fixed=1
      else
        echo "latex-engine-wrap: mktextfm failed for $tfm" >&2
      fi
    done
  fi

  missing="$(extract_missing_files <"$LOG" || true)"
  if [ -n "$missing" ] && [ -x "$TLMGR" ]; then
    for file in $missing; do
      pkg="$(resolve_package "$file")"
      [ -n "$pkg" ] || continue
      echo "latex-engine-wrap: missing $file -> tlmgr install $pkg" >&2
      if "$TLMGR" install --no-depends-at-all "$pkg" >/dev/null 2>&1 \
        || "$TLMGR" install "$pkg" >/dev/null 2>&1; then
        fixed=1
      else
        echo "latex-engine-wrap: failed to install package $pkg" >&2
      fi
    done
  fi

  if [ "$fixed" -ne 1 ]; then
    exit "$rc"
  fi
  try=$((try + 1))
done

exit 1
