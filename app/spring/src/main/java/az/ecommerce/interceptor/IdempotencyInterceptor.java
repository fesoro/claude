package az.ecommerce.interceptor;

import az.ecommerce.shared.application.middleware.IdempotencyMiddleware;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.slf4j.MDC;
import org.springframework.stereotype.Component;
import org.springframework.web.servlet.HandlerInterceptor;

/**
 * Laravel: IdempotencyMiddleware.php
 * X-Idempotency-Key header-i MDC-yə qoyur ki, IdempotencyMiddleware (CommandBus)
 * onu oxuya bilsin.
 */
@Component
public class IdempotencyInterceptor implements HandlerInterceptor {

    public static final String HEADER = "X-Idempotency-Key";

    @Override
    public boolean preHandle(HttpServletRequest req, HttpServletResponse res, Object handler) {
        String key = req.getHeader(HEADER);
        if (key != null && !key.isBlank()) {
            MDC.put(IdempotencyMiddleware.MDC_KEY, key);
        }
        return true;
    }

    @Override
    public void afterCompletion(HttpServletRequest req, HttpServletResponse res, Object h, Exception ex) {
        MDC.remove(IdempotencyMiddleware.MDC_KEY);
    }
}
