package com.example.hello.controller;

import jakarta.validation.Valid;
import jakarta.validation.constraints.NotBlank;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.time.Instant;
import java.util.Collection;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.atomic.AtomicLong;

@RestController
@RequestMapping("/api")
public class HelloController {

    // In-memory store — database yoxdur, Junior level
    private final Map<Long, Message> store = new ConcurrentHashMap<>();
    private final AtomicLong counter = new AtomicLong();

    // --- DTOs (Java 21 Records) ---

    record MessageRequest(@NotBlank(message = "text boş ola bilməz") String text) {}

    record Message(long id, String text, Instant createdAt) {}

    // --- Endpoints ---

    @GetMapping("/hello")
    public Map<String, String> hello(@RequestParam(defaultValue = "World") String name) {
        return Map.of("message", "Salam, " + name + "!");
    }

    @GetMapping("/messages")
    public Collection<Message> getAll() {
        return store.values();
    }

    @PostMapping("/messages")
    @ResponseStatus(HttpStatus.CREATED)
    public Message create(@RequestBody @Valid MessageRequest req) {
        long id = counter.incrementAndGet();
        Message msg = new Message(id, req.text(), Instant.now());
        store.put(id, msg);
        return msg;
    }

    @GetMapping("/messages/{id}")
    public ResponseEntity<Message> getById(@PathVariable Long id) {
        Message msg = store.get(id);
        if (msg == null) {
            return ResponseEntity.notFound().build();
        }
        return ResponseEntity.ok(msg);
    }

    @DeleteMapping("/messages/{id}")
    public ResponseEntity<Void> delete(@PathVariable Long id) {
        if (store.remove(id) == null) {
            return ResponseEntity.notFound().build();
        }
        return ResponseEntity.noContent().build();
    }
}
