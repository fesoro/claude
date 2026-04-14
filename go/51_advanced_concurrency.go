package main

import (
	"context"
	"fmt"
	"sync"
	"sync/atomic"
	"time"
)

// ===============================================
// IRELILEMIS CONCURRENCY
// ===============================================

func main() {

	// -------------------------------------------
	// 1. ATOMIC - Kilidsis thread-safe emeliyyatlar
	// -------------------------------------------
	// Mutex-den suretlidir, sadə eded emeliyyatlari ucun
	var atomicSayici atomic.Int64

	var wg sync.WaitGroup
	for i := 0; i < 1000; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			atomicSayici.Add(1) // thread-safe artirim
		}()
	}
	wg.Wait()
	fmt.Println("Atomic sayici:", atomicSayici.Load()) // 1000

	// Diger atomic emeliyyatlar
	var deger atomic.Int32
	deger.Store(42)             // deyeri yaz
	fmt.Println("Load:", deger.Load()) // deyeri oxu
	kohne := deger.Swap(100)    // deyisdir, kohnesini qaytir
	fmt.Println("Kohne:", kohne, "Yeni:", deger.Load())

	// CompareAndSwap - yalniz gozlenen deyerdirse deyisdir
	deyisdi := deger.CompareAndSwap(100, 200)
	fmt.Println("CAS ugurlu:", deyisdi, "Deyer:", deger.Load())

	// atomic.Value - istənilən tipi saxla
	var config atomic.Value
	config.Store(map[string]string{"host": "localhost"})
	cfg := config.Load().(map[string]string)
	fmt.Println("Config:", cfg)

	// -------------------------------------------
	// 2. SYNC.MAP - Thread-safe map
	// -------------------------------------------
	// Normal map goroutine-safe deyil, sync.Map-dir
	var safeMap sync.Map

	// Yazma
	safeMap.Store("ad", "Orkhan")
	safeMap.Store("yas", 25)

	// Oxuma
	deyer2, ok := safeMap.Load("ad")
	if ok {
		fmt.Println("sync.Map ad:", deyer2)
	}

	// Yoxla ve yaz (yoxdursa yaz)
	movcud, yuklenib := safeMap.LoadOrStore("ad", "Default")
	fmt.Println("LoadOrStore:", movcud, "yuklenib:", yuklenib) // Orkhan, true

	// Gezme
	safeMap.Range(func(key, value interface{}) bool {
		fmt.Printf("  %v: %v\n", key, value)
		return true // false qaytarsa dayanir
	})

	// Silme
	safeMap.Delete("yas")

	// -------------------------------------------
	// 3. SYNC.POOL - Obyekt yeniden istifade
	// -------------------------------------------
	// Coxlu qisa omurlu obyekt yaradanda GC yukunu azaldir
	bufferPool := &sync.Pool{
		New: func() interface{} {
			fmt.Println("Yeni buffer yaradilir")
			return make([]byte, 1024)
		},
	}

	// Pool-dan al
	buf := bufferPool.Get().([]byte)
	fmt.Println("Buffer olcusu:", len(buf))

	// Isletdikden sonra geri qoy
	bufferPool.Put(buf)

	// Novbeti defə pool-dan alanda yeniden yaratmayacaq
	buf2 := bufferPool.Get().([]byte)
	fmt.Println("Tekrar buffer:", len(buf2))

	// -------------------------------------------
	// 4. SYNC.ONCE - Yalniz bir defe islet
	// -------------------------------------------
	var once sync.Once
	var wg2 sync.WaitGroup

	baslat := func() {
		fmt.Println("Bu yalniz BIR DEFE isleyir")
	}

	for i := 0; i < 5; i++ {
		wg2.Add(1)
		go func() {
			defer wg2.Done()
			once.Do(baslat) // yalniz birinci goroutine isleyecek
		}()
	}
	wg2.Wait()

	// -------------------------------------------
	// 5. ERRGROUP - Xetali goroutine qrupu
	// -------------------------------------------
	// go get golang.org/x/sync/errgroup
	// Alternativ: el ile implementasiya

	type ErrGroup struct {
		wg   sync.WaitGroup
		err  error
		once sync.Once
	}

	errGo := func(eg *ErrGroup, f func() error) {
		eg.wg.Add(1)
		go func() {
			defer eg.wg.Done()
			if err := f(); err != nil {
				eg.once.Do(func() { eg.err = err })
			}
		}()
	}

	var eg ErrGroup
	errGo(&eg, func() error {
		fmt.Println("Is 1 isleyir")
		return nil
	})
	errGo(&eg, func() error {
		fmt.Println("Is 2 isleyir")
		return nil
	})
	errGo(&eg, func() error {
		fmt.Println("Is 3 isleyir")
		return fmt.Errorf("is 3 ugursuz oldu")
	})
	eg.wg.Wait()
	if eg.err != nil {
		fmt.Println("ErrGroup xetasi:", eg.err)
	}

	// -------------------------------------------
	// 6. SEMAPHORE - Eyni anda isleyen goroutine limiti
	// -------------------------------------------
	// Meselen: eyni anda max 3 API sorgusu
	semaphore := make(chan struct{}, 3) // 3 slot

	var wg3 sync.WaitGroup
	for i := 1; i <= 10; i++ {
		wg3.Add(1)
		go func(id int) {
			defer wg3.Done()

			semaphore <- struct{}{} // slot al (dolu olsa gozle)
			defer func() { <-semaphore }() // slot azad et

			fmt.Printf("Is %d basladi\n", id)
			time.Sleep(100 * time.Millisecond)
			fmt.Printf("Is %d bitdi\n", id)
		}(i)
	}
	wg3.Wait()
	fmt.Println("Butun isler bitdi (max 3 paralel)")

	// -------------------------------------------
	// 7. FAN-OUT / FAN-IN pattern
	// -------------------------------------------
	// Fan-out: bir menbeden bir nece worker-e is paylamaq
	// Fan-in: bir nece menbeden neticeleri bir kanala yigmaq

	// Menbe
	isler := make(chan int, 10)
	go func() {
		for i := 1; i <= 10; i++ {
			isler <- i
		}
		close(isler)
	}()

	// Fan-out: 3 worker
	neticeCh := make(chan string, 10)
	var fanWg sync.WaitGroup
	for w := 1; w <= 3; w++ {
		fanWg.Add(1)
		go func(wID int) {
			defer fanWg.Done()
			for is := range isler {
				netice := fmt.Sprintf("Worker %d: is %d = %d", wID, is, is*is)
				neticeCh <- netice
			}
		}(w)
	}

	// Fan-in: neticeleri yig
	go func() {
		fanWg.Wait()
		close(neticeCh)
	}()

	for n := range neticeCh {
		fmt.Println(n)
	}

	// -------------------------------------------
	// 8. CONTEXT ile goroutine legv etme
	// -------------------------------------------
	ctx, cancel := context.WithCancel(context.Background())

	go func(ctx context.Context) {
		for {
			select {
			case <-ctx.Done():
				fmt.Println("Goroutine dayandi:", ctx.Err())
				return
			default:
				fmt.Println("Isleyirem...")
				time.Sleep(100 * time.Millisecond)
			}
		}
	}(ctx)

	time.Sleep(350 * time.Millisecond)
	cancel() // goroutine-i dayandrir
	time.Sleep(50 * time.Millisecond)

	// -------------------------------------------
	// 9. SYNC.COND - Sertli gozleme
	// -------------------------------------------
	var mu sync.Mutex
	cond := sync.NewCond(&mu)
	hazir := false

	// Gozleyen goroutine
	go func() {
		mu.Lock()
		for !hazir {
			cond.Wait() // sert dogru olana qeder gozle
		}
		fmt.Println("Sert dogru oldu, davam edirem!")
		mu.Unlock()
	}()

	time.Sleep(200 * time.Millisecond)
	mu.Lock()
	hazir = true
	cond.Signal() // gozleyeni oyat (Broadcast - hamisini oyat)
	mu.Unlock()

	time.Sleep(100 * time.Millisecond)
}
