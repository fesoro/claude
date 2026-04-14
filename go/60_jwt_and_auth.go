package main

import (
	"crypto/hmac"
	"crypto/sha256"
	"crypto/tls"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"strings"
	"time"
)

// ===============================================
// JWT VE AUTHENTICATION (KIMLIK DOGRULAMA)
// ===============================================

// -------------------------------------------
// 1. JWT Token Strukturu
// -------------------------------------------

// JWT (JSON Web Token) 3 hisseden ibaretdir:
// Header.Payload.Signature
//
// Her hisse Base64URL ile kodlanir
// Numune: xxxxx.yyyyy.zzzzz

// Header - token tipi ve algoritm
type JWTHeader struct {
	Alg string `json:"alg"` // Algoritm: HS256, RS256, etc.
	Typ string `json:"typ"` // Token tipi: JWT
}

// Payload - melumatlar (claims)
type JWTPayload struct {
	// Standart claims
	Sub string `json:"sub"`           // Subject - istifadeci ID
	Iss string `json:"iss,omitempty"` // Issuer - token yaradan
	Aud string `json:"aud,omitempty"` // Audience - kimler ucun
	Exp int64  `json:"exp"`           // Expiration - bitmə vaxti
	Iat int64  `json:"iat"`           // Issued At - yaranma vaxti
	Nbf int64  `json:"nbf,omitempty"` // Not Before - baslanma vaxti

	// Custom claims
	Name  string `json:"name,omitempty"`
	Email string `json:"email,omitempty"`
	Role  string `json:"role,omitempty"`
}

// -------------------------------------------
// 2. Sade JWT yaratma (xarici kitabxanasiz)
// -------------------------------------------

var secretKey = []byte("super-gizli-acar-istehsalda-bele-etmeyin")

func base64URLEncode(data []byte) string {
	return strings.TrimRight(base64.URLEncoding.EncodeToString(data), "=")
}

func base64URLDecode(s string) ([]byte, error) {
	// Padding elave et
	switch len(s) % 4 {
	case 2:
		s += "=="
	case 3:
		s += "="
	}
	return base64.URLEncoding.DecodeString(s)
}

func createJWT(payload JWTPayload) (string, error) {
	// 1. Header
	header := JWTHeader{Alg: "HS256", Typ: "JWT"}
	headerJSON, err := json.Marshal(header)
	if err != nil {
		return "", err
	}
	headerEncoded := base64URLEncode(headerJSON)

	// 2. Payload
	payloadJSON, err := json.Marshal(payload)
	if err != nil {
		return "", err
	}
	payloadEncoded := base64URLEncode(payloadJSON)

	// 3. Signature
	signingInput := headerEncoded + "." + payloadEncoded
	mac := hmac.New(sha256.New, secretKey)
	mac.Write([]byte(signingInput))
	signature := base64URLEncode(mac.Sum(nil))

	// Token = Header.Payload.Signature
	return signingInput + "." + signature, nil
}

// -------------------------------------------
// 3. JWT dogrulama (validation)
// -------------------------------------------

func validateJWT(tokenString string) (*JWTPayload, error) {
	// Token-i 3 hisseye bol
	parts := strings.Split(tokenString, ".")
	if len(parts) != 3 {
		return nil, fmt.Errorf("yanlis token formati")
	}

	// Imzanı yoxla
	signingInput := parts[0] + "." + parts[1]
	mac := hmac.New(sha256.New, secretKey)
	mac.Write([]byte(signingInput))
	expectedSig := base64URLEncode(mac.Sum(nil))

	if !hmac.Equal([]byte(parts[2]), []byte(expectedSig)) {
		return nil, fmt.Errorf("imza duzgun deyil")
	}

	// Payload-i decode et
	payloadJSON, err := base64URLDecode(parts[1])
	if err != nil {
		return nil, fmt.Errorf("payload decode xetasi: %v", err)
	}

	var payload JWTPayload
	if err := json.Unmarshal(payloadJSON, &payload); err != nil {
		return nil, fmt.Errorf("payload parse xetasi: %v", err)
	}

	// Vaxti yoxla
	if time.Now().Unix() > payload.Exp {
		return nil, fmt.Errorf("token muddetini bitib (expired)")
	}

	return &payload, nil
}

// -------------------------------------------
// 4. golang-jwt kitabxanasi ile (comment olaraq)
// -------------------------------------------

// Real layihelerde github.com/golang-jwt/jwt/v5 istifade edin:
//
// import "github.com/golang-jwt/jwt/v5"
//
// // Token yaratma
// func CreateTokenWithLib(userID string, role string) (string, error) {
//     claims := jwt.MapClaims{
//         "sub":  userID,
//         "role": role,
//         "exp":  jwt.NewNumericDate(time.Now().Add(15 * time.Minute)),
//         "iat":  jwt.NewNumericDate(time.Now()),
//     }
//     token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)
//     return token.SignedString(secretKey)
// }
//
// // Token dogrulama
// func ValidateTokenWithLib(tokenStr string) (*jwt.Token, error) {
//     return jwt.Parse(tokenStr, func(token *jwt.Token) (interface{}, error) {
//         if _, ok := token.Method.(*jwt.SigningMethodHMAC); !ok {
//             return nil, fmt.Errorf("gozlenilmeyen metod: %v", token.Header["alg"])
//         }
//         return secretKey, nil
//     })
// }

// -------------------------------------------
// 5. Auth Middleware
// -------------------------------------------

func authMiddleware(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		// Authorization header-den token al
		authHeader := r.Header.Get("Authorization")
		if authHeader == "" {
			http.Error(w, "Authorization header lazimdir", http.StatusUnauthorized)
			return
		}

		// "Bearer <token>" formatini yoxla
		parts := strings.Split(authHeader, " ")
		if len(parts) != 2 || parts[0] != "Bearer" {
			http.Error(w, "Format: Bearer <token>", http.StatusUnauthorized)
			return
		}

		// Token-i dogrula
		payload, err := validateJWT(parts[1])
		if err != nil {
			http.Error(w, fmt.Sprintf("Token xetasi: %v", err), http.StatusUnauthorized)
			return
		}

		// Istifadeci melumatini header-e elave et
		r.Header.Set("X-User-ID", payload.Sub)
		r.Header.Set("X-User-Role", payload.Role)

		// Novbeti handler-e kec
		next(w, r)
	}
}

// Role yoxlayan middleware
func requireRole(role string, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		userRole := r.Header.Get("X-User-Role")
		if userRole != role {
			http.Error(w, "Icaze yoxdur", http.StatusForbidden)
			return
		}
		next(w, r)
	}
}

// -------------------------------------------
// 6. Refresh Token Pattern
// -------------------------------------------

// Access Token - qisa omurlu (15 deq), API sorğulari ucun
// Refresh Token - uzun omurlu (7 gun), yeni access token almaq ucun

type TokenPair struct {
	AccessToken  string `json:"access_token"`
	RefreshToken string `json:"refresh_token"`
	ExpiresIn    int    `json:"expires_in"` // saniye
}

func generateTokenPair(userID, role string) (*TokenPair, error) {
	// Access token - 15 deqiqe
	accessPayload := JWTPayload{
		Sub:  userID,
		Role: role,
		Iat:  time.Now().Unix(),
		Exp:  time.Now().Add(15 * time.Minute).Unix(),
	}
	accessToken, err := createJWT(accessPayload)
	if err != nil {
		return nil, err
	}

	// Refresh token - 7 gun
	refreshPayload := JWTPayload{
		Sub: userID,
		Iat: time.Now().Unix(),
		Exp: time.Now().Add(7 * 24 * time.Hour).Unix(),
	}
	refreshToken, err := createJWT(refreshPayload)
	if err != nil {
		return nil, err
	}

	return &TokenPair{
		AccessToken:  accessToken,
		RefreshToken: refreshToken,
		ExpiresIn:    900, // 15 deqiqe = 900 saniye
	}, nil
}

// -------------------------------------------
// 7. OAuth2 Esasi Axin (Authorization Code Flow)
// -------------------------------------------

// OAuth2 axini (meselen Google ile giris):
//
// 1. Istifadeci -> Sizin App: "Google ile giris et"
// 2. Sizin App -> Google: Redirect (client_id, redirect_uri, scope)
// 3. Google -> Istifadeci: "Icaze verirsiniz?"
// 4. Istifadeci -> Google: "Beli"
// 5. Google -> Sizin App: Authorization Code (redirect_uri-ye)
// 6. Sizin App -> Google: Code + client_secret -> Access Token
// 7. Sizin App -> Google API: Access Token ile melumat al
//
// Go-da oauth2 paketi:
//
// import "golang.org/x/oauth2"
// import "golang.org/x/oauth2/google"
//
// var googleOAuthConfig = &oauth2.Config{
//     ClientID:     "sizin-client-id",
//     ClientSecret: "sizin-client-secret",
//     RedirectURL:  "http://localhost:8080/callback",
//     Scopes:       []string{"email", "profile"},
//     Endpoint:     google.Endpoint,
// }
//
// // Login handler
// func handleGoogleLogin(w http.ResponseWriter, r *http.Request) {
//     url := googleOAuthConfig.AuthCodeURL("state-token")
//     http.Redirect(w, r, url, http.StatusTemporaryRedirect)
// }
//
// // Callback handler
// func handleGoogleCallback(w http.ResponseWriter, r *http.Request) {
//     code := r.URL.Query().Get("code")
//     token, err := googleOAuthConfig.Exchange(r.Context(), code)
//     // token.AccessToken ile Google API-ya sorgu gonder
// }

// -------------------------------------------
// 8. HTTPS Server (TLS ile)
// -------------------------------------------

func setupTLSServer() {
	// TLS konfiqurasiyasi
	tlsConfig := &tls.Config{
		MinVersion:               tls.VersionTLS12,
		PreferServerCipherSuites: true,
		CipherSuites: []uint16{
			tls.TLS_ECDHE_RSA_WITH_AES_256_GCM_SHA384,
			tls.TLS_ECDHE_RSA_WITH_AES_128_GCM_SHA256,
		},
	}

	server := &http.Server{
		Addr:      ":443",
		TLSConfig: tlsConfig,
	}

	// Self-signed sertifikat yaratmaq ucun:
	// openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -nodes

	fmt.Println("[TLS Server konfiqurasiyasi hazirdir]")
	fmt.Printf("Server struct: %+v\n", server.Addr)

	// Real serveri baslatmaq ucun:
	// log.Fatal(server.ListenAndServeTLS("cert.pem", "key.pem"))
}

func main() {

	// =====================
	// JWT YARATMA VE DOGRULAMA
	// =====================

	fmt.Println("=== JWT Token Yaratma ===")

	payload := JWTPayload{
		Sub:   "user123",
		Name:  "Orxan",
		Email: "orxan@test.com",
		Role:  "admin",
		Iat:   time.Now().Unix(),
		Exp:   time.Now().Add(15 * time.Minute).Unix(),
	}

	token, err := createJWT(payload)
	if err != nil {
		log.Fatal(err)
	}

	fmt.Println("Token:", token[:50]+"...")
	fmt.Println("Hisseler:", len(strings.Split(token, ".")))

	// Token-in hisselerini goster
	parts := strings.Split(token, ".")
	headerJSON, _ := base64URLDecode(parts[0])
	payloadJSON, _ := base64URLDecode(parts[1])
	fmt.Println("\nHeader:", string(headerJSON))
	fmt.Println("Payload:", string(payloadJSON))

	// -------------------------------------------
	fmt.Println("\n=== JWT Dogrulama ===")

	validPayload, err := validateJWT(token)
	if err != nil {
		log.Fatal(err)
	}
	fmt.Printf("Dogru token! Sub: %s, Role: %s\n", validPayload.Sub, validPayload.Role)

	// Yanlis token
	_, err = validateJWT(token + "tampered")
	if err != nil {
		fmt.Println("Gozlenilen xeta:", err)
	}

	// Muddetini bitmis token
	expiredPayload := JWTPayload{
		Sub: "user456",
		Iat: time.Now().Add(-1 * time.Hour).Unix(),
		Exp: time.Now().Add(-30 * time.Minute).Unix(), // 30 deq evvel bitmis
	}
	expiredToken, _ := createJWT(expiredPayload)
	_, err = validateJWT(expiredToken)
	if err != nil {
		fmt.Println("Expired token:", err)
	}

	// -------------------------------------------
	fmt.Println("\n=== Refresh Token Pattern ===")

	tokenPair, err := generateTokenPair("user123", "admin")
	if err != nil {
		log.Fatal(err)
	}
	fmt.Printf("Access Token:  %s...\n", tokenPair.AccessToken[:40])
	fmt.Printf("Refresh Token: %s...\n", tokenPair.RefreshToken[:40])
	fmt.Printf("Expires In:    %d saniye\n", tokenPair.ExpiresIn)

	// -------------------------------------------
	fmt.Println("\n=== HTTP Server Numunesi ===")

	// Handler-ler
	loginHandler := func(w http.ResponseWriter, r *http.Request) {
		// Real app-de istifadeci/parol yoxlayarsiz
		pair, _ := generateTokenPair("user1", "admin")
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(pair)
	}

	protectedHandler := func(w http.ResponseWriter, r *http.Request) {
		userID := r.Header.Get("X-User-ID")
		role := r.Header.Get("X-User-Role")
		fmt.Fprintf(w, "Salam, %s! Rolunuz: %s", userID, role)
	}

	adminHandler := func(w http.ResponseWriter, r *http.Request) {
		fmt.Fprintf(w, "Admin paneli")
	}

	// Route-lar (middleware ile)
	http.HandleFunc("/login", loginHandler)
	http.HandleFunc("/profile", authMiddleware(protectedHandler))
	http.HandleFunc("/admin", authMiddleware(requireRole("admin", adminHandler)))

	fmt.Println("Route-lar qeydiyyatdan kecdi:")
	fmt.Println("  POST /login        - token al")
	fmt.Println("  GET  /profile      - auth lazimdir")
	fmt.Println("  GET  /admin        - admin rolu lazimdir")

	// TLS setup
	fmt.Println("\n=== TLS/HTTPS Setup ===")
	setupTLSServer()

	fmt.Println("\n=== Tehlukesizlik Melumlari ===")
	fmt.Println(`
1. Secret key-i HECH VAXT kodda saxlamayin - env variable istifade edin
2. Access token qisa omurlu olmalidir (15 deq)
3. Refresh token-i verilənlər bazasinda saxlayin
4. HTTPS hemise istifade edin (HTTP deyil)
5. Token-de hessas melumat saxlamayin (parol, kart nomresi)
6. CORS ve Rate Limiting tetbiq edin
7. RS256 (asymmetric) HS256-dan daha tehlukesizdir
`)

	// Server-i baslatmaq ucun comment-i acin:
	// log.Fatal(http.ListenAndServe(":8080", nil))
}
