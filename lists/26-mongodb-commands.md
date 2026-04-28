## Connection / mongosh

mongosh "mongodb://user:pass@host:27017/db"
mongosh "mongodb+srv://user:pass@cluster.example.net/db" — Atlas / SRV
mongosh --host host --port 27017 -u user -p --authenticationDatabase admin
mongosh --eval 'db.users.countDocuments()'
mongosh --quiet
exit / quit() — çıx
help — kömək
db — cari DB
db.help() / db.users.help()
show dbs / show databases
show collections / show tables
use mydb — DB seç (yoxdursa lazy yaradır)
db.getName() / db.getCollectionNames()
db.stats() / db.users.stats()

## CRUD basics

db.users.insertOne({ name: "Alice", age: 30 })
db.users.insertMany([{ ... }, { ... }])
db.users.findOne()
db.users.findOne({ _id: ObjectId("...") })
db.users.find()                            // cursor
db.users.find().pretty()
db.users.find().limit(10).skip(20).sort({ created: -1 })
db.users.find({}, { name: 1, _id: 0 })     // projection
db.users.countDocuments({ status: "active" })
db.users.estimatedDocumentCount()          // fast, metadata
db.users.distinct("country")

db.users.updateOne(
  { _id: ObjectId("...") },
  { $set: { age: 31 }, $inc: { logins: 1 } }
)
db.users.updateMany({ status: "old" }, { $set: { status: "archived" } })
db.users.replaceOne({ _id: ... }, { ...new_doc })
db.users.updateOne({ email }, { $setOnInsert: {...}, $set: {...} }, { upsert: true })

db.users.deleteOne({ _id: ... })
db.users.deleteMany({ status: "spam" })

db.users.findOneAndUpdate({ _id: ... }, { $set: {...} }, { returnDocument: "after" })
db.users.findOneAndReplace(...)
db.users.findOneAndDelete(...)

## Bulk operations

db.users.bulkWrite([
  { insertOne: { document: {...} } },
  { updateOne: { filter: {...}, update: { $set: {...} }, upsert: true } },
  { deleteOne: { filter: {...} } }
], { ordered: false })

## Query operators

Comparison: $eq $ne $gt $gte $lt $lte $in $nin
Logical:    $and $or $nor $not
Element:    $exists $type
Evaluation: $regex $expr $jsonSchema $mod $where (avoid)
Array:      $all $elemMatch $size
Bitwise:    $bitsAllSet $bitsAnySet

db.users.find({ age: { $gte: 18, $lt: 65 } })
db.users.find({ tags: { $in: ["php","laravel"] } })
db.users.find({ $or: [{ a: 1 }, { b: 2 }] })
db.users.find({ name: /^ali/i })             // regex
db.users.find({ "address.city": "Baku" })    // nested field
db.users.find({ tags: { $elemMatch: { $eq: "php" } } })
db.users.find({ $expr: { $gt: ["$spent", "$budget"] } })

## Update operators

$set $unset $inc $mul $min $max $rename $currentDate
Array: $push $pull $addToSet $pop $pullAll
$push with $each / $slice / $sort / $position
$[]  — match all elements
$[<id>] — filtered positional (with arrayFilters)

db.users.updateOne(
  { _id },
  { $push: { logs: { $each: [{...}], $slice: -100 } } }
)
db.posts.updateOne(
  { _id, "comments.id": cid },
  { $set: { "comments.$.text": "edited" } }
)
db.posts.updateMany(
  {},
  { $set: { "comments.$[c].read": true } },
  { arrayFilters: [{ "c.user": "alice" }] }
)

## Aggregation pipeline

db.orders.aggregate([
  { $match: { status: "paid", created: { $gte: ISODate("2025-01-01") } } },
  { $group: { _id: "$user_id", total: { $sum: "$amount" }, n: { $sum: 1 } } },
  { $sort: { total: -1 } },
  { $limit: 10 },
  { $lookup: { from: "users", localField: "_id", foreignField: "_id", as: "user" } },
  { $unwind: "$user" },
  { $project: { _id: 0, name: "$user.name", total: 1, n: 1 } }
])

# Common stages
$match $project $group $sort $limit $skip
$lookup       — JOIN
$unwind       — array → docs
$addFields / $set
$replaceRoot / $replaceWith
$facet        — multi-pipeline parallel
$bucket / $bucketAuto
$count
$out / $merge — write result back
$graphLookup  — recursive (parents/children)
$densify / $fill (5.1+) — time-series gap fill
$setWindowFields (5.0+) — window functions
$search (Atlas Search)

# Operators inside aggregation
Arithmetic: $add $subtract $multiply $divide $mod $abs $ceil $floor $round
String: $concat $substr $toLower $toUpper $split $regex
Date: $year $month $dayOfMonth $hour $dateToString $dateDiff $dateAdd
Array: $size $arrayElemAt $filter $map $reduce $zip
Conditional: $cond $ifNull $switch $coalesce
Type conv: $toString $toInt $toDouble $toDate $convert

## Indexes

db.users.createIndex({ email: 1 })                              // ascending
db.users.createIndex({ email: 1 }, { unique: true })
db.users.createIndex({ created: -1 })
db.users.createIndex({ a: 1, b: -1 })                           // compound (leftmost-prefix)
db.users.createIndex({ tags: 1 })                               // multikey (array)
db.users.createIndex({ name: "text", body: "text" })            // full-text
db.users.createIndex({ loc: "2dsphere" })                       // geo
db.users.createIndex({ email: 1 }, { partialFilterExpression: { active: true } })
db.users.createIndex({ tmp: 1 }, { expireAfterSeconds: 3600 })  // TTL
db.users.createIndex({ email: 1 }, { collation: { locale: "en", strength: 2 } })
db.users.createIndex({ "$**": "text" })                         // wildcard text
db.users.createIndex({ created: 1 }, { name: "idx_created", background: true })
db.users.getIndexes()
db.users.dropIndex("idx_created")
db.users.dropIndexes()
db.users.reIndex()

## Performance / explain

db.users.find({...}).explain()
db.users.find({...}).explain("executionStats")
db.users.find({...}).explain("allPlansExecution")
db.users.aggregate([...]).explain()
# Stages: COLLSCAN (full scan!), IXSCAN, FETCH, SORT (in-mem!)
# winningPlan / executionStats.totalDocsExamined vs totalKeysExamined

db.currentOp()                                  // running ops
db.currentOp({ "secs_running": { $gt: 10 } })
db.killOp(opid)
db.serverStatus()
db.collection.dataSize() / storageSize() / totalIndexSize()
db.adminCommand({ top: 1 })

## Transactions (replica set / sharded)

const session = db.getMongo().startSession()
session.startTransaction({ readConcern: { level: "snapshot" }, writeConcern: { w: "majority" } })
try {
  session.getDatabase("db").users.updateOne({...}, {...}, { session })
  session.commitTransaction()
} catch (e) {
  session.abortTransaction()
} finally {
  session.endSession()
}

## Replica set

rs.status()
rs.conf()
rs.initiate({ _id: "rs0", members: [{ _id: 0, host: "h1:27017" }, ...] })
rs.add("h2:27017")
rs.addArb("arb:27017")           // arbiter (vote only)
rs.remove("h2:27017")
rs.stepDown(60)                  // primary → secondary for 60s
rs.printReplicationInfo()
rs.printSecondaryReplicationInfo()
rs.reconfig(cfg, { force: true })
db.isMaster() / db.hello()
# Read preference: primary, primaryPreferred, secondary, secondaryPreferred, nearest
db.users.find().readPref("secondary")
# Write concern: { w: "majority", wtimeout: 5000, j: true }
# Read concern: local, available, majority, linearizable, snapshot

## Sharded cluster

sh.status()
sh.enableSharding("db")
sh.shardCollection("db.users", { user_id: 1 })           // ranged
sh.shardCollection("db.events", { _id: "hashed" })        // hashed
sh.addShard("rs1/h1:27017,h2:27017")
sh.removeShard("rs1")
sh.moveChunk("db.users", { user_id: 1000 }, "shard0001")
sh.balancerStart() / sh.balancerStop() / sh.isBalancerRunning()
sh.getBalancerState()
sh.startBalancer() / sh.stopBalancer()
db.adminCommand({ listShards: 1 })

## Backup / restore

mongodump --uri="mongodb://..." --out=/backup
mongodump -d mydb -c users --query='{"active":true}' --out=/backup
mongodump --gzip --archive=/backup/dump.gz
mongorestore --uri="mongodb://..." /backup
mongorestore --gzip --archive=/backup/dump.gz
mongorestore --drop --nsInclude="db.*" /backup
mongoexport -d db -c users --type=json --out=users.json
mongoexport -d db -c users --type=csv --fields=_id,name,email --out=users.csv
mongoimport --uri=... --type=csv --headerline --file=users.csv -d db -c users

## Users / RBAC

use admin
db.createUser({
  user: "appuser",
  pwd: "secret",
  roles: [{ role: "readWrite", db: "mydb" }, { role: "read", db: "logs" }]
})
db.updateUser("appuser", { pwd: "newsecret" })
db.dropUser("appuser")
show users
db.getUsers()
# Built-in roles: read, readWrite, dbAdmin, userAdmin, dbOwner
# Cluster: clusterAdmin, clusterMonitor, hostManager
# Backup: backup, restore
# Super: root

db.createRole({ role: "myrole", privileges: [{ resource: { db: "mydb", collection: "" }, actions: ["find"] }], roles: [] })
db.grantRolesToUser("appuser", ["myrole"])

## Time-series collections (5.0+)

db.createCollection("metrics", {
  timeseries: { timeField: "ts", metaField: "meta", granularity: "minutes" },
  expireAfterSeconds: 2592000
})
# Optimized columnar storage for time-series

## Change streams

const cs = db.users.watch([{ $match: { operationType: "insert" } }])
while (!cs.isClosed()) { if (cs.hasNext()) printjson(cs.next()) }
# Resume token — resumeAfter / startAfter
# Use cases: CDC, real-time reactivity, cache invalidation

## Useful tools

mongostat            — live throughput
mongotop             — collection-level I/O
mongocompact         — compact storage (per-collection, locks)
mongodump/restore/export/import (above)
mongosync            — Atlas migration
Compass              — GUI
Atlas Charts         — dashboards
Studio 3T / Robo 3T  — third-party GUIs

## MongoDB vs SQL (qısa)

document model (BSON) vs relational rows
schema-flexible (mappings runtime) vs strict schema
embedded docs vs JOINs ($lookup mövcud, amma əksinə model embedding)
BSON tipləri: ObjectId, Date, Decimal128, Binary, UUID
no transactions across shards by default (4.2+ destəklənir)
write concern + read concern + read preference üçlüyü ilə tunable consistency
Atlas Search — Lucene-based, ayrı index (Elasticsearch əvəzi olaraq)
