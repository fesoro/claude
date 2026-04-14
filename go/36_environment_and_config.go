package main

import (
	"encoding/json"
	"fmt"
	"os"
	"strconv"
)

// ===============================================
// ENVIRONMENT VE KONFIQURASIYA
// ===============================================

// Proqramin ayarlarini idare etmeyin ferqli yollari

// -------------------------------------------
// 1. Konfiqurasiya strukturu
// -------------------------------------------
type Config struct {
	Server   ServerConfig   `json:"server"`
	Database DatabaseConfig `json:"database"`
	App      AppConfig      `json:"app"`
}

type ServerConfig struct {
	Host string `json:"host"`
	Port int    `json:"port"`
}

type DatabaseConfig struct {
	Host     string `json:"host"`
	Port     int    `json:"port"`
	User     string `json:"user"`
	Password string `json:"password"`
	DBName   string `json:"dbname"`
}

type AppConfig struct {
	Debug   bool   `json:"debug"`
	LogLevel string `json:"log_level"`
	Secret  string `json:"secret"`
}

// -------------------------------------------
// 2. Environment deyiskenlerinden oxumaq
// -------------------------------------------
func envdenOxu() Config {
	return Config{
		Server: ServerConfig{
			Host: getEnv("SERVER_HOST", "localhost"),
			Port: getEnvInt("SERVER_PORT", 8080),
		},
		Database: DatabaseConfig{
			Host:     getEnv("DB_HOST", "localhost"),
			Port:     getEnvInt("DB_PORT", 5432),
			User:     getEnv("DB_USER", "postgres"),
			Password: getEnv("DB_PASSWORD", ""),
			DBName:   getEnv("DB_NAME", "myapp"),
		},
		App: AppConfig{
			Debug:    getEnvBool("APP_DEBUG", false),
			LogLevel: getEnv("LOG_LEVEL", "info"),
			Secret:   getEnv("APP_SECRET", "deyisdir-meni"),
		},
	}
}

// Helper funksiyalar - default deyer ile env oxumaq
func getEnv(key, fallback string) string {
	if value, ok := os.LookupEnv(key); ok {
		return value
	}
	return fallback
}

func getEnvInt(key string, fallback int) int {
	if value, ok := os.LookupEnv(key); ok {
		if intVal, err := strconv.Atoi(value); err == nil {
			return intVal
		}
	}
	return fallback
}

func getEnvBool(key string, fallback bool) bool {
	if value, ok := os.LookupEnv(key); ok {
		if boolVal, err := strconv.ParseBool(value); err == nil {
			return boolVal
		}
	}
	return fallback
}

// -------------------------------------------
// 3. JSON faylindan oxumaq
// -------------------------------------------
func jsondenOxu(faylYolu string) (Config, error) {
	var config Config

	data, err := os.ReadFile(faylYolu)
	if err != nil {
		return config, fmt.Errorf("config fayli oxuna bilmedi: %w", err)
	}

	err = json.Unmarshal(data, &config)
	if err != nil {
		return config, fmt.Errorf("JSON parse xetasi: %w", err)
	}

	return config, nil
}

// -------------------------------------------
// 4. .env faylini oxumaq (sadə implementasiya)
// -------------------------------------------
func dotEnvOxu(faylYolu string) error {
	data, err := os.ReadFile(faylYolu)
	if err != nil {
		return err
	}

	setirler := splitLines(string(data))
	for _, setir := range setirler {
		// Bos setirler ve kommentleri kec
		if len(setir) == 0 || setir[0] == '#' {
			continue
		}

		// KEY=VALUE formatini parse et
		eqIdx := -1
		for i, c := range setir {
			if c == '=' {
				eqIdx = i
				break
			}
		}
		if eqIdx == -1 {
			continue
		}

		key := setir[:eqIdx]
		value := setir[eqIdx+1:]

		// Dirnaq isarelerini sil
		if len(value) >= 2 && (value[0] == '"' || value[0] == '\'') {
			value = value[1 : len(value)-1]
		}

		os.Setenv(key, value)
	}

	return nil
}

func splitLines(s string) []string {
	var lines []string
	line := ""
	for _, c := range s {
		if c == '\n' {
			lines = append(lines, line)
			line = ""
		} else if c != '\r' {
			line += string(c)
		}
	}
	if line != "" {
		lines = append(lines, line)
	}
	return lines
}

func main() {

	// -------------------------------------------
	// .env fayli yaratmaq (numune)
	// -------------------------------------------
	envContent := `# Server ayarlari
SERVER_HOST=0.0.0.0
SERVER_PORT=3000

# Database ayarlari
DB_HOST=localhost
DB_PORT=5432
DB_USER=myuser
DB_PASSWORD="gizli_parol"
DB_NAME=production_db

# App ayarlari
APP_DEBUG=false
LOG_LEVEL=warn
APP_SECRET="super-gizli-acar"
`
	os.WriteFile(".env.example", []byte(envContent), 0644)
	fmt.Println(".env.example fayli yaradildi")

	// -------------------------------------------
	// JSON config fayli yaratmaq (numune)
	// -------------------------------------------
	jsonConfig := Config{
		Server:   ServerConfig{Host: "localhost", Port: 8080},
		Database: DatabaseConfig{Host: "localhost", Port: 5432, User: "postgres", Password: "", DBName: "myapp"},
		App:      AppConfig{Debug: true, LogLevel: "debug", Secret: "dev-secret"},
	}
	jsonData, _ := json.MarshalIndent(jsonConfig, "", "  ")
	os.WriteFile("config.example.json", jsonData, 0644)
	fmt.Println("config.example.json fayli yaradildi")

	// -------------------------------------------
	// ENV-den config oxu
	// -------------------------------------------
	config := envdenOxu()
	fmt.Printf("\nENV Config: %+v\n", config)

	// -------------------------------------------
	// JSON-dan config oxu
	// -------------------------------------------
	jsonCfg, err := jsondenOxu("config.example.json")
	if err != nil {
		fmt.Println("JSON xeta:", err)
	} else {
		fmt.Printf("JSON Config: %+v\n", jsonCfg)
	}

	// -------------------------------------------
	// Butun env deyiskenlerini gostermek
	// -------------------------------------------
	fmt.Println("\nBezi muhit deyiskenleri:")
	muhum := []string{"HOME", "PATH", "USER", "SHELL", "GOPATH"}
	for _, key := range muhum {
		fmt.Printf("  %s = %s\n", key, os.Getenv(key))
	}

	// Temizlik
	os.Remove(".env.example")
	os.Remove("config.example.json")

	// TOVSIYELER:
	// - Production-da env deyiskenleri istifade edin (.env faylini git-e COMMIT ETMEYIN!)
	// - Default deyerler her zaman olsun
	// - Gizli melumatlari (parol, token) HEC VAXT koda yazmayin
	// - 12-Factor App prinsipini oxuyun: https://12factor.net/config
	// - Populyar kitabxanalar: viper (spf13/viper), envconfig (kelseyhightower)
}
