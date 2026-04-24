# 085 — Spring @WebMvcTest — Geniş İzah
**Səviyyə:** İrəli


## Mündəricat
1. [@WebMvcTest nədir?](#webmvctest-nədir)
2. [MockMvc əsasları](#mockmvc-əsasları)
3. [Request yoxlamaları](#request-yoxlamaları)
4. [Response yoxlamaları](#response-yoxlamaları)
5. [Exception handling testi](#exception-handling-testi)
6. [Security ilə test](#security-ilə-test)
7. [File upload testi](#file-upload-testi)
8. [İntervyu Sualları](#intervyu-sualları)

---

## @WebMvcTest nədir?

`@WebMvcTest` — yalnız Spring MVC layer-i yükləyir (controller, filter, interceptor, validator). Service, Repository yüklənmir — `@MockBean` ilə əvəz edilir.

```java
// ─── Bütün controller-lər ────────────────────────────
@WebMvcTest
class AllControllersTest {
    // Bütün @RestController bean-ləri yüklənir
}

// ─── Bir controller ───────────────────────────────────
@WebMvcTest(OrderController.class)
class OrderControllerTest {

    @Autowired
    private MockMvc mockMvc;

    @Autowired
    private ObjectMapper objectMapper;

    @MockBean
    private OrderService orderService;  // Service mock-lanır

    @MockBean
    private OrderMapper orderMapper;    // Lazımlı bütün bean-lər

    // ─── GET /api/orders/{id} ─────────────────────────
    @Test
    void shouldGetOrderById() throws Exception {
        OrderResponse response = new OrderResponse(1L, "customer-1", "PENDING");
        when(orderService.findById(1L)).thenReturn(Optional.of(response));

        mockMvc.perform(get("/api/orders/1")
                .accept(MediaType.APPLICATION_JSON))
            .andExpect(status().isOk())
            .andExpect(content().contentType(MediaType.APPLICATION_JSON))
            .andExpect(jsonPath("$.id").value(1))
            .andExpect(jsonPath("$.customerId").value("customer-1"))
            .andExpect(jsonPath("$.status").value("PENDING"));
    }

    // ─── POST /api/orders ─────────────────────────────
    @Test
    void shouldCreateOrder() throws Exception {
        OrderRequest request = new OrderRequest("customer-1", List.of(
            new OrderItem("product-1", 2, new BigDecimal("49.99"))
        ));
        OrderResponse response = new OrderResponse(1L, "customer-1", "PENDING");

        when(orderService.createOrder(any())).thenReturn(response);

        mockMvc.perform(post("/api/orders")
                .contentType(MediaType.APPLICATION_JSON)
                .content(objectMapper.writeValueAsString(request)))
            .andExpect(status().isCreated())
            .andExpect(header().string("Location", containsString("/api/orders/1")))
            .andExpect(jsonPath("$.id").value(1));
    }

    // ─── DELETE /api/orders/{id} ──────────────────────
    @Test
    void shouldDeleteOrder() throws Exception {
        doNothing().when(orderService).deleteOrder(1L);

        mockMvc.perform(delete("/api/orders/1"))
            .andExpect(status().isNoContent());

        verify(orderService).deleteOrder(1L);
    }
}
```

---

## MockMvc əsasları

```java
@WebMvcTest(OrderController.class)
class MockMvcBasicsTest {

    @Autowired
    private MockMvc mockMvc;

    // ─── HTTP metodlar ────────────────────────────────
    @Test
    void httpMethods() throws Exception {
        mockMvc.perform(get("/api/orders"));
        mockMvc.perform(post("/api/orders"));
        mockMvc.perform(put("/api/orders/1"));
        mockMvc.perform(patch("/api/orders/1"));
        mockMvc.perform(delete("/api/orders/1"));
        mockMvc.perform(head("/api/orders"));
        mockMvc.perform(options("/api/orders"));
    }

    // ─── Request parametrləri ─────────────────────────
    @Test
    void requestParameters() throws Exception {
        mockMvc.perform(get("/api/orders")
                .param("status", "PENDING")
                .param("page", "0")
                .param("size", "20")
                .param("sort", "createdAt,desc"));
    }

    // ─── Path variables ───────────────────────────────
    @Test
    void pathVariables() throws Exception {
        mockMvc.perform(get("/api/customers/{customerId}/orders/{orderId}",
            "customer-1", 42L));
    }

    // ─── Request headers ──────────────────────────────
    @Test
    void requestHeaders() throws Exception {
        mockMvc.perform(get("/api/orders")
                .header("Authorization", "Bearer eyJhbGci...")
                .header("X-Request-ID", "req-123")
                .accept(MediaType.APPLICATION_JSON));
    }

    // ─── JSON request body ────────────────────────────
    @Test
    void jsonRequestBody() throws Exception {
        String json = """
            {
              "customerId": "customer-1",
              "items": [
                {"productId": "prod-1", "quantity": 2}
              ]
            }
            """;

        mockMvc.perform(post("/api/orders")
            .contentType(MediaType.APPLICATION_JSON)
            .content(json));
    }

    // ─── Result actions zənciri ───────────────────────
    @Test
    void resultActionsChaining() throws Exception {
        mockMvc.perform(get("/api/orders/1"))
            .andDo(print())           // Console-a print et
            .andExpect(status().isOk())
            .andReturn();             // MvcResult əldə et
    }

    // ─── MvcResult — daha dərin yoxlama ──────────────
    @Test
    void mvcResult() throws Exception {
        MvcResult result = mockMvc.perform(get("/api/orders/1"))
            .andExpect(status().isOk())
            .andReturn();

        String responseBody = result.getResponse().getContentAsString();
        String location = result.getResponse().getHeader("Location");
        int statusCode = result.getResponse().getStatus();

        assertNotNull(responseBody);
    }
}
```

---

## Request yoxlamaları

```java
@WebMvcTest(OrderController.class)
class RequestValidationTest {

    @Autowired
    private MockMvc mockMvc;

    @MockBean
    private OrderService orderService;

    // ─── @Valid ilə validation testi ─────────────────
    @Test
    void shouldReturn400WhenRequestIsInvalid() throws Exception {
        String invalidJson = """
            {
              "customerId": null,
              "items": []
            }
            """;

        mockMvc.perform(post("/api/orders")
                .contentType(MediaType.APPLICATION_JSON)
                .content(invalidJson))
            .andExpect(status().isBadRequest())
            .andExpect(jsonPath("$.errors").isArray())
            .andExpect(jsonPath("$.errors[*].field",
                hasItems("customerId", "items")));
    }

    // ─── Content type yoxlama ─────────────────────────
    @Test
    void shouldReturn415ForWrongContentType() throws Exception {
        mockMvc.perform(post("/api/orders")
                .contentType(MediaType.TEXT_PLAIN)
                .content("plain text"))
            .andExpect(status().isUnsupportedMediaType());
    }

    // ─── Required parameter yoxlama ──────────────────
    @Test
    void shouldReturn400ForMissingRequiredParam() throws Exception {
        // @RequestParam(required = true) parametri verilmədi
        mockMvc.perform(get("/api/orders/search"))
            // status verilmədikdə
            .andExpect(status().isBadRequest());
    }

    // ─── ArgumentCaptor ilə gələn request-i yoxla ────
    @Test
    void shouldPassCorrectRequestToService() throws Exception {
        ArgumentCaptor<OrderRequest> captor =
            ArgumentCaptor.forClass(OrderRequest.class);

        when(orderService.createOrder(captor.capture()))
            .thenReturn(new OrderResponse(1L, "customer-1", "PENDING"));

        String json = """
            {"customerId": "customer-1", "items": [{"productId": "p1", "quantity": 1}]}
            """;

        mockMvc.perform(post("/api/orders")
                .contentType(MediaType.APPLICATION_JSON)
                .content(json))
            .andExpect(status().isCreated());

        assertEquals("customer-1", captor.getValue().customerId());
        assertEquals(1, captor.getValue().items().size());
    }
}
```

---

## Response yoxlamaları

```java
@WebMvcTest(OrderController.class)
class ResponseAssertionTest {

    @Autowired
    private MockMvc mockMvc;

    @MockBean
    private OrderService orderService;

    // ─── Status ───────────────────────────────────────
    @Test
    void statusAssertions() throws Exception {
        mockMvc.perform(get("/api/orders/1"))
            .andExpect(status().isOk())              // 200
            .andExpect(status().is(200))             // literal
            .andExpect(status().is2xxSuccessful());  // 2xx range
    }

    // ─── JSON path ────────────────────────────────────
    @Test
    void jsonPathAssertions() throws Exception {
        when(orderService.getAllOrders()).thenReturn(List.of(
            new OrderResponse(1L, "customer-1", "PENDING"),
            new OrderResponse(2L, "customer-2", "CONFIRMED")
        ));

        mockMvc.perform(get("/api/orders"))
            .andExpect(jsonPath("$").isArray())
            .andExpect(jsonPath("$.length()").value(2))
            .andExpect(jsonPath("$[0].id").value(1))
            .andExpect(jsonPath("$[0].status").value("PENDING"))
            .andExpect(jsonPath("$[1].customerId").value("customer-2"))
            .andExpect(jsonPath("$[*].status",
                containsInAnyOrder("PENDING", "CONFIRMED")));
    }

    // ─── Header yoxlama ───────────────────────────────
    @Test
    void headerAssertions() throws Exception {
        mockMvc.perform(post("/api/orders")
                .contentType(MediaType.APPLICATION_JSON)
                .content("{}"))
            .andExpect(header().exists("Location"))
            .andExpect(header().string("Content-Type",
                containsString("application/json")))
            .andExpect(header().doesNotExist("X-Deprecated"));
    }

    // ─── Content yoxlama ──────────────────────────────
    @Test
    void contentAssertions() throws Exception {
        mockMvc.perform(get("/api/orders/1"))
            .andExpect(content().contentType(MediaType.APPLICATION_JSON))
            .andExpect(content().string(containsString("PENDING")))
            .andExpect(content().json("""
                {"id": 1, "status": "PENDING"}
                """));
    }

    // ─── Pagination response ──────────────────────────
    @Test
    void shouldReturnPagedOrders() throws Exception {
        Page<OrderResponse> page = new PageImpl<>(
            List.of(
                new OrderResponse(1L, "c1", "PENDING"),
                new OrderResponse(2L, "c2", "CONFIRMED")
            ),
            PageRequest.of(0, 10),
            2
        );

        when(orderService.findAll(any(Pageable.class))).thenReturn(page);

        mockMvc.perform(get("/api/orders")
                .param("page", "0")
                .param("size", "10"))
            .andExpect(status().isOk())
            .andExpect(jsonPath("$.content").isArray())
            .andExpect(jsonPath("$.content.length()").value(2))
            .andExpect(jsonPath("$.totalElements").value(2))
            .andExpect(jsonPath("$.totalPages").value(1))
            .andExpect(jsonPath("$.number").value(0));
    }
}
```

---

## Exception handling testi

```java
@WebMvcTest(OrderController.class)
class ExceptionHandlingTest {

    @Autowired
    private MockMvc mockMvc;

    @MockBean
    private OrderService orderService;

    // ─── 404 Not Found ────────────────────────────────
    @Test
    void shouldReturn404WhenOrderNotFound() throws Exception {
        when(orderService.findById(99L))
            .thenThrow(new OrderNotFoundException("Order tapılmadı: 99"));

        mockMvc.perform(get("/api/orders/99"))
            .andExpect(status().isNotFound())
            .andExpect(jsonPath("$.title").value("Not Found"))
            .andExpect(jsonPath("$.detail").value(containsString("99")));
    }

    // ─── 409 Conflict ─────────────────────────────────
    @Test
    void shouldReturn409WhenOrderAlreadyExists() throws Exception {
        when(orderService.createOrder(any()))
            .thenThrow(new DuplicateOrderException("Artıq mövcuddur"));

        mockMvc.perform(post("/api/orders")
                .contentType(MediaType.APPLICATION_JSON)
                .content(validOrderJson()))
            .andExpect(status().isConflict());
    }

    // ─── 422 Business logic error ─────────────────────
    @Test
    void shouldReturn422WhenBusinessRuleFails() throws Exception {
        when(orderService.cancelOrder(1L))
            .thenThrow(new OrderCannotBeCancelledException("SHIPPED sifariş ləğv edilə bilməz"));

        mockMvc.perform(post("/api/orders/1/cancel"))
            .andExpect(status().isUnprocessableEntity())
            .andExpect(jsonPath("$.detail").value(containsString("SHIPPED")));
    }

    // ─── 500 Internal Server Error ────────────────────
    @Test
    void shouldReturn500ForUnexpectedError() throws Exception {
        when(orderService.findById(anyLong()))
            .thenThrow(new RuntimeException("Gözlənilməz xəta"));

        mockMvc.perform(get("/api/orders/1"))
            .andExpect(status().isInternalServerError());
    }

    // ─── ProblemDetail (RFC 7807) yoxlama ─────────────
    @Test
    void shouldReturnProblemDetail() throws Exception {
        when(orderService.findById(99L))
            .thenThrow(new OrderNotFoundException("Order tapılmadı: 99"));

        mockMvc.perform(get("/api/orders/99"))
            .andExpect(status().isNotFound())
            .andExpect(jsonPath("$.type").value("about:blank"))
            .andExpect(jsonPath("$.title").value("Not Found"))
            .andExpect(jsonPath("$.status").value(404))
            .andExpect(jsonPath("$.detail").exists())
            .andExpect(jsonPath("$.instance").value("/api/orders/99"));
    }
}
```

---

## Security ilə test

```java
// ─── @WithMockUser ────────────────────────────────────
@WebMvcTest(OrderController.class)
@Import(SecurityConfig.class)
class SecurityTest {

    @Autowired
    private MockMvc mockMvc;

    @MockBean
    private OrderService orderService;

    @MockBean
    private UserDetailsService userDetailsService;

    // Autentifikasiyasız — 401
    @Test
    void shouldReturn401WhenNotAuthenticated() throws Exception {
        mockMvc.perform(get("/api/orders"))
            .andExpect(status().isUnauthorized());
    }

    // Autentifikasiyalı user
    @Test
    @WithMockUser(username = "ali@example.com", roles = {"USER"})
    void shouldReturnOrdersForAuthenticatedUser() throws Exception {
        when(orderService.getMyOrders(any())).thenReturn(List.of());

        mockMvc.perform(get("/api/orders"))
            .andExpect(status().isOk());
    }

    // Admin tələb olunan endpoint
    @Test
    @WithMockUser(roles = {"USER"})
    void shouldReturn403ForNonAdmin() throws Exception {
        mockMvc.perform(delete("/api/admin/orders/1"))
            .andExpect(status().isForbidden());
    }

    @Test
    @WithMockUser(roles = {"ADMIN"})
    void shouldAllowAdminToDeleteOrder() throws Exception {
        doNothing().when(orderService).adminDeleteOrder(1L);

        mockMvc.perform(delete("/api/admin/orders/1"))
            .andExpect(status().isNoContent());
    }

    // JWT token ilə test
    @Test
    void shouldAuthenticateWithJwtToken() throws Exception {
        String jwtToken = generateTestJwt("ali@example.com", List.of("ROLE_USER"));

        mockMvc.perform(get("/api/orders")
                .header("Authorization", "Bearer " + jwtToken))
            .andExpect(status().isOk());
    }
}
```

---

## File upload testi

```java
@WebMvcTest(FileController.class)
class FileUploadTest {

    @Autowired
    private MockMvc mockMvc;

    @MockBean
    private FileStorageService fileStorageService;

    @Test
    void shouldUploadFile() throws Exception {
        MockMultipartFile file = new MockMultipartFile(
            "file",                    // parametr adı
            "orders.csv",             // fayl adı
            "text/csv",               // content type
            "id,status\n1,PENDING".getBytes()  // content
        );

        when(fileStorageService.store(any()))
            .thenReturn("uploads/orders.csv");

        mockMvc.perform(multipart("/api/files/upload")
                .file(file)
                .param("description", "Order export"))
            .andExpect(status().isOk())
            .andExpect(jsonPath("$.path").value("uploads/orders.csv"));
    }

    @Test
    void shouldReturn400ForEmptyFile() throws Exception {
        MockMultipartFile emptyFile = new MockMultipartFile(
            "file", "", "text/csv", new byte[0]);

        mockMvc.perform(multipart("/api/files/upload")
                .file(emptyFile))
            .andExpect(status().isBadRequest());
    }

    @Test
    void shouldReturn415ForWrongFileType() throws Exception {
        MockMultipartFile wrongType = new MockMultipartFile(
            "file", "script.sh", "application/x-sh", "#!/bin/bash".getBytes());

        mockMvc.perform(multipart("/api/files/upload")
                .file(wrongType))
            .andExpect(status().isUnsupportedMediaType());
    }
}
```

---

## İntervyu Sualları

### 1. @WebMvcTest ilə @SpringBootTest fərqi?
**Cavab:** `@WebMvcTest` — yalnız MVC layer (controller, filter, interceptor, validator, Jackson). Service, Repository yüklənmir. Daha sürətli. `@SpringBootTest` — bütün ApplicationContext. `@WebMvcTest` controller-in davranışını (HTTP metodlar, status kodlar, JSON serialize) test etmək üçün; `@SpringBootTest` end-to-end inteqrasiya üçün.

### 2. MockMvc-nin TestRestTemplate-dən fərqi?
**Cavab:** `MockMvc` — real HTTP request göndərmir, servlet-in mock implementasiyasını istifadə edir; `@WebMvcTest` ilə işləyir; server tələb etmir. `TestRestTemplate` — real HTTP request göndərir, real TCP bağlantısı; `@SpringBootTest(webEnvironment=RANDOM_PORT)` tələb edir. MockMvc daha sürətli, TestRestTemplate isə real network stacki test edir.

### 3. jsonPath() sintaksisi necədir?
**Cavab:** JSONPath — JSON üçün XPath. `$.field` — root-da field; `$[0].field` — array elementi; `$.items.length()` — array uzunluğu; `$[*].status` — bütün statuslar; `$.customer.address.city` — nested field. `hasItems()`, `containsInAnyOrder()` kimi Hamcrest matcher-ləri ilə işləyir.

### 4. @WithMockUser nədir?
**Cavab:** Spring Security Test annotation-u. Real authentication olmadan test üçün mock user yaradır. `username`, `password`, `roles`, `authorities` parametrləri var. `@WithMockUser(roles="ADMIN")` — ROLE_ADMIN authority ilə SecurityContext-ə user əlavə edir. `@WithAnonymousUser` anonimliyi test edir.

### 5. MockMvc-də exception necə test edilir?
**Cavab:** Controller-in `@ExceptionHandler` ya da `@ControllerAdvice` exception-ı necə handle etdiyini test etmək üçün mock-dan exception atılır: `when(service.method()).thenThrow(new XxxException(...))`. Sonra `.andExpect(status().isXxx())` ilə HTTP status, `.andExpect(jsonPath("$.title"))` ilə error response body yoxlanılır. ProblemDetail (RFC 7807) Spring 6-da default error formatdır.

*Son yenilənmə: 2026-04-10*
