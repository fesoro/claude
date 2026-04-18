# Mentoring Juniors

## Niyə vacibdir? (Why it matters)
Mentorluq senior mühəndis üçün ən yüksək effekt verən fəaliyyətlərdən biridir. Yaxşı mentorluq etdiyin bir junior 6 ay ərzində 10 dəfə daha məhsuldar olur, sənin sərf etdiyin vaxt isə azdır. Senior müsahibələri çox vaxt mentorluq barədə sual verir, çünki şirkətlər bilir ki, güclü seniorlar yalnız özlərini yox, bütün komandanı artırır.

Pis mentorluq (onların əvəzinə işini görmək, yalnız cavab vermək, izləməmək) asılı mühəndislər yaradır, onlar böyüyə bilmir. Yaxşı mentorluq isə gələcəkdə başqalarına mentorluq edəcək həmkar mühəndislər yaradır — komandaya qatlanaraq fayda verir.

## Yanaşma (Core approach)
1. **Balıq vermə, balıq tutmağı öyrət.** Junior "bunu necə edim?" deyə soruşanda, dərhal cavabı vermək istəyini saxla. Əvvəl nə cəhd etdiklərini soruş.
2. **Müntəzəm pair et.** Həftədə 30 dəqiqə pair programming 5 saat async cavabdan daha dəyərlidir.
3. **Əvvəl rubber duck.** Onlar kömək istədikdə, problemi izah etmələrini xahiş et. Yarı halda özləri həll edir.
4. **Yalnız task yox, böyümə söhbətləri də et.** Hər 1-2 ayda bir gündəlik işdən bir addım geri çəkil və soruş: nə öyrənmək istəyirlər? Onları nə bloklayır?
5. **Əvvəl blok aç, sonra öyrət.** Təcili bir şeydə ilişiblərsə, əvvəl blok aç (cavabı ver), sonra qayıt və səbəbi izah et.

## Konkret skript və template (Scripts & templates)

### Junior kömək istədikdə
- Birinci sual: "What have you tried so far?"
- İkinci sual: "What do you think is happening?"
- Üçüncü sual: "Where did you look?"
- Sonra, əgər hələ ilişiblərsə: "Let me share how I'd approach this problem..."

### Pairing session şablonu (30 dəq)
1. 5 dəq — nə həll edirik, onlar nə cəhd ediblər
2. 20 dəq — problem üzərində birlikdə işlə, mümkün olsa klaviaturanı ona ver
3. 5 dəq — düşün: nə öyrəndilər, növbədə tək nə sınayacaqlar

### Böyümə 1:1 skripti (aylıq, 30 dəq)
> "Let's step away from tickets today. I want to understand how you're doing overall. Three questions: What do you feel you're growing in? What do you feel stuck on? What do you want to learn in the next 3 months?"

Davamı:
> "If you want to work on [database design / system design / code review skills], I can find you stretch tasks and review your work more closely. Would that help?"

### Təkrarlanan səhv barədə feedback
> "I noticed in the last three PRs you've [specific pattern, e.g., skipped writing tests for the happy path]. I want to flag it now so it doesn't become a bigger problem. Can we pair tomorrow on writing tests for the next feature?"

### Junior çətinlik çəkəndə (xəbərdarlıq siqnalları)
İzləməli əlamətlər:
- PR-lar getdikcə kiçilir
- İrəliləmə olmadan eyni sualı təkrar-təkrar verir
- Code review-dan qaçır
- Standup-da sakit olur
- Kiçik deadline-ları ardıcıl qaçırır

Söhbət skripti:
> "Hey, I wanted to check in. I've noticed [specific observation]. How are you feeling about the work? Is there something blocking you that we can solve together?"

### Yüksəlişə (promotion) yol tövsiyə etmək
> "Based on the last 6 months, I think you're approaching [next level]. For the official case, we need to see evidence of: [scope, impact, leverage]. Let's find one project in Q3 where you can lead the technical side end-to-end. I'll support you."

## Safe phrases for B1 English speakers
- "What have you tried so far?" — kömək etməmişdən əvvəl ilk cəhdi tələb etmək
- "Walk me through your thinking." — onları izah etməyə məcbur etmək
- "Where did you look first?" — necə axtarmağı öyrətmək
- "Let me show you how I'd approach it." — Socratic metoddan demo rejiminə keçmək
- "Take your time, there's no rush." — təzyiqi azaltmaq
- "That's a good question." — sual verməyi təşviq etmək
- "It's okay to be stuck. That's how we learn." — çətinliyi normal saymaq
- "Let's pair on this for 20 minutes." — konkret kömək təklif etmək
- "Can you try this alone first and come back if you're stuck?" — müstəqilliyi təşviq etmək
- "What do you want to learn next?" — böyümə söhbətini açmaq
- "I noticed you're making good progress on X." — konkret tərif
- "I want to flag something small before it grows." — konstruktiv feedback açmaq
- "Don't worry, I made the same mistake when I started." — təhlükəsizlik hissi yaratmaq
- "What does 'done' look like for this task?" — scoping öyrətmək
- "Write it down so you remember next time." — vərdiş öyrətmək

## Common mistakes / anti-patterns
- Klaviaturanı əlindən almaq. Onlar izləyərək yox, özləri sürərək öyrənirlər.
- Hər dəfə dərhal cavabı vermək. Asılılıq yaradır.
- Heç vaxt cavabı verməmək — onlar saatlar sərf edir, halbuki 2 dəqiqəlik izahat saatları xilas edə bilər.
- "Çox məşğulam" deyə pairing-ləri atlamaq. Əgər investisiya etməsən, junior sənin bottleneck-in olur.
- Yalnız tasklar barədə danışmaq, böyümə barədə heç vaxt.
- Açıq düzəliş. Həmişə gizli düzəlt, açıq tərif et.
- Juniorları bir-biri ilə müqayisə etmək. "Why can't you be more like X?"
- Kömək istəyəcəklərini güman etmək. Çox junior qorxur. Proaktiv şəkildə yoxla.
- Mentor olduğunu söyləmədən mentorluq etmək. Aydın şəkildə de: "I'd like to help you grow in X."
- Onları bütün çətinliklərdən qorumaq. Juniorlar böyümək üçün çağırışa ehtiyac duyur.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "Tell me about a time you mentored someone."
- "How do you help a struggling team member?"
- "How do you balance your own work with mentoring?"

Cavab planı:
1. **Konkret nümunə:** "In my last team, I mentored a mid-level engineer who was stuck on getting to senior. We did weekly 30-minute pairing on system design problems."
2. **Yanaşma:** "I focus on teaching the approach, not giving the answer. I ask what they tried first."
3. **Nəticə:** "After 4 months she led the design of our notification service and was promoted."
4. **Fikirləşmə:** "I learned that mentoring is really about creating safety — they have to feel safe to try and fail in front of me."

## Further reading
- "The Manager's Path" by Camille Fournier (chapters on mentoring and tech lead)
- "Staff Engineer" by Will Larson (on leveraging others)
- "Radical Candor" by Kim Scott (feedback framework)
- "The Coaching Habit" by Michael Bungay Stanier (seven coaching questions)
- "Apprenticeship Patterns" by Dave Hoover and Adewale Oshineye
