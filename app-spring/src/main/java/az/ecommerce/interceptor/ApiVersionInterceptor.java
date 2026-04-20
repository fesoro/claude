package az.ecommerce.interceptor;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.slf4j.MDC;
import org.springframework.stereotype.Component;
import org.springframework.web.servlet.HandlerInterceptor;

/**
 * Laravel: ApiVersionMiddleware.php — X-API-Version header.
 * MDC-də saxlayır ki, transformer-lər versiyaya görə fərqli output verə bilsin.
 */
@Component
public class ApiVersionInterceptor implements HandlerInterceptor {
    @Override
    public boolean preHandle(HttpServletRequest req, HttpServletResponse res, Object handler) {
        String version = req.getHeader("X-API-Version");
        MDC.put("apiVersion", version != null ? version : "v2");
        return true;
    }
    @Override
    public void afterCompletion(HttpServletRequest req, HttpServletResponse res, Object h, Exception ex) {
        MDC.remove("apiVersion");
    }
}
