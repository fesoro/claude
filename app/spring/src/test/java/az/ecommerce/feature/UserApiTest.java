package az.ecommerce.feature;

import com.fasterxml.jackson.databind.ObjectMapper;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.http.MediaType;
import org.springframework.security.test.context.support.WithMockUser;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.setup.MockMvcBuilders;
import org.springframework.web.context.WebApplicationContext;
import jakarta.annotation.PostConstruct;

import java.util.Map;
import java.util.UUID;

import static org.springframework.security.test.web.servlet.setup.SecurityMockMvcConfigurers.springSecurity;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

/**
 * Laravel: tests/Feature/UserApiTest.php
 *
 * User endpoint-lərini (register, GET /users/{id}) MockMvc ilə test edir.
 *
 * Yoxlananlar:
 * - Register: uğurlu, email duplifikasiyası, qısa şifrə, boş payload
 * - GET /users/{id}: mövcud istifadəçi, mövcud olmayan ID
 */
@SpringBootTest
@ActiveProfiles("test")
class UserApiTest {

    @Autowired
    private WebApplicationContext context;

    @Autowired
    private ObjectMapper objectMapper;

    private MockMvc mockMvc;

    @PostConstruct
    void setup() {
        mockMvc = MockMvcBuilders.webAppContextSetup(context)
                .apply(springSecurity()).build();
    }

    @Test
    void shouldRegisterUserSuccessfully() throws Exception {
        Map<String, String> body = Map.of(
                "name", "Test İstifadəçi",
                "email", "usertest1@example.com",
                "password", "password123"
        );

        mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().isCreated())
                .andExpect(jsonPath("$.success").value(true))
                .andExpect(jsonPath("$.data.user_id").exists())
                .andExpect(jsonPath("$.data.token").exists());
    }

    @Test
    void shouldRejectRegisterWithDuplicateEmail() throws Exception {
        Map<String, String> body = Map.of(
                "name", "İstifadəçi 1",
                "email", "duplicate_user@example.com",
                "password", "password123"
        );
        mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().isCreated());

        // Eyni email ilə ikinci qeydiyyat
        Map<String, String> body2 = Map.of(
                "name", "İstifadəçi 2",
                "email", "duplicate_user@example.com",
                "password", "password123"
        );
        mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body2)))
                .andExpect(status().is4xxClientError());
    }

    @Test
    void shouldRejectRegisterWithShortPassword() throws Exception {
        Map<String, String> body = Map.of(
                "name", "Test",
                "email", "shortpw@example.com",
                "password", "123"
        );

        mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().isBadRequest());
    }

    @Test
    void shouldRejectRegisterWithEmptyPayload() throws Exception {
        mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("{}"))
                .andExpect(status().isBadRequest());
    }

    @Test
    @WithMockUser
    void shouldGetUserByIdWhenAuthenticated() throws Exception {
        // Register → user_id al
        Map<String, String> body = Map.of(
                "name", "Orxan",
                "email", "orxan_get@example.com",
                "password", "password123"
        );
        String regResponse = mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().isCreated())
                .andReturn().getResponse().getContentAsString();

        String userId = objectMapper.readTree(regResponse).at("/data/user_id").asText();

        mockMvc.perform(get("/api/users/" + userId)
                        .header("Authorization", "Bearer " + objectMapper.readTree(regResponse).at("/data/token").asText()))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.success").value(true))
                .andExpect(jsonPath("$.data.id").value(userId));
    }

    @Test
    void shouldReturn404ForUserGetWithoutAuth() throws Exception {
        mockMvc.perform(get("/api/users/" + UUID.randomUUID()))
                .andExpect(status().isNotFound());
    }

    @Test
    @WithMockUser
    void shouldReturn404ForNonExistentUser() throws Exception {
        mockMvc.perform(get("/api/users/" + UUID.randomUUID()))
                .andExpect(status().isNotFound())
                .andExpect(jsonPath("$.success").value(false));
    }
}
