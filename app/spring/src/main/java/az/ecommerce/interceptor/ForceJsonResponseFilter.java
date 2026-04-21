package az.ecommerce.interceptor;

import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletRequestWrapper;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.core.annotation.Order;
import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;

import java.io.IOException;
import java.util.Collections;
import java.util.Enumeration;

/**
 * Laravel: ForceJsonResponse.php
 * Bütün API request-lərinə Accept: application/json əlavə edir.
 */
@Component
@Order(1)
public class ForceJsonResponseFilter extends OncePerRequestFilter {

    @Override
    protected void doFilterInternal(HttpServletRequest req, HttpServletResponse res, FilterChain chain)
            throws ServletException, IOException {
        if (req.getRequestURI().startsWith("/api/")) {
            chain.doFilter(new HttpServletRequestWrapper(req) {
                @Override
                public Enumeration<String> getHeaders(String name) {
                    if ("Accept".equalsIgnoreCase(name)) {
                        return Collections.enumeration(Collections.singletonList("application/json"));
                    }
                    return super.getHeaders(name);
                }
            }, res);
        } else {
            chain.doFilter(req, res);
        }
    }
}
