# Reddit (Lead)

## Ümumi baxış
- **Nə edir:** Link paylaşma və müzakirə platforması. Threaded şərhli icmalar ("subreddit"-lər), upvote/downvote, ön səhifə reytinqi. "The front page of the internet."
- **Yaradılıb:** 2005-ci ildə Steve Huffman və Alexis Ohanian tərəfindən (Y Combinator-un ilk batch-ində).
- **Miqyas:**
  - ~500M+ həftəlik aktiv istifadəçi (hesabata görə dəyişir).
  - 15+ ildir ABŞ-da ən çox ziyarət edilən 10 saytdan birinə daxildir.
  - Milyonlarla subreddit.
- **Əsas tarixi anlar:**
  - 2005: ilkin olaraq **Common Lisp**-də yazıldı!
  - 2005 (sonda): bir neçə ay ərzində Python-a yenidən yazıldı.
  - 2006: Condé Nast tərəfindən alındı.
  - 2011: yenidən müstəqil oldu.
  - 2018: böyük redesign — React + Node.js-də yeni frontend; köhnə "old.reddit.com" hələ də Python.
  - 2023: API dəyişiklik mübahisəsi; kütləvi etiraz blackout-ları.
  - 2024: IPO.

Reddit klassik "Python + Postgres + Cassandra" case study-dir, 15 ildən artıqdır əslində eyni arxitekturada yüksək səviyyəli trafiki idarə edir.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Language (legacy + core) | Python (Py2-dən Py3-ə keçdi) | 2005-də ilkin yenidən yazılma |
| Original framework | Pylons → "r2" (Reddit-in özünün) | Ehtiyacları üçün qurulub |
| Newer frontend | React + Node.js (Next.js) | 2018 redesign |
| Primary DB | PostgreSQL (sharded) | Yetkin, yaxşı əməliyyat hekayəsi |
| Wide-column DB | Cassandra | Böyük miqyasda vote-lar, şərhlər |
| Cache | Memcached (böyük kluster) | Standart |
| Queue | Tarixən RabbitMQ → öz queue layer-ləri | Async job-lar |
| Search | Lucene-əsaslı (müxtəlif dövrlərdə Solr / Elasticsearch) | Content axtarışı |
| Real-time | Canlı threadlər üçün WebSocket-lər | Canlı xəbər yenilikləri |
| Infrastructure | Əsasən AWS | Uzun müddətlik AWS shop-u |
| Monitoring | Standart observability (Datadog, Graphite və s.) | Sənaye standartı |

## Dillər — Nə və niyə

### Common Lisp (ilk 6 ay)
- Steve Huffman və Aaron Swartz ilkin olaraq Reddit-i Common Lisp-də yazdılar.
- Tez praktik problemlərlə üzləşdilər: web üçün kitabxana ekosistemi nazik idi, hiring daha çətin idi.
- İşə salınmadan bir neçə ay sonra Python-a yenidən yazdılar.

### Python
- ~20 il üçün əsas dil.
- Uzun müddət Python 2; çox illik səylərlə Python 3-ə köçürüldü.
- Framework-lər: Pylons, sonra öz "r2"-ləri (open-source edildi, indi arxivdə).
- Reddit Python-un web üçün əsas qalmasına kömək etdi.

### Node.js / TypeScript
- 2018 redesign frontend-i server-side rendering üçün React + Node-dur.
- Köhnə UI (old.reddit.com) hələ də Python-dan xidmət olunur.

### Go, Rust
- Spesifik servislər üçün məhdud istifadə (feed ranking, şəkil emalı, moderation tooling).

## Framework seçimləri — Nə və niyə
- **Pylons** — erkən Python web framework, indi daha az populyardır, amma 2006-da məntiqli seçim idi.
- **r2** — Reddit-in Pylons-un daxili genişləndirilməsi. 2008-də open-source edildi; indi GitHub-da arxivdədir.
- 2018 redesign frontend-i üçün **React + Next.js**.

## Verilənlər bazası seçimləri — Nə və niyə

### PostgreSQL (sharded)
- Əsas relational store: istifadəçilər, subreddit-lər, link-lər, metadata.
- Subreddit / user id pattern-lərinə görə sharded.

### Cassandra
- Miqyasda vote-lar, şərh ağacları — böyük yazı həcmi.
- Cassandra-nın yazıya optimallaşdırılmış modeli "vote başına bir sətir" use case-ə uyğundur.

### Memcached
- Hər yerdə caching. Hər hot sorğu cache edilir.

### Arxitektura pattern-i: "cache həqiqətin mənbəyidir"
- Bəzi hot oxumalar üçün (ön səhifə listing-ləri kimi) cache effektiv olaraq authoritative-dir; DB cache fail edərsə yenidən hesablama üçündür.

## Proqram arxitekturası

```
   Clients (web, mobile, old.reddit)
          |
     [Edge / LB]
          |
     [Python app (r2) + Node.js frontend]
          |
     +----+----+----+------+-------+
     |    |    |    |      |       |
  Postgres Cassandra Memcached Search Queue (RabbitMQ/own)
```

### Feed / listing yaradılması
- Listing-lər (ön səhifə, subreddit hot səhifə) əvvəldən hesablanıb cache edilir.
- Ranking alqoritmi: "hot" (zamana görə azalan skor), "top", "new", "controversial", "rising".
- Şərh ağacları: Cassandra-da saxlanılır, hər thread üçün Python-da yığılır.

### Vote-lar
- Ağır yazı axını.
- Cassandra-da saxlanılır; skorlara aqreqasiya edilib Memcached-də cache olunur.

## İnfrastruktur və deploy
- AWS.
- Spesifik funksiyalar (chat, müasir feed, reklamlar, ML) üçün tədricən microservice-lər qəbul edildi.
- Əsas tətbiq hələ də mahiyyətcə monolitdir.

## Arxitekturanın təkamülü

| İl | Dəyişiklik |
|------|--------|
| 2005 | Bir neçə ay ərzində Common Lisp → Python yenidən yazılma |
| 2008 | r2 open-source edildi |
| 2010–2013 | Postgres-in miqyaslanması; Cassandra təqdim edildi |
| 2014–2016 | Açıq outage post-mortem-ləri; ops yetkinliyi |
| 2018 | React + Node-da yeni frontend (old.reddit hələ də Python) |
| 2020+ | Python 2 → Python 3 köçürməsi; daha çox servis |
| 2023 | API qiymətləndirmə mübahisəsi |
| 2024 | IPO |

## Əsas texniki qərarlar

1. **2005-də Common Lisp → Python yenidən yazılma.** Şirkət həyatında erkən yaxşı "sevdiyiniz şeyləri öldürün" anı.
2. **20 il Python-da qal.** Bir neçə Reddit-dən böyük şirkətin dil moda dövrələri (Ruby, Node, Go, Rust) ərzində Reddit Python-da yayımlamağa davam etdi.
3. **Yarı-authoritative layer olaraq Memcached.** Oxuma/yazma nisbəti tərəfindən formalaşdırılmış aqressiv caching strategiyası.
4. **Old.reddit.com-u canlı saxla.** Praktik — istifadəçilər onu sevir. İki frontend işlətmək xərcdir, amma məhsul disiplini vacibdir.
5. **r2-ni open-source et.** Kod indi arxivdə olsa da, illərlə Python web community-ə kömək etdi.

## Müsahibədə necə istinad etmək

1. **Python + Postgres PHP + MySQL-in edə biləcəyini edə bilər.** Reddit və Wikipedia "cansıxıcı" stack-lərin nəhəng saytları işlətdiyini sübut edir. Laravel + MySQL çox böyük məhsullar üçün yaxşıdır.
2. **Əvvəlcə cache oxumaları.** Oxuma-ağır tətbiqlər üçün hər hot sorğu cache edilməlidir. Redis ilə Laravel-in cache layer-i yerli ekvivalentdir.
3. **Listing-ləri əvvəlcədən hesabla.** Hot səhifə sorğu başına hesablanmır — dövrü olaraq hesablanır, cache edilir və xidmət olunur. Laravel developer-lər: istifadəçi başına və ya seqment başına "home feed"-i əvvəldən hesablamaq üçün queue-lardan istifadə edin.
4. **Təbii sərhədə görə sharding.** Reddit subreddit/user-a görə shard edir. Laravel-də forum və ya icma məhsulunda eyni pattern tətbiq olunur.
5. **İstifadəçilərə "yeni"ni məcbur etməyin.** Reddit old.reddit.com-u saxladı. Laravel dünyasında, yenidən yazmaqla yanaşı legacy admin və ya legacy istifadəçi UI-sini saxlamaq OK-dir.
6. **Açıq post-mortem-lər etibar qurur.** Reddit-in erkən outage post-mortem-ləri developer etibarı qazandı. İstənilən şirkətin engineering blogu üçün eyni doğrudur.

## Əlavə oxu üçün
- Reddit Engineering: post-mortem-lər (müxtəlif outage-lər, 2012-dən etibarən)
- Reddit Engineering: *Scaling Reddit's Comment Tree*
- Reddit Engineering: *Why We Use Python*
- Talks: Steve Huffman Y Combinator-da Reddit-in arxitektura təkamülü haqqında
- Book chapter: *The Architecture of Open Source Applications* — Reddit-tipli pattern-ləri də daxil edir
- Arxivlənmiş r2 repository-si (GitHub)
