# LLM Sampling Parametrləri — Temperature, Top-p, Top-k və Digərləri (Middle)

> Dil modellərinin mətn yaratma prosesini idarə edən hər bir parametr üçün tam bələdçi. Riyaziyyat, intuisiya, tapşırıq növünə görə praktiki tənzimləmələr və Laravel-də konfiqurasiya əsaslı parametr seçici daxildir.

---

## Mündəricat

1. [Sampling Problemi](#sampling-problemi)
2. [Logitlər və Xam Çıxış](#logitlər-və-xam-çıxış)
3. [Temperature](#temperature)
4. [Top-k Sampling](#top-k-sampling)
5. [Top-p (Nucleus) Sampling](#top-p-nucleus-sampling)
6. [Frequency Penalty](#frequency-penalty)
7. [Presence Penalty](#presence-penalty)
8. [Max Tokens](#max-tokens)
9. [Stop Sequences](#stop-sequences)
10. [Parametrlərin Birlikdə İstifadəsi](#parametrlərin-birlikdə-istifadəsi)
11. [Tapşırığa Görə Parametr Reseptləri](#tapşırığa-görə-parametr-reseptləri)
12. [Laravel Kodu: Konfiqurasiya Əsaslı Parametr Seçici](#laravel-kodu-konfiqurasiya-əsaslı-parametr-seçici)
13. [Parametr İstinad Cədvəli](#parametr-istinad-cədvəli)

---

## Sampling Problemi

Transformer-in irəli ötürümündən sonra bizdə **logitlər** vektoru var — lüğətdəki hər token üçün bir xam bal (~100,000 token). Bu balları tam olaraq bir sonrakı tokenə çevirməliyik.

Sadə yanaşmaların problemləri var:

```
GREEDY DECODING (həmişə ən yüksək ballı tokeni seç):
  Üstünlüklər: Deterministik, sürətli, sürpriz yoxdur
  Çatışmazlıqlar: Təkrarlayıcı, sıxıcı, yaradıcı imkanları əldən verir
               Döngülərə düşür: "Pişik oturdu. Pişik oturdu. Pişik oturdu."

XALİS TƏSADÜFİ SAMPLING (softmax nisbətinə görə seç):
  Üstünlüklər: Müxtəlif çıxışlar
  Çatışmazlıqlar: Həddən artıq təsadüfi — 0.001% ehtimalla uyğunsuz tokenlər seçilə bilər
               "Fransanın paytaxtı BANAN-dır"

HƏLL: Nəzarətli sampling
  Keyfiyyət və müxtəlifliyi balanslaşdırmaq üçün samplingdən əvvəl paylanmanı dəyişdirin.
  Temperature, top-k və top-p bu məqsəd üçün alətlərdir.
```

---

## Logitlər və Xam Çıxış

Logitlərin nə olduğunu başa düşmək, bu parametrlərin əslində nə etdiyini anlamağa kömək edir:

```
İrəli ötürümdən sonra, "Fransanın paytaxtı" konteksti üçün:

Token          Logit    Softmax Ehtimal
─────────────────────────────────────
" Paris"       9.42     0.7234
" Lyon"        5.11     0.0312
" Marsel"      4.98     0.0274
" bu"          4.23     0.0128
" bir"         3.87     0.0088
" yerləşir"    3.12     0.0044
" məşhur"      2.98     0.0038
...
" BANAN"      -8.12    ~0.0000001

Softmax: P(token_i) = exp(logit_i) / Σ exp(logit_j)

Model " Paris" haqqında ÇOX əmindir — 72% ehtimal.
Bütün sampling parametrləri son samplingdən əvvəl bu paylanmanı dəyişdirir.
```

---

## Temperature

Temperature `T` softmax-dan əvvəl logitləri miqyaslayır, paylanmanın "zirvəliliyini" idarə edir.

### Riyaziyyat

```
Standart softmax:        P(i) = exp(logit_i)     / Σ exp(logit_j)
Temperature ilə miqyas:  P(i) = exp(logit_i / T) / Σ exp(logit_j / T)
```

### Temperature-un Təsiri

```
Logitlər (sadələşdirilmiş): [" Paris": 9.42, " Lyon": 5.11, " bu": 4.23, ...]

T = 0.0 (greedy):
  " Paris" ehtimalı → 1.0000
  Digərləri         → 0.0000
  Tamamilə deterministik. Hər zaman eyni çıxış.

T = 0.1 (çox aşağı):
  " Paris"     → 0.9999
  " Lyon"      → 0.0001
  Demək olar ki, həmişə " Paris" seçilir. Nadir hallarda sürpriz olur.

T = 0.7 (orta — ümumi standart):
  " Paris"     → 0.8521
  " Lyon"      → 0.0643
  " Marsel"    → 0.0534
  Əsasən " Paris" seçilir, bəzən fərqlənir.

T = 1.0 (dəyişdirilməmiş paylanma):
  " Paris"     → 0.7234
  " Lyon"      → 0.0312
  Modelin öyrənilmiş həqiqi paylanması.

T = 1.5 (yüksəldilmiş):
  " Paris"     → 0.4512
  " Lyon"      → 0.1823
  " bu"        → 0.1234
  Əhəmiyyətli dərəcədə daha təsadüfi. "Maraqlı" amma potensial olaraq yanlış.

T = 2.0 (yüksək — demək olar ki, uniform):
  " Paris"     → 0.2341
  " Lyon"      → 0.1923
  " BANAN"     → 0.0012   ← Ehtimalı az tokenlərin indi real ehtimalı var
  Çox yaradıcı, çox vaxt uyğunsuz.
```

### İntuisiya

Temperature-u "əminlik diyalı" kimi düşünün:

- **Aşağı temperature**: Model çox əmindir. Ən yüksək ehtimallı seçimini seçir. Dəqiqlik, ardıcıllıq və düzgünlük istədikdə yaxşıdır.
- **Yüksək temperature**: Model tərəddüd edir. Ehtimalı daha bərabər paylayır. Çeşidlilik, yaradıcılıq və kəşf istədikdə yaxşıdır.
- **Temperature = 1.0**: Model tam olaraq öyrənildiyini çıxarır — dəyişdirilməmiş öyrənilmiş paylanma.

### Yüksək vs Aşağı Temperature-u Nə Zaman İstifadə Etməli

```
Aşağı temperature (0.0 - 0.3):
  ✓ Faktiki S/C ("Suyun qaynama temperaturu nədir?")
  ✓ Kod generasiyası (sintaktik cəhətdən düzgün kod istəyirsiniz)
  ✓ Məlumat çıxarımı ("Bu mətndən faktura məbləğini çıxar")
  ✓ Təsnifat ("Bu hiss müsbətdir, yoxsa mənfi?")
  ✓ Tərcümə (yüksək dəqiqlik tələb olunur)
  ✓ Riyaziyyat məsələləri
  ✓ Tək "düzgün cavab" olan istənilən tapşırıq

Orta temperature (0.5 - 0.8):
  ✓ Söhbət çat (təbii, lakin erratik deyil)
  ✓ Xülasələmə (ardıcıl, lakin təbii ifadə variasiyası ilə)
  ✓ E-poçt hazırlamaq (peşəkar, lakin robota bənzəyən deyil)
  ✓ Kod izahatı (aydın və bəzən yaradıcı metaforalar)
  ✓ Məhsul təsvirləri

Yüksək temperature (0.9 - 1.5):
  ✓ Yaradıcı yazı, bədii ədəbiyyat, şeir
  ✓ Beyin fırtınası (müxtəlif bucaqları araşdırma)
  ✓ İdeyalar generasiyası
  ✓ Öyrətmə datası üçün müxtəlif nümunələr generasiyası
  ✓ Marketinq kopiyası variasiyaları (A/B test namizədləri)

Produksiyada T > 1.5-dən çəkinin:
  Paylanma demək olar ki, uniform olur. Çıxışlar uyğunsuz olur.
  Faydalıdır: tədqiqat kəşfi, düşmənçilik testi üçün
```

---

## Top-k Sampling

Top-k samplingi `k` ən ehtimallı tokenlərlə məhdudlaşdırır.

### Riyaziyyat

```
1. Tokenləri ehtimala görə sıralayın (azalan)
2. Yalnız ilk k tokeni saxlayın
3. Onların ehtimallarını yenidən normalizə edin ki, cəmi 1.0 olsun
4. Bu azaldılmış paylanmadan nümunə götürün

k=5 ilə, nümunəmizdən:
  " Paris"     0.7234  →  yenidən normalizə: 0.7890
  " Lyon"      0.0312  →  yenidən normalizə: 0.0341
  " Marsel"    0.0274  →  yenidən normalizə: 0.0299
  " bu"        0.0128  →  yenidən normalizə: 0.0140
  " bir"       0.0088  →  yenidən normalizə: 0.0096
  (qalan ~99,995 token atılır)
```

### Top-k Müzakirələri

```
k = 1:    Həmişə ən ehtimallı tokeni seçir. Greedy ilə eynidir.
k = 10:   Sıx fokus. Keyfiyyət və kiçik variasiya arasında yaxşı balans.
k = 50:   Orta. Ən çox yayılmış tənzimləmə.
k = 100:  Daha çox çeşidlilik. Bəzən qeyri-adi seçimlər riski.
k = 0:    Deaktiv edilib (top-k filtri yoxdur). Bunun əvəzinə top-p istifadə edin.

Statik k-nın problemi:
  Bəzən ilk 50 token ehtimal kütləsinin 99.9%-ni əhatə edir.
  Bəzən yalnız 40%-ni əhatə edir.
  Top-k modelin nə dərəcədə "əmin" olduğuna uyğunlaşmır.
  Buna görə top-p çox vaxt üstün tutulur.
```

---

## Top-p (Nucleus) Sampling

Top-p (nucleus sampling da deyilir) kumulativ ehtimalı `p`-ni keçən ən kiçik token dəstini seçir.

### Riyaziyyat

```
Tokenləri ehtimala görə sıralayın (azalan).
Sıralanmış siyahıda irəliləyin, ehtimalı yığın.
Kumulativ ehtimal p-ni keçdikdə dayandırın.
Bu "nucleus"dən nümunə götürün.

p=0.9 ilə, nümunəmizdən:
Addım 1: " Paris" → kumulativ: 0.7234
Addım 2: " Lyon" → kumulativ: 0.7546
Addım 3: " Marsel" → kumulativ: 0.7820
...
Addım n: kumulativ 0.9-a çatır → DAYANIN

Bu n tokendən nümunə götürün (yenidən normalizə edilmiş).
```

### Top-p-nin Top-k-dan Üstün Olmasının Səbəbi

```
SSENARI A: Model çox əmindir
  " Paris":    0.95   ← 95% ehtimal
  " Lyon":     0.02
  " şəhər":   0.01
  ...

  top_k=50 ilə: Kütlənin 95%-i bir tokendə olmasına baxmayaraq
               50 tokendən nümunə alır. Lazımsız dərəcədə təsadüfi.
  top_p=0.9 ilə: " Paris"-i demək olar ki, həmişə seçir. Modelin
                 əmininliyinə hörmət edir. ✓

SSENARI B: Model qeyri-müəyyəndir
  " qırmızı":  0.12
  " mavi":     0.11
  " yaşıl":    0.10
  " sarı":     0.09
  " bənövşəyi": 0.08
  ...bütün yaxın ehtimallar...

  top_k=5 ilə: Yalnız 5 seçim, lakin kütlə 30 tokendə yayılıb.
               Qanuni alternativlər əldən çıxır.
  top_p=0.9 ilə: 90%-ə çatmaq üçün təbii olaraq ~20 tokeni əhatə edir.
                 Uyğun müxtəliflik. ✓
```

### p Dəyərlərinin Seçimi

```
p = 0.9:  Mühafizəkarlıq. Ən ehtimallı tokenlərə fokuslanır.
           Yaxşıdır: faktiki tapşırıqlar, kod, strukturlu çıxış.
p = 0.95: Orta. Ən çox yayılmış standart.
           Yaxşıdır: ümumi məqsədli çat.
p = 0.99: Azad. Nadir hallarda hər hansı tokeni istisna edir.
           Yaxşıdır: yaradıcı tapşırıqlar, müxtəliflik.
p = 1.0:  Deaktiv edilib. Nucleus filtri yoxdur.
```

---

## Frequency Penalty

Generasiya edilmiş çıxışda artıq neçə dəfə göründüklərinə əsasən tokenlərin ehtimalını azaldır.

### Riyaziyyat (OpenAI konvensiyası)

```
Dəyişdirilmiş logit = logit - (frequency_penalty × çıxışdakı_sayı)

Burada çıxışdakı_sayı bu tokenin indiyə qədər generasiya edilmiş
tokenlərda neçə dəfə göründüyüdür (PROMPT-da deyil).

Nümunə:
  " the" tokeni çıxışda 3 dəfə göründü.
  " the" üçün orijinal logit: 4.5
  frequency_penalty=0.5 ilə: 4.5 - (0.5 × 3) = 3.0
  
  Təsir: " the" daha çox istifadə edildikdə daha az ehtimallı olur.
```

### Frequency Penalty-nin Praktikada İstifadəsi

```
frequency_penalty = 0:   Heç bir təsir yoxdur. Model sərbəst təkrarlayır.
frequency_penalty = 0.3: Yüngül. Azaldır, lakin təkrarı tam qarşılamır.
frequency_penalty = 0.7: Orta. Nəzərəçarpacaq dərəcədə müxtəlif lüğət.
frequency_penalty = 1.0: Güclü. Hər token istifadəsinə mütənasib cəzalandırılır.
frequency_penalty = 2.0: Çox güclü. Nadir hallarda hər hansı tokeni təkrarlayır.

Qeyd: Claude-un API-si birbaşa frequency_penalty-ni açıqlamır.
Bu əsasən bir OpenAI parametr konsepsiyasıdır.
Claude təkrarı öyrənməsi vasitəsilə yerli olaraq idarə edir.
```

---

## Presence Penalty

Tezliyindən asılı olmayaraq çıxışda görünmüş tokenlərin ehtimalını azaldır.

### Riyaziyyat (OpenAI konvensiyası)

```
Dəyişdirilmiş logit = logit - (presence_penalty × (token göründüsə 1, əks halda 0))

Bu İKİLİ cəzadır: ya token göründü (və cəzalandırılır)
ya da görünmədi (cəza yoxdur). Frequency penalty-dən fərqli olaraq,
cəza təkrar sayı ilə artmır.

Təsir: Modeli YENİ mövzu və anlayışlar təqdim etməyə təşviq edir,
       yalnız təkrarlanan sözlərdən çəkinmək deyil.

presence_penalty = 0.0: Heç bir təsir yoxdur.
presence_penalty = 0.5: Orta dərəcədə yeni anlayışlara doğru itələmə.
presence_penalty = 1.0: Güclü itələmə. Model aktiv şəkildə mövzulara qayıtmaqdan qaçır.
```

### Frequency vs Presence: Hər Birini Nə Zaman İstifadə Etməli

```
FREQUENCY PENALTY: "Eyni sözləri təkrar-təkrar işlətmə"
  → İstifadə edilir: esselər, yaradıcı yazı, söz səviyyəsindəki təkrarı azaltmaq

PRESENCE PENALTY: "Yeni ideyalar araşdırmağa davam et, mövzulara qayıtma"
  → İstifadə edilir: beyin fırtınası, araşdırma, geniş əhatə etməli olan
                    uzun formatlı məzmun

BİRLİKDƏ:
  frequency=0.3, presence=0.3 → təbii, müxtəlif, mövzuya uyğun çıxış
  frequency=0.7, presence=0.0 → müxtəlif söz seçimi, fokuslanmış mövzu
  frequency=0.0, presence=0.7 → eyni sözlər qəbul edilir, yeni mövzular araşdırılır
```

---

## Max Tokens

`max_tokens` çıxış uzunluğunun ciddi yuxarı həddidir.

### Vacib Nüanslar

```
max_tokens HƏDƏFDİR, nişan deyil.
Model aşağıdakı hallarda dayanır:
  1. EOS (ardıcıllığın sonu) tokeni generasiya etdikdə, YAXUD
  2. Çıxış uzunluğu max_tokens-a çatdıqda

Çox aşağı tənzimləmək:
  - Cavab cümlənin ortasında kəsilir
  - Strukturlu çıxış (JSON) natamam və analiz edilə bilməz ola bilər
  - Strukturlu çıxış üçün bunun əvəzinə stop sequences istifadə edin

Çox yüksək tənzimləmək:
  - Model lazımsız məzmunla "yer doldurmaq" istəyə bilər
  - Daha yüksək xərc (çıxış tokeni üçün ödəyirsiniz)
  - Daha yavaş cavab (daha çox token generasiyası)

Claude-un limitləri:
  claude-haiku-4-5:    8,192 maksimum çıxış tokeni
  claude-sonnet-4-6:   8,192 maksimum çıxış tokeni
  claude-opus-4-7:     4,096 maksimum çıxış tokeni (yazıldığı vaxt)

Praktiki təlimatlar:
  Söhbət cavabı:            256 - 1024
  Texniki izahat:           1024 - 2048
  Kod generasiyası (funksiya): 512 - 2048
  Kod generasiyası (modul):   2048 - 4096
  Uzun formatlı analiz:      2048 - 8192
  İstifadəçiyə axın:         Böyük maks + axın istifadə edin, bitdikdə ləğv edin
```

---

## Stop Sequences

Stop sequences — generasiya edildikdə modelin dərhal dayanmasına səbəb olan sətrlərdir. Stop sequence özü çıxışa daxil edilmir.

### İstifadə Halları və Nümunələr

```php
// Birdən çox JSON obyekti generasiya etməzdən əvvəl dayandırın
'stop_sequences' => ['}\n{', "\n\n---"]

// Funksiyanın sonunda dayandırın (kod tamamlama üçün)
'stop_sequences' => ["\n\nfunction ", "\n\nclass ", "\n\n// ---"]

// Etiket əsaslı strukturlu çıxış üçün — bağlanan etikətdən sonra dayandırın
'stop_sequences' => ['</answer>', '</result>']

// Sıralama üçün — N elementdən sonra dayandırın
// (prompt-dakı təlimatlarla birlikdə)
'stop_sequences' => ["5."]  // "5. [element]"-dən sonra dayandırın

// Modelin bir ayırıcının ötəsinə keçməsinin qarşısını alın
'stop_sequences' => ["---SON---", "Human:", "İstifadəçi:"]
```

### Etibarlı Strukturlu Çıxış üçün Stop Sequences

```
Texnika: Prefill + stop sequence

API-yə göndərin:
  messages: [
    ...,
    {"role": "assistant", "content": "{"}  // açılan mötərizəni prefill edin
  ]
  stop_sequences: ["}"]  // yalnız uyğun bağlanmada dayandırın

Bu modeli məcbur edir:
1. "{" ilə başlasın  (prefill)
2. JSON məzmunu generasiya etsin
3. İlk "}"-da dayansın

Nəticə: Tam olaraq bir tam JSON obyekti alırsınız.
(Qeyd: bu texnika Claude API-nin mesaj prefilling-i ilə işləyir)
```

---

## Parametrlərin Birlikdə İstifadəsi

Bu parametrlər bir-biri ilə əlaqəlidir və ayrıca deyil, birlikdə tənzimlənməlidir.

### Tətbiq Sırası

```
SAMPLING BORULARI (sıra ilə tətbiq edilir):

1. Modelin irəli ötürümündən xam logitlər
          ↓
2. frequency_penalty və presence_penalty tətbiq edin
   (generasiya edilənlərə əsasən logitləri dəyişdirin)
          ↓
3. top-k filtri tətbiq edin
   (yalnız ilk k tokeni saxlayın, digərlərini -inf edin)
          ↓
4. top-p filtri tətbiq edin
   (nucleusu saxlayın, quyruğu -inf edin)
          ↓
5. temperature tətbiq edin
   (qalan logitləri T-yə bölün)
          ↓
6. Softmax
   (etibarlı ehtimal paylanmasına çevirin)
          ↓
7. Bir token seçin
```

### Ümumi Kombinasiyalar

```
Kombinasiya A: Dəqiq faktiki çıxış
  temperature=0.0, top_p=1.0, top_k=0
  → Xalis greedy. Hər dəfə eyni çıxış.

Kombinasiya B: Balanslaşdırılmış produksiya standartı
  temperature=0.7, top_p=0.95, top_k=40
  → Ardıcıl qalarkən təbii variasiya.

Kombinasiya C: Yaradıcı generasiya
  temperature=1.0, top_p=0.95, top_k=0
  → Nucleus filtri ilə tam model paylanması.

Kombinasiya D: Kod generasiyası
  temperature=0.2, top_p=0.95, top_k=50
  → Düzgünlük üçün aşağı temp, üslub üçün kiçik variasiya.

Kombinasiya E: Müxtəlif beyin fırtınası
  temperature=1.2, top_p=0.98, presence_penalty=0.5
  → Yeni ideyalara doğru itələmə, daha geniş paylanma.
```

---

## Tapşırığa Görə Parametr Reseptləri

### Məlumat Çıxarımı

```
Tapşırıq: "Bu PDF mətnindən faktura məbləğini, tarixini və satıcı adını çıxar"
Məqsəd: Dəqiq, ardıcıl, hallüsinasiya yoxdur

temperature:    0.0   // Deterministik — tək düzgün cavab var
top_p:          1.0   // Greedy ilə filtrə ehtiyac yoxdur
top_k:          1     // Greedy
max_tokens:     256   // Qısa, strukturlu cavab
stop_sequences: ["```", "\n\n\n"]  // Kod bloku sonunda dayandırın

Əsaslandırma: Bu faktiki çıxarım tapşırığıdır. "Düzgün" cavab
sənəd tərəfindən müəyyən edilir. Təsadüfilik yalnız dəqiqliyi azaldır.
```

### Kod Generasiyası

```
Tapşırıq: "E-poçt ünvanını yoxlamaq üçün PHP funksiyası yaz"
Məqsəd: Sintaktik cəhətdən düzgün, konvensiyalara uyğun, bəzən yaradıcı

temperature:    0.2   // Aşağı — kod düzgün olmalıdır
top_p:          0.95  // Üslubda kiçik variasiya
top_k:          50    // Namizədlər üzərində ciddi hədd
max_tokens:     1024  // Tam funksiya üçün kifayət qədər
stop_sequences: []    // Modelin özünün dayanmasına icazə verin

Əsaslandırma: Kodun düzgün/yanlış cavabları var (sintaks, məntiq). Aşağı temp
kömək edir. Kiçik variasiya müxtəlif tətbiq üslublarına icazə verir.
```

### Müştəri Dəstəyi Çatı

```
Tapşırıq: Ümumi söhbət müştəri dəstəyi
Məqsəd: Təbii, faydalı, robota bənzəyən deyil

temperature:    0.7   // İfadədə təbii variasiya
top_p:          0.95  // Standart nucleus
top_k:          50    // Standart
max_tokens:     512   // Qısa cavablar üstün tutulur
stop_sequences: []    // Təbii söhbət sonu

Əsaslandırma: Söhbətlər insan hissi verməlidir. T=0.7
robotvari (T=0.1) ilə uyğunsuz (T=1.5) arasında ən optimal nöqtədir.
```

### Yaradıcı Yazı

```
Tapşırıq: "Triller romanı üçün açılış paraqrafı yaz"
Məqsəd: Sürprizli, canlı, klişeyə düşməyən

temperature:    1.0   // Tam model paylanması
top_p:          0.95  // Nucleusu saxla, lakin çeşidliyə icazə ver
top_k:          0     // top-k limiti yoxdur (top-p-yə güvən)
presence_penalty: 0.6 // Yeni anlayışlara doğru itələmə
max_tokens:     300   // Paraqraf uzunluğu

Əsaslandırma: Yaradıcılıq daha az ehtimallı tokenlərin araşdırılmasını tələb edir.
T=1.0 tam paylanmanı verir; presence penalty açılış paraqrafı klişelərinin
qarşısını alır.
```

### Təsnifat

```
Tapşırıq: "Bu dəstək biletini təsnif edin: billing / texniki / hesab / digər"
Məqsəd: Deterministik, tək söz çıxışı

temperature:    0.0
max_tokens:     5     // Bir söz kifayətdir
stop_sequences: ["\n"]

Daha yaxşısı: Strukturlu çıxışı məcbur etmək üçün tool_use istifadə edin:
  {"category": "billing|texniki|hesab|digər"} qəbul edən alət
  Bu T=0 ilə belə samplingdən daha etibarlıdır
```

### Xülasələmə

```
Tapşırıq: 5,000 sözlük sənədi 3 paraqrafa xülasə edin
Məqsəd: Dəqiq, ardıcıl, yaxşı əhatəlik

temperature:    0.4   // Bir qədər müxtəlif ifadə
top_p:          0.95
frequency_penalty: 0.3  // Əsas ifadələrin təkrarından qaçın
max_tokens:     600   // Üç əhəmiyyətli paraqraf

Əsaslandırma: Xülasələr dəqiq (aşağı temp) lakin
təbii şəkildə ifadə edilmiş (T=0 deyil) olmalıdır. Frequency penalty
sənəd ifadələrinin təkrarının qarşısını almağa kömək edir.
```

---

## Laravel Kodu: Konfiqurasiya Əsaslı Parametr Seçici

```php
<?php

declare(strict_types=1);

namespace App\AI\Parameters;

use InvalidArgumentException;

/**
 * Tapşırıqdan xəbərdar olan LLM parametr seçici.
 *
 * Tapşırıq növünə görə parametr konfiqurasiyasını mərkəzləşdirir,
 * bütün AI xüsusiyyətlərini bir yerdən tənzimləməyi asanlaşdırır
 * və tətbiq boyunca ardıcıllığı təmin edir.
 *
 * İstifadə:
 *   $params = ParameterSelector::for('code_generation')
 *       ->withMaxTokens(2048)
 *       ->withCustomStop(['// SON'])
 *       ->build();
 *
 *   Anthropic::messages()->create([
 *       'model' => 'claude-sonnet-4-6',
 *       ...$params,
 *       'messages' => [...],
 *   ]);
 */
class ParameterSelector
{
    /**
     * Tapşırıq növünə görə əvvəlcədən konfiqurasiya edilmiş parametr profilləri.
     *
     * Empirik testlərə və dərc edilmiş ən yaxşı praktikalara əsaslanır.
     * Bütün dəyərlər sizin xüsusi domeniniz üçün nəzərdən keçirilməli
     * və tənzimlənməlidir.
     */
    private const PROFILES = [
        'data_extraction' => [
            'temperature'      => 0.0,
            'top_p'            => 1.0,
            'top_k'            => 1,
            'max_tokens'       => 512,
            'stop_sequences'   => [],
            'description'      => 'Deterministik çıxarım — dəqiqlik üçün sıfır temperature',
        ],

        'classification' => [
            'temperature'      => 0.0,
            'top_p'            => 1.0,
            'top_k'            => 1,
            'max_tokens'       => 20,
            'stop_sequences'   => ["\n"],
            'description'      => 'Deterministik tək etiket təsnifatı',
        ],

        'code_generation' => [
            'temperature'      => 0.2,
            'top_p'            => 0.95,
            'top_k'            => 50,
            'max_tokens'       => 2048,
            'stop_sequences'   => [],
            'description'      => 'Kiçik üslub variasiyası ilə aşağı-temp kod generasiyası',
        ],

        'code_review' => [
            'temperature'      => 0.3,
            'top_p'            => 0.95,
            'top_k'            => 50,
            'max_tokens'       => 1024,
            'stop_sequences'   => [],
            'description'      => 'Ardıcıl tapıntılarla analitik kod nəzərdən keçirmə',
        ],

        'conversational' => [
            'temperature'      => 0.7,
            'top_p'            => 0.95,
            'top_k'            => 50,
            'max_tokens'       => 1024,
            'stop_sequences'   => [],
            'description'      => 'Təbii söhbət cavabları',
        ],

        'customer_support' => [
            'temperature'      => 0.6,
            'top_p'            => 0.95,
            'top_k'            => 50,
            'max_tokens'       => 512,
            'stop_sequences'   => [],
            'description'      => 'Faydalı, peşəkar dəstək cavabları',
        ],

        'summarization' => [
            'temperature'      => 0.4,
            'top_p'            => 0.95,
            'top_k'            => 50,
            'max_tokens'       => 800,
            'stop_sequences'   => [],
            'description'      => 'Təbii ifadə ilə dəqiq xülasələmə',
        ],

        'creative_writing' => [
            'temperature'      => 1.0,
            'top_p'            => 0.95,
            'top_k'            => 0,
            'max_tokens'       => 2048,
            'stop_sequences'   => [],
            'description'      => 'Yaradıcı, müxtəlif, klişeyə düşməyən məzmun',
        ],

        'brainstorming' => [
            'temperature'      => 1.1,
            'top_p'            => 0.98,
            'top_k'            => 0,
            'max_tokens'       => 1024,
            'stop_sequences'   => [],
            'description'      => 'Müxtəlif ideya generasiyası, aşkara ötür',
        ],

        'translation' => [
            'temperature'      => 0.1,
            'top_p'            => 0.95,
            'top_k'            => 50,
            'max_tokens'       => 2048,
            'stop_sequences'   => [],
            'description'      => 'Təbii ifadə ilə dəqiq tərcümə',
        ],

        'structured_json' => [
            'temperature'      => 0.0,
            'top_p'            => 1.0,
            'top_k'            => 1,
            'max_tokens'       => 2048,
            'stop_sequences'   => [],
            'description'      => 'Deterministik strukturlu çıxış generasiyası',
        ],

        'reasoning' => [
            'temperature'      => 0.5,
            'top_p'            => 0.95,
            'top_k'            => 50,
            'max_tokens'       => 4096,
            'stop_sequences'   => [],
            'description'      => 'Orta determinizmlə analitik əsaslandırma',
        ],
    ];

    private array $overrides = [];

    private function __construct(private readonly string $taskType)
    {
        if (!isset(self::PROFILES[$taskType])) {
            throw new InvalidArgumentException(
                "Naməlum tapşırıq növü '{$taskType}'. Mövcud olanlar: " .
                implode(', ', array_keys(self::PROFILES))
            );
        }
    }

    /**
     * Verilmiş tapşırıq növü üçün parametr seçici yaradın.
     */
    public static function for(string $taskType): static
    {
        return new static($taskType);
    }

    /**
     * Bu sorğu üçün max_tokens-i ləğv edin.
     */
    public function withMaxTokens(int $maxTokens): static
    {
        $this->overrides['max_tokens'] = $maxTokens;
        return $this;
    }

    /**
     * Temperature-u ləğv edin.
     */
    public function withTemperature(float $temperature): static
    {
        if ($temperature < 0.0 || $temperature > 2.0) {
            throw new InvalidArgumentException('Temperature 0.0 ilə 2.0 arasında olmalıdır');
        }
        $this->overrides['temperature'] = $temperature;
        return $this;
    }

    /**
     * Stop sequences əlavə edin və ya dəyişdirin.
     */
    public function withStopSequences(array $stops): static
    {
        $this->overrides['stop_sequences'] = $stops;
        return $this;
    }

    /**
     * Profilin standartlarına əlavə stop sequences əlavə edin.
     */
    public function addStopSequences(array $stops): static
    {
        $existing = $this->overrides['stop_sequences']
            ?? self::PROFILES[$this->taskType]['stop_sequences'];
        $this->overrides['stop_sequences'] = array_values(
            array_unique(array_merge($existing, $stops))
        );
        return $this;
    }

    /**
     * API çağırışı üçün son parametrlər massivini yaradın.
     *
     * Yalnız Claude API tərəfindən dəstəklənən parametrləri qaytarır.
     * OpenAI-yə xas parametrlər (frequency_penalty, presence_penalty)
     * OpenAI çağırışı deyilsə istisna edilir.
     */
    public function build(string $provider = 'claude'): array
    {
        $profile = self::PROFILES[$this->taskType];
        $params = array_merge($profile, $this->overrides);

        $apiParams = [
            'max_tokens'     => $params['max_tokens'],
            'temperature'    => $params['temperature'],
        ];

        // top_p Claude tərəfindən dəstəklənir
        if (isset($params['top_p']) && $params['top_p'] < 1.0) {
            $apiParams['top_p'] = $params['top_p'];
        }

        // top_k Claude tərəfindən dəstəklənir (OpenAI deyil)
        if ($provider === 'claude' && isset($params['top_k']) && $params['top_k'] > 0) {
            $apiParams['top_k'] = $params['top_k'];
        }

        // stop_sequences
        if (!empty($params['stop_sequences'])) {
            $apiParams['stop_sequences'] = $params['stop_sequences'];
        }

        return $apiParams;
    }

    /**
     * Cari profilin insan tərəfindən oxunan təsvirini əldə edin.
     */
    public function describe(): string
    {
        $profile = self::PROFILES[$this->taskType];
        return "[{$this->taskType}] {$profile['description']}";
    }

    /**
     * Bütün mövcud tapşırıq növlərini əldə edin.
     */
    public static function availableTypes(): array
    {
        return array_keys(self::PROFILES);
    }

    /**
     * Tam profil məlumatını əldə edin (qeyd/debug üçün).
     */
    public function getProfile(): array
    {
        return array_merge(
            self::PROFILES[$this->taskType],
            $this->overrides,
            ['task_type' => $this->taskType]
        );
    }
}
```

### İstifadə Nümunələri

```php
<?php

use App\AI\Parameters\ParameterSelector;
use Anthropic\Laravel\Facades\Anthropic;

// 1. Kod generasiyası
$params = ParameterSelector::for('code_generation')
    ->withMaxTokens(3000)
    ->build();

$response = Anthropic::messages()->create([
    'model' => 'claude-sonnet-4-6',
    'system' => 'Siz ekspert PHP developersınız.',
    'messages' => [['role' => 'user', 'content' => 'Ödəniş emalı üçün Laravel servis sinifi yazın']],
    ...$params,
]);

// 2. Xüsusi dayandırma ilə məlumat çıxarımı
$params = ParameterSelector::for('data_extraction')
    ->withMaxTokens(200)
    ->addStopSequences(['</extracted>'])
    ->build();

// 3. Tənzimlənmiş temperature ilə yaradıcı yazı
$params = ParameterSelector::for('creative_writing')
    ->withTemperature(1.2)
    ->withMaxTokens(500)
    ->build();

// 4. Debug üçün profili qeyd edin
$selector = ParameterSelector::for('conversational');
logger()->debug('AI parametrləri', $selector->getProfile());
```

### Parametrləri Test Etmək üçün Artisan Əmri

```php
<?php

namespace App\Console\Commands;

use App\AI\Parameters\ParameterSelector;
use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Console\Command;

class TestAIParameters extends Command
{
    protected $signature = 'ai:test-params 
        {task : Tapşırıq növü (məs. code_generation, creative_writing)}
        {--prompt= : Test prompt}
        {--temperature= : Temperature-u ləğv et}
        {--max-tokens=500 : Maksimum çıxış tokenləri}';

    protected $description = 'AI parametr profillərini interaktiv şəkildə test edin';

    public function handle(): int
    {
        $taskType = $this->argument('task');
        $prompt = $this->option('prompt') ?? 'Paris haqqında qısa bir paraqraf yazın.';

        try {
            $selector = ParameterSelector::for($taskType);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            $this->line('Mövcud növlər: ' . implode(', ', ParameterSelector::availableTypes()));
            return self::FAILURE;
        }

        if ($this->option('temperature') !== null) {
            $selector->withTemperature((float) $this->option('temperature'));
        }

        $selector->withMaxTokens((int) $this->option('max-tokens'));
        $params = $selector->build();

        $this->info("Profil: " . $selector->describe());
        $this->table(
            ['Parametr', 'Dəyər'],
            collect($params)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])->toArray()
        );

        $this->line('');
        $this->info("Prompt: {$prompt}");
        $this->line('');

        $response = Anthropic::messages()->create([
            'model' => 'claude-haiku-4-5-20251001', // Ucuz test üçün Haiku istifadə edin
            'system' => 'Siz faydalı bir köməkçisiniz.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            ...$params,
        ]);

        $this->info("Cavab:");
        $this->line($response->content[0]->text);
        $this->line('');
        $this->comment("İstifadə edilən tokenlər: {$response->usage->inputTokens} giriş, {$response->usage->outputTokens} çıxış");

        return self::SUCCESS;
    }
}
```

---

## Parametr İstinad Cədvəli

| Parametr | Aralıq | Standart | Təsir | Claude Dəstəyi |
|-----------|-------|---------|--------|----------------|
| `temperature` | 0.0 – 2.0 | 1.0 | Paylanma kəskinliyi | Bəli |
| `top_p` | 0.0 – 1.0 | 1.0 | Nucleus ölçüsü | Bəli |
| `top_k` | 0 – lüğət | deaktiv | Ciddi token hədd | Bəli |
| `max_tokens` | 1 – 8192 | Tələb olunur | Çıxış uzunluq limiti | Bəli (tələb olunur) |
| `stop_sequences` | sətir siyahısı | [] | Erkən dayandırma tetikleyicileri | Bəli (4-ə qədər) |
| `frequency_penalty` | 0.0 – 2.0 | 0.0 | Təkrarlanan tokenləri cəzalandırır | Yalnız OpenAI |
| `presence_penalty` | 0.0 – 2.0 | 0.0 | Təkrarlanan hər mövzunu cəzalandırır | Yalnız OpenAI |
| `seed` | tam ədəd | təsadüfi | Təkrar edilə bilən çıxışlar | Yalnız OpenAI |
| `logit_bias` | {token_id: bias} | {} | Əl ilə token ağırlığı tənzimlənməsi | Yalnız OpenAI |

### Claude-a Məxsus Mülahizələr

```
1. Temperature və top_p eyni zamanda standartın altına endirilməməlidir.
   Anthropic yalnız birini və ya digərini dəyişdirməyi tövsiyə edir.

2. temperature=0 ilə top_k=1 deterministik greedy decoding verir.

3. Claude-un öyrənməsinə daxil edilmiş daxili təkrar cəzası var —
   GPT modelləri ilə müqayisədə frequency_penalty-yə o qədər ehtiyac yoxdur.

4. Stop sequences: Claude hər sorğu üçün 4-ə qədər stop sequence dəstəkləyir.

5. Axın: Bütün parametrlər axın aktiv olduqda eyni işləyir.
   Axın yalnız tokenlərin NƏ ZAMANI çatdırıldığına təsir edir, HANSIlarına deyil.
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Task-based Parameter Presets

Laravel-də `config/ai.php`-ə task presets əlavə et:
- `extraction`: `temperature=0.0, top_p=1.0`
- `classification`: `temperature=0.1`
- `creative_writing`: `temperature=0.9, top_p=0.95`
- `code_generation`: `temperature=0.2`

Hər preset ilə 10 sorğu çalışdır. Extraction task-da `temperature=0.9` ilə nə baş verir?

### Tapşırıq 2: Deterministiklik Testi

`temperature=0.0` ilə eyni kod review prompt-unu 10 dəfə çalışdır. Cavablar identikdir? Fərqliliklər haradandır? (`top_k`, nucleus sampling-in internal mexanizmi ilə əlaqəlidir.)

### Tapşırıq 3: A/B Test — Temperature Effect on Code Quality

Kod generasiya task-ı üçün `temperature=0.0` vs `temperature=0.4` A/B testi qur. 50 sorğu, hər birinə iki cavab al. Kod keyfiyyətini (syntax, logic, edge cases) ölç. Hansı temperature daha dəqiq, hansı daha creativity-rich nəticə verir?

---

## Əlaqəli Mövzular

- `01-how-ai-works.md` — Sampling parametrlərinin transformer inference-ə təsiri
- `02-models-overview.md` — Müxtəlif model ailələrinin default parameter-ları
- `../02-claude-api/02-prompt-engineering.md` — Prompt + parameter kombinasiyasının optimallaşdırılması
- `../07-workflows/07-ai-ab-testing.md` — Parameter dəyişikliklərini A/B test ilə ölçmə
