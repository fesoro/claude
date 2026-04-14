package main

import (
	"fmt"
)

// ===============================================
// CLEAN ARCHITECTURE / HEXAGONAL ARCHITECTURE
// ===============================================

// Clean Architecture - Robert C. Martin (Uncle Bob) terefinden ireli surulub.
// Esas fikir: business logic xarici asililiqlara (DB, HTTP, framework) baglanmamalidir.
// Go-da interface-ler vasitesile bu cox tebii sekilde heyata kecirilir.

func main() {
	fmt.Println("=== CLEAN ARCHITECTURE ===")

	// -------------------------------------------
	// 1. Laylar (tebeqeler) ve asililiq istiqameti
	// -------------------------------------------
	fmt.Println("\n--- 1. Arxitektura Laylar ---")
	fmt.Println(`
  // Asililiq istiqameti: XARICI -> DAXILI (hec vaxt terskine)
  //
  //  ┌──────────────────────────────────────────┐
  //  │  Handler / Delivery (HTTP, gRPC, CLI)    │  <- en xarici
  //  ├──────────────────────────────────────────┤
  //  │  Usecase / Service (business logic)      │
  //  ├──────────────────────────────────────────┤
  //  │  Repository (data access interface)      │
  //  ├──────────────────────────────────────────┤
  //  │  Domain / Entity (esas melumat modeli)   │  <- en daxili
  //  └──────────────────────────────────────────┘
  //
  //  Domain - hec neye asili deyil
  //  Usecase - yalniz Domain-e asilidir
  //  Repository interface - Usecase-de tanimlanir, xaricde implement olunur
  //  Handler - Usecase-i cagiri, HTTP/gRPC detallarini idare edir

  // Hexagonal Architecture (Ports & Adapters) eyni fikri basqa formada ifade edir:
  // - Ports = interface-ler (daxili)
  // - Adapters = implementasiyalar (xarici)
  //   - Primary adapters: HTTP handler, CLI, gRPC (sisteme giris)
  //   - Secondary adapters: PostgreSQL repo, Redis cache (sistemden cixis)`)

	// -------------------------------------------
	// 2. Domain layer
	// -------------------------------------------
	fmt.Println("\n--- 2. Domain Layer (Entity) ---")
	fmt.Println("Esas business obyektleri. Hec bir xarici asililiq yoxdur.")
	fmt.Println(`
  // internal/domain/user.go

  package domain

  import (
      "errors"
      "time"
  )

  // Esas entity
  type User struct {
      ID        int64
      Ad        string
      Email     string
      Parol     string // hashlenmiş
      YaranmaTarixi time.Time
      Aktiv     bool
  }

  // Domain xetalari
  var (
      ErrIstifadeciTapilmadi = errors.New("istifadeci tapilmadi")
      ErrEmailMovcuddur      = errors.New("bu email artiq qeydiyyatdadir")
      ErrEtibarsizEmail      = errors.New("email formati yanlisdir")
  )

  // Domain validation (business qaydalari)
  func (u *User) Dogrula() error {
      if u.Ad == "" {
          return errors.New("ad bos ola bilmez")
      }
      if len(u.Ad) < 2 || len(u.Ad) > 100 {
          return errors.New("ad 2-100 simvol olmalidir")
      }
      if u.Email == "" {
          return ErrEtibarsizEmail
      }
      return nil
  }

  // Value Object numunesi
  type Email struct {
      deger string
  }

  func YeniEmail(email string) (Email, error) {
      if !strings.Contains(email, "@") {
          return Email{}, ErrEtibarsizEmail
      }
      return Email{deger: strings.ToLower(email)}, nil
  }

  func (e Email) String() string { return e.deger }`)

	// -------------------------------------------
	// 3. Repository interface (port)
	// -------------------------------------------
	fmt.Println("\n--- 3. Repository Interface ---")
	fmt.Println("Usecase layinda tanimlanir, amma implementasiya xaricde olur.")
	fmt.Println(`
  // internal/domain/repository.go

  package domain

  import "context"

  // Bu interface-dir - implementasiya deyil!
  // Usecase bu interface-e asilidir, konkret DB-ye deyil.

  type UserRepository interface {
      YaratByID(ctx context.Context, id int64) (*User, error)
      EmailIleTap(ctx context.Context, email string) (*User, error)
      Yarat(ctx context.Context, user *User) error
      Yenile(ctx context.Context, user *User) error
      Sil(ctx context.Context, id int64) error
      Siyahi(ctx context.Context, limit, offset int) ([]*User, error)
  }

  // Diger repository-ler
  type OrderRepository interface {
      SifarisYarat(ctx context.Context, order *Order) error
      IstifadeciSifarisleri(ctx context.Context, userID int64) ([]*Order, error)
  }`)

	// -------------------------------------------
	// 4. Usecase / Service layer
	// -------------------------------------------
	fmt.Println("\n--- 4. Usecase / Service Layer ---")
	fmt.Println("Business logic buradadir. Repository interface-ini istifade edir.")
	fmt.Println(`
  // internal/usecase/user_usecase.go

  package usecase

  import (
      "context"
      "myapp/internal/domain"
      "golang.org/x/crypto/bcrypt"
  )

  type UserUsecase struct {
      repo domain.UserRepository  // interface-e asilidir, implementasiyaya deyil
  }

  func NewUserUsecase(repo domain.UserRepository) *UserUsecase {
      return &UserUsecase{repo: repo}
  }

  // Qeydiyyat - business logic
  func (uc *UserUsecase) Qeydiyyat(ctx context.Context, ad, email, parol string) (*domain.User, error) {
      // 1. Email movcudlugu yoxla
      movcud, err := uc.repo.EmailIleTap(ctx, email)
      if err != nil && err != domain.ErrIstifadeciTapilmadi {
          return nil, fmt.Errorf("email yoxlama ugursuz: %w", err)
      }
      if movcud != nil {
          return nil, domain.ErrEmailMovcuddur
      }

      // 2. Parolu hashle
      hash, err := bcrypt.GenerateFromPassword([]byte(parol), bcrypt.DefaultCost)
      if err != nil {
          return nil, fmt.Errorf("parol hashleme ugursuz: %w", err)
      }

      // 3. Entity yarat ve dogrula
      user := &domain.User{
          Ad:    ad,
          Email: email,
          Parol: string(hash),
          Aktiv: true,
      }
      if err := user.Dogrula(); err != nil {
          return nil, err
      }

      // 4. Database-e yaz
      if err := uc.repo.Yarat(ctx, user); err != nil {
          return nil, fmt.Errorf("istifadeci yaratma ugursuz: %w", err)
      }

      return user, nil
  }

  // Giris - business logic
  func (uc *UserUsecase) Giris(ctx context.Context, email, parol string) (*domain.User, error) {
      user, err := uc.repo.EmailIleTap(ctx, email)
      if err != nil {
          return nil, domain.ErrIstifadeciTapilmadi
      }

      err = bcrypt.CompareHashAndPassword([]byte(user.Parol), []byte(parol))
      if err != nil {
          return nil, errors.New("parol yanlisdir")
      }

      if !user.Aktiv {
          return nil, errors.New("hesab deaktivdir")
      }

      return user, nil
  }`)

	// -------------------------------------------
	// 5. Repository implementation (adapter)
	// -------------------------------------------
	fmt.Println("\n--- 5. Repository Implementation ---")
	fmt.Println("Konkret database implementasiyasi. Domain interface-ini implement edir.")
	fmt.Println(`
  // internal/repository/postgres/user_repo.go

  package postgres

  import (
      "context"
      "database/sql"
      "myapp/internal/domain"
  )

  // domain.UserRepository interface-ini implement edir
  type UserRepo struct {
      db *sql.DB
  }

  func NewUserRepo(db *sql.DB) *UserRepo {
      return &UserRepo{db: db}
  }

  func (r *UserRepo) YaratByID(ctx context.Context, id int64) (*domain.User, error) {
      user := &domain.User{}
      err := r.db.QueryRowContext(ctx,
          "SELECT id, ad, email, parol, aktiv FROM users WHERE id = $1", id,
      ).Scan(&user.ID, &user.Ad, &user.Email, &user.Parol, &user.Aktiv)

      if err == sql.ErrNoRows {
          return nil, domain.ErrIstifadeciTapilmadi
      }
      return user, err
  }

  func (r *UserRepo) EmailIleTap(ctx context.Context, email string) (*domain.User, error) {
      user := &domain.User{}
      err := r.db.QueryRowContext(ctx,
          "SELECT id, ad, email, parol, aktiv FROM users WHERE email = $1", email,
      ).Scan(&user.ID, &user.Ad, &user.Email, &user.Parol, &user.Aktiv)

      if err == sql.ErrNoRows {
          return nil, domain.ErrIstifadeciTapilmadi
      }
      return user, err
  }

  func (r *UserRepo) Yarat(ctx context.Context, user *domain.User) error {
      return r.db.QueryRowContext(ctx,
          "INSERT INTO users (ad, email, parol, aktiv) VALUES ($1, $2, $3, $4) RETURNING id",
          user.Ad, user.Email, user.Parol, user.Aktiv,
      ).Scan(&user.ID)
  }

  // Eyni interface-i MongoDB, Redis, InMemory ile de implement ede bilerik
  // Test ucun mock repository yaratmaq asandir`)

	// -------------------------------------------
	// 6. Handler / Delivery layer
	// -------------------------------------------
	fmt.Println("\n--- 6. Handler / Delivery Layer ---")
	fmt.Println("HTTP/gRPC detallarini idare edir. Usecase-i cagirir.")
	fmt.Println(`
  // internal/delivery/http/user_handler.go

  package http

  import (
      "encoding/json"
      "myapp/internal/usecase"
      "net/http"
  )

  type UserHandler struct {
      usecase *usecase.UserUsecase
  }

  func NewUserHandler(uc *usecase.UserUsecase) *UserHandler {
      return &UserHandler{usecase: uc}
  }

  type QeydiyyatSorgusu struct {
      Ad    string ` + "`json:\"ad\"`" + `
      Email string ` + "`json:\"email\"`" + `
      Parol string ` + "`json:\"parol\"`" + `
  }

  type XetaCavabi struct {
      Mesaj string ` + "`json:\"mesaj\"`" + `
  }

  func (h *UserHandler) Qeydiyyat(w http.ResponseWriter, r *http.Request) {
      var sorgu QeydiyyatSorgusu
      if err := json.NewDecoder(r.Body).Decode(&sorgu); err != nil {
          w.WriteHeader(http.StatusBadRequest)
          json.NewEncoder(w).Encode(XetaCavabi{Mesaj: "yanlıs format"})
          return
      }

      user, err := h.usecase.Qeydiyyat(r.Context(), sorgu.Ad, sorgu.Email, sorgu.Parol)
      if err != nil {
          status := http.StatusInternalServerError
          switch err {
          case domain.ErrEmailMovcuddur:
              status = http.StatusConflict
          case domain.ErrEtibarsizEmail:
              status = http.StatusBadRequest
          }
          w.WriteHeader(status)
          json.NewEncoder(w).Encode(XetaCavabi{Mesaj: err.Error()})
          return
      }

      w.WriteHeader(http.StatusCreated)
      json.NewEncoder(w).Encode(user)
  }

  // Router qurmaq
  func (h *UserHandler) Routes(mux *http.ServeMux) {
      mux.HandleFunc("POST /api/users/register", h.Qeydiyyat)
      mux.HandleFunc("POST /api/users/login", h.Giris)
      mux.HandleFunc("GET /api/users/{id}", h.AlByID)
  }`)

	// -------------------------------------------
	// 7. Project layout (qovluq strukturu)
	// -------------------------------------------
	fmt.Println("\n--- 7. Layihe Strukturu ---")
	fmt.Println(`
  myapp/
  ├── cmd/
  │   └── api/
  │       └── main.go              # Giris noqtesi, DI, server baslama
  ├── internal/
  │   ├── domain/                  # En daxili lay - hec neye asili deyil
  │   │   ├── user.go              # Entity, Value Objects
  │   │   ├── order.go
  │   │   ├── repository.go        # Repository interface-leri
  │   │   └── errors.go            # Domain xetalari
  │   ├── usecase/                 # Business logic
  │   │   ├── user_usecase.go
  │   │   ├── user_usecase_test.go # Mock repo ile test
  │   │   └── order_usecase.go
  │   ├── repository/              # Repository implementasiyalari
  │   │   ├── postgres/
  │   │   │   ├── user_repo.go
  │   │   │   └── order_repo.go
  │   │   └── redis/
  │   │       └── cache_repo.go
  │   └── delivery/                # Xarici interfeys
  │       ├── http/
  │       │   ├── user_handler.go
  │       │   ├── middleware.go
  │       │   └── router.go
  │       └── grpc/
  │           └── user_server.go
  ├── pkg/                         # Xarici paketler ucun paylasilabilen kod
  │   ├── logger/
  │   └── validator/
  ├── migrations/                  # Database migration-lar
  ├── config/
  │   └── config.yaml
  ├── go.mod
  └── go.sum`)

	// -------------------------------------------
	// 8. Dependency Injection (Wire / Fx)
	// -------------------------------------------
	fmt.Println("\n--- 8. Dependency Injection ---")
	fmt.Println("Butun lay-lari bir-birine baglama (wiring).")
	fmt.Println(`
  // ---- Manual DI (en sade yol) ----
  // cmd/api/main.go

  func main() {
      // Config
      cfg := config.Yukle()

      // Database
      db, err := sql.Open("postgres", cfg.Database.DSN)
      if err != nil {
          log.Fatal(err)
      }
      defer db.Close()

      // Repository (adapter) - interface-i implement edir
      userRepo := postgres.NewUserRepo(db)

      // Usecase - repository interface-ini alir
      userUsecase := usecase.NewUserUsecase(userRepo)

      // Handler - usecase-i alir
      userHandler := http.NewUserHandler(userUsecase)

      // Router
      mux := http.NewServeMux()
      userHandler.Routes(mux)

      // Server
      log.Fatal(http.ListenAndServe(":8080", mux))
  }

  // ---- Google Wire (compile-time DI) ----
  // go get github.com/google/wire

  // wire.go
  //go:build wireinject

  package main

  import "github.com/google/wire"

  func InitializeApp() (*App, error) {
      wire.Build(
          config.Yukle,
          database.Baglan,
          postgres.NewUserRepo,
          wire.Bind(new(domain.UserRepository), new(*postgres.UserRepo)),
          usecase.NewUserUsecase,
          delivery.NewUserHandler,
          NewApp,
      )
      return nil, nil
  }
  // "wire" komandasi bu koddan real initialization kodu yaradir

  // ---- Uber Fx (runtime DI) ----
  // go get go.uber.org/fx

  import "go.uber.org/fx"

  func main() {
      fx.New(
          fx.Provide(
              config.Yukle,
              database.Baglan,
              postgres.NewUserRepo,
              usecase.NewUserUsecase,
              delivery.NewUserHandler,
          ),
          fx.Invoke(func(h *delivery.UserHandler) {
              // Server basla
          }),
      ).Run()
  }`)

	// -------------------------------------------
	// 9. Repository pattern detalli
	// -------------------------------------------
	fmt.Println("\n--- 9. Repository Pattern ---")
	fmt.Println(`
  // Repository - data access layer-i abstrakt edir
  // Usecase DB-nin ne oldugunu bilmir (Postgres? MongoDB? File?)

  // Generic repository interface
  type Repository[T any] interface {
      AlByID(ctx context.Context, id int64) (*T, error)
      Yarat(ctx context.Context, entity *T) error
      Yenile(ctx context.Context, entity *T) error
      Sil(ctx context.Context, id int64) error
  }

  // Specification pattern ile sorgu
  type UserSpec struct {
      Email    *string
      Aktiv    *bool
      AdAxtaris *string
      Limit    int
      Offset   int
  }

  type UserRepository interface {
      Axtar(ctx context.Context, spec UserSpec) ([]*User, int, error)
  }

  // Implementasiya
  func (r *UserRepo) Axtar(ctx context.Context, spec UserSpec) ([]*User, int, error) {
      query := "SELECT id, ad, email FROM users WHERE 1=1"
      args := []interface{}{}
      i := 1

      if spec.Email != nil {
          query += fmt.Sprintf(" AND email = $%d", i)
          args = append(args, *spec.Email)
          i++
      }
      if spec.Aktiv != nil {
          query += fmt.Sprintf(" AND aktiv = $%d", i)
          args = append(args, *spec.Aktiv)
          i++
      }
      // ... diger filtrler
      return users, count, nil
  }`)

	// -------------------------------------------
	// 10. Unit of Work pattern
	// -------------------------------------------
	fmt.Println("\n--- 10. Unit of Work Pattern ---")
	fmt.Println("Bir nece repository emeliyyatini tek tranzaksiyada icra etmek.")
	fmt.Println(`
  // internal/domain/uow.go

  type UnitOfWork interface {
      // Tranzaksiya baslat ve commit/rollback et
      Icra(ctx context.Context, fn func(tx UnitOfWork) error) error

      // Tranzaksiya daxilinde repository-ler
      Users() UserRepository
      Orders() OrderRepository
  }

  // internal/repository/postgres/uow.go

  type PostgresUOW struct {
      db *sql.DB
      tx *sql.Tx
  }

  func (uow *PostgresUOW) Icra(ctx context.Context, fn func(tx UnitOfWork) error) error {
      tx, err := uow.db.BeginTx(ctx, nil)
      if err != nil {
          return err
      }

      txUow := &PostgresUOW{db: uow.db, tx: tx}

      if err := fn(txUow); err != nil {
          tx.Rollback()
          return err
      }
      return tx.Commit()
  }

  func (uow *PostgresUOW) Users() domain.UserRepository {
      if uow.tx != nil {
          return NewUserRepoTx(uow.tx) // tranzaksiya daxilinde
      }
      return NewUserRepo(uow.db)
  }

  // Usecase-de istifade
  func (uc *OrderUsecase) SifarisYarat(ctx context.Context, req SifarisReq) error {
      return uc.uow.Icra(ctx, func(tx domain.UnitOfWork) error {
          // 1. Istifadecini yoxla
          user, err := tx.Users().AlByID(ctx, req.UserID)
          if err != nil {
              return err
          }

          // 2. Sifarisi yarat
          order := &domain.Order{UserID: user.ID, Mebleg: req.Mebleg}
          if err := tx.Orders().SifarisYarat(ctx, order); err != nil {
              return err
          }

          // Hersey ugurlu - Icra() avtomatik COMMIT edecek
          // Xeta olarsa - avtomatik ROLLBACK
          return nil
      })
  }`)

	// -------------------------------------------
	// 11. Tam axin numunesi
	// -------------------------------------------
	fmt.Println("\n--- 11. Tam Axin: HTTP -> Handler -> Usecase -> Repo -> DB ---")
	fmt.Println(`
  // 1. Client POST /api/users/register gonderir
  //    {"ad": "Orxan", "email": "orxan@test.com", "parol": "12345"}
  //
  // 2. Router -> UserHandler.Qeydiyyat()
  //    - JSON parse edir
  //    - Usecase-i cagirir
  //
  // 3. UserUsecase.Qeydiyyat()
  //    - Email movcudlugunu yoxlayir (repo.EmailIleTap)
  //    - Parolu hashleyir
  //    - Domain validation (user.Dogrula)
  //    - Repo-ya yazir (repo.Yarat)
  //
  // 4. PostgresUserRepo.Yarat()
  //    - SQL INSERT icra edir
  //    - ID qaytarir
  //
  // 5. Cavab yuxari qaytir
  //    - Usecase -> Handler -> Client
  //    - HTTP 201 Created + user JSON
  //
  // Test ucun:
  // - Handler test: mock usecase
  // - Usecase test: mock repository
  // - Repository test: test database ve ya sqlmock
  //
  // Bu ayriliq sayesinde:
  // - PostgreSQL-den MongoDB-ye kecmek ucun yalniz repo deyisir
  // - HTTP-den gRPC-ye kecmek ucun yalniz handler deyisir
  // - Business logic (usecase) hec vaxt deyismir`)

	fmt.Println("\n=== XULASE ===")
	fmt.Println("Domain       - esas model, xarici asililiq yoxdur")
	fmt.Println("Usecase      - business logic, interface-lere asilidir")
	fmt.Println("Repository   - data access interface + implementasiya")
	fmt.Println("Handler      - HTTP/gRPC adapteri")
	fmt.Println("DI           - manual, Wire, ve ya Fx ile asililiq enjeksiyonu")
	fmt.Println("Unit of Work - bir nece repo emeliyyatini tek tranzaksiyada")
	fmt.Println("Fayda        - test rahatliqi, deyisdirile bilen komponentler")
}
