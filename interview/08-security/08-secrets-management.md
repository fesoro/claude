# Secrets Management (Senior ⭐⭐⭐)

## İcmal

Secrets Management — API key-lər, database parollar, JWT secret-lər, SSL sertifikatlar, encryption key-lər kimi həssas məlumatların təhlükəsiz saxlanması, paylanması, rotasiyası və audit edilməsidir. Interview-da bu mövzu production security maturity-nizi ölçür. `.env` faylını Git-ə commit etmək ən çox görülən security səhvlərindəndir — lakin production-grade secret management bundan çox daha geniş sahəni əhatə edir.

## Niyə Vacibdir

GitHub-da axtardıqda milyonlarla API key tapılır. TruffleHog-un araşdırmasına görə public repo-ların 2%-ündə aktiv credential var. Uber breach (2022) — contractor-ın AWS secret-ini koda yazması ilə başladı: 57 milyon user datası. Secret leak production database-ə tam girişdən tutmuş maliyyə zərərinə, GDPR cəriməsinə, reputasiya itkisinə qədər gedə bilər.

## Əsas Anlayışlar

- **Secret types — kateqoriyalar**: Database password, API key (Stripe, Twilio), JWT signing key, encryption key, TLS certificate + private key, SSH key, service account credential, OAuth client secret, webhook secret, S3 presigned URL key
- **`.env` file**: Development üçün uyğun. Production-da qeyri-kafi: rotation çətindir, audit trail yoxdur, environment-lər arası fərqli `.env` idarəsi mürəkkəbdir, Git commit risk var
- **Git commit accident**: Secret-i Git-ə push etsəniz — history-dən silmək yetərli deyil (fork-lar, clone-lar ola bilər). Dərhal rotate etmək lazımdır. `BFG Repo Cleaner` / `git filter-repo` history-dən silmək üçün — sonradan
- **HashiCorp Vault**: Enterprise secret management. Dynamic secrets, leasing, audit logging, policy-based access. Kubernetes, AWS, Azure integration. Open source + enterprise
- **AWS Secrets Manager**: Cloud-native secret management — IAM ilə inteqrasiya. Rotation avtomatik (Lambda ilə). Versioning. `secretsmanager:GetSecretValue` IAM permission
- **AWS Parameter Store**: Secrets Manager-dan sadə alternativ. `/prod/db/password` kimi hierarchical. SecureString tipi (KMS-lə encrypt). Pulsuz tier var
- **Azure Key Vault**: Azure-da ekvivalent. Managed HSM tier
- **Kubernetes Secrets**: Base64-encoded — şifrəli deyil! `base64 -d` ilə decode olunur. etcd encryption aktiv edilməlidir. Sealed Secrets (Bitnami) ya da External Secrets Operator daha güvənli
- **Dynamic Secrets**: Vault hər request üçün müvəqqəti DB credential yaradır — TTL ilə. Leak olsa 1 saatlıq girişi var. Müntəzəm rotation-dan üstün — credential never reused
- **Secret Rotation**: Müntəzəm key dəyişikliyi — manual vs automated. AWS Secrets Manager rotation Lambda ilə avtomatik. Vault lease renewal. Zero-downtime rotation dizaynı: köhnə credential bir müddət aktiv qalır, trafik köçür, köhnəsi deaktiv
- **Least Privilege**: Hər service yalnız öz secret-lərinə daxil ola bilər. IAM role per service. Vault policy per service
- **Secret Injection at Runtime**: Container start olduqda secret inject edilir — kod içinə yazılmır. AWS ECS task definition `secrets:` field. Kubernetes Secrets → envFrom. Vault Agent Sidecar
- **Envelope Encryption**: Data key (DEK) şifrələnir → KMS master key (KEK) ilə. DEK data ilə saxlanır (encrypted). KEK KMS-dədir. Key rotation: KEK rotate edilsə data yenidən şifrələmə lazım deyil — yalnız DEK-in yenidən encrypt edilməsi
- **SOPS (Secrets OPerationS)**: Mozilla-nın tool-u. Git-də şifrəli secret saxlamaq — `GitOps` üçün. KMS, PGP, age ilə şifrələmə. `sops -d .env.production.enc > .env`
- **Sealed Secrets**: Bitnami-nin Kubernetes tool-u. Public key ilə şifrələnmiş YAML Git-ə commit olunur. Yalnız cluster-dakı private key ilə deşifrələnir
- **Secret Detection — CI/CD-də**: TruffleHog, git-secrets, gitleaks, Trivy — push-dan əvvəl secret scan. GitHub Advanced Security secret scanning. `pre-commit` hook
- **SBOM (Software Bill of Materials)**: Dependency-lərdə hardcoded secret risk. SBOM ilə transparency
- **Immutable Infrastructure**: Server-lər artifact-dən yaranır, runtime-da secret inject edilir — config drift yoxdur

## Praktik Baxış

**Interview-da yanaşma:**
"`.env` faylını Git-ə commit etmiyoruz" yetərli deyil. Production-da secret-ləri nə ilə idarə edirsiniz? Rotation strategiyası nədir? Dynamic secrets nədir? Kubernetes Secrets-in Base64 olduğunu bilmək. Bu sualların cavablarını hazırlayın.

**Follow-up suallar (top companies-da soruşulur):**
- "Secret Git-ə push edildi — nə edərsiniz?" → Dərhal rotate et (credential-ı invalid et). History-dən sil (BFG/git filter-repo). Force push (team koordinasiyası ilə). GitHub Advanced Security alert-ləri yoxla. Post-mortem: niyə baş verdi, pre-commit hook yoxdurmu?
- "Dynamic secrets nədir? Necə işləyir?" → Vault hər service-ə unique, time-limited credential verir. DB üçün: Vault DB plugin → `CREATE ROLE 'v-service-xyz' ... VALID UNTIL '...'`. TTL bitdikdə credential avtomatik silir. Attacker leaked credential alsa 1 saatdan artıq faydalı deyil
- "Kubernetes Secret-i Base64-dür — bu şifrəli deməkdirmi?" → Xeyr. `base64 -d` ilə decode olunur — açıq mətndir. Həqiqi qoruma üçün: etcd encryption, Sealed Secrets, External Secrets Operator (AWS/Vault integration)
- "Zero-downtime rotation strategiyası?" → Köhnə credential əlavə qalır (grace period). Yeni credential deploy edilir. Trafik yeni credential-ə keçir. Köhnəsi deaktiv edilir. AWS Secrets Manager Lambda rotation hook bunu avtomatik edir
- "Service-ə minimum secret access necə qurulur?" → AWS: IAM Role per ECS task/Lambda. Vault: policy per service. `arn:aws:secretsmanager:*:*:secret:prod/myservice/*` — yalnız öz namespace-i

**Ümumi səhvlər (candidate-ların etdiyi):**
- `.env.production`-ı Git-ə commit etmək
- Kubernetes Secret-i şifrəli hesab etmək
- Uzun müddət secret rotation etməmək — 1+ il köhnə key
- Secret-i log-da göstərmək: `Log::info("Connecting to DB: {$password}")`
- Bütün service-lərə eyni API key vermək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Dynamic secrets-i izah etmək, envelope encryption-ı bilmək, GitOps üçün SOPS/Sealed Secrets-i izah edə bilmək, zero-downtime rotation dizaynını göstərə bilmək.

## Nümunələr

### Tipik Interview Sualı

"Production-da database parolunu, API key-lərini necə idarə edirsiniz?"

### Güclü Cavab

"Production-da `.env` faylı yetərli deyil — rotation çətindir, audit trail yoxdur. AWS-də Secrets Manager istifadə edirəm. ECS task-ın IAM Role-u yalnız öz secret-lərinə daxil ola bilir — başqa service-in secret-ini görə bilmir. Rotation AWS Lambda ilə avtomatik edilir.

Daha güclü yanaşma: Vault dynamic secrets — hər service deploy olduqda ayrı, time-limited DB user yaradılır. TTL bitdikdə credential avtomatik silinir. Leak olsa 1 saatlıq girişi var.

Kubernetes-də: etcd encryption aktiv, Sealed Secrets ilə public key ilə şifrələnmiş YAML Git-ə commit olunur — yalnız cluster-dakı private key ilə açılır.

CI/CD-də: TruffleHog pre-push hook, GitHub Advanced Security secret scanning."

### Kod/Konfiqurasiya Nümunəsi

```php
// ============================================================
// AWS Secrets Manager — Laravel inteqrasiyası
// ============================================================

use Aws\SecretsManager\SecretsManagerClient;
use Aws\Exception\AwsException;

class SecretsManagerLoader
{
    private array $cache = [];

    public function load(string $secretId): array
    {
        if (isset($this->cache[$secretId])) {
            return $this->cache[$secretId];
        }

        $client = new SecretsManagerClient([
            'version' => 'latest',
            'region'  => env('AWS_REGION', 'us-east-1'),
            // EC2/ECS-də IAM Role avtomatik — heç bir credential kod-da yoxdur
        ]);

        try {
            $result  = $client->getSecretValue(['SecretId' => $secretId]);
            $secrets = json_decode($result['SecretString'], true, 512, JSON_THROW_ON_ERROR);
            $this->cache[$secretId] = $secrets;
            return $secrets;
        } catch (AwsException $e) {
            Log::critical('Failed to load secret', [
                'secret_id' => $secretId,
                'error'     => $e->getAwsErrorCode(),
            ]);
            throw new \RuntimeException('Cannot load required secret: ' . $secretId);
        }
    }
}

// AppServiceProvider-da boot zamanı yüklə
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (app()->environment('production')) {
            $loader  = app(SecretsManagerLoader::class);
            $secrets = $loader->load('prod/myapp/database');

            config([
                'database.connections.mysql.host'     => $secrets['host'],
                'database.connections.mysql.database' => $secrets['dbname'],
                'database.connections.mysql.username' => $secrets['username'],
                'database.connections.mysql.password' => $secrets['password'],
            ]);

            // Stripe API key
            $stripeSecrets = $loader->load('prod/myapp/stripe');
            config(['services.stripe.secret' => $stripeSecrets['secret_key']]);
        }
    }
}
```

```yaml
# ============================================================
# Kubernetes — Sealed Secrets (Bitnami)
# ============================================================

# ❌ Regular Secret — Base64, NOT encrypted
apiVersion: v1
kind: Secret
metadata:
  name: app-secrets
data:
  DB_PASSWORD: c3VwZXJzZWNyZXQ=   # echo -n "supersecret" | base64
  # kubectl get secret app-secrets -o jsonpath='{.data.DB_PASSWORD}' | base64 -d
  # → supersecret  ← PLAIN TEXT!

---
# ✅ Sealed Secret — RSA encrypt, Git-ə commit etmək safe
# Yaratmaq üçün: kubeseal --format yaml < secret.yaml > sealed-secret.yaml
apiVersion: bitnami.com/v1alpha1
kind: SealedSecret
metadata:
  name: app-secrets
  namespace: production
spec:
  encryptedData:
    DB_PASSWORD: AgB7kXQ2...long_encrypted_string...
    # Yalnız cluster-dakı Sealed Secrets controller açır
    # Git-ə commit etmək tamamen safe
  template:
    metadata:
      name: app-secrets
```

```yaml
# ============================================================
# HashiCorp Vault — Dynamic DB Credentials
# ============================================================

# Vault konfiqurasiyası (HCL)
# 1. DB plugin aktiv et
vault secrets enable database

# 2. PostgreSQL connection konfigurasiya
vault write database/config/production-db \
    plugin_name=postgresql-database-plugin \
    allowed_roles="order-service,payment-service" \
    connection_url="postgresql://{{username}}:{{password}}@db:5432/prod?sslmode=require" \
    username="vault_admin" \
    password="vault_admin_pass"

# 3. Role müəyyən et
vault write database/roles/order-service \
    db_name=production-db \
    creation_statements="
        CREATE ROLE '{{name}}' WITH LOGIN PASSWORD '{{password}}' VALID UNTIL '{{expiration}}';
        GRANT SELECT, INSERT, UPDATE ON orders, products TO '{{name}}';
    " \
    revocation_statements="DROP ROLE IF EXISTS '{{name}}';" \
    default_ttl="1h" \
    max_ttl="24h"

# 4. Service credential alır (hər seferinde unique)
# vault read database/creds/order-service
# Key                Value
# lease_id           database/creds/order-service/AbCdEf123
# lease_duration     1h
# username           v-order-service-AbCdEf123
# password           A1-randomly-generated-password
```

```php
// ============================================================
// Zero-Downtime Secret Rotation
// ============================================================

class SecretRotationService
{
    /**
     * Database password zero-downtime rotation
     * Strategy: dual-write period (old + new both valid)
     */
    public function rotateDbPassword(string $secretId): void
    {
        $sm = app(SecretsManagerClient::class);

        // 1. Yeni parol generate et
        $newPassword = $this->generateSecurePassword();

        // 2. DB-yə yeni parol set et (köhnə hələ aktiv)
        DB::statement("ALTER USER 'app_user'@'%' IDENTIFIED BY ?", [$newPassword]);

        // 3. Secrets Manager-da yenilə
        $sm->putSecretValue([
            'SecretId'     => $secretId,
            'SecretString' => json_encode([
                'username' => 'app_user',
                'password' => $newPassword,
                'rotated_at' => now()->toIso8601String(),
            ]),
        ]);

        // 4. Aplikasiyaya bildiriş — config reload
        Cache::forget('db_credentials_loaded');

        // 5. Health check — yeni credential-la qoşulmaq mümkündürmü?
        try {
            DB::reconnect();
            DB::select('SELECT 1');
        } catch (\Exception $e) {
            // Rollback
            $this->rollbackRotation($secretId, $e);
            throw $e;
        }

        Log::channel('audit')->info('Secret rotated successfully', [
            'secret_id'   => $secretId,
            'rotated_at'  => now()->toIso8601String(),
        ]);
    }

    private function generateSecurePassword(): string
    {
        // Cryptographically secure random password
        $chars    = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        for ($i = 0; $i < 32; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}
```

```bash
# ============================================================
# SOPS — Git-də şifrəli secrets
# ============================================================

# Install: brew install sops

# AWS KMS key ilə şifrələmə
export SOPS_KMS_ARN="arn:aws:kms:us-east-1:123456789:key/abc-xyz"

# .env.production faylını şifrələ
sops --kms $SOPS_KMS_ARN -e .env.production > .env.production.enc

# Git-ə commit et (şifrəli hal)
git add .env.production.enc  # Safe!

# CI/CD-da deşifrələ (IAM role ilə)
sops -d .env.production.enc > .env.production

# ============================================================
# TruffleHog — Pre-push secret scan
# ============================================================

# .git/hooks/pre-push
#!/bin/sh
trufflehog git file://. --only-verified --fail
if [ $? -ne 0 ]; then
    echo "❌ Possible secrets detected! Aborting push."
    exit 1
fi

# GitHub Actions
# .github/workflows/security.yml
name: Security Scan
on: [push, pull_request]
jobs:
  secret-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0  # Full history scan
      - name: TruffleHog Scan
        uses: trufflesecurity/trufflehog@main
        with:
          base: ${{ github.event.repository.default_branch }}
          head: HEAD
          extra_args: --only-verified
```

## Praktik Tapşırıqlar

1. Git history-inizdə secret axtarın: `git log -p | grep -iE "(password|api_key|secret|token).*="`
2. TruffleHog ilə repository scan edin: `trufflehog git file:///path/to/repo`
3. AWS Parameter Store-dan secret yükləyən Laravel service yazın: `/prod/myapp/database` path-i
4. Kubernetes Secret-i `kubectl get secret app-secrets -o yaml | base64 -d` ilə decode edin — plain text görmək nə qədər asandır?
5. HashiCorp Vault-u Docker-da qurun: `docker run vault server -dev`. Dynamic DB credential alın
6. SOPS ilə `.env` faylını KMS ilə şifrələyin, decrypt edin — workflow-u anlayın
7. `pre-commit` hook-u qurun: push-dan əvvəl `git-secrets` ya da `gitleaks` ilə auto-scan

## Əlaqəli Mövzular

- `14-security-in-cicd.md` — CI/CD pipeline-da secret idarəsi, token injection
- `13-data-encryption.md` — Encryption at rest, envelope encryption, KMS
- `11-least-privilege.md` — Secret access minimum privilege, IAM policy
- `12-audit-logging.md` — Secret access audit, who accessed what when
