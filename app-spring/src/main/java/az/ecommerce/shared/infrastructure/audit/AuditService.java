package az.ecommerce.shared.infrastructure.audit;

import jakarta.servlet.http.HttpServletRequest;
import org.slf4j.MDC;
import org.springframework.scheduling.annotation.Async;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.stereotype.Service;

import java.util.UUID;

/**
 * Laravel: src/Shared/Infrastructure/Audit/AuditService.php
 *
 * Spring: AuditInterceptor request başa çatdıqdan sonra bunu çağırır.
 * @Async ilə performansa təsir etməsin deyə arxa fonda yazır.
 */
@Service
public class AuditService {

    private final AuditLogRepository repository;

    public AuditService(AuditLogRepository repository) {
        this.repository = repository;
    }

    @Async
    public void record(HttpServletRequest request, int responseStatus) {
        AuditLogEntity log = new AuditLogEntity();
        log.setMethod(request.getMethod());
        log.setUri(request.getRequestURI());
        log.setIpAddress(request.getRemoteAddr());
        log.setUserAgent(request.getHeader("User-Agent"));
        log.setResponseStatus(responseStatus);
        log.setCorrelationId(MDC.get("correlationId"));
        log.setAction(extractAction(request.getMethod()));
        log.setEntityType(extractEntityType(request.getRequestURI()));

        // Security context-dən user-i tap
        var auth = SecurityContextHolder.getContext().getAuthentication();
        if (auth != null && auth.isAuthenticated() && auth.getPrincipal() instanceof UUID userId) {
            log.setUserId(userId);
        }

        repository.save(log);
    }

    private String extractAction(String method) {
        return switch (method) {
            case "POST" -> "CREATE";
            case "PUT", "PATCH" -> "UPDATE";
            case "DELETE" -> "DELETE";
            default -> method;
        };
    }

    private String extractEntityType(String uri) {
        // /api/orders/123 → "orders"
        String[] parts = uri.split("/");
        return parts.length >= 3 ? parts[2] : null;
    }
}
