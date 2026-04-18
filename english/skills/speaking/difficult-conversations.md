# Difficult Conversations — Çətin Söhbətlər

## Bu Fayl Haqqında

İş mühitində bəzən çətin söhbətləri etməlisən:
- Kiminsə sözünə "yox" demək
- Pis xəbər vermək
- Münaqişə həll etmək
- Qərarla razılaşmamaq
- Şikayət etmək (kibar şəkildə)
- Gözlənilməz işi rədd etmək

Azərbaycan mədəniyyətində çoxlu insan bu söhbətlərdən qaçır və ya həddindən artıq birbaşa gedir. İngilis dilində **peşəkar peşəkar** - nə kobud, nə də həddindən artıq yumşaq.

Bu fayl sənə çətin söhbətləri **professional, rəsmi və səmimi** şəkildə keçirməyi öyrədəcək.

---

## 1. "Yox" Demək (Saying No)

Ən çətin bacarıqlardan biri. "Yox" demədiyin hər şey "bəli" sayılır və səni overload edir.

### Prinsiplər:
1. **Səbəb ver** — kontekst kömək edir
2. **Alternativ təklif et** — əgər olar
3. **Qısa saxla** — uzun izahat zəiflik səsləndirir

### Formal (Menecer, rəhbər):
- "Unfortunately, I won't be able to take this on. I'm already committed to [X]."
- "I appreciate you thinking of me, but I'll have to pass on this one."
- "I want to give this the attention it deserves, and right now I can't. Can we revisit next month?"
- "With my current workload, taking this on would mean dropping something else. Which would you prefer?"

### Peer (həmkarın):
- "I'd love to help, but I'm slammed this week."
- "I can't take this on right now — sorry!"
- "Can I help in a smaller way? I don't have capacity for the whole thing."

### Alternativ təklif:
- "I can't do it this week, but I could start on Monday."
- "I can't handle the whole thing, but I could take part [X]."
- "I'm not the right person, but have you asked [name]?"

### Praktik ssenari:

**Menecer:** "Can you take on this new project? It starts Monday."

**Zəif cavab:**
❌ "Okay, sure." *(sonra 70-saatlıq həftə başlayır)*

**Güclü cavab:**
✅ "Let me think for a moment. I'm currently working on the payments migration (ETA end of month) and the security audit. Taking this on would mean one of those slips. Which would you want me to deprioritize?"

### "Yox" deməyin Riski:
**İş yerinin tələb etdiyindən çox işləmək** = uzunmüddətli **burnout, şərt itirmək, keyfiyyət aşağı**. "Yox" deməyi öyrənmək inkişaf əlamətidir.

---

## 2. Pis Xəbər Vermək (Delivering Bad News)

Layihə gecikdi, release düşdü, sistem pozuldu — bildirmək lazımdır.

### Prinsiplər:
1. **Tez bildir** — gizlətmə
2. **Fakt + kontekst** ver
3. **Niyə olduğunu izah et** (blaming yox, səbəb)
4. **Həll yolu təklif et**
5. **Sualları cavabla**

### Formula:
> "I wanted to share [bad news]. Here's what happened: [context]. Here's what we're doing: [plan]. Here's when you'll know more: [timeline]."

### Nümunələr:

**Layihə gecikir:**
> "I want to flag that the payment migration will be late. We're tracking to finish 2 weeks after the original deadline. The main reason is we underestimated the data migration complexity — it requires more testing than we thought. Here's what we're doing: I've brought in a second engineer to help, and we're focusing only on the critical path. I'll send a weekly update. Happy to discuss if you have questions."

**Bug production-a çatdı:**
> "Heads up — we have a bug in production affecting about 5% of checkout flows. It was introduced in today's deploy. The on-call team is rolling back now. Impact: users can't complete purchases. ETA for recovery: ~30 minutes. I'll send a postmortem tomorrow."

**Bir insan işdən çıxarıldı:**
> "I have some difficult news. [Name] won't be continuing with the team. I can't go into all the details, but I want to thank [him/her] for the contributions. [Practical info — what happens next]. Please reach out to me directly if you have questions."

### Açar ifadələr:
- "I wanted to flag something..."
- "I have some unfortunate news..."
- "I need to let you know..."
- "Unfortunately, ___."
- "I want to be upfront about ___."
- "Here's where we stand: ___."
- "What we're doing about it: ___."

### Saxınılmalı:
- ❌ "It's not that bad." *(minimize etmə)*
- ❌ "It wasn't our fault." *(blame oynama)*
- ❌ Uzun izahat (5 abzas niyə olduğu)
- ❌ Həll təklifi yoxdur

---

## 3. Münaqişə Həlli (Conflict Resolution)

Kolleqa ilə mübahisən var — kod, qərar, və ya yanaşma haqqında.

### Pilləli Addımlar:

#### Pillə 1: Şəxsi (1-on-1) söhbət
- Başqasının qarşısında kritika etmə
- Yuxarı qalxmaq əvəzinə əvvəl özlərinizlə danış

#### Pillə 2: Əgər həll olmazsa, yumşaq tərəfə müraciət
- Menecer və ya team lead
- "I'd like your perspective on a disagreement [name] and I are having."

#### Pillə 3: Yazılı / rəsmi
- HR, formal mediasyon
- **Nadir istifadə et**

### Şəxsi söhbət üçün struktur:

#### 1. Neytral başlanğıc
- "Can I share something I've been thinking about?"
- "I want to clear the air about something."
- "Hey, do you have a few minutes? Just want to chat."

#### 2. "I" cümlələri işlət (You yox)
❌ "You always ignore my suggestions."  
✅ "I've been feeling like my input isn't being heard."

❌ "Your code is terrible."  
✅ "I'm struggling to review this. Could we walk through it together?"

#### 3. Konkret nümunə
- "In yesterday's meeting, when ___ happened..."
- "Looking at this specific PR..."
- "A specific example: ___"

#### 4. Qarşı tərəfi dinlə
- "What's your take?"
- "How did it look from your side?"
- "Help me understand your perspective."

#### 5. Həll axtar
- "How can we work on this together?"
- "What would work better for you?"
- "Can we try [specific change]?"

### Praktik ssenari:

**Sən:** "Hey Mike, got a minute? I wanted to talk about something that's been on my mind. In the last few code reviews, I felt like my suggestions weren't really considered — you pushed back pretty hard on most of them. I'm not saying you're wrong; I just want to understand your perspective. What's going on from your side?"

**Mike:** "Oh, I hadn't noticed. I think I've been stressed about the deadline and maybe I wasn't paying enough attention."

**Sən:** "That makes sense. Here's what might work: if we could do live review sessions once a week instead of all async, I think we could talk through disagreements faster. What do you think?"

---

## 4. Qərarla Razılaşmamaq

Menecerin, komandanın, və ya stakeholder-in qərarı ilə razılaşmırsan.

### Principle: "Disagree and commit"
Bu çox məşhur prinsipdir (Amazon mədəniyyəti):
1. Razılaşmadığını aydın bildir
2. Dinlənilir
3. Qərar verilir (sənin xeyrinə və ya əksinə)
4. Sənin mövqeyin qalib gəlmədisə belə, komanda qərarına tam dəstək ver

### Pillə 1: Rəsmi olmadan razılaşmama bildir
- "I hear the decision, but I want to share some concerns."
- "I'd like to push back on this, if that's okay."
- "Can I share a different perspective?"
- "I see it differently, and I want to make sure you have my input."

### Pillə 2: Səbəblər ver (emosiya yox)
- "My main concern is [X]."
- "I think we're underestimating [risk]."
- "In my experience, [pattern]."
- "Have we considered [alternative]?"

### Pillə 3: Qərar verildikdən sonra
**Əgər qərar senin xeyrinə dəyildi:**
- "Understood. I still have my doubts, but I'll execute fully."
- "Got it. I disagree, but I'll commit."
- "Okay, I'll put my full effort into making this work."

### Yanlış yanaşma:
❌ "Okay..." *(passive-aggressive)*  
❌ "I told you so." *(3 ay sonra)*  
❌ Sabotaj (intentionally bad effort)

### ⚠️ Nə vaxt eskalasiya et:
- Qərar **etik məsələ**dirsə
- **Qanun pozuntusu** varsa
- **Zərər böyükdür** və sən yazılı olaraq bildirməlisən

Amma adi iş qərarlarında: disagree, then commit.

---

## 5. Şikayət Etmək (Kibar şəkildə)

Problem var — texniki borc, menecer yanaşması, iş mühiti.

### Struktur: Fakt + Təsir + Təklif

### Formul:
> "I've noticed [fact]. This is causing [impact]. Could we [suggestion]?"

### Nümunələr:

**Yavaş dev environment:**
> "I wanted to bring something up. Our dev environment has been pretty slow — builds taking 20+ minutes. Over the last week, I've probably lost 2-3 hours of productive time waiting. Could we allocate a sprint to investigate? I'm happy to lead it."

**Çox meeting:**
> "One observation — my calendar has been almost fully booked with meetings, which is making it hard to do deep work. In the last 2 weeks, I had 4 days with no 2-hour focus blocks. Could we try a 'no-meeting Wednesday' and see how it goes?"

**Menecer praktikasi:**
> "Something I've been thinking about: when we chat in 1-on-1s, I sometimes feel like the topics jump around quickly. Could we structure them more? Maybe a shared agenda in advance — 3 bullet points each? That way I'd feel more prepared."

### Saxınılmalı:
- ❌ "Nothing works here."
- ❌ "Everyone hates [person]."
- ❌ Şəxsi şikayət, sistem şikayəti yox

### Uğurlu şikayətin xüsusiyyətləri:
- Spesifik
- Fakt-əsaslı
- Həll-yönümlü
- Peşəkar ton

---

## 6. Qarşılaşılan İnsan Tipləri

### 1. "Şiddətli-birbaşa" (Blunt)
**Necə yanaşmalısan:**
- Faktlara fokuslaş
- Emosiyalara girmə
- Qısa yaz

**İfadələr:**
- "Got it. My position is ___."
- "I disagree, and here's why: ___."

### 2. "Passiv-aqressiv"
**Necə yanaşmalısan:**
- Konkret misallar tələb et
- "Tell me more" — aydınlaşdır
- Yazılı qeyd saxla

**İfadələr:**
- "I'm sensing you're not happy with X. Could we talk about it directly?"
- "It sounds like there's something else going on. What's up?"

### 3. "Sorğusuz-sualsız razılaşan"
**Necə yanaşmalısan:**
- Dərinlik soruş: "What would you do differently?"
- Fikirlərinə çek: "Do you really agree, or are you just being polite?"

**İfadələr:**
- "What do you really think?"
- "If you were to push back, on what would you?"

### 4. "Həmişə qaçan" (Avoidant)
**Necə yanaşmalısan:**
- Yazılı məkana al
- Konkret suallar ver
- Deadline təyin et

**İfadələr:**
- "Could you respond by Friday?"
- "I need your input here before I can move forward."

### 5. "Üstün olmaq istəyən" (Controlling)
**Necə yanaşmalısan:**
- Seçim ver, biri deyil
- Fikirə təslim olma
- Öz territorini qoru

**İfadələr:**
- "I see a few options — which would you prefer?"
- "That's one approach. I'd also like to consider ___."

---

## 7. Emosiyanın Daxili Olduğu Anlarda

Başqa adam qışqırır, sən qəzəblisən, ya narahat olursan — ingiliscə emosiya idarə etmək.

### Sakitləşdirici ifadələr (özünə):
- "Let me take a minute."
- "Let's pause and come back to this."
- "I need a moment to think."
- "Can we step away and reconvene in 10?"

### Başqasını sakitləşdirmək:
- "I can see this is important to you."
- "I hear you. Let me make sure I understand."
- "That's a fair concern."
- "I see where you're coming from."

### ⚠️ Qaçın:
- "Calm down." *(əks təsir)*
- "You're being emotional." *(dismissive)*
- "Let me explain why you're wrong." *(fight mode)*

### Emosional qarşıdurma sonrası:
- "I appreciate you sharing that. Let me think about it."
- "Thank you for being honest. I'll consider what you said."
- "We might not agree, but I respect your position."

---

## 8. Menecerə "Daha çox pul istəyirəm" Demək

Məktəblər bunu öyrətmir, amma iş mühitində kritikdir.

### Zamanlama:
- Performance review vaxtı (təbii zamandır)
- Böyük layihəni bitirdikdən sonra
- Yeni məsuliyyət götürdükdən sonra
- **Yox:** kompaniya çətin vaxtında, layihədə uğursuzluqdan sonra

### Formula:
1. Nailiyyətlərini göstər
2. Market research ver
3. Konkret rəqəm istə
4. Alternativə açıq ol

### Nümunə:
> "I wanted to talk about my compensation. Over the last year, I've [specific achievements — led X, delivered Y, mentored Z]. I've done some market research, and similar roles in the market are paying [range]. I'd like to discuss bringing my salary closer to that range.  
>   
> I understand budget considerations. If a raise isn't possible immediately, I'm also open to discussing [alternatives — bonus, more equity, a promotion path]. What's the best way to think about this?"

### Açar ifadələr:
- "I wanted to discuss my compensation."
- "Over the last [period], I've [accomplishments]."
- "Based on market research..."
- "I'd like to have a conversation about..."
- "I understand [constraint]. What alternatives might work?"

### Menecer "yox" deyirsə:
- "What would I need to demonstrate to earn that in the future?"
- "Can we set a specific timeline to revisit?"
- "Thanks for the honest answer. I'll think about next steps."

---

## 9. Toksik Menecer / İş Mühiti

Bunlar nadir amma olur. İngiliscə necə reaksiya:

### Micromanagement:
"I've noticed you've been checking in on me multiple times a day. I'm committed to delivering quality work, and I'll proactively flag any issues. Could we agree on a regular check-in time instead?"

### Açıq tənqid (toplu):
"Could we have this conversation privately? I'd like to address the feedback, but not in front of the team."

### Heç vaxt razı qalmayan:
"I'd love to understand what 'good' looks like here. The feedback I've been getting has been vague — can we define specific criteria?"

### Şəxsi sərhəd pozuntuları:
"I'm happy to take on extra work when it's needed, but messaging me at 10 PM puts me in an awkward position. Could we agree that after-hours messages can wait until the next morning unless it's an outage?"

### Ağır hallarda: HR-a müraciət

**HR-ə yazmaqda ifadələr:**
- "I'd like to raise a concern confidentially."
- "I've been experiencing [situation]."
- "I have documented examples. Here they are: [list]."
- "I'd like to understand my options and next steps."

---

## 10. Müsbət Nümunə Söhbətlər — Dialoq

### Ssenari 1: Yeni layihə rədd
```
Manager: "Hey, can you take over the new reporting project? 
          Starts Monday."

You: "Let me understand the scope — what's the size of this one?"

Manager: "Probably 6 weeks full-time."

You: "Thanks for thinking of me. Right now, I'm committed to 
      finishing the payments migration (3 more weeks) and the 
      Q2 planning doc. Taking this on full-time would mean one 
      of those slips. What would be best for the team?"

Manager: "Hmm, migration is higher priority. Can you do the 
          reporting project part-time?"

You: "Part-time isn't ideal — it'll stretch to 12 weeks and 
      neither will be great. Could we find someone else to own 
      reporting, and I could advise on architecture?"

Manager: "Let me think. Good point."
```

### Ssenari 2: Pis xəbər stakeholder-a
```
Stakeholder: "Where are we on the launch?"

You: "Honestly, we're behind schedule. The original date was 
      April 15. We're now tracking to April 30."

Stakeholder: "That's a problem. Why?"

You: "Two main reasons. First, the third-party API integration 
      was more complex than estimated — there were edge cases we 
      didn't see until we started. Second, QA found critical 
      issues in the first round of testing, and fixing them 
      properly took time."

Stakeholder: "What are we doing about it?"

You: "A few things. I've moved QA in parallel with development 
      starting this week to catch issues earlier. I'm also 
      pulling in another engineer to help with the final testing. 
      My best estimate for the new date is April 30. I'll send 
      a written update every Friday until we ship."

Stakeholder: "Okay. Thanks for being direct."
```

### Ssenari 3: Həmkarla qərar müzakirəsi
```
Colleague: "We should definitely use Kafka for this."

You: "I'm not sure Kafka is the right tool here. Help me 
      understand your reasoning."

Colleague: "It's fast and scalable."

You: "Agreed, but I'd like to make the case for RabbitMQ. Our 
      team has operated RabbitMQ for years — we know its failure 
      modes. Kafka would be a new operational burden, and the 
      scale we need (1K msgs/sec) doesn't require Kafka-level 
      throughput."

Colleague: "But Kafka has better durability."

You: "True, but for our use case, we don't need the event log 
      feature. What if we start with RabbitMQ and switch to Kafka 
      if we outgrow it?"

Colleague: "I could live with that. Let me think about it."
```

---

## 11. "After" Söhbət — Qeydlər və Follow-up

Çətin söhbətdən sonra:

### Öz qeydini tut:
- Nə müzakirə edildi?
- Hansı qərarlar verildi?
- Nə vaxt sonrakı addım?

### Yazılı xülasə göndər (həssas mövzularda):
```
Hi [Name],

Thanks for the conversation today. Just to make sure we're 
aligned, here's my understanding:

1. We agreed [decision 1]
2. [You/I] will [action by date]
3. We'll revisit [topic] by [date]

Let me know if I missed anything.

Thanks,
[You]
```

Bu qeyd **məsuliyyəti paylaşır** və anlaşılmazlığın qarşısını alır.

---

## 12. Tonal Differences — Çətin Söhbətlərdə

### "I'm sorry" — vaxtında istifadə et
- "Sorry for missing the deadline." ✅ (real səhv)
- "Sorry for asking." ⚠️ (özünü aşağı saldın)
- "I'm sorry you feel that way." ❌ (həqiqi üzr yox)

### "It's fine" — əksəriyyət hallarda yalan
- Əgər "it's fine" deyirsənsə amma fine deyil — konkret et.
- Daha yaxşı: "I'm frustrated because ___."

### "With respect" — ehtiyatla
- "With all due respect, ___." — bəzən sarkastik səsləndirir
- Daha təbii: "I see it differently" və ya "Respectfully, ___"

---

## 13. Kültürel Fərqlər

### Azərbaycan ilə müqayisə:

**Birbaşalıq:**
- Azerbaycan: orta-yüksək birbaşalıq normaldır
- Qərb iş mühiti: dolayı, "sandwich" yanaşma daha təbii

**Rəsmilik:**
- Azerbaycan: yaşdan asılı (böyük = çox rəsmi)
- Qərb: rol və yaxınlıqdan asılı, yaş çox az faktor

**Əlaqə qurmaq:**
- Azerbaycan: uzun salamlaşma, şəxsi mövzu
- Qərb: iş söhbəti daha tez başlayır, şəxsi sərhədlər daha dəqiq

### Adaptasiya:
- Kiçik small talk ilə başla (5 dəqiqə maksimum)
- Faktlara tez keç
- "I" dilində danış, "we" deyil

---

## 14. Ən Çox Saxlanılmalı Səhvlər

### ❌ Emosional reaksiya
"That's ridiculous!" → "I see it differently."

### ❌ Ümumiləşdirmə
"You always..." → "In this specific case..."

### ❌ Passive-aggressive
"As we discussed." (əslində heç vaxt müzakirə olmamışıq)  
→ Birbaşa: "I think we need to clarify ___."

### ❌ Üzrlər
"Maybe it's just me, but possibly we could consider, if you don't mind..."  
→ "I'd like to propose ___."

### ❌ İnkar
"It's not a problem." (əslində problemdir)  
→ "This is becoming an issue. Let's address it."

---

## 15. Hazırlıq — Çətin Söhbətdən Əvvəl

Böyük söhbətdən əvvəl 10 dəqiqə hazırlıq et:

### Sualları öz-özünə ver:
1. **Mənim məqsədim nədir?** (konkret qərar, razılaşma, məlumat paylaşma?)
2. **Qarşı tərəfin məqsədi nədir?** (onlara nə lazımdır?)
3. **Ən pis nəticə nədir?** (və onunla necə məşğul olacaqsan?)
4. **Ən yaxşı nəticə nədir?** (onu necə çatdıracaqsan?)
5. **Hansı sözlərdən qaçmaq istəyirəm?** (triggers)

### 3 əsas ifadə hazırla:
- "Açılış cümləsi"
- "Əsas mesaj"
- "Bağlanış / next step"

---

## 16. Güclü Cümlə Şablonları

### Söhbət başlamaq
- "I want to bring something up."
- "Can we talk about ___?"
- "I've been thinking about ___, and I wanted to share."

### Fikir bildirmək
- "My perspective is ___."
- "I think we should consider ___."
- "What I'm feeling is ___."

### Sual vermək
- "Help me understand ___."
- "How do you see it?"
- "What would you suggest?"

### Razılaşma axtarmaq
- "Can we agree that ___?"
- "What works for both of us?"
- "Let's find a way forward."

### Bağlanış
- "Thanks for being open to this."
- "I feel better having talked."
- "Let's check in on this [specific time]."

---

## 17. Praktik Məşq Planı

### Həftə 1: Kiçik "yox"-lar
- Hər gün 1 kiçik şeyə "yox" de (əlavə task, meeting, məsuliyyət)
- Əməkdaşla razılaşmama sözləri məşq et

### Həftə 2: Fikir bildirmək
- Meeting-də ən azı 1 dəfə fərqli fikir bildir
- Menecerə 1 şikayət / təklif ver

### Həftə 3: Sərhədlər
- Messaging vaxtını müəyyən et
- Həddindən artıq iş istəyinə "yox" de

### Həftə 4: Böyük söhbət
- Qeyd saxladığın 1 məsələni həll etmək üçün söhbət tut
- Nəticəsi nə olsa olsun, uğurla bitirdin

---

## Əlaqəli Fayllar

- [Polite English](polite-english.md)
- [Giving Feedback](giving-feedback.md)
- [Agreeing/Disagreeing](agreeing-disagreeing.md)
- [Meeting / Standup English](meeting-standup-english.md)
- [Salary Negotiation](salary-negotiation.md)
- [Conversation Strategies](conversation-strategies.md)
