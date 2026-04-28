# Əsas AI Modelləri — Müqayisə, Qərar Matrisi və Arxitektor Bələdçisi (Junior)

> Hədəf auditoriyası: İstehsal sistemləri üçün düzgün modeli seçməli, mübadilələri dərindən başa düşməli və model-agnostik infrastruktur qurmalı olan arxitektorlar və senior developerlər.

---

## Mündəricat

1. [Niyə Model Seçimi Vacibdir](#why-model-selection-matters)
2. [Claude Ailəsi (Anthropic)](#claude-family-anthropic)
3. [GPT-4o (OpenAI)](#gpt-4o-openai)
4. [Gemini 1.5 Pro (Google)](#gemini-15-pro-google)
5. [Llama 3 (Meta)](#llama-3-meta)
6. [Mistral Ailəsi](#mistral-family)
7. [Qwen (Alibaba)](#qwen-alibaba)
8. [Hərtərəfli Müqayisə Cədvəli](#comprehensive-comparison-table)
9. [Qərar Matrisi](#decision-matrix)
10. [Arxitektor üçün Model Seçimi Çərçivəsi](#architects-model-selection-framework)
11. [Xərc Modelləşdirməsi](#cost-modeling)
12. [Model-Agnostik Sistemlər Qurmaq](#building-model-agnostic-systems)

---

## Niyə Model Seçimi Vacibdir

Model seçimi yalnız performans qərarı deyil — aşağıdakılar üçün nəticələri olan bir **sistem arxitekturası qərarıdır**:

- **Xərc**: Eyni tapşırıqda modellər arasında 10-100x fərq
- **Gecikmə**: İlk tokena qədər 50ms-dən 5000ms-ə qədər
- **Məxfilik**: Bulud API-ya qarşı öz infrastrukturunda ev sahibliyi
- **Uyğunluq**: Məlumatların saxlanma yeri, GDPR, HIPAA
- **İmkan**: Model tapşırığı etibarlı şəkildə yerinə yetirə bilirmi?
- **Vendor kilidlənməsi**: Keçid nə qədər asandır?

Yaxşı arxitekturalanmış sistem, modeli bir interfeysin arxasında mücərrədləşdirir və model seçimini kod problemi yox, konfiqurasiya problemi kimi qəbul edir.

---

## Claude Ailəsi (Anthropic)

Anthropic, Claude-u təhlükəsizlik, etibarlılıq və tapşırıq-izlənilmə keyfiyyəti ətrafında mövqeləndirir. Claude modelləri mürəkkəb tapşırıqları dəqiq yerinə yetirməyi, uzun context-ləri yaxşı idarə etməyi və zərərli çıxışlar üçün manipulyasiyaya müqavimət göstərməyi ilə tanınır.

### Claude Haiku 4.5 (`claude-haiku-4-5-20251001`)

```
Parametrlər:     ~Naməlum (Anthropic açıqlamır)
Context window: 200,000 token
Giriş qiyməti:  ~$0.80 / 1M token
Çıxış qiyməti:  ~$4.00 / 1M token
Sürət:          Claude ailəsinin ən sürətlisi
```

**Güclü tərəflər:**
- Son dərəcə sürətli — qısa tapşırıqlar üçün demək olar ki ani
- Aşağı xərc — yüksək həcmli tətbiqlər üçün uyğun
- Sadə təsnifat, çıxarma, marşrutlaşdırmada mükəmməl
- Yaxşı müəyyənləşdirilmiş tapşırıqlarda əla tapşırıq izlənilməsi

**Zəif tərəflər:**
- Mürəkkəb çox addımlı mühakimədə daha az bacarıqlı
- Qeyri-müəyyən tapşırıqlardakı incəlikləri qaçıra bilər
- Uzun yaradıcı və ya analitik yazı üçün ideal deyil

**Ən yaxşı istifadə:**
- Semantik marşrutlaşdırma (hansı agent/boru kəməri bu işi görməlidir?)
- Sadə təsnifat və etiketləmə
- Yüksək həcmli məlumat çıxarma boru kəmərləri
- İstifadəçiyə yönəlik avtomatik tamamlama və ya sürətli təkliflər
- Daha ağır modelə göndərməzdən əvvəl girişləri ön-filtrasiya

---

### Claude Sonnet 4.6 (`claude-sonnet-4-6`)

```
Parametrlər:     ~Naməlum
Context window: 200,000 token
Giriş qiyməti:  ~$3.00 / 1M token
Çıxış qiyməti:  ~$15.00 / 1M token
Sürət:          Sürətli, sürət-imkan balansı yaxşı
```

**Güclü tərəflər:**
- Optimal nöqtə: Opus imkanına çox daha aşağı xərclə yaxın
- Kod generasiyası, debug və izahda mükəmməl
- Güclü uzun-context mühakiməsi (200k window-dan səmərəli istifadə)
- Mürəkkəb, iç-içə promptlarda üstün tapşırıq izlənilməsi
- Strukturlaşdırılmış çıxışda (JSON, XML sxemləri) çox yaxşı
- Tool use / function calling etibarlıdır

**Zəif tərəflər:**
- Mühakimənin kənar hallarında Opus-dan bəzən daha az nüanslı
- Yaradıcı yazı bəzən "ilhamlı"dan daha "peşəkar" hiss verir

**Ən yaxşı istifadə:**
- Keyfiyyətin önəmli olduğu istehsal AI xüsusiyyətləri
- Kod köməkçiləri, kod nəzəriyyəsi, kod generasiyası
- Mürəkkəb sənəd analizi
- Səhvlərin nəticə verdiyi müştəri-yönəlik çat
- Tool use-lu agent tapşırıqları
- Əksər RAG boru kəmərləri
- **Ciddi tətbiqlərin əksəriyyəti üçün standart seçim**

---

### Claude Opus 4.6 (`claude-opus-4-6`)

```
Parametrlər:     ~Naməlum (ailənin ən böyüyü)
Context window: 200,000 token
Giriş qiyməti:  ~$15.00 / 1M token
Çıxış qiyməti:  ~$75.00 / 1M token
Sürət:          Ən yavaş — real vaxt üçün uyğun deyil
```

**Güclü tərəflər:**
- Ailədə ən yaxşı mühakimə keyfiyyəti
- Həqiqi nəticə çıxarma tələb edən yeni tapşırıqlarda üstün
- Son dərəcə qeyri-müəyyən və ya mürəkkəb tapşırıqları daha yaxşı izləyir
- Daha "fikirlərini bildirən" və daha yaxşı kalibre edilmiş qeyri-müəyyənlik
- Səhvlərin çox baha başa gəldiyi tapşırıqlar üçün ən yaxşısı

**Zəif tərəflər:**
- Sonnet-dən 5x baha
- Xeyli yavaş — interaktiv istifadə üçün sinir bozucu
- Əksər tapşırıqlar üçün həddindən artıq güclü

**Ən yaxşı istifadə:**
- Yüksək riskli, az həcmli analiz (tibbi, hüquqi, maliyyə)
- Mürəkkəb çox addımlı araşdırma tapşırıqları
- Tapşırıq keyfiyyətini benchmark etdiyiniz zaman, xərc ikinci plandadır
- Çıxışın incə kontekstini dərindən başa düşmə tələb edən tapşırıqlar
- Səhvlərin kaskad yaratdığı və yenidən cəhdlərin baha olduğu agent tapşırıqları

---

## GPT-4o (OpenAI)

```
Model ID:       gpt-4o, gpt-4o-mini
Context window: 128,000 token (gpt-4o), 128,000 (mini)
Giriş qiyməti:  $5.00 / 1M token (4o), $0.15 (mini)
Çıxış qiyməti:  $15.00 / 1M token (4o), $0.60 (mini)
Modallar:       Mətn, görüntü, audio (native), kod
```

**Güclü tərəflər:**
- Mükəmməl ekosistem — geniş üçüncü tərəf alətlər GPT-4-ü hədəfləyir
- Müxtəlif tapşırıqlarda güclü benchmark performansı
- Native audio giriş/çıxış (səsdən-sə)
- Müəssisə üçün etibarlılıq SLA-ları olan böyük provayder
- STEM mühakiməsi və riyaziyyatda çox güclü
- Daxili fayl axtarışı və kod interpretatorlu Assistants API

**Zəif tərəflər:**
- Claude-dan daha kiçik context window (128k-ya qarşı 200k)
- Mürəkkəb sistem promptlarını izləməkdə qeyri-sabit ola bilər
- Bəzən "köməkçi beyin" — yanlış fərziyyələrlə bəzən razılaşır
- Layiqli sürət həddləri üçün müəssisə tipi tələb olunur

**Ən yaxşı istifadə:**
- Native audio/səs interaksiyası tələb edən tətbiqlər
- OpenAI ekosisteminə (Assistants API) dərindən bağlı komandalar
- Kod interpretatoru (Python icra sandboxu) lazım olduğunda
- gpt-4o-mini xərclərinin önəmli olduğu yüksək həcmli tapşırıqlar
- Üçüncü tərəf alətlər açıq şəkildə OpenAI-ı hədəfləyəndə

---

## Gemini 1.5 Pro (Google)

```
Model ID:       gemini-1.5-pro, gemini-1.5-flash
Context window: 1,000,000 token (1M!), 1,000,000 (flash)
Giriş qiyməti:  $3.50 / 1M token (<=128k), $7.00 (>128k)
Çıxış qiyməti:  $10.50 / 1M token
Modallar:       Mətn, görüntü, audio, video, sənədlər
```

**Güclü tərəflər:**
- **1 milyon token context window** — sənəd analizi üçün rəqibsiz
- Native video anlayışı (yalnız kadrlar deyil)
- Çoxdilli tapşırıqlarda güclü (xüsusilə Asiya dilləri)
- Google Cloud (Vertex AI) ilə sıx inteqrasiya
- Kodlaşdırma tapşırıqlarında yaxşı performans

**Zəif tərəflər:**
- Claude ilə müqayisədə qeyri-sabit tapşırıq izlənilməsi
- Böyük context-lər üçün yüksək gecikmə
- Təhlükəsizlik filtrləri aqressiv və gözlənilməz ola bilər
- Açıq prompting olmadan daha az proqnozlaşdırılan strukturlaşdırılmış çıxış

**Ən yaxşı istifadə:**
- Çox uzun sənədlərin işlənməsi (bütün kod bazaları, kitablar)
- Video analiz boru kəmərləri
- Çoxdilli tətbiqlər (xüsusilə Asiya dilləriylə)
- Google Cloud-native arxitekturalar
- Eyni anda bir neçə böyük sənədin alınması tələb edən tapşırıqlar

---

## Llama 3 (Meta)

```
Modellər:       Llama 3.1 8B, 70B, 405B
Context window: 128,000 token
Lisenziya:      Açıq çəkili (xüsusi Meta lisenziyası — əksər hallarda ticarət üçün uyğun)
Hosting:        Öz ev sahibliyi və ya provayderlər vasitəsilə (Groq, Together, Fireworks)
Qiymət:        ~$0.20-0.90 / 1M token API vasitəsilə, ya da öz ev sahibliyi
```

**Güclü tərəflər:**
- **Açıq çəkilər** — öz infrastrukturunda yerləş
- İncə ayarlama üzərində tam nəzarət
- Heç bir məlumat infrastrukturu tərk etmir
- 70B model öz çəki sinfinin çox üstündə nəticə göstərir
- Groq-un LPU avadanlığında sürətli (~800 token/saniyə)
- Kodda güclü (xüsusilə CodeLlama törəmələri kimi incə ayarlamalarla)

**Zəif tərəflər:**
- Öz ev sahibliyini etmək üçün infrastruktur təcrübəsi tələb edir
- Claude/GPT-4-dən daha az inkişaf etmiş tapşırıq izlənilməsi
- RLHF keyfiyyəti ön sıra modellərin arxasındadır
- Təhlükəsizlik uyğunlaşdırması daha "qırılgandır"

**Ən yaxşı istifadə:**
- Hava boşluğu olan və ya ciddi məlumat egemenliyi tələbləri (HIPAA, GDPR, və s.)
- Keyfiyyət həddinin karşılandığı yüksək həcmli, xərclərə həssas tapşırıqlar
- Domain-spesifik tapşırıqlar üçün incə ayarlama
- Prototip hazırlama və inkişaf (pulsuz lokal test)
- Model fərdiləşdirməsi məhsula əsas olan tətbiqlər

---

## Mistral Ailəsi

```
Modellər:       Mistral 7B, Mixtral 8x7B, Mixtral 8x22B, Mistral Large
Context window: 32,000 - 128,000 token
Lisenziya:      Açıq çəkili (kiçik modellər üçün Apache 2.0)
Qiymət:        $0.70 - $6.00 / 1M token Mistral API vasitəsilə
```

**Güclü tərəflər:**
- 7B/8x7B üçün Apache 2.0 lisenziyası — həqiqətən açıq mənbə
- Mixtral **Mixture of Experts (MoE)** arxitekturasından istifadə edir — səmərəli
- Güclü çoxdilli (xüsusilə Avropa dilləri)
- Function calling dəstəyi
- Strukturlaşdırılmış çıxışda yaxşı

**Zəif tərəflər:**
- Mürəkkəb mühakimədə Claude/GPT-4-ün arxasında
- Claude və ya Gemini-dən daha kiçik context window
- Kənar hallarda daha az etibarlı tapşırıq izlənilməsi

**MoE arxitekturası qeydi:** Mixtral 8x7B-nin cəmi 46 milyard parametri var, lakin hər forward ötürümündə yalnız ~12 milyard aktivləşir (8 ekspert şəbəkəsinin 2-si). Bu sizə 7 milyard inference xərciylə 70 milyard sinif performansı verir — əhəmiyyətli bir səmərəlilik qazancı.

**Ən yaxşı istifadə:**
- Dil/uyğunluq tələbləri olan Avropa yerləşdirmələri
- 7 milyarddan daha yaxşı keyfiyyət tələb edən xərc-effektiv tapşırıqlar
- Həqiqətən açıq mənbə (Apache 2.0) model tələb edən tətbiqlər
- GPU məhdudiyyətli yerləşdirmə yerləri

---

## Qwen (Alibaba)

```
Modellər:       Qwen2.5 7B, 14B, 32B, 72B, Qwen2.5-Coder
Context window: 128,000 token
Lisenziya:      Açıq çəkili (Tongyi Qianwen lisenziyası — ticarət üçün pulsuz)
Qiymət:        Öz ev sahibliyi və ya Alibaba Cloud vasitəsilə
```

**Güclü tərəflər:**
- Çin dili tapşırıqlarında mükəmməl
- Qwen2.5-Coder, kodlaşdırma benchmark-larında GPT-4 ilə rəqabət aparır
- Uzun context imkanı
- Güclü riyaziyyat performansı (Qwen2.5-Math)
- Çoxlu hosting provayderlərindən əlçatanlıq

**Zəif tərəflər:**
- İngilis dili tapşırıq izlənilməsi Claude/GPT-4-ün arxasında qalır
- Qərb istehsal mühitlərindəki testlər daha azdır
- Daha kiçik icma və ekosistem

**Ən yaxşı istifadə:**
- Çin dili tətbiqləri (burada dominant seçim)
- Kodlama yüklü tətbiqlər (Qwen2.5-Coder)
- Alibaba Cloud ilə Asiya-Sakit Okean yerləşdirmələri
- Aşağı xərclə yüksək həcmli kod generasiyası

---

## Hərtərəfli Müqayisə Cədvəli

| Model | Context | Güclü tərəflər | Zəif tərəflər | Ən yaxşı istifadə | Qiymət (1M üçün Giriş/Çıxış) |
|-------|---------|-----------|------------|----------|---------------------------|
| Claude Haiku 4.5 | 200k | Sürət, xərc | Zəif mühakimə | Marşrutlaşdırma, təsnifat, yüksək həcm | $0.80 / $4.00 |
| Claude Sonnet 4.6 | 200k | Balans, kod, alətlər | — | Əksər istehsal tapşırıqları | $3.00 / $15.00 |
| Claude Opus 4.6 | 200k | Ən yaxşı mühakimə | Yavaş, baha | Yüksək riskli, mürəkkəb | $15.00 / $75.00 |
| GPT-4o | 128k | Ekosistem, audio | Kiçik context | Səs, kod interpretatoru, OpenAI ekosistemi | $5.00 / $15.00 |
| GPT-4o-mini | 128k | Xərc | Zəif keyfiyyət | Yüksək həcm, xərclərə həssas | $0.15 / $0.60 |
| Gemini 1.5 Pro | 1M | Nəhəng context, video | Qeyri-sabit | Uzun sənədlər, video, çoxdilli | $3.50 / $10.50 |
| Gemini 1.5 Flash | 1M | Sürət, nəhəng context | Daha az imkanlı | Yüksək həcmli uzun context | $0.35 / $1.05 |
| Llama 3.1 70B | 128k | Açıq çəkili, nəzarət | Daha az cilalanmış | Öz ev sahibliyi, incə ayarlama, məxfilik | ~$0.90 / $0.90 |
| Llama 3.1 405B | 128k | Açıq, ön səfərə yaxın | İnfrastruktur ağır | Öz ev sahib ön sıra | ~$5.00 / $5.00 |
| Mixtral 8x22B | 64k | MoE səmərəliliyi, açıq mənbə | Kiçik context | Açıq mənbə, Avropa dilləri | $2.00 / $6.00 |
| Qwen2.5-72B | 128k | Çin dili, riyaziyyat | İngilis keyfiyyəti | Çin tətbiqləri, riyaziyyat, kodlaşdırma | ~$1.00 / $1.00 |
| Qwen2.5-Coder | 128k | Kod generasiyası | Ümumi tapşırıqlar | Kodlama yüklü tətbiqlər | ~$0.50 / $0.50 |

---

## Qərar Matrisi

### "X-i nə vaxt istifadə etməliyəm..."

```
┌─────────────────────────────────────────────────────────────────┐
│  QƏRAR: Claude Haiku 4.5                                        │
│                                                                 │
│  ✓ Tapşırıq sadə və yaxşı müəyyənləşdirilmişdir (təsnifat,    │
│    çıxarma, marşrutlaşdırma, qiymətləndirmə)                    │
│  ✓ Həcm YÜKSƏKDIR (gündə milyonlarla sorğu)                   │
│  ✓ Gecikmə < 500ms olmalıdır                                  │
│  ✓ Bir qədər aşağı keyfiyyəti qəbul edə bilirsiniz             │
│  ✓ Daha ağır modelə göndərməzdən əvvəl ön-filtrasiya etmək     │
│    istəyirsiniz                                                 │
│                                                                 │
│  Nümunə tapşırıqlar:                                           │
│  - "Bu müştəri mesajı müsbət, mənfi, yoxsa neytral?"           │
│  - "Bu dəstək biletinin hansı kateqoriyaya aid olduğu?"         │
│  - "Bu mətn PII ehtiva edirmi? Bəli/Xeyr"                      │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  QƏRAR: Claude Sonnet 4.6                                       │
│                                                                 │
│  ✓ Əksər istehsal xüsusiyyətləri üçün standart seçim          │
│  ✓ Kod generasiyası, nəzəriyyəsi, izahı                        │
│  ✓ Mürəkkəb sənəd analizi                                      │
│  ✓ Tool use-lu agent tapşırıqları                               │
│  ✓ Keyfiyyətin önəmli olduğu müştəri-yönəlik çat              │
│  ✓ Mürəkkəb mətnlərden strukturlaşdırılmış çıxış / məlumat    │
│    çıxarması                                                    │
│  ✓ RAG boru kəmərləri                                          │
│                                                                 │
│  Özünüzdən soruşun: "Haiku kifayət edirmi?" → Xeyr → Sonnet   │
│  Özünüzdən soruşun: "Sonnet kifayət edirmi?" → Bəli → Sonnet  │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  QƏRAR: Claude Opus 4.6                                         │
│                                                                 │
│  ✓ Tapşırıq yüksək risklidir (tibbi, hüquqi, maliyyə analizi) │
│  ✓ Həcm AZ, keyfiyyət isə xərcdən üstündür                    │
│  ✓ Tapşırıq öyrənmə məlumatlarında görülməmiş yeni mühakimə   │
│    tələb edir                                                   │
│  ✓ Səhvlər kaskad yaradır və düzəltmək bahaya başa gəlir       │
│  ✓ Hər addımın düzgün olmalı olduğu mürəkkəb agent iş axınları│
│                                                                 │
│  Əks-nümunə: Sonnet-in yaxşı öhdəsindən gəldiyi tapşırıqlar  │
│    üçün Opus istifadəsi                                         │
│  Praktiki qayda: $75/1M çıxış token-i əsaslandıra bilmirsinizsə,│
│  Sonnet istifadədin                                             │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  QƏRAR: GPT-4o                                                  │
│                                                                 │
│  ✓ Native səs/audio imkanlarına ehtiyacınız var                │
│  ✓ Kod interpretatoru ilə Assistants API-ya ehtiyacınız var    │
│  ✓ Komandanızın dərin OpenAI təcrübəsi və alətləri var         │
│  ✓ Müəssisə müqavilələri artıq mövcuddur                       │
│                                                                 │
│  Claude-a keçməyi düşünün: tapşırıq izlənilməsi kritikdirsə,  │
│  128k-dan böyük context window lazımdırsa, xərc önəmlidirsə    │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  QƏRAR: Gemini 1.5 Pro                                          │
│                                                                 │
│  ✓ 200k token-dən böyük sənədləri işləməli olursunuz          │
│  ✓ Video anlayışı tələb olunur                                 │
│  ✓ Google Cloud-dasınız (Vertex AI)                            │
│  ✓ Çoxdilli (əsas olmayan ingilis) tətbiqlər                  │
│  ✓ Bütün kod bazalarını tək context-də işləməlisiniz           │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  QƏRAR: Llama 3.x (Öz ev sahibliyi)                            │
│                                                                 │
│  ✓ Məlumat infrastrukturu tərk edə bilməz (HIPAA, GDPR, və s.)│
│  ✓ Xüsusi məlumatlar üzərində incə ayarlama etmək istəyirsiniz │
│  ✓ API xərclərinin qəbuledilməz olduğu yüksək həcmli tapşırıqlar│
│  ✓ GPU infrastrukturu mövcuddur                                │
│  ✓ Model fərdiləşdirməsini məhsula əsas olaraq qurursunuz      │
│                                                                 │
│  Əks-nümunə: API-nin mühəndislik + infrastruktur xərcimizdən  │
│  ucuz olduğu hallarda öz ev sahibliyi                          │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  QƏRAR: Qwen2.5                                                 │
│                                                                 │
│  ✓ Əsas dil Çin dilidir                                        │
│  ✓ Kodlama ağır iş yükü (Qwen2.5-Coder)                       │
│  ✓ Asiya-Sakit Okean infrastrukturu (Alibaba Cloud)            │
│  ✓ Riyaziyyat/STEM ağır tapşırıqlar (Qwen2.5-Math)            │
└─────────────────────────────────────────────────────────────────┘
```

---

## Arxitektor üçün Model Seçimi Çərçivəsi

### Pilləli Model Arxitekturası

Mürəkkəb AI sistemi nadir hallarda tək modeldən istifadə edir. Tapşırıqları uyğun modelə yönləndirən **pilləli yanaşmadan** istifadədin:

```
┌──────────────────────────────────────────────────────────┐
│              PİLLƏLİ MODEL ARXİTEKTURASI                │
│                                                          │
│  Pillə 0: Lokal / Kənar Model                           │
│  ├── Llama 3.1 8B (INT4 kvantlaşdırılmış, cihazda)     │
│  ├── İstifadə: real vaxt avtomatik tamamlama, oflayn    │
│  └── Xərc: ~$0 (istifadəçi cihazında inference)        │
│                                                          │
│  Pillə 1: Sürətli / Ucuz API Modeli                     │
│  ├── Claude Haiku 4.5 ya da GPT-4o-mini                │
│  ├── İstifadə: təsnifat, marşrutlaşdırma, filtrləmə    │
│  └── Xərc: < $1 / 1M token                             │
│                                                          │
│  Pillə 2: Standart Keyfiyyətli API Modeli               │
│  ├── Claude Sonnet 4.6 ya da GPT-4o                    │
│  ├── İstifadə: əsas AI xüsusiyyətləri, kod, analiz    │
│  └── Xərc: ~$3-5 / 1M giriş token                     │
│                                                          │
│  Pillə 3: Premium / Mütəxəssis Model                   │
│  ├── Claude Opus 4.6 ya da ixtisaslaşmış incə ayarlama │
│  ├── İstifadə: yüksək riskli qərarlar, geri dönüş      │
│  └── Xərc: ~$15+ / 1M token                            │
└──────────────────────────────────────────────────────────┘
```

### Marşrutlaşdırma Məntiqi

```
Model seçimi üçün qərar axını:

1. Bu oflayn / cihazda edilə bilərmi?
   → Bəli: Lokal modeldən istifadə et (Llama kvantlaşdırılmış)
   → Xeyr: Davam et

2. Bu sadə, yaxşı müəyyənləşdirilmiş tapşırıqdırmı (təsnifat, etiketləmə)?
   → Bəli: Pillə 1-dən istifadə et (Haiku)
   → Xeyr: Davam et

3. Bu tapşırıq tələb edirmi: kod, mürəkkəb mühakimə, alətlər, uzun context?
   → Bəli: Pillə 2-dən istifadə et (Sonnet)
   → Xeyr: Pillə 1-dən istifadə et

4. Bu tapşırıq yüksək risklidirmi VƏ keyfiyyət Opus ilə xeyli yaxşılaşırmı?
   → Bəli: Pillə 3-dən istifadə et (Opus)
   → Xeyr: Pillə 2-dən istifadə et

5. Cavab keyfiyyət həddini qarşılayırmı?
   → Xeyr: Növbəti pilliyə qalx (daha yaxşı modellə yenidən cəhd et)
   → Bəli: Nəticəni qaytar
```

### Çox-Provayder Davamlılığı

```
Provayder mövcudluğu strategiyası:

Əsas:   Claude Sonnet 4.6 (Anthropic)
Ehtiyat1: GPT-4o (OpenAI)
Ehtiyat2: Gemini 1.5 Pro (Google)

Əsas sıradan çıxarsa (sürət məhdudiyyəti, nasazlıq, məzmun siyasəti):
→ Eyni prompt ilə ehtiyat1-ə yönləndir
→ İzləmə üçün geri dönüş hadisəsini loqlat

Bu model-agnostik prompt dizaynı tələb edir — Claude-a xas
davranışlara baxan promptlardan çəkinin (məs., XML teqləri universaldır,
amma "Anthropic modeli kimi..." deyil).
```

---

## Xərc Modelləşdirməsi

### Real Dünya Xərc Nümunələri

**Ssenari: Müştəri dəstəyi chatbotu, gündə 10.000 söhbət**

```
Fərziyyələr:
- Orta söhbət: 10 növbə
- Orta istifadəçi mesajı: 50 token
- Orta sistem promptu: 500 token
- Orta köməkçi cavabı: 200 token
- Context növbə başına artır (əvvəlki mesajlar daxildir)

Söhbət başına giriş token-ləri:
  Növbə 1: 500 (sistem) + 50 (istifadəçi) = 550
  Növbə 2: 550 + 200 (əv. cavab) + 50 = 800
  Növbə 10: ~2.500 token orta = 25.000 cəmi giriş token-i

Söhbət başına çıxış token-ləri:
  10 növbə × 200 token = 2.000 çıxış token-i

Söhbət başına:
  Claude Haiku:  25k × $0.0008 + 2k × $0.004 = $0.028
  Claude Sonnet: 25k × $0.003 + 2k × $0.015  = $0.105
  Claude Opus:   25k × $0.015 + 2k × $0.075  = $0.525

Gündəlik xərc (10.000 söhbət):
  Claude Haiku:  $280/gün  = $8.400/ay
  Claude Sonnet: $1.050/gün = $31.500/ay
  Claude Opus:   $5.250/gün = $157.500/ay

Hibrid yanaşma (sadə üçün Haiku, mürəkkəb üçün Sonnet — 70/30):
  = 0.70 × $280 + 0.30 × $1.050
  = $196 + $315 = $511/gün = $15.330/ay
  (bütün-Sonnet-ə nisbətdə 54% qənaət)
```

### Öz Ev Sahibliyi üçün Qurmaq vs Almaq Qərarı

```
70B modelin öz ev sahibliyi (Llama 3.1):

İnfrastruktur:
  2× A100 80GB GPU: ~$6/saat AWS-də (p4d.24xlarge mütənasib)
  yaxud 1× H100 80GB:   ~$4/saat
  Aylıq (24/7):    ~$3.000-4.000/ay

Claude Sonnet ($3/$15 hər 1M) ilə müqayisədə zərərsizlik:
  70% istifadə nisbəti, 30 token/saniyə məhsuldarlıq fərz edilərək:
  Aylıq token generasiyası: 0.7 × 30 × 3600 × 24 × 30 = ~54 milyard token

  Ekvivalent Sonnet xərci (çıxış): 54.000 × $15 = $810.000/ay
  İnfrastruktur xərci:              $4.000/ay

  → Miqyas olaraq öz ev sahibliyi kəskin şəkildə ucuzdur
  → AMMA: mühəndislik yükü, etibarlılıq, təhlükəsizlik, yüksəltmələr

Zərərsizlik nöqtəsi: ~$30.000-50.000/ay API xərclərindən

Bu həddın altında: API demək olar ki həmişə daha xərc-effektivdir
Bu həddın üstündə: Öz ev sahibliyini ciddi qiymətləndirin
```

---

## Model-Agnostik Sistemlər Qurmaq

### Abstraksiya Qatı Nümunəsi

```php
// Model-agnostik interfeys
interface AIModelInterface
{
    public function complete(array $messages, array $options = []): string;
    public function stream(array $messages, array $options = []): Generator;
    public function getModelId(): string;
    public function getContextWindow(): int;
}

// Claude üçün konkret tətbiq
class ClaudeModel implements AIModelInterface
{
    public function complete(array $messages, array $options = []): string
    {
        // Anthropic-spesifik tətbiq
    }
    
    public function getContextWindow(): int { return 200000; }
}

// OpenAI üçün konkret tətbiq
class OpenAIModel implements AIModelInterface
{
    public function complete(array $messages, array $options = []): string
    {
        // OpenAI-spesifik tətbiq
    }
    
    public function getContextWindow(): int { return 128000; }
}
```

### Model-Agnostik Prompt Dizayn Prinsipləri

1. **Model-spesifik tapşırıqlardan çəkinin** sistem promptlarında ("Claude modeli kimi..." səhvdir)
2. **Universal quruluşdan istifadədin** (XML teqləri hər yerdə işləyir, xüsusi token-lər isə yox)
3. **Promptları istehsala bağlamadan əvvəl çoxlu modeldə test edin**
4. **Promptları versiya nəzarəti altında saxlayın** model seçimindən müstəqil olaraq
5. **Keyfiyyəti ədədi ölçün** ki, modellər arasında obyektiv müqayisə edə biləsiniz

### Marşrutlaşdırma üçün İmkan Matrisi

```
İMKAN             Haiku  Sonnet  Opus  GPT-4o  Gemini  Llama-70B
─────────────────────────────────────────────────────────────────
Sadə təsnifat      ●●●    ●●●    ●●●   ●●●     ●●●     ●●●
Mürəkkəb mühakimə  ●●○    ●●●    ●●●   ●●●     ●●○     ●●○
Kod generasiyası   ●●○    ●●●    ●●●   ●●●     ●●○     ●●○
Uzun context (>100k)●●●   ●●●    ●●●   ●●○     ●●●     ●●○
Tool use / funksiyalar●●○ ●●●    ●●●   ●●●     ●●●     ●●○
Görüntü / şəkillər ●●●    ●●●    ●●●   ●●●     ●●●     ●○○
Audio (native)     ○○○    ○○○    ○○○   ●●●     ●●●     ○○○
Video analizi      ○○○    ○○○    ○○○   ○○○     ●●●     ○○○
Strukturlaşdırılmış çıxış●●○ ●●● ●●●  ●●●     ●●○     ●●○
Çoxdilli (Av.)    ●●○    ●●●    ●●●   ●●●     ●●●     ●●●
Çoxdilli (ZH/JA)  ●●○    ●●○    ●●○   ●●●     ●●●     ●●○
Öz ev sahibliyi   ○○○    ○○○    ○○○   ○○○     ○○○     ●●●
İncə ayarlanabilən ○○○   ○○○    ○○○   ●○○     ○○○     ●●●

● = güclü   ○ = zəif/mövcud deyil
```

---

## Xülasə

| Narahatlıq | Tövsiyə |
|---|---|
| Standart istehsal seçimi | Claude Sonnet 4.6 |
| Yüksək həcm, xərclərə həssas | Claude Haiku 4.5 ya da GPT-4o-mini |
| Ən yaxşı keyfiyyət, az həcm | Claude Opus 4.6 |
| Səs/audio inteqrasiyası | GPT-4o |
| Çox uzun sənədlər (>200k) | Gemini 1.5 Pro |
| Məlumat egemenliyi / öz ev sahibliyi | Llama 3.1 70B |
| Açıq mənbə (Apache 2.0) | Mistral 7B ya da Mixtral 8x7B |
| Əsas Çin dili | Qwen2.5 |
| Kodlama mütəxəssisi | Qwen2.5-Coder ya da Claude Sonnet 4.6 |
| Hibrid xərc optimallaşdırması | Marşrutlaşdırma üçün Haiku + icra üçün Sonnet |

**Əsas arxitektura fikirisi**: Model seçimi runtime konfiqurasiya qərarıdır. Konfiqurasiya dəyişikliyi ilə modelləri dəyişdirə bilən sistemlər qurun, keyfiyyəti ədədi ölçün və boru kəmərinizdəki hər addımda xərc ilə keyfiyyəti tarazlaşdırmaq üçün pilləli marşrutlaşdırmadan istifadə edin.

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Model Benchmark

Eyni 10 sual üçün `claude-haiku-4-5`, `claude-sonnet-4-6` ilə cavab al. Hər cavab üçün qeyd et: (a) keyfiyyət (1-5 skala), (b) latency (millisaniyə), (c) input+output token cost. Cədvəl çəkib hansı task tipi üçün hansı modelin ən yaxşı cost-quality nisbəti verdiyini müəyyənləşdir.

### Tapşırıq 2: Model Routing Konfiqurasiyası

Laravel-də `config/ai.php` yarat. Hər task tipi üçün (extraction, classification, creative, code) model seç. `AIModelRouter` servisi yaz ki, `task_type` parametrinə görə model seçir. Env dəyişəni ilə modeli runtime-da dəyişmək mümkün olsun.

### Tapşırıq 3: Hybrid Cost Optimization

100 real istifadəçi sorğusunu götür. Hər sorğunu "sadə" / "mürəkkəb" kimi əl ilə kateqoriyalaşdır. "Sadə" sorğuları Haiku ilə, "mürəkkəb" sorğuları Sonnet ilə işlə. Ümumi xərci bütün sorğuların Sonnet ilə işlənməsi ilə müqayisə et.

---

## Əlaqəli Mövzular

- `09-llm-provider-comparison.md` — Provayderlər arasında müqayisə: Claude vs GPT vs Gemini
- `10-model-selection-decision.md` — Qərar çərçivəsi: Opus vs Sonnet vs Haiku
- `11-llm-pricing-economics.md` — Model seçiminin unit economics üzərindəki təsiri
- `../02-claude-api/01-claude-api-guide.md` — API üzərindən model seçimi parametrləri
