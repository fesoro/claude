# Produksiya Sistemləri üçün Ətraflı Prompt Engineering

> Prompt dizaynına sistemli, mühəndis-birinci yanaşma. Nəzəriyyə, nümunələr, anti-nümunələr, versiyalaşdırma və miqyasda prompt-ları idarə etmək üçün produksiya Laravel infrastrukturunu əhatə edir.

---

## Mündəricat

1. [Prompt Engineering Miqyasda Niyə Önəmlidir](#prompt-engineering-miqyasda-niyə-önəmlidir)
2. [Sistem Prompt-ları — Arxitektura və Ən Yaxşı Praktikalar](#sistem-promptları)
3. [Struktur üçün XML Etikətləri](#struktur-üçün-xml-etikətləri)
4. [Az-Atışlı Prompting](#az-atışlı-prompting)
5. [Düşüncə Zənciri Prompting](#düşüncə-zənciri-prompting)
6. [ReAct Prompting](#react-prompting)
7. [Meta-Prompting](#meta-prompting)
8. [Çəkinilməli Anti-Nümunələr](#çəkinilməli-anti-nümunələr)
9. [Prompt Versiyalaşdırma Strategiyası](#prompt-versiyalaşdırma-strategiyası)
10. [Laravel: PromptTemplate Sinifi](#laravel-prompttemplate-sinifi)
11. [Laravel: DB Versiyalaşdırması ilə PromptLibrary](#laravel-db-versiyalaşdırması-ilə-promptlibrary)
12. [Laravel: Az-Atışlı Nümunə Qurucu](#laravel-az-atışlı-nümunə-qurucu)
13. [Produksiyada Prompt-ları Test Etmək](#produksiyada-promptları-test-etmək)

---

## Prompt Engineering Miqyasda Niyə Önəmlidir

Tək bir demo üçün hər hansı bir prompt işləyir. Milyonlarla istifadəçisi olan produksiya sistemi üçün prompt keyfiyyəti birbaşa aşağıdakılara təsir edir:

```
BİZNES TƏSİRİ:
  Keyfiyyət:   Daha yaxşı prompt çıxarım tapşırıqlarında dəqiqliyi iki dəfə artıra bilər
  Xərc:        Söhbətli prompt ayda minlərlə dollar israf edir
  Gecikmə:     Lazımsız çıxış tokenləri hər sorğuya yüzlərcə ms əlavə edir
  Təhlükəsizlik: Pis yazılmış prompt-ları sındırmaq daha asandır
  Saxlanılabilirlik: Versiyasız prompt-lar debug kabuslara çevrilir

Nümunə xərc təsiri:
  Pis prompt:   500 token sistem prompt, 400 token orta cavab
  Yaxşı prompt: 200 token sistem prompt, 250 token orta cavab
  
  Claude Sonnet ilə ayda 1M sorğuda:
  Pis:  (500 + 400) × 1M × $0.000015 = $13,500/ay
  Yaxşı: (200 + 250) × 1M × $0.000015 = $6,750/ay
  
  Qənaət: Yalnız prompt optimallaşdırmasından $6,750/ay.
```

---

## Sistem Prompt-ları

Sistem prompt-u kontekst, şəxsiyyət, məhdudiyyətlər və çıxış formatını müəyyən edir. Hər söhbətin əvvəlində emal edilir və **yazdığınız ən vacib prompt-dur**.

### Effektiv Sistem Prompt-unun Strukturu

```xml
<system_prompt>

1. ROL/ŞƏXSİYYƏT (1-3 cümlə)
   Modelin kim olduğu. Xüsusi, ümumi deyil.

2. KONTEKST (isteğe bağlı, 1-5 cümlə)
   Tətbiq, istifadəçilər, domen haqqında arxa plan məlumatı.

3. ƏSAS VƏZİFƏ (1-3 cümlə)
   Modelin əsas işinin nə olduğu.

4. İMKANLAR (siyahı şəklində, isteğe bağlı)
   Modelin NƏ EDƏCƏKLƏR.

5. MƏHDUDIYYƏTLƏR (siyahı şəklində)
   Modelin NƏ ETMƏDIYINI. Açıq olun.

6. ÇIXIŞ FORMATI (ətraflı)
   Cavabların tam olaraq necə strukturlaşdırılacağı.

7. NÜMUNƏLƏR (isteğe bağlı, güclü)
   İdeal davranışın 1-3 nümunəsi.

</system_prompt>
```

### Nümunə: Faktura Emal Sistemi

```
Siz Acme Corp-un kreditor hesabları sistemi üçün faktura emalı mütəxəssisiniz.

Siz satıcı fakturalarından məlumat çıxarımı və doğrulamada işçilərə kömək edirsiniz.
İstifadəçilər gündə 50-200 faktura emal edən hesablar ödəniş mühasibləridir.

Vəzifəniz:
1. İstifadəçilərin yapışdırdığı və ya təsvir etdiyi faktura mətndən strukturlu məlumat çıxarın
2. Potensial problemləri işarələyin (dublikat məbləğlər, çatışmayan sahələr, qeyri-adi xətt elementləri)
3. Faktura siyasətləri və prosedurları haqqında suallara cavab verin

EDƏCƏKLƏRÜNÜZ:
- Faktura məlumatını strukturlu formata çıxarın
- Ümumi faktura xətaları və uyğunsuzluqları müəyyən edin
- Acme Corp-un ödəniş şərtlərinə istinad edin (standart Net 30, təsdiqlənmiş satıcılar üçün Net 15)
- Ümumi formatlaşdırma problemləri üçün düzəlişlər təklif edin

ETMƏDIYÜNÜZ:
- Ödənişləri təsdiqləmək və ya icazə vermək (bunu insanlar edir)
- Göstərilən mətndə görə bilmədiyiniz faktura məlumatlarını uydurmaq
- Faktura emalı ilə əlaqəsiz mövzuları müzakirə etmək
- Bir faktura haqqında soruşduqda başqa fakturadan məlumat paylaşmaq

CAVAB FORMATI:
- Çıxarım sorğuları üçün: <invoice> etikətləri arasında JSON ilə cavab verin
- Suallar üçün: 1-3 cümlə ilə qısa cavab verin
- Problemlər üçün: ⚠️ prefiksi istifadə edin və konkret narahatlığı izah edin
- Çıxarım tamamlandıqda həmişə belə bitirin: "Növbəti faktura üçün hazıram."
```

### Sistem Prompt Ən Yaxşı Praktikalar

```
EDİN:
  ✓ Rol haqqında konkret olun. "Laravel-ə fokuslanmış baş PHP developer"
    "faydalı köməkçi"dən daha yaxşıdır
  ✓ Məhdudiyyətləri açıq şəkildə sıralayın. Modellər açıq qadağalara
    gizli gözləntilərdən daha yaxşı əməl edir
  ✓ Çıxış formatını ətraflı göstərin. "Sahələrlə JSON: ad, tarix, məbləğ"
    "strukturlu çıxış"dan daha yaxşıdır
  ✓ Kənar hal emalını daxil edin. "Faktura oxunmaz olduqda,
    təxmin etmək əvəzinə {"error": "illegible"} qaytarın"
  ✓ Fokuslanmış saxlayın. Beş deyil, bir əsas iş
  ✓ İndiki zaman istifadə edin: "Siz", "Siz kömək edirsiniz", "Olmalısınız" deyil

ETMƏYİN:
  ✗ Strukturu olmayan mətn divarı yazmaq
  ✗ Modelin heç vaxt ehtiyac duymayacağı təlimatları daxil etmək
  ✗ Eyni təlimatı bir neçə dəfə təkrarlamaq (kömək etmir)
  ✗ Qeyri-müəyyən sifətlər istifadə etmək: "Faydalı, dürüst və yaradıcı olun"
    (Claude artıq beledir — məlumat əlavə etmirsiniz)
  ✗ "Mən sizdən istəyirəm ki..." ilə başlamaq — modelin KİM OLDUĞU ilə başlayın
```

---

## Struktur üçün XML Etikətləri

Claude XML strukturlu prompt-lara xüsusilə yaxşı cavab verir. XML etikətləri modelin prompt-un bölmələri arasındakı sərhədləri anlamasına kömək edir.

### XML ilə Girişin Strukturlaşdırılması

```xml
<!-- Yaxşı: sənəd sərhədləri aydındır -->
<task>
  Aşağıdakı PHP kodunu təhlükəsizlik zəiflikləri üçün nəzərdən keçirin.
</task>

<code language="php">
<?php
$userId = $_GET['id'];
$query = "SELECT * FROM users WHERE id = $userId";
$result = mysqli_query($conn, $query);
?>
</code>

<output_format>
  Tapıntıları nömrəli siyahı kimi təqdim edin. Hər problem üçün daxil edin:
  1. Ciddililik (Kritik/Yüksək/Orta/Aşağı)
  2. Təsvir
  3. Düzəldilmiş kod parçası
</output_format>
```

```xml
<!-- Çox sənədli analiz üçün -->
<documents>
  <document id="1" type="purchase_order">
    PO-2024-001
    Satıcı: Acme Corp
    Cəmi: $5,400.00
  </document>
  
  <document id="2" type="invoice">
    Faktura #INV-8821
    Göndərən: Acme Corp
    Məbləğ: $5,600.00
  </document>
</documents>

<task>
  Bu sənədləri müqayisə edin və hər hansı uyğunsuzluqları müəyyən edin.
</task>
```

### XML ilə Çıxışın Strukturlaşdırılması

```xml
<!-- Claude-dan strukturlu formatda cavab verməsini xahiş edin -->
<task>
  Bu müştəri şikayətini analiz edin və aşağıdakıları verin:
  - Ciddililik balı
  - Kök problem kateqoriyası
  - Tövsiyə olunan cavab
  
  Cavabınızı bu XML etikətlərindən istifadə edərək formatlaşdırın:
  <severity>1-5</severity>
  <category>billing|texniki|çatdırılma|digər</category>
  <response>tövsiyə olunan cavabınız buradadır</response>
</task>
```

### XML-in Prompt-larda JSON-dan Üstünlükleri

```
XML prompt-larda üstün tutulur (cavablarda deyil) çünki:
  1. Claude-un öyrənməsi geniş XML/HTML məzmunu ehtiva edir — bu təbiidir
  2. XML boşluq və formatlaşdırma variasiyasına dözür
  3. İç-içə strukturlar oxumaq və yazmaq üçün daha aydındır
  4. Boş məzmun üçün öz-özünü bağlayan etiketlər işləyir
  5. Atributlar əlavə iç-içəsizlik olmadan metadata təqdim edir

CAVABLAR üçün JSON istifadə edin (proqramatik olaraq analiz etmək daha asandır)
PROMPT-LAR üçün XML istifadə edin (model tərəfindən şərh üçün daha yaxşı struktur)
```

---

## Az-Atışlı Prompting

Az-atışlı prompting istədiyiniz davranışı nümayiş etdirən giriş→çıxış cütlərinin nümunələrini verir.

### Az-Atışlı Nə Zaman İstifadə Etməli

```
AZ-ATIŞLI İSTİFADƏ EDİN:
  ✓ Çıxış formatı qeyri-adi və ya mürəkkəbdir
  ✓ Təsvir etmək çətin olan ardıcıl ton lazımdır
  ✓ Tapşırıq xüsusi domen leksikasını ehtiva edir
  ✓ Sıfır-atışlı qeyri-ardıcıl nəticələr verir
  ✓ Təsnifat sxemi qeyri-açıqdır

AZ-ATIŞLIDAN KEÇİN:
  ✓ Sadə, yaxşı başa düşülmüş tapşırıqlar
  ✓ Kontekst pəncərəsi sıxışdırılıb (nümunələr token istehlak edir)
  ✓ Model artıq sıfır-atışlı yaxşı nəticə verir
```

### Yaxşı Az-Atışlı Nümunənin Anatomiyası

```
SİSTEM: Siz müştəri mesajlarından məhsul qaytarma səbəblərini çıxarırsınız.
        Aşağıdakılara təsnif edin: defektli | yanlış_element | fikir_dəyişdi | çatdırılmada_zədə | digər

NÜMUNƏLƏRİ:

<example>
  <input>Tamamilə yanlış ölçü aldım, Böyük sifariş etdim, Kiçik gəldi</input>
  <output>yanlış_element</output>
</example>

<example>
  <input>Telefonumun ekranı normal istifadənin bir günündən sonra çatladı, bu olmamalıydı</input>
  <output>defektli</output>
</example>

<example>
  <input>Düzünü desəm, artıq ehtiyacım yoxdur, qardaşım mənə hədiyyə verdi</input>
  <output>fikir_dəyişdi</output>
</example>

<example>
  <input>Qutu tamamilə əzilmiş gəldi, sanki yük maşınından düşüb</input>
  <output>çatdırılmada_zədə</output>
</example>

İndi təsnif edin:
<input>Fermuarı iki dəfə istifadədən sonra qırıldı, bu normal aşınma deyil</input>
```

### Nümunələrin Keyfiyyəti

```
YAXŞI NÜMUNƏLƏRİ:
  - Kənar halları və sərhəd halları əhatə edir
  - Bir-biri ilə qarışdırıla bilən nümunələri daxil edir
  - Cümlə strukturunu və uzunluğunu dəyişdirir
  - Realist, domenə uyğun dil istifadə edir

PİS NÜMUNƏLƏRİ:
  - Çox sadə (modelin artıq idarə etdiyi açıq hallar)
  - Hamısı eyni kateqoriyada (qeyri-balans yaradır)
  - Süni/qeyri-realist ifadə
  - Token israf edən dublikatlar
```

---

## Düşüncə Zənciri Prompting

Düşüncə zənciri (CoT) modeli son cavabı verməzdən əvvəl addım-addım düşünməyə yönləndirir. Bu, mürəkkəb əsaslandırma tapşırıqlarında performansı dramatik şəkildə yaxşılaşdırır.

### Sıfır-Atışlı CoT

```
Sualdan əvvəl sadəcə "Gəlin bunu addım-addım düşünək."
əlavə etmək CoT əsaslandırmasını tetikləyir.

Nümunə:
  CoT olmadan: "127 × 43 nədir?"
  CoT ilə:    "127 × 43 nədir? Bunu addım-addım düşünün."

Mürəkkəb əsaslandırma üçün:
  "Cavabınızı verməzdən əvvəl <thinking> blokunda problem üzərindən
   düşünün, sonra son cavabınızı <answer> blokunda verin."
```

### Açıq Format ilə CoT

```xml
SİSTEM: Siz baş kod nəzərdən keçirənsiniz. Kodu nəzərdən keçirərkən:
1. Əvvəlcə <analysis> etikətlərindəki kodu analiz edin — fark etdiyiniz hər narahatlığı sıralayın
2. Sonra <priority_ranking> etikətlərindəki tapıntılarınızı prioritetləndirin
3. Nəhayət strukturlu baxışınızı <review> etikətlərində verin

Bu, qiymətləndirməyə sadiqliyindən əvvəl hərtərəfli analizi təmin edir.

İSTİFADƏÇİ: Bu autentifikasiya kodunu nəzərdən keçirin:
[kod buradadır]

KÖMƏKÇİ:
<analysis>
- 12-ci sətir: Parol hash_equals() əvəzinə == ilə müqayisə edilir — timing hücumu zəifliyi
- 7-ci sətir: İstifadəçi girişi birbaşa SQL sorğusunda — SQL injection riski
- 20-ci sətir: Giriş sonrası sessiya yenilənmir — sessiya fiksasiyası zəifliyi
- 3-cü sətir: Xəta mesajı e-poçtun mövcud olub-olmadığını açıqlayır — istifadəçi sadalama
...
</analysis>

<priority_ranking>
1. SQL injection (7-ci sətir) — Kritik, dərhal istismar mümkündür
2. Sessiya fiksasiyası (20-ci sətir) — Yüksək
3. Timing hücumu (12-ci sətir) — Orta
4. İstifadəçi sadalama (3-cü sətir) — Aşağı
</priority_ranking>

<review>
**KRİTİK: 7-ci Sətirdə SQL Injection**
...
</review>
```

### CoT-un Kömək etdiyi vs Zərər Verdiyi Halllar

```
KÖMƏK EDİR:
  ✓ Çox addımlı riyaziyyat/məntiq
  ✓ Kompromisləri olan mürəkkəb əsaslandırma
  ✓ Erkən xətaların yanlış nəticələrə kaskad etdiyi tapşırıqlar
  ✓ Kod debug (icra yolunu izləyin)

ZƏRƏR VERİR (və ya token israf edir):
  ✗ Sadə təsnifat (birbaşa cavab verin)
  ✗ Faktiki geri çağırma ("Fransanın paytaxtı nədir?")
  ✗ Token başına ödəyərkən və keyfiyyət onsuz da qəbul edildikdə
  ✗ Sürətli, qısa cavablar lazım olduqda
```

---

## ReAct Prompting

ReAct (Reasoning + Acting / Əsaslandırma + Fəaliyyət) düşüncə zənciri əsaslandırmasını alət çağırışlarına (fəaliyyət almaq) birləşdirir. Agent sistemlərinin əsasıdır.

```
NÜMUNƏ:
  Düşüncə: [cari vəziyyət haqqında düşünün və nə etməli]
  Fəaliyyət: [alət çağırın və ya fəaliyyət alın]
  Müşahidə: [fəaliyyətin nəticəsi]
  Düşüncə: [müşahidə haqqında düşünün]
  Fəaliyyət: [növbəti fəaliyyət]
  ...
  Düşüncə: [son cavab haqqında düşüncə]
  Cavab: [istifadəçiyə son cavab]

NÜMUNƏ:

İstifadəçi: "Eyfel Qülləsinin olduğu şəhərdəki cari hava necədir?"

Düşüncə: Eyfel Qülləsinin hansı şəhərdə olduğunu tapmalıyam, sonra həmin şəhər üçün hava almalıyam.
         Bilirəm ki, Eyfel Qüllesi Parisdə, Fransadadır.
Fəaliyyət: get_weather(city="Paris, Fransa")
Müşahidə: {"temperature": 18, "condition": "qismən buludlu", "humidity": 65}
Düşüncə: Hava məlumatım var. İndi istifadəçinin sualına cavab verə bilərəm.
Cavab: Eyfel Qülləsinin yerləşdiyi Parisdəki hava cari olaraq
       18°C-dir, qismən buludlu, rütubət 65%-dir.
```

ReAct nümunəsi Claude API-dəki alət istifadəsinin əsasını təşkil edir — Claude alətlər verildikdə bu nümunəni yerli olaraq çıxarır.

---

## Meta-Prompting

Meta-prompting prompt-ları yaratmaq, yaxşılaşdırmaq və ya qiymətləndirmək üçün LLM istifadə edir.

```php
// Öz prompt-larınızı yaxşılaşdırmaq üçün Claude istifadə edin
$metaPrompt = <<<PROMPT
Siz prompt engineering ekspertsiniz. Aşağıdakı prompt-u nəzərdən keçirin və yaxşılaşdırmalar təklif edin.
Fokus: aydınlıq, konkretlik, çıxış formatının ardıcıllığı və kənar hal emalı.

<current_prompt>
{$currentPrompt}
</current_prompt>

<task_description>
Bu prompt bunun üçün istifadə edilir: {$taskDescription}
Hədəf model: Claude Sonnet 4.6
Gözlənilən giriş: {$inputDescription}
Gözlənilən çıxış: {$outputDescription}
</task_description>

Verin:
1. Cari prompt-la problemlər
2. Yaxşılaşdırılmış versiya
3. Əsas dəyişikliklərin izahatı
PROMPT;
```

---

## Çəkinilməli Anti-Nümunələr

```
ANTİ-NÜMUNƏ 1: NEQASİYA-AĞIR PROMPT-LAR
  Pis:  "Uzun sözlü olmayın. Jargon istifadə etməyin. Soruşulmadıqca nümunə verməyin.
         Qeyri-rəsmi olmayın. Özünüzü təkrarlamayın."
  Niyə:  Hər "etmə" modelin diqqətdə qadağa saxlamasını tələb edir.
        Çox qadağa əsas tapşırığı sıxışdırır.
  Düzəliş: İstədiyinizi deyin: "Qısa və peşəkar olun.
        Yalnız istənilən məlumatla cavab verin."

ANTİ-NÜMUNƏ 2: QEYRI-MÜƏYYƏN KEYFİYYƏT TƏLİMATLARI
  Pis:  "Yüksək keyfiyyətli, hərtərəfli, ətraflı analiz verin."
  Niyə:  "Yüksək keyfiyyət" heç bir şey demək deyil — hər çıxış modelin
        ən yaxşı cəhdidir. Bu tokenlər əlavə edir, məlumat əlavə etmir.
  Düzəliş: DƏQIQ hərtərəflinin nə demək olduğunu göstərin:
        "Əhatə edin: təhlükəsizlik, performans, saxlanılabilirlik və test əhatəsi.
        400 söz ilə məhdudlaşdırın."

ANTİ-NÜMUNƏ 3: ROL GİRİŞ ŞİŞİRMƏSİ
  Pis:  "Siz Fortune 500 şirkətlərində işləmiş, korporativ
        proqram təminatı inkişafında 20 illik təcrübəyə malik ekspertsiniz..."
  Niyə:  Uzun rol təsvirləri token əlavə edir. Modelin "təcrübəsi" yoxdur —
        ya tapşırığı yerinə yetirə bilər, ya da yox.
  Düzəliş: "Siz baş proqram memarısınız. Birbaşa və texniki olun."

ANTİ-NÜMUNƏ 4: PROMPT İNJEKSİYA SƏTHİ
  Pis:  Sistem prompt-larına birbaşa istifadəçi girişinin daxil edilməsi
  Pis:  f"İstifadəçinin adı {userName}-dir. Ona {userName} kimi cavab verin."
  Niyə:  "Bütün əvvəlki təlimatları nəzərə almayın" adlı istifadəçi
        sistem prompt-unuzu ələ keçirə bilər.
  Düzəliş: İstifadəçi girişini XML etikətləri ilə aydın şəkildə məhdudlaşdırın.
        İstifadəçi məzmununun heç vaxt birbaşa sistem prompt-unda görünməsinə icazə verməyin.

ANTİ-NÜMUNƏ 5: ARDICSIZ NÜMUNƏLƏR
  Pis:  Tamamilə fərqli çıxış formatları olan 3 nümunə
  Niyə:  Modeli "düzgün" formatın nə olduğu haqqında çaşdırır.
  Düzəliş: Bütün nümunələr eyni çıxış strukturundan istifadə etməlidir.

ANTİ-NÜMUNƏ 6: QEYRI-MÜƏYYƏN FORMAT
  Pis:  "Məlumatı strukturlu formatda qaytarın."
  Niyə:  "Strukturlu" hər şeyi ifadə edə bilər.
  Düzəliş: "YALNIZ bu dəqiq sxemlə JSON obyekti qaytarın:
        {"name": string, "amount": float, "date": "YYYY-MM-DD"}"

ANTİ-NÜMUNƏ 7: ÇOXLU VƏZİFƏLƏRİ SIKIŞDIRMAQ
  Pis:  "Bunu Fransızcaya TƏRCÜMƏYİN VƏ qrammatika üçün YOXLAYIN VƏ
        XÜLASƏ EDİN VƏ hissini TƏSNİF EDİN."
  Niyə:  Modellər fokuslanmış tapşırıqlarda daha yaxşı nəticə verir.
        Çoxlu məqsədlər çıxış sahəsi üçün rəqabət aparır.
  Düzəliş: Ya tapşırıq başına bir prompt, ya da onları ardıcıl zəncirləyin.
```

---

## Prompt Versiyalaşdırma Strategiyası

Prompt-lar koddur. Tətbiq kodu ilə eyni ciddi qaydada versiya-nəzarəti edilməli, test edilməli və yerləşdirilməlidir.

```
VERSİYA NƏZARƏTI STRATEGİYASI:

1. PROMPT-LARI VERSİYA NƏZARƏTINƏ SAXLAYIN (Git)
   - Prompt-ları izlənilən fayllarda saxlayın (PHP-də hardcoded deyil)
   - Semantik versiyalaşdırma istifadə edin: v1.0.0, v1.1.0, və s.
   - Prompt-un NİYƏ dəyişdiyini izah edən commit mesajları yazın

2. AKTİV PROMPT-LARI VERİTABANINDA SAXLAYIN
   - Yerləşdirmə olmadan hot-swap imkanı verir
   - İzləyin: prompt_key, version, content, model, active, created_at
   - Tarixi saxlayın (soft delete, heç vaxt hard delete etməyin)

3. PROMPT DƏYİŞİKLİKLƏRİNİ A/B TEST EDİN
   - Trafiği bölün: 10% yeni versiya, 90% köhnə versiya
   - Tam yerləşdirməzdən əvvəl keyfiyyət metrikini ölçün
   - Geri çəkilmə veritabanı yeniləməsidir, yerləşdirmə deyil

4. HEÇBIR PROMPT DƏYİŞİKLİYİNİ TEST ETMƏDƏN YERLƏŞDİRMƏYİN
   - Benchmark test dəsti çalışdırın (50-100 nümunə)
   - Benchmark-da yeni vs köhnə prompt müqayisə edin
   - Minimum keyfiyyət həddini müəyyən edin (məs., >95% dəqiqlik)
```

---

## Laravel: PromptTemplate Sinifi

```php
<?php

declare(strict_types=1);

namespace App\AI\Prompts;

use InvalidArgumentException;

/**
 * Dəyişən əvəzetmə ilə tip-təhlükəsiz prompt şablonu.
 *
 * Şablonlar {{variable_name}} sintaksisindən istifadə edir.
 * {{#if condition}}...{{/if}} ilə şərti bölmələri dəstəkləyir.
 *
 * İstifadə:
 *   $template = PromptTemplate::fromString(
 *       "Aşağıdakı {{language}} kodunu nəzərdən keçirin:\n\n{{code}}"
 *   );
 *   $prompt = $template->render([
 *       'language' => 'PHP',
 *       'code'     => $code,
 *   ]);
 */
class PromptTemplate
{
    private readonly array $requiredVariables;

    private function __construct(
        private readonly string $template,
        private readonly string $name = '',
        private readonly string $version = '1.0.0',
    ) {
        $this->requiredVariables = $this->extractVariables();
    }

    /**
     * Xam sətirdən şablon yaradın.
     */
    public static function fromString(
        string $template,
        string $name = '',
        string $version = '1.0.0',
    ): static {
        return new static($template, $name, $version);
    }

    /**
     * Fayl yolundan şablon yaradın.
     */
    public static function fromFile(string $path): static
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("Şablon faylı tapılmadı: {$path}");
        }

        $content = file_get_contents($path);
        $filename = pathinfo($path, PATHINFO_FILENAME);

        return new static($content, $filename);
    }

    /**
     * Təqdim olunan dəyişənlərlə şablonu render edin.
     *
     * @param  array<string, scalar|null>  $variables
     * @throws MissingVariableException tələb olunan dəyişənlər təqdim edilmədikdə
     */
    public function render(array $variables): string
    {
        $this->validateVariables($variables);

        $rendered = $this->template;

        // Əvvəlcə şərti blokları render edin: {{#if var}}məzmun{{/if}}
        $rendered = $this->renderConditionals($rendered, $variables);

        // Bütün {{variable}} yer tutucularını əvəzləyin
        foreach ($variables as $key => $value) {
            $rendered = str_replace(
                '{{' . $key . '}}',
                (string) ($value ?? ''),
                $rendered
            );
        }

        // Əvəzlənməmiş dəyişənləri yoxlayın (bir bug göstərərdi)
        if (preg_match('/\{\{([^}]+)\}\}/', $rendered, $match)) {
            throw new UnreplacedVariableException(
                "'{$this->name}' şablonunda əvəzlənməmiş dəyişən var: {{{$match[1]}}}"
            );
        }

        return $rendered;
    }

    /**
     * Tələb olunan dəyişən adlarının siyahısını əldə edin.
     *
     * @return string[]
     */
    public function getRequiredVariables(): array
    {
        return $this->requiredVariables;
    }

    /**
     * Xam şablon sətirini əldə edin.
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Render edilmiş şablon üçün token sayı qiymətini alın.
     */
    public function estimateTokens(array $variables): int
    {
        $rendered = $this->render($variables);
        return (int) ceil(mb_strlen($rendered) / 4);
    }

    private function renderConditionals(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{#if\s+(\w+)\}\}(.*?)\{\{\/if\}\}/s',
            function (array $matches) use ($variables) {
                $condition = $variables[$matches[1]] ?? null;
                return $condition ? $matches[2] : '';
            },
            $template
        );
    }

    private function validateVariables(array $variables): void
    {
        $missing = array_diff($this->requiredVariables, array_keys($variables));

        if (!empty($missing)) {
            throw new MissingVariableException(
                "'{$this->name}' şablonunda tələb olunan dəyişənlər çatışmır: " .
                implode(', ', $missing)
            );
        }
    }

    private function extractVariables(): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $this->template, $matches);

        // Şərti idarəetmə açar sözlərini istisna edin
        $excluded = ['if', 'else', 'end'];
        return array_values(
            array_unique(
                array_filter($matches[1], fn ($v) => !in_array($v, $excluded, true))
            )
        );
    }
}

class MissingVariableException extends \InvalidArgumentException {}
class UnreplacedVariableException extends \RuntimeException {}
```

---

## Laravel: DB Versiyalaşdırması ilə PromptLibrary

```php
<?php
// database/migrations/xxxx_create_prompt_library_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index();        // məs. 'invoice.extraction'
            $table->string('version');             // məs. '2.1.0'
            $table->text('system_prompt');
            $table->text('user_prompt_template')->nullable();
            $table->string('model')->default('claude-sonnet-4-6');
            $table->json('parameters')->nullable(); // temperature, max_tokens, və s.
            $table->boolean('is_active')->default(false)->index();
            $table->string('description')->nullable();
            $table->string('deployed_by')->nullable();
            $table->json('benchmark_results')->nullable(); // keyfiyyət metrikleri
            $table->timestamps();
            $table->softDeletes();

            // Açar başına yalnız bir aktiv prompt
            $table->unique(['key', 'version']);
        });

        // A/B test trafik bölmələri üçün ayrı cədvəl
        Schema::create('ai_prompt_experiments', function (Blueprint $table) {
            $table->id();
            $table->string('experiment_name')->unique();
            $table->foreignId('control_prompt_id')->constrained('ai_prompts');
            $table->foreignId('variant_prompt_id')->constrained('ai_prompts');
            $table->unsignedSmallInteger('variant_traffic_percent')->default(10);
            $table->boolean('is_active')->default(false);
            $table->json('results')->nullable();
            $table->timestamps();
        });
    }
};
```

```php
<?php

declare(strict_types=1);

namespace App\AI\Prompts;

use App\Models\AiPrompt;
use App\Models\AiPromptExperiment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Versiyalaşdırılmış prompt-ları idarə etmək üçün mərkəzi kitabxana.
 *
 * Dəstəkləyir:
 * - DB-də versiya-nəzarəti altında prompt saxlanması
 * - Yerləşdirmə olmadan aktiv prompt-ların hot-swap edilməsi
 * - Trafik bölməsi ilə A/B testi
 * - Performans üçün keşləmə
 * - Audit izi
 */
class PromptLibrary
{
    private const CACHE_TTL = 300; // 5 dəqiqə

    /**
     * Verilmiş açar üçün aktiv prompt-u əldə edin.
     *
     * Bu açar üçün A/B eksperimenti gedirsə,
     * trafik bölməsinə əsasən nəzarət və ya variantı qaytarır.
     */
    public function get(string $key, ?string $userId = null): PromptConfig
    {
        $cacheKey = "ai_prompt:{$key}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $userId) {
            // Aktiv A/B eksperimentini yoxlayın
            $experiment = $this->getActiveExperiment($key);

            if ($experiment && $userId) {
                return $this->resolveExperimentPrompt($experiment, $userId);
            }

            $prompt = AiPrompt::where('key', $key)
                ->where('is_active', true)
                ->first();

            if (!$prompt) {
                throw new PromptNotFoundException("Açar üçün aktiv prompt tapılmadı: {$key}");
            }

            return $this->toConfig($prompt);
        });
    }

    /**
     * Prompt-un xüsusi versiyasını aktivləşdirin.
     * Eyni açar üçün hər hansı digər aktiv versiyanı deaktivləşdirir.
     */
    public function activate(string $key, string $version, string $deployedBy): void
    {
        \DB::transaction(function () use ($key, $version, $deployedBy) {
            // Bu açar üçün bütün cari aktiv prompt-ları deaktivləşdirin
            AiPrompt::where('key', $key)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Hədəf versiyasını aktivləşdirin
            $updated = AiPrompt::where('key', $key)
                ->where('version', $version)
                ->update([
                    'is_active'   => true,
                    'deployed_by' => $deployedBy,
                ]);

            if ($updated === 0) {
                throw new \InvalidArgumentException(
                    "key={$key} version={$version} üçün prompt tapılmadı"
                );
            }
        });

        // Keşi etibarsız edin
        Cache::forget("ai_prompt:{$key}");

        Log::info('Prompt aktivləşdirildi', [
            'key'         => $key,
            'version'     => $version,
            'deployed_by' => $deployedBy,
        ]);
    }

    /**
     * Yeni prompt versiyasını saxlayın.
     */
    public function store(
        string $key,
        string $version,
        string $systemPrompt,
        string $description,
        array $parameters = [],
        ?string $userPromptTemplate = null,
    ): AiPrompt {
        return AiPrompt::create([
            'key'                  => $key,
            'version'              => $version,
            'system_prompt'        => $systemPrompt,
            'user_prompt_template' => $userPromptTemplate,
            'description'          => $description,
            'parameters'           => $parameters,
            'is_active'            => false, // activate() vasitəsilə açıq şəkildə aktivləşdirin
        ]);
    }

    /**
     * Sorğu üçün prompt-u render edin.
     */
    public function render(string $key, array $variables = [], ?string $userId = null): RenderedPrompt
    {
        $config = $this->get($key, $userId);

        $systemPrompt = $config->systemPrompt;

        // Təqdim olunubsa istifadəçi prompt şablonunu render edin
        $userPromptRendered = null;
        if ($config->userPromptTemplate) {
            $template = PromptTemplate::fromString($config->userPromptTemplate);
            $userPromptRendered = $template->render($variables);
        }

        return new RenderedPrompt(
            systemPrompt: $systemPrompt,
            userPrompt: $userPromptRendered,
            config: $config,
        );
    }

    private function resolveExperimentPrompt(
        AiPromptExperiment $experiment,
        string $userId,
    ): PromptConfig {
        // Deterministik təyinat: eyni istifadəçi həmişə eyni variantı alır
        $hash = crc32($experiment->experiment_name . $userId);
        $isVariant = abs($hash) % 100 < $experiment->variant_traffic_percent;

        $prompt = $isVariant
            ? $experiment->variantPrompt
            : $experiment->controlPrompt;

        Log::info('A/B eksperiment prompt-u həll edildi', [
            'experiment'  => $experiment->experiment_name,
            'variant'     => $isVariant ? 'variant' : 'control',
            'user_id'     => $userId,
            'prompt_id'   => $prompt->id,
        ]);

        return $this->toConfig($prompt);
    }

    private function getActiveExperiment(string $key): ?AiPromptExperiment
    {
        return AiPromptExperiment::whereHas('controlPrompt', fn ($q) => $q->where('key', $key))
            ->where('is_active', true)
            ->first();
    }

    private function toConfig(AiPrompt $prompt): PromptConfig
    {
        return new PromptConfig(
            id: $prompt->id,
            key: $prompt->key,
            version: $prompt->version,
            systemPrompt: $prompt->system_prompt,
            userPromptTemplate: $prompt->user_prompt_template,
            model: $prompt->model,
            parameters: $prompt->parameters ?? [],
        );
    }
}

readonly class PromptConfig
{
    public function __construct(
        public int $id,
        public string $key,
        public string $version,
        public string $systemPrompt,
        public ?string $userPromptTemplate,
        public string $model,
        public array $parameters,
    ) {}
}

readonly class RenderedPrompt
{
    public function __construct(
        public string $systemPrompt,
        public ?string $userPrompt,
        public PromptConfig $config,
    ) {}
}

class PromptNotFoundException extends \RuntimeException {}
```

---

## Laravel: Az-Atışlı Nümunə Qurucu

```php
<?php

declare(strict_types=1);

namespace App\AI\Prompts;

/**
 * Prompt-lar üçün az-atışlı nümunə blokları qurur.
 *
 * Az-atışlı nümunələr xüsusi tapşırıqlarda model performansını
 * dramatik şəkildə yaxşılaşdırır. Bu qurucu nümunə kitabxanalarını
 * idarə etməyə və sorğu başına ən uyğun nümunələri seçməyə kömək edir.
 */
class FewShotExampleBuilder
{
    /** @var FewShotExample[] */
    private array $examples = [];

    private int $maxExamples = 5;
    private string $format = 'xml'; // 'xml' | 'text' | 'json'

    public function addExample(
        string $input,
        string $output,
        array $tags = [],
        float $weight = 1.0,
    ): static {
        $this->examples[] = new FewShotExample($input, $output, $tags, $weight);
        return $this;
    }

    /**
     * Massivdən nümunələr yükləyin (məs., veritabanı və ya konfiqurasiyadan).
     */
    public function loadFrom(array $examples): static
    {
        foreach ($examples as $example) {
            $this->addExample(
                input: $example['input'],
                output: $example['output'],
                tags: $example['tags'] ?? [],
                weight: $example['weight'] ?? 1.0,
            );
        }
        return $this;
    }

    public function withMaxExamples(int $max): static
    {
        $this->maxExamples = $max;
        return $this;
    }

    public function withFormat(string $format): static
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Etiket uyğunluğuna əsasən uyğun nümunələri seçin.
     * Çəkili təsadüfi seçimə qayıdış edir.
     */
    public function selectFor(array $requiredTags = []): static
    {
        if (empty($requiredTags) || empty($this->examples)) {
            return $this;
        }

        $clone = clone $this;

        // Hər nümunəni etiket üst-üstə düşməsinə görə qiymətləndirin
        $scored = array_map(function (FewShotExample $ex) use ($requiredTags) {
            $overlap = count(array_intersect($ex->tags, $requiredTags));
            return ['example' => $ex, 'score' => $overlap + $ex->weight];
        }, $clone->examples);

        // Balına görə azalan sıralayin, ilk N-i alın
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $selected = array_slice($scored, 0, $clone->maxExamples);

        $clone->examples = array_column($selected, 'example');

        return $clone;
    }

    /**
     * Prompt-a daxil etmək üçün formatlanmış nümunələr sətirini qurun.
     */
    public function build(): string
    {
        if (empty($this->examples)) {
            return '';
        }

        $selected = array_slice($this->examples, 0, $this->maxExamples);

        return match ($this->format) {
            'xml'  => $this->buildXml($selected),
            'text' => $this->buildText($selected),
            'json' => $this->buildJson($selected),
            default => $this->buildXml($selected),
        };
    }

    /**
     * @param  FewShotExample[]  $examples
     */
    private function buildXml(array $examples): string
    {
        $lines = ["<examples>"];

        foreach ($examples as $example) {
            $lines[] = "  <example>";
            $lines[] = "    <input>{$example->input}</input>";
            $lines[] = "    <output>{$example->output}</output>";
            $lines[] = "  </example>";
        }

        $lines[] = "</examples>";
        return implode("\n", $lines);
    }

    /**
     * @param  FewShotExample[]  $examples
     */
    private function buildText(array $examples): string
    {
        $parts = [];

        foreach ($examples as $i => $example) {
            $num = $i + 1;
            $parts[] = "Nümunə {$num}:\nGiriş: {$example->input}\nÇıxış: {$example->output}";
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param  FewShotExample[]  $examples
     */
    private function buildJson(array $examples): string
    {
        $data = array_map(fn ($ex) => [
            'input'  => $ex->input,
            'output' => $ex->output,
        ], $examples);

        return json_encode($data, JSON_PRETTY_PRINT);
    }
}

readonly class FewShotExample
{
    public function __construct(
        public string $input,
        public string $output,
        public array $tags = [],
        public float $weight = 1.0,
    ) {}
}
```

### Tam Prompt Montaj Nümunəsi

```php
<?php

// Hər şeyi bir araya gətirin: şablon + nümunələr + kitabxana

use App\AI\Prompts\{FewShotExampleBuilder, PromptLibrary, PromptTemplate};
use App\AI\Client\ClaudeClient;

class SupportTicketClassifier
{
    public function __construct(
        private readonly PromptLibrary $library,
        private readonly ClaudeClient $client,
    ) {}

    public function classify(string $ticketText, string $userId): string
    {
        // 1. Kitabxanadan versiyalaşdırılmış prompt-u əldə edin
        $prompt = $this->library->get('support.classification', $userId);

        // 2. Az-atışlı nümunələri qurun
        $examples = (new FewShotExampleBuilder())
            ->addExample(
                input: "Ödənişim uğursuz oldu, lakin yenə də hesablandım",
                output: "billing",
                tags: ['billing', 'payment']
            )
            ->addExample(
                input: "Tətbiq parametrləri açdığımda çöküdür",
                output: "technical",
                tags: ['technical', 'bug']
            )
            ->addExample(
                input: "Məlumatlarımı necə ixrac edə bilərəm?",
                output: "how_to",
                tags: ['usage', 'feature']
            )
            ->withMaxExamples(3)
            ->withFormat('xml')
            ->build();

        // 3. Nümunələrlə şablonu render edin
        $template = PromptTemplate::fromString(
            "Bu dəstək biletini təsnif edin.\n\n{$examples}\n\n" .
            "İndi təsnif edin:\n<ticket>{{ticket_text}}</ticket>\n\n" .
            "YALNIZ kateqoriya etiketiylə cavab verin."
        );

        $userMessage = $template->render(['ticket_text' => $ticketText]);

        // 4. API-ni çağırın
        $response = $this->client->messages(
            messages: [['role' => 'user', 'content' => $userMessage]],
            options: [
                'system'      => $prompt->systemPrompt,
                'model'       => $prompt->model,
                'temperature' => 0.0,
                'max_tokens'  => 20,
            ]
        );

        return trim($response->content);
    }
}
```

---

## Produksiyada Prompt-ları Test Etmək

```php
<?php

namespace Tests\AI\Prompts;

use App\AI\Client\ClaudeClient;
use App\AI\Prompts\PromptLibrary;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Prompt keyfiyyəti reqressiya testləri.
 *
 * Prompt dəyişikliklərini yerləşdirməzdən əvvəl bunları çalışdırın.
 * Hədəf: benchmark-da > 95% keçmə nisbəti.
 *
 * QEYD: Bunlar real API çağırışları edir. @group ai olaraq etiketləyin
 * və CI zamanı xərc qarşısını almaq üçün vahid testlərdən ayrıca çalışdırın.
 */
class InvoiceExtractionPromptTest extends TestCase
{
    public static function invoiceExtractionCases(): array
    {
        return [
            'standard_invoice' => [
                'input'    => 'Acme Corp-dan Faktura #1234, $500.00 2024-02-15-ə qədər ödənilməlidir',
                'expected' => ['vendor' => 'Acme Corp', 'total' => 500.00],
            ],
            'invoice_with_tax' => [
                'input'    => 'TechCo-dan Faktura: Cəm $400, Vergi $40, Ümumi $440',
                'expected' => ['total' => 440.0, 'tax' => 40.0],
            ],
            'ambiguous_currency' => [
                'input'    => 'Məbləğ: 1,500 (valyuta göstərilməyib)',
                'expected' => ['total' => 1500.0],
            ],
        ];
    }

    #[DataProvider('invoiceExtractionCases')]
    public function test_extracts_correctly(
        string $input,
        array $expected,
    ): void {
        // Bu, hazırlama mühitdə real API-ni çağırar
        $this->markTestSkipped('--group=ai bayrağı ilə çalışdırın');
    }
}
```

---

*Əvvəlki: [06 — Claude API Bələdçisi](./01-claude-api-guide.md) | Növbəti: [08 — Alət İstifadəsi](./04-tool-use.md)*
