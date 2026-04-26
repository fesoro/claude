# 25 — Jackson JSON Serializasiya və Deserializasiya

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [Jackson nədir və Spring Boot-da niyə default-dur](#jackson-nedir)
2. [ObjectMapper əsasları](#objectmapper)
3. [Spring MVC ilə avtomatik inteqrasiya](#spring-mvc)
4. [Ən çox istifadə olunan annotasiyalar](#annotasiyalar)
5. [Tarix və zaman sahələri (JavaTimeModule)](#tarix)
6. [Polimorfik serializasiya](#polimorfik)
7. [Custom serializer/deserializer](#custom)
8. [Jackson2ObjectMapperBuilder və spring.jackson.\*](#konfiqurasiya)
9. [Tree model — JsonNode](#tree)
10. [Tam REST controller nümunəsi](#nümunə)
11. [Ümumi Səhvlər](#sehvler)
12. [İntervyu Sualları](#intervyu)

---

## 1. Jackson nədir və Spring Boot-da niyə default-dur {#jackson-nedir}

**Jackson** — Java üçün ən populyar JSON kitabxanasıdır. Spring Boot `spring-boot-starter-web` əlavə edildikdə Jackson avtomatik olaraq classpath-ə düşür.

```xml
<!-- pom.xml — spring-boot-starter-web Jackson-u tranzitiv olaraq gətirir -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
</dependency>
```

Starter-in içində gizli:

```
spring-boot-starter-web
 └── spring-boot-starter-json
      ├── jackson-databind          (əsas — ObjectMapper burdadır)
      ├── jackson-core              (low-level JSON parsing)
      ├── jackson-annotations       (@JsonProperty və s.)
      └── jackson-datatype-jsr310   (Java 8 time API üçün)
```

### Real həyat analogiyası:

Jackson **tərcüməçi** kimidir: Java obyektini (POJO) JSON mətninə və əksinə çevirir. Tərcüməçi olmadan server və frontend bir-birini başa düşməzdi.

| Proses | Java termini | Sinonimi |
|---|---|---|
| POJO → JSON | **Serializasiya** | marshalling, encoding |
| JSON → POJO | **Deserializasiya** | unmarshalling, decoding |

---

## 2. ObjectMapper əsasları {#objectmapper}

`ObjectMapper` — Jackson-un mərkəzi sinifidir. Spring Boot avtomatik olaraq tək `ObjectMapper` bean-ı yaradır və onu bütün tətbiqdə bölüşdürür.

### Əsas metodlar:

```java
import com.fasterxml.jackson.databind.ObjectMapper;

public class JacksonBasics {

    // Spring-də mapper-i inject etmək olar
    private final ObjectMapper mapper = new ObjectMapper();

    public void demo() throws Exception {

        User user = new User(1L, "Orxan", "orxan@example.com");

        // 1) Obyekt -> JSON string
        String json = mapper.writeValueAsString(user);
        // {"id":1,"name":"Orxan","email":"orxan@example.com"}

        // 2) JSON string -> Obyekt
        User back = mapper.readValue(json, User.class);

        // 3) Obyekt -> fayl
        mapper.writeValue(new File("user.json"), user);

        // 4) Obyekt -> OutputStream
        try (OutputStream os = new FileOutputStream("user.json")) {
            mapper.writeValue(os, user);
        }

        // 5) Pretty print (insana oxunaqlı format)
        String pretty = mapper.writerWithDefaultPrettyPrinter()
                              .writeValueAsString(user);

        // 6) List<T> deserializasiyası — TypeReference lazımdır
        String listJson = "[{\"id\":1,\"name\":\"A\"},{\"id\":2,\"name\":\"B\"}]";
        List<User> users = mapper.readValue(
            listJson,
            new TypeReference<List<User>>() {}  // generic tipi qoruyur
        );
    }
}
```

### POJO tələbləri:

```java
// Jackson POJO tələblər:
// 1) No-arg constructor (deserializasiya üçün)
// 2) Getter-lər (serializasiya üçün) VƏ YA public field-lər
// 3) Setter-lər və ya konstruktor (deserializasiya üçün)

public class User {
    private Long id;
    private String name;
    private String email;

    public User() {}  // MÜHÜM — no-arg constructor

    public User(Long id, String name, String email) {
        this.id = id;
        this.name = name;
        this.email = email;
    }

    // getter/setter-lər Jackson üçün vacibdir
    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getEmail() { return email; }
    public void setEmail(String email) { this.email = email; }
}
```

---

## 3. Spring MVC ilə avtomatik inteqrasiya {#spring-mvc}

Spring Boot `MappingJackson2HttpMessageConverter`-i avtomatik qeyd edir. Bunun sayəsində siz `ObjectMapper`-ı birbaşa istifadə etmirsiniz — `@RequestBody` və `@ResponseBody` bunu sizin üçün edir.

```java
@RestController  // @Controller + @ResponseBody — hər metod avtomatik JSON qaytarır
@RequestMapping("/api/users")
public class UserController {

    @GetMapping("/{id}")
    public User getUser(@PathVariable Long id) {
        // User obyektini qaytarırıq, Spring onu avtomatik JSON-a çevirir
        return new User(id, "Orxan", "orxan@example.com");
    }

    @PostMapping
    public User createUser(@RequestBody User user) {
        // Gələn JSON avtomatik User obyektinə deserializasiya olunur
        return user;
    }
}
```

### Mətnarxası ardıcıllıq:

```
Client -> POST /api/users {"name":"Orxan"}
   |
   v
DispatcherServlet
   |
   v
MappingJackson2HttpMessageConverter  <-- Jackson burada işləyir
   |  (ObjectMapper.readValue ilə User obyektinə çevirir)
   v
UserController.createUser(User user)
   |
   v
return user  -- Jackson writeValueAsString ilə JSON-a çevirir
   |
   v
Client <- HTTP 200 {"id":null,"name":"Orxan"}
```

### @ResponseBody və @RequestBody:

```java
@Controller  // @RestController deyil — bu halda @ResponseBody lazımdır
public class LegacyController {

    @PostMapping("/users")
    @ResponseBody  // Cavabı JSON kimi qaytar (view name olaraq deyil)
    public User create(@RequestBody User user) {  // Body-ni User-a çevir
        return user;
    }
}
```

---

## 4. Ən çox istifadə olunan annotasiyalar {#annotasiyalar}

### @JsonProperty — ad dəyişdirmək

```java
public class User {

    @JsonProperty("user_id")  // Java field: id, JSON: user_id
    private Long id;

    @JsonProperty("full_name")
    private String name;

    // JSON output: {"user_id":1,"full_name":"Orxan"}
}
```

### @JsonIgnore — serializasiyadan çıxarmaq

```java
public class User {
    private Long id;
    private String name;

    @JsonIgnore  // JSON-da görünməz, oxunmaz
    private String password;

    // Nəticə: {"id":1,"name":"Orxan"} — password yoxdur
}
```

### @JsonIgnoreProperties — sinif səviyyəsində

```java
// İstənməyən field-ləri sinif səviyyəsində qeyd et
@JsonIgnoreProperties({"password", "internalCode"})
public class User {
    private Long id;
    private String name;
    private String password;      // çıxarılır
    private String internalCode;  // çıxarılır
}

// Naməlum field-ləri ignore et (çox faydalıdır!)
@JsonIgnoreProperties(ignoreUnknown = true)
public class User {
    private Long id;
    private String name;
    // JSON-da extra field gəlsə də xəta verməz
}
```

### @JsonInclude — null-ları çıxar

```java
// null dəyərləri serializasiyadan tamamilə çıxar
@JsonInclude(JsonInclude.Include.NON_NULL)
public class User {
    private Long id;
    private String name;
    private String email;  // null olsa JSON-da görünməz
}

// User(1, "Orxan", null) -> {"id":1,"name":"Orxan"}

// Boş kolleksiyaları da çıxar
@JsonInclude(JsonInclude.Include.NON_EMPTY)
public class Response {
    private List<String> items;  // boş olsa çıxarılır
}
```

### Qlobal olaraq application.properties-də:

```properties
# Bütün JSON cavablarda null-ları çıxar
spring.jackson.default-property-inclusion=non_null

# Pretty print (inkişaf mühitində faydalıdır)
spring.jackson.serialization.indent-output=true

# Naməlum field-lərdə xəta vermə
spring.jackson.deserialization.fail-on-unknown-properties=false
```

### @JsonFormat — format təyini

```java
public class Event {

    @JsonFormat(shape = JsonFormat.Shape.STRING, pattern = "yyyy-MM-dd HH:mm:ss")
    private LocalDateTime createdAt;

    @JsonFormat(shape = JsonFormat.Shape.STRING, pattern = "#0.00")
    private BigDecimal price;
}

// Çıxış: {"createdAt":"2026-04-24 10:30:00","price":"19.99"}
```

### @JsonCreator + @JsonProperty — immutable obyektlər üçün

```java
// Final field-lərlə immutable sinif
public class User {
    private final Long id;
    private final String name;

    @JsonCreator  // Jackson-a de: "deserializasiya üçün bu konstruktoru istifadə et"
    public User(
        @JsonProperty("id") Long id,
        @JsonProperty("name") String name
    ) {
        this.id = id;
        this.name = name;
    }

    public Long getId() { return id; }
    public String getName() { return name; }
}
```

### @JsonAlias — çoxlu ad qəbul et

```java
public class User {
    // Deserializasiyada hər üç ad qəbul olunur; serializasiyada "name" yazılır
    @JsonAlias({"fullName", "full_name", "nickname"})
    private String name;
}
```

### @JsonView — view əsaslı filtr

```java
public class Views {
    public static class Public {}
    public static class Internal extends Public {}
}

public class User {
    @JsonView(Views.Public.class)
    private Long id;

    @JsonView(Views.Public.class)
    private String name;

    @JsonView(Views.Internal.class)  // yalnız Internal view-də görünür
    private String email;
}

@RestController
public class UserController {

    @GetMapping("/public/{id}")
    @JsonView(Views.Public.class)  // yalnız public field-lər qaytarılır
    public User publicView(@PathVariable Long id) { /* ... */ }

    @GetMapping("/admin/{id}")
    @JsonView(Views.Internal.class)  // hamısı qaytarılır
    public User internalView(@PathVariable Long id) { /* ... */ }
}
```

### @JsonUnwrapped — iç-içə obyekti yastı et

```java
public class Address {
    private String city;
    private String street;
}

public class User {
    private String name;

    @JsonUnwrapped
    private Address address;
}

// Normal (unwrapped olmadan): {"name":"Orxan","address":{"city":"Bakı","street":"Nizami"}}
// @JsonUnwrapped ilə:         {"name":"Orxan","city":"Bakı","street":"Nizami"}
```

### @JsonManagedReference + @JsonBackReference — dövrləri kəs

```java
// Dövrü əlaqə problemi:
// User-in Order-ləri var, Order-in User-i var -> sonsuz döngü!

public class User {
    private Long id;
    private String name;

    @JsonManagedReference  // bu tərəf serializasiya olunur
    private List<Order> orders;
}

public class Order {
    private Long id;
    private BigDecimal amount;

    @JsonBackReference  // bu tərəf IGNORE olunur — dövrü kəsir
    private User user;
}

// Nəticə: {"id":1,"name":"Orxan","orders":[{"id":10,"amount":100}]}
// Order içində "user" field-i JSON-a daxil olmur
```

---

## 5. Tarix və zaman sahələri (JavaTimeModule) {#tarix}

Köhnə `Date` yox, Java 8 `LocalDateTime`, `LocalDate`, `Instant` istifadə edin. Bunun üçün `JavaTimeModule` lazımdır.

```java
// Problem: default olaraq LocalDateTime "saatlar massivi" kimi serializasiya olunur
// {"createdAt":[2026,4,24,10,30,0]}  -- YANLIŞ, çirkin

// Həll: JavaTimeModule qeyd etmək
@Configuration
public class JacksonConfig {

    @Bean
    public ObjectMapper objectMapper() {
        ObjectMapper mapper = new ObjectMapper();
        mapper.registerModule(new JavaTimeModule());
        // ISO-8601 formatında yazsın — "2026-04-24T10:30:00" kimi
        mapper.disable(SerializationFeature.WRITE_DATES_AS_TIMESTAMPS);
        return mapper;
    }
}
```

### Spring Boot-da daha asan yol — properties:

```properties
# application.properties
# Tarixləri timestamp (millis) kimi deyil, ISO-8601 string kimi yaz
spring.jackson.serialization.write-dates-as-timestamps=false

# Standart zaman zonası
spring.jackson.time-zone=Asia/Baku

# Default tarix formatı (istəsən)
spring.jackson.date-format=yyyy-MM-dd'T'HH:mm:ss.SSSXXX
```

### Konkret field üçün format:

```java
public class Event {

    // ISO-8601 — ən yaxşı seçim
    private LocalDateTime createdAt;  // "2026-04-24T10:30:00"

    @JsonFormat(pattern = "dd.MM.yyyy", timezone = "Asia/Baku")
    private LocalDate birthday;  // "24.04.2026"

    @JsonFormat(pattern = "yyyy-MM-dd HH:mm:ss", timezone = "UTC")
    private Instant timestamp;
}
```

### Timezone tələsi:

```java
// YANLIŞ — timezone qeyd edilməyib, default JVM timezone-u götürür
private LocalDateTime serverTime;

// DOĞRU — explicit timezone istifadə et
private ZonedDateTime serverTime;  // zonası JSON-da saxlanır
// və ya
private Instant serverTime;        // UTC — heç vaxt qarışmır
```

---

## 6. Polimorfik serializasiya {#polimorfik}

Abstrakt sinif və ya interfeysi JSON-a çevirərkən tipi qeyd etmək lazımdır.

```java
// Əsas sinif
@JsonTypeInfo(
    use = JsonTypeInfo.Id.NAME,     // sinfin adı ilə fərqləndir
    include = JsonTypeInfo.As.PROPERTY,
    property = "type"                // JSON-da "type" field-i əlavə olunur
)
@JsonSubTypes({
    @JsonSubTypes.Type(value = Dog.class, name = "dog"),
    @JsonSubTypes.Type(value = Cat.class, name = "cat")
})
public abstract class Animal {
    protected String name;
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
}

public class Dog extends Animal {
    private String breed;
    public String getBreed() { return breed; }
    public void setBreed(String breed) { this.breed = breed; }
}

public class Cat extends Animal {
    private int lives;
    public int getLives() { return lives; }
    public void setLives(int lives) { this.lives = lives; }
}
```

```json
// Serializasiya nəticəsi:
{"type":"dog","name":"Rex","breed":"Labrador"}
{"type":"cat","name":"Whiskers","lives":9}
```

Deserializasiya zamanı Jackson "type" field-inə baxır və düzgün sinfi qurur.

---

## 7. Custom serializer/deserializer {#custom}

Bəzən standart davranış kifayət etmir.

```java
// Custom serializer — Money obyektini "19.99 USD" kimi yaz
public class MoneySerializer extends JsonSerializer<Money> {

    @Override
    public void serialize(Money value, JsonGenerator gen,
                          SerializerProvider serializers) throws IOException {
        gen.writeString(value.getAmount() + " " + value.getCurrency());
    }
}

// Custom deserializer — "19.99 USD" string-ini Money obyektinə çevir
public class MoneyDeserializer extends JsonDeserializer<Money> {

    @Override
    public Money deserialize(JsonParser p, DeserializationContext ctxt)
            throws IOException {
        String text = p.getText();          // "19.99 USD"
        String[] parts = text.split(" ");
        return new Money(new BigDecimal(parts[0]), parts[1]);
    }
}

// Sinifə birləşdir
public class Product {
    private String name;

    @JsonSerialize(using = MoneySerializer.class)
    @JsonDeserialize(using = MoneyDeserializer.class)
    private Money price;
}
```

### Qlobal qeyd etmək:

```java
@Configuration
public class JacksonConfig {

    @Bean
    public Module moneyModule() {
        SimpleModule module = new SimpleModule();
        module.addSerializer(Money.class, new MoneySerializer());
        module.addDeserializer(Money.class, new MoneyDeserializer());
        return module;
    }
}
// Spring Boot bu Module-u avtomatik ObjectMapper-a əlavə edəcək
```

---

## 8. Jackson2ObjectMapperBuilder və spring.jackson.\* {#konfiqurasiya}

Spring Boot `Jackson2ObjectMapperBuilder` təqdim edir — mapper-ı customize etməyin ən təmiz yolu.

```java
@Configuration
public class JacksonConfig {

    @Bean
    public Jackson2ObjectMapperBuilderCustomizer customizer() {
        return builder -> builder
            .failOnUnknownProperties(false)
            .serializationInclusion(JsonInclude.Include.NON_NULL)
            .modules(new JavaTimeModule())
            .featuresToDisable(SerializationFeature.WRITE_DATES_AS_TIMESTAMPS)
            .timeZone("Asia/Baku");
    }
}
```

### Spring Boot property-ləri (tam siyahı):

```properties
# Serializasiya
spring.jackson.serialization.indent-output=true
spring.jackson.serialization.write-dates-as-timestamps=false
spring.jackson.serialization.write-enums-using-to-string=true

# Deserializasiya
spring.jackson.deserialization.fail-on-unknown-properties=false
spring.jackson.deserialization.accept-single-value-as-array=true

# Property inclusion
spring.jackson.default-property-inclusion=non_null
# always, non_null, non_absent, non_default, non_empty

# Naming strategy
spring.jackson.property-naming-strategy=SNAKE_CASE
# KEBAB_CASE, LOWER_CAMEL_CASE, UPPER_CAMEL_CASE

# Timezone və tarix
spring.jackson.time-zone=UTC
spring.jackson.date-format=yyyy-MM-dd'T'HH:mm:ss.SSSXXX
```

### Naming strategy — snake_case nümunəsi:

```java
public class User {
    private String firstName;  // Java camelCase
    private String lastName;
}

// spring.jackson.property-naming-strategy=SNAKE_CASE ilə:
// {"first_name":"Orxan","last_name":"Mammadov"}
```

---

## 9. Tree model — JsonNode {#tree}

Bəzən POJO yox, sadəcə JSON strukturunu oxumaq lazımdır.

```java
String json = """
    {
        "user": {
            "id": 1,
            "name": "Orxan",
            "roles": ["admin", "user"]
        }
    }
    """;

ObjectMapper mapper = new ObjectMapper();
JsonNode root = mapper.readTree(json);

// Tree-dən oxumaq
Long id = root.path("user").path("id").asLong();        // 1
String name = root.path("user").path("name").asText();  // "Orxan"
String firstRole = root.path("user").path("roles").get(0).asText();  // "admin"

// Bütün role-lara iterate et
for (JsonNode role : root.path("user").path("roles")) {
    System.out.println(role.asText());
}

// Dinamik JSON qurmaq
ObjectNode obj = mapper.createObjectNode();
obj.put("name", "Orxan");
obj.put("age", 30);
obj.putArray("tags").add("java").add("spring");

String output = mapper.writeValueAsString(obj);
// {"name":"Orxan","age":30,"tags":["java","spring"]}
```

---

## 10. Tam REST controller nümunəsi {#nümunə}

Nested DTO + tarix + polimorfik field olan real nümunə.

### DTO-lar:

```java
// Ödəniş tipini polimorfik göstər
@JsonTypeInfo(use = JsonTypeInfo.Id.NAME, include = JsonTypeInfo.As.PROPERTY, property = "type")
@JsonSubTypes({
    @JsonSubTypes.Type(value = CardPayment.class, name = "card"),
    @JsonSubTypes.Type(value = CashPayment.class, name = "cash")
})
public abstract class Payment {
    private BigDecimal amount;
    public BigDecimal getAmount() { return amount; }
    public void setAmount(BigDecimal amount) { this.amount = amount; }
}

public class CardPayment extends Payment {
    private String cardLast4;
    public String getCardLast4() { return cardLast4; }
    public void setCardLast4(String cardLast4) { this.cardLast4 = cardLast4; }
}

public class CashPayment extends Payment {
    private String cashier;
    public String getCashier() { return cashier; }
    public void setCashier(String cashier) { this.cashier = cashier; }
}

// Nested DTO
public class Address {
    private String city;
    private String street;
    // getter/setter-lər
    public String getCity() { return city; }
    public void setCity(String city) { this.city = city; }
    public String getStreet() { return street; }
    public void setStreet(String street) { this.street = street; }
}

// Əsas DTO
@JsonInclude(JsonInclude.Include.NON_NULL)
public class OrderResponse {

    @JsonProperty("order_id")
    private Long orderId;

    @JsonFormat(shape = JsonFormat.Shape.STRING, pattern = "yyyy-MM-dd'T'HH:mm:ss")
    private LocalDateTime createdAt;

    private Address shippingAddress;

    private Payment payment;  // polimorfik field

    @JsonIgnore
    private String internalNotes;  // JSON-da görünməz

    // getter/setter-lər
    public Long getOrderId() { return orderId; }
    public void setOrderId(Long orderId) { this.orderId = orderId; }
    public LocalDateTime getCreatedAt() { return createdAt; }
    public void setCreatedAt(LocalDateTime createdAt) { this.createdAt = createdAt; }
    public Address getShippingAddress() { return shippingAddress; }
    public void setShippingAddress(Address shippingAddress) { this.shippingAddress = shippingAddress; }
    public Payment getPayment() { return payment; }
    public void setPayment(Payment payment) { this.payment = payment; }
    public String getInternalNotes() { return internalNotes; }
    public void setInternalNotes(String internalNotes) { this.internalNotes = internalNotes; }
}
```

### Controller:

```java
@RestController
@RequestMapping("/api/orders")
public class OrderController {

    @GetMapping("/{id}")
    public OrderResponse getOrder(@PathVariable Long id) {
        OrderResponse r = new OrderResponse();
        r.setOrderId(id);
        r.setCreatedAt(LocalDateTime.now());

        Address addr = new Address();
        addr.setCity("Bakı");
        addr.setStreet("Nizami");
        r.setShippingAddress(addr);

        CardPayment p = new CardPayment();
        p.setAmount(new BigDecimal("99.99"));
        p.setCardLast4("1234");
        r.setPayment(p);

        return r;
    }

    @PostMapping
    public OrderResponse create(@RequestBody OrderResponse req) {
        // Jackson JSON-u avtomatik deserializasiya edir
        req.setOrderId(42L);
        req.setCreatedAt(LocalDateTime.now());
        return req;
    }
}
```

### Nəticə JSON:

```json
{
  "order_id": 42,
  "createdAt": "2026-04-24T10:30:00",
  "shippingAddress": {
    "city": "Bakı",
    "street": "Nizami"
  },
  "payment": {
    "type": "card",
    "amount": 99.99,
    "cardLast4": "1234"
  }
}
```

---

## 11. BigDecimal və Optional dəstəyi

### BigDecimal dəqiqliyi:

```java
// Problem: default olaraq BigDecimal "19.9900" kimi yazıla bilər
// Həll: NORMALIZE_STANDARD_BIGDECIMAL feature

@Configuration
public class JacksonConfig {
    @Bean
    public Jackson2ObjectMapperBuilderCustomizer bd() {
        return b -> b.featuresToEnable(
            DeserializationFeature.USE_BIG_DECIMAL_FOR_FLOATS
        );
    }
}
```

### Optional<T> dəstəyi:

```xml
<!-- jackson-datatype-jdk8 avtomatik gəlir Spring Boot-da -->
<dependency>
    <groupId>com.fasterxml.jackson.datatype</groupId>
    <artifactId>jackson-datatype-jdk8</artifactId>
</dependency>
```

```java
public class User {
    private Long id;
    private Optional<String> middleName;  // boş olsa JSON-da null
}
```

---

## Ümumi Səhvlər {#sehvler}

### 1. No-arg constructor unudulub

```java
// XƏTA verir:
// com.fasterxml.jackson.databind.exc.InvalidDefinitionException:
// Cannot construct instance of `User` (no Creators, like default constructor, exist)

public class User {
    private final Long id;  // final və no-arg constructor yoxdur

    public User(Long id) { this.id = id; }
}

// DÜZGÜN:
public class User {
    private Long id;
    public User() {}                     // no-arg constructor
    public User(Long id) { this.id = id; }
}

// VƏ YA immutable halda:
public class User {
    private final Long id;
    @JsonCreator
    public User(@JsonProperty("id") Long id) { this.id = id; }
}
```

### 2. Getter yoxdur — field serializasiya olunmur

```java
public class User {
    public Long id;            // public field — serializasiya olunur
    private String name;       // private + getter yox — serializasiya olunmaz
}
// Həll: getter əlavə et və ya ObjectMapper-ı visibility ilə konfiqurasiya et
```

### 3. Tarix timezone-u qarışır

```java
// YANLIŞ — LocalDateTime timezone saxlamır
@JsonProperty("created_at")
private LocalDateTime createdAt;

// Server "Asia/Baku"-dadır, amma client "UTC" gözləyir — xəta!

// DOĞRU — timezone explicit qeyd et
private ZonedDateTime createdAt;
// və ya
private Instant createdAt;  // həmişə UTC
```

### 4. Unknown property xətası

```
UnrecognizedPropertyException: Unrecognized field "extraField"
```

```java
// Həll 1 — sinif səviyyəsində
@JsonIgnoreProperties(ignoreUnknown = true)
public class User { /* ... */ }

// Həll 2 — qlobal
// application.properties:
// spring.jackson.deserialization.fail-on-unknown-properties=false
```

### 5. Sonsuz dövrə (JPA entity-lərində)

```java
// Problem: User -> Order -> User -> Order -> ... StackOverflowError

@Entity
public class User {
    @OneToMany(mappedBy = "user")
    private List<Order> orders;
}

@Entity
public class Order {
    @ManyToOne
    private User user;
}

// Həll 1 — @JsonManagedReference / @JsonBackReference
// Həll 2 — @JsonIgnore
// Həll 3 — ən yaxşı — DTO istifadə et (növbəti fayl: 206)
```

### 6. ObjectMapper-ı hər dəfə yaratmaq

```java
// YANLIŞ — ObjectMapper yaratmaq bahalıdır, thread-safe-dir, tək instance saxla
public String toJson(Object o) throws Exception {
    return new ObjectMapper().writeValueAsString(o);  // hər çağırışda yeni
}

// DOĞRU — Spring-dən inject et
@Service
public class JsonService {
    private final ObjectMapper mapper;
    public JsonService(ObjectMapper mapper) { this.mapper = mapper; }
}
```

---

## İntervyu Sualları {#intervyu}

**S: Spring Boot-da default JSON kitabxanası hansıdır?**
C: Jackson. `spring-boot-starter-web` starter-i avtomatik olaraq Jackson-u classpath-ə əlavə edir və `MappingJackson2HttpMessageConverter` vasitəsilə `@RequestBody`/`@ResponseBody` üçün istifadə edir.

**S: @RequestBody necə işləyir?**
C: Spring MVC gələn HTTP request body-ni oxuyur, `HttpMessageConverter`-lər içindən uyğun olanı seçir (JSON üçün `MappingJackson2HttpMessageConverter`), və `ObjectMapper.readValue` çağıraraq Java obyektinə deserializasiya edir.

**S: Jackson POJO-lardan nə tələb edir?**
C: No-arg (parametri olmayan) konstruktor — deserializasiya zamanı obyekti yaratmaq üçün. Getter metodları — serializasiya üçün. Setter-lər və ya `@JsonCreator` ilə işarələnmiş konstruktor — deserializasiya üçün field-ləri doldurmaq.

**S: @JsonIgnore və @JsonIgnoreProperties fərqi nədir?**
C: `@JsonIgnore` field səviyyəsində, bir konkret field-i çıxarır. `@JsonIgnoreProperties({"a","b"})` sinif səviyyəsində, bir neçə field-i eyni anda çıxarır. Həmçinin `@JsonIgnoreProperties(ignoreUnknown = true)` naməlum field-lərə görə xəta verməməyi təmin edir.

**S: Tarix və zaman field-ləri üçün nə konfiqurasiya lazımdır?**
C: `JavaTimeModule`-u `ObjectMapper`-a qeyd etmək (Spring Boot bunu avtomatik edir, əgər `jackson-datatype-jsr310` classpath-dədirsə). Həmçinin `spring.jackson.serialization.write-dates-as-timestamps=false` ilə ISO-8601 formatında yazmaq. Timezone üçün `ZonedDateTime` və ya `Instant` istifadə etmək məsləhətdir.

**S: Polimorfik JSON necə işləyir?**
C: Abstrakt sinifə `@JsonTypeInfo` (hansı field tipi göstərir) və `@JsonSubTypes` (alt sinif → ad mapping) annotasiyaları verilir. Serializasiyada "type" field-i JSON-a əlavə olunur, deserializasiyada Jackson həmin field-ə baxıb düzgün alt sinfi seçir.

**S: JPA entity-ləri ilə Jackson-da sonsuz dövrə problemi necə həll olunur?**
C: Üç yol var: (1) `@JsonManagedReference` və `@JsonBackReference` ilə birtərəfli serializasiya, (2) `@JsonIgnore` ilə əlaqəli field-i çıxarmaq, (3) ən yaxşı həll — entity-ni birbaşa qaytarmamaq, DTO istifadə etmək.

**S: ObjectMapper thread-safe-dirmi?**
C: Bəli, konfiqurasiya edildikdən sonra `ObjectMapper` tam thread-safe-dir və tətbiqdə tək instance saxlanmalıdır. Spring Boot onu bean kimi yaradır və inject edir.

**S: snake_case JSON format-ı necə təyin olunur?**
C: Qlobal olaraq `spring.jackson.property-naming-strategy=SNAKE_CASE`. Və ya sinif səviyyəsində `@JsonNaming(PropertyNamingStrategies.SnakeCaseStrategy.class)`. Və ya field səviyyəsində `@JsonProperty("user_name")`.

**S: Naməlum JSON field-lərinə necə davranmaq lazımdır?**
C: Production-da ümumiyyətlə `fail-on-unknown-properties=false` tövsiyə olunur — API forward-compatible olur. Sinif səviyyəsində `@JsonIgnoreProperties(ignoreUnknown = true)`. Kritik API-larda isə strict yanaşma lazımdır — naməlum field client xətasını göstərir.
