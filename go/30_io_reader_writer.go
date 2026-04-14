package main

import (
	"bytes"
	"fmt"
	"io"
	"os"
	"strings"
)

// ===============================================
// io.Reader VE io.Writer INTERFEYSLERI
// ===============================================

// Go-nun en fundamental interfeysleridir
// Fayllar, network, stringler, bufferlar - hamisi bu interfeysleri tetbiq edir

// type Reader interface {
//     Read(p []byte) (n int, err error)
// }
//
// type Writer interface {
//     Write(p []byte) (n int, err error)
// }

// -------------------------------------------
// 1. Xususi Reader yaratma
// -------------------------------------------
type SayiciReader struct {
	limit   int
	current int
}

func (s *SayiciReader) Read(p []byte) (int, error) {
	if s.current >= s.limit {
		return 0, io.EOF // bitmis
	}
	s.current++
	metn := fmt.Sprintf("Setir %d\n", s.current)
	n := copy(p, metn)
	return n, nil
}

// -------------------------------------------
// 2. Xususi Writer yaratma
// -------------------------------------------
type BoyukHerfWriter struct {
	hedef io.Writer
}

func (b *BoyukHerfWriter) Write(p []byte) (int, error) {
	boyuk := bytes.ToUpper(p)
	return b.hedef.Write(boyuk)
}

func main() {

	// -------------------------------------------
	// strings.Reader - stringden oxumaq
	// -------------------------------------------
	reader := strings.NewReader("Salam Dunya!")

	buf := make([]byte, 5) // 5 byte-lig buffer
	for {
		n, err := reader.Read(buf)
		if err == io.EOF {
			break
		}
		fmt.Print(string(buf[:n]))
	}
	fmt.Println()

	// -------------------------------------------
	// bytes.Buffer - yaddashda Reader+Writer
	// -------------------------------------------
	var buffer bytes.Buffer
	buffer.WriteString("Birinci ")
	buffer.WriteString("Ikinci ")
	buffer.WriteString("Ucuncu")
	fmt.Println("Buffer:", buffer.String())

	// Buffer-den oxumaq
	oxundu := make([]byte, 7)
	buffer.Read(oxundu)
	fmt.Println("Oxundu:", string(oxundu)) // "Birinci"

	// -------------------------------------------
	// io.Copy - Reader-den Writer-e kopyalama
	// -------------------------------------------
	menbe := strings.NewReader("Bu metn kopyalanir\n")
	io.Copy(os.Stdout, menbe) // ekrana yazir (os.Stdout bir Writer-dir)

	// -------------------------------------------
	// io.ReadAll - butun melumati oxumaq
	// -------------------------------------------
	reader2 := strings.NewReader("Tam metn burada")
	data, _ := io.ReadAll(reader2)
	fmt.Println("ReadAll:", string(data))

	// -------------------------------------------
	// io.MultiReader - bir nece reader-i birlesdir
	// -------------------------------------------
	r1 := strings.NewReader("Salam ")
	r2 := strings.NewReader("Dunya ")
	r3 := strings.NewReader("Go!")
	multi := io.MultiReader(r1, r2, r3)
	hamisi, _ := io.ReadAll(multi)
	fmt.Println("Multi:", string(hamisi))

	// -------------------------------------------
	// io.TeeReader - oxuyarken basqa yere de yaz
	// -------------------------------------------
	var log bytes.Buffer
	orijinal := strings.NewReader("Muhum melumat")
	tee := io.TeeReader(orijinal, &log) // oxunan her sey log-a da yazilir

	netice, _ := io.ReadAll(tee)
	fmt.Println("Netice:", string(netice))
	fmt.Println("Log:", log.String()) // eyni melumat

	// -------------------------------------------
	// io.Pipe - goroutine arasi stream
	// -------------------------------------------
	pr, pw := io.Pipe()

	go func() {
		defer pw.Close()
		pw.Write([]byte("Pipe vasitesile gonderildi"))
	}()

	pipeData, _ := io.ReadAll(pr)
	fmt.Println("Pipe:", string(pipeData))

	// -------------------------------------------
	// Xususi Reader istifade
	// -------------------------------------------
	sayici := &SayiciReader{limit: 3}
	sayiciData, _ := io.ReadAll(sayici)
	fmt.Print("Sayici:\n", string(sayiciData))

	// -------------------------------------------
	// Xususi Writer istifade
	// -------------------------------------------
	boyukYaz := &BoyukHerfWriter{hedef: os.Stdout}
	fmt.Fprint(boyukYaz, "bu kicik herflerle yazildi\n") // BOYUK HERFLERLE cixir

	// -------------------------------------------
	// io.LimitReader - mehdud oxuma
	// -------------------------------------------
	uzunMelumat := strings.NewReader("Bu cox uzun bir metn ola bilerdi")
	mehdud := io.LimitReader(uzunMelumat, 10) // yalniz 10 byte
	mehdudData, _ := io.ReadAll(mehdud)
	fmt.Println("Mehdud:", string(mehdudData))

	// NIYE MUHIMDIR:
	// - http.Request.Body bir io.Reader-dir
	// - os.File hem Reader hem Writer-dir
	// - json.NewDecoder(reader) - istənilən menbeden JSON oxuyur
	// - json.NewEncoder(writer) - istənilən hedefe JSON yazir
	// - Butun I/O emeliyyatlari bu iki interface uzerinde qurulub
}
