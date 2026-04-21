package az.ecommerce.shared.infrastructure.featureflags;

import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.stereotype.Component;

import java.util.HashMap;
import java.util.Map;

/**
 * Laravel: src/Shared/Infrastructure/FeatureFlags/FeatureFlag.php
 *
 * Spring: @ConfigurationProperties — application.yml-də `app.features.*` map.
 *
 * NÜMUNƏ application.yml:
 *   app:
 *     features:
 *       new-checkout-flow: false
 *       advanced-search: true
 *
 * İstifadə:
 *   if (featureFlag.isEnabled("new-checkout-flow")) { ... }
 */
@Component
@ConfigurationProperties(prefix = "app")
public class FeatureFlag {

    private Map<String, Boolean> features = new HashMap<>();

    public boolean isEnabled(String name) {
        return features.getOrDefault(name, false);
    }

    public Map<String, Boolean> getFeatures() { return features; }
    public void setFeatures(Map<String, Boolean> features) { this.features = features; }
}
