# Pair Programming English — Cüt Proqramlaşdırma Dili

## Bu Fayl Haqqında

Pair programming (cütlük halında kod yazmaq) — iki nəfər bir ekran qarşısında işləyir: biri yazır (driver), digəri yönləndirir (navigator). Bu **çox danışıqlı** prosesdir — hər iki tərəf öz fikrini çatdırmalıdır.

Azərbaycan danışanların çətinliyi: canlı, sürətli söhbət zamanı texniki dil. Bu fayl sənə pair programming-in bütün mərhələlərində lazım olan ifadələri öyrədir.

---

## 1. Başlayarkən — Setup

### Görüş açmaq:
- "Ready to pair?"
- "Let me share my screen."
- "Can you see my screen clearly?"
- "The font size okay? I can zoom in."
- "Got your coffee? Let's start."

### Rolları təyin etmək:
- "Do you want to drive first, or should I?"
- "I'll start as driver, you navigate?"
- "Let's swap every 30 minutes."
- "We can use the Pomodoro approach — 25 min, 5 min break."

### Məqsədi aydınlaşdırmaq:
- "What are we trying to accomplish?"
- "Let's agree on the scope first."
- "I'd like to finish [X] by the end of this session."
- "Should we start with tests or code?"

---

## 2. Kod Yazmazdan Əvvəl — Planlaşdırma

### Problemə baxış:
- "Let's read the ticket together first."
- "Before we code, let's think through it."
- "What's the user trying to do?"
- "What's the input and expected output?"

### Birlikdə düşünmək:
- "At a high level, I'm thinking ___."
- "My first instinct is to ___."
- "I'd approach it like ___."
- "What's your gut feeling?"

### Qeyd götürmək:
- "Let me jot that down."
- "Want me to write this on the whiteboard?"
- "I'll create a scratch file for our notes."

---

## 3. Driver Kimi — Yazarkən

### Niyyət bildir:
- "Okay, I'm going to start by ___."
- "Let me first write the function signature."
- "I'll set up the basic structure."
- "Now I'll handle the error case."

### Düşündüyünü söylə:
- "I'm thinking we should use [X] here..."
- "The tricky part is ___."
- "Wait, I'm second-guessing this..."
- "Let me try something."

### Yavaş getmək:
- "Hold on, let me finish this thought."
- "Give me a second to type this out."
- "Don't interrupt yet — let me finish the logic."

### Fikri soruş:
- "Does this make sense?"
- "Am I on the right track?"
- "What do you think?"
- "Any better approach?"

---

## 4. Navigator Kimi — Yönləndirmə

### Müşahidə:
- "Looks good so far."
- "I like this approach."
- "You're on the right track."

### Təklif:
- "Maybe we should [suggestion]?"
- "Have you considered [alternative]?"
- "What if we [different approach]?"
- "Could we simplify this by ___?"

### Səhv tapmaq:
- "Wait, I think there's a typo on line 12."
- "I see a bug — the condition is reversed."
- "Shouldn't that be `>=` instead of `>`?"
- "Check line 34 — the variable is undefined."

### Düşündürmə:
- "What happens if [edge case]?"
- "Have we handled the null case?"
- "Is this thread-safe?"
- "Could this cause a memory issue?"

### ⚠️ Kobud olmadan:
❌ "You did it wrong."  
✅ "I think there's a small issue here."

❌ "No, that's stupid."  
✅ "I'd push back on that approach. Here's why ___."

---

## 5. Sinxronlaşdırma — "Biz Eyni Səhifədəyik?"

### Bir-birini yoxlamaq:
- "Are we on the same page?"
- "Is this the approach you had in mind?"
- "Let me summarize what we decided."
- "Just to recap, we're building ___."

### Başqa yerə getmək:
- "Wait, I lost you. Where were we?"
- "Can you back up?"
- "I missed what you just said."
- "Could you repeat the last part?"

### Razılaşma yoxlaması:
- "Sound good to you?"
- "Are we agreed on [X]?"
- "Any concerns with this direction?"

---

## 6. Debug / Hata Tapmaq

### "İşləmir" deyəndə:
- "Hmm, that's weird."
- "That's not what I expected."
- "It should work — let me check."
- "Something's off."

### Hipotezisləri test etmək:
- "Let's add a print statement here."
- "I wonder if the value is getting set correctly."
- "Let me run the debugger."
- "What if we log the input?"

### Aha an:
- "Oh! I see it."
- "Found it — the issue is ___."
- "That explains it."
- "I bet this is the issue."

### Stuck qalmaq:
- "I'm really stuck."
- "Let me take a step back."
- "Should we ask someone?"
- "Maybe take a 5-minute break?"

---

## 7. Razılaşmama — Texniki Mübahisə

### Yumşaq razılaşmama:
- "I see your point, but I think ___."
- "I'd go a different direction here."
- "I'm not sure about that approach."
- "I respectfully disagree — here's why."

### Alternativ təklif:
- "What about this instead?"
- "Could we try [X] first and see?"
- "Let me show you what I had in mind."

### Kompromis:
- "Let's go with your way for now — we can refactor later."
- "Can we meet in the middle?"
- "How about we try both approaches and compare?"
- "I can live with that."

### Qətiyyət tələb edəndə:
- "Okay, what's the tiebreaker?"
- "Let's ask [third person] for input."
- "We can try both — time-box to 30 minutes each."
- "Let's commit to one and move on."

---

## 8. Rolları Dəyişmək

### Keçid:
- "Ready to swap?"
- "Your turn to drive."
- "Take the keyboard."
- "Let me pass control."

### Canlı söhbətdə:
- "Quick break to swap."
- "I've been driving for a while — want to take over?"
- "My brain is fried, you drive."

### Fiziki ofisdə:
- "Scoot over."
- "Here's the keyboard."

### Online:
- "Let me turn off screen share."
- "Your turn — can you share?"
- "Passing the ball to you."

---

## 9. Fasilə və Davam

### Fasilə:
- "Let's take a quick break."
- "5-minute coffee break?"
- "I need to clear my head."
- "Stretch break?"

### Dayanmağa ehtiyac:
- "My attention is slipping."
- "I'm losing focus — let's pause."
- "Can we come back to this after lunch?"

### Davam:
- "Okay, where were we?"
- "Let me reread the last change."
- "Picking up from ___."
- "Let's recap before we continue."

---

## 10. Bitirmək

### Bitirmək yaxınlaşır:
- "We're almost there."
- "Just need to add tests."
- "Let me clean up the code."
- "Should be ready to commit."

### Yekun:
- "That was a productive session."
- "Thanks for pairing with me!"
- "I learned a lot from this."
- "Good work, team."

### Sonraki addımlar:
- "Let me push this and create a PR."
- "I'll write up what we did."
- "Want to review it async after?"
- "Catch up on the rest tomorrow?"

---

## 11. Kod Haqqında Sual Vermək

### Kodu oxumaq:
- "Walk me through this function."
- "What does this do?"
- "Why are we doing [X] here?"
- "Is there a reason for [pattern]?"

### Kontekst istəmək:
- "What's the history here?"
- "Who wrote this originally?"
- "Is this a known pattern in the codebase?"

### Git komandaları:
- "Let me git blame to see who wrote it."
- "Checking the commit history."
- "Let's look at the PR that introduced this."

---

## 12. Real-time Kod Rəyi (Live Review)

### Positive:
- "Oh, this is clean."
- "Nice abstraction."
- "I like the naming."
- "This reads really well."

### Suggestion:
- "Small nit — could we rename this?"
- "Maybe pull this into a helper?"
- "Comment might help future readers."

### Concern:
- "I'm worried about performance here."
- "This could break if [edge case]."
- "We should add a test for this."

---

## 13. Texniki Seçimlər — Danışıqla

### Data structure seçimi:
- "Should we use a dict or a list here?"
- "A set would be faster for lookups."
- "We don't need ordered, so a hash map is fine."

### Algorithm seçimi:
- "Brute force would work but O(n²)."
- "Can we do this in one pass?"
- "Binary search might be overkill here."

### Library seçimi:
- "Can we use the stdlib for this?"
- "Is there already a helper for this?"
- "We have lodash — let's use that."

### Naming:
- "How about `processData` for the function name?"
- "`userId` or `user_id`? What's our convention?"
- "This name is misleading — let's rename it."

---

## 14. "I Don't Know" Demək

**Ən vacib pair programming bacarığı: bilmədiyini etiraf etmək.**

### Yumşaq:
- "I'm not sure."
- "That's a good question — let me check."
- "I don't actually know. Let's look it up."
- "I've never worked with this."

### Fərasətli:
- "No idea, but I have a guess: ___."
- "Not sure, but I'd try ___ first."
- "Let me think out loud..."

### Kömək istəmək:
- "Can we pull in someone who knows this?"
- "Is there a docs I should read?"
- "Let me phone a friend."

### ❌ Pis:
- Özünü bilir kimi göstərmək
- "I think so..." dediyin üçün səhv yönə getmək
- Səhv məlumatı kimisə sürətli vermək

---

## 15. Tez-tez İşlədilən Texniki Terminlər

### Kod yazarkən:
| İfadə | Nə vaxt işlədilir |
|-------|-------------------|
| "Let's refactor this." | Strukturu dəyişmək |
| "Let's extract this into a function." | Funksiyaya ayırmaq |
| "Let's inline this." | Funksiyanı geri qoymaq |
| "Let's wrap this in a try-catch." | Try-catch bağlamaq |
| "Let's mock this for the test." | Mock etmək |
| "Let's stub this out." | Əvəzedici qoymaq |
| "Let me scaffold the structure first." | Skelet qurmaq |
| "Let me stub the function signatures." | İmza qoymaq |

### Test haqqında:
- "Let's start with the happy path."
- "Now let's add edge cases."
- "What about the error case?"
- "Let's test the boundary conditions."

### Debugging:
- "Let me put a breakpoint here."
- "Drop in a print statement."
- "Let's step through this."
- "Add some logging."

---

## 16. Onlayn Pair Programming (Remote)

### Fərqli çətinliklər:
- Eyni ekrana baxmaq çətin
- Kablonun gecikməsi
- Çok sozlü olmaq lazım
- "Pointing" (işarə etmək) yoxdur

### Həll:
- "Line number please?" ("Look at line 42")
- "Scroll down a bit."
- "Can you zoom in?"
- "Use your cursor to point."

### Alətlər:
- VS Code Live Share
- JetBrains Code With Me
- GitHub Codespaces + multiplayer
- Just Zoom/Meet + screen share

### Zoom/Meet-də:
- "Can I take control?"
- "Let me request control."
- "I'll send my cursor to your screen."

---

## 17. Pair Programming Etikası

### ✅ Et:
- Fikirlərini aydın ifadə et
- Dinlə, başqasını kəsmə
- Suallar ver (əzbər bilmirsənsə)
- Təvazökar ol ("I might be wrong")
- Fasilə al (yorğunluq keyfiyyəti öldürür)

### ❌ Etmə:
- Klaviaturasını sərt şəkildə ələ al (driver haqqına hörmət)
- Hər bir xırda şeyi düzəlt (nitpick)
- Özünü bilir kimi göstər
- Passiv qal (həmişə fikrin olsun)

---

## 18. Texniki Meneceriniz Sənə Pair Programming Təklif Edərsə

Bəzən menecerin / senior sənə tərəfdaş kimi işləmək təklif edir. Bu dəyərli imkandır.

### Faydalanmaq üçün:
- Suallarını əvvəlcədən hazırla
- Onların yanaşmasına diqqətlə bax
- "Why did you do that?" soruş (düşüncə prosesini öyrən)
- Qeyd götür (sonra özün təkrar et)

### İfadələr:
- "I'd love to hear your thought process as you code."
- "Could you explain why you chose that approach?"
- "Is there a mental model that helps you with this?"
- "Thanks for taking the time — I learned a lot."

---

## 19. Nümunə Dialog — Real Pair Session

```
Orkhan: "Ready to pair? I'm sharing my screen."

Sara: "Yep, I see it. What are we working on?"

Orkhan: "Ticket 123 — add a cache to the /users endpoint. 
         Current latency is 500ms, we want < 100ms."

Sara: "Got it. Have you thought about the approach?"

Orkhan: "I was thinking Redis, with a 60-second TTL. 
         What do you think?"

Sara: "Sounds reasonable. What's the invalidation strategy?"

Orkhan: "Oh, good point. I hadn't thought that through. 
         When a user updates their profile, we'd need to 
         invalidate their cache entry."

Sara: "Exactly. How do we detect updates?"

Orkhan: "The user service publishes an event on update. 
         We could subscribe to that."

Sara: "I like it. Let's start coding."

Orkhan: "I'll drive first. Let me add the Redis client."

[10 minutes later]

Sara: "Wait — line 34. You're caching the raw query result, 
       but we usually cache serialized JSON. Otherwise we'll 
       have type issues."

Orkhan: "Oh, good catch. Let me fix that."

Sara: "Also, what about cache stampede? If the cache expires 
       and 100 requests come in at once, we hit the DB 100 times."

Orkhan: "Hmm. Should we add a lock?"

Sara: "Or use probabilistic early refresh. Let me show you 
       a pattern I've used before."

[Sara takes over, demonstrates]

Orkhan: "Cool, I hadn't seen that. Let me swap back and 
         continue with this approach."

[30 minutes later]

Sara: "I think we're in good shape. Want me to write tests?"

Orkhan: "Yeah — I'll refactor while you write tests. Then 
         we'll review together."

[20 minutes later]

Sara: "Tests pass."

Orkhan: "Let me run it locally against real data. ...okay, 
         latency is now 30ms. We beat the target."

Sara: "Nice! Let me take a look at the final code."

[5 minutes of review]

Sara: "Looks good to me. Ready to ship."

Orkhan: "Thanks for pairing! Learned a lot about cache 
         stampede today."

Sara: "Anytime."
```

---

## 20. Öyrənmə Strategiyası

### Başlayan üçün:
- Senior ilə cütləş (passiv navigator kimi başla)
- Yalnız bu 10 ifadəni bil:
  1. "Could you explain that?"
  2. "Why did you choose this?"
  3. "What about [edge case]?"
  4. "Let me try something."
  5. "I'm not sure about this."
  6. "Let me check the docs."
  7. "Want to try a different approach?"
  8. "Let me summarize what we decided."
  9. "Can we take a break?"
  10. "Thanks for pairing!"

### Orta səviyyə üçün:
- Peer ilə cütləş
- Həm driver, həm navigator ol
- Razılaşmazlıqda öz mövqeyini müdafiə et

### Senior üçün:
- Juniors ilə cütləş
- Öyrətmə fırsatı ver
- "Why" suallarını cavabla

---

## Əlaqəli Fayllar

- [Meeting / Standup English](meeting-standup-english.md)
- [Technical Discussion Phrases](technical-discussion-phrases.md)
- [Tech Deep Dive Vocabulary](../../vocabulary/by-topic/technology/tech-deep-dive.md)
- [Giving Feedback](giving-feedback.md)
- [Difficult Conversations](difficult-conversations.md)
- [Onboarding English](onboarding-english.md)
