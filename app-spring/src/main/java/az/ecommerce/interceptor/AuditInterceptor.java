package az.ecommerce.interceptor;

import az.ecommerce.shared.infrastructure.audit.AuditService;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.stereotype.Component;
import org.springframework.web.servlet.HandlerInterceptor;

import java.util.Set;

/**
 * Laravel: AuditMiddleware.php
 * POST/PUT/PATCH/DELETE əməliyyatlarını audit_logs cədvəlinə yazır
 * (uğurlu — status < 500).
 */
@Component
public class AuditInterceptor implements HandlerInterceptor {

    private static final Set<String> AUDIT_METHODS = Set.of("POST", "PUT", "PATCH", "DELETE");

    private final AuditService auditService;

    public AuditInterceptor(AuditService auditService) {
        this.auditService = auditService;
    }

    @Override
    public void afterCompletion(HttpServletRequest req, HttpServletResponse res, Object h, Exception ex) {
        if (AUDIT_METHODS.contains(req.getMethod()) && res.getStatus() < 500) {
            auditService.record(req, res.getStatus());
        }
    }
}
