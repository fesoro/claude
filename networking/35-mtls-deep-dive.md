# mTLS (Mutual TLS) Deep Dive

## Nədir? (What is it?)

**mTLS** (Mutual TLS) standart TLS-in genişləndirilmiş formasıdır. Standart TLS yalnız server-in kimliyini doğrulayır - client anonimdir. mTLS-də **həm server, həm client** bir-birini sertifikatla authenticate edir.

İstifadə halları:
- **Service-to-service authentication** (microservices, service mesh)
- **Zero Trust networking** (BeyondCorp style)
- **IoT device authentication** (hər device unikal sertifikat)
- **API-level mutual auth** (B2B integrations)
- **VPN alternatives** (WireGuard, OpenVPN mTLS)
- **Banking & fintech** (regulatory requirements)

```
Standard TLS:                    mTLS:
  Client ---> verifies Server      Client <---> verifies each other <---> Server
  (Client anonymous)               (Both have certificates)
```

## Necə İşləyir? (How does it work?)

### 1. Standard TLS 1.3 Handshake (for comparison)

```
Client                               Server
  |---- ClientHello -------------------->|
  |                                      |
  |<--- ServerHello                      |
  |     + Certificate (server's)         |
  |     + CertificateVerify              |
  |     + Finished ----------------------|
  |                                      |
  |---- Finished ----------------------->|
  |                                      |
  [encrypted application data]
```

### 2. mTLS 1.3 Handshake

```
Client                                  Server
  |---- ClientHello ---------------------->|
  |                                         |
  |<--- ServerHello                         |
  |     + Certificate (server's)            |
  |     + CertificateRequest   <-- NEW      |
  |     + CertificateVerify                 |
  |     + Finished -------------------------|
  |                                         |
  |---- Certificate (client's) <-- NEW ---->|
  |     + CertificateVerify    <-- NEW      |
  |     + Finished ------------------------>|
  |                                         |
  [encrypted application data]

CertificateRequest: server asks client for cert
Client's Certificate: chain of X.509 certs
Client's CertificateVerify: proves possession of private key
```

### 3. Verification Steps on Both Sides

```
Server verifies client cert:
  1. Cert signed by trusted CA? (matches server's CA bundle)
  2. Cert not expired?
  3. Cert not revoked? (CRL / OCSP check)
  4. Subject/SAN matches expected identity?
  5. CertificateVerify signature valid?
    -> Proves client owns private key

Client verifies server cert:
  Same checks, plus hostname verification (SNI matches CN/SAN).
```

### 4. PKI Setup for mTLS

```
+-------------------+
|    Root CA        |  (kept offline, rarely used)
+---------+---------+
          |
          v
+-------------------+
| Intermediate CA   |  (issues client/server certs)
+----+-------+------+
     |       |
     v       v
+--------+ +--------+
| Server | | Client |
| Cert   | | Cert   |
+--------+ +--------+

All parties trust Root CA.
Client presents cert signed by Intermediate.
Server verifies chain: Client -> Intermediate -> Root (trusted).
```

## Əsas Konseptlər (Key Concepts)

### Certificate Authentication vs Token Auth

```
Token (JWT, OAuth):
  + Simple to implement
  + Stateless
  - Tokens can be stolen (copy-paste)
  - Revocation tricky

mTLS Certificate:
  + Private key never leaves device (TPM/HSM)
  + Cryptographic proof of possession
  + Clear identity (cert subject)
  - Complex PKI management
  - Cert rotation needed
```

### Service Mesh mTLS (Istio Example)

```
Istio automates mTLS for microservices:

1. Istiod (control plane) is a CA
2. Each pod gets Envoy sidecar proxy
3. Sidecar receives short-lived cert (24h TTL)
4. SPIFFE ID embedded in cert:
     spiffe://cluster.local/ns/prod/sa/orders
5. All traffic between sidecars is mTLS
6. Application code unchanged (transparent)

Apply with:
  apiVersion: security.istio.io/v1
  kind: PeerAuthentication
  metadata:
    name: default
  spec:
    mtls:
      mode: STRICT     # reject non-mTLS
```

### SPIFFE / SPIRE

```
SPIFFE (Secure Production Identity Framework For Everyone):
  Universal identity standard for workloads.
  Format: spiffe://trust-domain/path
  Example: spiffe://example.com/ns/prod/sa/api

SPIRE (SPIFFE Runtime Environment):
  Reference implementation.
  Server + agents issue certs based on workload attestation.
  Integrates with Kubernetes, VMs, bare metal.

Node attestation:    proves node identity (k8s_psat, aws_iid, etc.)
Workload attestation: proves workload identity (pod labels, process unix uid)

Workload calls:
  -> Agent checks attestation
  -> Issues short-lived X.509 or JWT-SVID
  -> Valid for few hours, auto-rotated
```

### Certificate Revocation

```
CRL (Certificate Revocation List):
  CA publishes signed list of revoked certs.
  Client downloads periodically.
  Problem: large file, delay in updates.

OCSP (Online Certificate Status Protocol):
  Client queries CA: "is this cert revoked?"
  Real-time but adds latency + privacy leak.

OCSP Stapling:
  Server queries CA, caches response, sends with handshake.
  Best of both worlds.

Short-lived certs (SPIRE, Istio):
  TTL = 1-24 hours.
  No revocation needed - just don't renew.
  Modern Zero Trust approach.
```

### Cert Subject vs SAN

```
X.509 cert contains identity:

Common Name (CN):  Legacy, avoid for hostnames
Subject Alt Name (SAN):
  DNS:api.example.com
  DNS:*.api.example.com
  IP:10.0.0.5
  URI:spiffe://example.com/workload/api
  email:service@example.com

Modern clients ignore CN, check only SAN.
```

### Performance Overhead

```
TLS handshake: 1-2 RTT
mTLS handshake: same RTT, plus:
  - Server validates client cert (CPU)
  - CRL/OCSP check (network, unless stapled)
  - Larger certificate messages

Mitigation:
  - Session resumption (skip full handshake)
  - Connection pooling (handshake once, reuse)
  - HTTP/2 or HTTP/3 (multiplex over single conn)
  - Short cert chains
```

## PHP/Laravel ilə İstifadə

### Outgoing mTLS Request (Guzzle)

```php
use Illuminate\Support\Facades\Http;

$response = Http::withOptions([
    'cert'    => [storage_path('certs/client.crt'), config('mtls.cert_password')],
    'ssl_key' => [storage_path('certs/client.key'), config('mtls.key_password')],
    'verify'  => storage_path('certs/ca-bundle.crt'),  // trust this CA
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

### Combined PEM File

```bash
# Some servers prefer combined cert + key:
cat client.crt client.key > client.pem

# Guzzle with single file:
```

```php
Http::withOptions([
    'cert'   => storage_path('certs/client.pem'),
    'verify' => storage_path('certs/ca-bundle.crt'),
])->get('https://api.partner.com/status');
```

### Receiving mTLS in Laravel (Nginx terminates)

Nginx verifies client cert and passes info as headers.

```nginx
server {
    listen 443 ssl;
    server_name api.internal.example.com;

    ssl_certificate     /etc/nginx/ssl/server.crt;
    ssl_certificate_key /etc/nginx/ssl/server.key;

    # mTLS config
    ssl_client_certificate /etc/nginx/ssl/ca-bundle.crt;
    ssl_verify_client      on;
    ssl_verify_depth       2;

    location / {
        # Pass client identity to Laravel
        proxy_set_header X-Client-Verify   $ssl_client_verify;
        proxy_set_header X-Client-Subject  $ssl_client_s_dn;
        proxy_set_header X-Client-Issuer   $ssl_client_i_dn;
        proxy_set_header X-Client-Serial   $ssl_client_serial;
        proxy_set_header X-Client-Fingerprint $ssl_client_fingerprint;

        proxy_pass http://127.0.0.1:9000;
    }
}
```

### Laravel Middleware Reading mTLS Identity

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

### Per-route Authorization by Cert Identity

```php
// routes/api.php
Route::middleware('mtls')->group(function () {
    Route::post('/internal/charge', [BillingController::class, 'charge'])
         ->middleware('mtls.allow:orders-service,admin-service');

    Route::get('/internal/users', [UserController::class, 'index'])
         ->middleware('mtls.allow:reporting-service');
});
```

```php
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

### Generating Client Certs (CLI, openssl)

```bash
# Generate CA
openssl req -x509 -newkey rsa:4096 -keyout ca.key -out ca.crt -days 3650 -nodes \
  -subj "/CN=Example Internal CA"

# Generate service cert signing request
openssl req -newkey rsa:4096 -keyout orders.key -out orders.csr -nodes \
  -subj "/CN=orders-service/O=Example"

# Sign with CA
openssl x509 -req -in orders.csr -CA ca.crt -CAkey ca.key -CAcreateserial \
  -out orders.crt -days 365 -sha256
```

### Cert Rotation with Laravel Scheduled Command

```php
// app/Console/Commands/RotateMtlsCert.php
class RotateMtlsCert extends Command
{
    protected $signature = 'mtls:rotate';

    public function handle()
    {
        // Fetch new cert from internal CA service (e.g., HashiCorp Vault)
        $response = Http::withToken(env('VAULT_TOKEN'))
            ->post(env('VAULT_ADDR').'/v1/pki/issue/app-role', [
                'common_name' => 'laravel-app',
                'ttl' => '720h',
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

## Interview Sualları (Q&A)

### 1. mTLS ilə adi TLS arasında fərq nədir?

**Cavab:** Adi TLS-də yalnız server sertifikat təqdim edir, client anonimdir (istifadəçi adı/parol kimi application-level auth olur). mTLS-də hər iki tərəf sertifikatla authenticate olur - server client-dən də sertifikat istəyir və yoxlayır. Service-to-service auth üçün ideal.

### 2. mTLS niyə microservices üçün yaxşıdır?

**Cavab:** (1) Strong identity - hər servisin kriptoqrafik identity-si var, (2) Mutual auth - heç bir servis digərini spoof edə bilməz, (3) Encrypted traffic - hətta private network-də, (4) Token-lardan fərqli olaraq private key workload-dan çıxmır, (5) Service mesh (Istio) bunu avtomatlaşdırır.

### 3. SPIFFE və SPIRE nədir?

**Cavab:** **SPIFFE** workload identity üçün universal standartdır - `spiffe://trust-domain/path` formatlı ID. **SPIRE** onun reference implementation-u - Kubernetes pod-larına, VM-lərə workload attestation (hansı pod-dur, hansı service account-dur) əsasında short-lived X.509 sertifikatları paylayır. Istio bunu default istifadə edir.

### 4. Certificate revocation necə işləyir?

**Cavab:** 3 yanaşma: (1) **CRL** - CA revoked sertifikat listesi publish edir, böyük fayl, gecikməli, (2) **OCSP** - real-time CA-ya sorğu, latency və privacy problemləri, (3) **OCSP Stapling** - server özü OCSP-ni cache edib handshake-də verir, (4) **Short-lived certs** (1-24 saat TTL) - revocation-a ehtiyac yoxdur, yenilənmir.

### 5. mTLS performance overhead necə olur?

**Cavab:** Əlavə RTT yoxdur (handshake eyni mesaj sayıdır), amma əlavə CPU server-də client cert yoxlamağa, əlavə network CRL/OCSP check-ə sərf olunur. Mitigation: session resumption, connection pooling, HTTP/2 multiplexing, OCSP stapling, short cert chains.

### 6. mTLS-i Nginx ilə necə implement edirsən?

**Cavab:**
```nginx
ssl_client_certificate /etc/nginx/ssl/ca.crt;
ssl_verify_client      on;
```
Nginx client cert-i yoxlayır, valid olsa, `$ssl_client_s_dn` (subject), `$ssl_client_verify` kimi variable-ları backend-ə header kimi ötürür. Laravel bu header-dən identity oxuyub authorization edir.

### 7. Cert rotation-ı necə idarə edirsən?

**Cavab:** Avtomatik olmalıdır. (1) HashiCorp Vault PKI secret engine-dən short-lived cert issue et, (2) Laravel scheduled command-la təzələ, (3) Istio/SPIRE istifadə edirsənsə, bunu avtomatik edir (24h TTL), (4) monitoring: cert expiry-yə 30 gün qalanda alert.

### 8. mTLS və OAuth/JWT arasında nə vaxt seçməliyəm?

**Cavab:**
- **mTLS** - service-to-service, stabil infrastructure (eyni CA-dan keçir), Zero Trust, yüksək təhlükəsizlik tələbi.
- **JWT/OAuth** - user authentication (browser, mobile), 3rd party integrations, dynamic client-lər, stateless.
- **Combined** - mTLS transport, JWT user identity (bearer token mTLS connection üzərindən).

### 9. SPIFFE ID-nin üstünlüyü nədir IP/hostname-ə görə?

**Cavab:** IP dəyişə bilər (pod restart, scaling), hostname DNS-dən asılıdır. SPIFFE ID workload-a bağlıdır - pod yenidən başlasa da identity qalır (service account əsaslı). Kubernetes dynamic environment-də sabit identity verir. İstio policy-lər SPIFFE ID ilə yazılır.

### 10. mTLS production-da hansı problemlərlə qarşılaşa bilərsən?

**Cavab:** (1) Cert expiry unutmaq -> outage, (2) CA rotation mürəkkəb (bütün client-lər trust yeniləməlidir), (3) Debug çətin (tcpdump ilə encrypted trafik görünmür), (4) Cross-environment (dev/prod fərqli CA), (5) Mobile client-də private key qorumaq, (6) Load balancer mTLS pass-through vs termination qərarı.

## Best Practices

1. **Avtomatik cert rotation qur** - HashiCorp Vault, cert-manager (Kubernetes), SPIRE istifadə et. Manual rotation ölümcüldür.
2. **Short-lived certs istifadə et** (1-24 saat) - revocation problemini ortadan qaldırır, blast radius-u azaldır.
3. **Ayrı intermediate CA yarat** - Root CA offline saxla (HSM), intermediate-dən issue et. Compromise olsa intermediate rotate et.
4. **Cert monitoring qur** - Prometheus blackbox exporter, Datadog ilə expiry izlə, 30 gün qalanda alert.
5. **SPIFFE istifadə et microservices üçün** - vendor lock-in-dən qaç, standart API-lərdən istifadə et.
6. **mTLS + JWT combine et** - transport-da mTLS (service identity), application-da JWT (user identity) istifadə et.
7. **Verify mode "STRICT" qoy** - Istio-da `PERMISSIVE` yalnız migration müddəti üçün, sonra `STRICT`-ə keç.
8. **Sensitive headers log-lama** - `X-Client-Subject` log-da qala bilər, amma private key heç vaxt.
9. **Certificate pinning düşün** - kritik integrasiyalar üçün yalnız spesifik cert fingerprint-ə güvən, CA kompromis olsa da qorun.
10. **Load balancer mTLS termination vs pass-through seç** - pass-through end-to-end security, termination performance. Istifadə case-ə görə.
