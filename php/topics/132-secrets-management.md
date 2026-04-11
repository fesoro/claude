# Secrets Management

## Mündəricat
1. [Problem](#problem)
2. [Secrets Management Prinsipləri](#secrets-management-prinsipləri)
3. [Alətlər](#alətlər)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Problem

```
Tətbiqin sirr saxlaması lazımdır:
  DB parolu, API açarları, JWT secret, SSL sertifikatlar

Pis praktikalar:
  ✗ .env faylında git-ə commit etmək
  ✗ Kod içinə hardcode etmək
  ✗ S3 bucket-a plain text saxlamaq
  ✗ Bütün komandanın paylaşıq parol sənədini istifadə etməsi

Nəticələri:
  → Data breach
  → Compliance pozuntusu (GDPR, PCI DSS)
  → Audit uğursuzluğu

Real incident: GitHub-a push edilmiş AWS keys
  → Botlar 60 saniyə ərzində tapır
  → Kredit kartına böyük xərc
```

---

## Secrets Management Prinsipləri

```
1. Heç vaxt secrets-i koda commit etməyin:
   .gitignore → .env
   git-secrets, detect-secrets pre-commit hook

2. Rotation (fırlanma):
   Secrets müntəzəm dəyişdirilməlidir
   DB parolu 90 gündən çox istifadə edilməməlidir
   Automatic rotation (Vault, AWS Secrets Manager)

3. Least Privilege:
   Hər servis yalnız öz lazım olan secret-lərinə çıxış əldə edir
   OrderService DB parolunu Payment secret-inə çıxışı yoxdur

4. Audit:
   Kim nə vaxt hansı secret-ə baxdı?
   Anormal giriş alert-i

5. Encryption at rest and in transit:
   Secret-lər şifrəli saxlanılır
   Transit zamanı TLS

6. Dynamic secrets:
   Hər tətbiq qaldıqda yeni unikal credentials yaradılır
   Vault database engine: hər servis üçün ayrı DB user
```

---

## Alətlər

```
HashiCorp Vault:
  ✓ Dynamic secrets (DB, AWS, SSH)
  ✓ Secret rotation
  ✓ Fine-grained ACL
  ✓ Audit log
  Çətin quraşdırma, əvəzinə güclü

AWS Secrets Manager:
  ✓ AWS ilə native inteqrasiya
  ✓ Automatic rotation (Lambda ilə)
  ✓ CloudTrail audit
  AWS-ə vendor lock-in

Azure Key Vault / GCP Secret Manager:
  Müvafiq cloud platform-lar üçün

Kubernetes Secrets:
  base64 encode (şifrəli DEYİL!)
  etcd encryption at rest aktiv edilməlidir
  External Secrets Operator → Vault/AWS ilə sync

Environment variables (production-da):
  Container-a inject edilir (Kubernetes secret → env var)
  Process-dən oğurlanmaq mümkündür amma kod içindən yaxşıdır
```

---

## PHP İmplementasiyası

```php
<?php
// 1. HashiCorp Vault client (vault-php/vault)
use Vault\Client;
use Vault\AuthenticationStrategies\AppRoleAuthenticationStrategy;

class VaultSecretsProvider
{
    private Client $vault;
    private array  $cache = [];

    public function __construct(string $vaultAddr, string $roleId, string $secretId)
    {
        $this->vault = new Client(new \GuzzleHttp\Client(['base_uri' => $vaultAddr]));
        $this->vault->setAuthenticationStrategy(
            new AppRoleAuthenticationStrategy($roleId, $secretId)
        );
        $this->vault->authenticate();
    }

    public function get(string $path, string $key): string
    {
        $cacheKey = "{$path}:{$key}";

        if (!isset($this->cache[$cacheKey])) {
            $secret = $this->vault->read($path);
            $this->cache[$cacheKey] = $secret->getData()[$key]
                ?? throw new SecretNotFoundException("{$path}:{$key}");
        }

        return $this->cache[$cacheKey];
    }

    public function getDatabaseCredentials(): array
    {
        // Vault dynamic secrets — hər dəfə yeni credentials
        $creds = $this->vault->read('database/creds/app-role');
        return [
            'username' => $creds->getData()['username'],
            'password' => $creds->getData()['password'],
        ];
    }
}
```

```php
<?php
// 2. AWS Secrets Manager
use Aws\SecretsManager\SecretsManagerClient;

class AwsSecretsProvider
{
    private SecretsManagerClient $client;

    public function __construct(string $region = 'eu-west-1')
    {
        $this->client = new SecretsManagerClient([
            'version' => 'latest',
            'region'  => $region,
            // IAM Role ilə avtomatik credentials (EC2/ECS/Lambda)
        ]);
    }

    public function get(string $secretName): array
    {
        $result = $this->client->getSecretValue(['SecretId' => $secretName]);
        return json_decode($result['SecretString'], true);
    }
}

// Symfony DI-da konfiqurasiya
// services.yaml:
// App\Infrastructure\Secrets\AwsSecretsProvider:
//   arguments:
//     $region: '%env(AWS_REGION)%'
//
// # Secret-i env var kimi inject et
// App\Infrastructure\Database\Connection:
//   arguments:
//     $password: '%env(json:key:password:aws_secret:myapp/db)%'
```

```php
<?php
// 3. .env faylının git-dən qorunması
// .gitignore:
// .env
// .env.local
// .env.*.local

// .env.example — template (commit edilir):
// DATABASE_URL=mysql://user:password@localhost/dbname
// JWT_SECRET=your-secret-here
// STRIPE_KEY=sk_live_...

// Pre-commit hook (detect-secrets):
// pip install detect-secrets
// detect-secrets scan > .secrets.baseline
// detect-secrets audit .secrets.baseline

// PHP-da runtime secrets yükləmək:
class SecretLoader
{
    public static function loadForEnvironment(string $env): void
    {
        match ($env) {
            'production'  => self::loadFromVault(),
            'staging'     => self::loadFromAwsSecrets(),
            'development' => self::loadFromEnvFile(),
            default       => throw new \InvalidArgumentException("Unknown env: {$env}"),
        };
    }

    private static function loadFromEnvFile(): void
    {
        // Development-də .env.local
        (new Dotenv())->loadEnv('.env');
    }

    private static function loadFromVault(): void
    {
        $provider = new VaultSecretsProvider(
            vaultAddr: getenv('VAULT_ADDR'),
            roleId:    getenv('VAULT_ROLE_ID'),
            secretId:  getenv('VAULT_SECRET_ID'),
        );

        // DB credentials-ı environment-ə set et
        $db = $provider->getDatabaseCredentials();
        putenv("DB_USERNAME={$db['username']}");
        putenv("DB_PASSWORD={$db['password']}");
    }
}
```

---

## İntervyu Sualları

- Secrets-i .env faylında saxlamağın problemi nədir?
- Dynamic secrets nədir? Statik secrets-dən üstünlüyü?
- Kubernetes Secrets-in base64 encode olması şifrələmə sayılırmı?
- Secret rotation prosesini izah edin.
- Least privilege secrets access necə tətbiq edilir?
- Bir developer yanlışlıqla API key-i GitHub-a push etdisə nə etmək lazımdır?
