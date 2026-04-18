# Tech Lead Role

## Niyə vacibdir? (Why it matters)
Tech lead — sənin dəyərinin artıq kod sətirləri ilə yox, komanda nəticəsi ilə ölçüldüyü ilk roldur. Yaxşı tech lead 5 mühəndisi 7 mühəndis kimi işlədir. Pis tech lead isə 5 mühəndisi 3 mühəndis kimi işlədir. Rol kritikdir, çünki əksər şirkətlər güclü IC-ləri təlim vermədən bu rola keçirir və bu keçid çətindir.

Senior PHP/Laravel mühəndisi üçün tech lead adətən növbəti addımdır. Hələ də kod yazırsan, amma daha az. Komandanın texniki istiqamətini idarə edirsən və problemlər olduqda stakeholderlərin zəng etdiyi şəxs sənsən.

## Yanaşma (Core approach)
1. **Tech lead menecer deyil, amma üst-üstə düşən hissələr var.** Sən hiring/firing/maaş kimi qərarlara sahib deyilsən, amma hər üçünə təsir edirsən. Texniki istiqamətə isə sən sahibsən.
2. **Təxmini vaxt bölgüsü: 30% IC, 40% tech direction, 30% komanda sağlamlığı.** Bu komanda ölçüsünə görə dəyişir. Kiçik komanda = daha çox IC. Böyük komanda = daha çox istiqamət, daha az IC.
3. **IC vaxtını qoru, amma azalmasına icazə ver.** Daha az kod yazacaqsan. İşin belədir. Tam vaxt kod yazmaq istəyirsənsə, tech lead sənin üçün deyil.
4. **Texniki roadmap-ı idarə et.** "Növbəti kvartalda nə qururuq və niyə?" sualına cavab verən sənsən.
5. **Komanda sağlamlığı işin bir hissəsidir.** Komandandakı mühəndislər tükənirsə, bu sənin problemindir — hətta menecer olsa belə.

## Konkret skript və template (Scripts & templates)

### Həftəlik tech lead ritmi
- Bazar ertəsi: 1 saat roadmap review (bu həftə nə edirik, planda gedirikmi)
- Çərşənbə axşamı/Cümə axşamı: code review-lar + pairing
- Çərşənbə: 1:1-lər, stakeholder sync
- Cümə: design review-lar, RFC yazmaq, tech debt planını təmizləmək

### Özünü tech lead kimi təqdim etmək (ilk komanda iclası)
> "Hi everyone. As you know I'm stepping into the tech lead role for this team. My goal in the first month is to listen and understand where we are. I'll keep doing some hands-on work, but I'll also be setting up: [weekly design review, monthly tech debt day, clearer RFC process]. I want to hear from each of you in 1:1s — what is going well, what is blocking us, what would you change."

### Texniki istiqamət təyin etmək
Email və ya RFC açılışı:
> "Over the next quarter, our team's focus is: [3 priorities]. Here's the reasoning: [business context]. I want feedback on this by Friday. If I don't hear concerns, we'll commit to this direction next Monday."

### Scope creep-ə "yox" demək (PM-ə)
> "If we add this feature to the current sprint, the API migration slips by two weeks. Which do you prefer? I can break the feature into a smaller v1 that fits — want me to scope that?"

### Komandanın qarşısındakı blokları açmaq
> "I noticed you've been waiting on the payments team for 3 days. Let me reach out to their tech lead and push this. Don't wait another day on your own."

### Komandanda texniki fikir ayrılığını həll etmək
> "I hear two valid approaches. Let's write a 1-page RFC with both options and trade-offs. I'll make the final call on Friday. Either of you can write it — volunteer?"

### Özün IC işinə qayıtmalı olduqda
> "I'm going to pick up the auth refactor ticket this sprint. I've been too far from the code, and this is a good way to stay close. Plus it unblocks two of you."

## Safe phrases for B1 English speakers
- "Let me think about that and come back to you tomorrow." — böyük qərarda vaxt qazanmaq
- "I want to hear from everyone before we decide." — hamının rəyini daxil edən qərarvermə
- "My goal is to understand, not to judge." — 1:1 açmaq
- "What do you think we should do?" — qərarı həvalə etmək
- "Let me unblock this for you." — senior davranışı
- "I don't know, but I'll find out." — iddiaçılıq əvəzinə dürüstlük
- "Let's write this down so we all remember." — danışığı sənədə çevirmək
- "I want to protect your focus time." — qayğı göstərmək
- "This is a tech lead decision, and I'll own the outcome." — lazım olanda səlahiyyəti öz üzərinə götürmək
- "I disagree, but let me explain my reasoning." — kobud olmadan birbaşa olmaq
- "Is this urgent, or can it wait?" — öz vaxtını idarə etmək
- "Who is the right person to own this?" — həvalə etmək
- "Let's timebox this to 15 minutes." — iclas idarəsi
- "I'll draft a proposal and share it Friday." — hərəkətə bağlanmaq
- "What would success look like for this project?" — nəticədən başlamaq

## Common mistakes / anti-patterns
- Hələ də hər şeyi özün etmək. Həvalə etməsən scale edə bilməzsən.
- "Mən edəm daha sürətlidir" deyə həvalə etməmək. Qısa müddətdə doğrudur, uzunmüddətdə səhvdir.
- "Texniki deyil" deyə 1:1-lərdən qaçmaq. Artıq 1:1-lər işin əsasıdır.
- Qərarlar üçün bottleneck-ə çevrilmək. Mümkün olanda qərarları aşağıya ötür.
- "Niyəni" çatdırmamaq. Komandan "nəyi" deyil, "niyəni" izləyir.
- Bütün vaxtını iclaslarda keçirmək. Həftədə 1-2 səhəri dərin iş üçün qoru.
- Heç nə yazmamaq. Sənədləşdirilməyibsə, olmamış kimidir.
- Meneceri ilə skip-level vaxtını atlamaq. Menecerin bir resursdur.
- Tech lead "ən senior IC" kimi düşünmək. Bu belə deyil. Bu liderlik roludur.
- Seniorları mikromenecment etmək. Onlar tərk edəcək.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "What does a tech lead do in your experience?"
- "How do you balance coding and leading?"
- "How do you decide what your team works on?"
- "How do you handle a team member who disagrees with the direction?"

Cavab planı:
1. **Rolun tərifi:** "I see tech lead as three parts: ~30% IC, ~40% technical direction, ~30% team health. The split shifts by team size."
2. **Konkret nümunə:** "In my last team of 5, I led the migration from Laravel 8 to 10. I wrote the RFC, split it into phases, and pair-programmed the first phase to set patterns."
3. **Balanslaşdırma:** "I protect one coding day a week minimum. But I accept that my IC output is lower — my job is to make the team faster."
4. **Fikir ayrılığı:** "I encourage disagreement in design review. Once the decision is made, I expect everyone to commit, including me when I'm overruled."

## Further reading
- "The Manager's Path" by Camille Fournier (especially the "Tech Lead" chapter)
- "Staff Engineer" by Will Larson
- "An Elegant Puzzle" by Will Larson
- "The Making of a Manager" by Julie Zhuo
- "High Output Management" by Andy Grove
