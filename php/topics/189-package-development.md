# PHP Package Development & Composer SDK

## Mündəricat
1. [Niyə öz package?](#niyə-öz-package)
2. [Composer package strukturu](#composer-package-strukturu)
3. [composer.json deep](#composerjson-deep)
4. [Autoloading (PSR-4)](#autoloading-psr-4)
5. [Laravel package (Service Provider)](#laravel-package-service-provider)
6. [Symfony bundle](#symfony-bundle)
7. [Versiyalaşdırma (semver)](#versiyalaşdırma-semver)
8. [Publishing — Packagist](#publishing--packagist)
9. [GitHub Actions CI](#github-actions-ci)
10. [Private package (Composer Satis, Repman)](#private-package-composer-satis-repman)
11. [Testing tip-ləri](#testing-tip-ləri)
12. [İntervyu Sualları](#intervyu-sualları)

---

## Niyə öz package?

```
Use cases:
  - Internal SDK (microservice client library)
  - Reusable functionality (cross-project)
  - Open-source contribution
  - Plugin architecture
  - 3rd-party API wrapper
  - DSL / DSL helpers
  - UI component library

Niyə Laravel package olaraq fokus?
  Laravel ekosistemi qururlur:
    - Spatie (50+ package)
    - Filament, Nova, Livewire, Inertia
    - 100k+ open-source Laravel package
```

---

## Composer package strukturu

```
my-package/
├── composer.json           # metadata + autoloading
├── README.md
├── LICENSE
├── CHANGELOG.md
├── .gitignore
├── .gitattributes          # vendor/test export-ignore
├── .github/
│   └── workflows/
│       └── tests.yml
├── src/                    # main source code
│   ├── MyPackage.php
│   ├── Service.php
│   └── Exceptions/
├── tests/                  # unit/feature tests
│   ├── TestCase.php
│   └── Feature/
├── config/                 # publishable config
│   └── my-package.php
├── database/
│   └── migrations/
└── routes/
    └── web.php
```

---

## composer.json deep

```json
{
    "name": "vendor/my-package",
    "description": "Brief description (max ~100 char)",
    "type": "library",
    "license": "MIT",
    "keywords": ["laravel", "package", "tag1"],
    "homepage": "https://github.com/vendor/my-package",
    
    "authors": [
        {
            "name": "Ali Veliyev",
            "email": "ali@example.com",
            "homepage": "https://example.com",
            "role": "Developer"
        }
    ],
    
    "support": {
        "issues": "https://github.com/vendor/my-package/issues",
        "source": "https://github.com/vendor/my-package"
    },
    
    "require": {
        "php": "^8.2",
        "illuminate/support": "^10.0|^11.0"
    },
    
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "orchestra/testbench": "^8.0|^9.0",
        "phpstan/phpstan": "^1.10",
        "laravel/pint": "^1.0"
    },
    
    "autoload": {
        "psr-4": {
            "Vendor\\MyPackage\\": "src/"
        },
        "files": [
            "src/helpers.php"
        ]
    },
    
    "autoload-dev": {
        "psr-4": {
            "Vendor\\MyPackage\\Tests\\": "tests/"
        }
    },
    
    "extra": {
        "laravel": {
            "providers": [
                "Vendor\\MyPackage\\MyPackageServiceProvider"
            ],
            "aliases": {
                "MyPackage": "Vendor\\MyPackage\\Facades\\MyPackage"
            }
        }
    },
    
    "scripts": {
        "test": "vendor/bin/phpunit",
        "test:coverage": "vendor/bin/phpunit --coverage-html coverage",
        "analyse": "vendor/bin/phpstan analyse",
        "format": "vendor/bin/pint"
    },
    
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

---

## Autoloading (PSR-4)

```
PSR-4 — namespace-i qovluq strukturuna map edir.

namespace prefix:    Vendor\MyPackage\
base directory:      src/

Vendor\MyPackage\Service              → src/Service.php
Vendor\MyPackage\Http\Controller      → src/Http/Controller.php
Vendor\MyPackage\Exceptions\NotFound  → src/Exceptions/NotFound.php

Composer optimization:
  composer dump-autoload -o   # classmap + class file map
  Production-da MƏCBURI — autoloader 5-10× sürətlənir

Authoritative:
  composer dump-autoload --classmap-authoritative
  Yalnız classmap-da olan class-lar tapılır (yeni file əlavə → manual dump)
```

```php
<?php
// src/helpers.php — global function-lar
if (!function_exists('my_helper')) {
    function my_helper(string $input): string
    {
        return strtoupper($input);
    }
}

// composer.json autoload.files-də list
// Composer install zamanı YÜKLƏNİR (lazy yox)
```

---

## Laravel package (Service Provider)

```php
<?php
// src/MyPackageServiceProvider.php
namespace Vendor\MyPackage;

use Illuminate\Support\ServiceProvider;

class MyPackageServiceProvider extends ServiceProvider
{
    // Bind binding-lər (singletons və s.)
    public function register(): void
    {
        // Config merge
        $this->mergeConfigFrom(__DIR__.'/../config/my-package.php', 'my-package');
        
        // Singleton
        $this->app->singleton(MyService::class, function ($app) {
            return new MyService(
                config('my-package.api_key'),
                $app->make('cache')
            );
        });
        
        // Alias
        $this->app->alias(MyService::class, 'my-package.service');
    }
    
    // Bootstrap (kernel boot olunduqdan sonra)
    public function boot(): void
    {
        // Migration-ları load et
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
        // Route-ları load et
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        
        // View
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'my-package');
        
        // Translation
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'my-package');
        
        // Console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\InstallCommand::class,
            ]);
            
            // Publishable assets
            $this->publishes([
                __DIR__.'/../config/my-package.php' => config_path('my-package.php'),
            ], 'my-package-config');
            
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'my-package-migrations');
            
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/my-package'),
            ], 'my-package-views');
        }
        
        // Macro (extend existing class)
        Builder::macro('whereLike', function ($column, $value) {
            return $this->where($column, 'LIKE', "%{$value}%");
        });
        
        // Blade directive
        Blade::directive('myDirective', function ($expression) {
            return "<?php echo myFunction($expression); ?>";
        });
    }
}
```

```bash
# User publish edə bilər:
php artisan vendor:publish --tag=my-package-config
php artisan vendor:publish --tag=my-package-migrations
php artisan vendor:publish --provider="Vendor\MyPackage\MyPackageServiceProvider"
```

---

## Symfony bundle

```php
<?php
// src/MyPackageBundle.php
namespace Vendor\MyPackage;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class MyPackageBundle extends Bundle
{
    // Bundle struktur Symfony 7+ ilə minimal
}

// src/DependencyInjection/MyPackageExtension.php
namespace Vendor\MyPackage\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class MyPackageExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    }
}

// config/bundles.php (user app-də)
return [
    Vendor\MyPackage\MyPackageBundle::class => ['all' => true],
];
```

---

## Versiyalaşdırma (semver)

```
SemVer 2.0:
  MAJOR.MINOR.PATCH
  
  MAJOR: Breaking changes (1.0.0 → 2.0.0)
  MINOR: New features, backward compat (1.0.0 → 1.1.0)
  PATCH: Bug fix, backward compat (1.0.0 → 1.0.1)

Pre-release:
  1.0.0-alpha.1
  1.0.0-beta.2
  1.0.0-rc.1

Composer constraints:
  ^1.2.3   1.2.3 <= x < 2.0.0   (backward compat in major)
  ~1.2.3   1.2.3 <= x < 1.3.0   (patch only)
  >=1.2    1.2.0+
  1.*      1.0.0 <= x < 2.0.0
  
Laravel package convention:
  - Major Laravel uyumlu: ^10.0|^11.0
  - PHP version: ^8.2 (minimum dəstəklədiyiniz)
  - Drop old versions yalnız MAJOR upgrade-də

Tag git-də:
  git tag v1.0.0
  git push --tags
  Composer Packagist-dən tag-ı oxuyur
```

---

## Publishing — Packagist

```
1. GitHub-da public repository
2. composer.json hazırda olsun
3. Packagist.org-da hesab aç
4. "Submit Package" → GitHub URL
5. Webhook qur (auto-update on push)
6. Version: git tag → Packagist görür

Composer install:
  composer require vendor/my-package
  composer require vendor/my-package:^1.0

Yenidən publish (yeni version):
  CHANGELOG.md update
  composer.json version bump (lazım deyil — git tag oxunur)
  git tag v1.1.0
  git push --tags
  Packagist auto-update via webhook
```

---

## GitHub Actions CI

```yaml
# .github/workflows/tests.yml
name: tests
on:
  push:
    branches: [main, develop]
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['8.2', '8.3']
        laravel: ['10.*', '11.*']
        dependency-version: [prefer-lowest, prefer-stable]
        exclude:
          - laravel: 11.*
            php: 8.1
    
    name: PHP ${{ matrix.php }} - Laravel ${{ matrix.laravel }} - ${{ matrix.dependency-version }}
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: pcov
          tools: composer:v2
      
      - name: Install dependencies
        run: |
          composer require "illuminate/support:${{ matrix.laravel }}" --no-update
          composer update --${{ matrix.dependency-version }} --prefer-dist --no-interaction
      
      - name: Run tests
        run: vendor/bin/phpunit --coverage-text
      
      - name: Run static analysis
        run: vendor/bin/phpstan analyse --no-progress
```

---

## Private package (Composer Satis, Repman)

```
Private package yayımlama yolları:

1. SATIS (open source, simple)
   - JSON-based static repository
   - GitHub repos → static index
   - Self-host

2. REPMAN
   - Modern Satis alternative
   - Web UI, auth
   - Self-host (Docker)

3. PRIVATE PACKAGIST (paid SaaS)
   - Packagist.com
   - Tightly integrated GitHub/GitLab/Bitbucket
   - Mirror public packages

4. GITHUB PRIVATE (composer üçün)
   composer.json:
   "repositories": [
     {
       "type": "vcs",
       "url": "git@github.com:vendor/private-package.git"
     }
   ]
   "require": {
     "vendor/private-package": "^1.0"
   }
   # Auth: SSH key və ya GITHUB_TOKEN
```

---

## Testing tip-ləri

```php
<?php
// Orchestra Testbench — Laravel package testing
// tests/TestCase.php
namespace Vendor\MyPackage\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Vendor\MyPackage\MyPackageServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MyPackageServiceProvider::class];
    }
    
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
    }
}

// tests/Feature/ExampleTest.php
class ExampleTest extends TestCase
{
    public function test_service_works(): void
    {
        $service = app(MyService::class);
        $this->assertInstanceOf(MyService::class, $service);
    }
}

// Pest:
// tests/Pest.php
uses(Tests\TestCase::class)->in('Feature');
```

---

## İntervyu Sualları

- PSR-4 autoloading necə işləyir?
- `composer dump-autoload -o` nə edir?
- Laravel package-də Service Provider-in `register` və `boot` arasında fərq?
- `mergeConfigFrom` vs `publishes` — hansı nə vaxt?
- Composer-də `^1.2` vs `~1.2` fərqi?
- Package versioning üçün SemVer qaydaları nədir?
- Laravel auto-discovery `extra.laravel` ilə necə işləyir?
- Orchestra Testbench niyə Laravel package testing üçün lazımdır?
- Private package üçün hansı seçimlər var?
- GitHub Actions matrix testing nə üçündür?
- `prefer-lowest` vs `prefer-stable` test fərqi?
- Composer scripts (test, format) niyə yazılır?
