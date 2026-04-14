package main

import "fmt"

// ===============================================
// DEPENDENCY INJECTION (DI) - ASILILIQ ENJEKSIYASI
// ===============================================

// Dependency Injection - bir obyektin asili oldugu
// diger obyektleri xaricdən almasıdır.
// Bu, kodun test edilmesini ve deyisdirilmesini asanlasdirir.

// -------------------------------------------
// 1. Constructor Injection (Konstruktor Enjeksiya)
// -------------------------------------------

// Evvelce interface tanimlayaq - DI-nin esasi budur
// Konkret implementasiyaya deyil, interfeyse asili oluruq

type Logger interface {
	Log(message string)
}

type ConsoleLogger struct{}

func (c *ConsoleLogger) Log(message string) {
	fmt.Println("[LOG]:", message)
}

type FileLogger struct {
	FileName string
}

func (f *FileLogger) Log(message string) {
	fmt.Printf("[FILE %s]: %s\n", f.FileName, message)
}

// -------------------------------------------
// 2. Interface-based DI (Interfeys esasli DI)
// -------------------------------------------

// Repository interfeysi - verilənlər bazası əməliyyatları
type UserRepository interface {
	FindByID(id int) (*User, error)
	Save(user *User) error
	Delete(id int) error
}

type User struct {
	ID    int
	Name  string
	Email string
}

// -------------------------------------------
// 3. Konkret Repository implementasiyasi
// -------------------------------------------

// In-memory repository (test ucun ideal)
type InMemoryUserRepo struct {
	users map[int]*User
}

func NewInMemoryUserRepo() *InMemoryUserRepo {
	return &InMemoryUserRepo{
		users: make(map[int]*User),
	}
}

func (r *InMemoryUserRepo) FindByID(id int) (*User, error) {
	user, ok := r.users[id]
	if !ok {
		return nil, fmt.Errorf("istifadeci tapilmadi: %d", id)
	}
	return user, nil
}

func (r *InMemoryUserRepo) Save(user *User) error {
	r.users[user.ID] = user
	return nil
}

func (r *InMemoryUserRepo) Delete(id int) error {
	delete(r.users, id)
	return nil
}

// PostgreSQL repository (real layihe ucun)
type PostgresUserRepo struct {
	connectionString string
}

func NewPostgresUserRepo(connStr string) *PostgresUserRepo {
	return &PostgresUserRepo{connectionString: connStr}
}

func (r *PostgresUserRepo) FindByID(id int) (*User, error) {
	// Real layihede burada SQL sorgusu olacaq:
	// db.QueryRow("SELECT id, name, email FROM users WHERE id=$1", id)
	fmt.Printf("[POSTGRES] FindByID(%d) cagirildi\n", id)
	return &User{ID: id, Name: "DB User", Email: "db@test.com"}, nil
}

func (r *PostgresUserRepo) Save(user *User) error {
	fmt.Printf("[POSTGRES] Save(%v) cagirildi\n", user)
	return nil
}

func (r *PostgresUserRepo) Delete(id int) error {
	fmt.Printf("[POSTGRES] Delete(%d) cagirildi\n", id)
	return nil
}

// -------------------------------------------
// 4. Service Layer - Constructor Injection
// -------------------------------------------

// UserService - is mentiqi burada yerlesir
// Repository VE Logger-i xaricdən alir (DI)
type UserService struct {
	repo   UserRepository
	logger Logger
}

// Constructor Injection - asililiqlar konstruktorda verilir
func NewUserService(repo UserRepository, logger Logger) *UserService {
	return &UserService{
		repo:   repo,
		logger: logger,
	}
}

func (s *UserService) GetUser(id int) (*User, error) {
	s.logger.Log(fmt.Sprintf("Istifadeci axtarilir: %d", id))
	user, err := s.repo.FindByID(id)
	if err != nil {
		s.logger.Log(fmt.Sprintf("XETA: %v", err))
		return nil, err
	}
	s.logger.Log(fmt.Sprintf("Istifadeci tapildi: %s", user.Name))
	return user, nil
}

func (s *UserService) CreateUser(id int, name, email string) error {
	s.logger.Log(fmt.Sprintf("Yeni istifadeci yaradilir: %s", name))
	user := &User{ID: id, Name: name, Email: email}
	return s.repo.Save(user)
}

func (s *UserService) RemoveUser(id int) error {
	s.logger.Log(fmt.Sprintf("Istifadeci silinir: %d", id))
	return s.repo.Delete(id)
}

// -------------------------------------------
// 5. DI Container Pattern (Sade versiya)
// -------------------------------------------

// DI Container - butun asililiqları bir yerde idarə edir
type Container struct {
	logger Logger
	repo   UserRepository
}

func NewContainer(env string) *Container {
	c := &Container{}

	// Muhite gore muxtelif implementasiyalar
	switch env {
	case "test":
		c.logger = &ConsoleLogger{}
		c.repo = NewInMemoryUserRepo()
	case "production":
		c.logger = &FileLogger{FileName: "app.log"}
		c.repo = NewPostgresUserRepo("postgres://localhost/mydb")
	default:
		c.logger = &ConsoleLogger{}
		c.repo = NewInMemoryUserRepo()
	}

	return c
}

func (c *Container) UserService() *UserService {
	return NewUserService(c.repo, c.logger)
}

// -------------------------------------------
// 6. Wire Framework haqqinda (Google Wire)
// -------------------------------------------

// Google Wire - compile-time dependency injection framework
// go install github.com/google/wire/cmd/wire@latest
//
// Wire ile islemek ucun 2 addim lazimdir:
//
// 1. Provider funksiyalar yazmaq (artiq yazmisiq):
//    func NewUserService(repo UserRepository, logger Logger) *UserService
//    func NewInMemoryUserRepo() *InMemoryUserRepo
//
// 2. wire.go faylinda injector yazmaq:
//
// //go:build wireinject
//
// package main
//
// import "github.com/google/wire"
//
// func InitializeUserService() *UserService {
//     wire.Build(
//         NewInMemoryUserRepo,
//         wire.Bind(new(UserRepository), new(*InMemoryUserRepo)),
//         NewConsoleLogger,
//         wire.Bind(new(Logger), new(*ConsoleLogger)),
//         NewUserService,
//     )
//     return nil
// }
//
// Sonra `wire` komandasini isledirsiz ve
// avtomatik wire_gen.go yaranir.

// -------------------------------------------
// 7. Funksional DI (Option Pattern ile)
// -------------------------------------------

type ServerConfig struct {
	Host   string
	Port   int
	Logger Logger
}

type Option func(*ServerConfig)

func WithHost(host string) Option {
	return func(c *ServerConfig) {
		c.Host = host
	}
}

func WithPort(port int) Option {
	return func(c *ServerConfig) {
		c.Port = port
	}
}

func WithLogger(logger Logger) Option {
	return func(c *ServerConfig) {
		c.Logger = logger
	}
}

func NewServer(opts ...Option) *ServerConfig {
	// Default deyerler
	cfg := &ServerConfig{
		Host:   "localhost",
		Port:   8080,
		Logger: &ConsoleLogger{},
	}
	for _, opt := range opts {
		opt(cfg)
	}
	return cfg
}

// -------------------------------------------
// 8. Mock ile Test etme ornegi
// -------------------------------------------

// Test zamani mock repository istifade edirik
type MockUserRepo struct {
	Users    map[int]*User
	SaveErr  error
	FindErr  error
}

func (m *MockUserRepo) FindByID(id int) (*User, error) {
	if m.FindErr != nil {
		return nil, m.FindErr
	}
	user, ok := m.Users[id]
	if !ok {
		return nil, fmt.Errorf("tapilmadi")
	}
	return user, nil
}

func (m *MockUserRepo) Save(user *User) error {
	if m.SaveErr != nil {
		return m.SaveErr
	}
	m.Users[user.ID] = user
	return nil
}

func (m *MockUserRepo) Delete(id int) error {
	delete(m.Users, id)
	return nil
}

func main() {

	// =====================
	// DI PRAKTIKI NUMUNE
	// =====================

	fmt.Println("=== Constructor Injection ===")

	// Test muhiti - InMemory repo ve Console logger
	repo := NewInMemoryUserRepo()
	logger := &ConsoleLogger{}
	service := NewUserService(repo, logger)

	// Istifadeci yarat
	service.CreateUser(1, "Orxan", "orxan@test.com")
	service.CreateUser(2, "Leyla", "leyla@test.com")

	// Istifadeci axtar
	user, err := service.GetUser(1)
	if err == nil {
		fmt.Printf("Tapildi: %+v\n", user)
	}

	// Movcud olmayan istifadeci
	_, err = service.GetUser(99)
	if err != nil {
		fmt.Println("Xeta:", err)
	}

	// -------------------------------------------
	fmt.Println("\n=== DI Container ===")

	// Container ile - muhite gore avtomatik secim
	testContainer := NewContainer("test")
	testService := testContainer.UserService()
	testService.CreateUser(10, "Test User", "test@test.com")

	prodContainer := NewContainer("production")
	prodService := prodContainer.UserService()
	prodService.GetUser(1)

	// -------------------------------------------
	fmt.Println("\n=== Option Pattern (Funksional DI) ===")

	server1 := NewServer()
	fmt.Printf("Default: %s:%d\n", server1.Host, server1.Port)

	server2 := NewServer(
		WithHost("0.0.0.0"),
		WithPort(9090),
		WithLogger(&FileLogger{FileName: "server.log"}),
	)
	fmt.Printf("Custom: %s:%d\n", server2.Host, server2.Port)
	server2.Logger.Log("Server basladildi")

	// -------------------------------------------
	fmt.Println("\n=== Mock ile Test ===")

	mockRepo := &MockUserRepo{
		Users: map[int]*User{
			1: {ID: 1, Name: "Mock User", Email: "mock@test.com"},
		},
	}
	mockService := NewUserService(mockRepo, &ConsoleLogger{})

	u, _ := mockService.GetUser(1)
	fmt.Printf("Mock-dan: %+v\n", u)

	// Xeta simulyasiyasi
	mockRepo.FindErr = fmt.Errorf("database connection lost")
	_, err = mockService.GetUser(1)
	fmt.Println("Mock xeta:", err)

	// -------------------------------------------
	fmt.Println("\n=== DI Ustunlukleri ===")
	fmt.Println(`
1. Test edilme asanligi - mock/stub istifade ede bilersiz
2. Loose coupling - komponentler bir-birinden asili deyil
3. Single Responsibility - her komponent oz isini gorur
4. Deyisdirile bilen - implementasiyanı asanliqla deyisdire bilersiz
5. Kodun oxunaqliligini artirir
`)
}
