package main

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"fmt"
	"io"
	"strings"

	"golang.org/x/crypto/bcrypt"
)

// ===============================================
// TEHLUKESIZLIK (SECURITY)
// ===============================================

// Go-da tehlukesiz proqram yazmaq ucun bilmeniz gerekenler

func main() {

	// -------------------------------------------
	// 1. PAROL HASHING (bcrypt)
	// -------------------------------------------
	// go get golang.org/x/crypto/bcrypt
	// HECH VAXT parolu aciq metn kimi saxlamayin!
	// HECH VAXT MD5/SHA ile hash etmeyin (suretlidir = tehlukelidir)

	fmt.Println("=== Parol Hashing ===")

	parol := "gizli_parol_123"

	// Hash yarat (her defe ferqli netice verir - salt avtomatikdir)
	hash, err := bcrypt.GenerateFromPassword([]byte(parol), bcrypt.DefaultCost)
	if err != nil {
		fmt.Println("Hash xetasi:", err)
		return
	}
	fmt.Println("Hash:", string(hash))

	// Parolu yoxla
	err = bcrypt.CompareHashAndPassword(hash, []byte(parol))
	if err == nil {
		fmt.Println("Parol DOGRUDUR")
	}

	err = bcrypt.CompareHashAndPassword(hash, []byte("yanlis_parol"))
	if err != nil {
		fmt.Println("Parol YANLISDIR")
	}

	// -------------------------------------------
	// 2. SHA-256 HASH
	// -------------------------------------------
	// Melumat butunluyunu yoxlamaq ucun (parol ucun deyil!)

	fmt.Println("\n=== SHA-256 ===")

	melumat := "Bu muhum melumatdir"
	hashBytes := sha256.Sum256([]byte(melumat))
	hashStr := hex.EncodeToString(hashBytes[:])
	fmt.Println("SHA-256:", hashStr)

	// -------------------------------------------
	// 3. HMAC - Mesaj Autentifikasiyasi
	// -------------------------------------------
	// Mesajin deyisdirilmediyini ve gondericinin dogru oldugunu yoxlamaq

	fmt.Println("\n=== HMAC ===")

	gizliAcar := []byte("super-gizli-acar")
	mesaj := []byte("Muhum mesaj")

	// HMAC yarat
	mac := hmac.New(sha256.New, gizliAcar)
	mac.Write(mesaj)
	imza := mac.Sum(nil)
	fmt.Println("HMAC:", hex.EncodeToString(imza))

	// HMAC yoxla
	mac2 := hmac.New(sha256.New, gizliAcar)
	mac2.Write(mesaj)
	gozlenen := mac2.Sum(nil)
	etibarlimi := hmac.Equal(imza, gozlenen) // timing-safe muqayise!
	fmt.Println("HMAC etibarlimi:", etibarlimi)

	// -------------------------------------------
	// 4. AES ENCRYPTION (sifrləmə/desifreleme)
	// -------------------------------------------
	fmt.Println("\n=== AES Encryption ===")

	aesAcar := make([]byte, 32) // AES-256 ucun 32 byte
	rand.Read(aesAcar)

	orijinal := "Bu gizli melumatdir"

	// Sifreleme
	sifrelenmis, err := aesEncrypt([]byte(orijinal), aesAcar)
	if err != nil {
		fmt.Println("Sifrəlmə xetasi:", err)
		return
	}
	fmt.Println("Sifrelenmis:", base64.StdEncoding.EncodeToString(sifrelenmis))

	// Desifreleme
	desifrelmis, err := aesDecrypt(sifrelenmis, aesAcar)
	if err != nil {
		fmt.Println("Desifrəlmə xetasi:", err)
		return
	}
	fmt.Println("Desifrelmis:", string(desifrelmis))

	// -------------------------------------------
	// 5. TESADUFI DEYER YARATMA
	// -------------------------------------------
	fmt.Println("\n=== Tesadufi Deyerler ===")

	// Tehlukesiz tesadufi byte-lar (crypto/rand)
	token := make([]byte, 32)
	rand.Read(token)
	fmt.Println("Token:", hex.EncodeToString(token))

	// Base64 token
	b64Token := base64.URLEncoding.EncodeToString(token)
	fmt.Println("Base64 token:", b64Token)

	// QEYD: math/rand TEHLUKESIZ deyil - gizli deyerler ucun istifade etmeyin!
	// HER ZAMAN crypto/rand istifade edin

	// -------------------------------------------
	// 6. INPUT SANITIZATION
	// -------------------------------------------
	fmt.Println("\n=== Input Sanitization ===")

	// SQL Injection qorunmasi
	// YANLIS:
	// query := "SELECT * FROM users WHERE name = '" + userInput + "'"
	// DOGRU:
	// db.Query("SELECT * FROM users WHERE name = $1", userInput)

	// XSS qorunmasi
	// html/template paketi avtomatik escape edir
	// YANLIS: text/template istifade etmek
	// DOGRU:  html/template istifade etmek

	// Path traversal qorunmasi
	tehlukeliYol := "../../../etc/passwd"
	if strings.Contains(tehlukeliYol, "..") {
		fmt.Println("TEHLIKE: Path traversal tesebbusu:", tehlukeliYol)
	}

	// -------------------------------------------
	// 7. TEHLUKESIZLIK TOVSIYELER
	// -------------------------------------------
	fmt.Println(`
=== Tehlukesizlik Yoxlama Siyahisi ===

PAROLLAR:
  [x] bcrypt ve ya argon2 istifade edin
  [x] Minimum 12 simvol telab edin
  [x] Parolu hec vaxt log-a yazmayin

SQL:
  [x] Her zaman parameterized query ($1, $2) istifade edin
  [x] ORM istifade edin (GORM, sqlx)
  [x] Database istifadecisine minimum icaze verin

HTTP:
  [x] HTTPS istifade edin (TLS)
  [x] CORS duzgun konfiqurasiya edin
  [x] Rate limiting elave edin
  [x] Helmet/security header-leri elave edin
  [x] CSRF token istifade edin (form-lar ucun)

INPUT:
  [x] Butun istifadeci girislerini dogrulayin
  [x] html/template istifade edin (XSS qorunmasi)
  [x] Path traversal yoxlayin
  [x] Fayl yuklemelerini yoxlayin (tip, olcu)

GIZLI MELUMATLAR:
  [x] Parollari, tokenleri koda yazmayin
  [x] .env faylini git-e commit etmeyin
  [x] Environment deyiskenleri istifade edin
  [x] crypto/rand istifade edin (math/rand deyil)

ASILILIQLIAR:
  [x] go mod tidy ile lazim olmayanlari silin
  [x] govulncheck ile zeyiflikleri yoxlayin:
      go install golang.org/x/vuln/cmd/govulncheck@latest
      govulncheck ./...
`)
}

// AES-GCM sifrəlmə
func aesEncrypt(plaintext, key []byte) ([]byte, error) {
	block, err := aes.NewCipher(key)
	if err != nil {
		return nil, err
	}

	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return nil, err
	}

	nonce := make([]byte, gcm.NonceSize())
	if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
		return nil, err
	}

	return gcm.Seal(nonce, nonce, plaintext, nil), nil
}

// AES-GCM desifreleme
func aesDecrypt(ciphertext, key []byte) ([]byte, error) {
	block, err := aes.NewCipher(key)
	if err != nil {
		return nil, err
	}

	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return nil, err
	}

	nonceSize := gcm.NonceSize()
	if len(ciphertext) < nonceSize {
		return nil, fmt.Errorf("sifrelenmis metn cox qisadir")
	}

	nonce, ciphertext := ciphertext[:nonceSize], ciphertext[nonceSize:]
	return gcm.Open(nil, nonce, ciphertext, nil)
}
