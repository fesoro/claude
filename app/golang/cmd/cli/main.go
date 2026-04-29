// Package main — CLI commands (Artisan / Spring Shell əvəzi)
//
// İstifadə:
//   go run ./cmd/cli outbox:publish
//   go run ./cmd/cli queue:failed-monitor
//   go run ./cmd/cli projection:rebuild
//   go run ./cmd/cli worker:graceful
//   go run ./cmd/cli rabbitmq:consume <queue>
package main

import (
	"context"
	"fmt"
	"log/slog"
	"os"
	"os/signal"
	"syscall"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
	"github.com/spf13/cobra"

	"github.com/orkhan/ecommerce/internal/config"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/database"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/messaging"
)

func main() {
	root := &cobra.Command{
		Use:   "cli",
		Short: "Ecommerce Go CLI (Laravel Artisan / Spring Shell əvəzi)",
	}

	root.AddCommand(
		outboxPublishCmd(),
		failedMonitorCmd(),
		projectionRebuildCmd(),
		workerGracefulCmd(),
		rabbitmqConsumeCmd(),
	)

	if err := root.Execute(); err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
}

// loadConfig — configs/config.yaml-ı yükləyir
func loadConfig() (*config.Config, error) {
	path := os.Getenv("CONFIG_PATH")
	if path == "" {
		path = "configs/config.yaml"
	}
	return config.Load(path)
}

// outboxPublishCmd — pending outbox mesajlarını RabbitMQ-yə göndərir
func outboxPublishCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "outbox:publish",
		Short: "Outbox-dakı pending mesajları RabbitMQ-yə göndər",
		RunE: func(_ *cobra.Command, _ []string) error {
			cfg, err := loadConfig()
			if err != nil {
				return fmt.Errorf("config yüklənmədi: %w", err)
			}

			dbs, err := database.Open(cfg.Database)
			if err != nil {
				return fmt.Errorf("DB açılmadı: %w", err)
			}
			defer dbs.Close()

			conn, err := amqp.Dial(cfg.RabbitMQ.URL)
			if err != nil {
				return fmt.Errorf("RabbitMQ bağlantısı: %w", err)
			}
			defer conn.Close()

			pub, err := messaging.NewPublisher(conn)
			if err != nil {
				return fmt.Errorf("publisher yaradılmadı: %w", err)
			}
			defer pub.Close()

			ctx := context.Background()
			published := 0

			repos := []struct {
				name string
				repo *messaging.OutboxRepository
			}{
				{"user", messaging.NewOutboxRepository(dbs.User)},
				{"product", messaging.NewOutboxRepository(dbs.Product)},
				{"order", messaging.NewOutboxRepository(dbs.Order)},
				{"payment", messaging.NewOutboxRepository(dbs.Payment)},
			}

			for _, r := range repos {
				msgs, err := r.repo.FindPending(100)
				if err != nil {
					slog.Error("pending mesajlar oxunmadı", "db", r.name, "err", err)
					continue
				}
				for _, m := range msgs {
					if err := pub.Publish(ctx, m.RoutingKey, m.Payload, nil); err != nil {
						slog.Error("publish xətası", "id", m.ID, "err", err)
						continue
					}
					if err := r.repo.MarkPublished(m.ID); err != nil {
						slog.Error("markPublished xətası", "id", m.ID, "err", err)
					}
					published++
				}
			}

			slog.Info("outbox:publish tamamlandı", "published", published)
			return nil
		},
	}
}

// failedMonitorCmd — DLQ-dakı failed job-ların sayını göstərir
func failedMonitorCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "queue:failed-monitor",
		Short: "DLQ-dakı unretried failed job-ların sayını göstər",
		RunE: func(_ *cobra.Command, _ []string) error {
			cfg, err := loadConfig()
			if err != nil {
				return fmt.Errorf("config yüklənmədi: %w", err)
			}

			dbs, err := database.Open(cfg.Database)
			if err != nil {
				return fmt.Errorf("DB açılmadı: %w", err)
			}
			defer dbs.Close()

			dlqRepo := messaging.NewDLQRepository(dbs.Order)
			count, err := dlqRepo.FindUnretriedCount()
			if err != nil {
				return fmt.Errorf("DLQ sorğusu: %w", err)
			}

			fmt.Printf("DLQ: %d unretried failed job\n", count)
			return nil
		},
	}
}

// projectionRebuildCmd — order_read_models cədvəlini outbox event-lərindən yenidən qurur
func projectionRebuildCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "projection:rebuild",
		Short: "order_read_models cədvəlini sıfırdan yenidən qur",
		RunE: func(_ *cobra.Command, _ []string) error {
			cfg, err := loadConfig()
			if err != nil {
				return fmt.Errorf("config yüklənmədi: %w", err)
			}

			dbs, err := database.Open(cfg.Database)
			if err != nil {
				return fmt.Errorf("DB açılmadı: %w", err)
			}
			defer dbs.Close()

			// Read model cədvəlini sıfırla
			if err := dbs.Order.Exec("TRUNCATE TABLE order_read_models").Error; err != nil {
				return fmt.Errorf("truncate xətası: %w", err)
			}

			slog.Info("order_read_models sıfırlandı")

			// Outbox-dan publish olunmuş order event-lərini oxu
			outboxRepo := messaging.NewOutboxRepository(dbs.Order)
			msgs, err := outboxRepo.FindPending(10000)
			if err != nil {
				return fmt.Errorf("outbox oxunmadı: %w", err)
			}

			slog.Info("projection:rebuild tamamlandı — event store-dan yenidən qurmaq üçün consumer başladın",
				"pending_events", len(msgs))
			fmt.Println("Read model sıfırlandı. Tam rebuild üçün outbox:publish + consumer işə salın.")
			return nil
		},
	}
}

// workerGracefulCmd — SIGTERM göndərərək işləyən worker prosesini graceful dayandırır
func workerGracefulCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "worker:graceful",
		Short: "Bu prosesə SIGTERM göndər — graceful shutdown başlar",
		RunE: func(_ *cobra.Command, _ []string) error {
			slog.Info("SIGTERM özünə göndərilir — graceful shutdown başlayır")
			p, err := os.FindProcess(os.Getpid())
			if err != nil {
				return err
			}
			return p.Signal(syscall.SIGTERM)
		},
	}
}

// rabbitmqConsumeCmd — müəyyən queue-dan mesaj oxuyub loglar
func rabbitmqConsumeCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "rabbitmq:consume [queue]",
		Short: "RabbitMQ queue-dan mesajları oxu və logla (Ctrl+C ilə dayandır)",
		Args:  cobra.ExactArgs(1),
		RunE: func(_ *cobra.Command, args []string) error {
			queue := args[0]

			cfg, err := loadConfig()
			if err != nil {
				return fmt.Errorf("config yüklənmədi: %w", err)
			}

			conn, err := amqp.Dial(cfg.RabbitMQ.URL)
			if err != nil {
				return fmt.Errorf("RabbitMQ bağlantısı: %w", err)
			}
			defer conn.Close()

			ch, err := conn.Channel()
			if err != nil {
				return fmt.Errorf("channel: %w", err)
			}
			defer ch.Close()

			msgs, err := ch.Consume(queue, "", false, false, false, false, nil)
			if err != nil {
				return fmt.Errorf("consume: %w", err)
			}

			ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
			defer stop()

			slog.Info("Consume başladı", "queue", queue)
			fmt.Printf("Consuming from '%s' — Ctrl+C ilə dayandır\n", queue)

			timeout := time.NewTimer(30 * time.Second)
			defer timeout.Stop()

			for {
				select {
				case <-ctx.Done():
					fmt.Println("\nDayandırıldı.")
					return nil
				case <-timeout.C:
					fmt.Println("30 saniyə mesaj gəlmədi — çıxılır.")
					return nil
				case msg, ok := <-msgs:
					if !ok {
						return nil
					}
					timeout.Reset(30 * time.Second)
					slog.Info("Mesaj alındı",
						"queue", queue,
						"routing_key", msg.RoutingKey,
						"body", string(msg.Body),
					)
					_ = msg.Ack(false)
				}
			}
		},
	}
}
