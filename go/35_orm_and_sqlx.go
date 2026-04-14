package main

import (
	"fmt"
	"time"
)

// ===============================================
// ORM (GORM) VE SQLX
// ===============================================

// Bu fayl GORM ve sqlx istifadesini gosterir.
// Kodu isletmek ucun verilenbazasi (PostgreSQL/MySQL/SQLite) lazimdir.
// Asagidaki numuneler oyrenmek ucundur - isletmek ucun
// "go get" ile paketleri yukleyin ve DB baglantisi qurun.
//
// Qurasdirma:
//   go get -u gorm.io/gorm
//   go get -u gorm.io/driver/postgres
//   go get -u gorm.io/driver/sqlite
//   go get -u github.com/jmoiron/sqlx
//   go get -u github.com/golang-migrate/migrate/v4

// -------------------------------------------
// 1. GORM Model Teyin Etme
// -------------------------------------------

// gorm.Model daxili field-ler elave edir:
// ID, CreatedAt, UpdatedAt, DeletedAt (soft delete)

// type gorm.Model struct {
//     ID        uint           `gorm:"primaryKey"`
//     CreatedAt time.Time
//     UpdatedAt time.Time
//     DeletedAt gorm.DeletedAt `gorm:"index"`
// }

type Istifadeci struct {
	ID        uint      `gorm:"primaryKey" json:"id"`
	Ad        string    `gorm:"size:100;not null" json:"ad"`
	Email     string    `gorm:"uniqueIndex;size:255" json:"email"`
	Yash      int       `gorm:"default:0" json:"yash"`
	Aktiv     bool      `gorm:"default:true" json:"aktiv"`
	ProfilID  *uint     `json:"profil_id"`                               // HasOne ucun
	Profil    *Profil   `gorm:"foreignKey:ProfilID" json:"profil"`       // HasOne
	Sifarisler []Sifarish `gorm:"foreignKey:IstifadeciID" json:"sifarisler"` // HasMany
	Rollar    []Rol     `gorm:"many2many:istifadeci_rollar;" json:"rollar"` // ManyToMany
	CreatedAt time.Time `json:"created_at"`
	UpdatedAt time.Time `json:"updated_at"`
}

type Profil struct {
	ID        uint   `gorm:"primaryKey"`
	Bio       string `gorm:"size:500"`
	Avatar    string `gorm:"size:255"`
	IstifadeciID uint
}

type Sifarish struct {
	ID            uint    `gorm:"primaryKey"`
	Mehsul        string  `gorm:"size:200;not null"`
	Qiymet        float64 `gorm:"not null"`
	IstifadeciID  uint    `gorm:"not null"` // BelongsTo ucun foreign key
	Istifadeci    Istifadeci `gorm:"foreignKey:IstifadeciID"` // BelongsTo
	CreatedAt     time.Time
}

type Rol struct {
	ID   uint   `gorm:"primaryKey"`
	Ad   string `gorm:"size:50;uniqueIndex"`
	Istifadeciler []Istifadeci `gorm:"many2many:istifadeci_rollar;"` // ManyToMany
}

// -------------------------------------------
// 2. GORM Hooks (BeforeCreate, AfterCreate, etc.)
// -------------------------------------------

// BeforeCreate - yaratmadan evvel islenir
// Email yoxlamasi, default deyerler ve s.
func (u *Istifadeci) BeforeCreate(tx interface{}) error {
	if u.Ad == "" {
		return fmt.Errorf("ad bosh ola bilmez")
	}
	fmt.Printf("[Hook] BeforeCreate: %s yaradilir...\n", u.Ad)
	return nil
}

// AfterCreate - yaratdiqdan sonra islenir
// Log yazma, bildiris gonderme ve s.
func (u *Istifadeci) AfterCreate(tx interface{}) error {
	fmt.Printf("[Hook] AfterCreate: %s yaradildi (ID: %d)\n", u.Ad, u.ID)
	return nil
}

// -------------------------------------------
// sqlx ucun model (struct tag-ler ferqlidir)
// -------------------------------------------

type IstifadeciSQL struct {
	ID    int    `db:"id"`
	Ad    string `db:"ad"`
	Email string `db:"email"`
	Yash  int    `db:"yash"`
}

func main() {

	// -------------------------------------------
	// 3. GORM Baglanti ve AutoMigrate
	// -------------------------------------------

	fmt.Println("=== 3. GORM Baglanti ve AutoMigrate ===")

	// PostgreSQL baglantisi:
	/*
		import (
			"gorm.io/gorm"
			"gorm.io/driver/postgres"
		)

		dsn := "host=localhost user=postgres password=sifre dbname=testdb port=5432 sslmode=disable"
		db, err := gorm.Open(postgres.Open(dsn), &gorm.Config{})
		if err != nil {
			log.Fatal("DB baglantisi ugursuz:", err)
		}

		// SQLite baglantisi (test ucun):
		// import "gorm.io/driver/sqlite"
		// db, err := gorm.Open(sqlite.Open("test.db"), &gorm.Config{})
	*/

	// AutoMigrate - struct-lara gore cedvelleri yaradir/yenileyir
	// Movcud melumatlari silmir, yalniz yeni sutunlar elave edir
	/*
		err = db.AutoMigrate(
			&Istifadeci{},
			&Profil{},
			&Sifarish{},
			&Rol{},
		)
		if err != nil {
			log.Fatal("Migration ugursuz:", err)
		}
	*/
	fmt.Println("AutoMigrate struct-lara gore cedvelleri yaradir")
	fmt.Println("Movcud melumatlari silmir, yalniz yeni field elave edir")

	// -------------------------------------------
	// 4. GORM CRUD - Create
	// -------------------------------------------

	fmt.Println("\n=== 4. GORM Create ===")

	fmt.Println(`
// Tek qeyd yaratma:
user := Istifadeci{Ad: "Eli", Email: "eli@test.com", Yash: 25}
result := db.Create(&user)
// user.ID avtomatik doldurulur
fmt.Println("Yaradildi, ID:", user.ID)
fmt.Println("Tesir eden setir:", result.RowsAffected)

// Toplu yaratma (batch insert):
istifadeciler := []Istifadeci{
    {Ad: "Aysel", Email: "aysel@test.com", Yash: 30},
    {Ad: "Kenan", Email: "kenan@test.com", Yash: 22},
    {Ad: "Nigar", Email: "nigar@test.com", Yash: 28},
}
db.Create(&istifadeciler) // Hamisi bir SQL ile yaradilir

// Mueyyenlemish field-leri yaratma:
db.Select("Ad", "Email").Create(&user) // Yalniz Ad ve Email
db.Omit("Yash").Create(&user)          // Yash-dan bashqa hamisi
`)

	// -------------------------------------------
	// 5. GORM CRUD - Read (First, Find, Where)
	// -------------------------------------------

	fmt.Println("=== 5. GORM Read ===")

	fmt.Println(`
// Tek qeyd - First (ID ile)
var user Istifadeci
db.First(&user, 1)              // SELECT * FROM istifadecis WHERE id = 1
db.First(&user, "id = ?", 1)    // Eyni netice

// Sherte gore - Where
db.Where("ad = ?", "Eli").First(&user)
db.Where("yash > ?", 25).Find(&users)
db.Where("ad LIKE ?", "%eli%").Find(&users)
db.Where("ad IN ?", []string{"Eli", "Aysel"}).Find(&users)
db.Where("yash BETWEEN ? AND ?", 20, 30).Find(&users)

// Hamisi - Find
var users []Istifadeci
db.Find(&users) // SELECT * FROM istifadecis

// Siralanmish ve limitle
db.Order("created_at desc").Limit(10).Offset(0).Find(&users)

// Sayma
var count int64
db.Model(&Istifadeci{}).Where("aktiv = ?", true).Count(&count)

// Mueyyenlemish field-leri sec
db.Select("ad", "email").Find(&users)

// Birinci tapilani al, tapilmasa xeta
result := db.Where("email = ?", "eli@test.com").First(&user)
if result.Error != nil {
    // errors.Is(result.Error, gorm.ErrRecordNotFound) ile yoxla
}
`)

	// -------------------------------------------
	// 6. GORM CRUD - Update
	// -------------------------------------------

	fmt.Println("=== 6. GORM Update ===")

	fmt.Println(`
// Tek field yenile
db.Model(&user).Update("ad", "Eli Yeni")

// Bir neche field yenile
db.Model(&user).Updates(Istifadeci{Ad: "Eli", Yash: 26})
// DIQQET: Sifir deyerler (0, "", false) yenilenmeyecek!

// Sifir deyerleri de yenilemek ucun map istifade edin:
db.Model(&user).Updates(map[string]interface{}{
    "ad":    "Eli",
    "yash":  0,     // Bu da yenilenecek
    "aktiv": false, // Bu da yenilenecek
})

// Where ile toplu yenileme
db.Model(&Istifadeci{}).Where("aktiv = ?", false).Update("aktiv", true)

// Save - butun field-leri yenile (INSERT or UPDATE)
user.Ad = "Eli Deyishdi"
user.Yash = 30
db.Save(&user)
`)

	// -------------------------------------------
	// 7. GORM CRUD - Delete
	// -------------------------------------------

	fmt.Println("=== 7. GORM Delete ===")

	fmt.Println(`
// Soft Delete (eger DeletedAt fieldi varsa)
// Qeyd silinmir, deleted_at sutununa tarix yazilir
db.Delete(&user, 1) // UPDATE istifadecis SET deleted_at = NOW() WHERE id = 1

// Soft delete olunmushlari tapmaq:
db.Unscoped().Where("id = ?", 1).Find(&user)

// Hard Delete (hemishəlik silme)
db.Unscoped().Delete(&user, 1) // DELETE FROM istifadecis WHERE id = 1

// Sherte gore silme
db.Where("aktiv = ?", false).Delete(&Istifadeci{})
`)

	// -------------------------------------------
	// 8. GORM Associations (Elaqeler)
	// -------------------------------------------

	fmt.Println("=== 8. GORM Associations ===")

	fmt.Println(`
// HasOne - Istifadecinin bir Profili var
// HasMany - Istifadecinin bir nece Sifarishi var
// BelongsTo - Sifarish bir Istifadeciye mexsusdur
// Many2Many - Istifadeci ve Rol arasinda cox-a-cox elaqe

// Eager Loading (Preload) - elaqeli melumatlari birlikde yukle
var user Istifadeci
db.Preload("Profil").First(&user, 1)
db.Preload("Sifarisler").First(&user, 1)
db.Preload("Rollar").First(&user, 1)

// Hamisi birlikde
db.Preload("Profil").
   Preload("Sifarisler").
   Preload("Rollar").
   First(&user, 1)

// Nested Preload
db.Preload("Sifarisler", "qiymet > ?", 100).First(&user, 1)

// Association yaratma
db.Model(&user).Association("Rollar").Append(&Rol{Ad: "admin"})
db.Model(&user).Association("Rollar").Delete(&rol)
db.Model(&user).Association("Rollar").Clear() // Hamisi silinir
db.Model(&user).Association("Rollar").Count() // Say

// Joins ile sorgu (daha effektiv)
db.Joins("Profil").First(&user, 1)
// SELECT * FROM istifadecis LEFT JOIN profils ON ...
`)

	// -------------------------------------------
	// 9. GORM Transactions
	// -------------------------------------------

	fmt.Println("=== 9. GORM Transactions ===")

	fmt.Println(`
// Usul 1: Avtomatik Transaction (tovsiye olunur)
err := db.Transaction(func(tx *gorm.DB) error {
    // tx istifade edin, db yox!
    if err := tx.Create(&Istifadeci{Ad: "Eli"}).Error; err != nil {
        return err // Rollback olacaq
    }

    if err := tx.Create(&Sifarish{Mehsul: "Laptop", Qiymet: 2000}).Error; err != nil {
        return err // Rollback olacaq
    }

    return nil // Commit olacaq
})

// Usul 2: Manual Transaction
tx := db.Begin()

if err := tx.Create(&user).Error; err != nil {
    tx.Rollback()
    return
}

if err := tx.Create(&sifarish).Error; err != nil {
    tx.Rollback()
    return
}

tx.Commit()

// Nested Transaction (SavePoint)
db.Transaction(func(tx *gorm.DB) error {
    tx.Create(&user1)

    tx.Transaction(func(tx2 *gorm.DB) error {
        tx2.Create(&user2)
        return errors.New("xeta") // Yalniz bu ic transaction rollback olur
    })

    return nil // Xarici transaction commit olur, user1 saxlanir
})
`)

	// -------------------------------------------
	// 10. sqlx Esaslari
	// -------------------------------------------

	fmt.Println("=== 10. sqlx ===")

	fmt.Println(`
// sqlx standart database/sql paketinin genishlendirilmish versiyasidir.
// ORM deyil, SQL yazirsiniz, amma struct mapping avtomatikdir.

import (
    "github.com/jmoiron/sqlx"
    _ "github.com/lib/pq" // PostgreSQL driver
)

// Baglanti
db, err := sqlx.Connect("postgres",
    "host=localhost user=postgres password=sifre dbname=testdb sslmode=disable")
if err != nil {
    log.Fatal(err)
}
defer db.Close()

// Get - tek netice (struct-a)
var user IstifadeciSQL
err = db.Get(&user, "SELECT * FROM istifadeciler WHERE id=$1", 1)

// Select - bir nece netice (slice-a)
var users []IstifadeciSQL
err = db.Select(&users, "SELECT * FROM istifadeciler WHERE yash > $1", 25)

// NamedExec - named parametrler
_, err = db.NamedExec(
    "INSERT INTO istifadeciler (ad, email, yash) VALUES (:ad, :email, :yash)",
    map[string]interface{}{
        "ad":    "Eli",
        "email": "eli@test.com",
        "yash":  25,
    },
)

// NamedExec struct ile
user := IstifadeciSQL{Ad: "Aysel", Email: "aysel@test.com", Yash: 30}
_, err = db.NamedExec(
    "INSERT INTO istifadeciler (ad, email, yash) VALUES (:ad, :email, :yash)",
    user,
)

// In - slice ile WHERE IN
query, args, err := sqlx.In(
    "SELECT * FROM istifadeciler WHERE ad IN (?)",
    []string{"Eli", "Aysel"},
)
query = db.Rebind(query) // Placeholder-leri duzelt ($1, $2...)
db.Select(&users, query, args...)

// Transaction
tx, err := db.Beginx()
tx.MustExec("INSERT INTO istifadeciler (ad, email) VALUES ($1, $2)", "Kenan", "k@test.com")
tx.MustExec("UPDATE istifadeciler SET yash = $1 WHERE ad = $2", 25, "Kenan")
err = tx.Commit() // ve ya tx.Rollback()
`)

	// -------------------------------------------
	// 11. Raw SQL vs ORM - Ne vaxt ne istifade etmeli?
	// -------------------------------------------

	fmt.Println("=== 11. Raw SQL vs ORM ===")

	fmt.Println(`
ORM (GORM) istifade edin:
  + Sadə CRUD emeliyyatlari (Create, Read, Update, Delete)
  + Prototip ve suretli inkishaf
  + Model elaqeleri (associations)
  + Avtomatik migration
  + Soft delete, hooks, callbacks lazim olduqda
  - Murekkeb sorgularda yavash ola biler
  - Yaradilan SQL-i kontrol etmek cetin ola biler

sqlx / Raw SQL istifade edin:
  + Murekkeb JOIN-lar ve aggregate sorgular
  + Performance kritik olduqda
  + Movcud SQL biliyinizi istifade etmek
  + SQL uzerinde tam kontrol lazim olduqda
  + Bulk emeliyyatlar (toplu insert/update)
  - Daha cox kod yazmaq lazimdir
  - Migration-i ozunuz idarə etmelisiniz

GORM-da Raw SQL:
  db.Raw("SELECT * FROM istifadecis WHERE yash > ?", 25).Scan(&users)
  db.Exec("UPDATE istifadecis SET aktiv = ? WHERE yash < ?", false, 18)
`)

	// -------------------------------------------
	// 12. Migration Aletleri (golang-migrate)
	// -------------------------------------------

	fmt.Println("=== 12. Migration ===")

	fmt.Println(`
// golang-migrate - verilenbazasi migration aleti
// Qurasdirma:
//   go install -tags 'postgres' github.com/golang-migrate/migrate/v4/cmd/migrate@latest

// Migration fayllarini yarat:
//   migrate create -ext sql -dir migrations -seq create_istifadeciler

// Bu 2 fayl yaradir:
//   migrations/000001_create_istifadeciler.up.sql
//   migrations/000001_create_istifadeciler.down.sql

// UP fayli (000001_create_istifadeciler.up.sql):
//   CREATE TABLE istifadeciler (
//       id SERIAL PRIMARY KEY,
//       ad VARCHAR(100) NOT NULL,
//       email VARCHAR(255) UNIQUE NOT NULL,
//       yash INT DEFAULT 0,
//       created_at TIMESTAMP DEFAULT NOW()
//   );

// DOWN fayli (000001_create_istifadeciler.down.sql):
//   DROP TABLE IF EXISTS istifadeciler;

// Migration-i islet:
//   migrate -path migrations -database "postgres://user:pass@localhost:5432/db?sslmode=disable" up
//   migrate -path migrations -database "..." down 1  // Son migration-i geri al
//   migrate -path migrations -database "..." version  // Movcud versiya

// Go kodundan migration:
import "github.com/golang-migrate/migrate/v4"

m, err := migrate.New(
    "file://migrations",
    "postgres://user:pass@localhost:5432/db?sslmode=disable",
)
m.Up()   // Butun migration-leri islet
m.Down() // Hamisi geri al
m.Steps(1)  // 1 addim ireli
m.Steps(-1) // 1 addim geri

// GORM AutoMigrate vs golang-migrate:
// AutoMigrate: Sadə, field elave edir, amma sutun silmir, tip deyishmir
// golang-migrate: Tam kontrol, versiyalanmish, team isinde daha yaxshi
// Production ucun golang-migrate tovsiye olunur!
`)

	fmt.Println("\n=== Xulase ===")
	fmt.Println("GORM - ORM, suretli inkishaf, sadə CRUD")
	fmt.Println("sqlx - Raw SQL + struct mapping, murekkeb sorgular")
	fmt.Println("golang-migrate - versiyalanmish DB migration")
}

// ISLETMEK UCUN:
// go run 66_orm_and_sqlx.go
//
// PAKETLERI YUKLEMEK UCUN:
// go get -u gorm.io/gorm
// go get -u gorm.io/driver/postgres
// go get -u github.com/jmoiron/sqlx
// go get -u github.com/golang-migrate/migrate/v4
