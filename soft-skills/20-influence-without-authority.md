# Influence Without Authority (Lead)

## Niyə vacibdir? (Why it matters)
Senior və staff mühəndislərin nadir hallarda tabeliyində insanlar olur. Buna baxmayaraq onlar təşkilat üzrə dəyişikliyə liderlik etməlidirlər — standartlar, arxitektura, işə qəbul, proseslər. Bu "authority olmadan təsir etmək"-dir: əmr verməklə yox, inandırma, sübut və etibar vasitəsilə işləri həyata keçirmək. Şirkətlər staff/principal namizədlərini getdikcə daha çox məhz bu bacarığa görə qiymətləndirir, çünki bu rolun ən çətin hissəsidir.

Staff-track-ə doğru irəliləyən senior PHP/Laravel mühəndisi üçün bu bacarıq karyeranın tavanını kod yazmaqdan daha çox müəyyən edir.

## Yanaşma (Core approach)
1. **Əvvəl yaz.** Təkliflər, RFC-lər, design sənədləri. Yalnız şifahi mövcud olan ideyalar ölür. Yazılı artefaktlar oxucu və momentum toplayır.
2. **Böyük iclasdan əvvəl buy-in al.** Stakeholderlərlə design review-dan əvvəl 1:1-lərdə danış. İclas baş tutanda qərar artıq çoxdan verilmiş olur.
3. **Sponsor tap.** Sənin ideyanı dəstəkləyən direktor və ya staff+ sənin tək aça bilməyəcəyin resursları aça bilər.
4. **Döyüşləri seç.** Məhdud siyasi büdcən var. Onu əsl vacib şeylərə xərclə. Kiçik şeylərə məhəl qoyma.
5. **"Yes" deməyi asan et.** İstəyi mümkün olan ən kiçik addıma endir. 1 saatlıq review almaq bir aylıq buy-in-dən asandır.

## Konkret skript və template (Scripts & templates)

### 1:1 "iclasönü" skripti (design review-dan əvvəl buy-in almaq)
> "I'm putting together a proposal for [X] for next week's design review. I wanted to run it by you first — both to get your input and to know if you see concerns I should address in the doc. Do you have 20 minutes this week?"

### Təklif pitch (qısa — Slack və ya sürətli söhbət üçün)
```
Problem: [1-2 sentences]
Why now: [1-2 sentences]
Proposal: [1-2 sentences]
Ask: Review the doc (link) and tell me if you'd support it. 30 min commitment.
```

### Sponsor tapmaq
> "I'd like to propose [X] across the platform. Before I write it up, I want to know if you'd sponsor it — meaning you'd weigh in during calibration / leadership discussions. I'd do the writing and the work. I just need someone senior to back it when the question comes up."

### Müxtəlif auditoriyalar üçün çərçivələmək
- **Mühəndislik direktorlarına:** "This reduces tech debt and unblocks [team A] and [team B]."
- **Product-a:** "This lets us ship the [feature] 2 weeks faster."
- **Öz komandana:** "This removes a daily pain point — you'll spend less time on [annoying thing]."

### İdeya tutmayanda (otağı oxumaq)
> "I want to check — is there a reason this isn't getting traction? Am I missing context, or is the timing wrong?"

### İdeyanın ölü olduğunu qəbul etmək
> "I hear this isn't the right time. I'll step back. Can I check in on this again in 6 months if the situation changes?"

### Başqaları vasitəsilə təsiri artırmaq
> "I've been thinking about [X] for a while. [Other senior engineer], you mentioned it too. Want to co-write the RFC? Two names on it gives it more weight."

### Standartı dəyişmək üçün yazı (geniş komandaya email)
```
Subject: Proposal: Standardize on [X]

TL;DR: I propose we standardize on [X] across services. Rationale below.

Current state:
- Team A uses [option 1]
- Team B uses [option 2]
- Inconsistency causes [problem]

Proposal:
- Adopt [X] as the default.
- Migration: opt-in over 2 quarters.

Asks:
- Tech leads: reply by [date] with support / concern / questions.
- If silence by [date], we'll treat as tacit approval and move forward.
```

## Safe phrases for B1 English speakers
- "I'd like your input on something I'm working on." — açılış
- "Before I take this to the wider group, I want your view." — iclasönü
- "Would you support this if I wrote it up?" — marağı yoxlamaq
- "What's the minimum version that would work?" — istəyi azaltmaq
- "Who else should I talk to?" — stakeholderləri xəritələmək
- "Can you sponsor this?" — dəstək istəmək
- "I see this as high-impact because..." — dəyəri çərçivələmək
- "I'm not asking for a decision today." — təzyiqi azaltmaq
- "Let me know if the timing is wrong." — asan çıxış yolu vermək
- "I'll do the work — I just need your backing." — onların maya dəyərini azaltmaq
- "Have I made the case clearly?" — feedback istəmək
- "Is there anything blocking you from saying yes?" — etirazları üzə çıxarmaq
- "What would change your mind?" — müqavimətlə işləmək
- "I'll write a 1-pager and share it Friday." — hərəkətə söz
- "I appreciate you hearing me out." — hörmətlə bağlamaq

## Common mistakes / anti-patterns
- Böyük iclasda qalib gəlməyə çalışmaq. Sən iclasdan əvvəl qazanmış olmalıydın.
- Yazılı artefakt yoxdur. Yalnız söhbətlərdə təqdim etmək. Ölür.
- Eyni ideyanı hər iclasda itələmək. İnsanlar səni eşitməyi dayandırır.
- Stakeholderləri xəritələməmək. Səhv insana getmək.
- Etirazlara məhəl qoymamaq. Onlar yox olmur — kalibrasiya zamanı üzə çıxır.
- Orijinal versiyana çox bağlı qalmaq. Təsir güzəşt deməkdir.
- Krediti özünə yığmaq. Paylaşılan kredit = daha geniş koalisiya.
- Təklif vermək əvəzinə şikayət etmək. Seniorlar təklif verir.
- Kiçik addımlar əvəzinə böyük partlayış təklifləri. "Redesign everything" uğursuz olur. "One small test" uğurlu olur.
- Davamını gətirmə. "Təklif etdim, amma onlar hərəkət etmədi" — niyə olduğunu soruşdun? İterasiya etdin?

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "How do you drive change when you don't have authority?"
- "Tell me about a time you influenced a decision across teams."
- "Describe a time you proposed something that wasn't accepted."

Cavab planı:
1. **Prinsip:** "Influence is about trust, evidence, and making it easy to say yes. I write things down and get buy-in in 1:1s before the big meeting."
2. **Nümunə:** "I wanted our team to move to trunk-based development. I knew I couldn't just mandate it. I wrote a 2-page RFC, had 1:1 chats with three senior engineers and the manager, addressed each concern in the doc, then proposed it in design review. By the meeting, it was already supported — the meeting formalized it."
3. **Uğursuzluq nümunəsi:** "I once proposed killing a legacy service. The director said no. I pushed again a month later — same answer. I let it go. Six months later the org changed and the service was killed. The lesson: I spent political budget pushing when the timing was wrong."
4. **Nəticə çərçivəsi:** "Influence is a long game. I measure it in decisions made, not meetings won."

## Further reading
- "Staff Engineer" by Will Larson
- "The Staff Engineer's Path" by Tanya Reilly
- "Influence: The Psychology of Persuasion" by Robert Cialdini
- "Crucial Conversations" by Patterson, Grenny, McMillan, Switzler
- "Getting to Yes" by Roger Fisher and William Ury
- "The Hard Thing About Hard Things" by Ben Horowitz
