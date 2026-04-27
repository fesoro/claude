package com.example.blog.service;

import com.example.blog.entity.Post;
import com.example.blog.entity.User;
import com.example.blog.repository.PostRepository;
import org.springframework.cache.annotation.CacheEvict;
import org.springframework.cache.annotation.Cacheable;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.security.access.AccessDeniedException;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.NoSuchElementException;

@Service
@Transactional(readOnly = true)
public class PostService {

    private final PostRepository repo;

    public PostService(PostRepository repo) { this.repo = repo; }

    public Page<Post> findPublished(Pageable pageable) {
        return repo.findPublished(pageable);
    }

    @Cacheable(value = "posts", key = "#id")
    public Post findById(Long id) {
        return repo.findById(id)
                .orElseThrow(() -> new NoSuchElementException("Post tapılmadı: " + id));
    }

    @Transactional
    public Post create(String title, String content, boolean published, User author) {
        Post post = new Post();
        post.setTitle(title);
        post.setContent(content);
        post.setPublished(published);
        post.setAuthor(author);
        return repo.save(post);
    }

    @Transactional
    @CacheEvict(value = "posts", key = "#id")
    public Post update(Long id, String title, String content, boolean published, User requester) {
        Post post = findById(id);
        checkOwner(post, requester);
        post.setTitle(title);
        post.setContent(content);
        post.setPublished(published);
        return repo.save(post);
    }

    @Transactional
    @CacheEvict(value = "posts", key = "#id")
    public void delete(Long id, User requester) {
        Post post = findById(id);
        checkOwner(post, requester);
        repo.delete(post);
    }

    private void checkOwner(Post post, User requester) {
        if (!post.getAuthor().getId().equals(requester.getId())) {
            throw new AccessDeniedException("Yalnız öz postunu dəyişə bilərsən");
        }
    }
}
