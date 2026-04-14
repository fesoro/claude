package main

import (
	"encoding/json"
	"fmt"
	"log"
	"strings"
)

// ===============================================
// JSON ENCODING VE DECODING
// ===============================================

// Go-da JSON islemek ucun standart "encoding/json" paketi istifade olunur.
// Struct tag-ler ile JSON field adlari ve davranisi idarə olunur.

// -------------------------------------------
// 1. Sadə Struct ve JSON Tag-ler
// -------------------------------------------

// json:"ad" - JSON-da field adi
// json:"ad,omitempty" - bosh olduqda JSON-a daxil etme
// json:"-" - bu fieldi hec vaxt JSON-a daxil etme
type Istifadeci struct {
	ID       int    `json:"id"`
	Ad       string `json:"ad"`
	Email    string `json:"email,omitempty"` // Bosh olduqda gorunmez
	Yash     int    `json:"yash,omitempty"`
	Shifre   string `json:"-"`               // JSON-a hec vaxt daxil olmaz
	Aktiv    bool   `json:"aktiv"`
}

// -------------------------------------------
// 2. Nested (ic-ice) Struct-lar
// -------------------------------------------

type Unvan struct {
	Sheher  string `json:"sheher"`
	Kuce    string `json:"kuce"`
	PostKod string `json:"post_kod,omitempty"`
}

type Ishci struct {
	Ad     string   `json:"ad"`
	Vezife string   `json:"vezife"`
	Unvan  Unvan    `json:"unvan"`           // Nested struct
	Bacariqlar []string `json:"bacariqlar"` // JSON array
}

// -------------------------------------------
// 7. Custom MarshalJSON / UnmarshalJSON
// -------------------------------------------

type Status int

const (
	StatusAktiv   Status = 1
	StatusPassiv  Status = 2
	StatusSilinib Status = 3
)

// Custom JSON marshal - reqem evezine metn yazilir
func (s Status) MarshalJSON() ([]byte, error) {
	var str string
	switch s {
	case StatusAktiv:
		str = "aktiv"
	case StatusPassiv:
		str = "passiv"
	case StatusSilinib:
		str = "silinib"
	default:
		str = "namelum"
	}
	return json.Marshal(str)
}

// Custom JSON unmarshal - metn reqeme cevrilir
func (s *Status) UnmarshalJSON(data []byte) error {
	var str string
	if err := json.Unmarshal(data, &str); err != nil {
		return err
	}
	switch str {
	case "aktiv":
		*s = StatusAktiv
	case "passiv":
		*s = StatusPassiv
	case "silinib":
		*s = StatusSilinib
	default:
		return fmt.Errorf("namelum status: %s", str)
	}
	return nil
}

type Hesab struct {
	Ad     string `json:"ad"`
	Status Status `json:"status"`
}

func main() {

	// -------------------------------------------
	// 3. json.Marshal (Struct -> JSON)
	// -------------------------------------------

	fmt.Println("=== 3. json.Marshal ===")

	user := Istifadeci{
		ID:     1,
		Ad:     "Eli",
		Email:  "eli@test.com",
		Yash:   25,
		Shifre: "gizli123", // JSON-a daxil olmayacaq
		Aktiv:  true,
	}

	// Marshal - struct-u JSON byte slice-a cevirir
	jsonData, err := json.Marshal(user)
	if err != nil {
		log.Fatal(err)
	}
	fmt.Println(string(jsonData))
	// {"id":1,"ad":"Eli","email":"eli@test.com","yash":25,"aktiv":true}

	// MarshalIndent - gozel formatlanmish JSON
	gozelJSON, err := json.MarshalIndent(user, "", "  ")
	if err != nil {
		log.Fatal(err)
	}
	fmt.Println(string(gozelJSON))

	// omitempty numunesi - bosh field JSON-da gorunmur
	boshUser := Istifadeci{
		ID:   2,
		Ad:   "Aysel",
		Aktiv: false,
	}
	jsonData2, _ := json.MarshalIndent(boshUser, "", "  ")
	fmt.Println(string(jsonData2))
	// Email ve Yash gorunmeyecek (omitempty)

	// -------------------------------------------
	// 4. json.Unmarshal (JSON -> Struct)
	// -------------------------------------------

	fmt.Println("\n=== 4. json.Unmarshal ===")

	jsonStr := `{
		"id": 3,
		"ad": "Kenan",
		"email": "kenan@test.com",
		"yash": 30,
		"aktiv": true
	}`

	var yeniUser Istifadeci
	err = json.Unmarshal([]byte(jsonStr), &yeniUser)
	if err != nil {
		log.Fatal(err)
	}
	fmt.Printf("Ad: %s, Yash: %d, Email: %s\n",
		yeniUser.Ad, yeniUser.Yash, yeniUser.Email)

	// Nested struct numunesi
	ishciJSON := `{
		"ad": "Nigar",
		"vezife": "Backend Developer",
		"unvan": {
			"sheher": "Baki",
			"kuce": "Nizami 15"
		},
		"bacariqlar": ["Go", "PostgreSQL", "Docker"]
	}`

	var ishci Ishci
	json.Unmarshal([]byte(ishciJSON), &ishci)
	fmt.Printf("Ishci: %s, Sheher: %s, Bacariqlar: %v\n",
		ishci.Ad, ishci.Unvan.Sheher, ishci.Bacariqlar)

	// -------------------------------------------
	// 5. json.NewEncoder / json.NewDecoder (Streaming)
	// -------------------------------------------

	fmt.Println("\n=== 5. Encoder / Decoder ===")

	// Encoder - io.Writer-e JSON yazir
	// Adeten http.ResponseWriter ile istifade olunur
	fmt.Print("Encoder ile: ")
	encoder := json.NewEncoder(fmt.Sprintf("")) // numune ucun
	_ = encoder

	// Praktik numune: strings.Builder-e yazma
	var buf strings.Builder
	enc := json.NewEncoder(&buf)
	enc.SetIndent("", "  ")
	enc.Encode(user) // Avtomatik \n elave edir
	fmt.Println(buf.String())

	// Decoder - io.Reader-den JSON oxuyur
	// Adeten http.Request.Body ile istifade olunur
	jsonReader := strings.NewReader(`{"id":4,"ad":"Vefa","aktiv":true}`)
	decoder := json.NewDecoder(jsonReader)

	var oxunanUser Istifadeci
	err = decoder.Decode(&oxunanUser)
	if err != nil {
		log.Fatal(err)
	}
	fmt.Printf("Decoder ile oxunan: %s (ID: %d)\n", oxunanUser.Ad, oxunanUser.ID)

	// -------------------------------------------
	// 6. map[string]interface{} ile Dinamik JSON
	// -------------------------------------------

	fmt.Println("\n=== 6. Dinamik JSON ===")

	// Strukturu onceden bilmediyimiz JSON ucun
	dinamikJSON := `{
		"ad": "Mehemmed",
		"yash": 28,
		"married": false,
		"diller": ["Go", "Python", "JS"],
		"unvan": {"sheher": "Baki"}
	}`

	var data map[string]interface{}
	json.Unmarshal([]byte(dinamikJSON), &data)

	// Deyerlere catmaq ucun type assertion lazimdir
	fmt.Println("Ad:", data["ad"].(string))
	fmt.Println("Yash:", data["yash"].(float64)) // JSON reqemleri float64 olur!

	// Diller array-dir
	diller := data["diller"].([]interface{})
	for _, dil := range diller {
		fmt.Println("  Dil:", dil.(string))
	}

	// Nested map
	unvan := data["unvan"].(map[string]interface{})
	fmt.Println("Sheher:", unvan["sheher"].(string))

	// Tehlukesiz type assertion (ok pattern)
	yash, ok := data["yash"].(float64)
	if ok {
		fmt.Println("Yash (tehlukesiz):", int(yash))
	}

	// -------------------------------------------
	// 7. Custom Marshal/Unmarshal numunesi
	// -------------------------------------------

	fmt.Println("\n=== 7. Custom Marshal/Unmarshal ===")

	hesab := Hesab{
		Ad:     "Eli",
		Status: StatusAktiv,
	}
	hesabJSON, _ := json.MarshalIndent(hesab, "", "  ")
	fmt.Println(string(hesabJSON))
	// {"ad":"Eli","status":"aktiv"} - reqem yox, metn yazildi!

	// Unmarshal - metni yeniden reqeme cevirir
	var yeniHesab Hesab
	json.Unmarshal([]byte(`{"ad":"Vefa","status":"passiv"}`), &yeniHesab)
	fmt.Printf("Hesab: %s, Status: %d\n", yeniHesab.Ad, yeniHesab.Status)

	// -------------------------------------------
	// 8. json.RawMessage
	// -------------------------------------------

	fmt.Println("\n=== 8. json.RawMessage ===")

	// RawMessage - JSON-un bir hissesini parse etmeden saxlayir
	// Strukturu sonra mueyyenleshdirmek isteyende faydalidi

	type Bildirish struct {
		Tip     string          `json:"tip"`
		Melumat json.RawMessage `json:"melumat"` // Parse olunmur
	}

	type EmailMelumat struct {
		Kimden string `json:"kimden"`
		Kime   string `json:"kime"`
		Movzu  string `json:"movzu"`
	}

	type SMSMelumat struct {
		Nomre string `json:"nomre"`
		Metn  string `json:"metn"`
	}

	bildJSON := `{"tip":"email","melumat":{"kimden":"a@b.com","kime":"c@d.com","movzu":"Salam"}}`

	var bild Bildirish
	json.Unmarshal([]byte(bildJSON), &bild)

	// Tipe gore parse et
	switch bild.Tip {
	case "email":
		var em EmailMelumat
		json.Unmarshal(bild.Melumat, &em)
		fmt.Printf("Email: %s -> %s, Movzu: %s\n", em.Kimden, em.Kime, em.Movzu)
	case "sms":
		var sm SMSMelumat
		json.Unmarshal(bild.Melumat, &sm)
		fmt.Printf("SMS: %s, Metn: %s\n", sm.Nomre, sm.Metn)
	}

	// -------------------------------------------
	// 9. json.Number (Dequiq reqem islemek)
	// -------------------------------------------

	fmt.Println("\n=== 9. json.Number ===")

	// Problem: JSON-da reqemler defolt olaraq float64 olur
	// Boyuk integer-ler (ID-ler) dequiqliyi itire biler
	// json.Number bunu hell edir

	jsonReqem := `{"id": 9007199254740993, "qiymet": 19.99}`
	dec := json.NewDecoder(strings.NewReader(jsonReqem))
	dec.UseNumber() // json.Number istifade et

	var reqemData map[string]interface{}
	dec.Decode(&reqemData)

	// json.Number kimi alinir, string kimi saxlanir
	idNumber := reqemData["id"].(json.Number)
	fmt.Println("ID (string):", idNumber.String())

	idInt, _ := idNumber.Int64()
	fmt.Println("ID (int64):", idInt)

	qiymetNumber := reqemData["qiymet"].(json.Number)
	qiymet, _ := qiymetNumber.Float64()
	fmt.Println("Qiymet (float64):", qiymet)

	// -------------------------------------------
	// 10. Slice/Array JSON
	// -------------------------------------------

	fmt.Println("\n=== 10. Slice JSON ===")

	// Struct slice-i JSON-a cevirme
	istifadeciler := []Istifadeci{
		{ID: 1, Ad: "Eli", Email: "eli@test.com", Aktiv: true},
		{ID: 2, Ad: "Aysel", Aktiv: false},
		{ID: 3, Ad: "Kenan", Email: "kenan@test.com", Yash: 22, Aktiv: true},
	}

	listJSON, _ := json.MarshalIndent(istifadeciler, "", "  ")
	fmt.Println(string(listJSON))

	// JSON array-dan struct slice-a cevirme
	var oxunanList []Istifadeci
	json.Unmarshal(listJSON, &oxunanList)
	for _, u := range oxunanList {
		fmt.Printf("  %d: %s (aktiv: %t)\n", u.ID, u.Ad, u.Aktiv)
	}
}

// ISLETMEK UCUN:
// go run 64_json_encoding.go
