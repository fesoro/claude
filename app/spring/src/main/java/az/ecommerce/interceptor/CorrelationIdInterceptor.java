package az.ecommerce.interceptor;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.slf4j.MDC;
import org.springframework.stereotype.Component;
import org.springframework.web.servlet.HandlerInterceptor;

import java.util.UUID;

/**
 * Laravel: CorrelationIdMiddleware.php
 * X-Correlation-ID header oxuyur (yoxdursa generate edir), MDC-yə yerləşdirir.
 * Bütün log-larda correlation_id görünəcək (logback-spring.xml-də konfiqurasiya).
 */
@Component
public class CorrelationIdInterceptor implements HandlerInterceptor {

    public static final String HEADER = "X-Correlation-ID";
    public static final String MDC_KEY = "correlationId";

    @Override
    public boolean preHandle(HttpServletRequest request, HttpServletResponse response, Object handler) {
        String id = request.getHeader(HEADER);
        if (id == null || id.isBlank()) id = UUID.randomUUID().toString();
        MDC.put(MDC_KEY, id);
        response.setHeader(HEADER, id);
        return true;
    }

    @Override
    public void afterCompletion(HttpServletRequest req, HttpServletResponse res, Object h, Exception ex) {
        MDC.remove(MDC_KEY);
    }
}
