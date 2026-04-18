# Describing Your Work — İşini Necə İzah Etmək

## Bu Fayl Haqqında

Müsahibədə, small talk-da, networking-də sənə soruşurlar:
- "What do you do?"
- "Tell me about your current project."
- "What's your typical day like?"
- "What technologies do you work with?"

Azərbaycan danışanların çoxu bu sualları **yarımçıq və ya həddən artıq texniki** cavablandırır. Bu fayl sənə **aydın, maraqlı, strukturlu** cavab verməyi öyrədir.

---

## 1. "What do you do?" — Əsas Sual

### Hədəflər:
- 30-45 saniyə
- Aydın, texniki jargon olmadan (xüsusilə non-tech insanlar üçün)
- Mənalı kontekst ver

### Strukturlar:

#### A: Əsas (A2-B1)
> "I'm a [rol]. I work at [şirkət], where I [əsas iş]."

Nümunə: "I'm a backend developer. I work at a fintech company, where I build APIs for mobile payments."

#### B: Ətraflı (B1-B2)
> "I'm a [rol] with [X] years of experience. Currently, I work at [şirkət] on [konkret məhsul/sahə]. My main focus is [konkret iş]."

Nümunə: "I'm a backend developer with about 4 years of experience. Currently, I work at [Company] on our payments platform. My main focus is building APIs that handle transactions reliably at scale."

#### C: Hekayə şəklində (B2+)
> "I work in tech — more specifically, I'm a backend engineer. Most of my day-to-day is spent building the systems that handle [konkret şey]. It's the kind of work where you don't see it, but when it breaks, everyone notices."

---

## 2. Tech Adamı ilə vs Qeyri-Tech Adamı ilə

### ✅ Tech adamı ilə (konkret, texniki)
> "I work on a payment processing API built in Python. It's a FastAPI service backed by PostgreSQL, handling about 5K transactions per second at peak. I mostly deal with performance and data consistency."

### ✅ Qeyri-tech adamı ilə (analogiyalar)
> "I write the code that makes apps work behind the scenes. Imagine when you pay for something in an app — someone has to write the code that makes sure your money actually goes to the right place. That's the kind of thing I do."

### ⚠️ Həddindən artıq texniki (qaçın non-tech-lə):
❌ "I develop microservices using FastAPI, Kafka, and Kubernetes with a focus on CQRS patterns."  
→ Onlar başa düşmür. Sadə saxla.

---

## 3. "Tell me about your current project."

### Struktur (90 saniyə):
1. **Nə:** Layihə nədir, nə üçündür? (20 san)
2. **Sənin rolun:** Nə edirsən konkret? (30 san)
3. **Texnologiya:** Hansı stack? (20 san)
4. **Nailiyyət/çətinlik:** Bir vacib məqam (20 san)

### Nümunə:
> **(Nə)** "Right now, I'm working on our new mobile payment platform. It's the main product of the company — used by about 2 million customers monthly.  
>   
> **(Rol)** I'm on the backend team of five. My specific area is transaction processing — making sure payments are processed correctly, especially in edge cases like network failures or duplicate requests. I also handle integrations with two banking partners.  
>   
> **(Tech stack)** We use Python with FastAPI for the API layer, PostgreSQL for transactional data, Redis for caching, and Kafka for async event processing. Deployed on AWS with Kubernetes.  
>   
> **(Məqam)** The most interesting challenge was designing idempotent retries — making sure a failed transaction doesn't result in a double charge. I wrote an idempotency key system that handles 99.99% of edge cases we've seen. Took about two months to get right, but it was a great learning experience."

### Açar ifadələr:
- "Right now, I'm working on ___."
- "It's used by ___ customers / companies."
- "I'm on the [team] team of [size]."
- "My specific area is ___."
- "The tech stack includes ___."
- "One interesting challenge was ___."

---

## 4. "What technologies do you work with?"

### ❌ Sadəcə siyahı (zəif):
"Python, Django, PostgreSQL, Redis, Docker, Kubernetes, AWS..."

### ✅ Strukturlu, kontekstli (güclü):
> "My day-to-day stack is Python and Django for the backend, with PostgreSQL as the main database and Redis for caching. For infrastructure, we use Docker and Kubernetes on AWS — I'm responsible for our service's deployment configuration.  
>   
> On the side, I've been exploring Go for some performance-critical services. And for observability, we use Datadog for metrics and Sentry for error tracking."

### Açar ifadələr:
- "My day-to-day stack is ___."
- "For [specific purpose], we use ___."
- "I'm also familiar with ___."
- "I've been exploring ___ lately."
- "We use ___ for [metric / logging / monitoring]."

### Səviyyələri ayır:
- **Expert:** "I'm very comfortable with ___."
- **Proficient:** "I work with ___ daily."
- **Intermediate:** "I've used ___ in projects."
- **Learning:** "I'm currently exploring ___."

---

## 5. "What's your typical day like?"

### Struktur — Zaman sırasına görə:

> "My day usually starts around 9 am. First thing, I check Slack for any urgent issues and skim my email — just to make sure nothing's blocking anyone. Then we have our **daily standup** at 9:30 — 15-minute team sync.  
>   
> After that, I dive into my main focus work until lunch. I try to protect those 2 hours for deep coding or design work — no meetings if I can avoid it.  
>   
> Afternoon is more mixed: usually a couple of meetings — either design reviews, one-on-ones, or cross-team syncs. I also do code reviews, which take maybe an hour total throughout the day.  
>   
> I usually wrap up around 6, after writing a quick update in our team channel about what I finished and what's next."

### Açar ifadələr:
- "My day usually starts around ___."
- "First thing, I ___."
- "We have our [meeting] at ___."
- "I try to protect time for ___."
- "In the afternoon, ___."
- "I wrap up around ___ by ___."

### Variantlar:
- **"A typical day"** = adi gün
- **"On a good day"** = yaxşı gün
- **"On a deploy day"** = yaylım günü
- **"When I'm on call"** = növbətçi olduğum vaxt

---

## 6. "What do you enjoy about your work?"

### Variantlar:

**Problem solving:**
> "I really enjoy the problem-solving aspect. Especially when I'm debugging something complex — there's that moment when a system that seemed broken suddenly makes sense. It feels like solving a puzzle."

**Building:**
> "I enjoy the act of building. Going from a blank file to a working feature — I find it really satisfying. Especially when people actually use what I built."

**Learning:**
> "What I like most is the constant learning. Tech changes so fast that I never feel like I know everything. There's always a new tool, a new pattern, a better way to do something."

**People:**
> "Honestly, the people. I've been fortunate to work with really smart, kind colleagues. I learn as much from them as from any book or course."

**Impact:**
> "I enjoy seeing the impact of my work. When I ship something that makes our system faster or helps a user avoid an error — that feels good."

---

## 7. "What don't you enjoy?" (Soft kritika)

⚠️ Qayda: Həqiqi de, amma **mənfi** ton yoxdur.

### Nümunələr:

**Meetings:**
> "Honestly, I find long meetings draining. I prefer async written updates where possible. In my current team, we've worked on reducing unnecessary meetings, which helped."

**Ambiguity:**
> "I'm not a huge fan of unclear requirements. I've learned to ask a lot of questions upfront, though, so it's less of an issue now."

**Context switching:**
> "Too much context switching. When I have to jump between 5 different topics in a day, my quality drops. I try to batch similar work together when I can."

**Bad documentation:**
> "Working with poorly documented legacy code is tough. But it's also an opportunity — I often improve the docs as I go."

---

## 8. Hazırki Rol Haqqında Daha Detallı

### "Walk me through your role."

> "Sure. My title is Senior Backend Engineer. I'm part of a team of 6 — four engineers, one product manager, one designer. We own the checkout and payments part of our product.  
>   
> My day-to-day varies. Maybe 50% coding — features and bug fixes. Around 20% on architecture and design work — figuring out how new features should be built. Another 20% on code reviews and mentoring. The rest is meetings and syncing with other teams.  
>   
> What I'm responsible for specifically: the health of our payment service, which processes about $10M in transactions per month. If something breaks there, I'm the person most people look at."

### Açar ifadələr:
- "My title is ___."
- "I'm part of a team of ___."
- "We own ___."
- "My day-to-day varies — about [%] coding, [%] design, [%] mentoring..."
- "What I'm responsible for specifically is ___."

---

## 9. "What problems are you solving?"

### Nümunə (Backend):
> "The main problems I work on are around **reliability at scale**. Our traffic has grown 5x in the last year, and the systems that worked fine at 10K requests per second are starting to show cracks at 50K. I'm focused on identifying those cracks before they become outages — things like adding caching where it helps, tuning database queries, and introducing better failure handling."

### Nümunə (Frontend):
> "I work on a dashboard used by product managers across the company. The main challenges are **performance and usability** — the data is complex, and if the dashboard takes more than 2 seconds to load, people stop using it. So I spend a lot of time thinking about data fetching strategies, rendering optimization, and making sure the UI feels fast."

### Nümunə (DevOps):
> "My focus is on **developer experience**. The faster our engineers can deploy, test, and iterate, the faster the whole company moves. Right now I'm working on reducing our deployment time — it used to take 40 minutes, now it's down to 5, and I want to hit 2 minutes by end of quarter."

---

## 10. "Tell me about the team."

### Yaxşı nümunə:
> "Our team is small — four engineers plus a PM and designer. We work fully remote, but we overlap a few hours a day.  
>   
> The culture is pretty direct. We give each other honest feedback in code reviews, but always with respect. We keep standups short — 15 minutes max. Important decisions go into written docs, not chat, so we have a record.  
>   
> I'd describe the team as senior — we're mostly mid to senior engineers who've worked on similar products before. The downside is we're stretched thin; the upside is there's very little hand-holding needed."

### Açar ifadələr:
- "Our team is ___ — [size + composition]."
- "We work [fully remote / hybrid / in-office]."
- "The culture is ___."
- "We [how we communicate / make decisions]."
- "I'd describe the team as ___."

---

## 11. Layihənin Təsirini İzah Etmək

### Formula:
> "This matters because ___. The impact is ___."

### Nümunələr:
- "This matters because downtime costs us $X per minute. My work reduced incidents by Y%, which translates to significant savings."
- "This project directly enabled the team to ship features 3x faster, which let us capture two big customer deals last quarter."
- "Without this migration, we couldn't have scaled past 50K users. Now we're projecting 500K."

### Rəqəmlərlə gücləndir:
- "Performance: cut response time by 60%"
- "Reliability: improved uptime from 99.5% to 99.95%"
- "Scale: went from 1K to 50K RPS"
- "Cost: reduced infra bill by 40%"
- "Team: onboarding time went from 2 weeks to 3 days"

---

## 12. Sualları Kibar Qarşılıqlı Soruşmaq

Bir tərəfli monoloq olmamalıdır. Sual qaytarma bacarığı da vacibdir.

### Nümunələr:
- "That's what I do. What about you?"
- "Enough about me — what does your role look like?"
- "What's your team working on these days?"
- "How long have you been at [company]?"
- "What drew you to this industry?"

---

## 13. Təkrar Yoxlama — Özünü Səsləndir

### 60-saniyəlik "Elevator Pitch"

Bu şablonu öz həyatına uyğunlaşdır:

> "I'm **[rol]** with **[X] years** of experience. I specialize in **[sahə]** — most recently I've been working on **[konkret layihə]**.  
>   
> Before this, I was at **[əvvəlki şirkət]**, where I **[əsas nailiyyət]**.  
>   
> What I enjoy most is **[nə maraqlıdır]**. Looking ahead, I'd like to **[hansı istiqamət]**."

### Öz cavabını yaz:
```
Rol: ___
Təcrübə: ___ il
İxtisas: ___
Hazırki layihə: ___
Əvvəlki nailiyyət: ___
Ləzzət: ___
Gələcək hədəf: ___
```

---

## 14. Səhvlər — Etmə

### ❌ Çox qısa
"I'm a developer. I write code."  
→ Maraqsız. Kontekst ver.

### ❌ Çox uzun
3 dəqiqəlik monoloq.  
→ Maksimum 60-90 saniyə. Qarşı tərəfi dinlətsin.

### ❌ Çox texniki
"I implement microservices with CQRS pattern, Kafka for event sourcing..."  
→ Kimsə başa düşmürsə, sadə saxla.

### ❌ Komanda haqqında şikayət
"My team is dysfunctional and the boss is terrible."  
→ Heç vaxt. Neytral və ya müsbət.

### ❌ "I'm just a..." (alçaqgönüllülük)
"I'm just a junior, I don't do anything important."  
→ Öz dəyərini aşağı salma. Rolunu aydın, təvazökar bildir.

### ❌ Fikir təfərrüatı olmadan
"I work with computers."  
→ 2020-dir, hamı "computers"-lə işləyir. Konkret ol.

---

## 15. Praktik Məşq

### Hər gün 1 versiya hazırla:

**Gün 1:** Yeni tanışa 30 saniyəlik təqdim (sadə)  
**Gün 2:** Müsahibədə 90 saniyəlik təqdim (STAR)  
**Gün 3:** Non-tech qohumu üçün 45 saniyə (analogiyalar)  
**Gün 4:** Texniki CTO-ya 2 dəqiqə (detallı)  
**Gün 5:** Konfransda networking — 60 saniyə (engaging)

### Qeydə al, özün dinlə:
- 5+ dəfə "um", "you know", "like"? → Azalt.
- Qarışıq yerdə dayandın? → Tenses yoxla.
- Cansıxıcı səsləndin? → Emosiya əlavə et.
- Rəqəm yoxdur? → 1-2 konkret rəqəm əlavə et.

---

## 16. Əlavə İfadələr — Söz Bankı

### Layihə təsviri:
- "It's a platform that ___."
- "The goal is to ___."
- "It helps [users] to ___."
- "We're trying to solve the problem of ___."

### Rol təsviri:
- "My main responsibility is ___."
- "I'm primarily focused on ___."
- "Day to day, I handle ___."
- "I'm the person who ___."

### Nailiyyət:
- "I'm particularly proud of ___."
- "The highlight for me was ___."
- "What worked well was ___."

### Çətinlik:
- "One of the toughest parts was ___."
- "The tricky part was ___."
- "We struggled with ___."

### Öyrənmək:
- "I learned a lot about ___."
- "It taught me ___."
- "The biggest takeaway was ___."

---

## Əlaqəli Fayllar

- [Top 50 Interview Questions](../../exercises/speaking/top-50-interview-questions.md)
- [Self-Introduction](../../exercises/speaking/self-introduction.md)
- [Technical Discussion Phrases](technical-discussion-phrases.md)
- [System Design Discussion](system-design-discussion.md)
- [Tech Deep Dive Vocabulary](../../vocabulary/by-topic/technology/tech-deep-dive.md)
- [Storytelling](storytelling.md)
