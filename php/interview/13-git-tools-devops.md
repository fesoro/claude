# Git, Alətlər və DevOps (Middle)

## 1. Git — Senior Developer üçün vacib biliklər

```bash
# Branching strategiyası — Git Flow
main        # production kodu
develop     # development branch
feature/*   # yeni xüsusiyyətlər
hotfix/*    # production bugfix
release/*   # release hazırlığı

# Rebase vs Merge
git merge feature    # merge commit yaradır, tarix qorunur
git rebase main      # commit-ləri köçürür, təmiz tarix

# Interactive rebase — commit-ləri düzəlt
git rebase -i HEAD~3   # son 3 commit-i redaktə et
# squash, fixup, reword, edit, drop

# Cherry-pick — bir commit-i başqa branch-a köçür
git cherry-pick abc123

# Stash — dəyişiklikləri müvəqqəti saxla
git stash push -m "WIP: payment feature"
git stash list
git stash pop

# Bisect — bug-ın hansı commit-də yarandığını tap
git bisect start
git bisect bad HEAD
git bisect good v1.0
# Git avtomatik yoxlayacaq

# Reflog — itirilmiş commit-ləri tap
git reflog
git checkout HEAD@{5}
```

---

## 2. Composer — PHP dependency manager

```bash
# composer.json vs composer.lock
# composer.json — versiya range (^8.0)
# composer.lock — exact versiyalar (git-ə commit et!)

# Install vs Update
composer install    # .lock faylından exact versiyaları quraşdır
composer update     # .json-a görə yenilə və .lock yenidən yarat

# Autoloading
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Domain\\": "domain/"
    }
}

# Scripts
"scripts": {
    "post-autoload-dump": ["@php artisan package:discover"],
    "test": "php artisan test",
    "analyse": "phpstan analyse",
    "format": "pint"
}

# Private packages
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:company/private-package.git"
    }
]
```

---

## 3. PHP Alətləri

```bash
# PHPStan / Larastan — static analysis
# phpstan.neon
parameters:
    level: 8     # ən yüksək səviyyə
    paths:
        - app
        - domain

# Laravel Pint — code style (PSR-12)
./vendor/bin/pint

# PHP CS Fixer
./vendor/bin/php-cs-fixer fix

# PHPUnit / Pest
php artisan test
php artisan test --parallel    # paralel testlər
php artisan test --coverage    # code coverage

# Infection — mutation testing
./vendor/bin/infection --min-msi=80
```

---

## 4. Linux / Server əsasları

```bash
# Process management
ps aux | grep php
kill -9 <PID>
top / htop

# Log analizi
tail -f /var/log/nginx/error.log
tail -f storage/logs/laravel.log
journalctl -u php-fpm -f

# Permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data /var/www/app

# Crontab
crontab -e
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1

# Nginx config əsasları
server {
    listen 80;
    server_name example.com;
    root /var/www/app/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 5. Redis əsasları

```bash
# Data structures
SET key "value"            # String
GET key

HSET user:1 name "Orxan"  # Hash
HGET user:1 name
HGETALL user:1

LPUSH queue:emails "job1"  # List (queue)
RPOP queue:emails

SADD tags:post:1 "php"    # Set
SMEMBERS tags:post:1

ZADD leaderboard 100 "user:1"  # Sorted Set
ZRANGE leaderboard 0 -1 WITHSCORES

# TTL
SETEX session:abc 3600 "data"  # 1 saat TTL
TTL session:abc

# Pub/Sub (Broadcasting)
SUBSCRIBE channel:chat
PUBLISH channel:chat "hello"
```

```php
// Laravel-də Redis
use Illuminate\Support\Facades\Redis;

Redis::set('key', 'value');
Redis::get('key');

// Pipeline — toplu əməliyyat (daha sürətli)
Redis::pipeline(function ($pipe) {
    for ($i = 0; $i < 1000; $i++) {
        $pipe->set("key:$i", "value:$i");
    }
});

// Lua script — atomic əməliyyat
Redis::eval("
    local current = redis.call('get', KEYS[1])
    if current and tonumber(current) >= tonumber(ARGV[1]) then
        return redis.call('decrby', KEYS[1], ARGV[1])
    end
    return nil
", 1, 'stock:product:1', 5);
```
