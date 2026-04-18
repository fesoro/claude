# Meeting & Standup English — İş Görüşü İngiliscəsi

## Bu Fayl Haqqında

Software engineer işində **yarısından çoxu görüşlərdə keçir**: daily standup, sprint planning, retrospective, 1-on-1, design review, all-hands və s. Hər növ görüşün öz ifadələri var.

Bu fayl sənə bütün əsas görüş tiplərində istifadə olunan ifadələri öyrədəcək.

---

## 1. Daily Standup (Daily Sync)

**Nə:** 15 dəqiqəlik gündəlik görüş. Hər kəs 3 sualı cavablandırır:
1. Dünən nə etdin?
2. Bu gün nə edəcəksən?
3. Bloklanmısan?

### Template:
> "Yesterday, I [keçmiş]. Today, I'm planning to [hazırki plan]. [Blocker var / yox]."

### ✅ Yaxşı standup nümunəsi:
> "Yesterday, I finished the authentication refactor and pushed it for review. Today, I'm planning to start on the password reset flow — should be a day's work. No blockers."

> "Yesterday, I was debugging that flaky test in the CI pipeline. I traced it to a race condition — fixed now. Today, I'll be on the new API design — I want to share a draft with Alex by end of day. One blocker: I need access to the staging database."

### Açar ifadələr:
- "Yesterday, I [worked on / finished / started / continued] ___."
- "Today, I'm planning to ___."
- "I'm about to wrap up ___."
- "I'm switching gears to ___."
- "No blockers." / "I'm blocked on ___."
- "I need a hand with ___."
- "I could use help from [person] on ___."

### Bloker bildirmək:
- "I'm waiting on design for the new page."
- "I'm blocked by the API change from backend team."
- "I'm stuck on a weird behavior in Firefox — could use pairing."
- "I need review on PR #123 before I can continue."

### ❌ Saxınmalı:
- Həddindən artıq detal ("Let me tell you the whole story...")  
- Bütün tasklərin siyahısı (yalnız vacibləri)
- Söhbət halını uzatmaq ("Can I jump into a side topic?" — standup sonuna saxla)

---

## 2. Görüşə Qoşulma (Joining a Call)

### Vaxtında gəlmək:
- "Hi everyone!"
- "Good morning / afternoon!"
- "Sorry I'm a minute late."
- "Thanks for having me."

### Texniki problem:
- "Can you hear me okay?"
- "Let me know if my audio is clear."
- "Sorry, I was on mute."
- "My connection is a bit spotty."
- "Could you repeat that? I missed the beginning."
- "Let me try to turn on my camera."

### Gözləyərkən:
- "Let's give everyone another minute."
- "Are we still missing anyone?"
- "Shall we get started?"

---

## 3. Sprint Planning

### Məqsəd: Növbəti sprint üçün iş seçmək və təxmin etmək

### Başlanğıc:
- "Let's kick off planning."
- "Should we start with the top of the backlog?"
- "Let's go through each ticket."

### Tapşırıq müzakirə:
- "Could you walk us through this ticket?"
- "What's the scope of this work?"
- "Are the acceptance criteria clear?"
- "Do we have a design for this?"
- "Is there a dependency I should know about?"

### Təxmin etmək (estimation):
- "I'd estimate this at 3 story points."
- "Let's size this relatively — is it bigger or smaller than the last one?"
- "I think this is a 5-pointer."
- "I'd call this a medium — say 3 points."
- "I'm leaning towards 8, but could be 5 if the API is already in place."

### Razılaşmama:
- "I'd actually go higher — this has a lot of edge cases."
- "I think it's smaller than you're estimating — we already have similar logic in place."
- "Let's split the difference at 5."

### Saxlamaq:
- "Let's not commit to this in this sprint — we're already at capacity."
- "Can we push this to the next sprint?"
- "We'll take this as a stretch goal if we finish the rest."

### Qərar:
- "Okay, sprint is full — let's not add more."
- "Sounds like we have consensus."
- "Let's lock this in."

---

## 4. Sprint Retrospective (Retro)

### Məqsəd: Son sprintdə nə yaxşı, nə pis, nə dəyişdirək

### Struktur (ən geniş): **What went well / What didn't / What to change**

### Nümunələr:

**Yaxşı olanlar:**
- "Communication was strong this sprint."
- "Deployment was smooth — no incidents."
- "I appreciated how everyone jumped in to help with the payment bug."
- "The new PR template saved a lot of back-and-forth."

**Pis olanlar:**
- "We underestimated the payment feature — took twice as long."
- "Too many meetings this sprint ate into coding time."
- "The staging environment was down for two days."
- "We didn't have clear acceptance criteria for ticket 123."

**Dəyişdirmək:**
- "Let's add a pre-planning session for design-heavy tickets."
- "I'd suggest we limit meetings to no more than two per day."
- "Can we alert someone when staging goes down?"
- "Let's require acceptance criteria before a ticket enters the sprint."

### Razılaşmama:
- "I hear you, but I think the meetings were actually helpful."
- "I disagree — I'd say our communication could still improve."

### Fəaliyyət addımları:
- "Let's make this an action item."
- "Who's taking this one?"
- "I'll own this."
- "Can we revisit this next retro?"

### Tonu müsbət saxla:
- "I don't want to blame anyone, but..."
- "Just flagging, not criticizing..."
- "This is a systemic issue, not a people issue."

---

## 5. 1-on-1 Məneceri ilə

**Nə:** Meneceri ilə fərdi görüş, adətən 30-60 dəqiqə, həftədə bir dəfə.

### Mənecerin soruşduqları:
- "How's everything going?"
- "Anything on your mind?"
- "Any blockers I can help with?"
- "How are you feeling about your workload?"
- "How can I support you?"

### Səninki cavabları:

**Ümumi vəziyyət:**
- "Things are going well overall."
- "I've had a productive week."
- "It's been a tough week, but I'm okay."
- "I'm a bit overwhelmed right now."

**Çətinliklər:**
- "I'm struggling with [konkret]."
- "I feel stretched thin."
- "I'd like your help prioritizing."
- "I'm running into blockers with [team]."

**Peşəkar inkişaf:**
- "I'd like to grow in [direction]."
- "Can we talk about my career path?"
- "I'm interested in taking on [more responsibility / different work]."
- "I want to work on my [public speaking / system design / etc.]."

**Geribildirim:**
- "Could you share any feedback from the last project?"
- "Is there anything I should be doing differently?"
- "How am I perceived by the team?"
- "What do you think I should work on?"

### Sual vermək:
- "What's the team's priority for next quarter?"
- "Are there any changes coming I should know about?"
- "How is leadership thinking about [topic]?"

---

## 6. Design Review / Architecture Discussion

### Sənin layihəni təqdim:
- "Let me walk you through the proposal."
- "I'll share my screen."
- "The context is ___."
- "The problem we're trying to solve is ___."
- "Here's the approach I'm considering."
- "I want to get your thoughts before I commit to this."

### Alternativ təklif:
- "Have you considered ___?"
- "What if we did it this way instead?"
- "I'd suggest an alternative approach."
- "Another option might be ___."

### Sual vermək:
- "How does this handle [edge case]?"
- "What's the scaling story here?"
- "How would this behave under [condition]?"
- "Can you explain the trade-offs?"
- "What's the rollback plan?"
- "How does this affect downstream services?"

### Diskussiya etmək:
- "I see two trade-offs here..."
- "The pros are X; the cons are Y."
- "It's simpler but less flexible."
- "I'd prioritize reliability over performance in this case."

### Razılaşmaq / razılaşmamaq:
- "I'm convinced — let's go with this."
- "I like the approach, but I have one concern."
- "I'm not sold on this. Here's why: ___."
- "I need more time to think about it."
- "Let me sleep on it."

### Bitirmək:
- "Let's document the decision."
- "I'll write it up in a tech spec."
- "Who's going to own the implementation?"
- "What's our timeline?"

---

## 7. Code Review Söhbəti (canlı)

### Öz PR-ni müdafiə etmək:
- "Thanks for reviewing. Let me address your comments."
- "Good point — I hadn't thought of that case."
- "You're right, I'll update that."
- "I'd actually push back on this one. Here's why: ___."
- "I agree with the suggestion, but I want to flag a consideration."

### Reviewer kimi:
- "Overall, this looks great."
- "Just a few nits, no blockers."
- "Could you help me understand this part?"
- "Is this intentional?"
- "What happens if [edge case]?"
- "This might be out of scope, but have you considered ___?"

### Nazik kritika:
- "Just a thought — not blocking..."
- "In my experience, this pattern can lead to ___."
- "I've seen this bite us before."

---

## 8. All-Hands / Company-wide Meetings

### Rol: Dinləyici

**Cavab vermək:**
- "Could you clarify what you mean by ___?"
- "Can I ask a follow-up?"
- "Great question — I had a similar thought."
- "Thanks for the transparency."

**Sual vermək:**
- "How does this affect the engineering team specifically?"
- "What's the timeline for this change?"
- "Can you share more about the reasoning?"
- "What can we do to help?"

### Bağlanış sözləri:
- "Thanks for the update."
- "Good to hear."
- "Look forward to learning more."

---

## 9. Cross-team Sync (komandalar arası)

### Məqsəd: Başqa komandalar ilə uzlaşma

### Məqsəd aydınlaşdırmaq:
- "Let's make sure we're aligned on [topic]."
- "I want to sync on the [project] timeline."
- "Where are we on [deliverable]?"

### Təqdim etmək:
- "From our side, the status is ___."
- "We're planning to deliver ___ by ___."
- "Our main focus this quarter is ___."
- "We need ___ from your team."

### Müqavilə etmək:
- "Can we agree on [specific commitment]?"
- "Who owns this going forward?"
- "Let's set a deadline for ___."
- "I'll send a written summary after this call."

### Təhlükə haqqında xəbərdarlıq:
- "I want to flag a risk: ___."
- "If we don't do X by Y, we'll miss the deadline."
- "We're going to need your team's help for this to work."

---

## 10. Tövsiyə Veriş / Feedback Görüşü

### Müsbət geribildirim:
- "I wanted to share some positive feedback."
- "I really appreciated how you handled [situation]."
- "You did a great job on [project]."
- "Thank you for [specific action]."

### Konstruktiv geribildirim:
- "I wanted to give you some feedback — is now a good time?"
- "I've noticed [pattern]. I wanted to share that with you."
- "From my perspective, it would help if ___."
- "One suggestion: ___."
- "I think you could benefit from ___."

### Qəbul etmək:
- "Thanks for sharing that."
- "I appreciate the honesty."
- "You're right — I'll work on that."
- "Can you give me an example?"
- "Let me think on this."

---

## 11. Karantin / Sıxıntılı Anlar

### Söhbət qaytarma (redirect):
- "Let's take that offline."
- "Can we sync on this separately?"
- "I think we're going off track."
- "Let's circle back to the agenda."

### Eyni şeyi təkrar soruşan:
- "I think we covered this earlier."
- "I'd point you to [dokument/link]."
- "Can we move on?"

### Çətin söhbətlər:
- "I hear your concern."
- "I understand why you'd feel that way."
- "Let me think about how to respond."
- "Can we take a short break?"

### Mübahisə olduqda:
- "Let's step back."
- "We might need to disagree on this."
- "Let me propose a middle ground."
- "We don't have to decide right now."

### Yaxşı müvazinət:
- "Let's focus on what we can control."
- "I think we both want the same outcome."

---

## 12. Görüş Yekunlaşdırma

### Əməl addımları:
- "Let me summarize the action items."
- "Who's taking this?"
- "When's the deadline?"
- "Let's capture this in the doc."
- "I'll send a follow-up email."

### Nümunə bağlanış:
> "Okay, to summarize: Sara is going to draft the design doc by Friday, and I'll review it over the weekend. We'll reconvene next Tuesday. Anyone have anything else?"

### Sonra:
- "Thanks everyone, that was productive."
- "Have a good rest of your day."
- "Good luck with the work."
- "Let's ship it!"

---

## 13. Pis Xəbər Vermək

### Layihə gecikir:
> "I want to be upfront about where we are with the project. We're behind schedule — I estimate we'll need another two weeks. Here's why: ___. Here's what we're doing to catch up: ___."

### Səhv olub:
> "I need to flag something. We had an incident last night that affected 500 users for about an hour. We rolled back and everything's stable now. I'm writing up a post-mortem."

### Qərar dəyişikliyi:
> "We've decided to change direction on [project]. Here's the reasoning: ___. I know this is disappointing, but I wanted to share the full context."

---

## 14. Nəzakətli Sözlər — Tezərtə Ol!

Görüşlərdə yumşaltıcı ifadələr kobud səslənməmək üçün vacibdir:

| Kobud | Nəzakətli |
|-------|-----------|
| "You're wrong." | "I see it differently." |
| "That's a bad idea." | "I'd be cautious — here's why." |
| "No." | "I don't think so, because ___." |
| "Why did you do that?" | "Help me understand the reasoning." |
| "Shut up, let me finish." | "If I could just finish my thought." |
| "That won't work." | "I have some concerns about that approach." |
| "You don't understand." | "Let me try to explain it differently." |

---

## 15. Tez-tez Istifadə olunan Jargonlar

### "Circle back"
= daha sonra qayıtmaq
- "Let's circle back on this next week."

### "Take offline"
= görüş bitdikdən sonra fərdi müzakirə etmək
- "Let's take this offline — we're running over."

### "Loop in"
= biri söhbətə daxil etmək
- "Let me loop in Sara — she owns this area."

### "Sync up"
= uzlaşmaq, görüşmək
- "Can we sync up after standup?"

### "Ping me"
= xəbər göndər
- "Ping me when you're ready."

### "Double-click"
= daha detallı gəlmək
- "Can we double-click on this point?"

### "Bandwidth"
= vaxt / kapasite
- "Do you have the bandwidth to take this on?"

### "Deep dive"
= dərindən analiz
- "Let's do a deep dive on the architecture."

### "Move the needle"
= real fərq yaratmaq
- "This project will really move the needle on revenue."

### "Low-hanging fruit"
= asan əldə edilən
- "Let's start with the low-hanging fruit."

---

## 16. Tez-tez Edilən Səhvlər

### ❌ "We will see."
Bu həqiqətdə "I don't care" kimi səslənir. Daha konkret ol: "I'll follow up tomorrow."

### ❌ "I will try."
Zəif səslənir. "I'll [do action] by [date]" daha güclüdür.

### ❌ Çox "maybe"
"Maybe we should maybe think about maybe trying ___." → "I suggest we try ___."

### ❌ "Yes" hər şeyə
Hər şeyə razılaşma zəif görünür. Lazım olanda push back et.

### ❌ Mute-də qalmaq
Az danışmaq = az görünmək. Heç olmasa 1-2 qiymətli müdaxilə et.

### ❌ Çox uzun müdaxilə
Söz alanda 60 saniyəni keçmə. Nöqtənə gəl.

---

## 17. Nümunə Dialoqlar

### Standup (Virtual):
```
Scrum master: "Alright, let's start standup. Sara, want to kick off?"

Sara: "Sure. Yesterday, I finished the login refactor and sent the PR. 
       Today, I'll be working on 2FA. No blockers."

Scrum master: "Great. Mike?"

Mike: "Yesterday, I was debugging that payment issue. I think I found 
       the root cause — will share more after standup. Today, I'll 
       write the fix and tests. Blocker: I need access to the staging 
       DB — John, could you grant that?"

John: "Yep, I'll send access after the meeting."

Mike: "Thanks!"
```

### Retro:
```
Facilitator: "Let's start with 'what went well.' Who wants to go first?"

Engineer A: "I think our deploy cadence improved — we shipped three 
           times this sprint without incident."

Engineer B: "Agreed. I'd also add — the new PR template made reviews 
           much faster."

Facilitator: "Great. Now, 'what didn't go well'?"

Engineer C: "Honestly, the sprint was too crowded. We committed to 
           40 points but finished 28. I felt stretched."

Engineer A: "Same here. Maybe we should pad our estimates more."

Facilitator: "Okay, potential action item: review our estimation 
           approach. Who wants to own that?"

Engineer C: "I'll take it."
```

---

## 18. Görüş Hazırlıq Checklist

Hər görüşdən əvvəl yoxla:

- [ ] Görüş məqsədi aydındır?
- [ ] Hansı qərar vermək lazımdır?
- [ ] Agenda var?
- [ ] Sənin rol aydındır? (dinləyici / təqdimatçı / qərar verən)
- [ ] Sənin payına düşən material hazırdır?
- [ ] Vacib olan 1-2 fikrin var?
- [ ] Sualların hazırdır?

---

## Əlaqəli Fayllar

- [Conversation Strategies](conversation-strategies.md)
- [Agreeing/Disagreeing](agreeing-disagreeing.md)
- [Giving Opinions](giving-opinions.md)
- [Discussion Phrases](discussion-phrases.md)
- [Technical Discussion Phrases](technical-discussion-phrases.md)
- [Polite English](polite-english.md)
- [Tech Deep Dive Vocabulary](../../vocabulary/by-topic/technology/tech-deep-dive.md)
