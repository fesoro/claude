package com.example.auth.service;

import com.example.auth.dto.AuthResponse;
import com.example.auth.dto.LoginRequest;
import com.example.auth.dto.RegisterRequest;
import com.example.auth.entity.User;
import com.example.auth.repository.UserRepository;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;

@Service
public class AuthService {

    private final UserRepository repo;
    private final PasswordEncoder encoder;
    private final JwtService jwt;
    private final AuthenticationManager authManager;

    public AuthService(UserRepository repo, PasswordEncoder encoder,
                       JwtService jwt, AuthenticationManager authManager) {
        this.repo        = repo;
        this.encoder     = encoder;
        this.jwt         = jwt;
        this.authManager = authManager;
    }

    public AuthResponse register(RegisterRequest req) {
        if (repo.existsByEmail(req.email())) {
            throw new IllegalArgumentException("Bu email artıq istifadə edilir");
        }
        User user = new User();
        user.setEmail(req.email());
        user.setPassword(encoder.encode(req.password()));
        user.setName(req.name());
        repo.save(user);
        return new AuthResponse(jwt.generate(user), user.getEmail(), user.getName());
    }

    public AuthResponse login(LoginRequest req) {
        // Spring Security şifrəni yoxlayır; yanlış olarsa BadCredentialsException atır
        authManager.authenticate(
                new UsernamePasswordAuthenticationToken(req.email(), req.password())
        );
        User user = repo.findByEmail(req.email()).orElseThrow();
        return new AuthResponse(jwt.generate(user), user.getEmail(), user.getName());
    }
}
