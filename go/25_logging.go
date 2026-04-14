package main

import (
	"log"
	"log/slog"
	"os"
)

// ===============================================
// LOGGING (LOG YAZMAQ)
// ===============================================

func main() {

	// =====================
	// 1. STANDART LOG PAKETI
	// =====================

	// Sadə log
	log.Println("Bu sadə log mesajidir")
	log.Printf("Istifadeci %s daxil oldu, yas: %d", "Orkhan", 25)

	// Prefix (on ek) elave etme
	log.SetPrefix("[APP] ")
	log.Println("Prefix ile log")

	// Bayraqlari deyisme (neyin gorseneceyini tenzimle)
	log.SetFlags(log.Ldate | log.Ltime | log.Lshortfile)
	log.Println("Tarix + vaxt + fayl gorsenir")

	// Bayraqlarin menalari:
	// log.Ldate      - tarix (2024/03/15)
	// log.Ltime      - vaxt (14:30:00)
	// log.Lmicroseconds - mikrosaniye deqiqliyi
	// log.Llongfile   - tam fayl yolu ve setir nomresi
	// log.Lshortfile  - qisa fayl adi ve setir nomresi
	// log.LUTC        - UTC vaxt
	// log.Lmsgprefix  - prefix mesajdan evvel

	// Fayla log yazmaq
	faylLog, err := os.OpenFile("app.log", os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0644)
	if err != nil {
		log.Fatal("Log fayli acilamadi:", err)
	}
	defer faylLog.Close()

	faylLogger := log.New(faylLog, "[FAYL] ", log.Ldate|log.Ltime)
	faylLogger.Println("Bu mesaj fayla yazildi")

	// log.Fatal - log yazib proqrami dayandirr (os.Exit(1))
	// log.Fatalf("Kritik xeta: %v", err)

	// log.Panic - log yazib panic verir
	// log.Panicf("Panic: %v", err)

	// =====================
	// 2. SLOG - STRUKTURLU LOG (Go 1.21+)
	// =====================
	// slog - modern, strukturlu, seviyyeli log paketi
	// JSON ve ya text formatinda log yazmaq imkani verir

	// Default slog (text format)
	slog.Info("Proqram basladi")
	slog.Warn("Disk sahesi azalir", "qalan_gb", 5)
	slog.Error("Baglanti qirildi", "server", "db-01", "sebeb", "timeout")

	// Acar-deyer cutleri ile
	slog.Info("Istifadeci daxil oldu",
		"istifadeci_id", 42,
		"ad", "Orkhan",
		"ip", "192.168.1.1",
	)

	// Seviyyeler:
	// slog.Debug() - detalli melumat (default olaraq gorsenmez)
	// slog.Info()  - umumi melumat
	// slog.Warn()  - xebardarliq
	// slog.Error() - xeta

	// -------------------------------------------
	// JSON formatinda log
	// -------------------------------------------
	jsonHandler := slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
		Level: slog.LevelDebug, // debug seviyyesinden baslat
	})
	jsonLogger := slog.New(jsonHandler)

	jsonLogger.Info("Sifaris yaradildi",
		"sifaris_id", "ORD-123",
		"meblgg", 99.99,
		"valyuta", "AZN",
	)
	// Cixis: {"time":"...","level":"INFO","msg":"Sifaris yaradildi","sifaris_id":"ORD-123",...}

	// -------------------------------------------
	// Text formatinda log
	// -------------------------------------------
	textHandler := slog.NewTextHandler(os.Stdout, &slog.HandlerOptions{
		Level: slog.LevelInfo,
	})
	textLogger := slog.New(textHandler)
	textLogger.Info("Text format", "acar", "deyer")

	// -------------------------------------------
	// Qrup ile log
	// -------------------------------------------
	jsonLogger.Info("Server basladi",
		slog.Group("server",
			slog.String("host", "localhost"),
			slog.Int("port", 8080),
		),
		slog.Group("tls",
			slog.Bool("aktiv", true),
		),
	)

	// -------------------------------------------
	// Logger-i default etmek
	// -------------------------------------------
	slog.SetDefault(jsonLogger) // bundan sonra slog.Info() JSON yazir
	slog.Info("Bu indi JSON formatindadir")

	// -------------------------------------------
	// With ile sabit saheler
	// -------------------------------------------
	reqLogger := jsonLogger.With("request_id", "abc-123", "metod", "GET")
	reqLogger.Info("Sorgu alindi")
	reqLogger.Info("Cavab gonderildi", "status", 200)
	// Her iki log-da request_id ve metod olacaq

	// Temizlik
	os.Remove("app.log")

	// TOVSIYELER:
	// - Production-da slog istifade edin (strukturlu, suretli)
	// - Development-de text, production-da JSON format
	// - Her zaman seviyyeleri duzgun istifade edin (Debug/Info/Warn/Error)
	// - Hassas melumatlari (parol, token) log-a YAZMAYIN
}
