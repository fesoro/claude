package az.ecommerce.search;

import az.ecommerce.shared.infrastructure.api.ApiResponse;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.Map;

/**
 * Laravel: app/Http/Controllers/SearchController.php
 * Cross-context search — Product + Order birgə nəticələr.
 *
 * Real layihədə Elasticsearch və ya MeiliSearch istifadə olunur.
 */
@RestController
@RequestMapping("/api/search")
public class SearchController {

    @GetMapping
    public ApiResponse<Map<String, Object>> search(@RequestParam String q) {
        return ApiResponse.success(Map.of(
                "query", q,
                "products", List.of(),
                "orders", List.of()
        ));
    }
}
