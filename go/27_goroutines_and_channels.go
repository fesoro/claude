package main

import (
	"fmt"
	"sync"
	"time"
)

// ===============================================
// GOROUTINE VE KANALLAR (CONCURRENCY)
// ===============================================

// Goroutine - Go-nun en guclu xususiyyeti
// Yungul thread (mux goroutine = 1 OS thread)
// "go" acar sozu ile bashlanir

// -------------------------------------------
// 1. Goroutine yaratma
// -------------------------------------------
func salam(ad string) {
	for i := 0; i < 3; i++ {
		fmt.Printf("Salam %s (i=%d)\n", ad, i)
		time.Sleep(100 * time.Millisecond)
	}
}

// -------------------------------------------
// 2. Kanala yazan funksiya
// -------------------------------------------
func kvadratHesabla(n int, ch chan int) {
	ch <- n * n // kanala yaz
}

// -------------------------------------------
// 3. Worker pattern
// -------------------------------------------
func worker(id int, isler <-chan int, neticeler chan<- int) {
	for is := range isler {
		fmt.Printf("Worker %d isi %d isleyir\n", id, is)
		time.Sleep(100 * time.Millisecond)
		neticeler <- is * 2
	}
}

func main() {

	// -------------------------------------------
	// 1. Sadə Goroutine
	// -------------------------------------------
	go salam("Eli")   // yeni goroutine-de isleyir
	go salam("Veli")  // basqa goroutine-de isleyir
	salam("Orkhan")   // main goroutine-de isleyir

	// Main biterse butun goroutine-ler dayanir!
	// Ona gore gozlemek lazimdir

	// -------------------------------------------
	// 2. WaitGroup ile gozleme
	// -------------------------------------------
	var wg sync.WaitGroup

	for i := 1; i <= 3; i++ {
		wg.Add(1) // goroutine sayini artir
		go func(num int) {
			defer wg.Done() // bitende sayini azalt
			fmt.Printf("Goroutine %d isleyir\n", num)
			time.Sleep(100 * time.Millisecond)
		}(i)
	}

	wg.Wait() // butun goroutine-ler bitene qeder gozle
	fmt.Println("Butun goroutine-ler bitdi")

	// -------------------------------------------
	// 3. KANALLAR (Channels)
	// -------------------------------------------
	// Goroutine-ler arasi melumat gondermek ucun
	// make(chan tip) ile yaradilir

	// a) Buffersiz kanal (senkron - gonderici qebuledici gozleyir)
	ch := make(chan string)

	go func() {
		ch <- "Salam kanaldan!" // kanala yaz
	}()

	mesaj := <-ch // kanaldan oxu (gozleyir)
	fmt.Println(mesaj)

	// b) Bufferli kanal (asinxron - buffer dolana qeder blok etmir)
	bufCh := make(chan int, 3) // 3 elementlik buffer
	bufCh <- 1
	bufCh <- 2
	bufCh <- 3
	// bufCh <- 4  // BLOK! buffer dolu

	fmt.Println(<-bufCh) // 1
	fmt.Println(<-bufCh) // 2
	fmt.Println(<-bufCh) // 3

	// -------------------------------------------
	// 4. Kanal yonleri (directional channels)
	// -------------------------------------------
	// chan<- int  - yalniz yazma
	// <-chan int  - yalniz oxuma
	// chan int    - her ikisi

	// Misal: kvadrat hesablama
	kvadratCh := make(chan int, 5)

	go kvadratHesabla(3, kvadratCh)
	go kvadratHesabla(5, kvadratCh)
	go kvadratHesabla(7, kvadratCh)

	fmt.Println("Kvadrat:", <-kvadratCh)
	fmt.Println("Kvadrat:", <-kvadratCh)
	fmt.Println("Kvadrat:", <-kvadratCh)

	// -------------------------------------------
	// 5. Kanali baglamaq ve range ile oxumaq
	// -------------------------------------------
	ededCh := make(chan int)

	go func() {
		for i := 1; i <= 5; i++ {
			ededCh <- i
		}
		close(ededCh) // kanali bagla - range-in dayanmasi ucun
	}()

	for n := range ededCh { // kanal baglanana qeder oxu
		fmt.Println("Oxundu:", n)
	}

	// -------------------------------------------
	// 6. SELECT - bir nece kanali dinleme
	// -------------------------------------------
	// switch kimi, amma kanallar ucun
	ch1 := make(chan string)
	ch2 := make(chan string)

	go func() {
		time.Sleep(100 * time.Millisecond)
		ch1 <- "birinci"
	}()

	go func() {
		time.Sleep(50 * time.Millisecond)
		ch2 <- "ikinci"
	}()

	// Hansi kanal evvel hazir olsa onu oxu
	for i := 0; i < 2; i++ {
		select {
		case msg1 := <-ch1:
			fmt.Println("ch1:", msg1)
		case msg2 := <-ch2:
			fmt.Println("ch2:", msg2)
		}
	}

	// Select ile timeout
	timeoutCh := make(chan string)

	go func() {
		time.Sleep(2 * time.Second) // cox gec cavab verir
		timeoutCh <- "gec cavab"
	}()

	select {
	case msg := <-timeoutCh:
		fmt.Println("Cavab:", msg)
	case <-time.After(500 * time.Millisecond):
		fmt.Println("Timeout! Cavab gelmedi")
	}

	// -------------------------------------------
	// 7. Worker Pool pattern
	// -------------------------------------------
	isler := make(chan int, 10)
	neticeler := make(chan int, 10)

	// 3 worker baslat
	for w := 1; w <= 3; w++ {
		go worker(w, isler, neticeler)
	}

	// 5 is gonder
	for j := 1; j <= 5; j++ {
		isler <- j
	}
	close(isler)

	// Neticeleri oxu
	for r := 1; r <= 5; r++ {
		fmt.Println("Netice:", <-neticeler)
	}

	// -------------------------------------------
	// 8. Mutex - paylasilan melumati qoruma
	// -------------------------------------------
	var mu sync.Mutex
	sayici := 0
	var wg2 sync.WaitGroup

	for i := 0; i < 1000; i++ {
		wg2.Add(1)
		go func() {
			defer wg2.Done()
			mu.Lock()   // kilitle
			sayici++
			mu.Unlock() // kilidi ac
		}()
	}

	wg2.Wait()
	fmt.Println("Sayici:", sayici) // 1000 (mutex olmasaydi yanlis ola bilerdi)
}
