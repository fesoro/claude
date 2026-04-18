# B2 Reading — Software Engineering

Bu fayl B2 səviyyəli, texniki mövzularda oxu mətnlərindən ibarətdir. Hər mətn təxminən **400-600 söz**-dən ibarətdir — Cambridge/IELTS reading paragrafları ilə eyni uzunluq.

### Oxuma strategiyası
1. **Birinci oxuma (skim):** 1-2 dəqiqədə, lüğətə baxmadan — əsas fikir nədir?
2. **İkinci oxuma (detail):** lüğət istifadə edərək, hər sözü anlamağa çalışın
3. **Üçüncü oxuma (language):** yeni ifadələri qeyd edin, tərcümə edin
4. Sualları cavablandırın

---

## Text 1: The Hidden Cost of Microservices

Over the past decade, microservices have become the default answer for how to build scalable backend systems. The promise is compelling: instead of a single, monolithic application that becomes harder to maintain as it grows, you split your system into small, independent services, each owned by a small team. In theory, teams can move faster, deploy independently, and choose the right technology for each problem.

In practice, however, microservices come with a set of hidden costs that are often underestimated. The most obvious one is **operational complexity**. Running a single application is relatively simple — you deploy it, monitor it, and scale it. Running fifty microservices, each with its own deployment pipeline, database, and monitoring, is an entirely different challenge. Companies that adopt microservices often find themselves needing to invest heavily in tooling, observability, and infrastructure before they see any real benefit.

Another cost is **distributed debugging**. In a monolith, if something breaks, you can usually find the problem by looking at a single stack trace. In a microservices system, a user request might touch ten different services, each running in a different container, in a different region. Tracing that request across all of them requires sophisticated tooling and discipline. Without it, engineers spend hours trying to figure out which service actually caused the problem.

**Data consistency** is perhaps the most subtle cost. In a monolithic system, transactions are straightforward — you update several tables atomically, and either everything succeeds or nothing does. Once data is split across multiple services, each with its own database, you lose that guarantee. Suddenly, your team needs to learn about eventual consistency, saga patterns, and distributed transactions — topics that most developers have never had to think about.

Finally, there's the **human cost**. Microservices only work when teams truly own their services end-to-end. If one team is responsible for writing the code but another team handles the infrastructure, the boundaries break down and you end up with the worst of both worlds: the complexity of microservices without the autonomy that justifies it.

None of this means microservices are a bad idea. At sufficient scale — think hundreds of engineers working on the same product — they become almost necessary. But for smaller teams and smaller systems, a well-structured monolith is often the better choice. It's easier to reason about, easier to operate, and allows the team to focus on building features rather than managing infrastructure.

The real lesson from the microservices era is that **architectural decisions have a long shadow**. A choice that feels cutting-edge today can look naive five years later, when you're still paying the complexity tax on a system that didn't need that much complexity in the first place.

---

### Comprehension Questions

1. What is the main argument of the text?
2. Name three hidden costs of microservices mentioned in the text.
3. What does "operational complexity" refer to in this context?
4. Why is data consistency harder in microservices than in a monolith?
5. What does the author suggest for smaller teams?
6. What does "a long shadow" mean in the final paragraph?

### Answers

1. Microservices promise benefits (scalability, team autonomy) but come with significant hidden costs that are often underestimated; for smaller teams, a well-structured monolith is usually a better choice.
2. Any three of: operational complexity, distributed debugging, data consistency, human cost.
3. Running many services (each with its own pipeline, database, monitoring) requires heavy investment in tooling and infrastructure.
4. Data is split across services with their own databases, so you lose the atomic-transaction guarantee; teams must learn eventual consistency, sagas, and distributed transactions.
5. A well-structured monolith — easier to reason about, operate, and allows focus on features.
6. Architectural decisions have long-lasting consequences that may only become visible years later.

### Key Vocabulary

| Ingilis | Azərbaycanca |
|---------|--------------|
| compelling | cəlbedici, inandırıcı |
| underestimated | qiymətləndirilməmiş |
| entirely | tamamilə |
| sophisticated | mürəkkəb, ixtisaslaşmış |
| discipline | nizam, sistemli yanaşma |
| straightforward | birbaşa, sadə |
| atomically | atomic şəkildə (hamısı birlikdə) |
| subtle | incə, gizli |
| autonomy | muxtariyyat, müstəqillik |
| sufficient | kifayət qədər |
| reason about | haqqında düşünmək |
| cutting-edge | ən müasir |
| naive | sadəlövh |
| complexity tax | mürəkkəblik bahası |

---

## Text 2: Remote Work — The New Normal

Five years ago, remote work was seen by many companies as an exception — a benefit reserved for top performers or a necessity during business trips. Today, for most software engineering teams, it has become the default. The shift happened fast, and the reasons go beyond the pandemic that accelerated it.

The most obvious benefit is **talent access**. When a company can hire anywhere in the world, it's no longer limited to the few candidates who happen to live near its office. A startup in Berlin can hire a senior engineer from Buenos Aires; a product company in San Francisco can build teams across Europe and Asia. This has opened up careers for engineers in countries where the local job market used to offer limited opportunities.

For employees, the appeal is equally strong. No commute means two hours of life reclaimed every day. The ability to live where rent is lower or family is closer is a quality-of-life improvement that a salary bump can't match. Parents of young children often describe remote work as the only way they can hold a serious engineering job while still being present for their family.

But remote work is not without its challenges, and many of them are harder than they first appear. **Communication overhead** is higher: conversations that would have taken thirty seconds in an office now require messages, meetings, or both. **Async coordination** — working across time zones where you may not overlap with your teammates for more than a few hours — demands clearer writing, better documentation, and more discipline around process.

Onboarding new engineers is also harder. A new hire in an office absorbs the culture passively — by overhearing conversations, watching how people handle disagreements, and building relationships through daily interactions. Remotely, all of this has to be created intentionally. Companies that don't invest in structured onboarding often end up with new hires who feel lost and disconnected for months.

Perhaps the most underrated challenge is **loneliness**. Engineers who thrive remotely typically have strong self-management skills and active social lives outside of work. Those who don't can find themselves sinking into isolation, which over time affects both wellbeing and performance. Some of the best remote companies have started organising in-person gatherings two or three times a year, not just for productivity reasons, but to keep people connected as humans.

Looking forward, the trend seems to be toward **hybrid models** — where teams meet in person occasionally but do most of their work remotely. It's not perfect, but it combines the access and flexibility of remote work with some of the social benefits of an office. For engineers entering the job market today, remote work isn't a perk anymore. It's a reality they need to learn to navigate.

---

### Comprehension Questions

1. What shift does the author describe at the beginning?
2. How has remote work changed hiring for companies?
3. Give two benefits remote work provides for employees.
4. What is "async coordination" and why is it demanding?
5. Why is onboarding harder remotely?
6. What challenge does the author call "underrated"?
7. What does the author predict about the future of remote work?

### Answers

1. Remote work has moved from being an exception to being the default for most software teams.
2. Companies can now hire from anywhere in the world, removing geographic limits.
3. Any two: no commute (extra time), ability to live where rent is lower / family is closer, better balance for parents.
4. Working across time zones with little overlap — requires clear writing, good docs, disciplined process.
5. New hires can't absorb culture passively (overhearing conversations, daily interactions); it must be created intentionally.
6. Loneliness — remote isolation affects wellbeing and performance over time.
7. Hybrid models — mostly remote with occasional in-person meetings.

### Key Vocabulary

| Ingilis | Azərbaycanca |
|---------|--------------|
| reserved for | ... üçün saxlanmış |
| default | standart, əsas |
| accelerated | sürətləndirdi |
| reclaimed | geri alınmış |
| a salary bump | maaş artımı |
| appear | görünmək |
| overhead | əlavə yük |
| overlap | üst-üstə düşmək |
| absorb | mənimsəmək, özünə çəkmək |
| intentionally | qəsdən, məqsədli şəkildə |
| disconnected | əlaqəsiz |
| underrated | dəyəri az qiymətləndirilmiş |
| thrive | çiçəklənmək, uğurlu olmaq |
| sinking into | batmaq |
| gatherings | toplantılar |
| hybrid models | qarışıq modellər |
| perk | iş imtiyazı |
| navigate | yol tapmaq |

---

## Text 3: What Makes a Good Code Review?

Code review is one of the most valuable practices in modern software engineering, yet most teams do it poorly. The difference between a good review and a bad one is rarely about technical expertise. It's about **intent, clarity, and attitude**.

A good review starts with the right mindset. The reviewer's goal is not to prove that they are smarter than the author, nor to catch every possible mistake. It's to help the team ship **correct, maintainable code** while building shared understanding of the codebase. This framing changes how feedback is delivered. Instead of "this is wrong," a thoughtful reviewer writes "I think this might miss the case where X happens — could you check?" It's a small difference in wording, but a huge difference in how the conversation feels.

The second ingredient is **focus**. Good reviewers prioritize what matters. Not every comment needs to block a merge. Questions about architecture, correctness, or security are high-priority. Preferences about variable names or formatting usually aren't. When everything is criticized equally, the review becomes noise, and authors learn to ignore it. Many teams use prefixes like "nit:" (short for nitpick) to mark low-priority comments, making it easier for the author to tell what's essential from what's optional.

The third ingredient is **timeliness**. A code review that sits in the queue for three days is more damaging than people realize. The author has already context-switched to another task; when the review finally comes back, they need time to re-familiarize themselves with the change before they can respond. Teams that treat reviews as a top priority — reviewing within hours rather than days — ship faster and have better morale.

Good reviewers also **ask questions instead of making assumptions**. "Why did you choose this approach?" is more generative than "This approach is wrong." Often, the author has a reason the reviewer didn't anticipate. And when there isn't a good reason, asking the question helps the author see the issue themselves, which is a far more lasting way to learn.

Perhaps the most common failure is treating code review as a **gatekeeping exercise**. Some reviewers see their role as preventing bad code from reaching production. This is part of the job, but only part. The other part — arguably more important — is **developing teammates**. A reviewer who takes the time to explain why a particular pattern is better, or to suggest a reading resource, invests in the long-term capability of the team. That investment compounds over time.

Finally, code review works best when it's a **two-way conversation**. Authors who respond defensively to feedback, or who dismiss concerns without engaging with them, make reviewers reluctant to speak up in the future. Authors who engage thoughtfully — explaining their reasoning, accepting valid points, pushing back on weak ones — create a dynamic where everyone improves.

Writing good code is hard. Reviewing it well is almost as hard. But teams that invest in doing both well are the ones that build systems their successors will actually want to maintain.

---

### Comprehension Questions

1. According to the author, what is the main goal of a code review?
2. How should feedback ideally be phrased?
3. Why shouldn't every comment block a merge?
4. What is a "nit" and why is it used?
5. Why does a delayed review hurt the author?
6. What is the difference between "gatekeeping" and "developing teammates"?
7. What kind of author response makes reviewers reluctant to give feedback?

### Answers

1. To help the team ship correct, maintainable code while building shared understanding (not to prove who is smarter).
2. As suggestions or questions, not accusations — e.g., "I think this might miss X" rather than "this is wrong."
3. Because when everything is criticized equally, reviews become noise and authors learn to ignore them.
4. A "nit" (short for nitpick) marks low-priority, optional feedback — helps the author tell essential from optional.
5. The author has context-switched to other work and needs time to re-familiarize themselves before responding.
6. Gatekeeping = blocking bad code; developing teammates = explaining reasons and helping others grow. Both matter, but the second compounds over time.
7. Defensive or dismissive responses — makes reviewers reluctant to speak up in future.

### Key Vocabulary

| Ingilis | Azərbaycanca |
|---------|--------------|
| expertise | təcrübə, ixtisas |
| intent | niyyət |
| mindset | düşüncə tərzi |
| maintainable | saxlana bilən, baxıla bilən |
| framing | təqdimetmə, yanaşma |
| nit / nitpick | kiçik qeyd |
| timeliness | vaxtında olma |
| context-switched | fikirlərini başqa işə keçirtmək |
| re-familiarize | yenidən tanış olmaq |
| morale | əhval, ruh yüksəkliyi |
| generative | yaradıcı, inkişaf etdirici |
| anticipate | qabaqcadan görmək |
| gatekeeping | qapı gözləmə, seçim edən rol |
| arguably | mübahisə ilə, demək olar ki |
| compounds over time | zamanla qat-qat artır |
| reluctant | istəməz, tərəddüdlü |
| dismiss | rədd etmək, əhəmiyyət verməmək |
| pushing back | etiraz etmək |
| successors | gələcək nəsil işçiləri |

---

## Məşq — Gist sualları

Hər mətni oxuduqdan **5 dəqiqə sonra**, kitabı bağlamadan bu sualları cavablandırın:

1. **Text 1 (Microservices):** Əgər siz 10 mühəndisli startup-da olsanız, microservices və ya monolith seçərdiniz? Niyə?
2. **Text 2 (Remote Work):** Remote işin 3 əsas çağırışı nələrdir? Sizcə hansı ən çətindir?
3. **Text 3 (Code Review):** Yaxşı reviewer-in 4 xüsusiyyətini sadalayın.

Bu sualları **ingiliscə cavablandırın** (yazılı və ya səsli). Bu həm reading, həm də speaking məşqidir.
