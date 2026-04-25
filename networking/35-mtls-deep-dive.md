# mTLS Deep Dive (Senior)

## İcmal

**mTLS** (Mutual TLS) standart TLS-in genişləndirilmiş formasıdır. Standart TLS yalnız server-in kimliyini doğrulayır — client anonimdir. mTLS-də **həm server, həm client** bir-birini sertifikatla authenticate edir.

```
Standard TLS:                    mTLS:
  Client ---> verifies Server      Client <---> verifies each other <---> Server
  (Client anonim)                  (Hər iki tərəfdə sertifikat var)
```

İstifadə halları: service-to-service authentication (microservices, service mesh), Zero Trust networking, IoT device authentication, B2B API integrations, banking & fintech (regulatory requirements).

## Niyə Vacibdir

Microserviceslər arasında traffic-i yalnız application-level token-larla qorumaq kifayət deyil — network-daxili traffic-i şifrələmək və hər servisin kriptoqrafik identity-sinə sahib olmaq lazımdır. Service mesh (Istio) bu prosesi avtomatlaşdırır, amma mTLS-i başa düşmədən konfiqurasiya və debug etmək mümkün deyil.

## Əsas Anlayışlar

### mTLS 1.3 Handshake

```
Client                                  Server
  |---- ClientHello ---------------------->|
  |                                         |
  |<--- ServerHello                         |
  |     + Certificate (server's)            |
  |     + CertificateRequest   <-- YENİ     |
  |     + CertificateVerify                 |
  |     + Finished -------------------------|
  |                                         |
  |---- Certificate (client's) <-- YENİ --->|
  |     + CertificateVerify    <-- YENİ     |
  |     + Finished ------------------------>|
  |                                         |
  [encrypted application data]

CertificateRequest: server client-dən sertifikat istəyir
Client's Certificate: X.509 sertifikat zənciri
Client's CertificateVerify: private key sahibliyini sübut edir
```

### Hər İki Tərəfin Yoxlamaları

```
Server client sertifikatını yoxlayır:
  1. Sertifikat etibarlı CA tərəfindən imzalanıb? (CA bundle ilə uyğun)
  2. Sertifikat müddəti bitib?
  3. Sertifikat revoke olunub? (CRL / OCSP yoxlaması)
  4. Subject/SAN gözlənilən identity-ə uyğundur?
  5. CertificateVerify imzası etibarlıdır?
     → Client private key sahibidir sübut olunur

Client server sertifikatını yoxlayır:
  Eyni yoxlamalar, əlavə olaraq hostname verification (SNI CN/SAN-a uyğundur).
```

### PKI Qurulumu

```
+-------------------+
|    Root CA        |  (offline saxlanılır, nadir istifadə)
+---------+---------+
          |
          v
+-------------------+
| Intermediate CA   |  (client/server sertifikatları verir)
+----+-------+------+
     |       |
     v       v
+--------+ +--------+
| Server | | Client |
| Cert   | | Cert   |
+--------+ +--------+

Bütün tərəflər Root CA-ya güvənir.
Client Intermediate CA tərəfindən imzalanmış sertifikat təqdim edir.
Server zənciri yoxlayır: Client → Intermediate → Root (etibarlı).
```

### Certificate Authentication vs Token Auth

```
Token (JWT, OAuth):
  + Sadə implementasiya
  + Stateless
  - Token oğurlana bilər (copy-paste)
  - Revocation çətin

mTLS Certificate:
  + Private key cihazdan çıxmır (TPM/HSM)
  + Kriptoqrafik sahib sübut
  + Aydın identity (cert subject)
  - Mürəkkəb PKI idarəetməsi
  - Sertifikat rotasiyası lazımdır
```

### Service Mesh mTLS (Istio)

```
Istio microserviceslər üçün mTLS-i avtomatlaşdırır:

1. Istiod (control plane) CA-dır
2. Hər pod Envoy sidecar proxy alır
3. Sidecar qısa ömürlü sertifikat alır (24s TTL)
4. Sertifikatda SPIFFE ID var:
     spiffe://cluster.local/ns/prod/sa/orders
5. Sidecar-lar arasındakı bütün traffic mTLS-dir
6. Application kodu dəyişdirilmir (şəffaf)

Tətbiq:
  apiVersion: security.istio.io/v1
  kind: PeerAuthentication
  metadata:
    name: default
  spec:
    mtls:
      mode: STRICT     # mTLS olmayan bağlantıları rədd et
```

### SPIFFE / SPIRE

```
SPIFFE (Secure Production Identity Framework For Everyone):
  Workload-lar üçün universal identity standartı.
  Format: spiffe://trust-domain/path
  Misal: spiffe://example.com/ns/prod/sa/api

SPIRE (SPIFFE Runtime Environment):
  Reference implementasiya.
  Server + agentlər workload attestation əsasında sertifikat verir.
  Kubernetes, VM, bare metal ilə inteqrasiya edir.

Node attestation:    node identity-sini sübut edir (k8s_psat, aws_iid)
Workload attestation: workload identity-sini sübut edir (pod labels, unix uid)

Workload çağırır:
  → Agent attestation yoxlayır
  → Qısa ömürlü X.509 və ya JWT-SVID verir
  → Bir neçə saat etibarlı, avtomatik yenilənir
```

### Certificate Revocation

```
CRL (Certificate Revocation List):
  CA revoke olmuş sertifikatların siyahısını publish edir.
  Client dövri olaraq yükləyir.
  Problem: böyük fayl, gecikmə.

OCSP (Online Certificate Status Protocol):
  Client CA-ya real-time soruşur: "bu sertifikat revoke olunub?"
  Real-time amma latency əlavə edir + privacy problemi.

OCSP Stapling:
  Server CA-ya soruşur, cavabı cache edir, handshake ilə göndərir.
  Hər ikisinin üstünlüyü.

Qısa ömürlü sertifikatlar (SPIRE, Istio):
  TTL = 1-24 saat.
  Revocation lazım deyil — yenilənmir.
  Modern Zero Trust yanaşması.
```

### Performance Overhead

```
TLS handshake: 1-2 RTT
mTLS handshake: eyni RTT, əlavə:
  - Server client sertifikatını yoxlayır (CPU)
  - CRL/OCSP yoxlaması (network, stapling olmasa)
  - Daha böyük sertifikat mesajları

Azaltma:
  - Session resumption (tam handshake skip et)
  - Connection pooling (bir dəfə handshake, yenidən istifadə)
  - HTTP/2 (tək connection üzərindən multiplexing)
  - Qısa sertifikat zənciri
```

## Praktik Baxış

- **Avtomatik rotation məcburidir:** Manual rotation outage riski. Vault, cert-manager, SPIRE istifadə et.
- **Qısa ömürlü sertifikatlar (1-24h):** Revocation problemini aradan qaldırır, blast radius azalır.
- **Ayrı Intermediate CA:** Root CA offline, HSM-də saxla. Compromise olsa yalnız intermediate rotate et.
- **Load balancer qərarı:** mTLS pass-through (end-to-end security) vs termination (performance) — istifadə case-ə görə seç.
- **Debug çətin:** tcpdump ilə encrypted traffic görünmür. `openssl s_client` ilə handshake debug et.

### Anti-patterns

- Static, uzunömürlü client sertifikatları — rotation etmirsan, breach olsa uzun müddət açıq qalır
- Yalnız IP-based trust + mTLS — IP dəyişə bilər (pod restart), SPIFFE ID daha etibarlıdır
- Root CA-nı online saxlamaq — intermediate CA-dan keç
- Development-da `ssl_verify_client off` qoyub unutmaq — production-da aktiv olduğunu yoxla

## Nümunələr

### Ümumi Nümunə

```
mTLS request flow (Nginx termination):

  [orders-service]
       |
       | mTLS (client cert: CN=orders-service)
       v
  [Nginx]
       | ssl_verify_client on
       | CN-i Laravel-ə header kimi göndərir
       v
  [Laravel]
       | X-Client-Subject: CN=orders-service,OU=backend
       | Middleware CN-i yoxlayır
       | Route authorization edir
```

### Kod Nümunəsi

**Outgoing mTLS Request (Laravel):**
```php
use Illuminate\Support\Facades\Http;

$response = Http::withOptions([
    'cert'    => [storage_path('certs/client.crt'), config('mtls.cert_password')],
    'ssl_key' => [storage_path('certs/client.key'), config('mtls.key_password')],
    'verify'  => storage_path('certs/ca-bundle.crt'),
])->post('https://api.partner.com/v1/transactions', [
    'amount' => 1000,
]);

if ($response->failed()) {
    logger()->error('mTLS call failed', [
        'status' => $response->status(),
        'body'   => $response->body(),
    ]);
}
```

**Nginx mTLS Konfiqurasiyası:**
```nginx
server {
    listen 443 ssl;
    server_name api.internal.example.com;

    ssl_certificate     /etc/nginx/ssl/server.crt;
    ssl_certificate_key /etc/nginx/ssl/server.key;

    # mTLS konfigurasiyası
    ssl_client_certificate /etc/nginx/ssl/ca-bundle.crt;
    ssl_verify_client      on;
    ssl_verify_depth       2;

    location / {
        # Client identity-ni Laravel-ə ötür
        proxy_set_header X-Client-Verify      $ssl_client_verify;
        proxy_set_header X-Client-Subject     $ssl_client_s_dn;
        proxy_set_header X-Client-Issuer      $ssl_client_i_dn;
        proxy_set_header X-Client-Serial      $ssl_client_serial;
        proxy_set_header X-Client-Fingerprint $ssl_client_fingerprint;

        proxy_pass http://127.0.0.1:9000;
    }
}
```

**Laravel Middleware — mTLS Identity Oxuma:**
```php
// app/Http/Middleware/MtlsAuthenticate.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MtlsAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->header('X-Client-Verify') !== 'SUCCESS') {
            abort(401, 'Client certificate required');
        }

        $subject = $request->header('X-Client-Subject', '');
        // Parse: CN=orders-service,OU=backend,O=Example
        $cn = $this->extractCn($subject);

        $allowedServices = config('mtls.allowed_services', []);
        if (! in_array($cn, $allowedServices)) {
            abort(403, "Service {$cn} not authorized");
        }

        $request->attributes->set('mtls_identity', $cn);
        return $next($request);
    }

    private function extractCn(string $subject): ?string
    {
        if (preg_match('/CN=([^,]+)/', $subject, $m)) {
            return $m[1];
        }
        return null;
    }
}
```

**Per-route Service Authorization:**
```php
// routes/api.php
Route::middleware('mtls')->group(function () {
    Route::post('/internal/charge', [BillingController::class, 'charge'])
         ->middleware('mtls.allow:orders-service,admin-service');

    Route::get('/internal/users', [UserController::class, 'index'])
         ->middleware('mtls.allow:reporting-service');
});

// app/Http/Middleware/MtlsAllow.php
class MtlsAllow
{
    public function handle(Request $request, Closure $next, ...$services)
    {
        $identity = $request->attributes->get('mtls_identity');
        if (! in_array($identity, $services)) {
            abort(403, "Identity {$identity} not allowed here");
        }
        return $next($request);
    }
}
```

**Sertifikat Yaratma (CLI):**
```bash
# CA yarat
openssl req -x509 -newkey rsa:4096 -keyout ca.key -out ca.crt -days 3650 -nodes \
  -subj "/CN=Example Internal CA"

# Servis sertifikat signing request
openssl req -newkey rsa:4096 -keyout orders.key -out orders.csr -nodes \
  -subj "/CN=orders-service/O=Example"

# CA ilə imzala
openssl x509 -req -in orders.csr -CA ca.crt -CAkey ca.key -CAcreateserial \
  -out orders.crt -days 365 -sha256
```

**Avtomatik Sertifikat Rotasiyası (HashiCorp Vault):**
```php
// app/Console/Commands/RotateMtlsCert.php
class RotateMtlsCert extends Command
{
    protected $signature = 'mtls:rotate';

    public function handle()
    {
        $response = Http::withToken(env('VAULT_TOKEN'))
            ->post(env('VAULT_ADDR') . '/v1/pki/issue/app-role', [
                'common_name' => 'laravel-app',
                'ttl'         => '720h',
            ]);

        $data = $response->json('data');
        file_put_contents(storage_path('certs/client.crt'), $data['certificate']);
        file_put_contents(storage_path('certs/client.key'), $data['private_key']);

        $this->info('Cert rotated, new expiry: ' . $data['expiration']);
    }
}

// Kernel.php
$schedule->command('mtls:rotate')->dailyAt('02:00');
```

## Praktik Tapşırıqlar

1. **Local PKI qur:** `openssl` ilə Root CA, Intermediate CA, client sertifikatı yarat. Chain-i yoxla: `openssl verify -CAfile ca.crt client.crt`.

2. **Nginx mTLS:** Nginx-i yuxarıdakı konfiqurasiya ilə qur, `ssl_verify_client on` aktiv et, `curl --cert client.crt --key client.key` ilə test et.

3. **Laravel middleware:** `MtlsAuthenticate` middleware-ini yaz, test üçün Nginx headers-i manual header kimi göndər, CN-i düzgün parse etdiyini yoxla.

4. **Vault PKI integration:** HashiCorp Vault-da PKI secret engine aktiv et, Laravel üçün role yarat, `RotateMtlsCert` command-ı yaz.

5. **Sertifikat expiry monitoring:** `openssl x509 -in client.crt -noout -dates` — expiry tarixini çıxar, 30 gün qalmış alert göndərən script yaz.

6. **mTLS + JWT combine:** mTLS ilə service identity, JWT ilə user identity. `MtlsAuthenticate` + `auth:api` middleware-lərini eyni route-da birlikdə işlət.

## Əlaqəli Mövzular

- [HTTPS & SSL/TLS](06-https-ssl-tls.md)
- [Zero Trust Security](33-zero-trust.md)
- [API Security](17-api-security.md)
- [gRPC](10-grpc.md)
- [Service Discovery](43-service-discovery.md)
