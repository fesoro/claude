# TLS/HTTPS Server (Middle)

## İcmal

Go-da HTTPS server qurmaq `http.ListenAndServeTLS` ilə sadədir. Sertifikat əldə etmək üçün **Let's Encrypt** (pulsuz, avtomatik) standartdır. Production-da TLS versiyası, cipher suite-lər, mutual TLS (mTLS) konfigurasiyası vacibdir.

## Niyə Vacibdir

- HTTP-də həssas data (token, şifrə) açıq gedir — HTTPS məcburidir
- Let's Encrypt `golang.org/x/crypto/acme/autocert` ilə birbaşa Go-ya inteqrasiya olunur
- mTLS — microservice-lər arası kimlik doğrulama
- TLS konfigurasiyası səhv olduqda güvənsiz cipherlar aktiv qalır

## Əsas Anlayışlar

- **`tls.Config`** — TLS versiyası, cipher suite-lər, sertifikat yüklənməsi
- **`tls.LoadX509KeyPair`** — fayl-dən sertifikat + private key yüklə
- **`autocert.Manager`** — Let's Encrypt sertifikatlarını avtomatik al/yenilə
- **`tls.MinVersion`** — minimum TLS 1.2; production-da 1.3 tövsiyə olunur
- **`mTLS`** — `ClientAuth: tls.RequireAndVerifyClientCert` — client sertifikat tələb edir
- **`HSTS`** — `Strict-Transport-Security` header — browser-ı həmişə HTTPS-ə yönləndirir
- **`http.Redirect`** — HTTP → HTTPS yönləndirməsi

## Praktik Baxış

**Ne vaxt nə istifadə et:**

| Ssenari | Yanaşma |
|---------|---------|
| Local development | self-signed cert (`mkcert`) |
| Production, domain var | Let's Encrypt (autocert) |
| Private/corporate | öz CA-dan sertifikat |
| Microservice-to-microservice | mTLS |
| Load balancer arxasında | TLS termination (LB-də TLS, Go-da HTTP) |

**Trade-off-lar:**
- Let's Encrypt: port 80 açıq olmalıdır (ACME challenge); wildcard üçün DNS challenge
- Self-managed cert: yenilənməni izləmək lazımdır (90 gün ömür)
- mTLS: hər client-in sertifikatı olmalıdır — infrastructure idarəsi artır

**Common mistakes:**
- TLS 1.0/1.1 aktiv buraxmaq — həssas; MinVersion = tls.VersionTLS12 minimum
- `InsecureSkipVerify: true` — development-dən production-a keçdikdə silinməlidir
- HTTP-ni HTTPS-ə yönləndirməmək — search engine penalty + güvənsizlik

## Nümunələr

### Nümunə 1: Sadə HTTPS — sertifikat faylından

```go
package main

import (
    "crypto/tls"
    "net/http"
)

func main() {
    mux := http.NewServeMux()
    mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
        w.Write([]byte("HTTPS işləyir!"))
    })

    tlsCfg := &tls.Config{
        MinVersion: tls.VersionTLS12,
        // Güclü cipher-lər; Go 1.17+ default-ları artıq yaxşıdır
        CurvePreferences: []tls.CurveID{
            tls.X25519,
            tls.CurveP256,
        },
    }

    srv := &http.Server{
        Addr:      ":443",
        Handler:   mux,
        TLSConfig: tlsCfg,
    }

    // cert.pem + key.pem faylları lazımdır
    if err := srv.ListenAndServeTLS("cert.pem", "key.pem"); err != nil {
        panic(err)
    }
}
```

### Nümunə 2: Let's Encrypt — autocert

```go
package main

import (
    "crypto/tls"
    "net/http"

    "golang.org/x/crypto/acme/autocert"
)

func main() {
    mux := http.NewServeMux()
    mux.HandleFunc("/", handler)

    // autocert Manager — sertifikatları avtomatik alır, yenilər
    m := &autocert.Manager{
        Prompt:     autocert.AcceptTOS,
        HostPolicy: autocert.HostWhitelist("example.com", "www.example.com"),
        Cache:      autocert.DirCache("/var/cache/autocert"), // sertifikatları saxla
    }

    // HTTPS server
    httpsServer := &http.Server{
        Addr:      ":443",
        Handler:   mux,
        TLSConfig: m.TLSConfig(),
    }

    // HTTP → HTTPS yönləndirmə (port 80 ACME challenge üçün lazımdır)
    httpServer := &http.Server{
        Addr:    ":80",
        Handler: m.HTTPHandler(http.HandlerFunc(redirectHTTPS)),
    }

    go httpServer.ListenAndServe()
    httpsServer.ListenAndServeTLS("", "") // autocert sertifikatları idarə edir
}

func redirectHTTPS(w http.ResponseWriter, r *http.Request) {
    target := "https://" + r.Host + r.URL.RequestURI()
    http.Redirect(w, r, target, http.StatusMovedPermanently)
}
```

### Nümunə 3: HSTS + güvənlik header-ləri

```go
func securityMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        // HSTS — 1 il; includeSubDomains
        w.Header().Set("Strict-Transport-Security", "max-age=31536000; includeSubDomains")
        // Digər güvənlik header-ləri
        w.Header().Set("X-Content-Type-Options", "nosniff")
        w.Header().Set("X-Frame-Options", "DENY")
        w.Header().Set("Content-Security-Policy", "default-src 'self'")
        next.ServeHTTP(w, r)
    })
}
```

### Nümunə 4: mTLS — mutual TLS

```go
package main

import (
    "crypto/tls"
    "crypto/x509"
    "net/http"
    "os"
)

func main() {
    // Client CA sertifikatlarını yüklə
    caCert, err := os.ReadFile("ca.crt")
    if err != nil {
        panic(err)
    }
    caCertPool := x509.NewCertPool()
    caCertPool.AppendCertsFromPEM(caCert)

    tlsCfg := &tls.Config{
        ClientAuth: tls.RequireAndVerifyClientCert, // client sertifikat məcburidir
        ClientCAs:  caCertPool,
        MinVersion: tls.VersionTLS13,
    }

    srv := &http.Server{
        Addr:      ":8443",
        Handler:   http.HandlerFunc(mtlsHandler),
        TLSConfig: tlsCfg,
    }

    srv.ListenAndServeTLS("server.crt", "server.key")
}

func mtlsHandler(w http.ResponseWriter, r *http.Request) {
    if r.TLS != nil && len(r.TLS.PeerCertificates) > 0 {
        clientName := r.TLS.PeerCertificates[0].Subject.CommonName
        w.Write([]byte("Salam, " + clientName))
    }
}
```

### Nümunə 5: mTLS client

```go
func newMTLSClient(certFile, keyFile, caFile string) (*http.Client, error) {
    cert, err := tls.LoadX509KeyPair(certFile, keyFile)
    if err != nil {
        return nil, err
    }

    caCert, err := os.ReadFile(caFile)
    if err != nil {
        return nil, err
    }
    caCertPool := x509.NewCertPool()
    caCertPool.AppendCertsFromPEM(caCert)

    tlsCfg := &tls.Config{
        Certificates: []tls.Certificate{cert},
        RootCAs:      caCertPool,
    }

    return &http.Client{
        Transport: &http.Transport{TLSClientConfig: tlsCfg},
    }, nil
}
```

## Praktik Tapşırıqlar

1. **Self-signed cert:** `mkcert localhost` ilə local HTTPS server qur, brauzer ilə test et
2. **HTTP Redirect:** Port 80-dən 443-ə yönləndirən middleware yaz
3. **mTLS:** openssl ilə CA, server cert, client cert yarat; Go-da mTLS server + client yaz
4. **Sertifikat yoxlama:** Sertifikatın bitmə tarixini yoxlayan monitor funksiyası yaz

## PHP ilə Müqayisə

```
PHP/Laravel              →  Go
────────────────────────────────────────
Nginx/Apache HTTPS       →  net/http TLS directly
Let's Encrypt (Certbot)  →  autocert.Manager
.htaccess redirect       →  http.Redirect middleware
PHP-FPM + TLS termination →  ListenAndServeTLS
```

PHP-də TLS adətən web server (Nginx/Apache) tərəfindən idarə edilir. Go-da TLS birbaşa `net/http`-ə inteqrasiya olunub — ayrı server lazım deyil.

## Əlaqəli Mövzular

- [01-http-server](01-http-server.md) — HTTP server əsasları
- [03-middleware-and-routing](03-middleware-and-routing.md) — security middleware
- [../advanced/07-security](../advanced/07-security.md) — TLS + input validation + auth
- [17-graceful-shutdown](17-graceful-shutdown.md) — TLS server-i düzgün bağlamaq
