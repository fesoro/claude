# Go Workspace (Middle)

## İcmal

`go.work` faylı Go 1.18 ilə gəlmişdir. Bir neçə Go modulunu lokal olaraq birlikdə işləməyə imkan verir — serverə push etmədən, `replace` direktivlərini hər `go.mod`-a əlavə etmədən. Microservice layihəsini və ya paylaşılan kitabxananı eyni anda inkişaf etdirəndə bu mexanizm çox vacibdir.

## Niyə Vacibdir

Təsəvvür edin: `payment-service` və `shared-library` iki ayrı Git repo-dadır. `shared-library`-ə yeni funksiya əlavəsi edirsiniz. Bunu test etmək üçün ya GitHub-a push etməli, ya da `go.mod`-da `replace` direktivi əlavə etməlisiniz. Workspace bu problemi həll edir — iki modulu eyni workspace-də işlədərək lokal dəyişiklikləri dərhal test edə bilirsiniz.

## Əsas Anlayışlar

- **`go.work`** — workspace-in kök konfiqurasiya faylı; `go work init` ilə yaradılır
- **`go work use <path>`** — workspace-ə modul əlavə edir
- **`go work sync`** — `go.work.sum` faylını yeniləyir
- **`GOWORK=off`** — workspace-i müvəqqəti söndürmək üçün environment variable
- **`go.work.sum`** — workspace-in dependency checksum faylı (`.gitignore`-a əlavə etmə)
- **`replace` direktivi** — `go.mod`-da alternativ modul yolu göstərmək üçün (workspace-dən fərqli)

## Praktik Baxış

**replace direktivi ilə müqayisə:**

```
replace direktivi (köhnə üsul)    →  go.work (yeni üsul)
go.mod-da yazılır                 →  go.work-da yazılır
Hər modulda ayrı                  →  Bir yerdə idarə olunur
Komit edilə bilər (bəzən problem) →  .gitignore-a əlavə edin
Birdən çox modulda paylaşmaq çətin →  Workspace avtomatik paylaşır
```

**Nə vaxt workspace, nə vaxt replace:**

| Ssenari | Seçim |
|---------|-------|
| Lokal inkişaf, bir neçə modul | `go.work` |
| CI/CD, production build | `GOWORK=off` |
| Fork-u test etmək (başqasının lib) | `replace` |
| Modul repozu özünüzdür | `go.work` |

**Trade-off-lar:**
- `go.work` repo-ya komit edilməməlidir — komanda üzvlərinin lokal yolları fərqlidir
- CI/CD pipeline-da `GOWORK=off` işlətmək — production build üçün go.mod-dakı versiyaları istifadə edir
- Workspace-dəki modullar arasında circular dependency — build fail olur

**Common mistakes:**
- `go.work.sum`-u `.gitignore`-a əlavə etməmək
- `go.work`-u repo-ya push etmək (komanda üzvlərinin müxtəlif lokal yolları var)
- CI/CD-də `GOWORK=off` unutmaq — lokal yollar serverdə mövcud deyil

## Nümunələr

### Nümunə 1: Workspace yaratmaq — əsas istifadə

```bash
# Layihə strukturu:
# ~/projects/
# ├── myapp/           (ana tətbiq)
# ├── shared-lib/      (paylaşılan kitabxana)
# └── payment-svc/     (payment microservice)

# 1. Hər modul üçün go.mod yaradılıb:
# myapp/go.mod         → module github.com/company/myapp
# shared-lib/go.mod    → module github.com/company/shared-lib
# payment-svc/go.mod   → module github.com/company/payment-svc

# 2. Workspace-i kök qovluqda yarat
cd ~/projects
go work init

# 3. Modulları əlavə et
go work use ./myapp
go work use ./shared-lib
go work use ./payment-svc

# Nəticə: go.work faylı yaranır
```

```
# ~/projects/go.work
go 1.22

use (
    ./myapp
    ./shared-lib
    ./payment-svc
)
```

### Nümunə 2: Konkret layihə strukturu

```
~/projects/
├── go.work
├── go.work.sum
│
├── myapp/
│   ├── go.mod
│   ├── go.sum
│   └── main.go
│
└── shared-lib/
    ├── go.mod
    ├── go.sum
    └── utils/
        └── format.go
```

```go
// shared-lib/go.mod
module github.com/company/shared-lib

go 1.22
```

```go
// shared-lib/utils/format.go
package utils

import "fmt"

func FormatOrderID(id int) string {
    return fmt.Sprintf("ORD-%06d", id)
}
```

```go
// myapp/go.mod
module github.com/company/myapp

go 1.22

require github.com/company/shared-lib v0.0.0
```

```go
// myapp/main.go
package main

import (
    "fmt"
    "github.com/company/shared-lib/utils"
)

func main() {
    // Workspace sayəsində lokal shared-lib-dən istifadə edir
    // Push etməyə ehtiyac yoxdur
    id := utils.FormatOrderID(42)
    fmt.Println(id) // ORD-000042
}
```

```bash
# myapp-ı işlət — shared-lib-dən avtomatik istifadə edir
cd ~/projects
go run ./myapp/
```

### Nümunə 3: go work init ilə sıfırdan quruluş

```bash
# Sıfırdan workspace qurmaq
mkdir ~/projects && cd ~/projects

# Modul 1: ana tətbiq
mkdir myapp && cd myapp
go mod init github.com/company/myapp
cat > main.go << 'EOF'
package main

import (
    "fmt"
    "github.com/company/logger"
)

func main() {
    logger.Info("Tətbiq başladı")
    fmt.Println("Salam!")
}
EOF
cd ..

# Modul 2: paylaşılan logger kitabxanası
mkdir logger && cd logger
go mod init github.com/company/logger
mkdir -p log
cat > log/log.go << 'EOF'
package logger

import (
    "fmt"
    "time"
)

func Info(msg string) {
    fmt.Printf("[INFO] %s — %s\n", time.Now().Format("15:04:05"), msg)
}
EOF
cd ..

# Workspace yarat
go work init ./myapp ./logger

# İndi myapp lokal logger-dən istifadə edir
go run ./myapp/
```

### Nümunə 4: replace direktivi ilə müqayisə

```go
// Köhnə üsul — replace direktivi (go.mod-da):
// myapp/go.mod

module github.com/company/myapp

go 1.22

require github.com/company/shared-lib v1.0.0

// PROBLEM: Bu sətri komit etməyin — CI-da fail olacaq
replace github.com/company/shared-lib => ../shared-lib
```

```
# Yeni üsul — go.work (yalnız lokal)
# go.work — .gitignore-a əlavə edin

go 1.22

use (
    ./myapp
    ./shared-lib
)

# go.mod-da replace LAZIM DEYİL
# go.work avtomatik lokal yolu prioritet edir
```

### Nümunə 5: Mövcud layihəyə workspace əlavə etmək

```bash
# Mövcud layihə var: api-server go.mod-da shared-lib-i GitHub-dan alır
# Siz shared-lib-ə yeni funksiya əlavə etdiniz, amma hələ push etmədiniz

# 1. Hər iki repo-nu yanlızca lokal klon edin
git clone https://github.com/company/api-server ~/projects/api-server
git clone https://github.com/company/shared-lib ~/projects/shared-lib

# 2. shared-lib-ə dəyişiklik edin
# ~/projects/shared-lib/cache/redis.go — yeni funksiya

# 3. Workspace yarat
cd ~/projects
go work init ./api-server ./shared-lib

# 4. api-server artıq lokal shared-lib-i istifadə edir
cd api-server
go run . # shared-lib-dən yeni funksiya işləyir

# 5. Test et, sonra shared-lib-i push et
cd ../shared-lib
git commit -am "Yeni cache funksiyası"
git push

# 6. api-server-in go.mod-unu yenilə
cd ../api-server
go get github.com/company/shared-lib@latest

# 7. Workspace-i söndür (artıq lazım deyil)
cd ~/projects
rm go.work go.work.sum
```

### Nümunə 6: Workspace ilə test

```bash
# Bütün workspace modullarını test et
go test ./...

# Xüsusi modulu test et
go test ./myapp/...
go test ./shared-lib/...

# Race detector ilə
go test -race ./...

# Benchmark
go test -bench=. ./shared-lib/utils/...
```

```go
// myapp/main_test.go — workspace-dəki shared-lib-i istifadə edir
package main

import (
    "testing"
    "github.com/company/shared-lib/utils"
)

func TestFormatOrderID(t *testing.T) {
    got := utils.FormatOrderID(42)
    want := "ORD-000042"
    if got != want {
        t.Errorf("got %q, want %q", got, want)
    }
}
```

### Nümunə 7: CI/CD — workspace-siz build

```yaml
# .github/workflows/build.yml

name: Build

on: [push, pull_request]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-go@v5
        with:
          go-version: '1.22'

      - name: Build (workspace olmadan)
        env:
          GOWORK: off  # workspace-i söndür
        run: go build ./...

      - name: Test
        env:
          GOWORK: off  # go.mod-dakı versiyaları istifadə edir
        run: go test ./...
```

### Nümunə 8: .gitignore

```gitignore
# .gitignore — workspace fayllarını komit etməyin

# Go workspace — lokal inkişaf üçün, komanda üzvlərinin yolları fərqlidir
go.work
go.work.sum

# Standart Go
*.test
*.prof
/bin/
/dist/
```

## Praktik Tapşırıqlar

**Tapşırıq 1: İki modul workspace**

`api` (HTTP server) və `domain` (business logic + entities) adlı iki modul yaradın. Workspace qurun. `domain` modulundakı struct-ları `api` modulunda istifadə edin. Push etmədən işləyən sistemi test edin.

```
~/myproject/
├── go.work
├── api/
│   ├── go.mod   (module example.com/api)
│   └── main.go  (imports example.com/domain)
└── domain/
    ├── go.mod   (module example.com/domain)
    └── order.go (Order struct, service funksiyaları)
```

**Tapşırıq 2: Mövcud kitabxananı fork etmək**

Hər hansı açıq mənbə Go kitabxanasını (məs: `github.com/google/uuid`) fork edin. Lokal dəyişiklik edin. Workspace vasitəsilə öz kodunuzda lokal fork-u istifadə edin. CI-da `GOWORK=off` ilə orijinal kitabxananın istifadəsini yoxlayın.

**Tapşırıq 3: Microservice workspace**

3 microservice: `user-svc`, `order-svc`, `gateway`. Hamısı eyni `proto` modulundan protobuf tipləri istifadə edir. Workspace qurun. `proto`-ya yeni field əlavə edin — bütün servislərin dəyişikliyi görüb compile olduğunu yoxlayın.

## Ətraflı Qeydlər

**go work edit — workspace-i proqramatik dəyişmək:**

```bash
# Modul əlavə et
go work edit -use ./newmodule

# Modul sil
go work edit -dropuse ./oldmodule

# go.work-u formatlа
go work edit -fmt
```

**Workspace və vendor:**

```bash
# Workspace ilə vendor eyni anda istifadə etmək mümkün deyil
# go work vendor — workspace-dəki bütün modulların dependency-lərini vendor/ qovluğuna köçürür
go work vendor
```

**go.work versiya məhdudiyyəti:**

```
# go.work-dakı "go" direktivi minimum Go versiyasını göstərir
go 1.22  # bütün workspace modulları bu versiya ilə uyğun olmalıdır
```

## Əlaqəli Mövzular

- `14-packages-and-modules` — go.mod, go.sum əsasları
- `22-init-and-modules` — module init, import yolları
- `../backend/18-project-structure` — mono-repo vs multi-repo qərarı
- `../advanced/23-docker-and-deploy` — workspace ilə Docker build
- `../advanced/26-microservices` — servislərarası paylaşılan kod
