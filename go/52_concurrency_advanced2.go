package main

import (
	"fmt"
	"runtime"
	"time"
)

// ===============================================
// CONCURRENCY - IRELILEMIS MOVZULAR 2
// ===============================================

func main() {

	// -------------------------------------------
	// 1. GOROUTINE vs THREAD
	// -------------------------------------------
	fmt.Println("=== Goroutine vs Thread ===")

	fmt.Println(`
+-------------------+------------------------+------------------------+
| Xususiyyet        | Goroutine              | OS Thread              |
+-------------------+------------------------+------------------------+
| Yaddas            | ~2-8 KB (stack)        | ~1-8 MB (stack)        |
| Yaranma sureti    | Microsaniyeler         | Millisaniyeler         |
| Planlasdirici     | Go runtime (M:N)       | OS kernel              |
| Kontekst kecid    | ~tens of ns            | ~microseconds          |
| Sayi              | Yuz minlerle           | Minlerle               |
| Idare              | Go runtime             | OS                     |
| Kommunikasiya     | Channel (tehlukesiz)   | Shared memory (riskli) |
+-------------------+------------------------+------------------------+

Go M:N planlasdirma modeli:
- M goroutine -> N OS thread uzerine palanir
- GOMAXPROCS ile nece OS thread istifade olunacagini teyinle
`)

	fmt.Println("GOMAXPROCS:", runtime.GOMAXPROCS(0)) // cari deyer
	fmt.Println("NumCPU:", runtime.NumCPU())
	fmt.Println("NumGoroutine:", runtime.NumGoroutine())

	// Coxlu goroutine yaratmaq ucuz
	for i := 0; i < 10000; i++ {
		go func() {
			time.Sleep(10 * time.Millisecond)
		}()
	}
	fmt.Println("10000 goroutine yaradildi, sayi:", runtime.NumGoroutine())
	time.Sleep(50 * time.Millisecond)

	// -------------------------------------------
	// 2. NON-BLOCKING CHANNEL OPERATIONS
	// -------------------------------------------
	fmt.Println("\n=== Non-Blocking Channel ===")

	mesajlar := make(chan string)
	sinyallar := make(chan bool)

	// Non-blocking oxuma (default ile)
	select {
	case msg := <-mesajlar:
		fmt.Println("Mesaj alindi:", msg)
	default:
		fmt.Println("Mesaj yoxdur (blok etmedi)")
	}

	// Non-blocking yazma
	msg := "salam"
	select {
	case mesajlar <- msg:
		fmt.Println("Mesaj gonderildi")
	default:
		fmt.Println("Mesaj gonderile bilmedi (receiver yoxdur)")
	}

	// Non-blocking coxlu kanal
	select {
	case msg := <-mesajlar:
		fmt.Println("Mesaj:", msg)
	case sig := <-sinyallar:
		fmt.Println("Sinyal:", sig)
	default:
		fmt.Println("Hec bir kanalda melumat yoxdur")
	}

	// -------------------------------------------
	// 3. TIMERS
	// -------------------------------------------
	fmt.Println("\n=== Timers ===")

	// Bir defəlik timer
	timer1 := time.NewTimer(500 * time.Millisecond)
	fmt.Println("Timer baslanir...")

	<-timer1.C // timer bitene qeder gozle
	fmt.Println("Timer 1 bitdi")

	// Timer-i legv etmek
	timer2 := time.NewTimer(1 * time.Second)
	go func() {
		<-timer2.C
		fmt.Println("Timer 2 bitdi") // hec vaxt cap olunmayacaq
	}()
	dayandi := timer2.Stop()
	if dayandi {
		fmt.Println("Timer 2 legv edildi")
	}

	// time.After - qisa yol
	fmt.Println("time.After gozleyir...")
	<-time.After(200 * time.Millisecond)
	fmt.Println("time.After bitdi")

	// Timer ile timeout pattern
	ch := make(chan string, 1)
	go func() {
		time.Sleep(300 * time.Millisecond)
		ch <- "netice"
	}()

	select {
	case n := <-ch:
		fmt.Println("Netice:", n)
	case <-time.After(1 * time.Second):
		fmt.Println("Timeout!")
	}

	// -------------------------------------------
	// 4. TICKERS (tekrarlanan timer)
	// -------------------------------------------
	fmt.Println("\n=== Tickers ===")

	// Her 200ms-de bir isleyen ticker
	ticker := time.NewTicker(200 * time.Millisecond)
	done := make(chan bool)

	go func() {
		for {
			select {
			case <-done:
				return
			case t := <-ticker.C:
				fmt.Println("Tick:", t.Format("15:04:05.000"))
			}
		}
	}()

	time.Sleep(1 * time.Second) // 1 saniye gozle (5 tick olacaq)
	ticker.Stop()
	done <- true
	fmt.Println("Ticker dayandi")

	// -------------------------------------------
	// 5. STATEFUL GOROUTINES
	// -------------------------------------------
	fmt.Println("\n=== Stateful Goroutines ===")

	// Mutex yerine, bir goroutine state-i idare edir
	// Diger goroutine-ler channel vasitesile sorgu gonderir

	type readOp struct {
		key  string
		resp chan string
	}
	type writeOp struct {
		key   string
		value string
		resp  chan bool
	}

	reads := make(chan readOp)
	writes := make(chan writeOp)

	// State idare eden goroutine (tek sahibi)
	go func() {
		state := map[string]string{}
		for {
			select {
			case read := <-reads:
				read.resp <- state[read.key]
			case write := <-writes:
				state[write.key] = write.value
				write.resp <- true
			}
		}
	}()

	// Yazma
	writeResp := make(chan bool)
	writes <- writeOp{key: "dil", value: "Go", resp: writeResp}
	<-writeResp
	fmt.Println("Yazildi")

	writes <- writeOp{key: "versiya", value: "1.22", resp: writeResp}
	<-writeResp

	// Oxuma
	readResp := make(chan string)
	reads <- readOp{key: "dil", resp: readResp}
	fmt.Println("Oxundu:", <-readResp) // Go

	reads <- readOp{key: "versiya", resp: readResp}
	fmt.Println("Oxundu:", <-readResp) // 1.22

	// USTUNLUK: Data race mumkun deyil - yalniz bir goroutine state-e toxunur
	// Mutex-e alternative olaraq daha temiz ola biler

	// -------------------------------------------
	// 6. Goroutine leak-den qacinmaq
	// -------------------------------------------
	fmt.Println("\n=== Goroutine Leak Qarsisini Alma ===")

	// YANLIS - goroutine hec vaxt bitmeyecek (leak)
	// go func() {
	//     val := <-ch  // hec vaxt deyer gelmese, ebedi gozleyir
	// }()

	// DOGRU - context ve ya done channel ile legv etmek
	doneCh := make(chan struct{})
	dataCh := make(chan int)

	go func() {
		select {
		case val := <-dataCh:
			fmt.Println("Deyer:", val)
		case <-doneCh:
			fmt.Println("Legv edildi, goroutine bitmis")
			return
		}
	}()

	close(doneCh) // goroutine-i temiz dayandirır
	time.Sleep(50 * time.Millisecond)
	fmt.Println("Goroutine sayi:", runtime.NumGoroutine())
}
