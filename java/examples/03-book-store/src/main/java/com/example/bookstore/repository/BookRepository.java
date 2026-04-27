package com.example.bookstore.repository;

import com.example.bookstore.entity.Book;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;

import java.util.List;

public interface BookRepository extends JpaRepository<Book, Long> {

    // Başlıq üzrə case-insensitive axtarış + pagination
    @Query("SELECT b FROM Book b WHERE LOWER(b.title) LIKE LOWER(CONCAT('%', :q, '%'))")
    Page<Book> searchByTitle(@Param("q") String query, Pageable pageable);

    // Müəllifin bütün kitabları
    List<Book> findByAuthorId(Long authorId);
}
