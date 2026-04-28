# Big Ball of Mud (Anti-Pattern / Architect)

Real dünyada ən çox rast gəlinən "arxitektura" — hər hansı strukturun olmadığı sistem.
Aydın modul hüdudları, layer-lər və ya pattern-lar yoxdur.
**Əsas dəyəri:** Bu anti-pattern-i tanıyıb, refactoring strategiyası hazırlamaq.

**Əlamətlər:**
- God Class / God Service — hər şeyi edən mega-class
- Circular dependencies — A → B → C → A
- Spaghetti code — hər şey hər şeyi çağırır
- DB everywhere — model-lər, controller-lər, hər yerdə DB query
- No tests — "touching anything breaks everything"
- Copy-paste code — eyni logic 10 yerdə
- Magic globals — hər yerdəki static methods, global state

---

## Big Ball of Mud Nümunəsi (Laravel)

```
❌ BIG BALL OF MUD — Real-world görünüşü:

app/
├── Http/Controllers/
│   └── OrderController.php
│       // 800 sətir controller
│       // Order yaratmaq, payment çəkmək, email göndərmək,
│       // inventory yoxlamaq, PDF generate etmək...
│       // DB::table() everywhere
│       // Global helpers: sendMail(), generatePDF()
│       // env() directly in business logic
│       // Hardcoded strings: "status" => "pending"
│
├── Models/
│   └── Order.php
│       // God model: 50+ method
│       // Business logic + formatting + emailing
│       // Direct DB queries in model methods
│       // Static methods calling other models
│       // Order::withUserAndProductsAndPaymentsAndShipping()->...
│
├── helpers.php                                # Global functions: 200+ lines
│   // function sendOrderEmail($orderId) { ... }
│   // function calculateDiscount($total, $userId) { ... }
│   // function generateInvoice($orderId) { ... }
│
├── Services/
│   └── OrderService.php
│       // 1200 sətir "service"
│       // Depends on: 15 other classes
│       // Circular: calls UserService which calls OrderService
│
└── (no tests — nobody dares to touch this)
```

---

## Refactoring Strategiyası (Strangler Fig + Layer Extraction)

```
✅ ADDIM-ADDIM ÇIXIŞ:

Faza 1 — Identify & Stabilize (1-2 ay):
├── app/
│   ├── Legacy/                               # Köhnə kodu buraya al
│   │   ├── Controllers/
│   │   │   └── OrderController.php           # Moved here: "legacy zone"
│   │   └── Models/
│   │       └── Order.php
│   │
│   ├── New/                                  # Yeni kod buraya gedir
│   │   └── (boş — hələ hazır deyil)
│   │
│   └── Http/Controllers/
│       └── OrderController.php               # Thin wrapper → delegates to Legacy

Faza 2 — Extract Domain (2-4 ay):
├── app/
│   ├── Domain/
│   │   └── Order/
│   │       ├── Order.php                     # Pure domain model (no DB)
│   │       ├── OrderStatus.php               # Value Object (no more magic strings)
│   │       └── OrderRepositoryInterface.php
│   │
│   ├── Application/
│   │   └── PlaceOrderService.php             # Only order placement logic
│   │
│   └── Legacy/
│       └── (still exists — not everything migrated yet)

Faza 3 — Extract Services (4-6 ay):
├── app/
│   ├── Domain/                               # Clean domain layer
│   ├── Application/                          # Use cases
│   │   ├── PlaceOrderService.php
│   │   ├── ProcessPaymentService.php         # Extracted from God Service
│   │   └── NotificationService.php
│   ├── Infrastructure/                       # DB, email, etc.
│   └── Legacy/                               # Shrinking...

Faza 4 — Tests + Delete Legacy (6+ ay):
├── app/
│   ├── Domain/
│   ├── Application/
│   └── Infrastructure/
└── tests/                                    # Finally possible to test!
    ├── Unit/
    └── Feature/
```

---

## Spring Boot — God Service Refactoring

```
❌ BEFORE — God Service:

OrderService.java (2000 lines)
├── placeOrder()      — creates order + reserves inventory + charges + sends email
├── cancelOrder()     — refunds + releases inventory + notifies + updates analytics
├── getOrderReport()  — complex SQL queries + formatting + PDF generation
└── (15 more methods, all tangled)


✅ AFTER — Separated concerns:

ordering/
├── OrderService.java         — only order aggregate management
│
inventory/
├── InventoryService.java     — stock reservation/release
│
payment/
├── PaymentService.java       — charge/refund
│
notification/
└── NotificationService.java  — emails/SMS

coordination/
└── OrderFulfillmentSaga.java — orchestrates the above (clean!)
```

---

## Golang — Detection Tools

```
project/
├── tools/                                    # Code analysis
│   ├── coupling-analyzer/
│   │   └── main.go                           # Count imports per file
│   └── complexity-checker/
│       └── main.go                           # Cyclomatic complexity
│
├── internal/
│   └── legacy/                               # Isolated "mud zone"
│       ├── order_handler.go                  # God handler (being refactored)
│       └── README.md                         # "Do not add new code here"
│
├── internal/
│   └── new/                                  # Clean code zone
│       ├── domain/
│       ├── application/
│       └── infrastructure/
│
└── go.mod
```

---

## Refactoring Prinsipləri

```
1. Strangler Fig — köhnəni birbaşa silmə, tədricən əvəz et
2. "Seam" tap — Köhnə koddakı natural separation nöqtəsini tap
3. Test coverage əvvəl — refactor etməzdən əvvəl characterization test yaz
4. Small steps — 500 sətir God class-ı 1 gündə refactor etmə
5. Legacy zone izolasiyası — yeni feature-lar köhnə zona-ya gəlməsin

Ölçü göstəriciləri:
- God Class: 500+ line class (Laravel Model 300+, Service 400+)
- High coupling: 10+ import olan fayl
- No tests: 0% coverage
- Deep nesting: if inside if inside if inside for...
- Magic strings: "status" => "pending" (50 yerdə)

Real həyat həqiqəti:
  Big Ball of Mud çox vaxt iş görür.
  Problem: touch anything → everything breaks.
  Refactoring məqsədi: Testable, deployable, understandable.
  Perfection deyil — controlled chaos.
```
