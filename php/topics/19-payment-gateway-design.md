# Payment Gateway Dizaynı - Tam Hərtərəfli Bələdçi

## 1. Payment Gateway Nədir?

**Payment Gateway** — müştəri ilə satıcı arasında ödəniş əməliyyatlarını təhlükəsiz şəkildə emal edən bir texnoloji vasitədir. Fiziki mağazadakı POS terminalının onlayn ekvivalentidir.

```
Ödəniş axını (sadələşdirilmiş):

Müştəri (Kart məlumatları) 
    -> Payment Gateway (Şifrələmə, Validasiya)
        -> Payment Processor (Əməliyyat emalı)
            -> Card Network (Visa/Mastercard)
                -> İssuing Bank (Müştərinin bankı)
                    -> Təsdiq / Rədd
                <- Card Network
            <- Payment Processor
        <- Payment Gateway
    <- Müştəriyə Cavab (Uğurlu / Uğursuz)
```

### Əsas Terminlər

| Termin | Azərbaycanca | İzahı |
|--------|-------------|-------|
| **Merchant** | Satıcı | Ödəniş qəbul edən biznes |
| **Acquirer** | Əldə edən bank | Satıcının bankı |
| **Issuer** | Emitent bank | Müştərinin kartını verən bank |
| **Card Network** | Kart şəbəkəsi | Visa, Mastercard, AmEx |
| **Payment Processor** | Ödəniş prosessoru | Əməliyyatları emal edən şirkət |
| **Payment Gateway** | Ödəniş şlüzü | API vasitəsilə ödəniş qəbul edən xidmət |
| **PSP** | Ödəniş xidmət provayderi | Stripe, PayPal, Braintree |

### Gateway vs Processor vs PSP

```
Payment Gateway: API interfeysi (məlumat ötürülməsi)
    - Kart məlumatlarını şifrələyir
    - Validasiya edir
    - API endpoint təmin edir

Payment Processor: Əməliyyat emalı
    - Banklar arası kommunikasiya
    - Settlement (hesablaşma) prosesi
    - Fraud detection

PSP (Payment Service Provider): Hər ikisini təmin edir
    - Stripe = Gateway + Processor + Merchant Account
    - Sadə inteqrasiya
    - Əlavə xidmətlər (subscription, invoicing)
```

---

## 2. Payment Flow: Authorization, Capture, Void, Refund

Ödəniş əməliyyatının dörd əsas mərhələsi var:

### Authorization (Avtorizasiya)

Kart sahibinin hesabında kifayət qədər vəsait olub-olmadığını yoxlayır və məbləği **blokda saxlayır** (hold). Pul hələ çıxmır.

*Kart sahibinin hesabında kifayət qədər vəsait olub-olmadığını yoxlayır üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

class AuthorizationService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private PaymentRepository $payments,
    ) {}

    /**
     * Kartda məbləği bloklayır (hold), amma pulu çıxmır.
     * Məsələn: Otel rezervasiyası zamanı depozit bloklanır.
     */
    public function authorize(PaymentRequest $request): PaymentResult
    {
        // 1. Idempotency yoxlaması - eyni əməliyyatın təkrar olmasının qarşısı
        $existing = $this->payments->findByIdempotencyKey($request->idempotencyKey);
        if ($existing) {
            return PaymentResult::fromExisting($existing);
        }

        // 2. Payment record yarat
        $payment = $this->payments->create([
            'amount' => $request->amount,
            'currency' => $request->currency,
            'status' => PaymentStatus::PENDING,
            'idempotency_key' => $request->idempotencyKey,
            'customer_id' => $request->customerId,
            'metadata' => $request->metadata,
        ]);

        try {
            // 3. Gateway-ə authorization göndər
            $result = $this->gateway->authorize(
                amount: $request->amount,
                currency: $request->currency,
                paymentMethod: $request->paymentMethodToken,
                metadata: [
                    'payment_id' => $payment->id,
                    'order_id' => $request->orderId,
                ],
            );

            // 4. Payment-i yenilə
            $payment->update([
                'status' => PaymentStatus::AUTHORIZED,
                'gateway_transaction_id' => $result->transactionId,
                'authorization_code' => $result->authorizationCode,
                'authorized_at' => now(),
                'authorization_expires_at' => now()->addDays(7), // adətən 7 gün
            ]);

            return PaymentResult::authorized($payment, $result);

        } catch (PaymentDeclinedException $e) {
            $payment->update([
                'status' => PaymentStatus::DECLINED,
                'decline_reason' => $e->reason,
                'decline_code' => $e->code,
            ]);

            return PaymentResult::declined($payment, $e->reason);

        } catch (PaymentGatewayException $e) {
            $payment->update([
                'status' => PaymentStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```

### Capture (Tutma)

Əvvəlcədən avtorizasiya edilmiş məbləği **həqiqətən çıxır**. Authorization + Capture ayrı edilə bilər, ya da birlikdə (auth+capture / sale).

*Əvvəlcədən avtorizasiya edilmiş məbləği **həqiqətən çıxır**. Authoriza üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

class CaptureService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private PaymentRepository $payments,
    ) {}

    /**
     * Əvvəlcədən avtorizasiya olunmuş ödənişi tutma (capture).
     * Misal: Sifariş göndərildikdə pul çıxılır.
     */
    public function capture(string $paymentId, ?int $amount = null): PaymentResult
    {
        $payment = $this->payments->findOrFail($paymentId);

        // Yalnız AUTHORIZED statusunda capture etmək olar
        if (!$payment->canBeCaptured()) {
            throw new InvalidPaymentStateException(
                "Payment {$paymentId} cannot be captured. Current status: {$payment->status->value}"
            );
        }

        // Authorization vaxtı bitib?
        if ($payment->authorization_expires_at->isPast()) {
            throw new AuthorizationExpiredException(
                "Authorization for payment {$paymentId} has expired."
            );
        }

        // Partial capture: bəzən avtorizasiya məbləğindən az capture etmək olar
        $captureAmount = $amount ?? $payment->amount;
        if ($captureAmount > $payment->amount) {
            throw new InvalidAmountException(
                "Capture amount ({$captureAmount}) cannot exceed authorized amount ({$payment->amount})."
            );
        }

        try {
            $result = $this->gateway->capture(
                transactionId: $payment->gateway_transaction_id,
                amount: $captureAmount,
            );

            $payment->update([
                'status' => PaymentStatus::CAPTURED,
                'captured_amount' => $captureAmount,
                'captured_at' => now(),
                'capture_transaction_id' => $result->transactionId,
            ]);

            event(new PaymentCaptured($payment));

            return PaymentResult::captured($payment, $result);

        } catch (PaymentGatewayException $e) {
            $payment->update([
                'status' => PaymentStatus::CAPTURE_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Avtorizasiya + Capture birlikdə (Sale / Direct Charge).
     * Rəqəmsal məhsullar kimi dərhal çatdırılan mallarda istifadə olunur.
     */
    public function authAndCapture(PaymentRequest $request): PaymentResult
    {
        $payment = $this->payments->create([
            'amount' => $request->amount,
            'currency' => $request->currency,
            'status' => PaymentStatus::PENDING,
            'idempotency_key' => $request->idempotencyKey,
        ]);

        try {
            $result = $this->gateway->charge(
                amount: $request->amount,
                currency: $request->currency,
                paymentMethod: $request->paymentMethodToken,
            );

            $payment->update([
                'status' => PaymentStatus::CAPTURED,
                'gateway_transaction_id' => $result->transactionId,
                'captured_amount' => $request->amount,
                'authorized_at' => now(),
                'captured_at' => now(),
            ]);

            return PaymentResult::captured($payment, $result);

        } catch (\Throwable $e) {
            $payment->update(['status' => PaymentStatus::FAILED]);
            throw $e;
        }
    }
}
```

### Void (Ləğv)

Capture olunmamış authorization-u **ləğv edir**. Pul heç çıxılmamışdısa, hold-u silir.

*Capture olunmamış authorization-u **ləğv edir**. Pul heç çıxılmamışdıs üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

class VoidService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private PaymentRepository $payments,
    ) {}

    /**
     * Avtorizasiyanı ləğv edir (pul hold-dan çıxır).
     * Capture olunmamış əməliyyatlar üçün istifadə edilir.
     */
    public function void(string $paymentId, string $reason): PaymentResult
    {
        $payment = $this->payments->findOrFail($paymentId);

        if (!$payment->canBeVoided()) {
            throw new InvalidPaymentStateException(
                "Payment {$paymentId} cannot be voided. Status: {$payment->status->value}"
            );
        }

        try {
            $result = $this->gateway->void(
                transactionId: $payment->gateway_transaction_id,
            );

            $payment->update([
                'status' => PaymentStatus::VOIDED,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            event(new PaymentVoided($payment));

            return PaymentResult::voided($payment, $result);

        } catch (PaymentGatewayException $e) {
            // Void uğursuz olsa, manual müdaxilə lazım ola bilər
            Log::critical('Void failed for payment', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

### Refund (Geri qaytarma)

Capture olunmuş (çıxılmış) məbləği müştəriyə **geri qaytarır**. Tam və ya qismən ola bilər.

*Capture olunmuş (çıxılmış) məbləği müştəriyə **geri qaytarır**. Tam və üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

class RefundService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private PaymentRepository $payments,
        private RefundRepository $refunds,
    ) {}

    /**
     * Tam və ya qismən geri qaytarma.
     */
    public function refund(
        string $paymentId,
        ?int $amount = null,
        string $reason = '',
    ): RefundResult {
        $payment = $this->payments->findOrFail($paymentId);

        if (!$payment->canBeRefunded()) {
            throw new InvalidPaymentStateException(
                "Payment {$paymentId} cannot be refunded. Status: {$payment->status->value}"
            );
        }

        $refundAmount = $amount ?? $payment->captured_amount;

        // Artıq edilmiş refund-ları hesabla
        $totalRefunded = $this->refunds->totalRefundedForPayment($paymentId);
        $maxRefundable = $payment->captured_amount - $totalRefunded;

        if ($refundAmount > $maxRefundable) {
            throw new InvalidAmountException(
                "Refund amount ({$refundAmount}) exceeds refundable amount ({$maxRefundable})."
            );
        }

        $refund = $this->refunds->create([
            'payment_id' => $paymentId,
            'amount' => $refundAmount,
            'reason' => $reason,
            'status' => RefundStatus::PENDING,
        ]);

        try {
            $result = $this->gateway->refund(
                transactionId: $payment->gateway_transaction_id,
                amount: $refundAmount,
            );

            $refund->update([
                'status' => RefundStatus::COMPLETED,
                'gateway_refund_id' => $result->refundId,
                'completed_at' => now(),
            ]);

            // Tam refund edilibsə, payment statusunu yenilə
            $newTotalRefunded = $totalRefunded + $refundAmount;
            if ($newTotalRefunded >= $payment->captured_amount) {
                $payment->update(['status' => PaymentStatus::REFUNDED]);
            } else {
                $payment->update([
                    'status' => PaymentStatus::PARTIALLY_REFUNDED,
                    'refunded_amount' => $newTotalRefunded,
                ]);
            }

            event(new PaymentRefunded($payment, $refund));

            return RefundResult::success($refund, $result);

        } catch (PaymentGatewayException $e) {
            $refund->update([
                'status' => RefundStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

### Auth+Capture vs Ayrı-ayrı — Nə Zaman Hansı?

```
Auth + Capture (Sale) istifadə et:
  ✅ Rəqəmsal məhsullar (dərhal çatdırılır)
  ✅ Abunəliklər (subscription)
  ✅ Xidmət haqları

Ayrı Auth, sonra Capture istifadə et:
  ✅ Fiziki mal satışı (göndərildikdə capture)
  ✅ Otel rezervasiyası (çıxışda capture)
  ✅ İcarə (depozit blokla, sonra capture et)
  ✅ Marketplace (satıcı sifarişi qəbul etdikdə capture)
```

---

## 3. Payment Methods (Ödəniş Üsulları)

### Credit/Debit Card

*Credit/Debit Card üçün kod nümunəsi:*
```php
<?php

namespace App\Enums;

enum PaymentMethodType: string
{
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case BANK_TRANSFER = 'bank_transfer';
    case DIGITAL_WALLET = 'digital_wallet';
    case BUY_NOW_PAY_LATER = 'bnpl';
    case CRYPTOCURRENCY = 'crypto';
}
```

*case CRYPTOCURRENCY = 'crypto'; üçün kod nümunəsi:*
```php
<?php

namespace App\ValueObjects;

/**
 * Kart məlumatlarını əks etdirən Value Object.
 * PCI DSS-ə görə, ham kart nömrəsini heç vaxt saxlama!
 * Yalnız tokenləşdirilmiş versiyasını istifadə et.
 */
final readonly class CardDetails
{
    public function __construct(
        public string $last4,
        public string $brand,      // visa, mastercard, amex
        public int $expMonth,
        public int $expYear,
        public string $fingerprint, // kart fingerprint (uniqueness)
        public ?string $cardholderName = null,
        public ?string $country = null,
        public ?string $funding = null, // credit, debit, prepaid
    ) {}

    public function isExpired(): bool
    {
        $expDate = Carbon::createFromDate($this->expYear, $this->expMonth)->endOfMonth();
        return $expDate->isPast();
    }

    public function maskedNumber(): string
    {
        return "**** **** **** {$this->last4}";
    }
}
```

### Bank Transfer (SEPA, ACH, Wire)

*Bank Transfer (SEPA, ACH, Wire) üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment\Methods;

class BankTransferPayment implements PaymentMethodInterface
{
    /**
     * Bank köçürməsi ilə ödəniş.
     * ACH (ABŞ), SEPA (Avropa), BACS (Britaniya).
     * Daha uzun settlement müddəti (1-5 iş günü), lakin aşağı komissiya.
     */
    public function initiate(PaymentRequest $request): PaymentResult
    {
        return match ($request->bankTransferType) {
            'ach' => $this->initiateACH($request),
            'sepa' => $this->initiateSEPA($request),
            'wire' => $this->initiateWireTransfer($request),
            default => throw new UnsupportedTransferTypeException(),
        };
    }

    private function initiateSEPA(PaymentRequest $request): PaymentResult
    {
        // SEPA Direct Debit: müştərinin IBAN-ından çıxılır
        // Mandate (icazə) tələb olunur
        $mandate = $this->createMandate(
            iban: $request->iban,
            accountHolder: $request->accountHolder,
            mandateReference: $request->mandateReference,
        );

        return $this->gateway->createSEPAPayment(
            amount: $request->amount,
            currency: 'EUR', // SEPA yalnız EUR
            mandate: $mandate,
        );
    }
}
```

### Digital Wallets (Apple Pay, Google Pay)

*Digital Wallets (Apple Pay, Google Pay) üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment\Methods;

class DigitalWalletPayment implements PaymentMethodInterface
{
    /**
     * Apple Pay / Google Pay ödənişi.
     * Tokenləşdirilmiş kart məlumatları cihazdan gəlir.
     * Daha yüksək təhlükəsizlik (biometric auth + device token).
     */
    public function processApplePay(string $paymentToken, int $amount): PaymentResult
    {
        // Apple Pay token-i decrypt edilir (Apple-ın sertifikatı ilə)
        // Sonra normal kart ödənişi kimi emal olunur
        $decryptedToken = $this->decryptApplePayToken($paymentToken);

        return $this->gateway->charge(
            amount: $amount,
            paymentMethod: [
                'type' => 'apple_pay',
                'token' => $decryptedToken,
            ],
        );
    }

    public function processGooglePay(string $paymentToken, int $amount): PaymentResult
    {
        $decryptedToken = $this->decryptGooglePayToken($paymentToken);

        return $this->gateway->charge(
            amount: $amount,
            paymentMethod: [
                'type' => 'google_pay',
                'token' => $decryptedToken,
            ],
        );
    }
}
```

---

## 4. PCI DSS Compliance

**PCI DSS (Payment Card Industry Data Security Standard)** — kart məlumatlarının təhlükəsiz saxlanılması və emalı üçün beynəlxalq standartdır. Bütün kart qəbul edən bizneslər bu standarta uyğun olmalıdır.

### PCI DSS Uyğunluq Səviyyələri

```
Level 1: İldə 6 milyon+ əməliyyat (tam audit)
Level 2: İldə 1-6 milyon əməliyyat
Level 3: İldə 20 min - 1 milyon əməliyyat
Level 4: İldə 20 mindən az əməliyyat (SAQ - Self Assessment)
```

### Əsas Qaydalar

```
1. Kart nömrəsini (PAN) heç vaxt düz mətnlə saxlama
2. CVV/CVC-ni heç vaxt saxlama (hətta şifrələnmiş belə)
3. Kart məlumatlarını log-lara yazma
4. HTTPS istifadə et (TLS 1.2+)
5. Güclü şifrələmə istifadə et (AES-256)
6. Firewall-larla qoru
7. Müntəzəm penetration testing et
8. Access control tətbiq et
```

### Laravel-də PCI Uyğunluq (SAQ A - ən sadə)

*Laravel-də PCI Uyğunluq (SAQ A - ən sadə) üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * PCI SAQ A: Kart məlumatları HEÇVAXT serverinə gəlmir.
 * Client-side SDK (Stripe.js, Braintree Drop-in) istifadə edir.
 * Kart məlumatları birbaşa gateway-ə göndərilir, token qaytarılır.
 */
class PaymentController extends Controller
{
    /**
     * Frontend Stripe.js ilə kart məlumatlarını tokenləşdirir.
     * Serverə yalnız token gəlir — kart nömrəsi heç vaxt serverə toxunmur.
     */
    public function charge(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method_id' => 'required|string', // Stripe.js-dən gələn token
            'amount' => 'required|integer|min:50',    // minimum 50 qəpik
        ]);

        // DOGRU: Token ilə işləyirik (kart nömrəsi yoxdur)
        $result = $this->paymentService->charge(
            paymentMethodToken: $request->payment_method_id,
            amount: $request->amount,
        );

        return response()->json($result);
    }

    /**
     * YANLIŞ! Bu PCI DSS pozuntusudur!
     * Kart nömrəsini heç vaxt serverə göndərmə!
     */
    // public function chargeInsecure(Request $request)
    // {
    //     // HEÇ VAXT BU CÜRƏ ETMƏ!
    //     $cardNumber = $request->card_number;  // PCI DSS POZUNTUSU
    //     $cvv = $request->cvv;                  // PCI DSS POZUNTUSU
    //     Log::info("Card: " . $cardNumber);     // PCI DSS POZUNTUSU
    // }
}
```

### Frontend-də Tokenləşdirmə (Stripe.js)

*Frontend-də Tokenləşdirmə (Stripe.js) üçün kod nümunəsi:*
```javascript
// Kart məlumatları heç vaxt serverə getmir
// Stripe.js birbaşa Stripe-a göndərir və token qaytarır

const stripe = Stripe('pk_live_...');
const elements = stripe.elements();
const cardElement = elements.create('card');
cardElement.mount('#card-element');

async function handlePayment() {
    // 1. Stripe.js kart məlumatlarını birbaşa Stripe-a göndərir
    const { paymentMethod, error } = await stripe.createPaymentMethod({
        type: 'card',
        card: cardElement,
        billing_details: {
            name: document.getElementById('name').value,
        },
    });

    if (error) {
        showError(error.message);
        return;
    }

    // 2. Serverə YALNIZ token göndərilir (pm_xxx)
    const response = await fetch('/api/payments/charge', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
            payment_method_id: paymentMethod.id, // "pm_1KqX..." (kart nömrəsi yox!)
            amount: 5000, // 50.00 AZN (qəpiklərlə)
        }),
    });

    const result = await response.json();
    if (result.success) {
        showSuccess('Ödəniş uğurludur!');
    }
}
```

---

## 5. Tokenization (Tokenləşdirmə)

**Tokenization** — həssas məlumatları (kart nömrəsi) unikal, mənasız bir token ilə əvəz edir. Token-dən kart nömrəsini bərpa etmək mümkün deyil.

```
Tokenization Prosesi:

Kart nömrəsi: 4242 4242 4242 4242
        ↓ (Stripe serverində)
Token: tok_1KqXyz... (və ya pm_1KqXyz...)
        ↓
Database-də saxlanılır: tok_1KqXyz... (təhlükəsiz)

Token ilə ödəniş etmək olar, amma kart nömrəsini bilmək olmaz.
```

*Token ilə ödəniş etmək olar, amma kart nömrəsini bilmək olmaz üçün kod nümunəsi:*
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'customer_id',
        'gateway',                // stripe, paypal
        'gateway_payment_method_id', // pm_1KqXyz... (token)
        'type',                   // card, bank_account
        'last4',                  // 4242
        'brand',                  // visa
        'exp_month',              // 12
        'exp_year',               // 2027
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'exp_month' => 'integer',
        'exp_year' => 'integer',
    ];

    // HEÇ VAXT kart nömrəsini və ya CVV-ni saxlama!
    // Yalnız token, last4, brand, exp_month, exp_year saxlanılır.

    public function isExpired(): bool
    {
        return Carbon::createFromDate($this->exp_year, $this->exp_month)->endOfMonth()->isPast();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
```

*return $this->belongsTo(Customer::class); üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

class TokenizationService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
    ) {}

    /**
     * Client-dan gələn token-i customer-ə bağlayır.
     * Sonradan recurring payment üçün istifadə olunacaq.
     */
    public function attachPaymentMethod(
        string $customerId,
        string $paymentMethodToken,
    ): PaymentMethod {
        // Gateway-də müştəriyə payment method əlavə et
        $gatewayResult = $this->gateway->attachPaymentMethod(
            customerToken: $customerId,
            paymentMethodToken: $paymentMethodToken,
        );

        // Lokal database-ə yaz (yalnız təhlükəsiz məlumatlar)
        return PaymentMethod::create([
            'customer_id' => $customerId,
            'gateway' => $this->gateway->getName(),
            'gateway_payment_method_id' => $gatewayResult->id,
            'type' => $gatewayResult->type,
            'last4' => $gatewayResult->card->last4,
            'brand' => $gatewayResult->card->brand,
            'exp_month' => $gatewayResult->card->expMonth,
            'exp_year' => $gatewayResult->card->expYear,
        ]);
    }
}
```

---

## 6. 3D Secure (3DS)

**3D Secure** — kartla ödəniş zamanı əlavə autentifikasiya addımı. Müştəri bankın səhifəsinə yönləndirilir və OTP (birdəfəlik kod) və ya biometrik təsdiq edir. Fraud-u əhəmiyyətli dərəcədə azaldır.

```
3DS Axını:

1. Müştəri kart məlumatlarını daxil edir
2. Payment gateway 3DS tələb edir
3. Müştəri bankın autentifikasiya səhifəsinə yönləndirilir
4. Müştəri OTP/biometrik ilə təsdiq edir
5. Bank nəticəni gateway-ə qaytarır
6. Gateway ödənişi tamamlayır

3DS 1.0 → Pop-up pəncərə (köhnə, pis UX)
3DS 2.0 → Inline iframe, frictionless flow (yaxşı UX)
```

*3DS 2.0 → Inline iframe, frictionless flow (yaxşı UX) üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

class ThreeDSecureService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private PaymentRepository $payments,
    ) {}

    /**
     * 3DS tələb edən ödəniş yaratmaq.
     * PaymentIntent (Stripe) və ya ödəniş sessiyası yaradılır.
     */
    public function createPaymentRequiring3DS(PaymentRequest $request): ThreeDSResult
    {
        $payment = $this->payments->create([
            'amount' => $request->amount,
            'currency' => $request->currency,
            'status' => PaymentStatus::REQUIRES_ACTION,
        ]);

        $intent = $this->gateway->createPaymentIntent(
            amount: $request->amount,
            currency: $request->currency,
            paymentMethod: $request->paymentMethodToken,
            confirm: true,
            returnUrl: route('payments.3ds.callback', $payment->id),
        );

        if ($intent->requiresAction()) {
            // 3DS autentifikasiya lazımdır
            $payment->update([
                'status' => PaymentStatus::REQUIRES_ACTION,
                'gateway_transaction_id' => $intent->id,
            ]);

            return ThreeDSResult::requiresAction(
                payment: $payment,
                clientSecret: $intent->clientSecret, // Frontend-ə göndərilir
                redirectUrl: $intent->redirectUrl,    // Bankın 3DS səhifəsi
            );
        }

        if ($intent->isSucceeded()) {
            $payment->update([
                'status' => PaymentStatus::CAPTURED,
                'gateway_transaction_id' => $intent->id,
            ]);

            return ThreeDSResult::success($payment);
        }

        return ThreeDSResult::failed($payment, $intent->failureMessage);
    }

    /**
     * 3DS callback — bank müştərini geri yönləndirir.
     */
    public function handle3DSCallback(string $paymentId): PaymentResult
    {
        $payment = $this->payments->findOrFail($paymentId);

        $intent = $this->gateway->retrievePaymentIntent(
            $payment->gateway_transaction_id
        );

        return match ($intent->status) {
            'succeeded' => $this->handleSucceeded($payment, $intent),
            'requires_payment_method' => $this->handleFailed($payment, $intent),
            'canceled' => $this->handleCanceled($payment),
            default => throw new UnexpectedPaymentStateException(),
        };
    }
}
```

*default => throw new UnexpectedPaymentStateException(), üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Controllers;

class ThreeDSecureController extends Controller
{
    /**
     * 3DS tamamlandıqdan sonra müştəri bura qayıdır.
     */
    public function callback(string $paymentId, Request $request): RedirectResponse
    {
        $result = $this->threeDSecureService->handle3DSCallback($paymentId);

        if ($result->isSuccess()) {
            return redirect()->route('orders.success', $result->payment->order_id)
                ->with('message', 'Ödəniş uğurla tamamlandı!');
        }

        return redirect()->route('checkout.payment')
            ->withErrors(['payment' => 'Ödəniş uğursuz oldu. Zəhmət olmasa yenidən cəhd edin.']);
    }
}
```

---

## 7. Idempotency Key (İkili Ödənişin Qarşısının Alınması)

**Idempotency** — eyni əməliyyatın bir neçə dəfə göndərilməsinin eyni nəticə verməsini təmin edir. Network timeout, retry və s. hallarda dublikat ödənişin qarşısını alır.

```
Problem:
Müştəri "Ödə" düyməsinə basır → Request göndərilir → Timeout olur
Müştəri yenidən basır → İkinci request göndərilir → İKİ DƏFƏ PUL ÇIXIlIR!

Həll (Idempotency Key):
Request 1: POST /payments {idempotency_key: "abc123"} → Ödəniş yaradılır
Request 2: POST /payments {idempotency_key: "abc123"} → Eyni ödəniş qaytarılır (yeni ödəniş yox)
```

*Request 2: POST /payments {idempotency_key: "abc123"} → Eyni ödəniş qa üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency middleware: eyni idempotency key ilə gələn
 * ikinci request-ə əvvəlki cavabı qaytarır.
 */
class IdempotencyMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        // Yalnız yazma əməliyyatları üçün (POST, PUT, PATCH)
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');
        if (!$idempotencyKey) {
            return $next($request);
        }

        $cacheKey = "idempotency:{$idempotencyKey}";

        // Əvvəlki cavab varsa, onu qaytar
        $cachedResponse = Cache::get($cacheKey);
        if ($cachedResponse) {
            return response()->json(
                data: $cachedResponse['body'],
                status: $cachedResponse['status'],
                headers: ['Idempotency-Replayed' => 'true'],
            );
        }

        // Əgər eyni key ilə paralel request işləyirsə, gözlə (lock)
        $lock = Cache::lock("idempotency_lock:{$idempotencyKey}", 30);
        if (!$lock->get()) {
            return response()->json(
                ['error' => 'A request with this idempotency key is already being processed.'],
                409
            );
        }

        try {
            $response = $next($request);

            // Cavabı cache-lə (24 saat)
            if ($response->isSuccessful() || $response->isClientError()) {
                Cache::put($cacheKey, [
                    'body' => json_decode($response->getContent(), true),
                    'status' => $response->getStatusCode(),
                ], now()->addHours(24));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
```

*$lock->release(); üçün kod nümunəsi:*
```php
<?php

// routes/api.php
Route::middleware(['auth:sanctum', 'idempotency'])->group(function () {
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::post('/payments/{payment}/capture', [PaymentController::class, 'capture']);
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);
});
```

*Route::post('/payments/{payment}/refund', [PaymentController::class, ' üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

class PaymentService
{
    /**
     * Database səviyyəsində idempotency yoxlaması.
     * Cache-dən əlavə database unique constraint istifadə edir.
     */
    public function createPayment(PaymentRequest $request): Payment
    {
        // Database unique constraint ilə dublikat qorunması
        return DB::transaction(function () use ($request) {
            // Əvvəlcə mövcud ödəniş yoxla
            $existing = Payment::where('idempotency_key', $request->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing; // Dublikat — mövcud ödənişi qaytar
            }

            // Yeni ödəniş yarat
            return Payment::create([
                'idempotency_key' => $request->idempotencyKey,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'status' => PaymentStatus::PENDING,
            ]);
        });
    }
}
```

### Migration (idempotency_key üçün unique index)

*Migration (idempotency_key üçün unique index) üçün kod nümunəsi:*
```php
<?php

// database/migrations/xxxx_create_payments_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key')->unique(); // Dublikat qorunması
            $table->foreignUuid('customer_id')->constrained();
            $table->foreignUuid('order_id')->nullable()->constrained();
            $table->string('gateway');                // stripe, paypal
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->string('status');                 // pending, authorized, captured...
            $table->integer('amount');                // qəpiklərlə (5000 = 50.00 AZN)
            $table->string('currency', 3);            // AZN, USD, EUR
            $table->integer('captured_amount')->default(0);
            $table->integer('refunded_amount')->default(0);
            $table->string('payment_method_type')->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('card_brand')->nullable();
            $table->string('authorization_code')->nullable();
            $table->string('decline_reason')->nullable();
            $table->string('decline_code')->nullable();
            $table->string('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('authorization_expires_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['order_id']);
            $table->index(['created_at']);
        });
    }
};
```

---

## 8. Payment State Machine

Ödəniş əməliyyatı müəyyən statuslar arasında keçir. Hər keçid (transition) müəyyən qaydalara tabedir.

```
Payment State Machine:

                    ┌─────────┐
                    │ PENDING │ (Başlanğıc)
                    └────┬────┘
                         │
                    ┌────▼────┐
               ┌────│AUTHORIZED│────┐
               │    └────┬────┘    │
               │         │         │
          ┌────▼───┐ ┌───▼────┐ ┌──▼────┐
          │ VOIDED │ │CAPTURED│ │EXPIRED│
          └────────┘ └───┬────┘ └───────┘
                         │
              ┌──────────┼──────────┐
              │          │          │
        ┌─────▼────┐ ┌──▼────┐ ┌───▼──────────┐
        │ SETTLED  │ │REFUNDED│ │PARTIALLY_    │
        └──────────┘ └───────┘ │REFUNDED      │
                               └──────────────┘

    Əlavə keçidlər:
    PENDING → DECLINED (kart rədd)
    PENDING → FAILED (texniki xəta)
    AUTHORIZED → CAPTURE_FAILED
    CAPTURE_FAILED → CAPTURED (retry ilə)
```

*CAPTURE_FAILED → CAPTURED (retry ilə) üçün kod nümunəsi:*
```php
<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case AUTHORIZED = 'authorized';
    case CAPTURED = 'captured';
    case SETTLED = 'settled';
    case VOIDED = 'voided';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case DECLINED = 'declined';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case REQUIRES_ACTION = 'requires_action'; // 3DS gözləyir
    case CAPTURE_FAILED = 'capture_failed';
    case DISPUTED = 'disputed';  // Chargeback

    /**
     * Bu statusdan hansı statuslara keçmək olar.
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [
                self::AUTHORIZED,
                self::CAPTURED,      // auth+capture birlikdə
                self::DECLINED,
                self::FAILED,
                self::REQUIRES_ACTION,
            ],
            self::REQUIRES_ACTION => [
                self::AUTHORIZED,
                self::CAPTURED,
                self::FAILED,
                self::DECLINED,
            ],
            self::AUTHORIZED => [
                self::CAPTURED,
                self::VOIDED,
                self::EXPIRED,
                self::CAPTURE_FAILED,
            ],
            self::CAPTURE_FAILED => [
                self::CAPTURED,      // retry ilə
                self::VOIDED,
                self::FAILED,
            ],
            self::CAPTURED => [
                self::SETTLED,
                self::REFUNDED,
                self::PARTIALLY_REFUNDED,
                self::DISPUTED,
            ],
            self::SETTLED => [
                self::REFUNDED,
                self::PARTIALLY_REFUNDED,
                self::DISPUTED,
            ],
            self::PARTIALLY_REFUNDED => [
                self::REFUNDED,       // tam refund olana qədər
                self::DISPUTED,
            ],
            // Terminal states — buradan keçid yoxdur
            self::VOIDED, self::REFUNDED, self::DECLINED,
            self::FAILED, self::EXPIRED => [],
            self::DISPUTED => [
                self::CAPTURED,  // dispute uğursuz (merchant qazandı)
                self::REFUNDED,  // dispute uğurlu (müştəri qazandı)
            ],
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions());
    }

    public function isTerminal(): bool
    {
        return empty($this->allowedTransitions());
    }

    public function isCaptured(): bool
    {
        return in_array($this, [self::CAPTURED, self::SETTLED]);
    }
}
```

*return in_array($this, [self::CAPTURED, self::SETTLED]); üçün kod nümunəsi:*
```php
<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $casts = [
        'status' => PaymentStatus::class,
        'metadata' => 'array',
        'authorized_at' => 'datetime',
        'authorization_expires_at' => 'datetime',
        'captured_at' => 'datetime',
        'voided_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Status dəyişikliyini keçidlə təmin edir.
     * Yanlış keçidlərin qarşısını alır.
     */
    public function transitionTo(PaymentStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new InvalidPaymentStateException(
                "Cannot transition from {$this->status->value} to {$newStatus->value} " .
                "for payment {$this->id}. " .
                "Allowed transitions: " . implode(', ', array_map(
                    fn (PaymentStatus $s) => $s->value,
                    $this->status->allowedTransitions(),
                ))
            );
        }

        $oldStatus = $this->status;
        $this->status = $newStatus;
        $this->save();

        // Status dəyişikliyi tarixçəsinə yaz
        $this->statusHistory()->create([
            'from_status' => $oldStatus->value,
            'to_status' => $newStatus->value,
            'transitioned_at' => now(),
        ]);

        // Event-lər göndər
        event(new PaymentStatusChanged($this, $oldStatus, $newStatus));
    }

    public function canBeCaptured(): bool
    {
        return $this->status->canTransitionTo(PaymentStatus::CAPTURED);
    }

    public function canBeVoided(): bool
    {
        return $this->status->canTransitionTo(PaymentStatus::VOIDED);
    }

    public function canBeRefunded(): bool
    {
        return in_array($this->status, [
            PaymentStatus::CAPTURED,
            PaymentStatus::SETTLED,
            PaymentStatus::PARTIALLY_REFUNDED,
        ]);
    }

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PaymentStatusHistory::class);
    }
}
```

---

## 9. Webhook Handling

Payment gateway-ləri əməliyyat nəticəsi haqqında **webhook** (callback URL) vasitəsilə məlumat göndərir. Bu asinxron bildirimdir — gateway sizin endpoint-inizə HTTP POST göndərir.

### Niyə Webhook Lazımdır?

```
1. Asinxron əməliyyatlar (bank transfer, 3DS) — nəticə gec gəlir
2. Dispute/Chargeback — müştəri öz bankına şikayət edir
3. Subscription yenilənməsi — avtomatik ödəniş uğurlu/uğursuz
4. Payout — satıcıya pul köçürülməsi
5. Settlement — gündəlik hesablaşma
```

*5. Settlement — gündəlik hesablaşma üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    public function __construct(
        private WebhookProcessor $processor,
    ) {}

    /**
     * Stripe webhook-larını qəbul edir.
     * CSRF koruması deaktivdir (webhook route-unda).
     */
    public function handle(Request $request): Response
    {
        // 1. İmza yoxlaması — webhook-un həqiqətən Stripe-dan gəldiyini təsdiq et
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret'),
            );
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'ip' => $request->ip(),
                'signature' => $signature,
            ]);
            return response('Invalid signature', 400);
        }

        // 2. Dublikat webhook yoxlaması (idempotency)
        if (WebhookEvent::where('gateway_event_id', $event->id)->exists()) {
            // Artıq emal olunub — 200 qaytar ki, Stripe təkrar göndərməsin
            return response('Already processed', 200);
        }

        // 3. Webhook event-ini database-ə yaz
        $webhookEvent = WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => $event->id,
            'type' => $event->type,
            'payload' => json_decode($payload, true),
            'status' => 'pending',
        ]);

        // 4. Asinxron emal et (böyük yükdə queue istifadə et)
        ProcessWebhookEvent::dispatch($webhookEvent);

        // 5. Dərhal 200 qaytar (Stripe 5 saniyə timeout gözləyir)
        return response('Webhook received', 200);
    }
}
```

*return response('Webhook received', 200); üçün kod nümunəsi:*
```php
<?php

namespace App\Jobs;

use App\Models\WebhookEvent;

class ProcessWebhookEvent implements ShouldQueue
{
    public int $tries = 5;
    public array $backoff = [30, 60, 300, 900, 3600]; // retry gecikmələri

    public function __construct(
        private WebhookEvent $webhookEvent,
    ) {}

    public function handle(WebhookHandlerFactory $factory): void
    {
        $handler = $factory->make($this->webhookEvent->type);

        try {
            $handler->process($this->webhookEvent);

            $this->webhookEvent->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

        } catch (\Throwable $e) {
            $this->webhookEvent->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e; // Job retry mexanizmi işləyir
        }
    }
}
```

*throw $e; // Job retry mexanizmi işləyir üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Webhooks;

class WebhookHandlerFactory
{
    /**
     * Webhook tipinə görə uyğun handler qaytarır.
     */
    public function make(string $eventType): WebhookHandlerInterface
    {
        return match ($eventType) {
            'payment_intent.succeeded' => app(PaymentSucceededHandler::class),
            'payment_intent.payment_failed' => app(PaymentFailedHandler::class),
            'charge.refunded' => app(ChargeRefundedHandler::class),
            'charge.dispute.created' => app(DisputeCreatedHandler::class),
            'charge.dispute.closed' => app(DisputeClosedHandler::class),
            'customer.subscription.created' => app(SubscriptionCreatedHandler::class),
            'customer.subscription.updated' => app(SubscriptionUpdatedHandler::class),
            'customer.subscription.deleted' => app(SubscriptionDeletedHandler::class),
            'invoice.paid' => app(InvoicePaidHandler::class),
            'invoice.payment_failed' => app(InvoicePaymentFailedHandler::class),
            default => app(UnhandledWebhookHandler::class),
        };
    }
}
```

*default => app(UnhandledWebhookHandler::class), üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Webhooks\Handlers;

class PaymentSucceededHandler implements WebhookHandlerInterface
{
    public function process(WebhookEvent $event): void
    {
        $paymentIntentId = $event->payload['data']['object']['id'];
        $amount = $event->payload['data']['object']['amount'];

        $payment = Payment::where('gateway_transaction_id', $paymentIntentId)->first();

        if (!$payment) {
            Log::warning('Payment not found for webhook', [
                'payment_intent_id' => $paymentIntentId,
                'event_id' => $event->gateway_event_id,
            ]);
            return;
        }

        // Artıq captured statusundadırsa, heç nə etmə
        if ($payment->status === PaymentStatus::CAPTURED) {
            return;
        }

        $payment->transitionTo(PaymentStatus::CAPTURED);
        $payment->update([
            'captured_amount' => $amount,
            'captured_at' => now(),
        ]);

        // Sifarişi yenilə
        $payment->order?->markAsPaid();

        // Bildiriş göndər
        $payment->customer->notify(new PaymentConfirmation($payment));
    }
}
```

### Webhook Route Konfiqurasiyası

*Webhook Route Konfiqurasiyası üçün kod nümunəsi:*
```php
<?php

// routes/api.php - CSRF koruması olmadan
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');

Route::post('/webhooks/paypal', [PayPalWebhookController::class, 'handle'])
    ->name('webhooks.paypal');
```

*->name('webhooks.paypal'); üçün kod nümunəsi:*
```php
<?php

// app/Http/Middleware/VerifyCsrfToken.php
class VerifyCsrfToken extends Middleware
{
    /**
     * Webhook endpoint-ləri CSRF yoxlamasından azad edilir.
     */
    protected $except = [
        'webhooks/*',
    ];
}
```

---

## 10. Strategy Pattern ilə Multi-Gateway Support

Bir neçə ödəniş gateway-ini dəstəkləmək üçün **Strategy Pattern** istifadə olunur. Hər gateway eyni interfeysi implement edir.

### PaymentGateway Interface

*PaymentGateway Interface üçün kod nümunəsi:*
```php
<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Gateway adı.
     */
    public function getName(): string;

    /**
     * Kartda məbləği bloklayır (authorization).
     */
    public function authorize(
        int $amount,
        string $currency,
        string $paymentMethod,
        array $metadata = [],
    ): GatewayResponse;

    /**
     * Avtorizasiya olunmuş məbləği tutma (capture).
     */
    public function capture(
        string $transactionId,
        ?int $amount = null,
    ): GatewayResponse;

    /**
     * Avtorizasiya + Capture birlikdə (sale / direct charge).
     */
    public function charge(
        int $amount,
        string $currency,
        string $paymentMethod,
        array $metadata = [],
    ): GatewayResponse;

    /**
     * Avtorizasiyanı ləğv et (void).
     */
    public function void(string $transactionId): GatewayResponse;

    /**
     * Geri qaytarma (refund).
     */
    public function refund(
        string $transactionId,
        ?int $amount = null,
    ): GatewayResponse;

    /**
     * Payment Intent yarat (3DS dəstəyi ilə).
     */
    public function createPaymentIntent(
        int $amount,
        string $currency,
        string $paymentMethod,
        bool $confirm = false,
        ?string $returnUrl = null,
    ): PaymentIntentResponse;

    /**
     * Customer yarat.
     */
    public function createCustomer(
        string $email,
        string $name,
        array $metadata = [],
    ): CustomerResponse;

    /**
     * Payment method-u customer-ə bağla.
     */
    public function attachPaymentMethod(
        string $customerToken,
        string $paymentMethodToken,
    ): PaymentMethodResponse;
}
```

### GatewayResponse DTO

*GatewayResponse DTO üçün kod nümunəsi:*
```php
<?php

namespace App\DTOs;

final readonly class GatewayResponse
{
    public function __construct(
        public bool $success,
        public string $transactionId,
        public string $status,
        public ?string $authorizationCode = null,
        public ?string $failureMessage = null,
        public ?string $failureCode = null,
        public array $rawResponse = [],
    ) {}

    public static function success(string $transactionId, string $status, array $raw = []): self
    {
        return new self(
            success: true,
            transactionId: $transactionId,
            status: $status,
            rawResponse: $raw,
        );
    }

    public static function failed(string $message, string $code, array $raw = []): self
    {
        return new self(
            success: false,
            transactionId: '',
            status: 'failed',
            failureMessage: $message,
            failureCode: $code,
            rawResponse: $raw,
        );
    }
}
```

### Stripe Gateway Implementation

*Stripe Gateway Implementation üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\PaymentGatewayInterface;
use Stripe\StripeClient;

class StripeGateway implements PaymentGatewayInterface
{
    private StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function authorize(
        int $amount,
        string $currency,
        string $paymentMethod,
        array $metadata = [],
    ): GatewayResponse {
        try {
            $intent = $this->client->paymentIntents->create([
                'amount' => $amount,
                'currency' => strtolower($currency),
                'payment_method' => $paymentMethod,
                'capture_method' => 'manual', // Yalnız authorize (capture etmə)
                'confirm' => true,
                'metadata' => $metadata,
                'return_url' => $metadata['return_url'] ?? config('app.url') . '/payments/callback',
            ]);

            return GatewayResponse::success(
                transactionId: $intent->id,
                status: $intent->status,
                raw: $intent->toArray(),
            );

        } catch (\Stripe\Exception\CardException $e) {
            throw new PaymentDeclinedException(
                reason: $e->getError()->message,
                code: $e->getError()->decline_code ?? $e->getError()->code,
            );

        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new PaymentGatewayException(
                "Stripe API error: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function capture(string $transactionId, ?int $amount = null): GatewayResponse
    {
        try {
            $params = [];
            if ($amount !== null) {
                $params['amount_to_capture'] = $amount;
            }

            $intent = $this->client->paymentIntents->capture($transactionId, $params);

            return GatewayResponse::success(
                transactionId: $intent->id,
                status: $intent->status,
                raw: $intent->toArray(),
            );

        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new PaymentGatewayException("Stripe capture failed: {$e->getMessage()}", previous: $e);
        }
    }

    public function charge(
        int $amount,
        string $currency,
        string $paymentMethod,
        array $metadata = [],
    ): GatewayResponse {
        try {
            $intent = $this->client->paymentIntents->create([
                'amount' => $amount,
                'currency' => strtolower($currency),
                'payment_method' => $paymentMethod,
                'capture_method' => 'automatic', // Dərhal capture et
                'confirm' => true,
                'metadata' => $metadata,
                'return_url' => $metadata['return_url'] ?? config('app.url') . '/payments/callback',
            ]);

            return GatewayResponse::success(
                transactionId: $intent->id,
                status: $intent->status,
                raw: $intent->toArray(),
            );

        } catch (\Stripe\Exception\CardException $e) {
            throw new PaymentDeclinedException(
                reason: $e->getError()->message,
                code: $e->getError()->decline_code ?? $e->getError()->code,
            );
        }
    }

    public function void(string $transactionId): GatewayResponse
    {
        try {
            $intent = $this->client->paymentIntents->cancel($transactionId);

            return GatewayResponse::success(
                transactionId: $intent->id,
                status: 'voided',
                raw: $intent->toArray(),
            );

        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new PaymentGatewayException("Stripe void failed: {$e->getMessage()}", previous: $e);
        }
    }

    public function refund(string $transactionId, ?int $amount = null): GatewayResponse
    {
        try {
            $params = ['payment_intent' => $transactionId];
            if ($amount !== null) {
                $params['amount'] = $amount;
            }

            $refund = $this->client->refunds->create($params);

            return GatewayResponse::success(
                transactionId: $refund->id,
                status: $refund->status,
                raw: $refund->toArray(),
            );

        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new PaymentGatewayException("Stripe refund failed: {$e->getMessage()}", previous: $e);
        }
    }

    public function createPaymentIntent(
        int $amount,
        string $currency,
        string $paymentMethod,
        bool $confirm = false,
        ?string $returnUrl = null,
    ): PaymentIntentResponse {
        $intent = $this->client->paymentIntents->create([
            'amount' => $amount,
            'currency' => strtolower($currency),
            'payment_method' => $paymentMethod,
            'confirm' => $confirm,
            'return_url' => $returnUrl,
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        return new PaymentIntentResponse(
            id: $intent->id,
            clientSecret: $intent->client_secret,
            status: $intent->status,
            requiresAction: $intent->status === 'requires_action',
            redirectUrl: $intent->next_action?->redirect_to_url?->url,
        );
    }

    public function createCustomer(string $email, string $name, array $metadata = []): CustomerResponse
    {
        $customer = $this->client->customers->create([
            'email' => $email,
            'name' => $name,
            'metadata' => $metadata,
        ]);

        return new CustomerResponse(
            id: $customer->id,
            email: $customer->email,
            name: $customer->name,
        );
    }

    public function attachPaymentMethod(
        string $customerToken,
        string $paymentMethodToken,
    ): PaymentMethodResponse {
        $pm = $this->client->paymentMethods->attach($paymentMethodToken, [
            'customer' => $customerToken,
        ]);

        return new PaymentMethodResponse(
            id: $pm->id,
            type: $pm->type,
            card: new CardDetails(
                last4: $pm->card->last4,
                brand: $pm->card->brand,
                expMonth: $pm->card->exp_month,
                expYear: $pm->card->exp_year,
                fingerprint: $pm->card->fingerprint,
            ),
        );
    }
}
```

### PayPal Gateway Implementation

*PayPal Gateway Implementation üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\PaymentGatewayInterface;

class PayPalGateway implements PaymentGatewayInterface
{
    private PayPalHttpClient $client;

    public function __construct()
    {
        $environment = config('services.paypal.sandbox')
            ? new SandboxEnvironment(
                config('services.paypal.client_id'),
                config('services.paypal.secret'),
            )
            : new ProductionEnvironment(
                config('services.paypal.client_id'),
                config('services.paypal.secret'),
            );

        $this->client = new PayPalHttpClient($environment);
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function authorize(
        int $amount,
        string $currency,
        string $paymentMethod,
        array $metadata = [],
    ): GatewayResponse {
        $request = new OrdersCreateRequest();
        $request->body = [
            'intent' => 'AUTHORIZE', // Yalnız avtorizasiya
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => strtoupper($currency),
                        'value' => number_format($amount / 100, 2, '.', ''),
                    ],
                    'reference_id' => $metadata['order_id'] ?? uniqid(),
                ],
            ],
        ];

        try {
            $response = $this->client->execute($request);
            $order = $response->result;

            return GatewayResponse::success(
                transactionId: $order->id,
                status: $order->status,
                raw: (array) $order,
            );

        } catch (HttpException $e) {
            throw new PaymentGatewayException("PayPal authorization failed: {$e->getMessage()}");
        }
    }

    public function capture(string $transactionId, ?int $amount = null): GatewayResponse
    {
        // PayPal-da əvvəlcə authorization-ı tapıb capture etmək lazımdır
        $request = new AuthorizationsCaptureRequest($transactionId);
        if ($amount !== null) {
            $request->body = [
                'amount' => [
                    'value' => number_format($amount / 100, 2, '.', ''),
                    'currency_code' => 'USD',
                ],
            ];
        }

        $response = $this->client->execute($request);

        return GatewayResponse::success(
            transactionId: $response->result->id,
            status: $response->result->status,
            raw: (array) $response->result,
        );
    }

    public function charge(
        int $amount,
        string $currency,
        string $paymentMethod,
        array $metadata = [],
    ): GatewayResponse {
        $request = new OrdersCreateRequest();
        $request->body = [
            'intent' => 'CAPTURE', // Dərhal capture et
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => strtoupper($currency),
                        'value' => number_format($amount / 100, 2, '.', ''),
                    ],
                ],
            ],
        ];

        $response = $this->client->execute($request);

        return GatewayResponse::success(
            transactionId: $response->result->id,
            status: $response->result->status,
            raw: (array) $response->result,
        );
    }

    public function void(string $transactionId): GatewayResponse
    {
        $request = new AuthorizationsVoidRequest($transactionId);
        $this->client->execute($request);

        return GatewayResponse::success(
            transactionId: $transactionId,
            status: 'voided',
        );
    }

    public function refund(string $transactionId, ?int $amount = null): GatewayResponse
    {
        $request = new CapturesRefundRequest($transactionId);
        if ($amount !== null) {
            $request->body = [
                'amount' => [
                    'value' => number_format($amount / 100, 2, '.', ''),
                    'currency_code' => 'USD',
                ],
            ];
        }

        $response = $this->client->execute($request);

        return GatewayResponse::success(
            transactionId: $response->result->id,
            status: $response->result->status,
            raw: (array) $response->result,
        );
    }

    // ... digər metodlar
}
```

### Gateway Factory və Service Provider

*Gateway Factory və Service Provider üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;

class PaymentGatewayFactory
{
    /**
     * Gateway adına görə uyğun implementation qaytarır.
     */
    public function make(string $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            'stripe' => app(StripeGateway::class),
            'paypal' => app(PayPalGateway::class),
            'braintree' => app(BraintreeGateway::class),
            default => throw new UnsupportedGatewayException("Gateway '{$gateway}' is not supported."),
        };
    }

    /**
     * Default gateway qaytarır (config-dan).
     */
    public function default(): PaymentGatewayInterface
    {
        return $this->make(config('payment.default_gateway', 'stripe'));
    }
}
```

*return $this->make(config('payment.default_gateway', 'stripe')); üçün kod nümunəsi:*
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Default gateway-i bind et
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(PaymentGatewayFactory::class)->default();
        });

        // Singleton olaraq service-ləri register et
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(PaymentGatewayFactory::class);
    }

    public function boot(): void
    {
        // Config publish et
        $this->publishes([
            __DIR__ . '/../../config/payment.php' => config_path('payment.php'),
        ], 'payment-config');
    }
}
```

*], 'payment-config'); üçün kod nümunəsi:*
```php
<?php

// config/payment.php
return [
    'default_gateway' => env('PAYMENT_GATEWAY', 'stripe'),

    'gateways' => [
        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
            'sandbox' => env('PAYPAL_SANDBOX', true),
        ],
        'braintree' => [
            'environment' => env('BRAINTREE_ENV', 'sandbox'),
            'merchant_id' => env('BRAINTREE_MERCHANT_ID'),
            'public_key' => env('BRAINTREE_PUBLIC_KEY'),
            'private_key' => env('BRAINTREE_PRIVATE_KEY'),
        ],
    ],

    'currency' => env('PAYMENT_CURRENCY', 'AZN'),

    // Authorization-un keçərlilik müddəti
    'authorization_ttl_days' => 7,

    // Retry parametrləri
    'retry' => [
        'max_attempts' => 3,
        'backoff_minutes' => [5, 30, 120],
    ],
];
```

---

## 11. Laravel-də Tam Payment System Dizaynı

### PaymentService (Əsas Servis)

*PaymentService (Əsas Servis) üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use App\Enums\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private PaymentGatewayFactory $gatewayFactory,
        private PaymentRepository $payments,
        private AuditLogService $auditLog,
    ) {}

    /**
     * Yeni ödəniş yarat və authorize et.
     */
    public function authorize(PaymentRequest $request): PaymentResult
    {
        $gateway = $this->resolveGateway($request->gateway);

        return DB::transaction(function () use ($request, $gateway) {
            // Idempotency
            $existing = $this->payments->findByIdempotencyKey($request->idempotencyKey);
            if ($existing) {
                return PaymentResult::fromExisting($existing);
            }

            $payment = $this->payments->create([
                'idempotency_key' => $request->idempotencyKey,
                'customer_id' => $request->customerId,
                'order_id' => $request->orderId,
                'gateway' => $gateway->getName(),
                'amount' => $request->amount,
                'currency' => $request->currency,
                'status' => PaymentStatus::PENDING,
                'metadata' => $request->metadata,
            ]);

            $this->auditLog->log($payment, 'payment.created', [
                'amount' => $request->amount,
                'currency' => $request->currency,
            ]);

            try {
                $result = $gateway->authorize(
                    amount: $request->amount,
                    currency: $request->currency,
                    paymentMethod: $request->paymentMethodToken,
                    metadata: ['payment_id' => $payment->id],
                );

                $payment->transitionTo(PaymentStatus::AUTHORIZED);
                $payment->update([
                    'gateway_transaction_id' => $result->transactionId,
                    'authorization_code' => $result->authorizationCode,
                    'authorized_at' => now(),
                    'authorization_expires_at' => now()->addDays(
                        config('payment.authorization_ttl_days')
                    ),
                ]);

                $this->auditLog->log($payment, 'payment.authorized', [
                    'transaction_id' => $result->transactionId,
                ]);

                return PaymentResult::authorized($payment, $result);

            } catch (PaymentDeclinedException $e) {
                $payment->transitionTo(PaymentStatus::DECLINED);
                $payment->update([
                    'decline_reason' => $e->reason,
                    'decline_code' => $e->code,
                ]);

                $this->auditLog->log($payment, 'payment.declined', [
                    'reason' => $e->reason,
                    'code' => $e->code,
                ]);

                return PaymentResult::declined($payment, $e->reason);

            } catch (\Throwable $e) {
                $payment->transitionTo(PaymentStatus::FAILED);
                $payment->update(['error_message' => $e->getMessage()]);

                Log::error('Payment authorization failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new PaymentFailedException(
                    "Authorization failed for payment {$payment->id}",
                    previous: $e,
                );
            }
        });
    }

    /**
     * Avtorizasiya olunmuş ödənişi capture et.
     */
    public function capture(string $paymentId, ?int $amount = null): PaymentResult
    {
        $payment = $this->payments->findOrFail($paymentId);
        $gateway = $this->resolveGateway($payment->gateway);

        if (!$payment->canBeCaptured()) {
            throw new InvalidPaymentStateException(
                "Payment {$paymentId} cannot be captured (status: {$payment->status->value})"
            );
        }

        if ($payment->authorization_expires_at?->isPast()) {
            $payment->transitionTo(PaymentStatus::EXPIRED);
            throw new AuthorizationExpiredException("Authorization expired for payment {$paymentId}");
        }

        $captureAmount = $amount ?? $payment->amount;

        try {
            $result = $gateway->capture(
                transactionId: $payment->gateway_transaction_id,
                amount: $captureAmount,
            );

            $payment->transitionTo(PaymentStatus::CAPTURED);
            $payment->update([
                'captured_amount' => $captureAmount,
                'captured_at' => now(),
            ]);

            $this->auditLog->log($payment, 'payment.captured', [
                'captured_amount' => $captureAmount,
            ]);

            event(new PaymentCaptured($payment));

            return PaymentResult::captured($payment, $result);

        } catch (\Throwable $e) {
            $payment->transitionTo(PaymentStatus::CAPTURE_FAILED);

            Log::error('Payment capture failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Tam ödəniş prosesi: authorize + capture (sale).
     */
    public function charge(PaymentRequest $request): PaymentResult
    {
        $gateway = $this->resolveGateway($request->gateway);

        return DB::transaction(function () use ($request, $gateway) {
            $existing = $this->payments->findByIdempotencyKey($request->idempotencyKey);
            if ($existing) {
                return PaymentResult::fromExisting($existing);
            }

            $payment = $this->payments->create([
                'idempotency_key' => $request->idempotencyKey,
                'customer_id' => $request->customerId,
                'order_id' => $request->orderId,
                'gateway' => $gateway->getName(),
                'amount' => $request->amount,
                'currency' => $request->currency,
                'status' => PaymentStatus::PENDING,
            ]);

            try {
                $result = $gateway->charge(
                    amount: $request->amount,
                    currency: $request->currency,
                    paymentMethod: $request->paymentMethodToken,
                    metadata: ['payment_id' => $payment->id],
                );

                $payment->transitionTo(PaymentStatus::CAPTURED);
                $payment->update([
                    'gateway_transaction_id' => $result->transactionId,
                    'captured_amount' => $request->amount,
                    'authorized_at' => now(),
                    'captured_at' => now(),
                ]);

                event(new PaymentCaptured($payment));

                return PaymentResult::captured($payment, $result);

            } catch (PaymentDeclinedException $e) {
                $payment->transitionTo(PaymentStatus::DECLINED);
                return PaymentResult::declined($payment, $e->reason);

            } catch (\Throwable $e) {
                $payment->transitionTo(PaymentStatus::FAILED);
                throw new PaymentFailedException("Charge failed", previous: $e);
            }
        });
    }

    private function resolveGateway(?string $gateway = null): PaymentGatewayInterface
    {
        return $gateway
            ? $this->gatewayFactory->make($gateway)
            : $this->gatewayFactory->default();
    }
}
```

### Payment Controller

*Payment Controller üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\CapturePaymentRequest;
use App\Http\Requests\RefundPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\Payment\PaymentService;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
    ) {}

    /**
     * Ödəniş yarat (authorize + capture).
     *
     * POST /api/payments
     */
    public function store(CreatePaymentRequest $request): JsonResponse
    {
        $result = $this->paymentService->charge(
            new PaymentRequest(
                amount: $request->amount,
                currency: $request->currency ?? config('payment.currency'),
                paymentMethodToken: $request->payment_method_id,
                customerId: $request->user()->customer_id,
                orderId: $request->order_id,
                idempotencyKey: $request->header('Idempotency-Key', Str::uuid()->toString()),
                gateway: $request->gateway,
                metadata: $request->metadata ?? [],
            ),
        );

        return response()->json(
            new PaymentResource($result->payment),
            $result->isSuccess() ? 201 : 422,
        );
    }

    /**
     * Yalnız authorize et (capture sonra olacaq).
     *
     * POST /api/payments/authorize
     */
    public function authorize(CreatePaymentRequest $request): JsonResponse
    {
        $result = $this->paymentService->authorize(
            new PaymentRequest(
                amount: $request->amount,
                currency: $request->currency ?? config('payment.currency'),
                paymentMethodToken: $request->payment_method_id,
                customerId: $request->user()->customer_id,
                orderId: $request->order_id,
                idempotencyKey: $request->header('Idempotency-Key', Str::uuid()->toString()),
                gateway: $request->gateway,
            ),
        );

        return response()->json(new PaymentResource($result->payment), 201);
    }

    /**
     * Capture et.
     *
     * POST /api/payments/{payment}/capture
     */
    public function capture(CapturePaymentRequest $request, string $paymentId): JsonResponse
    {
        $result = $this->paymentService->capture($paymentId, $request->amount);

        return response()->json(new PaymentResource($result->payment));
    }

    /**
     * Refund et.
     *
     * POST /api/payments/{payment}/refund
     */
    public function refund(RefundPaymentRequest $request, string $paymentId): JsonResponse
    {
        $result = $this->paymentService->refund(
            paymentId: $paymentId,
            amount: $request->amount,
            reason: $request->reason ?? '',
        );

        return response()->json(new PaymentResource($result->payment));
    }

    /**
     * Ödəniş detallarını göstər.
     *
     * GET /api/payments/{payment}
     */
    public function show(string $paymentId): JsonResponse
    {
        $payment = Payment::with(['refunds', 'statusHistory', 'customer'])->findOrFail($paymentId);

        return response()->json(new PaymentResource($payment));
    }
}
```

### Retry Logic for Failed Payments

*Retry Logic for Failed Payments üçün kod nümunəsi:*
```php
<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Enums\PaymentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class RetryFailedPayment implements ShouldQueue
{
    use Queueable;

    public int $tries;
    public array $backoff;

    public function __construct(
        private string $paymentId,
    ) {
        $this->tries = config('payment.retry.max_attempts', 3);
        $this->backoff = config('payment.retry.backoff_minutes', [5, 30, 120]);
    }

    public function handle(PaymentService $paymentService): void
    {
        $payment = Payment::findOrFail($this->paymentId);

        // Artıq uğurludursa, retry etmə
        if ($payment->status->isCaptured()) {
            return;
        }

        // Yalnız retry edilə bilən statuslar
        if (!in_array($payment->status, [
            PaymentStatus::FAILED,
            PaymentStatus::CAPTURE_FAILED,
        ])) {
            return;
        }

        Log::info("Retrying failed payment", [
            'payment_id' => $this->paymentId,
            'attempt' => $this->attempts(),
        ]);

        try {
            if ($payment->status === PaymentStatus::CAPTURE_FAILED) {
                $paymentService->capture($this->paymentId);
            } else {
                // Tam yenidən charge et
                $paymentService->retryCharge($this->paymentId);
            }
        } catch (\Throwable $e) {
            Log::warning("Payment retry failed", [
                'payment_id' => $this->paymentId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Son cəhd uğursuz olsa, bildiriş göndər
            if ($this->attempts() >= $this->tries) {
                $payment->customer->notify(new PaymentRetryExhausted($payment));
                Log::error("All payment retries exhausted", ['payment_id' => $this->paymentId]);
            }

            throw $e;
        }
    }

    /**
     * Son cəhddən sonra job failed olduqda.
     */
    public function failed(\Throwable $e): void
    {
        Log::critical("Payment retry job permanently failed", [
            'payment_id' => $this->paymentId,
            'error' => $e->getMessage(),
        ]);
    }
}
```

### Expired Authorization Cleanup

*Expired Authorization Cleanup üçün kod nümunəsi:*
```php
<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Enums\PaymentStatus;

/**
 * Vaxtı bitmiş authorization-ları avtomatik expire edir.
 * Hər gün schedule ilə işləyir.
 */
class ExpireAuthorizationsCommand extends Command
{
    protected $signature = 'payments:expire-authorizations';
    protected $description = 'Expire stale payment authorizations';

    public function handle(): void
    {
        $expired = Payment::where('status', PaymentStatus::AUTHORIZED)
            ->where('authorization_expires_at', '<', now())
            ->get();

        foreach ($expired as $payment) {
            try {
                $payment->transitionTo(PaymentStatus::EXPIRED);

                Log::info('Authorization expired', ['payment_id' => $payment->id]);

                event(new AuthorizationExpired($payment));

            } catch (\Throwable $e) {
                Log::error('Failed to expire authorization', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Expired {$expired->count()} authorizations.");
    }
}
```

### Logging and Audit Trail

*Logging and Audit Trail üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

class AuditLogService
{
    /**
     * Ödəniş əməliyyatlarının tam tarixçəsini saxlayır.
     * PCI DSS tələb edir ki, bütün əməliyyatlar qeyd olunsun.
     */
    public function log(Payment $payment, string $action, array $context = []): void
    {
        PaymentAuditLog::create([
            'payment_id' => $payment->id,
            'action' => $action,
            'status_before' => $context['status_before'] ?? $payment->getOriginal('status'),
            'status_after' => $payment->status->value,
            'amount' => $context['amount'] ?? $payment->amount,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id(),
            'context' => $context,
            'performed_at' => now(),
        ]);

        // Əlavə olaraq structured log
        Log::channel('payment')->info($action, [
            'payment_id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'gateway' => $payment->gateway,
            ...$context,
        ]);
    }
}
```

*'gateway' => $payment->gateway, üçün kod nümunəsi:*
```php
<?php

// database/migrations/xxxx_create_payment_audit_logs_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('payment_id')->constrained()->cascadeOnDelete();
            $table->string('action');             // payment.created, payment.authorized, ...
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();
            $table->integer('amount')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->json('context')->nullable();
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index(['payment_id', 'performed_at']);
            $table->index(['action', 'performed_at']);
        });
    }
};
```

---

## 12. Laravel Cashier (Stripe)

**Laravel Cashier** — Stripe ilə subscription (abunəlik) əsaslı ödənişləri idarə etmək üçün Laravel-in rəsmi paketidir.

***Laravel Cashier** — Stripe ilə subscription (abunəlik) əsaslı ödəniş üçün kod nümunəsi:*
```bash
composer require laravel/cashier
php artisan migrate
```

*php artisan migrate üçün kod nümunəsi:*
```php
<?php

namespace App\Models;

use Laravel\Cashier\Billable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Billable; // Cashier trait

    // Bu trait aşağıdakıları əlavə edir:
    // - subscriptions() relationship
    // - subscription() metodu
    // - createAsStripeCustomer()
    // - charge(), refund()
    // - invoices(), downloadInvoice()
}
```

*// - invoices(), downloadInvoice() üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Controllers;

class SubscriptionController extends Controller
{
    /**
     * Yeni abunəlik yaratmaq.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:basic,pro,enterprise',
            'payment_method' => 'required|string',
        ]);

        $user = $request->user();

        // Stripe customer yarat (əgər yoxdursa)
        if (!$user->hasStripeId()) {
            $user->createAsStripeCustomer();
        }

        // Payment method əlavə et
        $user->updateDefaultPaymentMethod($request->payment_method);

        // Abunəlik yarat
        $subscription = $user->newSubscription('default', $this->getPriceId($request->plan))
            ->trialDays(14)           // 14 gün pulsuz sınaq
            ->create($request->payment_method);

        return response()->json([
            'subscription' => $subscription,
            'message' => 'Abunəlik uğurla yaradıldı!',
        ], 201);
    }

    /**
     * Plan dəyişdirmə (upgrade/downgrade).
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->subscription('default')->swap($this->getPriceId($request->plan));

        return response()->json(['message' => 'Plan dəyişdirildi.']);
    }

    /**
     * Abunəliyi ləğv etmə.
     */
    public function cancel(Request $request): JsonResponse
    {
        // Dövr sonunda ləğv et (dərhal yox)
        $request->user()->subscription('default')->cancel();

        return response()->json(['message' => 'Abunəlik dövr sonunda ləğv olacaq.']);
    }

    /**
     * Dərhal ləğv et.
     */
    public function cancelImmediately(Request $request): JsonResponse
    {
        $request->user()->subscription('default')->cancelNow();

        return response()->json(['message' => 'Abunəlik dərhal ləğv edildi.']);
    }

    /**
     * Abunəliyi bərpa et (ləğv edilmişsə).
     */
    public function resume(Request $request): JsonResponse
    {
        $request->user()->subscription('default')->resume();

        return response()->json(['message' => 'Abunəlik bərpa edildi.']);
    }

    /**
     * Faktura yükləmə.
     */
    public function downloadInvoice(Request $request, string $invoiceId)
    {
        return $request->user()->downloadInvoice($invoiceId, [
            'vendor' => 'Şirkətin adı',
            'product' => 'Abunəlik',
            'street' => 'Ünvan',
            'location' => 'Bakı, Azərbaycan',
            'phone' => '+994 XX XXX XX XX',
        ]);
    }

    private function getPriceId(string $plan): string
    {
        return match ($plan) {
            'basic' => config('cashier.prices.basic'),         // price_xxx
            'pro' => config('cashier.prices.pro'),             // price_xxx
            'enterprise' => config('cashier.prices.enterprise'), // price_xxx
        };
    }
}
```

---

## 13. Recurring Payments / Subscriptions (Manual)

Cashier istifadə etmədən manual subscription sistemi:

*Cashier istifadə etmədən manual subscription sistemi üçün kod nümunəsi:*
```php
<?php

namespace App\Models;

class Subscription extends Model
{
    protected $fillable = [
        'customer_id',
        'plan_id',
        'gateway',
        'gateway_subscription_id',
        'status',             // active, canceled, past_due, trialing, paused
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'canceled_at',
        'ends_at',            // null = aktiv, tarix = həmin tarixdə bitəcək
        'payment_method_id',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at?->isFuture() ?? false;
    }

    public function isCanceled(): bool
    {
        return $this->canceled_at !== null;
    }

    public function onGracePeriod(): bool
    {
        return $this->isCanceled() && $this->ends_at?->isFuture();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
```

*return $this->belongsTo(Plan::class); üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

class SubscriptionBillingService
{
    public function __construct(
        private PaymentService $paymentService,
        private SubscriptionRepository $subscriptions,
    ) {}

    /**
     * Hər gün işləyən recurring billing prosesi.
     * Subscription dövrü bitmiş müştərilərdən pul çıxır.
     */
    public function processRecurringBilling(): BillingReport
    {
        $report = new BillingReport();

        $dueSubscriptions = $this->subscriptions->getDueForBilling();

        foreach ($dueSubscriptions as $subscription) {
            try {
                $this->billSubscription($subscription);
                $report->addSuccess($subscription);
            } catch (PaymentDeclinedException $e) {
                $this->handleDeclinedPayment($subscription, $e);
                $report->addDeclined($subscription, $e->reason);
            } catch (\Throwable $e) {
                Log::error('Subscription billing failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
                $report->addFailed($subscription, $e->getMessage());
            }
        }

        return $report;
    }

    private function billSubscription(Subscription $subscription): void
    {
        $plan = $subscription->plan;

        $result = $this->paymentService->charge(
            new PaymentRequest(
                amount: $plan->price,
                currency: $plan->currency,
                paymentMethodToken: $subscription->payment_method_id,
                customerId: $subscription->customer_id,
                idempotencyKey: "sub_{$subscription->id}_" . now()->format('Y-m-d'),
                metadata: [
                    'subscription_id' => $subscription->id,
                    'plan_id' => $plan->id,
                    'billing_period' => now()->format('Y-m'),
                ],
            ),
        );

        if ($result->isSuccess()) {
            // Növbəti dövr tarixlərini yenilə
            $subscription->update([
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => $this->calculateNextBillingDate($plan),
            ]);
        }
    }

    /**
     * Ödəniş rədd olunduqda dunning prosesi (təkrar cəhd).
     * Adətən 3-4 dəfə cəhd edilir, müxtəlif günlərdə.
     */
    private function handleDeclinedPayment(
        Subscription $subscription,
        PaymentDeclinedException $e,
    ): void {
        $subscription->increment('failed_payment_attempts');

        if ($subscription->failed_payment_attempts >= 4) {
            // 4 uğursuz cəhddən sonra abunəliyi ləğv et
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'cancellation_reason' => 'payment_failed',
            ]);

            $subscription->customer->notify(
                new SubscriptionCanceledDueToPaymentFailure($subscription)
            );
        } else {
            // Abunəliyi "past_due" statusuna keçir
            $subscription->update(['status' => 'past_due']);

            // Müştəriyə bildiriş göndər
            $subscription->customer->notify(
                new PaymentFailedNotification($subscription, $e->reason)
            );

            // Növbəti retry-ı planla
            $retryDelay = match ($subscription->failed_payment_attempts) {
                1 => 3,   // 3 gün sonra
                2 => 5,   // 5 gün sonra
                3 => 7,   // 7 gün sonra
                default => 3,
            };

            RetrySubscriptionPayment::dispatch($subscription)
                ->delay(now()->addDays($retryDelay));
        }
    }

    private function calculateNextBillingDate(Plan $plan): Carbon
    {
        return match ($plan->interval) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'yearly' => now()->addYear(),
        };
    }
}
```

---

## 14. Split Payments (Bölünmüş Ödənişlər)

**Split payments** — bir ödənişi bir neçə alıcı arasında bölüşdürmək. Marketplace-lərdə (Uber, Airbnb, Etsy) çox istifadə olunur.

***Split payments** — bir ödənişi bir neçə alıcı arasında bölüşdürmək.  üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

class SplitPaymentService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
    ) {}

    /**
     * Stripe Connect ilə split payment.
     * Platform komissiya götürür, qalanı satıcıya gedir.
     *
     * Misal: 100 AZN ödəniş
     *   - Platform: 10 AZN (10% komissiya)
     *   - Satıcı: 90 AZN
     */
    public function createSplitPayment(SplitPaymentRequest $request): PaymentResult
    {
        // Stripe Connect: PaymentIntent yaratmaq
        $intent = $this->gateway->createPaymentIntent(
            amount: $request->totalAmount,
            currency: $request->currency,
            paymentMethod: $request->paymentMethodToken,
            metadata: [
                'order_id' => $request->orderId,
                'splits' => json_encode($request->splits),
            ],
            transferData: [
                // Satıcının Stripe Connected Account-u
                'destination' => $request->vendorStripeAccountId,
                // Platform komissiyası
                'application_fee_amount' => $request->platformFee,
            ],
        );

        return PaymentResult::success($intent);
    }

    /**
     * Çoxlu satıcıya bölmə (Separate Charges and Transfers).
     *
     * Misal: 200 AZN sifariş, 2 satıcıdan
     *   - Satıcı A: 80 AZN (Məhsul 1)
     *   - Satıcı B: 100 AZN (Məhsul 2)
     *   - Platform: 20 AZN (10% komissiya)
     */
    public function createMultiVendorPayment(MultiVendorPaymentRequest $request): PaymentResult
    {
        // 1. Tam məbləği platform hesabından çıx
        $charge = $this->gateway->charge(
            amount: $request->totalAmount,
            currency: $request->currency,
            paymentMethod: $request->paymentMethodToken,
        );

        // 2. Hər satıcıya transfer et
        foreach ($request->splits as $split) {
            $this->gateway->createTransfer(
                amount: $split->amount,
                currency: $request->currency,
                destination: $split->vendorStripeAccountId,
                sourceTransaction: $charge->transactionId,
                metadata: [
                    'vendor_id' => $split->vendorId,
                    'order_item_id' => $split->orderItemId,
                ],
            );
        }

        return PaymentResult::success($charge);
    }
}
```

*return PaymentResult::success($charge); üçün kod nümunəsi:*
```php
<?php

// Split Payment Request DTO
final readonly class SplitPaymentRequest
{
    /**
     * @param SplitItem[] $splits
     */
    public function __construct(
        public int $totalAmount,
        public string $currency,
        public string $paymentMethodToken,
        public string $orderId,
        public array $splits,
        public int $platformFee,
        public string $vendorStripeAccountId,
    ) {}
}

final readonly class SplitItem
{
    public function __construct(
        public string $vendorId,
        public string $vendorStripeAccountId,
        public int $amount,
        public ?string $orderItemId = null,
    ) {}
}
```

---

## 15. Escrow Payments (Əmanət Ödənişləri)

**Escrow** — ödəniş müəyyən şərt yerinə yetirilənə qədər saxlanılır. Müştəri ödəyir, lakin satıcı pulu yalnız iş tamamlandıqdan sonra alır.

```
Escrow axını:

1. Müştəri ödəyir → Pul escrow hesabında saxlanılır
2. Satıcı işi/malı təmin edir
3. Müştəri təsdiq edir (və ya müddət keçir)
4. Pul satıcıya köçürülür

Dispute olsa: Platform arbitraj edir
```

*Dispute olsa: Platform arbitraj edir üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

class EscrowService
{
    public function __construct(
        private PaymentService $paymentService,
        private EscrowRepository $escrows,
    ) {}

    /**
     * Escrow ödənişi yaratmaq.
     * Pul platform hesabında tutulur.
     */
    public function createEscrow(EscrowRequest $request): Escrow
    {
        // 1. Müştəridən pulu çıx (platform hesabına)
        $payment = $this->paymentService->charge(new PaymentRequest(
            amount: $request->amount,
            currency: $request->currency,
            paymentMethodToken: $request->paymentMethodToken,
            customerId: $request->buyerId,
            orderId: $request->orderId,
            idempotencyKey: $request->idempotencyKey,
        ));

        // 2. Escrow record yarat
        return $this->escrows->create([
            'payment_id' => $payment->id,
            'buyer_id' => $request->buyerId,
            'seller_id' => $request->sellerId,
            'amount' => $request->amount,
            'platform_fee' => $request->platformFee,
            'seller_amount' => $request->amount - $request->platformFee,
            'status' => EscrowStatus::HELD,
            'release_conditions' => $request->releaseConditions,
            'auto_release_at' => now()->addDays($request->autoReleaseDays ?? 14),
        ]);
    }

    /**
     * Escrow-u azad et — satıcıya pul köçür.
     */
    public function release(string $escrowId, string $releasedBy): Escrow
    {
        $escrow = $this->escrows->findOrFail($escrowId);

        if ($escrow->status !== EscrowStatus::HELD) {
            throw new InvalidEscrowStateException("Escrow cannot be released.");
        }

        // Satıcıya transfer et
        $this->paymentService->transferToSeller(
            amount: $escrow->seller_amount,
            sellerAccountId: $escrow->seller->stripe_account_id,
            sourcePaymentId: $escrow->payment_id,
        );

        $escrow->update([
            'status' => EscrowStatus::RELEASED,
            'released_at' => now(),
            'released_by' => $releasedBy,
        ]);

        $escrow->seller->notify(new EscrowReleasedNotification($escrow));

        return $escrow;
    }

    /**
     * Escrow-u müştəriyə qaytarma (dispute).
     */
    public function refundToBuyer(string $escrowId, string $reason): Escrow
    {
        $escrow = $this->escrows->findOrFail($escrowId);

        $this->paymentService->refund(
            paymentId: $escrow->payment_id,
            amount: $escrow->amount,
            reason: $reason,
        );

        $escrow->update([
            'status' => EscrowStatus::REFUNDED,
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ]);

        return $escrow;
    }
}
```

*həll yanaşmasını üçün kod nümunəsi:*
```php
<?php

// Avtomatik escrow release üçün scheduled command
namespace App\Console\Commands;

class AutoReleaseEscrowCommand extends Command
{
    protected $signature = 'escrow:auto-release';
    protected $description = 'Auto-release escrows past their release date';

    public function handle(EscrowService $escrowService): void
    {
        $escrows = Escrow::where('status', EscrowStatus::HELD)
            ->where('auto_release_at', '<=', now())
            ->get();

        foreach ($escrows as $escrow) {
            try {
                $escrowService->release($escrow->id, 'system_auto_release');
                $this->info("Released escrow: {$escrow->id}");
            } catch (\Throwable $e) {
                Log::error("Auto-release failed for escrow {$escrow->id}: {$e->getMessage()}");
            }
        }
    }
}
```

---

## 16. Currency Conversion (Valyuta Çevirməsi)

*16. Currency Conversion (Valyuta Çevirməsi) üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

class CurrencyConversionService
{
    public function __construct(
        private ExchangeRateProvider $rateProvider,
    ) {}

    /**
     * Valyuta çevirməsi.
     * Məbləğ qəpiklərlə (minor unit) işlənir.
     */
    public function convert(int $amount, string $from, string $to): ConversionResult
    {
        if ($from === $to) {
            return new ConversionResult(
                originalAmount: $amount,
                convertedAmount: $amount,
                fromCurrency: $from,
                toCurrency: $to,
                rate: 1.0,
            );
        }

        $rate = $this->rateProvider->getRate($from, $to);

        // Dəqiq hesablama üçün bcmath istifadə et (float dəqiqlik problemi yoxdur)
        $convertedAmount = (int) bcmul((string) $amount, (string) $rate, 0);

        return new ConversionResult(
            originalAmount: $amount,
            convertedAmount: $convertedAmount,
            fromCurrency: $from,
            toCurrency: $to,
            rate: $rate,
        );
    }

    /**
     * Multi-currency ödəniş.
     * Müştəri öz valyutasında ödəyir, satıcı öz valyutasında alır.
     */
    public function createMultiCurrencyPayment(
        int $amount,
        string $presentmentCurrency, // Müştərinin valyutası (AZN)
        string $settlementCurrency,  // Satıcının valyutası (USD)
    ): MultiCurrencyPayment {
        $conversion = $this->convert($amount, $presentmentCurrency, $settlementCurrency);

        return new MultiCurrencyPayment(
            presentmentAmount: $amount,
            presentmentCurrency: $presentmentCurrency,
            settlementAmount: $conversion->convertedAmount,
            settlementCurrency: $settlementCurrency,
            exchangeRate: $conversion->rate,
            rateLockedAt: now(),
            rateExpiresAt: now()->addMinutes(15), // 15 dəqiqə kurs qiymətini saxla
        );
    }
}
```

*rateExpiresAt: now()->addMinutes(15), // 15 dəqiqə kurs qiymətini saxl üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

/**
 * Valyuta məzənnəsi provayderi.
 * API-dən kursları alır və cache-ləyir.
 */
class ExchangeRateProvider
{
    public function __construct(
        private HttpClient $http,
    ) {}

    public function getRate(string $from, string $to): float
    {
        $cacheKey = "exchange_rate:{$from}:{$to}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($from, $to) {
            $response = $this->http->get("https://api.exchangerate-api.com/v4/latest/{$from}");
            $data = $response->json();

            return $data['rates'][$to]
                ?? throw new UnsupportedCurrencyException("Rate not found for {$from} to {$to}");
        });
    }
}
```

### Money Value Object (Pul Dəyəri)

*Money Value Object (Pul Dəyəri) üçün kod nümunəsi:*
```php
<?php

namespace App\ValueObjects;

/**
 * Money Value Object — pul dəyərini təmsil edir.
 * Float istifadə etmə! Hər zaman minor unit (qəpik) ilə işlə.
 */
final readonly class Money
{
    public function __construct(
        public int $amount,     // Qəpiklərlə: 5000 = 50.00 AZN
        public string $currency, // ISO 4217: AZN, USD, EUR
    ) {}

    public static function fromDecimal(float $amount, string $currency): self
    {
        $multiplier = self::getMinorUnitMultiplier($currency);
        return new self(
            amount: (int) round($amount * $multiplier),
            currency: $currency,
        );
    }

    public function toDecimal(): float
    {
        $multiplier = self::getMinorUnitMultiplier($this->currency);
        return $this->amount / $multiplier;
    }

    public function format(): string
    {
        $formatter = new \NumberFormatter('az_AZ', \NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($this->toDecimal(), $this->currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->amount * $factor), $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount > $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException(
                "Cannot operate on {$this->currency} and {$other->currency}"
            );
        }
    }

    /**
     * Valyutaya görə minor unit çarpanı.
     * Əksər valyutalar 100 (2 onluq), bəziləri fərqlidir.
     */
    private static function getMinorUnitMultiplier(string $currency): int
    {
        return match (strtoupper($currency)) {
            'JPY', 'KRW' => 1,       // Yapon yeni, Koreya vonu (0 onluq)
            'BHD', 'KWD' => 1000,    // Bəhreyn dinarı, Küveyt dinarı (3 onluq)
            default => 100,            // Əksər valyutalar (2 onluq)
        };
    }
}
```

---

## 17. Payment Reconciliation (Ödəniş Uyğunlaşdırılması)

**Reconciliation** — öz verilənlər bazanızdakı ödəniş qeydlərini gateway-in hesabatları ilə müqayisə etmək. Uyğunsuzluqları tapmaq üçün gündəlik/həftəlik edilir.

***Reconciliation** — öz verilənlər bazanızdakı ödəniş qeydlərini gatew üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

class ReconciliationService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private PaymentRepository $payments,
    ) {}

    /**
     * Gündəlik reconciliation: database ilə gateway-i müqayisə et.
     */
    public function reconcile(\DateTimeInterface $date): ReconciliationReport
    {
        $report = new ReconciliationReport($date);

        // 1. Gateway-dən həmin günün əməliyyatlarını al
        $gatewayTransactions = $this->gateway->listTransactions(
            startDate: Carbon::parse($date)->startOfDay(),
            endDate: Carbon::parse($date)->endOfDay(),
        );

        // 2. Database-dəki ödənişləri al
        $localPayments = $this->payments->getByDate($date);

        // 3. Gateway-də var amma database-də yoxdur
        foreach ($gatewayTransactions as $transaction) {
            $local = $localPayments->firstWhere('gateway_transaction_id', $transaction->id);

            if (!$local) {
                $report->addMissing('local', $transaction);
                continue;
            }

            // Məbləğ uyğunsuzluğu
            if ($local->captured_amount !== $transaction->amount) {
                $report->addAmountMismatch($local, $transaction);
            }

            // Status uyğunsuzluğu
            if ($this->mapGatewayStatus($transaction->status) !== $local->status->value) {
                $report->addStatusMismatch($local, $transaction);
            }
        }

        // 4. Database-də var amma gateway-də yoxdur
        $gatewayIds = collect($gatewayTransactions)->pluck('id');
        foreach ($localPayments as $payment) {
            if ($payment->gateway_transaction_id && !$gatewayIds->contains($payment->gateway_transaction_id)) {
                $report->addMissing('gateway', $payment);
            }
        }

        // 5. Hesabat yaz
        ReconciliationLog::create([
            'date' => $date,
            'total_gateway_transactions' => count($gatewayTransactions),
            'total_local_payments' => $localPayments->count(),
            'mismatches' => $report->getMismatchCount(),
            'missing_local' => $report->getMissingLocalCount(),
            'missing_gateway' => $report->getMissingGatewayCount(),
            'report_data' => $report->toArray(),
        ]);

        // Uyğunsuzluq varsa, alert göndər
        if ($report->hasMismatches()) {
            Notification::route('slack', config('payment.reconciliation_slack_channel'))
                ->notify(new ReconciliationMismatchAlert($report));
        }

        return $report;
    }
}
```

---

## 18. Error Handling və Recovery

*18. Error Handling və Recovery üçün kod nümunəsi:*
```php
<?php

namespace App\Exceptions\Payment;

/**
 * Payment xətalarının iyerarxiyası.
 */
class PaymentException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly ?string $paymentId = null,
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

// Müştərinin kartı rədd olundu
class PaymentDeclinedException extends PaymentException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $declineCode = '',
        string $message = '',
    ) {
        parent::__construct($message ?: "Payment declined: {$reason}");
    }
}

// Gateway ilə texniki problem
class PaymentGatewayException extends PaymentException {}

// Yanlış status keçidi
class InvalidPaymentStateException extends PaymentException {}

// Authorization vaxtı bitib
class AuthorizationExpiredException extends PaymentException {}

// Məbləğ xətası
class InvalidAmountException extends PaymentException {}

// Gateway dəstəklənmir
class UnsupportedGatewayException extends PaymentException {}
```

*class UnsupportedGatewayException extends PaymentException {} üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

/**
 * Circuit Breaker: Gateway down olduqda, dəfələrlə cəhd etmə.
 * Müəyyən sayda xəta olduqda, müvəqqəti olaraq gateway-ə müraciət dayandırılır.
 */
class PaymentCircuitBreaker
{
    private const FAILURE_THRESHOLD = 5;   // 5 ardıcıl xəta
    private const RECOVERY_TIMEOUT = 60;   // 60 saniyə gözlə

    public function __construct(
        private CacheStore $cache,
    ) {}

    public function isAvailable(string $gateway): bool
    {
        $state = $this->getState($gateway);

        return match ($state['status']) {
            'closed' => true,   // Normal işləyir
            'open' => $this->shouldAttemptRecovery($state), // Timeout bitibsə, yoxla
            'half_open' => true, // Bir cəhd et
            default => true,
        };
    }

    public function recordSuccess(string $gateway): void
    {
        $this->cache->put("circuit:{$gateway}", [
            'status' => 'closed',
            'failures' => 0,
            'last_failure_at' => null,
        ], 3600);
    }

    public function recordFailure(string $gateway): void
    {
        $state = $this->getState($gateway);
        $failures = ($state['failures'] ?? 0) + 1;

        if ($failures >= self::FAILURE_THRESHOLD) {
            $this->cache->put("circuit:{$gateway}", [
                'status' => 'open',
                'failures' => $failures,
                'last_failure_at' => now()->timestamp,
            ], 3600);

            Log::critical("Circuit breaker opened for {$gateway}", [
                'failures' => $failures,
            ]);
        } else {
            $this->cache->put("circuit:{$gateway}", [
                'status' => 'closed',
                'failures' => $failures,
                'last_failure_at' => now()->timestamp,
            ], 3600);
        }
    }

    private function shouldAttemptRecovery(array $state): bool
    {
        $elapsed = now()->timestamp - ($state['last_failure_at'] ?? 0);
        return $elapsed >= self::RECOVERY_TIMEOUT;
    }

    private function getState(string $gateway): array
    {
        return $this->cache->get("circuit:{$gateway}", [
            'status' => 'closed',
            'failures' => 0,
        ]);
    }
}
```

*'failures' => 0, üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

/**
 * Gateway ilə əlaqəni Circuit Breaker ilə qoruyur.
 * Gateway down olduqda fallback gateway-ə keçir.
 */
class ResilientPaymentService
{
    public function __construct(
        private PaymentGatewayFactory $factory,
        private PaymentCircuitBreaker $circuitBreaker,
    ) {}

    public function charge(PaymentRequest $request): PaymentResult
    {
        $primaryGateway = $request->gateway ?? config('payment.default_gateway');
        $fallbackGateway = config('payment.fallback_gateway');

        // Primary gateway ilə cəhd et
        if ($this->circuitBreaker->isAvailable($primaryGateway)) {
            try {
                $result = $this->factory->make($primaryGateway)->charge(
                    $request->amount,
                    $request->currency,
                    $request->paymentMethodToken,
                );
                $this->circuitBreaker->recordSuccess($primaryGateway);
                return $result;
            } catch (PaymentGatewayException $e) {
                $this->circuitBreaker->recordFailure($primaryGateway);
                Log::warning("Primary gateway failed, trying fallback", [
                    'primary' => $primaryGateway,
                    'fallback' => $fallbackGateway,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback gateway ilə cəhd et
        if ($fallbackGateway && $this->circuitBreaker->isAvailable($fallbackGateway)) {
            try {
                $result = $this->factory->make($fallbackGateway)->charge(
                    $request->amount,
                    $request->currency,
                    $request->paymentMethodToken,
                );
                $this->circuitBreaker->recordSuccess($fallbackGateway);
                return $result;
            } catch (PaymentGatewayException $e) {
                $this->circuitBreaker->recordFailure($fallbackGateway);
            }
        }

        throw new PaymentGatewayException(
            "All payment gateways are unavailable. Please try again later."
        );
    }
}
```

---

## 19. Security Best Practices (Təhlükəsizlik Tövsiyələri)

### Əsas Qaydalar

```
1. PCI DSS uyğunluğu:
   ✅ Kart məlumatlarını HEÇVAXT serverinə qəbul etmə (Stripe.js istifadə et)
   ✅ CVV-ni HEÇVAXT saxlama
   ✅ Log-lara həssas məlumat yazma

2. Şifrələmə:
   ✅ TLS 1.2+ (HTTPS) istifadə et
   ✅ At-rest encryption (AES-256) həssas məlumatlar üçün
   ✅ API açarlarını .env-də saxla, HEÇVAXT kod-da yazma

3. Autentifikasiya:
   ✅ Webhook imza yoxlaması (signature verification)
   ✅ API key rotation (müntəzəm dəyişdir)
   ✅ Rate limiting (brute force qoruması)

4. Fraud Prevention:
   ✅ 3D Secure 2.0 aktivləşdir
   ✅ Address Verification Service (AVS)
   ✅ Velocity checks (qısa müddətdə çox əməliyyat)
   ✅ IP geolocation yoxlaması
   ✅ Device fingerprinting

5. Monitoring:
   ✅ Anomali aşkarlama (qeyri-adi məbləğlər, həcm)
   ✅ Real-time alerting
   ✅ Audit trail (hər əməliyyatı qeyd et)
```

*✅ Audit trail (hər əməliyyatı qeyd et) üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment;

/**
 * Fraud aşkarlama xidməti.
 * Şübhəli əməliyyatları müəyyən edir.
 */
class FraudDetectionService
{
    /**
     * Ödənişdən əvvəl risk qiymətləndirməsi.
     */
    public function assessRisk(PaymentRequest $request): RiskAssessment
    {
        $score = 0;
        $flags = [];

        // 1. Velocity check: Son 1 saatda neçə əməliyyat?
        $recentCount = Payment::where('customer_id', $request->customerId)
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($recentCount > 5) {
            $score += 30;
            $flags[] = 'high_velocity';
        }

        // 2. Məbləğ anomaliyası: Müştərinin orta ödənişindən çox yüksək?
        $avgAmount = Payment::where('customer_id', $request->customerId)
            ->where('status', PaymentStatus::CAPTURED)
            ->avg('amount') ?? 0;

        if ($avgAmount > 0 && $request->amount > $avgAmount * 3) {
            $score += 25;
            $flags[] = 'unusual_amount';
        }

        // 3. Yeni müştəri + böyük məbləğ
        $customer = Customer::find($request->customerId);
        if ($customer?->created_at->isAfter(now()->subDay()) && $request->amount > 50000) {
            $score += 20;
            $flags[] = 'new_customer_high_amount';
        }

        // 4. IP ölkəsi ilə kart ölkəsinin fərqi
        $ipCountry = geoip(request()->ip())?->iso_code;
        $cardCountry = $request->metadata['card_country'] ?? null;
        if ($ipCountry && $cardCountry && $ipCountry !== $cardCountry) {
            $score += 15;
            $flags[] = 'country_mismatch';
        }

        // 5. Gecə saatlarında böyük əməliyyat
        $hour = now()->hour;
        if (($hour >= 1 && $hour <= 5) && $request->amount > 30000) {
            $score += 10;
            $flags[] = 'late_night_transaction';
        }

        $riskLevel = match (true) {
            $score >= 60 => RiskLevel::HIGH,
            $score >= 30 => RiskLevel::MEDIUM,
            default => RiskLevel::LOW,
        };

        return new RiskAssessment(
            score: $score,
            level: $riskLevel,
            flags: $flags,
            shouldBlock: $score >= 80,
            should3DS: $score >= 40,
        );
    }
}
```

*should3DS: $score >= 40, üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Middleware;

/**
 * Ödəniş endpoint-ləri üçün rate limiting.
 */
class PaymentRateLimiter
{
    public function handle(Request $request, \Closure $next): Response
    {
        $key = 'payment_rate:' . ($request->user()?->id ?? $request->ip());

        // Dəqiqədə maksimum 10 ödəniş cəhdi
        $limiter = RateLimiter::attempt(
            key: $key,
            maxAttempts: 10,
            callback: fn () => $next($request),
            decaySeconds: 60,
        );

        if (!$limiter) {
            Log::warning('Payment rate limit exceeded', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Çox sayda ödəniş cəhdi. Zəhmət olmasa bir dəqiqə gözləyin.',
            ], 429);
        }

        return $limiter;
    }
}
```

---

## 20. Real-World Complete Payment System Code

### Tam Controller + Routes + Requests

*Tam Controller + Routes + Requests üçün kod nümunəsi:*
```php
<?php

// routes/api.php
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Webhooks\StripeWebhookController;

Route::middleware(['auth:sanctum', 'throttle:payment'])->prefix('payments')->group(function () {
    Route::post('/', [PaymentController::class, 'store']);
    Route::post('/authorize', [PaymentController::class, 'authorize']);
    Route::get('/{payment}', [PaymentController::class, 'show']);
    Route::post('/{payment}/capture', [PaymentController::class, 'capture']);
    Route::post('/{payment}/void', [PaymentController::class, 'void']);
    Route::post('/{payment}/refund', [PaymentController::class, 'refund']);
});

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
```

*Route::post('/webhooks/stripe', [StripeWebhookController::class, 'hand üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Requests;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:50'], // Minimum 50 qəpik
            'currency' => ['sometimes', 'string', 'size:3'],
            'payment_method_id' => ['required', 'string'],
            'order_id' => ['sometimes', 'uuid', 'exists:orders,id'],
            'gateway' => ['sometimes', 'string', 'in:stripe,paypal,braintree'],
            'metadata' => ['sometimes', 'array'],
            'metadata.*' => ['string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum ödəniş məbləği 0.50 AZN-dir.',
            'payment_method_id.required' => 'Ödəniş üsulu seçilməlidir.',
        ];
    }
}
```

### PaymentResult DTO

*PaymentResult DTO üçün kod nümunəsi:*
```php
<?php

namespace App\DTOs;

use App\Models\Payment;

final readonly class PaymentResult
{
    private function __construct(
        public Payment $payment,
        public bool $success,
        public string $status,
        public ?string $message = null,
        public ?string $clientSecret = null, // 3DS üçün
        public ?GatewayResponse $gatewayResponse = null,
    ) {}

    public static function authorized(Payment $payment, GatewayResponse $response): self
    {
        return new self(
            payment: $payment,
            success: true,
            status: 'authorized',
            gatewayResponse: $response,
        );
    }

    public static function captured(Payment $payment, GatewayResponse $response): self
    {
        return new self(
            payment: $payment,
            success: true,
            status: 'captured',
            gatewayResponse: $response,
        );
    }

    public static function declined(Payment $payment, string $reason): self
    {
        return new self(
            payment: $payment,
            success: false,
            status: 'declined',
            message: $reason,
        );
    }

    public static function voided(Payment $payment, GatewayResponse $response): self
    {
        return new self(
            payment: $payment,
            success: true,
            status: 'voided',
            gatewayResponse: $response,
        );
    }

    public static function fromExisting(Payment $payment): self
    {
        return new self(
            payment: $payment,
            success: $payment->status->isCaptured(),
            status: $payment->status->value,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}
```

### Testing (Feature Test)

*Testing (Feature Test) üçün kod nümunəsi:*
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Payment;
use App\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Gateways\FakeGateway;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test zamanı real gateway-i mock ilə əvəz et
        $this->app->bind(PaymentGatewayInterface::class, FakeGateway::class);
    }

    public function test_successful_payment(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/payments', [
            'amount' => 5000, // 50.00 AZN
            'currency' => 'AZN',
            'payment_method_id' => 'pm_test_success',
            'order_id' => $user->orders()->first()?->id,
        ], [
            'Idempotency-Key' => 'test-key-123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'captured')
            ->assertJsonPath('data.amount', 5000);

        $this->assertDatabaseHas('payments', [
            'idempotency_key' => 'test-key-123',
            'status' => 'captured',
            'amount' => 5000,
        ]);
    }

    public function test_declined_payment(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/payments', [
            'amount' => 5000,
            'payment_method_id' => 'pm_test_declined', // FakeGateway bunu rədd edir
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.status', 'declined');
    }

    public function test_idempotency_prevents_double_charge(): void
    {
        $user = User::factory()->create();
        $idempotencyKey = 'unique-key-' . Str::uuid();

        // Birinci request
        $response1 = $this->actingAs($user)->postJson('/api/payments', [
            'amount' => 5000,
            'payment_method_id' => 'pm_test_success',
        ], ['Idempotency-Key' => $idempotencyKey]);

        // İkinci request (eyni key)
        $response2 = $this->actingAs($user)->postJson('/api/payments', [
            'amount' => 5000,
            'payment_method_id' => 'pm_test_success',
        ], ['Idempotency-Key' => $idempotencyKey]);

        $response1->assertStatus(201);
        $response2->assertStatus(201); // Eyni cavab

        // Database-də yalnız 1 ödəniş var
        $this->assertEquals(1, Payment::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_capture_authorized_payment(): void
    {
        $user = User::factory()->create();

        // Əvvəl authorize et
        $authResponse = $this->actingAs($user)->postJson('/api/payments/authorize', [
            'amount' => 10000,
            'payment_method_id' => 'pm_test_success',
        ]);

        $paymentId = $authResponse->json('data.id');

        // Sonra capture et
        $captureResponse = $this->actingAs($user)
            ->postJson("/api/payments/{$paymentId}/capture");

        $captureResponse->assertOk()
            ->assertJsonPath('data.status', 'captured');
    }

    public function test_refund_captured_payment(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->captured()->create(['customer_id' => $user->customer_id]);

        $response = $this->actingAs($user)->postJson("/api/payments/{$payment->id}/refund", [
            'amount' => 2000, // Qismən refund (20.00)
            'reason' => 'Müştəri razı deyil',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'partially_refunded');
    }
}
```

### FakeGateway (Testing üçün)

*FakeGateway (Testing üçün) üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\PaymentGatewayInterface;

/**
 * Test üçün fake gateway.
 * Real API çağırışı etmir, əvvəlcədən təyin olunmuş nəticələr qaytarır.
 */
class FakeGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'fake';
    }

    public function authorize(
        int $amount,
        string $currency,
        string $paymentMethod,
        array $metadata = [],
    ): GatewayResponse {
        return $this->processPayment($paymentMethod, $amount);
    }

    public function charge(
        int $amount,
        string $currency,
        string $paymentMethod,
        array $metadata = [],
    ): GatewayResponse {
        return $this->processPayment($paymentMethod, $amount);
    }

    public function capture(string $transactionId, ?int $amount = null): GatewayResponse
    {
        return GatewayResponse::success("cap_{$transactionId}", 'captured');
    }

    public function void(string $transactionId): GatewayResponse
    {
        return GatewayResponse::success($transactionId, 'voided');
    }

    public function refund(string $transactionId, ?int $amount = null): GatewayResponse
    {
        return GatewayResponse::success("re_{$transactionId}", 'refunded');
    }

    private function processPayment(string $paymentMethod, int $amount): GatewayResponse
    {
        // Test payment method-una görə nəticə
        return match (true) {
            str_contains($paymentMethod, 'declined') =>
                throw new PaymentDeclinedException('Card declined', 'card_declined'),
            str_contains($paymentMethod, 'insufficient') =>
                throw new PaymentDeclinedException('Insufficient funds', 'insufficient_funds'),
            str_contains($paymentMethod, 'error') =>
                throw new PaymentGatewayException('Gateway error'),
            default => GatewayResponse::success('txn_fake_' . Str::random(16), 'succeeded'),
        };
    }

    // ... digər interface metodlarının fake implementasiyası
}
```

---

## 21. İntervyu Sualları və Cavabları

### Sual 1: Payment Gateway ilə Payment Processor arasındakı fərq nədir?

**Cavab:** Payment Gateway API interfeysidir — kart məlumatlarını şifrələyir və prosessora ötürür. Payment Processor isə banklar arasında əsl pul köçürməsini həyata keçirir. PSP (Stripe, PayPal) hər ikisini birləşdirir. Analogiya: Gateway poçt qutusudur (məktubu qəbul edir), Processor poçtçudur (məktubu çatdırır).

### Sual 2: Authorization və Capture niyə ayrılır?

**Cavab:** Fiziki mal satışında sifariş zamanı pul bloklanır (authorize), mal göndərildikdə çıxılır (capture). Bu müştərini qoruyur — mal göndərilməsə, authorization expire olur və pul qaytarılır. Oteldə check-in zamanı depozit bloklanır, check-out zamanı əsl məbləğ capture edilir. Rəqəmsal mallar üçün adətən auth+capture birlikdə edilir.

### Sual 3: Idempotency key nə üçündür və necə implement olunur?

**Cavab:** Network timeout, retry, müştərinin düyməyə iki dəfə basması hallarında dublikat ödənişin qarşısını alır. Client unikal key (UUID) göndərir, server bunu saxlayır. Eyni key ilə ikinci request gəldikdə, yeni ödəniş yaratmır, əvvəlki nəticəni qaytarır. Database-də unique constraint + cache ilə implement olunur. Stripe 24 saat idempotency saxlayır.

### Sual 4: PCI DSS compliance üçün ən sadə yol nədir?

**Cavab:** SAQ A — kart məlumatlarının serverinizə heç toxunmaması. Stripe.js, Braintree Drop-in kimi client-side SDK istifadə edin. Kart nömrəsi birbaşa gateway-in serverinə gedir, sizə yalnız token qaytarılır. Bu token ilə ödəniş edirsiniz. CVV-ni heç vaxt, heç yerdə saxlamayın. Log-lara kart məlumatları yazmayın.

### Sual 5: 3D Secure nədir və nə zaman tətbiq olunmalıdır?

**Cavab:** 3D Secure kartla ödəniş zamanı əlavə autentifikasiya addımıdır — OTP, biometrik və ya bank tətbiqi ilə təsdiq. Fraud-u azaldır və "liability shift" (məsuliyyət keçidi) təmin edir — əgər 3DS-dən keçib fraud olarsa, məsuliyyət merchantdən bankın üstünə keçir. 3DS 2.0 frictionless flow dəstəkləyir — aşağı riskli əməliyyatlar avtomatik təsdiqlənir, yalnız yüksək riskli olanlarda müştəri əlavə autentifikasiya edir. Avropa (SCA), Hindistan və bir çox ölkələrdə məcburidir.

### Sual 6: Payment state machine necə dizayn olunur?

**Cavab:** Hər payment bir statusda olur (pending, authorized, captured, refunded...). Hər statusdan yalnız müəyyən statuslara keçmək olar. Məsələn, `pending`-dən `refunded`-ə birbaşa keçmək olmaz — əvvəl `captured` olmalıdır. Status keçidləri enum-da təyin olunur, `transitionTo()` metodu yanlış keçidləri exception ilə rədd edir. Hər keçid audit log-a yazılır. Bu pattern `InvalidPaymentStateException` ilə data integrity-ni qoruyur.

### Sual 7: Webhook handling-də nələrə diqqət etmək lazımdır?

**Cavab:** 1) İmza yoxlaması — webhook-un həqiqətən gateway-dən gəldiyini təsdiq et. 2) Idempotency — eyni webhook iki dəfə gələ bilər, dublikat emal etmə. 3) Tez cavab ver — 200 dərhal qaytar, emalı queue-da et. 4) Retry mexanizmi — emal uğursuz olsa, gateway təkrar göndərəcək. 5) Event ordering — webhook-lar sıra ilə gəlməyə bilər, buna hazır ol. 6) CSRF-dən azad et.

### Sual 8: Multi-gateway support niyə lazımdır?

**Cavab:** 1) Bir gateway down olduqda digərinə keçmək (failover). 2) Müxtəlif ölkələr/regionlar üçün müxtəlif gateway-lər. 3) Komissiya optimallaşdırması — bəzi gateway-lər müəyyən kart tipləri üçün ucuzdur. 4) Payment method dəstəyi — Stripe kartlar üçün, PayPal cüzdan üçün. Strategy Pattern ilə eyni interfeysi implement edən gateway-lər arasında asanlıqla keçid mümkündür.

### Sual 9: Split payment nədir və marketplace-lərdə necə istifadə olunur?

**Cavab:** Split payment bir ödənişin bir neçə tərəf arasında bölünməsidir. Uber-də müştəri 20 AZN ödəyir, 15 AZN sürücüyə, 5 AZN Uber-ə gedir. Stripe Connect ilə implement olunur — Direct Charges (birbaşa satıcıya) və ya Destination Charges (platformadan satıcıya transfer). Çoxlu satıcılı sifarişlərdə Separate Charges and Transfers istifadə olunur.

### Sual 10: Ödəniş sistemində error handling və recovery necə olmalıdır?

**Cavab:** Xüsusi exception iyerarxiyası lazımdır: `PaymentDeclinedException` (kart rədd), `PaymentGatewayException` (texniki xəta), `InvalidPaymentStateException` (yanlış status keçidi). Circuit Breaker pattern istifadə edin — 5 ardıcıl xəta olduqda, gateway-ə müraciəti dayandırın, 60 saniyə sonra yenidən cəhd edin. Fallback gateway konfiqurasiya edin. Retry mexanizmi exponential backoff ilə olmalıdır. Hər xəta strukturlaşdırılmış şəkildə log-lanmalıdır.

### Sual 11: Pul məbləğlərini sistemdə necə saxlamaq lazımdır?

**Cavab:** Heç vaxt `float` istifadə etmə — dəqiqlik problemi var (0.1 + 0.2 !== 0.3). Həmişə **minor unit** (qəpik, sent) ilə integer olaraq saxla: 50.00 AZN = 5000 qəpik. Money Value Object istifadə et — valyutanı və məbləği birlikdə saxlayır, əməliyyatlar (toplama, çıxma) zamanı valyuta uyğunluğunu yoxlayır. `bcmath` funksiyaları dəqiq hesablama üçün istifadə olunur. Yapon yeni kimi valyutalarda minor unit yoxdur (çarpan = 1).

### Sual 12: Escrow payment nədir və nə zaman lazımdır?

**Cavab:** Escrow müştərinin ödənişini saxlayır, satıcıya yalnız iş/mal təmin olunduqdan sonra köçürür. Freelance platformalarda (müştəri işi qəbul edəndə pul freelancer-ə verilir), marketplace-lərdə (mal çatdırıldıqda satıcıya verilir) istifadə olunur. Dispute olduqda platform arbitraj edir. Auto-release timeout qoyulur — müştəri müddət ərzində itiraz etməsə, pul avtomatik azad olunur.

### Sual 13: Payment reconciliation nədir və niyə vacibdir?

**Cavab:** Reconciliation öz database-nizdəki ödəniş qeydlərini gateway-in hesabatları ilə müqayisə etməkdir. Uyğunsuzluqları (itən ödəniş, status fərqi, məbləğ fərqi) tapır. Gündəlik schedule job ilə işləyir. Nə yoxlayır: 1) Gateway-də var amma database-də yox. 2) Database-də var amma gateway-də yox. 3) Məbləğ fərqi. 4) Status fərqi. Uyğunsuzluq tapıldıqda Slack/email alert göndərilir. Maliyyə auditi üçün mütləq lazımdır.

### Sual 14: Recurring payment-lərdə dunning prosesi nədir?

**Cavab:** Dunning — subscription ödənişi uğursuz olduqda təkrar cəhd prosesidir. Adətən 3-4 dəfə fərqli günlərdə təkrar edilir (3 gün, 5 gün, 7 gün sonra). Hər uğursuz cəhddə müştəriyə email göndərilir ("Ödəniş uğursuz oldu, kart məlumatlarını yeniləyin"). Bütün cəhdlər uğursuz olduqda, abunəlik ləğv edilir. Bu müddətdə subscription `past_due` statusunda olur.

### Sual 15: Ödəniş sistemini necə test edərsiniz?

**Cavab:** 1) FakeGateway — real API çağırmayan mock gateway yarat (test payment method-larına görə müxtəlif nəticələr qaytarır). 2) Stripe Test Mode — `pk_test_` açarları ilə real API-ni test rejimində istifadə et. 3) Test kartları: `4242424242424242` (uğurlu), `4000000000000002` (rədd), `4000002500003155` (3DS tələb edən). 4) Idempotency testləri — eyni key ilə iki request göndər, yalnız bir ödəniş yarandığını yoxla. 5) State machine testləri — yanlış keçidlərin exception atdığını yoxla. 6) Webhook testləri — fake webhook göndər, düzgün emal olunduğunu yoxla.

---

## 22. Xülasə — Payment System Arxitekturası

```
┌──────────────────────────────────────────────────────────────┐
│                       Frontend (Client)                       │
│  ┌─────────────┐  ┌────────────┐  ┌──────────────────────┐  │
│  │  Stripe.js  │  │ Apple Pay  │  │   Google Pay SDK     │  │
│  └──────┬──────┘  └─────┬──────┘  └──────────┬───────────┘  │
│         │ token          │ token              │ token         │
└─────────┼────────────────┼────────────────────┼──────────────┘
          │                │                    │
          ▼                ▼                    ▼
┌──────────────────────────────────────────────────────────────┐
│                    Laravel API (Backend)                       │
│                                                               │
│  ┌──────────────┐  ┌───────────────┐  ┌──────────────────┐  │
│  │  Controller  │──│PaymentService │──│  FraudDetection  │  │
│  └──────────────┘  └───────┬───────┘  └──────────────────┘  │
│                            │                                  │
│  ┌─────────────────────────┼────────────────────────────┐    │
│  │          PaymentGatewayInterface                      │    │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐   │    │
│  │  │  Stripe  │  │  PayPal  │  │    Braintree     │   │    │
│  │  │ Gateway  │  │ Gateway  │  │    Gateway       │   │    │
│  │  └──────────┘  └──────────┘  └──────────────────┘   │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                               │
│  ┌──────────┐  ┌───────────┐  ┌──────────┐  ┌───────────┐  │
│  │ Payment  │  │  Webhook  │  │  Audit   │  │  Circuit  │  │
│  │  Model   │  │ Processor │  │   Log    │  │  Breaker  │  │
│  └──────────┘  └───────────┘  └──────────┘  └───────────┘  │
│                                                               │
│  ┌──────────────────────────────────────────────────────┐    │
│  │              Queue (Redis/SQS)                        │    │
│  │  ┌─────────────┐ ┌──────────┐ ┌──────────────────┐  │    │
│  │  │RetryPayment │ │ Process  │ │  Reconciliation  │  │    │
│  │  │    Job      │ │ Webhook  │ │       Job        │  │    │
│  │  └─────────────┘ └──────────┘ └──────────────────┘  │    │
│  └──────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────┘
```

**Əsas prinsiplər:**
- Kart məlumatları **heçvaxt** serverinizə toxunmasın (tokenization)
- Hər əməliyyat **idempotent** olsun (dublikat qorunması)
- Status keçidləri **state machine** ilə idarə olunsun
- Bütün əməliyyatlar **audit log**-a yazılsın
- Gateway xətalarında **Circuit Breaker** + fallback istifadə olunsun
- Webhook-lar **asinxron** emal olunsun
- Pul məbləğləri **integer** (minor unit) ilə saxlanılsın
- Hər şey **test edilə bilən** olsun (FakeGateway ilə)

---

## Anti-patternlər

**1. Ödəniş Məbləğini Float ilə Saxlamaq**
`amount DECIMAL` əvəzinə `FLOAT` tipi istifadə etmək, ya da PHP-də `float` ilə pul hesablamaları aparmaq — `0.1 + 0.2 !== 0.3` kimi dəqiqlik xətaları maliyyə fərqlərinə yol açır. Məbləği həmişə ən kiçik vahiddə (`int`, məs. qəpik) saxlayın; göstərmək üçün bölün.

**2. Webhook İmzasını Yoxlamamaq**
Gateway-dən gələn webhook-u imza yoxlaması olmadan emal etmək — hər kəs webhook endpoint-inə POST göndərib ödəniş statusunu dəyişdirə bilər. Gateway-in HMAC imzasını (məs. Stripe-ın `Stripe-Signature` header) mütləq yoxlayın; uyğunsuz imzalı sorğuları rədd edin.

**3. Dublikat Ödənişə Qarşı İdempotency Tətbiq Etməmək**
Network xətası zamanı eyni ödənişi bir dəfədən artıq emal etmək — müştəri iki dəfə debitlənir. Hər ödəniş cəhdini unikal `idempotency_key` ilə göndərin; verilənlər bazasında ödənişi tamamlanmış kimi işarələyib dublikat webhook-ları ignore edin.

**4. Ödəniş Statusunu Sinxron Polling ilə Yoxlamaq**
Gateway-in API-ni hər saniyə sorğulayıb ödəniş nəticəsini gözləmək — rate limit aşılır, HTTP connection bloklanır, miqyaslanma problemi yaranır. Webhook-a əsaslı asinxron arxitektura qurun; istifadəçiyə "emal olunur" statusu göstərin, webhook gəldikdə yeniləyin.

**5. Kart Məlumatlarını Öz Serverinizdə Saxlamaq**
PAN, CVV, expiry date-i öz database-inizdə saxlamağa çalışmaq — PCI DSS uyğunluğu tələb olunur, ciddi cərimə riski var, data ihlalı zamanı maliyyə və reputasiya zərəri böyük olur. Tokenization istifadə edin; gateway-in token-ını saxlayın, həssas məlumatlar heç vaxt serverinizə dəyməsin.

**6. Refund/Chargeback Prosesini Planlaşdırmamaq**
Sistemin yalnız uğurlu ödəniş axını üçün dizayn edilməsi — geri qaytarma, mübahisə, partial refund tələbləri gəldikdə kod dəyişikliyi lazım gəlir, maliyyə hesabatı uyğunsuzlaşır. Ödəniş sistemini ilk gündən refund, partial refund, void, chargeback state-lərini nəzərə alaraq dizayn edin.

**7. Bütün Gateway Xətalarını Eyni Cür İşləmək**
`PaymentException` tutub "Ödəniş uğursuz oldu" mesajı göndərmək — kart limiti aşımı, müvəqqəti şəbəkə xətası, kart bloku, fraud rədd kimi müxtəlif xətalar fərqli cavab tələb edir. Xəta kodlarını (decline codes) parse edib müştəriyə uyğun mesaj göstərin; müvəqqəti xətalar üçün retry, daimi rədd üçün isə müştəridən yeni kart tələb edin.

**8. Test Mühitindən Production Açarlarına Keçişi Avtomatlaşdırmamaq**
`STRIPE_KEY=sk_test_...` config-i production-a deploy etmək — real müştərilərin ödənişi test mühitinə gedir, heç biri çəkilmir. CI/CD pipeline-da `sk_test_` key-lərinin production-da olmaması mütləq yoxlanılmalıdır; environment-specific secret rotation prosedurunuz olsun.
