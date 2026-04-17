# Pipe and Filter Architecture

Data flows through a series of processing components (filters) connected by pipes.
Each filter transforms data independently and passes it to the next filter.

**Key concepts:**
- **Filter** — Independent processing component that transforms data
- **Pipe** — Connector that passes data between filters
- **Pipeline** — A chain of filters connected by pipes
- **Source** — Origin of data (input)
- **Sink** — Final destination (output)

**Common use cases:** ETL pipelines, image processing, data validation, middleware chains.

---

## Laravel

```
app/
├── Pipeline/
│   ├── Contracts/
│   │   ├── PipeInterface.php              # Filter contract
│   │   ├── PipelineInterface.php
│   │   └── PipelineBuilderInterface.php
│   ├── Pipeline.php                        # Pipeline implementation
│   └── PipelineBuilder.php
│
├── Filters/                                # Reusable filters
│   ├── Order/
│   │   ├── ValidateOrderFilter.php
│   │   ├── CalculateTotalFilter.php
│   │   ├── ApplyDiscountFilter.php
│   │   ├── CalculateTaxFilter.php
│   │   ├── CheckInventoryFilter.php
│   │   ├── ReserveStockFilter.php
│   │   └── CreateOrderRecordFilter.php
│   │
│   ├── Payment/
│   │   ├── ValidatePaymentFilter.php
│   │   ├── FraudDetectionFilter.php
│   │   ├── ProcessPaymentFilter.php
│   │   └── SendReceiptFilter.php
│   │
│   ├── User/
│   │   ├── ValidateUserDataFilter.php
│   │   ├── NormalizeEmailFilter.php
│   │   ├── HashPasswordFilter.php
│   │   ├── CreateUserFilter.php
│   │   └── SendWelcomeEmailFilter.php
│   │
│   ├── Import/                             # ETL pipeline filters
│   │   ├── ReadCsvFilter.php
│   │   ├── ValidateRowFilter.php
│   │   ├── TransformDataFilter.php
│   │   ├── DeduplicateFilter.php
│   │   ├── EnrichDataFilter.php
│   │   └── PersistDataFilter.php
│   │
│   └── Common/
│       ├── LoggingFilter.php
│       ├── AuthorizationFilter.php
│       └── RateLimitFilter.php
│
├── Services/
│   ├── OrderProcessingService.php          # Builds order pipeline
│   ├── PaymentProcessingService.php
│   ├── UserRegistrationService.php
│   └── DataImportService.php
│
├── Http/
│   └── Controllers/
│       ├── OrderController.php
│       └── ImportController.php
│
└── Models/
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── pipeline/
│   ├── Filter.java                         # Filter interface
│   ├── Pipeline.java                       # Pipeline implementation
│   ├── PipelineBuilder.java
│   └── PipelineContext.java                # Data flowing through pipe
│
├── filter/
│   ├── order/
│   │   ├── ValidateOrderFilter.java
│   │   ├── CalculateTotalFilter.java
│   │   ├── ApplyDiscountFilter.java
│   │   ├── CalculateTaxFilter.java
│   │   ├── CheckInventoryFilter.java
│   │   ├── ReserveStockFilter.java
│   │   └── CreateOrderRecordFilter.java
│   │
│   ├── payment/
│   │   ├── ValidatePaymentFilter.java
│   │   ├── FraudDetectionFilter.java
│   │   ├── ProcessPaymentFilter.java
│   │   └── SendReceiptFilter.java
│   │
│   ├── user/
│   │   ├── ValidateUserDataFilter.java
│   │   ├── NormalizeEmailFilter.java
│   │   ├── HashPasswordFilter.java
│   │   └── CreateUserFilter.java
│   │
│   ├── etl/
│   │   ├── ReadCsvFilter.java
│   │   ├── ValidateRowFilter.java
│   │   ├── TransformDataFilter.java
│   │   ├── DeduplicateFilter.java
│   │   └── PersistDataFilter.java
│   │
│   └── common/
│       ├── LoggingFilter.java
│       └── AuthorizationFilter.java
│
├── service/
│   ├── OrderProcessingService.java
│   ├── PaymentProcessingService.java
│   └── DataImportService.java
│
├── controller/
│   ├── OrderController.java
│   └── ImportController.java
│
└── config/
    └── PipelineConfig.java
```

---

## Golang

```
project/
├── cmd/
│   └── api/
│       └── main.go
│
├── internal/
│   ├── pipeline/
│   │   ├── filter.go                      # Filter interface
│   │   ├── pipeline.go                    # Pipeline implementation
│   │   ├── builder.go
│   │   └── context.go                     # Data flowing through pipe
│   │
│   ├── filter/
│   │   ├── order/
│   │   │   ├── validate_order.go
│   │   │   ├── calculate_total.go
│   │   │   ├── apply_discount.go
│   │   │   ├── calculate_tax.go
│   │   │   ├── check_inventory.go
│   │   │   ├── reserve_stock.go
│   │   │   └── create_order_record.go
│   │   │
│   │   ├── payment/
│   │   │   ├── validate_payment.go
│   │   │   ├── fraud_detection.go
│   │   │   ├── process_payment.go
│   │   │   └── send_receipt.go
│   │   │
│   │   ├── user/
│   │   │   ├── validate_user.go
│   │   │   ├── normalize_email.go
│   │   │   ├── hash_password.go
│   │   │   └── create_user.go
│   │   │
│   │   ├── etl/
│   │   │   ├── read_csv.go
│   │   │   ├── validate_row.go
│   │   │   ├── transform_data.go
│   │   │   ├── deduplicate.go
│   │   │   └── persist_data.go
│   │   │
│   │   └── common/
│   │       ├── logging.go
│   │       └── authorization.go
│   │
│   ├── service/
│   │   ├── order_processing.go
│   │   ├── payment_processing.go
│   │   └── data_import.go
│   │
│   ├── handler/
│   │   ├── order_handler.go
│   │   └── import_handler.go
│   │
│   └── config/
│       └── config.go
│
├── pkg/
│   └── pipeline/
│       ├── filter.go                      # Reusable pipeline library
│       └── pipeline.go
├── go.mod
└── Makefile
```
