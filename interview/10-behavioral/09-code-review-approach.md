# Code Review Approach (Senior ⭐⭐⭐)

## İcmal

Code review sualları interviewer-ə sizin keyfiyyət standartlarınızı, komanda dinamikasına yanaşmanızı və başqalarını necə inkişaf etdirdiyinizi göstərir. "Code review-u necə aparırsınız?", "Çətin bir PR feedback-i verin dediyinizdə nə baş verdi?" kimi suallar bu kateqoriyadandır.

Senior developer üçün code review sadəcə bug tapmaq deyil — knowledge transfer, architectural consistency, team growth alətidir. Eyni zamanda team velocity-yə təsir edən kritik proses-dir: çox ləng reviewer PR-ları bloklayır, çox tez review isə keyfiyyəti aşağı salır.

Code review-un iki tərəfi var: **reviewer** kimi nə edirsinizsə, **author** kimi nə edirsinizsə. Güclü cavab hər ikisini əhatə edir.

---

## Niyə Vacibdir

Interviewerlər bu sualı soruşarkən bir neçə şeyi ölçürlər: texniki standartlarınızın yüksəkliyini, başqaları ilə constructive şəkildə işləyə bildiyinizi, "gatekeeper" yox "mentor" rolunu oynaya bildiyinizi. Çox sərt reviewer-lər PR queue-nu blokladır, çox yumşaqlar isə keyfiyyəti aşağı salır. Doğru balansı tapa bilmək senior engineer-in əsas keyfiyyətidir.

---

## Əsas Anlayışlar

### 1. İnterviewerin həqiqətən nəyi ölçdüyü

- **Priority order** — correctness > security > performance > maintainability > style
- **Constructiveness** — "bu yanlışdır" yox, "bu yanaşmanı nəzərə al, çünki..."
- **Scope discipline** — style nitpick-lərdə boğulmaq deyil, real issue-lara fokus
- **Speed** — PR-ları blokladıqda komanda velocity-si düşür — 24 saat SLA vacibdir
- **Junior-a fərqli yanaşma** — educate vs just flag, Socratic method
- **Comment labeling** — blocking/suggestion/question/nit fərqi
- **Author perspective** — PR yazan niyə bu seçimi etdi? Soruşun, hücum etməyin
- **Positive feedback** — yalnız problem tapmaq deyil, yaxşı şeyləri də qeyd etmək

### 2. Red flags — zəif cavabın əlamətləri

- "Hər PR-da 50 comment yazıram" — gatekeeper davranışıdır, PR author-u demotiv edir
- "Mənim yazdığım kimi olmayan hər şeyi reject edirəm" — subjective standard
- PR-lara 3–4 gün cavab verməmək — velocity blocker
- Sadəcə style issue-larına fokuslanmaq, business logic-i keçmək
- Personal attack: "bu kod çox pisdir" — author-a deyil, kod-a yanaşın
- Security issue tapıb susub keçmək — responsibility-dən qaçmaq
- "Heç vaxt approve etmirəm birinci review-da" — ego-based policy

### 3. Green signals — güclü cavabın əlamətləri

- Comment-ləri blocking/suggestion/question kimi label etmək
- Niyə dəyişiklik lazım olduğunu izah etmək — "çünki X production-da Y problemini yarada bilər"
- Security, performance, correctness-i style-dan önə çəkmək
- Junior-lara Socratic method ilə sual vermək — cavab vermədən
- Positive feedback əlavə etmək — "bu approach çox yaxşıdır"
- Self-review etmək — PR yazmadan əvvəl öz kodunu oxumaq
- "24 saat SLA" — komanda velocity-ni hörmət etmək

### 4. Şirkət tipinə görə gözlənti

| Şirkət tipi | Fokus |
|-------------|-------|
| **Stripe, Shopify** | Engineering excellence, detailed review, learning culture |
| **Startup** | Speed — functional, minimal nitpick |
| **Enterprise** | Compliance, security, process adherence |
| **Distributed team** | Async-first, yazılı izahat, clear blocking vs non-blocking |

### 5. Seniority səviyyəsinə görə gözlənti

| Səviyyə | Review depth |
|---------|-------------|
| **Senior** | Security, correctness, performance — öz domain-ında |
| **Lead** | Architecture alignment, pattern consistency, team standards |
| **Staff/Principal** | Cross-team review, RFC review, architectural decisions |

### 6. "Gatekeeper" vs "Collaborator" fərqi

| Gatekeeper | Collaborator |
|-----------|-------------|
| "Mənim standartlarım olmalıdır" | "Komandanın standartları olmalıdır" |
| Reject etmək = güclü review | Mentor etmək = güclü review |
| "Sən yanılırsın" | "Bu approach üçün alternativ düşünmüsən?" |
| Her PR-da 30+ comment | Priority-based focused comments |
| "Heç vaxt birinci review-da approve etmirəm" | "Blocking issue yoxdursa, approve" |

---

## Praktik Baxış

### Cavabı necə qurmaq

İki hissə: əvvəlcə ümumi code review fəlsəfənizi 30 saniyə izah edin, sonra konkret nümunəyə keçin. Nümunə mütləq real olmalı — həm texniki (nə tapdın), həm interpersonal (necə communicate etdin) aspektlərini əhatə etsin.

### Review priority sırası

```
P0 — Security (authentication, injection, secrets, crypto)
P1 — Correctness (logic error, race condition, data loss)
P2 — Performance (N+1, missing index, unbounded query)
P3 — Maintainability (separation of concerns, naming, complexity)
P4 — Tests (missing test, wrong assertion)
P5 — Style (formatting, naming convention)
```

### Comment labeling sistemi

- **`[blocking]`** — merge olunmadan əvvəl mütləq düzəlməlidir
- **`[suggestion]`** — yaxşılaşdıra bilər, amma blocker deyil
- **`[question]`** — anlamaq istəyirəm, cavab ver — yanlış da olmaya bilər
- **`[nit]`** — minor style, author istəsə düzəldər

Bu label-lar PR author-un başını aydın edir: 3 blocking + 7 suggestion varsa, blocking-ləri fix et, suggestion-lara zaman tapanda bax.

### Junior vs Senior PR-a fərqli yanaşma

| Junior PR | Senior PR |
|-----------|-----------|
| "Niyə belə etdin?" sualı ilə başla | Faktlar + alternatif göstər |
| Resource link əlavə et | Trade-off izah et |
| Call edib izah et (sync) | Yazılı comment (async friendly) |
| Pozitiv nəzərə al da vur | Direct və konkret |
| Özü fix etsin | Özü seçim etsin |

### Self-review checklist (PR author kimi)

- Bu kodu bir ay sonra başqası anlaya bilərmi?
- Hər edge case üçün test varmı?
- Security: hardcoded secret, injection, auth bypass var mı?
- Performance: N+1, unbounded loop, missing index?
- Error handling: exception catch-lər düzgün işləyir?
- Dependency əlavəsi lazım idi ya mövcud kod genişləndirilə bilərdi?

### Tez-tez soruşulan follow-up suallar

1. **"What do you do when PR author disagrees with your review comment?"** — "Əvvəl onların perspektivini başa düşürəm. Əgər mənim comment texniki arqumentə əsaslanırsasa, daha çox data gətirirəm. Əgər subyektivdirsə — onların seçimini qəbul edirəm. `[blocking]` vs `[suggestion]` bu anı asanlaşdırır."
2. **"A PR comes in with 500 lines of changes and no tests — what do you do?"** — "İlk sual: 'Niyə testlər yoxdur?' Əgər complex idi — 'Əvvəlcə testlər yazın, sonra implementation review edərik.' 500 sətir PR-ı da decomposition problemi kimi qeyd edirəm."
3. **"How do you ensure code review doesn't become a bottleneck?"** — "24 saat SLA özüm üçün. Priority qazanmaq üçün 'hər gün review batch' qaydası. Blocking vs non-blocking aydın etiketlə — author blocker olmayan şeylər üçün gözləməsin."
4. **"Have you ever missed a critical bug in code review?"** — Honest cavab: "Bəli — [konkret nümunə]. Bunu önləmək üçün indi P0/P1 checklist-i var."
5. **"How do you build a code review culture in a team that doesn't have one?"** — "RFC ilə "code review niyə vacibdir" yazıram. Özüm modelling edirəm — comment label-lar, konstruktiv tone. Team retrospective-də velocity → review lag korrelyasiyasını göstərirəm."
6. **"How do you handle it when a reviewer is too harsh or nitpicky on your PR?"** — "'Bu comment blocking mıdır?' sualını soruşuram. Non-blocking üçün sync meeting self. Blocking üçün data ilə cavab verirəm."
7. **"What's your policy on approving PRs that have minor issues?"** — "'Nit' ya 'suggestion' olan PR-ları approve edib 'author istəsə düzəldir' qeyd edirəm. Sadəcə blocking issue-lar üçün request changes."

### Nə deyilməsin

- "Hər şeyi çox diqqətlə yoxlayıram, heç bir bug buraxmıram" — bu boast, sübut lazımdır
- "Style baxımından çox ciddi yanaşıram" — bu əslində red flag ola bilər
- "Junior-ların kodu həmişə yenidən yazıram" — mentor deyil, gatekeeper
- "30–40 comment yazıram" — bu PR author-u demotiv edir, velocity-ni öldürür

---

## Nümunələr

### Tipik Interview Sualı

"Describe your approach to code reviews. Can you give me an example where your review caught a significant issue?"

---

### Güclü Cavab (STAR formatında)

**Situation:**
E-commerce şirkətdə senior backend developer idim. 2-junior, 1-mid developer olan teamdəydim. Bir gün junior developer authentication module-ü refactor edən PR açdı — 8 fayl, ~400 sətir dəyişiklik. PR description-da "JWT token handling improved, security enhanced" yazırdı. O özünə çox confident idi. Mən ilk baxışdan bəzi ciddi issue-lar gördüm — amma həm texniki düzgünlüyü, həm developer motivasiyasını qorumaq lazım idi.

**Task:**
PR-ı review etməliydim. Açıb baxanda bir neçə ciddi issue gördüm — bunları necə communicate edəcəyim əhəmiyyətli idi: həm security-ni fix etmək lazım idi, həm developer-i demotiv etmədən öyrətmək. O confidence-i qorunmalıydı.

**Action:**
Review-a başlamadan PR-ı başdan sona bir dəfə oxudum — big picture üçün. Sonra priority sırasına görə comment-lər yazdım:

**[blocking] Security — P0:**
```php
// Junior-un kodu:
$token = base64_encode($userId . ':' . time() . ':' . $secret);
```
Comment: "Base64 encoding deyil, sadəcə encoding — asanlıqla decode edilir. JWT_SECRET plaintext saxlanılır. Bu token-i intercept edən biri userId-ni görür. `firebase/php-jwt` library-sinə bax — HMAC-SHA256 ilə imzalama tələb olunur. [OWASP JWT guide] linkini əlavə etdim. Bu blocking — merge olmadan fix lazımdır."

**[blocking] Logic error — P0:**
Token expiry check-i yox idi. `created_at` field var idi, amma heç yerdə yoxlanılmırdı. Köhnə token-lər ömürlük valid qalırdı.
Comment: "Token-in `exp` claim-i olmalı, hər request-də validate olunmalıdır. Misal əlavə etdim."

**[suggestion] Performance — P1:**
```php
// Hər request-də DB hit:
$user = User::where('id', $userId)->first();
```
Comment: "[suggestion] Blocking deyil, amma yüksək traffic-də hər auth request-i DB-ə gedəcək. Redis cache layer düşün — `user:{id}` key ilə 15 dəqiqəlik TTL. Bu blocking deyil, amma production-a çıxmadan əvvəl nəzərə alsaq yaxşıdır."

**[nit] Style — P5:**
"Bu method 80 sətirdir. 3 kiçik private metoda parçalaya bilərəm — `validateToken()`, `getUserFromToken()`, `refreshIfExpired()`. [nit] — Blocker deyil."

Junior-u yazılı comment-lərlə bağlamadım — call-a çağırdım. Security issue-nu whiteboard-da göstərdim: "base64_decode('YWRtaW4=') == 'admin' — gör nə asan." Ona "bu sənin günahın deyil, JWT-nin bu aspekti çox az dokumentasiya-da izah olunur" dedim. Hər comment-ə resource link əlavə etdim — cavab yox, yön. Pozitiv bir şeyi də mention etdim: "Rate limiting logic-i çox yaxşı həll edilib — bu approach düzgündür."

**Result:**
Junior 2 gündə security issue-ları düzəltdi, Redis cache əlavə etdi. Daha əhəmiyyətlisi — 1 həftə sonra başqa junior-un PR-ında özü "bu hardcoded secret-dir, env-ə keçirirəm" comment-i yazdı. Knowledge transfer oldu. PR final-da 4 comment ilə merge olundu, staging-də auth latency 15ms-dən 4ms-ə düşdü. Security audit-dən keçdi.

---

### Alternativ Ssenari — Senior developer ilə defensive moment

Senior developer-dən gələn PR-da `N+1` query problemi tapdım. O, özü də senior idi — "bu köhnə kod, mənim əlavəm deyil" dedi.

Mən: "Haqlısan, bu kod köhnədir. Amma bu PR-da biz bu query-dən istifadə edirik — bu PR-ın kontekstinde N+1 bizim feature-ımızdır. `with(['items', 'customer'])` əlavə etmək 5 dəqiqəlik iş. Uzunmüddətli N+1-i fix etmək planı ayrıca ticket edə bilərik, əgər razısansa." O qəbul etdi, `with()` əlavə etdi. Defensive olmasının səbəbi "blame edilmək" qorxusu idi — blame etmədən, birgə məsuliyyət çərçivəsindən danışmaq işə yaradı.

---

### Zəif Cavab Nümunəsi

"Mən çox detallı review edirəm. Hər sətri oxuyuram, style guide-a uyğun olmayan hər şeyi comment edirəm. Çox vaxt 30–40 comment yazıram. Komanda bilir ki, mənim yanımdan keçmək çətindir — keyfiyyət vacibdir."

**Niyə zəifdər:** 30–40 comment = PR author-u demotiv edir, "bu developer-ə PR açmaq istəmirəm" düşüncəsini yaradır. Style-obsession görünür. Slow velocity, bottleneck yaranır. "Mənim yanımdan keçmək çətindir" — gatekeeper self-image-dir, mentor deyil. Real issue-lar style-da boğulur. Team productivity azalır.

---

## Praktik Tapşırıqlar

1. **Comment kategoriyaları məşq et:** Növbəti review-da hər comment-i blocking/suggestion/question/nit kimi labella. Sonra gör: blocking-lər hansı % idi? Style nit-lər çox idi? Bu self-awareness-i göstərir.

2. **Junior PR scenario:** Junior developer-dən gəlmiş bir PR-ı götür. Hər comment-i "educate" fokusunda yenidən yaz — cavab vermək yerinə, sual ver: "Bu approach seçilməsinin səbəbi nə idi?"

3. **"Worst PR" hekayəsi hazırla:** Ən çətin code review-u STAR formatında yaz. Həm texniki issue, həm interpersonal challenge olsun. Bu hazır hekayəni interview-da istifadə et.

4. **Code review checklist yarat:** Öz personal checklist-ini yaz: security → correctness → performance → tests → documentation → style. Bu checklist-i interview-da mention et — sistematik yanaşmanı göstərir.

5. **"Big PR" policy hazırla:** 300+ sətir PR gəlsə nə edirsiniz? Policy yazın: "Draft PR açıq saxlanmalı, review tez-tez olsun" ya da "atomic PR-lara böl." Bu söhbət interviewer-ə process thinking göstərir.

6. **Positive feedback tapşırığı:** Növbəti 5 PR review-da hər birinde ən azı 1 pozitiv comment yazın. "Bu approach çox elegant-dır", "Bu test case mükəmməldir." Pozitiv reinformcement team culture-unu yaxşılaşdırır.

7. **"Missed bug" hekayəsi:** Bir dəfə review etdiyiniz PR-da sonradan bug tapıldı? Bu honest hekayəni hazırlayın — "niyə qaçırdım, indi bu checklistimdə var." Self-awareness interviewer-i razı salır.

8. **Self-review ritual:** Növbəti 3 PR-ı açmadan əvvəl özünüzün yazdığı kodu review edin — sanki başqa bir developer-ə aiddir. Neçə issue tapdınız? Bu practice PR keyfiyyətini artırır, review cycle-ı azaldır.

---

## Ətraflı Qeydlər

### PR size policy

Böyük PR-lar review keyfiyyətini azaldır. Policy:
- **İdeal:** <200 sətir dəyişiklik
- **Acceptable:** 200-400 sətir
- **Problem:** >400 sətir — decompose lazımdır

Böyük PR-lara yanaşma:
1. "Bu PR-ı 3 hissəyə bölə bilərik?" soruş
2. "Draft PR açın, daha tez-tez review edim" təklif et
3. High-level architecture review et, detail review sonra

### Security review checklist (PHP/Laravel)

```php
// P0 security issues to always check:

// 1. Mass assignment vulnerability
User::create($request->all()); // BAD
User::create($request->only(['name', 'email'])); // GOOD

// 2. SQL injection
DB::select("SELECT * FROM users WHERE id = $id"); // BAD
DB::select("SELECT * FROM users WHERE id = ?", [$id]); // GOOD

// 3. Hardcoded secrets
$apiKey = "sk-1234567890abcdef"; // BAD
$apiKey = config('services.stripe.secret'); // GOOD

// 4. Missing authorization check
public function update(Request $request, Post $post) {
    // Missing: $this->authorize('update', $post);
}

// 5. Unvalidated redirect
return redirect($request->input('redirect_url')); // BAD — open redirect
```

Bu checklist review-a başlamadan "security scan" kimi istifadə edilir.

### "Code review culture" qurmaq — 5 addım

1. **Model et** — özünüz yaxşı review yazın; komanda oxuyur
2. **Label sistemi tətbiq et** — blocking/suggestion/nit — hamı istifadə etsin
3. **Review retrospective** — ayda bir "review-larımız necədir?" müzakirəsi
4. **PR template** — author-u self-review-a vadar et
5. **24 saat SLA** — şirkətin review turnaround standartı

Bu 5 addım formal komanda direktivi olmadan tətbiq edilə bilər — "leadership without authority" nümunəsidir.

### Review-da "Nitpicking trap"nı necə önləmək

Çox nitpick = PR author demotivation + velocity azalma. Həll:
- "Yalnız blocking issue-lar üçün review taleb edirəm" qaydası
- Style issue-ları automated tool-a ver (PHP CS Fixer, Laravel Pint, PHPStan)
- "Nitpick budget" — PR başına max 2-3 nit comment
- "Nit comment yazıram = mən bunu mention etdim, amma author qərar verir"

Automated linting style issue-ları aradan qaldırarsa — review yalnız logic + security + architecture-a fokuslanır.

### Code review KPI-ları — ölçülə bilən nəticələr

Review prosesinin effektivliyini göstərən rəqəmlər:
- **Bug escape rate:** Review-dan keçib production-a gəlmiş bug sayı (aylıq)
- **PR turnaround time:** Submit → merge ortalama sürəti
- **Review comment ratio:** Blocking comment / Total comment nisbəti
- **Rework rate:** Rejection-dan sonra redo tələb edən PR faizi

Bu rəqəmlərdən biri hekayənizə daxilsə — interview-da fərq yaradır.

---

## Əlaqəli Mövzular

- `05-mentoring-juniors.md` — Junior-lara review feedback vermək
- `04-technical-disagreements.md` — PR author ilə razılaşmadıqda
- `06-managing-technical-debt.md` — Review-da tech debt-i necə qeyd etmək
- `13-leadership-without-authority.md` — Engineering standards-ı top-down olmadan tətbiq etmək
- `03-greatest-technical-challenge.md` — Review-da tapılan kritik bug hekayəsi
