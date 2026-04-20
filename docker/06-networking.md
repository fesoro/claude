# Docker Networking

## Nədir? (What is it?)

Docker networking konteynerlərin bir-biri ilə və xarici dünya ilə əlaqə qurmasını idarə edir. Docker bir neçə şəbəkə driver-i təqdim edir — hər birinin öz istifadə sahəsi var. Konteynerlər eyni şəbəkədə olduqda service adı ilə bir-birini tapır (DNS resolution).

## Əsas Konseptlər

### Şəbəkə Tipləri

```
┌─────────────────────────────────────────────────────┐
│                    Docker Host                       │
│                                                      │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐            │
│  │  bridge   │ │  host    │ │  none    │            │
│  │ (default) │ │          │ │          │            │
│  │           │ │ No NAT   │ │ No net   │            │
│  │ 172.17.x  │ │ Host net │ │ Isolated │            │
│  └──────────┘ └──────────┘ └──────────┘            │
│                                                      │
│  ┌──────────┐ ┌──────────┐                          │
│  │ overlay  │ │ macvlan  │                          │
│  │          │ │          │                          │
│  │ Multi-   │ │ Physical │                          │
│  │ host     │ │ network  │                          │
│  └──────────┘ └──────────┘                          │
└─────────────────────────────────────────────────────┘
```

### 1. Bridge Network (Default)

Default şəbəkə driver-i. Konteyner yaradılanda avtomatik `bridge` şəbəkəsinə qoşulur.

```bash
# Default bridge şəbəkəsi
docker run -d --name web nginx
docker inspect web | grep IPAddress
# 172.17.0.x

# Default bridge-in problemi: DNS resolution işləmir!
docker run -d --name app1 nginx
docker run -d --name app2 nginx
docker exec app1 ping app2  # XƏTA — ad ilə tapmır

# Həll: Xüsusi bridge şəbəkəsi yaratmaq
docker network create mynet
docker run -d --name app1 --network mynet nginx
docker run -d --name app2 --network mynet nginx
docker exec app1 ping app2  # İşləyir! DNS resolution var
```

**Default vs Custom Bridge:**

| Xüsusiyyət | Default Bridge | Custom Bridge |
|-------------|---------------|---------------|
| DNS Resolution | Yox (yalnız IP) | Var (service adı ilə) |
| İzolyasiya | Bütün konteynerlər eyni şəbəkədə | Seçilmiş konteynerlər |
| Konfiqurasiya | Məhdud | Tam (subnet, gateway) |
| Auto-connect | Bəli | Əl ilə |

### 2. Host Network

Konteyner host-un şəbəkəsini birbaşa istifadə edir. NAT (Network Address Translation) yoxdur.

```bash
docker run -d --network host nginx
# Nginx birbaşa host-un 80 portunda dinləyir
# Port mapping (-p) lazım deyil

# Performans üçün yaxşıdır amma izolyasiya azdır
# Yalnız Linux-da tam işləyir
```

### 3. Overlay Network

Çox host-lu şəbəkə. Docker Swarm və ya Kubernetes ilə istifadə olunur.

```bash
# Swarm mode-da overlay yaratmaq
docker network create --driver overlay --attachable my-overlay

# Service-lər overlay üzərindən əlaqə qura bilər
docker service create --name web --network my-overlay nginx
```

### 4. Macvlan Network

Konteynerə fiziki şəbəkədə öz MAC adresi verir. Konteyner fiziki cihaz kimi görünür.

```bash
docker network create -d macvlan \
  --subnet=192.168.1.0/24 \
  --gateway=192.168.1.1 \
  -o parent=eth0 \
  my-macvlan

docker run -d --network my-macvlan \
  --ip=192.168.1.100 \
  nginx
```

### 5. None Network

Heç bir şəbəkə yoxdur. Tam izolə edilmiş konteyner.

```bash
docker run -d --network none nginx
# Xarici dünya ilə əlaqəsi yoxdur
# Yalnız internal processing üçün
```

### DNS Resolution

Xüsusi bridge şəbəkələrində Docker daxili DNS server (127.0.0.11) işlədir.

```bash
# Konteyner adı ilə DNS resolution
docker network create app-net

docker run -d --name mysql --network app-net mysql:8.0
docker run -d --name redis --network app-net redis:7
docker run -d --name app --network app-net php:8.3-fpm

# app konteyneri mysql və redis-i ad ilə tapır
docker exec app ping mysql   # 172.18.0.2
docker exec app ping redis   # 172.18.0.3

# Network alias-lar
docker run -d --name mysql-primary \
  --network app-net \
  --network-alias db \
  mysql:8.0
# Həm "mysql-primary" həm "db" adı ilə tapılır
```

### Port Mapping

```bash
# host:container
docker run -p 8080:80 nginx

# Yalnız localhost
docker run -p 127.0.0.1:8080:80 nginx

# Random host port
docker run -p 80 nginx
docker port <container_id>  # Hansı porta map olunduğunu görmək

# UDP port
docker run -p 53:53/udp dns-server

# Çoxlu port
docker run -p 80:80 -p 443:443 nginx
```

### Şəbəkə Əmrləri

```bash
# Şəbəkələri siyahılamaq
docker network ls

# Şəbəkə yaratmaq
docker network create --driver bridge \
  --subnet=172.28.0.0/16 \
  --ip-range=172.28.5.0/24 \
  --gateway=172.28.5.254 \
  my-custom-net

# Şəbəkə məlumatı
docker network inspect my-custom-net

# Konteyneri şəbəkəyə qoşmaq
docker network connect my-custom-net container_name

# Konteyneri şəbəkədən ayırmaq
docker network disconnect my-custom-net container_name

# Şəbəkə silmək
docker network rm my-custom-net

# İstifadə olunmayan şəbəkələri silmək
docker network prune
```

### Şəbəkə İzolyasiyası

```yaml
# docker-compose.yml — frontend/backend ayrılması
services:
  nginx:
    image: nginx
    networks:
      - frontend        # Xarici dünya ilə əlaqə
      - backend         # App ilə əlaqə

  app:
    image: php:8.3-fpm
    networks:
      - backend         # Nginx və DB ilə əlaqə

  mysql:
    image: mysql:8.0
    networks:
      - backend         # Yalnız app ilə əlaqə
      # frontend-ə qoşulmur — xaricdən əlçatmazdır!

networks:
  frontend:
    driver: bridge
  backend:
    driver: bridge
    internal: true      # Xarici internet əlaqəsi yoxdur
```

### Konteyner Arasında Əlaqə

```bash
# Eyni şəbəkədə olan konteynerlər:
# 1. Service adı ilə (DNS)
docker exec app ping mysql

# 2. IP adresi ilə
docker exec app ping 172.18.0.2

# 3. Network alias ilə
docker exec app ping db

# Fərqli şəbəkədə olan konteynerlər:
# Birbaşa əlaqə qura bilmirlər!
# Həll: konteyneri hər iki şəbəkəyə qoşmaq
docker network connect frontend app
```

## Praktiki Nümunələr

### Multi-Service Şəbəkə Topologiyası

```yaml
services:
  # Public-facing load balancer
  traefik:
    image: traefik:v2.10
    ports:
      - "80:80"
      - "443:443"
    networks:
      - public
      - services

  # API Gateway
  api-gateway:
    image: nginx
    networks:
      - services
      - microservices

  # Microservices
  user-service:
    build: ./services/user
    networks:
      - microservices
      - databases

  order-service:
    build: ./services/order
    networks:
      - microservices
      - databases
      - messaging

  # Data layer
  postgres:
    image: postgres:16
    networks:
      - databases

  rabbitmq:
    image: rabbitmq:3-management
    networks:
      - messaging

networks:
  public:          # Traefik -> Internet
  services:        # Traefik -> API Gateway
  microservices:   # API Gateway -> Services
  databases:       # Services -> Databases
    internal: true
  messaging:       # Services -> Message Queue
    internal: true
```

## PHP/Laravel ilə İstifadə

### Laravel App MySQL/Redis ilə Əlaqə

```yaml
# docker-compose.yml
services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html:ro
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    networks:
      - frontend
      - backend
    depends_on:
      - app

  app:
    build: .
    environment:
      # MySQL-ə service adı ilə qoşulur
      DB_HOST: mysql            # <-- "mysql" service adı = DNS adı
      DB_PORT: 3306
      DB_DATABASE: laravel
      DB_USERNAME: laravel
      DB_PASSWORD: secret

      # Redis-ə service adı ilə qoşulur
      REDIS_HOST: redis         # <-- "redis" service adı = DNS adı
      REDIS_PORT: 6379

      # Konteyner daxili əlaqə üçün host adları
      CACHE_DRIVER: redis
      SESSION_DRIVER: redis
      QUEUE_CONNECTION: redis
    networks:
      - backend

  mysql:
    image: mysql:8.0
    # Port mapping yalnız xarici əlaqə üçün (phpMyAdmin, IDE)
    ports:
      - "3306:3306"     # Development-də host-dan əlçatan
    environment:
      MYSQL_DATABASE: laravel
      MYSQL_USER: laravel
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: rootsecret
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - backend

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"     # Development-də host-dan əlçatan
    volumes:
      - redis-data:/data
    networks:
      - backend

networks:
  frontend:
    driver: bridge
  backend:
    driver: bridge

volumes:
  mysql-data:
  redis-data:
```

### Laravel .env Docker Mühiti üçün

```env
# .env
DB_CONNECTION=mysql
DB_HOST=mysql               # Docker service adı!
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret

REDIS_HOST=redis             # Docker service adı!
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail (MailHog konteyneri)
MAIL_MAILER=smtp
MAIL_HOST=mailhog            # Docker service adı!
MAIL_PORT=1025
```

### Nginx -> PHP-FPM Əlaqəsi

```nginx
# docker/nginx/default.conf
server {
    listen 80;
    root /var/www/html/public;

    location ~ \.php$ {
        # "app" — PHP-FPM konteynerinin service adıdır
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## İntervyu Sualları

### 1. Docker-da hansı şəbəkə tipləri var?
**Cavab:** Bridge (default, tək host, NAT ilə), host (host şəbəkəsini paylaşır, NAT yoxdur), overlay (çox host, Swarm/K8s), macvlan (fiziki şəbəkədə öz MAC adresi), none (şəbəkə yoxdur). Ən çox bridge və overlay istifadə olunur.

### 2. Default bridge və custom bridge arasında fərq nədir?
**Cavab:** Default bridge-də DNS resolution işləmir (yalnız IP ilə əlaqə), bütün konteynerlər eyni şəbəkədə olur. Custom bridge-də DNS resolution var (service adı ilə əlaqə), izolyasiya təmin olunur, konfiqurasiya imkanları genişdir.

### 3. Konteynerlər bir-birini necə tapır?
**Cavab:** Xüsusi bridge şəbəkəsində Docker daxili DNS server (127.0.0.11) işlədilir. Konteynerlər service adı və ya network alias ilə bir-birini tapır. Compose-da service adı avtomatik DNS adı olur.

### 4. Port mapping necə işləyir?
**Cavab:** `-p host_port:container_port` ilə host-un portunu konteynerin portuna yönləndirir. Docker iptables qaydaları yaradır. `-p 127.0.0.1:8080:80` yalnız localhost-dan əlçatan edir.

### 5. `internal: true` nə edir?
**Cavab:** Şəbəkənin xarici internet əlaqəsini bağlayır. Bu şəbəkədəki konteynerlər yalnız bir-biri ilə əlaqə qura bilir. Database və internal service-lər üçün idealdır.

### 6. Laravel konteyneri MySQL-ə necə qoşulur?
**Cavab:** Docker Compose-da eyni şəbəkədə olduqda, Laravel `DB_HOST=mysql` ilə qoşulur — burada "mysql" service adıdır. Docker DNS bunu MySQL konteynerinin IP adresinə həll edir. Port mapping lazım deyil, konteynerlər daxili şəbəkədə birbaşa əlaqə qurur.

### 7. Host networking nə vaxt istifadə olunur?
**Cavab:** Yüksək performans lazım olduqda (NAT overhead yoxdur), çoxlu port lazım olduqda, legacy tətbiqlər üçün. Dezavantajı izolyasiyanın az olmasıdır. Production-da adətən bridge tövsiyə olunur.

## Best Practices

1. **Həmişə xüsusi bridge şəbəkəsi yaradın** — Default bridge istifadə etməyin (DNS yoxdur).
2. **Şəbəkələri məqsədə görə ayırın** — Frontend, backend, database ayrı şəbəkələrdə.
3. **`internal: true` istifadə edin** — Database şəbəkəsinin interneti olmamalıdır.
4. **Port mapping-i minimuma endirin** — Yalnız xarici əlaqə lazım olanlarda.
5. **`127.0.0.1` ilə bind edin** — Development-də portu yalnız localhost-da açın.
6. **Network alias istifadə edin** — Daha çevik DNS adlandırma.
7. **Health check ilə depends_on** — Şəbəkə əlaqəsinin hazır olduğunu təmin edin.
8. **Şəbəkə debug üçün `docker network inspect`** — Problemləri araşdırın.
9. **Container adlarını DNS-ə uyğun seçin** — Sadə, qısa, mənalı adlar.
10. **Production-da overlay istifadə edin** — Çox host-lu deployment üçün.
