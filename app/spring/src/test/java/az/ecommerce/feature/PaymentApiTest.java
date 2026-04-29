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
 * Laravel: tests/Feature/PaymentApiTest.php
 *
 * Payment endpoint-lərini MockMvc ilə test edir.
 *
 * Strategy Pattern — payment_method sahəsinə görə gateway seçilir:
 *   credit_card → CreditCardGateway
 *   paypal → PayPalGateway
 *   bank_transfer → BankTransferGateway
 *
 * Yoxlananlar:
 * - Auth olmadan 401
 * - Boş payload → 400
 * - Yanlış payment_method → 400
 * - Yanlış currency → 400
 * - GET /payments/{id}: auth olmadan 401
 */
@SpringBootTest
@ActiveProfiles("test")
class PaymentApiTest {

    @Autowired
    private WebApplicationContext context;

    @Autowired
    private ObjectMapper objectMapper;

    private MockMvc mockMvc;
    private String authToken;
    private String orderId;
    private String userId;

    @PostConstruct
    void setup() {
        mockMvc = MockMvcBuilders.webAppContextSetup(context)
                .apply(springSecurity()).build();
    }

    @BeforeEach
    void registerUserAndCreateOrder() throws Exception {
        // Register
        String email = "pay_" + UUID.randomUUID() + "@example.com";
        String regResponse = mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(Map.of(
                                "name", "Payment Tester",
                                "email", email,
                                "password", "password123"
                        ))))
                .andExpect(status().isCreated())
                .andReturn().getResponse().getContentAsString();

        authToken = objectMapper.readTree(regResponse).at("/data/token").asText();
        userId = objectMapper.readTree(regResponse).at("/data/user_id").asText();

        // Product yarat
        String prodResponse = mockMvc.perform(post("/api/products")
                        .header("Authorization", "Bearer " + authToken)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(Map.of(
                                "name", "Test Məhsul",
                                "description", "Test",
                                "priceAmount", 1000,
                                "currency", "AZN",
                                "stockQuantity", 50
                        ))))
                .andExpect(status().isCreated())
                .andReturn().getResponse().getContentAsString();

        String productId = objectMapper.readTree(prodResponse).at("/data/id").asText();

        // Order yarat
        String orderResponse = mockMvc.perform(post("/api/orders")
                        .header("Authorization", "Bearer " + authToken)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(Map.of(
                                "userId", userId,
                                "items", List.of(Map.of(
                                        "productId", productId,
                                        "productName", "Test Məhsul",
                                        "unitPriceAmount", 1000,
                                        "currency", "AZN",
                                        "quantity", 1
                                )),
                                "currency", "AZN",
                                "address", Map.of(
                                        "street", "Test 1",
                                        "city", "Bakı",
                                        "zip", "AZ1000",
                                        "country", "AZ"
                                )
                        ))))
                .andExpect(status().isCreated())
                .andReturn().getResponse().getContentAsString();

        orderId = objectMapper.readTree(orderResponse).at("/data/id").asText();
    }

    @Test
    void shouldReturn401WhenProcessingPaymentWithoutAuth() throws Exception {
        mockMvc.perform(post("/api/payments/process")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(Map.of(
                                "orderId", UUID.randomUUID().toString(),
                                "userId", UUID.randomUUID().toString(),
                                "amount", 1000,
                                "currency", "AZN",
                                "method", "CREDIT_CARD"
                        ))))
                .andExpect(status().isUnauthorized());
    }

    @Test
    void shouldReturn400WithEmptyPayload() throws Exception {
        mockMvc.perform(post("/api/payments/process")
                        .header("Authorization", "Bearer " + authToken)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("{}"))
                .andExpect(status().isBadRequest());
    }

    @Test
    void shouldReturn400WithInvalidPaymentMethod() throws Exception {
        mockMvc.perform(post("/api/payments/process")
                        .header("Authorization", "Bearer " + authToken)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(Map.of(
                                "orderId", orderId,
                                "userId", userId,
                                "amount", 1000,
                                "currency", "AZN",
                                "method", "BITCOIN"
                        ))))
                .andExpect(status().isBadRequest());
    }

    @Test
    void shouldReturn400WithInvalidCurrency() throws Exception {
        mockMvc.perform(post("/api/payments/process")
                        .header("Authorization", "Bearer " + authToken)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(Map.of(
                                "orderId", orderId,
                                "userId", userId,
                                "amount", 1000,
                                "currency", "GBP",
                                "method", "CREDIT_CARD"
                        ))))
                .andExpect(status().isBadRequest());
    }

    @Test
    void shouldReturn401WhenViewingPaymentWithoutAuth() throws Exception {
        mockMvc.perform(get("/api/payments/" + UUID.randomUUID()))
                .andExpect(status().isUnauthorized());
    }

    @Test
    void shouldProcessPaymentSuccessfully() throws Exception {
        mockMvc.perform(post("/api/payments/process")
                        .header("Authorization", "Bearer " + authToken)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(Map.of(
                                "orderId", orderId,
                                "userId", userId,
                                "amount", 1000,
                                "currency", "AZN",
                                "method", "CREDIT_CARD"
                        ))))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.success").value(true))
                .andExpect(jsonPath("$.data.payment_id").exists());
    }
}
