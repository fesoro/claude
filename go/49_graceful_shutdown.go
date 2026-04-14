package main

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"sync"
	"syscall"
	"time"
)

// ===============================================
// GRACEFUL SHUTDOWN - DUZGUN BAGLANMA
// ===============================================

// Proqram baglananda:
// - Yeni sorqulari qebul etme
// - Movcud sorqulari tamamla
// - Database baglantilarini bagla
// - Fayllari bagla
// - Temizlik et
// Buna graceful shutdown deyilir

// -------------------------------------------
// 1. Sadə signal handling
// -------------------------------------------
func sadeSignalOrnek() {
	fmt.Println("=== Sadə Signal ===")

	// Ctrl+C (SIGINT) ve ya SIGTERM signal-ini tutmaq
	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)

	go func() {
		sig := <-sigCh
		fmt.Println("\nSignal alindi:", sig)
		fmt.Println("Temizlik edirem...")
		time.Sleep(500 * time.Millisecond)
		fmt.Println("Proqram baglanir")
		os.Exit(0)
	}()
}

// -------------------------------------------
// 2. HTTP Server graceful shutdown
// -------------------------------------------
func httpServerOrnek() {
	mux := http.NewServeMux()

	mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		// Yava sorgu simulyasiyasi
		time.Sleep(2 * time.Second)
		fmt.Fprintf(w, "Salam! Vaxt: %s", time.Now().Format("15:04:05"))
	})

	mux.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		fmt.Fprint(w, "OK")
	})

	server := &http.Server{
		Addr:         ":8080",
		Handler:      mux,
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 15 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	// Server-i ayri goroutine-de baslat
	go func() {
		log.Printf("Server :8080 portunda isleyir")
		if err := server.ListenAndServe(); err != http.ErrServerClosed {
			log.Fatalf("Server xetasi: %v", err)
		}
	}()

	// Signal gozle
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	sig := <-quit

	log.Printf("Signal alindi: %v. Graceful shutdown baslanir...", sig)

	// Shutdown ucun 30 saniye vaxt ver
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	// Yeni baglantilari qebul etme, movcudlari tamamla
	if err := server.Shutdown(ctx); err != nil {
		log.Fatalf("Server shutdown xetasi: %v", err)
	}

	log.Println("Server duzgun baglandi")
}

// -------------------------------------------
// 3. Tam graceful shutdown (server + worker + DB)
// -------------------------------------------

// Application - butun komponentleri idare edir
type Application struct {
	server *http.Server
	wg     sync.WaitGroup
	stopCh chan struct{}
}

func NewApplication() *Application {
	app := &Application{
		stopCh: make(chan struct{}),
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		fmt.Fprint(w, "OK")
	})

	app.server = &http.Server{
		Addr:    ":8080",
		Handler: mux,
	}

	return app
}

// Background worker baslat
func (app *Application) StartWorker(ad string, is func()) {
	app.wg.Add(1)
	go func() {
		defer app.wg.Done()
		defer log.Printf("Worker '%s' dayandi", ad)

		log.Printf("Worker '%s' basladi", ad)
		for {
			select {
			case <-app.stopCh:
				return
			default:
				is()
				time.Sleep(1 * time.Second)
			}
		}
	}()
}

// Duzgun baglanma
func (app *Application) Shutdown(ctx context.Context) error {
	log.Println("1. Yeni sorqulari dayandiriram...")
	if err := app.server.Shutdown(ctx); err != nil {
		return fmt.Errorf("server shutdown: %w", err)
	}

	log.Println("2. Worker-leri dayandiriram...")
	close(app.stopCh) // butun worker-lere signal gonder

	// Worker-lerin bitmesini gozle (timeout ile)
	doneCh := make(chan struct{})
	go func() {
		app.wg.Wait()
		close(doneCh)
	}()

	select {
	case <-doneCh:
		log.Println("3. Butun worker-ler dayandi")
	case <-ctx.Done():
		return fmt.Errorf("worker-ler vaxtinda dayanmadi")
	}

	log.Println("4. Database baglantilari baglanir...")
	// db.Close()

	log.Println("5. Temizlik tamamlandi!")
	return nil
}

func main() {
	app := NewApplication()

	// Worker-leri baslat
	app.StartWorker("email-gonderici", func() {
		log.Println("[email] novbeni yoxlayiram...")
	})
	app.StartWorker("cache-yenileyici", func() {
		log.Println("[cache] yenileyirem...")
	})

	// Server-i baslat
	go func() {
		log.Println("Server :8080 portunda isleyir")
		if err := app.server.ListenAndServe(); err != http.ErrServerClosed {
			log.Fatalf("Server xetasi: %v", err)
		}
	}()

	// -------------------------------------------
	// Signal gozle
	// -------------------------------------------
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	sig := <-quit
	log.Printf("\nSignal alindi: %v", sig)

	// 30 saniye vaxt ver
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	if err := app.Shutdown(ctx); err != nil {
		log.Printf("Shutdown xetasi: %v", err)
		os.Exit(1)
	}

	log.Println("Proqram duzgun baglandi")

	// MUHUM QEYDLER:
	// - Production-da HER ZAMAN graceful shutdown istifade edin
	// - Kubernetes/Docker SIGTERM gonderir, sonra SIGKILL (30 san)
	// - Health check endpoint-i (/health) olsun
	// - Database baglantilarini en sonda baglayin
	// - Worker-ler ucun context istifade edin
	// - Shutdown timeout-u Kubernetes terminationGracePeriodSeconds-den kicik olsun
}
