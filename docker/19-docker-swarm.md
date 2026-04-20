# Docker Swarm

## Nədir? (What is it?)

Docker Swarm — Docker Engine-ə daxil olan native konteyner orkestrasiya həllidir. Bir neçə Docker host-u bir cluster (swarm) olaraq birləşdirir və konteynerləri bu cluster üzərində idarə edir.

Swarm, Kubernetes-dən daha sadə və daha asan quraşdırılır. Docker CLI ilə eyni əmrləri istifadə edir. Kiçik-orta ölçülü proyektlər və Docker Compose ilə tanış olan komandalar üçün uyğundur.

### Swarm vs Kubernetes

| Xüsusiyyət | Docker Swarm | Kubernetes |
|-------------|-------------|------------|
| Quraşdırma | Çox sadə (`docker swarm init`) | Kompleks |
| Öyrənmə | Asan (Docker CLI) | Çətin |
| Scaling | Yaxşı | Əla (HPA, VPA) |
| Networking | Overlay network | CNI plugin-lər |
| Service Discovery | Daxili DNS | CoreDNS |
| Load Balancing | Daxili (routing mesh) | Service + Ingress |
| Rolling Updates | Dəstəkləyir | Dəstəkləyir |
| Ekosistem | Kiçik | Böyük (Helm, Operators) |
| Produksiya | Kiçik-orta layihələr | Böyük layihələr |
| Bazarda tələb | Azalır | Artır |

## Əsas Konseptlər

### 1. Swarm Arxitekturası

```
┌─────────────────────────────────────────────────────────┐
│                     Docker Swarm Cluster                 │
│                                                          │
│  ┌──────────────── Manager Nodes ──────────────────┐    │
│  │                                                   │    │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐ │    │
│  │  │  Manager 1 │  │  Manager 2 │  │  Manager 3 │ │    │
│  │  │  (Leader)  │  │ (Follower) │  │ (Follower) │ │    │
│  │  └────────────┘  └────────────┘  └────────────┘ │    │
│  │           Raft Consensus Algorithm               │    │
│  └──────────────────────────────────────────────────┘    │
│                                                          │
│  ┌──────────────── Worker Nodes ───────────────────┐    │
│  │                                                   │    │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐       │    │
│  │  │ Worker 1 │  │ Worker 2 │  │ Worker 3 │       │    │
│  │  │ [task]   │  │ [task]   │  │ [task]   │       │    │
│  │  │ [task]   │  │ [task]   │  │          │       │    │
│  │  └──────────┘  └──────────┘  └──────────┘       │    │
│  └──────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────┘
```

**Manager Node:** Cluster-i idarə edir, scheduling qərarları verir, state saxlayır (Raft). Əmrlər yalnız manager-dən qəbul olunur.

**Worker Node:** Task-ları (konteynerləri) icra edir. Manager-dən göstəriş alır.

**Leader:** Manager node-lardan biri leader olur. Digərləri follower olaraq leader-ə problem olduqda onu əvəz edir.

### 2. Swarm İnisializasiyası

```bash
# Swarm yaratmaq (ilk manager)
docker swarm init --advertise-addr 192.168.1.10

# Çıxışda worker join token göstərilir:
# docker swarm join --token SWMTKN-xxx 192.168.1.10:2377

# Manager join token almaq
docker swarm join-token manager

# Worker join token almaq
docker swarm join-token worker

# Worker əlavə etmək (worker node-da)
docker swarm join --token SWMTKN-xxx 192.168.1.10:2377

# Node-ları görmək
docker node ls
# ID            HOSTNAME   STATUS   AVAILABILITY   MANAGER STATUS
# abc123 *      manager1   Ready    Active         Leader
# def456        worker1    Ready    Active
# ghi789        worker2    Ready    Active

# Node-u drain etmək (maintenance üçün)
docker node update --availability drain worker1

# Swarm-dan çıxmaq
docker swarm leave
docker swarm leave --force    # Manager üçün
```

### 3. Services

Swarm-da service — tətbiqin run-time təsviridir. Docker Compose-dakı service ilə oxşardır, amma cluster səviyyəsindədir.

```bash
# Service yaratmaq
docker service create \
    --name laravel-app \
    --replicas 3 \
    --publish 80:9000 \
    --env APP_ENV=production \
    --mount type=volume,source=storage,target=/var/www/html/storage \
    --network app-network \
    --update-delay 10s \
    --update-parallelism 1 \
    --restart-condition on-failure \
    mycompany/laravel:1.0.0

# Service-ləri görmək
docker service ls

# Service detallları
docker service inspect --pretty laravel-app

# Service task-ları (konteynerlər)
docker service ps laravel-app

# Service log-ları
docker service logs laravel-app
docker service logs -f --tail 100 laravel-app

# Scaling
docker service scale laravel-app=5
docker service scale laravel-app=5 redis=3

# Yeniləmə
docker service update \
    --image mycompany/laravel:1.1.0 \
    laravel-app

# Rollback
docker service rollback laravel-app

# Service silmək
docker service rm laravel-app
```

### 4. Overlay Networks

```bash
# Overlay network yaratmaq
docker network create \
    --driver overlay \
    --subnet 10.0.0.0/24 \
    --attachable \
    app-network

# Encrypted overlay
docker network create \
    --driver overlay \
    --opt encrypted \
    secure-network

# Service-ə network əlavə etmək
docker service update \
    --network-add app-network \
    laravel-app

# Network-ləri görmək
docker network ls
```

### 5. Stacks

Stack — bir neçə service-i docker-compose.yml formatında deploy etmək üçün istifadə olunur.

```yaml
# docker-stack.yml
version: "3.8"

services:
  app:
    image: mycompany/laravel:1.0.0
    deploy:
      replicas: 3
      update_config:
        parallelism: 1
        delay: 10s
        failure_action: rollback
        order: start-first
      rollback_config:
        parallelism: 1
        delay: 5s
      restart_policy:
        condition: on-failure
        delay: 5s
        max_attempts: 3
      resources:
        limits:
          cpus: "1.0"
          memory: 512M
        reservations:
          cpus: "0.25"
          memory: 128M
      placement:
        constraints:
          - node.role == worker
    environment:
      APP_ENV: production
      DB_HOST: mysql
      REDIS_HOST: redis
    networks:
      - frontend
      - backend
    secrets:
      - app_key
      - db_password

  nginx:
    image: mycompany/laravel-nginx:1.0.0
    deploy:
      replicas: 2
      placement:
        constraints:
          - node.role == worker
    ports:
      - "80:80"
      - "443:443"
    networks:
      - frontend

  mysql:
    image: mysql:8.0
    deploy:
      replicas: 1
      placement:
        constraints:
          - node.labels.db == true
    environment:
      MYSQL_DATABASE: laravel
      MYSQL_USER: laravel
      MYSQL_PASSWORD_FILE: /run/secrets/db_password
      MYSQL_ROOT_PASSWORD_FILE: /run/secrets/db_root_password
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - backend
    secrets:
      - db_password
      - db_root_password

  redis:
    image: redis:7-alpine
    deploy:
      replicas: 1
    networks:
      - backend

  queue-worker:
    image: mycompany/laravel:1.0.0
    command: php artisan queue:work --tries=3 --timeout=90
    deploy:
      replicas: 2
      restart_policy:
        condition: on-failure
    environment:
      APP_ENV: production
      DB_HOST: mysql
      REDIS_HOST: redis
    networks:
      - backend
    secrets:
      - app_key
      - db_password

networks:
  frontend:
    driver: overlay
  backend:
    driver: overlay
    internal: true    # Xarici əlaqə yoxdur

volumes:
  mysql-data:
    driver: local

secrets:
  app_key:
    external: true
  db_password:
    external: true
  db_root_password:
    external: true
```

```bash
# Secret-ləri yaratmaq
echo "base64:your-app-key" | docker secret create app_key -
echo "secret123" | docker secret create db_password -
echo "rootsecret" | docker secret create db_root_password -

# Stack deploy
docker stack deploy -c docker-stack.yml laravel

# Stack-ları görmək
docker stack ls

# Stack service-ləri
docker stack services laravel

# Stack task-ları
docker stack ps laravel

# Stack silmək
docker stack rm laravel

# Secret-ləri görmək
docker secret ls
```

### 6. Secrets və Configs

```bash
# Secret yaratmaq
echo "my-secret-value" | docker secret create my_secret -
docker secret create my_secret ./secret-file.txt

# Secret-i service-ə vermək
docker service create \
    --name myapp \
    --secret my_secret \
    mycompany/laravel:1.0.0

# Konteyner daxilində secret /run/secrets/my_secret faylında olur

# Config yaratmaq (non-sensitive)
docker config create nginx_conf ./nginx.conf

# Config istifadə
docker service create \
    --name nginx \
    --config source=nginx_conf,target=/etc/nginx/conf.d/default.conf \
    nginx:alpine
```

### 7. Rolling Updates

```bash
# Update konfiqurasiyası ilə service yaratmaq
docker service create \
    --name laravel-app \
    --replicas 5 \
    --update-delay 10s \
    --update-parallelism 2 \
    --update-failure-action rollback \
    --update-order start-first \
    --rollback-delay 5s \
    --rollback-parallelism 1 \
    mycompany/laravel:1.0.0

# Update başlatmaq
docker service update \
    --image mycompany/laravel:1.1.0 \
    laravel-app

# Update-i izləmək
docker service ps laravel-app
watch docker service ps laravel-app

# Manual rollback
docker service rollback laravel-app
```

**Update parametrləri:**

| Parametr | Təsvir |
|----------|--------|
| --update-parallelism | Eyni anda neçə task yenilənir |
| --update-delay | Task-lar arası gözləmə |
| --update-failure-action | Uğursuzluqda: pause, continue, rollback |
| --update-order | start-first (əvvəl yenisini başlat) və ya stop-first |
| --update-max-failure-ratio | Maksimum uğursuzluq faizi |

## Praktiki Nümunələr

### Laravel Production Swarm Setup

```bash
# 1. Swarm init (manager node)
docker swarm init --advertise-addr 192.168.1.10

# 2. Worker node-ları əlavə et
# Worker 1-də:
docker swarm join --token SWMTKN-xxx 192.168.1.10:2377
# Worker 2-də:
docker swarm join --token SWMTKN-xxx 192.168.1.10:2377

# 3. Node label-ları (DB üçün)
docker node update --label-add db=true worker1

# 4. Network-lər
docker network create --driver overlay frontend
docker network create --driver overlay --internal backend

# 5. Secret-lər
echo "base64:app-key-here" | docker secret create app_key -
echo "db-password" | docker secret create db_password -

# 6. Stack deploy
docker stack deploy -c docker-stack.yml laravel

# 7. Yoxlama
docker stack services laravel
docker stack ps laravel
```

### Health Check ilə Service

```bash
docker service create \
    --name laravel-app \
    --replicas 3 \
    --health-cmd "curl -f http://localhost:9000/health || exit 1" \
    --health-interval 30s \
    --health-retries 3 \
    --health-timeout 10s \
    --health-start-period 60s \
    mycompany/laravel:1.0.0
```

### Routing Mesh

```
Swarm Routing Mesh — istənilən node-a gələn request istənilən 
task-a yönləndirilə bilər:

Client → Node 1:80 → Routing Mesh → Task on Node 2
Client → Node 2:80 → Routing Mesh → Task on Node 1
Client → Node 3:80 → Routing Mesh → Task on Node 2

Bütün node-lar published port-da dinləyir.
```

## PHP/Laravel ilə İstifadə

### Laravel Queue Worker Swarm-da

```yaml
# docker-stack.yml (yalnız queue hissəsi)
services:
  queue-default:
    image: mycompany/laravel:1.0.0
    command: php artisan queue:work redis --queue=default --tries=3
    deploy:
      replicas: 3
      restart_policy:
        condition: on-failure
        delay: 5s

  queue-high:
    image: mycompany/laravel:1.0.0
    command: php artisan queue:work redis --queue=high --tries=3
    deploy:
      replicas: 2
      restart_policy:
        condition: on-failure

  scheduler:
    image: mycompany/laravel:1.0.0
    command: >
      sh -c "while true; do php artisan schedule:run; sleep 60; done"
    deploy:
      replicas: 1
      placement:
        constraints:
          - node.role == manager
```

### Laravel Secret-ləri Swarm-da

```php
// Laravel-də Swarm secret-lərini oxumaq
// config/database.php
'mysql' => [
    'password' => file_exists('/run/secrets/db_password')
        ? trim(file_get_contents('/run/secrets/db_password'))
        : env('DB_PASSWORD'),
],

// config/app.php
'key' => file_exists('/run/secrets/app_key')
    ? trim(file_get_contents('/run/secrets/app_key'))
    : env('APP_KEY'),
```

### Swarm-da Laravel Deployment Script

```bash
#!/bin/bash
# deploy.sh

set -e

VERSION=$1
STACK_NAME="laravel"

if [ -z "$VERSION" ]; then
    echo "Usage: ./deploy.sh <version>"
    exit 1
fi

echo "Deploying Laravel ${VERSION}..."

# Image pull (bütün node-larda)
docker service update \
    --image mycompany/laravel:${VERSION} \
    ${STACK_NAME}_app

docker service update \
    --image mycompany/laravel:${VERSION} \
    ${STACK_NAME}_queue-worker

# Migration (bir dəfə)
docker run --rm \
    --network ${STACK_NAME}_backend \
    --secret app_key \
    --secret db_password \
    mycompany/laravel:${VERSION} \
    php artisan migrate --force

echo "Deployment complete!"
```

## İntervyu Sualları

### S1: Docker Swarm ilə Kubernetes arasında nə fərq var?
**C:** Swarm daha sadə (quraşdırma, öyrənmə, istifadə), Docker CLI ilə işləyir, kiçik layihələr üçün uyğundur. Kubernetes daha güclü (HPA, CRD, Operators, Helm), böyük ekosistemi var, böyük layihələr üçün standartdır. Swarm-ın bazardakı payı azalır, Kubernetes sənaye standartıdır.

### S2: Swarm-da manager node-lar neçə olmalıdır?
**C:** Raft consensus üçün tək sayda olmalıdır: 3, 5, 7. 3 manager — 1 düşə bilər. 5 manager — 2 düşə bilər. Formula: (N-1)/2 failure tolerans. 3 manager əksər produksiya halları üçün kifayətdir. 1 manager — HA yoxdur, produksiya üçün uyğun deyil.

### S3: Overlay network nədir?
**C:** Docker Swarm-da node-lar arası konteyner əlaqəsi üçün virtual network-dür. VXLAN tunneling istifadə edir. Konteynerlər fərqli fiziki node-larda olsa belə, eyni network-də olduqları kimi əlaqə qura bilərlər. `--opt encrypted` ilə traffic şifrələnə bilər.

### S4: Routing mesh necə işləyir?
**C:** Published port bütün swarm node-larında açılır. İstənilən node-a gələn request, həmin service-in task-ına yönləndirilir — task hansı node-da olmasından asılı olmayaraq. İngress load balancing təmin edir. External load balancer istənilən node-a point edə bilər.

### S5: Swarm secret-ləri necə işləyir?
**C:** Secret-lər Raft log-da encrypted saxlanır. Yalnız ehtiyacı olan service-lərə verilir. Konteyner daxilində `/run/secrets/<name>` faylı olaraq mount olunur (RAM-da, diskə yazılmır). Env var-dan daha təhlükəsizdir. Service silinəndə secret konteynerdən silinir.

### S6: Stack ilə service arasında fərq nədir?
**C:** Service — tək bir tətbiq (məsələn, nginx). Stack — bir neçə service-in toplusu, docker-compose.yml formatında təyin olunur. Stack = Swarm üçün docker-compose. `docker stack deploy` ilə bütün service-lər, network-lər, volume-lər birlikdə yaradılır.

### S7: Swarm-dan Kubernetes-ə miqrasiya nə vaxt düşünülməlidir?
**C:** Layihə böyüyəndə (daha çox service, daha kompleks deployment), HPA lazım olanda, Helm chart-lar istifadə etmək istədikdə, cloud-native ekosistem (service mesh, monitoring) lazım olanda, komandada K8s biliyi artanda. Kiçik layihələrdə Swarm yetərlidir.

## Best Practices

1. **3+ manager node istifadə edin** — HA üçün
2. **Manager node-larda iş yükü işlətməyin** — `--constraint node.role==worker`
3. **Overlay network-ləri ayırın** — frontend/backend ayrı
4. **Secret istifadə edin** — env var əvəzinə
5. **Update-lərdə `start-first` order istifadə edin** — downtime azaldır
6. **Health check əlavə edin** — sağlam olmayan task-ları avtomatik əvəz edir
7. **Resource limit qoyun** — `--limit-cpu`, `--limit-memory`
8. **Node label-ları istifadə edin** — placement constraints üçün
9. **Drain node maintenance üçün** — `docker node update --availability drain`
10. **Monitoring qurun** — Prometheus + Grafana Swarm üçün də işləyir
