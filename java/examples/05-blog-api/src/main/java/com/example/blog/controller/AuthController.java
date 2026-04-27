package com.example.blog.controller;

import com.example.blog.entity.User;
import com.example.blog.repository.UserRepository;
import com.example.blog.security.JwtService;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.web.bind.annotation.*;

import java.util.Map;

@RestController
@RequestMapping("/api/auth")
public class AuthController {

    private final UserRepository userRepo;
    private final PasswordEncoder encoder;
    private final JwtService jwt;
    private final AuthenticationManager authManager;

    public AuthController(UserRepository userRepo, PasswordEncoder encoder,
                          JwtService jwt, AuthenticationManager authManager) {
        this.userRepo    = userRepo;
        this.encoder     = encoder;
        this.jwt         = jwt;
        this.authManager = authManager;
    }

    @PostMapping("/register")
    @ResponseStatus(HttpStatus.CREATED)
    public Map<String, Object> register(@RequestBody @Valid RegisterReq req) {
        if (userRepo.existsByEmail(req.email())) {
            throw new IllegalArgumentException("Bu email artıq mövcuddur");
        }
        User user = new User();
        user.setEmail(req.email());
        user.setPassword(encoder.encode(req.password()));
        user.setName(req.name());
        userRepo.save(user);
        return Map.of("token", jwt.generate(user), "email", user.getEmail());
    }

    @PostMapping("/login")
    public Map<String, Object> login(@RequestBody @Valid LoginReq req) {
        authManager.authenticate(new UsernamePasswordAuthenticationToken(req.email(), req.password()));
        User user = userRepo.findByEmail(req.email()).orElseThrow();
        return Map.of("token", jwt.generate(user), "email", user.getEmail(), "name", user.getName());
    }

    @ExceptionHandler(IllegalArgumentException.class)
    public ResponseEntity<Map<String, String>> conflict(IllegalArgumentException ex) {
        return ResponseEntity.status(409).body(Map.of("error", ex.getMessage()));
    }

    record RegisterReq(@NotBlank @Email String email, @NotBlank @Size(min = 6) String password, @NotBlank String name) {}
    record LoginReq(@NotBlank @Email String email, @NotBlank String password) {}
}
