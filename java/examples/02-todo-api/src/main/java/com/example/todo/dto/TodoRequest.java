package com.example.todo.dto;

import com.example.todo.entity.Todo;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;

public record TodoRequest(
        @NotBlank(message = "Başlıq boş ola bilməz")
        @Size(max = 255, message = "Başlıq 255 simvoldan çox ola bilməz")
        String title,

        Todo.Priority priority
) {}
