#!/bin/sh
# Install TinyTeX + common packages into a persistent volume (not the image).
set -eu

TINYTEX_HOME="${TINYTEX_HOME:-/opt/ApplicationData/tinytex}"
MARKER="$TINYTEX_HOME/.appdashboard-packages.ok"
PACKAGES="${TINYTEX_PACKAGES:-xetex latexmk fontspec luatex collection-fontsrecommended collection-langcyrillic geometry fancyvrb framed xcolor booktabs setspace ulem}"

mkdir -p "$TINYTEX_HOME"

# One-time migrate from image-baked TinyTeX (older builds) into the volume.
if [ -d /opt/TinyTeX/bin ] && [ ! -d "$TINYTEX_HOME/bin" ] && [ ! -L /opt/TinyTeX ]; then
  echo "ensure-tinytex: migrating image TinyTeX -> $TINYTEX_HOME"
  cp -a /opt/TinyTeX/. "$TINYTEX_HOME/" || true
fi

install_wrappers() {
  BINDIR="$1"
  WRAP_SRC="/var/www/AppDashboard/deploy/scripts/latex-engine-wrap.sh"
  WRAP_DST="/usr/local/lib/appdashboard/latex-engine-wrap.sh"
  mkdir -p /usr/local/lib/appdashboard /etc/appdashboard
  if [ -f "$WRAP_SRC" ]; then
    cp -f "$WRAP_SRC" "$WRAP_DST"
  fi
  chmod 0755 "$WRAP_DST" 2>/dev/null || true
  printf '%s\n' "$BINDIR" > /etc/appdashboard/tinytex-bindir
  for eng in xelatex pdflatex latexmk lualatex; do
    if [ -x "$BINDIR/$eng" ]; then
      ln -sfn "$WRAP_DST" "/usr/bin/$eng"
    fi
  done
}

link_bins() {
  BINDIR="$(dirname "$(find "$TINYTEX_HOME" -name xelatex 2>/dev/null | head -n1)")"
  if [ -z "$BINDIR" ] || [ ! -x "$BINDIR/xelatex" ]; then
    return 1
  fi
  if [ -e /opt/TinyTeX ] && [ ! -L /opt/TinyTeX ]; then
    rm -rf /opt/TinyTeX
  fi
  ln -sfn "$TINYTEX_HOME" /opt/TinyTeX
  install_wrappers "$BINDIR"
  return 0
}

if ! link_bins; then
  echo "ensure-tinytex: installing TinyTeX into $TINYTEX_HOME"
  TMP_HOME="$(mktemp -d)"
  export HOME="$TMP_HOME"
  export TINYTEX_DIR="$TINYTEX_HOME"
  curl -fsSL https://yihui.org/tinytex/install-bin-unix.sh | sh
  if [ -d "$TMP_HOME/.TinyTeX" ] && [ ! -d "$TINYTEX_HOME/bin" ]; then
    cp -a "$TMP_HOME/.TinyTeX/." "$TINYTEX_HOME/"
  fi
  rm -rf "$TMP_HOME"
  link_bins || {
    echo "ensure-tinytex: TinyTeX install failed" >&2
    exit 1
  }
fi

TLMGR="$(echo "$TINYTEX_HOME"/bin/*/tlmgr)"
if [ ! -x "$TLMGR" ]; then
  echo "ensure-tinytex: tlmgr not found" >&2
  exit 1
fi

if [ ! -f "$MARKER" ] || ! kpsewhich ulem.sty >/dev/null 2>&1; then
  echo "ensure-tinytex: installing TeX packages"
  "$TLMGR" path add || true
  # shellcheck disable=SC2086
  "$TLMGR" install $PACKAGES || true
  if kpsewhich ulem.sty >/dev/null 2>&1; then
    date -u +%Y-%m-%dT%H:%M:%SZ > "$MARKER"
  fi
fi

chown -R www-data:www-data "$TINYTEX_HOME" 2>/dev/null || true
link_bins || true
echo "ensure-tinytex: ready"
