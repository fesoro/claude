# Data Encryption at Rest and in Transit (Senior ⭐⭐⭐)

## İcmal
Data encryption — məlumatın icazəsiz şəxslər tərəfindən oxunmaması üçün şifrələnməsidir. "At rest" — saxlanma zamanı (database, disk, backup), "in transit" — ötürülmə zamanı (HTTP, API, internal service communication). Interview-da bu mövzu Senior developer-ın yalnız "HTTPS istifadə edirəm" demədən, şifrələmənin hər qatını nə dərəcədə başa düşdüyünü yoxlayır.

## Niyə Vacibdir
Data breach zamanı şifrələnmiş data praktiki olaraq dəyərsizdir — attacker məlumatı istifadə edə bilmir. PCI DSS, HIPAA, GDPR kimi compliance standartları şifrələməni tələb edir. İnterviewerlər developer-ın key management-ı, algorithm seçimini, TLS konfiqurasiyasını düşünüb-düşünmədiyini görmək istəyir. Şifrələmə var olmaq yetərli deyil — düzgün implement edilməlidir: zəif algoritm, pis key management, sertifikat yoxlamasının bypass edilməsi şifrələməni faydasız edir.

## Əsas Anlayışlar

**Encryption at Rest:**
- **Transparent Data Encryption (TDE)**: Database mühərriki disk üzərindəki datanı avtomatik şifrələyir — PostgreSQL, MySQL Enterprise, SQL Server, Oracle dəstəkləyir. Disk oğurlanmasına qarşı qoruyur, amma database-ə daxil olan kəs şifrəsiz görür.
- **Column-level encryption**: Xüsusilə sensitive sütunları (SSN, kredit kartı, tibbi məlumat) database engine-dən asılı olmadan application qatında şifrələmək. Database admin belə plain text görmir.
- **Application-level encryption**: Data database-ə yazılmadan əvvəl şifrələnir — developer tam nəzarət sahibidir, lakin key management mürəkkəbdir. `encrypt()` before write, `decrypt()` after read.
- **Full disk encryption**: LUKS (Linux), BitLocker (Windows) — fiziki server oğurlanırsa data qorunur. Cloud: AWS EBS encryption, Google Cloud Disk encryption.
- **Backup encryption**: Backup faylları şifrələnməlidir — çox zaman unudulur. Açar backup-dan ayrı saxlanmalıdır.
- **Key rotation**: Şifrələmə açarları müntəzəm dəyişdirilməlidir — köhnə açarla şifrələnmiş datanın yenidən şifrələnməsi (re-encryption) lazım ola bilər.

**Encryption in Transit:**
- **TLS (Transport Layer Security)**: HTTPS-in özəyi. TLS 1.3 tövsiyə edilir — 1-RTT handshake, daha güclü cipher suite. TLS 1.0/1.1 artıq deprecated. SSL tamamilə köhnəlmiş, istifadə edilməməlidir.
- **TLS handshake**: Client-server açar mübadiləsi (key exchange), sertifikat doğrulaması, session key yaradılması. TLS 1.3-də 1 round-trip kifayət edir.
- **Certificate validation**: Sertifikatın etibarlılığı, imzası (CA chain), CN/SAN uyğunluğu, revocation (OCSP, CRL) yoxlanması. `verify=false` production-da kritik security problemidir.
- **mTLS (mutual TLS)**: Hər iki tərəf öz sertifikatını təqdim edir — server client-i doğrulayır. Microservices arası kommunikasiya üçün ideal.
- **Certificate Pinning**: Xüsusi sertifikatı ya public key-i tətbiqə "pin" etmək — MITM-ə qarşı əlavə qat. Mobile app-larda istifadə olunur.
- **HSTS Preloading**: Brauzer HTTP-dən HTTPS-ə redirect-i belə gözləmir — birbaşa HTTPS istifadə edir.

**Kriptografik anlayışlar:**
- **Symmetric encryption**: Eyni açarla şifrələmə/deşifrələmə. AES-256-GCM — hal-hazırda ən geniş tövsiyə olunan. Sürətli, güclü, authenticated encryption.
- **Asymmetric encryption**: Public key ilə şifrələ, private key ilə aç. RSA-2048+, ECDSA (P-256). TLS handshake-də key exchange üçün istifadə olunur.
- **AES-256-GCM**: Authenticated encryption — şifrələmə + bütövlük (integrity) yoxlaması birlikdə. GCM mode IV/nonce tələb edir.
- **Hashing vs Encryption**: Hash bir istiqamətlidir — şifrə üçün. Encryption iki istiqamətlidir — data üçün. `md5()` ilə "şifrələmə" etmək — bu hash-dir, encryption deyil, üstəlik md5 sındırılmışdır.
- **IV (Initialization Vector) / Nonce**: Hər şifrələmə əməliyyatı üçün kriptografik random IV — eyni data + eyni key → eyni ciphertext problemi qarşısını alır.
- **Key Derivation Function (KDF)**: Şifrədən kriptografik açar əldə etmək — PBKDF2, Argon2, bcrypt. Brute force hücumunu yavaşlatır.
- **Envelope encryption**: Data bir açarla (DEK — Data Encryption Key), həmin açar başqa bir açarla (KEK — Key Encryption Key) şifrələnir. AWS KMS, HashiCorp Vault bu modeli istifadə edir.
- **Authenticated Encryption (AEAD)**: Şifrələmə + integrity yoxlaması birlikdə. AES-GCM, ChaCha20-Poly1305. Authentication tag olmadan ciphertext dəyişdirilib-dəyişdirilmədiyini bilmək olmur.
- **Forward Secrecy (PFS)**: Hər session üçün yeni ephemeral key. Session key sızdısa, əvvəlki session-lar deşifrə olunmur. ECDHE key exchange.

**Key Management:**
- **AWS KMS**: Cloud-based managed key service. Key heç vaxt KMS-dən çıxmır — şifrələmə/deşifrələmə KMS tərəfindən edilir.
- **HashiCorp Vault**: Self-hosted açar idarəsi, dynamic secrets, key rotation, encryption as a service.
- **Key rotation automated**: Açar rotasiyası avtomatlaşdırılmalıdır — manual proses unudulur. 90 gün standart müddətdir.
- **Secret sprawl**: Açarların koda, config fayllara yayılması — mütləq qarşısı alınmalıdır. `.env`-də belə kritik key-lər olmamamalıdır.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"HTTPS istifadə edirəm" yalnız in-transit-i əhatə edir. Güclü cavab at-rest şifrələməsini — xüsusilə column-level encryption, key management, backup encryption — da izah edir. "Envelope encryption" anlayışını bilmək Senior developer-ı orta cavabdan ayırır. Şifrələnmiş sütunda search etmək kimi trade-off-ı qeyd etmək əla cavab əlamətidir.

**Hansı konkret nümunələr gətirmək:**
- "Kredit kartı son 4 rəqəmini saxlayırıq, tam nömrə heç saxlanmır — PCI DSS tokenization"
- "Database-dəki SSN sütununu Laravel-in encryption helper-i ilə şifrələyirik, key AWS KMS-dədir"
- "Internal microservice-lər arası kommunikasiya mTLS ilə qorunur — Istio service mesh istifadə edirik"

**Follow-up suallar interviewerlər soruşur:**
- "Şifrələmə açarı sızdı. Nə edərsiniz? Recovery planı?"
- "Şifrələnmiş sütunda search etmək lazımdır. Bunu necə edərdiniz?"
- "TLS sertifikatı expire oldu. Bu problemi necə önlərdiniz?"
- "TLS 1.2 vs 1.3 fərqi nədir?"
- "GDPR right to erasure — şifrəni silmək data silməyə bərabər sayılırmı?"

**Red flags — pis cavab əlamətləri:**
- `CURLOPT_SSL_VERIFYPEER = false` — "development-da işləyir" deyib production-a aparmaq
- Şifrələmə açarını `.env`-də plain text saxlamaq
- `md5()` ilə "şifrələmə" — hash funksiyasını encryption kimi istifadə etmək
- Backup fayllarını şifrəsiz saxlamaq
- Bütün sütunları şifrələmək — "çox şifrələmə yaxşıdır" — performance cost-u və search problemi düşünməmək

## Nümunələr

### Tipik Interview Sualı
"Tətbiqinizdə istifadəçilərin tibbi məlumatlarını (HIPAA scope) saxlamaq lazımdır. Encryption strategiyanız necə olardı?"

### Güclü Cavab
"HIPAA scope-u nəzərə alaraq çox qatlı yanaşma lazımdır.

In-transit üçün — bütün kommunikasiya TLS 1.3 üzərindən. Nginx-də `ssl_protocols TLSv1.2 TLSv1.3` — 1.0/1.1 tamamilə deaktiv. Internal service-lər arası mTLS. Certificate expiry monitoring — Certbot + auto-renew.

At-rest üçün — database server-inin TDE-si aktiv olsun — disk/backup qatında baza qoruma. Amma bu kifayət deyil — database admin hər şeyi görür. Tibbi məlumatlar (diagnoz, müalicə, prescriptions) column-level encryption — application yazmazdan əvvəl şifrələyir. Database admin ciphertext görür, plain text yox.

Key management üçün — açarları kodda ya `.env`-də saxlamaq olmaz. AWS KMS istifadə edirik. Envelope encryption: tibbi data bir DEK ilə şifrələnir, DEK özü KMS-dəki KEK ilə şifrələnir. DEK yaddaşdan istifadə olunandan sonra silinir.

Şifrələnmiş sütunlarda search problemi — biz axtarışa ehtiyac olan field-lər üçün blind index (searchable encryption, HMAC hash of value) istifadə edirik.

Backup-lar da şifrələnir, şifrələmə açarları backup-dan tamamilə ayrı saxlanır.

Key rotation — 90 gündə bir avtomatik rotation. Köhnə açarla şifrələnmiş data background job ilə yenidən şifrələnir.

GDPR 'right to erasure' gəldikdə — tibbi məlumatlar üçün açarı silmək datanı kriptografik olaraq silinmiş edir — 'crypto shredding' texnikası."

### Kod Nümunəsi — Laravel Column-Level Encryption

```php
// app/Models/PatientRecord.php
use Illuminate\Database\Eloquent\Casts\Attribute;

class PatientRecord extends Model
{
    // Laravel built-in encrypted cast — APP_KEY ilə AES-256-CBC
    protected $casts = [
        'diagnosis'    => 'encrypted',
        'prescription' => 'encrypted',
        'notes'        => 'encrypted:array', // şifrələnmiş JSON
    ];

    // Əlavə nəzarət üçün manual encryption attribute
    protected function socialSecurityNumber(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? Crypt::decryptString($value) : null,
            set: fn($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    // Blind index — şifrələnmiş field-də axtarış üçün
    // Əsl dəyəri encrypt et, HMAC hash-ını ayrı sütunda saxla
    protected function emailBlindIndex(): Attribute
    {
        return Attribute::make(
            set: fn($value) => hash_hmac('sha256', strtolower($value), config('app.blind_index_key')),
        );
    }
}

// Migration
Schema::create('patient_records', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('patient_id');
    $table->text('diagnosis');          // encrypted ciphertext
    $table->text('prescription');       // encrypted ciphertext
    $table->string('email_blind_index', 64)->nullable()->index(); // HMAC hash — axtarış üçün
    $table->timestamps();
});

// Axtarış: plain text → blind index → database query
$blindIndex = hash_hmac('sha256', strtolower($email), config('app.blind_index_key'));
$record = PatientRecord::where('email_blind_index', $blindIndex)->first();
```

### Kod Nümunəsi — AWS KMS Envelope Encryption

```php
// app/Services/EncryptionService.php
use Aws\Kms\KmsClient;

class EncryptionService
{
    private KmsClient $kms;
    private string $keyId;

    public function __construct()
    {
        $this->kms   = new KmsClient(['version' => 'latest', 'region' => config('aws.region')]);
        $this->keyId = config('aws.kms_key_id');
    }

    public function encrypt(string $plaintext): array
    {
        // KMS-dən ephemeral data encryption key (DEK) al
        $result      = $this->kms->generateDataKey(['KeyId' => $this->keyId, 'KeySpec' => 'AES_256']);
        $dek         = $result['Plaintext'];           // plain DEK — yalnız yaddaşda
        $encryptedDek = $result['CiphertextBlob'];     // şifrələnmiş DEK — saxla

        // AES-256-GCM ilə şifrələ
        $iv         = random_bytes(12); // GCM üçün 96-bit IV
        $tag        = '';
        $ciphertext = openssl_encrypt(
            $plaintext, 'AES-256-GCM', $dek, OPENSSL_RAW_DATA, $iv, $tag, '', 16
        );

        // Plain DEK-i yaddaşdan sil — kritik!
        sodium_memzero($dek);

        return [
            'ciphertext'    => base64_encode($ciphertext),
            'encrypted_dek' => base64_encode($encryptedDek),
            'iv'            => base64_encode($iv),
            'tag'           => base64_encode($tag),
        ];
    }

    public function decrypt(array $encryptedData): string
    {
        // KMS-dən şifrələnmiş DEK-i aç
        $result = $this->kms->decrypt([
            'CiphertextBlob' => base64_decode($encryptedData['encrypted_dek']),
        ]);
        $dek = $result['Plaintext'];

        $plaintext = openssl_decrypt(
            base64_decode($encryptedData['ciphertext']),
            'AES-256-GCM',
            $dek,
            OPENSSL_RAW_DATA,
            base64_decode($encryptedData['iv']),
            base64_decode($encryptedData['tag'])
        );

        sodium_memzero($dek);

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed — data may be tampered');
        }

        return $plaintext;
    }
}
```

### Kod Nümunəsi — TLS Konfiqurasiyası Nginx

```nginx
# /etc/nginx/conf.d/ssl.conf

# Yalnız TLS 1.2 və 1.3 — köhnə versiyalar deaktiv
ssl_protocols TLSv1.2 TLSv1.3;

# Güclü cipher suite-lar — Forward Secrecy dəstəkləyir
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
ssl_prefer_server_ciphers off; # TLS 1.3-də client seçir

# HSTS — 2 il
add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;

# OCSP Stapling — sertifikat revocation sürətli yoxlanır
ssl_stapling on;
ssl_stapling_verify on;
resolver 1.1.1.1 8.8.8.8 valid=300s;

# Session — TLS 1.3-də session ticket-lar deaktiv (PFS üçün)
ssl_session_tickets off;
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 1d;

# DH params — 2048-bit minimum
ssl_dhparam /etc/nginx/dhparam.pem;
```

### Kod Nümunəsi — Let's Encrypt + Auto-Renewal

```bash
# Certbot ilə SSL sertifikat al
certbot certonly --nginx -d example.com -d www.example.com --email admin@example.com --agree-tos

# Cron — hər gün sertifikat yoxla, expire olmadan 30 gün əvvəl yenilə
echo "0 0 * * * certbot renew --quiet --post-hook 'nginx -s reload'" | crontab -

# Expiry monitoring — alert göndər
certbot certificates --domain example.com | grep "Expiry Date"

# systemd timer ilə (daha etibarlı)
# certbot.timer systemd-də default aktivdir Ubuntu-da
systemctl status certbot.timer
```

### Attack Nümunəsi — `verify=false` MITM

```php
// ❌ Development-da "işləmək üçün" SSL yoxlamasını söndürmək
$response = Http::withOptions([
    'verify' => false, // SSL sertifikat yoxlaması SÖNDÜRÜLDÜ
])->get('https://api.partner.com/data');

// Bu production-a getsə:
// 1. Attacker network-də MITM qurur
// 2. Öz sertifikatı ilə saxta HTTPS endpoint qaldırır
// 3. Laravel verify=false olduğu üçün saxta sertifikatı qəbul edir
// 4. Bütün data attacker-dən keçir — görünür, dəyişdirilir
// 5. Kredit kartı, API key, hər şey sıxır

// ✅ Həmişə verify=true (default)
$response = Http::get('https://api.partner.com/data');

// Özünüzün CA-nız varsa (internal service)
$response = Http::withOptions([
    'verify' => '/path/to/your/ca-bundle.pem',
])->get('https://internal-service.company.com/api');

// ❌ md5 ilə "şifrələmə"
$encrypted = md5($sensitiveData); // Bu encryption deyil, hash-dir!
// md5 sındırılmışdır, rainbow table mövcuddur
// md5('password123') = 482c811da5d5b4bc6d497ffa98491e38 — hər yerdə tapılır

// ✅ Düzgün: AES encryption
$encrypted = Crypt::encryptString($sensitiveData); // AES-256-CBC + HMAC
$decrypted = Crypt::decryptString($encrypted);
```

### İkinci Nümunə — Crypto Shredding (GDPR)

```
Ssenari: GDPR "right to erasure" gəldi, şifrələnmiş data üçün

Problematik yanaşma:
- Hər backup-da istifadəçinin datası var
- Backup-dan silmək texniki cəhətdən çox çətindir
- 7 yıllıq backup-lardan silmək: mümkünsüz

Crypto Shredding həlli:
1. Hər istifadəçi üçün ayrı encryption key (user DEK)
2. User DEK AWS KMS-də saxlanır
3. İstifadəçinin bütün data-sı öz DEK-i ilə şifrələnir
4. Backup-larda yalnız ciphertext var

Right to erasure geldi:
- AWS KMS-dən user-in DEK-ini sil
- Ciphertext backup-larda qalır, amma
- DEK olmadan deşifrə etmək kriptografik olaraq mümkünsüz
- Data "silinmiş" sayılır — GDPR-ə uyğundur

Trade-off:
+ Backup-lardan fiziki silmə lazım deyil
+ Audit trail: DEK silinməsi qeyd edilir
- Key management mürəkkəbləşir
- Hər user üçün ayrı DEK → KMS xərci artır
- DEK itirilsə data itirilir
```

## Praktik Tapşırıqlar

- Database-inizdə hansı sütunlar şifrələnməlidir? Hal-hazırda şifrələnibmi? (SSN, kredit kartı, tibbi məlumat)
- Şifrələmə açarları harada saxlanır? `.env`-dədirsə, AWS KMS-ə keçid planı hazırlayın
- TLS sertifikatınız nə vaxt expire olur? Avtomatik yenilənirmi? Monitoring varmı?
- Backup fayllarınız şifrələnirmi? Şifrələmə açarı backup ilə eyni yerdədirsə problem var
- `CURLOPT_SSL_VERIFYPEER = false` ya da `'verify' => false` olan kodu tapın — düzəldin
- Şifrələnmiş sütunda axtarış etmək lazımdırsa blind index strategiyasını implementasiya edin
- Nginx-dən `openssl s_client -connect yourdomain.com:443` ilə TLS versiyasını yoxlayın

## Əlaqəli Mövzular
- `07-password-hashing.md` — Şifrələmə (encryption) ilə hashing fərqi — şifrə heç vaxt encrypt edilməməlidir
- `08-secrets-management.md` — Şifrələmə açarlarını secret kimi idarə etmək
- `10-security-headers.md` — HSTS in-transit şifrələməni browser tərəfindən enforce edir
- `11-least-privilege.md` — Şifrələmə açarlarına minimum icazə prinsipi
- `15-threat-modeling.md` — Threat model-də data theft vektoru şifrələmə ilə məhdudlaşdırılır
