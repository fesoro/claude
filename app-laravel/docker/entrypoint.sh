#!/bin/bash
# =============================================================================
# Entrypoint Skripti — Konteyner başlanğıc nöqtəsi
# =============================================================================
#
# Entrypoint nədir?
# -----------------
# Entrypoint — Docker konteyneri işə düşəndə İLK icra olunan skriptdir.
# Dockerfile-dakı CMD əmrindən fərqli olaraq, entrypoint həmişə işləyir
# və CMD-ni arqument kimi qəbul edə bilər.
#
# Bu skript nə edir?
# ------------------
# 1. MySQL-in hazır olmasını gözləyir (konteyner işə düşsə belə, MySQL
#    daxilən hələ hazır olmaya bilər)
# 2. Laravel konfiqurasiyasını yoxlayır (.env faylı, açar)
# 3. Miqrasiyaları icra edir (verilənlər bazası cədvəllərini yaradır)
# 4. PHP-FPM-i işə salır
#
# Niyə MySQL-i gözləyirik?
# ------------------------
# Docker Compose "depends_on" yalnız konteynerin İŞƏ DÜŞMƏYINI gözləyir,
# konteynerin İSTİFADƏYƏ HAZIR olmasını yox. MySQL konteyneri işə düşə bilər,
# amma daxili inisializasiya hələ davam edə bilər. Ona görə port yoxlaması edirik.
# =============================================================================

# Xəta baş verdikdə skripti dayandır
set -e

# -------------------------------------------------------
# MySQL-in hazır olmasını gözləyən funksiya
# -------------------------------------------------------
# "mysqladmin ping" əmri MySQL serverinin cavab verib-vermədiyini yoxlayır.
# Hər 3 saniyədən bir yoxlayırıq, maksimum 30 cəhd (90 saniyə).
# Bu, "wait-for-it" yanaşması adlanır.
wait_for_mysql() {
    echo "MySQL-in hazır olması gözlənilir..."
    # Sayğac — maksimum cəhd sayı
    max_tries=30
    # Cari cəhd
    counter=0

    # MySQL cavab verənə qədər davam et
    while ! mysqladmin ping -h"mysql" -u"root" -p"secret" --silent 2>/dev/null; do
        counter=$((counter + 1))
        if [ $counter -gt $max_tries ]; then
            echo "XƏTA: MySQL ${max_tries} cəhddən sonra hazır olmadı!"
            echo "MySQL konteynerinin vəziyyətini yoxlayın: docker compose logs mysql"
            exit 1
        fi
        echo "MySQL hələ hazır deyil... Cəhd: ${counter}/${max_tries}"
        # 3 saniyə gözlə və yenidən yoxla
        sleep 3
    done
    echo "MySQL hazırdır!"
}

# -------------------------------------------------------
# Əsas proses
# -------------------------------------------------------

echo "========================================="
echo "  DDD E-Commerce — Konteyner başladılır"
echo "========================================="

# Addım 1: MySQL-i gözlə
wait_for_mysql

# Addım 2: Composer asılılıqlarını quraşdır
# Volume mount Dockerfile-dakı COPY-ni əvəz edir, ona görə burada
# yenidən quraşdırma lazımdır
echo "Composer asılılıqları quraşdırılır..."
if [ -f "composer.json" ]; then
    composer install --no-interaction
else
    echo "XƏBƏRDARLIQ: composer.json tapılmadı. Laravel quraşdırılmayıb?"
fi

# Addım 3: .env faylını yoxla
# Əgər .env yoxdursa, .env.docker faylından kopyala
if [ ! -f ".env" ]; then
    echo ".env faylı tapılmadı. .env.docker faylından kopyalanır..."
    if [ -f ".env.docker" ]; then
        cp .env.docker .env
    elif [ -f ".env.example" ]; then
        cp .env.example .env
    fi
fi

# Addım 4: Tətbiq açarını yarat (əgər yoxdursa)
# Laravel şifrələmə üçün unikal açar istifadə edir (APP_KEY)
if [ -f "artisan" ]; then
    # APP_KEY boşdursa yeni açar yarat
    if grep -q "APP_KEY=$" .env 2>/dev/null || grep -q "APP_KEY=\"\"" .env 2>/dev/null; then
        echo "Tətbiq açarı yaradılır..."
        php artisan key:generate
    fi

    # Addım 5: Keşi təmizlə
    echo "Konfiqurasiya keşi təmizlənir..."
    php artisan config:clear
    php artisan cache:clear

    # Addım 6: Miqrasiyaları icra et
    # --force: istehsal mühitində belə icra et (interaktiv sual verməsin)
    # Hər verilənlər bazası üçün ayrı miqrasiya ola bilər
    echo "Verilənlər bazası miqrasiyaları icra olunur..."
    php artisan migrate --force || echo "XƏBƏRDARLIQ: Miqrasiyalar icra oluna bilmədi. Əl ilə yoxlayın."
fi

echo "========================================="
echo "  Konteyner hazırdır! PHP-FPM başladılır."
echo "========================================="

# Addım 7: PHP-FPM-i işə sal
# "exec" əmri cari prosesi PHP-FPM ilə əvəz edir.
# Bu vacibdir çünki Docker ƏSAS PROSESİ (PID 1) izləyir.
# "exec" olmadan bash PID 1 olardı və Docker PHP-FPM-in vəziyyətini bilməzdi.
# PHP-FPM dayandıqda Docker konteyneri dayandığını başa düşür.
exec php-fpm
