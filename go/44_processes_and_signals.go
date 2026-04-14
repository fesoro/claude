package main

import (
	"fmt"
	"log"
	"os"
	"os/exec"
	"os/signal"
	"strings"
	"syscall"
	"time"
)

// ===============================================
// PROSES IDARE ETME VE SINYALLAR
// ===============================================

func main() {

	// -------------------------------------------
	// 1. SPAWNING PROCESSES (Xarici proqram isletme)
	// -------------------------------------------
	fmt.Println("=== Spawning Processes ===")

	// Sadə emr isletme
	out, err := exec.Command("echo", "Salam Go-dan!").Output()
	if err != nil {
		log.Println("Xeta:", err)
	} else {
		fmt.Println("Netice:", string(out))
	}

	// ls emri
	out, err = exec.Command("ls", "-la").Output()
	if err != nil {
		log.Println("Xeta:", err)
	} else {
		fmt.Println("Fayllar:")
		// Yalniz ilk 3 setir
		lines := strings.Split(string(out), "\n")
		for i, line := range lines {
			if i >= 4 {
				break
			}
			fmt.Println(" ", line)
		}
	}

	// -------------------------------------------
	// 2. CombinedOutput - stdout + stderr
	// -------------------------------------------
	out, err = exec.Command("date").CombinedOutput()
	if err != nil {
		log.Println("Xeta:", err)
	} else {
		fmt.Println("Tarix:", strings.TrimSpace(string(out)))
	}

	// -------------------------------------------
	// 3. CMD ile detalli kontrol
	// -------------------------------------------
	fmt.Println("\n=== CMD Detalli ===")

	cmd := exec.Command("echo", "detalli kontrol")
	cmd.Stdout = os.Stdout // cixisi birbaşa ekrana yonlendir
	cmd.Stderr = os.Stderr
	err = cmd.Run()
	if err != nil {
		log.Println("Run xetasi:", err)
	}

	// Exit kodu almaq
	cmd2 := exec.Command("ls", "/movcud_olmayan_qovluq")
	err = cmd2.Run()
	if err != nil {
		if exitErr, ok := err.(*exec.ExitError); ok {
			fmt.Println("Exit kodu:", exitErr.ExitCode())
		}
	}

	// -------------------------------------------
	// 4. Pipe ile emrler zenciri
	// -------------------------------------------
	fmt.Println("\n=== Pipe ===")

	// echo "salam dunya" | tr 'a-z' 'A-Z'
	echo := exec.Command("echo", "salam dunya go dili")
	tr := exec.Command("tr", "a-z", "A-Z")

	// echo-nun cixisini tr-nin girisine bagla
	tr.Stdin, _ = echo.StdoutPipe()
	tr.Stdout = os.Stdout

	tr.Start()
	echo.Run()
	tr.Wait()

	// -------------------------------------------
	// 5. Shell vasitesile emr (bash -c)
	// -------------------------------------------
	fmt.Println("\n=== Shell Emri ===")

	shellOut, err := exec.Command("bash", "-c", "echo $HOME && echo $USER").Output()
	if err != nil {
		log.Println("Shell xetasi:", err)
	} else {
		fmt.Print("Shell:", string(shellOut))
	}

	// -------------------------------------------
	// 6. EXEC'ING PROCESSES (cari prosesi evez etme)
	// -------------------------------------------
	fmt.Println("\n=== Exec (konsept) ===")

	// syscall.Exec cari Go prosesini basqa proses ile evez edir
	// Go prosesi tamamilə yox olur, yeni proses onun yerini alir
	// Unix exec() system call-dur

	fmt.Println(`
// Numune (isletmeyin - cari proses yox olacaq!):
binary, _ := exec.LookPath("ls")
args := []string{"ls", "-la"}
env := os.Environ()
syscall.Exec(binary, args, env) // QAYIDIS YOXDUR!
// Bu setir hec vaxt islemeyecek
`)

	// -------------------------------------------
	// 7. SINYALLAR (Signals)
	// -------------------------------------------
	fmt.Println("=== Sinyallar ===")

	fmt.Println(`
Muhum sinyallar:
  SIGINT  (2)  - Ctrl+C basildigda
  SIGTERM (15) - Proqrami dayandirmaq ucun (kill emri)
  SIGKILL (9)  - Zorla oldurme (tutula bilmez!)
  SIGHUP  (1)  - Terminal baglandigda
  SIGUSR1 (10) - Istifadeci tanimli sinyal 1
  SIGUSR2 (12) - Istifadeci tanimli sinyal 2

Sinyal gondermek:
  kill -SIGINT <PID>     # ve ya Ctrl+C
  kill -SIGTERM <PID>    # ve ya kill <PID>
  kill -SIGUSR1 <PID>    # xususi sinyal
`)

	// Bir nece sinyali tutmaq
	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh,
		syscall.SIGINT,  // Ctrl+C
		syscall.SIGTERM, // kill
	)

	// Sinyal ignore etmek
	// signal.Ignore(syscall.SIGHUP)

	// Sinyal tutmani dayandirmaq
	// signal.Stop(sigCh)

	// Sinyal gozleme (demo ucun timeout ile)
	fmt.Println("Ctrl+C basin ve ya 2 saniye gozleyin...")

	select {
	case sig := <-sigCh:
		fmt.Println("\nSinyal alindi:", sig)
		// Temizlik et
		fmt.Println("Temizlik edirem...")
		time.Sleep(500 * time.Millisecond)
		fmt.Println("Proqram duzgun baglandi")
	case <-time.After(2 * time.Second):
		fmt.Println("Timeout - demo bitdi")
	}

	// -------------------------------------------
	// 8. EXIT (Proqrami dayandirma)
	// -------------------------------------------
	fmt.Println("\n=== Exit ===")

	fmt.Println(`
os.Exit(0)  - Ugurlu cixis
os.Exit(1)  - Umumi xeta
os.Exit(2)  - Yanlis istifade

MUHUM: os.Exit defer-leri isletmir!
Ona gore graceful shutdown istifade edin (40-ci fayla baxin)
`)

	// Cari prosesin PID-i
	fmt.Println("PID:", os.Getpid())
	fmt.Println("PPID:", os.Getppid()) // parent PID
}
