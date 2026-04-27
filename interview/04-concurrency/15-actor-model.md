# Actor Model (Architect ⭐⭐⭐⭐⭐)

## İcmal
Actor Model — state-i yalnız özü idarə edən, mesajlaşma ilə kommunikasiya edən, paylanmış hesablamanın fundamental abstraksiyasıdır. "Shared nothing" — hər actor öz state-ini saxlayır, heç bir digəri birbaşa daxil ola bilməz. 1973-ildə Carl Hewitt tərəfindən irəli sürülüb; Erlang/OTP-nin uğurunun əsasında dayanır, Akka (Java/Scala), Elixir/Phoenix, Microsoft Orleans — hamısı bu modeli implements edir. Architect interview-larda distributed system dizaynında niyə actor model seçildiyi soruşulur.

## Niyə Vacibdir
Lock-based concurrency — paylanmış sistemdə mümkün deyil: fərqli maşınlar arasında lock ola bilməz. Actor Model bu problemi aradan qaldırır: mesaj göndərmək şəbəkə packet göndərməkdən fərqlənmir. İnterviewer bu sualla sizin "shared memory" sınırlarını, Actor-un isolation qarantiyalarını, supervision hierarchy-ni, Erlang-ın 99.9999999% uptime iddiasının arxasını, və real distributed system dizaynında Actor-u nə vaxt seçəcəyinizi bildiyinizi yoxlayır.

## Əsas Anlayışlar

- **Actor:** State + Behavior + Mailbox — yeganə kommunikasiya mesajla
- **Mailbox:** Actor-un mesaj növbəsi — mesajlar sıraya girir, ardıcıl işlənir
- **Asynchronous Messaging:** Actor mesaj göndərir, cavab gözləmir — fire-and-forget
- **"Let It Crash":** Erlang/OTP fəlsəfəsi — xəta yarandıqda recover etməyə çalışma, restart et
- **Supervision Tree:** Supervisor actor uşaq actor-ların lifecycle-ını idarə edir
- **Supervision Strategy:**
  - `OneForOne` — uğursuz child restart edilir, digərləri toxunulmur
  - `AllForOne` — birisi fail olarsa hamısı restart edilir
- **Actor Address / PID:** Actor-a istinad — location transparent (local vs remote fərq yoxdur)
- **Location Transparency:** Eyni API ilə local actor ya da remote actor — şəbəkə şəffafdır
- **Cluster Sharding (Akka):** Actor-ları distributed şəkildə distribute etmək — key-based routing
- **Event Sourcing + Actor:** Actor-un bütün mesajları log — state-i istənilən anda rekonstrüksiya etmək
- **Dead Letter Mailbox:** Çatdırıla bilməyən mesajlar — actor artıq yoxdur
- **Back-pressure (Akka Streams):** Reactive Streams + Actor — stream backpressure
- **Persistent Actor (Akka Persistence):** Actor state-i persist edir — crash sonrası recover
- **Virtual Actors (Orleans):** Actor yaşayır/yatır — activation/deactivation avtomatik; Microsoft Orleans
- **Fiber/Goroutine vs Actor:** Fiber/goroutine shared memory paylaşa bilər; actor heç vaxt

## Praktik Baxış

**Interview-da yanaşma:**
- "Niyə actor model?" — Distributed system, shared memory mümkün deyil, isolation lazım
- Supervision tree-ni çizin — "Let it crash" ideologiyasını izah edin
- Akka Cluster Sharding ilə distributed actor dizaynı göstərin

**Follow-up suallar:**
- "Actor model vs reactive programming?" — Actor model-in kommunikasiyası mesajdır; reactive streams backpressure əlavə edir; ortaq: async, non-blocking
- "Deadlock Actor Model-da mümkündürmü?" — Bəli: A, B-dən cavab gözləyir; B, A-dan — iki actor bir-birini block edir
- "Erlang 99.9999999% uptime necə? (9 nine)" — Supervision tree + hot code reload + isolated processes

**Ümumi səhvlər:**
- "Actor = thread" düşünmək — Actor thread agnostikdir
- Actor-lar arasında state paylaşmağa cəhd etmək
- "Let it crash" yerinə hər xətanı handle etməyə çalışmaq

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Supervision tree-nin niyə "error-kernel" pattern-i olduğunu izah etmək
- Virtual actor (Orleans) vs persistent actor (Akka) fərqini bilmək
- Actor model-in real production use case-ini bilmək: Discord, WhatsApp, Riak

## Nümunələr

### Tipik Interview Sualı
"Yüksək məhsuldarlıqlı real-time chat sistemi dizayn edin. Actor Model istifadə etsəniz nə dəyişər?"

### Güclü Cavab
Klassik thread-based chat: hər connection bir thread — 1M connection = 1M thread, yəni ~8TB RAM. Actor model ilə: hər chat room bir actor, hər user connection bir actor. Room actor öz member list-ini saxlayır, broadcast mesajları işləyir — shared memory, lock yoxdur. Horizontal scaling: Akka Cluster Sharding ilə room actor-lar node-lar arasında distribute edilir; eyni room həmişə eyni node-a route edilir. Crash: "Let it crash" — room actor xəta yaratdıqda supervisor onu restart edir, son state Persistent Actor ilə recover edilir. Discord bu arxitekturada Erlang + Elixir istifadə edir, 15M concurrent user dəstəkləyir. Dezavantaj: debugging çətin, mesaj sırasının qarantiyası yalnız eyni actor üçün keçərlidir, distributed deadlock mümkündür.

### Kod Nümunəsi
```java
// Akka Typed — Java Actor Model
import akka.actor.typed.*;
import akka.actor.typed.javadsl.*;
import java.util.*;

// === Mesajlar (Command) ===
sealed interface ChatRoomCommand {}
record JoinRoom(String userId, ActorRef<UserCommand> userRef) implements ChatRoomCommand {}
record LeaveRoom(String userId) implements ChatRoomCommand {}
record SendMessage(String userId, String text) implements ChatRoomCommand {}

sealed interface UserCommand {}
record ReceiveMessage(String from, String text) implements UserCommand {}
record RoomJoined(String roomId) implements UserCommand {}

// === ChatRoom Actor ===
public class ChatRoomActor extends AbstractBehavior<ChatRoomCommand> {

    private final String roomId;
    // STATE — yalnız bu actor-un öz state-i, heç kim birbaşa daxil ola bilməz
    private final Map<String, ActorRef<UserCommand>> members = new HashMap<>();

    private ChatRoomActor(ActorContext<ChatRoomCommand> context, String roomId) {
        super(context);
        this.roomId = roomId;
    }

    public static Behavior<ChatRoomCommand> create(String roomId) {
        return Behaviors.setup(ctx -> new ChatRoomActor(ctx, roomId));
    }

    @Override
    public Receive<ChatRoomCommand> createReceive() {
        return newReceiveBuilder()
            .onMessage(JoinRoom.class, this::onJoin)
            .onMessage(LeaveRoom.class, this::onLeave)
            .onMessage(SendMessage.class, this::onMessage)
            .build();
    }

    private Behavior<ChatRoomCommand> onJoin(JoinRoom cmd) {
        members.put(cmd.userId(), cmd.userRef());
        // User-a qoşulma təsdiqi göndər
        cmd.userRef().tell(new RoomJoined(roomId));
        // Broadcast: "X qoşuldu"
        broadcast(cmd.userId(), cmd.userId() + " joined the room");
        getContext().getLog().info("User {} joined room {}", cmd.userId(), roomId);
        return this;
    }

    private Behavior<ChatRoomCommand> onLeave(LeaveRoom cmd) {
        members.remove(cmd.userId());
        broadcast("system", cmd.userId() + " left the room");
        return this;
    }

    private Behavior<ChatRoomCommand> onMessage(SendMessage cmd) {
        broadcast(cmd.userId(), cmd.text());
        return this;
    }

    private void broadcast(String from, String text) {
        // Bütün member-lara mesaj göndər — async, non-blocking
        members.forEach((userId, ref) -> ref.tell(new ReceiveMessage(from, text)));
    }
}

// === Supervision ===
public class ChatSystemActor extends AbstractBehavior<ChatRoomCommand> {

    public static Behavior<ChatRoomCommand> create() {
        return Behaviors.supervise(
            Behaviors.setup(ctx -> {
                // ChatRoom-lar child actor olaraq yaradılır
                // Supervisor onFailure strategiyasını idarə edir
                return new ChatSystemActor(ctx);
            })
        )
        .onFailure(
            Exception.class,
            SupervisorStrategy.restart()  // Crash → restart
                .withLimit(3, Duration.ofMinutes(1)) // 1 dəqiqədə 3 restart, sonra eskalasiya
        );
    }

    private ChatSystemActor(ActorContext<ChatRoomCommand> ctx) {
        super(ctx);
    }

    @Override
    public Receive<ChatRoomCommand> createReceive() {
        return newReceiveBuilder().build();
    }
}

// === Main ===
class ChatApp {
    public static void main(String[] args) {
        ActorSystem<ChatRoomCommand> system =
            ActorSystem.create(ChatRoomActor.create("general"), "ChatSystem");

        // Mesaj göndər — async, fire and forget
        system.tell(new JoinRoom("alice", null)); // Sadələşdirilmiş
        system.tell(new SendMessage("alice", "Hello, world!"));
        system.tell(new LeaveRoom("alice"));

        system.terminate();
    }
}
```

```elixir
# Erlang/Elixir: OTP GenServer — Actor Model-in klassik implementasiyası
defmodule ChatRoom do
  use GenServer  # OTP Actor

  # State struct
  defstruct members: %{}, messages: []

  # === Public API ===
  def start_link(room_id) do
    GenServer.start_link(__MODULE__, room_id, name: via_tuple(room_id))
  end

  def join(room_id, user_id, user_pid) do
    GenServer.cast(via_tuple(room_id), {:join, user_id, user_pid})
    # cast — async, cavab gözlənilmir
  end

  def send_message(room_id, user_id, text) do
    GenServer.cast(via_tuple(room_id), {:message, user_id, text})
  end

  def get_members(room_id) do
    GenServer.call(via_tuple(room_id), :get_members)
    # call — sync, cavab gözlənilir (timeout: 5000ms)
  end

  # === Callbacks (State Machine) ===
  @impl true
  def init(room_id) do
    IO.puts("Room #{room_id} started")
    {:ok, %__MODULE__{}}
  end

  @impl true
  def handle_cast({:join, user_id, user_pid}, state) do
    new_members = Map.put(state.members, user_id, user_pid)
    broadcast(new_members, {:room_event, "#{user_id} joined"})
    {:noreply, %{state | members: new_members}}
  end

  @impl true
  def handle_cast({:message, user_id, text}, state) do
    broadcast(state.members, {:new_message, user_id, text})
    new_messages = [{user_id, text} | state.messages] |> Enum.take(100)
    {:noreply, %{state | messages: new_messages}}
  end

  @impl true
  def handle_call(:get_members, _from, state) do
    {:reply, Map.keys(state.members), state}
  end

  # === "Let It Crash" — xəta handle etmirik, supervisor restart edir ===
  # def handle_info(:something_weird, state) do
  #   raise "Unexpected!"  # Supervisor bizi restart edəcək
  # end

  defp broadcast(members, message) do
    Enum.each(members, fn {_id, pid} -> send(pid, message) end)
  end

  defp via_tuple(room_id), do: {:via, Registry, {ChatRegistry, room_id}}
end

# === Supervision Tree ===
defmodule ChatApp.Supervisor do
  use Supervisor

  def start_link(_) do
    Supervisor.start_link(__MODULE__, [], name: __MODULE__)
  end

  @impl true
  def init(_) do
    children = [
      {Registry, keys: :unique, name: ChatRegistry},
      {DynamicSupervisor, name: ChatRoomSupervisor, strategy: :one_for_one}
      # one_for_one: uğursuz child restart edilir, digərləri davam edir
    ]
    Supervisor.init(children, strategy: :one_for_one)
  end
end

# Dynamic room yaratmaq
defmodule ChatApp do
  def create_room(room_id) do
    DynamicSupervisor.start_child(
      ChatRoomSupervisor,
      {ChatRoom, room_id}
    )
  end
end
```

```go
// Go: Goroutine + channel ilə Actor pattern
package main

import (
    "fmt"
    "sync"
)

// Actor — goroutine + mailbox channel
type Actor struct {
    mailbox chan Message
    state   map[string]interface{}
}

type Message struct {
    Type    string
    Payload interface{}
    Reply   chan interface{}
}

func NewActor(bufferSize int) *Actor {
    a := &Actor{
        mailbox: make(chan Message, bufferSize),
        state:   make(map[string]interface{}),
    }
    go a.run() // Actor = goroutine
    return a
}

func (a *Actor) run() {
    for msg := range a.mailbox {
        switch msg.Type {
        case "set":
            pair := msg.Payload.([]interface{})
            a.state[pair[0].(string)] = pair[1]
        case "get":
            key := msg.Payload.(string)
            msg.Reply <- a.state[key]
        case "stop":
            close(a.mailbox)
            return
        }
    }
}

// Fire and forget
func (a *Actor) Tell(msgType string, payload interface{}) {
    a.mailbox <- Message{Type: msgType, Payload: payload}
}

// Request-reply
func (a *Actor) Ask(msgType string, payload interface{}) interface{} {
    reply := make(chan interface{}, 1)
    a.mailbox <- Message{Type: msgType, Payload: payload, Reply: reply}
    return <-reply
}

func main() {
    actor := NewActor(100)

    var wg sync.WaitGroup
    // Paralel yazma — Actor serializes all messages
    for i := 0; i < 100; i++ {
        wg.Add(1)
        go func(n int) {
            defer wg.Done()
            actor.Tell("set", []interface{}{fmt.Sprintf("key%d", n), n})
        }(i)
    }
    wg.Wait()

    // Oxuma
    val := actor.Ask("get", "key42")
    fmt.Println("key42:", val) // 42 — race condition yoxdur

    actor.Tell("stop", nil)
}
```

## Praktik Tapşırıqlar

- Akka Typed ilə bank account actor yazın: deposit, withdraw, getBalance mesajları; overdraft-ı reject edin
- Supervision tree yaradın: parent actor child-ı izləyir, crash → OneForOne restart
- Elixir GenServer ilə sadə key-value store yazın, `mix test` ilə test edin
- Go-da goroutine + channel ilə actor pattern: concurrent counter — lock olmadan
- Actor Model-in deadlock ssenarini göstərin: A, B-ni ask edir; B, A-yı ask edir

## Əlaqəli Mövzular
- `08-producer-consumer.md` — Actor mailbox = bounded queue
- `11-memory-models.md` — "Shared nothing" — Actor model-in memory qarantiyası
- `14-reactive-programming.md` — Akka Streams = Actor + Reactive Streams
- `13-green-threads.md` — Actor goroutine/fiber üzərindədir
