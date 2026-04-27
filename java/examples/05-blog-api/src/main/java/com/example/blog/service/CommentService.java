package com.example.blog.service;

import com.example.blog.entity.Comment;
import com.example.blog.entity.Post;
import com.example.blog.entity.User;
import com.example.blog.repository.CommentRepository;
import com.example.blog.repository.PostRepository;
import org.springframework.security.access.AccessDeniedException;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;
import java.util.NoSuchElementException;

@Service
@Transactional(readOnly = true)
public class CommentService {

    private final CommentRepository commentRepo;
    private final PostRepository postRepo;

    public CommentService(CommentRepository commentRepo, PostRepository postRepo) {
        this.commentRepo = commentRepo;
        this.postRepo    = postRepo;
    }

    public List<Comment> findByPost(Long postId) {
        return commentRepo.findByPostIdOrderByCreatedAtAsc(postId);
    }

    @Transactional
    public Comment create(Long postId, String content, User author) {
        Post post = postRepo.findById(postId)
                .orElseThrow(() -> new NoSuchElementException("Post tapılmadı: " + postId));
        Comment comment = new Comment();
        comment.setContent(content);
        comment.setPost(post);
        comment.setAuthor(author);
        return commentRepo.save(comment);
    }

    @Transactional
    public void delete(Long commentId, User requester) {
        Comment comment = commentRepo.findById(commentId)
                .orElseThrow(() -> new NoSuchElementException("Şərh tapılmadı: " + commentId));
        if (!comment.getAuthor().getId().equals(requester.getId())) {
            throw new AccessDeniedException("Yalnız öz şərhini silə bilərsən");
        }
        commentRepo.delete(comment);
    }
}
