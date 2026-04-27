package main

import (
	"bufio"
	"fmt"
	"log"
	"net"
	"strings"
	"sync"
)

type client struct {
	conn net.Conn
	name string
	send chan string
}

type hub struct {
	mu      sync.RWMutex
	clients map[*client]struct{}
	join    chan *client
	leave   chan *client
	message chan string
}

func newHub() *hub {
	return &hub{
		clients: make(map[*client]struct{}),
		join:    make(chan *client),
		leave:   make(chan *client),
		message: make(chan string, 100),
	}
}

func (h *hub) run() {
	for {
		select {
		case c := <-h.join:
			h.mu.Lock()
			h.clients[c] = struct{}{}
			h.mu.Unlock()
			h.broadcast(fmt.Sprintf("*** %s joined the chat ***", c.name))

		case c := <-h.leave:
			h.mu.Lock()
			delete(h.clients, c)
			h.mu.Unlock()
			close(c.send)
			h.broadcast(fmt.Sprintf("*** %s left the chat ***", c.name))

		case msg := <-h.message:
			h.broadcast(msg)
		}
	}
}

func (h *hub) broadcast(msg string) {
	h.mu.RLock()
	defer h.mu.RUnlock()
	for c := range h.clients {
		select {
		case c.send <- msg:
		default:
			// slow client — drop message
		}
	}
}

func handleConn(conn net.Conn, h *hub) {
	defer conn.Close()
	reader := bufio.NewReader(conn)

	fmt.Fprint(conn, "Enter your name: ")
	name, _ := reader.ReadString('\n')
	name = strings.TrimSpace(name)
	if name == "" {
		name = conn.RemoteAddr().String()
	}

	c := &client{conn: conn, name: name, send: make(chan string, 32)}
	h.join <- c

	// writer goroutine: sends hub messages to this client
	go func() {
		for msg := range c.send {
			fmt.Fprintf(conn, "%s\n", msg)
		}
	}()

	fmt.Fprintf(conn, "Welcome, %s! Type a message or /quit to exit.\n", name)

	for {
		line, err := reader.ReadString('\n')
		if err != nil {
			break
		}
		line = strings.TrimSpace(line)
		if line == "" {
			continue
		}
		if line == "/quit" {
			break
		}
		h.message <- fmt.Sprintf("[%s]: %s", name, line)
	}

	h.leave <- c
}

func main() {
	h := newHub()
	go h.run()

	ln, err := net.Listen("tcp", ":8888")
	if err != nil {
		log.Fatal(err)
	}
	defer ln.Close()

	log.Println("TCP Chat Server running on :8888")
	log.Println("Connect: telnet localhost 8888  OR  nc localhost 8888")

	for {
		conn, err := ln.Accept()
		if err != nil {
			log.Println("accept error:", err)
			continue
		}
		log.Printf("new connection: %s", conn.RemoteAddr())
		go handleConn(conn, h)
	}
}
