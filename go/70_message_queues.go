package main

import (
	"fmt"
)

// ===============================================
// MESSAGE QUEUES (MESAJ NOVBELERI)
// ===============================================

// Message queue - servisler arasi asinxron kommunikasiya ucundur.
// Gonderici mesaji novbeye qoyur, alici oz sureti ile oxuyur.
// Bu decoupling (ayrilma) yaradir ve sistemi daha dayaniqli edir.

func main() {
	fmt.Println("=== MESSAGE QUEUES ===")

	// -------------------------------------------
	// 1. Kafka producer/consumer
	// -------------------------------------------
	fmt.Println("\n--- 1. Apache Kafka ---")
	fmt.Println("Kafka - yuksek performansli, paylanmis mesaj axini platformasidir.")
	fmt.Println("Topic, Partition, Consumer Group, Offset anlayislari var.")
	fmt.Println(`
  // go get github.com/segmentio/kafka-go

  import "github.com/segmentio/kafka-go"

  // ---- PRODUCER (mesaj gondermek) ----
  func kafkaYazici() error {
      yazici := &kafka.Writer{
          Addr:     kafka.TCP("localhost:9092"),
          Topic:    "sifarisler",
          Balancer: &kafka.LeastBytes{}, // partition secimi
      }
      defer yazici.Close()

      mesajlar := []kafka.Message{
          {
              Key:   []byte("sifaris-1"),
              Value: []byte(` + "`" + `{"id":1,"mehsul":"laptop","miqdar":1}` + "`" + `),
          },
          {
              Key:   []byte("sifaris-2"),
              Value: []byte(` + "`" + `{"id":2,"mehsul":"telefon","miqdar":2}` + "`" + `),
          },
      }

      err := yazici.WriteMessages(context.Background(), mesajlar...)
      if err != nil {
          return fmt.Errorf("mesaj yazma ugursuz: %w", err)
      }
      fmt.Println("Mesajlar gonderildi")
      return nil
  }

  // ---- CONSUMER (mesaj oxumaq) ----
  func kafkaOxucu(ctx context.Context) error {
      oxucu := kafka.NewReader(kafka.ReaderConfig{
          Brokers:  []string{"localhost:9092"},
          Topic:    "sifarisler",
          GroupID:  "sifaris-emali-grupu", // consumer group
          MinBytes: 10e3,                  // 10KB
          MaxBytes: 10e6,                  // 10MB
      })
      defer oxucu.Close()

      for {
          mesaj, err := oxucu.ReadMessage(ctx)
          if err != nil {
              if ctx.Err() != nil {
                  return nil // context legv edildi
              }
              return fmt.Errorf("oxuma ugursuz: %w", err)
          }

          fmt.Printf("Topic: %s, Partition: %d, Offset: %d\n",
              mesaj.Topic, mesaj.Partition, mesaj.Offset)
          fmt.Printf("Key: %s, Value: %s\n", mesaj.Key, mesaj.Value)

          // Mesaji emal et...
          // ReadMessage avtomatik commit edir (GroupID varsa)
      }
  }

  // Consumer Group: eyni group-da olan consumer-ler partition-lari paylasir
  // Meselen: 3 partition, 3 consumer = her biri 1 partition oxuyur
  // Eger 1 consumer duserse, onun partition-i diger consumer-e verilir`)

	// -------------------------------------------
	// 2. RabbitMQ
	// -------------------------------------------
	fmt.Println("\n--- 2. RabbitMQ ---")
	fmt.Println("RabbitMQ - AMQP protokolu ile isleyen mesaj brokeridir.")
	fmt.Println("Exchange, Queue, Binding, Routing Key anlayislari var.")
	fmt.Println(`
  // go get github.com/rabbitmq/amqp091-go

  import amqp "github.com/rabbitmq/amqp091-go"

  // Baglanti qurmaq
  func rabbitmqBaglanti() (*amqp.Connection, *amqp.Channel, error) {
      conn, err := amqp.Dial("amqp://guest:guest@localhost:5672/")
      if err != nil {
          return nil, nil, fmt.Errorf("baglanti ugursuz: %w", err)
      }

      ch, err := conn.Channel()
      if err != nil {
          conn.Close()
          return nil, nil, fmt.Errorf("kanal ugursuz: %w", err)
      }

      return conn, ch, nil
  }

  // Mesaj gondermek (Publisher)
  func rabbitmqGonder(ch *amqp.Channel, novbe, mesaj string) error {
      // Novbe yaradin (eger yoxdursa)
      q, err := ch.QueueDeclare(
          novbe, // ad
          true,  // durable (server restart-dan salamat qalir)
          false, // auto-delete
          false, // exclusive
          false, // no-wait
          nil,   // arguments
      )
      if err != nil {
          return err
      }

      return ch.PublishWithContext(context.Background(),
          "",     // exchange (default)
          q.Name, // routing key
          false,  // mandatory
          false,  // immediate
          amqp.Publishing{
              DeliveryMode: amqp.Persistent, // mesaj disk-e yazilir
              ContentType:  "application/json",
              Body:         []byte(mesaj),
          },
      )
  }

  // Mesaj almaq (Consumer)
  func rabbitmqAl(ch *amqp.Channel, novbe string) error {
      mesajlar, err := ch.Consume(
          novbe, // queue
          "",    // consumer tag
          false, // auto-ack (false = manual acknowledge)
          false, // exclusive
          false, // no-local
          false, // no-wait
          nil,
      )
      if err != nil {
          return err
      }

      for mesaj := range mesajlar {
          fmt.Printf("Alinan: %s\n", mesaj.Body)

          // Mesaji emal et...

          mesaj.Ack(false) // mesaji tesdiqle (queue-dan silinir)
          // mesaj.Nack(false, true) // ret et ve yeniden novbeye qoy
      }
      return nil
  }`)

	// -------------------------------------------
	// 3. NATS
	// -------------------------------------------
	fmt.Println("\n--- 3. NATS ---")
	fmt.Println("NATS - sadə, yuksek performansli mesajlasma sistemidir.")
	fmt.Println("JetStream - davamliliq (persistence) elave edir.")
	fmt.Println(`
  // go get github.com/nats-io/nats.go

  import "github.com/nats-io/nats.go"

  func natsNumune() error {
      // Baglanin
      nc, err := nats.Connect(nats.DefaultURL) // localhost:4222
      if err != nil {
          return err
      }
      defer nc.Close()

      // Sadə pub/sub
      // Abune ol
      nc.Subscribe("sifarisler.yeni", func(m *nats.Msg) {
          fmt.Printf("Alinan: %s\n", string(m.Data))
      })

      // Mesaj gonder
      nc.Publish("sifarisler.yeni", []byte("Yeni sifaris #123"))

      // Request/Reply pattern
      nc.Subscribe("xidmetler.sagliq", func(m *nats.Msg) {
          m.Respond([]byte("saglamdir"))
      })

      cavab, err := nc.Request("xidmetler.sagliq", nil, 2*time.Second)
      if err != nil {
          return err
      }
      fmt.Println("Cavab:", string(cavab.Data))

      // Queue Group - load balancing
      // Eyni queue group-da olan subscriber-lerden yalniz biri mesaji alir
      nc.QueueSubscribe("isler.emal", "isciler", func(m *nats.Msg) {
          fmt.Printf("Isci mesaj aldi: %s\n", string(m.Data))
      })

      return nil
  }

  // JetStream - davamliliq ile
  func jetStreamNumune() error {
      nc, _ := nats.Connect(nats.DefaultURL)
      js, _ := nc.JetStream()

      // Stream yarat
      js.AddStream(&nats.StreamConfig{
          Name:     "SIFARISLER",
          Subjects: []string{"sifarisler.>"},
      })

      // Publish
      js.Publish("sifarisler.yeni", []byte("Sifaris #456"))

      // Durable consumer
      sub, _ := js.SubscribeSync("sifarisler.yeni",
          nats.Durable("sifaris-emali"),
      )
      msg, _ := sub.NextMsg(5 * time.Second)
      msg.Ack()

      return nil
  }`)

	// -------------------------------------------
	// 4. Message patterns
	// -------------------------------------------
	fmt.Println("\n--- 4. Mesaj Pattern-leri ---")
	fmt.Println(`
  // ---- Pub/Sub (publish/subscribe) ----
  // Bir gonderici, bir nece alici
  // Her abune olan mesaji alir
  // Istifade: bildirisler, hadiselerin yayilmasi

  Publisher ----> Topic ----> Subscriber A
                        |---> Subscriber B
                        |---> Subscriber C

  // ---- Request/Reply ----
  // Gonderici cavab gozleyir (sinxron kimi, amma asinxrondur)
  // Istifade: servisler arasi sorgulama

  Client --request--> Service
  Client <--reply---- Service

  // ---- Queue Groups (is paylasma) ----
  // Eyni qrupda olan alicilardan yalniz biri mesaji alir
  // Istifade: load balancing, is paylasma

  Publisher ----> Queue ----> Worker A (bu aldi)
                         |--> Worker B (bu almadi)
                         |--> Worker C (bu almadi)

  // ---- Fan-out ----
  // Bir mesaj butun alicilara gonderilir
  // Pub/Sub-un xususi halidi

  // ---- Competing Consumers ----
  // Bir nece consumer eyni queue-dan oxuyur
  // RabbitMQ-da default beledır`)

	// -------------------------------------------
	// 5. Idempotency ve exactly-once semantics
	// -------------------------------------------
	fmt.Println("\n--- 5. Idempotency (tekrar emniyet) ---")
	fmt.Println("Idempotent = eyni emeliyyat tekrar edilse netice deyismez.")
	fmt.Println(`
  // Problem: mesaj 2 defe gele biler (network xetasi, retry)
  // Hell: her mesaja unikal ID ver ve tekrarlari yoxla

  type Mesaj struct {
      ID        string    // unikal idempotency key
      Melumat   []byte
      Tarix     time.Time
  }

  // Idempotency yoxlayici
  type IdempotencyYoxlayici struct {
      mu    sync.Mutex
      cache map[string]bool // real-da Redis/DB istifade edin
  }

  func (iy *IdempotencyYoxlayici) ArtiqIslenib(mesajID string) bool {
      iy.mu.Lock()
      defer iy.mu.Unlock()
      if iy.cache[mesajID] {
          return true // artiq islenmisdir
      }
      iy.cache[mesajID] = true
      return false
  }

  func mesajIsle(mesaj Mesaj) error {
      if yoxlayici.ArtiqIslenib(mesaj.ID) {
          log.Printf("Mesaj %s artiq islenmisdir, atlaniir", mesaj.ID)
          return nil // xeta qaytarmirig, sadece atlayiriq
      }

      // Mesaji emal et...
      return nil
  }

  // Exactly-once semantics:
  // Kafka: idempotent producer + transactions
  // RabbitMQ: publisher confirms + consumer acknowledgment
  // NATS JetStream: message deduplication window
  //
  // Praktikada: at-least-once delivery + idempotent consumer
  // Bu kombinasiya en etibarli yoldur`)

	// -------------------------------------------
	// 6. Dead Letter Queue (DLQ)
	// -------------------------------------------
	fmt.Println("\n--- 6. Dead Letter Queue (DLQ) ---")
	fmt.Println("DLQ - islene bilmeyen mesajlarin yonlendirildiyi xususi novbedir.")
	fmt.Println(`
  // Mesaj niye DLQ-ya duser?
  // - Islenme ugursuz oldu (xeta)
  // - Retry limiti asdi
  // - Mesaj formati yanlisdir
  // - TTL (yasama muddeti) bitdi

  type MesajIscisi struct {
      esasNovbe  string
      dlqNovbe   string
      maxTekrar  int
  }

  func (mi *MesajIscisi) mesajIsle(mesaj Mesaj) error {
      var sonXeta error
      tekrarSayi := 0

      for tekrarSayi < mi.maxTekrar {
          err := mi.emalEt(mesaj)
          if err == nil {
              return nil // ugurlu
          }
          sonXeta = err
          tekrarSayi++
          log.Printf("Tekrar %d/%d ugursuz: %v", tekrarSayi, mi.maxTekrar, err)

          // Exponential backoff
          gozle := time.Duration(1<<tekrarSayi) * time.Second
          time.Sleep(gozle)
      }

      // Butun tekrarlar ugursuz - DLQ-ya gonder
      log.Printf("Mesaj DLQ-ya gonderilir: %s", mesaj.ID)
      return mi.dlqYaGonder(mesaj, sonXeta)
  }

  // RabbitMQ-da DLQ avtomatik qurmaq
  func dlqQurmaq(ch *amqp.Channel) error {
      // DLQ yaradiriq
      _, err := ch.QueueDeclare("sifarisler.dlq", true, false, false, false, nil)
      if err != nil {
          return err
      }

      // Esas novbeni DLQ ile elaqelendiririk
      args := amqp.Table{
          "x-dead-letter-exchange":    "",
          "x-dead-letter-routing-key": "sifarisler.dlq",
          "x-message-ttl":             int32(60000), // 60 saniye
      }
      _, err = ch.QueueDeclare("sifarisler", true, false, false, false, args)
      return err
  }`)

	// -------------------------------------------
	// 7. Error handling ve retry strategies
	// -------------------------------------------
	fmt.Println("\n--- 7. Xeta Idare Etme ve Retry ---")
	fmt.Println(`
  // ---- Retry strategiyalari ----

  // 1. Sabit araliqliq retry
  func sabitRetry(fn func() error, maxTekrar int, aralig time.Duration) error {
      for i := 0; i < maxTekrar; i++ {
          if err := fn(); err == nil {
              return nil
          }
          time.Sleep(aralig) // her defe eyni muddet
      }
      return fmt.Errorf("butun tekrarlar ugursuz")
  }

  // 2. Exponential backoff (artimli gozleme)
  func eksponensialRetry(fn func() error, maxTekrar int) error {
      for i := 0; i < maxTekrar; i++ {
          if err := fn(); err == nil {
              return nil
          }
          gozle := time.Duration(1<<i) * time.Second // 1s, 2s, 4s, 8s, 16s
          jitter := time.Duration(rand.Intn(1000)) * time.Millisecond
          time.Sleep(gozle + jitter) // jitter - butun consumer-lerin eyni anda retry etmesinin qarsisini alir
      }
      return fmt.Errorf("butun tekrarlar ugursuz")
  }

  // 3. Circuit breaker ile retry (sony/gobreaker)
  // Coxlu ugursuzlugdan sonra bir muddet hec cehd etme

  // ---- Poison message handling ----
  // Bezi mesajlar hec vaxt islene bilmez (format xetasi, melumat pozulub)
  // Bunlari detect edib DLQ-ya gondermek lazimdir

  func zererliMesajYoxla(mesaj []byte) error {
      var m SifarisMessaji
      if err := json.Unmarshal(mesaj, &m); err != nil {
          return fmt.Errorf("zererli mesaj (parse olunmur): %w", err)
      }
      if m.ID == "" || m.Mebleg <= 0 {
          return fmt.Errorf("zererli mesaj (melumat etibarsiz)")
      }
      return nil // mesaj normaldir
  }

  // ---- Graceful shutdown ----
  // Consumer-i duzgun dayandirmaq muhimdir

  func gracefulConsumer(ctx context.Context) {
      // ctx legv olunanda artiq yeni mesaj almiriq
      // amma hazirda islenen mesaji bitiririk

      for {
          select {
          case <-ctx.Done():
              log.Println("Consumer dayandirili...")
              // Islenmekte olan mesajlari bitir
              // Connection-lari bagla
              return
          case mesaj := <-mesajKanali:
              mesajIsle(mesaj)
          }
      }
  }`)

	fmt.Println("\n=== XULASE ===")
	fmt.Println("Kafka     - yuksek hachm, log-esasli, partition ile olceklenir")
	fmt.Println("RabbitMQ  - cevik routing, exchange/queue modeli, AMQP")
	fmt.Println("NATS      - sade, suretli, cloud-native, JetStream ile davamliliq")
	fmt.Println("Pattern   - pub/sub, request/reply, queue group secin ehtiyaca gore")
	fmt.Println("Idempotency - tekrar mesajlara hazirliqli olun")
	fmt.Println("DLQ       - islene bilmeyen mesajlari ayri yigin")
	fmt.Println("Retry     - exponential backoff + jitter en yaxsi yanasmadi")
}
