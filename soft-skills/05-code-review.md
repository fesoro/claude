# Code Review (Middle)

## Niyə vacibdir? (Why it matters)
Code review senior mühəndislərin öz zövqünü göstərdiyi, juniorları öyrətdiyi və kod bazasını qoruduğu əsas yerdir. Yaxşı review prodakşna çıxmadan əvvəl buglari tutur, komanda içində biliyi yayır və insanların kodu pushlamaqdan qorxmadığı mədəniyyət qurur. Pis review mədəniyyəti qorxu yaradır, deliveri yavaşladır və juniorlara öz işlərini gizlətdirir.

Senior PHP/Laravel mühəndisi üçün code review həm də nüfuz qazandığın yerdir. İnsanlar sənin "senior" olduğunu öz PR-larının keyfiyyətinə yox, məhz sənin review-larının keyfiyyətinə görə qərarlaşdıracaq.

## Yanaşma (Core approach)
1. **24 saat ərzində review et.** 3 gün gözləyən PR sınıq komandadır. Bu gün review edə bilmirsənsə, bunu de və vaxt ver.
2. **Stil barədə yox, əvvəlcə məntiq, arxitektura və təhlükəsizlik barədə kommentariya yaz.** Stil xırdalıqları real məsələdir, amma ən az vacib kateqoriyadır. Prioriteti aydın göstərmək üçün prefiks istifadə et.
3. **Əmr vermə, sual ver.** "Niyə X-i Y-dən seçdin?" "Y istifadə et"-dən yaxşıdır. Sən konteksti bilməyə bilərsən.
4. **Müsbət feedback də ver.** PR təmizdirsə, bunu de. "Bu refactor service class-ı çox daha oxunaqlı edir" heç nəyə başa gəlmir, amma etibar qurur.
5. **Tonu seniority-ə uyğunlaşdır.** Juniorlara qarşı daha mehriban ol və səbəbi izah et. Seniorlarla birbaşa ol və onların bunu başa düşəcəyinə güvən.

## Konkret skript və template (Scripts & templates)

### Comment prefixes (team convention)
- `Nit:` — stil və ya şəxsi üstünlük, blok etmir. Author məhəl qoymaya bilər.
- `Question:` — başa düşmək istəyirəm, mütləq dəyişmək lazım deyil.
- `Suggestion:` — məncə bu daha yaxşıdır, amma author qərar verir.
- `Blocking:` — merge-dən əvvəl düzəldilməlidir. Bug, təhlükəsizlik və ya arxitektura məsələsi.
- `Praise:` — müsbət feedback, səmimi.

### Junior PR review edərkən
Açılış kommenti:
> "Thanks for the PR. Overall the approach is solid. I left a few questions and one blocking comment about the database query. Let me know if anything is unclear — happy to pair on it."

Inline kommentariya (öyrətmək üçün):
> "Question: What happens here if `$user` is null? I think we should handle that case. One common pattern in Laravel is to use `firstOrFail()` earlier in the flow so the controller can assume a valid user."

### Razı olmadığın senior PR-ı review edərkən
> "I'd love to understand why you chose `EventDispatcher` here over a direct service call. My concern is that the event flow becomes harder to trace in logs. Not blocking — you may have context I don't. Can we discuss in Slack?"

### Müsbət feedback vermək (bunu atlama)
> "Praise: The test cases here cover the edge cases I was going to ask about. Nice."
> "This refactor makes the `OrderService` much easier to read. Thanks for doing it while you were in there."

### Sərt səslənmədən dəyişiklik tələb etmək
> "I think we need to handle the timeout case before merging. Could you add a try/catch around the external call and log the failure? Happy to review again quickly once it's in."

### Qeydlərlə təsdiq etmək
> "LGTM. Left two non-blocking suggestions you can address in a follow-up if you want."

### Öz review-ını qəbul edərkən — kommentlərə cavab vermək
- Səmimi təşəkkür: "Good catch, fixing now."
- Razı olmamaq: "I see your point. My reasoning was X. What do you think?"
- Kömək istəmək: "I'm not sure how to handle this cleanly. Can we pair for 15 minutes?"

## Safe phrases for B1 English speakers
- "Thanks for the PR." — istənilən review üçün neytral başlanğıc
- "Overall the approach looks good." — təkliflərdən əvvəl müsbət çərçivə
- "I have a few questions." — növbəti deyiləcəkləri yumşaldır
- "Could you help me understand why you chose X?" — etirazı təhlükəsiz şəkildə bildirmək
- "I think we should handle the case when..." — edge case təklif etmək
- "Not blocking, but..." — feedback-in istəyə bağlı olduğunu aydın göstərmək
- "Nice catch." / "Good point." — qısa müsbət ifadələr
- "Happy to pair on this if it helps." — dəstək təklif etmək
- "Let me know if anything is unclear." — müzakirəyə dəvət
- "I'll merge once the tests pass." — bağlayıcı ifadə
- "I'd love to understand your reasoning here." — dialoqa dəvət
- "This is a preference, feel free to ignore." — nit-i yumşaltmaq
- "Could we discuss this in Slack?" — lazım olanda PR-dan çıxarmaq
- "Good work on the tests." — konkret tərif
- "I was going to suggest X, but I see you already did it." — müsbət diqqət

## Common mistakes / anti-patterns
- Yalnız mənfi şeyləri qeyd etmək. Tərif etibar qurur və kodu həqiqətən oxuduğunu göstərir.
- "Why did you do this?" — "Question:" prefiksi olmadan — hirsli səslənir.
- Junior PR-da 40 nit vermək. Ən vacib 3-nü seç, pattern-i qeyd et və davam et.
- Authorun kodunu kommentdə yenidən yazmaq ("change it to this"). Əvəzinə narahatlığı izah et və qoy onlar həll etsin.
- 3 gün səssizlikdən sonra review etmək. Bu gün review edə bilmirsənsə, xəbər ver.
- Oxumadan təsdiqləmək. Vaxtın yoxdursa, başqasını təyin et.
- Cümə axşamı günortadan sonra tab vs space mübahisəsi etmək. Buna dəyməz.
- Heç vaxt təsdiq etməmək, çünki həmişə "bir daha şey" tapırsan. Nə vaxt kifayət qədər yaxşının kifayət qədər yaxşı olduğunu bil.
- Məntiq səhvdirsə, yalnız stil üçün review etmək.
- Açıq sərt tənqid. Ton lazımdırsa, DM-ə və ya zəngə keç.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "How do you give feedback on a PR from a more senior engineer?"
- "How do you handle a junior who keeps making the same mistake in PRs?"
- "Describe your code review process."
- "How do you balance speed and quality in reviews?"

Cavab planı (STAR-lite):
1. **Prinsip:** "I try to review within 24 hours, and I prefix my comments so priority is clear."
2. **Nümunə:** "Recently a junior opened a PR where the SQL was vulnerable to N+1. Instead of rewriting it, I asked why they chose that approach, explained the cost, and linked them to Laravel's eager loading docs. Next PR they did it right without me."
3. **Ton barədə prinsip:** "I always include at least one piece of positive feedback. If the PR is clean, I say so."
4. **Seniorla razılaşmamaq:** "If I disagree with a senior, I phrase it as a question and offer to discuss offline. I don't block their PR unless it's a real bug or security issue."

## Further reading
- "The Art of Readable Code" by Dustin Boswell and Trevor Foucher
- "What to look for in a code review" by Trisha Gee (Google blog series)
- Google Engineering Practices Documentation — Code Review Developer Guide (free online)
- "Your Code as a Crime Scene" by Adam Tornhill
- Conventional Comments specification (conventionalcomments.org)
