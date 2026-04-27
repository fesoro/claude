# Input Validation and Sanitization (Middle ⭐⭐)

## İcmal
Input Validation — istifadəçi girdisinin gözlənilən formata uyğun olduğunu yoxlamaqdır. Sanitization — girdidən zərərli elementləri təmizləməkdir. "Trust no input" — bütün xarici məlumatı şübhəli qəbul etmək security-nin fundamental prinsipidir. SQL injection, XSS, SSRF kimi hücumların çoxu validation/sanitization olmaması ilə başlayır.

## Niyə Vacibdir
Backend validation client-side validation-ın bypass olunmasından müstəqildir — Postman, curl, JavaScript-i söndürmüş hər brauzer client-side validation-ı atlaya bilər. Server-side validation mütləq lazımdır. Yanlış data sistemə girərsə cascade problemlər yaranır — null pointer, type error, ya da daha pis, data corruption. OWASP Top 10-da A03:Injection və A08:Software and Data Integrity Failures bu zəifliyin bilavasitə nəticəsidir.

## Əsas Anlayışlar

- **Validation**: Məlumatın düzgün formatlı olduğunu yoxlama — `required`, `email`, `min:8`, `in:active,inactive`. Məlumat düzgün formatda deyilsə rədd et.
- **Sanitization**: Məlumatı kabul edilmiş hala gətirmək — `strip_tags()`, `trim()`, `htmlspecialchars()`. Məlumatı rədd etmək əvəzinə təmizləyirsən.
- **Whitelist vs Blacklist**: Whitelist — yalnız icazəli dəyərlərə izin ver. Blacklist — bilinen zərərliləri blok et. Whitelist daha güvənlidir, çünki naməlum zərərliləri avtomatik rədd edir.
- **Client-side validation**: UX üçün — server-side-a alternativ deyil. Browser developer tools ilə 5 saniyədə bypass olunur.
- **Server-side validation**: Mütləq lazımdır — real müdafiə xətti burada başlayır.
- **Form Request**: Laravel-də validation logic-i controller-dən ayırmaq üçün — Single Responsibility Principle.
- **Strict typing**: PHP-də `declare(strict_types=1)` — type coercion-ı önləyir. `"1abc"` string-i integer kimi qəbul edilməsin.
- **Mass assignment vulnerability**: `$model->fill($request->all())` — `$fillable` olmadan kritik field-lər yazıla bilər: `is_admin`, `fraud_score`, `total_amount`.
- **File upload validation**: MIME type, extension, magic bytes yoxlaması. `.php.jpg` adlı fayl extension validation-ı keçər, amma PHP kodu içindədirsə server-side execute oluna bilər.
- **Numeric overflow**: Integer limitlərini yoxlama — mənfi quantity, mənfi amount payment fraud-a açıqdır.
- **Regex DoS (ReDoS)**: Catastrophic backtracking — `/(a+)+$/` kimi pattern exponential vaxt alır. CPU dondura bilir.
- **Encoding issues**: UTF-8 validation — null byte (`\x00`), overlong encoding, homoglyph attack (Cyrillic "a" vs Latin "a").
- **Second validation — nested data**: JSON body içindəki array-lər — hər səviyyəni validate et. `items.*.product_id` kimi.
- **Validation vs Business logic**: "Email format doğrudur" validation, "Bu email artıq qeydiyyatdadır" business logic — bunları ayır.
- **Contextual output encoding**: Validation + sanitization yetərli deyil — output-un context-inə görə encode et: HTML, JSON, SQL, shell.
- **Second-order injection**: Data ilk yazılışda təhlükəsiz görünür, sonradan başqa context-də istifadə olanda exploit olunur.
- **Open redirect validation**: `redirect_url` parametrini whitelist ilə yoxla — `https://evil.com` daxil olmasın.
- **SSRF (Server-Side Request Forgery)**: URL-i validate etmədən internal network-ə request göndərmək — `http://169.254.169.254/` AWS metadata.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"Laravel validation qaydaları yazıram" orta cavabdır. Güclü cavab mass assignment, file upload MIME+magic bytes validation, ReDoS, second-order injection, contextual output encoding kimi nüansları bilir. "Trust no input" prinsipini real nümunə ilə izah et.

**Follow-up suallar interviewerlər soruşur:**
- "Mass assignment nədir? Necə qorunursunuz? `$fillable` yetərlidirmi?"
- "File upload-da MIME type validation yetərlimi? Magic bytes nədir?"
- "Nested array-ları necə validate edirsiniz?"
- "Sanitization validation-ın yerini ala bilərmi?"
- "ReDoS hücumu nədir, real production-da baş verə bilərmi?"
- "Open redirect validation nə üçün lazımdır?"

**Ümumi candidate səhvləri:**
- Yalnız client-side validation — server tarafı yoxdur
- `$request->all()` birbaşa model-ə vermək — mass assignment
- Extension yoxlaması MIME type yoxlamasına alternativ deyil — `.jpg.php` faylı
- Sanitization-ı validation əvəzinə istifadə etmək — məlumat düzgün formatdadır, amma zərərli ola bilər
- Numeric field-də negative value-nin mümkün olub-olmadığını düşünməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- "Validate first, sanitize if needed" prinsipini bilmək
- HTML allowed content-i HTMLPurifier ilə sanitize etmək vs plain text field-i simply escape etmək fərqini izah etmək
- Magic bytes yoxlamasını bilmək (`finfo` extension)
- Second-order injection anlayışını izah edə bilmək

## Nümunələr

### Tipik Interview Sualı
"Input validation haqqında nə bilirsiniz? Laravel-də necə tətbiq edirsiniz? Mass assignment vulnerability nədir?"

### Güclü Cavab
"Bütün xarici girdini şübhəli qəbul edirəm — client-side validation bypass oluna bilər, bu fundamental qaydadır. Server-side Form Request ilə strict validation yazıram: format, type, length, allowed values hər biri yoxlanılır.

Mass assignment üçün — `$request->all()`-ı birbaşa model-ə verməmək. `$fillable` listini explicit saxlayıram, yaxud `$request->safe()->only([...])` istifadə edirəm. `is_admin` kimi field-in form-dan gəlməsi kritik security problemidir.

File upload-da layered approach: extension + MIME type + magic bytes. Extension aldatmaq çox asandır — `finfo` extension-ı ilə real MIME yoxlayıram. Yüklənmiş faylı PHP-nin execute edə bilməyəceği qovluğa yerləşdirirəm, orijinal adı istifadə etmirəm.

ReDoS riskindən qaçmaq üçün mürəkkəb regex yazmaqdan çəkinirəm — `filter_var()` kimi hazır funksiyaları üstün tuturam.

Sanitization yalnız lazım olan hallarda — HTML content üçün HTMLPurifier whitelist yanaşması. Plain text üçün isə `htmlspecialchars()` output encoding-dir, sanitization deyil."

### Kod Nümunəsi — Form Request

```php
// app/Http/Requests/PlaceOrderRequest.php
class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'items'                  => 'required|array|min:1|max:50',
            'items.*.product_id'     => 'required|uuid|exists:products,id',
            'items.*.quantity'       => 'required|integer|min:1|max:100',
            'shipping.first_name'    => 'required|string|max:100|regex:/^[\p{L}\s\-]+$/u',
            'shipping.email'         => 'required|email:rfc,dns',
            'shipping.phone'         => 'nullable|string|regex:/^\+?[1-9]\d{7,14}$/',
            'coupon_code'            => 'nullable|string|max:20|alpha_num',
            'note'                   => 'nullable|string|max:500',
            'redirect_url'           => 'nullable|url|in:' . implode(',', config('app.allowed_redirects')),
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.product_id.exists' => 'Product not found',
            'items.*.quantity.max'      => 'Maximum 100 units per item',
        ];
    }

    // Validated data üzərindəki əlavə sanitization
    protected function passedValidation(): void
    {
        $this->merge([
            'note' => strip_tags($this->note ?? ''),
        ]);
    }
}
```

### Kod Nümunəsi — Mass Assignment Qoruması

```php
// app/Models/Order.php
class Order extends Model
{
    // Yalnız bu field-ları fill() qəbul edir
    protected $fillable = ['customer_id', 'status', 'shipping_address'];

    // Heç vaxt fill() ilə yazılmamalı olanlar
    protected $guarded = ['id', 'total_amount', 'admin_note', 'fraud_score', 'is_fraud'];
}

// Controller-da
// ❌ Dangerous — is_fraud, total_amount gələ bilər
$order = new Order($request->all());

// ✅ Safe — yalnız etibar olunan field-lər
$order = new Order($request->safe()->only(['shipping_address']));
$order->customer_id = $request->user()->id;
$order->total_amount = $this->calculateTotal($request->validated('items'));
$order->save();
```

### Kod Nümunəsi — File Upload Multi-layer Validation

```php
class AvatarUploadController extends Controller
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_SIZE_BYTES = 2 * 1024 * 1024; // 2MB

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => [
                'required',
                'file',
                'max:2048',                               // KB — Laravel check
                'mimes:jpeg,png,webp',                   // Extension check
                'mimetypes:image/jpeg,image/png,image/webp', // MIME header check
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
            ],
        ]);

        $file = $request->file('avatar');

        // Magic bytes check — MIME spoof hücumuna qarşı
        // Content-Type header-ı saxtalaşdırmaq mümkündür
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file->getRealPath());

        if (!in_array($realMime, self::ALLOWED_MIME_TYPES, true)) {
            abort(422, 'Invalid file content');
        }

        // Orijinal adı istifadə etmə — path traversal riski var
        $filename = Str::uuid() . '.' . $file->extension();

        // PHP-nin execute edə bilməyəceği qovluğa yüklə
        // public/ deyil, private storage
        $path = $file->storeAs('avatars', $filename, 'private');

        return response()->json(['path' => $path]);
    }
}
```

### Attack Nümunəsi — File Upload Bypass

```
Hücum ssenarisi:
1. Attacker "shell.php.jpg" adlı fayl hazırlayır
2. Faylın içeriyi: <?php system($_GET['cmd']); ?>
3. Yalnız extension yoxlayırsansa: ".jpg" görürsən → keçir
4. Yalnız MIME header yoxlayırsansa: attacker Content-Type: image/jpeg göndərir → keçir
5. Magic bytes yoxlayırsansa: faylın ilk byte-ları JPEG magic bytes deyil → rədd edilir

Magic bytes yoxlaması olmadan:
- Fayl yüklənir: /uploads/shell.php.jpg
- Nginx .php faylları execute edir: /uploads/shell.php.jpg → PHP işlənir
- Attacker: GET /uploads/shell.php.jpg?cmd=id → www-data kimliyi

Həll: magic bytes + execution-dan xəbərsiz qovluq + UUID adlandırma
```

### Kod Nümunəsi — ReDoS Qoruması

```php
// ❌ Vulnerable regex — catastrophic backtracking
$pattern = '/^(a+)+$/';
$input   = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaX'; // 30 char
preg_match($pattern, $input); // CPU saniyələrlə dona bilər

// ❌ Başqa bir problematik pattern
$email_pattern = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';
// Mürəkkəb — ReDoS riskli, öz regex yazmaq lazım deyil

// ✅ Safe alternativlər
// Email — PHP built-in, regex yoxdur
$isValid = filter_var($email, FILTER_VALIDATE_EMAIL);

// Phone — sadə pattern, backtrack yoxdur
$phonePattern = '/^\+?[1-9]\d{7,14}$/';

// URL validation
$isValid = filter_var($url, FILTER_VALIDATE_URL);

// Integer
$isValid = filter_var($value, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 1000]
]);
```

### İkinci Nümunə — Second-Order Injection

```php
// Birinci yazan — validation keçir, görünüşcə təhlükəsiz
$username = "admin'--";
// Validation: string, max:100 → keçir
// Database-ə yazılır: INSERT INTO users (username) VALUES ('admin''--')
// PostgreSQL-də düzgün escape edilir, problem yoxdur

// Sonradan başqa sorğuda istifadə:
// ❌ Raw SQL-ə yerləşdirilir — developer "database-dən gəlir, güvənlidir" düşünür
$query = "SELECT * FROM logs WHERE searched_by = '$username'";
// Nəticə: SELECT * FROM logs WHERE searched_by = 'admin'--'
// SQL injection! Comment ilə qalan şərt kesildi.

// ✅ Həll: Hər context-də parameterized query/encoding istifadə et
DB::select('SELECT * FROM logs WHERE searched_by = ?', [$username]);
// Database-dən gəlsə belə — contextual encoding tətbiq et
```

### Kod Nümunəsi — Numeric Edge Cases

```php
$request->validate([
    'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
    'amount'   => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],
    'year'     => ['required', 'integer', 'between:2000,2100'],
    'discount' => ['nullable', 'numeric', 'min:0', 'max:100'], // Percentage
]);

// Business logic validation — validation-dan ayrı
// Negative quantity ilə cart manipulation
if ($request->quantity <= 0) {
    throw ValidationException::withMessages([
        'quantity' => 'Quantity must be positive'
    ]);
}

// Integer overflow — PHP_INT_MAX yoxlaması
if ($request->quantity > PHP_INT_MAX / $priceInCents) {
    abort(422, 'Amount overflow');
}
```

## Praktik Tapşırıqlar

- Codebase-inizdə `$request->all()` istifadəsini tapın — `$fillable` varsa da `safe()->only()` ilə müqayisə edin
- File upload endpoint-ini test edin: `.php` extension ilə `.jpg` MIME ötürün, real MIME `finfo` ilə yoxlayın — nə baş verir?
- ReDoS-a qarşı 5 validation regex-inizi yoxlayın — catastrophic backtracking üçün regex101.com istifadə edin
- Nested JSON validation yazın: `items[].product_id` validation `items.*.product_id` formatında
- HTMLPurifier konfiqurasiya edin: yalnız `b`, `i`, `p`, `a[href]`, `ul`, `li` tag-larına icazə verin, `script`, `onload` rədd edin
- Second-order injection ssenariyu simulate edin: database-dən alınan string-i raw SQL-ə yerləşdirin, exploit edin, sonra düzəldin
- Open redirect test edin: `redirect_url=https://evil.com` göndərin — qəbul edilirmi?
- Negative quantity ilə shopping cart-a məhsul əlavə etməyə çalışın — sistemin necə reaksiya verdiyini yoxlayın

## Ətraflı Qeydlər

**Validation library-ları**: Laravel validation powerful-dur, lakin kompleks domain qaydaları üçün `spatie/laravel-data` DTO validation, ya da custom Rule class-lar daha oxunaqlı kod verir.

**HTMLPurifier konfiqurasiyası**:
```php
// config/purifier.php — spatie/laravel-html-purifier
return [
    'settings' => [
        'default' => [
            'HTML.Allowed'       => 'b,i,u,em,strong,p,br,ul,ol,li,a[href|title],img[src|alt|width|height]',
            'HTML.ForbiddenAttributes' => 'style,onclick,onload,onerror',
            'CSS.AllowedProperties'  => '',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty'   => true,
        ],
    ],
];

// İstifadə
$safeHtml = clean($userHtml); // Whitelist yanaşması
```

**Honeypot field-lər**: Bot-ları confuse etmək üçün hidden field — bot doldurursa, insan görə bilmir. Laravel `spatie/laravel-honeypot` paketi.

**Rate limiting validation-ı tamamlayır**: Brute force validation bypass cəhdlərinə qarşı rate limiting lazımdır — `RateLimiter::attempt()` ya `throttle` middleware.

## Əlaqəli Mövzular

- `02-sql-injection.md` — Parameterized query ilə injection müdafiəsi
- `03-xss-csrf.md` — XSS sanitization, contextual encoding
- `01-owasp-top-10.md` — OWASP A03 Injection, A08 Data Integrity
- `10-security-headers.md` — CSP əlavə qoruma kimi output encoding-i tamamlayır
