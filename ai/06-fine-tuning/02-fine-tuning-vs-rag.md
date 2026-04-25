# İncə Tənzimləmə vs RAG vs Prompt Mühəndisliyi: Qərar Çərçivəsi (Middle)

## Əsas Sual

LLM davranışını uyğunlaşdırmaq haqqında hər AI mühəndisliyi qərarı üç yanaşmaya gəlir. Bunu yanlış anlamaq həftələr işi və minlərlə dollar israf etməyə gətirib çıxarır. Düzgün anlamaq isə işləyən həll ilə işləməyən həll arasındakı fərqdir.

Yanaşmalar bir-birini istisna etmir — ən yaxşı istehsal sistemləri çox vaxt hər üçünü birləşdirir. Lakin onları effektiv şəkildə birləşdirmək üçün hər birini dərindən başa düşməlisiniz.

---

## Üç Yanaşmanın Tərifləri

### Prompt Mühəndisliyi

Davranışını dəyişdirmək üçün *modelə nə dediyinizi* dəyişin. Heç bir öyrənmə, heç bir xarici məlumat əldə etmə yoxdur.

```
Prompt mühəndisliyi olmadan:
  İstifadəçi: "Bu müqaviləni xülasələyin"
  Model: [uzun, çoxsözlü xülasə]

Prompt mühəndisliyi ilə:
  Sistem: "Siz hüquq analitikisiniz. Müqavilələri tam olaraq 5 nöqtəli siyahıda xülasələyin.
           Hər nöqtə təsirlənmiş tərəfin adı ilə başlamalıdır."
  İstifadəçi: "Bu müqaviləni xülasələyin"
  Model: [strukturlaşdırılmış 5-nöqtəli xülasə]
```

**Nəyi dəyişir**: cavab formatı, ton, ətraflılıq səviyyəsi, mühakimə yanaşması, persona.
**Nəyi dəyişə bilməz**: bilik (modelin öyrənmə məlumatları), müxtəlif girişlər arasında ardıcıl davranış.

### Retrieval-Augmented Generation (RAG)

Sorğu zamanı kontekst pəncərəsinə uyğun xarici bilikləri daxil edin.

```
İstifadəçi sualı ──▶ [Vektor axtarışı] ──▶ [Uyğun sənədləri əldə et]
                                                    │
                                                    ▼
                              [Prompt + əldə edilmiş kontekst] ──▶ LLM ──▶ Cavab
```

**Nəyi dəyişir**: modelin məlumata çıxışı. Öyrədilmədikləri şeylər haqqında suallara cavab verə bilir.
**Nəyi dəyişə bilməz**: modelin necə mühakimə etdiyi və ya cavabları necə formatladığı.

### İncə Tənzimləmə

Öyrənmə məlumatlarınızla modelin çəkilərini yeniləyin.

```
Əsas model (ümumi bilik, ümumi davranış)
       +
Öyrənmə məlumatlarınız (sahə nümunələri, istənilən çıxışlar)
       ↓
İncə tənzimlənmiş model (ümumi bilik + sizin davranışınız)
```

**Nəyi dəyişir**: davranış, üslub, format, sahəyə xas mühakimə nümunələri, şəxsiyyət.
**Nəyi dəyişə bilməz**: əsas modelin öyrənməsindəki faktlar (incə tənzimləmə etibarlı şəkildə bilik əlavə etmir).

---

## Ətraflı Müqayisə Matrisi

| Ölçü | Prompt Mühəndisliyi | RAG | İncə Tənzimləmə |
|---|---|---|---|
| **İnkişaf vaxtı** | Saatlar | Günlər-həftələr | Həftələr-aylar |
| **Tətbiq xərci** | Sıfıra yaxın | Orta (vektor DB, embedding) | Orta-yüksək (GPU hesablaması) |
| **İterasiya sürəti** | Ani | Sürətli | Yavaş (hər iterasiya = yenidən öyrənmə) |
| **Bilik aktüallığı** | Statik (öyrənmə kəsilmə tarixi) | Real vaxt (DB-ni yeniləyin) | Statik (öyrənmə məlumatları) |
| **Bilik tutumu** | Yalnız kontekst pəncərəsi | Məhdudiyyətsiz (əldə etmə) | Məhdudiyyətsiz (lakin örtük) |
| **Format ardıcıllığı** | Dəyişkən | Dəyişkən | Yüksək |
| **Üslub ardıcıllığı** | Dəyişkən | Dəyişkən | Yüksək |
| **Nəticə çıxarma xərci** | Əsas model xərci | Əsas model + əldə etmə | Adətən aşağı (daha kiçik model) |
| **Məxfilik** | Məlumat API-yə gedir | Məlumat API-yə + DB-yə gedir | Məlumat öyrənmə pipeline-na gedir |
| **Sazlanabilirlik** | Asan (bu sadəcə mətndir) | Orta (əldə etmə sazlanması) | Çətin (niyə çəkilər dəyişdi?) |
| **Geri qaytarılabilirlik** | Ani (promptu dəyişin) | Asan (DB-ni yeniləyin) | Çətin (yenidən öyrənin) |
| **Sitat dəstəyi** | Əl ilə | Yerli (əldə edilmiş mənbələr) | Yoxdur |
| **Yeni məlumatı idarə edir** | Bəli (promptda daxil edin) | Bəli (DB-yə əlavə edin) | Xeyr (yenidən öyrənmə tələb olunur) |

---

## Qərar Axışı

```
BAŞLANĞIC: LLM davranışını fərdiləşdirməliyəm
               │
               ▼
    Bunu daha yaxşı bir promptla həll edə bilərəm?
    (format, təlimatlar, nümunələr, persona)
               │
         ┌─────┴─────┐
        BƏLI          XEYİR
         │             │
         ▼             ▼
      Əvvəlcə      Modelə öyrədilmədiyi
      prompt       böyük/dinamik biliklərə
      mühəndisliyi çıxış lazımdırmı?
      sınayın           │
                  ┌─────┴─────┐
                 BƏLI          XEYİR
                  │             │
                  ▼             ▼
                RAG           Bütün girişlər üzrə ardıcıl
                istifadə edin davranış/formata ehtiyacım varmı?
                                  │
                            ┌─────┴─────┐
                           BƏLI          XEYİR
                            │             │
                            ▼             ▼
                        İncə          Az sayda nümunəli
                        tənzimləyin   prompt mühəndisliyi

GELİŞMİŞ: Yanaşmaları birləşdirə bilərəm?
  - RAG + İncə tənzimləmə: üslub üçün incə tənzimləyin, bilik üçün RAG
  - Prompt + RAG: RAG nəticələrinin necə istifadə olunduğunu idarə etmək üçün promptları istifadə edin
  - Hamı birlikdə: davranış üçün incə tənzimləyin, bilik üçün RAG, tapşırıq kontrolü üçün promptlar
```

---

## Hər Birinin Qalib Gəldiyi İstifadə Halları

### Prompt Mühəndisliyi Qalib Gəlir

**1. Format standartlaşdırması**
```
Ehtiyac: Xüsusi sxemlə bütün cavablar JSON formatında
Həll: Sistem promptunda dəqiq JSON sxemini göstərin + strukturlaşdırılmış çıxışlardan istifadə edin
İncə tənzimləmə həddindən artıqdır: sxem tez-tez dəyişə bilər; promptlar dərhal yenilənir
```

**2. Persona və ton**
```
Ehtiyac: Model brendinizin səsinə bənzər danışmalıdır
Həll: Ətraflı persona təsviri + sistem promptunda az sayda nümunə
İncə tənzimləmə riski: persona düşmənçilik promptları altında sürüşə bilər; promptlar açıq-aşkardır
```

**3. Sürətli iterasiya (prototipləmə)**
```
Zaman çizelgesi: MVP qurmaq və ya fikir tədqiq etmək
Həll: Prompt mühəndisliyi
İncə tənzimləmə yanlış seçimdir, çünki: hələ hansı davranışa ehtiyac duyduğunuzu bilmirsiniz
```

**4. Ara sıra kənar vəziyyət idarəsi**
```
Ehtiyac: 5-10 xüsusi giriş nümunəsini fərqli idarə edin
Həll: Sistem promptuna təlimatlar + nümunələr əlavə edin
İncə tənzimləmə həddindən artıqdır: öyrənmə xərcini əsaslandırmaq üçün çox az vəziyyət
```

### RAG Qalib Gəlir

**1. Cari məlumat**
```
Ehtiyac: Dəyişən şeylər haqqında suallara cavab verin (səhm qiymətləri, xəbərlər, məhsul kataloqu)
Həll: Müntəzəm yenilənən bilik bazası ilə RAG
İncə tənzimləmə uğursuz olur, çünki: öyrənmə məlumatları dərhal köhnəlir
```

**2. Böyük məxfi bilik bazası**
```
Ehtiyac: 100.000 daxili sənəd haqqında suallara cavab verin
Həll: Vektor axtarışı ilə RAG
İncə tənzimləmə uğursuz olur, çünki: incə tənzimləmə vasitəsilə 100K sənədin dəyərindəki
                                      bilikləri çəkilərə yerləşdirə bilməzsiniz
```

**3. Mənbə atfı**
```
Ehtiyac: "Budur cavab, budur da mənbə sənəd"
Həll: RAG (əldə edilmiş parçalar sitatlar üçün mövcuddur)
İncə tənzimləmə uğursuz olur, çünki: model bir şeyi haradan öyrəndiyini sitat göstərə bilmir
```

**4. Tənzimlənmiş sənayələr**
```
Ehtiyac: Hər cavab xüsusi bir siyasət sənədinə izlənilə bilər
Həll: RAG — cavabı hansı sənədin verdiyi göstərə bilərsiniz
Prompt mühəndisliyi: mənbəyə izlənilə bilməz
İncə tənzimləmə: mənbəyə izlənilə bilməz
```

**5. Fərdiləşdirilmiş kontekst**
```
Ehtiyac: Model hər istifadəçinin tarixini, üstünlüklərini və keçmiş qarşılıqlı əlaqələrini "bilməlidir"
Həll: İstifadəçi başına yaddaş deposunda RAG
İncə tənzimləmə: istifadəçi başına fərdiləşdirə bilmir (hər istifadəçi üçün ayrı model lazım olardı)
```

### İncə Tənzimləmə Qalib Gəlir

**1. Yüksək həcmdə ardıcıl çıxış strukturu**
```
Ehtiyac: Ayda 1M API cavabı tam eyni XML formatında
Həll: Küçük bir modeli (Llama 7B və ya Mistral 7B) bu format üçün incə tənzimləyin
İqtisadiyyat: incə tənzimlənmiş Llama üçün $0.0002/sorğu vs Claude Haiku üçün $0.003/sorğu
              1M sorğu/ay ilə illik qənaət: ~$33.600
```

**2. Sahəyə xas mühakimə nümunələri**
```
Ehtiyac: Bir təhlükəsizlik müfəttişi kimi mühakimə edən model (sadəcə təhlükəsizlik bilikləri deyil)
Həll: Təhlükəsizlik auditinin mühakimə izlərinin nümunələrini incə tənzimləyin
RAG uğursuz olur: RAG bilik əlavə edir, mühakimə nümunələrini deyil
```

**3. İxtisaslaşmış dil variantları**
```
Ehtiyac: Düzgün qrammatika və idiomlarla Azərbaycan/Suahili/başqa dillərdə yazan model
Həll: Həmin dildə yüksək keyfiyyətli ana dilli mətndə incə tənzimləyin
Prompt mühəndisliyi uğursuz olur: əsas modelin dil qabiliyyəti sabitdir
```

**4. Düşmənçilik promptları altında davranış ardıcıllığı**
```
Ehtiyac: Hətta sövqetdirildikdə belə heç vaxt personasından çıxmayan müştəri xidməti botu
Həll: Meydan oxuma altında personanı qoruyarkən nümunələrle incə tənzimləyin
Prompt mühəndisliyi uğursuz olur: ağıllı istifadəçilər sistem promptlarını ləğv edə bilər
```

**5. Məxfiyyətə həssas yerləşdirmə**
```
Ehtiyac: Heç bir müştəri məlumatı şirkətin serverlərindən çıxa bilməz
Həll: Açıq mənbəli modeli incə tənzimləyin + yerli yerləşdirin
Bulud API uğursuz olur: bütün məlumatlar satıcıya gedir
```

---

## Hibrid Yanaşmalar

### RAG + İncə Tənzimləmə (Ən Güclü)

Modelin necə mühakimə etdiyi və cavab verdiyi üçün incə tənzimləyin; hansı biliklərə çıxış əldə etdiyi üçün RAG istifadə edin.

```
İncə tənzimləmə təmin edir:
  - Dəqiq çıxış formatı (JSON sxemi, xüsusi struktur)
  - Sahəyə xas mühakimə üslubu
  - İstifadəçiləriniz üçün uyğun ətraflılıq səviyyəsi

RAG təmin edir:
  - Cari məhsul kataloqu
  - İstifadəçiyə xas kontekst
  - Siyasət sənədləri və uyğunluq qaydaları
```

**Tətbiq yanaşması**:
1. Əsas modeli davranış nümunələrinde incə tənzimləyin (sahə biliyisiz)
2. Nəticə çıxarma zamanı uyğun sənədləri əldə edin və kontekstə daxil edin
3. İncə tənzimlənmiş model əldə edilmiş məlumatı istənilən format/üslubda istifadə edir

### İncə Tənzimləmə + Prompt Mühəndisliyi

80%-lik ümumi hal üçün bir dəfə incə tənzimləyin. Kənar vəziyyətlərin 20%-ini idarə etmək üçün promptlardan istifadə edin.

```
İncə tənzimlənmiş model: standart sorğuları düzgün formatda idarə edir
Sistem promptu: kənar vəziyyət təlimatları, tarixə xas kontekst, istifadəçi üstünlüklərini əlavə edir
```

### Hər Üçü Birlikdə

Korporativ istehsal nümunəsi:

```
Sistem promptu:    Tapşırıq təlimatları, təhlükəsizlik qaydaları, cari tarix
Əldə edilmiş kontekst: Uyğun sənədlər, istifadəçi tarixi, məhsul məlumatları
İncə tənzimlənmiş model: Sahəyə xas mühakimə + ardıcıl format
```

---

## Həqiqi Rəqəmlərlə Xərc-Fayda Təhlili

### Ssenari: E-ticarət məhsul təsviri yaratma

**Həcm**: Ayda 500.000 təsvir
**Tələb**: Ardıcıl format, brend səsi, SEO-optimallaşdırılmış

**Seçim A: Ətraflı promptla Claude Haiku**
```
Giriş tokenları:  500K sorğu × 200 token  = 100M token × $0.80/1M  = $80
Çıxış tokenları: 500K sorğu × 300 token  = 150M token × $4.00/1M  = $600
Aylıq xərc:  $680
İllik xərc:   $8.160
```

**Seçim B: RunPod-da incə tənzimlənmiş Llama 3.1 8B**
```
Bir dəfəlik incə tənzimləmə:  2.000 nümunə × 3 dövr = ~$15
Nəticə çıxarma xərci:         500K sorğu × 500 token = 250M token
                               4×RTX 4090 üzərində işlənir = $1.80/saat
                               500K təsvir @ 1.000 token/saniyə hərəsi = 250.000 san = 2.9 gün
                               Xərc: 2.9 gün × 24s × $1.80 = $125/ay
Aylıq xərc:  $125
İllik xərc:   $1.515 + $15 incə tənzimləmə = $1.530
İllik qənaət: $6.630 (81% xərc azalması)
```

**Seçim C: Kateqoriyaya xas məlumatlar üçün RAG ilə incə tənzimlənmiş model**
```
Əlavə edir: kateqoriyaya xas məlumat əldə etmə (vektor DB: $50/ay)
Yaxşılaşma: daha kontekstual dəqiq təsvirlər
Cəmi: $175/ay
İllik: $2.115
Yenə qənaət edir: Seçim A-ya nisbətən $6.045/il
```

---

## Memarın Qərar Cədvəli

Dizayn baxışlarında sürətli qərar vermək üçün bu cədvəldən istifadə edin:

| Ssenari | Tövsiyə olunan Yanaşma | Səbəb |
|---|---|---|
| Cari/canlı məlumata ehtiyac var | RAG | Bilik öyrənmə dövrəlrindən daha sürətli dəyişir |
| Həmişə xüsusi çıxış formatı lazımdır | İncə tənzimləyin | Promptlar ləğv edilə bilər |
| Prototip / MVP | Prompt mühəndisliyi | Ən sürətli iterasiya |
| Böyük sənəd Sual-Cavabı | RAG | 10K sənədi çəkilərə yerləşdirə bilməzsiniz |
| Brend səsi / tonu | İncə tənzimləyin (kiçik) | Bütün promptlar boyunca ardıcıl |
| >100K sorğu/ay xərc qənaəti | Açıq mənbəli incə tənzimləyin | İqtisadiyyat qurulum xərcini əsaslandırır |
| Sitatlara ehtiyac var | RAG | Mənbə atfı əldə etməni tələb edir |
| Yeni dil dəstəyi | İncə tənzimləyin | Əsas model qabiliyyətləri sabitdir |
| Məxfilik / yerli | Açıq mənbəli incə tənzimləyin | Xarici API-yə məlumat göndərə bilməzsiniz |
| Çox tapşırıqlı ümumi köməkçi | Prompt mühəndisliyi + RAG | Çeviklik > ardıcıllıq |
| İxtisaslaşmış sahə mühakiməsi | İncə tənzimləyin | Mühakimə nümunələri, yalnız bilik deyil |

---

## Geniş Yayılmış Səhvlər

### İstifadə halını sübut etmədən incə tənzimləmə ilə başlamaq

Düzgün ardıcıllıq: əvvəlcə prompt mühəndisliyi → bilik lazımdırsa RAG → yalnız əsas məhsulun işlədiyini yoxladıqdan sonra incə tənzimləmə.

İncə tənzimləmə davranışınızı sabit edir. Əgər məhsulunuzun istiqaməti dəyişirsə (dəyişəcək), öyrənmə investisiyanızı israf etdiniz.

### RAG-ın hər şeyi həll etdiyini güman etmək

RAG güclüdür, lakin real məhdudiyyətləri var:
- Əldə etmə keyfiyyəti embedding keyfiyyəti ilə məhdudlaşır
- Uzun sənədlər konteksti itirən parçalanma tələb edir
- Bilik bazasındakı ziddiyyətli məlumatlar modeli çaşdırır
- Gecikmə: əldə etmə üçün sorğu başına 100-300ms əlavə edin

### Yanlış məlumatlarla incə tənzimləmə

Klassik səhv: bütün tarixi məlumatlarınızı götürmək və seçim etmədən incə tənzimləmə etmək. Tarixi məlumatlar pis nümunələr, uyuşmaz formatlar və uzaqlaşmağa çalışdığınız davranışları ehtiva edir.

**Qayda**: yalnız öyrənmədən sonra modelin nümayiş etdirməsini istədiyiniz dəqiq davranışı təmsil edən nümunələr daxil edin.

### İncə tənzimləmənin ümumi xərci hesablanarkən nəticə çıxarma xərclərini unutmaq

Bir modeli incə tənzimləmək və sonra onu bahalı bulud GPU-larında işlətmək idarə olunan API-dən istifadədsən daha baha başa gələ bilər. Tam xərci hesablayın:

```
Ümumi xərc = İncə tənzimləmə xərci + (Nəticə çıxarma xərci/sorğu × Sorğu həcmi)
```

Bunu API xərci ilə müqayisə edin. Zərər-mənfəət nöqtəsi istifadə həcminə görə dəyişir.
