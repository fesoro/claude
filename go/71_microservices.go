package main

import (
	"fmt"
)

// ===============================================
// MICROSERVICES (MIKRO XIDMETLER)
// ===============================================

// Microservices - boyuk sistemi kicik, musteqil xidmetlere bolmekdir.
// Her xidmet oz database-i, oz deploy-u, oz komandasi ola biler.
// Go bu arxitektura ucun cox uygun dildir - kicik binary, suretli startup.

func main() {
	fmt.Println("=== MICROSERVICES ===")

	// -------------------------------------------
	// 1. Service decomposition (xidmet ayrilmasi)
	// -------------------------------------------
	fmt.Println("\n--- 1. Service Decomposition ---")
	fmt.Println("Monoliti mikro xidmetlere nece bolmeli?")
	fmt.Println(`
  // Prinspler:
  // 1. Single Responsibility - her xidmet bir is gorur
  // 2. Domain-Driven Design - business domain-e gore bol
  // 3. Bounded Context - her xidmetin oz melumati, oz modeli var

  // Misal: E-ticaret sistemi
  //
  // Monolit:
  // ┌──────────────────────────────────┐
  // │ Istifadeciler + Sifarisler +     │
  // │ Odenis + Mehsullar + Bildiris   │
  // │        TEK DATABASE              │
  // └──────────────────────────────────┘
  //
  // Microservices:
  // ┌──────────┐ ┌──────────┐ ┌──────────┐
  // │ User     │ │ Order    │ │ Payment  │
  // │ Service  │ │ Service  │ │ Service  │
  // │ [UserDB] │ │ [OrderDB]│ │ [PayDB]  │
  // └──────────┘ └──────────┘ └──────────┘
  // ┌──────────┐ ┌──────────┐
  // │ Product  │ │ Notif.   │
  // │ Service  │ │ Service  │
  // │[ProdDB]  │ │ [Redis]  │
  // └──────────┘ └──────────┘

  // Ne vaxt bolmeli?
  // - Komanda boyuyende (her komanda oz xidmetini sahiblenir)
  // - Farkli olcekleme ehtiyaci olanda (odenis coxdur, bildiris azdir)
  // - Farkli texnologiya lazim olanda (ML xidmeti Python, API Go)
  //
  // Ne vaxt BOLMEMELISIZ?
  // - Kicik komanda (2-3 developer)
  // - Domenler arasinda sixis coxdur
  // - "Monolit pis, microservice yaxsi" dusuncesi ile`)

	// -------------------------------------------
	// 2. API Gateway pattern
	// -------------------------------------------
	fmt.Println("\n--- 2. API Gateway ---")
	fmt.Println("API Gateway - butun xarici request-lerin tek giris noqtesidir.")
	fmt.Println(`
  // API Gateway-in vezifeler:
  // - Request routing (hansi xidmete yonlendirmek)
  // - Authentication/Authorization
  // - Rate limiting
  // - Request/Response transformation
  // - Load balancing
  // - Caching

  // Sadə API Gateway numunesi
  import (
      "net/http"
      "net/http/httputil"
      "net/url"
  )

  type APIGateway struct {
      xidmetler map[string]*httputil.ReverseProxy
  }

  func YeniGateway() *APIGateway {
      gw := &APIGateway{
          xidmetler: make(map[string]*httputil.ReverseProxy),
      }

      // Xidmetleri qeydiyyatdan kecir
      gw.xidmetQosul("/api/users", "http://user-service:8081")
      gw.xidmetQosul("/api/orders", "http://order-service:8082")
      gw.xidmetQosul("/api/products", "http://product-service:8083")

      return gw
  }

  func (gw *APIGateway) xidmetQosul(prefix, hedef string) {
      url, _ := url.Parse(hedef)
      proxy := httputil.NewSingleHostReverseProxy(url)
      gw.xidmetler[prefix] = proxy
  }

  func (gw *APIGateway) ServeHTTP(w http.ResponseWriter, r *http.Request) {
      // Auth middleware
      if !tokenYoxla(r) {
          http.Error(w, "Icaze yoxdur", http.StatusUnauthorized)
          return
      }

      // Routing
      for prefix, proxy := range gw.xidmetler {
          if strings.HasPrefix(r.URL.Path, prefix) {
              proxy.ServeHTTP(w, r)
              return
          }
      }
      http.Error(w, "Xidmet tapilmadi", http.StatusNotFound)
  }

  // Populyar API Gateway-ler: Kong, Traefik, Envoy, AWS API Gateway`)

	// -------------------------------------------
	// 3. Service Discovery
	// -------------------------------------------
	fmt.Println("\n--- 3. Service Discovery ---")
	fmt.Println("Xidmetlerin bir-birini tapmasi ucun mexanizmdir.")
	fmt.Println(`
  // Problem: xidmetlerin IP/port-u dinamikdir (Kubernetes, auto-scaling)
  // Hell: merkezi qeydiyyat sistemi

  // ---- Consul ile service discovery ----
  // go get github.com/hashicorp/consul/api

  import "github.com/hashicorp/consul/api"

  // Xidmeti qeydiyyatdan kecirmek
  func consulQeydiyyat() error {
      client, err := api.NewClient(api.DefaultConfig())
      if err != nil {
          return err
      }

      qeyd := &api.AgentServiceRegistration{
          ID:      "user-service-1",
          Name:    "user-service",
          Address: "192.168.1.10",
          Port:    8081,
          Check: &api.AgentServiceCheck{
              HTTP:     "http://192.168.1.10:8081/health",
              Interval: "10s",
              Timeout:  "5s",
          },
      }

      return client.Agent().ServiceRegister(qeyd)
  }

  // Xidmeti tapmaq
  func xidmetTap(ad string) ([]*api.ServiceEntry, error) {
      client, _ := api.NewClient(api.DefaultConfig())
      xidmetler, _, err := client.Health().Service(ad, "", true, nil)
      return xidmetler, err
  }

  // ---- etcd ile service discovery ----
  // go get go.etcd.io/etcd/client/v3

  import clientv3 "go.etcd.io/etcd/client/v3"

  func etcdQeydiyyat(ctx context.Context) error {
      cli, err := clientv3.New(clientv3.Config{
          Endpoints: []string{"localhost:2379"},
      })
      if err != nil {
          return err
      }
      defer cli.Close()

      // Lease ile muveqqeti qeydiyyat (TTL)
      lease, _ := cli.Grant(ctx, 30) // 30 saniye
      _, err = cli.Put(ctx,
          "/xidmetler/user-service/node-1",
          "192.168.1.10:8081",
          clientv3.WithLease(lease.ID),
      )

      // Keep-alive - lease-i yenile
      ch, _ := cli.KeepAlive(ctx, lease.ID)
      go func() {
          for range ch {} // lease aktiv saxla
      }()

      return err
  }

  // Kubernetes-de: DNS-esasli discovery (service-adi.namespace.svc.cluster.local)`)

	// -------------------------------------------
	// 4. Circuit Breaker pattern
	// -------------------------------------------
	fmt.Println("\n--- 4. Circuit Breaker ---")
	fmt.Println("Circuit breaker - ugursuz xidmete davaml request gondermemenin yoludur.")
	fmt.Println(`
  // Veziyet diaqrami:
  // CLOSED (normal) -> coxlu xeta -> OPEN (blok)
  // OPEN -> muddet kecdi -> HALF-OPEN (test)
  // HALF-OPEN -> ugurlu -> CLOSED
  // HALF-OPEN -> ugursuz -> OPEN

  // go get github.com/sony/gobreaker/v2

  import "github.com/sony/gobreaker/v2"

  // Circuit breaker yaratmaq
  cb := gobreaker.NewCircuitBreaker[[]byte](gobreaker.Settings{
      Name:        "user-service",
      MaxRequests: 3,               // HALF-OPEN-de max test request
      Interval:    10 * time.Second, // CLOSED-de sayac sifirlanma muddeti
      Timeout:     30 * time.Second, // OPEN-den HALF-OPEN-e kecme muddeti
      ReadyToTrip: func(counts gobreaker.Counts) bool {
          // 5 ardicil ugursuzluqda OPEN-e kec
          return counts.ConsecutiveFailures > 5
      },
      OnStateChange: func(name string, from, to gobreaker.State) {
          log.Printf("Circuit breaker %s: %s -> %s", name, from, to)
      },
  })

  // Istifade
  func istifadeciAl(id int) ([]byte, error) {
      netice, err := cb.Execute(func() ([]byte, error) {
          // User service-e request gonder
          resp, err := http.Get(fmt.Sprintf("http://user-service/users/%d", id))
          if err != nil {
              return nil, err
          }
          defer resp.Body.Close()
          return io.ReadAll(resp.Body)
      })

      if err != nil {
          // Fallback - keshden oxu, default deyer qaytar
          if errors.Is(err, gobreaker.ErrOpenState) {
              log.Println("Circuit breaker OPEN - keshden oxunur")
              return keshdenOxu(id)
          }
          return nil, err
      }
      return netice, nil
  }`)

	// -------------------------------------------
	// 5. Health checks ve readiness
	// -------------------------------------------
	fmt.Println("\n--- 5. Health Checks ---")
	fmt.Println(`
  // Her microservice asagidaki endpoint-leri temin etmelidir:

  // /health (liveness) - proses isleyir
  // /ready (readiness) - butun asililiqlar hazirdir

  type XidmetSaglamligi struct {
      db    *sql.DB
      redis *redis.Client
      kafka *kafka.Reader
  }

  func (xs *XidmetSaglamligi) ReadyHandler(w http.ResponseWriter, r *http.Request) {
      ctx, cancel := context.WithTimeout(r.Context(), 3*time.Second)
      defer cancel()

      yoxlamalar := map[string]func(context.Context) error{
          "database": func(ctx context.Context) error { return xs.db.PingContext(ctx) },
          "redis":    func(ctx context.Context) error { return xs.redis.Ping(ctx).Err() },
      }

      status := "hazir"
      neticeler := make(map[string]string)

      for ad, yoxla := range yoxlamalar {
          if err := yoxla(ctx); err != nil {
              status = "hazir_deyil"
              neticeler[ad] = err.Error()
          } else {
              neticeler[ad] = "ok"
          }
      }

      if status != "hazir" {
          w.WriteHeader(http.StatusServiceUnavailable)
      }
      json.NewEncoder(w).Encode(map[string]interface{}{
          "status":    status,
          "yoxlamalar": neticeler,
      })
  }`)

	// -------------------------------------------
	// 6. Configuration management (Viper)
	// -------------------------------------------
	fmt.Println("\n--- 6. Configuration Management (Viper) ---")
	fmt.Println(`
  // go get github.com/spf13/viper

  import "github.com/spf13/viper"

  type Konfiqurasiya struct {
      Server struct {
          Port    int    ` + "`mapstructure:\"port\"`" + `
          Host    string ` + "`mapstructure:\"host\"`" + `
      } ` + "`mapstructure:\"server\"`" + `
      Database struct {
          DSN     string ` + "`mapstructure:\"dsn\"`" + `
          MaxConn int    ` + "`mapstructure:\"max_conn\"`" + `
      } ` + "`mapstructure:\"database\"`" + `
      Redis struct {
          Addr string ` + "`mapstructure:\"addr\"`" + `
      } ` + "`mapstructure:\"redis\"`" + `
  }

  func konfiqurasiyaYukle() (*Konfiqurasiya, error) {
      viper.SetConfigName("config")       // fayl adi
      viper.SetConfigType("yaml")          // fayl tipi
      viper.AddConfigPath(".")             // cari qovluq
      viper.AddConfigPath("/etc/myapp/")   // sistem qovlugu

      // Environment variable-lar ile override
      viper.AutomaticEnv()
      viper.SetEnvPrefix("MYAPP") // MYAPP_SERVER_PORT=9090

      if err := viper.ReadInConfig(); err != nil {
          return nil, fmt.Errorf("konfiqurasiya oxunmadi: %w", err)
      }

      var konf Konfiqurasiya
      if err := viper.Unmarshal(&konf); err != nil {
          return nil, err
      }
      return &konf, nil
  }

  // config.yaml numunesi:
  // server:
  //   port: 8080
  //   host: "0.0.0.0"
  // database:
  //   dsn: "postgres://user:pass@localhost/mydb"
  //   max_conn: 25
  // redis:
  //   addr: "localhost:6379"

  // Dinamik konfiqurasiya (runtime-da deyisiklikleri izle)
  viper.WatchConfig()
  viper.OnConfigChange(func(e fsnotify.Event) {
      log.Println("Konfiqurasiya deyisdi:", e.Name)
  })`)

	// -------------------------------------------
	// 7. Distributed Transactions (Saga pattern)
	// -------------------------------------------
	fmt.Println("\n--- 7. Saga Pattern (paylanmis tranzaksiya) ---")
	fmt.Println("Microservices-de database transaction yoxdur. Saga bunu hell edir.")
	fmt.Println(`
  // Saga - bir sira local tranzaksiyalar ve compensating (geri qaytarma) aksiyalar

  // Misal: Sifaris yaratma prosesi
  // 1. OrderService: sifaris yarat
  // 2. PaymentService: odenis al
  // 3. InventoryService: anbarda azalt
  // 4. NotificationService: bildiris gonder
  //
  // Eger 3-cu merhele ugursuz olarsa:
  // - InventoryService: (ugursuz oldu)
  // - PaymentService: odenisi geri qaytar (compensate)
  // - OrderService: sifarisi legv et (compensate)

  // ---- Orchestration Saga (merkezlesdirilmis) ----
  // Bir orkestrator butun merheleler idare edir

  type SagaMerhelesi struct {
      Ad         string
      Icra       func(ctx context.Context, melumat interface{}) error
      Kompensasiya func(ctx context.Context, melumat interface{}) error
  }

  type SagaOrkestratoru struct {
      merheleler []SagaMerhelesi
  }

  func (so *SagaOrkestratoru) Icra(ctx context.Context, melumat interface{}) error {
      icraOlunanlar := []SagaMerhelesi{}

      for _, merhele := range so.merheleler {
          log.Printf("Saga merhelesi: %s", merhele.Ad)

          if err := merhele.Icra(ctx, melumat); err != nil {
              log.Printf("Saga ugursuz: %s - %v", merhele.Ad, err)

              // Geri qaytarma (compensation)
              for i := len(icraOlunanlar) - 1; i >= 0; i-- {
                  m := icraOlunanlar[i]
                  log.Printf("Kompensasiya: %s", m.Ad)
                  if compErr := m.Kompensasiya(ctx, melumat); compErr != nil {
                      log.Printf("Kompensasiya ugursuz: %s - %v", m.Ad, compErr)
                      // Burada manual mudaxile lazim ola biler
                  }
              }
              return fmt.Errorf("saga ugursuz: %s: %w", merhele.Ad, err)
          }
          icraOlunanlar = append(icraOlunanlar, merhele)
      }
      return nil
  }

  // Istifade
  saga := &SagaOrkestratoru{
      merheleler: []SagaMerhelesi{
          {
              Ad:           "sifaris-yarat",
              Icra:         sifarisYarat,
              Kompensasiya: sifarisLegvEt,
          },
          {
              Ad:           "odenis-al",
              Icra:         odenisAl,
              Kompensasiya: odenisGeriQaytar,
          },
          {
              Ad:           "anbar-azalt",
              Icra:         anbarAzalt,
              Kompensasiya: anbarArtir,
          },
      },
  }
  err := saga.Icra(ctx, sifarismelumati)

  // ---- Choreography Saga (merkezi olmadan) ----
  // Her xidmet hadise yayir, novbeti xidmet dinleyir
  // OrderService -> "sifaris_yaradildi" hadisesi -> PaymentService dinleyir
  // PaymentService -> "odenis_alindi" hadisesi -> InventoryService dinleyir
  // Daha sade, amma izlemek cetindir`)

	// -------------------------------------------
	// 8. Inter-service communication
	// -------------------------------------------
	fmt.Println("\n--- 8. Servisler arasi kommunikasiya ---")
	fmt.Println(`
  // 3 esas yol var:

  // ---- 1. HTTP/REST (sinxron) ----
  // + Sade, her dilde desteklenir
  // - Sixis yaradir, cavab gozlemek lazimdir

  func istifadeciServisi(ctx context.Context, id int) (*User, error) {
      req, _ := http.NewRequestWithContext(ctx, "GET",
          fmt.Sprintf("http://user-service:8081/users/%d", id), nil)
      resp, err := httpClient.Do(req)
      // ...
  }

  // ---- 2. gRPC (sinxron, yuksek performans) ----
  // + Suretli (HTTP/2, protobuf), type-safe, streaming
  // - Proto fayllari idare etmek lazim

  // user.proto:
  // service UserService {
  //     rpc GetUser (GetUserRequest) returns (User);
  //     rpc ListUsers (ListUsersRequest) returns (stream User);
  // }

  // go get google.golang.org/grpc

  import "google.golang.org/grpc"

  // gRPC client
  func grpcIstifadeciAl(ctx context.Context, id int) (*pb.User, error) {
      conn, err := grpc.Dial("user-service:50051", grpc.WithInsecure())
      if err != nil {
          return nil, err
      }
      defer conn.Close()

      client := pb.NewUserServiceClient(conn)
      return client.GetUser(ctx, &pb.GetUserRequest{Id: int64(id)})
  }

  // ---- 3. Message Queue (asinxron) ----
  // + Decoupling, dayaniqli, retry mumkun
  // - Muhitler, cavab gozleme yoxdur

  // Sifaris yaradildi -> Kafka -> Payment Service oxuyur
  // Bax: 70_message_queues.go

  // ---- Hansi nece sec? ----
  // Sinxron cavab lazimdir        -> gRPC ve ya HTTP
  // Yuksek performans, streaming  -> gRPC
  // Fire-and-forget, decoupling   -> Message Queue
  // Sadəlik, debug rahatliqi      -> HTTP/REST`)

	fmt.Println("\n=== XULASE ===")
	fmt.Println("Decomposition  - domain-e gore bol, kicik saxla")
	fmt.Println("API Gateway    - tek giris noqtesi, auth, routing")
	fmt.Println("Discovery      - xidmetleri dinamik tap (Consul, etcd, DNS)")
	fmt.Println("Circuit Breaker- ugursuz xidmete dayan, fallback istifade et")
	fmt.Println("Health Check   - /health ve /ready endpoint-leri mezburdur")
	fmt.Println("Config         - Viper ile fayl + env var + dinamik konfiqurasiya")
	fmt.Println("Saga           - paylanmis tranzaksiya + kompensasiya")
	fmt.Println("Kommunikasiya  - HTTP, gRPC, Message Queue - ehtiyaca gore sec")
}
