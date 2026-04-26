# Figma (Lead)

## Ümumi baxış
- **Nə edir:** Brauzer-əsaslı birgə iş dizayn aləti. Adobe Illustrator, Sketch, Adobe XD ilə rəqabət aparır. Web brauzerdə vektor dizayn fayllarının real-time multiplayer redaktəsi.
- **Yaradılıb:** 2012-ci ildə Dylan Field və Evan Wallace tərəfindən.
- **İşə salınma:** illərlə daxili inkişafdan sonra 2016-da ictimaiyyətə açıq oldu.
- **Alınma:** Adobe 2022-də ~**$20B**-lik alışı elan etdi. 2023-də UK/EU tənzimləyiciləri tərəfindən bloklandı; Adobe **$1B break-up fee** ödədi və razılaşmanı ləğv etdi.
- **Miqyas:**
  - Milyonlarla dizayner (dəqiq MAU açıqlanmır; təxminən bir neçə milyon).
  - FigJam (whiteboard) və Dev Mode yeni istifadəçi bazası əlavə etdi.
  - Korporativ mühitlərdə fayllarda tez-tez onlarla eyni anda redaktor olur.
- **Əsas tarixi anlar:**
  - 2016: ictimai işə salınma. Brauzerdə vektor redaktəsinin mümkün olduğunu nümayiş etdirdi.
  - 2017: multiplayer yayımlandı — Figma-nı fərqləndirən funksiya.
  - 2019: community və plugin-lər.
  - 2021: FigJam (whiteboard məhsul).
  - 2022: Adobe alışı elan edildi.
  - 2023: tənzimləyici təzyiqə görə razılaşma ləğv edildi.
  - 2024: Dev Mode, AI funksiyaları.

Figma WebAssembly vasitəsilə **brauzer tətbiqinin native səviyyə performansına çatması** və **CRDT-əsaslı real-time birgə iş** üçün ən təmiz müasir nümunədir.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Rendering engine | C++ WebAssembly-ə (WASM) compile olunub | Brauzerdə native səviyyə performans |
| Graphics | WebGL (GPU-accelerated canvas) | Minlərlə elementlə 60fps vektor render |
| UI shell | TypeScript + React | Sürətli iterasiya, tanış stack |
| Multiplayer server | Rust | Yüksək throughput realtime üçün təhlükəsizlik + performans |
| Backend API | TypeScript (Node.js) + bəzi tarixi Go/Ruby hissələr | Server tərəfli məntiq, auth, billing |
| Primary DB | PostgreSQL (sharded, Vitess-tipli pattern-lər) | ACID, güclü SQL |
| Cache | Redis | Session-lar, presence, müvəqqəti state |
| Search | Elasticsearch / custom | Dizayn axtarışı |
| Queue | SQS, Kafka | Background iş |
| Infrastructure | AWS | Commodity cloud |
| Desktop | Electron wrapper (eyni web tətbiq) | Web ilə kod paylaşımı |
| Mobile viewer | WASM core ilə native iOS/Android client-lər | Yolda baxış/şərh |

## Dillər — Nə və niyə

### C++ (WebAssembly-ə compile olunub)
- Renderer, scene graph və əsas data modeli C++ ilə yazılıb.
- Emscripten istifadə edilərək WASM-a compile olunur.
- Niyə: Tipik Figma faylında yüz minlərlə vektor obyekt olur. JavaScript çox yavaş idi. Yalnız WebGL kifayət deyil — sürətli CPU tərəfli scene-graph məntiqi də lazımdır.
- WASM brauzer sandbox-unda təxminən native performans verir.

### TypeScript
- Bütün UI: toolbar-lar, panel-lər, menyular, modal-lar.
- React-da kifayət qədər standart pattern-lərlə yazılıb (amma editor UI-si üçün custom performans optimizasiyaları).
- WASM core ilə tipli bridge vasitəsilə ünsiyyət qurur.

### Rust (multiplayer server)
- Figma-nın multiplayer server-i Rust-da yazılıb.
- Rust seçildi: yaddaş təhlükəsizliyi, GC yoxdur, proqnozlaşdırılan tail latency, şəbəkə-ağır kod üçün yaxşıdır.
- Blog: *"How Figma's multiplayer technology works"*.

### Go / Ruby / TypeScript (backend)
- Müxtəlif backend servislər.
- "Əsas backend" (icazələr, sənəd metadata-sı, billing) qarışıqdır.

## Framework seçimləri — Nə və niyə
- C++-ı WASM-a compile etmək üçün **Emscripten + custom build pipeline**.
- UI üçün **React**, amma standart React pattern-ləri editor üçün çox yavaş olduğu üçün çoxlu custom virtualization var.
- Multiplayer server üçün **Rust async runtime (tokio)**.
- Öz tooling-lərinə böyük sərmayə — **Figma-nın daxili build sistemi** post-larında qeyd olunur.

## Verilənlər bazası seçimləri — Nə və niyə

### PostgreSQL — sharded
- Figma illərlə tək Postgres instansı üzərində böyüdü.
- Məşhur 2022 post: *"How Figma's databases team lived to tell the scale"* — vertikal miqyaslamadan (getdikcə daha böyük EC2 instansları), sonra horizontal sharding-dən danışır.
- Sharding Notion-un yanaşmasına bənzər şəkildə tətbiq-əsaslı edilir: yenidən balanslaşdırıla bilən məntiqi shard-lar.

### Redis
- Session storage, müvəqqəti state, presence məlumatı ("indi bu fayla kim baxır").

### Fayl content-i üçün custom storage
- Figma faylları Postgres-də saxlanılmır. Onlar öz blob formatında (sıxılmış, chunk-lanmış) object storage-də saxlanılır.
- Metadata (fayl siyahısı, icazələr) Postgres-dədir; content başqa yerdədir.

### Elasticsearch
- Axtarış.

## Proqram arxitekturası

```
  Browser
   |
   +--- TypeScript UI (React)
   |
   +--- WASM core (C++ compiled) - scene graph, rendering, editing ops
   |        |
   |        v
   |   WebGL (GPU rendering)
   |
   +--- WebSocket to Multiplayer Server (Rust)
           |
           |  (CRDT operations)
           v
        Replica per document (Rust process)
           |
           v
        Persistence (Postgres + blob storage)
```

### Multiplayer modeli
- Dizayn əməliyyatları üçün optimallaşdırılmış **CRDT (Conflict-free Replicated Data Types)** əsaslıdır.
- Hər açıq sənədin multiplayer server-də koordinasiya edən process-i var.
- İstifadəçi redaktə etdikdə, əməliyyat WebSocket vasitəsilə server-ə göndərilir, digər redaktorlara yayılır və nəhayət saxlanılır.
- Client-lər əməliyyatları optimistik tətbiq edir və server-in sırası fərqli olarsa uzlaşdırır.

### Rendering
- Bütün scene graph WASM yaddaşında yaşayır.
- Hər frame-də WASM WebGL-ə çəkiliş çağırışları göndərir.
- React yalnız UI chrome-u render edir, dizayn canvas-ı özünü deyil.

## İnfrastruktur və deploy
- Əsasən AWS.
- Multiplayer server-lər üçün EC2-nin geniş istifadəsi (çoxlu sənəd saxlamaq üçün böyük RAM qutuları lazımdır).
- Postgres RDS və ya EC2-də işləyir — RDS instanslarını miqyaslamağın ağrıları haqqında ətraflı yazıblar.
- Multiplayer üçün blue/green tipli deploy ilə standart CI/CD (çünki WebSocket bağlantılarını asanlıqla kəsmək olmur).

## Arxitekturanın təkamülü

| Year | Change |
|------|--------|
| 2012–2015 | Araşdırma və qurma. Erkən WASM mövcud deyildi; asm.js istifadə etdilər. |
| 2016 | İctimai işə salınma. Qapalı beta-da multiplayer. |
| 2017 | Multiplayer hamıya yayımlandı. CRDT-əsaslı. |
| 2018–2020 | Artım; Postgres-in vertikal miqyaslanması. |
| 2021 | FigJam eyni infrastrukturda işə salındı. |
| 2022 | Postgres-in sharding-i; Dev Mode planlaması. |
| 2023 | Blog: *How Figma's databases team lived to tell the scale*. |
| 2024 | AI funksiyaları; WASM bundle ölçüsünün daha çox optimizasiyası. |

## 3-5 Əsas texniki qərarlar

1. **Əsas engine üçün C++ → WebAssembly.** Bu mərc (2010-ların əvvəlində, WASM demək olar ki mövcud olmayanda) Figma-nın sürətli olmasının və Sketch-in web-də olmamasının səbəbidir.
2. **Multiplayer üçün CRDT-lər.** Operational Transformation-dan (Google Docs kimi) çətin qurulur, amma mürəkkəb vektor data üçün daha dayanıqlıdır.
3. **Multiplayer server üçün Rust.** Native sürət, redaktələri ləngidə bilən GC pauzaları yoxdur.
4. **Postgres-in mümkün qədər uzun vertikal miqyaslanması.** Sharding-dən əvvəl çox böyük bir Postgres instansından böyük fayda əldə etdilər. Praktik, moda deyil.
5. **Fayl content-ini Postgres-də saxlama.** Metadata Postgres-də, blob-lar object storage-də. Giriş pattern-ə görə bölün.

## Müsahibədə necə istinad etmək

1. **Hot core-u normal koddan ayırın.** Figma-nın hot loop üçün WASM core-u və ətrafında React UI-si var. Laravel developer-lər üçün oxşar: Laravel-i funksiyaların 95%-i üçün istifadə edin, amma performansa kritik bir hissəniz varsa (PDF render, şəkil emalı, axtarış indeksləmə), arxada ixtisaslaşmış servis istifadə edin — ola bilər Go və ya Rust-da.
2. **Vertikal miqyaslama güclüdür.** Birinci gündən Postgres-i shard-lamağa ehtiyac yoxdur. Figma-nın blogu on milyardlarla dollar dəyərində şirkətin illərlə tək böyük Postgres üzərində işlədiyini göstərir.
3. **Real-time əməkdaşlıq sehr deyil.** WebSocket-lər + authoritative server + əməliyyat log. Laravel Reverb, Pusher və ya Node-əsaslı socket-lər daha sadə versiyaları edə bilər.
4. **CRDT və OT öyrəniləcək alətlərdir.** "Birgə redaktor" qurursanız, bu pattern-ləri bilin. Hətta daha sadə hallar üçün (paylaşılan to-do list), Yjs (JS CRDT kitabxanası) faydalıdır.
5. **Brauzer sandbox performans tavanı deyil.** WebAssembly brauzerin təxminən native tətbiqlər host edə bilməsi deməkdir. PHP developer-lər tez-tez brauzeri yalnız HTML/CSS/JS kimi düşünürlər — amma müasir məhsullar daha çoxunu edir.
6. **Blob-ları əsas DB-nizdə saxlamayın.** Şəkillər, PDF-lər, dizayn faylları — S3/MinIO istifadə edin, URL/metadata-nı MySQL/Postgres-də saxlayın.

## Əlavə oxu üçün
- Figma Blog: *How Figma's multiplayer technology works*
- Figma Blog: *How we built multiplayer for Figma*
- Figma Blog: *How Figma's databases team lived to tell the scale*
- Figma Blog: *Rust in Production at Figma*
- Figma Blog: *Building a Professional Design Tool on the Web*
- Talk: *Figma's Multiplayer Under the Hood* (müxtəlif konfranslar)
- Book: *Crafting Interpreters* — Figma deyil, amma scene-graph düşüncəsi üçün faydalıdır
- Emscripten documentation — texniki fon
