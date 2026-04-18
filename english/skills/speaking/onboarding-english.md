# Onboarding English — İlk Həftələr İngiliscə

## Bu Fayl Haqqında

Yeni işə başlamağın ilk 2-4 həftəsi — sən öyrənirsən, suallar verirsən, komanda mədəniyyətinə alışırsan. Bu mərhələdə **yanlış sualların olmadığını** xatırla; amma **necə** soruşmaq önəmlidir.

Bu fayl sənə **professional, təvazökar amma özünə güvənli** şəkildə ilk həftələri keçirməyi öyrədir.

---

## 1. İlk Gün — Təqdim Olunma

### Özünü təqdim et:

**Komandaya birinci dəfə:**
> "Hi everyone! I'm Orkhan, joining as a senior backend engineer. I've worked at [previous company] for 4 years, mainly on payment systems. I'm really excited to be here, and I'll probably have a lot of questions in the next few weeks — please bear with me!"

**İndividual həmkarla:**
> "Hi, I'm Orkhan — I just joined the backend team. Nice to meet you!"

### Açar ifadələr:
- "I just joined [team / company]."
- "I'm new — today's my first day."
- "Please bear with me — I'm still ramping up."
- "I'll probably have a lot of questions."
- "Nice to meet you!"

### ⚠️ Saxınmalı:
- ❌ "I don't know anything yet." (özünü aşağı salma)
- ❌ Həddindən artıq rəsmi ("Good day, sir")
- ❌ Həddən artıq iddia ("I'm the new expert")

---

## 2. Setup Günlərində — Texniki Problemlər

### Kömək istəmək:
- "Quick question — how do I [setup step]?"
- "I'm stuck on [specific step]. Any tips?"
- "I followed the onboarding doc, but I got [error]. Has anyone seen this?"
- "Sorry to bother — I'm setting up my dev environment and hit a wall."

### IT-dən kömək:
- "I'm a new hire. Could you help me set up [VPN / email / laptop]?"
- "My access isn't working for [system]. Who should I contact?"
- "Could you add me to [channel / group]?"

### Nümunə:
> "Hi — new hire here (started Monday). I'm trying to clone the main repo but getting a permission error. Could you check my access? My GitHub handle is orkhanaliyev."

---

## 3. İlk Toplantılarda

### Onboarding görüşü:
- "Thanks for taking the time to chat with me!"
- "I'd love to learn more about [team / project]."
- "Could you walk me through [topic]?"
- "I have a few questions — is it okay if I ask as we go?"

### Team meeting-ə ilk dəfə:
- "Hi, I'm Orkhan — just wanted to introduce myself. I'll mostly be listening today."
- "I'm new to the team, so I might ask basic questions — apologies in advance."

### Standup-a ilk dəfə:
> "Yesterday was my first day, so just getting set up. Today, I'll be reading documentation and probably setting up my environment. No blockers — but happy to accept any reading recommendations!"

---

## 4. Sual Vermək — Sanat

### Qayda: Aydın, kontekstli, və "mən artıq cəhd etdim" göstər

### ❌ Zəif sual:
> "How do I deploy?"

### ✅ Yaxşı sual:
> "Hi — I'm trying to deploy my first change to staging. I looked at the onboarding doc and ran `npm run deploy:staging`, but got [specific error]. Did I miss a step?"

### Sualın strukturu:
1. **Nə etməyə çalışırsan?**
2. **Nə etdin?**
3. **Nə baş verdi?**
4. **Konkret sualın nədir?**

### Praktik nümunə (Slack):
```
Hey team! Quick q for my first PR.

What I'm doing: Adding a new field to the User model.

What I did: 
- Created a migration in /migrations
- Updated the model
- Ran `npm run migrate`

What I got: 
- Error: "relation users already exists"
- Dev DB seems to be out of sync

Question: Is there a standard way to reset the dev DB? 
Or should I just drop the table and re-run?

Thanks!
```

### Açar ifadələr:
- "I'm trying to ___."
- "I've already tried ___."
- "I'm not sure if ___."
- "Could someone point me in the right direction?"

---

## 5. "Əptal Görünməkdən" Qorxma

Yeni işə girənlərin ən böyük səhvi: başa düşmədiyini gizlətmək. **Səhvdir** — çünki bu sonrakı səhvlərə gətirir.

### "Düşündüyümü yoxlayım" ifadəsi:
- "Let me make sure I understand. You're saying ___?"
- "Just to clarify — we want to [X], right?"
- "I want to paraphrase to check my understanding."

### "Açıq soruşmaq":
- "I'm not familiar with [term]. Could you explain?"
- "This is new to me. What does [X] mean?"
- "I'm missing some context here. Could you back up?"

### Rhetorical:
- "Sorry if this is a basic question, but ___."
- "Forgive the naive question — ___?"

⚠️ Amma **həmişə** "sorry if this is basic" demə — bir dəfə kifayətdir, sonra birbaşa soruş.

---

## 6. Dokumentasiya Oxuyarkən

### Aydın olmayanda:
- "The docs mention [X] but don't explain [Y]. Is there more context?"
- "I read the architecture doc — still unclear on how [service A] talks to [service B]."
- "Could you point me to where [X] is documented?"

### Zidd məlumat olanda:
- "The doc says [X], but the code seems to do [Y]. Which is correct?"
- "I'm seeing conflicting info in two places — could you clarify?"

### Doc olmayanda:
- "Is there any documentation on [topic]? I couldn't find it."
- "Would it be useful if I wrote a doc on [X] as I learn it?"

### Sən bunu edə bilərsən:
**Yeni gəlmişkən dokumentasiya yazmaq ən yaxşı töhvədir.** Bir şey öyrənirsən? Yaz! Sonrakı yeni işçilər faydalanır.

---

## 7. İlk Code Review-un

### Öz PR-nı göndərmək:
- "Hi team, this is my first PR. I tried to follow the team conventions, but let me know if I missed anything."
- "Happy to iterate — I know I probably have things to learn about your codebase style."
- "Please be as direct as you want in feedback — I'd rather learn now."

### Rəy alanda:
- "Thanks for the detailed review!"
- "Good point — I hadn't thought of that. Updating now."
- "Could you help me understand why X is better?" (əyər agree deyilsənsə)

### Kritika tərtib:
> Senior: "This function is doing too much. Split it."  
> You: "Got it, makes sense. Let me refactor and push a new version."

> Senior: "Our convention is snake_case, not camelCase."  
> You: "Thanks — I missed that in the style guide. Let me fix."

---

## 8. Menecerinlə İlk 1-on-1

### Hazırlığ:
- 3 sualın olsun
- Son həftə təəssüratın (qısa)
- Prioriyetlərin (necə anlayırsan)

### Açılış:
> "Thanks for making time. I'd love to hear how you see my first week going, and I have a few questions from my side."

### Soruşacağın suallar:
- "What's your top priority for me in the next month?"
- "Who are the key people I should get to know?"
- "How does success look for me at 30/60/90 days?"
- "What's your communication preference — Slack, email, meetings?"
- "Are there any landmines I should avoid?"

### Səndən gözlənilən cavab:
> "So far it's been a lot of reading and setup. I feel I have a good understanding of the codebase structure, but still need to learn our deployment process. By end of next week, I'd like to have my first PR merged and start on the reporting feature."

---

## 9. Slack / Chat İlk Həftələrdə

### Observe et, sonra iştirak et
İlk bir neçə gün komandanın tonuna, emoji istifadəsinə, mesaj uzunluğuna diqqət et. Sonra analoji davran.

### Ümumi sağ-salamat davranış:
- "Hey team!" (səhər salamlamaq normaldırsa)
- 👍 reaksiyası (mesaj oxuduğunu göstərmək)
- Emoji reasonable amount (komanda mədəniyyətinə görə)

### Mesajların tipi:
**Məhsuldar:**
- "PR #247 up for review when you have time 👀"
- "Question on X — anyone worked on this before?"
- "Shipped feature Y 🚀"

**Sosial:**
- "Happy Friday!"
- "Any weekend recommendations for [area]?"
- "Welcome [new person]!"

### ⚠️ İlk ayda qaçın:
- ❌ Çox siyasi söhbət
- ❌ Həddindən artıq meme (hələ ton bilmirsən)
- ❌ Ciddi şikayət public kanalda

---

## 10. Şirkət Mədəniyyətini Öyrənmək

### Suallara diqqət et:
- Ne vaxt meeting başlayır/bitir?
- Kamera açıq/bağlı?
- Formal /informal dil?
- Emoji istifadəsi?
- DM vs public channel?
- After-hours mesaj qəbul olunur?

### Sor:
- "What are the unwritten rules of the team?"
- "Any cultural things I should know?"
- "When do people usually take lunch?"
- "What's the deal with [specific thing]?"

---

## 11. Öyrənməyə Girmək

### Şirkət haqqında:
- "What's the history of this product?"
- "Why do we do X this way?" (ehtiyatla — "I'm curious" tonu)
- "What are the biggest challenges the team is facing?"

### Texniki:
- "Could you walk me through the architecture?"
- "How does data flow from [A] to [B]?"
- "What are the critical paths I should be careful with?"

### İnsanlar:
- "Who should I talk to about [topic]?"
- "Who owns [system]?"
- "Who's the expert on [technology]?"

---

## 12. Səhv Etmə Etiketi

Yeni işə girəndə səhv edəcəksən. Necə etmək vacibdir.

### Kiçik səhv:
- "Oops, I broke the build. Rolling back now."
- "Sorry, I merged to the wrong branch. Fixing."

### Orta səhv:
- "I want to flag that I accidentally deleted [resource]. I'm restoring from backup."
- "I pushed the wrong config. Rolling back. Apologies for the noise."

### Böyük səhv (ürəyin düşür):
- "I need to raise something. I caused an incident in production — [brief description]. I've already rolled back and it's stable. I'll write a full postmortem."

### Qaydalar:
1. **Tez xəbər ver** — gizlətmə
2. **Mən etdim** — alibi axtarma
3. **Artıq düzəltməyə başladım** — proaktiv
4. **Dərsi öyrənəcəyəm** — reflection

### Nümunə postmortem-ə başlanğıc:
> "I want to share what happened and what I'm learning from it. As a new person, this experience has been valuable — here's what went wrong, and here's what I'll do differently."

---

## 13. 30/60/90 Gün Milestones

### İlk 30 gün: Öyrən
**Hədəflər:**
- Codebase-i ümumi qavra
- Əsas insanlarla tanış ol
- 1-2 kiçik PR shipladın
- İlk 1-on-1-larını yaxşı keçirdin

**Sual:** "Do I feel supported? If not, let my manager know."

### 60 gün: Töhfə ver
**Hədəflər:**
- Orta ölçüdə feature shipladın
- Bəzi code reviews verdin
- Team-in 1-2 prosesini başa düşdün
- Öz rolunun aydın təsəvvürü var

**Sual:** "Am I on track? Ask explicitly."

### 90 gün: Müstəqil
**Hədəflər:**
- Tək başına layihə apara bilərsən
- Yeni gələnə kömək edə bilərsən
- Öz təkliflərin var (process improvement və s.)
- Rolun sənə uyğun gəlir

**Sual:** "Am I where I should be? What's the next growth area?"

### Mehribani patronluq:
Menecerinin sənə onboarding planı verməsi normaldır. Yoxdursa, özün tələb et:
> "Could we put together a 30-60-90 day plan? I'd love to have clear milestones to work toward."

---

## 14. Yeni Terminologiya — Hazır Ol

Hər şirkətin öz jargonu var. Bir siyahı saxla:

### Tipik şirkət jargonu:
- Komanda adları (SRE, DevOps, Platform...)
- Layihə kod adları (Project Phoenix, Initiative Kronos)
- Daxili alətlər (internal tools)
- Akronimlər

### Sen:
- Hər yeni termini yaz
- İzah soruş, ya da Confluence/internal wiki-də axtar
- Təbii işlət (2-3 ay sonra)

### Sual vermək:
- "I keep hearing 'Project Phoenix' — what is it?"
- "What does 'PR' mean in our context? Pull request or public relations?"
- "Could you decode this acronym: SLA, SLO, SLI?"

---

## 15. Sosial Tərəf — İntroverted Olsan Belə

### Minimum effort (amma vacib):
- Səhər salam ver
- Nahar vaxtı bəzən komanda ilə (3 ayda 1)
- Team events-də iştirak (bəzən — hamısı lazım deyil)
- Coffee chat 1-on-1-lar (yeni insanla)

### Coffee chat təklifi:
> "Hi [Name], I just joined and I'd love to learn more about your work. Are you up for a 20-minute virtual coffee sometime next week?"

### Team event-lərdə:
- "Nice to finally meet you in person!"
- "I've been working with [Name] on [project] — glad to put a face to the name."
- "How long have you been with the company?"

---

## 16. Uzaq İşləmək — Remote Onboarding

### Ayrıca çətinliklər:
- Ofis söhbətləri yoxdur
- Mədəniyyəti özünə görə tapmalısan
- İzolyasiya hissi

### Strategiya:
- 1-on-1 call-lar sor (hər həftə 1-2 fərqli adamla)
- Standup-da video açıq
- Slack-də aktiv ol (amma overwhelming olmadan)
- Şirkət kanallarına qoşul (sosial, hobbiler)

### Uzaq mənecerinlə:
- "Since we're remote, would you want me to send a weekly written update?"
- "I'd love to sync more often in the first month — is twice weekly okay?"

---

## 17. Dəyər Verən Əlamətlər

Sən dəyər yaradırsan əgər:
- İş bitirirsən (kiçik PR-lar belə)
- Proaktiv sual verirsən
- Sənədləşdirmə təklif edirsən
- Başqasına kömək edirsən (yeni gələn kimi bu çətin olar amma mümkündür)

### Töhfə nümunələri ilk ayda:
- "I noticed the onboarding doc doesn't mention [X]. Want me to add that section?"
- "I wrote up my setup issues — here's a summary for the next new hire: [link]"
- "I tested [feature] in dev — here's a small UX issue I caught."

---

## 18. Dil Çətinlikləri

İkinci dil danışan kimi, ilk həftələr qat-qat çətin gələ bilər. Bu normaldır.

### Strategiyalar:
- **Yavaş danışmağı xahiş et:** "Could you speak a bit slower? I'm still getting used to different accents."
- **Təkrarla:** "Let me make sure I heard that right — you said [X]?"
- **Yazıya keç:** "Would you mind putting this in writing? Easier for me to process."
- **Vaxt al:** "Give me a moment to think about this."

### Əsəbi olmamaq:
- Heç kimsə səninlə problem görməz
- Əksər insanlar ikinci dil danışanla çox səbrli olur
- Əsas şey — özündən əmin ol, səhvlərə əhəmiyyət vermə

### ⚠️ Nə etmə:
- ❌ Bilmədiyində "Yes" də (başa düşməmisənsə)
- ❌ Sakitləşmə (sözləri unutma)
- ❌ Öz dilində danışma (əgər komanda İngiliscə danışırsa)

---

## 19. Nümunə İlk 5 Gün Dialoqları

### Gün 1 — HR ilə giriş
```
HR: "Welcome! Let's start with the paperwork."

You: "Thanks — excited to be here. What do I need to fill out?"

HR: "I'll send you the forms. Also, here's your laptop and access 
     info. Your manager will reach out this afternoon."

You: "Sounds good. Is there a schedule for the first day I 
     should follow?"

HR: "We sent the agenda this morning. Let me know if you didn't 
     get it."
```

### Gün 2 — Team intro görüşü
```
Manager: "Hi everyone! Let's welcome Orkhan to the team. Orkhan, 
         do you want to introduce yourself?"

You: "Sure! Hi all. I'm Orkhan — backend engineer, joining from 
     [Previous Company] where I worked on payment systems. Most 
     recent thing I shipped was a refactor of our transaction 
     processing, so I'm coming in with a lot of context for 
     the work you're doing here. Outside work, I play chess 
     and I'm a terrible guitar player. Happy to be here!"

Manager: "Great! Let's go around and everyone say what they 
         work on."

[After intros]

You: "Thanks everyone. I can already tell this is a strong team. 
     I'll probably have a lot of questions in the first few 
     weeks — please don't hesitate to tell me to read the docs 
     if the answer is there!"
```

### Gün 3 — Codebase walkthrough
```
Senior engineer: "Let me show you the repo structure."

You: "Thanks, that'd be super helpful. Can you start high-level 
     and we drill down?"

[30 minutes later]

You: "One question — the billing module seems to be in two 
     different folders. Any reason?"

Senior: "Good catch. Historical mistake. We're planning to 
         consolidate but haven't had time."

You: "Got it. Is this something I could take on, once I'm more 
     familiar with the code?"

Senior: "Honestly, yes. If you're interested, let's revisit 
         in a month."
```

### Gün 4 — İlk PR
```
You (in Slack): "Hey team, got my first PR up for review. 
                 It's small — just fixing a typo I found — 
                 but I wanted to get used to the workflow. 
                 Any feedback welcome!"

Peer: "LGTM!"

Another peer: "Welcome! Approved 🎉"
```

### Gün 5 — Həftə sonu menecerlə
```
Manager: "How's the first week feeling?"

You: "Honestly, a bit overwhelming, but in a good way. I feel 
     like there's a lot to learn. On the bright side, the team 
     has been super helpful — I've gotten answers to every 
     question within hours."

Manager: "That's great to hear. What's one thing we could do 
         better for new hires?"

You: "Maybe a one-page summary of 'who does what' — I spent 
     a while figuring out who owns which part of the system. 
     I could draft one as I learn if you think it'd be useful."

Manager: "I love that. Go for it."
```

---

## 20. Əməliyyat Planı — İlk 30 Gün

### Həftə 1: Tanış Ol
- Setup (laptop, access)
- Təqdim olunma
- Onboarding docs oxu
- 3-5 1-on-1 görüşü (key people)

### Həftə 2: Müşahidə Et
- Meetings-də aktiv dinlə
- Kod oxu
- Team process-lərini başa düş
- İlk kiçik PR (typo fix, comment əlavə)

### Həftə 3: Kiçik Töhfələr
- 1-2 bug fix
- Code reviews etməyə başla (tək-tük)
- Sualların daha az, amma daha dərin

### Həftə 4: Müstəqillik
- Kiçik feature sahib ol
- Sənədləşdirmə et (onboarding, process)
- 30-günlük retro — menecerlə nə yaxşı, nə pis

---

## Əlaqəli Fayllar

- [Small Talk](small-talk.md)
- [Polite English](polite-english.md)
- [Meeting / Standup English](meeting-standup-english.md)
- [Asking for Help — Everyday Expressions](../../vocabulary/everyday-expressions.md)
- [Slack / Teams Messaging](../writing/slack-teams-messaging.md)
- [Describing Your Work](describing-your-work.md)
