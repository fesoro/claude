# Async Communication (Junior)

## Niyə vacibdir? (Why it matters)
Uzaqdan və ya paylanmış mühəndis üçün async ünsiyyət əsas bacarıqdır. Sən iclasdakı səsindən daha çox Slack mesajların, PR açıqlamaların və sənədlərinlə qiymətləndirilirsən. Aydın yazan senior mühəndis istənilən timezone-da istənilən komanda ilə işləyə bilər. Yaza bilməyən senior isə bottleneck olur, çünki hər sual canlı iclas tələb edir.

Beynəlxalq şirkətlərdə işləyən Azərbaycan dili danışanlar üçün async eyni zamanda yazılı ingiliscənin ən böyük təəssürat yaratdığı yerdir. Bir yaxşı strukturlu mesaj on tələsik mesajdan daha çox dəyərlidir.

## Yanaşma (Core approach)
1. **Nə qədər vacibdirsə, o qədər yazılı olmalıdır.** Böyük qərarlar Slack thread-də yox, sənəddə yaşamalıdır. Slack söhbət üçündür; sənəd həqiqət mənbəyidir.
2. **Əksər şeylər üçün channel > DM.** DM-lər səninlə birlikdə ölür. Channel-lər başqalarına öyrənmək imkanı verir.
3. **Əvvəl kontekst, sonra sual.** "Quick question" demə. Eyni mesajda arxa planı ver.
4. **Oxucunun məşğul olduğunu və ekranına baxmadığını güman et.** "Can I ask you something?" yox — sadəcə soruş.
5. **Timezone-lar arası ötürmə əlavə qayğı tələb edir.** Başqa timezone-dakı insan səni oyatmadan işini davam etdirə biləcək EOD qeydləri yaz.

## Konkret skript və template (Scripts & templates)

### Slack mesaj strukturu (TL;DR əvvəl)
```
TL;DR: Need your review on PR #1234 before Friday so we don't block the payments team.

Context: We agreed last week to merge the refactor before the API migration. PR is ready, CI passing, I've addressed early feedback from Sarah.

Ask: 20 min review by Friday EOD your time.
```

### Blok-u async qaldırmaq
```
Blocker: OrderService is throwing 500s in staging since 10:30 UTC. Error: [paste]. What I tried: [list]. Need help from: someone familiar with the queue worker config. Impact: blocking QA from finishing today's test pass.
```

### Gün sonu handoff (timezone körpüsü)
```
EOD update — passing to EU team:
- Completed: auth middleware refactor, PR #1234 merged.
- In progress: order export feature. Stuck on PDF rendering library — exploring dompdf vs snappy. Thoughts welcome.
- Tomorrow (my morning): will review @alex's PR and finish the rate limit spec.
- If urgent: ping me, otherwise async is fine.
```

### Qərarı async sənədləşdirmək (Slack-də yüngül ADR)
```
Decision: We'll use Laravel's built-in queue with Redis driver, not Horizon UI.
Why: Simpler ops, no UI dependency for our current scale.
Trade-off: No built-in dashboard. We'll add Prometheus metrics instead.
Who decided: Me and @sarah after reviewing options. Posted here so the team can challenge by Friday.
```

### Sakit thread-də bağlanmaya doğru itələmək
```
Gentle bump — do we have a decision here? I want to unblock the spec by Friday. If no objections by then, I'll go with option B.
```

### Bu gün cavab verə bilməyəndə
```
Saw this — I'm heads-down on the release today. I'll respond properly tomorrow morning (your late evening). If it's urgent, tag @alex as backup.
```

### Peşəkar şəkildə eskalasiya (aqressiv olmadan)
```
Raising this because it's been open for a week. [Issue]. Impact: [X]. I'd like help from: [person/team]. If this is the wrong channel, please redirect me.
```

## Safe phrases for B1 English speakers
- "TL;DR: [one-line summary]." — uzun mesajı açmaq
- "Quick context:" — arxa planı izah etməzdən əvvəl
- "Ask:" — istəyi aydın göstərmək
- "Not urgent — respond when you can." — təzyiqi azaltmaq
- "Blocking — need input today." — təcililiyi göstərmək
- "Any concerns? If no response by Friday, I'll proceed." — qərara məcbur etmək
- "Gentle bump." — təzyiq olmadan izləmək
- "Happy to jump on a call if async isn't working." — canlı variant təklif etmək
- "I'll summarize this in the doc after we align." — yazılı formaya söz vermək
- "Let me move this to a thread so we don't spam the channel." — struktur
- "Correcting myself from earlier:" — səhvi açıq qəbul etmək
- "Can we move this to the [channel-name] channel?" — yönləndirmə
- "I'll give you my thinking, then a question." — strukturlu mesaj
- "I've added comments in the doc — let me know when you've had a chance to look." — async review istəyi
- "Flagging this early so it doesn't become a bigger issue." — proaktiv ton

## Common mistakes / anti-patterns
- "Hey" yazıb cavab gözləmək. Sualını birinci mesajda yaz.
- Uzun strukturu olmayan paraqraflar. Heç kəs oxumur.
- Vacib qərarların DM-də verilməsi. Onlar yox olur.
- Gecənin ortasında "async-dir" deyə Slack yazmaq. İnsanlar təzyiq hiss edir.
- Təcili olmayan şeylərdə @channel istifadə etmək. Komandaya səni mute etməyi öyrədir.
- Əvvəlki kontekstə heç vaxt link verməmək. Yeni adamlar izləyə bilmir.
- Mövzu ciddi və ya texnikidirsə emoji/zarafat istifadə etmək. Registra uyğunlaş.
- 2 saat cavab gəlməyəndə "???" göndərmək. Onlar məşğuldur.
- Eyni update-i 5 kanalda yazmaq. Bir dəfə yaz, link ver.
- Slack-i həqiqət mənbəyi kimi qəbul etmək. O, çay axınıdır, verilənlər bazası deyil.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "How do you work across timezones?"
- "What's your approach to async communication?"
- "Describe how you handle a blocker when no one is online."

Cavab planı:
1. **Prinsip:** "The more important the decision, the more written. Slack is the conversation, the doc is the source of truth."
2. **Konkret praktika:** "I do EOD handoff notes when working across timezones. Someone in Europe can pick up my work without needing to wake me."
3. **Bloku idarə etmək:** "If I'm blocked and no one is online, I don't wait. I document what I tried, what I know, and switch to a secondary task. When the person comes online they have everything they need."
4. **Nümunə:** "In my last team we split US/EU. I wrote a one-paragraph handoff each day in a shared doc. It cut our cross-timezone friction in half."

## Further reading
- "It Doesn't Have to Be Crazy at Work" by Jason Fried and David Heinemeier Hansson
- "Remote: Office Not Required" by Jason Fried and David Heinemeier Hansson
- GitLab Handbook — public chapter on Remote and Async
- "The Art of Readable Code" by Dustin Boswell and Trevor Foucher
- "Writing for Busy Readers" by Todd Rogers and Jessica Lasky-Fink
