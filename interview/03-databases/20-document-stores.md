# Document Stores vs Relational (Senior ⭐⭐⭐)

## İcmal
Document store — JSON/BSON kimi semi-structured document-ləri saxlayan NoSQL database növüdür. MongoDB ən tanınanıdır. Bu mövzu interview-da "MongoDB nə vaxt, PostgreSQL nə vaxt?" sualına contextual cavab verə bilmənizi yoxlayır. "MongoDB daha sürətlidir" kimi sadələşdirilmiş cavablar qəbul edilmir.

## Niyə Vacibdir
Document store-un yanlış istifadəsi transaction integrity itirməsinə, schema chaos-a, query complexity artmasına gətirib çıxarır. İnterviewer bu sualla sizin document modelinin trade-off-larını bildiyinizi, nə vaxt embedding, nə vaxt referencing etdiyinizi, MongoDB-nin transaction limitlərini başa düşdüyünüzü yoxlayır.

## Əsas Anlayışlar

- **Document:** JSON/BSON formatında özündə bütün əlaqəli datanı saxlayan structure. Schema-free — hər document fərqli field-lər saxlaya bilər
- **Collection:** Tabela analogu — lakin fixed schema yoxdur. Eyni collection-da fərqli shaped document-lər ola bilər
- **Embedding (Denormalization):** Əlaqəli datanı bir document içinə yerləşdirmək. JOIN yoxdur, bir oxumada hamısı gəlir. Dezavantaj: data duplication, update anomalies
- **Referencing (Normalization):** Başqa collection-a `ObjectId` reference saxlamaq. SQL `FK` analogu. Dezavantaj: application-level "JOIN" (`$lookup`) lazımdır
- **Embedding qaydası:** 1-to-few (az sayda) → embed; 1-to-many (çox ola bilər) → reference; many-to-many → reference
- **Schema Flexibility:** Sahə əlavə etmək migration tələb etmir — yeni field-i birbaşa yazırsan. Dezavantaj: schema "drift" — fərqli document-lər fərqli field-lər saxlayır, application-da null-check lazımdır
- **16MB Document Limit:** MongoDB-də tək document 16MB-dan böyük ola bilməz. Unbounded array embed edilsə — comments, log entries — bu limitə çatmaq mümkündür
- **Aggregation Pipeline:** MongoDB-nin query framework-ü — `$match`, `$group`, `$lookup`, `$project`, `$sort`, `$facet` stage-ləri
- **$lookup:** Collection-lar arası "LEFT OUTER JOIN" analogu. Performanslı deyil — iki collection ayrı `join stage`-ə çevrilir, memory-intensive
- **Multi-Document Transactions:** MongoDB 4.0+ ACID multi-document transactions. Lakin overhead var — single-document atomicity-si yetərsə istifadə etmə
- **Single-Document Atomicity:** Bir document-ə update həmişə atomicdir, transaction lazım deyil
- **ObjectId:** MongoDB-nin default 12-byte BSON ID — 4 byte timestamp + 5 byte random + 3 byte counter. Time-sortable
- **Atlas Search:** MongoDB Atlas-ın full-text search engine-i (Apache Lucene əsaslı). Elasticsearch alternatividir
- **Change Streams:** MongoDB-nin CDC analogu — collection-dakı dəyişiklikləri real-time dinləmək. `$watch`
- **Capped Collection:** Fixed-size, FIFO circular buffer — ən köhnə document avtomatik silinir. Log-like data üçün
- **Wildcard Index:** Bilinməyən field-ləri index-ləmək — schema-free data üçün
- **PostgreSQL JSONB:** MongoDB-nin alternatividir. SQL + JSON flexibility. Full-text search, GIN index ilə JSON query. "JSONB kafi isə MongoDB lazım deyil"

## Praktik Baxış

**Interview-da yanaşma:**
- Embedding vs referencing qərarı: access pattern əsasında seçim — "data birlikdə oxunurmu, ayrı da oxunurmu?"
- "MongoDB seçirəm çünki flexibledir" → antipattern, konkret use-case lazımdır
- Multi-document transaction overhead: "Mümkün qədər single-document atomicity istifadə et"

**Follow-up suallar:**
- "N+1 MongoDB-də necə həll olunur?" — Eager loading, aggregation pipeline, denormalization
- "$lookup niyə performanslı deyil?" — İki collection-u memory-də birləşdirir, B-Tree index-i tam istifadə etmir
- "Schema flexibility real dezavantajı nədir?" — Schema drift: fərqli document-lərdə fərqli field-lər → application-da `null` handling artır
- "MongoDB vs PostgreSQL JSONB nə vaxt?" — Həqiqətən dynamic schema + read-heavy + search lazımdırsa MongoDB; əks halda JSONB kifayət edir
- "Document 16MB limit-ə çatarsa?" — Nested array unbounded olmamalı; ayrı collection + reference

**Ümumi səhvlər:**
- "MongoDB JOIN yoxdur, problem deyil" — `$lookup` var, amma baha; application-level join da lazım ola bilər
- Schema flexibility-ni həmişə üstünlük kimi göstərmək — schema drift real problemdir
- Embedding-i həddindən çox istifadə: comments, audit logs, events → document şişər, 16MB limit
- Multi-document transaction-ı hər yerdə istifadə etmək — single-document atomicity kifayət edirsə istifadə etmə

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- "Data-nı access pattern-ə görə model edirsiniz, entity-yə görə deyil" demək
- Multi-document transaction overhead-ini bilmək
- "Bu xüsus case-da PostgreSQL JSONB daha yaxşı işlər" demək — MongoDB-ni universal həll kimi göstərməmək
- 16MB limit + unbounded array problem-ini bilmək

## Nümunələr

### Tipik Interview Sualı
"Bloq platforması üçün posts, comments, tags, authors MongoDB-də necə modellərdiniz? Embedding vs referencing qərarınızı əsaslandırın."

### Güclü Cavab
Qərar vermədən əvvəl access pattern-ləri soruşuram:

- Post sayfası açılanda həmişə author da göstərilirmi? → Bəli
- Author-u ayrıca da edit edirsinizmi? → Bəli
- Post-da neçə comment ola bilər? → Sınırsız

**Author:** Ayrı `authors` collection-da. Post-da yalnız `author_id` (reference) saxla. Çünki author öz başına edit edilir. Post document-ə sadəcə display üçün `author_summary: {id, name, avatar}` embed edə bilərik (denormalized cache) — author adı dəyişsə update lazımdır.

**Tags:** Post document-ə embed et (`["mongodb", "database"]`). Sayı az, dəyişmir, həmişə post ilə birlikdə oxunur.

**Comments:** Ayrı `comments` collection-da, `post_id` reference ilə. Sınırsız comment ola bilər → embed etsəydik document 16MB limit-ə çatardı. Pagination lazımdır.

**Bu klassik qayda:** 1-to-few → embed; 1-to-many (sınırsız) → reference.

### Kod Nümunəsi
```javascript
// MongoDB document dizaynı

// AUTHORS collection
db.authors.insertOne({
    _id:       ObjectId("507f1f77bcf86cd799439011"),
    name:      "Əli Həsənov",
    email:     "ali@example.com",
    bio:       "Senior Backend Developer",
    avatar_url: "https://cdn.example.com/avatars/ali.jpg",
    created_at: ISODate("2023-01-15")
});

// POSTS collection — hibrid yanaşma
db.posts.insertOne({
    _id:   ObjectId("507f1f77bcf86cd799439012"),
    title: "MongoDB Guide 2025",
    slug:  "mongodb-guide-2025",

    // Author: reference + denormalized summary
    author_id: ObjectId("507f1f77bcf86cd799439011"),  // Reference
    author_summary: {  // Denormalized — display üçün, JOIN lazım deyil
        name:       "Əli Həsənov",
        avatar_url: "https://cdn.example.com/avatars/ali.jpg"
        // BIO burada yoxdur — lazım olmur
    },

    // Tags: embed — az, dəyişmir
    tags: ["mongodb", "database", "nosql", "backend"],

    content:    "...",
    excerpt:    "MongoDB-nin əsas konseptlərini öyrənin.",
    published:  true,
    stats: {
        views:       1250,
        likes:       45,
        shares:      12,
    },
    // comments buraya embed ETMİRİK — sınırsız ola bilər!
    comment_count: 0,  // Denormalized counter
    created_at: ISODate("2025-01-15"),
    updated_at: ISODate("2025-01-15")
});

// COMMENTS collection — ayrı, pagination-lı
db.comments.insertOne({
    _id:       ObjectId("507f1f77bcf86cd799439013"),
    post_id:   ObjectId("507f1f77bcf86cd799439012"),  // Reference
    user_id:   42,
    text:      "Çox faydalı oldu, təşəkkür!",
    likes:     5,
    created_at: ISODate("2025-01-16")
});
```

```javascript
// Aggregation Pipeline nümunələri

// Post + comment count + author tam məlumatı
db.posts.aggregate([
    // 1. Filter: tag-ə görə
    { $match: {
        tags:      "mongodb",
        published: true,
        created_at: { $gte: ISODate("2025-01-01") }
    }},

    // 2. Author-un tam məlumatını qat ($lookup — performanslı deyil amma lazımdır)
    { $lookup: {
        from:         "authors",
        localField:   "author_id",
        foreignField: "_id",
        as:           "author_detail",
        pipeline: [   // Alt-sorğu ilə yalnız lazımi field-lər
            { $project: { name: 1, bio: 1, avatar_url: 1 } }
        ]
    }},
    { $unwind: "$author_detail" },

    // 3. Son yazılar əvvəl
    { $sort: { created_at: -1 } },

    // 4. Pagination
    { $skip: 0 },
    { $limit: 10 },

    // 5. Qaytarılacaq field-lər
    { $project: {
        title:            1,
        slug:             1,
        excerpt:          1,
        tags:             1,
        stats:            1,
        comment_count:    1,
        "author_detail":  1,
        created_at:       1
    }}
]);

// Comment-ləri pagination ilə gətir
db.comments.aggregate([
    { $match: { post_id: ObjectId("507f1f77bcf86cd799439012") } },
    { $sort:  { created_at: -1 } },
    { $skip:  0 },
    { $limit: 20 },
    { $lookup: {
        from:         "users",
        localField:   "user_id",
        foreignField: "_id",
        as:           "user",
        pipeline: [
            { $project: { name: 1, avatar_url: 1 } }
        ]
    }},
    { $unwind: "$user" }
]);
```

```php
// Laravel MongoDB (mongodb/laravel-mongodb package)
// composer require mongodb/laravel-mongodb

use MongoDB\Laravel\Eloquent\Model;

class Post extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'posts';

    protected $casts = [
        'published'  => 'boolean',
        'tags'       => 'array',
        'stats'      => 'array',
        'created_at' => 'datetime',
    ];

    // Author summary accessor
    public function getAuthorNameAttribute(): string
    {
        return $this->author_summary['name'] ?? 'Unknown';
    }

    // Comments — reference relationship
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    // Author full data — reference
    public function author()
    {
        return $this->belongsTo(Author::class, 'author_id');
    }
}

// N+1 problem MongoDB-də də mövcuddur
// YANLIŞ:
$posts = Post::where('published', true)->limit(20)->get();
foreach ($posts as $post) {
    // Hər post üçün ayrı query!
    echo $post->comments()->count();
}

// DÜZGÜN: Eager loading (where mümkündür)
$posts = Post::with('comments')->where('published', true)->limit(20)->get();

// Yaxud aggregation pipeline ilə (daha effektiv)
$posts = Post::raw(fn($collection) => $collection->aggregate([
    ['$match' => ['published' => true]],
    ['$lookup' => [
        'from'         => 'comments',
        'localField'   => '_id',
        'foreignField' => 'post_id',
        'as'           => 'recent_comments',
        'pipeline'     => [
            ['$sort'  => ['created_at' => -1]],
            ['$limit' => 3],
        ],
    ]],
    ['$addFields' => [
        'comment_count' => ['$size' => '$recent_comments'],
    ]],
    ['$sort'  => ['created_at' => -1]],
    ['$limit' => 20],
]));
```

```php
// Schema Migration MongoDB-də
// MongoDB-də column yoxdur, amma schema migration lazımdır

// Ssenario: Bütün post-lara 'reading_time' field əlavə et
class AddReadingTimeToPostsMigration
{
    public function up(): void
    {
        Post::where('reading_time', 'exists', false)
            ->chunkById(500, function ($posts) {
                foreach ($posts as $post) {
                    $wordCount   = str_word_count($post->content ?? '');
                    $readingTime = max(1, (int) ceil($wordCount / 200));

                    $post->update(['reading_time' => $readingTime]);
                }
                usleep(100_000); // 100ms throttle
            });
    }
}

// Schema validation (MongoDB 3.6+)
// Məcburi field-lər, type check
db.createCollection("posts", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["title", "author_id", "published"],
            properties: {
                title:     { bsonType: "string", minLength: 3 },
                published: { bsonType: "bool" },
                tags:      {
                    bsonType:  "array",
                    items:     { bsonType: "string" },
                    maxItems:  20
                }
            }
        }
    },
    validationLevel:  "moderate",
    validationAction: "error"
});
```

```javascript
// Change Streams — CDC
// MongoDB-nin real-time event stream

const changeStream = db.posts.watch([
    // Yalnız insert və update-ləri izlə
    { $match: {
        operationType: { $in: ['insert', 'update', 'delete'] }
    }}
], {
    fullDocument: 'updateLookup'  // Update-lərdə tam document al
});

changeStream.on('change', async (change) => {
    const { operationType, fullDocument, documentKey } = change;

    switch (operationType) {
        case 'insert':
            // Elasticsearch-ə index et
            await elasticClient.index({
                index:  'posts',
                id:     documentKey._id.toString(),
                document: {
                    title:   fullDocument.title,
                    content: fullDocument.content,
                    tags:    fullDocument.tags,
                }
            });
            break;

        case 'update':
            // Cache invalidate et
            await redis.del(`post:${documentKey._id}`);
            break;

        case 'delete':
            await elasticClient.delete({
                index: 'posts',
                id:    documentKey._id.toString(),
            });
            break;
    }
});

// Multi-document transaction (MongoDB 4.0+)
const session = client.startSession();

try {
    await session.withTransaction(async () => {
        // Post yarat
        const post = await posts.insertOne(
            { title: "Yeni Post", author_id: userId },
            { session }
        );

        // Author-un post_count-unu artır
        await authors.updateOne(
            { _id: userId },
            { $inc: { post_count: 1 } },
            { session }
        );
        // Hər iki əməliyyat atomicdir — biri fail olarsa hər ikisi rollback
    });
} finally {
    await session.endSession();
}
```

```php
// PostgreSQL JSONB vs MongoDB müqayisəsi
// PostgreSQL JSONB — SQL + flexibility

// Migration
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->decimal('price', 10, 2);
    $table->jsonb('attributes')->nullable(); // Dynamic schema
    $table->timestamps();
});

// GIN index JSONB üçün
DB::statement('CREATE INDEX idx_products_attributes ON products USING GIN (attributes)');

// Yazma
Product::create([
    'name'       => 'Laptop',
    'price'      => 999.99,
    'attributes' => [
        'brand'  => 'Dell',
        'cpu'    => 'Intel i7',
        'ram'    => '16GB',
        'color'  => 'silver',
    ],
]);

// Sorğu — JSONB operator-ları
$dellProducts = Product::whereRaw(
    "attributes->>'brand' = ?", ['Dell']
)->get();

// JSON containment operator
$products = Product::whereRaw(
    "attributes @> ?::jsonb",
    [json_encode(['brand' => 'Dell', 'color' => 'silver'])]
)->get();

// Qərar: PostgreSQL JSONB yetərsə MongoDB lazım deyil
// PostgreSQL JSONB seç əgər:
//   - Mövcud PostgreSQL stack-ın var
//   - ACID transactions kritikdirsə
//   - JSON + relational JOIN-lar lazımdırsa
//   - Operational complexity artırmaq istəmirsənsə
// MongoDB seç əgər:
//   - Həqiqətən document-native workload varsa
//   - Çox yüksək write throughput (sharding lazımdır)
//   - Atlas Search, Change Streams lazımdırsa
//   - Team MongoDB expertise-inə sahibdirsə
```

### İkinci Nümunə — MongoDB Anti-Patterns

```javascript
// ANTİ-PATTERN 1: Unbounded array embed
// YANLIŞ — audit_log böyüyür, 16MB limit-ə çatır
db.users.updateOne(
    { _id: userId },
    { $push: { audit_log: {
        action:    'login',
        ip:        '1.2.3.4',
        timestamp: new Date()
    }}}
);
// Problem: 100K login = 100K element array → document 16MB-dan böyür

// DOĞRU — ayrı collection
db.audit_logs.insertOne({
    user_id:   userId,
    action:    'login',
    ip:        '1.2.3.4',
    timestamp: new Date()
});

// ANTİ-PATTERN 2: Hər sorğu üçün $lookup
// YANLIŞ — N+1 bənzəri
db.posts.aggregate([
    { $match: { published: true } },
    { $lookup: {
        from:         "users",
        localField:   "user_id",
        foreignField: "_id",
        as:           "user"
    }},
    { $lookup: {
        from:         "categories",
        localField:   "category_id",
        foreignField: "_id",
        as:           "category"
    }},
    { $lookup: {
        from:         "tags",
        localField:   "tag_ids",
        foreignField: "_id",
        as:           "tags"
    }},
]);
// 3 $lookup = 3 collection scan = yavaş

// DOĞRU — Lazım olan məlumatı embed et (display üçün)
// author_summary: {name, avatar} — $lookup lazım deyil
// category_name — embed et, ayrıca çəkmə
// tags: ["mongodb", "backend"] — string array embed et

// ANTİ-PATTERN 3: Schema-free = schema yoxdur
// YANLIŞ — "MongoDB flexible-dir, schema lazım deyil"
// Nəticə: 30 fərqli field variation, application-da 20 null-check
db.users.find().forEach(u => {
    // user.email ya da user.emailAddress ya da user.email_address?
    // user.name ya da user.fullName ya da user.first_name + user.last_name?
});

// DOĞRU — Schema validation (server-side) + application-level DTO
// Hər zaman explicit schema tətbiq et
```

## Praktik Tapşırıqlar

- Bloq platforması üçün schema dizayn edin: author, post, comment, tag — embedding/referencing qərarlarını əsaslandırın
- `$lookup` vs embed etmə: 100K post + comment-da `EXPLAIN` ilə query execution plan müqayisə edin
- Change Streams ilə MongoDB → Elasticsearch sync yazın: post insert olunanda Elasticsearch-ə index et
- Schema migration: MongoDB-də `reading_time` field-ini bütün post-lara batch ilə əlavə edin
- PostgreSQL JSONB vs MongoDB benchmark: structured data üçün insert + query sürətini ölçün
- Multi-document transaction implement edin, partial failure simulate edin, rollback-in düzgün işlədiyini verify edin

## Əlaqəli Mövzular
- `01-sql-vs-nosql.md` — Nə vaxt SQL, nə vaxt document store seçmək
- `03-normalization-denormalization.md` — Embedding = denormalization — eyni trade-off-lar
- `17-polyglot-persistence.md` — Document store polyglot stack-ında nə vaxt yer alır
- `19-graph-databases.md` — Digər NoSQL növü ilə müqayisə
