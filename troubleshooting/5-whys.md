# 5 Whys

## Məqsəd
Əsas səbəbin analizi texnikası. 1930-cu illərdə Toyota-da Sakichi Toyoda tərəfindən inkişaf etdirilib, indi lean manufacturing-də standartdır və software engineering-də geniş qəbul edilib. Səth simptomlarından struktur səbəblərə keçmək üçün "niyə" sualını təkrarən ver.

## Nə vaxt istifadə etməli
- Post-mortem-in root cause müzakirəsi
- Bug-un niyə baş verdiyini debug etmək (nə olduğunu deyil)
- Proses uğursuzluqları: "deploy niyə qırıldı?"
- Komanda retro-ları: "bu layihə niyə geri qaldı?"

## Nə vaxt istifadə ETMƏMƏLİ
- Çoxlu qarşılıqlı təsir edən səbəbləri olan problemlər (fishbone / Ishikawa istifadə et)
- Sistemli təşkilat problemləri (daha geniş çərçivə lazımdır)
- "İnsan xətası"-na çox tez çatırsansa — bu simptomdur, davam et

## Texnika

Müşahidə olunan simptomla başla. Təkrarən "niyə?" soruş. Hər cavab növbəti niyənin subyekti olur. Həqiqətən dəyişə biləcəyin struktur səbəbə çatanda dayan.

## Nümunə 1: Sayt aşağıdır

**Simptom**: Dünən sayt 20 dəqiqə aşağı idi.

**Niyə 1**: Database connection-ları bitdi.
**Niyə 2**: Çünki background job connection-ları sərbəst buraxmadan leak etdi.
**Niyə 3**: Çünki kod yolu `finally { $conn->close(); }` bloku buraxdı.
**Niyə 4**: Çünki codebase-də try-finally konvensiyası məcbur edilmir.
**Niyə 5**: Çünki code review tutmadı — və bunun üçün linter qaydası da yoxdur.

**Əsas səbəb**: Resurs təmizləmə pattern-ləri üçün avtomatlaşdırılmış yoxlama yox, review isə manual olaraq tutmadı.

**Action items (struktur)**:
- PDO connection leak-ləri üçün PHPStan / Psalm qaydası əlavə et
- Code review checklist-ini "resource cleanup" item-i ilə yenilə
- CI-də çoxlu connection açıb-bağlayan connection-leak canary test əlavə et

Addımlara bax: hər cavab qanuni, hər "niyə" daha dərinə gedir. İlk "niyə" (connection-lar bitdi) simptomdur. "Niyə 5"-ə çatdıqda fəaliyyət göstərə biləcəyimiz bir şeyimiz var: alət və proses.

## Nümunə 2: Yavaş checkout səhifəsi

**Simptom**: Checkout səhifə p95 həftə ərzində 500ms-dən 3s-ə qalxdı.

**Niyə 1**: Əvvəl 50ms olan bir query indi 2s-dir.
**Niyə 2**: Çünki `orders` cədvəli 10M sətirdən keçdi.
**Niyə 3**: Çünki filtr etdiyimiz sütunda index yoxdur.
**Niyə 4**: Çünki sütun əlavə olunanda PR reviewer index lazım olduğunu bilmirdi.
**Niyə 5**: Çünki migration template-imiz mühəndisi indexing ehtiyaclarını düşünməyə sövq etmir.

**Əsas səbəb**: Migration review-larında proses boşluğu.

**Action items**:
- Migration template-ə "Indexing considerations" bölməsi daxil edilir
- CI dəyişdirilən cədvələ dəyən hər query üzərində `EXPLAIN` işlədir
- Slow query alert threshold-u 5s-dən 1s-ə sıxlaşdırıldı

## Nümunə 3: Deploy outage-a səbəb oldu

**Simptom**: Çərşənbə axşamı deploy checkout-u 8 dəqiqə qırdı.

**Niyə 1**: Yeni kod legacy data-da null qaytaran metodu çağırdı.
**Niyə 2**: Çünki staging test-lərində legacy data yox idi.
**Niyə 3**: Çünki staging DB 6 aydır refresh olunmayıb.
**Niyə 4**: Çünki avtomatlaşdırılmış prod-to-staging sync-imiz yoxdur.
**Niyə 5**: Çünki heç kim staging data freshness-ə sahib deyil.

**Əsas səbəb**: Staging data parity üçün sahib və ya proses yoxdur.

**Action items**:
- Staging data sahibliyini platform komandasına təyin et
- Aylıq avtomatlaşdırılmış prod snapshot → staging tətbiq et (PII maskalanması ilə)
- CI staging-bənzər data həcminə qarşı test-lərin alt-dəstini işlədir

## Əsas məsələ DƏRİNLİK-dir

İlk "niyə" demək olar ki, həmişə proksimat səbəb verir (qırılan şey). Sonuncu "niyə" struktur / sistemli səbəb verməlidir (qırılan şeyin baş verməsinə icazə verən şey).

Səth səbəblər: "kodda bug var idi"
Struktur səbəblər: "həmin bug-u tutacaq review prosesi / test harness / monitoring-imiz yoxdur"

Struktur səbəbləri düzəlt. Struktur fix olmadan səth fix-lər təkrarlanan incident-lərə gətirib çıxarır.

## Həmişə tam 5 deyil

- Bəzən 3 niyə kifayətdir (`3 Whys`)
- Bəzən 7 lazımdır (`7 Whys`)
- Daha çox "niyə" mühəndislik əvəzinə fəlsəfəyə çevriləndə dayan
- Həqiqətən dəyişə biləcəyin şey olanda dayan

## Məhdudiyyətlər

### Çoxlu səbəblər

Real uğursuzluqların çox vaxt çoxlu qarşılıqlı təsir edən səbəbləri var. Hər birində 5 Whys sənə çoxlu paralel zəncir verir. Bir zənciri məcbur etmə.

Fishbone / Ishikawa diagramını nə vaxt istifadə et:
- Səbəb "A VƏ B VƏ C birləşməsi"-dirsə
- Fərqli Whys zəncirləri əlaqəsiz struktur səbəblərdə bitirsə
- Çoxlu contributing factor-lu mürəkkəb incident-lər

### Blame riski

Əgər hər "niyə" bir şəxsə işarə edirsə ("niyə Alice bunu belə yazdı?"), səhv edirsən. "Niyə" sistemlərə işarə etməlidir:
- "Bu kodun merge olmasına niyə icazə verildi?"
- "Niyə test tutmadı?"
- "Niyə review qaçırdı?"

"Alice niyə bilmirdi?" deyil.

### Hindsight bias

"Niyə X-i düşünmədik?" ədalətsiz ola bilər — həmin andakı komanda sənin indi olan data-ya sahib deyildi. Konteksti tanı.

### Confirmation bias

Soruşduğun ilk "niyə" ağacı məhdudlaşdırır. Fərqli ilk "niyə" seç və fərqli root tapa bilərsən. Mürəkkəb uğursuzluqlar üçün bir neçə başlanğıc nöqtəsi cəhd et.

## Görüşdə 5 Whys necə keçirməli

**Quruluş** (5 dəq):
- Whiteboard / Miro / Figjam
- Simptomu yuxarıda yaz
- Hər kəs simptom ifadəsi ilə razı

**İterasiya** (20-30 dəq):
- "Bu niyə baş verdi?" soruş
- Komanda müzakirə edir, facilitator konsensus cavabı yazır
- O cavab üzrə "niyə" soruş
- Struktur səbəb üzə çıxana qədər davam et
- Debat varsa: çoxlu cavab yaz, ağacı budaqlara ayır

**Yekun** (10 dəq):
- Struktur root-u(ları) müəyyən et
- Owner-li action item-lərə map et
- Yoxla: bunları düzəltmək bütün zənciri əngəlləyir?

## Template

```
Incident / Problem: ___________________________

Why 1: _________________________________________
  Evidence/data: _______________________________

Why 2: _________________________________________
  Evidence/data: _______________________________

Why 3: _________________________________________
  Evidence/data: _______________________________

Why 4: _________________________________________
  Evidence/data: _______________________________

Why 5: _________________________________________
  Evidence/data: _______________________________

Root cause: ____________________________________

Structural action items:
  1. __________________________________________
  2. __________________________________________
  3. __________________________________________
```

## Software engineering nümunə keçidləri

### Queue backlog

- **Simptom**: Dünən email notification-lar 3 saat gecikdi
- **Niyə**: Horizon queue-da 50k backlog var idi
- **Niyə**: Peak zamanı worker sayı kifayət deyildi
- **Niyə**: Auto-scaling threshold queue-depth deyil, CPU-ya qurulmuşdu
- **Niyə**: Auto-scaling config-i CPU-bound olan API xidmətindən kopyaladıq
- **Niyə**: Queue-service auto-scaling üçün template yoxdur
- **Root**: Queue worker-ləri scale etmək üçün guidance çatışmır
- **Action**: Infra sənədlərində queue xidmətləri üçün auto-scaling reçeti yarat

### Redis crash oldu

- **Simptom**: Redis saat 3-də öldü, alert gəldi, manual bərpa edildi
- **Niyə**: Redis OOM-killed oldu
- **Niyə**: Memory `maxmemory`-ə çatdı + eviction policy yox idi
- **Niyə**: `maxmemory-policy=noeviction` — write-lər uğursuz olur, amma auto-eviction yoxdur
- **Niyə**: Bu Redis-i queue üçün istifadə etdiyimizdən qoyulmuşdu — eviction job-ları drop edərdi
- **Niyə**: Eyni Redis-i cache üçün də istifadə edirik, bəzi key-lərdə TTL yoxdur
- **Niyə**: Queue Redis-i cache Redis-dən ayırmadıq
- **Root**: Uyğunsuz use case-lər üçün shared Redis
- **Action**: İki Redis instansiyasına böl, fərqli eviction policy-lər

## Müsahibə bucağı

"Əsas səbəbin analizini necə edirsən?"

Güclü cavab:
- "Başlanğıc çərçivəsi kimi 5 Whys istifadə edirəm. Simptomla başla, struktur, düzəldilə bilən səbəbə çatana qədər niyə soruşmağa davam et."
- "Əsas intizam: ilk 'niyə' cavabında dayanma. Proksimat səbəb adətən kod və ya data-dır; struktur səbəb proses, alət və ya dizayn."
- "Blame-yönümlü niyə-lərdən qaçıram. 'Mühəndis niyə onu belə yazdı' səhv sualdır. 'Kod niyə göndərməyə icazə verildi' düzgün sualdır."
- "Çoxlu səbəbli mürəkkəb incident-lər üçün fishbone/Ishikawa-ya keçirəm — 5 Whys tək səbəb zənciri fərz edir."
- "Son məhsul: struktur action item-ləri, fərdi cəzalar deyil."

Bonus: "İncident-lərimdən biri ilə klassik 5 Whys hekayəsi — səth səbəbi 'kod legacy data-da qırıldı' idi. Dərinə qazanda: 'çünki staging-də legacy data yoxdur'. Daha dərin: 'çünki heç kim staging freshness-ə sahib deyil'. Həqiqi fix bu idi — sahibliyi təyin et. O vaxtdan bəri legacy-data ilə bağlı incident-imiz olmayıb."
