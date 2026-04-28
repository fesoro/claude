# Reactive Architecture / Actor Model (Architect)

**Reactive Manifesto** prinsiplərinə əsaslanan arxitektura: Responsive, Resilient, Elastic, Message-Driven.
**Actor Model**-də hər "actor" müstəqil hesablama vahididir — öz state-inə sahibdir, yalnız mesajlarla kommunikasiya edir.

**Əsas anlayışlar:**
- **Actor** — Müstəqil hesablama vahidi: öz state-i + mailbox-ı var
- **Message Passing** — Aktorlar yalnız async mesajlarla ünsiyyət qurur
- **Supervision** — Parent actor child actor-un xətalarını idarə edir
- **Location Transparency** — Actor lokal vs remote fərqi yoxdur
- **Backpressure** — Producer consumer-dan sürətli işləyəndə yükü tənzimləmək
- **Non-Blocking I/O** — Thread bloklanmır, event loop istifadə olunur

**Nə zaman lazımdır:**
- Yüksək concurrency: 100K+ eyni anda connection
- Real-time streaming: ticker, chat, live dashboard
- Distributed state: game state, auction, trading
- Fault-tolerant long-running processes

---

## Golang (Actor-like with goroutines + channels)

```
project/
├── cmd/
│   └── main.go
│
├── internal/
│   ├── actor/                                 # Actor-like primitives
│   │   ├── actor.go                          # Base actor interface
│   │   ├── mailbox.go                        # Buffered channel mailbox
│   │   ├── supervisor.go                     # Error handling + restart
│   │   └── registry.go                       # Actor registry (name → chan)
│   │
│   ├── actors/                               # Concrete actors
│   │   ├── auction/
│   │   │   ├── auction_actor.go              # Manages one auction's state
│   │   │   │   // Messages: PlaceBid, CloseAuction, GetStatus
│   │   │   │   // State: currentBid, highestBidder, participants
│   │   │   ├── bid_validator_actor.go        # Validates bids
│   │   │   └── notification_actor.go         # Sends notifications
│   │   │
│   │   ├── chat/
│   │   │   ├── room_actor.go                 # Chat room state
│   │   │   │   // Messages: JoinRoom, LeaveRoom, SendMessage
│   │   │   └── session_actor.go              # Per-user WebSocket session
│   │   │
│   │   └── trading/
│   │       ├── order_book_actor.go            # Order book state
│   │       │   // Messages: PlaceOrder, CancelOrder, GetBook
│   │       └── matching_engine_actor.go       # Order matching
│   │
│   ├── stream/                               # Reactive streams
│   │   ├── pipeline.go                       # stream processing pipeline
│   │   ├── backpressure.go                   # Bounded channel backpressure
│   │   └── operators/
│   │       ├── map.go
│   │       ├── filter.go
│   │       ├── batch.go                      # Batch N items
│   │       └── throttle.go
│   │
│   ├── websocket/                            # Non-blocking WebSocket
│   │   ├── hub.go                            # Central hub: manages connections
│   │   ├── client.go                         # Per-connection goroutine
│   │   └── handler.go
│   │
│   └── config/
│       └── config.go
│
└── go.mod
```

---

## Java (Akka Actors / Project Loom + Virtual Threads)

```
src/main/java/com/example/reactive/
│
├── actor/                                    # Akka-style actors
│   ├── AuctionActor.java                    # Typed actor
│   │   // Behavior<AuctionCommand> — handles PlaceBid, Close, GetStatus
│   │   // State: immutable AuctionState
│   │   // On failure: restart behavior
│   ├── AuctionCommand.java                  // Sealed interface: PlaceBid | Close | ...
│   ├── AuctionState.java                    // Immutable state
│   │
│   ├── chat/
│   │   ├── ChatRoomActor.java               // Manages room participants
│   │   └── SessionActor.java                // Per-user session
│   │
│   └── supervision/
│       └── SupervisionStrategy.java         // Restart / Stop / Escalate
│
├── reactive/                                // Spring WebFlux reactive
│   ├── AuctionHandler.java                 // Reactive HTTP handler
│   │   // Returns Mono<Response> / Flux<Response>
│   ├── AuctionRouter.java                   // RouterFunction-based routing
│   └── AuctionRepository.java              // R2DBC (reactive PostgreSQL)
│
├── streaming/                               // Reactive streams
│   ├── BidStreamProcessor.java
│   │   // Flux.fromPublisher() → filter → map → buffer → sink
│   ├── BackpressureConfig.java
│   └── MetricsPipeline.java
│
├── websocket/                               // Reactive WebSocket
│   ├── AuctionWebSocketHandler.java
│   │   // Flux<WebSocketMessage> for outbound
│   │   // session.receive() → Flux for inbound
│   └── ChatWebSocketHandler.java
│
└── config/
    └── ReactiveConfig.java                  // WebFlux, R2DBC config
```

---

## Laravel (Async with Octane + Swoole)

```
project/
├── app/
│   ├── Actor/                               # Pseudo-actor with Swoole coroutines
│   │   ├── ActorInterface.php
│   │   ├── AuctionActor.php                 # Coroutine-based actor
│   │   │   // Swoole\Coroutine\Channel as mailbox
│   │   │   // Co::create() for concurrent execution
│   │   └── ActorRegistry.php               # Maps name → channel
│   │
│   ├── Reactive/
│   │   ├── Stream/
│   │   │   ├── BidStream.php               # Generator-based stream
│   │   │   └── Pipeline.php               # Chain of transformations
│   │   └── Backpressure/
│   │       └── BoundedChannel.php          # Limit buffer size
│   │
│   ├── WebSocket/
│   │   ├── AuctionWebSocketController.php  # Octane WebSocket handler
│   │   └── ChatWebSocketController.php
│   │
│   └── Http/Controllers/
│       └── AuctionController.php
│
├── config/
│   └── octane.php                          # Swoole server config
└── Dockerfile
    # php artisan octane:start --server=swoole
```

---

## Actor Nümunəsi (Golang)

```go
// auction_actor.go — Actor state machine

type AuctionActor struct {
    mailbox  chan AuctionMsg         // Buffered channel = mailbox
    state    AuctionState
    children map[string]*NotifyActor // Child actors
}

type AuctionMsg interface{ isAuctionMsg() }

type PlaceBid struct {
    BidderID string
    Amount   float64
}

type GetStatus struct {
    Reply chan AuctionState
}

func (a *AuctionActor) Start() {
    go func() {
        for msg := range a.mailbox {
            switch m := msg.(type) {
            case PlaceBid:
                if m.Amount > a.state.CurrentBid {
                    a.state.CurrentBid = m.Amount
                    a.state.HighestBidder = m.BidderID
                    // Notify child actors
                    for _, child := range a.children {
                        child.Send(BidPlaced{Bidder: m.BidderID, Amount: m.Amount})
                    }
                }
            case GetStatus:
                m.Reply <- a.state
            }
        }
    }()
}

func (a *AuctionActor) Send(msg AuctionMsg) {
    a.mailbox <- msg  // Non-blocking send (buffered channel)
}
```
