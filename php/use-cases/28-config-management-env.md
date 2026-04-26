# Configuration Management və Environment-based Settings (Middle)

## Ssenari

Böyük bir e-commerce platforması üç mühitdə işləyir: development, staging və production. Komandada 25 developer var, hər biri lokal mühitdə fərqli konfiqurasiya ilə işləyir. Problemlər:

- Developer hardcoded API key-ləri commit edir, production-a leak olur
- Staging mühitdə bir feature aktiv, production-da deaktivdir — amma bunu izləmək çətindir
- Config drift: staging və production arasında konfiqurasiya fərqləri bug-lara səbəb olur
- Yeni developer onboarding zamanı `.env` faylını manual kopyalayır, köhnə dəyərlərlə işə başlayır
- Secret rotation (API key dəyişmə) zamanı bütün serverlərə manual deploy lazımdır
- Feature toggle-lar kod içində `if/else` ilə yazılıb, silmək unudulur

Həll: Mərkəzləşdirilmiş konfiqurasiya idarəetmə sistemi — environment-based settings, secret management, dynamic config, feature toggles və config validation.

---

## Arxitektura

```
┌─────────────────────────────────────────────────────────────────┐
│                    Config Management Flow                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────┐    ┌──────────────┐    ┌───────────────────────┐  │
│  │  .env     │───▶│ config/*.php │───▶│  config:cache         │  │
│  │  faylı    │    │  faylları    │    │  (bootstrap/cache/    │  │
│  └──────────┘    └──────────────┘    │   config.php)         │  │
│       │                               └───────────┬───────────┘  │
│       │                                           │              │
│       ▼                                           ▼              │
│  ┌──────────┐    ┌──────────────┐    ┌───────────────────────┐  │
│  │  Vault /  │───▶│ ConfigService│───▶│     Application       │  │
│  │  AWS SSM  │    │              │    │                       │  │
│  └──────────┘    └──────────────┘    └───────────────────────┘  │
│       │                 │                         ▲              │
│       │                 ▼                         │              │
│  ┌──────────┐    ┌──────────────┐    ┌───────────────────────┐  │
│  │ Encrypted │    │   Redis      │───▶│  Dynamic Config       │  │
│  │ Secrets   │    │   Cache      │    │  (no redeploy)        │  │
│  └──────────┘    └──────────────┘    └───────────────────────┘  │
│                         │                                        │
│                         ▼                                        │
│                  ┌──────────────┐                                │
│                  │  DB Config   │                                │
│                  │  (versioned) │                                │
│                  └──────────────┘                                │
│                                                                  │
│  Deploy Pipeline:                                                │
│  ┌─────┐  ┌─────────┐  ┌──────────┐  ┌──────┐  ┌───────────┐  │
│  │ Git │─▶│ CI/CD   │─▶│ Config   │─▶│Build │─▶│ Deploy    │  │
│  │Push │  │ Trigger │  │ Validate │  │Cache │  │ + Verify  │  │
│  └─────┘  └─────────┘  └──────────┘  └──────┘  └───────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Problem: Hardcoded Config və Env Leak

*Bu anti-pattern-ləri production-da tez-tez görürük:*

```php
// ❌ YANLIŞ: Hardcoded credentials
class PaymentService
{
    public function charge(float $amount): void
    {
        $client = new HttpClient();
        $client->post('https://api.stripe.com/v1/charges', [
            'headers' => [
                // Secret birbaşa kodda — git history-də qalacaq!
                'Authorization' => 'Bearer sk_live_abc123xyz',
            ],
            'json' => [
                'amount' => $amount * 100,
                'currency' => 'usd',
            ],
        ]);
    }
}

// ❌ YANLIŞ: env() birbaşa kod içində (config:cache ilə işləməyəcək)
class NotificationService
{
    public function send(string $message): void
    {
        // config:cache sonrası env() null qaytaracaq!
        $apiKey = env('NOTIFICATION_API_KEY');
        $endpoint = env('NOTIFICATION_ENDPOINT');
        
        // Hardcoded default — mühit fərqi yoxdur
        $retryCount = 3;
    }
}

// ❌ YANLIŞ: Mühit yoxlaması hardcoded
class FeatureService
{
    public function isNewCheckoutEnabled(): bool
    {
        // Bu kodu silmək unudulacaq
        if (env('APP_ENV') === 'production') {
            return false;
        }
        return true;
    }
}
```

---

## Laravel Config Best Practices

### 1. Config faylları düzgün strukturu

*Hər konfiqurasiya config/*.php fayllarında olmalı, env() yalnız config faylları içində çağırılmalıdır:*

```php
// config/services.php — Düzgün yanaşma
return [
    'stripe' => [
        'key'     => env('STRIPE_KEY'),
        'secret'  => env('STRIPE_SECRET'),
        'webhook' => [
            'secret'    => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    'notification' => [
        'driver'   => env('NOTIFICATION_DRIVER', 'log'),
        'api_key'  => env('NOTIFICATION_API_KEY'),
        'endpoint' => env('NOTIFICATION_ENDPOINT', 'https://api.notify.example.com'),
        'retry'    => [
            'times' => env('NOTIFICATION_RETRY_TIMES', 3),
            'delay' => env('NOTIFICATION_RETRY_DELAY', 100),
        ],
    ],

    'search' => [
        'driver'  => env('SEARCH_DRIVER', 'database'),
        'algolia' => [
            'id'     => env('ALGOLIA_APP_ID'),
            'secret' => env('ALGOLIA_SECRET'),
        ],
        'elasticsearch' => [
            'host' => env('ELASTICSEARCH_HOST', 'localhost'),
            'port' => env('ELASTICSEARCH_PORT', 9200),
        ],
    ],
];

// config/features.php — Feature toggles
return [
    'new_checkout'      => env('FEATURE_NEW_CHECKOUT', false),
    'dark_mode'         => env('FEATURE_DARK_MODE', false),
    'ai_recommendations'=> env('FEATURE_AI_RECOMMENDATIONS', false),
    'beta_api_v2'       => env('FEATURE_BETA_API_V2', false),
    'maintenance_banner'=> env('FEATURE_MAINTENANCE_BANNER', false),
];
```

### 2. config:cache əmri və env() məhdudiyyəti

```php
// config:cache nə edir:
// 1. Bütün config/*.php fayllarını bir masivə yığır
// 2. bootstrap/cache/config.php faylına yazır
// 3. Bundan sonra env() çağırışları boş qayıdır (config cache aktiv olduqda)

// ✅ DOĞRU: config() helper istifadə edin
class PaymentService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint,
    ) {}

    public static function create(): self
    {
        return new self(
            apiKey: config('services.stripe.secret'),
            endpoint: config('services.stripe.endpoint', 'https://api.stripe.com'),
        );
    }
}

// Və ya dependency injection ilə:
// AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(PaymentService::class, function ($app) {
        return new PaymentService(
            apiKey: config('services.stripe.secret'),
            endpoint: config('services.stripe.endpoint'),
        );
    });
}
```

### 3. Environment-specific konfiqurasiya

```php
// config/logging.php — mühitə görə logging
return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver'   => 'stack',
            'channels' => explode(',', env('LOG_STACK_CHANNELS', 'single')),
        ],

        'single' => [
            'driver' => 'single',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
        ],

        // Production-da JSON formatında log (ELK/Datadog üçün)
        'json' => [
            'driver' => 'single',
            'path'   => storage_path('logs/laravel.json.log'),
            'level'  => env('LOG_LEVEL', 'info'),
            'formatter' => \Monolog\Formatter\JsonFormatter::class,
        ],

        'slack' => [
            'driver'   => 'slack',
            'url'      => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji'    => ':boom:',
            'level'    => env('LOG_SLACK_LEVEL', 'critical'),
        ],
    ],
];

// .env.example — mühitlərin sənədləşdirilməsi
// Bu fayl bütün lazımi dəyişənləri göstərir:
/*
APP_NAME=MyApp
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Dev üçün:   LOG_STACK_CHANNELS=single
# Staging:    LOG_STACK_CHANNELS=json,slack
# Production: LOG_STACK_CHANNELS=json,slack,sentry
LOG_STACK_CHANNELS=single
LOG_LEVEL=debug

STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...

FEATURE_NEW_CHECKOUT=false
FEATURE_DARK_MODE=false
*/
```

---

## Secret Management

### 1. HashiCorp Vault inteqrasiyası

*Secretləri Vault-dan çəkib Laravel config-ə inject edən servis:*

```php
<?php

namespace App\Services\Config;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VaultSecretManager
{
    private string $vaultAddr;
    private string $vaultToken;
    private string $secretPath;

    public function __construct()
    {
        $this->vaultAddr  = config('vault.address', 'https://vault.example.com');
        $this->vaultToken = config('vault.token');
        $this->secretPath = config('vault.secret_path', 'secret/data/myapp');
    }

    /**
     * Vault-dan secretləri yüklə və config-ə inject et.
     * Boot zamanı çağırılır.
     */
    public function loadSecrets(): void
    {
        $environment = config('app.env');
        $cacheKey    = "vault_secrets_{$environment}";
        $cacheTtl    = config('vault.cache_ttl', 300); // 5 dəqiqə

        $secrets = Cache::remember($cacheKey, $cacheTtl, function () use ($environment) {
            return $this->fetchFromVault($environment);
        });

        if ($secrets === null) {
            Log::critical('Vault secretləri yüklənə bilmədi', [
                'environment' => $environment,
            ]);
            
            // Fallback: mövcud config dəyərlərini saxla
            return;
        }

        // Secretləri config-ə inject et
        foreach ($secrets as $key => $value) {
            config([$key => $value]);
        }

        Log::info('Vault secretləri uğurla yükləndi', [
            'environment' => $environment,
            'keys_count'  => count($secrets),
        ]);
    }

    private function fetchFromVault(string $environment): ?array
    {
        try {
            $response = Http::withHeaders([
                'X-Vault-Token' => $this->vaultToken,
            ])->get("{$this->vaultAddr}/v1/{$this->secretPath}/{$environment}");

            if (!$response->successful()) {
                Log::error('Vault sorğusu uğursuz oldu', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $data = $response->json('data.data');

            // Vault secret-lərini Laravel config key-lərinə map et
            return $this->mapSecretsToConfig($data);
        } catch (\Throwable $e) {
            Log::error('Vault bağlantı xətası', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Vault key-lərini Laravel config key-lərinə çevir.
     * Vault-da: STRIPE_SECRET → config: services.stripe.secret
     */
    private function mapSecretsToConfig(array $data): array
    {
        $mapping = [
            'STRIPE_SECRET'          => 'services.stripe.secret',
            'STRIPE_WEBHOOK_SECRET'  => 'services.stripe.webhook.secret',
            'DB_PASSWORD'            => 'database.connections.mysql.password',
            'REDIS_PASSWORD'         => 'database.redis.default.password',
            'MAIL_PASSWORD'          => 'mail.mailers.smtp.password',
            'AWS_SECRET_ACCESS_KEY'  => 'filesystems.disks.s3.secret',
            'NOTIFICATION_API_KEY'   => 'services.notification.api_key',
        ];

        $config = [];
        foreach ($mapping as $vaultKey => $configKey) {
            if (isset($data[$vaultKey])) {
                $config[$configKey] = $data[$vaultKey];
            }
        }

        return $config;
    }

    /**
     * Secret rotation — köhnə secret-i yenisi ilə əvəz et.
     * Vault-da secret dəyişdikdən sonra cache-i yenilə.
     */
    public function rotateSecret(string $configKey, string $newValue): void
    {
        config([$configKey => $newValue]);

        $environment = config('app.env');
        Cache::forget("vault_secrets_{$environment}");

        Log::info('Secret rotasiya edildi', [
            'config_key'  => $configKey,
            'environment' => $environment,
        ]);
    }
}
```

### 2. AWS Systems Manager Parameter Store

```php
<?php

namespace App\Services\Config;

use Aws\Ssm\SsmClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AwsSsmSecretManager
{
    private SsmClient $client;
    private string $prefix;

    public function __construct()
    {
        $this->client = new SsmClient([
            'region'  => config('aws.region', 'eu-west-1'),
            'version' => 'latest',
        ]);

        $this->prefix = sprintf(
            '/%s/%s/',
            config('app.name'),
            config('app.env')
        );
        // Məsələn: /MyApp/production/
    }

    /**
     * SSM-dən bütün parametrləri path prefiksinə görə yüklə.
     * /MyApp/production/STRIPE_SECRET → services.stripe.secret
     */
    public function loadParameters(): array
    {
        $cacheKey = 'ssm_params_' . md5($this->prefix);

        return Cache::remember($cacheKey, 600, function () {
            $parameters = [];
            $nextToken  = null;

            do {
                $params = array_filter([
                    'Path'           => $this->prefix,
                    'Recursive'      => true,
                    'WithDecryption' => true,
                    'MaxResults'     => 10,
                    'NextToken'      => $nextToken,
                ]);

                $result    = $this->client->getParametersByPath($params);
                $nextToken = $result['NextToken'] ?? null;

                foreach ($result['Parameters'] as $param) {
                    $key = str_replace($this->prefix, '', $param['Name']);
                    $parameters[$key] = $param['Value'];
                }
            } while ($nextToken);

            Log::info('SSM parametrləri yükləndi', [
                'prefix' => $this->prefix,
                'count'  => count($parameters),
            ]);

            return $parameters;
        });
    }

    /**
     * Tək parametr yüklə (lazım olduqda).
     */
    public function getParameter(string $name): ?string
    {
        try {
            $result = $this->client->getParameter([
                'Name'           => $this->prefix . $name,
                'WithDecryption' => true,
            ]);

            return $result['Parameter']['Value'];
        } catch (\Throwable $e) {
            Log::warning('SSM parametr tapılmadı', [
                'name'    => $name,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
```

### 3. Encrypted .env faylı

```php
<?php

namespace App\Services\Config;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\File;

class EncryptedEnvManager
{
    private string $encryptionKey;

    public function __construct(string $encryptionKey)
    {
        $this->encryptionKey = $encryptionKey;
    }

    /**
     * .env faylını şifrələ → .env.encrypted yaradılır.
     * Bu faylı git-ə commit etmək təhlükəsizdir.
     * 
     * Deşifrə üçün LARAVEL_ENV_ENCRYPTION_KEY lazımdır.
     */
    public function encrypt(string $envPath = null): string
    {
        $envPath       = $envPath ?? base_path('.env');
        $encryptedPath = $envPath . '.encrypted';

        $encrypter = new Encrypter(
            base64_decode($this->encryptionKey),
            'AES-256-CBC'
        );

        $contents  = File::get($envPath);
        $encrypted = $encrypter->encrypt($contents);

        File::put($encryptedPath, $encrypted);

        return $encryptedPath;
    }

    /**
     * .env.encrypted faylını deşifrə edib .env yaradır.
     * Deploy zamanı CI/CD pipeline-da istifadə olunur.
     */
    public function decrypt(string $encryptedPath = null): string
    {
        $encryptedPath = $encryptedPath ?? base_path('.env.encrypted');
        $envPath       = base_path('.env');

        $encrypter = new Encrypter(
            base64_decode($this->encryptionKey),
            'AES-256-CBC'
        );

        $encrypted = File::get($encryptedPath);
        $decrypted = $encrypter->decrypt($encrypted);

        File::put($envPath, $decrypted);

        return $envPath;
    }
}

// Laravel 11+ daxili dəstək:
// php artisan env:encrypt --env=production
// php artisan env:decrypt --env=production --key=base64:...
```

---

## Dynamic Config — DB və Redis əsaslı

*Deploy olmadan konfiqurasiya dəyişmək üçün:*

### Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->index();      // 'features', 'limits', 'ui'
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, int, bool, json, float
            $table->string('environment', 20)->nullable(); // null = bütün mühitlər
            $table->text('description')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['group', 'key', 'environment']);
        });

        // Config dəyişiklik tarixçəsi
        Schema::create('configuration_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_id')->constrained()->cascadeOnDelete();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->unsignedInteger('version');
            $table->string('changed_by');
            $table->string('change_reason')->nullable();
            $table->timestamp('created_at');
        });
    }
};
```

### ConfigService — əsas servis

```php
<?php

namespace App\Services\Config;

use App\Models\Configuration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ConfigService
{
    private const CACHE_PREFIX = 'dynamic_config:';
    private const CACHE_TTL    = 3600; // 1 saat
    private const CACHE_TAG    = 'dynamic_config';

    /**
     * Konfiqurasiya dəyərini oxu.
     * Əvvəlcə Redis cache-ə baxır, sonra DB-yə.
     */
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $environment = config('app.env');
        $cacheKey    = $this->cacheKey($group, $key, $environment);

        $value = Cache::tags([self::CACHE_TAG])->remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($group, $key, $environment, $default) {
                // Əvvəlcə environment-specific dəyəri axtar
                $config = Configuration::where('group', $group)
                    ->where('key', $key)
                    ->where(function ($query) use ($environment) {
                        $query->where('environment', $environment)
                              ->orWhereNull('environment');
                    })
                    ->orderByRaw('environment IS NULL ASC') // environment-specific üstünlük alır
                    ->first();

                if ($config === null) {
                    return $default;
                }

                return $this->castValue($config->value, $config->type);
            }
        );

        return $value;
    }

    /**
     * Konfiqurasiya dəyərini yaz/yenilə.
     * Versiya artırılır, tarixçə yazılır, cache yenilənir.
     */
    public function set(
        string $group,
        string $key,
        mixed $value,
        string $type = 'string',
        ?string $environment = null,
        ?string $reason = null,
    ): Configuration {
        $config = Configuration::firstOrNew([
            'group'       => $group,
            'key'         => $key,
            'environment' => $environment,
        ]);

        $oldValue = $config->exists ? $config->value : null;

        // Versiya artır
        $newVersion = ($config->version ?? 0) + 1;

        $config->fill([
            'value'      => $this->serializeValue($value, $type),
            'type'       => $type,
            'version'    => $newVersion,
            'updated_by' => Auth::user()?->email ?? 'system',
        ]);

        $config->save();

        // Tarixçə yaz
        $config->history()->create([
            'old_value'     => $oldValue,
            'new_value'     => $config->value,
            'version'       => $newVersion,
            'changed_by'    => $config->updated_by,
            'change_reason' => $reason,
        ]);

        // Cache yenilə — bütün mühitlər üçün
        $this->invalidateCache($group, $key);

        Log::info('Konfiqurasiya dəyişdirildi', [
            'group'       => $group,
            'key'         => $key,
            'environment' => $environment,
            'version'     => $newVersion,
            'changed_by'  => $config->updated_by,
        ]);

        return $config;
    }

    /**
     * Konfiqurasiyanı əvvəlki versiyaya qaytar.
     */
    public function rollback(string $group, string $key, int $targetVersion): Configuration
    {
        $config = Configuration::where('group', $group)
            ->where('key', $key)
            ->firstOrFail();

        $historyEntry = $config->history()
            ->where('version', $targetVersion)
            ->firstOrFail();

        return $this->set(
            group: $group,
            key: $key,
            value: $historyEntry->old_value,
            type: $config->type,
            environment: $config->environment,
            reason: "Rollback to version {$targetVersion}",
        );
    }

    /**
     * Qrup üzrə bütün konfiqurasiyaları oxu.
     */
    public function getGroup(string $group): array
    {
        $environment = config('app.env');
        $cacheKey    = self::CACHE_PREFIX . "group:{$group}:{$environment}";

        return Cache::tags([self::CACHE_TAG])->remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($group, $environment) {
                $configs = Configuration::where('group', $group)
                    ->where(function ($query) use ($environment) {
                        $query->where('environment', $environment)
                              ->orWhereNull('environment');
                    })
                    ->get()
                    ->groupBy('key')
                    ->map(function ($items) {
                        // Environment-specific olanı üstün tut
                        $config = $items->sortByDesc('environment')->first();
                        return $this->castValue($config->value, $config->type);
                    })
                    ->toArray();

                return $configs;
            }
        );
    }

    /**
     * Bütün dynamic config cache-ini təmizlə.
     */
    public function flushCache(): void
    {
        Cache::tags([self::CACHE_TAG])->flush();

        Log::info('Dynamic config cache təmizləndi');
    }

    private function cacheKey(string $group, string $key, string $env): string
    {
        return self::CACHE_PREFIX . "{$group}:{$key}:{$env}";
    }

    private function invalidateCache(string $group, string $key): void
    {
        // Bütün mühitlər üçün cache-i sil
        foreach (['local', 'staging', 'production'] as $env) {
            Cache::tags([self::CACHE_TAG])->forget(
                $this->cacheKey($group, $key, $env)
            );
        }

        // Qrup cache-ini də sil
        foreach (['local', 'staging', 'production'] as $env) {
            Cache::tags([self::CACHE_TAG])->forget(
                self::CACHE_PREFIX . "group:{$group}:{$env}"
            );
        }
    }

    private function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double'=> (float) $value,
            'bool', 'boolean'=> filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array'  => json_decode($value, true),
            default           => $value,
        };
    }

    private function serializeValue(mixed $value, string $type): string
    {
        if ($type === 'json' || $type === 'array') {
            return json_encode($value);
        }

        if ($type === 'bool' || $type === 'boolean') {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
```

### Configuration Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Configuration extends Model
{
    protected $fillable = [
        'group', 'key', 'value', 'type',
        'environment', 'description',
        'is_sensitive', 'version', 'updated_by',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
        'version'      => 'integer',
    ];

    public function history(): HasMany
    {
        return $this->hasMany(ConfigurationHistory::class)
            ->orderByDesc('version');
    }

    /**
     * Sensitive dəyərləri gizlət (API panel-də göstərmə).
     */
    public function getDisplayValueAttribute(): string
    {
        if ($this->is_sensitive) {
            return str_repeat('*', 8) . substr($this->value, -4);
        }

        return $this->value;
    }
}
```

---

## Feature Toggle Sistemi

*Feature toggle-lar dynamic config üzərində qurulur:*

```php
<?php

namespace App\Services\Config;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FeatureToggleService
{
    private ConfigService $configService;

    /** @var array<string, bool> Proses daxili cache */
    private array $resolvedFeatures = [];

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Feature aktiv/deaktiv yoxla.
     * 
     * Prioritet sırası:
     * 1. Dynamic config (DB) — admin paneldən idarə
     * 2. Static config (config/features.php) — deploy ilə dəyişir
     * 3. Default dəyər
     */
    public function isEnabled(string $feature, bool $default = false): bool
    {
        if (isset($this->resolvedFeatures[$feature])) {
            return $this->resolvedFeatures[$feature];
        }

        // 1. Dynamic config (DB/Redis)
        $dynamic = $this->configService->get('features', $feature);
        if ($dynamic !== null) {
            $this->resolvedFeatures[$feature] = (bool) $dynamic;
            return $this->resolvedFeatures[$feature];
        }

        // 2. Static config
        $static = config("features.{$feature}");
        if ($static !== null) {
            $this->resolvedFeatures[$feature] = (bool) $static;
            return $this->resolvedFeatures[$feature];
        }

        // 3. Default
        $this->resolvedFeatures[$feature] = $default;
        return $default;
    }

    /**
     * Feature-i aktivləşdir (deploy olmadan).
     */
    public function enable(string $feature, ?string $reason = null): void
    {
        $this->configService->set(
            group: 'features',
            key: $feature,
            value: true,
            type: 'bool',
            reason: $reason ?? "Feature '{$feature}' aktivləşdirildi",
        );

        unset($this->resolvedFeatures[$feature]);

        Log::info("Feature aktivləşdirildi: {$feature}");
    }

    /**
     * Feature-i deaktiv et (deploy olmadan).
     */
    public function disable(string $feature, ?string $reason = null): void
    {
        $this->configService->set(
            group: 'features',
            key: $feature,
            value: false,
            type: 'bool',
            reason: $reason ?? "Feature '{$feature}' deaktiv edildi",
        );

        unset($this->resolvedFeatures[$feature]);

        Log::info("Feature deaktiv edildi: {$feature}");
    }

    /**
     * Bütün feature-lərin siyahısı.
     */
    public function all(): array
    {
        $static  = config('features', []);
        $dynamic = $this->configService->getGroup('features');

        // Dynamic static-i override edir
        return array_merge($static, $dynamic);
    }
}
```

### Blade və Controller-da istifadə

```php
// Helper funksiya (app/helpers.php)
if (!function_exists('feature')) {
    function feature(string $name, bool $default = false): bool
    {
        return app(FeatureToggleService::class)->isEnabled($name, $default);
    }
}

// Controller-da istifadə
class CheckoutController extends Controller
{
    public function index()
    {
        if (feature('new_checkout')) {
            return view('checkout.new');
        }

        return view('checkout.classic');
    }
}

// Blade template-də istifadə
// resources/views/layouts/app.blade.php
/*
@if(feature('dark_mode'))
    <body class="dark-theme">
@else
    <body class="light-theme">
@endif

@if(feature('maintenance_banner'))
    <div class="alert alert-warning">
        Planlaşdırılmış texniki iş: {{ config('maintenance.message') }}
    </div>
@endif
*/

// Middleware ilə feature gate
class FeatureMiddleware
{
    public function handle($request, \Closure $next, string $feature)
    {
        if (!feature($feature)) {
            abort(404);
        }

        return $next($request);
    }
}

// Route-da:
// Route::get('/beta/api/v2', ...)->middleware('feature:beta_api_v2');
```

---

## Config Validation — Boot zamanı yoxlama

*Tətbiq başladıqda bütün vacib konfiqurasiyaların mövcud olduğunu yoxlayır:*

```php
<?php

namespace App\Services\Config;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class ConfigValidator
{
    /**
     * Vacib konfiqurasiyalar — bunlar olmadan tətbiq işləyə bilməz.
     * [config_key => [validation_rules]]
     */
    private array $requiredConfigs = [
        'app.key' => ['not_empty'],
        'app.url' => ['not_empty', 'url'],

        'database.connections.mysql.host'     => ['not_empty'],
        'database.connections.mysql.database'  => ['not_empty'],
        'database.connections.mysql.username'  => ['not_empty'],

        'services.stripe.secret'  => ['not_empty', 'starts_with:sk_'],
        'services.stripe.key'     => ['not_empty', 'starts_with:pk_'],

        'mail.mailers.smtp.host'  => ['not_empty'],

        'cache.default'   => ['not_empty', 'in:redis,memcached,file'],
        'queue.default'   => ['not_empty', 'in:redis,sqs,database'],
        'session.driver'  => ['not_empty', 'in:redis,database,file,cookie'],
    ];

    /**
     * Production-da əlavə yoxlamalar.
     */
    private array $productionConfigs = [
        'app.debug'  => ['equals:false'],
        'app.env'    => ['equals:production'],
        'logging.default' => ['not_empty'],
        'services.stripe.secret' => ['starts_with:sk_live_'],
    ];

    /**
     * Bütün konfiqurasiyaları yoxla.
     * Boot zamanı ServiceProvider-dən çağırılır.
     *
     * @throws RuntimeException
     */
    public function validate(): array
    {
        $errors = [];

        // Əsas yoxlamalar
        foreach ($this->requiredConfigs as $key => $rules) {
            $value      = config($key);
            $ruleErrors = $this->validateValue($key, $value, $rules);
            if (!empty($ruleErrors)) {
                $errors = array_merge($errors, $ruleErrors);
            }
        }

        // Production əlavə yoxlamalar
        if (config('app.env') === 'production') {
            foreach ($this->productionConfigs as $key => $rules) {
                $value      = config($key);
                $ruleErrors = $this->validateValue($key, $value, $rules);
                if (!empty($ruleErrors)) {
                    $errors = array_merge($errors, $ruleErrors);
                }
            }
        }

        if (!empty($errors)) {
            $errorMessage = "Config validation xətaları:\n" . implode("\n", $errors);

            Log::critical($errorMessage);

            // Production-da xəta at, dev-də yalnız warning ver
            if (config('app.env') === 'production') {
                throw new RuntimeException($errorMessage);
            }

            Log::warning('Config validation xəbərdarlıqları (non-production)', [
                'errors' => $errors,
            ]);
        }

        return $errors;
    }

    private function validateValue(string $key, mixed $value, array $rules): array
    {
        $errors = [];

        foreach ($rules as $rule) {
            $error = match (true) {
                $rule === 'not_empty' && empty($value)
                    => "Config '{$key}' boş və ya təyin edilməyib",

                $rule === 'url' && !filter_var($value, FILTER_VALIDATE_URL)
                    => "Config '{$key}' düzgün URL deyil: {$value}",

                str_starts_with($rule, 'starts_with:') && !str_starts_with((string)$value, substr($rule, 12))
                    => "Config '{$key}' '{$this->mask($value)}' ilə başlamalıdır: " . substr($rule, 12),

                str_starts_with($rule, 'equals:') && (string)$value !== substr($rule, 7)
                    => "Config '{$key}' = '{$value}', gözlənilən: " . substr($rule, 7),

                str_starts_with($rule, 'in:') && !in_array($value, explode(',', substr($rule, 3)))
                    => "Config '{$key}' = '{$value}', icazə verilən: " . substr($rule, 3),

                default => null,
            };

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * Sensitive dəyərləri mask et (log-a yazmamaq üçün).
     */
    private function mask(mixed $value): string
    {
        $str = (string) $value;
        if (strlen($str) <= 8) {
            return '****';
        }
        return substr($str, 0, 4) . '****' . substr($str, -4);
    }
}
```

### ServiceProvider-da qeydiyyat

```php
<?php

namespace App\Providers;

use App\Services\Config\ConfigService;
use App\Services\Config\ConfigValidator;
use App\Services\Config\FeatureToggleService;
use App\Services\Config\VaultSecretManager;
use Illuminate\Support\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfigService::class);
        $this->app->singleton(FeatureToggleService::class);
        $this->app->singleton(ConfigValidator::class);

        // Vault yalnız production/staging-də
        if (in_array(config('app.env'), ['production', 'staging'])) {
            $this->app->singleton(VaultSecretManager::class);
        }
    }

    public function boot(): void
    {
        // 1. Vault-dan secretləri yüklə (production/staging)
        if ($this->app->bound(VaultSecretManager::class)) {
            $this->app->make(VaultSecretManager::class)->loadSecrets();
        }

        // 2. Config validation
        $validator = $this->app->make(ConfigValidator::class);
        $errors    = $validator->validate();

        if (!empty($errors) && config('app.env') !== 'production') {
            // Dev-də console-a warning yaz
            foreach ($errors as $error) {
                logger()->warning("[Config] {$error}");
            }
        }
    }
}
```

---

## Multi-environment Deployment Pipeline

*CI/CD pipeline-da konfiqurasiya idarəsi:*

```yaml
# .github/workflows/deploy.yml
name: Deploy Pipeline

on:
  push:
    branches: [main, staging]

jobs:
  config-validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: PHP Setup
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install Dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Decrypt env file
        run: |
          php artisan env:decrypt \
            --env=${{ github.ref == 'refs/heads/main' && 'production' || 'staging' }} \
            --key=${{ secrets.ENV_ENCRYPTION_KEY }}

      - name: Cache config
        run: php artisan config:cache

      - name: Validate config
        run: php artisan config:validate

  deploy-staging:
    needs: config-validate
    if: github.ref == 'refs/heads/staging'
    runs-on: ubuntu-latest
    environment: staging
    steps:
      - name: Deploy to staging
        run: |
          # Config-i decrypt et
          php artisan env:decrypt --env=staging --key=${{ secrets.STAGING_ENV_KEY }}
          
          # Config cache yarat
          php artisan config:cache
          
          # Migrate
          php artisan migrate --force
          
          # Dynamic config seed (yeni feature toggle-lar)
          php artisan config:seed-defaults

  deploy-production:
    needs: config-validate
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    environment: production
    steps:
      - name: Deploy to production
        run: |
          # Vault-dan secretlər boot zamanı yüklənəcək
          # Yalnız VAULT_TOKEN və VAULT_ADDR lazımdır
          
          # Config cache
          php artisan config:cache
          
          # Zero-downtime deploy
          php artisan down --refresh=15 --retry=60
          php artisan migrate --force
          php artisan config:seed-defaults
          php artisan up
```

### Config Seed Command

```php
<?php

namespace App\Console\Commands;

use App\Services\Config\ConfigService;
use Illuminate\Console\Command;

class SeedDefaultConfigs extends Command
{
    protected $signature   = 'config:seed-defaults {--force : Mövcud dəyərləri override et}';
    protected $description = 'Default dynamic konfiqurasiyaları DB-yə yaz';

    public function handle(ConfigService $configService): int
    {
        $defaults = [
            // Feature toggles
            ['features', 'new_checkout',       false, 'bool', 'Yeni checkout UI'],
            ['features', 'dark_mode',          false, 'bool', 'Dark mode dəstəyi'],
            ['features', 'ai_recommendations', false, 'bool', 'AI tövsiyə sistemi'],
            ['features', 'beta_api_v2',        false, 'bool', 'Beta API v2 endpoint-ləri'],

            // Rate limits
            ['limits', 'api_rate_limit',       60,    'int',  'API rate limit (req/dəqiqə)'],
            ['limits', 'upload_max_size_mb',   10,    'int',  'Fayl yükləmə limiti (MB)'],
            ['limits', 'export_max_rows',      10000, 'int',  'Export max sətir sayı'],

            // UI config
            ['ui', 'items_per_page',           25,    'int',  'Səhifədə göstərilən element sayı'],
            ['ui', 'default_locale',           'az',  'string', 'Default dil'],
            ['ui', 'maintenance_message',      '',    'string', 'Texniki iş mesajı'],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($defaults as [$group, $key, $value, $type, $description]) {
            $exists = \App\Models\Configuration::where('group', $group)
                ->where('key', $key)
                ->whereNull('environment')
                ->exists();

            if ($exists && !$this->option('force')) {
                $this->line("  Skip: {$group}.{$key} (mövcuddur)");
                $skipped++;
                continue;
            }

            $configService->set(
                group: $group,
                key: $key,
                value: $value,
                type: $type,
                reason: 'Default seed',
            );

            // Description əlavə et
            \App\Models\Configuration::where('group', $group)
                ->where('key', $key)
                ->whereNull('environment')
                ->update(['description' => $description]);

            $this->info("  Created: {$group}.{$key} = {$value}");
            $created++;
        }

        $this->info("Seed tamamlandı: {$created} yaradıldı, {$skipped} skip edildi.");

        return Command::SUCCESS;
    }
}
```

### Config Validate Artisan Command

```php
<?php

namespace App\Console\Commands;

use App\Services\Config\ConfigValidator;
use Illuminate\Console\Command;

class ValidateConfig extends Command
{
    protected $signature   = 'config:validate';
    protected $description = 'Konfiqurasiya dəyərlərini yoxla';

    public function handle(ConfigValidator $validator): int
    {
        $this->info('Konfiqurasiya yoxlanılır...');

        $errors = $validator->validate();

        if (empty($errors)) {
            $this->info('Bütün konfiqurasiyalar düzgündür.');
            return Command::SUCCESS;
        }

        $this->error('Config xətaları tapıldı:');
        foreach ($errors as $error) {
            $this->line("  ✗ {$error}");
        }

        return Command::FAILURE;
    }
}
```

---

## Config Versioning və Rollback

*Admin panel üçün konfiqurasiya dəyişiklik tarixçəsi və geri qaytarma:*

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\Configuration;
use App\Services\Config\ConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController
{
    public function __construct(
        private readonly ConfigService $configService,
    ) {}

    /**
     * Bütün konfiqurasiyaları göstər (qrup üzrə).
     */
    public function index(Request $request): JsonResponse
    {
        $group   = $request->get('group');
        $configs = Configuration::query()
            ->when($group, fn($q) => $q->where('group', $group))
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->map(fn(Configuration $c) => [
                'id'          => $c->id,
                'group'       => $c->group,
                'key'         => $c->key,
                'value'       => $c->display_value, // sensitive mask olunur
                'type'        => $c->type,
                'environment' => $c->environment,
                'version'     => $c->version,
                'updated_by'  => $c->updated_by,
                'updated_at'  => $c->updated_at->toISOString(),
                'description' => $c->description,
            ]);

        return response()->json(['data' => $configs]);
    }

    /**
     * Konfiqurasiya dəyərini yenilə.
     */
    public function update(Request $request, string $group, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value'  => 'required',
            'reason' => 'required|string|max:255',
        ]);

        $config = Configuration::where('group', $group)
            ->where('key', $key)
            ->firstOrFail();

        $updated = $this->configService->set(
            group: $group,
            key: $key,
            value: $validated['value'],
            type: $config->type,
            environment: $config->environment,
            reason: $validated['reason'],
        );

        return response()->json([
            'message' => 'Konfiqurasiya yeniləndi',
            'data'    => [
                'version' => $updated->version,
            ],
        ]);
    }

    /**
     * Dəyişiklik tarixçəsi.
     */
    public function history(string $group, string $key): JsonResponse
    {
        $config = Configuration::where('group', $group)
            ->where('key', $key)
            ->firstOrFail();

        $history = $config->history()
            ->orderByDesc('version')
            ->limit(50)
            ->get()
            ->map(fn($h) => [
                'version'    => $h->version,
                'old_value'  => $config->is_sensitive ? '****' : $h->old_value,
                'new_value'  => $config->is_sensitive ? '****' : $h->new_value,
                'changed_by' => $h->changed_by,
                'reason'     => $h->change_reason,
                'changed_at' => $h->created_at->toISOString(),
            ]);

        return response()->json(['data' => $history]);
    }

    /**
     * Rollback — əvvəlki versiyaya qayıt.
     */
    public function rollback(Request $request, string $group, string $key): JsonResponse
    {
        $validated = $request->validate([
            'target_version' => 'required|integer|min:1',
        ]);

        $config = $this->configService->rollback(
            group: $group,
            key: $key,
            targetVersion: $validated['target_version'],
        );

        return response()->json([
            'message' => "Versiya {$validated['target_version']}-ə rollback edildi",
            'data'    => [
                'current_version' => $config->version,
            ],
        ]);
    }

    /**
     * Config fərqlərini mühitlər arasında müqayisə et.
     * Config drift aşkarlamaq üçün.
     */
    public function diff(string $env1, string $env2): JsonResponse
    {
        $configs1 = Configuration::where('environment', $env1)
            ->get()
            ->keyBy(fn($c) => "{$c->group}.{$c->key}");

        $configs2 = Configuration::where('environment', $env2)
            ->get()
            ->keyBy(fn($c) => "{$c->group}.{$c->key}");

        $allKeys = $configs1->keys()->merge($configs2->keys())->unique();

        $differences = [];
        foreach ($allKeys as $key) {
            $val1 = $configs1->get($key)?->value;
            $val2 = $configs2->get($key)?->value;

            if ($val1 !== $val2) {
                $isSensitive = $configs1->get($key)?->is_sensitive
                    || $configs2->get($key)?->is_sensitive;

                $differences[] = [
                    'key'  => $key,
                    $env1  => $isSensitive ? '****' : $val1,
                    $env2  => $isSensitive ? '****' : $val2,
                    'only_in' => $val1 === null ? $env2 : ($val2 === null ? $env1 : null),
                ];
            }
        }

        return response()->json([
            'environments' => [$env1, $env2],
            'differences'  => $differences,
            'total_diff'   => count($differences),
        ]);
    }
}
```

---

## Testlərdə Config Override

*Testlər zamanı konfiqurasiya dəyərlərini dəyişmək üçün yanaşmalar:*

```php
<?php

namespace Tests\Feature;

use App\Services\Config\ConfigService;
use App\Services\Config\FeatureToggleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Laravel-in daxili config override mexanizmi.
     * Test müddətində config dəyərini dəyişir.
     */
    public function test_config_override_with_set(): void
    {
        // Əvvəlcə default dəyəri yoxla
        $this->assertEquals('mysql', config('database.default'));

        // Test üçün override et
        config(['database.default' => 'sqlite']);
        $this->assertEquals('sqlite', config('database.default'));
        // Test bitdikdən sonra avtomatik reset olur
    }

    /**
     * Feature toggle test-də override.
     */
    public function test_feature_toggle_enabled(): void
    {
        config(['features.new_checkout' => true]);

        $response = $this->get('/checkout');

        $response->assertViewIs('checkout.new');
    }

    public function test_feature_toggle_disabled(): void
    {
        config(['features.new_checkout' => false]);

        $response = $this->get('/checkout');

        $response->assertViewIs('checkout.classic');
    }

    /**
     * Dynamic config (DB-based) test.
     */
    public function test_dynamic_config_set_and_get(): void
    {
        $configService = app(ConfigService::class);

        // Config yaz
        $configService->set(
            group: 'limits',
            key: 'api_rate_limit',
            value: 100,
            type: 'int',
            reason: 'Test',
        );

        // Oxu və yoxla
        $value = $configService->get('limits', 'api_rate_limit');
        $this->assertEquals(100, $value);
        $this->assertIsInt($value);
    }

    /**
     * Config rollback test.
     */
    public function test_config_rollback(): void
    {
        $configService = app(ConfigService::class);

        // V1: 60
        $configService->set('limits', 'api_rate_limit', 60, 'int', reason: 'Initial');

        // V2: 120
        $configService->set('limits', 'api_rate_limit', 120, 'int', reason: 'Increase');

        // V3: 30
        $configService->set('limits', 'api_rate_limit', 30, 'int', reason: 'Decrease');

        // Rollback to V2
        $configService->rollback('limits', 'api_rate_limit', 2);

        $value = $configService->get('limits', 'api_rate_limit');
        // Rollback V2-nin old_value-sunu qaytarır (V1 dəyəri = 60)
        $this->assertEquals(60, $value);
    }

    /**
     * Config validation test.
     */
    public function test_config_validation_catches_missing_values(): void
    {
        // Vacib config-i sil
        config(['services.stripe.secret' => null]);

        $validator = app(\App\Services\Config\ConfigValidator::class);
        $errors    = $validator->validate();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('stripe.secret', implode(' ', $errors));
    }

    /**
     * Environment-specific config test.
     */
    public function test_environment_specific_config_overrides_global(): void
    {
        $configService = app(ConfigService::class);

        // Global dəyər
        $configService->set('limits', 'max_upload', 10, 'int', environment: null);

        // Production-specific dəyər
        $configService->set('limits', 'max_upload', 50, 'int', environment: 'testing');

        // Testing mühitində olduğumuz üçün 50 gözləyirik
        $value = $configService->get('limits', 'max_upload');
        $this->assertEquals(50, $value);
    }

    /**
     * Config diff test — mühitlər arası fərq.
     */
    public function test_config_diff_between_environments(): void
    {
        $configService = app(ConfigService::class);

        // Staging config
        $configService->set('features', 'beta', true, 'bool', environment: 'staging');

        // Production config
        $configService->set('features', 'beta', false, 'bool', environment: 'production');

        // Admin endpoint-dən diff al
        $response = $this->actingAs($this->createAdmin())
            ->getJson('/admin/config/diff/staging/production');

        $response->assertOk()
            ->assertJsonPath('total_diff', 1)
            ->assertJsonPath('differences.0.key', 'features.beta');
    }

    /**
     * Feature toggle mock — test zamanı bütün feature-ləri override et.
     */
    public function test_with_mocked_features(): void
    {
        $mock = $this->mock(FeatureToggleService::class, function ($mock) {
            $mock->shouldReceive('isEnabled')
                ->with('new_checkout', false)
                ->andReturn(true);

            $mock->shouldReceive('isEnabled')
                ->with('dark_mode', false)
                ->andReturn(false);
        });

        // new_checkout aktiv
        $this->assertTrue(app(FeatureToggleService::class)->isEnabled('new_checkout'));
        
        // dark_mode deaktiv
        $this->assertFalse(app(FeatureToggleService::class)->isEnabled('dark_mode'));
    }

    /**
     * Sensitive config mask test.
     */
    public function test_sensitive_config_is_masked_in_api(): void
    {
        $config = \App\Models\Configuration::create([
            'group'        => 'services',
            'key'          => 'api_secret',
            'value'        => 'super_secret_value_123',
            'type'         => 'string',
            'is_sensitive' => true,
            'version'      => 1,
        ]);

        // API cavabında mask olunmuş dəyər
        $this->assertEquals('********_123', $config->display_value);
    }

    /**
     * Config history test.
     */
    public function test_config_changes_are_tracked(): void
    {
        $configService = app(ConfigService::class);

        $configService->set('ui', 'theme', 'light', 'string', reason: 'Initial');
        $configService->set('ui', 'theme', 'dark', 'string', reason: 'User request');
        $configService->set('ui', 'theme', 'auto', 'string', reason: 'Default change');

        $config  = \App\Models\Configuration::where('group', 'ui')
            ->where('key', 'theme')
            ->first();

        $history = $config->history;

        $this->assertCount(3, $history);
        $this->assertEquals(3, $config->version);
        $this->assertEquals('auto', $config->value);
    }

    // Helper
    private function createAdmin(): \App\Models\User
    {
        return \App\Models\User::factory()->create(['role' => 'admin']);
    }
}
```

### Test Helper Trait

```php
<?php

namespace Tests\Traits;

trait WithConfigOverrides
{
    /**
     * Bir neçə config dəyərini eyni anda override et.
     */
    protected function withConfig(array $configs): static
    {
        foreach ($configs as $key => $value) {
            config([$key => $value]);
        }

        return $this;
    }

    /**
     * Bütün feature-ləri aktivləşdir (test üçün).
     */
    protected function withAllFeaturesEnabled(): static
    {
        $features = config('features', []);
        foreach ($features as $key => $value) {
            config(["features.{$key}" => true]);
        }

        return $this;
    }

    /**
     * Production config ilə test et.
     */
    protected function withProductionConfig(): static
    {
        config([
            'app.env'   => 'production',
            'app.debug' => false,
        ]);

        return $this;
    }

    /**
     * Müəyyən mühit ilə test et.
     */
    protected function withEnvironment(string $env): static
    {
        config(['app.env' => $env]);

        return $this;
    }
}

// İstifadə:
// class MyTest extends TestCase
// {
//     use WithConfigOverrides;
//
//     public function test_something(): void
//     {
//         $this->withConfig([
//             'services.stripe.secret' => 'sk_test_123',
//             'features.new_checkout'  => true,
//         ]);
//         ...
//     }
// }
```

---

## Yekun Qaydalar

```
Config Management Best Practices:
═══════════════════════════════════════════════════════════════

1. env() yalnız config/*.php fayllarında    │ config:cache ilə uyğunluq
2. Sensitive data → Vault/SSM/encrypted     │ Git-ə secret commit etmə
3. config:cache production-da MƏCBURI       │ Performans artımı
4. Config validation boot zamanı            │ Xətaları erkən tut
5. Feature toggles → dynamic config         │ Deploy olmadan idarə
6. Config versioning + rollback             │ Dəyişiklikləri izlə
7. Environment diff → config drift aşkarla  │ Staging ≠ Prod tapmaq
8. .env.example həmişə aktual saxla         │ Onboarding asanlaşır
9. Testlərdə config() override istifadə et  │ env() yox, config()
10. CI/CD-də config:validate əlavə et       │ Deploy-dan əvvəl yoxla

Anti-patterns:
──────────────────────────────────────────
✗ Hardcoded API keys / secrets
✗ env() birbaşa service/controller-da
✗ if (env('APP_ENV') === 'production') kod içində
✗ .env faylını git-ə commit etmək
✗ Config dəyişikliyi üçün deploy etmək (dynamic olmalı)
✗ Secret rotation üçün bütün serverlərdə manual dəyişmək
✗ Test-lərdə real API key istifadə etmək

Monitoring:
──────────────────────────────────────────
• Config dəyişiklik audit log-u (kim, nə, nə vaxt)
• Feature toggle aktivlik izləmə
• Secret rotation vaxtını izlə (expired secret alert)
• Config drift alertləri (staging vs production fərqi)
• Config cache yaşını izlə (köhnə cache xəbərdarlığı)
```
