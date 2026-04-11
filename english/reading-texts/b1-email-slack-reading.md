# B1 Reading: Email & Slack Communication

İş mühitində rastlaşacağınız email və Slack mesajlarını oxuyub başa düşmə məşqləri.

---

## Text 1 — Formal Email: Meeting Reschedule

```
From: sarah.wilson@techglobal.com
To: development-team@techglobal.com
CC: michael.chen@techglobal.com
Subject: Sprint Review Meeting — Rescheduled to Thursday

Hi team,

I hope this email finds you well.

I'm writing to let you know that the sprint review meeting originally
scheduled for Wednesday 17th at 2:00 PM has been moved to Thursday 18th
at 3:00 PM. This is because the client has requested an additional day
to prepare their feedback.

The agenda remains the same:

1. Demo of completed features (20 min)
2. Client feedback and Q&A (15 min)
3. Discussion of next sprint priorities (15 min)
4. Any other business (10 min)

Please note that Michael from the QA team will also be joining us this
time, as he'd like to discuss the testing timeline for the next release.

If the new time doesn't work for anyone, please let me know by end of
day Tuesday so I can try to find an alternative.

Could each team member please prepare a brief summary (2-3 sentences)
of what they've completed this sprint? You can share it in the
#sprint-review Slack channel before the meeting.

Best regards,
Sarah Wilson
Project Manager
```

### Questions

1. Why was the meeting rescheduled?
2. What is the new date and time?
3. How long is the full meeting expected to last?
4. Why is Michael joining the meeting?
5. What should team members prepare?
6. Where should team members share their summaries?
7. By when should people notify Sarah if the new time doesn't work?

---

## Text 2 — Informal Email: Onboarding New Team Member

```
From: david.park@startup.io
To: backend-team@startup.io
Subject: New team member starting Monday!

Hey everyone,

Quick heads up — we have a new developer joining our team on Monday.
His name is Alex, and he's coming from a fintech background. He'll be
working mainly on the payment integration project.

A few things I'd appreciate your help with:

- If you see him in the office, please say hi and make him feel welcome
- Tom — could you set up his dev environment and give him access to
  the repos? Here's his GitHub username: @alex-dev-22
- Lisa — would you mind being his buddy for the first two weeks?
  Basically just checking in with him, answering questions, showing
  him how we do things
- Everyone — please add him to any relevant Slack channels he should
  be in

I've already shared our team wiki and coding standards doc with him,
so he'll have some reading to do on his first day. I've also booked
a 1-on-1 with him on Monday afternoon to go over the project roadmap.

If anyone has suggestions for improving our onboarding process, I'm
all ears!

Cheers,
David
```

### Questions

1. When is Alex starting?
2. What is his professional background?
3. What project will he work on?
4. What two things is Tom asked to do?
5. What is Lisa's role as a "buddy"?
6. What has David already shared with Alex?
7. What does "I'm all ears" mean?
   - A. I'm very busy
   - B. I'm happy to listen to suggestions
   - C. I'm confused

---

## Text 3 — Slack Conversation: Production Incident

```
#incident-response channel

@emma-ops [9:47 AM]
🚨 INCIDENT: The checkout API is returning 500 errors. Multiple users
are reporting they can't complete purchases. Investigating now.

@james-backend [9:49 AM]
I see it too. Error rate jumped from 0.1% to 23% about 10 minutes ago.
Checking the logs now.

@emma-ops [9:52 AM]
Found something — the last deployment was at 9:35 AM. @james-backend
was that yours?

@james-backend [9:53 AM]
Yes, I deployed the updated payment validation logic. Let me check if
that's the cause.

@emma-ops [9:55 AM]
Can we roll back immediately while we investigate? Users are being
affected right now.

@james-backend [9:56 AM]
Agreed. Rolling back to the previous version now.

@james-backend [10:02 AM]
Rollback complete. The error rate is dropping — back to 0.3% now.

@emma-ops [10:04 AM]
Confirmed — checkout is working again. Good catch on the quick
rollback. @james-backend can you figure out what went wrong with
the deployment and write a post-mortem?

@james-backend [10:06 AM]
Will do. Initial look suggests I missed an edge case in the validation
— orders with multiple discount codes were failing. I'll have a
detailed post-mortem by EOD.

@sarah-pm [10:15 AM]
Thanks for the quick resolution, everyone. How many users were
affected?

@emma-ops [10:18 AM]
Based on the logs, approximately 340 users received errors during
the 27-minute window. About 85% of them successfully completed
their purchase after the rollback. We'll follow up with the
remaining users.

@sarah-pm [10:20 AM]
OK. Please keep me updated. I'll inform the client.
```

### Questions

**A. Timeline — put events in the correct order (1-5):**

- [ ] James rolled back the deployment
- [ ] Emma noticed the 500 errors
- [ ] James deployed the payment validation update
- [ ] Error rate returned to normal
- [ ] Emma confirmed checkout was working

**B. Short answer:**

1. What HTTP error code were users receiving?
2. What was the error rate before the incident?
3. What time was the faulty deployment made?
4. What was the root cause of the errors?
5. How many users were affected?
6. What percentage of affected users completed their purchase after the fix?
7. What will James write by end of day?

**C. Vocabulary — match the Slack/work terms:**

| Term | Definition |
|------|-----------|
| 1. 500 error | a. returning to a previous software version |
| 2. rollback | b. a server-side error |
| 3. post-mortem | c. the percentage of failed requests |
| 4. error rate | d. a written analysis of what went wrong |
| 5. edge case | e. an unusual situation not covered by normal logic |

---

## Text 4 — Slack Thread: Technical Decision

```
#backend channel

@orkhan [2:30 PM]
Hey team, I need some input. For the new notification system, I'm
deciding between two approaches:

Option A: Send emails synchronously when events happen
Option B: Use a message queue (Redis + Bull) and process them async

Option A is simpler to implement but could slow down the API response
if the email service is slow. Option B is more work upfront but
scales better and won't block the user.

Thoughts?

  @lisa [2:35 PM]
  Definitely Option B. We had issues with synchronous email sending
  at my previous company — when the email provider had a brief outage,
  it brought down our entire API. Not worth the risk.

  @tom [2:38 PM]
  +1 for Option B. Also, with a queue you get retry logic for free —
  if an email fails, it just gets retried automatically. With
  synchronous, you'd have to build that yourself.

  @james [2:42 PM]
  Agree with everyone. One thing to consider — make sure you set a
  dead letter queue for emails that fail after all retries. You don't
  want failed emails to just disappear silently.

  @orkhan [2:45 PM]
  Great, Option B it is. Good point about the dead letter queue,
  @james — I'll add that. I'll have a draft PR ready for review
  by Friday.

  @david-lead [3:10 PM]
  Late to the party but +1 on Option B. Also, please add a rate
  limit — max 50 emails per user per day. We don't want to spam
  anyone accidentally.

  @orkhan [3:12 PM]
  👍 Noted. Will include rate limiting in the PR.
```

### Questions

1. What are the two options Orkhan is considering?
2. What is the main disadvantage of Option A?
3. What happened at Lisa's previous company?
4. What advantage of queues does Tom mention?
5. What does James recommend adding?
6. What is a "dead letter queue"?
   - A. A queue for deleted emails
   - B. A queue for emails that failed after all retry attempts
   - C. A queue for urgent emails
7. What rate limit does David suggest?
8. When will Orkhan have the PR ready?

---

## Answers

### Text 1:
1. The client requested an additional day to prepare their feedback.
2. Thursday 18th at 3:00 PM.
3. 60 minutes (20+15+15+10).
4. To discuss the testing timeline for the next release.
5. A brief summary (2-3 sentences) of what they completed this sprint.
6. In the #sprint-review Slack channel.
7. By end of day Tuesday.

### Text 2:
1. Monday.
2. Fintech.
3. The payment integration project.
4. Set up his dev environment and give him access to the repos.
5. Checking in with Alex, answering questions, showing him how things work — for two weeks.
6. The team wiki and coding standards doc.
7. B — I'm happy to listen to suggestions.

### Text 3:
**A. Timeline:**
3 → 1 → 2 → 4 → 5
(Deploy at 9:35 → Emma noticed at 9:47 → Rollback at 10:02 → Error rate dropped → Confirmed working at 10:04)

**B:**
1. 500
2. 0.1%
3. 9:35 AM
4. Orders with multiple discount codes were failing (edge case in payment validation)
5. Approximately 340 users
6. About 85%
7. A (detailed) post-mortem

**C:**
1-b, 2-a, 3-d, 4-c, 5-e

### Text 4:
1. Synchronous email sending vs. asynchronous with a message queue.
2. It could slow down the API response if the email service is slow.
3. When the email provider had an outage, it brought down the entire API.
4. Retry logic — failed emails get retried automatically.
5. A dead letter queue.
6. B
7. Maximum 50 emails per user per day.
8. By Friday.
