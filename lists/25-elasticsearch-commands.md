## Concepts

Cluster — node-lar toplusu
Node — Elasticsearch instance
Index — RDBMS-də table-a bənzər (logical grouping)
Shard — index-in primary partition (Lucene index)
Replica — shard kopyası (HA + read scale)
Document — JSON record
Mapping — schema (field types)
Analyzer — text → tokens (tokenizer + filters)
Inverted index — term → document list
Refresh — yeni doc-lar search-ə görünür (1s default)
Flush — translog → disk
Segment — Lucene immutable file; merge-lənir
Coordinating node — request gateway
Master / Data / Ingest / ML node roles
Data tiers: hot / warm / cold / frozen
ILM — Index Lifecycle Management
Aliases — index üzərində adlandırma (rolling reindex üçün)
Data stream — append-only log (time-series, replaces _type)

## REST API basics

curl -u user:pass http://localhost:9200/
curl -X GET 'localhost:9200/_cat/indices?v'
curl -X PUT 'localhost:9200/myindex' -H 'Content-Type: application/json' -d '{...}'
curl -X POST 'localhost:9200/myindex/_doc' -H 'Content-Type: application/json' -d '{...}'

GET / — cluster info
GET /_cluster/health
GET /_cluster/health?level=indices
GET /_cluster/state
GET /_nodes
GET /_nodes/stats
GET /_cluster/settings
GET /_cluster/allocation/explain
GET /_tasks — running tasks
POST /_tasks/<task-id>/_cancel

## _cat APIs (human-readable)

GET /_cat/indices?v&s=store.size:desc
GET /_cat/health?v
GET /_cat/nodes?v
GET /_cat/shards?v
GET /_cat/aliases?v
GET /_cat/templates?v
GET /_cat/pending_tasks?v
GET /_cat/recovery?v&active_only=true
GET /_cat/segments?v
GET /_cat/thread_pool?v
GET /_cat/master?v
GET /_cat/allocation?v — disk per node
GET /_cat/count/myindex
GET /_cat/aliases/myalias?v

## Index management

PUT /myindex
PUT /myindex
{
  "settings": {
    "number_of_shards": 3,
    "number_of_replicas": 1,
    "refresh_interval": "1s",
    "analysis": {
      "analyzer": {
        "my_analyzer": { "tokenizer": "standard", "filter": ["lowercase", "stop"] }
      }
    }
  },
  "mappings": {
    "properties": {
      "title":   { "type": "text", "analyzer": "my_analyzer" },
      "tags":    { "type": "keyword" },
      "created": { "type": "date" },
      "price":   { "type": "double" }
    }
  }
}
DELETE /myindex
HEAD /myindex
GET /myindex
GET /myindex/_settings
GET /myindex/_mapping
PUT /myindex/_settings { "index.refresh_interval": "30s" }
PUT /myindex/_mapping { "properties": { "new_field": { "type": "keyword" } } } — only adds, no breaking change
POST /myindex/_close — read/write disable, save resources
POST /myindex/_open
POST /myindex/_forcemerge?max_num_segments=1
POST /myindex/_refresh
POST /myindex/_flush
POST /myindex/_clear_cache

## Document API

PUT /myindex/_doc/1 { ... } — create or replace
POST /myindex/_doc { ... } — auto-generated id
PUT /myindex/_create/1 { ... } — fail if exists (409)
GET /myindex/_doc/1
GET /myindex/_source/1
HEAD /myindex/_doc/1
DELETE /myindex/_doc/1
POST /myindex/_update/1 { "doc": { "field": "v" } }
POST /myindex/_update/1 { "script": { "source": "ctx._source.count++", "lang": "painless" } }
POST /myindex/_update/1 { "doc": {...}, "doc_as_upsert": true }
POST /myindex/_update_by_query { "query": {...}, "script": {...} }
POST /myindex/_delete_by_query { "query": {...} }

## Bulk API

POST /_bulk
{ "index":  { "_index": "myindex", "_id": "1" } }
{ "title": "doc1" }
{ "create": { "_index": "myindex", "_id": "2" } }
{ "title": "doc2" }
{ "update": { "_index": "myindex", "_id": "1" } }
{ "doc": { "title": "updated" } }
{ "delete": { "_index": "myindex", "_id": "3" } }

# NDJSON format, hər sətir newline ilə bitir
# Recommended size: 5–15 MB / batch

## Search DSL

GET /myindex/_search
GET /myindex/_search?q=title:foo
GET /_search?size=10&from=20&sort=created:desc

POST /myindex/_search
{
  "query": { "match_all": {} },
  "from": 0, "size": 10,
  "sort": [{ "created": "desc" }],
  "_source": ["title", "created"]
}

# Match (analyzed text)
{ "query": { "match": { "title": "quick fox" } } }
{ "query": { "match": { "title": { "query": "quick fox", "operator": "and" } } } }
{ "query": { "match_phrase": { "title": "quick brown fox" } } }
{ "query": { "match_phrase_prefix": { "title": "quick" } } }
{ "query": { "multi_match": { "query": "term", "fields": ["title^3", "body"] } } }

# Term (exact, no analysis — keyword/numeric/date)
{ "query": { "term": { "tags": "php" } } }
{ "query": { "terms": { "tags": ["php","laravel"] } } }
{ "query": { "range": { "price": { "gte": 10, "lt": 100 } } } }
{ "query": { "exists": { "field": "deleted_at" } } }
{ "query": { "prefix": { "name": "ali" } } }
{ "query": { "wildcard": { "name": "ali*ce" } } }
{ "query": { "regexp": { "name": "al.*" } } }
{ "query": { "fuzzy": { "name": { "value": "alise", "fuzziness": "AUTO" } } } }
{ "query": { "ids": { "values": ["1","2"] } } }

# Bool combiner
{
  "query": {
    "bool": {
      "must":     [{ "match": { "title": "fox" } }],
      "should":   [{ "match": { "body": "lazy" } }],
      "filter":   [{ "term": { "status": "active" } }, { "range": { "price": { "lte": 100 } } }],
      "must_not": [{ "term": { "spam": true } }],
      "minimum_should_match": 1
    }
  }
}

# Nested / parent-child
{ "query": { "nested": { "path": "comments", "query": { "match": { "comments.text": "good" } } } } }
{ "query": { "has_child": { "type": "comment", "query": {...} } } }

# Function score / boost
{ "query": { "function_score": { "query": {...}, "functions": [...], "score_mode": "sum" } } }

# Highlighting
{ "query": {...}, "highlight": { "fields": { "title": {} } } }

# Pagination (deep) — search_after / PIT
POST /myindex/_pit?keep_alive=1m
{ "query": {...}, "pit": { "id": "...", "keep_alive": "1m" }, "search_after": [last_sort_value] }

# Scroll (legacy, deep snapshot)
POST /myindex/_search?scroll=1m { "query": {...} }
POST /_search/scroll { "scroll": "1m", "scroll_id": "..." }
DELETE /_search/scroll { "scroll_id": "..." }

## Aggregations

POST /myindex/_search
{
  "size": 0,
  "aggs": {
    "by_status": { "terms": { "field": "status", "size": 10 } },
    "avg_price": { "avg": { "field": "price" } },
    "price_stats": { "stats": { "field": "price" } },
    "price_hist": { "histogram": { "field": "price", "interval": 50 } },
    "by_day":    { "date_histogram": { "field": "created", "calendar_interval": "1d" } },
    "ranges":    { "range": { "field": "price", "ranges": [{ "to": 50 }, { "from": 50, "to": 100 }] } },
    "by_status_then_avg": {
      "terms": { "field": "status" },
      "aggs":  { "avg_price": { "avg": { "field": "price" } } }
    },
    "top_users": { "terms": { "field": "user_id" }, "aggs": { "top_hit": { "top_hits": { "size": 1 } } } },
    "unique_users": { "cardinality": { "field": "user_id" } },
    "percentiles": { "percentiles": { "field": "rt_ms", "percents": [50,95,99] } }
  }
}

## Mapping types

Text — analyzed (full-text search)
Keyword — exact (filter, sort, agg)
Numeric — long, integer, short, byte, double, float, half_float, scaled_float
Date — flexible formats (epoch_millis, ISO, custom)
Boolean
Binary (base64)
IP, GEO_POINT, GEO_SHAPE
Nested — array of objects (independent docs)
Object — nested without independence (flattened)
Join — parent/child
Dense_vector / sparse_vector — KNN search (8.x)
Completion — suggester
Search_as_you_type
Multi-fields — { "title": { "type": "text", "fields": { "keyword": { "type": "keyword" } } } }
Dynamic templates — auto-mapping rules

## Analyzers

Built-in: standard, simple, whitespace, stop, keyword, pattern, language analyzers (english, russian, ...)
Custom: tokenizer + filters
Tokenizers: standard, whitespace, keyword, ngram, edge_ngram, path_hierarchy
Filters: lowercase, stop, snowball, asciifolding, synonym, stemmer, length, ngram
GET /_analyze { "analyzer": "standard", "text": "Quick brown fox" } — test
GET /myindex/_analyze { "field": "title", "text": "..." }

## ILM (Index Lifecycle Management)

PUT /_ilm/policy/logs-policy
{
  "policy": {
    "phases": {
      "hot":    { "actions": { "rollover": { "max_age": "1d", "max_size": "50gb" } } },
      "warm":   { "min_age": "7d",  "actions": { "shrink": { "number_of_shards": 1 }, "forcemerge": { "max_num_segments": 1 } } },
      "cold":   { "min_age": "30d", "actions": { "freeze": {} } },
      "delete": { "min_age": "90d", "actions": { "delete": {} } }
    }
  }
}
PUT /_index_template/logs { "index_patterns": ["logs-*"], "template": { "settings": { "index.lifecycle.name": "logs-policy" } } }
GET /_ilm/policy
POST /_ilm/start / _ilm/stop
GET /myindex/_ilm/explain

## Reindex / aliases

POST /_reindex { "source": { "index": "old" }, "dest": { "index": "new" } }
POST /_reindex?wait_for_completion=false { ... } — async, returns task id
POST /_reindex { "source": { "index": "old", "query": {...} }, "dest": { "index": "new" }, "script": {...} }
POST /old/_clone/new — clone (read-only source)
POST /myindex/_split/larger { "settings": { "index.number_of_shards": 6 } } — split
POST /myindex/_shrink/smaller { "settings": { "index.number_of_shards": 1 } } — shrink

POST /_aliases
{ "actions": [
  { "remove": { "index": "old", "alias": "live" } },
  { "add":    { "index": "new", "alias": "live" } }
] }
GET /_alias/live

## Snapshots / restore

PUT /_snapshot/my_repo { "type": "fs", "settings": { "location": "/backup" } }
PUT /_snapshot/my_repo/snap_1?wait_for_completion=true { "indices": "logs-*" }
GET /_snapshot/my_repo/_all
DELETE /_snapshot/my_repo/snap_1
POST /_snapshot/my_repo/snap_1/_restore { "indices": "logs-2025-01" }
SLM (Snapshot Lifecycle Management):
PUT /_slm/policy/daily { "schedule": "0 30 1 * * ?", "name": "<daily-{now/d}>", "repository": "my_repo", "config": { "indices": ["*"] }, "retention": { "expire_after": "30d" } }

## Vector / KNN search (8.x+)

PUT /myindex { "mappings": { "properties": { "vector": { "type": "dense_vector", "dims": 768, "index": true, "similarity": "cosine" } } } }
POST /myindex/_search { "knn": { "field": "vector", "query_vector": [...], "k": 10, "num_candidates": 100 } }
Hybrid (lexical + vector): { "query": {...}, "knn": {...}, "rank": { "rrf": {} } }

## Performance / debug

GET /myindex/_stats
GET /myindex/_segments
POST /myindex/_search?explain=true { ... } — score breakdown
GET /myindex/_validate/query?explain&q=*
POST /myindex/_search?profile=true { ... } — query profile
GET /_nodes/hot_threads — hot Java threads
GET /_cluster/pending_tasks
GET /_recovery
GET /_cat/thread_pool/search?v&h=node_name,active,queue,rejected
GET /_cluster/allocation/explain { "index": "myindex", "shard": 0, "primary": true }
PUT /myindex/_settings { "index.routing.allocation.exclude._name": "node-1" } — node drain

## Security (X-Pack / built-in)

bin/elasticsearch-users useradd alice -p secret -r role1
GET /_security/user
GET /_security/role
PUT /_security/role/myrole { "indices": [{ "names": ["myindex"], "privileges": ["read"] }] }
POST /_security/api_key { "name": "key1", "expiration": "30d" }
GET /_security/api_key?owner=true
DELETE /_security/api_key { "ids": ["..."] }

## Common patterns

Time-series: data stream + ILM rollover
Versioning: alias-swap reindex
Multi-tenant: filter by tenant + routing key
Search-as-you-type: completion suggester or edge_ngram
Logs: structured JSON + ILM hot/warm/cold + ECS schema
Application search: relevance tuning (function_score, boost, synonyms)
Vector search: dense_vector + KNN + hybrid (RRF)
Idempotency on bulk: external version (?version_type=external)
