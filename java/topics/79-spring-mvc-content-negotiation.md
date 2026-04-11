# Spring MVC Content Negotiation — Geniş İzah

## Mündəricat
1. [Content Negotiation nədir?](#content-negotiation-nədir)
2. [Accept header](#accept-header)
3. [produces/consumes](#producesconsumes)
4. [HttpMessageConverter](#httpmessageconverter)
5. [Jackson konfiqurasiyası](#jackson-konfiqurasiyası)
6. [Custom MessageConverter](#custom-messageconverter)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Content Negotiation nədir?

**Content Negotiation** — client (browser, mobile app) hansı formatda cavab almaq istədiyini server-ə bildirdiyi mexanizmdir. Server uyğun formatda cavab qaytarır.

```
Client:  GET /api/users
         Accept: application/json

Server:  HTTP/1.1 200 OK
         Content-Type: application/json
         [{"id":1,"name":"Ali"}]

---

Client:  GET /api/users
         Accept: application/xml

Server:  HTTP/1.1 200 OK
         Content-Type: application/xml
         <users><user><id>1</id><name>Ali</name></user></users>
```

---

## Accept header

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    // Accept: application/json → JSON cavab
    // Accept: application/xml → XML cavab (Jackson XML lazımdır)
    @GetMapping
    public List<User> getUsers() {
        return userService.findAll();
    }

    // Yalnız JSON qəbul edir
    @GetMapping(produces = MediaType.APPLICATION_JSON_VALUE)
    public List<User> getUsersJson() {
        return userService.findAll();
    }

    // Birden çox format
    @GetMapping(
        value = "/export",
        produces = {
            MediaType.APPLICATION_JSON_VALUE,
            "text/csv",
            MediaType.APPLICATION_XML_VALUE
        }
    )
    public ResponseEntity<?> exportUsers(
            @RequestHeader(value = "Accept", defaultValue = "application/json")
            String acceptHeader) {

        List<User> users = userService.findAll();

        if (acceptHeader.contains("text/csv")) {
            String csv = convertToCsv(users);
            return ResponseEntity.ok()
                .contentType(MediaType.parseMediaType("text/csv"))
                .body(csv);
        }

        return ResponseEntity.ok(users);
    }
}
```

---

## produces/consumes

```java
@RestController
@RequestMapping("/api")
public class ApiController {

    // produces — cavab formatı
    @GetMapping(value = "/data",
                produces = MediaType.APPLICATION_JSON_VALUE)
    public Data getData() {
        return new Data("məlumat");
    }

    // consumes — gələn request formatı
    @PostMapping(value = "/upload",
                 consumes = MediaType.MULTIPART_FORM_DATA_VALUE)
    public String uploadFile(@RequestParam MultipartFile file) {
        return "Fayl qəbul edildi: " + file.getOriginalFilename();
    }

    // Request JSON, response XML
    @PostMapping(
        value = "/convert",
        consumes = MediaType.APPLICATION_JSON_VALUE,
        produces = MediaType.APPLICATION_XML_VALUE
    )
    public UserXml convertUser(@RequestBody UserJson userJson) {
        return new UserXml(userJson.getName(), userJson.getEmail());
    }

    // Bir neçə format
    @PostMapping(
        value = "/users",
        consumes = {
            MediaType.APPLICATION_JSON_VALUE,
            MediaType.APPLICATION_XML_VALUE
        },
        produces = MediaType.APPLICATION_JSON_VALUE
    )
    public User createUser(@RequestBody User user) {
        return userService.save(user);
    }
}
```

---

## HttpMessageConverter

Spring MVC HTTP body-ni Java obyektinə və əksinə çevirən interfeys:

```java
// Built-in converter-lər:
// MappingJackson2HttpMessageConverter — JSON ↔ Java
// StringHttpMessageConverter — String ↔ text/plain
// ByteArrayHttpMessageConverter — byte[] ↔ application/octet-stream
// ResourceHttpMessageConverter — Resource ↔ binary
// MappingJackson2XmlHttpMessageConverter — XML ↔ Java (jackson-dataformat-xml lazımdır)
// FormHttpMessageConverter — form data ↔ MultiValueMap

// Mövcud converter-ləri görmək
@SpringBootApplication
public class App {
    public static void main(String[] args) {
        ConfigurableApplicationContext ctx = SpringApplication.run(App.class, args);
        RequestMappingHandlerAdapter adapter =
            ctx.getBean(RequestMappingHandlerAdapter.class);
        adapter.getMessageConverters()
            .forEach(c -> System.out.println(c.getClass().getSimpleName()));
    }
}
```

---

## Jackson konfiqurasiyası

```java
@Configuration
public class JacksonConfig {

    @Bean
    @Primary
    public ObjectMapper objectMapper() {
        ObjectMapper mapper = new ObjectMapper();

        // Null field-ləri JSON-a daxil etmə
        mapper.setSerializationInclusion(JsonInclude.Include.NON_NULL);

        // Tarix formatı
        mapper.disable(SerializationFeature.WRITE_DATES_AS_TIMESTAMPS);
        mapper.registerModule(new JavaTimeModule());

        // Bilinməyən field-ləri ignore et (gələn JSON-da artıq field olarsa)
        mapper.configure(DeserializationFeature.FAIL_ON_UNKNOWN_PROPERTIES, false);

        // Boş siniflərdə xəta çıxarma
        mapper.configure(SerializationFeature.FAIL_ON_EMPTY_BEANS, false);

        // Snake case
        mapper.setPropertyNamingStrategy(PropertyNamingStrategies.SNAKE_CASE);

        // Pretty print (development üçün)
        // mapper.enable(SerializationFeature.INDENT_OUTPUT);

        return mapper;
    }
}
```

**application.yml ilə:**
```yaml
spring:
  jackson:
    serialization:
      write-dates-as-timestamps: false
      indent-output: false
    deserialization:
      fail-on-unknown-properties: false
    default-property-inclusion: non_null
    property-naming-strategy: SNAKE_CASE
    time-zone: UTC
    date-format: yyyy-MM-dd'T'HH:mm:ss
```

**Jackson annotasiyaları:**
```java
@JsonIgnoreProperties(ignoreUnknown = true)
public class UserDto {

    @JsonProperty("user_id")      // JSON field adını dəyişdirmək
    private Long id;

    @JsonIgnore                    // Bu field-i ignore et
    private String password;

    @JsonInclude(JsonInclude.Include.NON_NULL)  // Null olsa daxil etmə
    private String middleName;

    @JsonFormat(pattern = "yyyy-MM-dd HH:mm:ss")  // Tarix formatı
    private LocalDateTime createdAt;

    @JsonSerialize(using = CustomSerializer.class)  // Custom serializer
    private BigDecimal price;
}
```

---

## Custom MessageConverter

```java
// CSV format üçün custom converter
public class CsvHttpMessageConverter extends AbstractHttpMessageConverter<List<?>> {

    public CsvHttpMessageConverter() {
        super(MediaType.parseMediaType("text/csv"));
    }

    @Override
    protected boolean supports(Class<?> clazz) {
        return List.class.isAssignableFrom(clazz);
    }

    @Override
    protected List<?> readInternal(Class<? extends List<?>> clazz,
                                   HttpInputMessage inputMessage)
            throws IOException, HttpMessageNotReadableException {
        // CSV-dən oxuma (optional)
        throw new UnsupportedOperationException("CSV reading not supported");
    }

    @Override
    protected void writeInternal(List<?> list,
                                 HttpOutputMessage outputMessage)
            throws IOException, HttpMessageNotWritableException {

        try (PrintWriter writer = new PrintWriter(
                new OutputStreamWriter(outputMessage.getBody()))) {

            if (!list.isEmpty()) {
                Object first = list.get(0);
                // Header — field adları
                Field[] fields = first.getClass().getDeclaredFields();
                writer.println(Arrays.stream(fields)
                    .map(Field::getName)
                    .collect(Collectors.joining(",")));

                // Data
                for (Object item : list) {
                    writer.println(Arrays.stream(fields)
                        .map(f -> {
                            f.setAccessible(true);
                            try {
                                Object val = f.get(item);
                                return val != null ? val.toString() : "";
                            } catch (IllegalAccessException e) {
                                return "";
                            }
                        })
                        .collect(Collectors.joining(",")));
                }
            }
        }
    }
}

// Qeydiyyat
@Configuration
public class WebConfig implements WebMvcConfigurer {

    @Override
    public void extendMessageConverters(List<HttpMessageConverter<?>> converters) {
        converters.add(new CsvHttpMessageConverter());
    }
}

// İstifadəsi
@GetMapping(value = "/users/export",
            produces = "text/csv")
public List<User> exportUsers() {
    return userService.findAll();
    // CsvHttpMessageConverter avtomatik çevirir
}
```

**ResponseBodyAdvice — bütün cavabları dəyişdirmək:**

```java
@ControllerAdvice
public class ApiResponseWrapper implements ResponseBodyAdvice<Object> {

    @Override
    public boolean supports(MethodParameter returnType,
                           Class<? extends HttpMessageConverter<?>> converterType) {
        // Yalnız JSON converter üçün
        return converterType.isAssignableFrom(
            MappingJackson2HttpMessageConverter.class);
    }

    @Override
    public Object beforeBodyWrite(Object body,
                                  MethodParameter returnType,
                                  MediaType selectedContentType,
                                  Class<? extends HttpMessageConverter<?>> selectedConverterType,
                                  ServerHttpRequest request,
                                  ServerHttpResponse response) {

        // Bütün cavabları standart formata sar
        if (body instanceof ErrorResponse) {
            return body; // Error response-u olduğu kimi burax
        }

        return new ApiResponse<>(true, body, null);
    }
}

// Standart cavab formatı
public record ApiResponse<T>(boolean success, T data, String error) {}
```

---

## İntervyu Sualları

### 1. Content Negotiation Spring-də necə işləyir?
**Cavab:** Client `Accept` header-ini göndərir (məs: `application/json`). Spring `ContentNegotiationManager` uyğun `HttpMessageConverter`-i seçir. Converter Java obyektini həmin formata çevirir.

### 2. HttpMessageConverter nədir?
**Cavab:** HTTP request body-ni Java obyektinə (`readInternal`), Java obyektini HTTP response body-ə (`writeInternal`) çevirən interfeys. Spring built-in converter-lar təqdim edir (Jackson JSON, XML, String, byte[]).

### 3. produces ilə consumes fərqi nədir?
**Cavab:** `produces` — endpoint-in qaytardığı format (response Content-Type). `consumes` — endpoint-in qəbul etdiyi format (request Content-Type). Hər ikisi `MediaType` dəyərləri alır.

### 4. Jackson-da bilinməyən field-ləri necə ignore etmək olar?
**Cavab:** `@JsonIgnoreProperties(ignoreUnknown = true)` sinfə əlavə etmək, ya da `ObjectMapper`-də `FAIL_ON_UNKNOWN_PROPERTIES=false` konfiqurasiyası.

### 5. Bütün API cavablarını ümumi formata salmaq üçün nə istifadə edilir?
**Cavab:** `ResponseBodyAdvice` interface-i. `@ControllerAdvice` ilə birlikdə bütün controller-lərin cavablarını intercept edib dəyişdirmək mümkündür.

*Son yenilənmə: 2026-04-10*
