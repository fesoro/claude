package az.ecommerce.config;

import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.http.HttpMethod;
import org.springframework.security.config.annotation.method.configuration.EnableMethodSecurity;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.annotation.web.configurers.AbstractHttpConfigurer;
import org.springframework.security.config.http.SessionCreationPolicy;
import org.springframework.security.web.SecurityFilterChain;
import org.springframework.security.web.authentication.UsernamePasswordAuthenticationFilter;

/**
 * Laravel: app/Providers/AppServiceProvider.php + auth.php konfiqurasiya
 * Spring: SecurityFilterChain — JWT-based stateless auth.
 *
 * Public endpoint-lər:
 *   /api/auth/register, /login, /forgot-password, /reset-password, /2fa/verify
 *   /api/products (GET), /api/users/{id} (GET), /api/search, /api/health/*
 *
 * Hər başqa endpoint auth tələb edir.
 */
@Configuration
@EnableMethodSecurity   // @PreAuthorize işlədə bilmək üçün
public class SecurityConfig {

    private final JwtAuthFilter jwtAuthFilter;

    public SecurityConfig(JwtAuthFilter jwtAuthFilter) {
        this.jwtAuthFilter = jwtAuthFilter;
    }

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            .csrf(AbstractHttpConfigurer::disable)
            .sessionManagement(s -> s.sessionCreationPolicy(SessionCreationPolicy.STATELESS))
            .authorizeHttpRequests(auth -> auth
                // Public Laravel route-ları
                .requestMatchers("/api/auth/register", "/api/auth/login",
                                 "/api/auth/forgot-password", "/api/auth/reset-password",
                                 "/api/auth/2fa/verify", "/api/auth/2fa/verify-backup").permitAll()
                .requestMatchers("/api/health/**", "/actuator/**").permitAll()
                .requestMatchers("/api/search").permitAll()
                .requestMatchers(HttpMethod.GET, "/api/products/**").permitAll()
                .requestMatchers(HttpMethod.GET, "/api/users/*").permitAll()
                .anyRequest().authenticated()
            )
            .addFilterBefore(jwtAuthFilter, UsernamePasswordAuthenticationFilter.class);
        return http.build();
    }
}
