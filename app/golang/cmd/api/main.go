// Laravel: public/index.php → bootstrap → routes/api.php
// Spring: EcommerceApplication.java → @SpringBootApplication
// Go: cmd/api/main.go — explicit, manual wiring (no auto-DI)
package main

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/orkhan/ecommerce/internal/config"
	"github.com/orkhan/ecommerce/internal/server"
)

func main() {
	// 1. Config yüklə
	configPath := os.Getenv("APP_CONFIG")
	if configPath == "" {
		configPath = "configs/config.yaml"
	}
	cfg, err := config.Load(configPath)
	if err != nil {
		slog.Error("config yüklənmədi", "err", err)
		os.Exit(1)
	}

	// 2. Structured logger (Spring logback-spring.xml + LogstashEncoder əvəzi)
	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
		Level: slog.LevelInfo,
	}))
	slog.SetDefault(logger)

	// 3. Application qur — bütün dependency-lər manual wire olunur
	// Laravel: AppServiceProvider, DomainServiceProvider
	// Spring: @SpringBootApplication + @ComponentScan
	// Go: bütün konstruksiya bir yerdə — açıq və izləməsi asan
	app, err := server.NewApplication(cfg, logger)
	if err != nil {
		slog.Error("application qurulmadı", "err", err)
		os.Exit(1)
	}
	defer app.Close()

	// 4. HTTP server
	srv := &http.Server{
		Addr:         fmt.Sprintf(":%d", cfg.App.Port),
		Handler:      app.Router(),
		ReadTimeout:  10 * time.Second,
		WriteTimeout: 30 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	go func() {
		slog.Info("HTTP server başladı", "addr", srv.Addr)
		if err := srv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			slog.Error("server xətası", "err", err)
			os.Exit(1)
		}
	}()

	// 5. Graceful shutdown — SIGTERM/SIGINT-də 30s ərzində bütün request-lər bitsin
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	slog.Info("shutdown başladı")
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	if err := srv.Shutdown(ctx); err != nil {
		slog.Error("graceful shutdown failed", "err", err)
	}
	slog.Info("server dayandı")
}
