#!/bin/sh
# Install TinyTeX + common packages into a persistent volume (not the image).
set -eu

TINYTEX_HOME="${TINYTEX_HOME:-/opt/ApplicationData/tinytex}"
MARKER="$TINYTEX_HOME/.appdashboard-packages.ok"
PACKAGES="${TINYTEX_PACKAGES:-pdftex xetex latexmk fontspec luatex ec extsizes collection-fontsrecommended collection-langcyrillic geometry fancyvrb framed xcolor booktabs setspace ulem}"

mkdir -p "$TINYTEX_HOME"

# Flatten installer layout: TinyTeX may land in $TINYTEX_HOME/.TinyTeX.
if [ -d "$TINYTEX_HOME/.TinyTeX/bin" ] && [ ! -d "$TINYTEX_HOME/bin" ]; then
  echo "ensure-tinytex: flattening $TINYTEX_HOME/.TinyTeX -> $TINYTEX_HOME"
  cp -a "$TINYTEX_HOME/.TinyTeX/." "$TINYTEX_HOME/"
  rm -rf "$TINYTEX_HOME/.TinyTeX"
fi

# One-time migrate from image-baked TinyTeX (older builds) into the volume.
if [ -d /opt/TinyTeX/bin ] && [ ! -d "$TINYTEX_HOME/bin" ] && [ ! -L /opt/TinyTeX ]; then
  echo "ensure-tinytex: migrating image TinyTeX -> $TINYTEX_HOME"
  cp -a /opt/TinyTeX/. "$TINYTEX_HOME/" || true
fi

find_engine_bindir() {
  for cand in \
    "$TINYTEX_HOME"/bin/*/xelatex \
    "$TINYTEX_HOME"/.TinyTeX/bin/*/xelatex \
    /opt/TinyTeX/bin/*/xelatex; do
    if [ -f "$cand" ] && [ -x "$cand" ]; then
      dirname "$cand"
      return 0
    fi
  done
  # Prefer real binaries; ignore texmf directories named "xelatex".
  hit="$(find "$TINYTEX_HOME" -type f -name xelatex 2>/dev/null | head -n1 || true)"
  if [ -n "$hit" ] && [ -x "$hit" ]; then
    dirname "$hit"
    return 0
  fi
  return 1
}

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
  for eng in xelatex pdflatex latex latexmk lualatex; do
    if [ -f "$BINDIR/$eng" ] && [ -x "$BINDIR/$eng" ]; then
      ln -sfn "$WRAP_DST" "/usr/bin/$eng"
    fi
  done
}

link_bins() {
  BINDIR="$(find_engine_bindir)" || return 1
  if [ -z "$BINDIR" ] || [ ! -f "$BINDIR/xelatex" ] || [ ! -x "$BINDIR/xelatex" ]; then
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
  # Install into a temp home, then copy into the volume root (not .TinyTeX nested).
  unset TINYTEX_DIR || true
  curl -fsSL https://yihui.org/tinytex/install-bin-unix.sh | sh
  if [ -d "$TMP_HOME/.TinyTeX" ] && [ ! -d "$TINYTEX_HOME/bin" ]; then
    cp -a "$TMP_HOME/.TinyTeX/." "$TINYTEX_HOME/"
  fi
  rm -rf "$TMP_HOME"
  if [ -d "$TINYTEX_HOME/.TinyTeX/bin" ] && [ ! -d "$TINYTEX_HOME/bin" ]; then
    cp -a "$TINYTEX_HOME/.TinyTeX/." "$TINYTEX_HOME/"
    rm -rf "$TINYTEX_HOME/.TinyTeX"
  fi
  link_bins || {
    echo "ensure-tinytex: TinyTeX install failed" >&2
    exit 1
  }
fi

BINDIR="$(find_engine_bindir)" || {
  echo "ensure-tinytex: engine bindir not found" >&2
  exit 1
}
TLMGR="$BINDIR/tlmgr"
if [ ! -x "$TLMGR" ]; then
  echo "ensure-tinytex: tlmgr not found" >&2
  exit 1
fi

need_packages=0
if [ ! -f "$MARKER" ]; then
  need_packages=1
fi
if [ ! -f "$BINDIR/pdflatex" ] || [ ! -x "$BINDIR/pdflatex" ]; then
  need_packages=1
fi
if ! "$BINDIR/kpsewhich" ulem.sty >/dev/null 2>&1; then
  need_packages=1
fi

if [ "$need_packages" = "1" ]; then
  echo "ensure-tinytex: installing TeX packages"
  "$TLMGR" path add || true
  # shellcheck disable=SC2086
  "$TLMGR" install $PACKAGES || true
  # cm-super maps must be registered or T2A/cmr sizes like larm2074 fail.
  if [ -x "$BINDIR/updmap-sys" ]; then
    "$BINDIR/updmap-sys" --force >/dev/null 2>&1 || true
  fi
  if [ -x "$BINDIR/pdflatex" ] && "$BINDIR/kpsewhich" ulem.sty >/dev/null 2>&1 \
    && "$BINDIR/kpsewhich" extarticle.cls >/dev/null 2>&1; then
    date -u +%Y-%m-%dT%H:%M:%SZ > "$MARKER"
  fi
fi

# Pre-build common T2A/LH metrics so 14pt bold/italic do not fail at first compile.
pregen_tfms() {
  MKTEXTFM="$BINDIR/mktextfm"
  [ -x "$MKTEXTFM" ] || return 0
  echo "ensure-tinytex: pre-generating common T2A TFMs"
  for series in larm labx lati labi; do
    for size in 0500 0600 0700 0800 0900 1000 1095 1200 1440 1728 2074 2488 2986; do
      name="${series}${size}"
      if ! "$BINDIR/kpsewhich" "${name}.tfm" >/dev/null 2>&1; then
        "$MKTEXTFM" "$name" >/dev/null 2>&1 || true
      fi
    done
  done
}
pregen_tfms

# Ensure www-data can generate missing TFM/PK on demand.
chown -R www-data:www-data "$TINYTEX_HOME" 2>/dev/null || true
chmod -R ug+rwX "$TINYTEX_HOME/texmf-var" 2>/dev/null || true
link_bins || true
echo "ensure-tinytex: ready ($(cat /etc/appdashboard/tinytex-bindir 2>/dev/null || true))"
