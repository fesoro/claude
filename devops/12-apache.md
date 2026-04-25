# Apache HTTP Server (Middle)

## Nədir? (What is it?)

Apache HTTP Server (httpd) dünyanın ən köhnə və ən çox istifadə olunan web server-lərindən biridir. Process-based arxitekturaya sahibdir, .htaccess faylları ilə qovluq səviyyəsində konfiqurasiya dəstəkləyir. Shared hosting mühitlərində hələ də geniş istifadə olunur. Laravel-i həm Apache, həm Nginx ilə deploy etmək mümkündür.

## Əsas Konseptlər (Key Concepts)

### Quraşdırma və Əsas Əmrlər

```bash
# Ubuntu/Debian
sudo apt update && sudo apt install apache2

# CentOS/RHEL
sudo yum install httpd

# Əmrlər
sudo systemctl start apache2
sudo systemctl stop apache2
sudo systemctl restart apache2
sudo systemctl reload apache2
sudo systemctl enable apache2

# Konfiqurasiya test
sudo apachectl configtest
sudo apache2ctl -t

# Versiya
apache2 -v
httpd -v            # CentOS

# Yüklənmiş modullar
apache2ctl -M
apachectl -M
```

### Konfiqurasiya Strukturu

```bash
# Ubuntu/Debian
/etc/apache2/
├── apache2.conf           # Əsas konfiqurasiya
├── ports.conf             # Listen portları
├── envvars                # Environment dəyişənləri
├── sites-available/       # Virtual host konfiqurasiyaları
│   ├── 000-default.conf
│   └── laravel.conf
├── sites-enabled/         # Aktiv site-lar (symlink)
├── mods-available/        # Mövcud modullar
├── mods-enabled/          # Aktiv modullar
└── conf-available/        # Əlavə konfiqurasiyalar

# CentOS/RHEL
/etc/httpd/
├── conf/
│   └── httpd.conf         # Əsas konfiqurasiya
├── conf.d/                # Əlavə konfiqurasiyalar
└── conf.modules.d/        # Modul konfiqurasiyaları
```

### httpd.conf / apache2.conf

```apache
# /etc/apache2/apache2.conf

# Server əsas parametrləri
ServerRoot "/etc/apache2"
ServerName example.com
ServerAdmin admin@example.com
Timeout 300
KeepAlive On
MaxKeepAliveRequests 100
KeepAliveTimeout 5

# Security
ServerTokens Prod             # Versiya məlumatını gizlə
ServerSignature Off           # Error səhifələrində versiya göstərmə
TraceEnable Off               # TRACE metodu söndür

# Directory defaults
<Directory />
    Options None
    AllowOverride None
    Require all denied
</Directory>

<Directory /var/www/>
    Options -Indexes +FollowSymLinks    # Qovluq listingi qadağan
    AllowOverride All                    # .htaccess icazəsi
    Require all granted
</Directory>

# Logging
ErrorLog ${APACHE_LOG_DIR}/error.log
CustomLog ${APACHE_LOG_DIR}/access.log combined
LogLevel warn

# Modules
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule ssl_module modules/mod_ssl.so
LoadModule headers_module modules/mod_headers.so

# MPM (Multi-Processing Module) - prefork, worker, event
# event MPM - ən müasir, performanslı
<IfModule mpm_event_module>
    StartServers             3
    MinSpareThreads         75
    MaxSpareThreads        250
    ThreadsPerChild         25
    MaxRequestWorkers      400
    MaxConnectionsPerChild   0
</IfModule>
```

### Virtual Hosts

```apache
# /etc/apache2/sites-available/laravel.conf

# HTTP -> HTTPS redirect
<VirtualHost *:80>
    ServerName example.com
    ServerAlias www.example.com
    Redirect permanent / https://example.com/
</VirtualHost>

# HTTPS Virtual Host
<VirtualHost *:443>
    ServerName example.com
    ServerAlias www.example.com
    DocumentRoot /var/www/laravel/public

    # SSL
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/example.com/privkey.pem

    # Directory settings
    <Directory /var/www/laravel/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # mod_rewrite - Laravel routing üçün
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteRule ^ index.php [L]
        </IfModule>
    </Directory>

    # .env faylını qorumaq
    <FilesMatch "^\.env">
        Require all denied
    </FilesMatch>

    # Security headers
    <IfModule mod_headers.c>
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-XSS-Protection "1; mode=block"
        Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
        Header always unset X-Powered-By
    </IfModule>

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/laravel_error.log
    CustomLog ${APACHE_LOG_DIR}/laravel_access.log combined

    # PHP-FPM (proxy_fcgi istifadə edərək)
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>
</VirtualHost>
```

### .htaccess

```apache
# Laravel public/.htaccess (default)
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Əlavə .htaccess qaydaları
# Gzip compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE image/svg+xml
</IfModule>

# Cache headers
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>

# File protection
<Files .env>
    Order allow,deny
    Deny from all
</Files>
```

### mod_rewrite

```apache
# URL rewrite qaydaları
RewriteEngine On

# HTTP -> HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# www -> non-www
RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]
RewriteRule ^(.*)$ https://%1/$1 [R=301,L]

# IP-yə görə bloklama
RewriteCond %{REMOTE_ADDR} ^192\.168\.1\.100$
RewriteRule .* - [F,L]

# User-Agent bloklama (bot)
RewriteCond %{HTTP_USER_AGENT} (BadBot|Scraper) [NC]
RewriteRule .* - [F,L]

# Maintenance mode
RewriteCond %{DOCUMENT_ROOT}/maintenance.html -f
RewriteCond %{REQUEST_FILENAME} !maintenance.html
RewriteRule ^(.*)$ /maintenance.html [R=503,L]

# Köhnə URL redirect
RewriteRule ^old-page$ /new-page [R=301,L]
RewriteRule ^blog/(\d+)$ /posts/$1 [R=301,L]

# RewriteRule flags:
# [L] - Last rule (sonrakı qaydaları yoxlama)
# [R=301] - Redirect (301 permanent, 302 temporary)
# [F] - Forbidden (403)
# [NC] - No Case (case insensitive)
# [QSA] - Query String Append
# [P] - Proxy
```

### mod_php vs PHP-FPM

```bash
# mod_php - Apache modulu olaraq PHP
# - Apache hər request üçün PHP interpreter yükləyir
# - Konfiqurasiya sadədir
# - Performans aşağıdır
# - PHP Apache ilə eyni user-da işləyir (www-data)
sudo a2enmod php8.3

# PHP-FPM - FastCGI Process Manager
# - Ayrı process pool kimi işləyir
# - Performans yüksəkdir
# - Hər site üçün ayrı pool/user mümkün
# - Nginx ilə eyni yanaşma
sudo a2enmod proxy_fcgi
sudo a2enconf php8.3-fpm

# PHP-FPM handler
<FilesMatch \.php$>
    SetHandler "proxy:unix:/var/run/php/php8.3-fpm.sock|fcgi://localhost"
</FilesMatch>

# Tövsiyə: Həmişə PHP-FPM istifadə edin (mod_php yox)
```

### Apache Modulları

```bash
# Modul idarəsi (Ubuntu/Debian)
sudo a2enmod rewrite        # mod_rewrite aktiv et
sudo a2enmod ssl            # mod_ssl aktiv et
sudo a2enmod headers        # mod_headers aktiv et
sudo a2enmod proxy          # mod_proxy aktiv et
sudo a2enmod proxy_fcgi     # PHP-FPM üçün
sudo a2enmod deflate        # Gzip compression
sudo a2enmod expires        # Cache headers
sudo a2enmod http2          # HTTP/2 dəstəyi

sudo a2dismod php8.3        # mod_php deaktiv et

# Site idarəsi
sudo a2ensite laravel.conf   # Site aktiv et
sudo a2dissite 000-default   # Default site deaktiv et

# Dəyişikliklərdən sonra
sudo systemctl reload apache2
```

## Praktiki Nümunələr (Practical Examples)

### Apache vs Nginx Müqayisəsi

```
Xüsusiyyət           | Apache                    | Nginx
--------------------- |---------------------------|---------------------------
Arxitektura           | Process/Thread-based       | Event-driven, async
.htaccess             | Dəstəkləyir               | Dəstəkləmir
Yaddaş istifadəsi     | Yüksək                    | Aşağı
Statik fayllar        | Orta                      | Çox sürətli
Dinamik content       | mod_php ilə daxili         | PHP-FPM ilə xarici
Konfiqurasiya         | .htaccess ilə qovluq       | Yalnız əsas config
                      | səviyyəsində               |
Module sistemi        | Runtime load/unload        | Compile-time
Reverse proxy         | mod_proxy                  | Daxili (native)
HTTP/2                | mod_http2                  | Daxili
Shared hosting        | Uyğundur                   | Uyğun deyil
Öyrənmə               | Asandır                    | Ortadır

Nəticə: Yeni proyektlər üçün Nginx tövsiyə olunur.
Apache shared hosting və legacy sistemlər üçün istifadə olunur.
```

### Laravel Apache Setup Script

```bash
#!/bin/bash
# setup-laravel-apache.sh

DOMAIN="example.com"
APP_DIR="/var/www/laravel"

# Apache və modulları quraşdır
sudo apt install -y apache2 libapache2-mod-fcgid

# Lazımlı modulları aktiv et
sudo a2enmod rewrite ssl headers proxy_fcgi setenvif http2
sudo a2enconf php8.3-fpm

# Default site-ı deaktiv et
sudo a2dissite 000-default

# Virtual host yarat
sudo tee /etc/apache2/sites-available/laravel.conf > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $APP_DIR/public

    <Directory $APP_DIR/public>
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog \${APACHE_LOG_DIR}/${DOMAIN}_error.log
    CustomLog \${APACHE_LOG_DIR}/${DOMAIN}_access.log combined
</VirtualHost>
EOF

# Aktiv et
sudo a2ensite laravel.conf

# Permissions
sudo chown -R www-data:www-data $APP_DIR/storage $APP_DIR/bootstrap/cache
sudo chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache

# Test və restart
sudo apachectl configtest && sudo systemctl reload apache2

echo "Apache configured for $DOMAIN"
```

## PHP/Laravel ilə İstifadə

### Laravel .htaccess Customization

```apache
# public/.htaccess - API rate limiting nümunəsi
<IfModule mod_rewrite.c>
    RewriteEngine On

    # API versioning
    RewriteRule ^api/v1/(.*)$ api/$1 [QSA,L]

    # Maintenance mode bypass (IP ilə)
    RewriteCond %{REMOTE_ADDR} !^10\.0\.0\.
    RewriteCond %{DOCUMENT_ROOT}/storage/framework/down -f
    RewriteRule ^ /503.html [R=503,L]

    # Standard Laravel routing
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# CORS headers (API üçün)
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header set Access-Control-Allow-Headers "Content-Type, Authorization"
</IfModule>
```

### PHP Konfiqurasiyası Apache ilə

```ini
; /etc/php/8.3/fpm/php.ini - Laravel üçün vacib parametrlər
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
max_input_vars = 5000
```

## Interview Sualları

### S1: Apache-nin MPM (Multi-Processing Module) tipləri hansılardır?
**C:** 1) **prefork** - hər request üçün ayrı process yaradır, mod_php ilə istifadə olunur, ən çox yaddaş istifadə edir, thread-safe olmayan modullarla uyğundur. 2) **worker** - hər process daxilində bir neçə thread istifadə edir, daha az yaddaş, daha çox concurrent request. 3) **event** - ən müasir, worker-ə oxşar amma keepalive bağlantıları ayrı thread-də saxlayır, ən yaxşı performans. PHP-FPM ilə event MPM tövsiyə olunur.

### S2: .htaccess-in üstünlük və mənfi tərəfləri nədir?
**C:** Üstünlüklər: server restart etmədən konfiqurasiya dəyişmək, shared hosting-də root olmadan konfiqurasiya, qovluq səviyyəsində fərqli qaydalar. Mənfi: performans azalır (hər request-də .htaccess faylları oxunur), güvənlik riski (istifadəçi konfiqurasiyanı dəyişə bilər). AllowOverride None ilə söndürmək və bütün qaydaları VirtualHost-da yazmaq daha performanslıdır.

### S3: Apache-dən Nginx-ə keçid edərkən nələrə diqqət etmək lazımdır?
**C:** 1) .htaccess qaydaları Nginx-ə çevrilməlidir (rewrite rules), 2) mod_rewrite -> Nginx rewrite/try_files, 3) mod_php əvəzinə PHP-FPM, 4) VirtualHost -> server block, 5) AllowOverride/Allow,Deny -> location blocks, 6) .htpasswd authentication fərqlidir. Laravel üçün əsas dəyişiklik try_files direktividir.

### S4: Apache-də 403 Forbidden erroru necə həll olunur?
**C:** Səbəblər və həllər: 1) Directory permission - `chmod 755` qovluqlar, `chmod 644` fayllar, 2) `Require all granted` əlavə et, 3) SELinux konteksti düzəlt: `restorecon -Rv /var/www`, 4) .htaccess-də `Deny from all` yoxla, 5) `Options -Indexes` qovluq listingini bağlayır - index faylı olmalıdır, 6) Owner www-data olmalıdır.

### S5: mod_rewrite RewriteCond və RewriteRule necə işləyir?
**C:** RewriteCond şərtdir, RewriteRule hərəkətdir. Əvvəlcə bütün RewriteCond-lar yoxlanır (AND məntiqi ilə), hamısı doğrudursa RewriteRule icra olunur. `%{REQUEST_FILENAME} !-f` faylın mövcud olmadığını yoxlayır. `^(.*)$` bütün URL-ləri tutur. `[L]` sonuncu qayda, `[R=301]` redirect, `[F]` forbidden deməkdir. Laravel-in front controller pattern-i bununla işləyir.

## Best Practices

1. **mod_php əvəzinə PHP-FPM** istifadə edin - daha yaxşı performans və təhlükəsizlik
2. **event MPM** istifadə edin - ən performanslı MPM
3. **.htaccess-i minimuma endirin** - mümkünsə VirtualHost-da konfiqurasiya edin
4. **ServerTokens Prod** - Versiya məlumatını gizlədin
5. **Options -Indexes** - Qovluq listingini söndürün
6. **Modulları minimuma endirin** - Yalnız lazımlı modulları yükləyin
7. **Security headers** əlavə edin - mod_headers ilə
8. **.env faylını qoruyun** - FilesMatch ilə deny edin
9. **Log rotation** konfiqurasiya edin - logrotate istifadə edin
10. **SSL/TLS aktiv edin** - Let's Encrypt ilə pulsuz sertifikat alın
