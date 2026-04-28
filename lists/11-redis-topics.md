## String

SET key value [EX seconds] [PX ms] [NX|XX] [KEEPTTL]
GET key
GETSET key value (deprecated → SET ... GET)
GETDEL key
MSET k1 v1 k2 v2
MGET k1 k2
SETNX key value — yalnız yoxdursa (distributed lock üçün baza)
SETEX key seconds value
PSETEX key ms value
APPEND key value
STRLEN key
GETRANGE key start end
SETRANGE key offset value
INCR key
DECR key
INCRBY key n / DECRBY key n
INCRBYFLOAT key n
BITCOUNT key [start end]
BITOP AND|OR|XOR|NOT dest k1 k2
BITPOS key 0|1
SETBIT key offset 0|1
GETBIT key offset
BITFIELD key GET/SET/INCRBY ...

## List (doubly linked list)

LPUSH key v1 v2 ...
RPUSH key v1 v2 ...
LPOP key [count]
RPOP key [count]
LRANGE key start stop
LLEN key
LINDEX key i
LSET key i value
LINSERT key BEFORE|AFTER pivot value
LREM key count value
LTRIM key start stop
RPOPLPUSH src dst (deprecated → LMOVE)
LMOVE src dst LEFT|RIGHT LEFT|RIGHT
BLPOP key [key ...] timeout — blocking
BRPOP key [key ...] timeout
BLMOVE — blocking

## Hash (field-value dict)

HSET key field value [field value ...]
HGET key field
HMGET key field1 field2
HGETALL key
HDEL key field1 field2
HEXISTS key field
HKEYS key / HVALS key
HLEN key
HINCRBY key field n
HINCRBYFLOAT key field n
HSCAN key cursor MATCH pat COUNT n
HSETNX key field value
HRANDFIELD key count

## Set (unique members)

SADD key m1 m2
SREM key m
SMEMBERS key
SISMEMBER key m
SMISMEMBER key m1 m2 — multi check (7.0+)
SINTER / SINTERSTORE
SUNION / SUNIONSTORE
SDIFF / SDIFFSTORE
SCARD key
SPOP key [count]
SRANDMEMBER key [count]
SMOVE src dst member
SSCAN key cursor MATCH pat

## Sorted Set / ZSet (score-sorted, unique members)

ZADD key [NX|XX|GT|LT] score member [score member ...]
ZRANGE key start stop [REV] [WITHSCORES] [BYSCORE] [LIMIT offset count]
ZRANGEBYSCORE key min max — (legacy, ZRANGE ... BYSCORE)
ZREVRANGE (köhnə, ZRANGE ... REV)
ZRANGEBYLEX / ZREVRANGEBYLEX
ZRANK key member / ZREVRANK
ZSCORE key member
ZINCRBY key n member
ZCARD key
ZCOUNT key min max
ZLEXCOUNT key min max
ZPOPMIN / ZPOPMAX key [count]
BZPOPMIN / BZPOPMAX — blocking
ZREM key m1 m2
ZREMRANGEBYRANK key start stop
ZREMRANGEBYSCORE key min max
ZUNIONSTORE / ZINTERSTORE / ZDIFFSTORE
ZRANDMEMBER key [count] [WITHSCORES]

## Stream (append-only log)

XADD key * field value ...
XREAD COUNT n STREAMS key id
XRANGE key start end
XREVRANGE
XLEN key
XDEL key id
XTRIM key MAXLEN ~ n
XGROUP CREATE stream group $ MKSTREAM
XGROUP CREATECONSUMER
XREADGROUP GROUP g c COUNT n STREAMS stream >
XACK stream group id
XPENDING stream group
XCLAIM stream group consumer min-idle-time id ...
XAUTOCLAIM (6.2+)
XINFO STREAM / GROUPS / CONSUMERS

## Pub/Sub (fire-and-forget, non-durable)

PUBLISH channel message
SUBSCRIBE ch1 ch2
UNSUBSCRIBE
PSUBSCRIBE pattern*
PUNSUBSCRIBE
PUBSUB CHANNELS [pattern]
PUBSUB NUMSUB
PUBSUB NUMPAT
SSUBSCRIBE / SPUBLISH — sharded pub/sub (cluster)

## Geospatial

GEOADD key lon lat member
GEOPOS key m1 m2
GEODIST key m1 m2 [unit]
GEOSEARCH key FROMLONLAT ... BYRADIUS ... ASC
GEOSEARCHSTORE
GEOHASH key member

## HyperLogLog (approx cardinality)

PFADD key elem ...
PFCOUNT key [key ...]
PFMERGE dest src1 src2

## Bitmap (set on strings)

Bit-field operations yuxarıda STRING-də

## Scripting / server-side

EVAL "script" numkeys key1 ... arg1 ...
EVALSHA sha1 numkeys ...
SCRIPT LOAD "script"
SCRIPT FLUSH
FUNCTION LOAD ... (7.0+)
FCALL fn_name numkeys ...

## Transaction / optimistic

MULTI — transaction başla
EXEC — commit
DISCARD
WATCH key — optimistic locking
UNWATCH

## TTL / expiration

EXPIRE key seconds [NX|XX|GT|LT]
PEXPIRE key ms
EXPIREAT key unix-ts
PEXPIREAT
TTL key / PTTL
PERSIST key — TTL sil
EXPIRETIME / PEXPIRETIME (7.0+)

## Keys / management

EXISTS key [key ...]
DEL key [key ...]
UNLINK key (async delete, böyük key üçün)
TYPE key
RENAME src dst
RENAMENX src dst
KEYS pattern — prod-da qaçın!
SCAN cursor MATCH pat COUNT n — güvənli traverse
RANDOMKEY
TOUCH key
COPY src dst [DB n] [REPLACE]
DUMP / RESTORE
OBJECT ENCODING key
OBJECT IDLETIME key
OBJECT FREQ key
DEBUG OBJECT key

## Database / connection

SELECT n — DB seç (0-15 default)
FLUSHDB / FLUSHALL
DBSIZE
AUTH [user] password
HELLO 3 — RESP3 upgrade
CLIENT LIST / KILL / GETNAME / SETNAME / PAUSE / NO-EVICT
CLIENT REPLY ON|OFF|SKIP
INFO [section] — server stats
CONFIG GET / SET / RESETSTAT / REWRITE
DEBUG SLEEP n
SHUTDOWN [NOSAVE|SAVE]
MONITOR — bütün komandları izlə (test üçün)
PING / ECHO
TIME
WAIT numreplicas timeout — replication barrier
RESET

## Persistence / replication

SAVE / BGSAVE — RDB snapshot
BGREWRITEAOF — AOF rewrite
LASTSAVE
REPLICAOF host port (əvvəl SLAVEOF)
REPLICAOF NO ONE — replication kəs

## Cluster

CLUSTER INFO
CLUSTER NODES
CLUSTER SHARDS (7.0+)
CLUSTER SLOTS (legacy)
CLUSTER KEYSLOT key
CLUSTER GETKEYSINSLOT slot count
CLUSTER COUNTKEYSINSLOT slot
CLUSTER MEET ip port
CLUSTER FORGET node-id
CLUSTER RESET
CLUSTER FAILOVER
MOVED / ASK redirect responses

## Client-side caching (RESP3)

CLIENT TRACKING ON REDIRECT client-id
CLIENT TRACKINGINFO
CLIENT CACHING yes/no

## ACL (6.0+)

ACL WHOAMI
ACL LIST / GETUSER / SETUSER / DELUSER
ACL CAT
ACL LOG
ACL SAVE / LOAD

## Modules (loadable)

RedisJSON — JSON.SET, JSON.GET, JSON.ARRAPPEND
RediSearch — FT.CREATE, FT.SEARCH (full-text + vector)
RedisBloom — BF.ADD, BF.EXISTS (Bloom filter)
RedisGraph (discontinued)
RedisTimeSeries — TS.ADD, TS.RANGE
Vector search (RediSearch-də HNSW)

## Patterns

Distributed lock (SET NX PX + Lua unlock)
Rate limiter (INCR + EXPIRE; token bucket; sliding window with ZSET)
Leaderboard (ZSET)
Feed (LIST və ya ZSET)
Session store
Cache-aside (GET → miss → DB → SETEX)
Counter (INCR)
Queue (LPUSH + BRPOP)
Delayed queue (ZSET + timestamp as score)
Pub/Sub vs Streams (Streams persistent, consumer groups var)
Circuit breaker state (SET NX + EXPIRE)

## Redis 7.x new commands

# 7.0
LMPOP count key [key ...] LEFT|RIGHT [COUNT n]        — pop from multiple lists
ZMPOP count key [key ...] MIN|MAX [COUNT n]            — pop from multiple ZSETs
BLMPOP timeout count key LEFT|RIGHT                    — blocking multi-list pop
BZMPOP timeout count key MIN|MAX
OBJECT FREQ key                                         — LFU frequency counter
XAUTOCLAIM (already in 6.2, improved in 7.0)
FUNCTION LOAD / FCALL — user-defined functions (replaces EVAL for prod use)

# 7.2
SINTERCARD count key [key ...] [LIMIT limit]           — set intersection size (no full result)
ZINTERCARD count key [key ...] [LIMIT limit]           — sorted set intersection size
LPOS key element [RANK n] [COUNT n] [MAXLEN n]        — find element position(s) in list

# 7.4
CLIENT NO-TOUCH                    — CLIENT NO-TOUCH ON/OFF: prevent key access from updating LRU/LFU clock
                                     Use: read-only cache inspection without polluting eviction stats
HGETDEL key FIELDS n f1 f2         — atomic get-then-delete hash fields
HGETEX key [EX|PX|EXAT|PXAT|PERSIST] FIELDS n f1 f2  — get + set TTL on fields
HSETEX key seconds FIELDS n f1 v1  — set hash fields with per-field TTL
Hash field expiry (hash TTL) — Redis 7.4 nadir feature: individual hash field expiration

## MEMORY commands (production use)

MEMORY USAGE key [SAMPLES n]      — approximate memory in bytes (includes overhead)
MEMORY DOCTOR                      — recommendations
MEMORY MALLOC-STATS                — allocator stats
MEMORY PURGE                       — free memory back to OS (jemalloc fragmentation)
MEMORY STATS                       — full breakdown

# Key inspection pattern
MEMORY USAGE bigkey               — find unexpectedly large keys
DEBUG OBJECT key                   — serializedlength (RDB size), encoding, refcount, lru_seconds

# Memory optimization per type
String: < 44 bytes → embstr (one alloc); use int encoding for counters
Hash: ≤ 128 fields AND field values ≤ 64 bytes → listpack (compact); else hashtable
List: ≤ 128 elements AND values ≤ 64 bytes → listpack
ZSet: ≤ 128 elements AND values ≤ 64 bytes → listpack; else skiplist
Set: ≤ 128 members AND integer set → intset; else listpack/hashtable

# Config for listpack thresholds
hash-max-listpack-entries 128
hash-max-listpack-value 64
zset-max-listpack-entries 128
zset-max-listpack-value 64
list-max-listpack-size 128

## Redis Stack modules (bundled)

# RedisJSON (json.io)
JSON.SET key $ '{"name":"alice","age":30}'
JSON.GET key $.name                          — JSONPath ($.field)
JSON.ARRAPPEND key $.items '{"id":1}'
JSON.NUMINCRBY key $.age 1
JSON.DEL key $.field
JSON.MGET key1 key2 $.name

# RediSearch (full-text + vector)
FT.CREATE idx ON JSON PREFIX 1 user: SCHEMA $.name TEXT $.age NUMERIC
FT.SEARCH idx "alice" RETURN 2 $.name $.age
FT.SEARCH idx "@age:[25 35]"
FT.AGGREGATE idx "*" GROUPBY 1 @status REDUCE COUNT 0 AS count
FT.DROPINDEX idx

# Vector search (RediSearch HNSW)
FT.CREATE vidx ON HASH SCHEMA embedding VECTOR HNSW 6 TYPE FLOAT32 DIM 1536 DISTANCE_METRIC COSINE
HSET doc:1 embedding <binary_vector>
FT.SEARCH vidx "*=>[KNN 10 @embedding $vec]" PARAMS 2 vec <binary_vector> DIALECT 2

# RedisBloom
BF.RESERVE bf 0.001 1000000        — 0.1% FP, 1M capacity
BF.ADD bf item
BF.EXISTS bf item                  — false positive possible
BF.MADD / BF.MEXISTS
CF.ADD / CF.EXISTS                  — Cuckoo filter (supports deletion)
CMS.INITBYDIM / CMS.INCRBY / CMS.QUERY   — Count-Min Sketch (freq estimation)
TOPK.ADD / TOPK.LIST / TOPK.QUERY        — Top-K heavy hitters

# RedisTimeSeries
TS.CREATE temp:sensor RETENTION 86400000 LABELS location nyc type temp
TS.ADD temp:sensor * 23.5            — * = auto timestamp
TS.RANGE temp:sensor - + AGGREGATION avg 3600000   — hourly avg
TS.MRANGE - + FILTER type=temp AGGREGATION last 60000   — multi-series
TS.CREATERULE src dst AGGREGATION avg 3600000             — downsampling rule

## Eviction policies

noeviction          — return error when maxmemory reached (default)
allkeys-lru         — evict any key by LRU
volatile-lru        — evict only keys with TTL by LRU
allkeys-lfu         — evict by access frequency (7.0+)
volatile-lfu        — TTL keys by frequency
allkeys-random
volatile-random
volatile-ttl        — evict keys with shortest TTL first

# Config
maxmemory 2gb
maxmemory-policy allkeys-lru

## Keyspace notifications (for expiry events etc)

CONFIG SET notify-keyspace-events KEA   — K=keyspace, E=keyevent, A=all events
SUBSCRIBE __keyevent@0__:expired         — get expired keys
SUBSCRIBE __keyspace@0__:mykey           — events on specific key
Use: cache invalidation, TTL-based workflows
AVOID: high-traffic prod — every command generates notification = overhead
