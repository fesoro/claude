# =============================================================================
# Dockerfile — Laravel tətbiqi üçün PHP 8.3-FPM image-i
# =============================================================================
#
# Dockerfile nədir?
# -----------------
# Dockerfile — Docker image-i qurmaq üçün təlimatlar faylıdır.
# Hər sətir bir "qat" (layer) yaradır. Docker bu qatları keşləyir,
# dəyişməyən qatlar yenidən qurulmur — bu, qurma prosesini sürətləndirir.
#
# PHP-FPM nədir?
# ---------------
# PHP-FPM (FastCGI Process Manager) — PHP-nin performanslı icra mühitidir.
# Nginx kimi veb serverlər PHP sorğularını FPM-ə ötürür.
# FPM prosesləri idarə edir və yüksək yük altında effektiv işləyir.
# =============================================================================

# Əsas image — PHP 8.3 FPM versiyası
# "FROM" təlimatı hansı əsas image üzərində qurulacağını göstərir
FROM php:8.3-fpm

# -------------------------------------------------------
# Sistem asılılıqlarının quraşdırılması
# -------------------------------------------------------
# "RUN" təlimatı konteynerin içində əmr icra edir
# Hər RUN yeni qat yaradır, ona görə əmrləri birləşdiririk (&&)
# Bu, image ölçüsünü kiçik saxlayır
#
# Quraşdırılan paketlər:
#   - git: Composer paketlərini yükləmək üçün (bəzi paketlər git repo-dan gəlir)
#   - curl: HTTP sorğuları göndərmək üçün (Composer yükləmə və s.)
#   - libpng-dev: PNG şəkil emalı üçün (GD kitabxanası)
#   - libonig-dev: mbstring genişlənməsi üçün (çoxbaytlı simvol dəstəyi)
#   - libxml2-dev: XML emalı üçün (SOAP, XML parsing)
#   - libzip-dev: ZIP arxiv emalı üçün (Composer paketləri ZIP formatında gəlir)
#   - zip, unzip: Arxivlərlə işləmək üçün əmr sətri alətləri
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    # Quraşdırmadan sonra keş fayllarını silirik — image ölçüsünü azaldır
    && docker-php-ext-install \
        pdo_mysql \
        bcmath \
        sockets \
        zip \
        xml \
        mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# -------------------------------------------------------
# PHP genişlənmələri haqqında:
# -------------------------------------------------------
#   - pdo_mysql:  MySQL verilənlər bazasına qoşulmaq üçün (PDO drayver)
#   - bcmath:     Dəqiq riyazi hesablamalar üçün (qiymət, ödəniş hesablamaları)
#   - sockets:    RabbitMQ ilə əlaqə üçün (AMQP protokolu soketlər istifadə edir)
#   - zip:        ZIP arxivlərlə işləmək üçün (paket idarəetməsi)
#   - xml:        XML sənədləri emal etmək üçün (API cavabları, SOAP və s.)
#   - mbstring:   Çoxbaytlı simvollar üçün (UTF-8 dəstəyi, Azərbaycan hərfləri)

# -------------------------------------------------------
# Composer quraşdırılması
# -------------------------------------------------------
# Composer — PHP-nin paket idarəetmə alətidir (npm-in PHP versiyası).
# "COPY --from" çoxmərhələli qurma (multi-stage build) texnikasıdır.
# Rəsmi Composer image-indən yalnız composer icra faylını götürürük.
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# -------------------------------------------------------
# İşçi qovluğun təyini
# -------------------------------------------------------
# "WORKDIR" — sonrakı bütün əmrlər bu qovluqda icra olunacaq.
# Həmçinin, konteynerə daxil olanda (docker exec) bu qovluqda olacaqsınız.
WORKDIR /var/www/html

# -------------------------------------------------------
# Tətbiq fayllarının kopyalanması
# -------------------------------------------------------
# "COPY" — host maşındakı faylları konteynerin içinə kopyalayır.
# "." (birinci) — host-dakı cari qovluq (build context)
# "." (ikinci) — konteynerdəki WORKDIR (/var/www/html)
COPY . .

# -------------------------------------------------------
# Composer asılılıqlarının quraşdırılması
# -------------------------------------------------------
# --no-interaction: İnteraktiv suallara "xeyr" cavab verir
# --no-dev: İstehsal (production) üçün lazım olmayan paketləri quraşdırmır
# --optimize-autoloader: Sinif yükləmə xəritəsini optimallaşdırır (sürət artır)
# Qeyd: İnkişaf mühitində volume mount kodu əvəz edəcək, buna görə
# entrypoint.sh-da yenidən composer install işlədilir
RUN composer install --no-interaction --no-dev --optimize-autoloader || true

# -------------------------------------------------------
# Fayl icazələrinin tənzimlənməsi
# -------------------------------------------------------
# Laravel storage və cache qovluqlarına yazma icazəsi lazımdır.
# www-data — PHP-FPM-in standart istifadəçisidir.
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

# -------------------------------------------------------
# PHP-FPM-in işə salınması
# -------------------------------------------------------
# "EXPOSE" — sənədləşdirmə məqsədlidir, port 9000-in istifadə olunduğunu bildirir
# "CMD" — konteyner başlayanda icra olunacaq standart əmr
EXPOSE 9000
CMD ["php-fpm"]
