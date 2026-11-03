# Deployment Guide

Production deployment, updates, and scaling.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Internet                              │
└──────────────────────────┬──────────────────────────────────┘
                           │
                    ┌──────▼──────┐
                    │  Reverse    │
                    │  Proxy      │  (Traefik / Nginx / Caddy)
                    │  TLS Term   │
                    └──────┬──────┘
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
        ┌──────────┐ ┌──────────┐ ┌──────────┐
        │ App 1    │ │ App 2    │ │ App N    │  (PHP-FPM + Nginx)
        │ (scale)  │ │ (scale)  │ │ (scale)  │
        └────┬─────┘ └────┬─────┘ └────┬─────┘
             │            │            │
             └────────────┼────────────┘
                          ▼
                 ┌─────────────────┐
                 │  PostgreSQL     │  (Primary + Replica)
                 │  Redis          │  (Cluster)
                 │  OnlyOffice     │  (Document Server)
                 └─────────────────┘
```

---

## Docker Compose (Production)

### `docker-compose.prod.yml`

```yaml
version: '3.8'

services:
  appdashboard:
    image: ghcr.io/yourorg/appdashboard:latest
    deploy:
      replicas: 3
      resources:
        limits:
          cpus: '2'
          memory: 2G
        reservations:
          cpus: '1'
          memory: 1G
      restart_policy:
        condition: on-failure
        delay: 5s
        max_attempts: 3
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - LOG_LEVEL=error
    volumes:
      - appdashboard_data:/var/www/html/storage
      - ./nginx.prod.conf:/etc/nginx/nginx.conf:ro
    depends_on:
      - appdashboard-db
      - redis
    networks:
      - internal
      - proxy

  appdashboard-db:
    image: postgres:16-alpine
    deploy:
      resources:
        limits:
          memory: 4G
    environment:
      - POSTGRES_DB=appdashboard
      - POSTGRES_USER=appdashboard
      - POSTGRES_PASSWORD_FILE=/run/secrets/db_password
    secrets:
      - db_password
    volumes:
      - appdashboard_db:/var/lib/postgresql/data
      - ./postgresql.conf:/etc/postgresql/postgresql.conf:ro
    command: postgres -c config_file=/etc/postgresql/postgresql.conf
    networks:
      - internal

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes --maxmemory 512mb --maxmemory-policy allkeys-lru
    volumes:
      - appdashboard_redis:/data
    networks:
      - internal

  onlyoffice:
    image: onlyoffice/documentserver:latest
    environment:
      - JWT_ENABLED=true
      - JWT_SECRET_FILE=/run/secrets/onlyoffice_jwt
      - WOPI_ENABLED=true
    secrets:
      - onlyoffice_jwt
    volumes:
      - onlyoffice_data:/var/lib/onlyoffice
      - onlyoffice_log:/var/log/onlyoffice
    networks:
      - internal

secrets:
  db_password:
    file: ./secrets/db_password.txt
  onlyoffice_jwt:
    file: ./secrets/onlyoffice_jwt.txt

volumes:
  appdashboard_data:
  appdashboard_db:
  appdashboard_redis:
  onlyoffice_data:
  onlyoffice_log:

networks:
  internal:
    driver: overlay
    attachable: true
  proxy:
    external: true
```

---

## Environment Variables (Production)

```env
# .env.prod
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_HOST=appdashboard-db
DB_PORT=5432
DB_DATABASE=appdashboard
DB_USERNAME=appdashboard
DB_PASSWORD_FILE=/run/secrets/db_password

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD_FILE=/run/secrets/redis_password

# Security
JWT_SECRET=base64-encoded-64-char-secret
SESSION_DOMAIN=.your-domain.com
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12

# OnlyOffice
ONLYOFFICE_URL=https://onlyoffice.your-domain.com
ONLYOFFICE_JWT_SECRET_FILE=/run/secrets/onlyoffice_jwt

# Email
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD_FILE=/run/secrets/mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com

# Backup
BACKUP_S3_ENDPOINT=s3.eu-central-1.amazonaws.com
BACKUP_S3_BUCKET=appdashboard-backups-prod
BACKUP_S3_KEY_FILE=/run/secrets/backup_s3_key
BACKUP_S3_SECRET_FILE=/run/secrets/backup_s3_secret
BACKUP_ENCRYPTION_KEY_FILE=/run/secrets/backup_encryption_key

# Monitoring
SENTRY_DSN=https://xxx@sentry.io/xxx
```

---

## Reverse Proxy (Traefik Example)

```yaml
# traefik.yml
http:
  routers:
    appdashboard:
      rule: "Host(`your-domain.com`)"
      entrypoints: ["websecure"]
      tls:
        certResolver: letsencrypt
      middlewares:
        - security-headers
        - rate-limit
      service: appdashboard
    onlyoffice:
      rule: "Host(`onlyoffice.your-domain.com`)"
      entrypoints: ["websecure"]
      tls:
        certResolver: letsencrypt
      service: onlyoffice
  services:
    appdashboard:
      loadbalancer:
        servers:
          - url: "http://appdashboard:80"
        passHostHeader: true
    onlyoffice:
      loadbalancer:
        servers:
          - url: "http://onlyoffice:80"
  middlewares:
    security-headers:
      headers:
        frameDeny: true
        sslRedirect: true
        browserXssFilter: true
        contentTypeNosniff: true
        stsSeconds: 31536000
        stsIncludeSubdomains: true
        stsPreload: true
        customFrameOptionsValue: "SAMEORIGIN"
    rate-limit:
      rateLimit:
        average: 100
        burst: 200
```

---

## SSL/TLS

### Let's Encrypt (Automatic)

```yaml
# In Traefik
certificatesResolvers:
  letsencrypt:
    acme:
      email: admin@your-domain.com
      storage: /letsencrypt/acme.json
      httpChallenge:
        entryPoint: web
```

### Custom Certificates

```yaml
tls:
  stores:
    default:
      defaultCertificate:
        certFile: /certs/your-domain.com.crt
        keyFile: /certs/your-domain.com.key
```

---

## Database Tuning (PostgreSQL)

`postgresql.conf`:
```conf
# Memory
shared_buffers = 1GB
effective_cache_size = 3GB
work_mem = 16MB
maintenance_work_mem = 256MB

# WAL
wal_buffers = 16MB
min_wal_size = 1GB
max_wal_size = 4GB
checkpoint_completion_target = 0.9

# Parallelism
max_worker_processes = 8
max_parallel_workers_per_gather = 4
max_parallel_workers = 8

# Logging
log_min_duration_statement = 1000
log_line_prefix = '%t [%p]: [%l-1] user=%u,db=%d,app=%a,client=%h '
log_checkpoints = on
log_connections = on
log_disconnections = on

# Extensions
shared_preload_libraries = 'pg_stat_statements'
pg_stat_statements.track = all
```

---

## Scaling

### Horizontal (Multiple App Containers)

```bash
docker compose -f docker-compose.prod.yml up -d --scale appdashboard=5
```

**Requirements**:
- Shared storage volume (`appdashboard_data`) — use NFS, Ceph, or cloud volume
- Shared Redis — use Redis Cluster or Sentinel
- Session affinity not required (stateless JWT)

### Vertical (More Resources)

```yaml
deploy:
  resources:
    limits:
      cpus: '4'
      memory: 4G
```

---

## Updates

### Zero-Downtime Deploy

```bash
# 1. Pull new image
docker compose -f docker-compose.prod.yml pull

# 2. Run migrations (on one container)
docker compose -f docker-compose.prod.yml exec appdashboard php project/cli.php migrate

# 3. Rolling restart
docker compose -f docker-compose.prod.yml up -d --no-deps --scale appdashboard=3 appdashboard
```

### With Traefik (Health Checks)

```yaml
# In docker-compose.prod.yml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/health"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 40s
```

Traefik automatically drains connections from unhealthy containers.

---

## Monitoring

### Health Endpoints

| Endpoint | Checks |
|----------|--------|
| `GET /health` | HTTP 200 = OK |
| `GET /health/db` | DB connection + query |
| `GET /health/redis` | Redis ping |
| `GET /health/onlyoffice` | OnlyOffice health |

### Prometheus Metrics

Enable in Admin → Config → Performance → **Prometheus Exporter**

```yaml
# prometheus.yml
scrape_configs:
  - job_name: 'appdashboard'
    static_configs:
      - targets: ['appdashboard:9090']
```

### Log Aggregation

```yaml
# docker-compose.prod.yml
logging:
  driver: "json-file"
  options:
    max-size: "10m"
    max-file: "5"
# Or use Loki/Fluentd/Grafana
```

---

## Security Hardening

| Measure | Implementation |
|---------|----------------|
| Non-root user | `user: "1000:1000"` in compose |
| Read-only rootfs | `read_only: true` + tmpfs for `/tmp`, `/var/cache` |
| Capabilities | Drop all: `cap_drop: ["ALL"]` |
| Secrets | Docker secrets (not env vars) |
| Network | Internal overlay network; no public ports on app |
| Updates | Watchtower or CI/CD for automated security patches |

---

## Disaster Recovery

| Scenario | RTO | RPO | Procedure |
|----------|-----|-----|-----------|
| App container crash | <1 min | 0 | Auto-restart (health check) |
| DB primary failure | <5 min | <1 min | Promote replica; update DNS |
| Full server loss | <30 min | <1 hour | Restore from offsite backup |
| Region outage | <1 hour | <1 hour | Failover to secondary region |

**Test quarterly**: Restore to staging, verify all apps functional.