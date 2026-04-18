# Mock Interview — Full Transcripts (B1/B2)

Bu fayl tam müsahibə transkriptlərindən ibarətdir. Dinləmə bacarığınızı inkişaf etdirmək üçün:

1. **Əvvəlcə** — mətni oxumadan, sadəcə Text-to-Speech və ya oxuyan birinin köməyi ilə dinləyin
2. **İkinci dəfə** — transkriptlə birlikdə dinləyin
3. **Üçüncü dəfə** — "shadowing" (arxasınca təkrar) metodu ilə oxuyun
4. Sonda sualları cavablandırın

---

## Mock Interview 1 — Screening Call (B1)

**Context:** Bir recruiter (işə cəlb edən) ilk telefon müsahibəsi aparır. Müddəti: təxminən 20 dəqiqə. Bu müsahibədə adətən ümumi suallar verilir.

### Transcript

> **Recruiter:** Hi, thanks for joining the call today. Can you hear me okay?
>
> **Candidate:** Yes, I can hear you clearly. Thank you for the invitation.
>
> **Recruiter:** Great. So, just to kick things off, could you tell me a bit about yourself and your background?
>
> **Candidate:** Sure. I'm a backend developer with about four years of experience. I've mostly worked with Node.js and Python, and I've built APIs for e-commerce and fintech products. Most recently, I've been working at a startup where I lead a small team of three developers. We're building a payment processing platform that handles around fifty thousand transactions per day.
>
> **Recruiter:** That sounds interesting. What made you apply for this role?
>
> **Candidate:** A few reasons, actually. First, I've been following your company for a while and I really like the product. Second, I'm looking for a role where I can have more technical impact on architecture decisions — and from the job description, it seems like this position offers that. And finally, I'd like to work in a fully remote setup, which fits my current lifestyle.
>
> **Recruiter:** That's good to hear. Can you walk me through your current tech stack?
>
> **Candidate:** Of course. On the backend, we use Node.js with Express and TypeScript. Our main database is PostgreSQL, and we use Redis for caching. For message queues, we rely on RabbitMQ. Everything runs on AWS — mostly ECS for containers and RDS for the database. We also use GitHub Actions for our CI/CD pipeline.
>
> **Recruiter:** Perfect. And what would you say is your strongest skill?
>
> **Candidate:** I'd say it's system design. I really enjoy thinking about how different components fit together, especially around scalability and data consistency. I've also become pretty comfortable with debugging production issues, which I think comes from experience rather than theory.
>
> **Recruiter:** Great. Any questions for me at this stage?
>
> **Candidate:** Yes, a couple. What does the interview process look like from here? And is there anything specific I should prepare for the technical round?
>
> **Recruiter:** Good questions. After this call, there'll be a technical interview with our lead engineer — it usually involves some coding and system design. Then a final round with the hiring manager and a team member. For the technical round, I'd recommend brushing up on distributed systems and having a project you can talk through in depth.
>
> **Candidate:** That's really helpful, thank you. I'll prepare accordingly.
>
> **Recruiter:** Perfect. We'll be in touch within a couple of days. Have a great rest of your day.
>
> **Candidate:** You too, thanks again.

### Comprehension Questions

1. How many years of experience does the candidate have?
2. What database does the candidate currently use?
3. Give two reasons why the candidate applied for the role.
4. What are the next steps in the interview process?
5. What should the candidate prepare for the technical round?

### Answers

1. About four years.
2. PostgreSQL (with Redis for caching).
3. Any two: likes the product / wants more impact on architecture / wants fully remote work.
4. Technical interview with lead engineer (coding + system design), then final round with hiring manager and team member.
5. Distributed systems and a project to talk through in depth.

### Key Phrases to Learn

| Phrase | Azerbaijani |
|--------|-------------|
| "just to kick things off" | başlamaq üçün |
| "walk me through" | ardıcıl izah etmək |
| "brush up on" | təkrar etmək, canlandırmaq |
| "in depth" | dərindən |
| "the role" | vəzifə |
| "fits my current lifestyle" | həyat tərzimə uyğundur |

---

## Mock Interview 2 — Technical Interview (B2)

**Context:** Lead Engineer texniki suallar verir. Müddət: təxminən 45 dəqiqə.

### Transcript

> **Interviewer:** Alright, let's jump into the technical part. I'd like to start with a system design question — something fairly open-ended. Imagine you need to design a URL shortener, like bit.ly. How would you approach it?
>
> **Candidate:** Good question. Before I dive in, can I ask a few clarifying questions?
>
> **Interviewer:** Please do.
>
> **Candidate:** First, what kind of scale are we talking about — how many URLs per day? Second, do we need custom aliases, or is random generation fine? And third, do we need analytics like click tracking?
>
> **Interviewer:** Let's say around a hundred million URLs per day. Custom aliases are optional but supported. And yes, we'd want basic click analytics.
>
> **Candidate:** Okay, that helps. So at that scale, we're looking at roughly a thousand writes per second on average, with probably ten times that in reads — let's say ten thousand reads per second. That means read-heavy, so caching will be important.
>
> For the core service, I'd have a URL service that handles creation and redirection. For generating short codes, I'd use base-62 encoding — that gives us around fifty-six billion unique codes with just six characters, which is more than enough. For storage, I'd go with a key-value store like DynamoDB or Cassandra, because the access pattern is simple — look up by key — and these scale horizontally really well.
>
> **Interviewer:** Why not a relational database?
>
> **Candidate:** Mainly because we don't need joins or complex queries. At a hundred million writes per day, a relational database would work, but scaling it horizontally is much harder. With a key-value store, sharding is built in, which saves us a lot of operational complexity down the line.
>
> **Interviewer:** Fair point. What about the analytics part?
>
> **Candidate:** For click tracking, I wouldn't write to the main database on every click — that would kill performance. Instead, I'd push events to a message queue like Kafka, and have a separate analytics pipeline that processes them asynchronously. That way, the redirect stays fast, and we can aggregate clicks in something like ClickHouse or BigQuery.
>
> **Interviewer:** Nice. What about caching?
>
> **Candidate:** Since reads dominate, I'd put a Redis cache in front of the database. Popular URLs would sit in memory, and we'd get most reads served directly from cache. Cache invalidation isn't really an issue here because short URLs rarely change — once created, they basically never update.
>
> **Interviewer:** Good. One last thing — how would you handle a malicious user who creates billions of short links?
>
> **Candidate:** A few layers. Rate limiting per IP and per user account, so no one can hammer the service. Captchas for suspicious patterns. And on the business side, I'd probably require authentication after a certain number of creations.
>
> **Interviewer:** Solid answer. Let's move on to coding.

### Comprehension Questions

1. What scale did the interviewer specify?
2. Why did the candidate choose a key-value store over a relational database?
3. How does the candidate handle click tracking without slowing down redirects?
4. What is the main role of Redis in this design?
5. Name two ways to prevent abuse.

### Answers

1. 100 million URLs per day, with roughly 10,000 reads per second.
2. No need for joins/complex queries; key-value stores scale horizontally more easily; sharding is built in.
3. Pushes click events to a message queue (Kafka) and processes them asynchronously.
4. Caching popular URLs in memory to reduce database load (reads dominate).
5. Any two: rate limiting per IP/user, captchas for suspicious patterns, authentication after N creations.

### Key Phrases to Learn

| Phrase | Azerbaijani |
|--------|-------------|
| "jump into" | dərhal başlamaq |
| "open-ended" | açıq-uclu (konkret cavabı olmayan) |
| "dive in" | dərinə getmək |
| "read-heavy" | daha çox oxumaya yönəlmiş |
| "scale horizontally" | horizontal miqyaslanmaq |
| "kill performance" | performansı çox azaltmaq |
| "down the line" | gələcəkdə |
| "sit in memory" | yaddaşda saxlanmaq |
| "hammer the service" | xidmətə həddindən çox sorğu göndərmək |
| "solid answer" | yaxşı cavab |

---

## Mock Interview 3 — Behavioral Round (B2)

**Context:** Hiring Manager davranış (behavioral) sualları verir. Müddət: təxminən 30 dəqiqə.

### Transcript

> **Manager:** So, tell me about a time when you disagreed with a technical decision your team was making. How did you handle it?
>
> **Candidate:** Sure. About a year ago, my team was planning to rewrite our main service in Go. The argument was mostly around performance. I actually disagreed — I thought we were solving the wrong problem. Our performance issues weren't really language-related; they were architectural, mostly around how we were querying the database.
>
> **Manager:** How did you approach the conversation?
>
> **Candidate:** I didn't want to just say "no" without evidence. So I spent a weekend profiling the existing service and documented exactly where time was being spent. It turned out that around seventy percent of latency came from a few inefficient queries. I put together a short document with the findings and shared it in our tech review meeting.
>
> **Manager:** And how did the team react?
>
> **Candidate:** Mixed, at first. A couple of people were pretty attached to the idea of rewriting in Go — honestly, I think partly because it sounded exciting. But when we went through the numbers, the case for optimising first became hard to argue with. We agreed to fix the queries and reassess in three months. After the fixes, latency dropped by about sixty percent, and the rewrite conversation basically faded away.
>
> **Manager:** Looking back, what would you do differently?
>
> **Candidate:** Honestly, I think I could have raised the concern earlier. I waited until the rewrite was already being planned, which made pushing back harder because people had started to mentally commit. If I had raised the architectural question two weeks earlier, the conversation would have been much smoother.
>
> **Manager:** That's a really good reflection. Let me ask another one — can you tell me about a time you had to give difficult feedback to someone on your team?
>
> **Candidate:** Yes. I had a junior developer who was technically very strong, but he wasn't communicating well in code reviews. His comments were sometimes blunt, and a couple of people had mentioned it made them uncomfortable.
>
> **Manager:** How did you handle it?
>
> **Candidate:** I set up a one-on-one with him and framed it around impact rather than blame. I said something like, "Your feedback is almost always technically correct, but the way it lands sometimes makes it harder for the team to receive it." I gave him two or three specific examples — not to make him defensive, but so it felt concrete. Then we talked about softer ways to phrase the same feedback, like asking questions instead of making statements.
>
> **Manager:** How did he respond?
>
> **Candidate:** He was actually pretty grateful. He said he hadn't realised how his comments were coming across. Over the next few weeks, his code review style shifted noticeably. A few months later, one of the people who had originally complained came up to me and mentioned that they were now enjoying his reviews.
>
> **Manager:** That's a great outcome. Thanks for sharing.

### Comprehension Questions

1. What was the original team decision the candidate disagreed with?
2. How did the candidate support their disagreement?
3. What was the actual source of the performance problem?
4. What lesson did the candidate take from the experience?
5. How did the candidate frame the difficult feedback conversation?

### Answers

1. Rewriting the main service in Go.
2. Profiled the existing service over a weekend and documented where time was being spent; ~70% of latency came from inefficient queries.
3. Architectural issues — specifically inefficient database queries, not the language.
4. Raise concerns earlier, before a decision has momentum / before people mentally commit.
5. Around impact rather than blame; gave specific examples; discussed how to phrase feedback differently.

### Key Phrases to Learn

| Phrase | Azerbaijani |
|--------|-------------|
| "the argument was around" | əsas arqument ... idi |
| "solving the wrong problem" | səhv problemi həll etmək |
| "attached to the idea" | fikrə bağlı |
| "hard to argue with" | etiraz etmək çətin |
| "faded away" | yavaş-yavaş yox oldu |
| "push back" | etiraz etmək, geri çəkmək |
| "mentally commit" | fikrən qərar vermək |
| "one-on-one" | tək-tək görüş |
| "frame around" | müəyyən baxışdan təqdim etmək |
| "how it lands" | necə qəbul olunur |
| "come across" | təəssürat yaratmaq |

---

## Dinləmə Strategiyaları

Bu transkriptləri dinləyərkən:

### Birinci dinləmə — əsas məna
- Hər dialoqun əsas mövzusu nədir?
- Sual verənin tonu necədir — formal yoxsa yarımformal?
- Cavab verən tərəddüd edir, ya əmindir?

### İkinci dinləmə — detallar
- Rəqəmlərə diqqət yetir (100M, 70%, 4 years və s.)
- Nümunələrə diqqət yetir — konkret texnologiyalar, alətlər
- "Filler" sözləri qeyd et: "actually", "honestly", "basically", "I'd say"

### Üçüncü dinləmə — dil strukturu
- Keçid sözləri: "so", "alright", "let's move on"
- Clarifying question strukturu: "Can I ask...?", "Just to clarify..."
- Həmfikir olma/olmama ifadələri

---

## Shadowing Məşqi

Transkripti oxuyan zaman danışanın arxasınca təxminən 1-2 saniyə gecikmə ilə təkrar edin. Bu, tələffüzünüzü, ritminizi və intonasiyanızı təkmilləşdirəcək.

Başlanğıc üçün 30 saniyəlik bir hissə seçin və 5-10 dəfə təkrar edin. Sonra növbəti hissəyə keçin.
