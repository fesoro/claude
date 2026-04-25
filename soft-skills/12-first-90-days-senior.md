# First 90 Days as a Senior Engineer (Senior)

## Niyə vacibdir? (Why it matters)
Sənin ilk 90 günün növbəti 2+ il üçün sənin barəndə olan narrativi müəyyən edir. Gəlib dinləyən, konteksti öyrənən və etibar qazanan mühəndis "daha çox istədiyimiz senior" olur. Gəlib dərhal kodu tənqid edən və hər şeyi dəyişməyə çalışan mühəndis isə "dinləməyən senior" olur. Hər ikisi ağıllıdır. Hər ikisi eyni kodu yazır. Fərq ilk 90 gündədir.

Yeni şirkət və ya komandaya qoşulan senior PHP/Laravel mühəndisi üçün instinkt çox vaxt sürətli ship etməklə dəyərini sübut etməkdir. Buna müqavimət göstər. Sənin ilk 90 gündəki dəyərin sürətli ship etmək deyil, sürətli öyrənməkdir.

## Yanaşma (Core approach)
1. **Dinləmə rejimi: ilk 30 gün.** Sual ver. Tənqid etmə. Konteksti mənimsə. Kod səbəblərə görə belədir — mühakimə etməzdən əvvəl səbəbləri tap.
2. **Təşkilatı, xidmətləri, texnikanı xəritələ.** Nəyə kim sahibdir. Xidmətlər nə edir. Məlumat necə axır. Bunu kağıza köçür.
3. **İlk 2 həftə ərzində hər komanda yoldaşı ilə 1:1.** Giriş, qiymətləndirmə yox. Komandanın nəyi fərqli edilməsini arzuladıqlarını soruş.
4. **Bir şey dəyişməzdən əvvəl etibar qazan.** Əvvəl kiçik və faydalı bir şey ship et. Sonra böyük dəyişikliklər təklif etmək üçün kreditin olacaq.
5. **30-60-90 günlük plan yaz və menecerinlə paylaş.** Fikrini görünən edir və feedback-ə dəvət edir.

## Konkret skript və template (Scripts & templates)

### 30-60-90 günlük plan şablonu
```
# 30-60-90 plan — [Your name], [Team]
Shared with: [manager]
Updated: [date]

## Context (my understanding)
- Team's mission: [short]
- Top priorities: [1-3]
- Main systems I'll work on: [list]

## Days 1-30: Listen & Learn
- 1:1 with every team member (30 min each)
- Read main codebases (OrderService, AuthService, ...)
- Read the top-10 design docs / RFCs
- Attend every team ritual at least once
- Ship: one small PR (typo fix, doc update, minor bug) — just to get the deploy flow in my hands

## Days 31-60: Contribute
- Pick up a medium ticket end-to-end
- Review ~5 PRs per week
- Start pairing with one junior weekly
- Identify 2-3 pain points to propose fixing in days 60-90

## Days 61-90: Improve
- Lead a small project (2-3 week scope)
- Propose one process or technical improvement (written RFC)
- Deliver a first "leverage moment": doc, workshop, or refactor the team needed

## Open questions for my manager
- What does success look like at 90 days?
- What's the one thing my predecessor did best that I should continue?
- Who are the 3 people outside the team I should meet first?
```

### Menecerinlə ilk 1:1 (1-ci həftə)
> "Thanks for making time. A few things I'd like to understand today:
> 1. What does success look like for me at 30 / 60 / 90 days?
> 2. What's going well on the team that I should protect?
> 3. What's one thing that, if fixed, would make the biggest difference?
> 4. How do you like to communicate — Slack / docs / meetings?
> 5. Any landmines I should know about?"

### Komanda yoldaşları ilə ilk 1:1 (1-2-ci həftə)
> "Hi, I'm new. I'm trying to understand the team, not evaluate it. Can you tell me:
> - What you work on day-to-day?
> - What's going well on the team?
> - What's one thing you wish we did differently?
> - Who should I talk to next?"

### Skip-level ilə ilk 1:1 (təxminən 3-4-cü həftə)
> "I appreciate the time. I'm still in listening mode, so I don't have strong opinions yet. Three questions: What does success for this team look like from your level? What trends do you see in the org I should know about? Is there any way I should be thinking beyond my team?"

### Mühakimə etmədən müşahidə etmək (daxili çərçivə)
Fərqli edəcəyin kod və ya prosesi görəndə, dayan. Soruş:
- Niyə bu cür edilib?
- Kim qərar verib?
- Məhdudiyyətlər nə olub?
- Həll etdiyi problem hələ də problemdir?

60 gündən sonra mühakimə etmək üçün kifayət qədər biləcəksən. Bundan əvvəl təxmin edirsən.

### Kiçik ilk qələbə — ideyalar
- Onboarding sənədindəki typo-nu düzəlt (sən təzəsən — sən onu görürsən)
- "Dev env quraşdırmağı necə etməli" sənədi yaz (yeni qoşulan boşluqları bilir)
- Səs-küylü bir Slack alert-i təmizlə
- Araşdırdığın bug üçün çatışmayan test əlavə et
- Queue-da 5 sətirlik bug-u düzəlt

Kiçik qələbələr etibar qurur. Birinci gün böyük yenidən yazmalar onu dağıdır.

### Dəyişiklik təklif etmək (60-90 gündən sonra)
> "In my first two months I've seen [observation]. Here's what I'd propose [small change]. I want to run it by you before writing the RFC — does this feel like the right problem to solve?"

## Safe phrases for B1 English speakers
- "I'm still in listening mode." — fikir üçün vaxt qazanmaq
- "Help me understand why..." — maraqlı, mühakimə edən yox
- "What was the context when this was decided?" — tarixə hörmət
- "I'll take notes and follow up." — niyyət göstərmək
- "Who else should I talk to about this?" — xəritələmək
- "What's one thing you wish the team did differently?" — input açmaq
- "What does good look like here?" — kalibrasiya
- "I don't have strong opinions yet." — dürüst təvazökarlıq
- "I want to earn trust before proposing changes." — yanaşmanı göstərmək
- "Can you walk me through [system] at a high level?" — öyrənmək
- "What are the landmines I should know?" — səhvlərdən qaçmaq
- "Who are the key stakeholders outside the team?" — xaricə xəritələmək
- "I'd like your feedback at 30 days." — input-a dəvət
- "Thanks for being patient with my questions." — mehribanlıq
- "I'll share my 30-60-90 plan by Friday." — söz vermək

## Common mistakes / anti-patterns
- Birinci gün kodu, arxitekturanı və ya prosesi tənqid etmək.
- Dərhal böyük yenidən yazma təklif etmək. Hələ bilmirsən.
- "Onsuz da iclaslarda görəcəm" deyə 1:1 girişləri atlamaq.
- 90 gün görünməz olmaq. Dinləmə rejimi yoxa çıxmaq deyil — kiçik PR-ı ship et.
- Öz komandandan kənar stakeholderlərlə görüşməmək. Sonradan divarlara toxunacaqsan.
- Kontekst olmadan başını aşağı salıb işləmək. Kod asan hissədir.
- İlk ay həddən artıq söz vermək. "I'll fix the CI pipeline by month 2" — sonra qaçırmaq.
- Əvvəlki şirkətin yolunun daha yaxşı olduğunu güman etmək. Çox vaxt belə deyil — və ya kontekst fərqlidir.
- 30-60-90 planı yoxdur. Menecerin səni kalibrləmək üçün yol tapa bilmir.
- "Mənim ticket-lərimə aid deyil" deyə komanda ritüallarını atlamaq. Aiddir — kontekst orada yaşayır.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "What would your first 90 days look like?"
- "How do you onboard yourself to a new team?"
- "How do you decide when to start proposing changes?"

Cavab planı:
1. **Faza 1 (1-30 gün):** "Listen mode. I meet every teammate in 1:1, read the top design docs, and ship one tiny PR to get the deploy flow in my hands."
2. **Faza 2 (31-60 gün):** "I take ownership of one medium-sized ticket end-to-end. I start reviewing PRs. I identify 2-3 pain points quietly for later."
3. **Faza 3 (61-90 gün):** "I propose one improvement, write a small RFC, and lead a 2-3 week project. By then I've earned the credit to propose."
4. **Prinsip:** "I resist the urge to criticize early. Code exists for reasons I don't see yet."
5. **Plan artefaktı:** "I share a written 30-60-90 plan with my manager in week one and update it every 30 days."

## Further reading
- "The First 90 Days" by Michael Watkins
- "The Manager's Path" by Camille Fournier
- "Staff Engineer" by Will Larson
- "The Effective Engineer" by Edmond Lau
- Will Larson's blog post: "Productive onboarding at senior+ levels"
