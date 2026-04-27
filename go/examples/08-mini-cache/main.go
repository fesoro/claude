package main

import (
	"bufio"
	"fmt"
	"log"
	"net"
	"strconv"
	"strings"
	"sync"
	"time"
)

type entry struct {
	value     string
	expiresAt time.Time
	hasTTL    bool
}

type cache struct {
	mu   sync.RWMutex
	data map[string]entry
}

func newCache() *cache {
	c := &cache{data: make(map[string]entry)}
	go c.gc()
	return c
}

// Background GC: evict expired keys every 5 seconds
func (c *cache) gc() {
	for range time.NewTicker(5 * time.Second).C {
		c.mu.Lock()
		now := time.Now()
		for k, e := range c.data {
			if e.hasTTL && now.After(e.expiresAt) {
				delete(c.data, k)
			}
		}
		c.mu.Unlock()
	}
}

func (c *cache) set(key, value string, ttl time.Duration) {
	c.mu.Lock()
	defer c.mu.Unlock()
	e := entry{value: value}
	if ttl > 0 {
		e.hasTTL = true
		e.expiresAt = time.Now().Add(ttl)
	}
	c.data[key] = e
}

func (c *cache) get(key string) (string, bool) {
	c.mu.RLock()
	defer c.mu.RUnlock()
	e, ok := c.data[key]
	if !ok || (e.hasTTL && time.Now().After(e.expiresAt)) {
		return "", false
	}
	return e.value, true
}

func (c *cache) del(keys ...string) int {
	c.mu.Lock()
	defer c.mu.Unlock()
	n := 0
	for _, k := range keys {
		if _, ok := c.data[k]; ok {
			delete(c.data, k)
			n++
		}
	}
	return n
}

func (c *cache) keys() []string {
	c.mu.RLock()
	defer c.mu.RUnlock()
	now := time.Now()
	var result []string
	for k, e := range c.data {
		if !e.hasTTL || !now.After(e.expiresAt) {
			result = append(result, k)
		}
	}
	return result
}

func (c *cache) ttl(key string) int {
	c.mu.RLock()
	defer c.mu.RUnlock()
	e, ok := c.data[key]
	if !ok {
		return -2 // key does not exist
	}
	if !e.hasTTL {
		return -1 // no TTL set
	}
	rem := time.Until(e.expiresAt)
	if rem < 0 {
		return -2 // expired
	}
	return int(rem.Seconds())
}

func (c *cache) flush() {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.data = make(map[string]entry)
}

func (c *cache) size() int {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return len(c.data)
}

func handle(conn net.Conn, c *cache) {
	defer conn.Close()
	fmt.Fprintln(conn, "mini-cache 1.0  |  SET GET DEL KEYS TTL FLUSHALL INFO QUIT")

	scanner := bufio.NewScanner(conn)
	for scanner.Scan() {
		parts := strings.Fields(scanner.Text())
		if len(parts) == 0 {
			continue
		}
		cmd := strings.ToUpper(parts[0])

		switch cmd {
		case "SET":
			// SET key value [EX seconds]
			if len(parts) < 3 {
				fmt.Fprintln(conn, "ERR: SET key value [EX seconds]")
				continue
			}
			var ttl time.Duration
			if len(parts) >= 5 && strings.ToUpper(parts[3]) == "EX" {
				secs, err := strconv.Atoi(parts[4])
				if err != nil || secs <= 0 {
					fmt.Fprintln(conn, "ERR: invalid expire time")
					continue
				}
				ttl = time.Duration(secs) * time.Second
			}
			c.set(parts[1], parts[2], ttl)
			fmt.Fprintln(conn, "OK")

		case "GET":
			if len(parts) < 2 {
				fmt.Fprintln(conn, "ERR: GET key")
				continue
			}
			val, ok := c.get(parts[1])
			if !ok {
				fmt.Fprintln(conn, "(nil)")
			} else {
				fmt.Fprintf(conn, "%q\n", val)
			}

		case "DEL":
			if len(parts) < 2 {
				fmt.Fprintln(conn, "ERR: DEL key [key ...]")
				continue
			}
			fmt.Fprintf(conn, "(integer) %d\n", c.del(parts[1:]...))

		case "KEYS":
			ks := c.keys()
			if len(ks) == 0 {
				fmt.Fprintln(conn, "(empty)")
			} else {
				for i, k := range ks {
					fmt.Fprintf(conn, "%d) %q\n", i+1, k)
				}
			}

		case "TTL":
			if len(parts) < 2 {
				fmt.Fprintln(conn, "ERR: TTL key")
				continue
			}
			fmt.Fprintf(conn, "(integer) %d\n", c.ttl(parts[1]))

		case "FLUSHALL":
			c.flush()
			fmt.Fprintln(conn, "OK")

		case "INFO":
			fmt.Fprintf(conn, "keys: %d\n", c.size())

		case "QUIT", "EXIT":
			fmt.Fprintln(conn, "BYE")
			return

		default:
			fmt.Fprintf(conn, "ERR: unknown command %q\n", cmd)
		}
	}
}

func main() {
	c := newCache()

	ln, err := net.Listen("tcp", ":6399")
	if err != nil {
		log.Fatal(err)
	}
	defer ln.Close()

	log.Println("mini-cache listening on :6399")
	log.Println("Connect: nc localhost 6399  OR  telnet localhost 6399")

	for {
		conn, err := ln.Accept()
		if err != nil {
			log.Println("accept:", err)
			continue
		}
		log.Printf("client connected: %s", conn.RemoteAddr())
		go handle(conn, c)
	}
}
