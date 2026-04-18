# Cross-Team Communication

## Niyə vacibdir? (Why it matters)
Senior işin çoxu cross-team-dir. Bir feature-in çıxması üçün product, design, data, DevOps və digər iki mühəndislik komandası lazımdır. Bu sərhədlər arasında ünsiyyət qura bilən mühəndis yalnız öz komandasında danışan mühəndisdən 10 dəfə daha faydalı olur. Şirkətlər senior+ səviyyədə insan tutanda buna qismən komandalar arası koordinasiya vergisini azaltmaq üçün edir.

Senior PHP/Laravel mühəndisi üçün cross-team bacarıqları "bir komandada senior IC"-ni "staff-track mühəndis"-dən ayıran şeydir. Yazdığın kod işin 30%-idir. Qalan 70% doğru insanların nə qurduğun barədə razılaşmasını təmin etməkdir.

## Yanaşma (Core approach)
1. **Dump etmə, tərcümə et.** Product menecerlərə `ORM lazy loading` maraqlı deyil. Onlara "dashboard-da istifadəçilər 2 saniyə əlavə gözləyəcək" maraqlıdır. Texniki dili təsir/vaxt/risk dilinə çevir.
2. **Digər disiplinlərə empatiya.** Dizaynerlərin sənin görmədiyin UX məhdudiyyətləri var. Data komandasının sənin görmədiyin data keyfiyyət problemləri var. Onların sənin bilmədiyin şeyləri bildiyini güman et.
3. **Eng komandaları arasında əvvəl contract.** Kod yazmağa başlamazdan əvvəl API və ya event contract-ını təyin et. Bu həftələr qazandırır.
4. **Default olaraq async-dostu ol.** Hər kəs sənin timezone və ya iclas vaxtında deyil. Yaz.
5. **"Mənim problemim deyil" senior səviyyədə heç vaxt cavab deyil.** Əgər sənin komandanın nəticəsinə və ya istifadəçilərə təsir edirsə, bu sənin problemindir. Yönləndir, buraxma.

## Konkret skript və template (Scripts & templates)

### PM (product manager) ilə danışıq
Texniki narahatlığı tərcümə etmək:
> "If we ship it this week without the cache layer, the dashboard will be slow for users with >1000 orders. That's about 15% of customers. Options: (1) ship now, fix later — 2 weeks of slower dashboard for power users; (2) add cache — 3 extra days; (3) ship behind feature flag for small accounts only. Which fits your goals best?"

Scope-a etiraz etmək:
> "I want to make sure we're aligned. The sprint has A, B, C. Adding D means one of them slips. Which do you want to deprioritize?"

### Dizayner ilə danışıq
Onların sənətinə hörmət göstərmək:
> "I have a technical constraint I want to share before you go deeper in the design. We can't do server-side rendering on this page — it would need a rewrite. Here's what's easy, medium, and hard from eng side. Want to sync for 15 min?"

Dizayn üzərində feedback vermək:
> "I love the empty state. One small concern from eng side: the animation would cost us extra bundle size. Could we use a static illustration instead? Happy to discuss."

### Data komandası ilə danışıq
Data tələb etmək:
> "I need to answer: 'What percentage of users trigger X event?' Ideally bucketed by plan. Timeline: I need a rough number by Thursday, doesn't need to be perfect. Can you help, or should I query a read replica myself?"

### Başqa Mühəndislik komandası ilə danışıq (contract-first)
> "Before we build, I'd like to agree on the API contract. Here's a draft spec: [link]. Key fields: [X, Y, Z]. Error modes: [timeout, 404, 500]. Can we review this async by Friday and sync for 30 min Monday if needed? That way neither team is blocked on the other during the build."

### Komandalar arası bug eskalasiyası
> "We're seeing timeouts from the payments API since yesterday's deploy. Our users are affected (about 200 failed checkouts). Can someone from payments take a look today? I'll post logs in this thread."

### Başqa komandadan "mənim problemim deyil" cavabı aldıqda
> "I hear you — this might not be owned by your team directly. Can you help me find the right owner? I don't want to keep bouncing this around while users are affected."

## Safe phrases for B1 English speakers
- "Help me understand the constraint from your side." — empatiyanı açmaq
- "From the engineering side, this is hard because..." — texniki narahatlığı çərçivəyə salmaq
- "What does success look like for you?" — məqsədlər üzərində razılaşmaq
- "Let me translate that to what it means for users." — təsir çərçivəsi
- "Can we agree on the contract before we start coding?" — contract-first çərçivəsi
- "Who is the right owner for this?" — yönləndirmə
- "I want to make sure we're aligned." — ümumi anlayışı yoxlamaq
- "That's a good question — let me check and come back." — vaxt qazanmaq
- "Here are three options with trade-offs." — seçim təqdim etmək
- "Can we sync for 15 minutes?" — qısa iclas istəyi
- "I'll write up a summary after this call." — sənədləşməyə söz vermək
- "Not my team directly, but let me help you find the owner." — buraxmamaq
- "What's blocking you?" — başqalarının qarşısındakı blokları açmaq
- "This is a judgment call, I defer to you on priority." — prioritet səlahiyyətini geri vermək
- "Let's agree on the deadline, then I'll plan around it." — söz vermək

## Common mistakes / anti-patterns
- Mühəndis olmayanlarla jargon istifadə etmək. "We need to refactor the ORM to avoid N+1." Yox.
- Alternativ vermədən "yox" demək. Həmişə ən azı bir yol təklif et.
- Pis niyyət güman etmək. Dizaynerlər "çətinlik törətmir". Onların sənin görmədiyin konteksti var.
- Dəfələrlə səhv adama yönləndirmək. Doğru sahibi tapmaq üçün araşdır.
- İclasdan sonra heç nə yazmamaq. Şifahi razılaşmalar yox olur.
- İclaslar daha məhsuldar göründüyü üçün async seçimini atlamaq.
- "Qismən başqa komanda sahibidir" deyə ticket-ləri buraxmaq.
- Mühəndislik maya dəyərini izah etmədən deadline-ları dartmaq.
- Qeyri-çevik olmaq. "We can't do that" — həqiqətdə demək istədiyin "that takes 2 weeks" olanda.
- Məşğul insanlara uzun strukturu olmayan Slack mesajları göndərmək. Həmişə struktur.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "Tell me about a time you worked with a non-technical stakeholder."
- "How do you handle a disagreement with a PM about scope?"
- "Describe a cross-team project you led."

Cavab planı:
1. **Kontekst:** "We were building a notification system. It touched product, design, platform team, and data."
2. **Hərəkət — tərcümə:** "I wrote a 1-page summary for PM with impact, timeline, and risks. For designers, I explained what was cheap vs expensive. For data, I defined the events we'd emit with a schema."
3. **Hərəkət — contract-first:** "Before any team wrote code, we agreed on the event contract in a 30-minute meeting."
4. **Nəticə:** "We shipped 2 weeks earlier than planned because no team was blocked mid-build."
5. **Fikirləşmə:** "The biggest lesson: the hard part was not the code, it was getting four teams to agree on the same contract."

## Further reading
- "Staff Engineer" by Will Larson (sections on cross-team influence)
- "Team Topologies" by Matthew Skelton and Manuel Pais
- "An Elegant Puzzle" by Will Larson
- "Crucial Conversations" by Patterson, Grenny, McMillan, Switzler
- "The Art of Gathering" by Priya Parker (on running cross-functional meetings)
