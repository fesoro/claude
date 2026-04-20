package az.ecommerce.config;

import az.ecommerce.interceptor.*;
import org.springframework.context.annotation.Configuration;
import org.springframework.web.servlet.config.annotation.CorsRegistry;
import org.springframework.web.servlet.config.annotation.InterceptorRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;

/**
 * Laravel: bootstrap/app.php-də middleware registrasiyası.
 * Spring: WebMvcConfigurer ilə interceptor-ları registr edirik.
 */
@Configuration
public class WebMvcConfig implements WebMvcConfigurer {

    private final CorrelationIdInterceptor correlationId;
    private final IdempotencyInterceptor idempotency;
    private final TenantInterceptor tenant;
    private final AuditInterceptor audit;
    private final ApiVersionInterceptor apiVersion;
    private final FeatureFlagInterceptor featureFlag;

    public WebMvcConfig(CorrelationIdInterceptor correlationId, IdempotencyInterceptor idempotency,
                        TenantInterceptor tenant, AuditInterceptor audit,
                        ApiVersionInterceptor apiVersion, FeatureFlagInterceptor featureFlag) {
        this.correlationId = correlationId;
        this.idempotency = idempotency;
        this.tenant = tenant;
        this.audit = audit;
        this.apiVersion = apiVersion;
        this.featureFlag = featureFlag;
    }

    @Override
    public void addInterceptors(InterceptorRegistry registry) {
        registry.addInterceptor(correlationId).addPathPatterns("/api/**");
        registry.addInterceptor(idempotency).addPathPatterns("/api/**");
        registry.addInterceptor(tenant).addPathPatterns("/api/**");
        registry.addInterceptor(audit).addPathPatterns("/api/**");
        registry.addInterceptor(apiVersion).addPathPatterns("/api/**");
        registry.addInterceptor(featureFlag).addPathPatterns("/api/**");
    }

    @Override
    public void addCorsMappings(CorsRegistry registry) {
        // Laravel: config/cors.php
        registry.addMapping("/api/**")
                .allowedOriginPatterns("*")
                .allowedMethods("GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS")
                .allowedHeaders("*")
                .exposedHeaders("X-Correlation-ID");
    }
}
