# Docker Context (Senior)

> **Səviyyə (Level):** ⭐⭐⭐ Senior

## Nədir? (What is it?)

Docker Context — fərqli Docker host-larına (local, remote VPS, SSH üzərindən server) keçid etmək üçün mexanizmdir. Hər context bir Docker API endpoint-ini saxlayır. `docker context use` ilə aktiv context dəyişdirilir — həmin andan bütün `docker` əmrləri həmin host-a yönləndirilir.

PHP/Laravel developer üçün praktik ssenari: development laptop-dan birbaşa production VPS-ə `docker compose up` etmək, SSH tunnel qurmadan.

## Əsas Konseptlər

### 1. Context Anatomiyası

```
Context:
  Name:        prod-vps
  Endpoint:    ssh://deploy@185.12.34.56
  TLS verify:  true
  Orchestrator: swarm (optional)

Default context ("default") = local Docker daemon (unix:///var/run/docker.sock)
```

### 2. Context Əmrləri

```bash
# Mövcud context-ləri gör
docker context ls
# NAME       DESCRIPTION   DOCKER ENDPOINT               ORCHESTRATOR
# default *  Current       unix:///var/run/docker.sock
# prod-vps                 ssh://deploy@185.12.34.56

# Aktiv context
docker context inspect default

# Context yaratmaq (SSH üzərindən)
docker context create prod-vps \
    --description "Production VPS" \
    --docker "host=ssh://deploy@185.12.34.56"

# Context-i aktiv et
docker context use prod-vps

# Sonra bütün docker əmrləri prod-vps-ə gedir
docker ps
docker images

# Local-a qayıtmaq
docker context use default

# Context-i silmək
docker context rm prod-vps
```

### 3. SSH Context üçün Tələblər

```bash
# 1. SSH key-in olmalıdır (password-lu SSH context-lə işləmir)
ssh-keygen -t ed25519 -C "deploy@myapp"
ssh-copy-id -i ~/.ssh/id_ed25519.pub deploy@185.12.34.56

# 2. Remote host-da Docker quraşdırılmış olmalıdır
ssh deploy@185.12.34.56 "docker version"

# 3. deploy user-i docker group-unda olmalıdır
ssh deploy@185.12.34.56 "sudo usermod -aG docker deploy"

# 4. SSH agent-ı işləyir?
eval $(ssh-agent)
ssh-add ~/.ssh/id_ed25519
```

### 4. TLS Context (TCP üzərindən)

SSH əvəzinə TLS-lə birbaşa TCP connection:

```bash
# Server-də Docker daemon TLS ilə listen etsin (daemon.json)
# /etc/docker/daemon.json:
{
  "hosts": ["unix:///var/run/docker.sock", "tcp://0.0.0.0:2376"],
  "tls": true,
  "tlsverify": true,
  "tlscacert": "/etc/docker/certs/ca.pem",
  "tlscert": "/etc/docker/certs/server-cert.pem",
  "tlskey": "/etc/docker/certs/server-key.pem"
}

# Context yaratmaq
docker context create prod-tls \
    --docker "host=tcp://185.12.34.56:2376,ca=/path/ca.pem,cert=/path/cert.pem,key=/path/key.pem"
```

**SSH vs TLS:**
- SSH daha sadədir — yalnız SSH key lazımdır
- TLS daha sürətlidir — overhead az
- Production-da TLS, development-da SSH tövsiyə olunur

### 5. DOCKER_CONTEXT Environment Variable

```bash
# Context dəyişmədən müvəqqəti başqa context ilə
DOCKER_CONTEXT=prod-vps docker ps

# docker-compose ilə
DOCKER_CONTEXT=prod-vps docker compose -f docker-compose.prod.yml up -d

# Və ya --context flag ilə
docker --context prod-vps ps
docker --context prod-vps compose up -d
```

### 6. Context-i Export/Import

```bash
# Context-i paylaşmaq (team üçün)
docker context export prod-vps > prod-vps.dockercontext

# Başqa maşında import etmək
docker context import prod-vps prod-vps.dockercontext

# Qeyd: Exported context-də credentials yoxdur (SSH key ayrıca lazımdır)
```

## Praktiki Nümunələr

### Çoxlu VPS İdarəsi

```bash
# Hər mühit üçün context
docker context create staging \
    --docker "host=ssh://deploy@staging.example.com"

docker context create prod-eu \
    --docker "host=ssh://deploy@prod-eu.example.com"

docker context create prod-us \
    --docker "host=ssh://deploy@prod-us.example.com"

docker context ls
# NAME       DESCRIPTION
# default *  Local Docker
# staging    Staging server
# prod-eu    Production EU
# prod-us    Production US
```

### Laravel Production Deploy Script

```bash
#!/bin/bash
# deploy.sh

CONTEXT="${DOCKER_CONTEXT:-prod-eu}"
IMAGE="myregistry/laravel:${VERSION:-latest}"

echo "→ Deploying to context: $CONTEXT"
echo "→ Image: $IMAGE"

# Remote host-da image pull et
docker --context "$CONTEXT" pull "$IMAGE"

# Docker Compose ilə deploy
docker --context "$CONTEXT" compose \
    -f docker-compose.prod.yml \
    --env-file .env.production \
    up -d --no-build

# Verify
docker --context "$CONTEXT" compose \
    -f docker-compose.prod.yml \
    ps
```

```bash
# İstifadəsi
VERSION=1.2.0 DOCKER_CONTEXT=prod-eu ./deploy.sh
VERSION=1.2.0 DOCKER_CONTEXT=prod-us ./deploy.sh
```

### Docker Compose Remote Deploy

```yaml
# docker-compose.prod.yml
services:
  app:
    image: myregistry/laravel:${VERSION}
    restart: unless-stopped
    environment:
      - APP_ENV=production
    env_file:
      - /etc/laravel/.env   # Remote host-dakı .env
    depends_on:
      - mysql
      - redis

  nginx:
    image: myregistry/nginx:${VERSION}
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /etc/nginx/certs:/etc/nginx/certs:ro

  mysql:
    image: mysql:8.0
    volumes:
      - mysql-data:/var/lib/mysql
    environment:
      - MYSQL_ROOT_PASSWORD_FILE=/run/secrets/mysql_root
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    restart: unless-stopped

volumes:
  mysql-data:
```

```bash
# Local-dan remote-a deploy
docker --context prod-eu compose \
    -f docker-compose.prod.yml \
    up -d --pull always
```

### CI/CD ilə Context

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup SSH key
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.DEPLOY_SSH_KEY }}" > ~/.ssh/deploy_key
          chmod 600 ~/.ssh/deploy_key
          ssh-keyscan -H ${{ secrets.PROD_HOST }} >> ~/.ssh/known_hosts

      - name: Create Docker context
        run: |
          docker context create prod \
            --docker "host=ssh://deploy@${{ secrets.PROD_HOST }}"

      - name: Deploy
        run: |
          docker --context prod compose \
            -f docker-compose.prod.yml \
            up -d --pull always
        env:
          VERSION: ${{ github.sha }}
```

### Docker Stack (Swarm Mode) ilə

```bash
# Swarm mode-da bir fərqli context növü — orchestrator: swarm
docker context create prod-swarm \
    --docker "host=ssh://deploy@185.12.34.56" \
    --default-stack-orchestrator swarm

docker --context prod-swarm stack deploy \
    -c docker-compose.prod.yml \
    laravel-app

docker --context prod-swarm stack services laravel-app
```

## PHP/Laravel ilə İstifadə

### Makefile ilə Multi-Context Workflow

```makefile
# Makefile
COMPOSE_FILE = docker-compose.prod.yml
VERSION ?= latest

.PHONY: deploy-staging deploy-prod logs-prod shell-prod

deploy-staging:
	DOCKER_CONTEXT=staging VERSION=$(VERSION) docker compose \
		-f $(COMPOSE_FILE) up -d --pull always

deploy-prod:
	@echo "⚠ Deploying to PRODUCTION"
	@read -p "Continue? [y/N] " confirm && [ "$$confirm" = "y" ]
	DOCKER_CONTEXT=prod VERSION=$(VERSION) docker compose \
		-f $(COMPOSE_FILE) up -d --pull always

logs-prod:
	docker --context prod compose -f $(COMPOSE_FILE) logs -f app

shell-prod:
	docker --context prod compose -f $(COMPOSE_FILE) \
		exec app php artisan tinker

migrate-prod:
	docker --context prod compose -f $(COMPOSE_FILE) \
		exec app php artisan migrate --force
```

### Artisan Əmrləri Remote

```bash
# Production-da migration işlət
docker --context prod compose exec app \
    php artisan migrate --force

# Cache-i refresh et
docker --context prod compose exec app \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Queue-u monitor et
docker --context prod compose exec app \
    php artisan queue:monitor

# Tinker (production-da diqqətlə!)
docker --context prod compose exec app \
    php artisan tinker
```

## İntervyu Sualları

### S1: Docker Context nədir və nə üçün lazımdır?
**C:** Docker Context müxtəlif Docker host-larına (local, remote, cloud) keçid etmək üçün named endpoint-lərdir. `docker context use prod` deyəndə həmin andan bütün `docker` əmrləri prod serverinə yönlənir. SSH tunnel qurmağa, DOCKER_HOST export etməyə ehtiyac qalmır. Çoxlu server idarə edərkən çox vacibdir.

### S2: SSH context TLS context-dən niyə fərqlidir?
**C:** SSH context sadədir — mövcud SSH key istifadə edir, serverdə portları açmaq lazım deyil. TLS context birbaşa TCP ilə işləyir — 2376 portu açıq olmalı, sertifikat yaradılmalıdır. SSH daha sadə qurulur, TLS daha sürətlidir (SSH overhead yoxdur). Development üçün SSH, CI/CD üçün TLS daha uyğundur.

### S3: `DOCKER_CONTEXT` environment variable-ı `docker context use`-dan nə ilə fərqlənir?
**C:** `docker context use` aktiv context-i system-wide (persistent) dəyişdirir. `DOCKER_CONTEXT` env variable isə yalnız həmin shell session üçün keçərlidir. Script-lərdə `DOCKER_CONTEXT=prod docker ps` kimi müvəqqəti istifadə üçün env variable, daimi iş üçün `docker context use` tövsiyə olunur.

### S4: Context-i export etdikdə credentials saxlanırmı?
**C:** Xeyr. `docker context export` endpoint-i (host address, TLS cert paths) saxlayır, amma credentials-ı (SSH private key, TLS private key) saxlamır. Team-dən biri context-i import etsə, öz SSH key-ini ayrıca konfiqurasiya etməlidir.

### S5: Remote context-də docker compose necə işləyir?
**C:** `docker --context prod compose` əmri local-dan remote Docker API-yə compose YAML-ı göndərir. Build remote-da deyil — `--no-build` flag-i istifadə olunur. Image-lər registry-dən pull edilir. Bind mount-lar remote host-a görədir (`./`  = remote host-dakı path), local path deyil.

### S6: Docker Context ilə Kubernetes context arasında fərq?
**C:** Docker context Docker daemon endpoint-i idarə edir. Kubernetes context (`kubectl config use-context`) K8s API server-i idarə edir. Docker Swarm üçün Docker context, K8s üçün kubectl context istifadə olunur. Hər ikisi remote infrastructure-a local əmrlərlə çatmağı təmin edir.

## Best Practices

1. **Context-ləri adlandırın** — `prod-eu`, `staging`, `local` kimi aydın adlar
2. **SSH key-i əvvəlcədən qurun** — password-lu SSH context-lə işləmir
3. **`DOCKER_CONTEXT` env variable** — script-lərdə müvəqqəti istifadə üçün
4. **Production context-ini diqqətlə istifadə edin** — aktiv context-i terminal prompt-da göstərin
5. **`.env.production`-ı remote-da saxlayın** — context üzərindən local fayl keçmir
6. **`--pull always` flag-i** — deploy zamanı image-in yeni versiyasını çəkmək üçün
7. **Makefile-da deploy target-ları** — kontekst yanlışlığının qarşısı üçün təsdiqləmə
8. **CI/CD-də `known_hosts`** — SSH keyscan ilə host-u əvvəlcədən qeydə alın
9. **Context-i export etməyin** — `known_hosts` + private key ayrıca konfiqurasiya olunmalıdır
10. **Remote build etməyin** — image-i registry-dən pull edin, remote-da build etməyin

## Əlaqəli Mövzular

- [docker-basics.md](01-docker-basics.md) — Docker daemon, client arxitekturası
- [docker-compose.md](05-docker-compose.md) — Compose ilə multi-service idarəsi
- [docker-registry.md](12-docker-registry.md) — Image registry, push/pull
- [docker-security.md](10-docker-security.md) — Docker daemon security
- [docker-ci-cd-github-actions-php.md](51-docker-ci-cd-github-actions-php.md) — CI/CD deploy pipeline
