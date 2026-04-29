package az.ecommerce.feature;

import com.fasterxml.jackson.databind.ObjectMapper;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.http.MediaType;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.setup.MockMvcBuilders;
import org.springframework.web.context.WebApplicationContext;
import jakarta.annotation.PostConstruct;

import java.util.Map;

import static org.springframework.security.test.web.servlet.setup.SecurityMockMvcConfigurers.springSecurity;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

/**
 * Laravel: tests/Feature/AuthTest.php
 *
 * Spring: MockMvc ilə auth endpoint-lərini test edir.
 * H2 in-memory DB istifadə edir (application-test.yml).
 *
 * Test flow:
 *   1. Register → token al
 *   2. Login → token al
 *   3. Me → auth tələb edir
 *   4. Logout → JWT stateless, həmişə 200
 */
@SpringBootTest
@ActiveProfiles("test")
class AuthApiTest {

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
    void shouldRegisterNewUser() throws Exception {
        Map<String, String> body = Map.of(
                "name", "Test İstifadəçi",
                "email", "newuser@example.com",
                "password", "password123"
        );

        mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().isCreated())
                .andExpect(jsonPath("$.success").value(true))
                .andExpect(jsonPath("$.data.token").exists())
                .andExpect(jsonPath("$.data.user_id").exists());
    }

    @Test
    void shouldRejectRegisterWithInvalidEmail() throws Exception {
        Map<String, String> body = Map.of(
                "name", "Test",
                "email", "not-an-email",
                "password", "password123"
        );

        mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().isBadRequest());
    }

    @Test
    void shouldRejectRegisterWithShortPassword() throws Exception {
        Map<String, String> body = Map.of(
                "name", "Test",
                "email", "test2@example.com",
                "password", "123"
        );

        mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().isBadRequest());
    }

    @Test
    void shouldLoginAfterRegistration() throws Exception {
        // Register
        Map<String, String> registerBody = Map.of(
                "name", "Login Test",
                "email", "logintest@example.com",
                "password", "password123"
        );
        mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(registerBody)))
                .andExpect(status().isCreated());

        // Login
        Map<String, String> loginBody = Map.of(
                "email", "logintest@example.com",
                "password", "password123"
        );
        mockMvc.perform(post("/api/auth/login")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(loginBody)))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.success").value(true))
                .andExpect(jsonPath("$.data.token").exists());
    }

    @Test
    void shouldRejectLoginWithWrongPassword() throws Exception {
        Map<String, String> body = Map.of(
                "email", "unknown@example.com",
                "password", "wrongpassword"
        );

        mockMvc.perform(post("/api/auth/login")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().is4xxClientError());
    }

    @Test
    void shouldReturn401ForMeWithoutToken() throws Exception {
        mockMvc.perform(get("/api/auth/me"))
                .andExpect(status().isUnauthorized());
    }

    @Test
    void shouldLogoutSuccessfully() throws Exception {
        // Register et, token al
        Map<String, String> registerBody = Map.of(
                "name", "Logout Test",
                "email", "logouttest@example.com",
                "password", "password123"
        );
        String response = mockMvc.perform(post("/api/auth/register")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(registerBody)))
                .andExpect(status().isCreated())
                .andReturn().getResponse().getContentAsString();

        String token = objectMapper.readTree(response).at("/data/token").asText();

        // Logout
        mockMvc.perform(post("/api/auth/logout")
                        .header("Authorization", "Bearer " + token))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.success").value(true));
    }
}
