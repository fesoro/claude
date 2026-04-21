## CLI

nginx — daemon olaraq başlat
nginx -t — config syntax test
nginx -T — config test + dump (birləşdirilmiş)
nginx -s reload — config reload (SIGHUP)
nginx -s stop — tez dayan (SIGTERM)
nginx -s quit — graceful shutdown (SIGQUIT)
nginx -s reopen — log fayllarını yenidən aç
nginx -c /etc/nginx/nginx.conf — alternativ config
nginx -p /path/to/prefix — prefix path
nginx -g "daemon off;" — foreground (docker üçün)
nginx -V — build info + modules
nginx -v — versiya
systemctl reload nginx / restart nginx / status nginx

## Config layout

/etc/nginx/nginx.conf — main config
/etc/nginx/conf.d/*.conf — include
/etc/nginx/sites-available/ + sites-enabled/ — Debian convention
/etc/nginx/mime.types — MIME types
/etc/nginx/modules-enabled/ — dynamic modules
/var/log/nginx/access.log, error.log — default log yerləri
/var/www/html — default docroot

## Main / events context

user www-data; — işləyən user
worker_processes auto; — CPU sayı
worker_rlimit_nofile 65535; — file descriptor limit
pid /run/nginx.pid;
error_log /var/log/nginx/error.log warn;
events {
  worker_connections 4096;
  use epoll; — Linux
  multi_accept on;
}

## HTTP context

http {
  include /etc/nginx/mime.types;
  default_type application/octet-stream;
  sendfile on;
  tcp_nopush on;
  tcp_nodelay on;
  keepalive_timeout 65;
  keepalive_requests 1000;
  types_hash_max_size 2048;
  server_tokens off; — versiya gizlə
  client_max_body_size 20m;
  client_body_timeout 60s;
  client_header_timeout 60s;
  send_timeout 60s;
  reset_timedout_connection on;
  large_client_header_buffers 4 16k;
  include /etc/nginx/conf.d/*.conf;
}

## Server block

server {
  listen 80;
  listen 443 ssl http2;
  listen 443 ssl; http2 on; — 1.25.1+ syntax
  listen [::]:80; — IPv6
  listen 80 default_server; — fallback
  listen unix:/var/run/nginx.sock;
  server_name example.com www.example.com;
  server_name *.example.com; — wildcard
  server_name ~^(?<sub>.+)\.example\.com$; — regex
  root /var/www/html;
  index index.html index.htm index.php;
  charset utf-8;
  access_log /var/log/nginx/access.log main;
  error_log /var/log/nginx/error.log warn;
}

## Location

location / { ... } — prefix match
location = /exact { ... } — exact match (ən sürətli)
location ^~ /static/ { ... } — prefix (regex-dən üstün)
location ~ \.php$ { ... } — regex case-sensitive
location ~* \.(jpg|png)$ { ... } — regex case-insensitive
location @fallback { ... } — named location (error_page üçün)
try_files $uri $uri/ /index.php?$query_string; — file/dir/fallback zəncir
try_files $uri @fallback;
alias /var/www/static/; — root-dan fərqli (location prefix əvəz)
root /var/www; — request URI alias + root
internal; — yalnız error_page üçün
return 301 https://$host$request_uri; — redirect
return 404 "not found";
rewrite ^/old/(.*)$ /new/$1 permanent; — 301
rewrite ^/old/(.*)$ /new/$1 last; — internal (re-match location)
rewrite ^/old/(.*)$ /new/$1 break; — stop rewrite phase
rewrite ^/old/(.*)$ /new/$1 redirect; — 302
if ($request_method = POST) { ... } — "if is evil" — minimal istifadə
set $var value;

## proxy_pass

location /api/ {
  proxy_pass http://backend; — trailing / vacib
  proxy_pass http://127.0.0.1:3000;
  proxy_http_version 1.1;
  proxy_set_header Host $host;
  proxy_set_header X-Real-IP $remote_addr;
  proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  proxy_set_header X-Forwarded-Proto $scheme;
  proxy_set_header X-Forwarded-Host $host;
  proxy_set_header Upgrade $http_upgrade; — WebSocket
  proxy_set_header Connection "upgrade";
  proxy_connect_timeout 60s;
  proxy_send_timeout 60s;
  proxy_read_timeout 60s;
  proxy_buffering on;
  proxy_buffers 8 16k;
  proxy_buffer_size 16k;
  proxy_busy_buffers_size 32k;
  proxy_request_buffering on;
  proxy_next_upstream error timeout http_502 http_503;
  proxy_intercept_errors on;
}

## Upstream

upstream backend {
  server 10.0.0.1:3000 weight=3 max_fails=3 fail_timeout=30s;
  server 10.0.0.2:3000 backup;
  server 10.0.0.3:3000 down;
  server unix:/var/run/app.sock;
  keepalive 32; — persistent connections
  keepalive_requests 10000;
  keepalive_timeout 60s;
  # Balance methods:
  # (default) round robin
  least_conn;
  ip_hash; — sticky (client IP)
  hash $request_uri consistent; — consistent hashing
  random two least_conn;
  zone backend_zone 64k; — shared memory (health)
}

## SSL / TLS

server {
  listen 443 ssl;
  http2 on;
  server_name example.com;
  ssl_certificate /etc/ssl/cert.pem;
  ssl_certificate_key /etc/ssl/key.pem;
  ssl_trusted_certificate /etc/ssl/ca.pem; — OCSP stapling üçün
  ssl_protocols TLSv1.2 TLSv1.3;
  ssl_ciphers HIGH:!aNULL:!MD5;
  ssl_prefer_server_ciphers off; — TLS 1.3-də mənasız
  ssl_session_cache shared:SSL:10m;
  ssl_session_timeout 1h;
  ssl_session_tickets off;
  ssl_stapling on;
  ssl_stapling_verify on;
  resolver 1.1.1.1 8.8.8.8 valid=300s;
  resolver_timeout 5s;
  ssl_dhparam /etc/ssl/dhparam.pem;
  ssl_ecdh_curve X25519:secp384r1:secp256r1;
  add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
}
# Let's Encrypt: certbot --nginx -d example.com

## Headers

add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'" always;
add_header Permissions-Policy "geolocation=()" always;
add_header X-Request-ID $request_id always;
expires 30d;
expires max; — "Expires: Thu, 31 Dec 2037 ..."
expires off; — təmizlə
proxy_hide_header X-Powered-By;

## Gzip / compression

gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_comp_level 5;
gzip_proxied any;
gzip_types text/plain text/css application/json application/javascript text/xml application/xml image/svg+xml;
gzip_disable "msie6";
# Brotli (external module) — brotli on; brotli_types ...
# gzip_static on; — pre-compressed .gz fayl

## Cache

# Static asset cache
location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2)$ {
  expires 1y;
  add_header Cache-Control "public, immutable";
  access_log off;
}

# Proxy cache
proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=my_cache:10m max_size=1g inactive=60m use_temp_path=off;
proxy_cache_key "$scheme$request_method$host$request_uri";

location / {
  proxy_cache my_cache;
  proxy_cache_valid 200 302 10m;
  proxy_cache_valid 404 1m;
  proxy_cache_use_stale error timeout http_500 http_502 http_503 http_504 updating;
  proxy_cache_background_update on;
  proxy_cache_revalidate on;
  proxy_cache_lock on;
  proxy_cache_methods GET HEAD;
  proxy_no_cache $cookie_session;
  proxy_cache_bypass $http_pragma $http_authorization;
  add_header X-Cache-Status $upstream_cache_status always;
  proxy_pass http://backend;
}

# fastcgi_cache — eyni ideya PHP-FPM üçün

## Rate limiting

# http context:
limit_req_zone $binary_remote_addr zone=api_rl:10m rate=10r/s;
limit_conn_zone $binary_remote_addr zone=addr_conn:10m;

# location:
limit_req zone=api_rl burst=20 nodelay;
limit_req_status 429;
limit_conn addr_conn 20;
limit_conn_status 429;
limit_req_log_level warn;

## Access control

allow 10.0.0.0/8;
deny all;
auth_basic "Restricted";
auth_basic_user_file /etc/nginx/.htpasswd; — htpasswd -c ... user
satisfy any; — allow OR auth
auth_request /auth-verify; — sub-request auth (ForwardAuth)
# GeoIP: geoip2 module / geoip_country $variable

## PHP-FPM (FastCGI)

location ~ \.php$ {
  include fastcgi_params;
  fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
  # veya fastcgi_pass 127.0.0.1:9000;
  fastcgi_index index.php;
  fastcgi_split_path_info ^(.+\.php)(/.+)$;
  fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  fastcgi_param PATH_INFO $fastcgi_path_info;
  fastcgi_read_timeout 120s;
  fastcgi_buffers 8 16k;
}

## Logging

log_format main '$remote_addr - $remote_user [$time_local] "$request" $status $body_bytes_sent "$http_referer" "$http_user_agent" "$http_x_forwarded_for" rt=$request_time uct="$upstream_connect_time" urt="$upstream_response_time"';
log_format json escape=json '{"time":"$time_iso8601","status":$status,"method":"$request_method","uri":"$request_uri","rt":$request_time,"ua":"$http_user_agent"}';
access_log /var/log/nginx/access.log main;
access_log off; — deaktiv
access_log /var/log/nginx/access.log main buffer=32k flush=5s;
error_log /var/log/nginx/error.log warn;
# Logrotate — /etc/logrotate.d/nginx

## WebSocket

map $http_upgrade $connection_upgrade {
  default upgrade;
  ''      close;
}
location /ws/ {
  proxy_pass http://backend;
  proxy_http_version 1.1;
  proxy_set_header Upgrade $http_upgrade;
  proxy_set_header Connection $connection_upgrade;
  proxy_read_timeout 1h;
}

## Map / geo

map $http_user_agent $is_bot {
  default 0;
  ~*(bot|crawler|spider) 1;
}
geo $allowed {
  default 0;
  10.0.0.0/8 1;
}
map $host $backend {
  api.example.com backend_api;
  www.example.com backend_web;
}

## Common variables

$host / $hostname / $server_name
$remote_addr / $remote_port / $remote_user
$request / $request_uri / $uri / $args / $query_string
$request_method / $request_body / $request_id / $request_time
$http_x_forwarded_for / $http_user_agent / $http_referer / $http_cookie
$cookie_NAME — cookie oxu
$arg_NAME — query param
$scheme / $server_addr / $server_port
$status / $body_bytes_sent / $bytes_sent
$connection / $connection_requests
$upstream_addr / $upstream_status / $upstream_response_time / $upstream_cache_status
$ssl_protocol / $ssl_cipher / $ssl_server_name (SNI)
$time_local / $time_iso8601 / $msec
$document_root / $realpath_root

## Error pages

error_page 404 /404.html;
error_page 500 502 503 504 /50x.html;
error_page 401 = @auth;
location = /50x.html { root /usr/share/nginx/html; internal; }
recursive_error_pages on;
proxy_intercept_errors on;

## Stream (TCP/UDP proxy)

stream {
  upstream db { server 10.0.0.1:5432; server 10.0.0.2:5432; }
  server { listen 5432; proxy_pass db; proxy_connect_timeout 5s; }
  server { listen 53 udp; proxy_pass dns_upstream; }
}

## Debug / troubleshoot

nginx -t — syntax
nginx -T | less — full rendered config
curl -I https://example.com — status + headers
curl -v --resolve example.com:443:1.2.3.4 https://example.com — IP override
tail -f /var/log/nginx/access.log /var/log/nginx/error.log
ngrep -W byline port 80
openssl s_client -connect example.com:443 -servername example.com — TLS handshake
ss -tlnp | grep nginx — port listener
/etc/init.d/nginx reload / systemctl reload nginx
GET /nginx_status — stub_status module (active connections)
nginx_plus / amplify — commercial monitoring

## Useful snippets

# HTTP → HTTPS redirect
server { listen 80; server_name example.com; return 301 https://$host$request_uri; }

# www → non-www
server { listen 443 ssl; server_name www.example.com; return 301 https://example.com$request_uri; }

# Deny hidden files
location ~ /\. { deny all; }

# Deny sensitive files
location ~* \.(env|git|log|sql|bak)$ { deny all; }

# Maintenance mode
if (-f $document_root/maintenance.html) { return 503; }
error_page 503 @maintenance;
location @maintenance { rewrite ^ /maintenance.html break; }

# CORS
add_header Access-Control-Allow-Origin "$http_origin" always;
add_header Access-Control-Allow-Credentials true always;
if ($request_method = OPTIONS) { return 204; }
