# Mocking və Testify (Senior)

## İcmal

Go-da test etmə filosofiyası PHP-nin PHPUnit/Mockery-sindən fərqlidir: Go interface-based mocking istifadə edir, magic method-lar yoxdur. `testify` — Go-nun ən populyar test kitabxanasıdır (assert, mock, suite). `testify/mock` avtomatik mock generasiyası, `httptest` HTTP handler-lərin test edilməsi üçün standartdır. Düzgün test arxitekturası interface-lərdən asılıdır — konkret tip-dən yox.

## Niyə Vacibdir

- **Testable kod** — interface olmadan mock mümkün deyil; bu dizayn qərarlarını yaxşılaşdırır
- **testify/assert** standart `testing` paketindən daha aydın xəta mesajları verir
- **testify/mock** — DB, email, cache kimi xarici asılıqlıqları izolə edir
- **httptest** — real HTTP server başlatmadan handler-ləri test etmək
- **PHP Mockery ilə fərq** — Go-da mock class avtomatik deyil, interface-i implement etmək lazımdır

## Əsas Anlayışlar

### Interface-based Mocking Prinsipi

Go-da mock üçün pre-requisite: dependency interface kimi qəbul edilməlidir:

```go
// Testable — interface qəbul edir
type UserService struct {
    repo  UserRepository  // interface — mock oluna bilər
    email EmailSender     // interface — mock oluna bilər
}

// Test edilə bilməz — konkret tip
type UserService struct {
    repo  *PostgresUserRepo // konkret — mock olmur
    email *SMTPSender       // konkret — mock olmur
}
```

**PHP Mockery ilə müqayisə:**

```php
// PHP Mockery — class-ı birbaşa mock edə bilər
$mock = Mockery::mock(UserRepository::class);
$mock->shouldReceive('find')->andReturn($user);
```

```go
// Go — interface lazımdır, sonra mock yaranır
type UserRepository interface {
    FindByID(id int) (*User, error)
}

// Manual mock
type MockUserRepo struct {
    user *User
    err  error
}
func (m *MockUserRepo) FindByID(id int) (*User, error) {
    return m.user, m.err
}
```

### testify/assert vs require

```go
assert.Equal(t, expected, actual)  // xəta olsa test DAVAM edir
require.Equal(t, expected, actual) // xəta olsa test DAYANIR
```

**Nə vaxt require:** test davam etsə mənasız olacaqsa — məs., nil pointer yoxlaması.

### testify/mock — Gözləntilər

```go
type MockRepo struct {
    mock.Mock
}

func (m *MockRepo) FindByID(id int) (*User, error) {
    args := m.Called(id) // çağırılmanı qeyd et
    return args.Get(0).(*User), args.Error(1)
}

// Test-də:
mockRepo := new(MockRepo)
mockRepo.On("FindByID", 42).Return(&User{Name: "Orkhan"}, nil)
// gözlənti: FindByID(42) çağırılacaq, &User{...} qaytaracaq

mockRepo.AssertExpectations(t) // bütün .On() çağırıldımı?
mockRepo.AssertCalled(t, "FindByID", 42)
mockRepo.AssertNumberOfCalls(t, "FindByID", 1)
```

### testify/suite — Test Paketi

PHP-nin `setUp`/`tearDown`-una bənzər:

```go
type ServiceTestSuite struct {
    suite.Suite
    service *UserService
    repo    *MockRepo
}

func (s *ServiceTestSuite) SetupTest() { // hər test-dən əvvəl
    s.repo = new(MockRepo)
    s.service = NewUserService(s.repo)
}

func (s *ServiceTestSuite) TestCreateUser() {
    s.repo.On("Save", mock.Anything).Return(nil)
    _, err := s.service.Create("Orkhan", "orkhan@example.com")
    s.NoError(err)
}

func TestServiceSuite(t *testing.T) {
    suite.Run(t, new(ServiceTestSuite))
}
```

### httptest Paketi

```go
// Recorder — HTTP response-u yaddaşda saxlayır
rr := httptest.NewRecorder()
req := httptest.NewRequest("GET", "/users/1", nil)
handler.ServeHTTP(rr, req)
assert.Equal(t, http.StatusOK, rr.Code)
assert.JSONEq(t, `{"id":1}`, rr.Body.String())

// Tam test server
ts := httptest.NewServer(handler)
defer ts.Close()
resp, _ := http.Get(ts.URL + "/api/users")
```

### mock.Anything — Çevik Uyğunluq

```go
mockRepo.On("Save", mock.Anything).Return(nil)            // hər argument
mockRepo.On("Save", mock.AnythingOfType("*User")).Return(nil) // müəyyən tip
mockRepo.On("FindByID", mock.MatchedBy(func(id int) bool {
    return id > 0
})).Return(user, nil) // xüsusi şərt
```

## Praktik Baxış

### Arquitektura: Testable Kod Yazmaq

```
Handler → Service → Repository → DB
   ↓          ↓          ↓
Mock-lamaq üçün hər qatın interface-i olmalıdır
```

```go
// domain/interfaces.go
type UserRepository interface {
    FindByID(ctx context.Context, id int) (*User, error)
    Save(ctx context.Context, user *User) error
}

type EmailSender interface {
    Send(to, subject, body string) error
}

// adapter/repository/postgres/user.go — real implementasiya
// test üçün mocklar interface-i implement edir
```

### Table-driven Test + Testify

```go
func TestCreateUser(t *testing.T) {
    tests := []struct {
        name     string
        input    CreateUserInput
        repoErr  error
        wantErr  bool
        wantCode string
    }{
        {"uğurlu", CreateUserInput{"Orkhan", "orkhan@example.com"}, nil, false, ""},
        {"repo xətası", CreateUserInput{"Ali", "ali@example.com"}, errors.New("db error"), true, "db_error"},
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            mockRepo := new(MockUserRepo)
            mockRepo.On("Save", mock.Anything).Return(tt.repoErr)
            
            svc := NewUserService(mockRepo)
            _, err := svc.Create(tt.input)
            
            if tt.wantErr {
                assert.Error(t, err)
            } else {
                assert.NoError(t, err)
            }
            mockRepo.AssertExpectations(t)
        })
    }
}
```

### gomock vs testify/mock

| Xüsusiyyət | testify/mock | gomock |
|------------|-------------|--------|
| Setup | Əl ilə | `mockgen` code gen |
| Sintaksis | Fluent `.On()` | `EXPECT().Method()` |
| Çeviklik | Yüksək | Orta |
| Compile-time | Yox | Bəli |
| Populyarlıq | Çox | Orta |

### Trade-off-lar

| Yanaşma | Üstünlük | Çatışmazlıq |
|---------|----------|-------------|
| El ilə mock | Sadə, şəffaf | Çox kod, hər metod üçün |
| testify/mock | Gözlənti yoxlaması | Qurulum tələb edir |
| gomock + mockgen | Avtomatik generasiya | Dependency, murəkkəb |
| Fake struct | Sadə, embedded state | Çevik deyil |
| In-memory DB | Gerçəyə yaxın | Daha yavaş |

### Anti-pattern-lər

```go
// Anti-pattern 1: Konkret tip injection — test olmur
func NewService() *UserService {
    return &UserService{
        repo: &PostgresRepo{db: connectDB()}, // mock olmur!
    }
}

// Anti-pattern 2: Qeyri-deterministic mock
mockRepo.On("FindByID", mock.Anything).Return(randomUser()) // hər dəfə fərqli

// Anti-pattern 3: Mock-u test arasında paylaşmaq
var globalMock = new(MockRepo) // paralel testlərdə data race!

// Anti-pattern 4: args.Get(0).(*Type) — panic riski
func (m *MockRepo) Find(id int) (*User, error) {
    args := m.Called(id)
    return args.Get(0).(*User), args.Error(1) // nil return olsa panic!
}
// Düzgün:
if args.Get(0) == nil {
    return nil, args.Error(1)
}
return args.Get(0).(*User), args.Error(1)

// Anti-pattern 5: testify/assert mock-da deyil, require olmalıdır
assert.NoError(t, err) // əgər err != nil → test davam edir, nil pointer sonra panic!
require.NoError(t, err) // dərhal dayanır — daha güvənli
```

## Nümunələr

### Nümunə 1: El ilə Mock + testify/assert

```go
package main_test

import (
    "errors"
    "testing"

    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

// --- Interface-lər ---
type User struct {
    ID    int
    Name  string
    Email string
}

type UserRepository interface {
    FindByID(id int) (*User, error)
    Save(user *User) error
}

type EmailSender interface {
    Send(to, subject, body string) error
}

// --- Service ---
type UserService struct {
    repo  UserRepository
    email EmailSender
}

func NewUserService(r UserRepository, e EmailSender) *UserService {
    return &UserService{repo: r, email: e}
}

func (s *UserService) Register(name, email string) (*User, error) {
    user := &User{Name: name, Email: email}
    if err := s.repo.Save(user); err != nil {
        return nil, err
    }
    s.email.Send(email, "Xoş gəldiniz!", "Salam "+name)
    return user, nil
}

// --- El ilə Mock-lar ---
type MockUserRepo struct {
    savedUsers []*User
    returnErr  error
}

func (m *MockUserRepo) FindByID(id int) (*User, error) {
    for _, u := range m.savedUsers {
        if u.ID == id {
            return u, nil
        }
    }
    return nil, errors.New("tapılmadı")
}

func (m *MockUserRepo) Save(user *User) error {
    if m.returnErr != nil {
        return m.returnErr
    }
    user.ID = len(m.savedUsers) + 1
    m.savedUsers = append(m.savedUsers, user)
    return nil
}

type MockEmailSender struct {
    sentEmails []string
    returnErr  error
}

func (m *MockEmailSender) Send(to, subject, body string) error {
    m.sentEmails = append(m.sentEmails, to)
    return m.returnErr
}

// --- Test-lər ---
func TestRegister_Success(t *testing.T) {
    repo := &MockUserRepo{}
    email := &MockEmailSender{}
    svc := NewUserService(repo, email)

    user, err := svc.Register("Orkhan", "orkhan@example.com")

    require.NoError(t, err)
    require.NotNil(t, user)
    assert.Equal(t, "Orkhan", user.Name)
    assert.Equal(t, "orkhan@example.com", user.Email)
    assert.Greater(t, user.ID, 0)
    assert.Len(t, email.sentEmails, 1)
    assert.Equal(t, "orkhan@example.com", email.sentEmails[0])
}

func TestRegister_RepoError(t *testing.T) {
    repo := &MockUserRepo{returnErr: errors.New("db connection failed")}
    email := &MockEmailSender{}
    svc := NewUserService(repo, email)

    user, err := svc.Register("Orkhan", "orkhan@example.com")

    assert.Error(t, err)
    assert.Nil(t, user)
    assert.Len(t, email.sentEmails, 0) // email göndərilmir
}
```

### Nümunə 2: testify/mock ilə Avtomatik Mock

```go
package main_test

import (
    "testing"

    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/mock"
    "github.com/stretchr/testify/require"
)

// --- testify/mock ilə Mock ---
type MockUserRepoTestify struct {
    mock.Mock
}

func (m *MockUserRepoTestify) FindByID(id int) (*User, error) {
    args := m.Called(id)
    if args.Get(0) == nil {
        return nil, args.Error(1)
    }
    return args.Get(0).(*User), args.Error(1)
}

func (m *MockUserRepoTestify) Save(user *User) error {
    args := m.Called(user)
    return args.Error(0)
}

func TestRegister_WithTestifyMock(t *testing.T) {
    mockRepo := new(MockUserRepoTestify)
    mockEmail := &MockEmailSender{}

    // Gözləntilər
    mockRepo.On("Save", mock.AnythingOfType("*main_test.User")).
        Run(func(args mock.Arguments) {
            // Save çağırıldıqda user-ə ID ver
            args.Get(0).(*User).ID = 99
        }).
        Return(nil)

    svc := NewUserService(mockRepo, mockEmail)
    user, err := svc.Register("Vüsal", "vusal@example.com")

    require.NoError(t, err)
    assert.Equal(t, 99, user.ID)

    // Bütün gözləntilər yerinə yetirildi?
    mockRepo.AssertExpectations(t)
    mockRepo.AssertCalled(t, "Save", mock.AnythingOfType("*main_test.User"))
    mockRepo.AssertNumberOfCalls(t, "Save", 1)
}

// Table-driven test + testify/mock
func TestRegister_TableDriven(t *testing.T) {
    tests := []struct {
        name      string
        inputName string
        inputEmail string
        saveError error
        wantError bool
    }{
        {"uğurlu", "Orkhan", "orkhan@example.com", nil, false},
        {"db xətası", "Ali", "ali@example.com", errors.New("db error"), true},
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            mockRepo := new(MockUserRepoTestify)
            mockEmail := &MockEmailSender{}

            mockRepo.On("Save", mock.Anything).Return(tt.saveError)

            svc := NewUserService(mockRepo, mockEmail)
            _, err := svc.Register(tt.inputName, tt.inputEmail)

            if tt.wantError {
                assert.Error(t, err)
            } else {
                assert.NoError(t, err)
            }
            mockRepo.AssertExpectations(t)
        })
    }
}
```

### Nümunə 3: httptest ilə HTTP Handler Test

```go
package main_test

import (
    "encoding/json"
    "net/http"
    "net/http/httptest"
    "testing"

    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

// Handler
func UserHandler(svc *UserService) http.HandlerFunc {
    return func(w http.ResponseWriter, r *http.Request) {
        id := 1 // real halda URL-dən alınır
        user, err := svc.repo.FindByID(id)
        if err != nil {
            http.Error(w, `{"error":"not_found"}`, http.StatusNotFound)
            return
        }

        w.Header().Set("Content-Type", "application/json")
        json.NewEncoder(w).Encode(user)
    }
}

func TestUserHandler_Found(t *testing.T) {
    mockRepo := new(MockUserRepoTestify)
    expectedUser := &User{ID: 1, Name: "Orkhan", Email: "orkhan@example.com"}
    mockRepo.On("FindByID", 1).Return(expectedUser, nil)

    svc := &UserService{repo: mockRepo}
    handler := UserHandler(svc)

    req := httptest.NewRequest("GET", "/users/1", nil)
    rr := httptest.NewRecorder()

    handler.ServeHTTP(rr, req)

    assert.Equal(t, http.StatusOK, rr.Code)
    assert.Equal(t, "application/json", rr.Header().Get("Content-Type"))

    var resp User
    require.NoError(t, json.NewDecoder(rr.Body).Decode(&resp))
    assert.Equal(t, "Orkhan", resp.Name)

    mockRepo.AssertExpectations(t)
}

func TestUserHandler_NotFound(t *testing.T) {
    mockRepo := new(MockUserRepoTestify)
    mockRepo.On("FindByID", 1).Return(nil, errors.New("not found"))

    svc := &UserService{repo: mockRepo}
    handler := UserHandler(svc)

    req := httptest.NewRequest("GET", "/users/1", nil)
    rr := httptest.NewRecorder()

    handler.ServeHTTP(rr, req)

    assert.Equal(t, http.StatusNotFound, rr.Code)
    mockRepo.AssertExpectations(t)
}
```

### Nümunə 4: testify/suite

```go
package main_test

import (
    "testing"

    "github.com/stretchr/testify/suite"
)

type UserServiceSuite struct {
    suite.Suite
    svc   *UserService
    repo  *MockUserRepoTestify
    email *MockEmailSender
}

// Hər testdən əvvəl çağırılır
func (s *UserServiceSuite) SetupTest() {
    s.repo = new(MockUserRepoTestify)
    s.email = &MockEmailSender{}
    s.svc = NewUserService(s.repo, s.email)
}

// Hər testdən sonra çağırılır
func (s *UserServiceSuite) TearDownTest() {
    s.repo.AssertExpectations(s.T())
}

func (s *UserServiceSuite) TestRegisterSuccess() {
    s.repo.On("Save", mock.Anything).Return(nil)
    user, err := s.svc.Register("Orkhan", "orkhan@example.com")
    s.NoError(err)
    s.Equal("Orkhan", user.Name)
    s.Len(s.email.sentEmails, 1)
}

func (s *UserServiceSuite) TestRegisterDBError() {
    s.repo.On("Save", mock.Anything).Return(errors.New("db error"))
    _, err := s.svc.Register("Test", "test@example.com")
    s.Error(err)
    s.Len(s.email.sentEmails, 0)
}

// Suite-i çalışdır
func TestUserServiceSuite(t *testing.T) {
    suite.Run(t, new(UserServiceSuite))
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Payment Service Test:**
`PaymentGateway` interface yazın: `Charge(amount float64, card string) (transactionID string, error)`. Mock yazın. Uğurlu ödəniş, rədd olunan kart, network xətası hallarını test edin.

**Tapşırıq 2 — Cache Mock:**
`Cache` interface: `Get(key string) (string, bool)`, `Set(key, value string, ttl time.Duration)`. Mock ilə service-in cache-dən istifadəsini test edin. Cache miss → DB, cache hit → DB çağırılmasın.

**Tapşırıq 3 — gomock:**
`mockgen` aləti ilə `UserRepository` interface üçün mock generate edin. `go generate ./...` pipeline-a əlavə edin.

**Tapşırıq 4 — Integration Test:**
`testify/suite` + `httptest.NewServer` ilə tam integration test yazın. Real HTTP sorğuları göndərin, mock middleware ilə auth test edin.

## Əlaqəli Mövzular

- [17-interfaces](17-interfaces.md) — Interface əsasları
- [24-testing](24-testing.md) — Go test əsasları
- [36-httptest](36-httptest.md) — httptest dərin analiz
- [42-struct-advanced](42-struct-advanced.md) — Struct composition
- [55-repository-pattern](55-repository-pattern.md) — Repository interface mocking
- [64-dependency-injection](64-dependency-injection.md) — DI ilə test
