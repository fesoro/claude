# Handling Criticism — Tənqidi Qəbul Etmək

## Səviyyə
B1 (code review + peşəkar inkişaf!)

---

## Niyə Vacibdir?

Tənqid qəbul etmək:
- **Code review**-larda hər gün
- Peşəkar inkişaf
- Performance review
- Interview "tell me about feedback" sualı

**Yaxşı criticism handling = senior engineer imza**

---

## 2 Növ Tənqid

### 1. Constructive (Konstruktiv)

Səni inkişaf etdirmək üçün.
- "Could you add tests here?"
- "This function is a bit long."

### 2. Destructive (Destruktiv)

Zərərli, kobud.
- "This is bad."
- "Why did you do this?"

**Fərqi bil** — hər ikisinə fərqli cavab ver.

---

## Qızıl Qayda — STAR Technique

1. **S**top (durdur — defensive olma)
2. **T**hink (fikirləş)
3. **A**cknowledge (qəbul et)
4. **R**espond (cavabla)

---

## 1. İlk Reaksiya (Stop!)

### İnstinktiv reaksiyalar (KAÇIN!)

- ❌ Defensive: "But I..."
- ❌ Dismissive: "That's not important."
- ❌ Emotional: "Why are you attacking me?"
- ❌ Passive-aggressive: "Sure, whatever."

### ✓ İlk sözlər

- "Thank you for the feedback."
- "I hear you."
- "Let me think about that."
- "That's a good point."

**Breathe. Don't react immediately.**

---

## 2. Active Listening

Qulaq as. Başa düş.

### Techniques

- **Paraphrase**: "So what you're saying is..."
- **Clarify**: "Could you give an example?"
- **Acknowledge**: "I see where you're coming from."

### Avoid

- Interrupting
- Preparing rebuttal while they talk
- Getting emotional

---

## 3. Acknowledge (Qəbul Et)

### Yumşaq qəbul

- "You're right."
- "That's a fair point."
- "I see your perspective."
- "That's a valid concern."

### Deeper acknowledgment

- "I didn't consider that."
- "That's a good observation."
- "I can see how that could be improved."

---

## 4. Respond Professionally

### Options

#### A. Agree + Commit

- "You're right. I'll fix it."
- "Good point. I'll update the PR."

#### B. Agree partially

- "I agree about X, but I'd like to discuss Y."
- "Good point on performance — but readability is also important."

#### C. Disagree respectfully

- "I see your point, but I think X works better because..."
- "I'd push back on that — here's why..."

#### D. Ask for time

- "Let me think about that and get back to you."
- "I'll need to dig in more before responding."

---

## 5. Code Review Context

Ən çox tənqid kontekst.

### Common reviewer comments

- "Extract this into a function."
- "Missing tests."
- "This is hard to read."
- "Consider edge cases."

### ✓ Good responses

- "Good catch, refactoring now."
- "Added tests, thanks!"
- "You're right — let me rewrite."
- "Thanks, added null check."

### ✗ Bad responses

- "That's nitpicking."
- "I disagree." (without explanation)
- "But it works!"

---

## 6. Disagreeing (Constructively)

Bəzən tənqid səhvdir. Nəzakətlə müdafiə et.

### Phrases

- "I see your point, **but**..."
- "I appreciate the feedback, **however**..."
- "I'd respectfully push back on..."
- "I disagree, and here's why..."

### Provide evidence

- "Actually, this is the standard pattern because [reason]."
- "I tested X, Y, Z and this performed best."
- "The docs recommend this approach."

### Ask for reasoning

- "Why do you think X is better?"
- "What's the concern with this approach?"
- "Could you explain the downside?"

---

## 7. Performance Review

Formal workplace criticism.

### During review

- **Listen** fully
- **Take notes**
- **Ask clarifying questions**
- **Don't argue**

### Phrases

- "Thank you for the feedback."
- "Could you give a specific example?"
- "What would success look like?"
- "How can I improve in this area?"

### After review

- Write a summary email
- Set action items
- Follow up in 1-3 months

---

## 8. Public Criticism

Meeting-də / public space-də.

### Options

#### Short acknowledgment

- "Thanks for raising that. Let's discuss offline."

#### Take it offline

- "That's a good point. Can we chat 1:1 after?"

#### Address briefly, move on

- "Fair point. I'll follow up with details."

### ✗ Avoid

- Getting defensive publicly
- Arguing in front of others
- Crying (harda olursan ol)

---

## 9. Harsh / Unfair Criticism

Bəzi tənqid kobud olur. Necə davranmaq?

### Don't match their tone

Onlar kobud olsa da, sən peşəkar qal.

### Calm response

- "I hear you're frustrated. Let's talk when we're calm."
- "Let's focus on the problem, not the person."
- "I'd like to understand — can you rephrase?"

### Set boundaries

- "I'm open to feedback, but not to personal attacks."
- "I'd prefer we keep this professional."

### Document

Toxik patterns varsa, yaz → HR.

---

## 10. Emotional Regulation

Tənqid eşitdikdə:

### Physical

- Take 3 deep breaths
- Don't type immediately
- Walk away briefly if needed

### Mental

- Reframe: "This is feedback, not an attack"
- Separate: "Criticism of work ≠ criticism of me"
- Delay: "I'll respond thoughtfully, not reactively"

---

## 11. Receiving Feedback Well

### Before

- **Ask for it**: "Any feedback?"
- **Be specific**: "How can I improve X?"

### During

- **Listen actively**
- **Don't interrupt**
- **Take notes**

### After

- **Thank them**: "Thanks for the feedback."
- **Follow up**: "I've updated based on your input."
- **Show growth**: implement changes

---

## 12. Common Criticism Scenarios

### "Your code is messy."

### ✓ Response

"Thanks for pointing that out. Could you show me a specific area?
I'll refactor."

### "You missed the deadline."

### ✓ Response

"You're right, I did. The reason was [X]. Here's my plan to
prevent this next time: [Y]."

### "You don't communicate enough."

### ✓ Response

"I appreciate the feedback. What specific situations come to
mind? I'd like to improve."

### "This PR is wrong."

### ✓ Response (if they're right)

"You're right, let me fix that."

### ✓ Response (if they're wrong)

"Could you explain the concern? I thought I handled X by doing Y."

---

## 13. Email Criticism

Harsh email received?

### ✓ Don't reply immediately

Wait 24 hours if possible.

### Good response template

```
Hi [Name],

Thanks for the feedback on [X].

I want to make sure I understand fully. You mentioned [Y] —
could you clarify what you had in mind?

I'd love to align on [topic] so we can move forward.

Could we discuss via call / in person?

Best,
[Your name]
```

---

## 14. Giving Back (Feedback)

Bəzən sənə verilən tənqidə cavab feedback ver.

### Phrases

- "I hear you. One thing I'd like to share..."
- "Thanks for the feedback. I also want to mention..."
- "To add to this conversation..."

### ✗ Don't

- Tit-for-tat: "Well, YOU did X!"
- Retaliatory: saved-up criticism

---

## 15. Interview Kontekstində

"Tell me about a time you received difficult feedback."

### Structure (STAR)

1. **Situation**: context
2. **Task**: what you were doing
3. **Action**: how you handled feedback
4. **Result**: how you improved

### Example

"**Situation**: My manager told me my **communication was unclear**
in meetings.

**Task**: I needed to be more concise.

**Action**: I **asked for specific examples**, then **practiced**
summarizing before meetings.

**Result**: Got **positive feedback** in next review."

---

## 16. Criticism in Different Cultures

### US

- Direct but polite
- "I like X, but could improve Y"
- Sandwich method (pozitiv-neqativ-pozitiv)

### UK

- Indirect
- "It's quite interesting, but perhaps..."

### Germany / Netherlands

- Very direct
- "This is wrong." (neutral, not rude)

### Japan

- Very indirect
- Read between the lines

### Azerbaijan context

- Adjust to recipient
- Tech teams often direct
- Always polite

---

## Phrases Collection

### Accepting criticism

- "Thanks for the feedback."
- "I appreciate the input."
- "That's a fair point."
- "I hadn't considered that."
- "Good catch."
- "Noted."

### Asking for clarification

- "Could you give a specific example?"
- "Can you help me understand?"
- "What would you suggest?"

### Partial disagreement

- "I agree with X, but..."
- "I see that perspective, however..."
- "I'd like to discuss Y further."

### Committing to improve

- "I'll work on this."
- "I'll incorporate your feedback."
- "Here's my plan to address this."

### Following up

- "Based on your feedback, I've changed X."
- "Wanted to update you on the improvements."

---

## Growth Mindset

### Fixed mindset (bad)

- "I'm bad at this."
- "I can't change."
- "Criticism = attack."

### Growth mindset (good)

- "I can improve at this."
- "I'm learning."
- "Criticism = opportunity to grow."

**Embrace criticism as input, not attack.**

---

## Xatırlatma

**Criticism Handling:**
1. ✓ **Stop** (don't react emotionally)
2. ✓ **Listen** actively
3. ✓ **Acknowledge** the feedback
4. ✓ **Respond** professionally
5. ✓ **Act** on valid points
6. ✓ **Follow up** with growth

**Qızıl qayda:**
- **Separate** criticism of work from criticism of self.
- **Thank**, then consider.
- **Growth mindset** → every critique = learning.

**Interview qızıl:**
- "I ask for feedback regularly."
- "I act on constructive criticism."
- "I grew from X feedback."

→ Related: [asking-for-help.md](asking-for-help.md), [saying-no-politely.md](saying-no-politely.md), [difficult-conversations.md](difficult-conversations.md), [giving-feedback.md](giving-feedback.md)
