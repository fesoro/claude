## Concepts

Broker — RabbitMQ server
Connection — TCP qoşulması (AMQP)
Channel — connection üzərində virtual yol (lightweight)
Vhost — namespace (resource isolation)
Exchange — routing nöqtəsi
Queue — mesaj saxlanması
Binding — exchange ↔ queue arasındakı qayda
Routing key — mesaj routing label
Producer — mesaj göndərən
Consumer — mesaj alan
Message — body + properties + headers
Acknowledgement (ack/nack/reject) — consumer-dən təsdiq
Prefetch — consumer-ə eyni anda göndəriləcək max unack mesaj
Dead Letter Exchange (DLX) — failed/expired mesajların yönəldildiyi
TTL — message / queue level expiration
Priority queue — mesaj priority (max 255)
Quorum queue — Raft-based replicated queue (modern, classic mirrored əvəzinə)
Stream queue — append-only log (3.9+, Kafka-like)
Federation — broker-lər arası exchange/queue replication
Shovel — mesaj broker-dən broker-ə köçürmə
Lazy queue — disk-first storage (large queues)
Confirm mode — publisher confirms (durability)

## rabbitmqctl (CLI admin)

rabbitmqctl status — node status
rabbitmqctl cluster_status
rabbitmqctl node_health_check (deprecated → list_queues + checks)
rabbitmqctl list_users
rabbitmqctl add_user alice secret
rabbitmqctl delete_user alice
rabbitmqctl change_password alice newsecret
rabbitmqctl set_user_tags alice administrator / monitoring / management / policymaker
rabbitmqctl list_vhosts
rabbitmqctl add_vhost myvhost
rabbitmqctl delete_vhost myvhost
rabbitmqctl set_permissions -p / alice ".*" ".*" ".*" — configure write read
rabbitmqctl list_permissions -p /
rabbitmqctl list_user_permissions alice
rabbitmqctl list_queues -p / name messages messages_ready messages_unacknowledged consumers
rabbitmqctl list_queues --vhost / --formatter=json
rabbitmqctl list_exchanges name type durable
rabbitmqctl list_bindings
rabbitmqctl list_connections name peer_host channels
rabbitmqctl list_channels
rabbitmqctl list_consumers
rabbitmqctl close_connection <pid> "reason"
rabbitmqctl purge_queue myqueue -p /
rabbitmqctl delete_queue myqueue
rabbitmqctl set_policy ha-all "^" '{"ha-mode":"all"}' -- legacy mirroring
rabbitmqctl set_policy queues-quorum "^events\." '{"queue-mode":"default"}' --apply-to queues
rabbitmqctl list_policies
rabbitmqctl clear_policy ha-all
rabbitmqctl set_global_parameter cluster_name "prod-rmq"
rabbitmqctl eval 'rabbit:status().' — Erlang runtime expression
rabbitmqctl reset / force_reset — node reset (DİQQƏT)
rabbitmqctl join_cluster rabbit@node1 — cluster-ə qoşul
rabbitmqctl forget_cluster_node rabbit@node1
rabbitmqctl set_cluster_name prod
rabbitmqctl rebalance_queues — leader balance (quorum)
rabbitmqctl list_quorum_queues
rabbitmqctl list_streams

## rabbitmq-plugins

rabbitmq-plugins list
rabbitmq-plugins enable rabbitmq_management
rabbitmq-plugins enable rabbitmq_management rabbitmq_prometheus rabbitmq_shovel rabbitmq_federation rabbitmq_delayed_message_exchange
rabbitmq-plugins disable rabbitmq_management
rabbitmq-plugins set ... — fixed list

## rabbitmq-diagnostics

rabbitmq-diagnostics ping
rabbitmq-diagnostics check_running
rabbitmq-diagnostics check_local_alarms
rabbitmq-diagnostics check_port_connectivity
rabbitmq-diagnostics check_virtual_hosts
rabbitmq-diagnostics check_node_is_mirror_sync_critical
rabbitmq-diagnostics observer — ekran-əsaslı izləmə
rabbitmq-diagnostics memory_breakdown
rabbitmq-diagnostics environment
rabbitmq-diagnostics listeners
rabbitmq-diagnostics consume_event_stream

## rabbitmqadmin (HTTP API CLI)

rabbitmqadmin -u admin -p pass list queues
rabbitmqadmin -u admin -p pass list exchanges
rabbitmqadmin -u admin -p pass list bindings
rabbitmqadmin declare exchange name=ex type=topic durable=true
rabbitmqadmin declare queue name=q durable=true arguments='{"x-queue-type":"quorum"}'
rabbitmqadmin declare binding source=ex destination=q routing_key="orders.*"
rabbitmqadmin publish exchange=ex routing_key="orders.created" payload='{"id":1}'
rabbitmqadmin get queue=q count=10 ackmode=ack_requeue_false
rabbitmqadmin purge queue name=q
rabbitmqadmin delete queue name=q
rabbitmqadmin export rabbit.json — config export
rabbitmqadmin import rabbit.json

## Management HTTP API

GET /api/overview — broker stats
GET /api/queues / /api/queues/{vhost}/{name}
GET /api/exchanges
GET /api/bindings
GET /api/connections / /api/channels
PUT /api/queues/{vhost}/{name} — declare
DELETE /api/queues/{vhost}/{name}
POST /api/queues/{vhost}/{name}/get — peek messages
POST /api/exchanges/{vhost}/{name}/publish — publish
GET /api/healthchecks/node
GET /api/aliveness-test/{vhost} — declare/publish/consume test
curl -u admin:pass http://localhost:15672/api/queues | jq

## Exchange types

direct — exact routing key match
topic — pattern match (*=1 word, #=0+ words)
fanout — broadcast (routing key ignored)
headers — header-based routing (rare)
default exchange (empty name) — direct, queue name = routing key
x-delayed-message — plugin (delayed delivery)
consistent-hash exchange — plugin (sharding)

## Queue types & arguments

Classic — single-node, mirrored (deprecated)
Quorum — Raft replicated, durable, high availability (default-go-to)
Stream — append-only log, partition-like, plugin-bundled (3.9+)

declare arguments:
  x-queue-type: classic / quorum / stream
  x-message-ttl: 60000 — ms
  x-expires: 1800000 — queue idle TTL ms
  x-max-length: 10000 — max ready messages
  x-max-length-bytes: 1048576
  x-overflow: reject-publish / drop-head / reject-publish-dlx
  x-dead-letter-exchange: dlx
  x-dead-letter-routing-key: failed
  x-max-priority: 10 — classic only
  x-single-active-consumer: true
  x-queue-master-locator: client-local / min-masters
  x-quorum-initial-group-size: 3 — quorum
  x-stream-max-segment-size-bytes: 524288000

## AMQP 0-9-1 frames (gist)

Connection.Open / Channel.Open
Exchange.Declare / Queue.Declare / Queue.Bind
Basic.Publish (mandatory, immediate flags)
Basic.Consume / Basic.Get
Basic.Ack / Basic.Nack / Basic.Reject
Basic.Qos prefetch_count
Confirm.Select — publisher confirms
Tx.Select / Tx.Commit — channel transactions (slow, prefer confirms)

## Message properties

content-type, content-encoding
delivery-mode: 1 (transient) / 2 (persistent)
priority (0-255 if x-max-priority)
correlation-id (RPC reply matching)
reply-to (RPC reply queue)
expiration — per-message TTL ms (string!)
message-id, timestamp, type
user-id, app-id
headers — custom dict (used by headers exchange)
cluster-id

## Publisher confirms (durability)

channel.confirmSelect() / channel.confirm_select()
basic.publish + ack/nack callback
publisher persistent message: deliveryMode=2 + durable queue + confirmSelect
mandatory flag — unroutable mesaj basic.return ilə geri gəlir
Transactional channel — slow, prefer confirms

## Consumer best practices

basic.qos(prefetch_count=N) — flow control (default unlimited!)
manual ack mode (auto_ack=false)
ack on success, nack(requeue=false) on permanent failure → DLX
nack(requeue=true) yalnız transient errors üçün (loop riski)
Idempotent consumer (deduplication + idempotency key)
Consumer cancellation notification (cancelOk callback)
Heartbeat (default 60s) — broker tracks liveness
Connection recovery — Java/.NET clients auto-recover

## Dead Letter Exchange (DLX)

Trigger:
  - basic.reject / nack with requeue=false
  - message TTL expires
  - queue length exceeded (x-overflow)
Setup:
  declare exchange dlx (type=fanout/topic)
  declare queue q with x-dead-letter-exchange=dlx, x-dead-letter-routing-key=failed
  declare queue dlq, bind dlx → dlq
DLX-də x-death header axıdır (count, reason, time, exchange, routing-keys)

## Delayed messages

rabbitmq_delayed_message_exchange plugin
declare exchange type=x-delayed-message arguments={x-delayed-type=direct}
publish with header x-delay=ms

Alternative: TTL + DLX pattern (per-queue TTL → DLX → main queue)

## Streams

rabbitmq-streams — CLI
declare queue with x-queue-type=stream
Append-only, replayable, multi-consumer (offset-based, Kafka-like)
RabbitMQ Streams plugin protocol (port 5552) — high throughput
Native consumer offset tracking
Use cases: event sourcing, analytics, log streams

## Federation / Shovel

Federation:
  rabbitmqctl set_parameter federation-upstream upstream1 '{"uri":"amqp://other-broker"}'
  rabbitmqctl set_policy fed "^events\." '{"federation-upstream":"upstream1"}'
Shovel (one-way mirror):
  rabbitmqctl set_parameter shovel my-shovel '{"src-uri":"...","src-queue":"q","dest-uri":"...","dest-exchange":"ex"}'

## Clustering / HA

rabbitmqctl join_cluster rabbit@node1
3+ node odd cluster
Quorum queues — CP, Raft (default for HA)
Mirrored classic queues — deprecated 3.10+, removed 4.0
Network partition handling: cluster_partition_handling = pause_minority / autoheal / ignore
HAProxy / Kubernetes Service for load balancing connections
Single Active Consumer (per queue ordering)

## Security

TLS — listeners.ssl.default = 5671 + ssl_options
SASL — PLAIN (default), EXTERNAL (mTLS), AMQPLAIN
User tags: administrator, monitoring, management, policymaker, none
Permissions: configure / write / read regex per vhost
LDAP plugin (rabbitmq_auth_backend_ldap)
OAuth2 plugin (rabbitmq_auth_backend_oauth2)
Disable guest/guest from non-localhost (default behavior)

## Monitoring

rabbitmq_prometheus plugin — /metrics endpoint
Management UI: http://localhost:15672 (port)
Key metrics: messages_ready, messages_unacknowledged, publish_rate, deliver_rate, consumer_utilisation, memory, fd_used, sockets_used, disk_free
Alarms: memory (vm_memory_high_watermark), disk (disk_free_limit)
rabbitmq_top plugin — process-level

## Configuration files

/etc/rabbitmq/rabbitmq.conf — main (sysctl-like, modern)
/etc/rabbitmq/advanced.config — Erlang term (legacy fallback)
/etc/rabbitmq/enabled_plugins
/etc/rabbitmq/rabbitmq-env.conf — env vars

Common settings:
  default_user / default_pass / default_vhost
  loopback_users.guest = false (require explicit IP)
  vm_memory_high_watermark.relative = 0.6
  disk_free_limit.absolute = 2GB
  channel_max = 2047
  heartbeat = 60
  cluster_formation.peer_discovery_backend = classic_config / k8s / consul / etcd

## Common patterns

Work queue — single queue, multiple consumers (round-robin)
Pub/Sub — fanout exchange, ephemeral queues per consumer
Routing — direct exchange, routing key = "error", "info"
Topic — topic exchange, "logs.*.error", "orders.#"
Headers — headers exchange, x-match=all/any
RPC — reply-to queue + correlation-id
Sharding — consistent-hash exchange plugin
Delayed — x-delayed-message plugin or TTL+DLX
Retry/Backoff — retry queue with TTL → DLX → main (exp backoff via multiple queues)
Idempotent consumer — DB unique constraint on message_id
Saga — choreography via topic exchange events
Outbox — DB outbox table → publisher worker → RabbitMQ

## RabbitMQ vs Kafka (qısa)

RabbitMQ: smart broker / dumb consumer, push, ack-based, low latency, complex routing
Kafka: dumb broker / smart consumer, pull, offset-based, high throughput, log-based
RabbitMQ üçün ideal: task queues, RPC, complex routing, low-volume + low-latency
Kafka üçün ideal: event streams, replay, partition ordering, analytics, high throughput
