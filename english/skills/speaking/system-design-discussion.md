# System Design Discussion (B2)

Texniki müsahibələrdə "design a system that..." tipli suallar tez-tez verilir. Məqsəd sizin dərin bilik deyil, **strukturlu düşünmə tərzinizi** və **aydın ifadə etmə bacarığınızı** göstərməkdir. Bu fayl system design söhbətinin hər mərhələsi üçün ingiliscə ifadələr verir.

---

## Ümumi Struktur

Yaxşı bir system design cavabı adətən bu ardıcıllıqla olur:

1. **Clarify requirements** — tələbləri aydınlaşdır
2. **Estimate scale** — miqyası hesabla
3. **High-level design** — ümumi dizayn
4. **Deep dive** — 1-2 komponentə daha dərindən bax
5. **Trade-offs & alternatives** — güzəştlər və alternativlər
6. **Scaling & bottlenecks** — miqyaslama və darboğazlar

Hər mərhələni ingiliscə necə idarə edəcəyinizi aşağıda görəcəksiniz.

---

## 1. Tələbləri Aydınlaşdırmaq (Clarifying Requirements)

İlk 5 dəqiqə **sual verməklə** keçməlidir. Bu, təcrübəli mühəndisin ən vacib bacarıqlarından biridir.

### Functional requirements (nə etməlidir)
- "Before I jump into the design, I'd like to clarify a few things."
- "What are the **core features** we're designing for?"
- "Should the system support **[specific feature]**, or is that out of scope?"
- "Who are the **primary users** — end users, internal teams, or third-party developers?"
- "Is this a **read-heavy** or **write-heavy** system?"

### Non-functional requirements (necə etməlidir)
- "What kind of **scale** are we looking at — hundreds, thousands, millions of users?"
- "What's the expected **read-to-write ratio**?"
- "Are there any **latency requirements** — how fast should responses be?"
- "What **availability** do we need — 99%, 99.9%, 99.99%?"
- "Are there any **consistency** requirements, or is eventual consistency fine?"
- "Any specific **compliance** or data residency requirements?"

### Scope
- "Should I assume this is a **greenfield project** or are we extending an existing system?"
- "Are we designing for **mobile, web, or both**?"
- "Is **internationalization** in scope?"

### Real-life misal

> **Interviewer:** Design Twitter.
>
> **Candidate:** Sure. Before I start, let me clarify a few things. First, are we designing the whole product or focusing on a specific feature — like the timeline, tweeting, or search? Second, what scale are we talking about — Twitter has hundreds of millions of users, but if we're designing at smaller scale, the approach changes. And third, do we need to support media uploads, or is it text-only for this exercise?

---

## 2. Miqyası Təxmin Etmək (Estimating Scale)

Rəqəmləri yüksək səslə hesablamaq müsahibi razı salır. Dəqiq olmaq vacib deyil — **struktur göstərmək** vacibdir.

### Phrases
- "Let me do some **back-of-the-envelope calculations**."
- "If we assume **[number]** users and **[number]** actions per day, that gives us roughly..."
- "So we're looking at approximately **[X]** requests per second."
- "Assuming each record is about **[Y]** KB, storage would be around **[Z]** TB per year."

### Misal

> "Let's say we have 300 million daily active users, and the average user sends 2 tweets per day. That's 600 million tweets per day, or about 7,000 tweets per second on average. But we should assume peak traffic is 3-4 times that, so let's design for around 25,000 writes per second at peak. For reads — people read way more than they write — let's say 100x. So we're looking at roughly 2.5 million reads per second at peak."

---

## 3. Yüksək Səviyyəli Dizayn (High-Level Design)

Bir neçə əsas komponent çəkin və onları bir-birinə bağlayın.

### Təqdimat
- "Let me sketch out the main components."
- "At a high level, the system has **[N]** main services: **[list]**."
- "User requests come in through **[entry point]**, get routed to **[service]**, which then..."
- "I'll draw this out as we go."

### Komponentləri təsvir etmək
- "The **[component]** is responsible for **[responsibility]**."
- "This service handles all **[X]** operations."
- "We have a **[cache / queue / database]** sitting between **[A]** and **[B]**."
- "Data flows from **[source]** through **[process]** to **[destination]**."

### Bağlantılar
- "**Service A** calls **Service B** synchronously for this operation."
- "For this flow, I'd make the call **asynchronous** — we don't need an immediate response."
- "These two services communicate via a **message queue**."
- "We publish an event here and **[other service]** consumes it."

### Misal

> "At a high level, we have a client (web or mobile), which talks to a load balancer. Behind the load balancer, we have an API gateway that handles authentication and rate limiting. The gateway routes to three main services: a tweet service, a timeline service, and a user service. We have PostgreSQL for user data and relationships, Cassandra for tweets themselves because of the scale, and Redis for caching timelines. For the fan-out when someone tweets, we use Kafka as a message queue."

---

## 4. Dərindən Baxış (Deep Dive)

Müsahib adətən bir komponenti seçib dərindən soruşacaq.

### Yanaşma
- "Let me go deeper into **[component]**."
- "There are a couple of ways to approach this. Option one is **[A]**, option two is **[B]**."
- "I think I'd go with **[option]** because **[reason]**."
- "Let me walk you through the flow step by step."

### Data model
- "The main entities are: **[list]**."
- "A **[User]** has many **[Posts]**, and each **[Post]** has many **[Comments]**."
- "I'd store this in a **[relational / key-value / document]** database because **[reason]**."

### Algorithms
- "For this, we can use a **[specific technique]** — for example, a **consistent hash ring**."
- "The trade-off is **[A]** vs **[B]** — I'd lean toward **[A]** here because **[reason]**."

---

## 5. Trade-offs və Alternativlər

Bu, təcrübəli mühəndisi yeni başlayandan ayıran ən vacib mərhələdir.

### Trade-off izah etmək
- "There's a **trade-off** between **[A]** and **[B]**."
- "We can go with **[option]**, but the **downside** is **[downside]**."
- "The **upside** of this approach is **[upside]**; the **downside** is **[downside]**."
- "It really depends on what we optimize for — **latency** or **consistency**."

### Alternativləri müqayisə etmək
- "We could also use **[alternative]** here. The main difference is **[difference]**."
- "I chose **[option]** over **[alternative]** because **[reason]**, but **[alternative]** would make sense if **[condition]**."
- "In practice, both work — it depends on your **operational preferences**."

### Misal

> "For the feed generation, there are two main approaches: push-based fan-out, where we write to every follower's timeline when someone tweets, and pull-based, where we compute the timeline on read. The trade-off is write cost vs read cost. Push-based gives you fast reads but expensive writes for users with many followers — think celebrities with millions of followers. Pull-based is the opposite. In practice, most large systems use a hybrid — push for normal users, pull for celebrity accounts."

---

## 6. Miqyaslama və Darboğazlar

### Darboğazları müəyyən etmək
- "The first bottleneck we'd hit is probably the **[component]**."
- "At this scale, the **[database / API / cache]** becomes the limiting factor."
- "This is where we'd start to see **[symptom]**."

### Həllər
- "To address this, we can **[action]**."
- "We'd **shard** the database by **[key]**."
- "We can **replicate** reads across multiple regions."
- "Adding a **CDN** in front reduces load on origin servers."
- "For the hot-key problem, we could **partition** further or add a **local cache** per service."

### Availability və reliability
- "For **high availability**, we'd deploy across multiple availability zones."
- "We'd need a **failover strategy** for the database."
- "Each service should be **stateless** so we can scale horizontally."
- "We'd add **circuit breakers** so a downstream failure doesn't cascade."

### Misal

> "The first bottleneck at this scale would be the write path to the tweets database. At 25,000 writes per second, a single Cassandra cluster can handle it, but if we grow 5x, we'd need to shard more aggressively. For reads, Redis gives us a lot of headroom — a single Redis instance can handle around 100,000 ops per second. Beyond that, we'd partition Redis by user ID. We'd also add a CDN for static assets and media."

---

## 7. Bilmədiyiniz Zaman

Müsahibdə hər şeyi bilmək lazım deyil. Vacib olan **düşünmə tərzinizi** göstərməkdir.

- "I haven't designed something at exactly this scale, but based on my experience with **[related system]**, I'd approach it as follows..."
- "I'm not 100% sure about the specifics of **[specific tech]**, but conceptually I'd use something like **[general approach]**."
- "I'd want to **benchmark this** in practice before committing to an approach."
- "That's a good question — I'd need to research **[specific thing]**, but my initial instinct is **[thought]**."
- "Honestly, I'd probably ask a teammate or read up on **[topic]** before implementing this."

---

## Tam Nümunə — Pastebin

> **Interviewer:** How would you design Pastebin?
>
> **Candidate:** Sure. Let me start with a few clarifying questions.
>
> First — what scale are we aiming for? Let's assume a million new pastes per day and maybe 10x reads. Second — do pastes expire, or are they permanent? Third — do we need features like syntax highlighting, editing, or access control?
>
> **Interviewer:** Let's say pastes can expire, most users are anonymous, reads are 10x writes, and no authentication is needed.
>
> **Candidate:** Perfect. So roughly 12 writes per second and 120 reads per second — small scale, definitely manageable.
>
> At a high level, I'd have three components: a web service that handles creation and retrieval, a storage layer for the paste content, and a metadata database for things like creation time and expiration.
>
> For the paste ID, I'd generate a short random code — say 8 characters of base-62 — which gives us enough uniqueness. I'd check for collisions before saving, though at this scale collisions will be extremely rare.
>
> For storage, I'd put the paste content in object storage like S3, because pastes can be large and we don't want to bloat the main database. The metadata — ID, creation time, expiration, content pointer — would go in a simple relational database like PostgreSQL.
>
> For reads, I'd add a Redis cache in front. Since content is immutable once created, caching is straightforward. We'd cache the most recently accessed pastes.
>
> For expiration, I'd have a background job that runs periodically — maybe every hour — and cleans up expired pastes. Alternatively, we could rely on S3 lifecycle rules if we set up the right object tags.
>
> The trade-off here is mostly simplicity versus features. I'm keeping it simple because the problem is small. If we needed to scale to, say, 10,000 writes per second, I'd shard the metadata database and maybe use a NoSQL store instead.
>
> Any particular area you'd like me to go deeper on?

---

## 8. Müsahibəçi ilə Real Vaxtda İnteraksiyon

### Müsahibəçi sözünü kəsəndə:
```
"Good point — let me address that."
"Actually, that's exactly what I was about to get to."
"You're right — I was oversimplifying. Let me revise."
"That's a fair concern. The reason I chose this was [X], 
but if [Y] is a priority, then [alternative] would work better."
```

### Sıradakı addıma keçmək istəyəndə:
```
"Should I go deeper here, or shall I move on to [next component]?"
"I can spend more time on this if useful — or would you prefer 
I continue with the high-level design first?"
"I'll keep this at a high level for now and come back 
if you want to drill in."
```

### Diaqram çəkərkən (whiteboard / screen share):
```
"Let me draw this out."
"On the left we have the client, and on the right the backend."
"This arrow represents a synchronous call."
"This dashed line is asynchronous — via a queue."
"Let me label the components as we go."
```

### Aydın olmayan tələbi netleşdirmək:
```
"Just to confirm — when you say 'scale', are you thinking 
millions of users, or more like tens of thousands?"
"Should I focus on the storage layer, or is the 
read path more interesting to you?"
"Are you looking for a production-grade design, 
or is a reasonable sketch sufficient?"
```

---

## 9. Ən Çox Düşdüyün Tələlər

### Tələ 1: Sual vermədən başlamaq
❌ "Okay, so I'll design a Twitter-like system..."
✅ "Before I start, let me ask a few clarifying questions."

### Tələ 2: Çox dərindən başlamaq
❌ Dərhal database schema-ya keçmək
✅ "Let me start at a high level and we can drill down."

### Tələ 3: Bir seçimi trade-off-suz qəbul etmək
❌ "I'll use PostgreSQL."
✅ "I'd use PostgreSQL here because it's relational and 
we need transactions — though at massive scale, we'd 
revisit that."

### Tələ 4: Susmaq
❌ 2 dəqiqə susaraq düşünmək
✅ "Let me think through this out loud — there are a few options..."

---

## Məşq: Öz sistem dizaynınızı danışın

Aşağıdakı 3 sualı seçin və hər birini 15-20 dəqiqəyə yüksək səslə izah edin:

1. **Design a rate limiter** (sorğu limitləyici dizayn et)
2. **Design a news feed** (xəbər axını dizayn et)
3. **Design a file upload service** (fayl yükləmə servisi dizayn et)
4. **Design an online chat app** (onlayn söhbət tətbiqi dizayn et)
5. **Design a web crawler** (veb saytları gəzən sistem dizayn et)

### Qeyd üçün şablon

| Mərhələ | Nə dedim | Sahəyə əlavə |
|---------|----------|--------------|
| Clarify | ... | ... |
| Estimate | ... | ... |
| High-level | ... | ... |
| Deep dive | ... | ... |
| Trade-offs | ... | ... |
| Scaling | ... | ... |

---

## Əsas "survival" ifadələri

Müsahibdə yaddan çıxarsanız, bu üç ifadə vaxt qazandırar:

1. **"That's a great question. Let me think about it for a moment."** — fikrinizi toplamaq üçün
2. **"Let me make sure I understand correctly — you're asking about [X], right?"** — sualı təkrarlamaq üçün
3. **"I see two approaches here. Let me walk through both and then pick one."** — struktur qoymaq üçün
