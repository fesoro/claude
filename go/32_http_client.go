package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"time"
)

// ===============================================
// HTTP CLIENT - API-lere SORGU GONDERME
// ===============================================

// Go-da xarici API-lere sorgu gondermek ucun net/http paketi istifade olunur
// Xarici kitabxana lazim deyil!

func main() {

	// -------------------------------------------
	// 1. Sadə GET sorgusu
	// -------------------------------------------
	resp, err := http.Get("https://jsonplaceholder.typicode.com/posts/1")
	if err != nil {
		fmt.Println("XETA:", err)
		return
	}
	defer resp.Body.Close() // MUHUM: her zaman baglayin!

	fmt.Println("Status:", resp.StatusCode) // 200
	fmt.Println("Status text:", resp.Status) // "200 OK"

	body, _ := io.ReadAll(resp.Body)
	fmt.Println("Cavab:", string(body))

	// -------------------------------------------
	// 2. JSON cavabi struct-a cevirme
	// -------------------------------------------
	type Post struct {
		UserID int    `json:"userId"`
		ID     int    `json:"id"`
		Title  string `json:"title"`
		Body   string `json:"body"`
	}

	resp2, err := http.Get("https://jsonplaceholder.typicode.com/posts/1")
	if err != nil {
		fmt.Println("XETA:", err)
		return
	}
	defer resp2.Body.Close()

	var post Post
	json.NewDecoder(resp2.Body).Decode(&post)
	fmt.Printf("Post: %+v\n\n", post)

	// -------------------------------------------
	// 3. POST sorgusu (JSON body ile)
	// -------------------------------------------
	yeniPost := Post{
		UserID: 1,
		Title:  "Yeni meqale",
		Body:   "Meqalenin metni",
	}

	jsonData, _ := json.Marshal(yeniPost)
	resp3, err := http.Post(
		"https://jsonplaceholder.typicode.com/posts",
		"application/json",
		bytes.NewBuffer(jsonData),
	)
	if err != nil {
		fmt.Println("XETA:", err)
		return
	}
	defer resp3.Body.Close()

	fmt.Println("POST Status:", resp3.StatusCode) // 201 Created

	var yaradilmis Post
	json.NewDecoder(resp3.Body).Decode(&yaradilmis)
	fmt.Printf("Yaradildi: %+v\n\n", yaradilmis)

	// -------------------------------------------
	// 4. Xususi Client (timeout, header)
	// -------------------------------------------
	// Default http.Get timeout-suz isleyir - PRODUCTION-da istifade etmeyin!
	// Her zaman xususi client yaradin

	client := &http.Client{
		Timeout: 10 * time.Second, // 10 saniye timeout
	}

	// Xususi request yaratmaq
	req, err := http.NewRequest("GET", "https://jsonplaceholder.typicode.com/posts/1", nil)
	if err != nil {
		fmt.Println("XETA:", err)
		return
	}

	// Header elave etme
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer TOKEN_BURADA")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", "MenimApp/1.0")

	resp4, err := client.Do(req)
	if err != nil {
		fmt.Println("XETA:", err)
		return
	}
	defer resp4.Body.Close()

	fmt.Println("Xususi client status:", resp4.StatusCode)

	// -------------------------------------------
	// 5. PUT sorgusu
	// -------------------------------------------
	yenilenmisPpost := Post{UserID: 1, ID: 1, Title: "Yenilenmis", Body: "Yeni metn"}
	putData, _ := json.Marshal(yenilenmisPpost)

	putReq, _ := http.NewRequest(
		"PUT",
		"https://jsonplaceholder.typicode.com/posts/1",
		bytes.NewBuffer(putData),
	)
	putReq.Header.Set("Content-Type", "application/json")

	putResp, err := client.Do(putReq)
	if err != nil {
		fmt.Println("XETA:", err)
		return
	}
	defer putResp.Body.Close()
	fmt.Println("PUT Status:", putResp.StatusCode)

	// -------------------------------------------
	// 6. DELETE sorgusu
	// -------------------------------------------
	delReq, _ := http.NewRequest("DELETE", "https://jsonplaceholder.typicode.com/posts/1", nil)
	delResp, err := client.Do(delReq)
	if err != nil {
		fmt.Println("XETA:", err)
		return
	}
	defer delResp.Body.Close()
	fmt.Println("DELETE Status:", delResp.StatusCode)

	// -------------------------------------------
	// 7. Query parametrleri
	// -------------------------------------------
	baseURL := "https://jsonplaceholder.typicode.com/posts"
	params := url.Values{}
	params.Add("userId", "1")
	params.Add("_limit", "3")

	tamURL := baseURL + "?" + params.Encode()
	fmt.Println("URL:", tamURL)
	// https://jsonplaceholder.typicode.com/posts?userId=1&_limit=3

	// -------------------------------------------
	// 8. Form data gondermek
	// -------------------------------------------
	formData := url.Values{
		"username": {"orkhan"},
		"password": {"12345"},
	}
	formResp, err := http.PostForm("https://httpbin.org/post", formData)
	if err != nil {
		fmt.Println("Form XETA:", err)
		return
	}
	defer formResp.Body.Close()
	fmt.Println("Form Status:", formResp.StatusCode)

	// -------------------------------------------
	// 9. Cavab header-lerini oxumaq
	// -------------------------------------------
	fmt.Println("\nCavab Header-leri:")
	for key, values := range resp4.Header {
		for _, v := range values {
			fmt.Printf("  %s: %s\n", key, v)
		}
	}

	// -------------------------------------------
	// 10. Status kodunu yoxlamaq
	// -------------------------------------------
	statusYoxla := func(resp *http.Response) error {
		if resp.StatusCode >= 200 && resp.StatusCode < 300 {
			return nil // ugurlu
		}
		body, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("API xetasi: %d - %s", resp.StatusCode, string(body))
	}
	_ = statusYoxla

	// MUHUM QEYDLER:
	// - resp.Body.Close() HER ZAMAN defer ile baglayın
	// - Production-da http.Client{Timeout: ...} istifade edin
	// - Default client timeout-suz isleyir - tehlükelidir!
	// - Eyni client-i tekrar istifade edin (connection pooling)
	// - Context ile timeout/cancellation daha yaxsidir (req.WithContext)
}
