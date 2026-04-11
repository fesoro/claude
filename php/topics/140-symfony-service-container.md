# Symfony Service Container

## Mündəricat
1. [Container nədir?](#container-nədir)
2. [Service Definition](#service-definition)
3. [Compiler Passes](#compiler-passes)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Container nədir?

```
Symfony Service Container — Dependency Injection Container.
Obyektlər arasındakı dependency-ləri idarə edir.

Əsas konseptlər:
  Service:     Container tərəfindən idarə olunan obyekt
  Definition:  Service-in necə yaradılacağı (class, args, calls)
  Autowiring:  Constructor type-hint-ə görə avtomatik inject
  Autoconfigure: Interface-ə görə avtomatik tag

Compile:
  Symfony container "compile" edilir:
    Services.yaml/annotations → PHP kod → cache
    Production-da hər dəfə yenidən yaradılmır
    
  ContainerBuilder → compile() → PhpDumper → cached_container.php

Scopes (PHP 8 / Symfony 6+):
  Singleton (default): bütün request boyu eyni instance
  Prototype: hər inject-də yeni instance (shared: false)
```

---

## Service Definition

```yaml
# services.yaml

services:
  # Default ayarlar — bütün servislərə tətbiq edilir
  _defaults:
    autowire: true      # Constructor type-hint ilə avtomatik inject
    autoconfigure: true # Interface-ə görə tag
    public: false       # Container-dən direkt çıxış yoxdur

  # Bütün App\ namespace-ini service kimi qeydiyyat
  App\:
    resource: '../src/'
    exclude:
      - '../src/Entity/'
      - '../src/Migrations/'

  # Manual definition
  App\Service\PaymentService:
    arguments:
      $apiKey: '%env(STRIPE_API_KEY)%'
      $mode:   'production'
    tags:
      - { name: 'app.payment_provider', priority: 10 }

  # Abstract service (template)
  App\Repository\AbstractRepository:
    abstract: true
    arguments: ['@doctrine.orm.entity_manager']

  # Alias
  App\Contract\PaymentInterface: '@App\Service\StripePaymentService'
```

---

## Compiler Passes

```
Compiler Pass — container compile zamanı servisleri manipulyasiya edir.

İstifadə halları:
  - Tag-ləri collect et, chain yarat
  - Dynamic service definition
  - Validation (lazımi servislər mövcuddur?)

Nümunə: Tagged Payment Providers toplama
  1. Hər payment provider-ı tag et
  2. CompilerPass: tag-ləri topla, PaymentRegistry-ə inject et
  3. Result: PaymentRegistry bütün provider-ları bilir

Addımlar:
  registerForAutoconfiguration → Interface-ə tag əlavə et
  addCompilerPass             → CompilerPass qeydiyyat
  process($container)         → Servicləri manipulyasiya et
```

---

## PHP İmplementasiyası

```php
<?php
// 1. Interface + Tag ilə Payment Provider system

// Interface
namespace App\Contract;
interface PaymentProviderInterface
{
    public function supports(string $method): bool;
    public function charge(float $amount, string $currency): PaymentResult;
    public function getName(): string;
}

// Concrete providers
namespace App\Payment;

#[AsTaggedItem('payment.provider', priority: 10)]
class StripePaymentProvider implements PaymentProviderInterface
{
    public function supports(string $method): bool { return $method === 'card'; }
    public function getName(): string { return 'stripe'; }
    // ...
}

#[AsTaggedItem('payment.provider', priority: 5)]
class PaypalPaymentProvider implements PaymentProviderInterface
{
    public function supports(string $method): bool { return $method === 'paypal'; }
    public function getName(): string { return 'paypal'; }
    // ...
}

// Registry
class PaymentRegistry
{
    /** @param PaymentProviderInterface[] $providers */
    public function __construct(
        private iterable $providers, // Tagged Iterator inject
    ) {}

    public function getFor(string $method): PaymentProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($method)) {
                return $provider;
            }
        }
        throw new UnsupportedPaymentMethodException($method);
    }
}
```

```yaml
# services.yaml — Tagged Iterator
App\Payment\PaymentRegistry:
  arguments:
    $providers: !tagged_iterator 'payment.provider'
```

```php
<?php
// 2. Custom Compiler Pass
namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class PaymentProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(PaymentRegistry::class)) {
            return;
        }

        $registry = $container->findDefinition(PaymentRegistry::class);
        $taggedServices = $container->findTaggedServiceIds('payment.provider');

        $providers = [];
        foreach ($taggedServices as $id => $tags) {
            $priority   = $tags[0]['priority'] ?? 0;
            $providers[] = ['ref' => new Reference($id), 'priority' => $priority];
        }

        // Priority-ə görə sırala
        usort($providers, fn($a, $b) => $b['priority'] <=> $a['priority']);

        $registry->setArgument(
            '$providers',
            array_column($providers, 'ref')
        );
    }
}

// Kernel-ə qeydiyyat
class AppKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PaymentProviderPass());

        // Autoconfigure: Interface implement edən hər sinif tag alır
        $container->registerForAutoconfiguration(PaymentProviderInterface::class)
            ->addTag('payment.provider');
    }
}
```

```php
<?php
// 3. Service Decoration (Decorator pattern)
namespace App\DependencyInjection;

// Orijinal service
class UserRepository implements UserRepositoryInterface { /* ... */ }

// Decorator
class CachedUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private UserRepositoryInterface $inner, // Orijinala istinad
        private CacheInterface $cache,
    ) {}

    public function findById(string $id): ?User
    {
        return $this->cache->get("user:{$id}", function() use ($id) {
            return $this->inner->findById($id);
        });
    }
}
```

```yaml
# services.yaml — Decoration
App\Repository\CachedUserRepository:
  decorates: App\Repository\UserRepository
  # 'inner' avtomatik inject edilir
```

---

## İntervyu Sualları

- Symfony Container "compile" edilmə nə deməkdir?
- Autowiring necə işləyir? Konflikti necə həll edirsiniz?
- Compiler Pass nəyə lazımdır?
- Tagged services nədir? Real nümunə verin.
- Service decoration pattern Container-da necə tətbiq edilir?
- `shared: false` nə zaman istifadə edilir?
