# Architecture və Deployment

## 1. Monolith vs Microservices — nə vaxt hansı?

**Monolith (Laravel):**
- Kiçik/orta komanda (2-10 developer)
- MVP və startup mərhələsi
- Deploy sadədir
- Debugging asandır
- Laravel bunu çox yaxşı edir

**Microservices:**
- Böyük komanda (10+ developer)
- Müxtəlif hissələr müxtəlif scale tələb edir
- Müxtəlif texnologiya stack-ləri lazımdır
- Operational complexity çox yüksəkdir

**Hybrid yanaşma:**
- Monolith-dən başla
- Lazım olduqca xüsusi servisləri ayır (payment, notification, search)
- Laravel + microservices birlikdə

---

## 2. Laravel-də Domain-Driven Design (DDD)

```
app/
├── Domain/                    # Business logic
│   ├── Order/
│   │   ├── Models/
│   │   │   └── Order.php
│   │   ├── Actions/
│   │   │   ├── PlaceOrderAction.php
│   │   │   └── CancelOrderAction.php
│   │   ├── DTOs/
│   │   │   └── PlaceOrderDTO.php
│   │   ├── Events/
│   │   │   └── OrderPlaced.php
│   │   ├── Exceptions/
│   │   │   └── InsufficientStockException.php
│   │   └── Repositories/
│   │       └── OrderRepositoryInterface.php
│   └── User/
│       └── ...
├── App/                       # Application layer (HTTP, Console)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── OrderController.php
│   │   └── Requests/
│   │       └── PlaceOrderRequest.php
│   └── Console/
└── Infrastructure/            # External services implementation
    ├── Repositories/
    │   └── EloquentOrderRepository.php
    ├── Payment/
    │   └── StripePaymentGateway.php
    └── Notification/
        └── FirebasePushNotification.php
```

```php
// Action class — bir business əməliyyat
class PlaceOrderAction {
    public function __construct(
        private OrderRepositoryInterface $orders,
        private PaymentGateway $payment,
        private InventoryService $inventory,
    ) {}

    public function execute(PlaceOrderDTO $dto): Order {
        $this->inventory->ensureAvailable($dto->items);

        $order = $this->orders->create($dto);
        $this->payment->charge($order);

        OrderPlaced::dispatch($order);

        return $order;
    }
}
```

---

## 3. Docker ilə Laravel deployment

```dockerfile
# Dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    libpng-dev libjpeg-turbo-dev \
    && docker-php-ext-install pdo_mysql gd opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
```

```yaml
# docker-compose.yml
services:
  app:
    build: .
    volumes:
      - ./storage:/var/www/html/storage
    depends_on:
      - mysql
      - redis
    environment:
      DB_HOST: mysql
      REDIS_HOST: redis

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: app
      MYSQL_ROOT_PASSWORD: secret
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:alpine

  queue:
    build: .
    command: php artisan queue:work --tries=3 --timeout=90
    depends_on:
      - mysql
      - redis

  scheduler:
    build: .
    command: sh -c "while true; do php artisan schedule:run; sleep 60; done"
    depends_on:
      - mysql

volumes:
  mysql_data:
```

---

## 4. CI/CD Pipeline

```yaml
# .github/workflows/ci.yml
name: CI/CD

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: testing
          MYSQL_ROOT_PASSWORD: password
        ports: ['3306:3306']

    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo_mysql, redis

      - run: composer install --prefer-dist --no-progress
      - run: cp .env.testing .env
      - run: php artisan key:generate

      - name: Run Tests
        run: php artisan test --parallel --coverage-min=80
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          DB_PASSWORD: password

      - name: Static Analysis
        run: vendor/bin/phpstan analyse

      - name: Code Style
        run: vendor/bin/pint --test

  deploy:
    needs: test
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - name: Deploy
        run: |
          # Forge, Envoyer, və ya custom deploy
          curl -X POST ${{ secrets.DEPLOY_WEBHOOK }}
```

---

## 5. Zero-Downtime Deployment

```bash
# Laravel Envoyer / Deployer strategiyası
# 1. Yeni release qovluğu yarat
# 2. Kodu çək
# 3. Composer install
# 4. Migrate
# 5. Cache clear & rebuild
# 6. Symlink dəyiş (atomic operation)
# 7. Queue restart
# 8. OPcache reset

# Deployer misal
# deploy.php
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'artisan:migrate',
    'artisan:cache:clear',
    'artisan:config:cache',
    'artisan:route:cache',
    'deploy:symlink',        // Atomic switch
    'artisan:queue:restart',
]);
```

---

## 6. Environment Management

```php
// .env faylları
.env                  // Local development
.env.testing          // Test environment
.env.staging          // Staging
.env.production       // Production (server-da, git-də yox!)

// Config caching — production-da mütləq
php artisan config:cache
// Bundan sonra env() yalnız config fayllarında işləyir
// Kodda config('app.name') istifadə et, env('APP_NAME') yox!

// Feature flags
if (config('features.new_checkout')) {
    // yeni checkout
}
```

---

## 7. Logging və Monitoring

```php
// Structured logging
Log::channel('slack')->critical('Payment gateway down', [
    'gateway' => 'stripe',
    'error' => $exception->getMessage(),
    'affected_orders' => $pendingOrders->count(),
]);

// Custom log channel
// config/logging.php
'channels' => [
    'payments' => [
        'driver' => 'daily',
        'path' => storage_path('logs/payments.log'),
        'days' => 30,
    ],
],

// Context
Log::withContext(['request_id' => Str::uuid()]);

// External monitoring: Sentry, Bugsnag, Datadog
// composer require sentry/sentry-laravel
Sentry\init(['dsn' => env('SENTRY_DSN')]);
```
