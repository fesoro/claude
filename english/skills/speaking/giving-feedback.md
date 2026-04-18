# Giving Feedback — Rəy Vermək İngilis Dilində

## Bu Fayl Haqqında

Software engineer işində **rəy vermək və almaq** sənin effektivliyini müəyyən edən əsas bacarıqlardan biridir. Kod reviewinde, performance review-da, 1-on-1-larda və günün hər saatında rəy mübadiləsi olur.

Azərbaycan mədəniyyətində rəy tez-tez **birbaşa** verilir; Qərb iş mühitində **kontekst + konkret nümunə + təklif** strukturu var.

---

## 1. Rəy Vermək — Əsas Struktur (SBI Modeli)

**SBI = Situation + Behavior + Impact**

### Formula:
> "In [situation], when you [behavior], it [impact]."

### Nümunə 1 (müsbət):
> "In our yesterday's standup, when you **explained the blocker clearly and suggested a workaround**, it **helped the whole team move forward faster**. I appreciated that."

### Nümunə 2 (konstruktiv):
> "In the design review meeting today, when you **interrupted Sara while she was presenting**, it **made it hard for her to finish her thought and may have discouraged her**. Could we try to hold questions until the end next time?"

---

## 2. Müsbət Rəy (Positive Feedback)

### Başlanğıc ifadələri:
- "I wanted to share some positive feedback."
- "I really appreciated how you handled ___."
- "Great job on ___."
- "You did an excellent job with ___."
- "I was impressed by ___."
- "I noticed you ___, which was really helpful."

### Səbəb əlavə et (niyə yaxşı idi):
- "...which helped [nəticə]."
- "...and that made a real difference."
- "...which is exactly what we needed."
- "...that took the team to the next level."

### Tam nümunələr:

**Texniki:**
> "I wanted to give you some positive feedback on the PR you shipped yesterday. Your code was really clean, and the tests covered all the edge cases I could think of. The fact that you also added a Slack alert for the rate limit was thoughtful — it shows you're thinking about operations, not just features. Great work."

**Ünsiyyət:**
> "You did an excellent job handling that client call today. They were frustrated, but you listened, acknowledged their concern, and explained what we could do. That's hard, and you made it look easy."

**Komanda işi:**
> "I appreciated how you stepped in to help Mike with the deployment issue yesterday. You didn't have to — it wasn't your task — but you saw he was stuck and offered help. That's the kind of culture we want."

### ⚠️ Zəif müsbət rəy:
- ❌ "Good job." *(çox ümumi)*
- ❌ "You're doing great." *(konkret deyil)*
- ❌ "Nice work." *(səthi)*

### ✅ Güclü:
- Konkret *(hansı iş, hansı davranış)*
- Kontekstli *(nə vaxt, harada)*
- Təsirli *(nəticə nə idi)*

---

## 3. Konstruktiv Rəy (Constructive Feedback)

**Qayda:** Qarşı tərəfin **müdafiə pozisiyasına** keçməməsi üçün diqqətli olmaq lazımdır.

### Başlanğıc ifadələri — yumşaldıcı:
- "Can I share an observation?"
- "Would you be open to some feedback?"
- "I wanted to bring something up — is now a good time?"
- "I've noticed something and wanted to share..."
- "One thing I'd gently push back on..."

### Tonu Yumşaltmaq:
- "I might be wrong, but it seems like ___."
- "I'd suggest considering ___."
- "Have you thought about ___?"
- "Another angle might be ___."
- "What if we tried ___?"

### Birbaşa amma nəzakətli:
- "I don't agree with ___, and here's why."
- "I think we can do better on ___."
- "This area needs work — let me explain."

### Tam nümunələr:

**PR/Code feedback:**
> "Quick thought on the PR — the logic is solid, but the function is getting pretty long (200 lines). Would you be open to extracting the validation part into a separate helper? It'd be easier to test in isolation. Just a suggestion, not a blocker."

**Meeting feedback:**
> "I wanted to give you some feedback on today's presentation. Overall, the content was strong. One thing I'd gently push back on is the pace — you went through the slides pretty quickly in the beginning, and a few people (including me) weren't able to follow some points. If you want, we could work on pacing before your next one."

**Behavior feedback:**
> "Hey, can I share an observation? In our team meetings this week, I noticed you've been on your laptop a lot during other people's updates. I'm sure you're busy, but it might come across as not being engaged. It might be worth closing the laptop during others' time to talk, or letting the team know if you're genuinely focused on something urgent."

### ⚠️ Saxınmalı:
- ❌ "You always ___." *(overgeneralization)*
- ❌ "Everyone says ___." *(anonim şikayət)*
- ❌ "You never ___." *(overgeneralization)*
- ❌ "You are lazy/careless/bad." *(şəxsi hücum)*

### ✅ İstifadə et:
- "I noticed that in [specific case]..."
- "In [situation], I felt ___ because ___."
- "Could we try [specific suggestion]?"

---

## 4. "Feedback Sandwich" — Klassik Model

**Struktur:** Müsbət + Konstruktiv + Müsbət

### Nümunə:
> "Hey Mike, wanted to share some thoughts on the project. 
>   
> **(+)** First, really impressed by how quickly you ramped up on the new codebase — you shipped your first feature in week two, which is fast.  
>   
> **(?)** One thing to keep in mind: I noticed a few PRs that went in without tests. For features that touch payment flow, tests are especially important — those are the areas where bugs can cost real money. It'd be great to make tests a habit going forward.  
>   
> **(+)** Overall, you're off to a strong start. Let me know if you want to pair on anything."

### ⚠️ Sandwich-in zəif tərəfi:
- Çox şəffaf olur — qarşı tərəf "konstruktiv hissəni" gözləyir
- Müsbət hissəni "ənənə" saymaq olur
- **Alternativ:** birbaşa konstruktiv ver, müsbət ayrı günə saxla

---

## 5. Code Review Rəyləri

### Müsbət:
- "LGTM! Really clean code."
- "Nice refactor."
- "Love this abstraction."
- "Great tests."
- "Approved — ship it."

### Təklif (nəzakətli):
- "Just a nit, feel free to ignore: [suggestion]."
- "Might be worth considering: [alternative]."
- "Up to you: [option]."
- "Thought: would it be cleaner to [pattern]?"

### Sual:
- "Help me understand — why did you choose X over Y?"
- "Is there a reason this is public vs private?"
- "What happens if [edge case]?"
- "Could you add a comment explaining this logic?"

### Blocking:
- "I think we need to address this before merging: [issue]."
- "This might cause [specific problem]. Could we [solution]?"
- "Let's discuss before merging — there's a concern with [X]."

### Tam nümunələr:

**Nazik təklif:**
> "Nice work overall! Just a small suggestion — the `processData` function is doing a lot of things. Could we split it into `validateInput`, `transformData`, and `saveResult`? It'd make testing easier. Not a blocker, though."

**Ciddi narahatlıq:**
> "I have a concern about this approach. The current implementation doesn't handle the case where the API returns a partial success (200 with error in body). In my previous project, this caused data corruption that took us days to track down. Could we add handling for this before merging?"

---

## 6. Performance Review Rəyi

### Müsbət — konkret nümunə ilə:
> "You've shown strong ownership this quarter. The payment integration project is a great example — you didn't just implement the feature, you also caught a subtle bug in our existing flow and fixed it. That kind of initiative is exactly what we need."

### İnkişaf sahəsi (yumşaq amma aydın):
> "One area I'd like to see you grow in is cross-team communication. On the [project] project, there were a couple of moments where updates to the product team were delayed, which caused them to be surprised by scope changes. Going forward, I'd suggest sending weekly written updates to the team."

### Hədəflər təyin etmək:
- "For next quarter, I'd like you to focus on ___."
- "A good goal would be ___."
- "Let's set a target of ___ by end of ___."

---

## 7. Mentor Rəyi (Junior-a)

**Prinsip:** Konkret, tədris məqsədli, həvəsləndirici.

### Müsbət + gələcəkdə nə edəsi:
> "You did a great job with the API refactor. The naming is much clearer now, and the tests are comprehensive. If you want to take the next step, I'd suggest thinking about backward compatibility. Right now, if someone calls the old endpoint, they get an error. For the next similar project, think about whether we need a deprecation path."

### Səhv üçün:
> "I noticed in the review that you pushed back on Sara's suggestion pretty hard. Her suggestion was actually valid — the function was too long. I know it can feel personal when someone critiques your code, but remember that code review is about the code, not about you. One tip: wait 10 minutes before responding to feedback you don't agree with. It helps."

---

## 8. Menecer-dən Rəy Almaq

### Rəy xahiş et:
- "How do you think I'm doing?"
- "What areas should I focus on?"
- "Is there anything you'd like me to do differently?"
- "How am I perceived by the team?"
- "What would you like me to work on next quarter?"
- "If there's one thing I could improve, what would it be?"

### Rəyə cavab ver:
- "Thanks for sharing that."
- "That's helpful — I hadn't thought of it that way."
- "I agree. I'll work on ___."
- "Could you give me a specific example?"
- "What would 'better' look like here?"
- "I understand. Let me think about how to approach this."

### Rəy ilə razılaşmama (kibar):
- "I appreciate the feedback. I see it a bit differently, though — [your view]."
- "That's an interesting take. In my experience, ___."
- "Could we dig into this more? I want to make sure I understand."

---

## 9. Razılaşmama Rəyi (Senior-dan)

Senior əminlikdə səhv fikir ifadə edirsə, necə kibar rəy verəsən?

### ❌ Birbaşa:
"You're wrong."

### ✅ Yumşaq:
- "I might be missing something, but I thought ___."
- "Help me understand — wouldn't [X] be a problem?"
- "Have we considered [Y] in this context?"
- "I'd push back gently on that. Here's why ___."
- "In my experience, the issue with [approach] is ___."

### Tam nümunə:
> "I hear what you're saying about using option A. I might be missing some context, but I'm worried about [specific concern]. In my previous company, we tried a similar approach and ran into [problem]. Would it be worth exploring option B, at least as a backup?"

---

## 10. Rəy Vermə Ssenarisi — 5 Situasiya

### Ssenari 1: Junior PR-da çox səhv var

❌ **Pis:**
> "There are so many issues with this code. You need to redo it."

✅ **Yaxşı:**
> "Thanks for the PR! I've left several comments. Some are style nits (feel free to ignore), but there are a few I'd like to discuss — particularly around error handling and the database query in the loop. Could we jump on a 15-minute call to walk through them together? I think you'll get a lot out of seeing my thought process."

### Ssenari 2: Kollega meeting-də çox danışır

❌ **Pis:**
> "You talk too much in meetings."

✅ **Yaxşı:**
> "Can I share an observation? In our last two design meetings, I noticed you were doing most of the talking — probably 70% of the time. You have great ideas, but it might be worth leaving more space for others, especially the quieter people on the team. Sometimes the best solutions come from people who need to be invited to speak."

### Ssenari 3: Menecer sənin işinə qarışır (mikromenecment)

❌ **Pis:**
> "Stop micromanaging me."

✅ **Yaxşı:**
> "I wanted to bring something up. In the last few weeks, you've been checking in pretty frequently on my progress — sometimes multiple times a day. I know that's probably well-intentioned, but it's actually making it harder for me to focus. Could we agree on a regular check-in time instead, and trust me to reach out if I need help?"

### Ssenari 4: Kollega deadline-ı müntəzəm keçir

❌ **Pis:**
> "You're always late."

✅ **Yaxşı:**
> "Hey, got a minute? I wanted to check in on how things are going. I've noticed the last three tickets you took slipped past their deadlines, and I'm wondering if there's something I should know about. Are you stuck? Overloaded? I'd rather know early so we can figure it out together."

### Ssenari 5: CEO çox böyük plan elan edir

❌ **Pis:**
> "This is unrealistic."

✅ **Yaxşı:**
> "I really appreciate the ambition here, and I'm excited about the direction. I want to flag a concern, though — hitting this timeline would require trade-offs that I don't think we've fully discussed. Could we walk through what we'd need to deprioritize? I want to make sure we're aligned on the cost."

---

## 11. Rəy Alanın Cavab Strategiyaları

### Emosiya azalt:
1. Dinlə — sözünü kəsmə
2. 10 saniyə gözlə cavab verməzdən əvvəl
3. "Thanks for sharing" de
4. Dəqiqləşdir — "Can you give me a specific example?"
5. Qərar sonraya saxla

### Qəbul et:
- "You're right, I should have ___."
- "That makes sense. I'll adjust."
- "Good point — I hadn't seen it from that angle."
- "Thanks for flagging. I'll work on it."

### Dəqiqləşdir:
- "Could you help me understand what 'better' would look like?"
- "Can you give me a specific example?"
- "I want to make sure I'm understanding — are you saying ___?"

### Qismən razılaşma:
- "I hear you on [point A]. I'd gently push back on [point B]."
- "That's fair, though I think ___."

### Razılaşmama:
- "I see it differently. Here's my perspective: ___."
- "I understand, but in this case I'd make a different choice because ___."
- "Let me think about this and come back to you."

---

## 12. Rəy Vermək üçün "Sehirli" İfadələr

### Yumşaltma:
- "I might be wrong, but..."
- "Just a thought..."
- "Feel free to push back on this..."
- "Take this with a grain of salt..."
- "It's just my perspective..."

### Konkret etmək:
- "For example..."
- "Specifically, in the last [event]..."
- "Concretely, it looked like..."

### Pozitiv niyyət bildirmək:
- "I'm sharing this because I want you to succeed."
- "This is meant to help, not criticize."
- "I care about this work, which is why..."

### Bağlamaq:
- "Does this make sense?"
- "Let me know your thoughts."
- "I'm open to other perspectives."

---

## 13. Tonda Fərqlər — Eyni Rəy, Fərqli Tonlar

**Baş rəy:** "Your presentation was too long."

| Ton | Necə demək |
|-----|-------------|
| Kobud | "Your presentation was way too long. Boring." |
| Birbaşa | "The presentation was too long." |
| Peşəkar | "I felt the presentation ran a bit long — some sections could have been tighter." |
| Yumşaq | "Really liked the content! For next time, you might consider trimming the middle section to keep energy up." |
| Həddindən artıq yumşaq | "I mean, it was really good — no complaints — but maybe, you know, if you wanted to tweak something, perhaps the length?" |

**Prioritet:** Peşəkar və ya yumşaq. "Həddindən artıq yumşaq" → mesaj itirilir; "kobud" → qarşı tərəf müdafiə pozisiyasına keçir.

---

## 14. Yazılı Rəy (Performance Review Sənədi)

Bəzən yazılı rəy yazmaq lazımdır (performance review, 360 review).

### Struktur:
```
## Strengths
- [Konkret misal] — [niyə güclüdür]
- [Konkret misal] — [niyə güclüdür]

## Areas for Growth
- [Konkret müşahidə] — [nəyə kömək edəcək]
- [Konkret müşahidə] — [təklif]

## Overall Impression
[1-2 cümlə yekun]
```

### Nümunə:
```
## Strengths
- Technical depth: Led the migration of the auth service. 
  Chose the right trade-offs between speed and reliability.
- Mentorship: Onboarded two new engineers in Q2. Both got 
  productive quickly, largely due to your guidance.

## Areas for Growth
- Written communication: Technical decisions sometimes don't 
  get documented until after the fact. Writing a one-page 
  design doc before starting major work would help the team 
  align faster.
- Pushing back: I've noticed you sometimes accept unrealistic 
  deadlines without negotiating. Next quarter, try flagging 
  concerns earlier.

## Overall Impression
Strong senior engineer who has grown significantly this year. 
Ready for more system-level ownership if interested.
```

---

## 15. Hər gün Edilən Mikro-Rəy (Micro-Feedback)

Böyük rəy görüşü gözləmə — kiçik, tez rəyi gündəlik et:

**Chat-də:**
- "👍 nice catch on that bug!"
- "That comment in the PR was 🔥"
- "Loved how you structured today's standup update."

**Canlı:**
- "Hey, before you go — great job on that demo."
- "Small thing — I noticed you volunteered for the on-call rotation. Appreciate it."
- "Wanted to say your blog post was really well written."

### Niyə önəmlidir?
Kiçik rəy **daha təsirli**dir — yaxındır, konkretdir, unutulmur.

---

## 16. Öz Rəyini İstə (Feedback Asking)

Açıq ol — **rəy istəmək** özü sənin inkişaf əlamətidir.

### Soruşma ifadələri:
- "How did the meeting go from your perspective?"
- "Any feedback on my presentation?"
- "Was there anything I could have done better?"
- "If you had one piece of advice for me, what would it be?"
- "How do I come across in meetings?"

### Adi 1-on-1-da:
- "What should I start doing?"
- "What should I stop doing?"
- "What should I keep doing?"

### Layihə sonrası:
- "What would you change if we did this project again?"
- "Any parts where I missed something?"
- "What would have made my contribution more valuable?"

---

## 17. Mədəniyyət Fərqi — Azərbaycan vs Qərb

### Azərbaycan mədəniyyəti:
- Daha birbaşa
- Emosiya ifadə olunur
- Hiyerarxiyaya hörmət (senior-a qarşı rəy çətin)

### Qərb (ABŞ, Avropa) iş mühiti:
- Konstruktiv və strukturlu
- Emosiyadan çox məlumat
- **Flat hierarchy** — hətta junior senior-a rəy verə bilər

### Ortaq nöqtə:
- Konkret nümunə ver
- Şəxsə deyil, davranışa fokuslaş
- Təklif təklif et

---

## 18. Tez-tez Edilən Səhvlər

### ❌ Səhv 1: "Yalnız problem" söyləmək
> "There are too many bugs in your code."  
→ Yaxşı rəy həll də təklif edir.

✅ **Düzəliş:** "I noticed several bugs in recent PRs. Could we pair on a test-writing session this week? It might help catch these earlier."

### ❌ Səhv 2: "You" + "always" / "never"
> "You never listen."  
→ Müdafiə reaksiyası.

✅ **Düzəliş:** "In yesterday's meeting, I felt like my point about the timeline didn't land. Could we revisit?"

### ❌ Səhv 3: Uzun vaxt saxlamaq
Rəy 3 ay geç verirsən → təsiri yoxdur.

✅ **Düzəliş:** Rəyi dərhal və ya 24-48 saat ərzində ver.

### ❌ Səhv 4: Ümumi qohumluqdan danışmaq
> "Everyone thinks you should ___."  
→ Anonim, qeyri-inandırıcı.

✅ **Düzəliş:** "I think you should ___."

### ❌ Səhv 5: Public humilasyon
Toplu Slack kanalında və ya görüş zamanı səhvini hamının qarşısında çıxartmaq.

✅ **Düzəliş:** 1-on-1, DM, və ya fərdi görüşdə.

### ❌ Səhv 6: Müsbət rəy vermirsən
Yalnız problem çıxarırsan, nə yaxşı olduğu deyirsən — komanda demotivasiyaya düşür.

✅ **Düzəliş:** 5-ə 1 nisbəti — 5 müsbət rəy hər bir konstruktiv rəyə görə.

---

## 19. Praktik Məşq

### Ssenari A:
Kollega Sara son bir neçə sprintdə velocityni aşağı düşürüb. Sən komandadakı peersan. Ona nə deyərsən?

### Nümunə rəy:
> "Hey Sara, got a minute? I wanted to check in. I've noticed in the last couple of sprints we've both been completing fewer points than usual — especially you, but I've also felt off. Is there anything going on? I'm asking as a friend, not to be nosy. If you're stretched thin or struggling with something, we can talk through it. Or if you don't want to — totally respect that."

### Ssenari B:
Junior Mike sənin PR-nı təqdim edib. Kod çalışır amma tests yoxdur.

### Nümunə rəy:
> "PR looks great functionally, Mike. One thing before we merge — I don't see tests. This flow touches production data, so tests are pretty important here. Could you add at least happy path + one edge case before we merge? Happy to pair on it if you're not sure how to start."

### Ssenari C:
Sənin menecer sənə "You're too detailed-oriented, loosen up" deyib. Amma sən bunun müdrik tövsiyyə olduğunu düşünmürsən — detal sənin işində vacibdir.

### Nümunə cavab:
> "Thanks for flagging it. Can you give me a specific example? I want to make sure I understand. In my current work — especially the security review — I feel the detail is what prevents problems later. But if there's a specific area where I'm over-rotating, I'd like to know."

---

## 20. Fəaliyyət Məktəbi — 30 Gün

### Həftə 1: Müsbət rəyi gündəlik et
- Hər gün 1 konkret müsbət rəy ver (chat, canlı, email)
- Yalnız "good job" deyil — konkret nümunə

### Həftə 2: Rəy istə
- Bir senior-dan "Any feedback?" soruş
- PR açanda "What could be better?" soruş

### Həftə 3: Konstruktiv rəyə başla
- 1 konstruktiv rəy ver (kiçik şeydən başla)
- SBI strukturunu işlət

### Həftə 4: Rəy al və tənqidi qəbul et
- Alınan rəyə 24 saat gözlə
- Konkret nümunə soruş
- Nə addım atacağını yaz

---

## Əlaqəli Fayllar

- [Meeting / Standup English](meeting-standup-english.md)
- [Polite English](polite-english.md)
- [Agreeing / Disagreeing](agreeing-disagreeing.md)
- [Describing Your Work](describing-your-work.md)
- [Conversation Strategies](conversation-strategies.md)
