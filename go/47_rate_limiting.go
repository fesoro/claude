package main

import (
	"fmt"
	"sync"
	"time"
)

// ===============================================
// RATE LIMITING - SURET MEHDUDIYYETI
// ===============================================

// API sorqularini, emeliyyatlari mehdudlasdirmaq ucun
// DDoS-dan qorunma, resurs idare etme, API kvota

func main() {

	// -------------------------------------------
	// 1. time.Tick ile sadə rate limiter
	// -------------------------------------------
	// Her 200ms-de bir sorguya icaze ver
	fmt.Println("=== Sadə Rate Limiter ===")

	limiter := time.Tick(200 * time.Millisecond)

	for i := 1; i <= 5; i++ {
		<-limiter // gozle
		fmt.Printf("Sorgu %d: %s\n", i, time.Now().Format("15:04:05.000"))
	}

	// -------------------------------------------
	// 2. Token Bucket algoritmi
	// -------------------------------------------
	// Mueyyen sayda token var, her sorgu 1 token istifade edir
	// Tokenler mueyyen suretde yenilenir
	// Burst (ani artim) imkani verir

	fmt.Println("\n=== Token Bucket ===")

	type TokenBucket struct {
		tokens     chan struct{}
		maxTokens  int
		refillRate time.Duration
		stopCh     chan struct{}
	}

	newBucket := func(maxTokens int, refillRate time.Duration) *TokenBucket {
		tb := &TokenBucket{
			tokens:     make(chan struct{}, maxTokens),
			maxTokens:  maxTokens,
			refillRate: refillRate,
			stopCh:     make(chan struct{}),
		}
		// Baslangicda dolu
		for i := 0; i < maxTokens; i++ {
			tb.tokens <- struct{}{}
		}
		// Tokenleri yenile
		go func() {
			ticker := time.NewTicker(refillRate)
			defer ticker.Stop()
			for {
				select {
				case <-ticker.C:
					select {
					case tb.tokens <- struct{}{}:
					default: // dolu, kec
					}
				case <-tb.stopCh:
					return
				}
			}
		}()
		return tb
	}

	bucket := newBucket(3, 500*time.Millisecond) // 3 token, her 500ms yenilenir

	// 3 sorgu burst olaraq kecir, sonra gozlemeli olacaq
	for i := 1; i <= 6; i++ {
		<-bucket.tokens // token al (yoxdursa gozle)
		fmt.Printf("Sorgu %d: %s\n", i, time.Now().Format("15:04:05.000"))
	}
	close(bucket.stopCh)

	// -------------------------------------------
	// 3. Sliding Window - Suresan pencere
	// -------------------------------------------
	fmt.Println("\n=== Sliding Window Rate Limiter ===")

	type SlidingWindow struct {
		mu       sync.Mutex
		requests []time.Time
		limit    int
		window   time.Duration
	}

	newSlidingWindow := func(limit int, window time.Duration) *SlidingWindow {
		return &SlidingWindow{
			requests: make([]time.Time, 0),
			limit:    limit,
			window:   window,
		}
	}

	allow := func(sw *SlidingWindow) bool {
		sw.mu.Lock()
		defer sw.mu.Unlock()

		indi := time.Now()
		pencereBasi := indi.Add(-sw.window)

		// Kohne sorqulari sil
		yeni := make([]time.Time, 0)
		for _, t := range sw.requests {
			if t.After(pencereBasi) {
				yeni = append(yeni, t)
			}
		}
		sw.requests = yeni

		// Limit yoxla
		if len(sw.requests) >= sw.limit {
			return false
		}

		sw.requests = append(sw.requests, indi)
		return true
	}

	sw := newSlidingWindow(3, 1*time.Second) // saniyede 3 sorgu

	for i := 1; i <= 8; i++ {
		if allow(sw) {
			fmt.Printf("Sorgu %d: ICAZE VERILDI\n", i)
		} else {
			fmt.Printf("Sorgu %d: REDD EDILDI (limit asildı)\n", i)
		}
		time.Sleep(200 * time.Millisecond)
	}

	// -------------------------------------------
	// 4. Per-IP Rate Limiter (HTTP ucun)
	// -------------------------------------------
	fmt.Println("\n=== Per-IP Rate Limiter (konsept) ===")

	type IPLimiter struct {
		mu       sync.Mutex
		visitors map[string]*SlidingWindow
		limit    int
		window   time.Duration
	}

	newIPLimiter := func(limit int, window time.Duration) *IPLimiter {
		return &IPLimiter{
			visitors: make(map[string]*SlidingWindow),
			limit:    limit,
			window:   window,
		}
	}

	getVisitor := func(ipl *IPLimiter, ip string) *SlidingWindow {
		ipl.mu.Lock()
		defer ipl.mu.Unlock()

		v, ok := ipl.visitors[ip]
		if !ok {
			v = newSlidingWindow(ipl.limit, ipl.window)
			ipl.visitors[ip] = v
		}
		return v
	}

	ipLimiter := newIPLimiter(5, time.Second) // IP basina saniyede 5

	// Simulyasiya
	ipList := []string{"192.168.1.1", "192.168.1.1", "10.0.0.1", "192.168.1.1"}
	for _, ip := range ipList {
		visitor := getVisitor(ipLimiter, ip)
		izin := allow(visitor)
		fmt.Printf("IP: %s -> %v\n", ip, izin)
	}

	// HTTP middleware ornegi:
	// func RateLimitMiddleware(next http.Handler) http.Handler {
	//     limiter := newIPLimiter(100, time.Minute) // deqiqede 100
	//     return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
	//         ip := r.RemoteAddr
	//         visitor := getVisitor(limiter, ip)
	//         if !allow(visitor) {
	//             http.Error(w, "Cox sayda sorgu", http.StatusTooManyRequests)
	//             return
	//         }
	//         next.ServeHTTP(w, r)
	//     })
	// }

	// POPULYAR KITABXANALAR:
	// - golang.org/x/time/rate  (standart, Token Bucket)
	// - github.com/ulule/limiter (Redis destəyi, middleware)
	// - github.com/didip/tollbooth (HTTP middleware)
}
