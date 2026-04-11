# B1 Reading: Tech Documentation & Work Communication

Real iş mühitində rastlaşacağınız mətn növlərini oxuyub başa düşmə məşqləri: GitHub issues, PR descriptions, Jira tickets.

---

## Text 1 — GitHub Issue

```
Title: Search results page loads slowly when filtering by date

Labels: bug, performance, priority: high

Opened by: @emma-dev — 3 days ago

## Description

When a user applies a date filter on the search results page, the page takes
between 8 and 12 seconds to load. Without the filter, the page loads in under
2 seconds. This is affecting user experience significantly.

## Steps to Reproduce

1. Go to the search page
2. Enter any search term (e.g., "laptop")
3. Click "Filter by date"
4. Select a date range (e.g., last 30 days)
5. Observe the loading time

## Expected Behaviour

The page should load in under 3 seconds with or without filters.

## Actual Behaviour

The page takes 8-12 seconds to load when the date filter is applied.

## Environment

- Browser: Chrome 120, Firefox 121
- OS: Windows 11, macOS Sonoma
- Screen size: desktop (1920x1080)

## Additional Context

I suspect this is related to the database query — the date filter might not
be using an index. I've checked the frontend and the delay is clearly
server-side.

---

Comment by @james-backend (2 days ago):
I looked into this. You're right — the date column doesn't have an index.
I'll create a PR to add one and run performance tests.

Comment by @emma-dev (1 day ago):
Thanks James. Let me know when the PR is ready and I'll test it on staging.
```

### Comprehension Questions

1. What is the main problem described in this issue?
2. How long does the page take to load with the date filter?
3. How long should the page take to load according to the expected behaviour?
4. What does Emma suspect is causing the problem?
5. What solution does James propose?
6. Where will Emma test the fix?

### Vocabulary

Find the words/phrases that mean:

1. reproduce = __________ (to repeat the problem)
2. the problem is on the server, not the browser = __________
3. a type of database optimisation = __________
4. a testing environment before production = __________

---

## Text 2 — Pull Request Description

```
Title: Add database index to improve search filter performance

PR #347 | base: main ← feature/search-index | by @james-backend

## Summary

This PR adds a composite index on the `created_at` and `category_id` columns
in the `products` table to resolve the slow search performance reported in
issue #289.

## Changes

- Added migration file: `20240115_add_search_index.sql`
- Updated the search query in `SearchRepository.java` to use the new index
- Added a performance test that verifies search with date filter responds
  in under 3 seconds

## Testing

- Ran the full test suite — all 247 tests pass
- Performance test results:
  - Before: 8.3 seconds average (with date filter)
  - After: 1.2 seconds average (with date filter)
  - Without filter: 1.1 seconds (no change)

## Checklist

- [x] Code follows project style guidelines
- [x] Tests added for new functionality
- [x] Documentation updated
- [x] No breaking changes
- [ ] Reviewed by at least one team member

## Notes

The index adds approximately 15 MB to the database size. Given that our
database is currently 4.2 GB, this is negligible. I've also checked that
the index doesn't negatively affect write performance — insert times
increased by less than 1 millisecond.

---

Review by @emma-dev:
LGTM! The performance improvement is impressive. One small nit: could you
add a comment in the migration file explaining why we chose a composite
index instead of two separate indexes?

Review by @lead-dev:
Approved. Let's deploy this to staging first and monitor for 24 hours
before pushing to production.
```

### Comprehension Questions

1. What does this PR add to the database?
2. Which table is affected?
3. What was the average load time before the fix?
4. What was the average load time after the fix?
5. How much extra space does the index require?
6. What does Emma ask James to add?
7. What does the lead developer want to do before production deployment?

### Vocabulary

Match the terms:

| Term | Definition |
|------|-----------|
| 1. composite index | a. a small, non-critical comment in a code review |
| 2. migration file | b. a database optimisation using multiple columns |
| 3. nit | c. a script that changes the database structure |
| 4. breaking change | d. so small it's not worth worrying about |
| 5. negligible | e. a modification that stops existing features from working |

---

## Text 3 — Jira Ticket

```
PROJ-1042: Implement email notification system

Type: Story
Priority: Medium
Sprint: Sprint 14 (Jan 15 - Jan 29)
Assignee: Orkhan M.
Reporter: Product Manager
Story Points: 8

## Description

As a user, I want to receive email notifications when:
- Someone comments on my post
- Someone mentions me in a comment
- My account settings are changed
- A weekly digest of activity on posts I follow

## Acceptance Criteria

1. Users can enable/disable each notification type independently
2. Emails are sent within 5 minutes of the triggering event
3. Weekly digest is sent every Monday at 9:00 AM (user's local time)
4. Users can unsubscribe from all emails with one click
5. Email templates follow the company's brand guidelines

## Technical Notes

- Use the existing email service (SendGrid)
- Store notification preferences in the `user_settings` table
- Queue emails using the job processing system (Redis + Bull)
- Rate limit: maximum 50 emails per user per day

## Sub-tasks

- [ ] PROJ-1043: Design email templates (assigned to: Design team)
- [ ] PROJ-1044: Create notification preferences API
- [ ] PROJ-1045: Implement email queue system
- [ ] PROJ-1046: Write integration tests
- [ ] PROJ-1047: Update user settings page in frontend

## Comments

Product Manager (3 days ago):
Please prioritise the comment notification — that's the one users have
been requesting the most. The weekly digest can come in a follow-up sprint
if needed.

Tech Lead (2 days ago):
Make sure to handle the case where a user deletes their account before a
queued email is sent. We don't want to send emails to non-existent users.
```

### Comprehension Questions

1. What type of Jira ticket is this?
2. How many story points is this task estimated at?
3. Name two of the four notification types the user should receive.
4. How quickly should emails be sent after the triggering event?
5. Which notification should be prioritised according to the Product Manager?
6. What edge case does the Tech Lead mention?
7. What email service should be used?
8. What is the maximum number of emails a user can receive per day?

### True / False / Not Given

9. The weekly digest will be sent on Fridays.
10. Users can turn off individual notification types.
11. The design team will create the email templates.
12. The task must be completed in Sprint 14.

---

## Answers

### Text 1:
**Comprehension:**
1. The search results page loads slowly when filtering by date.
2. Between 8 and 12 seconds.
3. Under 3 seconds.
4. The date column doesn't have a database index.
5. Add an index and run performance tests.
6. On staging.

**Vocabulary:**
1. reproduce / steps to reproduce
2. server-side
3. index
4. staging

### Text 2:
**Comprehension:**
1. A composite index on `created_at` and `category_id` columns.
2. The `products` table.
3. 8.3 seconds.
4. 1.2 seconds.
5. Approximately 15 MB.
6. A comment explaining why a composite index was chosen.
7. Deploy to staging and monitor for 24 hours.

**Vocabulary:**
1-b, 2-c, 3-a, 4-e, 5-d

### Text 3:
**Comprehension:**
1. Story
2. 8
3. Any two of: comment notification, mention notification, account settings change, weekly digest
4. Within 5 minutes.
5. Comment notification.
6. A user might delete their account before a queued email is sent.
7. SendGrid
8. 50

**True/False/Not Given:**
9. FALSE (Monday at 9 AM)
10. TRUE
11. TRUE
12. NOT GIVEN (PM said weekly digest can come in a follow-up sprint "if needed")
