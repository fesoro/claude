## Concepts

Broker — Kafka server instance
Cluster — bir neçə broker
Topic — named message stream
Partition — topic-in shard (ordered, append-only log)
Offset — partition daxilində mesajın pozisiyası
Replica — partition-ın nüsxəsi (leader + followers)
ISR (In-Sync Replicas) — leader ilə sinxron olan replicas
Leader election — controller tərəfindən
Producer — mesaj yazan
Consumer — mesaj oxuyan
Consumer group — paralel consume üçün qrup
Rebalance — partition-lar consumer-lar arasında yenidən bölünür
Committed offset — consumer group-un ən son oxuduğu offset
Log retention — vaxt/ölçü əsaslı silmə
Log compaction — son key dəyəri saxla
Controller — cluster metadata manager
KRaft — ZooKeeper-siz Kafka (2.8+, 3.3+ production, 4.0 default)
Quorum controller — KRaft controller
__consumer_offsets — offset-lərin saxlandığı internal topic
__transaction_state — transaction metadata
Tiered storage — köhnə segment-lər object store-da (3.6+)

## kafka-topics.sh

kafka-topics.sh --bootstrap-server localhost:9092 --list — topic siyahısı
kafka-topics.sh --bootstrap-server ... --create --topic my-topic --partitions 6 --replication-factor 3
kafka-topics.sh ... --describe --topic my-topic
kafka-topics.sh ... --alter --topic my-topic --partitions 12 (artır only)
kafka-topics.sh ... --delete --topic my-topic
kafka-topics.sh ... --config retention.ms=604800000 — config ilə
--config cleanup.policy=compact (və ya delete, compact,delete)
--config min.insync.replicas=2
--config segment.bytes=1073741824
--config max.message.bytes=1048576

## kafka-console-producer.sh

kafka-console-producer.sh --bootstrap-server localhost:9092 --topic my-topic
--property parse.key=true --property key.separator=: — key ilə yaz
--property compression.type=gzip — compression
--producer-property acks=all — durability
--producer.config producer.properties — config faylı

## kafka-console-consumer.sh

kafka-console-consumer.sh --bootstrap-server ... --topic my-topic --from-beginning
--group my-group — consumer group
--max-messages 10
--partition 0 --offset 100 — konkret offset
--property print.key=true --property print.timestamp=true --property print.headers=true
--consumer-property auto.offset.reset=earliest
--formatter kafka.tools.DefaultMessageFormatter

## kafka-consumer-groups.sh

kafka-consumer-groups.sh --bootstrap-server ... --list — qrupları göstər
kafka-consumer-groups.sh ... --describe --group my-group — lag + members
--all-groups
--state — state göstər
--members --verbose
--reset-offsets --group g --topic t --to-earliest --execute
--reset-offsets ... --to-latest / --to-offset N / --to-datetime ISO / --shift-by -100 / --to-current
--delete --group my-group — qrupu sil
--dry-run — testlə

## kafka-configs.sh

kafka-configs.sh --bootstrap-server ... --entity-type topics --entity-name my-topic --describe
--entity-type brokers --entity-name 0 --describe
--entity-type users --entity-name alice --describe
--alter --add-config 'retention.ms=86400000'
--alter --delete-config 'retention.ms'
--entity-type clients --entity-name app-1 --alter --add-config 'producer_byte_rate=1048576,consumer_byte_rate=1048576' — quota

## kafka-reassign-partitions.sh

--generate — JSON plan yarat (broker-lar arasında yenidən böl)
--execute — plan tətbiq et
--verify — statusu yoxla
--throttle 10000000 — bandwidth limit
Use case — broker add/remove, rebalance

## kafka-leader-election.sh

--election-type PREFERRED — preferred leader-ə keç
--election-type UNCLEAN — out-of-sync replica leader ola bilər (data loss riski)
--all-topic-partitions / --topic t --partition p

## kafka-acls.sh

--add --allow-principal User:alice --operation Read --topic my-topic
--list --topic my-topic
--remove --principal User:alice
Authorizer — AclAuthorizer (ZK) və ya StandardAuthorizer (KRaft)

## kafka-delegation-tokens.sh

--create --max-life-time-period N — token yarat
--describe / --expire / --renew

## kafka-dump-log.sh

kafka-dump-log.sh --files /data/kafka-logs/my-topic-0/00000000000000000000.log
--print-data-log — payload göstər
--deep-iteration — batch daxilini aç
--index-sanity-check — index-i yoxla
--offsets-decoder — __consumer_offsets decode

## kafka-verifiable-producer/consumer.sh

Test/benchmark üçün
--max-messages / --throughput / --acks / --producer.config

## kafka-producer-perf-test.sh

kafka-producer-perf-test.sh --topic t --num-records 1000000 --record-size 1000 --throughput -1 --producer-props bootstrap.servers=... acks=all
Performance ölçü üçün

## kafka-consumer-perf-test.sh

kafka-consumer-perf-test.sh --bootstrap-server ... --topic t --messages 1000000 --group g

## kafka-log-dirs.sh

--describe --bootstrap-server ... — broker log directory-ləri
Disk usage, replica placement göstərir

## Producer config (key options)

bootstrap.servers — broker list
acks — 0 / 1 / all (durability)
enable.idempotence=true — exactly-once per producer (5.0+ default)
max.in.flight.requests.per.connection — idempotence ilə <=5
retries / retry.backoff.ms
linger.ms — batch gözləmə
batch.size — batch ölçü
compression.type — none/gzip/snappy/lz4/zstd
max.request.size / buffer.memory
key.serializer / value.serializer
transactional.id — transaction üçün
delivery.timeout.ms
partitioner.class — DefaultPartitioner / RoundRobinPartitioner / UniformStickyPartitioner

## Consumer config (key options)

bootstrap.servers / group.id
key.deserializer / value.deserializer
auto.offset.reset — earliest / latest / none
enable.auto.commit=false — manual commit məsləhətdir
auto.commit.interval.ms
max.poll.records / max.poll.interval.ms
session.timeout.ms / heartbeat.interval.ms
fetch.min.bytes / fetch.max.wait.ms
isolation.level — read_committed / read_uncommitted
partition.assignment.strategy — RangeAssignor / RoundRobinAssignor / StickyAssignor / CooperativeStickyAssignor

## Broker / cluster config

broker.id / node.id (KRaft)
listeners / advertised.listeners
log.dirs — disk(s)
num.partitions — default
default.replication.factor
min.insync.replicas — ack=all ilə birlikdə durability
log.retention.hours / log.retention.bytes
log.segment.bytes
log.cleanup.policy — delete / compact
auto.create.topics.enable=false (prod)
unclean.leader.election.enable=false (data loss qoruma)
controller.quorum.voters (KRaft)
process.roles=broker,controller

## Transactions / exactly-once

producer.initTransactions()
producer.beginTransaction()
producer.send(...) + producer.sendOffsetsToTransaction(...)
producer.commitTransaction() / abortTransaction()
Consumer isolation.level=read_committed
EOS (Exactly-Once Semantics) — idempotent + transaction
Two-phase commit — __transaction_state topic

## Admin API (Java)

AdminClient.create(props)
createTopics / deleteTopics / listTopics / describeTopics
alterConfigs / describeConfigs
listConsumerGroups / describeConsumerGroups / deleteConsumerGroups
listOffsets / alterConsumerGroupOffsets
createAcls / describeAcls / deleteAcls

## Kafka Connect

connect-distributed.sh config/connect-distributed.properties
connect-standalone.sh — single worker
REST API — POST /connectors, GET /connectors/{name}/status, PUT /connectors/{name}/config
Source connectors — DB → Kafka (JDBC, Debezium, FileStream)
Sink connectors — Kafka → target (JDBC, S3, Elastic, Snowflake)
SMT (Single Message Transform) — InsertField, MaskField, Router, Cast
Converters — JSON / Avro / Protobuf (with Schema Registry)
DLQ (Dead Letter Queue) — errors.tolerance=all + errors.deadletterqueue.topic.name
Debezium — CDC connector (Postgres, MySQL, MongoDB, SQL Server)

## Kafka Streams (Java/Kotlin)

StreamsBuilder / KStream / KTable / GlobalKTable
stream.filter / map / flatMap / groupByKey / count / reduce / aggregate
join — stream-stream, stream-table, table-table
windowed — TumblingWindow, HoppingWindow, SessionWindow, SlidingWindow
State store — RocksDB + changelog topic
Processor API — low-level custom processor
KTable + GlobalKTable — materialized view
Interactive queries — state store-dan oxu
Exactly-once processing — processing.guarantee=exactly_once_v2
Repartition topic — key dəyişəndə avtomatik

## ksqlDB

CREATE STREAM ... WITH (KAFKA_TOPIC='t', VALUE_FORMAT='JSON')
CREATE TABLE — aggregation nəticə
SELECT ... FROM stream EMIT CHANGES — continuous query
INSERT INTO ... — stream-ə yaz
CREATE STREAM AS SELECT — persistent query
Pull query (key-by lookup) vs push query (EMIT CHANGES)
ksql CLI / REST API

## Schema Registry (Confluent)

POST /subjects/{name}/versions — schema register
GET /subjects/{name}/versions/latest
Compatibility — BACKWARD / FORWARD / FULL / NONE (+ TRANSITIVE)
Subjects — TopicNameStrategy / RecordNameStrategy / TopicRecordNameStrategy
Formats — Avro / Protobuf / JSON Schema
Serializer — KafkaAvroSerializer

## Security

SSL/TLS — listeners=SSL://... + ssl.* config
SASL — PLAIN / SCRAM-SHA-256 / SCRAM-SHA-512 / OAUTHBEARER / GSSAPI (Kerberos)
SASL_SSL — encryption + auth
mTLS — client cert auth
ACL — authorization (kafka-acls.sh)
Principal — User:name
Quotas — kafka-configs.sh ilə client/user limit

## Tools / ecosystem

kcat (kafkacat) — swiss-army CLI (produce/consume/list)
kcat -b broker -t topic -C — consume
kcat -b broker -t topic -P — produce
kcat -b broker -L — metadata
AKHQ / Kowl / Conduktor — web UI
Cruise Control — automated rebalancing
Burrow — consumer lag monitoring
Kafka Manager (CMAK) — legacy UI
Strimzi / Confluent Operator — Kubernetes
MirrorMaker 2 — cross-cluster replication

## Common patterns

Event sourcing — domain event → Kafka topic (source of truth)
CQRS — command → topic → projection (KTable, DB)
CDC — Debezium → Kafka → downstream
Outbox pattern — DB + topic atomic via outbox table + Debezium
Dead letter queue — failed messages
Retry topic — exponential backoff (retry-5s, retry-30s, dlq)
Saga — distributed transaction via events
Kafka as log (high throughput) vs queue (use consumer group)
Partitioning key — order guarantee per key only
At-least-once (default), at-most-once (acks=0), exactly-once (idempotent + transactional)
