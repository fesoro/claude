package az.ecommerce.interceptor;

import az.ecommerce.shared.infrastructure.featureflags.FeatureFlag;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.stereotype.Component;
import org.springframework.web.servlet.HandlerInterceptor;

/** Laravel: FeatureFlagMiddleware — request attribute-ə FeatureFlag inject edir */
@Component
public class FeatureFlagInterceptor implements HandlerInterceptor {
    private final FeatureFlag featureFlag;
    public FeatureFlagInterceptor(FeatureFlag f) { this.featureFlag = f; }
    @Override
    public boolean preHandle(HttpServletRequest req, HttpServletResponse res, Object handler) {
        req.setAttribute("featureFlag", featureFlag);
        return true;
    }
}
