package com.example.bookstore.service;

import com.example.bookstore.entity.Author;
import com.example.bookstore.entity.Book;
import com.example.bookstore.repository.AuthorRepository;
import com.example.bookstore.repository.BookRepository;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;
import java.util.NoSuchElementException;

@Service
@Transactional(readOnly = true)
public class BookService {

    private final BookRepository bookRepo;
    private final AuthorRepository authorRepo;

    public BookService(BookRepository bookRepo, AuthorRepository authorRepo) {
        this.bookRepo  = bookRepo;
        this.authorRepo = authorRepo;
    }

    public Page<Book> findAll(String search, Pageable pageable) {
        if (search != null && !search.isBlank()) {
            return bookRepo.searchByTitle(search, pageable);
        }
        return bookRepo.findAll(pageable);
    }

    public Book findById(Long id) {
        return bookRepo.findById(id)
                .orElseThrow(() -> new NoSuchElementException("Kitab tapılmadı: " + id));
    }

    public List<Book> findByAuthor(Long authorId) {
        return bookRepo.findByAuthorId(authorId);
    }

    @Transactional
    public Book create(String title, String isbn, Integer year, Long authorId) {
        Author author = authorRepo.findById(authorId)
                .orElseThrow(() -> new NoSuchElementException("Müəllif tapılmadı: " + authorId));
        Book book = new Book();
        book.setTitle(title);
        book.setIsbn(isbn);
        book.setYear(year);
        book.setAuthor(author);
        return bookRepo.save(book);
    }

    @Transactional
    public void delete(Long id) {
        bookRepo.delete(findById(id));
    }
}
