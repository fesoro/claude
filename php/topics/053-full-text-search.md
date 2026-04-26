# Full Text Search və Search Engine Fərqləri (Middle)

## Mündəricat
1. [Full Text Search Nədir](#full-text-search-nədir)
2. [Inverted Index](#inverted-index-necə-işləyir)
3. [Tokenization, Stemming, Lemmatization, Stop Words](#tokenization-stemming-lemmatization-stop-words)
4. [TF-IDF Scoring](#tf-idf-scoring)
5. [BM25 Algorithm](#bm25-algorithm)
6. [MySQL Full Text Search](#mysql-full-text-search)
7. [PostgreSQL Full Text Search](#postgresql-full-text-search)
8. [Elasticsearch](#elasticsearch)
9. [Meilisearch](#meilisearch)
10. [Algolia](#algolia)
11. [Typesense](#typesense)
12. [Search Engine Müqayisəsi](#search-engine-müqayisəsi)
13. [Laravel Scout](#laravel-scout)
14. [Fuzzy Search, Autocomplete, Synonyms, Faceted Search](#fuzzy-search-autocomplete-synonyms-faceted-search)
15. [Indexing Strategiyaları](#indexing-strategiyaları)
16. [Real-world: E-commerce Product Search](#real-world-e-commerce-product-search)
17. [Performance Optimization](#performance-optimization)
18. [İntervyu Sualları](#intervyu-sualları-və-cavabları)

---

## Full Text Search Nədir

Full Text Search (FTS) strukturlaşdırılmamış mətn data-sı üzərində axtarış aparmaq üçün istifadə olunan texnologiyadır. SQL-in `LIKE '%söz%'` əməliyyatından fərqli olaraq:

```
LIKE '%laptop%':
  - Full table scan edir (index istifadə edə bilmir)
  - Yalnız tam uyğunluq tapır (partial match yox)
  - Relevance scoring yoxdur
  - Stemming yoxdur ("running" axtaranda "run" tapmır)
  - Böyük data-da çox yavaşdır

Full Text Search:
  - Inverted index istifadə edir (çox sürətli)
  - Relevance scoring (nəticələr uyğunluğa görə sıralanır)
  - Stemming/Lemmatization (run, running, runs → eyni kök)
  - Stop words (the, a, an, is... kimi mənasız sözlər filtr olunur)
  - Fuzzy matching (typo tolerans)
  - Phrase search, boolean operators
  - Synonym dəstəyi
```

---

## Inverted Index Necə İşləyir

Inverted Index FTS-in əsas data strukturudur. Kitabın arxasındakı index kimi işləyir.

### Normal Index vs Inverted Index

```
Normal (Forward) Index:
  Document 1 → ["laravel", "php", "framework", "web"]
  Document 2 → ["react", "javascript", "web", "frontend"]
  Document 3 → ["laravel", "api", "rest", "php"]

Inverted Index:
  "laravel"    → [Doc 1, Doc 3]
  "php"        → [Doc 1, Doc 3]
  "framework"  → [Doc 1]
  "web"        → [Doc 1, Doc 2]
  "react"      → [Doc 2]
  "javascript" → [Doc 2]
  "frontend"   → [Doc 2]
  "api"        → [Doc 3]
  "rest"       → [Doc 3]
```

Axtarış: "laravel php" → "laravel" postings ∩ "php" postings → [Doc 1, Doc 3]

### İndeksləmə Prosesi

```
Orijinal Mətn: "Laravel is an amazing PHP framework for web development!"

1. Tokenization:    ["Laravel", "is", "an", "amazing", "PHP", "framework", "for", "web", "development"]
2. Lowercasing:     ["laravel", "is", "an", "amazing", "php", "framework", "for", "web", "development"]
3. Stop word removal: ["laravel", "amazing", "php", "framework", "web", "development"]
4. Stemming:        ["laravel", "amaz", "php", "framework", "web", "develop"]

Bu token-lər inverted index-ə əlavə olunur:
  "laravel"   → [Doc_ID: pozisiya]
  "amaz"      → [Doc_ID: pozisiya]
  "php"       → [Doc_ID: pozisiya]
  ...
```

---

## Tokenization, Stemming, Lemmatization, Stop Words

### Tokenization

Mətni token-lərə (söz vahidlərinə) bölmək prosesidir.

```
Sadə tokenization (whitespace split):
  "Hello, World! This is a test." → ["Hello,", "World!", "This", "is", "a", "test."]

Standart tokenizer (punctuation remove):
  "Hello, World! This is a test." → ["Hello", "World", "This", "is", "a", "test"]

Email/URL tokenizer:
  "Contact us at info@example.com" → ["Contact", "us", "at", "info@example.com"]

N-gram tokenizer (autocomplete üçün):
  "laptop" (bigram) → ["la", "ap", "pt", "to", "op"]
  "laptop" (trigram) → ["lap", "apt", "pto", "top"]

Edge n-gram (prefix autocomplete):
  "laptop" → ["l", "la", "lap", "lapt", "lapto", "laptop"]
```

### Stemming

Sözü kök formasına gətirmək (algoritmik, qeyri-dəqiq ola bilər):

```
Stemming (Porter Stemmer nümunəsi):
  "running"     → "run"
  "runs"        → "run"
  "runner"      → "runner"    (fərqli kök ola bilər)
  "connection"  → "connect"
  "connected"   → "connect"
  "connections" → "connect"
  "fishing"     → "fish"
  "fished"      → "fish"
  "university"  → "univers"   (tam dəqiq deyil - stemming-in problemi)
  "universe"    → "univers"   (eyni kök alınır - bəzən yanlış match)
```

### Lemmatization

Sözü lüğət formasına (lemma) gətirmək (NLP əsaslı, daha dəqiq):

```
Lemmatization:
  "better"  → "good"    (stemmer bunu tapa bilməz!)
  "went"    → "go"      (stemmer bunu tapa bilməz!)
  "running" → "run"
  "mice"    → "mouse"
  "are"     → "be"

Stemming daha sürətlidir, Lemmatization daha dəqiqdir.
Əksər search engine-lər stemming istifadə edir (performans üçün).
```

### Stop Words

Tez-tez işlədilən amma axtarış üçün mənasız olan sözlər:

```
English stop words: a, an, the, is, are, was, were, be, been, being, have, has, had, do, does, did, 
  will, would, shall, should, may, might, must, can, could, of, at, by, for, with, about, against, 
  between, through, during, before, after, above, below, to, from, up, down, in, out, on, off, over, 
  under, again, further, then, once, and, but, or, nor, not, so, very, just, that, this, it, its...

Azərbaycan dili: bir, bu, o, da, də, ki, və, ilə, üçün, olan, amma, lakin, çünki...
```

---

## TF-IDF Scoring

TF-IDF (Term Frequency - Inverse Document Frequency) sənədin axtarış sorğusuna nə qədər uyğun olduğunu ölçür.

### TF (Term Frequency)

Söz sənəddə nə qədər tez-tez keçir:

```
TF(t, d) = (t sözünün d sənədindəki sayı) / (d sənədindəki ümumi söz sayı)

Sənəd: "Laravel is a PHP framework. Laravel is great for web development."
TF("laravel", doc) = 2/11 ≈ 0.18
TF("php", doc) = 1/11 ≈ 0.09
TF("is", doc) = 2/11 ≈ 0.18  (amma stop word olduğu üçün adətən çıxarılır)
```

### IDF (Inverse Document Frequency)

Söz nə qədər nadir/unikaldır (bütün sənədlər üzərində):

```
IDF(t) = log(N / df(t))
  N = ümumi sənəd sayı
  df(t) = t sözünü ehtiva edən sənəd sayı

1000 sənəd var:
  "the"     → 990 sənəddə keçir → IDF = log(1000/990) ≈ 0.004 (çox aşağı - mənasız söz)
  "laravel" → 50 sənəddə keçir  → IDF = log(1000/50) ≈ 3.0 (yüksək - nadir, əhəmiyyətli)
  "quantum" → 2 sənəddə keçir   → IDF = log(1000/2) ≈ 6.2 (çox yüksək - çox nadir)
```

### TF-IDF Score

```
TF-IDF(t, d) = TF(t, d) × IDF(t)

"laravel" üçün:
  Document A (Laravel tutorial): TF = 0.18, IDF = 3.0 → Score = 0.54 (yüksək - uyğun)
  Document B (PHP overview):     TF = 0.02, IDF = 3.0 → Score = 0.06 (aşağı)

Final score = bütün axtarış sözlərinin TF-IDF cəmi
```

---

## BM25 Algorithm

BM25 (Best Matching 25) TF-IDF-in təkmilləşdirilmiş versiyasıdır. Elasticsearch, PostgreSQL və digər müasir search engine-lərdə default scoring algorithm-dir.

```
BM25(D, Q) = Σ IDF(qi) × [f(qi, D) × (k1 + 1)] / [f(qi, D) + k1 × (1 - b + b × |D|/avgdl)]

Parametrlər:
  f(qi, D) = qi sözünün D sənədindəki tezliyi (term frequency)
  |D|      = sənədin uzunluğu (söz sayı)
  avgdl    = orta sənəd uzunluğu
  k1       = term frequency saturation parametri (default: 1.2)
             k1 artdıqca term frequency daha çox təsir edir
  b        = sənəd uzunluğu normalizasiyası (default: 0.75)
             b=0: uzunluq nəzərə alınmır
             b=1: tam normalizasiya

TF-IDF-dən fərqləri:
1. Term Frequency Saturation: TF-IDF-də söz 10 dəfə keçirsə, score 10x artır.
   BM25-də artım azalır (diminishing returns) - 2 dəfə ilə 20 dəfə arasındakı fərq azdır.
   
2. Document Length Normalization: Qısa sənəddə "laravel" 5 dəfə keçirsə,
   10000 sözlük sənəddə 5 dəfə keçməsindən daha əhəmiyyətlidir.
```

---

## MySQL Full Text Search

### Əsas Setup

*Əsas Setup üçün kod nümunəsi:*
```sql
-- FULLTEXT index yaratma
CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    body TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FULLTEXT INDEX ft_articles (title, body)
) ENGINE=InnoDB;

-- Mövcud cədvələ əlavə
ALTER TABLE articles ADD FULLTEXT INDEX ft_title (title);
ALTER TABLE articles ADD FULLTEXT INDEX ft_title_body (title, body);

INSERT INTO articles (title, body) VALUES
('Laravel Framework Guide', 'Laravel is a PHP framework for web development. It provides elegant syntax.'),
('React Tutorial', 'React is a JavaScript library for building user interfaces. It is maintained by Meta.'),
('PHP Best Practices', 'PHP is a popular programming language for web development. Laravel uses PHP.'),
('Vue.js Introduction', 'Vue.js is a progressive JavaScript framework for building web applications.');
```

### Natural Language Mode

*Natural Language Mode üçün kod nümunəsi:*
```sql
-- Default mode: uyğunluğa görə sıralayır
SELECT *, MATCH(title, body) AGAINST('laravel php' IN NATURAL LANGUAGE MODE) AS relevance
FROM articles
WHERE MATCH(title, body) AGAINST('laravel php' IN NATURAL LANGUAGE MODE)
ORDER BY relevance DESC;

-- Qısa syntax (IN NATURAL LANGUAGE MODE default-dur)
SELECT * FROM articles
WHERE MATCH(title, body) AGAINST('laravel php');
```

### Boolean Mode

*Boolean Mode üçün kod nümunəsi:*
```sql
-- Boolean mode: operatorlarla dəqiq nəzarət
SELECT * FROM articles
WHERE MATCH(title, body) AGAINST('+laravel +php' IN BOOLEAN MODE);
-- + : mütləq olmalıdır
-- - : olmamalıdır
-- (operator yox): optional (scoring-ə təsir edir)

-- Operatorlar:
-- +söz   : mütləq lazımdır
-- -söz   : olmamalıdır
-- söz*   : prefix axtarışı (söz ilə başlayan)
-- "söz1 söz2" : dəqiq phrase
-- >söz   : score artır
-- <söz   : score azalır
-- ~söz   : negative contribution (lakin exclude etmir)
-- ()     : qruplaşdırma

-- Nümunələr
SELECT * FROM articles
WHERE MATCH(title, body) AGAINST('+laravel -react' IN BOOLEAN MODE);
-- laravel olmalı, react olmamalı

SELECT * FROM articles
WHERE MATCH(title, body) AGAINST('"web development"' IN BOOLEAN MODE);
-- Dəqiq phrase axtarışı

SELECT * FROM articles
WHERE MATCH(title, body) AGAINST('php*' IN BOOLEAN MODE);
-- php ilə başlayan bütün sözlər (php, phpunit, phpstorm...)

SELECT * FROM articles
WHERE MATCH(title, body) AGAINST('+laravel +(>framework <tutorial)' IN BOOLEAN MODE);
-- laravel + (framework daha yüksək priority, tutorial daha aşağı)
```

### Query Expansion Mode

*Query Expansion Mode üçün kod nümunəsi:*
```sql
-- Query expansion: əlaqəli sözləri avtomatik əlavə edir
-- İlk axtarışda tapılan nəticələrdəki sözləri ikinci axtarışa əlavə edir
SELECT * FROM articles
WHERE MATCH(title, body) AGAINST('database' WITH QUERY EXPANSION);
-- "database" axtarır, tapılan sənədlərdəki "sql", "mysql", "query" kimi sözləri
-- avtomatik əlavə edərək yenidən axtarır
```

### MySQL FTS Məhdudiyyətləri

```
1. Minimum söz uzunluğu: default 3 (ft_min_word_len) - InnoDB: 3, MyISAM: 4
2. Stop words siyahısı dəyişdirilə bilər amma məhduddur
3. Stemming yalnız built-in (custom analyzer yoxdur)
4. Yalnız InnoDB və MyISAM engine-lərində işləyir
5. CJK (Chinese, Japanese, Korean) dəstəyi məhduddur (n-gram parser lazım)
6. Relevance scoring basic-dir
7. Fuzzy search dəstəyi yoxdur
8. Synonym dəstəyi yoxdur (built-in)
9. Highlighting/snippets yoxdur
10. Faceted search yoxdur
```

*10. Faceted search yoxdur üçün kod nümunəsi:*
```sql
-- Minimum söz uzunluğunu dəyişmək (my.cnf):
-- [mysqld]
-- innodb_ft_min_token_size = 2
-- ft_min_word_len = 2

-- Stop words siyahısını görmək/dəyişmək:
SELECT * FROM information_schema.innodb_ft_default_stopword;

-- Custom stop word cədvəli
CREATE TABLE my_stopwords (value VARCHAR(30));
SET GLOBAL innodb_ft_server_stopword_table = 'mydb/my_stopwords';

-- CJK üçün n-gram parser
CREATE FULLTEXT INDEX ft_idx ON articles(title, body) WITH PARSER ngram;
```

---

## PostgreSQL Full Text Search

PostgreSQL-in FTS sistemi MySQL-dən çox daha güclü və çevikdir.

### Əsas Konseptlər: tsvector və tsquery

*Əsas Konseptlər: tsvector və tsquery üçün kod nümunəsi:*
```sql
-- tsvector: sənədi axtarışa hazır formata çevirir
SELECT to_tsvector('english', 'Laravel is an amazing PHP framework for web development');
-- Result: 'amaz':4 'develop':9 'framework':6 'laravel':1 'php':5 'web':8
-- Stop words silinib (is, an, for), stemming tətbiq olunub (amazing→amaz, development→develop)
-- Hər token-in pozisiyası saxlanılıb

-- tsquery: axtarış sorğusunu formatlayır
SELECT to_tsquery('english', 'laravel & php');
-- Result: 'laravel' & 'php'

-- Uyğunluq yoxlama: @@ operatoru
SELECT to_tsvector('english', 'Laravel is a PHP framework') @@ to_tsquery('english', 'laravel & php');
-- Result: true

-- Müxtəlif tsquery yaratma funksiyaları
SELECT plainto_tsquery('english', 'laravel php framework');
-- 'laravel' & 'php' & 'framework' (bütün sözlər AND ilə birləşir)

SELECT phraseto_tsquery('english', 'web development');
-- 'web' <-> 'develop' (ardıcıl olmalı - phrase search)

SELECT websearch_to_tsquery('english', '"web development" laravel -react');
-- 'web' <-> 'develop' & 'laravel' & !'react' (Google-a bənzər syntax)
```

### Axtarış Operatorları

*Axtarış Operatorları üçün kod nümunəsi:*
```sql
-- AND: &
SELECT * FROM articles WHERE to_tsvector('english', body) @@ to_tsquery('english', 'laravel & php');

-- OR: |
SELECT * FROM articles WHERE to_tsvector('english', body) @@ to_tsquery('english', 'laravel | react');

-- NOT: !
SELECT * FROM articles WHERE to_tsvector('english', body) @@ to_tsquery('english', 'php & !wordpress');

-- Phrase (ardıcıl sözlər): <->
SELECT * FROM articles WHERE to_tsvector('english', body) @@ to_tsquery('english', 'web <-> development');

-- Yaxınlıq: <N> (N söz aralığında)
SELECT * FROM articles WHERE to_tsvector('english', body) @@ to_tsquery('english', 'laravel <2> web');
-- laravel və web arasında 2-dən az söz olmalıdır

-- Prefix axtarış: :*
SELECT * FROM articles WHERE to_tsvector('english', body) @@ to_tsquery('english', 'develop:*');
-- develop ilə başlayan bütün sözlər (development, developer, developing...)
```

### Ranking və Scoring

*Ranking və Scoring üçün kod nümunəsi:*
```sql
-- ts_rank: TF-IDF əsaslı scoring
SELECT 
    title,
    ts_rank(
        to_tsvector('english', title || ' ' || body),
        plainto_tsquery('english', 'laravel php')
    ) AS rank
FROM articles
WHERE to_tsvector('english', title || ' ' || body) @@ plainto_tsquery('english', 'laravel php')
ORDER BY rank DESC;

-- ts_rank_cd: Cover Density ranking (proximity nəzərə alır)
SELECT 
    title,
    ts_rank_cd(
        to_tsvector('english', title || ' ' || body),
        plainto_tsquery('english', 'laravel php')
    ) AS rank
FROM articles
WHERE to_tsvector('english', title || ' ' || body) @@ plainto_tsquery('english', 'laravel php')
ORDER BY rank DESC;

-- Weight (ağırlıq) ilə ranking
-- A > B > C > D (A ən yüksək priority)
SELECT 
    title,
    ts_rank(
        setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
        setweight(to_tsvector('english', coalesce(body, '')), 'B'),
        plainto_tsquery('english', 'laravel')
    ) AS rank
FROM articles
ORDER BY rank DESC;
-- Title-dakı match body-dəkindən daha yüksək score alır
```

### GIN Index ilə Performans

*GIN Index ilə Performans üçün kod nümunəsi:*
```sql
-- Stored generated column + GIN index (ən yaxşı yanaşma)
ALTER TABLE articles ADD COLUMN search_vector tsvector
    GENERATED ALWAYS AS (
        setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
        setweight(to_tsvector('english', coalesce(body, '')), 'B')
    ) STORED;

CREATE INDEX idx_articles_search ON articles USING GIN (search_vector);

-- Artıq birbaşa search_vector sütununu istifadə edə bilərik
SELECT title, ts_rank(search_vector, websearch_to_tsquery('english', 'laravel php')) AS rank
FROM articles
WHERE search_vector @@ websearch_to_tsquery('english', 'laravel php')
ORDER BY rank DESC;
```

### Highlight (Snippet) Yaratma

*Highlight (Snippet) Yaratma üçün kod nümunəsi:*
```sql
-- ts_headline: axtarış nəticəsində matchlanmış hissəni göstərir
SELECT 
    title,
    ts_headline('english', body, plainto_tsquery('english', 'laravel php'),
        'StartSel=<mark>, StopSel=</mark>, MaxWords=35, MinWords=15, ShortWord=3'
    ) AS snippet
FROM articles
WHERE search_vector @@ plainto_tsquery('english', 'laravel php');

-- Nəticə:
-- "<mark>Laravel</mark> is a <mark>PHP</mark> framework for web development. It provides elegant syntax."
```

### Custom Dictionary və Configuration

*Custom Dictionary və Configuration üçün kod nümunəsi:*
```sql
-- Mövcud text search configuration-ları
SELECT cfgname FROM pg_ts_config;
-- simple, danish, dutch, english, finnish, french, german, hungarian, italian, 
-- norwegian, portuguese, romanian, russian, spanish, swedish, turkish

-- Custom configuration yaratma
CREATE TEXT SEARCH CONFIGURATION azerbaijani (COPY = simple);

-- Custom dictionary
CREATE TEXT SEARCH DICTIONARY azerbaijani_stem (
    TEMPLATE = simple,
    STOPWORDS = azerbaijani  -- $SHAREDIR/tsearch_data/azerbaijani.stop faylı lazımdır
);

ALTER TEXT SEARCH CONFIGURATION azerbaijani
    ALTER MAPPING FOR asciiword, asciihword, hword_asciipart, word, hword, hword_part
    WITH azerbaijani_stem;

-- Synonym dictionary
CREATE TEXT SEARCH DICTIONARY synonym_dict (
    TEMPLATE = synonym,
    SYNONYMS = my_synonyms  -- $SHAREDIR/tsearch_data/my_synonyms.syn
);
-- my_synonyms.syn fayl nümunəsi:
-- laptop notebook
-- phone mobile
-- car automobile
```

### Trigram Axtarış (Fuzzy Matching)

*Trigram Axtarış (Fuzzy Matching) üçün kod nümunəsi:*
```sql
-- pg_trgm extension: LIKE '%söz%' sürətləndirmək + fuzzy search
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Trigram: sözü 3 simvolluq parçalara bölmək
SELECT show_trgm('hello');
-- {"  h"," he","ell","hel","llo","lo "}

-- GIN trigram index (LIKE '%...%' və fuzzy search üçün)
CREATE INDEX idx_products_name_trgm ON products USING GIN (name gin_trgm_ops);

-- LIKE/ILIKE artıq index istifadə edə bilər!
SELECT * FROM products WHERE name ILIKE '%laptop%';

-- Similarity (fuzzy matching - typo tolerant)
SELECT name, similarity(name, 'laptp') AS sim  -- typo: laptp
FROM products
WHERE similarity(name, 'laptp') > 0.3
ORDER BY sim DESC;

-- Similarity operator
SELECT * FROM products WHERE name % 'laptp';  -- default threshold: 0.3
```

---

## Elasticsearch

### Nədir və Necə İşləyir

Elasticsearch Apache Lucene üzərində qurulmuş distributed, RESTful search və analytics engine-dir. JSON əsaslı sənəd saxlayır və real-time axtarış təmin edir.

```
Arxitektura:
  Cluster → Node(s) → Index(es) → Shard(s) → Segment(s) → Document(s)

  Cluster: bir və ya çox node-dan ibarət
  Node: bir Elasticsearch process-i
  Index: database-dəki cədvələ bənzəyir
  Shard: index-in horizontal bölünməsi (paralel axtarış)
    - Primary shard: orijinal data
    - Replica shard: primary-nin kopyası (high availability)
  Segment: Lucene-in immutable data strukturu
  Document: JSON formatında bir record
```

### Index, Document, Mapping

*Index, Document, Mapping üçün kod nümunəsi:*
```bash
# Index yaratma (cURL ilə)
curl -X PUT "localhost:9200/products" -H 'Content-Type: application/json' -d'
{
  "settings": {
    "number_of_shards": 3,
    "number_of_replicas": 1,
    "analysis": {
      "analyzer": {
        "product_analyzer": {
          "type": "custom",
          "tokenizer": "standard",
          "filter": ["lowercase", "stemmer", "stop"]
        },
        "autocomplete_analyzer": {
          "type": "custom",
          "tokenizer": "edge_ngram_tokenizer",
          "filter": ["lowercase"]
        }
      },
      "tokenizer": {
        "edge_ngram_tokenizer": {
          "type": "edge_ngram",
          "min_gram": 2,
          "max_gram": 20,
          "token_chars": ["letter", "digit"]
        }
      }
    }
  },
  "mappings": {
    "properties": {
      "name": {
        "type": "text",
        "analyzer": "product_analyzer",
        "fields": {
          "keyword": { "type": "keyword" },
          "autocomplete": {
            "type": "text",
            "analyzer": "autocomplete_analyzer",
            "search_analyzer": "standard"
          }
        }
      },
      "description": { "type": "text", "analyzer": "product_analyzer" },
      "price": { "type": "float" },
      "category": { "type": "keyword" },
      "tags": { "type": "keyword" },
      "brand": { "type": "keyword" },
      "in_stock": { "type": "boolean" },
      "rating": { "type": "float" },
      "created_at": { "type": "date" },
      "attributes": {
        "type": "nested",
        "properties": {
          "name": { "type": "keyword" },
          "value": { "type": "keyword" }
        }
      }
    }
  }
}'

# Document əlavə etmə
curl -X POST "localhost:9200/products/_doc/1" -H 'Content-Type: application/json' -d'
{
  "name": "Dell XPS 15 Laptop",
  "description": "High performance laptop for developers and creative professionals",
  "price": 1299.99,
  "category": "electronics",
  "tags": ["laptop", "dell", "developer"],
  "brand": "Dell",
  "in_stock": true,
  "rating": 4.5,
  "created_at": "2024-01-15",
  "attributes": [
    {"name": "RAM", "value": "16GB"},
    {"name": "Storage", "value": "512GB SSD"}
  ]
}'

# Bulk indexing (çox sənəd bir dəfəyə)
curl -X POST "localhost:9200/products/_bulk" -H 'Content-Type: application/x-ndjson' -d'
{"index": {"_id": "2"}}
{"name": "MacBook Pro 14", "price": 1999.99, "category": "electronics", "brand": "Apple"}
{"index": {"_id": "3"}}
{"name": "Logitech MX Mouse", "price": 99.99, "category": "accessories", "brand": "Logitech"}
'
```

### Query DSL

*Query DSL üçün kod nümunəsi:*
```bash
# 1. Match Query (full-text search - analyzed, fuzzy)
curl -X GET "localhost:9200/products/_search" -H 'Content-Type: application/json' -d'
{
  "query": {
    "match": {
      "name": {
        "query": "dell laptop",
        "operator": "and",
        "fuzziness": "AUTO"
      }
    }
  }
}'

# 2. Bool Query (mürəkkəb şərt birləşmələri)
curl -X GET "localhost:9200/products/_search" -H 'Content-Type: application/json' -d'
{
  "query": {
    "bool": {
      "must": [
        { "match": { "name": "laptop" } }
      ],
      "filter": [
        { "term": { "brand": "Dell" } },
        { "range": { "price": { "gte": 500, "lte": 2000 } } },
        { "term": { "in_stock": true } }
      ],
      "should": [
        { "match": { "description": "developer" } },
        { "range": { "rating": { "gte": 4.0 } } }
      ],
      "must_not": [
        { "term": { "category": "refurbished" } }
      ],
      "minimum_should_match": 1
    }
  },
  "sort": [
    { "_score": "desc" },
    { "price": "asc" }
  ],
  "from": 0,
  "size": 20,
  "highlight": {
    "fields": {
      "name": {},
      "description": { "fragment_size": 150, "number_of_fragments": 3 }
    },
    "pre_tags": ["<mark>"],
    "post_tags": ["</mark>"]
  }
}'

# 3. Term Query (exact match - analyzed deyil)
curl -X GET "localhost:9200/products/_search" -H 'Content-Type: application/json' -d'
{
  "query": {
    "term": { "category": "electronics" }
  }
}'

# 4. Range Query
curl -X GET "localhost:9200/products/_search" -H 'Content-Type: application/json' -d'
{
  "query": {
    "range": {
      "price": { "gte": 100, "lte": 500 }
    }
  }
}'

# 5. Nested Query (nested objects üçün)
curl -X GET "localhost:9200/products/_search" -H 'Content-Type: application/json' -d'
{
  "query": {
    "nested": {
      "path": "attributes",
      "query": {
        "bool": {
          "must": [
            { "term": { "attributes.name": "RAM" } },
            { "term": { "attributes.value": "16GB" } }
          ]
        }
      }
    }
  }
}'

# 6. Multi-match (birdən çox field-da axtarış)
curl -X GET "localhost:9200/products/_search" -H 'Content-Type: application/json' -d'
{
  "query": {
    "multi_match": {
      "query": "developer laptop",
      "fields": ["name^3", "description", "tags^2"],
      "type": "best_fields",
      "fuzziness": "AUTO"
    }
  }
}'
```

### Aggregations

*Aggregations üçün kod nümunəsi:*
```bash
# Faceted search üçün aggregation-lar
curl -X GET "localhost:9200/products/_search" -H 'Content-Type: application/json' -d'
{
  "size": 0,
  "aggs": {
    "categories": {
      "terms": { "field": "category", "size": 20 }
    },
    "brands": {
      "terms": { "field": "brand", "size": 20 }
    },
    "price_ranges": {
      "range": {
        "field": "price",
        "ranges": [
          { "to": 100 },
          { "from": 100, "to": 500 },
          { "from": 500, "to": 1000 },
          { "from": 1000 }
        ]
      }
    },
    "avg_price": {
      "avg": { "field": "price" }
    },
    "price_stats": {
      "stats": { "field": "price" }
    },
    "avg_rating_per_category": {
      "terms": { "field": "category" },
      "aggs": {
        "avg_rating": { "avg": { "field": "rating" } }
      }
    }
  }
}'
```

### Laravel Scout + Elasticsearch

*Laravel Scout + Elasticsearch üçün kod nümunəsi:*
```php
// composer require laravel/scout
// composer require matchish/laravel-scout-elasticsearch (populyar paket)

// config/scout.php
return [
    'driver' => env('SCOUT_DRIVER', 'elasticsearch'),
    
    'elasticsearch' => [
        'hosts' => [env('ELASTICSEARCH_HOST', 'localhost:9200')],
        'index' => env('ELASTICSEARCH_INDEX', 'products'),
    ],
];

// Product Model
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;
    
    // İndekslənəcək data
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'category' => $this->category?->name,
            'brand' => $this->brand?->name,
            'tags' => $this->tags->pluck('name')->toArray(),
            'in_stock' => $this->stock_quantity > 0,
            'rating' => (float) $this->average_rating,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
    
    // Search index adı
    public function searchableAs(): string
    {
        return 'products';
    }
    
    // Hansı model-lər indekslənsin
    public function shouldBeSearchable(): bool
    {
        return $this->is_active && !$this->trashed();
    }
}

// Axtarış
$results = Product::search('dell laptop')
    ->where('in_stock', true)
    ->where('category', 'electronics')
    ->orderBy('price', 'asc')
    ->paginate(20);

// Custom Elasticsearch query (matchish/laravel-scout-elasticsearch)
use Matchish\ScoutElasticSearch\MixedSearch;

$results = Product::search('laptop')
    ->rule(function ($builder) {
        return [
            'must' => [
                ['multi_match' => [
                    'query' => $builder->query,
                    'fields' => ['name^3', 'description', 'tags^2'],
                    'fuzziness' => 'AUTO',
                ]],
            ],
            'filter' => [
                ['term' => ['in_stock' => true]],
                ['range' => ['price' => ['gte' => 100, 'lte' => 2000]]],
            ],
        ];
    })
    ->get();

// İndeksləmə əmrləri
// php artisan scout:import "App\Models\Product"     // Bütün məhsulları indekslə
// php artisan scout:flush "App\Models\Product"      // İndeksi təmizlə

// Batch import (daha sürətli)
Product::query()
    ->with(['category', 'brand', 'tags'])
    ->searchable();

// Manual re-index
$product = Product::find(1);
$product->searchable(); // Bu product-u yenidən indekslə

// Queue ilə indeksləmə (config/scout.php: 'queue' => true)
// Model yaradıldıqda/yeniləndikdə/silindikdə avtomatik queue-ya gedir
```

---

## Meilisearch

### Nədir və Elasticsearch-dən Fərqi

Meilisearch Rust dilində yazılmış, typo-tolerant, sürətli və istifadəsi asan search engine-dir. "Instant search" üçün optimallaşdırılıb.

```
Meilisearch vs Elasticsearch:
┌──────────────────┬───────────────────────┬───────────────────────┐
│ Xüsusiyyət       │ Meilisearch           │ Elasticsearch         │
├──────────────────┼───────────────────────┼───────────────────────┤
│ Yazılıb          │ Rust                  │ Java                  │
│ Dil              │ Typo-tolerant default │ Fuzzy manual config   │
│ Setup            │ Çox asan (1 binary)   │ Mürəkkəb (JVM, conf) │
│ RAM istifadəsi   │ Az                    │ Çox (JVM heap)        │
│ Sənəd limiti     │ ~10M (optimal)        │ Milyard+              │
│ Query DSL        │ Sadə filter           │ Çox güclü DSL         │
│ Aggregations     │ Facets (basic)        │ Çox güclü             │
│ Distributed      │ Xeyr (tək node)       │ Bəli (cluster)        │
│ Real-time index  │ ~ms                   │ ~1 saniyə (refresh)   │
│ Autocomplete     │ Built-in              │ Manual konfiqurasiya  │
│ Analytics        │ Xeyr                  │ Kibana ilə güclü      │
│ İstifadə halı    │ Frontend search, SaaS │ Enterprise, log, big  │
└──────────────────┴───────────────────────┴───────────────────────┘
```

### Laravel Scout + Meilisearch

*Laravel Scout + Meilisearch üçün kod nümunəsi:*
```php
// composer require laravel/scout
// composer require meilisearch/meilisearch-php http-interop/http-factory-guzzle

// .env
// SCOUT_DRIVER=meilisearch
// MEILISEARCH_HOST=http://127.0.0.1:7700
// MEILISEARCH_KEY=masterKey

// config/scout.php
return [
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),
    
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
    ],
];

// Product Model (eyni Searchable trait)
class Product extends Model
{
    use Searchable;
    
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'category' => $this->category?->name,
            'brand' => $this->brand?->name,
            'tags' => $this->tags->pluck('name')->toArray(),
            'in_stock' => $this->stock_quantity > 0,
            'rating' => (float) $this->average_rating,
            'created_at_timestamp' => $this->created_at?->timestamp,
        ];
    }
}

// Meilisearch index konfiqurasiyası
use Meilisearch\Client;

class ConfigureMeilisearch extends Command
{
    protected $signature = 'meilisearch:configure';
    
    public function handle(): void
    {
        $client = app(Client::class);
        $index = $client->index('products');
        
        // Axtarışda istifadə olunacaq sahələr
        $index->updateSearchableAttributes([
            'name',        // 1-ci priority
            'description', // 2-ci priority
            'tags',        // 3-cü priority
            'brand',       // 4-cü priority
            'category',    // 5-ci priority
        ]);
        
        // Filter edilə bilən sahələr
        $index->updateFilterableAttributes([
            'category', 'brand', 'price', 'in_stock', 'rating', 'tags',
        ]);
        
        // Sıralama sahələri
        $index->updateSortableAttributes([
            'price', 'rating', 'created_at_timestamp',
        ]);
        
        // Facet sahələri
        $index->updateFaceting(['maxValuesPerFacet' => 100]);
        
        // Ranking qaydaları
        $index->updateRankingRules([
            'words',        // Daha çox söz uyğun gəlir → yüksək score
            'typo',         // Az typo → yüksək score
            'proximity',    // Sözlər bir-birinə yaxın → yüksək score
            'attribute',    // Yuxarıdakı sıra ilə priority
            'sort',         // User sort
            'exactness',    // Dəqiq uyğunluq → yüksək score
        ]);
        
        // Synonym-lar
        $index->updateSynonyms([
            'laptop' => ['notebook', 'computer'],
            'phone' => ['mobile', 'cellphone', 'smartphone'],
            'tv' => ['television', 'monitor'],
        ]);
        
        // Stop words
        $index->updateStopWords(['the', 'a', 'an', 'is', 'are', 'was', 'and', 'or']);
        
        $this->info('Meilisearch configured successfully!');
    }
}

// Axtarış (typo tolerant, instant)
$results = Product::search('laptp')  // typo - "laptop" tapacaq!
    ->where('in_stock', true)
    ->where('price', '>', 100)
    ->orderBy('price', 'asc')
    ->paginate(20);

// Faceted search
use Meilisearch\Client;

$client = app(Client::class);
$result = $client->index('products')->search('laptop', [
    'filter' => ['in_stock = true', 'price > 100'],
    'facets' => ['category', 'brand', 'tags'],
    'sort' => ['price:asc'],
    'limit' => 20,
    'offset' => 0,
    'attributesToHighlight' => ['name', 'description'],
    'highlightPreTag' => '<mark>',
    'highlightPostTag' => '</mark>',
]);

// $result->getHits()         - nəticələr
// $result->getFacetDistribution() - facet sayları: {"category": {"electronics": 5, ...}}
// $result->getEstimatedTotalHits()
// $result->getProcessingTimeMs()  - adətən < 50ms!
```

---

## Algolia

Algolia tam idarə olunan (hosted/SaaS) search API-dir. Server qurma, idarə etmə lazım deyil.

*Algolia tam idarə olunan (hosted/SaaS) search API-dir. Server qurma, i üçün kod nümunəsi:*
```php
// composer require algolia/algoliasearch-client-php

// .env
// SCOUT_DRIVER=algolia
// ALGOLIA_APP_ID=your-app-id
// ALGOLIA_SECRET=your-secret-key

// Algolia-nın üstünlükləri:
// 1. Tam managed - heç bir server lazım deyil
// 2. Global CDN - dünya üzrə aşağı latency
// 3. Çox güclü analytics dashboard
// 4. A/B testing built-in
// 5. Personalization (AI-based)
// 6. JavaScript InstantSearch widgets

// Algolia-nın mənfi tərəfləri:
// 1. Bahalıdır (records + operations ilə ödəniş)
// 2. Data hosted - privacy concerns
// 3. Vendor lock-in
// 4. Query DSL Elasticsearch qədər güclü deyil

// Laravel Scout ilə istifadə (eyni interface)
$results = Product::search('laptop')
    ->where('category', 'electronics')
    ->paginate(20);

// Algolia-ya xas: InstantSearch.js frontend component-ləri
// <script src="https://cdn.jsdelivr.net/npm/algoliasearch/dist/algoliasearch-lite.umd.js"></script>
// <script src="https://cdn.jsdelivr.net/npm/instantsearch.js"></script>
```

---

## Typesense

Typesense C++ dilində yazılmış, open-source, typo-tolerant search engine-dir. Algolia-nın open-source alternativı kimi tanınır.

*Typesense C++ dilində yazılmış, open-source, typo-tolerant search engi üçün kod nümunəsi:*
```php
// composer require typesense/typesense-php
// composer require typesense/laravel-scout-typesense-driver

// .env
// SCOUT_DRIVER=typesense
// TYPESENSE_API_KEY=your-key
// TYPESENSE_HOST=localhost
// TYPESENSE_PORT=8108

// Typesense üstünlükləri:
// 1. Çox sürətli (C++)
// 2. Open-source + self-hosted
// 3. Typo tolerant (built-in)
// 4. Clustering/HA dəstəyi
// 5. Geosearch dəstəyi
// 6. Collection schema auto-detection

// Typesense collection schema
use Typesense\Client;

$client = new Client([
    'api_key' => 'your-key',
    'nodes' => [['host' => 'localhost', 'port' => '8108', 'protocol' => 'http']],
]);

$schema = [
    'name' => 'products',
    'fields' => [
        ['name' => 'name', 'type' => 'string'],
        ['name' => 'description', 'type' => 'string'],
        ['name' => 'price', 'type' => 'float'],
        ['name' => 'category', 'type' => 'string', 'facet' => true],
        ['name' => 'brand', 'type' => 'string', 'facet' => true],
        ['name' => 'in_stock', 'type' => 'bool'],
        ['name' => 'rating', 'type' => 'float'],
    ],
    'default_sorting_field' => 'rating',
];

$client->collections->create($schema);

// Axtarış
$results = $client->collections['products']->documents->search([
    'q' => 'laptp',  // typo-tolerant
    'query_by' => 'name,description,brand',
    'filter_by' => 'in_stock:true && price:>100',
    'sort_by' => 'price:asc',
    'facet_by' => 'category,brand',
    'per_page' => 20,
]);
```

---

## Search Engine Müqayisəsi

```
┌─────────────────────┬──────────┬────────────┬────────────┬──────────┬───────────┐
│ Xüsusiyyət          │ MySQL FTS│ PgSQL FTS  │Elasticsearch│Meilisearch│Typesense │
├─────────────────────┼──────────┼────────────┼────────────┼──────────┼───────────┤
│ Setup               │ Ən asan  │ Asan       │ Mürəkkəb   │ Asan     │ Asan      │
│ Əlavə service lazım │ Xeyr    │ Xeyr       │ Bəli (JVM) │ Bəli     │ Bəli      │
│ Typo tolerance      │ Xeyr    │ Xeyr       │ Manual     │ Auto     │ Auto      │
│ Relevance quality   │ Basic   │ Yaxşı      │ Ən yaxşı   │ Yaxşı    │ Yaxşı     │
│ Fuzzy search        │ Xeyr    │ pg_trgm    │ Bəli       │ Bəli     │ Bəli      │
│ Faceted search      │ Xeyr    │ Xeyr       │ Bəli       │ Bəli     │ Bəli      │
│ Autocomplete        │ Yox     │ prefix :*  │ Manual     │ Built-in │ Built-in  │
│ Highlighting        │ Yox     │ ts_headline│ Bəli       │ Bəli     │ Bəli      │
│ Synonyms            │ Yox     │ Dictionary │ Bəli       │ Bəli     │ Bəli      │
│ Scalability         │ DB limit│ DB limit   │ Çox yüksək │ Orta     │ Yüksək    │
│ Aggregations        │ SQL     │ SQL        │ Çox güclü  │ Facets   │ Facets    │
│ Real-time           │ Bəli    │ Bəli       │ ~1s        │ ~ms      │ ~ms       │
│ RAM istifadəsi      │ Az      │ Az         │ Çox        │ Az       │ Az        │
│ Distributed         │ DB rep  │ DB rep     │ Bəli       │ Xeyr     │ Bəli      │
│ Laravel Scout       │ Xeyr   │ Xeyr       │ 3rd party  │ Official │ 3rd party │
│ Ən yaxşı üçün       │ Sadə FTS│ Orta FTS   │ Enterprise │ SaaS/SMB │ Self-host │
└─────────────────────┴──────────┴────────────┴────────────┴──────────┴───────────┘

Tövsiyə:
- Kiçik layihə, az data (<100K):       PostgreSQL FTS kifayətdir
- Orta layihə, typo tolerance lazım:    Meilisearch və ya Typesense
- Böyük layihə, mürəkkəb axtarış:     Elasticsearch
- Managed istəyirsinizsə:              Algolia (amma bahalıdır)
- Log/analytics:                        Elasticsearch + Kibana
```

---

## Laravel Scout

### Scout Konfiqurasiyası

*Scout Konfiqurasiyası üçün kod nümunəsi:*
```php
// composer require laravel/scout

// config/scout.php
return [
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),
    
    // Queue ilə indeksləmə (production üçün tövsiyə)
    'queue' => env('SCOUT_QUEUE', false),
    
    // Soft deleted model-ləri indekslə
    'soft_delete' => true,
    
    // Batch size
    'chunk' => ['searchable' => 500, 'unsearchable' => 500],
    
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
    ],
    
    'algolia' => [
        'id' => env('ALGOLIA_APP_ID'),
        'secret' => env('ALGOLIA_SECRET'),
    ],
];

// Model
use Laravel\Scout\Searchable;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Attributes\SearchUsingPrefix;

class Product extends Model
{
    use Searchable;
    
    // İndekslənəcək data
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'category' => $this->category?->name,
            'brand' => $this->brand?->name,
            'tags' => $this->tags->pluck('name')->toArray(),
            'in_stock' => $this->stock_quantity > 0,
            'rating' => (float) $this->average_rating,
        ];
    }
    
    // İndekslənməli mi? (şərt)
    public function shouldBeSearchable(): bool
    {
        return $this->is_active;
    }
    
    // Database driver üçün: full-text index sütunları
    #[SearchUsingFullText(['name', 'description'])]
    public function toSearchableArray(): array
    {
        return ['name' => $this->name, 'description' => $this->description];
    }
    
    // Search index adı (custom)
    public function searchableAs(): string
    {
        return 'products_' . app()->environment(); // products_production
    }
}
```

### Scout Axtarış API

*Scout Axtarış API üçün kod nümunəsi:*
```php
// Sadə axtarış
$products = Product::search('laptop')->get();

// Paginate
$products = Product::search('laptop')->paginate(20);

// Filter (where)
$products = Product::search('laptop')
    ->where('category', 'electronics')
    ->where('in_stock', true)
    ->get();

// Sorting
$products = Product::search('laptop')
    ->orderBy('price', 'asc')
    ->get();

// Query callback (driver-specific)
$products = Product::search('laptop', function ($engine, string $query, array $options) {
    // Meilisearch-ə xas options
    $options['filter'] = ['price > 100', 'in_stock = true'];
    $options['facets'] = ['category', 'brand'];
    $options['sort'] = ['price:asc'];
    
    return $engine->search($query, $options);
})->get();

// Raw results (driver-ə xas data)
$rawResults = Product::search('laptop')->raw();

// Scout within (index subset)
$products = Product::search('laptop')
    ->within('products_electronics') // Fərqli index-də axtarış
    ->get();

// İndeksləmə
// Avtomatik: create, update, delete zamanı avtomatik indekslənir
$product = Product::create([...]); // Avtomatik indekslənir

// Manual
$product->searchable();              // Bu product-u indekslə
$product->unsearchable();            // İndeksdən sil
Product::makeAllSearchable();        // Hamısını indekslə
Product::removeAllFromSearch();      // İndeksi təmizlə

// Batch
Product::withoutSyncingToSearch(function () {
    // Bu blokda indeksləmə baş vermir
    Product::factory()->count(1000)->create();
});
// Sonra manual import:
// php artisan scout:import "App\Models\Product"

// Conditional indeksləmə pause
$product->unsearchable();  // İndeksdən sil
$product->update(['is_active' => false]);
// shouldBeSearchable() false qaytardığı üçün yenidən indekslənməyəcək
```

---

## Fuzzy Search, Autocomplete, Synonyms, Faceted Search

### Fuzzy Search (Typo Tolerance)

*Fuzzy Search (Typo Tolerance) üçün kod nümunəsi:*
```php
// Elasticsearch fuzzy search
// "fuzziness": "AUTO" istifadə edilir
// AUTO: 0-2 simvol = exact, 3-5 simvol = 1 typo, 6+ simvol = 2 typo

// Elasticsearch query:
$query = [
    'multi_match' => [
        'query' => 'laptp',  // typo
        'fields' => ['name^3', 'description'],
        'fuzziness' => 'AUTO',
        'prefix_length' => 1,  // İlk N simvol dəqiq olmalıdır
    ],
];

// PostgreSQL fuzzy search (pg_trgm)
// Laravel-də:
$results = DB::table('products')
    ->selectRaw("*, similarity(name, ?) AS sim", [$searchTerm])
    ->whereRaw("similarity(name, ?) > 0.3", [$searchTerm])
    ->orderByDesc('sim')
    ->limit(20)
    ->get();

// Meilisearch: typo tolerance default aktiv
// Konfiqurasiya:
$client->index('products')->updateTypoTolerance([
    'enabled' => true,
    'minWordSizeForTypos' => [
        'oneTypo' => 4,    // 4+ simvol sözlərdə 1 typo-ya icazə
        'twoTypos' => 8,   // 8+ simvol sözlərdə 2 typo-ya icazə
    ],
    'disableOnWords' => ['PHP'],  // Bu sözlər üçün fuzzy deaktiv
    'disableOnAttributes' => ['sku'],  // SKU-da fuzzy lazım deyil
]);
```

### Autocomplete (Search-as-you-type)

*Autocomplete (Search-as-you-type) üçün kod nümunəsi:*
```php
// 1. Elasticsearch: Edge n-gram analyzer

// Index mapping-də:
// "autocomplete_analyzer": {
//   "tokenizer": "edge_ngram_tokenizer",
//   "filter": ["lowercase"]
// }

// 2. Meilisearch: built-in (prefix matching default)
$results = $client->index('products')->search('lap', [
    'limit' => 5,
    'attributesToRetrieve' => ['name', 'category'],
]);
// "lap" → "Laptop", "Lapel Pin", "Lap desk" və s.

// 3. PostgreSQL: prefix query
$results = DB::table('products')
    ->where('search_vector', '@@', DB::raw("to_tsquery('english', '" . $prefix . ":*')"))
    ->limit(10)
    ->get();

// 4. Laravel Livewire + Scout (real-time autocomplete)
// resources/views/livewire/search.blade.php
/*
<div>
    <input type="text" wire:model.live.debounce.300ms="query" placeholder="Axtarış...">
    
    @if(strlen($query) >= 2)
        <ul>
            @foreach($results as $product)
                <li>{{ $product->name }} - {{ $product->price }} AZN</li>
            @endforeach
        </ul>
    @endif
</div>
*/

// app/Livewire/Search.php
class Search extends Component
{
    public string $query = '';
    public $results = [];
    
    public function updatedQuery(): void
    {
        if (strlen($this->query) >= 2) {
            $this->results = Product::search($this->query)
                ->take(10)
                ->get();
        } else {
            $this->results = [];
        }
    }
    
    public function render()
    {
        return view('livewire.search');
    }
}
```

### Synonyms

*Synonyms üçün kod nümunəsi:*
```php
// Elasticsearch synonyms
// Index settings-da:
$settings = [
    'analysis' => [
        'filter' => [
            'synonym_filter' => [
                'type' => 'synonym',
                'synonyms' => [
                    'laptop, notebook, portable computer',
                    'phone, mobile, cellphone, smartphone',
                    'tv, television, tele',
                ],
            ],
        ],
        'analyzer' => [
            'with_synonyms' => [
                'tokenizer' => 'standard',
                'filter' => ['lowercase', 'synonym_filter', 'stemmer'],
            ],
        ],
    ],
];

// Meilisearch synonyms (çox asan)
$client->index('products')->updateSynonyms([
    'laptop' => ['notebook', 'portable computer'],
    'phone' => ['mobile', 'cellphone', 'smartphone'],
    'tv' => ['television'],
]);

// PostgreSQL synonyms (dictionary ilə)
// Fayl yaradılır: $SHAREDIR/tsearch_data/product_synonyms.syn
// laptop notebook
// phone mobile cellphone
// tv television
```

### Faceted Search

Faceted search axtarış nəticələrini kateqoriyalara bölüb, hər kateqoriyadakı nəticə sayını göstərir.

*Faceted search axtarış nəticələrini kateqoriyalara bölüb, hər kateqori üçün kod nümunəsi:*
```php
// E-commerce filter sidebar nümunəsi:
// Kateqoriya: Electronics (45), Clothing (23), Books (12)
// Marka: Apple (15), Samsung (12), Dell (8)
// Qiymət: 0-100 (20), 100-500 (30), 500+ (10)
// Rating: 4+ (35), 3+ (50)

// Meilisearch faceted search
class ProductSearchController extends Controller
{
    public function search(Request $request)
    {
        $client = app(\Meilisearch\Client::class);
        
        // Filter qurmaq
        $filters = [];
        if ($request->category) {
            $filters[] = "category = '{$request->category}'";
        }
        if ($request->brand) {
            $filters[] = "brand = '{$request->brand}'";
        }
        if ($request->min_price) {
            $filters[] = "price >= {$request->min_price}";
        }
        if ($request->max_price) {
            $filters[] = "price <= {$request->max_price}";
        }
        if ($request->in_stock) {
            $filters[] = "in_stock = true";
        }
        
        $result = $client->index('products')->search($request->q ?? '', [
            'filter' => $filters,
            'facets' => ['category', 'brand', 'tags'],
            'sort' => [$request->sort ?? 'rating:desc'],
            'limit' => $request->per_page ?? 20,
            'offset' => ($request->page ?? 0) * ($request->per_page ?? 20),
            'attributesToHighlight' => ['name', 'description'],
            'highlightPreTag' => '<mark>',
            'highlightPostTag' => '</mark>',
        ]);
        
        return response()->json([
            'hits' => $result->getHits(),
            'facets' => $result->getFacetDistribution(),
            'total' => $result->getEstimatedTotalHits(),
            'processing_time_ms' => $result->getProcessingTimeMs(),
        ]);
    }
}

// Elasticsearch aggregations ilə faceted search
$params = [
    'index' => 'products',
    'body' => [
        'query' => [
            'bool' => [
                'must' => [
                    ['multi_match' => [
                        'query' => $request->q,
                        'fields' => ['name^3', 'description', 'tags^2'],
                    ]],
                ],
                'filter' => [
                    ['term' => ['in_stock' => true]],
                ],
            ],
        ],
        'aggs' => [
            'categories' => [
                'terms' => ['field' => 'category', 'size' => 20],
            ],
            'brands' => [
                'terms' => ['field' => 'brand', 'size' => 20],
            ],
            'price_ranges' => [
                'range' => [
                    'field' => 'price',
                    'ranges' => [
                        ['key' => '0-100', 'to' => 100],
                        ['key' => '100-500', 'from' => 100, 'to' => 500],
                        ['key' => '500-1000', 'from' => 500, 'to' => 1000],
                        ['key' => '1000+', 'from' => 1000],
                    ],
                ],
            ],
            'avg_rating' => [
                'avg' => ['field' => 'rating'],
            ],
        ],
        'size' => 20,
        'from' => 0,
    ],
];
```

---

## Indexing Strategiyaları

### Nə Vaxt Re-index Etməli

*Nə Vaxt Re-index Etməli üçün kod nümunəsi:*
```php
// 1. Real-time indexing (default Laravel Scout davranışı)
// Model save/delete zamanı avtomatik
// Kiçik/orta layihələr üçün uyğun
class Product extends Model
{
    use Searchable;
    // Avtomatik: create, update, delete → search index yenilənir
}

// 2. Queue-based indexing (production üçün tövsiyə)
// config/scout.php: 'queue' => true
// Model dəyişikliyi → Queue job → Search index yenilənir
// Database əməliyyatı bloklanmır

// 3. Scheduled batch re-index (böyük data dəyişiklikləri üçün)
// app/Console/Kernel.php
$schedule->command('scout:import "App\\Models\\Product"')
    ->dailyAt('02:00') // Gecə saatlarında
    ->withoutOverlapping();

// 4. Event-driven re-index
class ProductObserver
{
    public function saved(Product $product): void
    {
        // Əlaqəli model-ləri də yenidən indekslə
        if ($product->wasChanged('category_id')) {
            // Kateqoriya dəyişibsə, əlaqəli axtarış data-sı yenilənib
            $product->searchable();
        }
    }
}

// 5. Zero-downtime re-index (Elasticsearch)
// Yeni index yarat → Data-nı yeni index-ə yaz → Alias-ı dəyiş
class ReindexProducts extends Command
{
    public function handle(): void
    {
        $client = app(\Elasticsearch\Client::class);
        $newIndex = 'products_' . date('YmdHis');
        
        // Yeni index yarat
        $client->indices()->create([
            'index' => $newIndex,
            'body' => $this->getMapping(),
        ]);
        
        // Data-nı batch ilə yaz
        Product::with(['category', 'brand', 'tags'])
            ->chunk(1000, function ($products) use ($client, $newIndex) {
                $params = ['body' => []];
                foreach ($products as $product) {
                    $params['body'][] = ['index' => ['_index' => $newIndex, '_id' => $product->id]];
                    $params['body'][] = $product->toSearchableArray();
                }
                $client->bulk($params);
            });
        
        // Alias-ı yeni index-ə yönləndir (zero downtime!)
        $client->indices()->updateAliases([
            'body' => [
                'actions' => [
                    ['remove' => ['index' => 'products_*', 'alias' => 'products']],
                    ['add' => ['index' => $newIndex, 'alias' => 'products']],
                ],
            ],
        ]);
        
        // Köhnə index-ləri sil
        // ...
    }
}
```

---

## Real-world: E-commerce Product Search

### Tam Search Service

*Tam Search Service üçün kod nümunəsi:*
```php
// Bu kod məhsul axtarışını idarə edən tam search service-i göstərir
class ProductSearchService
{
    public function __construct(
        private readonly \Meilisearch\Client $client,
    ) {}
    
    public function search(SearchRequest $request): SearchResult
    {
        $filters = $this->buildFilters($request);
        $sort = $this->buildSort($request);
        
        $meiliResult = $this->client->index('products')->search(
            $request->query ?? '',
            [
                'filter' => $filters,
                'facets' => ['category', 'brand', 'tags', 'color', 'size'],
                'sort' => $sort,
                'limit' => $request->perPage,
                'offset' => ($request->page - 1) * $request->perPage,
                'attributesToHighlight' => ['name', 'description'],
                'highlightPreTag' => '<mark>',
                'highlightPostTag' => '</mark>',
                'attributesToCrop' => ['description'],
                'cropLength' => 50,
                'showMatchesPosition' => true,
            ]
        );
        
        // Elasticsearch model-ləri ilə hydrate et
        $productIds = collect($meiliResult->getHits())->pluck('id');
        
        $products = Product::with(['category', 'brand', 'images', 'variants'])
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy(function ($product) use ($productIds) {
                return $productIds->search($product->id);
            })
            ->values();
        
        return new SearchResult(
            products: $products,
            facets: $meiliResult->getFacetDistribution(),
            total: $meiliResult->getEstimatedTotalHits(),
            page: $request->page,
            perPage: $request->perPage,
            processingTimeMs: $meiliResult->getProcessingTimeMs(),
            highlights: collect($meiliResult->getHits())->mapWithKeys(function ($hit) {
                return [$hit['id'] => $hit['_formatted'] ?? []];
            }),
        );
    }
    
    private function buildFilters(SearchRequest $request): array
    {
        $filters = [];
        
        if ($request->category) {
            $filters[] = "category = '{$request->category}'";
        }
        if ($request->brands) {
            $brandFilter = collect($request->brands)
                ->map(fn($b) => "brand = '{$b}'")
                ->implode(' OR ');
            $filters[] = "({$brandFilter})";
        }
        if ($request->minPrice !== null) {
            $filters[] = "price >= {$request->minPrice}";
        }
        if ($request->maxPrice !== null) {
            $filters[] = "price <= {$request->maxPrice}";
        }
        if ($request->inStockOnly) {
            $filters[] = "in_stock = true";
        }
        if ($request->minRating) {
            $filters[] = "rating >= {$request->minRating}";
        }
        if ($request->tags) {
            foreach ($request->tags as $tag) {
                $filters[] = "tags = '{$tag}'";
            }
        }
        
        return $filters;
    }
    
    private function buildSort(SearchRequest $request): array
    {
        return match($request->sortBy) {
            'price_asc' => ['price:asc'],
            'price_desc' => ['price:desc'],
            'newest' => ['created_at_timestamp:desc'],
            'rating' => ['rating:desc'],
            'name' => ['name:asc'],
            default => [], // relevance (default Meilisearch sorting)
        };
    }
}

// SearchRequest DTO
class SearchRequest
{
    public function __construct(
        public readonly ?string $query = null,
        public readonly ?string $category = null,
        public readonly ?array $brands = null,
        public readonly ?float $minPrice = null,
        public readonly ?float $maxPrice = null,
        public readonly bool $inStockOnly = false,
        public readonly ?float $minRating = null,
        public readonly ?array $tags = null,
        public readonly string $sortBy = 'relevance',
        public readonly int $page = 1,
        public readonly int $perPage = 20,
    ) {}
    
    public static function fromRequest(Request $request): self
    {
        return new self(
            query: $request->input('q'),
            category: $request->input('category'),
            brands: $request->input('brands'),
            minPrice: $request->input('min_price') ? (float) $request->input('min_price') : null,
            maxPrice: $request->input('max_price') ? (float) $request->input('max_price') : null,
            inStockOnly: $request->boolean('in_stock'),
            minRating: $request->input('min_rating') ? (float) $request->input('min_rating') : null,
            tags: $request->input('tags'),
            sortBy: $request->input('sort', 'relevance'),
            page: (int) $request->input('page', 1),
            perPage: min((int) $request->input('per_page', 20), 100),
        );
    }
}

// SearchResult DTO
class SearchResult
{
    public function __construct(
        public readonly Collection $products,
        public readonly array $facets,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $processingTimeMs,
        public readonly Collection $highlights,
    ) {}
    
    public function totalPages(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }
}

// Controller
class ProductSearchController extends Controller
{
    public function __construct(
        private readonly ProductSearchService $searchService,
    ) {}
    
    public function search(Request $request): JsonResponse
    {
        $searchRequest = SearchRequest::fromRequest($request);
        $result = $this->searchService->search($searchRequest);
        
        return response()->json([
            'data' => ProductResource::collection($result->products),
            'facets' => $result->facets,
            'meta' => [
                'total' => $result->total,
                'page' => $result->page,
                'per_page' => $result->perPage,
                'total_pages' => $result->totalPages(),
                'processing_time_ms' => $result->processingTimeMs,
            ],
        ]);
    }
}
```

---

## Performance Optimization

### Search Index Optimization

*Search Index Optimization üçün kod nümunəsi:*
```php
// 1. Yalnız lazımi field-ları indekslə
public function toSearchableArray(): array
{
    // PİS: bütün model attribute-larını indeksləmək
    // return $this->toArray();
    
    // YAXŞI: yalnız axtarışda lazım olanlar
    return [
        'id' => $this->id,
        'name' => $this->name,
        'description' => Str::limit($this->description, 500), // Uzun mətni kəs
        'price' => (float) $this->price,
        'category' => $this->category?->name,
    ];
}

// 2. Batch operations
// PİS: tək-tək indeksləmə
foreach ($products as $product) {
    $product->searchable(); // Hər biri ayrı HTTP request
}

// YAXŞI: batch
$products->searchable(); // Bir HTTP request

// 3. Queue istifadə et
// config/scout.php: 'queue' => true
// Bu sayədə user request-i bloklanmır

// 4. PostgreSQL FTS: generated column + GIN index
// to_tsvector() hər query-də hesablanmasın deyə stored column istifadə et
DB::statement("ALTER TABLE products ADD COLUMN search_vector tsvector 
    GENERATED ALWAYS AS (
        setweight(to_tsvector('english', coalesce(name, '')), 'A') ||
        setweight(to_tsvector('english', coalesce(description, '')), 'B')
    ) STORED");
DB::statement("CREATE INDEX idx_search ON products USING GIN (search_vector)");

// 5. Elasticsearch: refresh interval artır
// Default 1 saniyə - production-da artıra bilərsiniz
// curl -X PUT "localhost:9200/products/_settings" -d '{"refresh_interval": "30s"}'

// 6. Caching
// Populyar sorğuları cache-lə
class CachedSearchService
{
    public function search(SearchRequest $request): SearchResult
    {
        $cacheKey = 'search:' . md5(serialize($request));
        
        return Cache::remember($cacheKey, 300, function () use ($request) {
            return $this->searchService->search($request);
        });
    }
}

// 7. Search analytics: populyar sorğuları izlə
class SearchAnalytics
{
    public function logSearch(string $query, int $resultCount): void
    {
        Redis::zincrby('search:popular', 1, strtolower(trim($query)));
        
        if ($resultCount === 0) {
            Redis::zincrby('search:zero_results', 1, strtolower(trim($query)));
        }
    }
    
    public function getPopularSearches(int $limit = 10): array
    {
        return Redis::zrevrange('search:popular', 0, $limit - 1, 'WITHSCORES');
    }
    
    public function getZeroResultSearches(int $limit = 10): array
    {
        return Redis::zrevrange('search:zero_results', 0, $limit - 1, 'WITHSCORES');
    }
}
```

---

## İntervyu Sualları və Cavabları

### S1: Full Text Search nədir və LIKE-dan nə ilə fərqlənir?

**Cavab:** Full Text Search strukturlaşdırılmamış mətndə sürətli axtarış üçün texnologiyadır. LIKE `%söz%` full table scan edir, index istifadə edə bilmir, relevance scoring-i yoxdur, stemming-i yoxdur. FTS isə inverted index istifadə edir (çox sürətli), nəticələri uyğunluğa görə sıralayır (BM25/TF-IDF), stemming edir ("running" axtaranda "run" tapır), stop words filtrləyir, fuzzy matching dəstəkləyir. Böyük mətn data-sında LIKE istifadə etmək performans fəlakətidir.

### S2: Inverted Index necə işləyir?

**Cavab:** Normal index sənəddən sözlərə işarə edir (Doc1 → [laravel, php, web]). Inverted Index bunun əksini edir - sözdən sənədlərə (laravel → [Doc1, Doc3, Doc7]). Kitabın arxasındakı index kimi: "PHP" sözünü axtarırsan, səhifə 15, 42, 89 yazılıb. Axtarış zamanı sorğudakı hər söz üçün posting list alınır, kəsişmə (AND) və ya birləşmə (OR) tapılır. Bu O(1) əvəzinə O(n) scan etmədən nəticə qaytarır.

### S3: TF-IDF və BM25 arasındakı fərq nədir?

**Cavab:** TF-IDF hər söz üçün Term Frequency (sənəddəki tezlik) ilə Inverse Document Frequency-ni (nadirlik) çarpır. Problemi: TF linear artır - söz 100 dəfə keçirsə 100x score alır, bu realist deyil. BM25 bunu həll edir: (1) Term frequency saturation - tezlik artdıqca score artımı azalır (diminishing returns), (2) Document length normalization - qısa sənəddə 5 dəfə keçmək, 10000 sözlük sənəddə 5 dəfə keçməkdən daha əhəmiyyətlidir. Müasir search engine-lərin əksəriyyəti BM25 istifadə edir.

### S4: Elasticsearch ilə Meilisearch arasındakı fərqlər nələrdir?

**Cavab:** Elasticsearch Java/Lucene əsaslıdır, distributed cluster dəstəkləyir, çox güclü Query DSL və aggregation-ları var, milyardlarla sənəd idarə edə bilər, amma setup mürəkkəbdir və çox RAM tələb edir. Meilisearch Rust-da yazılıb, tək binary ilə qurulur, typo-tolerant default aktivdir, çox sürətlidir (<50ms), amma distributed deyil, ~10M sənəd limitli, aggregation-ları basic-dir. Meilisearch frontend/instant search üçün, Elasticsearch enterprise/analytics üçün daha uyğundur.

### S5: Laravel Scout nədir və hansı driver-ləri var?

**Cavab:** Laravel Scout model-ləri search engine-lərdə indeksləmək üçün driver-əsaslı abstraksiyadır. Searchable trait əlavə etməklə model avtomatik indekslənir (create/update/delete). Dəstəklənən driver-lər: Meilisearch (official, tövsiyə olunan), Algolia (official), Database (SQL əsaslı, full-text/LIKE), Collection (in-memory, test üçün), Elasticsearch (3rd party paketlər ilə), Typesense (3rd party). `toSearchableArray()` ilə indekslənəcək data-nı, `shouldBeSearchable()` ilə şərti müəyyən edirik. Queue dəstəyi var, batch import/flush əmrləri var.

### S6: PostgreSQL FTS-i MySQL FTS-dən nə ilə üstündür?

**Cavab:** PostgreSQL FTS çox daha güclüdür: (1) tsvector/tsquery tip sistemi - daha çevik sorğular, (2) Weight system (A,B,C,D) - title match body match-dən daha yüksək score, (3) GIN index - inverted index performance, (4) Custom dictionaries - öz dil/synonym dictionary-nizi yaratmaq, (5) ts_headline - nəticələrdə highlight/snippet, (6) websearch_to_tsquery - Google-a bənzər syntax, (7) phrase search (<->), proximity search (<N>), prefix search (:*). MySQL FTS isə basic MATCH AGAINST ilə məhdudlaşır, highlighting yoxdur, synonym dəstəyi yoxdur, custom analyzer yoxdur.

### S7: Faceted search nədir?

**Cavab:** Faceted search axtarış nəticələrini müxtəlif kateqoriyalara/atributlara görə qruplaşdırıb, hər qrupdakı nəticə sayını göstərir. E-commerce saytlarında filter sidebar budur: "Kateqoriya: Electronics (45), Books (12); Marka: Apple (15), Samsung (12); Qiymət: 0-100 (20), 100-500 (30)". İstifadəçi filtr seçdikdə digər facet-lərin sayları yenilənir. Elasticsearch aggregation-larla, Meilisearch/Typesense facets parametri ilə həyata keçirir. Database-lərdə native facet dəstəyi yoxdur, GROUP BY ilə simulyasiya etmək lazımdır.

### S8: Search index-i yeniləmə (re-indexing) strategiyaları hansılardır?

**Cavab:** (1) Real-time: model save/delete zamanı avtomatik (Laravel Scout default). Kiçik layihələr üçün. (2) Queue-based: model dəyişikliyi queue job-a göndərilir, asinxron indekslənir. Production üçün tövsiyə. (3) Scheduled batch: cron job ilə müəyyən vaxtlarda tam re-index. Böyük dəyişikliklər üçün. (4) Zero-downtime re-index: yeni index yarat → data yaz → alias dəyiş → köhnəni sil. Elasticsearch-də alias sistemi ilə edilir. (5) Event-driven: yalnız əlaqəli dəyişikliklərdə (kateqoriya adı dəyişəndə həmin kateqoriyadakı bütün məhsulları yenidən indekslə).

### S9: Stemming və Lemmatization arasındakı fərq nədir?

**Cavab:** Stemming sözü algoritmik olaraq kök formasına gətirir (suffix kəsmə) - sürətlidir amma bəzən yanlış nəticə verir: "university" → "univers", "better" → "better" (düzəltmir). Lemmatization isə NLP/lüğət əsaslıdır, sözü dil qaydalarına uyğun lemma-ya gətirir: "better" → "good", "went" → "go", "mice" → "mouse". Lemmatization daha dəqiqdir amma daha yavaşdır. Əksər search engine-lər performans üçün stemming istifadə edir. Elasticsearch-də hər ikisi üçün filter var.

### S10: Böyük e-commerce layihəsində search arxitekturasını necə qurardınız?

**Cavab:** Primary database PostgreSQL/MySQL-da normalized data saxlanılır. Meilisearch və ya Elasticsearch search index kimi istifadə olunur. Model Observer/Queue ilə data dəyişiklikləri asinxron olaraq search index-ə göndərilir. Gecə saatlarında scheduled full re-index. Populyar sorğular Redis-də cache-lənir (5 dəqiqə TTL). Search analytics ilə sıfır nəticəli sorğular izlənir, synonym-lar əlavə olunur. Autocomplete üçün edge n-gram (Elasticsearch) və ya native prefix (Meilisearch). Faceted search ilə filter sidebar. Frontend-də debounce (300ms) ilə axtarış sorğuları. Monitoring: axtarış latency, zero-result rate, click-through rate izlənir.

---

## Anti-patternlər

**1. `LIKE '%keyword%'` ilə Full-Text Search Etmək**
Böyük cədvəllərdə `WHERE name LIKE '%laravel%'` istifadəsi — indeks işləmir, full table scan aparılır, performans kəskin aşağı düşür. Dedicated full-text search (MySQL FULLTEXT, PostgreSQL `tsvector`, Meilisearch, Elasticsearch) istifadə edin.

**2. Search İndeksini Sinxron Yeniləmək**
Hər model save-ində search indeksini sinxron yeniləmək — HTTP request bloklanır, istifadəçi yavaş cavab alır, yüksək trafik zamanı timeout baş verir. Scout-un queue driver-ini aktivləşdirin; indeks yeniləmələri asinxron job ilə emal edilsin.

**3. Axtarış Sorğularını Cache Etməmək**
Eyni populyar axtarış sorğusunu hər dəfə search engine-ə göndərmək — resurs israfına yol açır, latency artır. Populyar sorğuları Redis-də qısa müddətə (3–10 dəqiqə) cache-ləyin; zero-result sorğularını da cache edin.

**4. Typo Toleransını Nəzərə Almamaq**
Yalnız dəqiq uyğunluq axtaran search qurmaq — istifadəçi "laptob" yazdıqda nəticə tapmır, axtarış faydasız görünür, dönüşüm düşür. Meilisearch/Elasticsearch-in fuzzy matching, typo tolerance xüsusiyyətlərini aktivləşdirin.

**5. Zero-Result Sorğularını İzləməmək**
Heç nəticə verməyən axtarış sorğularını analiz etməmək — istifadəçinin nə axtardığı bilinmir, synonym, alias əlavə etmək mümkün olmur, UX pisləşir. Search analytics qurun; zero-result sorğuları loqlayın, dövri olaraq nəzərdən keçirib synonym-lər əlavə edin.

**6. Bütün Sahələri Eyni Ağırlıqla İndeksləmək**
`name`, `description`, `tags` sahələrinə eyni relevance weight vermək — axtarış nəticə reytinqi düzgün olmur, title uyğunluğu description-dan daha vacib olmasına baxmayaraq eyni skora sahib olur. Boosting ilə kritik sahələrə (ad, başlıq) daha yüksək ağırlıq verin.

**7. Search Index-ini Primary DB Kimi İstifadə Etmək**
Elasticsearch/Meilisearch-i canonical data store kimi istifadə etmək — search engine-lər eventual consistency-ə əsaslanır, primary DB-nin rolunu oynaya bilməz, data itirilə bilər. Search engine yalnız ikincil (read-optimized) index rolunda olmalıdır; canonical data həmişə relational/primary DB-də saxlanılmalıdır.

**8. Multi-Tenant Sistemdə İndeks İzolyasiyasını Atlamaq**
Bütün tenant-ların məlumatlarını tək indeksdə saxlamaq — tenant filtrini query-dən unutmaq məlumat sızmasına yol açır. Hər tenant üçün ayrı index (index-per-tenant) daha güclü izolyasiya verir; ən azından hər sorğuya `tenant_id` filtri mütləq əlavə edilməlidir.
