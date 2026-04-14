package main

import "fmt"

// ===============================================
// DATABASE EMELIYYATLARI (database/sql)
// ===============================================

// Go-da database/sql paketi universaldir - ferqli DB-ler ucun eyni interface
// Driver-ler: PostgreSQL (lib/pq, pgx), MySQL (go-sql-driver), SQLite (mattn)

// QURASDIRMA:
// go get github.com/lib/pq            # PostgreSQL
// go get github.com/go-sql-driver/mysql  # MySQL
// go get github.com/mattn/go-sqlite3     # SQLite

func main() {
	fmt.Println("Bu fayl izah ucundur. Asagidaki kodlari database driver ile isledin.")

	kodOrnek := `
package main

import (
    "database/sql"
    "fmt"
    "log"

    _ "github.com/lib/pq" // PostgreSQL driver (import side-effect ucun)
)

// -------------------------------------------
// 1. Model
// -------------------------------------------
type Istifadeci struct {
    ID    int
    Ad    string
    Email string
    Yas   int
}

func main() {

    // -------------------------------------------
    // 2. QOSULMA (Connect)
    // -------------------------------------------
    // Format: "host=X port=X user=X password=X dbname=X sslmode=disable"
    dsn := "host=localhost port=5432 user=postgres password=1234 dbname=testdb sslmode=disable"
    db, err := sql.Open("postgres", dsn)
    if err != nil {
        log.Fatal("Qosulma xetasi:", err)
    }
    defer db.Close() // proqram bitende baglanti baglanir

    // Baglantiini yoxla
    if err := db.Ping(); err != nil {
        log.Fatal("Ping xetasi:", err)
    }
    fmt.Println("Databazaya qosuldu!")

    // Connection pool ayarlari
    db.SetMaxOpenConns(25)    // maksimum aciq baglanti
    db.SetMaxIdleConns(5)     // maksimum bos baglanti
    // db.SetConnMaxLifetime(5 * time.Minute)

    // -------------------------------------------
    // 3. CEDVEL YARATMA (CREATE TABLE)
    // -------------------------------------------
    _, err = db.Exec(` + "`" + `
        CREATE TABLE IF NOT EXISTS istifadeciler (
            id SERIAL PRIMARY KEY,
            ad VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            yas INTEGER DEFAULT 0
        )
    ` + "`" + `)
    if err != nil {
        log.Fatal("Cedvel yaratma xetasi:", err)
    }

    // -------------------------------------------
    // 4. ELAVE ETME (INSERT)
    // -------------------------------------------
    // $1, $2... placeholder istifade edin - SQL injection-dan qoruyur!
    var id int
    err = db.QueryRow(
        "INSERT INTO istifadeciler (ad, email, yas) VALUES ($1, $2, $3) RETURNING id",
        "Orkhan", "orkhan@mail.az", 25,
    ).Scan(&id)
    if err != nil {
        log.Fatal("Insert xetasi:", err)
    }
    fmt.Println("Elave edildi, ID:", id)

    // Exec ile (ID lazim deyilse)
    result, err := db.Exec(
        "INSERT INTO istifadeciler (ad, email, yas) VALUES ($1, $2, $3)",
        "Eli", "eli@mail.az", 30,
    )
    if err != nil {
        log.Fatal("Insert xetasi:", err)
    }
    tesirEdilen, _ := result.RowsAffected()
    fmt.Println("Tesir edilen setir:", tesirEdilen)

    // -------------------------------------------
    // 5. TEK SETIR OXUMA (SELECT one)
    // -------------------------------------------
    var ist Istifadeci
    err = db.QueryRow(
        "SELECT id, ad, email, yas FROM istifadeciler WHERE id = $1", 1,
    ).Scan(&ist.ID, &ist.Ad, &ist.Email, &ist.Yas)

    if err == sql.ErrNoRows {
        fmt.Println("Setir tapilmadi")
    } else if err != nil {
        log.Fatal("Oxuma xetasi:", err)
    } else {
        fmt.Printf("Tapildi: %+v\n", ist)
    }

    // -------------------------------------------
    // 6. BIR NECE SETIR OXUMA (SELECT many)
    // -------------------------------------------
    rows, err := db.Query("SELECT id, ad, email, yas FROM istifadeciler WHERE yas > $1", 20)
    if err != nil {
        log.Fatal("Query xetasi:", err)
    }
    defer rows.Close() // MUHUM: her zaman baglayin!

    var istifadeciler []Istifadeci
    for rows.Next() {
        var i Istifadeci
        err := rows.Scan(&i.ID, &i.Ad, &i.Email, &i.Yas)
        if err != nil {
            log.Fatal("Scan xetasi:", err)
        }
        istifadeciler = append(istifadeciler, i)
    }
    // rows.Next()-den sonra xeta yoxla
    if err = rows.Err(); err != nil {
        log.Fatal("Rows xetasi:", err)
    }
    fmt.Println("Tapildi:", len(istifadeciler), "istifadeci")

    // -------------------------------------------
    // 7. YENILEME (UPDATE)
    // -------------------------------------------
    result, err = db.Exec(
        "UPDATE istifadeciler SET yas = $1 WHERE ad = $2",
        26, "Orkhan",
    )
    if err != nil {
        log.Fatal("Update xetasi:", err)
    }
    tesir, _ := result.RowsAffected()
    fmt.Println("Yenilendi:", tesir, "setir")

    // -------------------------------------------
    // 8. SILME (DELETE)
    // -------------------------------------------
    result, err = db.Exec("DELETE FROM istifadeciler WHERE id = $1", 2)
    if err != nil {
        log.Fatal("Delete xetasi:", err)
    }
    silinmis, _ := result.RowsAffected()
    fmt.Println("Silinmis:", silinmis, "setir")

    // -------------------------------------------
    // 9. TRANSACTION
    // -------------------------------------------
    tx, err := db.Begin()
    if err != nil {
        log.Fatal("Transaction xetasi:", err)
    }

    _, err = tx.Exec("UPDATE istifadeciler SET yas = yas + 1 WHERE ad = $1", "Orkhan")
    if err != nil {
        tx.Rollback() // xeta olsa geri qaytar
        log.Fatal("TX xetasi:", err)
    }

    _, err = tx.Exec("UPDATE istifadeciler SET yas = yas + 1 WHERE ad = $1", "Eli")
    if err != nil {
        tx.Rollback()
        log.Fatal("TX xetasi:", err)
    }

    err = tx.Commit() // her sey ugurlu olsa tesdiqle
    if err != nil {
        log.Fatal("Commit xetasi:", err)
    }
    fmt.Println("Transaction ugurlu!")

    // -------------------------------------------
    // 10. PREPARED STATEMENT
    // -------------------------------------------
    // Eyni sorgu tekrar-tekrar islenirse daha suretlidir
    stmt, err := db.Prepare("SELECT ad, yas FROM istifadeciler WHERE yas > $1")
    if err != nil {
        log.Fatal(err)
    }
    defer stmt.Close()

    // Eyni stmt ferqli parametrlerle
    rows1, _ := stmt.Query(20)
    defer rows1.Close()
    // ...

    rows2, _ := stmt.Query(30)
    defer rows2.Close()
    // ...
}

// MUHUM QEYDLER:
// - HER ZAMAN placeholder ($1, $2) istifade edin, string birlesdirme DEYIL!
//   YANLIS: "WHERE ad = '" + ad + "'"  // SQL INJECTION tehlikesi!
//   DOGRU:  "WHERE ad = $1", ad
// - rows.Close() ve stmt.Close() HER ZAMAN defer ile cagirilmalidir
// - sql.ErrNoRows - setir tapilmadiqda xeta deyil, normal haldir
// - Connection pool avtomatikdir - db.Open() yalniz bir defe cagirin
`

	fmt.Println(kodOrnek)
}
