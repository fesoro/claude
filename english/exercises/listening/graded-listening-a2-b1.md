# Graded Listening Exercises — A2/B1

Bu məşqlər real iş mühitindəki söhbətləri simulyasiya edir. Transkripsiyaları "dinləmə" metoduyla istifadə edin: əvvəlcə sualları oxuyun, sonra mətni yalnız bir dəfə oxuyun, sonra cavablayın.

---

## Track 1 — Daily Standup Meeting (A2)
**Topic:** Development team standup

**Team Lead:** Good morning, everyone. Let's go around quickly. Sarah, you first.

**Sarah:** Hi. Yesterday I finished the login page design. Today I'm going to start working on the registration form. No blockers.

**Team Lead:** Great. Ahmed?

**Ahmed:** So yesterday I was trying to fix the bug in the payment module, but I ran into a problem with the database connection. I spent most of the day debugging. Today I'll continue with that. I might need help from someone who knows the database configuration better.

**Team Lead:** OK, talk to David after the standup — he set up the database. Lisa?

**Lisa:** I completed the unit tests for the user profile feature yesterday. All tests are passing. Today I'm going to review Ahmed's pull request from last week and then start on the notification system. No blockers from my side.

**Team Lead:** Perfect. I'll keep it short from my end — I have a meeting with the client at two o'clock to discuss the timeline. If there are any changes, I'll update everyone on Slack. That's it for today. Thanks, everyone.

---

### Questions — Track 1

**A. Complete the sentences. Write NO MORE THAN THREE WORDS.**

1. Sarah finished the __________ yesterday.
2. Ahmed had a problem with the __________.
3. The team lead told Ahmed to talk to __________ about the database.
4. Lisa completed __________ for the user profile feature.
5. The team lead has a meeting with the __________ at 2 p.m.

**B. True / False / Not Given**

6. Sarah has no blockers today.
7. Ahmed fixed the bug in the payment module.
8. Lisa's tests are all failing.
9. David set up the database.
10. The team lead will send updates by email.

---

## Track 2 — Sprint Planning Meeting (B1)
**Topic:** Planning next sprint tasks

**Project Manager:** Right, so let's plan the next sprint. We have two weeks. The main priority from the client is the search functionality. James, can your team handle that?

**James:** We can, but it depends on how complex they want it. If it's just a basic keyword search, we can do it in about four or five days. But if they want filters and sorting as well, that's more like eight days, plus testing.

**Project Manager:** Let's go with the full version — filters and sorting included. The client specifically asked for it. Can you still fit it into the sprint?

**James:** It'll be tight, but yes, if we don't get pulled into other things. I'd need two developers full-time on it.

**Project Manager:** Done. I'll make sure nobody reassigns them. What about the mobile responsiveness issue? Emma, that's yours, right?

**Emma:** Yes. I've already started looking at it. The main problem is the dashboard — it doesn't display properly on tablets. I think I need about three days to fix it and another day for testing across different devices.

**Project Manager:** Four days total then. That fits nicely. Anything else we should include?

**Emma:** There are a few minor UI bugs that users have reported. Nothing critical, but it would be nice to clean them up. Maybe two days?

**Project Manager:** Let's add them as stretch goals — if there's time, we do them. If not, they go into the next sprint. Everyone clear? Good. Let's get to work.

---

### Questions — Track 2

**A. Choose the correct answer (A, B, or C).**

1. What is the main priority for the next sprint?
   - A. Mobile responsiveness
   - B. Search functionality
   - C. UI bug fixes

2. How long will the full search feature take, including testing?
   - A. Four or five days
   - B. About a week
   - C. About eight days plus testing

3. What condition does James set for completing the search feature?
   - A. He needs three developers
   - B. His team must not be reassigned to other tasks
   - C. The client must reduce the requirements

4. What is the main mobile responsiveness problem?
   - A. The app crashes on tablets
   - B. The dashboard doesn't display properly on tablets
   - C. Images don't load on mobile devices

5. What did the project manager decide about the minor UI bugs?
   - A. They will be fixed first
   - B. They are stretch goals for this sprint
   - C. They will be ignored

**B. Short answer. Write NO MORE THAN THREE WORDS AND/OR A NUMBER.**

6. How many weeks is the sprint?
7. How many developers does James need full-time?
8. How many days does Emma need for testing across devices?
9. Who reported the minor UI bugs?
10. Where will unfixed UI bugs go?

---

## Track 3 — One-on-One with Manager (B1)
**Topic:** Weekly catch-up between developer and manager

**Manager:** Hey, thanks for meeting with me. How's everything going this week?

**Developer:** Pretty good, actually. I managed to close three tickets yesterday, which puts me ahead of schedule for the sprint.

**Manager:** That's great to hear. How are you finding the new codebase? I know you only joined the team a month ago.

**Developer:** Honestly, it took me a while to get used to it. The architecture is quite different from what I worked with before. But the documentation has been really helpful, and Tom has been answering a lot of my questions.

**Manager:** Good, I'm glad Tom's been supportive. Is there anything you're struggling with?

**Developer:** The deployment process is still a bit confusing for me. I've deployed to staging twice, but I haven't done a production deployment yet. I'd feel more confident if I could shadow someone the first time.

**Manager:** Absolutely. Let's pair you with Tom for the next production release — that's probably next Thursday. I'll set it up.

**Developer:** That would be brilliant. Thanks.

**Manager:** Anything else? How about the team dynamic — do you feel settled in?

**Developer:** Yeah, everyone's been really welcoming. The only thing is, sometimes in meetings, people talk quite fast and use a lot of abbreviations I'm not familiar with. I don't always want to interrupt to ask.

**Manager:** That's completely fair. I'll mention it in the next team meeting — we should all be mindful of that. And please don't hesitate to ask in the moment. Nobody will mind.

**Developer:** I appreciate that. Thank you.

---

### Questions — Track 3

**A. Answer the questions. Write NO MORE THAN THREE WORDS AND/OR A NUMBER.**

1. How many tickets did the developer close yesterday?
2. How long ago did the developer join the team?
3. Who has been helping the developer with questions?
4. What process does the developer find confusing?
5. When is the next production release?

**B. True / False / Not Given**

6. The developer is behind schedule for the sprint.
7. The developer found the new codebase easy from the start.
8. The developer has deployed to production before.
9. The manager will pair the developer with Tom for the next release.
10. The developer is unhappy with the team dynamic.

---

## Track 4 — Code Review Discussion (B1)
**Topic:** Two developers reviewing a pull request

**Emma:** Hey Carlos, I finished reviewing your PR. Got a few minutes to go through my comments?

**Carlos:** Sure, yeah. I was wondering what you thought.

**Emma:** Overall, the logic is solid. I just had a couple of things. First — the function name `processData`. It's a bit generic. Since it's specifically transforming the user input before saving, something like `sanitizeUserInput` would make the intent clearer.

**Carlos:** That makes sense. I'll rename it.

**Emma:** Good. The other thing — in the loop on line fifty-eight, you're running a database query inside the loop. If there are a hundred records, that's a hundred queries. It will be fine in testing, but in production it could be slow.

**Carlos:** Ah, I see the problem. Should I load the data before the loop and pass it in?

**Emma:** Exactly. Eager loading would work well here. Check how we do it in the order service — there's a similar pattern there.

**Carlos:** Got it. And are you happy with the test coverage?

**Emma:** The happy path is well tested. I'd like to see at least one test for the case where the input is empty — just to make sure it doesn't throw an unexpected error.

**Carlos:** I'll add that now. So once I fix these three things, you'll approve?

**Emma:** Yes, push the changes and re-request my review. Shouldn't take long.

---

### Questions — Track 4

**A. True / False / Not Given**

1. Emma thinks the overall logic in the PR is incorrect.
2. Carlos should rename the function to `cleanUserInput`.
3. The database query inside the loop could cause performance problems in production.
4. Emma suggests Carlos look at the order service for an example.
5. The current test coverage includes a test for empty input.

**B. Fill in the blanks. Write NO MORE THAN TWO WORDS.**

6. Emma says the function name is too __________.
7. Running a query inside a loop could be slow if there are many __________.
8. Emma recommends using __________ loading to fix the performance issue.
9. Carlos needs to add a test for the case where the input is __________.
10. Carlos must re-request Emma's review after __________ the changes.

---

## Track 5 — Remote Onboarding Call (B1)
**Topic:** New developer's first call with team lead

**Alex:** Welcome, glad to have you on board. This call is just to run through the basics so you're not lost on day one.

**New Dev:** Thanks, I appreciate it. I have a few questions, but please go ahead.

**Alex:** Sure. So — we're fully remote, and the team is spread across three time zones. We don't have fixed hours, but we do ask that everyone is available for a three-hour overlap window, which is two to five p.m. Central European Time. Outside of that, async is fine.

**New Dev:** That works for me. What tools do you use?

**Alex:** Slack for communication — we try to keep decisions documented in threads, not in DMs. Jira for task tracking. Every ticket should have a status update at least once a day. And GitHub for code — you'll need to open a PR for anything, even small fixes. No pushing directly to main.

**New Dev:** Got it. And what does the first week look like?

**Alex:** The first two days, just read the documentation and set up your local environment. There are instructions in the onboarding repo. Day three, you'll have a call with the product team to understand what we're building and why. From day four you'll start picking up small tickets — we'll label them "good first issue" in Jira.

**New Dev:** And for meetings?

**Alex:** We have a team standup on Monday and Wednesday at three p.m. CET, and a sprint review every two weeks on Friday. Everything else is async by default.

**New Dev:** Perfect. This all makes sense.

---

### Questions — Track 5

**A. Choose the correct answer (A, B, or C).**

1. What are the core overlap hours?
   - A. 9 a.m. to 12 p.m. CET
   - B. 2 p.m. to 5 p.m. CET
   - C. 10 a.m. to 1 p.m. CET

2. Where should team decisions be documented?
   - A. In direct messages
   - B. In Slack threads
   - C. In email

3. What happens on day three of onboarding?
   - A. The new developer starts picking up tickets
   - B. The new developer sets up the local environment
   - C. The new developer has a call with the product team

4. What does "good first issue" mean in this context?
   - A. Tickets that are already done
   - B. Small, beginner-friendly tickets
   - C. Tickets with no deadline

5. How often does the full team have a standup?
   - A. Every day
   - B. Twice a week
   - C. Once a week

**B. Short answer. Write NO MORE THAN THREE WORDS AND/OR A NUMBER.**

6. How many time zones does the team work across?
7. What tool is used for task tracking?
8. How often should Jira tickets be updated?
9. Where are the local environment setup instructions?
10. How often does the sprint review happen?

---

## Track 6 — Salary Negotiation (B1-B2)
**Topic:** Developer discussing a job offer with HR

**HR:** Hi, thanks for getting back to us. I wanted to follow up on the offer we sent over. Have you had a chance to review it?

**Developer:** I have, yes. I'm really excited about the role. One thing I wanted to discuss is the base salary — it's a bit lower than I was expecting based on the market.

**HR:** I understand. Our base for this level is set at sixty-two thousand. We've tried to be competitive within our structure. Is there a specific number you had in mind?

**Developer:** I was thinking around sixty-eight thousand. That's in line with what I've seen for senior roles in this area.

**HR:** I hear you. I'll be honest — we don't have much room to move on the base. What I can do is flag it for the hiring manager. But I don't want to overpromise.

**Developer:** I appreciate the honesty. Are there other components we could discuss? Remote allowance, for example?

**HR:** Yes, actually. We offer a five-hundred-euro annual remote work allowance for equipment and internet. That's on top of the base. And performance reviews happen every six months, so there's an opportunity to increase the base at the first review if things go well.

**Developer:** That's good to know. And on the start date — the offer says the first of next month. Is there any flexibility there?

**HR:** We'd ideally like someone in by then, but if you need an extra week, that's manageable. Just let us know.

**Developer:** Alright. Can I have until end of this week to give you a final answer?

**HR:** Of course. We'll hold the offer until Friday.

---

### Questions — Track 6

**A. True / False / Not Given**

1. The developer is unhappy with the role itself.
2. The HR manager says the base salary cannot be changed at all.
3. The remote work allowance is paid monthly.
4. Performance reviews happen twice a year.
5. The company cannot wait beyond the original start date under any circumstances.

**B. Fill in the blanks. Write NO MORE THAN THREE WORDS AND/OR A NUMBER.**

6. The offered base salary is __________ thousand euros.
7. The developer asked for a base salary of __________ thousand euros.
8. The remote work allowance is __________ euros per year.
9. The developer asked for the final answer deadline to be __________.
10. The company will hold the offer until __________.

---

## Answers

### Track 1:
**A:**
1. login page design
2. database connection
3. David
4. unit tests
5. client

**B:**
6. TRUE
7. FALSE (he was still debugging)
8. FALSE (all tests are passing)
9. TRUE
10. FALSE (he will update on Slack)

### Track 2:
**A:**
1. B
2. C
3. B
4. B
5. B

**B:**
6. Two (weeks)
7. Two (developers)
8. One (day)
9. Users
10. (Into) the next sprint

### Track 3:
**A:**
1. Three (tickets)
2. A month (ago)
3. Tom
4. The deployment process
5. (Next/Probably next) Thursday

**B:**
6. FALSE (ahead of schedule)
7. FALSE (took a while to get used to)
8. FALSE (hasn't done a production deployment)
9. TRUE
10. FALSE (everyone's been welcoming)

### Track 4:
**A:**
1. FALSE (she says the logic is solid)
2. FALSE (Emma suggests `sanitizeUserInput`, not `cleanUserInput`)
3. TRUE
4. TRUE
5. FALSE (Carlos needs to add this test)

**B:**
6. generic
7. records
8. eager
9. empty
10. pushing

### Track 5:
**A:**
1. B
2. B
3. C
4. B
5. B

**B:**
6. Three (time zones)
7. Jira
8. Once a day / daily
9. (In the) onboarding repo
10. Every two weeks

### Track 6:
**A:**
1. FALSE (she is excited about the role)
2. NOT GIVEN (HR says "not much room" and will flag it — not a definitive no)
3. FALSE (it is annual, not monthly)
4. TRUE
5. FALSE (HR says an extra week is manageable)

**B:**
6. sixty-two (62)
7. sixty-eight (68)
8. five hundred (500)
9. end of (this) week / Friday
10. Friday
