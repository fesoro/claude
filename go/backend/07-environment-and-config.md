# Environment and Config (Senior)

## İcmal

12-Factor App metodologiyasına görə konfiqurasiya mühitdən (environment) gəlməlidir — heç vaxt koda yazılmamalıdır. Go-da konfiqurasiyaların idarəsi üçün bir neçə yanaşma var: standart `os.Getenv`, `godotenv` (.env faylı parser), `viper` (multi-source config), `envconfig` (struct-based parsing). Doğru seçim layihənin mürəkkəbliyindən asılıdır.

## Niyə Vacibdir

- Fərqli mühitlər (dev, staging, production) üçün eyni binary fərqli davranmalıdır
- Sirlər (DB şifrəsi, API açarı) koda yazılsa git history-də daima qalır
- 12-Factor App principi — container mühitinin standartı
- `os.Getenv` birbaşa istifadəsi default dəyər dəstəkləmir, validation yoxdur — struct-based config daha güvənlidir
- Viper kimi multi-source config-da prioritet sırası (env > config fayl > default) idarə olunur

## Əsas Anlayışlar

**`os.Getenv(key)`** — boş string qaytarır əgər dəyər yoxdursa. Dəyərin mövcudluğunu yoxlamaq üçün `os.LookupEnv(key)` — `(string, bool)` qaytarır.

**`godotenv`** — Ruby-nin dotenv kitabxanasının Go portu. `.env` faylını oxuyub environment-ə set edir.

**`viper`** — çox mənbəli config: env + config fayl (YAML/JSON/TOML) + default dəyərlər. Prioritet sırası: env > config fayl > default.

**`envconfig`** — struct tag-lərinə görə environment-i avtomatik parse edir.

**12-Factor App III Faktoru:**

> Konfiqurasiya deployment-ə görə dəyişən hər şeydir. Koda qatılmamalı, environment variable-ə yazılmalıdır.

**Secret Management:**

- Development: `.env` faylı
- Staging/Production: AWS Secrets Manager, HashiCorp Vault, Kubernetes Secrets
- Docker: `--env-file` yaxud Compose `env_file`

## Praktik Baxış

**Tövsiyə olunan yanaşma (ən çox istifadə olunan):**

```
Kiçik servis  → os.LookupEnv + helper funksiyalar
Orta layihə   → envconfig (struct-based, validation ilə)
Böyük layihə  → viper (çox mühit, çox mənbə, hot reload)
```

**`.env` faylı qaydaları:**

- `.env` — `.gitignore`-a daxil et, heç vaxt commit etmə
- `.env.example` — şablon, şifrəsiz versiya, commit et
- `.env.test` — test mühiti üçün ayrı fayl

**Trade-off-lar:**

| Yanaşma | Üstünlük | Çatışmazlıq |
|---|---|---|
| `os.Getenv` | Sıfır dependency | Default yoxdur, type conversion əl ilə |
| `godotenv` | Sadə, tanış | Yalnız `.env` oxuyur |
| `envconfig` | Struct, validation, type-safe | Əlavə kitabxana |
| `viper` | Çox mənbə, hot reload | Mürəkkəb, ağır |

**Anti-pattern-lər:**

- Şifrəni koda yazmaq: `password := "my-secret"` — git history-də daima qalır
- `os.Getenv` ilə yoxlama etməmək: server port undefined gəlsə `:` olur, listen uğursuz
- `APP_SECRET = "change-me"` default dəyəri production-da qalmaq — həmişə məcburi validation yaz
- Config struct-unu global variable kimi tutmaq race condition riski yaradır — dependency injection istifadə et

## Nümunələr

### Nümunə 1: os.LookupEnv + Helper Funksiyalar

```go
package main

import (
    "fmt"
    "log"
    "os"
    "strconv"
    "time"
)

// String env — default dəyər ilə
func getEnv(key, fallback string) string {
    if v, ok := os.LookupEnv(key); ok && v != "" {
        return v
    }
    return fallback
}

// Int env
func getEnvInt(key string, fallback int) int {
    if v, ok := os.LookupEnv(key); ok {
        if i, err := strconv.Atoi(v); err == nil {
            return i
        }
    }
    return fallback
}

// Bool env
func getEnvBool(key string, fallback bool) bool {
    if v, ok := os.LookupEnv(key); ok {
        if b, err := strconv.ParseBool(v); err == nil {
            return b
        }
    }
    return fallback
}

// Duration env
func getEnvDuration(key string, fallback time.Duration) time.Duration {
    if v, ok := os.LookupEnv(key); ok {
        if d, err := time.ParseDuration(v); err == nil {
            return d
        }
    }
    return fallback
}

// Məcburi env — yoxdursa panic
func mustGetEnv(key string) string {
    v := os.Getenv(key)
    if v == "" {
        log.Fatalf("MÜTLƏQ mühit dəyişkəni mövcud deyil: %s", key)
    }
    return v
}

func main() {
    host := getEnv("SERVER_HOST", "localhost")
    port := getEnvInt("SERVER_PORT", 8080)
    debug := getEnvBool("APP_DEBUG", false)
    timeout := getEnvDuration("REQUEST_TIMEOUT", 30*time.Second)
    secret := mustGetEnv("APP_SECRET") // production-da mütləq olmalıdır

    fmt.Printf("Host: %s, Port: %d, Debug: %v\n", host, port, debug)
    fmt.Printf("Timeout: %s, Secret: %s\n", timeout, secret)
}
```

### Nümunə 2: Config Struct — Production Pattern

```go
package config

import (
    "fmt"
    "log"
    "os"
    "strconv"
    "time"
)

type Config struct {
    Server   Server
    Database Database
    Redis    Redis
    App      App
}

type Server struct {
    Host         string
    Port         int
    ReadTimeout  time.Duration
    WriteTimeout time.Duration
}

type Database struct {
    DSN          string
    MaxOpenConns int
    MaxIdleConns int
}

type Redis struct {
    Addr     string
    Password string
    DB       int
}

type App struct {
    Env       string // "development", "staging", "production"
    Debug     bool
    SecretKey string
    LogLevel  string
}

func Load() (*Config, error) {
    cfg := &Config{
        Server: Server{
            Host:         getEnv("SERVER_HOST", "0.0.0.0"),
            Port:         getEnvInt("SERVER_PORT", 8080),
            ReadTimeout:  getEnvDuration("READ_TIMEOUT", 5*time.Second),
            WriteTimeout: getEnvDuration("WRITE_TIMEOUT", 10*time.Second),
        },
        Database: Database{
            DSN:          buildDSN(),
            MaxOpenConns: getEnvInt("DB_MAX_OPEN_CONNS", 25),
            MaxIdleConns: getEnvInt("DB_MAX_IDLE_CONNS", 10),
        },
        Redis: Redis{
            Addr:     getEnv("REDIS_ADDR", "localhost:6379"),
            Password: getEnv("REDIS_PASSWORD", ""),
            DB:       getEnvInt("REDIS_DB", 0),
        },
        App: App{
            Env:       getEnv("APP_ENV", "development"),
            Debug:     getEnvBool("APP_DEBUG", false),
            SecretKey: mustGetEnv("APP_SECRET_KEY"),
            LogLevel:  getEnv("LOG_LEVEL", "info"),
        },
    }

    if err := cfg.validate(); err != nil {
        return nil, err
    }
    return cfg, nil
}

func buildDSN() string {
    host := getEnv("DB_HOST", "localhost")
    port := getEnvInt("DB_PORT", 5432)
    user := getEnv("DB_USER", "postgres")
    pass := os.Getenv("DB_PASSWORD") // şifrə — default ola bilməz
    name := mustGetEnv("DB_NAME")

    return fmt.Sprintf(
        "host=%s port=%d user=%s password=%s dbname=%s sslmode=disable",
        host, port, user, pass, name,
    )
}

func (c *Config) validate() error {
    if c.App.SecretKey == "change-me" || c.App.SecretKey == "" {
        return fmt.Errorf("APP_SECRET_KEY təyin edilməyib")
    }
    if c.App.Env == "production" && c.App.Debug {
        log.Println("XƏBƏRDARLIQ: production-da debug aktifdir!")
    }
    return nil
}

func (c *Config) IsDevelopment() bool { return c.App.Env == "development" }
func (c *Config) IsProduction() bool  { return c.App.Env == "production" }

// Helper funksiyalar
func getEnv(key, fallback string) string {
    if v, ok := os.LookupEnv(key); ok && v != "" {
        return v
    }
    return fallback
}

func getEnvInt(key string, fallback int) int {
    if v, ok := os.LookupEnv(key); ok {
        if i, err := strconv.Atoi(v); err == nil {
            return i
        }
    }
    return fallback
}

func getEnvBool(key string, fallback bool) bool {
    if v, ok := os.LookupEnv(key); ok {
        if b, err := strconv.ParseBool(v); err == nil {
            return b
        }
    }
    return fallback
}

func getEnvDuration(key string, fallback time.Duration) time.Duration {
    if v, ok := os.LookupEnv(key); ok {
        if d, err := time.ParseDuration(v); err == nil {
            return d
        }
    }
    return fallback
}

func mustGetEnv(key string) string {
    v := os.Getenv(key)
    if v == "" {
        log.Fatalf("Mütləq env dəyişkəni yoxdur: %s", key)
    }
    return v
}
```

### Nümunə 3: godotenv ilə .env Faylı

```go
package main

import (
    "fmt"
    "log"
    "os"

    "github.com/joho/godotenv"
)

func main() {
    // .env faylını yüklə — production-da ümumiyyətlə istifadə etmə
    // Yalnız development üçün
    if err := godotenv.Load(); err != nil {
        log.Println(".env faylı tapılmadı — sistem environment istifadə olunur")
    }

    // Xüsusi fayl
    // godotenv.Load(".env.local", ".env")

    // Overload — mövcud env-i yaz üstünə
    // godotenv.Overload(".env")

    dbURL := os.Getenv("DATABASE_URL")
    port := os.Getenv("PORT")
    fmt.Printf("DB: %s, Port: %s\n", dbURL, port)
}

// .env fayl nümunəsi:
/*
# Server
PORT=8080
HOST=localhost

# Database
DATABASE_URL=postgres://user:pass@localhost:5432/mydb?sslmode=disable
DB_MAX_CONNS=25

# App
APP_ENV=development
APP_SECRET_KEY=dev-secret-change-in-prod
APP_DEBUG=true

# Redis
REDIS_URL=redis://localhost:6379
*/
```

### Nümunə 4: Viper — Multi-Source Config

```go
package config

import (
    "strings"

    "github.com/spf13/viper"
)

func LoadWithViper() (*Config, error) {
    v := viper.New()

    // Default dəyərlər
    v.SetDefault("server.port", 8080)
    v.SetDefault("server.host", "0.0.0.0")
    v.SetDefault("database.max_open_conns", 25)
    v.SetDefault("app.env", "development")
    v.SetDefault("app.log_level", "info")

    // Config fayl — YAML, JSON, TOML
    v.SetConfigName("config")     // config.yaml, config.json
    v.SetConfigType("yaml")
    v.AddConfigPath(".")
    v.AddConfigPath("./config")
    v.AddConfigPath("/etc/myapp")

    // Fayl oxu — tapılmasa error deyil
    v.ReadInConfig() // xəta yox sayılır

    // Environment variable-lər — fayl üzərindədir
    v.AutomaticEnv()
    v.SetEnvKeyReplacer(strings.NewReplacer(".", "_"))
    // SERVER_PORT → server.port kimi işləyir

    // Əl ilə bind — spesifik env-lər
    v.BindEnv("app.secret_key", "APP_SECRET_KEY")
    v.BindEnv("database.dsn", "DATABASE_URL")

    cfg := &Config{}
    if err := v.Unmarshal(cfg); err != nil {
        return nil, err
    }
    return cfg, nil
}

// config.yaml nümunəsi:
/*
server:
  host: "0.0.0.0"
  port: 8080
  read_timeout: "5s"
  write_timeout: "10s"

database:
  host: "localhost"
  port: 5432
  max_open_conns: 25

app:
  env: "development"
  debug: true
  log_level: "debug"
*/
```

### Nümunə 5: Dependency Injection — Config-ı Ötürmək

```go
package main

import (
    "fmt"
    "log"
    "net/http"
)

// Server — config dependency injection ilə
type Server struct {
    cfg    *Config
    router *http.ServeMux
}

func NewServer(cfg *Config) *Server {
    s := &Server{
        cfg:    cfg,
        router: http.NewServeMux(),
    }
    s.setupRoutes()
    return s
}

func (s *Server) setupRoutes() {
    s.router.HandleFunc("GET /", func(w http.ResponseWriter, r *http.Request) {
        fmt.Fprintf(w, "Mühit: %s", s.cfg.App.Env)
    })
}

func (s *Server) Run() error {
    addr := fmt.Sprintf("%s:%d", s.cfg.Server.Host, s.cfg.Server.Port)
    log.Printf("Server başladı: %s (mühit: %s)", addr, s.cfg.App.Env)
    return http.ListenAndServe(addr, s.router)
}

func main() {
    cfg, err := Load() // config paketindən
    if err != nil {
        log.Fatal("Config xətası:", err)
    }

    srv := NewServer(cfg)
    if err := srv.Run(); err != nil {
        log.Fatal(err)
    }
}
```

### Nümunə 6: .env.example — Şablon Fayl

```bash
# .env.example — bu fayl git-ə commit olunur!
# Həqiqi dəyərləri .env-ə yaz (git-ignored)

# Server
SERVER_HOST=0.0.0.0
SERVER_PORT=8080
READ_TIMEOUT=5s
WRITE_TIMEOUT=10s

# Database — MÜTLƏQ doldurulmalıdır
DB_HOST=localhost
DB_PORT=5432
DB_USER=postgres
DB_PASSWORD=DEYISTIR
DB_NAME=myapp

# Redis
REDIS_ADDR=localhost:6379
REDIS_PASSWORD=

# App — MÜTLƏQ doldurulmalıdır
APP_ENV=development
APP_SECRET_KEY=UZUN_RANDOM_STRING_YAZIN
APP_DEBUG=false
LOG_LEVEL=info
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Config Package**

`internal/config` paketi yaz:
- `Config` struct — Server, DB, Redis, JWT bölmələri
- `Load() (*Config, error)` — environment-dən oxu
- Validation — məcburi sahələr boş olmamalı
- `.env.example` faylı yarat
- `IsDevelopment()`, `IsProduction()` metodları

**Tapşırıq 2 — Fərqli Mühitlər**

Eyni servisi 3 mühitdə işlət:
```
APP_ENV=development  → debug log, .env faylı
APP_ENV=staging      → JSON log, həqiqi DB
APP_ENV=production   → minimal log, secrets manager
```

**Tapşırıq 3 — Secret Validation**

Server başlayarkən validation yaz:
- `APP_SECRET_KEY` ən azı 32 karakter
- `DB_PASSWORD` boş olmamalı (production-da)
- JWT secret `"secret"` yaxud `"change-me"` olmamalı
- Validation uğursuz olsa server başlamasın

**Tapşırıq 4 — Hot Reload**

Viper ilə config faylının dəyişməsini izlə:
```go
viper.WatchConfig()
viper.OnConfigChange(func(e fsnotify.Event) {
    log.Println("Config dəyişdi:", e.Name)
})
```

## PHP ilə Müqayisə

PHP/Laravel-də `.env` + `config/` qovluğu + `env()` helper funksiyası istifadə olunurdu. Go-da eyni anlayış var, amma magic daha azdır — öz config struct-unu özün qurursan.

## Əlaqəli Mövzular

- [37-database](37-database.md) — DB bağlantı string-inin konfiqurasiyası
- [54-project-structure](54-project-structure.md) — `internal/config` qovluğu
- [65-jwt-and-auth](65-jwt-and-auth.md) — JWT secret konfiqurasiyası
- [70-docker-and-deploy](70-docker-and-deploy.md) — Container mühitində env idarəsi
- [71-monitoring-and-observability](71-monitoring-and-observability.md) — Log level konfiqurasiyası
