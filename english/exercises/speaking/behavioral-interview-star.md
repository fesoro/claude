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

## Hazırlıq Məşqi

Bu 7 sualın hər biri üçün öz STAR cavabınızı yazın. Sonra hər birini vaxt tutaraq səsli deyin. 2 dəqiqədən çox çəkməməlidir.

| # | Mövzu | Hazırdır? |
|---|-------|-----------|
| 1 | Teamwork | ☐ |
| 2 | Conflict | ☐ |
| 3 | Problem solving | ☐ |
| 4 | Tight deadline | ☐ |
| 5 | Mistake | ☐ |
| 6 | Initiative | ☐ |
| 7 | Adaptability | ☐ |
