# Certificate Transparency (CT)

## Nədir? (What is it?)

**Certificate Transparency** (CT) SSL/TLS sertifikatlarının public, auditable log-lara yazılmasını tələb edən framework-dür. RFC 6962 (CT v1) və RFC 9162 (CT v2) ilə standardlaşdırılıb.

Məqsəd: **unauthorized** və ya **miskdirected issued** sertifikatları aşkar etmək. CT olmasa, rogue CA sizin domain üçün sertifikat verir və siz bundan xəbərsiz qala bilərsiniz. CT-də hər cert public log-a yazılır - siz (və bütün dünya) onu monitor edə bilər.

Google 2013-də təqdim edib, 2015-də Symantec skandalından sonra (fake Google sertifikatları issue olundu) zərurət kimi qəbul edilib. 2018-dən Chrome bütün public cert-lər üçün CT məcburidir.

```
Without CT:                          With CT:
  Rogue CA issues                      Rogue CA issues
  fake cert for yoursite.com            fake cert for yoursite.com
        |                                       |
        v                                       v
  Used to MITM users                      Must submit to CT log
  (you don't know)                              |
                                                v
                                          Public log entry
                                                |
                                                v
                                     Monitoring service detects
                                                |
                                                v
                                       You get alerted -> revoke
```

## Necə İşləyir? (How does it work?)

### 1. Certificate Lifecycle with CT

```
1. You request cert from CA (Let's Encrypt, DigiCert, etc.)
2. CA creates pre-certificate
3. CA submits pre-cert to 2+ CT logs
4. Each log returns SCT (Signed Certificate Timestamp)
5. CA embeds SCTs in final cert (or stapled via OCSP)
6. Cert delivered to you
7. Browser verifies SCT during handshake
8. Monitor services scan logs, alert on unauthorized certs
```

### 2. SCT (Signed Certificate Timestamp)

```
SCT = cryptographic receipt from CT log saying
      "I have logged this cert at time T"

SCT embedded 3 ways:
  1. In certificate itself (X.509 extension)
  2. Via TLS extension during handshake
  3. Via OCSP stapling

Chrome requires 2+ SCTs from different trusted logs.

SCT structure:
  - Log ID
  - Timestamp
  - Signature (log's private key)

Browser verifies signature against known log public keys.
```

### 3. CT Log Structure (Merkle Tree)

```
CT logs are append-only Merkle trees:

           Root Hash
          /         \
       H1            H2
      /  \          /  \
    H3   H4       H5   H6
   /  \ / \      / \  / \
  C1 C2 C3 C4   C5 C6 C7 C8

Properties:
  - Append-only (cannot remove or modify)
  - Cryptographically verifiable (root hash)
  - Signed Tree Head (STH) published periodically
  - Anyone can audit: "Was my cert really logged?"
```

### 4. Monitoring Flow

```
Step 1: Monitoring service (e.g., Cert Spotter, Facebook CT Monitor):
         - Subscribes to all major CT logs
         - Downloads all new entries continuously
         - Indexes by domain name

Step 2: You register domains to monitor:
         example.com
         *.example.com

Step 3: Service alerts when:
         - Unexpected CA issues cert for your domain
         - Unexpected SAN entries
         - Unexpected issuance date
         - Wildcard cert when you expected specific

Step 4: You investigate:
         - Legitimate? (maybe colleague requested)
         - Unauthorized? -> contact CA for revocation
```

## Əsas Konseptlər (Key Concepts)

### Chrome CT Requirement (2018+)

```
Chrome 68+ (Aug 2018):
  All certs issued after Apr 30, 2018 must include CT.
  No CT = "Not Secure" warning.

Requirements:
  - 2+ SCTs from Chrome-recognized logs
  - At least one "Google operated" log
  - At least one "non-Google operated" log

Logs trusted by Chrome: https://chromium.googlesource.com/...
  Examples:
    - Google 'Argon' (yearly shards)
    - Cloudflare 'Nimbus'
    - DigiCert 'Yeti'
    - Let's Encrypt 'Oak'
```

### CAA Records (DNS-based CA Authorization)

```
CAA = Certification Authority Authorization
Lives in DNS, tells CAs: "Only these CAs can issue for my domain"

Example DNS records:
  example.com. IN CAA 0 issue "letsencrypt.org"
  example.com. IN CAA 0 issue "digicert.com"
  example.com. IN CAA 0 iodef "mailto:security@example.com"

Semantics:
  - issue "letsencrypt.org"  -> only Let's Encrypt can issue
  - issue ";"                -> no CA can issue
  - issuewild "x"            -> control wildcard issuance
  - iodef                    -> where to report violations

Before issuing, CA queries CAA. If not authorized, CA must refuse.
Mandatory for all public CAs since 2017.
```

### Certificate Authorities Overview

```
Let's Encrypt:
  - Free, automated, ACME protocol
  - 90-day certs (encourages automation)
  - Most issued CA globally (>300M active certs)
  - Operated by ISRG (non-profit)

DigiCert:
  - Commercial, enterprise-focused
  - EV (Extended Validation) offerings
  - Absorbed Symantec's CA business

Sectigo (formerly Comodo):
  - Volume commercial CA

GoDaddy, Entrust, GlobalSign - other major players

Google Trust Services:
  - Google's CA, free via ACME

Cloudflare:
  - Issues certs via Let's Encrypt or DigiCert
  - Free SSL for all customers
```

### ACME Protocol

```
ACME = Automated Certificate Management Environment (RFC 8555)
Used by Let's Encrypt, ZeroSSL, Google Trust Services, etc.

Client flow (e.g., certbot):
  1. Create account (generate keypair)
  2. Place order: "I want cert for example.com"
  3. CA sends challenge (HTTP-01, DNS-01, TLS-ALPN-01)
  4. Client proves control of domain
  5. CA validates challenge
  6. Client submits CSR
  7. CA issues cert (submits to CT logs)
  8. Client downloads cert

Renewal = same flow, fully automated.
```

### CT Log Ecosystem

```
Log operators (run CT logs):
  - Google (multiple Argon shards)
  - Cloudflare (Nimbus)
  - DigiCert (Yeti, Nessie)
  - Let's Encrypt (Oak)
  - TrustAsia (Asia-focused)

Logs are:
  - Sharded by year (e.g., Argon2024, Argon2025)
  - Append-only
  - Publicly auditable

Monitoring tools:
  - crt.sh (free, Sectigo)
  - Cert Spotter (SSLMate)
  - Facebook CT Monitor
  - Google's CT search
  - Censys
```

### CT Issues and Criticism

```
Pros:
  + Detects mis-issuance
  + Post-incident forensics
  + Accountability for CAs

Cons:
  - Privacy: cert for internal.example.com is public
    (mitigation: precert signed by log, name redacted is NOT standard)
  - Log size: billions of entries
  - Single point of failure if log goes down
```

## PHP/Laravel ilə İstifadə

Laravel app özü CT-ni implement etmir - CA edir. Amma monitoring, CAA idarəetməsi, və alerts Laravel-də qurulub.

### Monitor Your Domains via crt.sh API

```php
// app/Services/CtMonitor.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class CtMonitor
{
    public function certsForDomain(string $domain): array
    {
        $response = Http::get('https://crt.sh/', [
            'q'      => $domain,
            'output' => 'json',
        ]);

        if (! $response->ok()) return [];

        return collect($response->json())
            ->map(fn ($cert) => [
                'id'           => $cert['id'],
                'issuer'       => $cert['issuer_name'],
                'common_name'  => $cert['common_name'],
                'entries'      => $cert['name_value'],
                'not_before'   => $cert['not_before'],
                'not_after'    => $cert['not_after'],
            ])
            ->toArray();
    }
}
```

### Scheduled CT Monitoring Command

```php
// app/Console/Commands/MonitorCtLogs.php
namespace App\Console\Commands;

use App\Models\KnownCertificate;
use App\Notifications\UnauthorizedCertDetected;
use App\Services\CtMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class MonitorCtLogs extends Command
{
    protected $signature = 'ct:monitor';
    protected $description = 'Check CT logs for unauthorized certs';

    public function handle(CtMonitor $monitor)
    {
        $authorizedIssuers = config('security.authorized_issuers', [
            "Let's Encrypt",
            'DigiCert',
        ]);

        foreach (config('security.monitored_domains') as $domain) {
            foreach ($monitor->certsForDomain($domain) as $cert) {
                $known = KnownCertificate::firstOrCreate(
                    ['crt_sh_id' => $cert['id']],
                    [
                        'domain' => $domain,
                        'issuer' => $cert['issuer'],
                        'not_after' => $cert['not_after'],
                    ]
                );

                if ($known->wasRecentlyCreated) {
                    // New cert detected - verify issuer
                    $authorized = collect($authorizedIssuers)
                        ->contains(fn ($iss) => str_contains($cert['issuer'], $iss));

                    if (! $authorized) {
                        Notification::route('slack', config('services.slack.security'))
                            ->notify(new UnauthorizedCertDetected($cert, $domain));
                        $this->error("ALERT: unauthorized cert for {$domain} by {$cert['issuer']}");
                    } else {
                        $this->info("New cert for {$domain} by {$cert['issuer']} - OK");
                    }
                }
            }
        }
    }
}
```

### Verify SCT Count in Received Cert

```php
// app/Services/SctChecker.php
namespace App\Services;

class SctChecker
{
    public function getSctCount(string $hostname, int $port = 443): int
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => true,
                'SNI_enabled'       => true,
            ],
        ]);

        $client = @stream_socket_client(
            "ssl://{$hostname}:{$port}",
            $errno, $errstr, 10,
            STREAM_CLIENT_CONNECT, $context
        );

        if (! $client) return 0;

        $params = stream_context_get_params($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);

        if (! $cert) return 0;

        // Get OpenSSL cert info
        $info = openssl_x509_parse($cert);

        // Look for SCT extension
        $sctExt = $info['extensions']['ct_precert_scts'] ?? '';
        // Count SCT entries (simplified: one per line in openssl format)
        return substr_count($sctExt, 'Signed Certificate Timestamp');
    }
}
```

### Verify Your Domain's CAA Records

```php
// app/Services/CaaValidator.php
namespace App\Services;

class CaaValidator
{
    public function caaRecords(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_CAA);
        if (! $records) return [];

        return collect($records)->map(fn ($r) => [
            'flags' => $r['flags'] ?? 0,
            'tag'   => $r['tag']   ?? '',
            'value' => $r['value'] ?? '',
        ])->toArray();
    }

    public function isIssuerAuthorized(string $domain, string $caDomain): bool
    {
        foreach ($this->caaRecords($domain) as $rec) {
            if ($rec['tag'] === 'issue' && str_contains($rec['value'], $caDomain)) {
                return true;
            }
        }
        return false;
    }
}
```

### Notification Class

```php
// app/Notifications/UnauthorizedCertDetected.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class UnauthorizedCertDetected extends Notification
{
    use Queueable;

    public function __construct(public array $cert, public string $domain) {}

    public function via(): array { return ['slack']; }

    public function toSlack(): SlackMessage
    {
        return (new SlackMessage)
            ->error()
            ->content('Unauthorized certificate detected!')
            ->attachment(function ($a) {
                $a->title("Cert for {$this->domain}")
                  ->fields([
                      'Issuer'      => $this->cert['issuer'],
                      'Common Name' => $this->cert['common_name'],
                      'Not Before'  => $this->cert['not_before'],
                      'Not After'   => $this->cert['not_after'],
                      'crt.sh'      => "https://crt.sh/?id={$this->cert['id']}",
                  ]);
            });
    }
}
```

### Scheduling

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('ct:monitor')->everyThirtyMinutes();
}
```

## Interview Sualları (Q&A)

### 1. Certificate Transparency nədir və niyə vacibdir?

**Cavab:** CT sertifikatların public, append-only log-lara yazılmasını tələb edən framework-dür. Məqsəd: rogue və ya mis-issued sertifikatları erkən aşkar etmək. Əvvəl CA kompromis olsa, sizin domain üçün saxta cert issue oluna bilərdi və siz bilmirdiniz. CT ilə hər cert public-dir, monitor edə bilərsiniz. 2018-dən Chrome məcburi edir.

### 2. SCT nədir?

**Cavab:** Signed Certificate Timestamp - CT log-un cert-i qəbul etdiyinin kriptoqrafik quittance-i. Log CA-ya "mən bu cert-i T zamanında log etdim" imzalı qaytarır. Browser həmin SCT-ni handshake-də yoxlayır. Chrome 2+ müxtəlif log-dan SCT tələb edir.

### 3. CAA record nədir?

**Cavab:** DNS record-udur ki, "bu domain üçün yalnız göstərilən CA sertifikat issue edə bilər" deyir. 2017-dən bütün public CA-lar issuance-dan əvvəl CAA yoxlamalıdır. Misal: `example.com. IN CAA 0 issue "letsencrypt.org"`. Bu, rogue CA-dan hər hansı issuance-ın qarşısını alır.

### 4. Let's Encrypt niyə populyardır?

**Cavab:** (1) Pulsuzdur, (2) Tam avtomatlaşdırılıb (ACME protocol), (3) 90-günlük TTL (avtomatik renewal stimul), (4) Open-source infrastructure, (5) Qlobal çatımlıdır. 2026 itibarilə 300M+ aktiv sertifikat issue edir - internetin üçdə biri.

### 5. ACME nədir?

**Cavab:** Automated Certificate Management Environment (RFC 8555) - avtomatik cert issue və renewal protokolu. Let's Encrypt, ZeroSSL, Google Trust Services istifadə edir. Client (certbot, Caddy, cert-manager) CA ilə challenge protocol (HTTP-01, DNS-01, TLS-ALPN-01) vasitəsilə domain ownership-i isbat edir, cert alır.

### 6. CT log necə Merkle tree istifadə edir?

**Cavab:** Log append-only Merkle tree-dir. Hər cert yarpaq, yarpaqlar birləşdirilib Signed Tree Head (STH) yaradır. Properties: (1) əlavə edilə bilər, silinə bilməz, (2) kriptoqrafik olaraq verifikasiya edilə bilər, (3) inclusion proof - "bu cert log-dadır", (4) consistency proof - "log tutarlı şəkildə böyüyür".

### 7. CT monitor necə qurmaq olar?

**Cavab:** (1) crt.sh, Cert Spotter, Censys kimi hazır servis istifadə et, (2) Öz tool-unu yaz: CT log-lara subscribe ol (Google's CTL API), hər yeni entry-ni yoxla, öz domain-lərin üçün filter. (3) Authorized issuer listesi tut, kənar issuer olsa alert ver. Laravel scheduled command + Slack notification ilə qurmaq olar.

### 8. CT hansı privacy problemlərini yaradır?

**Cavab:** Bütün cert-lər public-dir, o cümlədən internal subdomain-lər (`jenkins.internal.example.com`). Reconnaissance üçün istifadə oluna bilər - attacker subdomain-lərinizi crt.sh-dan tapa bilər. Mitigation: wildcard cert (`*.example.com`) istifadə et, internal-ə ayrı private CA qur.

### 9. SCT neçə yerdə yerləşdirilir?

**Cavab:** 3 üsul: (1) X.509 extension olaraq cert-in özündə (ən yayılmış), (2) TLS handshake-də extension kimi (server cert-i göndərəndə), (3) OCSP stapling vasitəsilə. Chrome hər hansı birinin 2+ müxtəlif log-dan mövcudluğunu tələb edir.

### 10. Log qəza alsa və ya kompromis olsa nə olar?

**Cavab:** Chrome log-un "qualified" statusunu saxlayır. Log problem olsa, Chrome trust-dan çıxarır (disqualified). Yeni issue olunan cert-lər həmin log-u SCT kimi istifadə edə bilməz. Amma artıq issue olunmuş cert-lər expire olana kimi işləyir. Belə ki, multi-log redundancy vacibdir - CA minimum 2 log-a submit edir.

## Best Practices

1. **Domain-larını monitor et** - crt.sh, Cert Spotter və ya Laravel ilə öz monitor-unu qur.
2. **CAA record-lar əlavə et** - yalnız icazəli CA-ları göstər, mis-issuance qarşısını al.
3. **Authorized issuer listesi tut** - konfiqurasiyada, xaricdən issue olsa alert.
4. **Wildcard cert düşün internal-lər üçün** - `*.internal.example.com` CT-də tək entry, alt subdomain-lər gizli qalır.
5. **Short-lived cert istifadə et** - Let's Encrypt 90 gün, avtomatik renew. Expire-dən qorun.
6. **Cert expiry monitoring qur** - 30 gün qalanda alert (Uptime Robot, Prometheus blackbox, custom Laravel command).
7. **Ayrı internal CA qur** - həssas internal service-lər üçün public CT-yə düşməyən private PKI.
8. **CT disqualification-a hazırlı ol** - log və ya CA kompromis olsa re-issue lazım ola bilər.
9. **Security.txt əlavə et** - `/.well-known/security.txt` faylında incident contact.
10. **Audit log-u tut** - issue olunan cert-lər üçün daxili reyestr saxla, xarici CT ilə müqayisə et.
