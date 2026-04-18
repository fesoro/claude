# Behavioral Interview Q&A — STAR Method

Behavioral interview sualları keçmiş təcrübələriniz haqqındadır. Şirkətlər inanır ki, keçmişdə necə hərəkət etmisinizsə, gələcəkdə də eynisini edəcəksiniz. STAR metodu ilə strukturlu cavab verin.

---

## STAR Xatırlatma

| Hərif | Məna | Nə deməli |
|-------|------|-----------|
| **S** — Situation | Vəziyyət | Konteksti izah edin (harada, nə vaxt, nə baş verirdi) |
| **T** — Task | Tapşırıq | Sizin məsuliyyətiniz nə idi |
| **A** — Action | Hərəkət | Siz konkret olaraq nə etdiniz |
| **R** — Result | Nəticə | Nə nəticə əldə olundu (mümkünsə rəqəmlərlə) |

---

## 1. Teamwork (Komanda İşi)

### Sual: "Tell me about a time when you worked effectively as part of a team."

**STAR Cavab Nümunəsi:**

> **S:** "In my previous role, our team was tasked with building a new payment feature for our mobile app. There were five of us — two backend developers, two frontend developers, and a designer."
>
> **T:** "I was responsible for the backend API, but we also needed to make sure all the components worked together smoothly."
>
> **A:** "I organised daily sync meetings where each person shared their progress and blockers. When the frontend team had trouble integrating with my API, I wrote detailed documentation and even pair-programmed with them for a few hours. I also created a shared testing environment so everyone could test the full flow."
>
> **R:** "We delivered the feature two days ahead of schedule. The client was impressed, and the feature had zero critical bugs in the first month after launch."

### Öz cavabınız üçün suallar:
- Komandada hansı layihə üzərində işləmisiniz?
- Sizin rolunuz nə idi?
- Komandanı necə dəstəklədiniz?
- Nəticə nə oldu?

---

## 2. Conflict Resolution (Münaqişə Həlli)

### Sual: "Describe a time when you had a disagreement with a colleague. How did you handle it?"

**STAR Cavab Nümunəsi:**

> **S:** "About a year ago, I was working on a project with another developer. We needed to decide on the database architecture for a new feature."
>
> **T:** "We had completely different opinions — I wanted to use a NoSQL database for flexibility, and he preferred a relational database for data integrity."
>
> **A:** "Instead of arguing, I suggested we each prepare a short comparison document — listing pros, cons, and use cases for each approach. We then presented our findings to the tech lead and discussed them objectively. I made sure to actively listen to his points and acknowledge the valid ones."
>
> **R:** "We ended up going with the relational database, which was his suggestion. It turned out to be the right choice for our use case. More importantly, we maintained a great working relationship and he actually thanked me for keeping the discussion professional."

---

## 3. Problem Solving (Problemin Həlli)

### Sual: "Tell me about a difficult technical problem you solved."

**STAR Cavab Nümunəsi:**

> **S:** "Our application started experiencing intermittent slowdowns during peak hours. Users were complaining about response times of over ten seconds."
>
> **T:** "As the most senior backend developer on the team, it was my responsibility to investigate and fix the issue."
>
> **A:** "First, I analysed the server logs and identified that the database queries were the bottleneck. I used a profiling tool and discovered that one particular query was running without an index, causing a full table scan on a table with over two million rows. I added the appropriate index and also implemented caching for frequently requested data."
>
> **R:** "Response times dropped from ten seconds to under one second. The number of user complaints about performance went down by ninety percent. I also documented the issue and the fix so the team could avoid similar problems in the future."

---

## 4. Tight Deadline (Sıx Vaxt Çərçivəsi)

### Sual: "Can you give an example of when you had to work under a tight deadline?"

**STAR Cavab Nümunəsi:**

> **S:** "Last year, our biggest client moved their product launch date forward by three weeks. We still had several features in development."
>
> **T:** "I needed to reprioritise my tasks and make sure the most important features were delivered on time."
>
> **A:** "I sat down with the product manager and we identified the three must-have features versus the nice-to-have ones. I focused exclusively on the critical features, communicated clearly with the team about the updated priorities, and suggested we do daily standups instead of weekly check-ins to catch any issues early. I also said no to a few non-urgent requests that came in during that period."
>
> **R:** "We delivered all three critical features on time. The client was happy with the launch, and we added the remaining features in the following sprint."

---

## 5. Mistake / Failure (Səhv / Uğursuzluq)

### Sual: "Tell me about a mistake you made at work. What did you learn from it?"

**STAR Cavab Nümunəsi:**

> **S:** "Early in my career, I was deploying a code change to our production server on a Friday afternoon."
>
> **T:** "I was supposed to follow the deployment checklist, which included running all tests and getting a code review."
>
> **A:** "I was in a rush and skipped the full test suite — I only ran the tests related to my change. The deployment caused a bug that affected our checkout flow. About a hundred users couldn't complete their orders for roughly forty-five minutes before we rolled it back."
>
> **R:** "After that experience, I became much more disciplined about following processes. I actually proposed an automated deployment pipeline that runs the full test suite automatically before any production deployment. The team adopted it, and we haven't had a similar incident since. The lesson I took away was that shortcuts rarely save time — they usually create more work."

---

## 6. Leadership / Initiative (Liderlik / Təşəbbüs)

### Sual: "Tell me about a time when you took initiative beyond your regular responsibilities."

**STAR Cavab Nümunəsi:**

> **S:** "I noticed that our team was spending a lot of time on repetitive tasks — manually running tests, deploying to staging, and generating reports."
>
> **T:** "This wasn't part of my job description, but I felt it was affecting our productivity significantly."
>
> **A:** "I spent a few hours each week over a month building automation scripts. I created a CI/CD pipeline that automatically ran tests on every pull request, a script that deployed to staging with a single command, and a dashboard that generated weekly reports automatically."
>
> **R:** "The team saved approximately five hours per week collectively. My manager was impressed and asked me to present the tools to the wider engineering department. Two other teams ended up adopting the same approach."

---

## 7. Adaptability (Uyğunlaşma)

### Sual: "Describe a situation where you had to adapt to a significant change."

**STAR Cavab Nümunəsi:**

> **S:** "About six months into a project, the client completely changed the requirements. We had been building a web application, but they decided they wanted a mobile-first approach instead."
>
> **T:** "I had to quickly learn React Native, which I had never used before, and adapt the existing backend to serve a mobile client."
>
> **A:** "I spent the first week doing an intensive online course on React Native. I also restructured the API to be more mobile-friendly — smaller payloads, pagination, and better error handling. I communicated openly with the team about my learning curve and asked for help when I needed it."
>
> **R:** "Within three weeks, I was contributing productively to the mobile app. The project was delivered only one week behind the original deadline, which the client considered acceptable given the scope change."

---

## Ümumi Məsləhətlər

### Cavabınızın uzunluğu
- Hər cavab **1.5 - 2 dəqiqə** olmalıdır
- Çox qısa = kifayət qədər detal yoxdur
- Çox uzun = diqqəti itirirsiniz

### Nə etməli:
- Konkret nümunələr verin (ümumi cavablar verməyin)
- Rəqəmlərdən istifadə edin: "saved 5 hours per week", "reduced bugs by 90%"
- "I" deyin, "we" yox (sizin şəxsi töhfənizi göstərin)
- Hətta səhv haqqında danışarkən da öyrəndiyiniz dərsi vurğulayın

### Nə etməməli:
- Keçmiş iş yerinizi və ya həmkarlarınızı tənqid etməyin
- "I don't have an example" deməyin — əvvəlcədən hazırlaşın
- Yalan danışmayın — müsahib bunu hiss edəcək

---

## 8. Giving Difficult Feedback (Çətin Rəy Vermək)

### Sual: "Tell me about a time you had to give critical feedback to a colleague."

**STAR Cavab Nümunəsi:**

> **S:** "Last year, I was the tech lead on a project with a junior developer who was technically strong but whose code reviews were coming across as harsh. A couple of teammates had mentioned it to me privately."
>
> **T:** "As the lead, it was my responsibility to give him honest feedback — but in a way that was constructive, not discouraging."
>
> **A:** "I set up a one-on-one and framed the conversation around impact rather than blame. I said something like, 'Your technical points are almost always correct, but the way some of your comments are phrased makes it harder for the team to receive them.' I gave him two specific recent examples — not to make him defensive, but to make it concrete. Then we talked through softer ways to phrase the same feedback, like asking questions instead of making statements."
>
> **R:** "He was actually grateful — he hadn't realized how his comments were landing. Over the next few weeks, his review style shifted noticeably. A few months later, one of the teammates who had originally complained told me they now enjoyed his reviews. The lesson I took was that most people want to improve — they just need honest feedback delivered with care."

---

## 9. Handling Ambiguity (Qeyri-müəyyənlikdə İşləmək)

### Sual: "Describe a time when you had to work on a project without clear requirements."

**STAR Cavab Nümunəsi:**

> **S:** "A few months after joining my current company, I was asked to lead the rewrite of our search feature. The brief from the product manager was: 'We need search to be better.' That was it."
>
> **T:** "I needed to turn a vague request into a concrete project with clear goals and measurable success criteria — without blocking for weeks waiting for perfect requirements."
>
> **A:** "I started by talking to five different stakeholders — the PM, two engineers who had worked on search before, a customer support lead, and one actual customer. From those conversations, I identified three concrete pain points: slow response times, poor ranking for recent content, and no support for typos. I wrote a short design document with specific goals, shared it with the team and the PM, and asked them to push back on anything they disagreed with. After a week of feedback, we had a project plan everyone was aligned on."
>
> **R:** "We shipped the rewrite in six weeks. Search latency dropped by 70%, and customer complaints about search quality went down significantly in the following quarter. More importantly, I learned that in ambiguous situations, the fastest way forward is often to make your best guess, write it down, and invite disagreement — rather than waiting for someone else to make the decision for you."

---

## 10. Technical Debt Decision (Texniki Borc)

### Sual: "Tell me about a time you had to balance shipping features with fixing technical debt."

**STAR Cavab Nümunəsi:**

> **S:** "At my previous company, our notifications system was built on a legacy codebase that everyone dreaded working in. Every new feature on top of it took three times longer than it should have. But we also had strong pressure from leadership to ship new features."
>
> **T:** "As the owner of that area, I needed to make the case for investing in a refactor without sounding like I was avoiding real product work."
>
> **A:** "I didn't propose a big-bang rewrite — those almost never end well. Instead, I measured the actual cost of the legacy code: I tracked how long recent notification-related features had taken, compared to our estimates, and calculated that we were losing roughly two engineer-weeks per month to the legacy system. I took that data to my manager and the product team and proposed a gradual refactor — we'd pay down debt incrementally as part of each feature, rather than stopping feature work entirely."
>
> **R:** "The plan was approved. Over the next three months, we rewrote the core of the notifications system while still shipping features, and our velocity on that area roughly doubled. The biggest lesson was that technical arguments work much better when they come with numbers. Saying 'the code is bad' doesn't move people — saying 'we're losing eight engineer-days a month' does."

---

## 11. Working with Unclear Stakeholders (Aydın Olmayan Tərəflər)

### Sual: "Have you ever had to push back on a stakeholder's request?"

**STAR Cavab Nümunəsi:**

> **S:** "Two years ago, our sales team promised a feature to a large prospective customer without checking with engineering. The timeline they'd committed to was two weeks. Realistically, the feature would need six."
>
> **T:** "I was the engineering lead on the project. I needed to push back on the timeline without making the sales team look bad to the customer, and without us overpromising again."
>
> **A:** "I set up a meeting with the sales lead and the product manager. I came in prepared — I had a broken-down task list showing exactly why two weeks wasn't realistic. But rather than just saying no, I offered options: we could ship a stripped-down version in three weeks that would cover the customer's main use case, with a follow-up in another three weeks. I also proposed a lightweight process going forward — any time sales wanted to commit to a feature, they'd sync with engineering first."
>
> **R:** "The customer accepted the phased plan. The stripped-down version shipped in three weeks; the full version four weeks after that. And the new process stuck — the sales team actually appreciated having engineering input early, because it made their commitments more credible. The broader lesson I took was that 'no' on its own creates conflict; 'no, but here are three alternatives' creates partnership."

---

## 12. On-Call Incident (Növbətçilikdə Problem)

### Sual: "Tell me about a time you handled a production incident."

**STAR Cavab Nümunəsi:**

> **S:** "Last year, I was on call when our payment service started returning errors at 2 AM. About 15% of checkout requests were failing. PagerDuty woke me up."
>
> **T:** "As the on-call engineer, my job was to stop the bleeding quickly, then diagnose the root cause."
>
> **A:** "First, I opened our monitoring dashboard and saw the error rate had spiked about ten minutes earlier. I checked recent deployments — there had been a deploy to the payment service thirty minutes before the incident started. Based on that correlation, I made a judgment call: I rolled back the deploy without waiting to fully understand the bug. Error rates dropped back to normal within three minutes of the rollback. Then I opened an incident channel, pulled in the engineer who had owned the change, and we spent the next hour finding the actual root cause — a misconfigured timeout that only manifested under certain traffic patterns."
>
> **R:** "Total customer impact was about thirty minutes of elevated errors. We wrote a blameless postmortem the next day and added two specific improvements: an automated check that compares new timeout configs against historical values, and a pre-production traffic replay test. Neither would have been obvious without having lived through the incident."

---

## Hazırlıq Məşqi

Bu 12 sualın hər biri üçün öz STAR cavabınızı yazın. Sonra hər birini vaxt tutaraq səsli deyin. 2 dəqiqədən çox çəkməməlidir.

| # | Mövzu | Hazırdır? |
|---|-------|-----------|
| 1 | Teamwork | ☐ |
| 2 | Conflict | ☐ |
| 3 | Problem solving | ☐ |
| 4 | Tight deadline | ☐ |
| 5 | Mistake | ☐ |
| 6 | Initiative | ☐ |
| 7 | Adaptability | ☐ |
| 8 | Giving feedback | ☐ |
| 9 | Ambiguity | ☐ |
| 10 | Technical debt | ☐ |
| 11 | Stakeholder pushback | ☐ |
| 12 | On-call incident | ☐ |

---

## Bonus: Sual Bankı

Müsahiblər yuxarıdakı 12 kateqoriyanın variantlarını soruşa bilərlər. Hər biri üçün hazır olun:

### Teamwork variantları
- "Describe a team project where you were the most junior person."
- "Tell me about a time you helped a teammate who was struggling."

### Conflict variantları
- "Describe a disagreement with your manager."
- "Tell me about a time you worked with someone you didn't get along with."

### Problem solving variantları
- "What's the hardest bug you've ever debugged?"
- "Describe a time when the obvious solution didn't work."

### Deadline variantları
- "Tell me about a time you missed a deadline."
- "Describe a situation where you had to cut scope."

### Mistake variantları
- "What's the biggest professional mistake you've made?"
- "Tell me about a time you broke production."

### Initiative variantları
- "Tell me about something you built that wasn't in your job description."
- "Describe a time you identified a problem no one else had noticed."

### Adaptability variantları
- "Tell me about a time you had to learn a new technology quickly."
- "Describe a project where priorities changed mid-way."

### Leadership variantları
- "Tell me about a time you mentored someone."
- "Describe a situation where you had to lead without authority."

---

## Cavab strukturu yaddaş kartı

Hər cavabdan əvvəl beyninizdə bu dövrəni keçin:

```
S (10s) — When / Where / What was happening
T (10s) — My specific responsibility
A (60s) — What I did (steps + reasoning)
R (20s) — Outcome (numbers if possible) + what I learned
```

**Toplam: ~100 saniyə (1.5-2 dəqiqə)**
