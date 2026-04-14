package main

// ===============================================
// GO PROQRAMLASDIRMA DILI - TAM DERSLIK
// ===============================================
//
// Bu derslik Go dilini sifirdan professional seviyyeye
// qeder oyrenmek ucun hazirlanib.
// Fayllar seviyyeye gore siralanib.
//
// ===============================================
// JUNIOR - Baslangic (01-16)
// ===============================================
//
//  01 - Giris ve Qurasdirma (Introduction)
//  02 - Deyiskenler (Variables)
//  03 - Melumat Tipleri (Data Types)
//  04 - Operatorlar (Operators)
//  05 - Sertler (Conditionals)
//  06 - Donguler (Loops)
//  07 - Funksiyalar (Functions)
//  08 - Massivler ve Slice-lar (Arrays & Slices)
//  09 - Map-lar (Maps)
//  10 - Strukturlar (Structs)
//  11 - Pointer-ler (Pointers)
//  12 - String Emeliyyatlari (Strings & Strconv)
//  13 - Fayl Emeliyyatlari (File Operations)
//  14 - Paketler ve Modullar (Packages & Modules)
//  15 - Rekursiya (Recursion)
//  16 - Regular Expressions (Regexp)
//
// ===============================================
// JUNIOR/MIDDLE - Orta Baslangic (17-26)
// ===============================================
//
//  17 - Interfeys (Interfaces)
//  18 - Xeta Isleme (Error Handling)
//  19 - Tip Yoxlama ve Cevirme (Type Assertions & Conversions)
//  20 - JSON Kodlama/Dekodlama (JSON Encoding)
//  21 - Enum-lar (Enums)
//  22 - Init ve Modullar (Init & Modules)
//  23 - Epoch ve Scope
//  24 - Test Yazma (Testing)
//  25 - Loglama (Logging)
//  26 - CLI Tetbiqi (CLI App)
//
// ===============================================
// MIDDLE - Orta (27-50)
// ===============================================
//
//  27 - Goroutine ve Kanallar (Goroutines & Channels)
//  28 - Context
//  29 - Generics
//  30 - IO Reader/Writer
//  31 - HTTP Server
//  32 - HTTP Client
//  33 - Middleware ve Routing
//  34 - Verilenis Bazasi (Database)
//  35 - ORM ve sqlx
//  36 - Muhit ve Konfiqurasiya (Environment & Config)
//  37 - Embedding
//  38 - Slice (Qabaqcil)
//  39 - Struct (Qabaqcil)
//  40 - Pointer (Qabaqcil)
//  41 - Melumat Strukturlari (Data Structures)
//  42 - Text Template-ler
//  43 - TCP Server
//  44 - Prosesler ve Siqnallar (Processes & Signals)
//  45 - Fayl Emeliyyatlari (Qabaqcil)
//  46 - XML ve URL
//  47 - Rate Limiting
//  48 - Mocking ve Testify
//  49 - Graceful Shutdown
//  50 - Layihe Strukturu (Project Structure)
//
// ===============================================
// MIDDLE/SENIOR - Orta/Ireli (51-62)
// ===============================================
//
//  51 - Concurrency (Qabaqcil)
//  52 - Concurrency (Qabaqcil 2)
//  53 - Kanal Naxislari (Channel Patterns)
//  54 - Dizayn Naxislari (Design Patterns)
//  55 - Reflection
//  56 - WebSocket
//  57 - Tehlukesizlik (Security)
//  58 - Keshleme (Caching)
//  59 - Dependency Injection
//  60 - JWT ve Auth
//  61 - Fuzzing
//  62 - Build Tags
//
// ===============================================
// SENIOR - Ireli (63-72)
// ===============================================
//
//  63 - gRPC
//  64 - Profiling ve Benchmarking
//  65 - Yaddas Idareetmesi (Memory Management)
//  66 - Kod Generasiyasi (Code Generation)
//  67 - Docker ve Deploy
//  68 - Unsafe ve CGo
//  69 - Monitoring ve Observability
//  70 - Mesaj Novbeleri (Message Queues)
//  71 - Mikroservisler (Microservices)
//  72 - Clean Architecture
//
// ===============================================
// TOVSIYE OLUNAN OYRENMEK SIRASI
// ===============================================
//
// Merhelə 1 - Dil esaslari:       01 -> 12 (ardicicil)
// Merhelə 2 - Modullar ve fayllar: 13 -> 16
// Merhelə 3 - OOP ve xetalar:      17 -> 21
// Merhelə 4 - Test ve aletler:     22 -> 26
// Merhelə 5 - Concurrency ve web:  27 -> 37
// Merhelə 6 - Qabaqcil movzular:   38 -> 50
// Merhelə 7 - Dizayn ve arxitektura: 51 -> 62
// Merhelə 8 - Professional:        63 -> 72
