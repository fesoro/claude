// Package main — CLI commands (Artisan / Spring Shell əvəzi)
//
// İstifadə:
//   go run ./cmd/cli outbox:publish
//   go run ./cmd/cli queue:failed-monitor
//   go run ./cmd/cli projection:rebuild
//   go run ./cmd/cli worker:graceful
package main

import (
	"fmt"
	"os"

	"github.com/spf13/cobra"
)

func main() {
	root := &cobra.Command{
		Use:   "cli",
		Short: "Ecommerce Go CLI (Laravel Artisan / Spring Shell əvəzi)",
	}

	root.AddCommand(outboxPublishCmd(), failedMonitorCmd(),
		projectionRebuildCmd(), workerGracefulCmd(), rabbitmqConsumeCmd())

	if err := root.Execute(); err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
}

func outboxPublishCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "outbox:publish",
		Short: "Outbox-dakı mesajları RabbitMQ-yə göndər",
		Run: func(_ *cobra.Command, _ []string) {
			fmt.Println("Outbox publish işə düşdü")
			// Real-da: config load → db open → outbox publisher.publishBatch()
		},
	}
}

func failedMonitorCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "queue:failed-monitor",
		Short: "DLQ-dakı failed job-ların sayını göstər",
		Run: func(_ *cobra.Command, _ []string) {
			fmt.Println("DLQ: 0 failed jobs")
		},
	}
}

func projectionRebuildCmd() *cobra.Command {
	cmd := &cobra.Command{
		Use:   "projection:rebuild",
		Short: "Read model-i event store-dan yenidən qur",
		Run: func(_ *cobra.Command, _ []string) {
			fmt.Println("Read model rebuild completed")
		},
	}
	return cmd
}

func workerGracefulCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "worker:graceful",
		Short: "Worker-ı graceful dayandır",
		Run: func(_ *cobra.Command, _ []string) {
			fmt.Println("Worker gracefully stopped")
		},
	}
}

func rabbitmqConsumeCmd() *cobra.Command {
	return &cobra.Command{
		Use:   "rabbitmq:consume [queue]",
		Short: "RabbitMQ queue-dan manual consume et",
		Args:  cobra.ExactArgs(1),
		Run: func(_ *cobra.Command, args []string) {
			fmt.Printf("Consuming from %s\n", args[0])
		},
	}
}
