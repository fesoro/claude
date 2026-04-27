package com.example.blog.entity;

import com.fasterxml.jackson.annotation.JsonIgnoreProperties;
import jakarta.persistence.*;
import org.springframework.data.annotation.CreatedDate;
import org.springframework.data.jpa.domain.support.AuditingEntityListener;

import java.time.Instant;

@Entity
@Table(name = "comments")
@EntityListeners(AuditingEntityListener.class)
public class Comment {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(columnDefinition = "TEXT", nullable = false)
    private String content;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "post_id", nullable = false)
    @JsonIgnoreProperties("comments")
    private Post post;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "author_id", nullable = false)
    @JsonIgnoreProperties({"posts", "password"})
    private User author;

    @CreatedDate
    @Column(updatable = false)
    private Instant createdAt;

    public Long getId()              { return id; }
    public String getContent()       { return content; }
    public void setContent(String c) { this.content = c; }
    public Post getPost()            { return post; }
    public void setPost(Post p)      { this.post = p; }
    public User getAuthor()          { return author; }
    public void setAuthor(User a)    { this.author = a; }
    public Instant getCreatedAt()    { return createdAt; }
}
