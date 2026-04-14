package main

import (
	"context"
	"fmt"
	"time"
)

// ===============================================
// CONTEXT
// ===============================================

// Context - goroutine-ler arasi legv etme (cancellation), deadline ve deyer dasima ucundur
// HTTP handler, database sorgusu, API call kimi emeliyyatlarda istifade olunur

// -------------------------------------------
// 1. Context ile timeout
// -------------------------------------------
func avasDaxilOlma(ctx context.Context) (string, error) {
	// Yava API call-u simulyasiya edirik
	select {
	case <-time.After(2 * time.Second): // 2 saniye surer
		return "Melumat alindi", nil
	case <-ctx.Done(): // context legv edildise
		return "", ctx.Err()
	}
}

// -------------------------------------------
// 2. Context ile deyer dasima
// -------------------------------------------
type acarsozTipi string

const istifadeciIDKey acarsozTipi = "istifadeci_id"

func prosesEt(ctx context.Context) {
	// Context-den deyer almaq
	id := ctx.Value(istifadeciIDKey)
	if id != nil {
		fmt.Println("Istifadeci ID:", id)
	}
}

// -------------------------------------------
// 3. Context-i funksiyalar arasinda oturbme
// -------------------------------------------
func databaseSorgusu(ctx context.Context, sorgu string) (string, error) {
	select {
	case <-time.After(100 * time.Millisecond):
		return "Netice: " + sorgu, nil
	case <-ctx.Done():
		return "", fmt.Errorf("sorgu legv edildi: %w", ctx.Err())
	}
}

func xidmet(ctx context.Context) {
	netice, err := databaseSorgusu(ctx, "SELECT * FROM users")
	if err != nil {
		fmt.Println("XETA:", err)
		return
	}
	fmt.Println(netice)
}

func main() {

	// -------------------------------------------
	// context.Background() - kok context (bos)
	// -------------------------------------------
	ctx := context.Background()
	fmt.Println("Background context:", ctx)

	// -------------------------------------------
	// context.WithTimeout - mueyyen muddet sonra legv
	// -------------------------------------------
	ctx1, cancel1 := context.WithTimeout(ctx, 1*time.Second)
	defer cancel1() // her zaman cancel cagirin - resurs sizintisini onler

	netice, err := avasDaxilOlma(ctx1)
	if err != nil {
		fmt.Println("Timeout:", err) // 1 san < 2 san, timeout olacaq
	} else {
		fmt.Println(netice)
	}

	// Daha uzun timeout ile
	ctx2, cancel2 := context.WithTimeout(ctx, 3*time.Second)
	defer cancel2()

	netice, err = avasDaxilOlma(ctx2)
	if err != nil {
		fmt.Println("Timeout:", err)
	} else {
		fmt.Println(netice) // 3 san > 2 san, ugurlu olacaq
	}

	// -------------------------------------------
	// context.WithCancel - el ile legv etme
	// -------------------------------------------
	ctx3, cancel3 := context.WithCancel(ctx)

	go func() {
		time.Sleep(500 * time.Millisecond)
		cancel3() // 500ms sonra legv et
		fmt.Println("Context legv edildi")
	}()

	select {
	case <-time.After(2 * time.Second):
		fmt.Println("Is bitdi")
	case <-ctx3.Done():
		fmt.Println("Legv sebebi:", ctx3.Err())
	}

	// -------------------------------------------
	// context.WithDeadline - mueyyen vaxta qeder
	// -------------------------------------------
	deadline := time.Now().Add(500 * time.Millisecond)
	ctx4, cancel4 := context.WithDeadline(ctx, deadline)
	defer cancel4()

	select {
	case <-time.After(1 * time.Second):
		fmt.Println("Bitdi")
	case <-ctx4.Done():
		fmt.Println("Deadline kecdi:", ctx4.Err())
	}

	// -------------------------------------------
	// context.WithValue - deyer dasima
	// -------------------------------------------
	ctx5 := context.WithValue(ctx, istifadeciIDKey, 42)
	prosesEt(ctx5) // Istifadeci ID: 42

	// -------------------------------------------
	// Praktik misal: zencir
	// -------------------------------------------
	ctx6, cancel6 := context.WithTimeout(ctx, 5*time.Second)
	defer cancel6()

	xidmet(ctx6) // database sorgusu edir

	// MUHUM QAYDALAR:
	// 1. Context her zaman birinci parametr olmalidir: func DoSomething(ctx context.Context, ...)
	// 2. context.Background() yalniz main, init ve ya en ust seviyyede istifade olunur
	// 3. cancel() her zaman defer ile cagirin
	// 4. context.WithValue-ni az istifade edin - yalniz request-scoped deyerler ucun
	// 5. Context-i struct-da saxlamayin - parametr kimi otururun
}
