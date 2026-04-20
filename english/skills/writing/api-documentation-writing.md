# API Documentation Writing — API Sənədləşdirmə

## Səviyyə
B1-B2 (tech vacib!)

---

## Niyə Vacibdir?

API docs:
- Developer onboarding
- Customer success
- Portfolio göstərir
- Open source layihə vacibdir

**Yaxşı API docs = peşəkar imza**

---

## API Docs Struktur

```markdown
# API Name

## Overview
[What it does]

## Authentication
[How to authenticate]

## Base URL
[Production and sandbox URLs]

## Endpoints
[Detailed endpoint docs]

## Error Codes
[Common errors]

## Rate Limiting
[Limits]

## SDKs & Examples
[Code examples]

## Changelog
[Version history]
```

---

## 1. Overview Section

### Nə daxil edəcək?

- Purpose
- Audience (developers)
- Use cases

### Example

```markdown
# Payment API

The Payment API allows you to process credit card
payments, refunds, and view transaction history.

## Use Cases

- Accept credit card payments
- Issue refunds
- Retrieve transaction details
- Verify cards
```

---

## 2. Authentication

### Types

- **API key** (header / query param)
- **OAuth 2.0**
- **JWT**
- **Basic auth**

### Example (API Key)

```markdown
## Authentication

All requests require an API key in the `Authorization` header:

```
Authorization: Bearer YOUR_API_KEY
```

To get an API key, sign up at [dashboard.example.com](...).
```

---

## 3. Endpoint Documentation

Hər endpoint üçün:

### Required sections

1. **Method + path** (GET /users)
2. **Description**
3. **Parameters**
4. **Request example**
5. **Response example**
6. **Error codes**

### Template

```markdown
## GET /users

Retrieves a list of users.

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | int | No | Page number (default: 1) |
| `limit` | int | No | Items per page (default: 20, max: 100) |

### Request

```bash
curl -X GET https://api.example.com/users \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Response (200 OK)

```json
{
  "users": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    }
  ],
  "total": 100,
  "page": 1
}
```

### Errors

| Code | Meaning |
|------|---------|
| 401 | Invalid authentication |
| 403 | Insufficient permissions |
```

---

## 4. Request Methods

### HTTP verbs

- **GET** = oxu
- **POST** = yarat
- **PUT** = tam yenilə
- **PATCH** = qismən yenilə
- **DELETE** = sil

### Explain in docs

- "Use **POST** to create a new user."
- "Use **PATCH** to update specific fields."

---

## 5. Parameters

### Types

- **Path parameter**: `/users/{id}`
- **Query parameter**: `/users?page=2`
- **Request body**: JSON body
- **Header**: `Authorization: Bearer ...`

### Format

Təşkil edilmiş table ilə:

```markdown
### Parameters

| Name | In | Type | Required | Description |
|------|-----|------|----------|-------------|
| `id` | path | int | Yes | User ID |
| `expand` | query | string | No | Related data to include |
| `name` | body | string | Yes | User's full name |
| `email` | body | string | Yes | Valid email |
```

---

## 6. Request / Response Examples

### Request

```markdown
### Request

```bash
curl -X POST https://api.example.com/users \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com"
  }'
```
```

### Response

```markdown
### Response (201 Created)

```json
{
  "id": 123,
  "name": "John Doe",
  "email": "john@example.com",
  "created_at": "2024-04-19T10:30:00Z"
}
```
```

### Multiple languages

Bir neçə dil nümunəsi:

```markdown
### Python

```python
import requests

response = requests.post(
    "https://api.example.com/users",
    headers={"Authorization": f"Bearer {api_key}"},
    json={"name": "John", "email": "john@example.com"}
)
```

### JavaScript

```javascript
const response = await fetch('https://api.example.com/users', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${apiKey}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ name: 'John', email: 'john@example.com' })
});
```
```

---

## 7. Error Codes

Standardlaşdırılmış cədvəl:

```markdown
## Error Codes

| Code | Status | Meaning |
|------|--------|---------|
| 200 | OK | Success |
| 201 | Created | Resource created |
| 400 | Bad Request | Invalid input |
| 401 | Unauthorized | Invalid auth |
| 403 | Forbidden | Permission denied |
| 404 | Not Found | Resource doesn't exist |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | Our bug |
```

### Error response format

```json
{
  "error": {
    "code": "invalid_email",
    "message": "The email address is invalid.",
    "field": "email"
  }
}
```

---

## 8. Rate Limiting

### Explain limits

```markdown
## Rate Limiting

API requests are limited to **100 per minute per API key**.

### Response headers

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 87
X-RateLimit-Reset: 1713456000
```

### Exceeding limits

If you exceed the limit, you'll receive a **429 Too Many Requests**
response. Retry after the `Retry-After` seconds.
```

---

## 9. Pagination

### Cursor-based

```markdown
### Pagination

Use `cursor` parameter for paging:

```
GET /users?cursor=abc123
```

Response includes `next_cursor`:

```json
{
  "users": [...],
  "next_cursor": "def456",
  "has_more": true
}
```
```

### Page-based

```markdown
```
GET /users?page=2&limit=20
```
```

---

## 10. Webhooks

```markdown
## Webhooks

Register a webhook URL to receive event notifications:

### Register

```bash
POST /webhooks
{
  "url": "https://yourapp.com/webhook",
  "events": ["payment.succeeded", "payment.failed"]
}
```

### Receive

We send POST requests to your URL:

```json
{
  "event": "payment.succeeded",
  "data": {...},
  "timestamp": 1713456000
}
```

### Verify signature

We sign each request with HMAC-SHA256:

```
X-Signature: sha256=abcdef...
```
```

---

## 11. SDKs / Libraries

### List clients

```markdown
## Official SDKs

- **Python**: `pip install example-api`
- **Node.js**: `npm install example-api`
- **Ruby**: `gem install example-api`
- **PHP**: `composer require example/api`

### Community SDKs

- **Go**: [github.com/...](...)
- **Rust**: [github.com/...](...)
```

---

## 12. Versioning

### Explain strategy

```markdown
## Versioning

We version the API via URL path:

- `/v1/users` - stable
- `/v2/users` - current
- `/v3/users` - beta

### Deprecation

Old versions are supported for **6 months** after a new
version is released.

### Breaking changes

Breaking changes are only introduced in new major versions.
Minor changes (adding fields) are added without versioning.
```

---

## 13. Style Guide

### ✓ Do

- **Action verbs**: "Creates a user", "Returns the list"
- **Present tense**: "This endpoint **returns**..."
- **Third person**: "The API **accepts** JSON"
- **Consistent naming**: camelCase or snake_case, pick one
- **Examples everywhere**: real code

### ✗ Don't

- First person ("I", "we")
- Future tense ("will return")
- Vague descriptions
- Missing examples
- Broken links

---

## 14. Real World Examples

### Inspirations

Read these — great docs:

- **Stripe**: stripe.com/docs
- **Twilio**: twilio.com/docs
- **GitHub**: docs.github.com
- **Anthropic**: docs.anthropic.com

---

## 15. Tools

### Documentation generators

- **Swagger / OpenAPI** — industry standard
- **Postman** — API testing + docs
- **ReadMe.io** — hosted docs
- **Docusaurus** — static site
- **MkDocs** — static site
- **Slate** — beautiful docs

### OpenAPI specification

```yaml
openapi: 3.0.0
info:
  title: Example API
  version: 1.0.0
paths:
  /users:
    get:
      summary: List users
      responses:
        '200':
          description: Success
```

---

## 16. Writing Tips

### 1. Write for developers

- Assume technical knowledge
- No marketing fluff
- Direct, clear

### 2. Code examples are king

- Runnable examples
- Multiple languages
- Show input + output

### 3. Keep updated

- Docs go stale fast
- Update with each release
- Version docs with API

### 4. Add diagrams

- Authentication flows
- Webhook flow
- Pagination

---

## 17. Common Sections (Template)

```markdown
# My API

## Overview
## Getting Started
## Authentication
## Quickstart
## API Reference
  ### Users
    - List users
    - Get user
    - Create user
    - Update user
    - Delete user
  ### Posts
    - ...
## Error Codes
## Rate Limiting
## Webhooks
## Pagination
## SDKs
## Changelog
## FAQ
```

---

## 18. Interview Context

### Portfolio

API docs göstərmək:
- Contribute to open source
- Create your own docs
- Include in portfolio

### Interview

- "I wrote API docs for..." ✓
- "I improved developer onboarding via docs..." ✓

---

## Common Phrases

### Describing endpoints

- "This endpoint **returns**..."
- "**Creates** a new..."
- "**Retrieves** a list of..."
- "**Updates** the specified..."
- "**Deletes** the resource..."

### Parameters

- "**Required** parameters..."
- "**Optional** parameters..."
- "**Defaults to** X if not specified."

### Errors

- "Returns **404** if not found."
- "Throws **InvalidRequest** for bad input."

---

## Azərbaycanlı Səhvləri

- ✗ Azərbaycanca yazma!
- ✓ **English** docs always.

- ✗ Missing examples
- ✓ **Real code** examples mütləq

- ✗ Vague: "returns data"
- ✓ Specific: "returns JSON with `users` array"

---

## Xatırlatma

**Yaxşı API Docs:**
1. ✓ Clear overview
2. ✓ Authentication section
3. ✓ Endpoint details (method, params, response)
4. ✓ Real code examples (multiple languages)
5. ✓ Error codes table
6. ✓ Rate limits
7. ✓ Versioning policy
8. ✓ SDKs list
9. ✓ Changelog
10. ✓ **Keep updated!**

**Qızıl qayda:** Documentation is a **feature**, not an afterthought.

→ Related: [pr-descriptions.md](pr-descriptions.md), [readme-writing.md](readme-writing.md), [design-doc-writing.md](design-doc-writing.md)
