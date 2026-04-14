package main

import (
	"fmt"
	"sync"
)

// ===============================================
// DIZAYN NAXISLARI (DESIGN PATTERNS)
// ===============================================

// Go-da en cox istifade olunan dizayn naxislari

// -------------------------------------------
// 1. SINGLETON - Yalniz bir instance
// -------------------------------------------
// Butun proqramda yalniz bir dene olacaq obyekt (meselen: config, DB connection)

type verilenisBazasi struct {
	baglanti string
}

var (
	dbInstance *verilenisBazasi
	dbOnce     sync.Once
)

func DBAlq() *verilenisBazasi {
	dbOnce.Do(func() {
		// Bu kod yalniz BIR DEFE isleyecek, nece goroutine cagirsanda
		dbInstance = &verilenisBazasi{baglanti: "postgres://localhost:5432"}
		fmt.Println("DB instance yaradildi")
	})
	return dbInstance
}

// -------------------------------------------
// 2. FACTORY - Obyekt yaratma
// -------------------------------------------
// Yaratma mentiqini bir yere yigmaq

type Bildirish interface {
	Gonder(mesaj string) error
}

type EmailBildirish struct{}

func (e *EmailBildirish) Gonder(mesaj string) error {
	fmt.Println("Email gonderildi:", mesaj)
	return nil
}

type SMSBildirish struct{}

func (s *SMSBildirish) Gonder(mesaj string) error {
	fmt.Println("SMS gonderildi:", mesaj)
	return nil
}

type PushBildirish struct{}

func (p *PushBildirish) Gonder(mesaj string) error {
	fmt.Println("Push gonderildi:", mesaj)
	return nil
}

func BildirshYarat(tip string) Bildirish {
	switch tip {
	case "email":
		return &EmailBildirish{}
	case "sms":
		return &SMSBildirish{}
	case "push":
		return &PushBildirish{}
	default:
		return nil
	}
}

// -------------------------------------------
// 3. BUILDER - Addim-addim yaratma
// -------------------------------------------
// Coxlu parametrli obyekt yaratmaq ucun

type ServerConfig struct {
	Host    string
	Port    int
	TLS     bool
	MaxConn int
	Timeout int
}

type ServerBuilder struct {
	config ServerConfig
}

func YeniServer() *ServerBuilder {
	return &ServerBuilder{
		config: ServerConfig{
			Host:    "localhost",
			Port:    8080,
			MaxConn: 100,
			Timeout: 30,
		},
	}
}

func (b *ServerBuilder) SetHost(host string) *ServerBuilder {
	b.config.Host = host
	return b // zencir (chaining) ucun ozunu qaytarir
}

func (b *ServerBuilder) SetPort(port int) *ServerBuilder {
	b.config.Port = port
	return b
}

func (b *ServerBuilder) TLSEnable() *ServerBuilder {
	b.config.TLS = true
	return b
}

func (b *ServerBuilder) SetMaxConn(n int) *ServerBuilder {
	b.config.MaxConn = n
	return b
}

func (b *ServerBuilder) Build() ServerConfig {
	return b.config
}

// -------------------------------------------
// 4. FUNCTIONAL OPTIONS - Go-ya xas naxis
// -------------------------------------------
// Builder-in Go uslubu - daha idiomatikdir

type Server struct {
	host    string
	port    int
	tls     bool
	maxConn int
}

type ServerOption func(*Server)

func WithHost(host string) ServerOption {
	return func(s *Server) { s.host = host }
}

func WithPort(port int) ServerOption {
	return func(s *Server) { s.port = port }
}

func WithTLS() ServerOption {
	return func(s *Server) { s.tls = true }
}

func WithMaxConn(n int) ServerOption {
	return func(s *Server) { s.maxConn = n }
}

func NewServer(opts ...ServerOption) *Server {
	s := &Server{
		host:    "localhost",
		port:    8080,
		maxConn: 100,
	}
	for _, opt := range opts {
		opt(s)
	}
	return s
}

// -------------------------------------------
// 5. OBSERVER - Hadise dinleyicisi
// -------------------------------------------
type HadiseDinleyici func(melumat string)

type HadiseSistemi struct {
	dinleyiciler map[string][]HadiseDinleyici
}

func YeniHadiseSistemi() *HadiseSistemi {
	return &HadiseSistemi{
		dinleyiciler: make(map[string][]HadiseDinleyici),
	}
}

func (h *HadiseSistemi) Dinle(hadise string, fn HadiseDinleyici) {
	h.dinleyiciler[hadise] = append(h.dinleyiciler[hadise], fn)
}

func (h *HadiseSistemi) Yay(hadise, melumat string) {
	for _, fn := range h.dinleyiciler[hadise] {
		fn(melumat)
	}
}

// -------------------------------------------
// 6. MIDDLEWARE / DECORATOR
// -------------------------------------------
type StringEmeliyyat func(string) string

func LogMiddleware(next StringEmeliyyat) StringEmeliyyat {
	return func(s string) string {
		fmt.Println("[LOG] Giris:", s)
		netice := next(s)
		fmt.Println("[LOG] Cixis:", netice)
		return netice
	}
}

func main() {

	// 1. Singleton
	db1 := DBAlq()
	db2 := DBAlq() // yeniden yaradilmayacaq
	fmt.Println("Eyni instance:", db1 == db2) // true
	fmt.Println()

	// 2. Factory
	email := BildirshYarat("email")
	email.Gonder("Salam!")
	sms := BildirshYarat("sms")
	sms.Gonder("Kod: 1234")
	fmt.Println()

	// 3. Builder
	config := YeniServer().
		SetHost("0.0.0.0").
		SetPort(443).
		TLSEnable().
		SetMaxConn(500).
		Build()
	fmt.Printf("Builder: %+v\n\n", config)

	// 4. Functional Options
	server := NewServer(
		WithHost("0.0.0.0"),
		WithPort(443),
		WithTLS(),
		WithMaxConn(500),
	)
	fmt.Printf("Functional Options: %+v\n\n", server)

	// 5. Observer
	sistem := YeniHadiseSistemi()
	sistem.Dinle("sifaris", func(m string) {
		fmt.Println("Email gonder:", m)
	})
	sistem.Dinle("sifaris", func(m string) {
		fmt.Println("SMS gonder:", m)
	})
	sistem.Yay("sifaris", "Yeni sifaris #123")
	fmt.Println()

	// 6. Middleware
	boyukHerf := func(s string) string {
		result := ""
		for _, c := range s {
			if c >= 'a' && c <= 'z' {
				result += string(c - 32)
			} else {
				result += string(c)
			}
		}
		return result
	}
	logluBoyuk := LogMiddleware(boyukHerf)
	logluBoyuk("salam dunya")
}
