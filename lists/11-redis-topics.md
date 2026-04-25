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
