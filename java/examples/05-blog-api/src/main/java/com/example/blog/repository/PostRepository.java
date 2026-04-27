package com.example.blog.repository;

import com.example.blog.entity.Post;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;

public interface PostRepository extends JpaRepository<Post, Long> {

    // Yalnız published post-ları göstər, author-u JOIN FETCH et (N+1-dən qorun)
    @Query("SELECT p FROM Post p JOIN FETCH p.author WHERE p.published = true")
    Page<Post> findPublished(Pageable pageable);
}
