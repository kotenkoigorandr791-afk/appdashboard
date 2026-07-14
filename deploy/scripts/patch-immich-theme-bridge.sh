#!/usr/bin/env sh
set -eu
patched="$(docker exec -i immich_server sh -s <<'SH'
set -eu
file=/build/www/index.html
marker=appdashboard-immich-theme-bridge
script='<script id="appdashboard-immich-theme-bridge">(function(){try{var p=new URLSearchParams(location.search);var t=p.get("theme")||p.get("colorTheme")||p.get("color-theme")||p.get("appearance");if(t==="light"||t==="dark"){localStorage.setItem("immich-ui-theme",JSON.stringify(t));document.documentElement.classList.toggle("light",t==="light");document.documentElement.classList.toggle("dark",t==="dark");}}catch(e){}})();</script>'
if [ -f "$file" ] && ! grep -q "$marker" "$file"; then
  tmp="${file}.tmp"
  awk -v script="$script" '{print; if ($0 ~ /<head>/) print script}' "$file" > "$tmp"
  cat "$tmp" > "$file"
  rm -f "$tmp"
  echo patched
fi
SH
)"
if [ "$patched" = "patched" ]; then
  docker restart immich_server >/dev/null
fi
