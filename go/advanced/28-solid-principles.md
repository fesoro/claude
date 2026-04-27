# SOLID Prinsipləri (Lead)

## İcmal

SOLID — obyekt-yönümlü proqramlaşdırmanın 5 əsas prinsipinin akronimidir. Go-da klassik OOP yoxdur: class yoxdur, inheritance yoxdur. Lakin SOLID prinsipləri Go idiomlari vasitəsilə tətbiq edilir — struct composition, interface, small packages. Bu prinsiplər kodu dəyişdirməyi, test etməyi və genişləndirməyi asanlaşdırır.

## Niyə Vacibdir

PHP/Laravel developer kimi böyük class hierarchy-ə alışmış biri Go-ya keçəndə tez-tez eyni antipatternləri daşıyır: böyük struct-lar, concrete type-lara dependency, interface olmayan kod. SOLID-i Go kontekstindən anlamaq bu keçidi daha sağlam edir. Real proyektlərdə SOLID pozuntuları texniki borcu artırır, test yazmağı çətinləşdirir və yeni feature əlavə etməyi riskli edir.

## Əsas Anlayışlar

- **Single Responsibility Principle (SRP)** — hər struct/package-in yalnız bir dəyişmə səbəbi olmalıdır
- **Open/Closed Principle (OCP)** — genişlənməyə açıq, dəyişməyə qapalı; interface + composition ilə
- **Liskov Substitution Principle (LSP)** — interface implementasiyaları bir-birini əvəz edə bilməlidir
- **Interface Segregation Principle (ISP)** — kiçik, focused interface-lər; `io.Reader`, `io.Writer` nümunə
- **Dependency Inversion Principle (DIP)** — concrete type-a deyil, interface-ə depend ol
- **Composition over inheritance** — Go-da inheritance yoxdur, struct embedding + interface istifadə edilir
- **Implicit interfaces** — Go-da interface-i explicit implement etmək lazım deyil

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- Microservice-lərdə hər domain-in öz package-i (SRP)
- Yeni payment provider əlavə etmək üçün mövcud kodu dəyişməmək (OCP)
- Test-lərdə real DB əvəzinə mock repository istifadəsi (DIP)
- Kiçik interface-lər sayəsində partial implementation imkanı (ISP)

**Trade-off-lar:**
- Çox interface yaratmaq overhead yarada bilər — balans lazımdır
- Over-engineering riski var: sadə CRUD app-da hexagonal architecture lazım deyil
- Go-da interface implicit olduğu üçün hangi struct-ın hansı interface-i implement etdiyini izləmək çətin ola bilər

**Nə vaxt istifadə etməmək lazımdır:**
- Çox kiçik, bir dəfəlik skriptlər
- Prototip mərhələsindəki MVP kod
- Komanda SOLID-ə alışmamışsa, birdən tətbiq etmək confusion yaradır

**Ümumi səhvlər:**
- PHP-dən gəlib hər şeyi `Manager`, `Handler`, `Service` adlı bir struct-da toplamaq
- Interface əvəzinə concrete type-lara pointer pass etmək
- God struct: 20+ field, 30+ method — açıq SRP pozuntusu
- `UserService` interface-i olmadan `PostgresUserRepository`-ni birbaşa inject etmək

## Nümunələr

### Ümumi Nümunə

Bir e-commerce sistemini düşünün. Ödəniş sistemi əlavə etmək istəyirsiniz:

```
BAD:
PaymentService struct {
    ProcessStripe(...)
    ProcessPayPal(...)
    ProcessCrypto(...)
    SendEmail(...)       ← SRP pozuntusu
    LogToDatabase(...)   ← SRP pozuntusu
}

GOOD:
PaymentProcessor interface { Process(...) error }
StripeProcessor   implements PaymentProcessor
PayPalProcessor   implements PaymentProcessor
CryptoProcessor   implements PaymentProcessor

EmailNotifier     ayrıca struct
PaymentLogger     ayrıca struct
```

### Kod Nümunəsi

**Refactoring öncəsi — SOLID pozuntuları:**

```go
// BAD: Bütün məsuliyyət bir struct-da — SRP pozuntusu
// Concrete type-a depend edir — DIP pozuntusu
// Yeni payment əlavə etmək üçün struct-ı dəyişmək lazımdır — OCP pozuntusu
type PaymentService struct {
    db     *sql.DB
    mailer *smtp.Client
}

func (s *PaymentService) ProcessStripe(amount float64, token string) error {
    // Stripe API call
    s.db.Exec("INSERT INTO payments ...")
    s.mailer.SendMail("payment confirmed")
    return nil
}

func (s *PaymentService) ProcessPayPal(amount float64, email string) error {
    // PayPal API call
    s.db.Exec("INSERT INTO payments ...")
    s.mailer.SendMail("payment confirmed")
    return nil
}
```

**Refactoring sonrası — SOLID tətbiqi:**

```go
// ─── S: Single Responsibility ───────────────────────────────────────────────

// Hər struct-ın bir məsuliyyəti var
type PaymentRecord struct {
    ID        string
    Amount    float64
    Provider  string
    CreatedAt time.Time
}

// ─── I: Interface Segregation ────────────────────────────────────────────────

// Böyük bir interface əvəzinə kiçik, focused interface-lər
type PaymentProcessor interface {
    Process(ctx context.Context, amount float64) (string, error)
}

type PaymentNotifier interface {
    Notify(ctx context.Context, record PaymentRecord) error
}

type PaymentRepository interface {
    Save(ctx context.Context, record PaymentRecord) error
    FindByID(ctx context.Context, id string) (PaymentRecord, error)
}

// ─── O: Open/Closed — yeni provider əlavə etmək üçün mövcud kodu dəyişmirik ─

type StripeProcessor struct {
    apiKey string
}

func (p *StripeProcessor) Process(ctx context.Context, amount float64) (string, error) {
    // Stripe API call
    return "stripe_txn_123", nil
}

type PayPalProcessor struct {
    clientID string
    secret   string
}

func (p *PayPalProcessor) Process(ctx context.Context, amount float64) (string, error) {
    // PayPal API call
    return "paypal_txn_456", nil
}

// Yeni provider — mövcud koda toxunmuruq!
type CryptoProcessor struct {
    walletAddr string
}

func (p *CryptoProcessor) Process(ctx context.Context, amount float64) (string, error) {
    // Crypto API call
    return "crypto_txn_789", nil
}

// ─── L: Liskov Substitution ──────────────────────────────────────────────────

// Hər PaymentProcessor implementation bir-birini əvəz edə bilər.
// BAD example — LSP pozuntusu (komment olaraq):
//
// type BrokenProcessor struct{}
// func (b *BrokenProcessor) Process(_ context.Context, amount float64) (string, error) {
//     if amount > 0 { panic("not supported") }  ← interface contract-ı pozur!
//     return "", nil
// }

// ─── D: Dependency Inversion ─────────────────────────────────────────────────

// PaymentService concrete type-a deyil, interface-ə depend edir
type PaymentService struct {
    processor  PaymentProcessor  // interface — DIP ✓
    repository PaymentRepository // interface — DIP ✓
    notifier   PaymentNotifier   // interface — DIP ✓
}

func NewPaymentService(
    processor PaymentProcessor,
    repository PaymentRepository,
    notifier PaymentNotifier,
) *PaymentService {
    return &PaymentService{
        processor:  processor,
        repository: repository,
        notifier:   notifier,
    }
}

func (s *PaymentService) Pay(ctx context.Context, amount float64) error {
    txnID, err := s.processor.Process(ctx, amount)
    if err != nil {
        return fmt.Errorf("processing payment: %w", err)
    }

    record := PaymentRecord{
        ID:        txnID,
        Amount:    amount,
        CreatedAt: time.Now(),
    }

    if err := s.repository.Save(ctx, record); err != nil {
        return fmt.Errorf("saving payment record: %w", err)
    }

    if err := s.notifier.Notify(ctx, record); err != nil {
        // Notification failure critical deyil — log edib davam edirik
        log.Printf("notification failed: %v", err)
    }

    return nil
}

// ─── İstifadə nümunəsi ────────────────────────────────────────────────────────

func main() {
    // Stripe ilə işlədikdə
    stripeService := NewPaymentService(
        &StripeProcessor{apiKey: "sk_live_..."},
        NewPostgresPaymentRepository(db),
        NewEmailNotifier(mailer),
    )
    stripeService.Pay(ctx, 99.99)

    // PayPal-a keçid — PaymentService-ə toxunmuruq!
    paypalService := NewPaymentService(
        &PayPalProcessor{clientID: "...", secret: "..."},
        NewPostgresPaymentRepository(db),
        NewEmailNotifier(mailer),
    )
    paypalService.Pay(ctx, 49.99)
}
```

**Test-də DIP-in faydası — mock ilə:**

```go
// Test üçün mock processor — real API çağrılmır
type MockPaymentProcessor struct {
    ProcessFunc func(ctx context.Context, amount float64) (string, error)
}

func (m *MockPaymentProcessor) Process(ctx context.Context, amount float64) (string, error) {
    return m.ProcessFunc(ctx, amount)
}

func TestPaymentService_Pay_Success(t *testing.T) {
    mockProcessor := &MockPaymentProcessor{
        ProcessFunc: func(_ context.Context, _ float64) (string, error) {
            return "mock_txn_001", nil
        },
    }

    svc := NewPaymentService(mockProcessor, mockRepo, mockNotifier)
    err := svc.Pay(context.Background(), 100.0)
    assert.NoError(t, err)
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — SRP analizi:**
Mövcud layihənizdəki ən böyük struct-ı götürün. Neçə müxtəlif məsuliyyəti var? Onu necə bölərdınız? Siyahı düzəldin.

**Tapşırıq 2 — OCP tətbiqi:**
Notification sistemi qurun: `Notifier` interface, `EmailNotifier`, `SMSNotifier`, `SlackNotifier` implementasiyaları. `NotificationService` yalnız `Notifier` interface-ini bilsin. Yeni `TelegramNotifier` əlavə edin — mövcud koda toxunmadan.

**Tapşırıq 3 — ISP praktiki:**
Aşağıdakı fat interface-i parçalayın:
```go
// BAD — too fat
type UserManager interface {
    CreateUser(...)
    DeleteUser(...)
    GetUser(...)
    SendEmail(...)
    GenerateReport(...)
    ExportToCSV(...)
}
```
Hansı interface-lər yaradardınız?

**Tapşırıq 4 — DIP + test:**
`OrderService` yazın. `OrderRepository` interface-dən istifadə etsin. In-memory mock repository yazın. Unit test yazın — database olmadan.

**Tapşırıq 5 — LSP yoxlanması:**
`io.Reader` interface-ini implement edən 3 struct yazın: `FileReader`, `HTTPReader`, `StringReader`. Hər birini eyni funksiyaya (`processData(r io.Reader)`) pass edin. LSP-nin işlədiyini görün.

## Əlaqəli Mövzular

- `09-dependency-injection.md` — DIP-in praktiki tətbiqi
- `27-clean-architecture.md` — SOLID üzərində qurulan tam arxitektura
- `29-hexagonal-architecture.md` — Ports & Adapters, DIP-in genişlənməsi
- `04-design-patterns.md` — Strategy, Decorator kimi pattern-lər OCP-ni dəstəkləyir
