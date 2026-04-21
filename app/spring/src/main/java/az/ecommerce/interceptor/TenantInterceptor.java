package az.ecommerce.interceptor;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.slf4j.MDC;
import org.springframework.stereotype.Component;
import org.springframework.web.servlet.HandlerInterceptor;

/**
 * Laravel: TenantMiddleware.php
 * X-Tenant-ID header və ya subdomain-dən tenant-ı tap.
 * Hibernate multi-tenancy üçün TenantIdentifierResolver bunu MDC-dən oxuyur.
 */
@Component
public class TenantInterceptor implements HandlerInterceptor {
    public static final String MDC_KEY = "tenantId";
    @Override
    public boolean preHandle(HttpServletRequest req, HttpServletResponse res, Object handler) {
        String tenantId = req.getHeader("X-Tenant-ID");
        if (tenantId != null) MDC.put(MDC_KEY, tenantId);
        return true;
    }
    @Override
    public void afterCompletion(HttpServletRequest req, HttpServletResponse res, Object h, Exception ex) {
        MDC.remove(MDC_KEY);
    }
}
