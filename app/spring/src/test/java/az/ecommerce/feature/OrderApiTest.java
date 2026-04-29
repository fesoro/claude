package az.ecommerce.feature;

import com.fasterxml.jackson.databind.ObjectMapper;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.http.MediaType;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.setup.MockMvcBuilders;
import org.springframework.web.context.WebApplicationContext;
import jakarta.annotation.PostConstruct;

import java.util.List;
import java.util.Map;
import java.util.UUID;

import static org.springframework.security.test.web.servlet.setup.SecurityMockMvcConfigurers.springSecurity;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

/**
 * Laravel: tests/Feature/OrderApiTest.php
 *
 * Order endpoint-lərini tam HTTP layer-də test edir.
 * Hər test öncəsindən user register edirik və token alırıq.
 *
 * Test flow:
 *   1. Register user → JWT token al
 *   2. Create product → product ID al
 *   3. Create order → order ID al
 *   4. Get order, List orders, Cancel order
 */
@SpringBootTest
@ActiveProfiles("test")
class OrderApiTest {

    @Autowired
    private WebApplicationContext context;

    @Autowired
    private ObjectMapper objectMapper;

    private MockMvc mockMvc;
    private String authToken;
    private UUID productId;
    private UUID userId;

    @PostConstruct
    void setup() {
        mockMvc = MockMvcBuilders.webAppContextSetup(context)
                .apply(springSecurity()).build();
    }

    @BeforeEach
    void registerAndCreateProduct() throws Exception {
        // Her test üçün unikal user
        String email = "order_" + UUID.randomUUID() + "@example.com";
        Map<String, String> registerBody = Map.of(
                "name", "Order Tester",
                "email", email,
                "password", "password123"
        );
        String regResponse = mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(registerBody)))
                .andExpect(status().isCreated())
                .andReturn().getResponse().getContentAsString();

        authToken = objectMapper.readTree(regResponse).at("/data/token").asText();
        userId = UUID.fromString(objectMapper.readTree(regResponse).at("/data/user_id").asText());

        // Test məhsulu yarat
        Map<String, Object> productBody = Map.of(
                "name", "Test Məhsul",
                "description", "Test",
                "priceAmount", 1000,
                "currency", "AZN",
                "stockQuantity", 50
        );
        String prodResponse = mockMvc.perform(post("/api/products")
                        .header("Authorization", "Bearer " + authToken)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(productBody)))
                .andExpect(status().isCreated())
                .andReturn().getResponse().getContentAsString();

        String productIdStr = objectMapper.readTree(prodResponse).at("/data/id").asText();
        productId = UUID.fromString(productIdStr);
    }

    @Test
    void shouldCreateOrder() throws Exception {
        Map<String, Object> body = buildOrderBody();

        mockMvc.perform(post("/api/orders")
                        .header("Authorization", "Bearer " + authToken)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().isCreated())
                .andExpect(jsonPath("$.success").value(true))
                .andExpect(jsonPath("$.data.id").exists());
    }

    @Test
    void shouldGetOrderById() throws Exception {
        String orderId = createOrder();

        mockMvc.perform(get("/api/orders/" + orderId)
                        .header("Authorization", "Bearer " + authToken))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.success").value(true))
                .andExpect(jsonPath("$.data.id").value(orderId));
    }

    @Test
    void shouldListOrdersForCurrentUser() throws Exception {
        createOrder();

        mockMvc.perform(get("/api/orders/user/" + userId)
                        .header("Authorization", "Bearer " + authToken))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.success").value(true))
                .andExpect(jsonPath("$.data").isArray());
    }

    @Test
    void shouldCancelPendingOrder() throws Exception {
        String orderId = createOrder();

        mockMvc.perform(post("/api/orders/" + orderId + "/cancel")
                        .header("Authorization", "Bearer " + authToken))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.success").value(true));
    }

    @Test
    void shouldReturn401WhenCreatingOrderWithoutAuth() throws Exception {
        mockMvc.perform(post("/api/orders")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("{}"))
                .andExpect(status().isUnauthorized());
    }

    @Test
    void shouldReturn400WhenCreatingOrderWithEmptyItems() throws Exception {
        Map<String, Object> body = Map.of(
                "items", List.of(),
                "currency", "AZN",
                "shippingAddress", Map.of("street", "Test", "city", "Bakı", "country", "AZ", "zip", "AZ1000")
        );

        mockMvc.perform(post("/api/orders")
                        .header("Authorization", "Bearer " + authToken)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().isBadRequest());
    }

    // === Helper metodlar ===

    private Map<String, Object> buildOrderBody() {
        return Map.of(
                "userId", userId.toString(),
                "items", List.of(Map.of(
                        "productId", productId.toString(),
                        "productName", "Test Məhsul",
                        "unitPriceAmount", 1000,
                        "currency", "AZN",
                        "quantity", 2
                )),
                "currency", "AZN",
                "address", Map.of(
                        "street", "İstiqlaliyyət 5",
                        "city", "Bakı",
                        "zip", "AZ1000",
                        "country", "AZ"
                )
        );
    }

    private String createOrder() throws Exception {
        String response = mockMvc.perform(post("/api/orders")
                        .header("Authorization", "Bearer " + authToken)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(buildOrderBody())))
                .andExpect(status().isCreated())
                .andReturn().getResponse().getContentAsString();

        return objectMapper.readTree(response).at("/data/id").asText();
    }
}
