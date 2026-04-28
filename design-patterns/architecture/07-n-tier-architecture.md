# N-Tier Architecture (Senior)

## İcmal

N-Tier Architecture — tətbiqin fiziki olaraq ayrı serverlərdə çalışan təbəqələrə bölünməsidir. Ən vacib ayrım: **Tier = fiziki ayrılma** (ayrı server/process), **Layer = məntiqi ayrılma** (eyni serverdə, ayrı kod modulu). Bu iki konsepti qarışdırmaq ən geniş yayılmış səhvdir. Tipik PHP tətbiqi 3-Tier-dir: Web Tier (Nginx) + Application Tier (PHP-FPM) + Data Tier (MySQL, Redis).

## Niyə Vacibdir

Deployment arxitekturası haqqında danışmaq, infrastructure qərarlarını əsaslandırmaq, security və scalability-ni izah etmək Senior developer-in sahəsidir. Niyə DB port-u internet-ə açılmamalıdır (2-Tier problemi), niyə app server-lər horizontal scale edilir amma DB ayrıca qalır (3-Tier üstünlüyü), mikroservis arxitekturasında N-Tier necə görünür — bunları izah etmək lazımdır.

## Əsas Anlayışlar

- **Tier**: fiziki ayrılma — ayrı server, ayrı process, network üzərindən kommunikasiya
- **Layer**: məntiqi ayrılma — eyni serverdə, kod strukturu (Controller, Service, Repository)
- **2-Tier** (Client-Server): client birbaşa DB-yə qoşulur; desktop ERP sistemlər; security riski — DB port client-ə açıqdır
- **3-Tier**: Presentation (browser) + Logic (PHP app server) + Data (DB); ən geniş yayılmış
- **4-Tier**: Client + Web Tier (Nginx, static, SSL) + Application Tier (PHP-FPM, business) + Data Tier
- **Private Subnet**: DB server-i internet-ə açmamaq; yalnız app server IP-lərindən əlçatan olmaq — 3-Tier security tələbi

## Praktik Baxış

- **Real istifadə**: hər production PHP deployment-i N-Tier-dir; AWS/GCP-də security group qaydaları tier-lər arası kommunikasiyanı müəyyən edir
- **Trade-off-lar**: security artır, hər tier müstəqil scale edilir, fault isolation; lakin network latency artır (tier-lər arası), daha çox infrastructure, operational complexity artır
- **Hansı hallarda istifadə etməmək**: lokal development-də tek server kifayət edir; çok kiçik tətbiqlər üçün overhead haqlı olmaya bilər
- **Common mistakes**: Layer ilə Tier-i eyniləşdirmək; "3-Tier architecture var" deyib hər şeyi tek serverdə işlətmək

### Anti-Pattern Nə Zaman Olur?

**Məntiqi ayrılıq olduqda fiziki N-Tier**: eyni app server-də Nginx + PHP-FPM + MySQL çalışdırıb "3-Tier architecture-umuz var" demək — bu məntiqi layer-dır, fiziki tier deyil. Scaling tələbatı olmadan tier ayırmaq boş infrastructure xərcidir. Əvvəlcə məntiqi layer-ları düzgün qurun, fiziki tier-ları yalnız real scalability/security tələbatında əlavə edin.

---

## N-Tier nədir?

N-Tier — aplikasiyanı fiziki olaraq ayrı serverlərdə çalışan təbəqələrə bölmək.

```
// Bu kod Tier (fiziki) və Layer (məntiqi) ayrımını və N-Tier-in məqsədlərini izah edir
Tier = Fiziki ayrılma (ayrı server/process)
Layer = Məntiqi ayrılma (eyni serverdə, ayrı kod modulu)

N-Tier deployment məqsədi:
  ✅ Scalability — hər tier ayrıca scale edilir
  ✅ Security — tier-lər arası firewall
  ✅ Maintainability — ayrı team-lər
  ✅ Reliability — bir tier çöksə digərləri işləyir
```

---

## 2-Tier

```
// Bu kod client-server (2-tier) arxitekturasının üstünlük və çatışmazlıqlarını göstərir
Client-Server arxitekturası:

┌─────────────────┐         ┌─────────────────┐
│   Client Tier   │ ──────► │   Data Tier     │
│                 │         │                 │
│  Desktop App    │         │   Database      │
│  Mobile App     │ ◄────── │   (MySQL, PG)   │
└─────────────────┘         └─────────────────┘

Client birbaşa DB-yə qoşulur.

✅ Sadə
✅ Az latency
❌ Business logic client-dədir → dağınıq
❌ DB port-u client-ə açıqdır → security risk
❌ Client güncəlləmə çətin

Nümunə: Köhnə desktop ERP sistemlər
```

---

## 3-Tier

```
// Bu kod 3-tier arxitekturasının presentation, logic və data tier-lərini göstərir
Ən geniş yayılmış arxitektura:

┌─────────────┐    ┌─────────────────┐    ┌─────────────┐
│Presentation │    │    Logic Tier   │    │  Data Tier  │
│   Tier      │───►│                 │───►│             │
│             │    │  PHP App Server │    │  Database   │
│  Browser    │◄───│  (Laravel/API)  │◄───│  (MySQL)    │
│  Mobile     │    │                 │    │  (Redis)    │
└─────────────┘    └─────────────────┘    └─────────────┘

Hər tier ayrı server(lər)də:
  Presentation: CDN, Nginx (static files)
  Logic: PHP-FPM + Nginx (application servers)
  Data: MySQL, Redis (database servers)

✅ Security: DB yalnız app server-ə açıqdır
✅ Scale: App tier horizontal scale edilir
✅ Separation of concerns
❌ Network latency (tier-lər arası)
❌ Daha çox infrastructure
```

**PHP 3-Tier deployment:**

```
// Bu kod PHP tətbiqi üçün load balancer, app server və DB-dən ibarət 3-tier deployment-i göstərir
Internet
   │
   ▼
┌──────────────────┐
│   Load Balancer  │  (Nginx/HAProxy/AWS ALB)
│   (public IP)    │
└────────┬─────────┘
         │
   ┌─────┴──────┐
   ▼            ▼
┌──────┐    ┌──────┐    ← Presentation + Logic tier
│App 1 │    │App 2 │      (PHP-FPM + Nginx)
│      │    │      │      Private subnet
└──┬───┘    └──┬───┘
   └─────┬─────┘
         │
   ┌─────┴──────┐
   ▼            ▼
┌──────┐    ┌──────┐    ← Data tier
│MySQL │    │Redis │      Private subnet (DB only subnet)
│Master│    │Cache │      No public access!
└──────┘    └──────┘
```

---

## 4-Tier və N-Tier

```
// Bu kod 4-tier web arxitekturasını və mikroservis N-tier variantını göstərir
4-Tier (Web Application):

┌─────────────┐
│   Client    │  Browser, Mobile
└──────┬──────┘
       │ HTTPS
┌──────▼──────┐
│   Web Tier  │  Nginx (static files, SSL termination, CDN)
└──────┬──────┘
       │ HTTP (internal)
┌──────▼──────┐
│ Application │  PHP-FPM (business logic, API)
│    Tier     │
└──────┬──────┘
       │
┌──────▼──────┐
│  Data Tier  │  MySQL, Redis, Elasticsearch, S3
└─────────────┘

Mikroservis N-Tier:

┌────────────────────────────────────────────────────────┐
│                    Client Tier                         │
└────────────────────────┬───────────────────────────────┘
                         │
┌────────────────────────▼───────────────────────────────┐
│                  API Gateway Tier                      │
└──────┬──────────────────┬──────────────────────────────┘
       │                  │
┌──────▼──────┐    ┌──────▼──────┐
│  Order Svc  │    │  User Svc   │   ← Service Tier
└──────┬──────┘    └──────┬──────┘
       │                  │
┌──────▼──────┐    ┌──────▼──────┐
│  Orders DB  │    │  Users DB   │   ← Data Tier
└─────────────┘    └─────────────┘
```

---

## Fiziki vs Məntiqi Tier

```
// Bu kod məntiqi layer-ləri (kod) ilə fiziki tier-lərin (server) fərqini izah edir
Məntiqi Layer (kod strukturu):
  Presentation Layer → Controller
  Business Layer     → Service, Domain
  Data Layer         → Repository, Model
  
  Bunlar eyni serverdə olur — sadəcə kod ayrıdır.

Fiziki Tier (deployment):
  Tier 1: Web server (Nginx)
  Tier 2: App server (PHP-FPM)
  Tier 3: DB server (MySQL)
  
  Bunlar ayrı maşınlarda/prosesslərdə olur.

Eyni kod, fərqli deployment:
  Monolith (1 server):
    [Nginx + PHP-FPM + MySQL] → 1 tier, 3 layer
    
  3-Tier (3 server):
    [Nginx] + [PHP-FPM] + [MySQL] → 3 tier, 3 layer
    
  Lakin:
    [Nginx + PHP-FPM] + [MySQL] → 2 tier, 3 layer
```

---

## PHP Deployment Nümunəsi

*PHP Deployment Nümunəsi üçün kod nümunəsi:*
```nginx
// Bu kod web tier üçün Nginx-in PHP-FPM app tier-ə sorğu yönləndirməsini göstərir
# Web Tier: Nginx konfiqurasiyası
# /etc/nginx/sites-available/app.conf

upstream php_fpm {
    server app-server-1:9000;  # App Tier
    server app-server-2:9000;
}

server {
    listen 80;
    server_name api.example.com;
    
    # Static files — web tier-dən birbaşa
    location /static/ {
        root /var/www/public;
        expires 1y;
    }
    
    # PHP — app tier-ə yönləndir
    location / {
        fastcgi_pass php_fpm;
        fastcgi_param SCRIPT_FILENAME /var/www/public/index.php;
        include fastcgi_params;
    }
}
```

*include fastcgi_params; üçün kod nümunəsi:*
```php
// Bu kod app tier-dən data tier-dəki DB və cache serverlərinə qoşulmanı göstərir
// App Tier: config/database.php
// Data Tier-ə qoşulma
'mysql' => [
    'host'     => env('DB_HOST', 'db-server'),  // Ayrı Data Tier server
    'port'     => 3306,
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
],

'redis' => [
    'host'     => env('REDIS_HOST', 'cache-server'),  // Ayrı Cache server
    'port'     => 6379,
],
```

*'port'     => 6379, üçün kod nümunəsi:*
```yaml
# Bu kod docker-compose ilə 3-tier arxitekturasını simülasiya edir
# docker-compose.yml — 3-Tier simulation
services:
  nginx:           # Web Tier
    image: nginx
    ports: ["80:80"]
    depends_on: [php]

  php:             # Application Tier
    build: .
    environment:
      DB_HOST: mysql
      REDIS_HOST: redis
    depends_on: [mysql, redis]

  mysql:           # Data Tier
    image: mysql:8
    volumes: [mysql_data:/var/lib/mysql]

  redis:           # Data Tier
    image: redis:7
```

---

## Layered Architecture ilə fərqi

```
// Bu kod Layered Architecture ilə N-Tier Architecture-ın fərqini vizual olaraq göstərir
┌────────────────────────────────────────────────────────────┐
│              Layered Architecture                          │
│  (Məntiqi — eyni prosesdə, kod strukturu)                 │
│                                                            │
│  Controller → Service → Repository → Database             │
│  (hamısı eyni PHP prosesindədir)                          │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│              N-Tier Architecture                           │
│  (Fiziki — ayrı server/proseslər)                         │
│                                                            │
│  [Nginx Server] → [PHP Server] → [MySQL Server]           │
│  (network üzərindən kommunikasiya)                        │
└────────────────────────────────────────────────────────────┘

Bir-birini tamamlayır:
  3-Tier deployment + Layered kod strukturu = Tipik PHP app
```

---

## İntervyu Sualları

**1. Tier vs Layer fərqi nədir?**
Layer məntiqi ayrılmadır — eyni prosesdə, sadəcə kod strukturu (Controller, Service, Repository). Tier fiziki ayrılmadır — ayrı server/proseslərdə çalışır, network üzərindən kommunikasiya edir.

**2. 3-Tier arxitekturanın üstünlükləri nələrdir?**
Security: DB yalnız app tier-ə açıqdır, internet-ə deyil. Scalability: App tier-i ayrıca horizontal scale etmək olar. Separation: hər tier müstəqil update/deploy edilə bilər. Fault isolation: bir tier çöksə digərləri işləyir.

**3. Eyni kodun 1-tier və 3-tier deployment fərqi nədir?**
1-tier: Nginx + PHP-FPM + MySQL eyni serverdə. 3-tier: hər biri ayrı serverdə. Kod dəyişmir, yalnız konfiqurasiya (DB_HOST, environment vars). 3-tier daha secure və scalable amma daha çox infrastructure.

**4. Mikroservislər N-tier arxitekturasıdırmı?**
Hər iki konsepti özündə birləşdirir. Mikroservis = hər servis ayrı tier (fiziki). Lakin hər servisin daxilindəki kod layered ola bilər. API Gateway əlavə tier kimi çıxış edir.

**5. 2-tier arxitekturanın ən böyük security problemi nədir?**
DB port-u client-ə birbaşa açılır. Client (desktop app, mobile) DB credential-larına sahib olur. Reverse engineering ilə credential-lar əldə edilə bilər. Bütün business logic client-dədir — manipulation asandır. 3-tier bu problemi həll edir: DB yalnız app server-ə açıqdır.

**6. N-tier arxitekturasında tier-lər arasında kommunikasiya necə qorunur?**
Internal network-də private subnet-lər. Tier-lər arası firewall qaydaları (yalnız lazımi port-lar açıq). Web tier → App tier: HTTP/FastCGI. App tier → Data tier: DB protocol (3306), Redis (6379) — yalnız app server IP-lərdən. TLS/mTLS daxili kommunikasiya üçün. Secrets management (AWS Secrets Manager, Vault) ilə credential-lar.

---

## Anti-patternlər

**1. Tier-ləri atlaraq birbaşa DB-yə daxil olmaq**
Presentation layer-dan (controller) birbaşa DB sorğusu yazmaq, business logic qatını atlamaq — validation yoxlanmır, authorization bypass edilir, business qaydaları pozulur. Hər sorğu düzgün layer ardıcıllığından keçsin: Controller → Service → Repository → DB.

**2. Layer-ları fiziki tier ilə qarışdırmaq**
"3-tier arxitektura var" deyib hər şeyi bir serverdə işlətmək — deployment tier-ları yox, məntiq layer-ları mövcuddur, scale etmək mümkün olmur. Layer: kod strukturu (Service, Repository); Tier: fiziki ayrılıq (app server, DB server). Bunları ayrı anlayış kimi başa düşün.

**3. Business logic-i Presentation layer-da yazmaq**
Controller-da kompleks if-else şərtləri, hesablama, validation — test yazmaq çətinləşir, eyni logic başqa yerdə lazım olanda dublikat yaranır. Business logic-i Service layer-da saxlayın: controller yalnız HTTP request al, validate et, service-i çağır, cavab qaytar.

**4. Data layer-da business logic saxlamaq**
Repository-də `if ($user->isPremium()) { ... }` şərtləri — Repository yalnız data access üçündur, business qaydaları deyil. Business qaydaları Service layer-a köçürün; Repository-ni sorğu-nəticə məntiqinə məhdudlaşdırın.

**5. Hər layer-ı müstəqil test etməmək**
Bütün sistemi inteqrasiya testi ilə test etmək, unit test yazmamaq — bir layer-da xəta haradan gəldiyini anlamaq çətinləşir, test yavaş, əhatə azalır. Hər layer-ı mock-larla müstəqil test edin: Service unit test, Repository inteqrasiya testi, Controller feature test.

**6. Presentation layer-da DB connection açmaq**
`$pdo = new PDO(...)` middleware-də ya da controller constructor-da — connection pool-u idarə etmək çətindir, DB credential-ları presentation qatına sızır. DB bağlantısı yalnız Data layer-da (Repository/QueryBuilder) açılsın; dependency injection ilə inject edin.
