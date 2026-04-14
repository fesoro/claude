package main

import "fmt"

// ===============================================
// KOD GENERASIYASI (CODE GENERATION)
// ===============================================

// go generate - kompilyasiyadan evvel kodu avtomatik yaratmaq
// Tekrarlanan, mekanik kodu el ile yazmaq yerine generator-lardan istifade edin

func main() {

	fmt.Println(`
=======================================
1. GO GENERATE - ESAS
=======================================

go generate emri fayllardaki //go:generate kommentlerini tapib isleyir.
Bu emrler kompilyasiyadan evvel lazim olan kodu yaradir.

Istifade:
  go generate ./...     # butun fayllarda islet
  go generate           # cari paketde islet
=======================================
2. STRINGER - Enum ucun String() metodu
=======================================

go install golang.org/x/tools/cmd/stringer@latest
`)

	// Stringer ornegi
	stringerOrnek := `
package main

//go:generate stringer -type=Reng

type Reng int

const (
    Qirmizi Reng = iota
    Yashil
    Goy
    Sari
)

// "go generate" isletdikden sonra avtomatik yaranir:
// func (r Reng) String() string { ... }
// Qirmizi.String() = "Qirmizi"

func main() {
    r := Qirmizi
    fmt.Println(r)        // "Qirmizi" (String() avtomatik cagrilir)
    fmt.Println(Goy)      // "Goy"
}
`
	fmt.Println(stringerOrnek)

	fmt.Println(`
=======================================
3. ENUMER - Daha guclu enum generator
=======================================

go install github.com/dmarkham/enumer@latest
`)

	enumerOrnek := `
package main

//go:generate enumer -type=Status -json -text -sql

type Status int

const (
    Aktiv   Status = iota
    Passiv
    Silinmis
)

// Yaranir:
// StatusString("Aktiv")  -> Aktiv, nil
// Aktiv.MarshalJSON()    -> "Aktiv"
// Aktiv.MarshalText()    -> "Aktiv"
// StatusValues()         -> [Aktiv, Passiv, Silinmis]
`
	fmt.Println(enumerOrnek)

	fmt.Println(`
=======================================
4. MOCKGEN - Interface mock yaratma
=======================================

go install go.uber.org/mock/mockgen@latest
`)

	mockgenOrnek := `
package service

//go:generate mockgen -source=service.go -destination=mock_service.go -package=service

type UserRepository interface {
    FindByID(id int) (*User, error)
    Save(user *User) error
    Delete(id int) error
}

// "go generate" isletdikden sonra mock_service.go yaranir
// Testlerde:
// ctrl := gomock.NewController(t)
// mockRepo := NewMockUserRepository(ctrl)
// mockRepo.EXPECT().FindByID(1).Return(&User{ID: 1}, nil)
`
	fmt.Println(mockgenOrnek)

	fmt.Println(`
=======================================
5. SQLC - SQL-den Go kodu yaratma
=======================================

go install github.com/sqlc-dev/sqlc/cmd/sqlc@latest

Konfiqurasiya: sqlc.yaml
---
version: "2"
sql:
  - engine: "postgresql"
    queries: "query/"
    schema: "schema/"
    gen:
      go:
        package: "db"
        out: "internal/db"
---

SQL sorgusu: query/user.sql
---
-- name: GetUser :one
SELECT id, ad, email FROM istifadeciler WHERE id = $1;

-- name: ListUsers :many
SELECT id, ad, email FROM istifadeciler ORDER BY ad LIMIT $1;

-- name: CreateUser :one
INSERT INTO istifadeciler (ad, email) VALUES ($1, $2) RETURNING id, ad, email;
---

"sqlc generate" isletdikden sonra tip-tehlukesiz Go kodu yaranir:
func (q *Queries) GetUser(ctx context.Context, id int) (User, error)
func (q *Queries) ListUsers(ctx context.Context, limit int) ([]User, error)
=======================================
6. XUSUSI GENERATOR YAZMAQ
=======================================
`)

	xususigenOrnek := `
// generator.go - xususi kod generatoru
package main

import (
    "fmt"
    "os"
    "strings"
    "text/template"
)

var tmpl = template.Must(template.New("").Parse(` + "`" + `
// KOD AVTOMATIK YARADILMIS - DEYISMEYIN
// go generate ile yaradildi

package {{.Package}}

{{range .Types}}
func (v {{.Name}}) Validate() error {
    switch v {
    case {{join .Values ", "}}:
        return nil
    default:
        return fmt.Errorf("etibarsiz {{.Name}}: %v", v)
    }
}
{{end}}
` + "`" + `))

func main() {
    data := struct {
        Package string
        Types   []struct {
            Name   string
            Values []string
        }
    }{
        Package: "main",
        Types: []struct {
            Name   string
            Values []string
        }{
            {Name: "Status", Values: []string{"Aktiv", "Passiv", "Silinmis"}},
        },
    }

    f, _ := os.Create("validate_gen.go")
    defer f.Close()
    tmpl.Execute(f, data)
}
`
	fmt.Println(xususigenOrnek)

	fmt.Println(`
=======================================
7. POPULYAR GENERATORLAR
=======================================

Alet             | Nə yaradir              | Yuklemek
-----------------+--------------------------+----------------------------------
stringer         | String() metodu          | golang.org/x/tools/cmd/stringer
enumer           | Enum utilityleri         | github.com/dmarkham/enumer
mockgen          | Interface mock-lari      | go.uber.org/mock/mockgen
sqlc             | SQL-den Go kodu          | github.com/sqlc-dev/sqlc
protoc-gen-go    | Protobuf Go kodu         | google.golang.org/protobuf
oapi-codegen     | OpenAPI-den Go kodu      | github.com/oapi-codegen/oapi-codegen
wire             | Dependency injection     | github.com/google/wire
ent              | ORM/schema-dan Go kodu   | entgo.io/ent

=======================================
8. TOVSIYELER
=======================================

- Yaranmis fayllari _gen.go ve ya _string.go ile adlandirin
- Yaranmis fayllarin basina "// Code generated ... DO NOT EDIT." yazin
- go generate ./... emrini CI/CD-de isledin
- Yaranmis fayllari git-e commit edin (generator olmadan da build olsun)
`)
}
