package main

import "fmt"

// ===============================================
// MOCKING VE TESTIFY - IRELILEMIS TEST
// ===============================================

// Go-da mock yaratmaq ucun interface istifade olunur
// Testify - en populyar test kutubxanasi
// go get github.com/stretchr/testify

func main() {
	fmt.Println("Bu fayl test numunelerini gosterir")
	fmt.Println("Kodlari _test.go faylina yazin ve 'go test' ile isledin")

	kodlar := `
// ==========================================
// FAYL: service.go
// ==========================================
package main

// -------------------------------------------
// 1. Interface ile test edilebilen kod
// -------------------------------------------
// Real implementasiya yerine interface istifade edin
// Belelikle testde mock-laya bilersiz

type EmailGonderici interface {
    Gonder(kime, movzu, metn string) error
}

type IstifadeciRepo interface {
    TapID(id int) (*Istifadeci, error)
    Saxla(ist *Istifadeci) error
    Sil(id int) error
}

type Istifadeci struct {
    ID    int
    Ad    string
    Email string
}

// Service - interface-lerden asilidir, konkret tiplerden deyil
type IstifadeciService struct {
    repo  IstifadeciRepo
    email EmailGonderici
}

func NewIstifadeciService(repo IstifadeciRepo, email EmailGonderici) *IstifadeciService {
    return &IstifadeciService{repo: repo, email: email}
}

func (s *IstifadeciService) Qeydiyyat(ad, emailAddr string) (*Istifadeci, error) {
    ist := &Istifadeci{Ad: ad, Email: emailAddr}

    if err := s.repo.Saxla(ist); err != nil {
        return nil, fmt.Errorf("saxlama xetasi: %w", err)
    }

    err := s.email.Gonder(emailAddr, "Xos geldiniz!", "Salam "+ad)
    if err != nil {
        // Email gonderilmese de qeydiyyat ugurludur
        log.Printf("Email gondermek mumkun olmadi: %v", err)
    }

    return ist, nil
}

// ==========================================
// FAYL: service_test.go
// ==========================================
package main

import (
    "errors"
    "testing"

    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/mock"
    "github.com/stretchr/testify/require"
    "github.com/stretchr/testify/suite"
)

// -------------------------------------------
// 2. El ile Mock yaratmaq (interface ile)
// -------------------------------------------
type MockRepo struct {
    saxlananlar []*Istifadeci
    xeta        error
}

func (m *MockRepo) TapID(id int) (*Istifadeci, error) {
    for _, ist := range m.saxlananlar {
        if ist.ID == id {
            return ist, nil
        }
    }
    return nil, errors.New("tapilmadi")
}

func (m *MockRepo) Saxla(ist *Istifadeci) error {
    if m.xeta != nil {
        return m.xeta
    }
    m.saxlananlar = append(m.saxlananlar, ist)
    return nil
}

func (m *MockRepo) Sil(id int) error { return nil }

type MockEmail struct {
    gonderilenler []string
}

func (m *MockEmail) Gonder(kime, movzu, metn string) error {
    m.gonderilenler = append(m.gonderilenler, kime)
    return nil
}

func TestQeydiyyat_ElileMock(t *testing.T) {
    repo := &MockRepo{}
    email := &MockEmail{}
    service := NewIstifadeciService(repo, email)

    ist, err := service.Qeydiyyat("Orkhan", "orkhan@mail.az")

    if err != nil {
        t.Fatalf("Gozlenilmeyen xeta: %v", err)
    }
    if ist.Ad != "Orkhan" {
        t.Errorf("Ad = %s; gozlenen Orkhan", ist.Ad)
    }
    if len(email.gonderilenler) != 1 {
        t.Errorf("Email sayi = %d; gozlenen 1", len(email.gonderilenler))
    }
}

// -------------------------------------------
// 3. Testify/assert - Daha temiz assertions
// -------------------------------------------
func TestQeydiyyat_Assert(t *testing.T) {
    repo := &MockRepo{}
    email := &MockEmail{}
    service := NewIstifadeciService(repo, email)

    ist, err := service.Qeydiyyat("Orkhan", "orkhan@mail.az")

    // assert - ugursuz olsa test davam edir
    assert.NoError(t, err)
    assert.NotNil(t, ist)
    assert.Equal(t, "Orkhan", ist.Ad)
    assert.Equal(t, "orkhan@mail.az", ist.Email)
    assert.Len(t, email.gonderilenler, 1)

    // require - ugursuz olsa test DAYANIR
    require.NoError(t, err)
    require.NotNil(t, ist)
}

// -------------------------------------------
// 4. Testify/mock - Avtomatik mock
// -------------------------------------------
type TestifyMockRepo struct {
    mock.Mock
}

func (m *TestifyMockRepo) TapID(id int) (*Istifadeci, error) {
    args := m.Called(id) // cagirildigini qeyd et
    if args.Get(0) == nil {
        return nil, args.Error(1)
    }
    return args.Get(0).(*Istifadeci), args.Error(1)
}

func (m *TestifyMockRepo) Saxla(ist *Istifadeci) error {
    args := m.Called(ist)
    return args.Error(0)
}

func (m *TestifyMockRepo) Sil(id int) error {
    args := m.Called(id)
    return args.Error(0)
}

func TestQeydiyyat_TestifyMock(t *testing.T) {
    mockRepo := new(TestifyMockRepo)
    mockEmail := &MockEmail{}

    // Gozlentileri qurmaq
    mockRepo.On("Saxla", mock.AnythingOfType("*main.Istifadeci")).Return(nil)

    service := NewIstifadeciService(mockRepo, mockEmail)
    ist, err := service.Qeydiyyat("Orkhan", "orkhan@mail.az")

    assert.NoError(t, err)
    assert.Equal(t, "Orkhan", ist.Ad)

    // Gozlentilerin yerine yetirildiyini yoxla
    mockRepo.AssertExpectations(t)
    mockRepo.AssertCalled(t, "Saxla", mock.AnythingOfType("*main.Istifadeci"))
    mockRepo.AssertNumberOfCalls(t, "Saxla", 1)
}

// -------------------------------------------
// 5. Testify/suite - Test paketi
// -------------------------------------------
type IstifadeciServiceSuite struct {
    suite.Suite
    service *IstifadeciService
    repo    *MockRepo
    email   *MockEmail
}

// Her testden evvel isleyir
func (s *IstifadeciServiceSuite) SetupTest() {
    s.repo = &MockRepo{}
    s.email = &MockEmail{}
    s.service = NewIstifadeciService(s.repo, s.email)
}

func (s *IstifadeciServiceSuite) TestQeydiyyatUgurlu() {
    ist, err := s.service.Qeydiyyat("Orkhan", "orkhan@mail.az")
    s.NoError(err)
    s.Equal("Orkhan", ist.Ad)
}

func (s *IstifadeciServiceSuite) TestQeydiyyatXeta() {
    s.repo.xeta = errors.New("DB xetasi")
    _, err := s.service.Qeydiyyat("Test", "test@mail.az")
    s.Error(err)
    s.Contains(err.Error(), "saxlama xetasi")
}

func TestIstifadeciServiceSuite(t *testing.T) {
    suite.Run(t, new(IstifadeciServiceSuite))
}

// -------------------------------------------
// 6. httptest - HTTP handler test etmek
// -------------------------------------------
import (
    "net/http"
    "net/http/httptest"
)

func TestSalamHandler(t *testing.T) {
    // Saxta request yarat
    req, err := http.NewRequest("GET", "/salam?ad=Orkhan", nil)
    require.NoError(t, err)

    // Saxta response recorder yarat
    rr := httptest.NewRecorder()

    // Handler-i cagir
    handler := http.HandlerFunc(salamHandler)
    handler.ServeHTTP(rr, req)

    // Yoxla
    assert.Equal(t, http.StatusOK, rr.Code)
    assert.Contains(t, rr.Body.String(), "Orkhan")
}

// Tam test server
func TestAPI(t *testing.T) {
    // Saxta server yarat
    ts := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        w.WriteHeader(http.StatusOK)
        w.Write([]byte(` + "`" + `{"status":"ok"}` + "`" + `))
    }))
    defer ts.Close()

    // Saxta server-e sorgu gonder
    resp, err := http.Get(ts.URL)
    require.NoError(t, err)
    assert.Equal(t, 200, resp.StatusCode)
}
`

	fmt.Println(kodlar)

	// TOVSIYELER:
	// - Interface istifade edin ki asililiqlarinizi mock-laya bilesiniz
	// - assert (davam edir) vs require (dayandirir) ferqini bilin
	// - Table-driven testler + testify = en yaxsi kombinasiya
	// - httptest paketi HTTP handler testleri ucun idealdır
}
