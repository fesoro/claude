package main

import (
	"bufio"
	"fmt"
	"log"
	"net"
	"strings"
	"time"
)

// ===============================================
// TCP SERVER
// ===============================================

// TCP - asagi seviyyeli network protokolu
// HTTP, WebSocket, database baglantilari TCP uzerinde isleyir
// Xususi protokollar, oyun serverleri, chat ucun istifade olunur

// -------------------------------------------
// Client-i idare eden funksiya
// -------------------------------------------
func handleConnection(conn net.Conn) {
	defer conn.Close()

	clientAddr := conn.RemoteAddr().String()
	log.Printf("Yeni baglanti: %s", clientAddr)

	// Xos geldin mesaji
	conn.Write([]byte("Salam! Go TCP Server-e xos geldiniz.\n"))
	conn.Write([]byte("Emrler: /vaxt, /ad <adiniz>, /cix\n"))

	scanner := bufio.NewScanner(conn)
	for scanner.Scan() {
		mesaj := strings.TrimSpace(scanner.Text())

		if mesaj == "" {
			continue
		}

		log.Printf("[%s] Alindi: %s", clientAddr, mesaj)

		// Emrleri isleyir
		switch {
		case mesaj == "/vaxt":
			cavab := fmt.Sprintf("Server vaxti: %s\n", time.Now().Format("15:04:05"))
			conn.Write([]byte(cavab))

		case strings.HasPrefix(mesaj, "/ad "):
			ad := strings.TrimPrefix(mesaj, "/ad ")
			cavab := fmt.Sprintf("Salam, %s!\n", ad)
			conn.Write([]byte(cavab))

		case mesaj == "/cix":
			conn.Write([]byte("Salamat qalin!\n"))
			log.Printf("[%s] Ayrildi", clientAddr)
			return

		default:
			cavab := fmt.Sprintf("Echo: %s\n", mesaj)
			conn.Write([]byte(cavab))
		}
	}

	log.Printf("[%s] Baglanti baglandi", clientAddr)
}

func main() {

	// -------------------------------------------
	// 1. TCP SERVER
	// -------------------------------------------
	port := ":9090"

	listener, err := net.Listen("tcp", port)
	if err != nil {
		log.Fatal("Dinleme xetasi:", err)
	}
	defer listener.Close()

	log.Printf("TCP Server %s portunda isleyir...", port)
	log.Println("Test ucun: telnet localhost 9090")
	log.Println("Veya:     nc localhost 9090")

	for {
		conn, err := listener.Accept() // yeni baglanti gozle
		if err != nil {
			log.Println("Accept xetasi:", err)
			continue
		}
		go handleConnection(conn) // her client ayri goroutine-de
	}

	// -------------------------------------------
	// TCP CLIENT ornegi (ayri proqramda)
	// -------------------------------------------
	/*
		conn, err := net.Dial("tcp", "localhost:9090")
		if err != nil {
			log.Fatal("Qosulma xetasi:", err)
		}
		defer conn.Close()

		// Server-den oxu
		cavab := make([]byte, 1024)
		n, _ := conn.Read(cavab)
		fmt.Print(string(cavab[:n]))

		// Server-e yaz
		conn.Write([]byte("/vaxt\n"))
		n, _ = conn.Read(cavab)
		fmt.Print(string(cavab[:n]))
	*/

	// -------------------------------------------
	// Timeout ile TCP client
	// -------------------------------------------
	/*
		conn, err := net.DialTimeout("tcp", "localhost:9090", 5*time.Second)
		if err != nil {
			log.Fatal(err)
		}
		conn.SetDeadline(time.Now().Add(10 * time.Second)) // oxuma/yazma timeout
		conn.SetReadDeadline(time.Now().Add(5 * time.Second))  // yalniz oxuma
		conn.SetWriteDeadline(time.Now().Add(5 * time.Second)) // yalniz yazma
	*/
}
