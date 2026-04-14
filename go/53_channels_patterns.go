package main

import (
	"fmt"
	"math/rand"
	"sync"
	"time"
)

// ===============================================
// CHANNEL PATTERN-LERI
// ===============================================

// Go-da channel-ler goroutine-ler arasi kommunikasiya ucundur.
// Bu dersde channel-lerin real layihelerde istifade olunan
// muxteli pattern-lerini (sablonlarini) oyreneceyik.

// -------------------------------------------
// 1. Pipeline pattern (merhele zenciri)
// -------------------------------------------

// Pipeline - melumat bir nece merheleden kecir, her merhele bir funksiya,
// her funksiya channel-den oxuyur ve novbeti channel-e yazir.

// Eded yaradici - birinci merhele
func ededYarat(ededler ...int) <-chan int {
	cixis := make(chan int)
	go func() {
		defer close(cixis)
		for _, n := range ededler {
			cixis <- n
		}
	}()
	return cixis
}

// Kvadrata cevir - ikinci merhele
func kvadrat(giris <-chan int) <-chan int {
	cixis := make(chan int)
	go func() {
		defer close(cixis)
		for n := range giris {
			cixis <- n * n
		}
	}()
	return cixis
}

// Ikiqat et - ucuncu merhele
func ikiqat(giris <-chan int) <-chan int {
	cixis := make(chan int)
	go func() {
		defer close(cixis)
		for n := range giris {
			cixis <- n * 2
		}
	}()
	return cixis
}

// -------------------------------------------
// 2. Fan-out / Fan-in pattern
// -------------------------------------------

// Fan-out: bir channel-den bir nece goroutine oxuyur (isi paylasir)
// Fan-in: bir nece channel-den bir channel-e yigilir

// Agir is simulyasiyasi
func agirIs(id int, giris <-chan int, cixis chan<- string) {
	for n := range giris {
		time.Sleep(time.Duration(rand.Intn(100)) * time.Millisecond)
		cixis <- fmt.Sprintf("Isci-%d: %d isledi", id, n)
	}
}

// Fan-in: bir nece channel-i birlesdirir
func fanIn(kanallar ...<-chan string) <-chan string {
	var wg sync.WaitGroup
	birlesmis := make(chan string)

	// Her kanal ucun ayri goroutine
	for _, kanal := range kanallar {
		wg.Add(1)
		go func(k <-chan string) {
			defer wg.Done()
			for deger := range k {
				birlesmis <- deger
			}
		}(kanal)
	}

	// Hamisi bitende kanali bagla
	go func() {
		wg.Wait()
		close(birlesmis)
	}()

	return birlesmis
}

// -------------------------------------------
// 3. Done channel / Cancellation (legv etme)
// -------------------------------------------

// done channel - goroutine-lere "artiq dayanin" demek ucundur.
// context.Context-in alternatividir, amma daha asagi seviyyedir.

func ededAxini(done <-chan struct{}) <-chan int {
	cixis := make(chan int)
	go func() {
		defer close(cixis)
		n := 0
		for {
			select {
			case <-done: // legv siqnali geldise
				fmt.Println("  Eded axini dayandildi")
				return
			case cixis <- n:
				n++
			}
		}
	}()
	return cixis
}

// -------------------------------------------
// 4. Or-channel pattern
// -------------------------------------------

// Or-channel: bir nece done channel-den herhansi biri baglananda
// isare verir. "herhansi biri bitende" meqsedi ucundur.

func yaxud(kanallar ...<-chan struct{}) <-chan struct{} {
	switch len(kanallar) {
	case 0:
		return nil
	case 1:
		return kanallar[0]
	}

	yaxudBitti := make(chan struct{})
	go func() {
		defer close(yaxudBitti)
		switch len(kanallar) {
		case 2:
			select {
			case <-kanallar[0]:
			case <-kanallar[1]:
			}
		default:
			select {
			case <-kanallar[0]:
			case <-kanallar[1]:
			case <-kanallar[2]:
			case <-yaxud(append(kanallar[3:], yaxudBitti)...):
			}
		}
	}()
	return yaxudBitti
}

// Mueyyen muddete sonra baglanan kanal
func sonra(mueddet time.Duration) <-chan struct{} {
	kanal := make(chan struct{})
	go func() {
		defer close(kanal)
		time.Sleep(mueddet)
	}()
	return kanal
}

// -------------------------------------------
// 5. Tee channel
// -------------------------------------------

// Tee channel: bir channel-den gelen melumati iki ayri channel-e kopyalayir.
// Unix-deki "tee" komandasina benzer.

func teeKanal(done <-chan struct{}, giris <-chan int) (<-chan int, <-chan int) {
	cixis1 := make(chan int)
	cixis2 := make(chan int)

	go func() {
		defer close(cixis1)
		defer close(cixis2)
		for deger := range giris {
			// Heriki kanala gondermek ucun lokal deyisenleri istifade edirik
			var c1, c2 = cixis1, cixis2
			for i := 0; i < 2; i++ {
				select {
				case <-done:
					return
				case c1 <- deger:
					c1 = nil // artiq gonderildi, nil et ki bloklansin
				case c2 <- deger:
					c2 = nil
				}
			}
		}
	}()

	return cixis1, cixis2
}

// -------------------------------------------
// 6. Bridge channel
// -------------------------------------------

// Bridge channel: channel-lerin channel-ini tek bir channel-e cevirir.
// Yeni channel-ler yaradilir ve her birinden melumat oxunur.

func kanalKorpusu(done <-chan struct{}, kanalKanali <-chan <-chan int) <-chan int {
	cixis := make(chan int)

	go func() {
		defer close(cixis)
		for {
			var axin <-chan int
			select {
			case <-done:
				return
			case daxiliKanal, ok := <-kanalKanali:
				if !ok {
					return
				}
				axin = daxiliKanal
			}

			for deger := range axin {
				select {
				case <-done:
					return
				case cixis <- deger:
				}
			}
		}
	}()

	return cixis
}

// -------------------------------------------
// 7. Bounded parallelism (semafor ile mehdud paralellik)
// -------------------------------------------

// Semafor - buffered channel ile eyni anda islenen goroutine sayini mehdudlasdirir.
// Meselen: eyni anda en coxu 3 HTTP request gonderme.

type Netice struct {
	ID    int
	Deyer string
	Xeta  error
}

func mehdudIsle(isler []int, maxParalel int) []Netice {
	semafor := make(chan struct{}, maxParalel) // semafor
	neticeler := make([]Netice, len(isler))
	var wg sync.WaitGroup

	for i, is := range isler {
		wg.Add(1)
		go func(index, deger int) {
			defer wg.Done()

			semafor <- struct{}{} // slot tutur (bloklanir eger dolu ise)
			defer func() { <-semafor }() // slot azad edir

			// Simulyasiya
			time.Sleep(time.Duration(rand.Intn(50)) * time.Millisecond)
			neticeler[index] = Netice{
				ID:    deger,
				Deyer: fmt.Sprintf("netice_%d", deger),
			}
		}(i, is)
	}

	wg.Wait()
	return neticeler
}

// -------------------------------------------
// 8. Error handling in concurrent pipelines
// -------------------------------------------

// Xeta kanali - pipeline-da xetalari ayri kanal ile toplamaq

type NaticaVeYaXeta struct {
	Deyer int
	Xeta  error
}

func xetaliPipeline(giris <-chan int) <-chan NaticaVeYaXeta {
	cixis := make(chan NaticaVeYaXeta)

	go func() {
		defer close(cixis)
		for n := range giris {
			if n < 0 {
				cixis <- NaticaVeYaXeta{
					Xeta: fmt.Errorf("menfi eded qebul olunmur: %d", n),
				}
				continue
			}
			cixis <- NaticaVeYaXeta{
				Deyer: n * n,
			}
		}
	}()

	return cixis
}

// errgroup ile paralel islerin xeta idare etmesi
// (real kodda "golang.org/x/sync/errgroup" istifade olunur)
func errGroupNumune() {
	fmt.Println("\n  errgroup numunesi (konseptual):")
	fmt.Println(`
    import "golang.org/x/sync/errgroup"

    func melumatYukle(ctx context.Context) error {
        g, ctx := errgroup.WithContext(ctx)

        // Paralel isler
        g.Go(func() error {
            return istifadecileriYukle(ctx)
        })
        g.Go(func() error {
            return sifarisleriYukle(ctx)
        })
        g.Go(func() error {
            return mehsullariYukle(ctx)
        })

        // Herhansi biri xeta qaytarsa, hamisi legv olunur
        if err := g.Wait(); err != nil {
            return fmt.Errorf("yukleme ugursuz: %w", err)
        }
        return nil
    }`)
}

func main() {
	fmt.Println("=== CHANNEL PATTERN-LERI ===")

	// -------------------------------------------
	// 1. Pipeline numunesi
	// -------------------------------------------
	fmt.Println("\n--- 1. Pipeline pattern ---")
	fmt.Println("Merhele zenciri: ededYarat -> kvadrat -> ikiqat")

	// Pipeline: eded -> kvadrat -> ikiqat
	neticeler := ikiqat(kvadrat(ededYarat(2, 3, 4, 5)))
	for n := range neticeler {
		fmt.Println("  Netice:", n)
		// 2->4->8, 3->9->18, 4->16->32, 5->25->50
	}

	// -------------------------------------------
	// 2. Fan-out / Fan-in numunesi
	// -------------------------------------------
	fmt.Println("\n--- 2. Fan-out / Fan-in pattern ---")

	isKanali := make(chan int, 10)
	for i := 1; i <= 10; i++ {
		isKanali <- i
	}
	close(isKanali)

	// Fan-out: 3 isci eyni kanaldan oxuyur
	netice1 := make(chan string, 10)
	netice2 := make(chan string, 10)
	netice3 := make(chan string, 10)

	go func() {
		agirIs(1, isKanali, netice1)
		close(netice1)
	}()
	go func() {
		agirIs(2, isKanali, netice2)
		close(netice2)
	}()
	go func() {
		agirIs(3, isKanali, netice3)
		close(netice3)
	}()

	// Fan-in: 3 kanali birlesdiririk
	birlesmis := fanIn(netice1, netice2, netice3)
	for mesaj := range birlesmis {
		fmt.Println(" ", mesaj)
	}

	// -------------------------------------------
	// 3. Done channel numunesi
	// -------------------------------------------
	fmt.Println("\n--- 3. Done channel (legv etme) ---")

	done := make(chan struct{})
	ededler := ededAxini(done)

	// Yalniz 5 eded alaq
	for i := 0; i < 5; i++ {
		fmt.Println("  Alinan eded:", <-ededler)
	}
	close(done) // goroutine-e "dayan" deyirik
	time.Sleep(10 * time.Millisecond)

	// -------------------------------------------
	// 4. Or-channel numunesi
	// -------------------------------------------
	fmt.Println("\n--- 4. Or-channel pattern ---")
	fmt.Println("  Bir nece zamanlayicidan ilk biten secilir")

	baslangic := time.Now()
	<-yaxud(
		sonra(2*time.Second),
		sonra(1*time.Second),
		sonra(500*time.Millisecond), // en qisa - bu qalibdir
		sonra(3*time.Second),
	)
	fmt.Printf("  Bitti: %v sonra (en qisa secildi)\n", time.Since(baslangic).Round(time.Millisecond))

	// -------------------------------------------
	// 5. Tee channel numunesi
	// -------------------------------------------
	fmt.Println("\n--- 5. Tee channel ---")

	done2 := make(chan struct{})
	menbeyKanali := ededYarat(10, 20, 30)
	kanal1, kanal2 := teeKanal(done2, menbeyKanali)

	var wg sync.WaitGroup
	wg.Add(2)
	go func() {
		defer wg.Done()
		for d := range kanal1 {
			fmt.Println("  Kanal-1 aldi:", d)
		}
	}()
	go func() {
		defer wg.Done()
		for d := range kanal2 {
			fmt.Println("  Kanal-2 aldi:", d)
		}
	}()
	wg.Wait()

	// -------------------------------------------
	// 6. Bridge channel numunesi
	// -------------------------------------------
	fmt.Println("\n--- 6. Bridge channel ---")

	done3 := make(chan struct{})
	kanalKanali := make(chan (<-chan int))

	go func() {
		defer close(kanalKanali)
		for i := 0; i < 3; i++ {
			axin := make(chan int, 2)
			axin <- i*10 + 1
			axin <- i*10 + 2
			close(axin)
			kanalKanali <- axin
		}
	}()

	for deger := range kanalKorpusu(done3, kanalKanali) {
		fmt.Println("  Korpudan gelen:", deger)
	}

	// -------------------------------------------
	// 7. Bounded parallelism numunesi
	// -------------------------------------------
	fmt.Println("\n--- 7. Bounded parallelism (semafor) ---")
	fmt.Println("  Eyni anda max 3 goroutine isleyir:")

	isler := []int{1, 2, 3, 4, 5, 6, 7, 8}
	neticelerSlice := mehdudIsle(isler, 3)
	for _, n := range neticelerSlice {
		fmt.Printf("  ID=%d, Deyer=%s\n", n.ID, n.Deyer)
	}

	// -------------------------------------------
	// 8. Error handling in pipelines
	// -------------------------------------------
	fmt.Println("\n--- 8. Pipeline-da xeta idare etme ---")

	menbey := ededYarat(4, -2, 7, -1, 9)
	neticelerKanali := xetaliPipeline(menbey)

	for n := range neticelerKanali {
		if n.Xeta != nil {
			fmt.Println("  XETA:", n.Xeta)
		} else {
			fmt.Println("  Netice:", n.Deyer)
		}
	}

	errGroupNumune()

	fmt.Println("\n=== XULASE ===")
	fmt.Println("Pipeline     - merheleli melumat emali")
	fmt.Println("Fan-out/in   - isi paylasma ve neticeleri yigma")
	fmt.Println("Done channel - goroutine-leri legv etme")
	fmt.Println("Or-channel   - ilk biten siqnal")
	fmt.Println("Tee channel  - melumati iki yere kopyalama")
	fmt.Println("Bridge       - ic-ice kanallari birlesdirme")
	fmt.Println("Semafor      - paralelliye mehdudiyyet")
	fmt.Println("Xeta idare   - pipeline-da xetalari toplamaq")
}
