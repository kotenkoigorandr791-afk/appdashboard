# AppDashboard Docker

This file is superseded by [README.md](README.md).

```bash
cp .env.example .env
# set BASE_DOMAIN=
docker compose up -d --build
```

Open `http://localhost/` (or `https://$BASE_DOMAIN/`).

SSH WebSocket (`ssh-ws`) and nginx domain rendering start automatically via `docker-entrypoint.sh`.
See README.md for subdomain mapping (`ai.`, `search.`, `git.`, `photo.`, …).
