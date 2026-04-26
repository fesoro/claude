# WhatsApp (Senior)

## Ümumi baxış
- **Nə edir:** Qlobal mətn, səs, video mesajlaşma tətbiqi. Ucdan-uca şifrələnmiş (E2EE). Telefon nömrəsi əsaslı şəxsiyyət. Səsli və video zənglər, ~1024 nəfərə qədər qrup söhbətləri, Channels, Communities, Status (Stories ekvivalenti).
- **Qurulub:** 2009-da Jan Koum və Brian Acton tərəfindən (hər ikisi keçmiş Yahoo! mühəndisləri).
- **Alınıb:** 2014-də Facebook tərəfindən **~$19 milyard**-a — o vaxtlar ən böyük istehlakçı texnologiya alışı.
- **Miqyas:**
  - 2014 (alış zamanı): **~35 mühəndis** ilə ~450M istifadəçi. Əfsanəvi nisbət.
  - 2024: ~3B aylıq aktiv istifadəçi.
  - Gündə 100B+ mesaj.
  - Milyardlarla səsli/video dəqiqə.
- **Əsas tarixi anlar:**
  - 2009: ilk versiya, pullu tətbiq ($0.99/il).
  - 2014: Facebook tərəfindən alındı. Əsasən rahat buraxıldı.
  - 2016: Signal Protocol istifadə edərək bütün mesajlar üçün ucdan-uca şifrələmə (Open Whisper Systems / Moxie Marlinspike-dan).
  - 2016: illik ödənişi ləğv etdi, pulsuz oldu.
  - 2018: Jan Koum monetizasiya və məxfilik üzrə fikir ayrılıqları səbəbindən ayrıldı.
  - 2020+: WhatsApp Business, Payments, Channels, Communities.

WhatsApp modern texnologiyada **kiçik komanda + düzgün alət + sadə arxitektura = kütləvi miqyas** üçün ən məşhur nümunədir.

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Language | Erlang/OTP | Concurrency, hot code swap, fault tolerance |
| Core server | Ciddi şəkildə modifikasiya edilmiş ejabberd (XMPP serveri) | Başlamaq üçün döyüş sınağından keçmiş XMPP baza |
| OS | FreeBSD (custom patch-lər) | Üstün şəbəkə stack-i; Rick Reed-in komandası onu tənzimlədi |
| Web server (API) | Yaws, sonradan custom | Erlang-native |
| Database | Mnesia (bəzilər), əsasən istifadəçi başına flat fayllar | Sadəlik; istifadəçi data-sı istifadəçi başına kiçikdir |
| Cache | İn-process Erlang ETS cədvəlləri | Əksər şeylər üçün xarici cache lazım deyil |
| Queue/messaging | Erlang prosesləri və mesaj ötürülməsi | Dilin daxilinə qurulub |
| Protocol | Modifikasiya edilmiş XMPP, sonra custom binary | Mobil şəbəkələr üçün səmərəlilik |
| Encryption | Signal Protocol | E2EE üçün qızıl standart |
| Voice/video | Custom stack (FB birləşməsindən sonra paylaşılan Meta infra istifadə edir) | Miqyas və keyfiyyət |
| Infrastructure | Bare-metal FreeBSD serverləri; sonradan Meta infra | Məlum avadanlıq limitləri; daha az abstraksiya |
| Monitoring | Custom (Erlang-ın daxili observability + in-house alətlər) | Proses-səviyyəli introspeksiya |

## Dillər — Nə və niyə

### Erlang — WhatsApp-ın ürəyi
- Erlang Ericsson-da telekom switch-lər üçün dizayn edildi: milyonlarla bağlantını idarə etməli, heç vaxt söndürməməli və canlı kod yeniləmələrinə icazə verməli olan sistemlər.
- WhatsApp-ın istifadə halı eyni pattern-dir: istifadəçi başına bir uzun müddətli TCP bağlantısı.
- **İstifadə etdikləri əsas Erlang xüsusiyyətləri:**
  - **Yüngül proseslər** — istifadəçi bağlantısı başına bir Erlang prosesi. Box başına 2 milyon proses adi idi.
  - **"Let it crash" fəlsəfəsi** — supervisor-lar uğursuz prosesləri restart edir; hər yerdə defensive kod yoxdur.
  - **Hot code swap** — bağlantıları itirmədən işləyən serverləri yeniləyin.
  - **Mesaj ötürülməsi** — paylaşılan yaddaş yoxdur, locking problemləri yoxdur.
- Erlang-ın VM-i (BEAM) soft real-time iş yükləri üçün tənzimlənib.

### FreeBSD tənzimləmə (əməliyyat sistemi səviyyəsi)
- Rick Reed (WhatsApp-ın lider infra mühəndisi) FreeBSD-ni tənzimləmə haqqında klassik çıxışlar etdi.
- Kernel-i daha yüksək socket saylarına, daha yaxşı TCP keep-alive idarə etməyə və şəbəkə stack performansına görə patch etdilər.
- Nümunə nailiyyət: tək box-da **2M+ eyni vaxtda TCP bağlantısı**.

### C və C++
- Media idarəetməsi, səsli/video codec-lər.
- Signal Protocol-un C istinad implementasiyası var.

### Client dilləri
- iOS: Objective-C, sonra Swift.
- Android: Java, sonra Kotlin.
- Desktop (sonra): React + Electron.

## Framework seçimləri — Nə və niyə
- **ejabberd** — Erlang-da yazılmış açıq mənbə XMPP serveri. Onu fork etdilər və ciddi şəkildə modifikasiya etdilər. Sıfırdan mesajlaşma serveri yazmaq əvəzinə yetkin bazada dayandılar və customize etdilər.
- Zamanla ejabberd-in o qədər azı qaldı ki, o, mahiyyətcə indi custom serverdir.
- Web mənasında "framework" yoxdur — Erlang/OTP özü framework-dur.

## Verilənlər bazası seçimləri — Nə və niyə

WhatsApp-ın data modeli **radikal sadədir**:

- Mesajlar tranzitdədir, serverlərdə uzun müddət saxlanılmır. Çatdırıldıqdan sonra, mesajlar serverlərdən silinir. Serverlər tarix store deyil, relay-dir.
- İstifadəçi başına vəziyyət (kontaktlar, son görünmə, qrup üzvlüyü) kiçikdir — istifadəçi başına fayllara sığır.

### Mnesia
- Erlang-ın daxili paylanmış DB-si. Bəzi qlobal vəziyyət üçün istifadə olunur.

### İstifadəçi başına flat fayllar
- İstifadəçi data-sının çoxu istifadəçi başına fayllarda. Sadə, sürətli, shard-lamaq asan.

### Klassik mənada Postgres, MySQL yoxdur
- Bu web nəhənglərinin tam əksidir. WhatsApp-a nəhəng RDBMS lazım deyildi, çünki onun domeni (tranzitdə mesajlaşma) "sosial qraf" və ya "katalog" kimi görünmür.
- Alışdan sonra, Meta ilə inteqrasiyalar media, profil və s. üçün daha ənənəvi store-lar əlavə etdi.

## Proqram arxitekturası

```
  Mobile client (iOS/Android)
         |
         | (long-lived TCP with custom binary protocol)
         v
   [Load balancer / SSL termination]
         |
   [Front-end Erlang nodes]  -- one process per connection
         |
   [Chat servers, group servers, presence servers]
         |
   [Storage: per-user files, Mnesia]
```

- **Qoşulmuş cihaz başına bir proses.** Əgər box-a 2M istifadəçi qoşulubsa, 2M Erlang prosesi var.
- **Presence** (onlayn/oflayn, yazır) in-memory-dir; DB hit yoxdur.
- **Qrup fan-out** VM daxilində N prosesə mesajdır — çox sürətli.
- **Ucdan-uca şifrələmə** o deməkdir ki, server mesaj məzmununu görə bilməz. Server kor router-dir.

Bu bir messenger üçün ola biləcəyi qədər **saf relay arxitekturasına** yaxındır.

## İnfrastruktur və deploy
- Əvvəlcə: colo-da custom FreeBSD box-lar, spesifik NIC tənzimləmə, box başına nəhəng RAM.
- Alışdan sonra: Meta infrastrukturu ilə tədricən inteqrasiya olundu, lakin öz xarakterinin çoxunu saxladı.
- Deploy: bağlantıları itirmədən yeni versiyaları deploy etmək üçün Erlang-ın `code_change`-dən istifadə edərək **hot code swap**.

## Arxitekturanın təkamülü

| İl | Dəyişiklik |
|------|--------|
| 2009 | ejabberd əsaslı prototip |
| 2010 | Custom fork edilmiş ejabberd; FreeBSD tənzimləmə başlayır |
| 2011 | Per-connection Erlang prosesləri; box başına milyonlarla bağlantı |
| 2014 | Facebook tərəfindən alındı; miqrasiya işi yavaş başlayır |
| 2016 | Signal Protocol vasitəsilə E2EE bütün istifadəçilərə roll out edildi |
| 2018 | Səsli/video miqyas-artırması |
| 2020+ | WhatsApp Business, Payments; daha çox Meta infra inteqrasiyası |

## Əsas texniki qərarlar

1. **Node/Java/Go əvəzinə Erlang.** Concurrency modeli "istifadəçi başına bir proses" ideyasına mükəmməl uyğunlaşdı. Ağır thread-lər yoxdur, bağlantıları düşürən GC pauza-ları yoxdur.
2. **Custom kernel patch-lərlə FreeBSD.** Şəbəkə stack-ini ən dərin tənzimləyə biləcəkləri OS-i seçdilər. "Şaquli" performans strategiyası vs horizontal.
3. **Sistemi kiçik saxlamaq.** Ağır vəziyyət tələb edən xüsusiyyətlər əlavə etməməyi bilərəkdən etdilər (uzun müddət news feed yoxdur, reklam engine yoxdur). Əhatə intizamı kiçik komandaya icazə verdi.
4. **Kripto icad etmək əvəzinə Signal Protocol qəbul etmək.** Ağıllı: kripto-nu yanlış etmək asandır.
5. **Çatdırıldıqdan sonra mesajları silmək.** Saxlama problemi demək olar ki, heçə qədər kiçilir.

## Müsahibədə necə istinad etmək

1. **Problem üçün düzgün aləti seçin.** WhatsApp Erlang-ı seçdi, çünki problem concurrency idi. CRUD web tətbiqləri üçün PHP/Laravel tamamilə düzgün seçimdir. WhatsApp istifadə etdiyi üçün Erlang-ı kopyalamayın — *səbəbi* kopyalayın.
2. **Arxitektura sadəliyi dəbi məğlub edir.** WhatsApp-da microservices yox idi, service mesh yox idi, Kubernetes yox idi, "clean architecture" teatrı yox idi. Aydın problemləri var idi və onu həll edən ən kiçik sistemə sahib idilər.
3. **Vertikal miqyaslama ölü deyil.** Genişlənməzdən əvvəl bir box-ı 2M bağlantıya miqyaslaşdırdılar. PHP dünyasında, opcache + Octane + güclü server sizə horizontal miqyaslama hiyləgərliklərinə ehtiyac duymadan çox uzağa aparır.
4. **Əhatə intizamı.** Xüsusiyyətlərə yox deyin. Komanda kiçik idi, çünki sistemi kiçik saxlayırdılar.
5. **Yetkin OSS (ejabberd) üzərində dayanmaq nəhəng sürətləndiricidir.** Laravel developer-ləri üçün: yaxşı uyğunlaşan OSS paketlərini istifadə edin. Laravel Echo və ya Horizon artıq ehtiyacınız olanı edirsə, onları yenidən yazmayın.
6. **Silmə üçün dizayn edin.** Mesaj çatdırıldıqdan sonra, onu silin. SaaS tətbiqlərində, bütün datanı əbədi saxlamaq həqiqətən lazımdırmı deyə düşünün.

## Əlavə oxu üçün
- Rick Reed çıxışı: *"That's 'Billion' with a B: Scaling to the Next Level at WhatsApp"* (Erlang Factory)
- Rick Reed çıxışı: *"Scaling to Millions of Simultaneous Connections"* (Erlang Factory SF)
- Məqalə: *"How WhatsApp Handled 900 Million Users with 50 Engineers"*
- Jan Koum müsahibəsi: *"Why We Built WhatsApp"*
- Kitab: *Designing for Scalability with Erlang/OTP* — ümumi, WhatsApp-a spesifik deyil
- Moxie Marlinspike tərəfindən Signal Protocol ağ sənədi
- Ericsson: Joe Armstrong tərəfindən *Programming Erlang* (dil konteksti)
