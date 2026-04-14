package main

import "fmt"

// ===============================================
// WEBSOCKET - REAL-TIME ELAQE
// ===============================================

// WebSocket - server ve client arasi iki terefli, davamedici elaqe
// Chat, bildirisler, canli melumat (borsa, oyun) ucun istifade olunur

// En cox istifade olunan kitabxana: gorilla/websocket
// go get github.com/gorilla/websocket

func main() {
	fmt.Println("WebSocket ornekleri - asagidaki kodu ayri layihede isledin")

	serverKodu := `
package main

import (
    "fmt"
    "log"
    "net/http"
    "sync"

    "github.com/gorilla/websocket"
)

// -------------------------------------------
// 1. WebSocket upgrader
// -------------------------------------------
var upgrader = websocket.Upgrader{
    ReadBufferSize:  1024,
    WriteBufferSize: 1024,
    // CORS ucun (development)
    CheckOrigin: func(r *http.Request) bool { return true },
}

// -------------------------------------------
// 2. Client strukturu
// -------------------------------------------
type Client struct {
    conn *websocket.Conn
    send chan []byte
}

// -------------------------------------------
// 3. Hub - butun client-leri idare edir
// -------------------------------------------
type Hub struct {
    clients    map[*Client]bool
    broadcast  chan []byte     // hamiya gonder
    register   chan *Client    // yeni client
    unregister chan *Client    // ayrilmis client
    mu         sync.RWMutex
}

func NewHub() *Hub {
    return &Hub{
        clients:    make(map[*Client]bool),
        broadcast:  chan []byte(make(chan []byte, 256)),
        register:   make(chan *Client),
        unregister: make(chan *Client),
    }
}

func (h *Hub) Run() {
    for {
        select {
        case client := <-h.register:
            h.mu.Lock()
            h.clients[client] = true
            h.mu.Unlock()
            log.Printf("Yeni client qosuldu. Cem: %d", len(h.clients))

        case client := <-h.unregister:
            h.mu.Lock()
            if _, ok := h.clients[client]; ok {
                delete(h.clients, client)
                close(client.send)
            }
            h.mu.Unlock()
            log.Printf("Client ayrildi. Cem: %d", len(h.clients))

        case message := <-h.broadcast:
            h.mu.RLock()
            for client := range h.clients {
                select {
                case client.send <- message:
                default:
                    close(client.send)
                    delete(h.clients, client)
                }
            }
            h.mu.RUnlock()
        }
    }
}

// -------------------------------------------
// 4. Client oxuma ve yazma goroutine-leri
// -------------------------------------------
func (c *Client) readPump(hub *Hub) {
    defer func() {
        hub.unregister <- c
        c.conn.Close()
    }()

    for {
        _, message, err := c.conn.ReadMessage()
        if err != nil {
            break
        }
        log.Printf("Mesaj alindi: %s", message)
        hub.broadcast <- message // hamiya gonder
    }
}

func (c *Client) writePump() {
    defer c.conn.Close()
    for message := range c.send {
        err := c.conn.WriteMessage(websocket.TextMessage, message)
        if err != nil {
            break
        }
    }
}

// -------------------------------------------
// 5. WebSocket handler
// -------------------------------------------
func wsHandler(hub *Hub, w http.ResponseWriter, r *http.Request) {
    conn, err := upgrader.Upgrade(w, r, nil) // HTTP -> WebSocket
    if err != nil {
        log.Println("Upgrade xetasi:", err)
        return
    }

    client := &Client{
        conn: conn,
        send: make(chan []byte, 256),
    }

    hub.register <- client

    go client.readPump(hub)
    go client.writePump()
}

// -------------------------------------------
// 6. HTML chat sehifesi
// -------------------------------------------
var htmlSehife = ` + "`" + `<!DOCTYPE html>
<html>
<body>
    <h2>Go WebSocket Chat</h2>
    <div id="mesajlar" style="height:300px;overflow:auto;border:1px solid #ccc;padding:10px;"></div>
    <input id="giris" type="text" placeholder="Mesaj yazin..." style="width:300px">
    <button onclick="gonder()">Gonder</button>
    <script>
        var ws = new WebSocket("ws://" + location.host + "/ws");
        ws.onmessage = function(e) {
            var div = document.getElementById("mesajlar");
            div.innerHTML += "<p>" + e.data + "</p>";
            div.scrollTop = div.scrollHeight;
        };
        function gonder() {
            var giris = document.getElementById("giris");
            ws.send(giris.value);
            giris.value = "";
        }
        document.getElementById("giris").addEventListener("keypress", function(e) {
            if (e.key === "Enter") gonder();
        });
    </script>
</body>
</html>` + "`" + `

func main() {
    hub := NewHub()
    go hub.Run()

    http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
        w.Header().Set("Content-Type", "text/html")
        fmt.Fprint(w, htmlSehife)
    })

    http.HandleFunc("/ws", func(w http.ResponseWriter, r *http.Request) {
        wsHandler(hub, w, r)
    })

    log.Println("Chat server :8080 portunda isleyir...")
    log.Println("Brauzerde: http://localhost:8080")
    log.Fatal(http.ListenAndServe(":8080", nil))
}
`

	fmt.Println(serverKodu)

	// QEYDLER:
	// - gorilla/websocket en populyar WebSocket kitabxanasidir
	// - Her client ucun 2 goroutine isleyir (oxuma + yazma)
	// - Hub pattern - butun client-leri merkezi idare edir
	// - Production-da: ping/pong, reconnect, mesaj limiti elave edin
	// - Alternativ: nhooyr.io/websocket (daha modern)
}
