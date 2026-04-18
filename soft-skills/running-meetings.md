# Running Meetings

## Niyə vacibdir? (Why it matters)
Pis iclaslar komandanın təqvimində ən bahalı şeydir. 8 mühəndisin agenda-sız 1 saatlıq iclası şirkətə bir günlük mühəndislik dəyərindədir. Sıx iclas aparan senior mühəndis bütün komandaya həftədə saatlar qazandırır. Səliqəsiz iclas aparan senior mühəndis məhsuldarlığı aktiv şəkildə dağıdır — hətta iclas "doğru mövzu haqqında" olsa belə.

İclas aparmaq həm də görünürlük anıdır. Direktorlar və stakeholderlər kiminsə "senior material" olduğunu qismən iclası necə apardığına baxıb qərar verir.

## Yanaşma (Core approach)
1. **Agenda yoxdursa, iclas yoxdur.** Hətta təqvim dəvətində 3 bəndli agenda kifayətdir.
2. **Hər maddəni timebox et.** Standup 15 dəqiqədirsə və bloklarda 13-cü dəqiqəyə çatırsansa, qalanını park et.
3. **Action item-lərlə bitir.** Kim nəyi nə vaxta qədər edəcək. İclas action item-siz bitirsə, bu qərar iclası deyil, status update idi.
4. **Əvvəlcə yazılı üsul seç.** Bunu 3 paraqraflıq sənədə çevirmək olardımı? 60% halda: bəli.
5. **Kiçik iclaslar daha yaxşıdır.** Dinləyici yox, qərar verənləri dəvət et. 15 nəfər varsa, bu yayım deyil, iclas deyil.

## Konkret skript və template (Scripts & templates)

### Standup (maksimum 15 dəq)
Hər kəs cavab verir:
1. Dünən nə etdim
2. Bu gün nə edəcəm
3. Bloklar / kömək lazım olan yer

Apar­ma skripti:
> "Let's do a quick round. Please keep updates under 1 minute. If something needs discussion, we'll park it for after standup. [After round] Anyone who wants to go deeper, stay; everyone else can drop."

### Sprint planning (2 həftəlik sprint üçün 1 saat)
Agenda:
1. Sprint məqsədi (5 dəq) — nəyə nail olmağa çalışırıq
2. Capacity check (5 dəq) — kim yoxdur, kim 50%-dədir
3. Story walkthrough (30 dəq) — hər maddəni scope et
4. Commit (10 dəq) — nə sığır, nə sığmır
5. Parking lot (10 dəq) — risklər, asılılıqlar

Apar­ma:
> "Our capacity this sprint is roughly 25 points. We've brought in 32. Which three of these do we want to move to next sprint?"

### Design review (1 saat)
Agenda:
1. Author 5 dəqiqəlik kontekst verir (problem, məqsədlər, qeyri-məqsədlər)
2. 10 dəq sənədi async oxumaq (bəli, iclasda — insanlar oxumayıb)
3. 40 dəq suallar, müzakirə, qərarlar
4. 5 dəq action item-lər və növbəti addımlar

Apar­ma:
> "Before we discuss, let's take 10 minutes to read the doc. Add comments as you go. Then we'll discuss the top open questions."

### Retro (45 dəq)
Agenda:
1. Nə yaxşı getdi (10 dəq)
2. Nə yaxşı getmədi (15 dəq)
3. Növbədə nəyi sınayaq (15 dəq)
4. Action item-lər (5 dəq) — adı olan sahib, son tarix

Apar­ma:
> "Rule: we focus on process, not people. Let's keep it constructive. Start with what went well, even small things."

### İclas dəvət şablonu
```
Subject: [Decision] Caching strategy for order API
Attendees: [names, minimal set]
Duration: 30 min
Goal: Decide between Redis vs in-memory LRU for order caching.
Pre-read: [link to 1-page doc]
Agenda:
- 5 min — context
- 15 min — options and trade-offs
- 10 min — decision + action items
```

### İclası bağlamaq
> "Let's recap. We decided A. Maria owns the spec, due Friday. Open question: B, we'll handle async in the channel. Thanks everyone."

### Agenda-sız iclası rədd etmək
> "Happy to join — what's the goal? If it's a status update, I can send mine async."

## Safe phrases for B1 English speakers
- "Let's start with the goal of this meeting." — açılış
- "We have 30 minutes — let's move." — sürət təyin etmək
- "Can we timebox this to 5 minutes?" — yayınmanı idarə etmək
- "Let's park that for now." — mövzudan kənar müzakirəni dayandırmaq
- "I want to make sure we end on time." — vaxt barədə xəbərdarlıq
- "Who is the owner of this action?" — təyin etmək
- "What's the deadline for this?" — qapalı dövrə yaratmaq
- "Let's take a step back." — ilişəndə yenidən çərçivəyə salmaq
- "Can we get a decision today?" — qərara doğru itələmək
- "I'll send a summary after the call." — sənədləşməyə söz vermək
- "Let's take that offline." — iclasdan kənara çıxarmaq
- "Anyone who's not needed for the next part, feel free to drop." — vaxta hörmət
- "Can we hear from people who haven't spoken yet?" — sakit səsləri daxil etmək
- "Let's do a round." — strukturlaşdırılmış növbə ilə danışmaq
- "To summarize what I heard..." — eşitdiyini əks etdirmək

## Common mistakes / anti-patterns
- Agenda olmadan başlamaq. Hər kəsin vaxtını israf edir.
- 5 dəqiqə uzatmaq. Hər dəfə. İnsanlara iclaslarının həmişə uzadığını öyrədir.
- Yazılı qeyd yoxdur. Qərarlaşdırdığın hər şey unudulacaq.
- Bir nəfərin hakim olmasına icazə vermək. Sakit insanların yaxşı ideyaları var.
- Action item yoxdur. Onda bu iclas deyil, məruzə idi.
- Ölməli olan iclasları saxlamaq. Hər kvartalda təkrarlanan iclaslarını yoxla.
- "Görünürlük üçün" dəvət etmək. Ya töhfə verirlər, ya iştirak etmirlər.
- Canlı iclasda async ola biləcək status update-lər etmək.
- Vaxtında başlamamaq. Vaxtında gələn 8 nəfərə hörmət et.
- Strategiya iclasında implementasiya detallarına dalıb qalmaq.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "How do you run a design review?"
- "How do you run a retro?"
- "What do you do when a meeting runs off-topic?"
- "How do you handle a dominating voice in a meeting?"

Cavab planı:
1. **Prinsip:** "I default to 'no agenda, no meeting' and end every meeting with action items."
2. **Konkret nümunə — design review:** "I start with 10 minutes of silent reading. People haven't read the doc. After that we jump to questions with a timebox per section."
3. **Hakimiyyəti idarə etmək:** "I use structured rounds — 'let's hear from people who haven't spoken'. It creates space without calling anyone out."
4. **Qərarlar:** "I won't end without a named owner and a date. Otherwise I kick it to async."

## Further reading
- "Death by Meeting" by Patrick Lencioni
- "The Art of Gathering" by Priya Parker
- "High Output Management" by Andy Grove (on meeting types)
- "Scrum: The Art of Doing Twice the Work in Half the Time" by Jeff Sutherland
- Basecamp's Shape Up methodology (free online)
