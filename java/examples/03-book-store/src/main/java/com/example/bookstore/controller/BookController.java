package com.example.bookstore.controller;

import com.example.bookstore.entity.Author;
import com.example.bookstore.entity.Book;
import com.example.bookstore.repository.AuthorRepository;
import com.example.bookstore.service.BookService;
import jakarta.validation.Valid;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.domain.Sort;
import org.springframework.data.web.PageableDefault;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;
import java.util.NoSuchElementException;

@RestController
public class BookController {

    private final BookService bookService;
    private final AuthorRepository authorRepo;

    public BookController(BookService bookService, AuthorRepository authorRepo) {
        this.bookService = bookService;
        this.authorRepo  = authorRepo;
    }

    // --- Author endpoints ---

    @GetMapping("/api/authors")
    public List<Author> getAuthors() {
        return authorRepo.findAll();
    }

    @PostMapping("/api/authors")
    @ResponseStatus(HttpStatus.CREATED)
    public Author createAuthor(@RequestBody @Valid AuthorRequest req) {
        Author a = new Author();
        a.setName(req.name());
        a.setBio(req.bio());
        return authorRepo.save(a);
    }

    // --- Book endpoints ---

    @GetMapping("/api/books")
    public Page<Book> getBooks(
            @RequestParam(required = false) String search,
            @PageableDefault(size = 10, sort = "title", direction = Sort.Direction.ASC) Pageable pageable) {
        return bookService.findAll(search, pageable);
    }

    @PostMapping("/api/books")
    @ResponseStatus(HttpStatus.CREATED)
    public Book createBook(@RequestBody @Valid BookRequest req) {
        return bookService.create(req.title(), req.isbn(), req.year(), req.authorId());
    }

    @GetMapping("/api/books/{id}")
    public Book getBook(@PathVariable Long id) {
        return bookService.findById(id);
    }

    @GetMapping("/api/books/author/{authorId}")
    public List<Book> getByAuthor(@PathVariable Long authorId) {
        return bookService.findByAuthor(authorId);
    }

    @DeleteMapping("/api/books/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void deleteBook(@PathVariable Long id) {
        bookService.delete(id);
    }

    // --- Exception handler ---

    @ExceptionHandler(NoSuchElementException.class)
    public ResponseEntity<Map<String, String>> handleNotFound(NoSuchElementException ex) {
        return ResponseEntity.status(404).body(Map.of("error", ex.getMessage()));
    }

    // --- DTOs ---

    record AuthorRequest(@NotBlank String name, String bio) {}

    record BookRequest(
            @NotBlank String title,
            String isbn,
            Integer year,
            @NotNull Long authorId
    ) {}
}
