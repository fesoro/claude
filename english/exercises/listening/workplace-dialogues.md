# Workplace Dialogues — İş Mühiti Dialoqları

## Bu Fayl Haqqında

Bu faylda **10 real iş mühiti dialoqu** var — hər biri ~1 dəqiqəlik oxu / dinləmə üçün. Hər dialoq üçün:
- **Transcript** (sənin oxuya biləcəyin)
- **Comprehension suallar** (dialoqu başa düşdünmü?)
- **Vocabulary** (yeni sözlər)
- **Speaking məşqi** (öz rolunda oxu)

**Dinləmə üçün:** Bu dialoqları [ElevenLabs](https://elevenlabs.io) və ya başqa TTS vasitələri ilə səslə çevirib dinləyə bilərsən. Və ya bir dostla səsli oxu.

**A2-B1 səviyyə:** Bəzi dialoqlar sadə, bəziləri texniki. Sadədən başla.

---

## Dialog 1: Daily Standup (Səviyyə A2-B1)

### Transcript

**Manager (Sara):** Morning, everyone. Let's kick off standup. Mike, can you go first?

**Mike:** Sure. Yesterday, I finished the login refactor and opened a PR. Today, I'll start on the password reset flow. No blockers.

**Sara:** Great. Orkhan, your turn.

**Orkhan:** Yesterday, I was debugging the Kafka issue. I think I found the root cause — it's a timeout configuration. Today, I'll deploy the fix to staging and run tests. No blockers, but I'll probably need help with production deploy.

**Sara:** Got it. Ping me when you're ready. Lisa?

**Lisa:** Yesterday, I joined the API design meeting. Today, I'm writing the design doc. Blocker: I'm waiting on Mike's PR to merge before I can continue on my endpoint.

**Mike:** Oh, I'll review and merge within the hour.

**Lisa:** Perfect, thanks.

**Sara:** Alright, any other blockers? No? Let's wrap up. Have a good one, everyone.

### Questions

1. Who led the standup?
2. What did Mike finish yesterday?
3. What's Orkhan working on?
4. What's Lisa waiting on?
5. How will Mike help Lisa?

### Answers

1. Sara (the manager).
2. He finished the login refactor and opened a PR.
3. Deploying the Kafka fix to staging and running tests.
4. Mike's PR to merge.
5. He'll review and merge it within the hour.

### Key Vocabulary

- **standup** = gündəlik 15 dəqiqəlik görüş
- **kick off** = başlamaq
- **blocker** = bloklayan məsələ
- **ping** = mesaj göndərmək
- **wrap up** = bitirmək
- **refactor** = yenidən qurmaq (kod)
- **root cause** = əsl səbəb

### Speaking Practice

Orkhan rolunu yüksək səslə oxu. Daha sonra:
- Öz standup update-ini yaz (dünən, bu gün, bloker)
- Mirror qarşısında 3 dəfə de
- Qeydə al, dinlə

---

## Dialog 2: Asking for Help (Səviyyə A2)

### Transcript

**Orkhan:** Hey Sara, got a minute?

**Sara:** Sure, what's up?

**Orkhan:** I'm stuck on a bug. The checkout flow is throwing a 500 error when users have special characters in their address, but only in Chrome. I've tried a few things but no luck.

**Sara:** Interesting. Did you check the browser console?

**Orkhan:** Yes, I see an encoding error, but I don't know where it's coming from.

**Sara:** Hmm. Let me take a look. Can you share your screen?

**Orkhan:** Yeah, one second... Can you see it now?

**Sara:** Yep, clear. So the error is on line 142? Let me trace this.

**Orkhan:** Thanks so much for helping.

**Sara:** No problem. That's what I'm here for.

### Questions

1. Why is Orkhan asking for help?
2. What specific problem is he facing?
3. What did Sara ask him to check first?
4. What did Orkhan find in the console?
5. How is Sara going to help?

### Answers

1. He's stuck on a bug.
2. Checkout flow throws a 500 error with special characters in address, only in Chrome.
3. The browser console.
4. An encoding error.
5. She asked him to share his screen so she can trace the issue.

### Key Vocabulary

- **got a minute?** = vaxtın var?
- **what's up?** = nə olub?
- **stuck on** = ilişmiş
- **no luck** = uğur yoxdur
- **take a look** = baxmaq
- **trace** = izləmək (debug)
- **share your screen** = ekranı paylaşmaq

### Speaking Practice

- İki rolu növbə ilə oxu
- "Asking for help" template-lərini yadda saxla

---

## Dialog 3: Code Review Discussion (B1)

### Transcript

**Mike (Reviewer):** Hey, I reviewed your PR. Overall looks good, but I have a few concerns.

**Orkhan:** Cool, let me pull up the PR. What's the main concern?

**Mike:** The `processPayment` function is getting pretty long — 200 lines. Would you be open to splitting it?

**Orkhan:** Yeah, good point. I was thinking about that myself. Any suggestions on how to split?

**Mike:** Maybe separate validation, processing, and error handling into their own functions. Easier to test.

**Orkhan:** That makes sense. I'll refactor and push an update.

**Mike:** Also, line 89 — I see a potential race condition. Two users could hit this code path simultaneously.

**Orkhan:** Ah, you're right. I hadn't thought of that. Should I add a lock, or use a different pattern?

**Mike:** Let's use optimistic locking — we have helpers for that in the `utils` module.

**Orkhan:** Got it. I'll take a look. Anything else?

**Mike:** No, just those two. Otherwise, really clean code.

**Orkhan:** Thanks for the detailed review. I'll push updates in a couple of hours.

### Questions

1. What are Mike's two main concerns?
2. How does Mike suggest splitting the function?
3. What's the issue on line 89?
4. What solution does Mike propose?
5. How does Orkhan respond to the feedback?

### Answers

1. (1) `processPayment` function is too long (200 lines). (2) Potential race condition on line 89.
2. Separate validation, processing, and error handling into their own functions.
3. Two users could hit the code simultaneously — race condition.
4. Optimistic locking (using helpers in the `utils` module).
5. He accepts the feedback and agrees to refactor and push updates.

### Key Vocabulary

- **concerns** = narahatlıq
- **be open to** = razı olmaq
- **split** = bölmək
- **race condition** = race şərti (paralel səhv)
- **pattern** = nümunə, üsul
- **optimistic locking** = optimistik kilid
- **push an update** = yeniləmə göndər

### Speaking Practice

- Her rolu yüksək səslə 2 dəfə oxu
- Öz son PR-ını düşün — hansı "blocking" rəyi almısan?

---

## Dialog 4: Planning Meeting (B1)

### Transcript

**Product Manager (PM):** Alright team, let's plan the sprint. We have 4 priorities from product.

**Sara:** Go ahead.

**PM:** First, the checkout improvements. Second, new admin dashboard. Third, mobile SDK update. Fourth, infrastructure cost reduction.

**Mike:** That's a lot. What's the team capacity this sprint?

**Sara:** Around 40 story points, give or take.

**PM:** Got it. Let's see — checkout is estimated at 15, admin is 12, mobile is 8, infra is 10. That's 45.

**Orkhan:** So we can't fit everything. What's the priority order?

**PM:** Checkout is #1 — it's been promised for Q2. Admin is close second. Then mobile, then infra.

**Sara:** Let's commit to checkout, admin, and mobile — that's 35 points, with some buffer. Push infra to next sprint.

**PM:** Works for me. But let's flag infra as a risk — we can't push it forever.

**Orkhan:** Agreed. Could we also pull in the 3-point ticket to fix the login analytics bug? It's a quick win.

**Sara:** Sure, add it. That puts us at 38 — still under 40.

**PM:** Alright, we have a plan. Thanks everyone.

### Questions

1. How many priorities did the PM bring?
2. What's the team's capacity this sprint?
3. Which priority is #1?
4. What does the team decide to commit to?
5. What gets pushed to the next sprint?

### Answers

1. Four.
2. Around 40 story points.
3. Checkout improvements (promised for Q2).
4. Checkout (15), admin dashboard (12), mobile SDK (8), plus a 3-point login bug fix = 38.
5. Infrastructure cost reduction.

### Key Vocabulary

- **prioritize** = prioritlaşdırmaq
- **capacity** = tutum, gücə
- **story points** = iş vahidi
- **give or take** = təqribən
- **buffer** = ehtiyat
- **commit to** = öhdəlik götürmək
- **push to next sprint** = növbəti sprint-ə saxlamaq
- **flag as a risk** = risk kimi bildirmək
- **quick win** = tez uğur

### Speaking Practice

- PM rolunu oxu, sonra Sara rolunu
- Öz komandanın son planning-ini xatırla: neçə bilet, nə qədər xal?

---

## Dialog 5: 1-on-1 with Manager (B1)

### Transcript

**Manager (Sara):** Hey Orkhan, thanks for making the time. How's everything going?

**Orkhan:** Overall, things are good. I'm making progress on the migration project. But honestly, I've been feeling a bit stretched thin.

**Sara:** Tell me more.

**Orkhan:** It's the combination of the migration, the on-call rotation, and mentoring Lisa. I don't feel like I'm giving any of them my best.

**Sara:** I hear you. That's a lot on one plate. Let's think about this. Which one do you find most stressful?

**Orkhan:** Honestly, the on-call. When it fires during deep work, it pulls me out for hours.

**Sara:** That makes sense. Would it help if we moved you out of the rotation for the next month while you finish the migration?

**Orkhan:** That would really help. Are you sure? I don't want to burden the others.

**Sara:** Don't worry, we have five engineers — they can absorb it. Let's do that. What about mentoring Lisa?

**Orkhan:** Actually, that's going well. Lisa is quick, and the 1-on-1s aren't a heavy lift.

**Sara:** Good. So new plan: you focus on migration, pause on-call for a month, continue mentoring. Does that sound right?

**Orkhan:** Yes, thank you. That actually makes a big difference.

**Sara:** Glad we talked. Ping me if other things come up.

### Questions

1. How is Orkhan feeling?
2. What's causing his stress?
3. Which task does he find most stressful?
4. What solution does Sara propose?
5. What does Orkhan say about mentoring Lisa?

### Answers

1. Stretched thin (overwhelmed).
2. The combination of migration project, on-call rotation, and mentoring Lisa.
3. On-call — it pulls him out of deep work.
4. Move him out of the on-call rotation for the next month.
5. It's going well and not a heavy lift.

### Key Vocabulary

- **stretched thin** = çox yüklü, dağılmış
- **make time** = vaxt ayırmaq
- **on-call rotation** = növbətçilik siyahısı
- **pull out** = çıxarmaq
- **burden** = yük
- **absorb** = mənimsəmək
- **heavy lift** = ağır iş
- **glad we talked** = söhbətimiz üçün sevindim

### Speaking Practice

- Öz həqiqi 1-on-1-larda bu ifadələri işlət
- Menecerinlə növbəti görüşündə "I've been feeling stretched" kimi başla

---

## Dialog 6: Handling Bad News (B1-B2)

### Transcript

**Orkhan:** Hey Mike, got a sec? I need to flag something.

**Mike:** Sure. What's up?

**Orkhan:** So I've been working on the payment migration. I'm going to miss the deadline by 2 weeks.

**Mike:** Okay. What's the reason?

**Orkhan:** Two main issues. First, the third-party API has more edge cases than we estimated — their docs are outdated. Second, QA found critical bugs that need proper fixes, not band-aids.

**Mike:** Understood. What's the new timeline?

**Orkhan:** April 30 instead of April 15. I've already brought Lisa in to help parallelize.

**Mike:** Okay. Why didn't this come up earlier?

**Orkhan:** Honestly, I should have flagged it two weeks ago. I kept thinking I could recover. That was a mistake on my part.

**Mike:** I appreciate you being honest. For next time, please flag risks earlier — even if you're not sure. Better to over-communicate.

**Orkhan:** Understood. That's fair. I'll send a written update with the new timeline and dependencies.

**Mike:** Sounds good. Anything else you need?

**Orkhan:** Not right now. Thanks for being understanding.

### Questions

1. What's the bad news Orkhan delivers?
2. How long will the project be delayed?
3. What are the two main reasons?
4. What does Orkhan admit about his handling of this?
5. What advice does Mike give?

### Answers

1. He's going to miss the migration deadline by 2 weeks.
2. By 2 weeks (April 15 → April 30).
3. (1) Third-party API has more edge cases than estimated. (2) QA found critical bugs that need proper fixes.
4. He should have flagged this 2 weeks earlier but kept thinking he could recover.
5. Flag risks earlier, even if not sure — over-communicate.

### Key Vocabulary

- **flag something** = nəyisə bildirmək
- **miss the deadline** = deadline-ı keçmək
- **edge cases** = qeyri-adi hallar
- **outdated** = köhnəlmiş
- **band-aids** = müvəqqəti həll
- **parallelize** = paralel etmək
- **recover** = bərpa etmək
- **over-communicate** = daha çox məlumat vermək
- **written update** = yazılı məlumat

### Speaking Practice

- Orkhan rolunu 3 dəfə oxu — ton nəzakətli amma peşəkar
- Öz son "bad news" situasiyasını düşün: necə danışdın?

---

## Dialog 7: Networking at a Conference (A2-B1)

### Transcript

**Orkhan:** Hi, mind if I join you?

**Stranger:** Not at all, please! I'm Alex.

**Orkhan:** Nice to meet you, Alex. I'm Orkhan. Great conference so far?

**Alex:** Yeah, I liked the morning keynote. How about you?

**Orkhan:** Same here. I really enjoyed the Kubernetes talk. Which session are you going to next?

**Alex:** The one on observability at 2 pm. You?

**Orkhan:** Oh, I was considering that one too. Maybe I'll see you there.

**Alex:** Cool. What do you do, by the way?

**Orkhan:** I'm a backend engineer at a fintech startup in Baku. You?

**Alex:** Ah, fintech — always interesting. I work at a mid-sized SaaS company in Berlin. Platform team.

**Orkhan:** Cool. How's the tech scene in Berlin?

**Alex:** Booming. A lot of opportunities. Have you considered working abroad?

**Orkhan:** Actually, yeah. I've been looking into remote roles or potentially relocating.

**Alex:** Let's connect on LinkedIn. Happy to share any leads if something comes up.

**Orkhan:** That would be great. Thanks! Here, let me grab my phone...

### Questions

1. How does Orkhan start the conversation?
2. What did each of them enjoy about the conference?
3. What's Alex's job?
4. What did Orkhan say about working abroad?
5. How do they end the conversation?

### Answers

1. "Mind if I join you?"
2. Orkhan enjoyed the Kubernetes talk; Alex enjoyed the morning keynote.
3. Platform team at a mid-sized SaaS company in Berlin.
4. He's been looking into remote roles or potentially relocating.
5. They connect on LinkedIn and Alex offers to share leads.

### Key Vocabulary

- **mind if I join you?** = icazənizlə qoşulum?
- **keynote** = açılış nitqi
- **session** = sessiya (görüş)
- **backend engineer** = arxa tərəf mühəndisi
- **mid-sized** = orta ölçülü
- **booming** = sürətlə böyüyən
- **leads** = imkanlar (əlaqələr)
- **come up** = ortaya çıxmaq

### Speaking Practice

- Orkhan rolunu oxu
- Öz conference approach-ını hazırla: neçə cümlədə özünü təqdim et

---

## Dialog 8: Interview Follow-up Call (B1)

### Transcript

**Recruiter:** Hi Orkhan, this is Sara from TechCorp. How are you?

**Orkhan:** Hi Sara, doing well, thanks. How about you?

**Recruiter:** Great. Listen, I'm calling to give you an update on your interview process.

**Orkhan:** Please, go ahead.

**Recruiter:** So, I have good news. The team really enjoyed talking to you, and we'd like to move forward with an offer.

**Orkhan:** That's amazing! Thank you, I'm really excited.

**Recruiter:** Let me walk you through the offer. Base salary is 95K, plus 15K in annual bonus target, and equity vesting over 4 years. Full benefits, 25 days PTO, and you can work fully remote from Baku.

**Orkhan:** Thank you. That sounds great overall. I'd like to take a couple of days to review the details carefully. Would that be okay?

**Recruiter:** Of course, take your time. We typically ask for a decision within 5 business days. Does that work?

**Orkhan:** That's more than enough. I'll get back to you by Friday.

**Recruiter:** Perfect. I'll send the formal offer letter by email in the next hour. Let me know if you have any questions.

**Orkhan:** One question now — is there flexibility on the base salary? Based on my research, the range is a bit higher for similar roles.

**Recruiter:** I appreciate you bringing it up. There's a little room. Let's discuss it once you've had time to review the full package. We can hop on a call Thursday if that works.

**Orkhan:** Thursday works. Thanks so much, Sara. I really appreciate this.

### Questions

1. Why is the recruiter calling?
2. What are the main components of the offer?
3. How much time does Orkhan ask for?
4. What's the recruiter's deadline?
5. What question does Orkhan ask about salary?

### Answers

1. To give an update on the interview process and offer a job.
2. Base salary 95K + 15K annual bonus + equity over 4 years + full benefits + 25 days PTO + fully remote.
3. A couple of days.
4. 5 business days.
5. Whether there's flexibility on the base salary (his research shows higher range for similar roles).

### Key Vocabulary

- **move forward with an offer** = təklifə irəliləmək
- **base salary** = əsas maaş
- **annual bonus** = illik bonus
- **equity vesting** = paylar (zaman keçdikcə)
- **PTO (Paid Time Off)** = ödənişli tətil
- **take a couple of days** = bir neçə gün götürmək
- **flexibility on** = ... üzərində çeviklik
- **hop on a call** = zəng etmək

### Speaking Practice

- Orkhan cavablarını oxu — özünə güvənli amma nəzakətli
- Öz hazır cavabını yaz: "I'd like to take a couple of days to review..."

---

## Dialog 9: Explaining Your Project (Technical, B2)

### Transcript

**Interviewer:** Tell me about a technical project you're proud of.

**Orkhan:** Sure. I'd say the payment migration I led last year. Let me walk you through it.

**Interviewer:** Please.

**Orkhan:** So, context: we had a monolithic application handling payments. It worked, but deploys were slow — 2 hours each — and any change to payments required coordinating with unrelated teams. Our CEO wanted us to ship features 3x faster.

**Interviewer:** Makes sense. So what did you do?

**Orkhan:** I proposed extracting payments into a dedicated microservice. The trade-offs were clear: more operational complexity upfront, but much faster iteration long-term.

**Interviewer:** And how did you approach the migration?

**Orkhan:** Incrementally, over 6 months. Phase 1: I built the new service in parallel with the old. Phase 2: I routed 10% of traffic to the new service. Phase 3: Ramped to 100%. Phase 4: Decommissioned the old code.

**Interviewer:** Any challenges?

**Orkhan:** Plenty. The biggest was data consistency. We had to ensure the old and new services saw the same state during the transition. I designed an event-sourcing pattern where both services subscribed to a central event bus.

**Interviewer:** Interesting. What was the outcome?

**Orkhan:** Deploy time dropped from 2 hours to 10 minutes. The team now ships 5x more frequently. Incidents are actually down because changes are smaller and easier to roll back.

**Interviewer:** What did you learn from this?

**Orkhan:** Two big things. One: invest in rollout infrastructure before migrating — the traffic routing saved us many times. Two: have a clear "definition of done" before starting. We had some scope creep that almost pushed the timeline.

**Interviewer:** Great example. Thanks.

### Questions

1. What project is Orkhan describing?
2. Why did they need to migrate?
3. What was the biggest challenge?
4. How did he solve it?
5. What are the two lessons he learned?

### Answers

1. Payment migration from monolith to microservice.
2. Deploys were slow (2 hours) and changes required coordinating with unrelated teams. CEO wanted 3x faster shipping.
3. Data consistency — ensuring old and new services saw the same state.
4. Event-sourcing pattern with a central event bus that both services subscribed to.
5. (1) Invest in rollout infrastructure before migrating. (2) Have a clear "definition of done" to avoid scope creep.

### Key Vocabulary

- **monolithic** = monolitik
- **trade-offs** = güzəştlər
- **iteration** = iterasiya, yenidən işləmə
- **incrementally** = tədricən
- **route traffic** = trafiki yönəltmək
- **ramp to** = səviyyəsinə qaldırmaq
- **decommission** = istifadədən çıxarmaq
- **event-sourcing** = hadisə əsaslı pattern
- **scope creep** = miqyasın genişlənməsi

### Speaking Practice

- Bu cavabı **90 saniyədə** oxu
- Öz bənzər layihəni düşün
- STAR strukturunu tətbiq et (Situation, Task, Action, Result)

---

## Dialog 10: Salary Negotiation (B2)

### Transcript

**Recruiter:** Thanks for joining the call. As I mentioned on email, we'd like to discuss the compensation details.

**Orkhan:** Yes, thanks for being open to this. I've had time to review the initial offer.

**Recruiter:** Great. What are your thoughts?

**Orkhan:** The overall package is very attractive — the role, the team, the culture all appeal to me. I want to make this work. However, based on my research, the base salary is a bit below market for senior backend roles in this region.

**Recruiter:** I appreciate you doing the research. What range are you looking at?

**Orkhan:** Based on Glassdoor, Levels.fyi, and conversations with peers, the market for senior backend engineers with 5 years of experience is 105-115K. You offered 95. I'd be more comfortable at 105.

**Recruiter:** That's a 10K jump. Let me be honest — we have some flexibility, but 105 is at the upper end of our budget for this level.

**Orkhan:** I understand. I want to be fair. Here's my thinking: I'm bringing specific experience in payment systems, which is directly relevant to this role. I also led a team of 4 in my last role. If 105 is tough, would 102 plus a signing bonus work?

**Recruiter:** Let me speak with the hiring manager. Is signing bonus more important to you, or is the base?

**Orkhan:** Honestly, the base matters more because it compounds over time with raises. But a signing bonus is a nice recognition.

**Recruiter:** Understood. Give me until end of day to get back to you.

**Orkhan:** That works. Thanks for considering it.

**Recruiter:** Of course. I appreciate how you're handling this.

### Questions

1. What is Orkhan's initial reaction to the offer?
2. What research did he do?
3. What range is he asking for?
4. What alternative does he propose?
5. What does he value more: base or signing bonus?

### Answers

1. He likes the overall package (role, team, culture) but feels the base salary is below market.
2. Glassdoor, Levels.fyi, and conversations with peers.
3. 105-115K (he's asking for 105).
4. 102 plus a signing bonus.
5. Base salary, because it compounds over time with raises.

### Key Vocabulary

- **compensation** = kompensasiya (maaş + digər)
- **attractive** = cəlbedici
- **appeal to me** = məni cəlb edir
- **below market** = bazarın altında
- **range** = aralıq, sıra
- **upper end** = yuxarı həd
- **signing bonus** = müqavilə bonusu
- **compound** = cəmləşmək
- **nice recognition** = gözəl tanınma

### Speaking Practice

- Orkhan rolunu oxu (2 dəfə)
- Öz "counter-offer" ifadəni hazırla
- "Market research" — hansı 3 mənbə istifadə edirsən?

---

## Ümumi Dinləmə Strategiyaları

### 1. Tam Başa Düşməyi Hədəf etmə
- İlk dinləmədə 50-60% başa düşsən - normal
- 2-ci dinləmədə daha çox tut
- Transcript ilə müqayisə et

### 2. Açar sözləri Tut
- Sualı cavablandırmaq üçün bütün detallar lazım deyil
- Əsas fakt və adlara diqqət et

### 3. Kontekstə Əsaslan
- Bilmədiyin sözləri kontekstdən təxmin et
- Gelişmə üçün vacibdir

### 4. Aksent Müxtəlifliyinə Alış
- British, American, Indian aksentləri fərqlidir
- Hər biri ilə məşq et

---

## Dinləmə Məşq Planı

### Həftəlik:
- **Gün 1-2:** Dialog 1-3 (sadə)
- **Gün 3-4:** Dialog 4-6 (orta)
- **Gün 5-6:** Dialog 7-9 (çətin)
- **Gün 7:** Hər biri yenidən + özün oxu

### Hər dialog üçün:
1. **İlk oxu** (5 dəq) — ümumi məna tut
2. **Suallara cavab** (3 dəq)
3. **Cavabları yoxla** (2 dəq)
4. **Vocabulary yaz** (5 dəq)
5. **Yüksək səslə oxu** (5 dəq)

---

## Real Həyat Tətbiqi

### Həftədə 1 dəfə:
- Real bir iş zəngi ya Slack söhbəti izlə
- Yeni sözləri yaz
- Cümlələri təkrar et

### Podcastlar (real insanlar danışır):
- **The Stack Overflow Podcast** — tech söhbətləri
- **Software Engineering Daily** — dərin texniki
- **The Changelog** — open source
- **Darknet Diaries** — cyber security (hekayə)

### YouTube:
- Real interview recordings (bəzi şirkətlər paylaşır)
- Conference talks
- Tech YouTubers (Primeagen, Theo, Fireship)

---

## Əlaqəli Fayllar

- [Mock Interview Full](mock-interview-full.md)
- [Interview Listening Practice](interview-listening-practice.md)
- [Graded Listening A2-B1](graded-listening-a2-b1.md)
- [Transcript Exercise](transcript-exercise.md)
- [Meeting / Standup English](../../skills/speaking/meeting-standup-english.md)
- [Pair Programming English](../../skills/speaking/pair-programming-english.md)
