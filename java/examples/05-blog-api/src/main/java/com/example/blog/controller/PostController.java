package com.example.blog.controller;

import com.example.blog.entity.Post;
import com.example.blog.entity.User;
import com.example.blog.service.PostService;
import jakarta.validation.Valid;
import jakarta.validation.constraints.NotBlank;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.web.PageableDefault;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

import java.util.Map;
import java.util.NoSuchElementException;

@RestController
@RequestMapping("/api/posts")
public class PostController {

    private final PostService service;

    public PostController(PostService service) { this.service = service; }

    @GetMapping
    public Page<Post> list(@PageableDefault(size = 10) Pageable pageable) {
        return service.findPublished(pageable);
    }

    @GetMapping("/{id}")
    public Post get(@PathVariable Long id) {
        return service.findById(id);
    }

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public Post create(@RequestBody @Valid PostRequest req,
                       @AuthenticationPrincipal User user) {
        return service.create(req.title(), req.content(), req.published(), user);
    }

    @PutMapping("/{id}")
    public Post update(@PathVariable Long id,
                       @RequestBody @Valid PostRequest req,
                       @AuthenticationPrincipal User user) {
        return service.update(id, req.title(), req.content(), req.published(), user);
    }

    @DeleteMapping("/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void delete(@PathVariable Long id, @AuthenticationPrincipal User user) {
        service.delete(id, user);
    }

    @ExceptionHandler(NoSuchElementException.class)
    public ResponseEntity<Map<String, String>> notFound(NoSuchElementException ex) {
        return ResponseEntity.status(404).body(Map.of("error", ex.getMessage()));
    }

    record PostRequest(
            @NotBlank String title,
            @NotBlank String content,
            boolean published
    ) {}
}
