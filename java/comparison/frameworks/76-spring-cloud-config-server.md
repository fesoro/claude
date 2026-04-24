# Spring Cloud Config Server vs Laravel Config — Dərin Müqayisə

> **Seviyye:** Expert ⭐⭐⭐⭐

## Giriş

Mikroservis mühitində hər xidmətin öz `application.yml` və ya `.env` faylı olur. Amma xidmət sayı 20–50-yə çatanda sual yaranır: "Mən DB şifrəni bir yerdə dəyişsəm, bütün xidmətlərə necə yayılsın?" Cavab — **centralized config**.

**Spring Cloud Config Server** bu problemi həll edir: bir server Git reposunu (və ya Vault, JDBC, native filesystem) izləyir, bütün xidmətlər start vaxtı oradan konfiqurasiya çəkir. `@RefreshScope` və Spring Cloud Bus ilə yenidən start olmadan hot-reload etmək mümkündür.

**Laravel-də bu konsept built-in deyil.** Laravel əvəzinə belə yanaşmalara güvənir:
- `.env` + proses environment dəyişənləri
- `config/*.php` PHP-cached fayllar (`php artisan config:cache`)
- Xarici secret manager (Vault, AWS Secrets Manager, Doppler)
- Kubernetes ConfigMap və Secret mount

Bu fayl Spring Cloud Config-un bütün imkanlarını göstərir və Laravel tərəfində eyni nəticəni necə əldə etmək lazım olduğunu izah edir.

---

## Spring-də istifadəsi

### 1) Config Server quraşdırmaq

```xml
<!-- config-server/pom.xml -->
<dependencies>
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-config-server</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-security</artifactId>
    </dependency>
</dependencies>

<dependencyManagement>
    <dependencies>
        <dependency>
            <groupId>org.springframework.cloud</groupId>
            <artifactId>spring-cloud-dependencies</artifactId>
            <version>2023.0.3</version>
            <type>pom</type>
            <scope>import</scope>
        </dependency>
    </dependencies>
</dependencyManagement>
```

```java
@SpringBootApplication
@EnableConfigServer
public class ConfigServerApplication {
    public static void main(String[] args) {
        SpringApplication.run(ConfigServerApplication.class, args);
    }
}
```

```yaml
# config-server/src/main/resources/application.yml
server:
  port: 8888

spring:
  application:
    name: config-server
  security:
    user:
      name: ${CONFIG_USER:configuser}
      password: ${CONFIG_PASSWORD:s3cret}
  cloud:
    config:
      server:
        git:
          uri: https://github.com/mycompany/config-repo
          default-label: main
          clone-on-start: true
          timeout: 10
          username: ${GIT_USERNAME}
          password: ${GIT_TOKEN}
          search-paths:
            - '{application}'        # order-service/, payment-service/
            - shared
          force-pull: true
        encrypt:
          enabled: true

encrypt:
  key: ${ENCRYPT_KEY:a-very-long-symmetric-key-please-change}

management:
  endpoints:
    web:
      exposure:
        include: health, info, refresh, busrefresh, env
```

### 2) Git repository strukturu

```
config-repo/
├── application.yml              # bütün xidmətlər üçün ortaq
├── application-dev.yml
├── application-prod.yml
├── order-service.yml            # order-service üçün default
├── order-service-dev.yml
├── order-service-prod.yml
├── payment-service.yml
└── shared/
    └── database.yml
```

```yaml
# config-repo/order-service-prod.yml
server:
  port: 8081

spring:
  datasource:
    url: jdbc:postgresql://db-prod.internal:5432/orders
    username: orders_app
    password: '{cipher}AQAcx4...'   # encrypted via POST /encrypt
  jpa:
    hibernate:
      ddl-auto: validate

order:
  max-items: 50
  allow-partial-fulfillment: true
  notify:
    slack-webhook: '{cipher}AQBj9k...'

logging:
  level:
    com.example.order: INFO
```

### 3) Şifrələmə (Encryption)

```bash
# encrypt bir dəyəri
curl -u configuser:s3cret -X POST \
  --data-urlencode 'my-secret-password' \
  http://config-server:8888/encrypt

# nəticə: AQAcx4fJ2XqH... (bunu YAML-a '{cipher}AQAcx4fJ2XqH...' kimi yaz)

# decrypt yoxlamaq
curl -u configuser:s3cret -X POST \
  --data-urlencode 'AQAcx4fJ2XqH...' \
  http://config-server:8888/decrypt
```

Production üçün symmetric key yox, RSA keystore tövsiyə olunur:

```yaml
encrypt:
  key-store:
    location: classpath:/server.jks
    alias: configkey
    password: ${KEYSTORE_PASS}
    secret: ${KEY_SECRET}
```

### 4) Vault-backed config

```yaml
# config-server/application.yml
spring:
  profiles:
    active: vault
  cloud:
    config:
      server:
        vault:
          host: vault.internal
          port: 8200
          scheme: https
          backend: secret
          default-key: application
          kv-version: 2
          profile-separator: /
          authentication: TOKEN
          token: ${VAULT_TOKEN}
```

Config server Vault-dan `secret/data/order-service/prod` pathi çəkir. Eyni endpoint-dən həm Git dəyişənləri, həm də Vault secret-ləri qayıdır — composite mənbə.

### 5) JDBC-backed config

```yaml
spring:
  profiles:
    active: jdbc
  cloud:
    config:
      server:
        jdbc:
          sql: "SELECT `KEY`, `VALUE` FROM PROPERTIES WHERE APPLICATION=? AND PROFILE=? AND LABEL=?"
          order: 1
  datasource:
    url: jdbc:postgresql://config-db:5432/config
    username: config
    password: ${CONFIG_DB_PASS}
```

```sql
CREATE TABLE PROPERTIES (
  APPLICATION VARCHAR(128) NOT NULL,
  PROFILE     VARCHAR(64)  NOT NULL,
  LABEL       VARCHAR(64)  NOT NULL DEFAULT 'master',
  `KEY`       VARCHAR(256) NOT NULL,
  `VALUE`     TEXT,
  PRIMARY KEY (APPLICATION, PROFILE, LABEL, `KEY`)
);
```

### 6) Config Client (xidmət tərəfi)

```xml
<!-- order-service/pom.xml -->
<dependencies>
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-config</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-actuator</artifactId>
    </dependency>
</dependencies>
```

```yaml
# order-service/src/main/resources/application.yml
spring:
  application:
    name: order-service
  profiles:
    active: ${SPRING_PROFILES_ACTIVE:dev}
  config:
    import: 'optional:configserver:http://configuser:s3cret@config-server:8888'
  cloud:
    config:
      fail-fast: true
      retry:
        initial-interval: 1000
        max-attempts: 6
        multiplier: 1.5

management:
  endpoints:
    web:
      exposure:
        include: health, info, refresh
```

### 7) `@RefreshScope` — hot reload

```java
@RestController
@RefreshScope
public class OrderLimitsController {

    @Value("${order.max-items:10}")
    private int maxItems;

    @GetMapping("/limits")
    public Map<String, Object> limits() {
        return Map.of("maxItems", maxItems);
    }
}
```

Config-i Git-də dəyişəndən sonra yenidən oxumaq üçün:

```bash
# yalnız bir instance-a tətbiq et
curl -X POST http://order-service-1/actuator/refresh
```

### 8) Spring Cloud Bus — broadcast refresh

Bütün instance-lara eyni anda refresh göndərmək üçün Bus (RabbitMQ və ya Kafka) lazımdır.

```xml
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-bus-amqp</artifactId>
</dependency>
```

```yaml
spring:
  rabbitmq:
    host: rabbitmq
    port: 5672
    username: guest
    password: guest
```

```bash
# Config Server yenilə və bütün xidmətlərə broadcast göndər
curl -X POST http://config-server:8888/actuator/busrefresh
```

Git webhook Config Server-in `/monitor` endpoint-inə zəng çəkəndə bütün dəyişmiş file-lar üçün avtomatik `busrefresh` tetiklenir.

### 9) Profile-based configuration

```yaml
# bootstrap çağırışı
# http://config-server:8888/order-service/prod/main
# qaydası: /{application}/{profile}/{label}

# Spring client həm `order-service.yml`, həm `order-service-prod.yml` birləşdirir
# prod profile üstün gəlir
```

```java
@Profile("prod")
@Configuration
public class ProdOnlyBeans {
    @Bean
    public AuditLogger auditLogger() {
        return new CloudAuditLogger();
    }
}

@Profile("!prod")
@Configuration
public class DevBeans {
    @Bean
    public AuditLogger auditLogger() {
        return new ConsoleAuditLogger();
    }
}
```

### 10) Kubernetes ConfigMap alternativi

Spring Cloud Kubernetes varsa, Config Server əvəzinə birbaşa ConfigMap oxumaq mümkündür:

```xml
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-kubernetes-client-config</artifactId>
</dependency>
```

```yaml
spring:
  cloud:
    kubernetes:
      config:
        sources:
          - name: order-service-config
          - name: shared-config
        namespace: default
      secrets:
        enabled: true
        paths:
          - /etc/secrets
```

### 11) docker-compose.yml — lokal dev

```yaml
version: '3.9'
services:
  config-server:
    image: mycompany/config-server:latest
    ports: ["8888:8888"]
    environment:
      GIT_USERNAME: ${GIT_USER}
      GIT_TOKEN: ${GIT_TOKEN}
      ENCRYPT_KEY: a-very-long-symmetric-key
      CONFIG_USER: configuser
      CONFIG_PASSWORD: s3cret
    healthcheck:
      test: curl -f http://localhost:8888/actuator/health || exit 1
      interval: 10s

  rabbitmq:
    image: rabbitmq:3-management
    ports: ["5672:5672", "15672:15672"]

  order-service:
    image: mycompany/order-service:latest
    depends_on:
      config-server: { condition: service_healthy }
    environment:
      SPRING_PROFILES_ACTIVE: dev
      SPRING_CONFIG_IMPORT: configserver:http://configuser:s3cret@config-server:8888
      SPRING_RABBITMQ_HOST: rabbitmq
```

---

## Laravel-də istifadəsi

### 1) Ənənəvi yanaşma — `.env` + config cache

Laravel-in default metodu — hər xidmətin öz `.env` faylı. Production-da `config:cache` PHP arrayı yaddaşda saxlayır.

```bash
# .env (order-service üçün)
APP_NAME="Order Service"
APP_ENV=production
DB_HOST=db-prod.internal
DB_DATABASE=orders
DB_USERNAME=orders_app
DB_PASSWORD=very-secret

ORDER_MAX_ITEMS=50
ORDER_SLACK_WEBHOOK=https://hooks.slack.com/services/xxx
```

```php
// config/order.php
return [
    'max_items' => (int) env('ORDER_MAX_ITEMS', 10),
    'slack_webhook' => env('ORDER_SLACK_WEBHOOK'),
    'allow_partial_fulfillment' => env('ORDER_PARTIAL', true),
];
```

```bash
# deploy sonunda
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

**Məhdudiyyət:** `.env` dəyişəndə `config:cache` yenidən çağırılmalı və proses restart olmalıdır. Built-in hot-reload yoxdur.

### 2) Vault integration

```bash
composer require csharpru/vault-php
# və ya
composer require vault/vault-php-client
```

```php
// app/Providers/VaultServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Vault\Client;
use Vault\AuthenticationStrategies\TokenAuthenticationStrategy;

class VaultServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            $client = new Client(
                new \Zend\Uri\Uri(config('vault.url')),
                new \GuzzleHttp\Client()
            );
            $client->setAuthenticationStrategy(
                new TokenAuthenticationStrategy(config('vault.token'))
            );
            return $client;
        });
    }

    public function boot(Client $vault): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $secrets = cache()->remember('vault:order-service', 300, function () use ($vault) {
            $resp = $vault->read('secret/data/order-service/prod');
            return $resp->getData()['data'];
        });

        foreach ($secrets as $key => $value) {
            config(['order.' . $key => $value]);
        }
    }
}
```

### 3) AWS Secrets Manager

```bash
composer require aws/aws-sdk-php
```

```php
// app/Support/Secrets.php
namespace App\Support;

use Aws\SecretsManager\SecretsManagerClient;

class Secrets
{
    public static function load(string $name): array
    {
        $client = new SecretsManagerClient([
            'version' => 'latest',
            'region'  => env('AWS_DEFAULT_REGION', 'eu-central-1'),
        ]);

        $result = $client->getSecretValue(['SecretId' => $name]);
        return json_decode($result['SecretString'], true);
    }
}

// bootstrap/app.php və ya config provider-də
$secrets = cache()->remember(
    'aws-secrets:' . app()->environment(),
    600,
    fn () => \App\Support\Secrets::load('order-service/' . app()->environment())
);

foreach ($secrets as $k => $v) {
    putenv("{$k}={$v}");
    $_ENV[$k] = $v;
}
```

### 4) Kubernetes ConfigMap + Secret mount

Laravel üçün ən yayılmış pattern — ConfigMap-ı env kimi və ya file kimi mount etmək.

```yaml
# k8s/configmap.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: order-service-config
data:
  APP_ENV: "production"
  ORDER_MAX_ITEMS: "50"
  LOG_CHANNEL: "stderr"
---
apiVersion: v1
kind: Secret
metadata:
  name: order-service-secrets
type: Opaque
stringData:
  DB_PASSWORD: very-secret
  ORDER_SLACK_WEBHOOK: https://hooks.slack.com/services/xxx
```

```yaml
# k8s/deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: order-service
spec:
  template:
    spec:
      containers:
      - name: php
        image: mycompany/order-service:latest
        envFrom:
          - configMapRef:
              name: order-service-config
          - secretRef:
              name: order-service-secrets
```

### 5) Hot-reload config via observer/event

Laravel-də `.env` dəyişkənliyini runtime-da yaymaq üçün custom observer qurmaq mümkündür. Bu Spring-in `@RefreshScope`-una bənzər pattern-dir.

```php
// config/dynamic.php
return [
    'order_max_items' => Cache::get('config:order_max_items', 10),
    'feature_new_checkout' => Cache::get('config:feature_new_checkout', false),
];

// app/Models/DynamicConfig.php
class DynamicConfig extends Model
{
    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(function (DynamicConfig $cfg) {
            Cache::forever("config:{$cfg->key}", $cfg->value);
            event(new \App\Events\ConfigChanged($cfg->key, $cfg->value));
        });

        static::deleted(function (DynamicConfig $cfg) {
            Cache::forget("config:{$cfg->key}");
            event(new \App\Events\ConfigChanged($cfg->key, null));
        });
    }
}

// app/Listeners/RefreshRuntimeConfig.php
class RefreshRuntimeConfig
{
    public function handle(ConfigChanged $event): void
    {
        // runtime config-i yenilə (yalnız current proses üçün)
        config(['dynamic.' . $event->key => $event->value]);

        // bütün proseslərə yay (Octane/Horizon)
        broadcast(new ConfigBroadcast($event->key, $event->value))
            ->toOthers();
    }
}
```

### 6) Laravel + Consul KV (Spring Cloud-a ən yaxın)

Consul həm discovery, həm də KV store kimi işləyir. Laravel-də `sensiolabs/consul-php-sdk` ilə config çəkmək olur.

```bash
composer require sensiolabs/consul-php-sdk
```

```php
// app/Providers/ConsulConfigProvider.php
use SensioLabs\Consul\ServiceFactory;

class ConsulConfigProvider extends ServiceProvider
{
    public function register(): void
    {
        $kv = (new ServiceFactory(['base_uri' => env('CONSUL_URL')]))->get('kv');

        $load = function (string $path) use ($kv) {
            try {
                $resp = $kv->get("config/{$path}", ['recurse' => true]);
                $items = json_decode($resp->getBody(), true) ?: [];
                $flat = [];
                foreach ($items as $item) {
                    $key = substr($item['Key'], strlen("config/{$path}/"));
                    $flat[$key] = base64_decode($item['Value']);
                }
                return $flat;
            } catch (\Throwable) {
                return [];
            }
        };

        $shared = $load('application');
        $service = $load('order-service/' . app()->environment());
        $merged = array_merge($shared, $service);

        foreach ($merged as $key => $value) {
            config([str_replace('/', '.', $key) => $value]);
        }
    }
}
```

### 7) Envoyer/Forge deploy-də env rotation

```bash
# Forge Recipes və ya Envoyer hook
aws secretsmanager get-secret-value \
  --secret-id order-service/prod \
  --query SecretString --output text > /var/www/app/.env

cd /var/www/app
php artisan config:clear
php artisan config:cache
php-fpm reload
```

### 8) Per-env config fayllar

```php
// bootstrap/app.php
$app->booted(function ($app) {
    $envFile = base_path(".env.{$app->environment()}");
    if (file_exists($envFile)) {
        (\Dotenv\Dotenv::createImmutable(base_path(), ".env.{$app->environment()}"))->safeLoad();
    }
});
```

### 9) docker-compose.yml — lokal Laravel + Consul

```yaml
version: '3.9'
services:
  consul:
    image: hashicorp/consul:latest
    ports: ["8500:8500"]
    command: agent -dev -client=0.0.0.0

  vault:
    image: hashicorp/vault:latest
    ports: ["8200:8200"]
    environment:
      VAULT_DEV_ROOT_TOKEN_ID: root
    cap_add: [IPC_LOCK]

  order-service:
    image: mycompany/order-service-php:latest
    environment:
      CONSUL_URL: http://consul:8500
      VAULT_URL: http://vault:8200
      VAULT_TOKEN: root
    depends_on: [consul, vault]
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring Cloud Config | Laravel |
|---|---|---|
| Centralized config server | `@EnableConfigServer` built-in | Yoxdur — Consul/Vault/external |
| Git-backed config | Native dəstək | Manual (CI/CD pipeline ilə) |
| Vault backend | `spring.cloud.config.server.vault` | `csharpru/vault-php` package |
| JDBC backend | Native DB store | Custom model + provider |
| Hot reload | `@RefreshScope` + `/actuator/refresh` | Cache + event listener manual |
| Broadcast refresh | Spring Cloud Bus (RabbitMQ/Kafka) | Broadcasting channel manual |
| Encrypted values | `{cipher}...` prefix + `/encrypt` endpoint | Laravel Crypt facade manual |
| Profile support | `spring.profiles.active` | `APP_ENV` + `.env.{env}` |
| Label (Git branch) | `/{app}/{profile}/{label}` | Yoxdur — branch = deployment |
| K8s ConfigMap | Spring Cloud Kubernetes | Native K8s mount |
| K8s Secret | Spring Cloud Kubernetes | Native K8s mount |
| Runtime config override | `@ConfigurationProperties` + refresh | `config([...])` runtime call |
| Security layer | Spring Security basic auth + TLS | Nginx / K8s network policy |
| Config discovery | `spring.config.import=configserver:` | Service Provider `boot()` |

---

## Niyə belə fərqlər var?

**Spring mikroservislərə başlanğıcdan hazırdır.** Spring Cloud Netflix OSS miras gəlib — Eureka, Ribbon, Hystrix, Config Server. Bu alətlər "10–100 xidmət, hər biri Java proses" senariosu üçün yazılıb. Centralized config server elə bu senaryo üçündür: 50 xidmətin DB şifrəsini bir yerdən idarə etmək.

**Laravel tək monolit üçün optimallaşdırılıb.** Laravel-in `.env` + `config:cache` yanaşması bir tətbiq üçün sürətlidir və sadədir. Laravel ekosistemində "mikroservis" daha az yayılıb — tipik production bir-iki Laravel monolit + xarici xidmətlər (PgBouncer, Redis, Nginx). Buna görə central config server built-in deyil.

**Git-backed config niyə güclüdür?** Git versiya, review, rollback, audit verir. `git log config-repo/order-service-prod.yml` — kim nə vaxt dəyişib. Spring Cloud Config bunu darxili dəstəkləyir. Laravel-də eyni effekti almaq üçün CI/CD pipeline yazılır (GitHub Actions → deploy script → `.env` yenilə → `config:cache`).

**`@RefreshScope` nə edir?** Spring bean-i proxy-ləşdirir — hər method çağırışında current config-dən oxuyur. `POST /actuator/refresh` bean-i destroy edib yenidən yaradır. Laravel-də obyektlər singleton və ya factory-əsasədir — runtime refresh manual quraşdırmalıdır (event + cache invalidate).

**Kubernetes ConfigMap vs Config Server.** ConfigMap rahat, native, versiyalı deyil. Config Server Git-əsasəsdir, versiyalıdır. Mikroservis sayı artanda Config Server ilə tək Git repo idarə etmək ConfigMap-lərin 50 ayrı YAML-indən daha rahatdır. Amma tək xidmət və ya kiçik cluster üçün ConfigMap kifayətdir. Laravel adətən ikinci yolu seçir.

**Encryption yanaşması.** Spring `{cipher}...` prefix-i ilə selective field encryption verir — YAML oxunaqlı qalır, yalnız həssas dəyərlər şifrələnir. Laravel-də bütün secret-lər `.env`-dədir — file səviyyəsində SOPS, Vault, SealedSecrets istifadə olunur.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- `@EnableConfigServer` built-in server
- `spring.config.import=configserver:` client import
- `@RefreshScope` bean proxy
- `{cipher}...` selective field encryption
- Spring Cloud Bus broadcast refresh (RabbitMQ/Kafka)
- Git webhook → `/monitor` → avtomatik busrefresh
- Composite source — eyni zamanda Git + Vault + JDBC
- Git label (branch) seçimi runtime-da (`/{app}/{profile}/{label}`)
- `EnvironmentRepository` interface ilə custom backend yazmaq
- Spring Cloud Kubernetes ConfigMap/Secret adaptor
- `spring.cloud.config.fail-fast` — config server yox olsa app boot olmasın

**Yalnız Laravel-də (və ya Laravel-in üstün olduğu yerlər):**
- `php artisan config:cache` — OPcache-dostu ultra-sürətli config load
- `.env` faylı — sadə, git-ignored
- Per-request config override (`config(['x' => 'y'])`)
- `app()->environment()` ilə branch-siz per-env file
- Facade-level config (`Config::set()`, `Config::get()`)

**Hər ikisində var:**
- Kubernetes ConfigMap/Secret mount (native K8s feature)
- Vault integration (client SDK ilə)
- AWS Secrets Manager / Parameter Store
- HashiCorp Consul KV

---

## Best Practices

**Spring Cloud Config üçün:**
- `fail-fast: true` qoy — config server çatmırsa app boot olmasın
- `retry` enable et — network blip-də dayanmasın
- Git repo üçün `read-only` deploy key yarat, push icazəsi vermə
- `{cipher}` ilə secret-ləri şifrələ, düz mətn yazma
- Config Server-in özünü HA qur (ən azı 2 instance, behind LB)
- `/actuator/refresh` endpoint-i network-dən izolə et (yalnız internal)
- Config dəyişikliyi PR review ilə getsin — Git branch protection
- `spring.cloud.config.server.git.clone-on-start=true` qoy — server boot-da clone etsin

**Laravel config üçün:**
- Production-da həmişə `php artisan config:cache` işlət (10× sürət)
- `.env` faylını git-ignore et, `.env.example` saxla
- Secret-ləri Vault / AWS Secrets Manager / K8s Secret-də saxla
- `env()` funksiyasını yalnız `config/*.php` fayllarında çağır (config cache ilə bağlı)
- K8s-də envFrom + secretRef ilə mount et, hardcode etmə
- Deploy-da `config:clear` → `config:cache` → `php-fpm reload` ardıcıllığı
- Rotation üçün `aws secretsmanager rotate-secret` + Envoyer hook
- Horizon/Octane restart skriptini env dəyişikliyinə bağla

**Ümumi:**
- Never commit secrets. Git repo və konteyner image-də `.env` olmasın
- Per-env config ayır (dev/stage/prod), shared + override paterni
- Secret rotation policy yaz — 90 gündə bir dəyişdir
- Audit log saxla — kim hansı konfiqurasiyanı dəyişib
- Feature flag üçün ayrı sistem qur (LaunchDarkly, Unleash) — config server secret üçündür, flag üçün deyil

---

## Yekun

Spring Cloud Config Server enterprise Java mikroservis mühitində centralized config-ı həll edən mature bir həlldir. Git-backed store, `{cipher}` encryption, `@RefreshScope` hot reload, Spring Cloud Bus broadcast — hamısı built-in və inteqrasiyalı.

Laravel-də eyni konsept üçün **built-in** cavab yoxdur. Amma ekosistem alətlərini birləşdirərək oxşar nəticə almaq mümkündür: K8s ConfigMap/Secret mount `.env` üçün, Vault/AWS Secrets Manager secret üçün, Consul KV dinamik config üçün, custom service provider runtime override üçün.

Seçim: **Kiçik və orta Laravel tətbiqləri üçün K8s native mount + Vault kifayətdir.** 20+ xidmət və Java stack varsa, Spring Cloud Config Server ciddi fayda verir (Git history, selective encryption, hot reload). Polyglot mühitdə (Java + PHP + Go) Consul KV və ya Kubernetes native ConfigMap hər iki tərəfi əhatə edən ortaq həll yaradır.
