# Threat Modeling (Architect ⭐⭐⭐⭐⭐)

## İcmal
Threat modeling — sistemin potensial security zəifliklərini, hücum vektorlarını və riskləri kod yazılmadan əvvəl, dizayn mərhələsində sistematik şəkildə müəyyən etmə prosesidir. Microsoft STRIDE, OWASP Threat Dragon, LINDDUN, PASTA kimi metodologiyalar bu prosesi strukturlaşdırır. Interview-da bu mövzu Architect səviyyəsindəki candidate-dən gözlənilir — proaktiv security düşüncəsi, sistemin geniş attack surface-ni görmə bacarığı yoxlanılır.

## Niyə Vacibdir
Breach-lərin 80%-i proqnozlaşdırıla bilən hücum vektorlarından qaynaqlanır — threat modeling bunları əvvəlcədən müəyyən edir. Development zamanı tapılan security problemi production-dakından 100x ucuz düzəldilir. Compliance standartları (PCI DSS, ISO 27001, SOC 2 Type II) threat modelini tələb edir. İnterviewerlər Architect-dən yalnız "bunu necə qoruyacağam" deyil, "bu sistemdə kim, nə üçün, hansı yolla hücum edər" sualını soruşmağı gözləyir — bu perspektiv fərqi Architect ilə Senior developer-ı ayırır.

## Əsas Anlayışlar

**Threat Modeling metodologiyaları:**
- **STRIDE (Microsoft)**: Ən geniş yayılmış metodologiya — 6 kateqoriya: Spoofing, Tampering, Repudiation, Information Disclosure, Denial of Service, Elevation of Privilege
- **PASTA (Process for Attack Simulation and Threat Analysis)**: Risk-centric yanaşma — 7 mərhələ, iş impact-ını ön plana çəkir
- **LINDDUN**: Privacy-centric — Linkability, Identifiability, Non-repudiation, Detectability, Disclosure, Unawareness, Non-compliance
- **VAST (Visual Agile Simple Threat)**: Agile mühit üçün uyğunlaşdırılmış, automation-friendly
- **Attack Tree**: Hücumun ağac strukturunda modelləşdirilməsi — kök node hücum hədəfidir, child node-lar hücum yolları

**STRIDE kateqoriyaları:**
- **Spoofing**: Kimlik saxtakarlığı — başqa user/service kimi davranmaq. Mitigasiya: authentication, MFA
- **Tampering**: Datanı, kodu, kommunikasiyanı dəyişdirmək. Mitigasiya: integrity check, digital signature, HMAC
- **Repudiation**: Əməliyyatı inkar etmək — "mən etmədim". Mitigasiya: audit log, digital signature, non-repudiation
- **Information Disclosure**: Gizli məlumatın ifşası. Mitigasiya: encryption, access control, data classification
- **Denial of Service**: Servisi əlçatmaz etmək. Mitigasiya: rate limiting, auto-scaling, circuit breaker
- **Elevation of Privilege**: Az icazəli hesabdan daha çox icazə qazanmaq. Mitigasiya: PoLP, sandboxing, input validation

**Threat Modeling prosesi (4 sual):**
1. **Nə qururuq?** (What are we building?) — DFD (Data Flow Diagram), architecture diagram, trust boundaries
2. **Nə səhv gedə bilər?** (What can go wrong?) — STRIDE analizi, attack tree, brainstorming
3. **Bununla nə edəcəyik?** (What are we going to do about it?) — mitigasiya strategiyası, risk accept/transfer/mitigate
4. **İşimizi düzgün etdikmi?** (Did we do a good enough job?) — review, validation, re-assessment

**Trust Boundaries:**
- **Trust boundary**: İki fərqli güvən səviyyəsinə sahib komponent arasındakı xətt — internet → DMZ → internal network
- **Data Flow Diagram (DFD)**: Sistemdəki data axışını və trust boundary-ləri göstərən diaqram
- **Attack surface**: Sistemə giriş nöqtələrinin məcmusu — API endpoint-lər, file upload, admin panel, third-party integration

**Risk dəyərləndirməsi:**
- **DREAD**: Damage, Reproducibility, Exploitability, Affected users, Discoverability — risk skor hesablamaq üçün
- **CVSS (Common Vulnerability Scoring System)**: Standart vulnerability şiddəti metrikası — 0.0-10.0
- **Risk matrix**: Likelihood × Impact — hansı threat-lərin prioritetləndirilməsi lazımdır
- **Risk appetite**: Təşkilatın qəbul etməyə hazır olduğu risk səviyyəsi — bu biznes qərarıdır
- **Residual risk**: Bütün mitigasiyalardan sonra qalan risk — sıfıra çatdırmaq mümkün deyil

**Threat Modeling tool-ları:**
- **OWASP Threat Dragon**: Pulsuz, open-source, DFD-based
- **Microsoft Threat Modeling Tool**: STRIDE-focused, Windows-based
- **IriusRisk**: Enterprise-grade, automated threat library
- **draw.io**: Sadə diaqram aləti — DFD çəkmək üçün kifayətdir
- **Miro/Confluence**: Komanda ilə collaborative threat modeling üçün

**Advanced anlayışlar:**
- **Abuse cases**: Normal use case-lərin zərərli istifadəsi — "istifadəçi faylı yüklər" → "attacker malware yüklər"
- **Security requirements**: Threat model-dən çıxan konkret security tələbləri — acceptance criteria kimi
- **Threat intelligence**: Sənayedəki real hücum pattern-lərini threat model-ə inteqrasiya etmək — MITRE ATT&CK
- **MITRE ATT&CK**: Real dünya APT (Advanced Persistent Threat) taktikalarının kataloqu — enterprise, cloud, ICS matrislər
- **Red Team / Blue Team**: Red team hücum edir, blue team müdafiə edir — threat model-i real sınaqdan keçirmək
- **Purple Team**: Red + Blue birgə işləyir — bilik paylaşımı
- **Security Champion**: Hər development komanda-da security bilikli developer — threat modeling-ə owner

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Threat modeling-i nəzəri olaraq izah etmək yetərli deyil. Güclü cavab konkret bir sistem (payment service, user authentication, file upload) üçün STRIDE analizi aparır, trust boundary-ləri müəyyən edir, top threat-ləri sıralayır. "Mən bu sistemdəki ən böyük riski X hesab edirəm, çünkü..." — bu analitik yanaşma Architect səviyyəsini nümayiş etdirir.

**Hansı konkret nümunələr gətirmək:**
- "Yeni payment feature üçün threat model apardıq, 3 saatlıq session-da 12 threat tapdıq — 3-ü critical idi, development başlamadan həll etdik"
- "E-commerce platformasının checkout flow-u üçün STRIDE analizi etdik — Tampering (order total dəyişdirmək) ən yüksək risk idi"
- "Admin panel üçün ayrı trust boundary müəyyən etdik — normal user credential-ları ilə əlçatmaz"

**Follow-up suallar:**
- "Agile sprint-lərdə threat modeling-i necə inteqrasiya edərdiniz — hər feature üçün ayrıca?"
- "Threat modeling session-una kimləri dəvət edərdiniz? Yalnız developers, ya da PM, QA, ops da?"
- "Threat model-in aktuallığını necə qoruyursunuz — sistem dəyişdikcə?"

**Red flags (pis cavab əlamətləri):**
- "Threat modeling yalnız pentester-ların işidir"
- Yalnız nəzəri anlayışları saymaq, real sistem üçün tətbiqetmə nümunəsi verməmək
- Risk dəyərləndirməsini bilməmək — "bütün threat-lər bərabər dərəcədə kritikdir" demək
- Threat modeling-i bir dəfəlik proses kimi görmək — "bir dəfə etdik, bitdi"

## Nümunələr

### Tipik Interview Sualı
"Sizə bir e-commerce platformasının ödəniş sisteminin dizaynı verilir. Bu sistem üçün necə threat modeling aparardınız?"

### Güclü Cavab
"Mən strukturlaşdırılmış STRIDE metodologiyası ilə başlayardım.

**Addım 1 — Nə qururuq:**
İlk öncə Data Flow Diagram çəkərdim: Browser → API Gateway → Payment Service → PSP (Stripe/PayPal) → Database. Trust boundary-lər: internet/DMZ xətti, internal service xətti, external PSP xətti.

**Addım 2 — Nə səhv gedə bilər (STRIDE):**

*Spoofing:* PSP callback-larının doğrulanmaması — fake ödəniş konfirmasiyası. Həll: webhook signature doğrulaması.

*Tampering:* Client-side order total dəyişdirmək. Həll: order total server-side hesablanır, client-ə güvənilmir.

*Repudiation:* "Mən bu ödənişi etmədim" mübahisəsi. Həll: immutable audit log, PSP transaction ID.

*Information Disclosure:* Kredit kartı məlumatının sızdırılması. Həll: PCI DSS tokenization, heç vaxt tam kart nömrəsi saxlanmır.

*Denial of Service:* Checkout endpoint-inə flood. Həll: rate limiting, CAPTCHA, circuit breaker.

*Elevation of Privilege:* Normal user-in başqasının sifarişini ödəməsi. Həll: strict order ownership yoxlanması.

**Addım 3 — Prioritetləmə:**
Risk matrix ilə değerlendirərdim — Tampering (order total) high likelihood + high impact = kritik. Information Disclosure — low likelihood (PSP tokenization var) amma high impact.

**Addım 4 — Nəticə:**
Threat model-dən çıxan security requirement-ları sprint backlog-a əlavə edərdim — acceptance criteria kimi."

### Konfiqurasiya / Kod Nümunəsi

**STRIDE analiz cədvəli — Payment Service:**

| Threat | Kateqoriya | Komponent | Risk | Mitigasiya |
|--------|------------|-----------|------|------------|
| Fake PSP webhook | Spoofing | Webhook handler | High | HMAC signature verify |
| Order total manipulation | Tampering | Checkout API | Critical | Server-side calculation |
| Ödəniş inkarı | Repudiation | Order service | Medium | Audit log + PSP ref |
| Card data leak | Info Disclosure | Payment model | Critical | PCI tokenization |
| Checkout DDoS | DoS | API Gateway | Medium | Rate limit + WAF |
| User A pays for User B | Privilege Escalation | Order controller | High | Ownership check |

**Trust Boundary müəyyən etmə — Laravel-də:**
```php
// Trust boundary: External PSP callback → Internal system
// Her daxil olan webhook doğrulanmalıdır

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        // THREAT: Spoofing — fake webhook
        // MİTİGASİYA: Stripe signature verification
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            // Audit log: doğrulanmamış webhook cəhdi
            AuditLog::record('webhook.invalid_signature', [
                'ip'        => $request->ip(),
                'signature' => substr($sigHeader, 0, 20) . '...',
            ]);
            return response('Unauthorized', 401);
        }

        // İdempotency: eyni event iki dəfə emal edilməsin
        // THREAT: Replay attack
        if (WebhookEvent::where('stripe_id', $event->id)->exists()) {
            return response('Already processed', 200);
        }

        $this->processEvent($event);
        return response('OK', 200);
    }
}
```

**Server-side order total — Tampering mitigasiyası:**
```php
// THREAT: Tampering — client order total-ı manipulate edə bilər
// MİTİGASİYA: Total heç vaxt client-dən qəbul edilmir

class OrderService
{
    public function createOrder(User $user, array $items): Order
    {
        // Client-dən gələn price-a GÜVƏNMƏ — özümüz hesabla
        $total = collect($items)->reduce(function ($carry, $item) {
            $product = Product::findOrFail($item['product_id']);

            // Stok yoxlaması — race condition-a qarşı pessimistic lock
            $product = Product::where('id', $item['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($product->stock < $item['quantity']) {
                throw new InsufficientStockException();
            }

            // Qiyməti database-dən al — client-in verdiyindən deyil
            return $carry + ($product->price * $item['quantity']);
        }, 0);

        return Order::create([
            'user_id' => $user->id,
            'total'   => $total,  // Server-side hesablanmış total
            'items'   => $items,
        ]);
    }
}
```

**Attack Tree — File Upload:**
```
Hücum hədəfi: Serverdə ixtiyari kod icra etmək (RCE)
│
├── Zərərli fayl yükləmək
│   ├── PHP faylını şəkil kimi yükləmək
│   │   ├── Extension yoxlanmasını keçmək (.php.jpg)
│   │   └── MIME type yoxlanmasını keçmək (magic bytes)
│   ├── SVG faylında JavaScript (XSS vektoru)
│   └── Zip Slip — path traversal ilə arxiv
│
├── Yüklənmiş faylı icra etmək
│   ├── Web root-da saxlamaq
│   └── Direct URL-dən çatmaq
│
└── Mitigasiya
    ├── Whitelist extension (jpg, png, pdf)
    ├── Magic bytes doğrulaması
    ├── CDN/S3-da saxla, web server-dən kənarda
    ├── Random fayl adı (user-in adına güvənmə)
    └── Antivirus scan (ClamAV)
```

**Threat Model workshop üçün facilitation:**
```markdown
## Threat Modeling Session Agenda (3 saat)

### Hazırlıq (30 dəqiqə)
- DFD cizin — Miro/draw.io
- Trust boundary-ləri işarələyin
- Stakeholder-lar: dev lead, architect, PM, QA, security

### Scope müəyyənləşdir (15 dəqiqə)
- Bu session nəyi əhatə edir?
- Hansı komponentlər daxildədir?

### Threat Brainstorming - STRIDE (90 dəqiqə)
- Hər DFD elementi üçün STRIDE kateqoriyalarını keçin
- Hər threat-i card-a yazın (Miro sticky notes)
- Qiymətləndirmə etmədən — əvvəl topla

### Risk Dəyərləndirmə (30 dəqiqə)
- Hər threat-i DREAD/CVSS ilə qiymətləndir
- Prioritet cədvəli hazırla

### Mitigasiya planı (15 dəqiqə)
- Critical/High threat-lər üçün owner və ETA
- Backlog ticket-lər yarat
```

## Praktik Tapşırıqlar

**Özünütəst sualları:**
- Mövcud tətbiqinizdəki ən böyük 3 trust boundary hansılardır?
- Son feature-ın threat model-i aparılıbmı? Hansı threat-lər tapılıb?
- STRIDE kateqoriyalarının hər biri üçün layihənizdə bir nümunə verə bilərsinizmi?
- Layihənizdəki attack surface-i azaltmaq üçün hansı addımlar atılıb?

**Scenarios to think through:**
- SaaS tətbiqinizə yeni "file export" feature əlavə olunur. Bu feature üçün STRIDE analizini aparın — hansı threat-lər tapacaqsınız?
- Komanda "threat modeling vaxt itkisidir" deyir. Bu düşüncə modelini necə dəyişdirərdiniz? ROI-ni necə göstərərdiniz?
- Bir attacker şirkətin insider-idir (işçisi). Threat model-iniz bu ssenarini nəzərə alırmı? Necə dəyişdirərdiniz?
- Layihəniz cloud-a köçürülür (on-premise → AWS). Mövcud threat model necə dəyişir? Yeni threat-lər hansılardır?
- MITRE ATT&CK matrisindən istifadə edərək tətbiqinizin hansı taktikalara qarşı ən həssas olduğunu müəyyən edin.

## Əlaqəli Mövzular
- `01-owasp-top-10.md` — OWASP Top 10 threat modeling-in başlanğıc checklist-i kimi istifadə oluna bilər
- `11-least-privilege.md` — Elevation of Privilege (STRIDE-E) threat-inin əsas mitigasiyası
- `12-audit-logging.md` — Repudiation (STRIDE-R) threat-inin mitigasiyası
- `13-data-encryption.md` — Information Disclosure (STRIDE-I) threat-inin mitigasiyası
- `14-security-in-cicd.md` — Pipeline threat-larının threat model-ə daxil edilməsi
