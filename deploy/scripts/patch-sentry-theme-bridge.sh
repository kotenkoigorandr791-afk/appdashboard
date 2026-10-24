#!/usr/bin/env sh
set -eu
container=""
for name in glitchtip_web glitchtip-web; do
  if docker inspect -f '{{.State.Running}}' "$name" 2>/dev/null | grep -q '^true$'; then
    container=$name
    break
  fi
done
[ -n "$container" ] || exit 0
patched="$(docker exec -i "$container" sh -s <<'SH'
set -eu
file=""
for candidate in /code/dist/index.html /code/frontend/dist/index.html /usr/share/nginx/html/index.html /app/dist/index.html; do
  if [ -f "$candidate" ]; then
    file=$candidate
    break
  fi
done
[ -n "$file" ] || exit 0
marker=appdashboard-sentry-theme-bridge
script='<script id="appdashboard-sentry-theme-bridge">(function(){function apply(t){if(t!=="light"&&t!=="dark")return;try{document.documentElement.style.setProperty("color-scheme",t);localStorage.setItem("theme",t);}catch(e){}}try{var p=new URLSearchParams(location.search);var t=p.get("theme");if(t==="light"||t==="dark")apply(t);else{var s=localStorage.getItem("theme");if(s==="light"||s==="dark")apply(s);}}catch(e){}window.addEventListener("message",function(e){try{if(e&&e.data&&e.data.type==="appdashboard-theme")apply(e.data.theme);}catch(err){}});})();</script>'
if ! grep -q "$marker" "$file"; then
  tmp="${file}.tmp"
  awk -v script="$script" '{print; if ($0 ~ /<head[^>]*>/) print script}' "$file" > "$tmp"
  cat "$tmp" > "$file"
  rm -f "$tmp"
  echo patched
fi
SH
)"
if [ "$patched" = "patched" ]; then
  docker restart "$container" >/dev/null
fi
