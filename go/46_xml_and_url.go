package main

import (
	"encoding/xml"
	"fmt"
	"net/url"
)

// ===============================================
// XML PARSING VE URL PARSING
// ===============================================

func main() {

	// =====================
	// XML PARSING
	// =====================

	// -------------------------------------------
	// 1. Struct -> XML (Marshal)
	// -------------------------------------------
	fmt.Println("=== XML Marshal ===")

	type Adres struct {
		XMLName xml.Name `xml:"adres"`
		Sheher  string   `xml:"sheher"`
		Kuce    string   `xml:"kuce"`
	}

	type Shexs struct {
		XMLName xml.Name `xml:"shexs"`
		Ad      string   `xml:"ad"`
		Yas     int      `xml:"yas"`
		Email   string   `xml:"email,attr"` // XML attributu kimi
		Adres   Adres    `xml:"adres"`
		Hobiler []string `xml:"hobiler>hobi"` // ic-ice element
	}

	shexs := Shexs{
		Ad:    "Orkhan",
		Yas:   25,
		Email: "orkhan@mail.az",
		Adres: Adres{Sheher: "Baku", Kuce: "Nizami 10"},
		Hobiler: []string{"proqramlasdirma", "oxumaq", "uzme"},
	}

	// Gozel formatli XML
	xmlData, err := xml.MarshalIndent(shexs, "", "  ")
	if err != nil {
		fmt.Println("Marshal xetasi:", err)
		return
	}

	// XML header elave et
	fmt.Println(xml.Header + string(xmlData))

	// -------------------------------------------
	// 2. XML -> Struct (Unmarshal)
	// -------------------------------------------
	fmt.Println("\n=== XML Unmarshal ===")

	xmlStr := `<?xml version="1.0" encoding="UTF-8"?>
<shexs email="eli@mail.az">
  <ad>Eli</ad>
  <yas>30</yas>
  <adres>
    <sheher>Gence</sheher>
    <kuce>Ataturk 5</kuce>
  </adres>
  <hobiler>
    <hobi>futbol</hobi>
    <hobi>seyahet</hobi>
  </hobiler>
</shexs>`

	var shexs2 Shexs
	err = xml.Unmarshal([]byte(xmlStr), &shexs2)
	if err != nil {
		fmt.Println("Unmarshal xetasi:", err)
		return
	}
	fmt.Printf("Ad: %s, Yas: %d, Email: %s\n", shexs2.Ad, shexs2.Yas, shexs2.Email)
	fmt.Printf("Sheher: %s, Hobiler: %v\n", shexs2.Adres.Sheher, shexs2.Hobiler)

	// -------------------------------------------
	// 3. XML taglari
	// -------------------------------------------
	fmt.Println(`
XML Struct Tag-leri:
  xml:"ad"           -> <ad>deyer</ad>
  xml:"ad,attr"      -> attribut: <element ad="deyer">
  xml:"ad,omitempty"  -> bos olsa yazma
  xml:"-"             -> ignore et
  xml:",chardata"     -> element metni
  xml:",cdata"        -> CDATA bolumesu
  xml:"a>b"           -> ic-ice: <a><b>deyer</b></a>
  xml:",comment"      -> XML komment
`)

	// -------------------------------------------
	// 4. Dinamik XML (map kimi)
	// -------------------------------------------
	type GenericXML struct {
		XMLName xml.Name
		Attrs   []xml.Attr `xml:",any,attr"`
		Content string     `xml:",chardata"`
		Children []GenericXML `xml:",any"`
	}

	dynamicXML := `<kitab dil="az"><ad>Go Oyrenmek</ad><qiymet>25</qiymet></kitab>`
	var generic GenericXML
	xml.Unmarshal([]byte(dynamicXML), &generic)
	fmt.Println("Root:", generic.XMLName.Local) // kitab
	for _, child := range generic.Children {
		fmt.Printf("  %s: %s\n", child.XMLName.Local, child.Content)
	}

	// =====================
	// URL PARSING
	// =====================

	// -------------------------------------------
	// 5. URL Parse etme
	// -------------------------------------------
	fmt.Println("\n=== URL Parsing ===")

	rawURL := "https://user:pass@api.example.com:8080/v1/users?page=2&limit=10#section1"

	u, err := url.Parse(rawURL)
	if err != nil {
		fmt.Println("Parse xetasi:", err)
		return
	}

	fmt.Println("Scheme:  ", u.Scheme)               // https
	fmt.Println("User:    ", u.User.Username())       // user
	pass, _ := u.User.Password()
	fmt.Println("Password:", pass)                    // pass
	fmt.Println("Host:    ", u.Host)                  // api.example.com:8080
	fmt.Println("Hostname:", u.Hostname())            // api.example.com
	fmt.Println("Port:    ", u.Port())                // 8080
	fmt.Println("Path:    ", u.Path)                  // /v1/users
	fmt.Println("RawQuery:", u.RawQuery)              // page=2&limit=10
	fmt.Println("Fragment:", u.Fragment)               // section1

	// -------------------------------------------
	// 6. Query parametrlerini parse etme
	// -------------------------------------------
	fmt.Println("\n=== Query Params ===")

	params := u.Query() // url.Values (map[string][]string)
	fmt.Println("page: ", params.Get("page"))   // 2
	fmt.Println("limit:", params.Get("limit"))  // 10
	fmt.Println("yox:  ", params.Get("yox"))    // "" (movcud deyil)

	// Butun parametrleri gezme
	for key, values := range params {
		for _, v := range values {
			fmt.Printf("  %s = %s\n", key, v)
		}
	}

	// -------------------------------------------
	// 7. URL qurmaq
	// -------------------------------------------
	fmt.Println("\n=== URL Qurmaq ===")

	yeniURL := &url.URL{
		Scheme: "https",
		Host:   "api.example.com",
		Path:   "/v2/products",
	}

	// Query parametrleri elave et
	q := yeniURL.Query()
	q.Set("category", "electronics")
	q.Set("sort", "price")
	q.Add("tag", "new")
	q.Add("tag", "sale") // eyni acar, bir nece deyer
	yeniURL.RawQuery = q.Encode()

	fmt.Println("URL:", yeniURL.String())
	// https://api.example.com/v2/products?category=electronics&sort=price&tag=new&tag=sale

	// -------------------------------------------
	// 8. URL Encoding/Decoding
	// -------------------------------------------
	fmt.Println("\n=== URL Encoding ===")

	// Encode
	encoded := url.QueryEscape("salam dunya & xususi=simvollar")
	fmt.Println("Encoded:", encoded) // salam+dunya+%26+xususi%3Dsimvollar

	// Decode
	decoded, _ := url.QueryUnescape(encoded)
	fmt.Println("Decoded:", decoded) // salam dunya & xususi=simvollar

	// Path encode (boslluq %20 olur, + deyil)
	pathEncoded := url.PathEscape("my folder/my file.txt")
	fmt.Println("Path encoded:", pathEncoded) // my%20folder%2Fmy%20file.txt

	// -------------------------------------------
	// 9. Nisbi URL-u absoluta cevirme
	// -------------------------------------------
	base, _ := url.Parse("https://example.com/api/v1/")
	ref, _ := url.Parse("users?id=5")
	resolved := base.ResolveReference(ref)
	fmt.Println("Resolved:", resolved.String()) // https://example.com/api/v1/users?id=5
}
