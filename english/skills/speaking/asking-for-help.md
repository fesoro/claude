# Asking For Help — Nəzakətlə Kömək İstəmək

## Səviyyə
B1 (iş + kolleqalar!)

---

## Niyə Vacibdir?

Kömək istəmək:
- Çox sonra vaxt qorur
- Peşəkar imza
- Tim ruhu

**Amma səhv istəmək:**
- "Lazy / incompetent" kimi görünə bilər
- Cavab alınmır
- Kolleqalar narahat olur

**Həll:** Düzgün soruş.

---

## Qızıl Qayda

Kömək istəməzdən əvvəl:

1. **Özün cəhd etdin?** (Google, docs, code)
2. **Konkret sualdır?** (vague yox!)
3. **Context verdin?** (nə etdin, nə gözləyirsən)

---

## 1. Politely Asking

### Sadə polite

- "Could you help me with...?"
- "Would you mind...?"
- "Can I ask you something?"
- "Do you have a moment?"

### Formal

- "Would it be possible to...?"
- "I was wondering if you could..."
- "Could I bother you for a minute?"

### Informal (friends/peers)

- "Hey, quick question..."
- "Can you help me out?"
- "Got a sec?"

---

## 2. Pre-Help Checklist

Sual soruşmamışdan əvvəl:

### ✓ Did you...

- Google it?
- Read the docs?
- Check Stack Overflow?
- Look at similar code?
- Try debugging?
- Read error messages?

### Sonra əgər hələ çətindirsə → soruş.

---

## 3. "Rubber Duck" Method

Əvvəl özünə danış:

1. "What am I trying to do?"
2. "What did I try?"
3. "What happened?"
4. "Why is this unexpected?"

Bu zaman bəzən həll tapırsan!

Əgər tapmadın, indi hazırsan kömək istəməyə.

---

## 4. The Perfect Ask (XY Problem)

### ✗ Vague / XY problem

"How do I parse JSON in Python?"

(Əsl problem: fayla yazmaq istəyirsən, amma JSON ara addım.)

### ✓ Full context

"I'm trying to **[goal]**. I tried **[approach]**, but **[result]**.
I expected **[expected]**. Here's my code: **[snippet]**."

### Template

```
## What I'm trying to do
[Goal]

## What I tried
[Approach / code]

## What happened
[Error / unexpected result]

## What I expected
[Expected behavior]
```

---

## 5. Asking in Slack / Chat

### ✗ Bad

```
"Hey"
[waits for response]

"I have a question"
[waits for response]

"Do you have time?"
```

### ✓ Good

```
"Hey @reviewer! Quick question — when I run `npm install`,
I get `EACCES: permission denied`. I tried `sudo` but it
caused other issues. Any ideas? Here's the full error:
[paste]"
```

### "Don't ask to ask — just ask!"

Qızıl qayda: Don't ask "can I ask you something?" → **sual ver!**

---

## 6. Asking in Meeting

### Fasilə tap

- "Can I ask a quick question?"
- "Sorry to interrupt — a quick question..."
- "Before we move on, can I clarify..."

→ [interrupting-politely.md](interrupting-politely.md)

### Admit confusion

- "I didn't quite follow — can you explain X?"
- "Sorry, I'm lost at step 3..."
- "Could you walk through that again?"

---

## 7. Asking Senior Engineer

Senior-dən kömək istəmək — vaxtlarını dəyər.

### ✓ Good approach

- "I know you're busy. I have a 2-minute question about X."
- "When you have a sec, could I get your thoughts on Y?"
- "I tried A, B, C — got stuck. Mind pointing me in the right direction?"

### Show effort

- "I've been debugging for 2 hours..."
- "I read the docs but couldn't find..."
- "Tried these approaches: 1) X, 2) Y."

### Respect time

- Don't ping repeatedly
- Batch questions
- Share context upfront

---

## 8. Asking in Code Review

### On PRs

- "Could you take a look when you have time?"
- "Feedback welcome!"
- "Specifically interested in your thoughts on [X]."

### On comments

- "Could you clarify what you mean by...?"
- "I'm not sure I follow — could you show an example?"

---

## 9. Asking for Approval / Decision

### Polite

- "What are your thoughts on...?"
- "Would you approve this approach?"
- "Do you have any concerns with...?"

### Professional

- "I'd like to propose X. Please let me know if you have concerns."
- "Seeking your approval for..."

---

## 10. Asking for Clarification

### Meeting / conversation

- "Just to make sure I understand..."
- "Could you clarify X?"
- "I want to confirm — you said Y, correct?"

### Email / Slack

- "Could you elaborate on...?"
- "Not clear on X — could you explain?"

### Repeating back

- "So to summarize, you want X by Y. Is that right?"

---

## 11. Asking for Introduction

### Networking

- "Could you introduce me to X?"
- "I'd love a chat with X if you could connect us."
- "Would you mind making an intro?"

### Respectful

- Draft intro email (save them time)
- Say why you want to meet
- Be brief

---

## 12. Asking for Feedback

### Your work

- "Could I get your feedback on this?"
- "I'd appreciate your thoughts on..."
- "Any feedback on my approach?"

### Specific feedback

- "Specifically, I'd like feedback on:
  - Performance
  - Readability
  - Edge cases"

---

## 13. Emergency Asks

### Urgent

- "I have a production issue. Can you help urgently?"
- "Blocking issue — need your input ASAP."
- "🚨 prod down, need your eyes"

### Follow urgency protocol

- Page / call if truly urgent
- Post in incident channel
- Tag @here or @channel (judiciously)

---

## 14. Not Being a Burden

### ✓ Do

- Try first (Google, docs)
- Be specific
- Show your work
- Thank them

### ✗ Don't

- Ask basic questions (Googleable)
- Demand immediate response
- Ping every 5 minutes
- Ask the same person always

---

## 15. After They Help

### Thank them

- "Thanks so much!"
- "That really helped!"
- "I appreciate you taking the time."

### Share outcome

- "Your suggestion worked — here's the result."
- "Fixed it! Thanks again."

### Pay it forward

- Help others in return
- Document so others don't ask

---

## Phrases Collection

### Starting

- "Could you help me with...?"
- "Do you have a moment?"
- "Quick question..."
- "Hope I'm not bothering you, but..."

### Clarifying

- "Just to make sure..."
- "To confirm..."
- "I want to double-check..."

### Context

- "I've been working on X..."
- "I tried Y and Z..."
- "Here's what I expected..."

### Action

- "Could you take a look?"
- "Any thoughts on...?"
- "What would you recommend?"

### Thanks

- "Thanks for your help!"
- "Really appreciate it!"
- "Thanks for the input!"

---

## Slack Message Templates

### Tech question

```
Hey @person! I'm stuck on [X]. Tried [Y] and [Z], but getting
[error]. Here's the code: [snippet]. Any ideas?
```

### Meeting ask

```
@person do you have 15 min this week to discuss [topic]?
I'd like your input on [specific question].
```

### Review request

```
@reviewer could you review PR #123 when you have time?
It's a small change (20 lines) to [description]. Thanks!
```

---

## Email Templates

### Formal help request

```
Subject: Advice on [topic]

Hi [Name],

I hope you're doing well.

I'm working on [context] and could use your expertise on
[specific question].

Here's what I've tried: [approaches].

Would you have 15 minutes this week to discuss?

Thanks,
[Your name]
```

### Introduction request

```
Subject: Introduction to [Name]?

Hi [Connector],

I'm looking to connect with [Target Name] about [topic/goal].
Would you be willing to make an introduction?

Happy to draft the intro email if it helps.

Thanks,
[Your name]
```

---

## Interview Kontekstində

### "When did you ask for help?"

Prepare a story:

1. **Situation**: context
2. **Why you asked** (hit a wall, efficiency)
3. **How you asked** (context, specific)
4. **Outcome**

### Example

"I was stuck on a **distributed systems** issue for 2 hours.
I **summarized** what I tried and **asked the senior engineer**
for 10 minutes. She pointed out a **race condition**. I **learned**
to reach out earlier next time."

**Interviewer wants to see:**
- You try first
- You know when to escalate
- You learn from help

---

## Cultural Notes

### Asian culture

- Asking help = face-saving issue
- Often hesitate
- Modern tech teams: ask freely

### Western tech culture

- Asking = strength, not weakness
- Don't guess if you don't know
- But: try first!

### Azerbaijan in tech

- Balance: try first + ask professionally
- Show effort + be specific

---

## Common Mistakes

### ✗ Asking without trying

"How does React work?" (Google it!)

### ✗ Vague questions

"My code doesn't work." (What code? What error?)

### ✗ No context

"Can you help?" (With what?)

### ✗ Impatient pings

"Hello?"
"???"
"You there?"

---

## Xatırlatma

**Yaxşı kömək istəmə:**
1. ✓ Try first (Google, docs)
2. ✓ Specific question
3. ✓ Full context (what you tried, expected)
4. ✓ Code snippet / error
5. ✓ Polite tone
6. ✓ Respect time
7. ✓ Thank them

**Qızıl qayda:**
- **Don't ask to ask — just ask!**
- **Show your work.**
- **Be specific.**

**Interview qızıl:** "I ask for help **after trying independently** — I ask thoughtful questions with context."

→ Related: [handling-criticism.md](handling-criticism.md), [saying-no-politely.md](saying-no-politely.md), [polite-english.md](polite-english.md)
