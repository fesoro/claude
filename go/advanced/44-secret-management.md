# Secret Management (Senior)

## İcmal

**Secret management** — API key, database password, token kimi həssas məlumatların təhlükəsiz saxlanması, dağıtılması və rotasiyasıdır. Go-da env var-lardan başlayaraq **HashiCorp Vault**, **AWS Secrets Manager**, **GCP Secret Manager** kimi tam platformalara qədər dəyişir. Hardcoded secret — ən çox yayılmış security açığıdır.

## Niyə Vacibdir

- Hardcoded secret git history-də qalır — `git filter-branch` belə tam silmir
- 12-factor app: config env-dən gəlir, kod-dan deyil
- Secret rotation: hər 90 gündə password dəyişmək lazım gəlir — kod dəyişmədən
- Audit trail: kim nə vaxt hansı secret-i oxudu — compliance tələbi
- Zero-trust: service-lər bir-birindən secret almır, vault-dan alır

## Əsas Anlayışlar

- **Secret zero problem** — vault-a girmək üçün lazım olan ilk credential haradan gəlir?
- **Dynamic secret** — Vault hər request üçün yeni DB credential yaradır; expire olur
- **Static secret** — uzunömürlü, manual rotate; risklidir
- **Secret lease** — Vault secret-in ömrü; `lease_duration` bitdikdə yenilənməlidir
- **AppRole auth** — service identity; `role_id` + `secret_id` ilə vault-a giriş
- **IRSA / Workload Identity** — K8s/AWS/GCP-də pod-un cloud secret-lərə IAM vasitəsilə girişi
- **Secret scanning** — `git-secrets`, `gitleaks` — repo-da hardcoded secret aşkarlamaq
- **Sealed secret** — Kubernetes `SealedSecret` — şifrəli secret git-ə commit edilə bilər

## Praktik Baxış

**Ne vaxt nə istifadə et:**

| Yanaşma | İstifadə |
|---------|---------|
| Env var | Sadə app, Heroku/Railway, 12-factor |
| `.env` + dotenv | Local development |
| K8s Secret | Kubernetes deployment |
| AWS Secrets Manager | AWS stack |
| HashiCorp Vault | Multi-cloud, enterprise, dynamic secrets |
| GCP Secret Manager | GCP stack |

**Trade-off-lar:**
- Vault: güclü amma operational overhead yüksəkdir — operator lazımdır
- K8s Secret: base64 encode — şifrələnmir; etcd encryption at rest lazımdır
- AWS Secrets Manager: managed, amma vendor lock-in
- Env var: sadədir amma process list-də görünə bilər

**Common mistakes:**
- Secret-i log-a yazmaq (`log.Printf("connecting to %s", dbURL)`)
- `.env` faylını git-ə push etmək
- Secret-i error message-ə daxil etmək
- K8s Secret-in şifrəsiz olduğunu unutmaq

## Nümunələr

### Nümunə 1: Env var + dotenv (local development)

```go
package config

import (
    "fmt"
    "os"

    "github.com/joho/godotenv"
)

type Config struct {
    DBPassword string
    JWTSecret  string
    APIKey     string
}

func Load() (*Config, error) {
    // Yalnız local dev-də .env yüklə
    if os.Getenv("APP_ENV") == "" || os.Getenv("APP_ENV") == "development" {
        _ = godotenv.Load() // .env yoxdursa ignore et
    }

    cfg := &Config{
        DBPassword: os.Getenv("DB_PASSWORD"),
        JWTSecret:  os.Getenv("JWT_SECRET"),
        APIKey:     os.Getenv("API_KEY"),
    }

    if cfg.DBPassword == "" {
        return nil, fmt.Errorf("DB_PASSWORD tələb olunur")
    }
    if cfg.JWTSecret == "" {
        return nil, fmt.Errorf("JWT_SECRET tələb olunur")
    }

    return cfg, nil
}

// .env faylı (git-ə əlavə etmə — .gitignore-a yaz)
// DB_PASSWORD=supersecret
// JWT_SECRET=my-jwt-key-32-chars-minimum
// API_KEY=sk-prod-xxxx
```

### Nümunə 2: AWS Secrets Manager

```go
package secrets

import (
    "context"
    "encoding/json"
    "fmt"
    "sync"
    "time"

    "github.com/aws/aws-sdk-go-v2/config"
    "github.com/aws/aws-sdk-go-v2/service/secretsmanager"
)

type AWSSecretManager struct {
    client *secretsmanager.Client
    cache  sync.Map
    ttl    time.Duration
}

type cachedSecret struct {
    value     string
    expiresAt time.Time
}

func NewAWSSecretManager(ctx context.Context) (*AWSSecretManager, error) {
    cfg, err := config.LoadDefaultConfig(ctx)
    if err != nil {
        return nil, err
    }
    return &AWSSecretManager{
        client: secretsmanager.NewFromConfig(cfg),
        ttl:    5 * time.Minute,
    }, nil
}

func (m *AWSSecretManager) Get(ctx context.Context, secretName string) (string, error) {
    // Cache yoxla
    if cached, ok := m.cache.Load(secretName); ok {
        c := cached.(cachedSecret)
        if time.Now().Before(c.expiresAt) {
            return c.value, nil
        }
    }

    // AWS-dən al
    out, err := m.client.GetSecretValue(ctx, &secretsmanager.GetSecretValueInput{
        SecretId: &secretName,
    })
    if err != nil {
        return "", fmt.Errorf("secret alına bilmədi %s: %w", secretName, err)
    }

    value := *out.SecretString
    m.cache.Store(secretName, cachedSecret{
        value:     value,
        expiresAt: time.Now().Add(m.ttl),
    })

    return value, nil
}

// JSON secret (bir neçə dəyər)
func (m *AWSSecretManager) GetJSON(ctx context.Context, secretName string, dst any) error {
    raw, err := m.Get(ctx, secretName)
    if err != nil {
        return err
    }
    return json.Unmarshal([]byte(raw), dst)
}

// İstifadə:
// var dbCreds struct {
//     Host     string `json:"host"`
//     Password string `json:"password"`
// }
// sm.GetJSON(ctx, "prod/myapp/db", &dbCreds)
```

### Nümunə 3: HashiCorp Vault — dynamic DB credential

```go
package vault

import (
    "context"
    "fmt"
    "time"

    vault "github.com/hashicorp/vault/api"
    auth "github.com/hashicorp/vault/api/auth/approle"
)

type VaultClient struct {
    client *vault.Client
}

func NewVaultClient(addr, roleID, secretID string) (*VaultClient, error) {
    cfg := vault.DefaultConfig()
    cfg.Address = addr

    client, err := vault.NewClient(cfg)
    if err != nil {
        return nil, err
    }

    // AppRole auth
    appRoleAuth, err := auth.NewAppRoleAuth(roleID, &auth.SecretID{FromString: secretID})
    if err != nil {
        return nil, err
    }

    authInfo, err := client.Auth().Login(context.Background(), appRoleAuth)
    if err != nil {
        return nil, err
    }

    // Token auto-renew
    go client.NewLifetimeWatcher(&vault.LifetimeWatcherInput{Secret: authInfo})

    return &VaultClient{client: client}, nil
}

// Dynamic DB credential — hər call yeni credential yaradır
func (v *VaultClient) GetDBCredentials(ctx context.Context, role string) (user, pass string, ttl time.Duration, err error) {
    secret, err := v.client.Logical().ReadWithContext(ctx,
        fmt.Sprintf("database/creds/%s", role))
    if err != nil {
        return "", "", 0, err
    }

    return secret.Data["username"].(string),
        secret.Data["password"].(string),
        time.Duration(secret.LeaseDuration) * time.Second,
        nil
}

// Static secret
func (v *VaultClient) GetSecret(ctx context.Context, path string) (map[string]any, error) {
    secret, err := v.client.KVv2("secret").Get(ctx, path)
    if err != nil {
        return nil, err
    }
    return secret.Data, nil
}
```

### Nümunə 4: Kubernetes Secret + sidecar pattern

```yaml
# kubernetes secret (base64 encode — şifrəli deyil!)
apiVersion: v1
kind: Secret
metadata:
  name: app-secrets
type: Opaque
data:
  db-password: c3VwZXJzZWNyZXQ=   # base64("supersecret")
  jwt-secret: bXktand0LWtleQ==

---
# Deployment — secret-i env kimi inject et
apiVersion: apps/v1
kind: Deployment
spec:
  template:
    spec:
      containers:
        - name: app
          image: myapp:latest
          env:
            - name: DB_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: app-secrets
                  key: db-password
            - name: JWT_SECRET
              valueFrom:
                secretKeyRef:
                  name: app-secrets
                  key: jwt-secret
```

```go
// Vault Agent Sidecar — Go app Vault-u bilmir, sadəcə faylı oxuyur
// /vault/secrets/db-password → sidecar tərəfindən yazılır, auto-rotate olunur
func loadSecretFromFile(path string) (string, error) {
    data, err := os.ReadFile(path)
    if err != nil {
        return "", err
    }
    return strings.TrimSpace(string(data)), nil
}
```

### Nümunə 5: Secret scanning — gitleaks

```bash
# Quraşdırma
go install github.com/zricethezav/gitleaks/v8@latest

# Mövcud repo-da scan
gitleaks detect --source .

# Pre-commit hook
gitleaks protect --staged

# CI/CD (GitHub Actions)
# - uses: gitleaks/gitleaks-action@v2

# Custom config (.gitleaks.toml)
# [[rules]]
# id = "my-api-key"
# regex = '''sk-[a-zA-Z0-9]{48}'''
# description = "My app API key"
```

```go
// Secret-i log-a yazmaqdan qorun
type Config struct {
    DBPassword string
    JWTSecret  string
}

// String() metodunda gizlət
func (c Config) String() string {
    return fmt.Sprintf("Config{DBPassword:[REDACTED], JWTSecret:[REDACTED]}")
}

// slog ilə struct log-layanda String() çağrılır
// log.Info("config loaded", "config", cfg) → [REDACTED] görünür
```

## Praktik Tapşırıqlar

1. **Env loading:** `godotenv` ilə `.env` yüklə; `.gitignore`-a əlavə et; missing required var-lar üçün validation yaz
2. **AWS Secrets Manager:** Local mock (localstack) ilə AWS SM client yaz; TTL cache əlavə et
3. **Secret logging guard:** `Config` struct üçün `String()` metodu yaz; secret-ləri `[REDACTED]` ilə göstər
4. **gitleaks:** Repo-ya `gitleaks detect` işlət; pre-commit hook qur

## PHP ilə Müqayisə

```
PHP/Laravel                    →  Go
────────────────────────────────────────
.env + vlucas/phpdotenv        →  godotenv
config('app.key')              →  os.Getenv("APP_KEY")
Laravel Vault package          →  vault/api SDK
K8s Secret → env               →  os.Getenv (eyni)
```

Laravel `.env` faylı artıq standartdır. Go-da eyni pattern işləyir — əlavə olaraq Vault/AWS SDK inteqrasiyası daha geniş yayılmışdır (microservice mühiti).

## Əlaqəli Mövzular

- [../backend/07-environment-and-config](../backend/07-environment-and-config.md) — env-based konfiqurasiya
- [07-security](07-security.md) — TLS, auth, input validation
- [../backend/36-tls-https](../backend/36-tls-https.md) — mTLS ilə service identity
- [42-feature-flags](42-feature-flags.md) — env-based flag konfiqurasiyası
- [../backend/37-health-check](../backend/37-health-check.md) — secret-lərin yüklənib-yüklənmədiyini yoxlamaq
