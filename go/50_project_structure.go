package main

import "fmt"

// ===============================================
// LAYIHE STRUKTURU VE ARXITEKTURA
// ===============================================

// Go layihelerinde en cox istifade olunan strukturlar

func main() {

	fmt.Println(`
=======================================
1. SADƏ LAYIHE (kicik proqramlar)
=======================================

myapp/
├── go.mod
├── go.sum
├── main.go           # giris noqtesi
├── handler.go        # HTTP handler-leri
├── model.go          # struct-lar
├── storage.go        # database
└── README.md

Bir paket, bir qovluq. Kicik aletlen ucun kifayetdir.
=======================================
2. ORTA OLCULU LAYIHE (cox layiheler)
=======================================

myapp/
├── go.mod
├── go.sum
├── main.go
├── cmd/                    # Eger bir nece binary varsa
│   ├── server/
│   │   └── main.go         # go build ./cmd/server
│   └── worker/
│       └── main.go         # go build ./cmd/worker
│
├── internal/               # XARICI paketler idxal ede BILMEZ
│   ├── handler/            # HTTP handler-leri
│   │   ├── user.go
│   │   ├── product.go
│   │   └── middleware.go
│   ├── service/            # Biznes mentiq
│   │   ├── user.go
│   │   └── product.go
│   ├── repository/         # Database emeliyyatlari
│   │   ├── user.go
│   │   └── product.go
│   ├── model/              # Struct-lar / entity-ler
│   │   ├── user.go
│   │   └── product.go
│   └── config/             # Konfiqurasiya
│       └── config.go
│
├── pkg/                    # Xarici paketler idxal ede BILER (shared code)
│   ├── validator/
│   │   └── validator.go
│   └── logger/
│       └── logger.go
│
├── migrations/             # SQL migration fayllari
│   ├── 001_create_users.sql
│   └── 002_create_products.sql
│
├── config/                 # Konfiqurasiya fayllari
│   ├── config.yaml
│   └── config.example.yaml
│
├── docs/                   # Sənədləşdirmə
├── Makefile               # Build/test emrleri
├── Dockerfile             # Container
└── .github/workflows/     # CI/CD
=======================================
3. CLEAN ARCHITECTURE (boyuk layiheler)
=======================================

myapp/
├── cmd/
│   └── api/
│       └── main.go
│
├── internal/
│   ├── domain/             # ESASI: entity-ler ve interface-ler
│   │   ├── user.go         # type User struct, type UserRepository interface
│   │   └── errors.go
│   │
│   ├── usecase/            # Biznes qaydalari (domain-e asilidir)
│   │   ├── user_service.go
│   │   └── user_service_test.go
│   │
│   ├── adapter/            # Xarici alem ile elaqe
│   │   ├── repository/     # Database implementasiyasi
│   │   │   ├── postgres/
│   │   │   │   └── user.go # UserRepository interface-ni tetbiq edir
│   │   │   └── redis/
│   │   │       └── cache.go
│   │   │
│   │   ├── handler/        # HTTP/gRPC handler-ler
│   │   │   ├── http/
│   │   │   │   └── user.go
│   │   │   └── grpc/
│   │   │       └── user.go
│   │   │
│   │   └── client/         # Xarici API client-leri
│   │       └── payment.go
│   │
│   └── infrastructure/     # Framework, driver, kutubxana ayarlari
│       ├── database.go
│       ├── server.go
│       └── logger.go
│
├── pkg/                    # Paylashilan utility-ler
│   └── pagination/
│       └── pagination.go
│
└── api/                    # API sxemleri
    ├── openapi.yaml
    └── proto/
        └── user.proto
=======================================
4. ASILILIQ ENJEKSIYONU (DI)
=======================================
`)

	// DI ornegi
	diOrnek := `
// domain/user.go - Interface terefi
type UserRepository interface {
    FindByID(ctx context.Context, id int) (*User, error)
    Save(ctx context.Context, user *User) error
}

type EmailSender interface {
    Send(to, subject, body string) error
}

// usecase/user_service.go - Biznes mentiq
type UserService struct {
    repo  UserRepository   // interface-e asilidir, konkret tipe deyil
    email EmailSender
}

func NewUserService(repo UserRepository, email EmailSender) *UserService {
    return &UserService{repo: repo, email: email}
}

// adapter/repository/postgres/user.go - Konkret implementasiya
type PostgresUserRepo struct {
    db *sql.DB
}

func (r *PostgresUserRepo) FindByID(ctx context.Context, id int) (*User, error) {
    // SQL sorgusu
}

// cmd/api/main.go - Hamisi bir araya getirilir
func main() {
    db := setupDatabase()

    // Konkret implementasiyalar yarad
    userRepo := postgres.NewUserRepo(db)
    emailSender := smtp.NewSender(config)

    // Interface vasitesile inject et
    userService := usecase.NewUserService(userRepo, emailSender)
    userHandler := handler.NewUserHandler(userService)

    // Router qur
    mux := http.NewServeMux()
    mux.HandleFunc("GET /users/{id}", userHandler.GetByID)

    http.ListenAndServe(":8080", mux)
}
`
	fmt.Println(diOrnek)

	fmt.Println(`
=======================================
5. MUHUM QAYDALAR
=======================================

├── internal/  -> Xarici paketler IMPORT EDE BILMEZ (Go kompilyator qoruyur)
├── pkg/       -> Xarici paketler import ede biler
├── cmd/       -> Her alt qovluq bir binary-dir

- Paket adlari kicik herf, tek soz: user, product, handler (users DEYIL)
- Fayl adlari snake_case: user_service.go
- Interface adlari -er ile: Reader, Writer, Stringer, UserRepository
- Constructor: NewXxx() pattern: NewUserService(), NewServer()
- Deyer tipi alir, interface qaytarir (accept interfaces, return structs)
- Asililiq inversiyasi: usecase -> domain <- adapter (ox istiqameti!)
- Testler eyni paketde: user.go -> user_test.go

=======================================
6. MAKEFILE ORNEGI
=======================================

# Makefile
.PHONY: build run test lint clean

build:
	go build -o bin/api ./cmd/api

run:
	go run ./cmd/api

test:
	go test ./... -v -race -cover

lint:
	golangci-lint run

migrate:
	migrate -path migrations -database "postgres://..." up

docker:
	docker build -t myapp .

clean:
	rm -rf bin/
`)
}
