## Versions / info

openssl version                          — versiya
openssl version -a                       — build details + paths
openssl version -d                       — OPENSSLDIR
openssl list -commands                   — bütün subcommands
openssl list -cipher-algorithms
openssl list -digest-algorithms
openssl list -public-key-algorithms

## Random

openssl rand 32                          — 32 byte (binary)
openssl rand -hex 32                     — 64 hex char (256 bit)
openssl rand -base64 32
openssl rand -out random.bin 1024

## Hash / digest / HMAC

openssl dgst -sha256 file.txt
openssl dgst -sha512 file.txt
openssl dgst -md5 file.txt               — legacy
openssl sha256 file.txt                  — short form
openssl sha1 file.txt
echo -n "hello" | openssl dgst -sha256
echo -n "hello" | openssl sha256 -binary | base64
openssl dgst -sha256 -hmac "secret" file.txt
echo -n "msg" | openssl dgst -sha256 -hmac "key" -binary | base64    — HMAC for webhook signing
openssl dgst -sha256 -mac HMAC -macopt hexkey:abc... file
openssl dgst -sha256 -sign private.pem -out sig.bin file              — sign
openssl dgst -sha256 -verify public.pem -signature sig.bin file       — verify

## Symmetric encrypt / decrypt

openssl enc -aes-256-cbc -salt -pbkdf2 -in plain.txt -out enc.bin
openssl enc -d -aes-256-cbc -pbkdf2 -in enc.bin -out plain.txt
openssl enc -aes-256-gcm -in file -out file.enc -k "password"        — GCM
openssl enc -aes-256-cbc -pbkdf2 -iter 600000 -in plain -out enc -pass pass:... -e
openssl enc -base64 -in file -out file.b64
openssl enc -base64 -d -in file.b64 -out file
openssl enc -aes-256-cbc -K $(hex_key) -iv $(hex_iv) -in plain -out enc -nosalt
# Modern: prefer GCM/ChaCha20-Poly1305, age, gpg, sops — openssl enc avoid for new code

## Base64 / hex

echo -n "hello" | openssl base64
echo -n "hello" | base64                 — standart util
openssl base64 -A -in file               — single-line
openssl base64 -d -in file.b64
openssl rand -hex 16                     — hex random
xxd / xxd -r                             — hex dump / reverse

## Private key generation

# RSA
openssl genrsa -out private.pem 2048
openssl genrsa -aes256 -out private.pem 4096            — passphrase qoruyulan
openssl genpkey -algorithm RSA -out private.pem -pkeyopt rsa_keygen_bits:4096

# EC (elliptic curve)
openssl genpkey -algorithm EC -out ec.pem -pkeyopt ec_paramgen_curve:P-256
openssl ecparam -name prime256v1 -genkey -noout -out ec.pem
openssl ecparam -list_curves

# Ed25519 (modern)
openssl genpkey -algorithm ED25519 -out ed.pem
openssl genpkey -algorithm ED448 -out ed.pem

# X25519 (key-exchange)
openssl genpkey -algorithm X25519 -out x.pem

# Public key extract
openssl rsa -in private.pem -pubout -out public.pem
openssl pkey -in private.pem -pubout -out public.pem

# Inspect / convert
openssl pkey -in private.pem -text -noout
openssl rsa -in private.pem -text -noout                — RSA-only
openssl rsa -in legacy.pem -outform PEM -out modern.pem
openssl pkcs8 -topk8 -in private.pem -out p8.pem        — convert to PKCS#8
openssl pkcs8 -topk8 -nocrypt -in private.pem -out p8.pem  — unencrypted PKCS#8

## CSR (Certificate Signing Request)

openssl req -new -key private.pem -out csr.pem
openssl req -new -key private.pem -out csr.pem -subj "/C=AZ/ST=Baku/L=Baku/O=Co/CN=example.com"
openssl req -new -newkey rsa:2048 -nodes -keyout private.pem -out csr.pem  — birlikdə key+CSR
openssl req -new -newkey ec:<(openssl ecparam -name prime256v1) -nodes -keyout ec.pem -out csr.pem
openssl req -in csr.pem -text -noout                                       — CSR oxu
openssl req -in csr.pem -verify -noout
openssl req -in csr.pem -pubkey -noout                                     — public key extract

# SAN (Subject Alternative Name) ilə CSR
cat > csr.cnf <<EOF
[req]
distinguished_name = dn
req_extensions = ext
prompt = no
[dn]
CN = example.com
[ext]
subjectAltName = DNS:example.com,DNS:www.example.com,IP:1.2.3.4
EOF
openssl req -new -key private.pem -out csr.pem -config csr.cnf

## Self-signed certificate

openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -nodes \
  -subj "/CN=example.com" \
  -addext "subjectAltName=DNS:example.com,DNS:*.example.com,IP:127.0.0.1"

# CA + signed cert (mini-PKI)
# 1. Root CA
openssl req -x509 -newkey rsa:4096 -nodes -keyout ca.key -out ca.pem -days 3650 -subj "/CN=My Root CA"
# 2. Server key + CSR
openssl req -new -newkey rsa:2048 -nodes -keyout server.key -out server.csr -subj "/CN=example.com"
# 3. Sign CSR
openssl x509 -req -in server.csr -CA ca.pem -CAkey ca.key -CAcreateserial -out server.pem -days 365 -sha256 \
  -extfile <(printf "subjectAltName=DNS:example.com,DNS:*.example.com")
# 4. mTLS client cert
openssl req -new -newkey rsa:2048 -nodes -keyout client.key -out client.csr -subj "/CN=client1"
openssl x509 -req -in client.csr -CA ca.pem -CAkey ca.key -CAcreateserial -out client.pem -days 365

## Inspect / verify certificates

openssl x509 -in cert.pem -text -noout                       — full text
openssl x509 -in cert.pem -subject -noout
openssl x509 -in cert.pem -issuer -noout
openssl x509 -in cert.pem -dates -noout                      — notBefore / notAfter
openssl x509 -in cert.pem -enddate -noout
openssl x509 -in cert.pem -fingerprint -sha256 -noout
openssl x509 -in cert.pem -serial -noout
openssl x509 -in cert.pem -ext subjectAltName -noout         — SAN
openssl x509 -in cert.pem -purpose -noout
openssl x509 -in cert.pem -checkend 86400                    — bitir 24h-də? (exit 1 = yes)
openssl x509 -in cert.pem -noout -modulus | openssl md5      — match key↔cert (eyni hash olmalı)
openssl rsa -in private.pem -noout -modulus | openssl md5

# Verify cert chain
openssl verify -CAfile ca.pem cert.pem
openssl verify -untrusted intermediate.pem -CAfile root.pem cert.pem

# CRL / OCSP
openssl crl -in crl.pem -text -noout
openssl ocsp -issuer ca.pem -cert cert.pem -url http://ocsp.example.com -resp_text

## Convert formats

# PEM ↔ DER
openssl x509 -in cert.pem -outform DER -out cert.der
openssl x509 -in cert.der -inform DER -out cert.pem
openssl rsa -in key.pem -outform DER -out key.der

# PKCS#12 (.p12 / .pfx) — single file (cert+key+chain)
openssl pkcs12 -export -out bundle.p12 -inkey key.pem -in cert.pem -certfile ca.pem -name "alias"
openssl pkcs12 -in bundle.p12 -nokeys -out cert.pem
openssl pkcs12 -in bundle.p12 -nocerts -nodes -out key.pem
openssl pkcs12 -in bundle.p12 -info -nodes

# PKCS#7 (.p7b)
openssl crl2pkcs7 -nocrl -certfile cert.pem -out cert.p7b
openssl pkcs7 -in cert.p7b -print_certs -out cert.pem

## TLS connection debugging

openssl s_client -connect example.com:443
openssl s_client -connect example.com:443 -servername example.com           — SNI
openssl s_client -connect example.com:443 -servername example.com -showcerts
openssl s_client -connect host:443 -tls1_3 / -tls1_2                         — protocol force
openssl s_client -connect host:443 -cipher 'TLS_AES_256_GCM_SHA384'
openssl s_client -connect host:443 -CAfile ca.pem -verify_return_error
openssl s_client -connect host:443 -alpn h2,http/1.1                          — ALPN
openssl s_client -connect host:443 -status                                    — OCSP stapling
openssl s_client -connect host:443 -reconnect                                 — session reuse
openssl s_client -connect host:443 -starttls smtp                             — opportunistic TLS
openssl s_client -connect host:443 -starttls ftp/imap/pop3/xmpp/ldap/postgres
openssl s_client -connect host:443 -no_ticket -msg
openssl s_client -connect host:443 < /dev/null 2>/dev/null | openssl x509 -noout -dates  — bitmə tarixi
echo | openssl s_client -connect host:443 -servername host 2>/dev/null | openssl x509 -noout -text

# TLS server (test)
openssl s_server -cert cert.pem -key key.pem -accept 4433 -www
openssl s_server -cert cert.pem -key key.pem -accept 4433 -tls1_3

## Diffie-Hellman / parameters

openssl dhparam -out dh4096.pem 4096           — DH params (uzun çəkir)
openssl ecparam -name prime256v1 -out ec.pem -genkey

## Password / KDF

openssl passwd -6 "password"                    — SHA-512 crypt (Linux /etc/shadow)
openssl passwd -1 "password"                    — MD5 crypt (legacy)
openssl passwd -apr1 "password"                 — Apache MD5 (htpasswd)
openssl rand -base64 12                         — random password
openssl kdf -keylen 32 -kdfopt digest:SHA256 -kdfopt 'salt:abc' -kdfopt 'pass:p' PBKDF2

## Quick patterns

# Cert + key match check
[ "$(openssl x509 -in cert.pem -noout -modulus | sha256sum)" = "$(openssl rsa -in key.pem -noout -modulus | sha256sum)" ] && echo MATCH || echo MISMATCH

# Quick HTTPS health
echo | openssl s_client -connect example.com:443 -servername example.com 2>/dev/null \
  | openssl x509 -noout -subject -issuer -dates

# Generate htpasswd file (basic-auth)
printf "alice:$(openssl passwd -apr1 'pass')\n" >> .htpasswd

# TLS speed test (cipher benchmark)
openssl speed -evp aes-256-gcm
openssl speed rsa2048

# Convert SSH public key → RFC 4716 / PKCS8
ssh-keygen -e -f id_rsa.pub                    — RFC 4716
ssh-keygen -e -f id_rsa.pub -m PKCS8           — public key in PKCS8

# Decrypt encrypted private key
openssl pkey -in encrypted.pem -out plain.pem

# Encrypt private key with passphrase
openssl pkey -in plain.pem -aes256 -out encrypted.pem

## Common file types (quick reference)

.pem      — PEM (Base64 + BEGIN/END headers); cert, key, CSR, chain
.crt/.cer — usually cert; PEM or DER
.key      — usually private key; PEM
.csr      — Certificate Signing Request; PEM
.der      — DER (binary); cert or key
.p12/.pfx — PKCS#12 (cert + key + chain in one binary file)
.p7b/.p7c — PKCS#7 (cert chain, no key)
.jks      — Java KeyStore (use keytool)
fullchain.pem — leaf + intermediates concatenated
chain.pem      — intermediates only

# PEM file başlıqları (quick identify)
"-----BEGIN CERTIFICATE-----"          x509 cert
"-----BEGIN CERTIFICATE REQUEST-----"   CSR
"-----BEGIN PRIVATE KEY-----"           PKCS#8 unencrypted
"-----BEGIN ENCRYPTED PRIVATE KEY-----" PKCS#8 encrypted
"-----BEGIN RSA PRIVATE KEY-----"       PKCS#1 RSA (legacy)
"-----BEGIN EC PRIVATE KEY-----"        SEC1 EC (legacy)
"-----BEGIN OPENSSH PRIVATE KEY-----"   OpenSSH
"-----BEGIN PUBLIC KEY-----"            SubjectPublicKeyInfo
"-----BEGIN DH PARAMETERS-----"         DH params

## Let's Encrypt (qısa)

certbot certonly --standalone -d example.com
certbot --nginx -d example.com -d www.example.com
certbot renew --dry-run
certbot certificates                    — list installed
acme.sh --issue -d example.com --webroot /var/www/html
# Cert lives at /etc/letsencrypt/live/<domain>/{fullchain,privkey,cert,chain}.pem
