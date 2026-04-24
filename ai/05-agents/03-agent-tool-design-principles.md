# 32 — Agent Tool Dizayn Prinsipləri

> **Oxucu:** Baş Laravel/PHP developerlər, agent sistemləri quran arxitektlər
> **Ön şərtlər:** Claude tool-use API, JSON Schema, Laravel service container
> **Tarix:** 2026-04-21
> **Modellər:** `claude-sonnet-4-5`, `claude-opus-4-5`, `claude-haiku-4-5`

---

## Mündəricat

1. LLM Alətləri Necə "Oxuyur"
2. Adlandırma Konvensiyaları (verb_noun)
3. Parametr Sxemi — enum, required, optional
4. Description: Alətin Əsas Hissəsi
5. Error Mesajları — Modelə Özünü Düzəltmək Üçün Yol Ver
6. Idempotentlik — Niyə Vacibdir
7. List Alətləri üçün Pagination
8. Ölçü Büdcəsi — 10MB JSON Qaytarma
9. Tool Granularity — Mikro vs Makro
10. Progressive Disclosure
11. Anti-pattern-lər: Nələrdən Qaçmalı
12. Real Before/After Nümunələr
13. Laravel: `ToolBuilder` Fluent API
14. Test Strategiyası
15. Observability və Tool Metrics

---

## 1. LLM Alətləri Necə "Oxuyur"

Agent tool-ları proqramçının oxuduğu kimi oxumur. Claude (və ya istənilən LLM) alətlər haqqında **yalnız description və JSON schema**-dan məlumat alır. Model alətin daxili kodunu görmür, unit testlərini görmür, sizin başınızdakı konteksti görmür.

Bu bir həqiqəti doğurur: **alətin "səmərəliliyi" onun adından çox, description-ın aydınlığından asılıdır.**

### Modelin Gördüyü

Claude tool-use API-ə alətlər göndərildikdə, model yalnız bunu görür:

```json
{
  "name": "search_orders",
  "description": "İstifadəçi sifarişlərində axtarış apar",
  "input_schema": {
    "type": "object",
    "properties": {
      "query": {"type": "string"},
      "status": {"type": "string"}
    },
    "required": ["query"]
  }
}
```

Bu təsvir modelə demir ki:
- Hansı statuslar mümkündür? (`pending`, `paid`, `shipped`, `cancelled`?)
- `query` sifariş ID-si, məhsul adı, yoxsa müştəri e-poçtudur?
- Nəticə neçə sətir qaytaracaq? Pagination varmı?
- Boş nəticə necə göstərilir?

Nəticədə model təxmin etməyə məcbur olur. Təxminin isə 30-40% halda səhv olduğunu log-lar göstərir.

### "Description İlk, Ad İkinci" Qaydası

Senior tool dizayn prinsipi: **description qısa funksiya spesifikasiyası, yox API referensi olmalıdır**. Aşağıdakı fərqə baxın:

**Pis:**
```
description: "Sifarişləri tapır"
```

**Yaxşı:**
```
description: "Verilmiş müştəri e-poçtu və ya sifariş ID-si ilə sifarişlərdə axtarış aparır. Maksimum 50 nəticə qaytarır, ən yeni sifarişlər birinci. Axtarış həssasiyyəti: e-poçt üçün tam uyğunluq, məhsul adları üçün qismən uyğunluq."
```

İkinci description modelə bunları verir:
1. **Hansı girişi qəbul edir** (e-poçt VƏ YA sifariş ID)
2. **Nəticənin həddi** (50)
3. **Sıralama** (ən yeni birinci)
4. **Axtarışın tipi** (tam vs qismən uyğunluq)

---

## 2. Adlandırma Konvensiyaları — `verb_noun`

Model ad strukturundan funksionallıq haqqında güclü siqnal alır. Ən praktik konvensiya: **`verb_noun`** — Unix komandaları və REST API-lərdə sınanmış.

```
Yaxşı                      Pis
─────────────────────────  ─────────────────────────
search_orders              orders
create_invoice             invoice_handler
send_email                 emailer
cancel_subscription        subscription_cancellation
list_recent_deployments    deployments_recent
```

### Fe'llər üçün Leksikon

Eyni şeyi fərqli fe'llə adlandırmaq modeli çaşdırır. Layihədə vahid leksikon saxlayın:

| Niyyət | İstifadə et | Qaçın |
|--------|-------------|-------|
| Oxuma | `get_`, `list_`, `search_` | `fetch_`, `retrieve_`, `pull_` |
| Yaratma | `create_` | `make_`, `add_`, `new_` |
| Yenilənmə | `update_` | `modify_`, `change_`, `patch_` |
| Silmə | `delete_` | `remove_`, `destroy_`, `cancel_` |
| Göndərmə | `send_` | `dispatch_`, `emit_`, `post_` |

Fərqi xatırlayın:
- `get_X` — bir element, ID ilə
- `list_X` — bir neçə element, filter ilə
- `search_X` — bir neçə element, sorğu ilə

### Namespace Prefiksi

10+ alət olduqda namespace əlavə edin:

```
billing.create_invoice
billing.list_invoices
billing.cancel_subscription

crm.create_customer
crm.search_customers

notifications.send_email
notifications.send_sms
```

Claude API texniki olaraq nöqtə istisnalıdırsa, alt-xət də işləyir: `billing_create_invoice`. Vacib olan ardıcıllıqdır.

---

## 3. Parametr Sxemi — enum, required, optional

Parametr sxemi modelə "nə mümkündür" və "nə lazımdır" barədə danışır.

### Qayda 1: Sabit set üçün həmişə `enum` istifadə et

**Pis:**
```json
{
  "status": {"type": "string", "description": "Sifariş statusu"}
}
```

Model nə yaza bilər? `"Pending"`, `"pending"`, `"PENDING"`, `"waiting"`, `"in progress"`, `"not paid"`... Sonra daxili kodunuzda şərt yoxlamaları partlayır.

**Yaxşı:**
```json
{
  "status": {
    "type": "string",
    "enum": ["pending", "paid", "shipped", "delivered", "cancelled"],
    "description": "Sifariş statusu. 'paid' = ödəniş qəbul edildi, 'shipped' = kargo göndərildi."
  }
}
```

`enum` + hər bir dəyərin qısa mənası modelə lazım olan hər şeyi verir.

### Qayda 2: `required` minimal olsun

Hər əlavə məcburi parametr modelin "həll edə bilmədim" vəziyyətinə düşmə ehtimalını artırır. Yalnız həqiqətən vacib olanları məcburi et.

```json
{
  "required": ["query"],
  "properties": {
    "query": {"type": "string"},
    "limit": {"type": "integer", "default": 20, "minimum": 1, "maximum": 100},
    "offset": {"type": "integer", "default": 0, "minimum": 0}
  }
}
```

`default` dəyərlər kodunuzda təyin edilsə də, sxemdə göstərin — model bilsin ki, bu parametri buraxa bilər.

### Qayda 3: `description` hər parametr üçün

Hər property-nin öz description-u olmalıdır:

```json
{
  "customer_id": {
    "type": "integer",
    "description": "Müştərinin UUID-si deyil, daxili auto-increment ID. External ID üçün `customer_external_id` istifadə et.",
    "minimum": 1
  }
}
```

Bu cümlə modelə bir neçə şey öyrədir:
- Tam ədəd gözlənilir (UUID yox)
- External ID üçün fərqli parametr var
- 0 və ya mənfi keçərli deyil (`minimum: 1`)

### Qayda 4: Vaxt sahələri üçün format

```json
{
  "start_date": {
    "type": "string",
    "format": "date",
    "description": "ISO 8601 tarixi, nümunə: 2026-04-21. Yalnız tarix, saat yoxdur."
  },
  "created_at": {
    "type": "string",
    "format": "date-time",
    "description": "ISO 8601 timestamp UTC-də, nümunə: 2026-04-21T14:30:00Z"
  }
}
```

---

## 4. Description: Alətin Əsas Hissəsi

Description yaxşı strukturlaşdırıldıqda aşağıdakı sual-cavab ardıcıllığına cavab verir:

```
1. NƏ EDIR?        → Bir cümlə, aktiv formada
2. NƏ VAXT İSTİFADƏ? → Konkret istifadə halları
3. NƏ VAXT QAÇMALI? → Anti-istifadə halları
4. NƏ QAYTARIR?    → Strukturun qısa təsviri
5. YAN TƏSİRLƏR?   → Baza yazır? E-poçt göndərir?
```

### Şablon

```php
public static function description(): string
{
    return <<<DESC
        Verilmiş müştəri üçün yeni faktura yaradır və PDF nüsxəsini email ilə göndərir.

        Istifadə et:
        - Müştəri abunəliyi yenilənəndə
        - Manual fakturalama üçün (admin tərəfindən)
        - Billing pipeline çıxışında

        Qaçın:
        - Əvvəlki fakturanın dublikatı üçün (əvvəl list_invoices ilə yoxla)
        - Draft faktura üçün (bunun yerinə create_draft_invoice)

        Qaytarır: Yaradılmış faktura obyekti (id, number, total, pdf_url).

        Yan təsirlər:
        - DB-yə `invoices` cədvəlinə yazır
        - Müştərinin e-poçtuna PDF göndərir
        - Stripe-da invoice object yaradır
        DESC;
}
```

### Uzunluq

Description üçün ağıllı diapazon: **60-250 söz**. Çox qısa modeli məlumatsız qoyur, çox uzun isə kontekst pəncərəsini yeyir və model əsas məqamı itirir.

---

## 5. Error Mesajları — Modelə Özünü Düzəltmək Üçün Yol Ver

Agent axınında aləti çağırış uğursuz olduqda model error mesajını oxuyur və YENIDƏN cəhd edir. Mesaj modelə nəyi **dəqiqliklə fərqli etməli olduğunu** deməlidir.

### Pis Error Mesajları

```
"Invalid input"
"Error 500"
"Something went wrong"
"Permission denied"
```

Bu mesajlar modelə yalnız "alınmadı" deyir. Model nə edəcəyini bilmir: retry etsin? Fərqli parametr versin? Başqa alət çağırsın?

### Yaxşı Error Mesajları

```
"customer_id 12345 tapılmadı. list_customers(name='...') ilə mövcud müştərini axtarmağı cəhd et."

"invoice.total mənfi ola bilməz. Minimum 0.01 AZN tələb olunur."

"Rate limit aşıldı (60/min). 45 saniyə sonra yenidən cəhd et və ya daha kiçik batch ilə cəhd et."

"Customer 12345 artıq active abunəlik var (id: 789). cancel_subscription(789) və ya update_subscription(789) istifadə et."
```

### Strukturlaşdırılmış Error Response

```php
class ToolError {
    public function __construct(
        public string $code,          // machine-readable
        public string $message,       // human + model readable
        public ?string $suggestion,   // next step for the model
        public array $context = []
    ) {}

    public function toToolResult(): array {
        return [
            'type' => 'tool_result',
            'is_error' => true,
            'content' => json_encode([
                'error_code' => $this->code,
                'message' => $this->message,
                'suggestion' => $this->suggestion,
                'context' => $this->context,
            ]),
        ];
    }
}

// İstifadə:
throw new ToolError(
    code: 'CUSTOMER_NOT_FOUND',
    message: "customer_id 12345 tapılmadı",
    suggestion: "list_customers(email='...') ilə axtar",
    context: ['tried_id' => 12345]
);
```

### Validation Error-lar üçün Xüsusi Qayda

Laravel FormRequest validation error-larını birbaşa model-e vermə. Onlar human UI üçündür. Bunun yerinə:

```php
public function toToolResult(ValidationException $e): array {
    $errors = collect($e->errors())
        ->map(fn($msgs, $field) => "- {$field}: " . $msgs[0])
        ->values()
        ->implode("\n");

    return [
        'type' => 'tool_result',
        'is_error' => true,
        'content' => "Validation uğursuz oldu:\n{$errors}\n\nParametrləri düzəlt və yenidən cəhd et.",
    ];
}
```

---

## 6. Idempotentlik — Niyə Vacibdir

Agent retry edə bilər. Retry səbəbləri:
- Network timeout
- Rate limit
- Model "yəqin alınmadı" deyib ikinci dəfə çağırır
- Developer manual restart

Əgər `create_invoice` idempotent deyilsə, retry 5 faktura yarada bilər. Buna görə **yaradıcı alətlər idempotency key qəbul etməlidir**:

```json
{
  "name": "create_invoice",
  "input_schema": {
    "type": "object",
    "properties": {
      "customer_id": {"type": "integer"},
      "amount": {"type": "number"},
      "idempotency_key": {
        "type": "string",
        "description": "Bu əməliyyatın unikal identifikatoru. Eyni açarla çağırış eyni nəticəni qaytaracaq, ikinci faktura yaratmayacaq. UUIDv4 tövsiyə olunur."
      }
    },
    "required": ["customer_id", "amount", "idempotency_key"]
  }
}
```

### Backend İmplementasiyası

```php
class CreateInvoiceTool {
    public function execute(array $input): array {
        $key = $input['idempotency_key'];

        // Kilid al — eyni vaxtda iki retry olmasın
        return Cache::lock("invoice_create:{$key}", 10)->block(5, function () use ($key, $input) {
            // Əvvəl yoxla
            if ($cached = Cache::get("invoice_result:{$key}")) {
                return $cached;
            }

            // Yarat
            $invoice = Invoice::create([
                'customer_id' => $input['customer_id'],
                'amount' => $input['amount'],
                'idempotency_key' => $key,
            ]);

            $result = ['id' => $invoice->id, 'number' => $invoice->number];

            // 24 saat cache et
            Cache::put("invoice_result:{$key}", $result, 86400);

            return $result;
        });
    }
}
```

Unique index `idempotency_key` üzərində son müdafiə xəttidir.

---

## 7. List Alətləri üçün Pagination

Böyük dataset-i birbaşa modelə vermək kontekst pəncərəsini tıxayır və modeli çaşdırır. List alətləri həmişə **məhdud** olmalıdır.

### Sadə Limit + Offset

```json
{
  "name": "list_orders",
  "input_schema": {
    "properties": {
      "limit": {
        "type": "integer",
        "default": 20,
        "minimum": 1,
        "maximum": 100,
        "description": "Qaytarılacaq maksimum nəticə sayı"
      },
      "offset": {
        "type": "integer",
        "default": 0,
        "minimum": 0,
        "description": "Keçiriləcək nəticə sayı (pagination üçün)"
      }
    }
  }
}
```

### Cursor-based Pagination

Böyük dataset-lər üçün daha sabit:

```json
{
  "name": "list_orders",
  "input_schema": {
    "properties": {
      "cursor": {
        "type": "string",
        "description": "Əvvəlki çağırışdan `next_cursor` dəyəri. İlk çağırış üçün buraxın."
      }
    }
  }
}
```

Cavab:
```json
{
  "items": [...],
  "next_cursor": "eyJpZCI6MTIzNH0=",
  "has_more": true
}
```

### "Model-dostu" Meta-data

Hər list cavabı meta-data daxil etsin ki, model növbəti hərəkəti təyin edə bilsin:

```json
{
  "items": [...20 items...],
  "total_count": 1847,
  "returned_count": 20,
  "has_more": true,
  "next_cursor": "eyJpZCI6MjB9",
  "suggestion": "1847 nəticədən yalnız 20-si göstərildi. Daha spesifik filter əlavə et və ya cursor ilə davam et."
}
```

---

## 8. Ölçü Büdcəsi — 10MB JSON Qaytarma

LLM tool response-lərini tokenləşdirir. 1MB JSON → ~250k token. `claude-sonnet-4-5` üçün bu, 1M kontekstin 25%-dir. Bir neçə belə çağırış model-i susuzlaşdırır.

### Ölçü Limitləri

| Tool Cavabı | Hədəf | Hədd | Təsir |
|-------------|-------|------|-------|
| Tək obyekt | < 2KB | 10KB | Yaxşı |
| List (20 items) | < 10KB | 50KB | OK |
| Axtarış nəticəsi | < 20KB | 100KB | Sərhəd |
| Full export | **Alət deyil** | — | Download linki qaytarın |

### Azaltma Strategiyaları

**1. Yalnız lazım olan sahələri qaytar:**

```php
// Pis
return Invoice::with('customer', 'items.product', 'payments', 'refunds')->find($id);

// Yaxşı
return Invoice::find($id)->only([
    'id', 'number', 'total', 'status', 'customer_email'
]);
```

**2. Uzun mətnləri truncate et:**

```php
'description' => Str::limit($invoice->description, 200),
'notes_preview' => Str::limit($invoice->notes, 500),
'full_notes_available' => strlen($invoice->notes) > 500,
```

**3. Böyük nəticələr üçün reference qaytar:**

```php
if ($result->size_bytes > 50_000) {
    $token = Str::uuid();
    Cache::put("tool_result:{$token}", $result, 3600);

    return [
        'too_large' => true,
        'summary' => $result->summary(),
        'full_result_token' => $token,
        'suggestion' => "Tam nəticə üçün `get_cached_result(token='{$token}')` istifadə et.",
    ];
}
```

---

## 9. Tool Granularity — Mikro vs Makro

Bu, senior arxitektura qərarıdır. İki ifrat:

### Mikro-tools (çoxlu kiçik alətlər)

```
list_customers, get_customer, create_customer, update_customer,
delete_customer, list_customer_orders, get_customer_email, ...
```

**Müsbət:** Hər alət tək məsuliyyətli, test etmək asan.
**Mənfi:** Model 30+ alət arasında itir, çox tool-call edir, daha çox token.

### Makro-tools (az sayda güclü alətlər)

```
query_customers(filter, fields, include_related)
```

**Müsbət:** Az tool-call, aydın structure.
**Mənfi:** Hər alət kompleks, validation çətin.

### Balans: "Müştəri istifadə halları"

Praktiki qayda: **alət konkret istifadəçi tapşırığına uyğun olsun**. Alətlər UI ekranlarına bənzəməlidir, database CRUD-a yox.

```
Pis: list_customers + list_orders + list_payments
     → model 3 çağırış edib özü birləşdirir

Yaxşı: get_customer_overview(customer_id)
       → preaggregated məlumat: profil + son 5 sifariş + son ödəniş
```

Rəqəmlə: agent üçün **15-25 alət** adətən optimal. 30+ modeli azaldır, 10-dan az imkanları məhdudlaşdırır.

---

## 10. Progressive Disclosure

Bütün alətləri həmişə mövcud saxlamaq lazım deyil. **Kontekstə əsaslanan alət göstərmə**:

```php
class AgentToolRegistry {
    public function toolsForContext(AgentContext $ctx): array {
        $tools = [
            // Həmişə mövcud
            'search_help_articles',
            'get_user_info',
        ];

        if ($ctx->user->hasRole('billing')) {
            $tools[] = 'create_invoice';
            $tools[] = 'cancel_subscription';
        }

        if ($ctx->intent === 'support') {
            $tools[] = 'escalate_ticket';
        }

        if ($ctx->step === 'refund_flow') {
            $tools[] = 'issue_refund';
            $tools[] = 'check_refund_policy';
        }

        return $this->buildSchemas($tools);
    }
}
```

Faydaları:
- Kontekst pəncərəsinə qənaət
- Model daha az seçimdə itmir
- Təhlükəsizlik — istifadəçi icazəsi olmayan aləti görmür

---

## 11. Anti-pattern-lər

### Anti-pattern 1: Vague Description

```
Pis:  "Məlumatları idarə edir"
Yaxşı: "Customer cədvəlində axtarış, yaratma və yenilənmə əməliyyatları"
```

### Anti-pattern 2: Nested/Polymorphic Input

```json
// Pis — modelə "action" sahəsini düzgün seçdirmək çətin
{
  "action": {"enum": ["create", "update", "delete"]},
  "data": {"type": "object"}
}

// Yaxşı — üç ayrı alət
create_customer(data)
update_customer(id, data)
delete_customer(id)
```

### Anti-pattern 3: Overloaded Tool

```
get_customer — kimi:
  - ID-yə görə tap
  - email-ə görə tap
  - phone-a görə tap
  - bütün siyahını qaytar (id yoxdursa)
```

Modelə çaşdırıcı. Bölün:
```
get_customer(id)
search_customers(email | phone | name)
list_customers(filter)
```

### Anti-pattern 4: Ambiguous Return Types

Bəzən obyekt, bəzən array, bəzən null, bəzən string-ləşmiş JSON qaytarmayın. Həmişə eyni struktur:

```json
{
  "success": true,
  "data": {...},
  "meta": {...}
}
```

### Anti-pattern 5: Side Effects Olmadan Xəbərdarlıq

```
Pis:  description: "Sifarişi emal edir"
      → reality: kart charge edir, e-poçt göndərir, inventardan çıxarır

Yaxşı: description: "Sifarişi emal edir. YAN TƏSİRLƏR: kart ödənişi, müştəriyə email, inventar yeniləməsi. Geri qaytarılmaz. Test üçün `simulate_order(order_id)` istifadə et."
```

---

## 12. Real Before/After Nümunələr

### Nümunə: `check_inventory`

**Before (anti-pattern toplusu):**
```json
{
  "name": "inventory",
  "description": "Inventar",
  "input_schema": {
    "properties": {
      "action": {"type": "string"},
      "id": {"type": "string"},
      "data": {"type": "object"}
    }
  }
}
```

**After:**
```json
{
  "name": "check_inventory_level",
  "description": "Məhsul üçün cari inventar səviyyəsini anbara görə qaytarır. Yalnız oxuma, yan təsir yoxdur. Stok 0 olarsa `is_in_stock: false` qaytarır.",
  "input_schema": {
    "type": "object",
    "properties": {
      "product_sku": {
        "type": "string",
        "description": "Məhsul SKU kodu, məsələn 'SHOE-RED-42'. UUID deyil."
      },
      "warehouse_id": {
        "type": "integer",
        "description": "Konkret anbar. Buraxılsa, bütün anbarların cəmi qaytarılır."
      }
    },
    "required": ["product_sku"]
  }
}
```

### Nümunə: `send_notification`

**Before:**
```
description: "Bildiriş göndər"
params: {user, message, type}
```

**After:**
```
description:
"Istifadəçiyə bildiriş göndərir. `channel` parametri ilə email, SMS, push və ya in-app seçilir.
 Eyni `idempotency_key` ilə retry etməkdən dublikatlar qarşısını alır.
 SMS yalnız iş saatlarında (09:00-21:00 yerli) çatdırılır — sonraya qədər növbədə qalır."

params: {
  user_id: integer,
  channel: enum[email, sms, push, in_app],
  template_id: string (məs. 'order_shipped_v2'),
  variables: object (template-də istifadə olunan dəyişənlər),
  idempotency_key: string
}
```

---

## 13. Laravel: `ToolBuilder` Fluent API

Laravel-dəki tool tərifləri tez-tez təkrarçı olur. Fluent API prinsipləri zorlayır və konsistensiyanı təmin edir.

### Struktur

```
app/
  AI/
    Tools/
      ToolBuilder.php
      Tool.php
      ToolRegistry.php
      Definitions/
        CreateInvoiceTool.php
        ListOrdersTool.php
```

### `ToolBuilder` Əsas Kodu

```php
<?php

namespace App\AI\Tools;

class ToolBuilder
{
    private string $name;
    private string $description;
    private array $properties = [];
    private array $required = [];
    private $handler;

    public static function named(string $name): self
    {
        $instance = new self();
        $instance->name = $name;
        return $instance;
    }

    public function describe(string $description): self
    {
        if (strlen($description) < 40) {
            throw new \InvalidArgumentException(
                "Description çox qısa: '{$this->name}'. Minimum 40 simvol."
            );
        }
        $this->description = $description;
        return $this;
    }

    public function stringParam(
        string $name,
        string $description,
        bool $required = false,
        ?array $enum = null,
        ?string $format = null
    ): self {
        $prop = ['type' => 'string', 'description' => $description];

        if ($enum) $prop['enum'] = $enum;
        if ($format) $prop['format'] = $format;

        $this->properties[$name] = $prop;
        if ($required) $this->required[] = $name;

        return $this;
    }

    public function intParam(
        string $name,
        string $description,
        bool $required = false,
        ?int $min = null,
        ?int $max = null,
        ?int $default = null
    ): self {
        $prop = ['type' => 'integer', 'description' => $description];

        if ($min !== null) $prop['minimum'] = $min;
        if ($max !== null) $prop['maximum'] = $max;
        if ($default !== null) $prop['default'] = $default;

        $this->properties[$name] = $prop;
        if ($required) $this->required[] = $name;

        return $this;
    }

    public function numberParam(
        string $name,
        string $description,
        bool $required = false,
        ?float $min = null
    ): self {
        $prop = ['type' => 'number', 'description' => $description];
        if ($min !== null) $prop['minimum'] = $min;

        $this->properties[$name] = $prop;
        if ($required) $this->required[] = $name;
        return $this;
    }

    public function boolParam(string $name, string $description, bool $default = false): self
    {
        $this->properties[$name] = [
            'type' => 'boolean',
            'description' => $description,
            'default' => $default,
        ];
        return $this;
    }

    public function idempotencyKey(): self
    {
        return $this->stringParam(
            'idempotency_key',
            'Unikal əməliyyat açarı (UUIDv4 tövsiyə olunur). Eyni açar ilə retry eyni nəticəni qaytaracaq.',
            required: true
        );
    }

    public function pagination(int $maxLimit = 100): self
    {
        $this->intParam(
            'limit', 'Qaytarılacaq maksimum nəticə',
            min: 1, max: $maxLimit, default: 20
        );
        $this->stringParam(
            'cursor', 'Pagination üçün cursor. İlk çağırış üçün buraxın.'
        );
        return $this;
    }

    public function handle(callable $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    public function build(): Tool
    {
        if (!$this->description) {
            throw new \LogicException("describe() çağırılmayıb: {$this->name}");
        }

        return new Tool(
            name: $this->name,
            description: $this->description,
            schema: [
                'type' => 'object',
                'properties' => $this->properties,
                'required' => $this->required,
            ],
            handler: $this->handler
        );
    }
}
```

### İstifadə

```php
<?php

namespace App\AI\Tools\Definitions;

use App\AI\Tools\ToolBuilder;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;

class CreateInvoiceTool
{
    public static function build()
    {
        return ToolBuilder::named('create_invoice')
            ->describe(<<<DESC
                Müştəri üçün yeni faktura yaradır. PDF avtomatik hazırlanır və müştəri e-poçtuna göndərilir.
                Yan təsirlər: DB yazma, Stripe invoice, müştəri e-poçtu.
                Dublikatlardan qorunmaq üçün idempotency_key məcburidir.
                DESC)
            ->intParam('customer_id', 'Daxili müştəri ID', required: true, min: 1)
            ->numberParam('amount', 'Faktura məbləği AZN-lə', required: true, min: 0.01)
            ->stringParam('currency', 'Valyuta kodu', enum: ['AZN', 'USD', 'EUR'])
            ->stringParam('due_date', 'Ödəniş son tarixi (YYYY-MM-DD)', format: 'date')
            ->idempotencyKey()
            ->handle(function (array $input) {
                return Cache::lock("inv_lock:{$input['idempotency_key']}", 10)
                    ->block(5, fn() => self::execute($input));
            })
            ->build();
    }

    private static function execute(array $input): array
    {
        $cached = Cache::get("inv_result:{$input['idempotency_key']}");
        if ($cached) return $cached;

        $invoice = Invoice::create([
            'customer_id' => $input['customer_id'],
            'amount' => $input['amount'],
            'currency' => $input['currency'] ?? 'AZN',
            'due_date' => $input['due_date'] ?? now()->addDays(30),
            'idempotency_key' => $input['idempotency_key'],
        ]);

        dispatch(new \App\Jobs\GenerateInvoicePdfJob($invoice));
        dispatch(new \App\Jobs\SendInvoiceEmailJob($invoice));

        $result = [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'amount' => $invoice->amount,
            'status' => 'created',
        ];

        Cache::put("inv_result:{$input['idempotency_key']}", $result, 86400);
        return $result;
    }
}
```

### `ToolRegistry`

```php
<?php

namespace App\AI\Tools;

class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(Tool $tool): void
    {
        $this->tools[$tool->name] = $tool;
    }

    public function forAPI(): array
    {
        return array_map(
            fn(Tool $t) => [
                'name' => $t->name,
                'description' => $t->description,
                'input_schema' => $t->schema,
            ],
            array_values($this->tools)
        );
    }

    public function execute(string $name, array $input): array
    {
        if (!isset($this->tools[$name])) {
            return [
                'is_error' => true,
                'content' => "Tool '{$name}' qeydiyyatda yoxdur.",
            ];
        }

        try {
            $result = ($this->tools[$name]->handler)($input);
            return ['is_error' => false, 'content' => json_encode($result)];
        } catch (ToolError $e) {
            return $e->toToolResult();
        } catch (\Throwable $e) {
            report($e);
            return [
                'is_error' => true,
                'content' => "Gözlənilməz xəta: {$e->getMessage()}. Retry et və ya başqa alət cəhd et.",
            ];
        }
    }
}
```

### Service Provider qeydiyyatı

```php
// app/Providers/AIToolsServiceProvider.php
public function register(): void
{
    $this->app->singleton(ToolRegistry::class, function () {
        $registry = new ToolRegistry();

        foreach (config('ai.tools') as $toolClass) {
            $registry->register($toolClass::build());
        }

        return $registry;
    });
}
```

```php
// config/ai.php
return [
    'tools' => [
        \App\AI\Tools\Definitions\CreateInvoiceTool::class,
        \App\AI\Tools\Definitions\ListOrdersTool::class,
        // ...
    ],
];
```

---

## 14. Test Strategiyası

Alətlər testsiz düşmənə çevrilir. Üç test səviyyəsi:

### Səviyyə 1: Schema Validation Testləri

```php
// tests/Unit/Tools/CreateInvoiceToolTest.php
use App\AI\Tools\Definitions\CreateInvoiceTool;

test('create_invoice schema geçərli JSON Schema-dır', function () {
    $tool = CreateInvoiceTool::build();
    $validator = new \JsonSchema\Validator();
    $data = (object)[];
    $validator->validate($data, json_decode(json_encode($tool->schema)));
    expect($tool->schema['required'])->toContain('customer_id', 'amount', 'idempotency_key');
});

test('description minimum uzunluğu var', function () {
    $tool = CreateInvoiceTool::build();
    expect(strlen($tool->description))->toBeGreaterThan(40);
});

test('enum dəyərləri düzgündür', function () {
    $tool = CreateInvoiceTool::build();
    expect($tool->schema['properties']['currency']['enum'])
        ->toBe(['AZN', 'USD', 'EUR']);
});
```

### Səviyyə 2: Execution Testləri

```php
test('eyni idempotency_key ilə iki çağırış yalnız bir faktura yaradır', function () {
    $tool = CreateInvoiceTool::build();
    $input = [
        'customer_id' => 1,
        'amount' => 100,
        'idempotency_key' => 'test-key-1',
    ];

    $r1 = ($tool->handler)($input);
    $r2 = ($tool->handler)($input);

    expect($r1['id'])->toBe($r2['id']);
    expect(Invoice::where('idempotency_key', 'test-key-1')->count())->toBe(1);
});
```

### Səviyyə 3: Model-in-the-loop Testləri

Real Claude API ilə alətin düzgün istifadə edildiyini test edin:

```php
test('claude-haiku-4-5 create_invoice üçün düzgün parametrləri seçir', function () {
    $response = \App\AI\AgentRunner::run(
        model: 'claude-haiku-4-5',
        prompt: 'Müştəri #123 üçün 250 AZN faktura yarat',
        tools: [CreateInvoiceTool::build()],
    );

    $toolCall = $response->toolCalls[0];
    expect($toolCall->name)->toBe('create_invoice');
    expect($toolCall->input['customer_id'])->toBe(123);
    expect($toolCall->input['amount'])->toBe(250);
    expect($toolCall->input)->toHaveKey('idempotency_key');
});
```

---

## 15. Observability və Tool Metrics

Production-da alətlər üçün toplamalı olduğunuz metrics:

```
┌──────────────────────────────────────────────────────────┐
│  PER-TOOL METRICS                                        │
├──────────────────────────────────────────────────────────┤
│  tool_call_count{name, status}         Counter           │
│  tool_call_duration{name}              Histogram         │
│  tool_call_error{name, error_code}     Counter           │
│  tool_input_size_bytes{name}           Histogram         │
│  tool_output_size_bytes{name}          Histogram         │
│  tool_retry_count{name}                Counter           │
│  tool_idempotency_hit{name}            Counter           │
└──────────────────────────────────────────────────────────┘
```

### Middleware-style wrapping

```php
class InstrumentedTool
{
    public function __construct(private Tool $tool) {}

    public function execute(array $input): array
    {
        $start = microtime(true);
        $inputSize = strlen(json_encode($input));

        try {
            $result = ($this->tool->handler)($input);

            $duration = microtime(true) - $start;
            $outputSize = strlen(json_encode($result));

            \Prometheus\Counter::inc('tool_call_count', [
                'name' => $this->tool->name,
                'status' => 'ok',
            ]);
            \Prometheus\Histogram::observe('tool_call_duration', $duration, [
                'name' => $this->tool->name,
            ]);

            return ['is_error' => false, 'content' => json_encode($result)];
        } catch (\Throwable $e) {
            \Prometheus\Counter::inc('tool_call_error', [
                'name' => $this->tool->name,
                'error_code' => $e instanceof ToolError ? $e->code : 'UNKNOWN',
            ]);
            throw $e;
        }
    }
}
```

### Qızıl Siqnallar

Hansı göstəriciləri izləməlisiniz:
- **p95 execution time** — alətin yavaşlamasını erkən tutmaq
- **error rate per tool** — 1%-dən çoxdursa alert
- **retry rate** — 5%-dən çoxdursa yanlış dizayn əlaməti
- **schema validation failures** — model sxemi başa düşmür

---

## Yekun

Agent alətlərinin dizaynı interfeys dizaynıdır — yalnız istifadəçi LLM-dir. Əsas prinsiplər:

1. **Description aləti müəyyən edir**, kod yox
2. **Enum, required, format** sxemdə güclü istifadə olun
3. **Error mesajları modelə yol göstərsin**
4. **Idempotentlik** retry-ları təhlükəsiz etsin
5. **Ölçü büdcəsi** kontekst pəncərəsini qoru
6. **Granulyarlıq** istifadə hallarına uyğun olsun
7. **Observability** hər alətdə olsun

Laravel `ToolBuilder` pattern-i bu prinsipləri avtomatlaşdırır — onları qaçırmaq çətindir, bu da onların ən böyük gücüdür.
