package com.example.todo.service;

import com.example.todo.dto.TodoRequest;
import com.example.todo.entity.Todo;
import com.example.todo.repository.TodoRepository;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.time.Instant;
import java.util.List;
import java.util.NoSuchElementException;

@Service
@Transactional(readOnly = true)
public class TodoService {

    private final TodoRepository repo;

    public TodoService(TodoRepository repo) {
        this.repo = repo;
    }

    public List<Todo> findAll(Todo.Status status) {
        if (status != null) return repo.findByStatus(status);
        return repo.findAll();
    }

    public Todo findById(Long id) {
        return repo.findById(id)
                .orElseThrow(() -> new NoSuchElementException("Todo tapılmadı: " + id));
    }

    @Transactional
    public Todo create(TodoRequest req) {
        Todo todo = new Todo();
        todo.setTitle(req.title());
        if (req.priority() != null) todo.setPriority(req.priority());
        return repo.save(todo);
    }

    @Transactional
    public Todo update(Long id, TodoRequest req) {
        Todo todo = findById(id);
        todo.setTitle(req.title());
        if (req.priority() != null) todo.setPriority(req.priority());
        return repo.save(todo);
    }

    @Transactional
    public Todo complete(Long id) {
        Todo todo = findById(id);
        todo.setStatus(Todo.Status.DONE);
        todo.setCompletedAt(Instant.now());
        return repo.save(todo);
    }

    @Transactional
    public void delete(Long id) {
        Todo todo = findById(id);
        repo.delete(todo);
    }
}
