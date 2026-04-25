## Project init

composer init — interaktiv layihə yarad
composer init --name=vendor/project --type=library --require="php:^8.2"
composer create-project laravel/laravel myapp — skeleton yükle
composer create-project laravel/laravel myapp "^11.0"

## Install / update

composer install — composer.lock-a görə dəqiq versiyaları yüklə
composer install --no-dev — dev dependency-ləri skip et (prod)
composer install --no-interaction — CI/CD üçün
composer install --prefer-dist — zip arxiv (sürətli)
composer install --prefer-source — git clone (dev, patch üçün)
composer install --optimize-autoloader — prod autoloader
composer install --no-scripts — post-install skriptlər olmadan
composer update — bütün paketləri yenilə (composer.lock yenilənir)
composer update vendor/package — konkret paketi yenilə
composer update --with-all-dependencies — tranzitiv asılılıqları da yenilə
composer update --dry-run — nə dəyişəcəyini göstər
composer upgrade — update alias

## Require / remove

composer require vendor/package
composer require vendor/package:^2.0
composer require vendor/package --dev — dev dependency
composer require vendor/package --no-update — yalnız composer.json-a yaz
composer remove vendor/package
composer remove vendor/package --dev

## Autoload

composer dump-autoload — autoload fayllarını yenilə
composer dump-autoload --optimize — prod üçün classmap (sürətli)
composer dump-autoload -o — --optimize alias
composer dump-autoload --classmap-authoritative — yalnız classmap (no fallback)
composer dump-autoload --apcu — APCu cache

## Show / inspect

composer show — yüklənmiş bütün paketlər
composer show --installed — yüklənmiş
composer show vendor/package — paket detalları
composer show --tree — dependency tree
composer show -t — --tree alias
composer show --outdated — köhnə versiyaları göstər
composer show -o — --outdated alias
composer show --platform — PHP və extension versiyaları
composer show --self — current project
composer info vendor/package

## Search

composer search keyword — packagist-də axtar
composer search --only-name keyword

## Validate / diagnose

composer validate — composer.json doğrula
composer validate --strict
composer diagnose — ümumi problemlər yoxla
composer check-platform-reqs — platform requirements yoxla

## Scripts

composer run-script test — script çalışdır
composer run test — qısa alias
# composer.json-da:
# "scripts": {
#   "test": "phpunit",
#   "post-install-cmd": [...],
#   "post-update-cmd": [...]
# }

## Config

composer config --list — bütün config
composer config --global --list — global config
composer config name value — layihə config
composer config --global name value — global config
composer config --unset name
composer config repositories.local '{"type": "path", "url": "../local-pkg"}'
composer config --global preferred-install dist
composer config --global process-timeout 300
composer config minimum-stability dev
composer config prefer-stable true

## Authentication / credentials

composer config --global http-basic.repo.example.com user pass
composer config --global bearer.repo.example.com token
composer config --auth — auth.json göstər

## Lock file

composer update --lock — yalnız composer.lock-u yenilə (hash)
composer install —  lock-dan yüklə (CI-da istifadə et)
# composer.lock həmişə commit olunmalıdır (application)
# library-lərdə composer.lock .gitignore-a əlavə edilir

## Versions / constraints

^1.2.3 → >=1.2.3 <2.0.0 (major sabit)
~1.2.3 → >=1.2.3 <1.3.0 (minor sabit)
1.2.* → 1.2.x
>=1.0 <2.0
1.0 - 2.0 → >=1.0.0 <2.1.0
@dev — dev stability
dev-main — branch adı

## Self update / global

composer self-update — Composer-i yenilə
composer self-update --rollback — geri qayıt
composer global require vendor/package — global yüklə
composer global update — global paketlər yenilə
composer global show — global paketlər

## Packagist / repositories

# composer.json-da custom repo:
# "repositories": [
#   {"type": "composer", "url": "https://satis.example.com"},
#   {"type": "vcs", "url": "https://github.com/user/repo"},
#   {"type": "path", "url": "../local-package"}
# ]

## Optimization (prod)

composer install --no-dev --optimize-autoloader --no-interaction
composer install --no-dev -o --classmap-authoritative
# classmap-authoritative: yalnız classmap, PSR-4 fallback yox (daha sürətli, amma yeni class-lar scan edilmir)
# apcu: APCu cache ilə classmap cache-lər

## Environment variables

COMPOSER_HOME — global config direktori (~/.composer)
COMPOSER_VENDOR_DIR — vendor direktori override
COMPOSER_NO_INTERACTION=1 — CI üçün
COMPOSER_MEMORY_LIMIT=-1 — memory limit deaktiv
COMPOSER_PROCESS_TIMEOUT=600 — timeout artır
COMPOSER_AUTH — JSON formatında auth credentials

## Troubleshooting

composer diagnose — ümumi problemlər
composer clear-cache — composer cache sil
composer install --no-cache
composer why vendor/package — niyə yüklənib
composer why-not vendor/package ^2.0 — niyə yüklənə bilmir
composer prohibits vendor/package ^2.0 — hansı paket bloklayır
composer depends vendor/package — kim bu paketi require edir
