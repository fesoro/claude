# 04 — Java Naming Conventions və Kod Stili

> **Seviyye:** Junior ⭐


## Mündəricat
1. [Niyə naming conventions vacibdir?](#niye)
2. [Class və interface adları — PascalCase](#class)
3. [Method və dəyişən adları — camelCase](#camel)
4. [Sabitlər — UPPER_SNAKE_CASE](#upper)
5. [Package adları — lowercase.dotted](#package)
6. [Boolean adları — isX, hasX, canX](#boolean)
7. [Getter/Setter naming](#getset)
8. [Test metod adlandırılması](#test)
9. [Interface adları — Java üslubu](#interface)
10. [Exception adları](#exception)
11. [Javadoc əsasları](#javadoc)
12. [Import ordering](#import)
13. [Oracle Code Conventions vs Google Java Style](#google)
14. [.editorconfig](#editorconfig)
15. [Ümumi Səhvlər](#umumi)
16. [İntervyu Sualları](#intervyu)

---

## 1. Niyə naming conventions vacibdir? {#niye}

Kod yazmağın **80%-i oxumaqdır**. 6 ay sonra öz kodunu oxuyacaqsan, başqaları oxuyacaq, code review olacaq. Yaxşı adlar şərh (comment) ehtiyacını azaldır.

### Pis kod

```java
public class u {
    private String n;
    private int a;

    public u(String n, int a) {
        this.n = n;
        this.a = a;
    }

    public boolean check() {
        return a >= 18;
    }
}
```

### Yaxşı kod

```java
public class User {
    private String name;
    private int age;

    public User(String name, int age) {
        this.name = name;
        this.age = age;
    }

    public boolean isAdult() {
        return age >= 18;
    }
}
```

Eyni məntiq — amma yaxşı adlar sayəsində heç bir şərh lazım deyil.

### Konsistentlik = komanda işi

Böyük layihədə 10 developer işləyir. Hər kəs öz stilində yazsa — kaos yaranır. Naming conventions — **komandanın ümumi dilidir**.

---

## 2. Class və interface adları — PascalCase {#class}

Class və interface adları — **PascalCase** (hər sözün birinci hərfi böyük, ayırıcı yox).

```java
// DOĞRU
public class UserAccount { }
public class OrderService { }
public class HttpClient { }
public class XmlParser { }

// YANLIŞ
public class user_account { }     // underscore yoxdur
public class userAccount { }      // birinci hərf də böyük
public class USERACCOUNT { }      // yalnız sabitlər
public class XMLParser { }        // debate mövzusu — aşağıda
```

### Acronym debate: `XmlParser` vs `XMLParser`

Oracle stili — kısa akronimlər də PascalCase:

```java
// Oracle/Google Java Style
class XmlParser { }    // X-m-l-Parser
class HttpClient { }
class JsonSerializer { }
class UuidGenerator { }

// Köhnə stil (amma hələ də istifadə olunur)
class XMLParser { }
class HTTPClient { }
```

**Tövsiyə:** `XmlParser` — daha müasir və oxunaqlıdır. JDK-nın yeni sinifləri də bu stili izləyir (məs. `HttpRequest`, `XmlSerializer`).

### Noun / Verb qayda

- Class adı — **noun** (isim): `User`, `Order`, `PaymentGateway`
- Interface adı — **noun** və ya **adjective** (sifət): `Runnable`, `Comparable`, `Printable`
- Metod adı — **verb** (fel): `save()`, `calculate()`, `isEmpty()`

### Anti-pattern: "Manager", "Utility", "Helper"

Bu adlar nəyi etdiyini deyil, **nə olduğunu** (çox ümumi) göstərir. Daxili məntiqi yaxşı izah etmir.

```java
// YANLIŞ
class UserManager { }      // nə edir?
class OrderHelper { }      // çox ümumi
class StringUtils { }      // OK — static helper-lər üçün

// DAHA YAXŞI
class UserRepository { }   // data access
class UserValidator { }    // validation
class OrderProcessor { }   // işləmə məntiqi
```

---

## 3. Method və dəyişən adları — camelCase {#camel}

Metod və dəyişən adları — **camelCase** (birinci söz kiçik, sonrakılar böyük).

```java
// Dəyişənlər
int userCount = 0;
String fullName = "Anar Hüseynov";
double totalPrice = 1250.5;
LocalDate birthDate = LocalDate.of(1990, 1, 15);

// Metodlar
public void saveUser(User user) { }
public double calculateDiscount(double price) { }
public String formatPhoneNumber(String raw) { }
```

### YANLIŞ

```java
int user_count = 0;            // snake_case — Python/Ruby stili
int UserCount = 0;             // PascalCase — class adı kimi
int USERCOUNT = 0;             // constant kimi
int usercount = 0;             // heç bir convention
int uC = 0;                    // çox qısa, anlamsız
```

### Qısa adlardan qaçın

```java
// YANLIŞ
int i, j, k;       // yalnız loop-da
String s;          // nə? string?
Data d;            // hansı data?
int cnt;           // count? center?

// DOĞRU
int userCount;
String userName;
User currentUser;
int loginAttempts;
```

### Loop sayıcıları — istisna

```java
// for loop-da i, j, k normaldır
for (int i = 0; i < array.length; i++) {
    for (int j = 0; j < array[i].length; j++) {
        // ...
    }
}

// Enhanced for — daha deskriptiv ad
for (String name : names) {
    System.out.println(name);
}
```

### Boolean dəyişən adları

```java
// Sual formasında olmalıdır
boolean isActive;
boolean hasPermission;
boolean canEdit;
boolean shouldRetry;
boolean wasSuccessful;
```

---

## 4. Sabitlər — UPPER_SNAKE_CASE {#upper}

`static final` sabitlər — **UPPER_SNAKE_CASE** (hər sözün hərfi böyük, sözlər `_` ilə ayrılır).

```java
public class Constants {
    public static final int MAX_USERS = 100;
    public static final String API_BASE_URL = "https://api.example.com";
    public static final double DEFAULT_DISCOUNT = 0.1;
    public static final Duration CACHE_TIMEOUT = Duration.ofMinutes(5);

    // private static final də eyni qayda
    private static final int DEFAULT_BUFFER_SIZE = 1024;
}
```

### Nə sabit sayılır?

```java
// SABİT (compile-time constant) — UPPER_SNAKE
public static final int MAX_LOGIN_ATTEMPTS = 3;

// SABİT REFERENS, amma obyektin daxilini dəyişmək olur — yenə UPPER_SNAKE
public static final List<String> COUNTRIES = List.of("AZ", "TR", "RU");

// SADƏCƏ final (instance field) — camelCase
public class User {
    private final String name; // yox UPPER_SNAKE, çünki instance field
}
```

### Enum dəyərləri

```java
public enum OrderStatus {
    PENDING,
    IN_PROGRESS,
    COMPLETED,
    CANCELLED,
    PAYMENT_FAILED
}
```

---

## 5. Package adları — lowercase.dotted {#package}

Package adları — **kiçik hərflərlə**, nöqtə ilə ayrılır. `_` yox, `-` yox.

```java
// DOĞRU
package com.example.userservice;
package com.companyname.project.module;
package org.springframework.boot;

// YANLIŞ
package com.example.UserService;    // PascalCase
package com.example.user_service;   // underscore
package com.example.user-service;   // dash — compile error
```

### Reverse domain convention

Təşkilatın domaininin tərsi:

```
example.com       → com.example
google.com        → com.google
apache.org        → org.apache
```

Daxili layihə paketləri:

```
com.example.project.model        # data classes
com.example.project.service      # business logic
com.example.project.repository   # data access
com.example.project.controller   # REST endpoints
com.example.project.config       # configuration
com.example.project.util         # utility classes
```

### Multi-word package adları

```java
// YANLIŞ
package com.example.userauthentication;  // çox uzun, oxunmur

// DOĞRU — əgər 2+ söz varsa, ayrı paket et
package com.example.user.authentication;
```

---

## 6. Boolean adları — isX, hasX, canX {#boolean}

Boolean dəyişən və metod adları **sual formasında** olmalıdır.

### Prefixlər

| Prefix | Məna | Nümunə |
|---|---|---|
| `is` | ... vəziyyətdədir? | `isActive`, `isEmpty`, `isValid` |
| `has` | malikdir? | `hasPermission`, `hasChildren`, `hasErrors` |
| `can` | edə bilər? | `canEdit`, `canDelete`, `canAccess` |
| `should` | etməlidir? | `shouldRetry`, `shouldNotify` |
| `was` | oldu? | `wasSuccessful`, `wasCreated` |
| `will` | olacaq? | `willExpire` |

### Getter metodlarında

```java
public class User {
    private boolean active;

    // DOĞRU — "is" prefix
    public boolean isActive() {
        return active;
    }
    public void setActive(boolean active) {
        this.active = active;
    }
}
```

### YANLIŞ adlar

```java
boolean status;              // bəli? xeyr? nə deməkdir?
boolean flag;                // hansı flag?
boolean check;               // fel — metod kimi görsənir
boolean userActive;          // is yoxdur

// DOĞRU
boolean isUserActive;
boolean hasValidLicense;
boolean canBePurchased;
```

### Negative boolean-dan qaçın

```java
// YANLIŞ — iki negasiya çaş-baş salır
boolean isNotValid = false;     // "yox false"? yəni valid?
if (!isNotValid) { ... }        // qoşa inkar

// DOĞRU — müsbət formada
boolean isValid = true;
if (isValid) { ... }
```

---

## 7. Getter/Setter naming {#getset}

Java-da getter/setter konvensiyası — **JavaBeans specification**-a əsaslanır.

### Standart property

```java
public class Product {
    private String name;
    private double price;

    // GETTER — get + PascalCase(field)
    public String getName() { return name; }
    public double getPrice() { return price; }

    // SETTER — set + PascalCase(field)
    public void setName(String name) { this.name = name; }
    public void setPrice(double price) { this.price = price; }
}
```

### Boolean üçün xüsusi qayda

```java
public class User {
    private boolean active;

    // is + PascalCase (get yox!)
    public boolean isActive() { return active; }

    // set + PascalCase (normal)
    public void setActive(boolean active) { this.active = active; }
}
```

### Fluent / Record stili (yeni)

Record-larda və builder-lərdə sadəcə field adı istifadə olunur:

```java
public record Product(String name, double price) { }

Product p = new Product("Kitab", 25.0);
String ad = p.name();      // get yoxdur
double qiymət = p.price();
```

### Lombok ilə

```java
@Data  // getter, setter, toString, equals, hashCode avtomatik
public class Product {
    private String name;
    private double price;
}
```

---

## 8. Test metod adlandırılması {#test}

Test adı test-in **nə test etdiyini** aydın göstərməlidir.

### Pattern 1: `methodName_stateUnderTest_expectedBehavior`

```java
@Test
void withdraw_balanceIsSufficient_decreasesBalance() { }

@Test
void withdraw_balanceIsInsufficient_throwsException() { }

@Test
void login_validCredentials_returnsToken() { }

@Test
void login_invalidPassword_throwsAuthException() { }
```

### Pattern 2: `should_ExpectedBehavior_when_StateUnderTest`

```java
@Test
void shouldDecreaseBalance_whenWithdrawWithSufficientFunds() { }

@Test
void shouldThrowException_whenWithdrawWithInsufficientFunds() { }
```

### Pattern 3: BDD — `given_when_then`

```java
@Test
void givenSufficientBalance_whenWithdraw_thenBalanceDecreases() { }
```

### Pattern 4: JUnit 5 @DisplayName

```java
@Test
@DisplayName("Sufficient balance olduqda withdraw balansı azaldır")
void withdrawDecreasesBalance() { }
```

### Test metod uzunluğu

Test adları **uzun ola bilər** — oxunaqlıq vacibdir, yığcamlıq deyil.

```java
// Yaxşıdır — 60+ simvol
@Test
void shouldReturn404_whenUserDoesNotExist_inGetUserEndpoint() { }
```

---

## 9. Interface adları — Java üslubu {#interface}

Java C#-dan fərqli olaraq interface adlarında **`I` prefixi istifadə etmir**.

### YANLIŞ (C#/Hungarian notation stili)

```java
interface IUserRepository { }   // C# stilidir, Java yox
interface IOrderService { }
```

### DOĞRU (Java stili)

```java
interface UserRepository { }
interface OrderService { }
```

### Suffix pattern-ləri

| Suffix | Nə vaxt istifadə etmək? |
|---|---|
| `-able` | Qabiliyyət bildirir | `Runnable`, `Comparable`, `Serializable`, `Cloneable` |
| `-er`/`-or` | "Edici"ni bildirir | `Reader`, `Writer`, `Handler`, `Validator` |
| `-Service` | Business logic | `UserService`, `PaymentService` |
| `-Repository` | Data access | `UserRepository`, `OrderRepository` |
| heç nə | Domain konsepti | `User`, `Order` (həm class, həm interface ola bilər) |

### Implementation adlandırılması

```java
// İnterfeys — aydın və qısa
interface UserRepository { }

// Implementation — suffix ilə
class JpaUserRepository implements UserRepository { }
class InMemoryUserRepository implements UserRepository { }
class MockUserRepository implements UserRepository { }
```

Yəni texnoloji detaya görə ad ver: `Jpa`, `InMemory`, `Redis`, `Mock`.

### "Impl" suffix — son çarə

```java
// Köhnə stil (hələ çox layihədə qalır)
class UserRepositoryImpl implements UserRepository { }
```

`Impl` heç bir informasiya vermir. Mümkün olduqda, implementation texnologiyasını göstər.

---

## 10. Exception adları {#exception}

Exception sinif adı **`Exception` ilə bitməlidir**.

```java
// STANDART
public class UserNotFoundException extends RuntimeException { }
public class InvalidCredentialsException extends RuntimeException { }
public class PaymentFailedException extends Exception { }
public class InsufficientBalanceException extends RuntimeException { }

// YANLIŞ
public class UserNotFound { }          // Exception yoxdur
public class UserError { }             // Error — JVM xətaları üçündür
public class UserNotFoundEx { }        // qısaldılmış
```

### Exception-ın daxilində aydın mesaj

```java
public class UserNotFoundException extends RuntimeException {
    public UserNotFoundException(Long userId) {
        super("User tapılmadı: id=" + userId);
    }

    public UserNotFoundException(String username) {
        super("User tapılmadı: username=" + username);
    }
}
```

---

## 11. Javadoc əsasları {#javadoc}

Javadoc — public API-lərin rəsmi sənədləri üçündür.

### Standart formatda

```java
/**
 * İstifadəçi hesabını idarə edən servis.
 *
 * <p>Bu servis yeni istifadəçi yaratmaq, yeniləmək və silmək üçün
 * istifadə olunur. Bütün əməliyyatlar audit log-da qeyd olunur.
 *
 * @author Anar Hüseynov
 * @since 1.0
 */
public class UserService {

    /**
     * Yeni istifadəçi yaradır.
     *
     * @param username istifadəçi adı (3-30 simvol)
     * @param email e-poçt ünvanı, valid format olmalıdır
     * @return yaradılmış istifadəçinin ID-si
     * @throws IllegalArgumentException username və ya email yanlışdırsa
     * @throws DuplicateUserException eyni username varsa
     * @see User
     */
    public Long createUser(String username, String email) {
        // ...
    }
}
```

### Əsas teqlər

| Tag | Nə üçün? |
|---|---|
| `@param` | Metod parametri |
| `@return` | Qaytarma dəyəri (void üçün lazım deyil) |
| `@throws` | Atdığı exception |
| `@see` | Əlaqəli sinif/metod referansı |
| `@since` | Hansı versiyada əlavə edilib |
| `@deprecated` | İstifadə edilməməlidir |
| `@author` | Müəllif |
| `{@link}` | İçəri referans (inline) |
| `{@code}` | Kod nümunəsi (inline) |

### Inline tags

```java
/**
 * {@link User#getName()} metodunu çağırır və nəticəni
 * {@code String.toUpperCase()} ilə transform edir.
 */
public String getUpperName(User user) { ... }
```

### IDE generator

IntelliJ-də `/**` yazıb Enter — otomatik stub yaranır.

---

## 12. Import ordering {#import}

İmportları **sistemli şəkildə sıralamaq** oxunaqlığı artırır.

### Google Java Style (tövsiyə olunur)

```java
// 1. Static imports — əvvəl
import static java.lang.Math.PI;
import static java.util.Arrays.asList;

// 2. Bütün qeyri-static import-lar əlifba sırası ilə
import java.util.ArrayList;
import java.util.List;
import java.util.Map;

import org.springframework.stereotype.Service;

import com.example.domain.User;
```

### Oracle stili

```java
// 1. java.*
import java.util.List;
import java.util.Map;

// 2. javax.*
import javax.annotation.Nullable;

// 3. Üçüncü tərəf paketlər (əlifba sırası)
import org.springframework.stereotype.Service;

// 4. Öz paketin
import com.example.domain.User;

// 5. Static imports (ən sonda)
import static java.util.Arrays.asList;
```

### Wildcard import-dan qaçın

```java
// YANLIŞ — hansı siniflərin istifadə edildiyi bilinmir
import java.util.*;

// DOĞRU — aşkar
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
```

IntelliJ: `Settings → Editor → Code Style → Java → Imports → "Use single class import"`

---

## 13. Oracle Code Conventions vs Google Java Style {#google}

İki əsas rəsmi stil var:

| Mövzu | Oracle | Google |
|---|---|---|
| Indent | 4 boşluq | 2 boşluq |
| Line limit | 80 simvol | 100 simvol |
| Brace stil | K&R (opening braces in line) | K&R |
| Import order | java, javax, 3rd party, own | Static, all (əlifba ilə) |
| Wildcard import | İcazə var | QADAĞANDIR |
| Javadoc | `@param`, `@return` tələb | Tələb etmir |
| Acronym | `HTTPClient` | `HttpClient` |

### Azərbaycan komandalarda ən çox istifadə olunan

- Başlangıc və orta layihə: **Oracle** (IntelliJ default)
- Böyük şirkət / open source: **Google Java Style**
- Spring layihələri: çox zaman **Spring öz stili** (Google-a yaxın, 4 boşluq indent)

### Checkstyle ilə avtomatlaşdırma

```xml
<plugin>
    <groupId>org.apache.maven.plugins</groupId>
    <artifactId>maven-checkstyle-plugin</artifactId>
    <version>3.3.1</version>
    <configuration>
        <configLocation>google_checks.xml</configLocation>
    </configuration>
</plugin>
```

---

## 14. .editorconfig {#editorconfig}

`.editorconfig` faylı — fərqli IDE-lərdə eyni stili təmin edir.

### Fayl: `.editorconfig` (layihənin root-unda)

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true
indent_style = space

[*.java]
indent_size = 4
max_line_length = 120

[*.{yml,yaml,json}]
indent_size = 2

[*.{md,markdown}]
trim_trailing_whitespace = false

[Makefile]
indent_style = tab
```

### Dəstəkləyən IDE-lər

- IntelliJ IDEA — built-in
- VS Code — plugin lazımdır ("EditorConfig for VS Code")
- Eclipse — plugin lazımdır

---

## 15. Ümumi Səhvlər {#umumi}

### Səhv 1 — Class adında abreviatura

```java
// YANLIŞ
class UsrSrv { }
class OrdMgr { }

// DOĞRU
class UserService { }
class OrderManager { }
```

### Səhv 2 — Dəyişəndə qısaltma

```java
// YANLIŞ
int tmpCnt;
String usrNm;
boolean flg;

// DOĞRU
int temporaryCount;
String userName;
boolean isEnabled;
```

### Səhv 3 — Hungarian notation

```java
// YANLIŞ (C++ stili, Java yox)
String strName;
int iCount;
boolean bActive;

// DOĞRU
String name;
int count;
boolean isActive;
```

### Səhv 4 — Constants `public static`-dan sonra tip yazmamaq

```java
// PIS stil — camelCase sabit adı
public static final int maxUsers = 100;

// DOĞRU
public static final int MAX_USERS = 100;
```

### Səhv 5 — Interface-də `I` prefix

```java
// YANLIŞ (C# stili)
interface IUserRepository { }

// DOĞRU
interface UserRepository { }
```

### Səhv 6 — Impl suffix informasiyasız

```java
// PIS — Impl heç nə demir
class UserServiceImpl { }

// DAHA YAXŞI
class DefaultUserService { }
class JpaUserRepository { }
class RedisCacheService { }
```

### Səhv 7 — Negative boolean

```java
// YANLIŞ
boolean notValid;
if (!notValid) { ... } // çətin oxunur

// DOĞRU
boolean isValid;
if (isValid) { ... }
```

---

## İntervyu Sualları {#intervyu}

**S1: Java-da class adı üçün hansı convention istifadə olunur?**
> PascalCase — hər sözün birinci hərfi böyük, ayırıcı yox. Məsələn: `UserAccount`, `HttpClient`. Class adı adətən noun (isim) olur. Modern stildə kısa akronimlər də PascalCase-ə uyğundur: `XmlParser`, `HttpClient` (əvvəlki `XMLParser`, `HTTPClient` əvəzinə).

**S2: Niyə Java interface-ləri `I` prefixi istifadə etmir?**
> Java-nın tarixi konvensiyası odur ki, interface-in "interface" olması istifadəçi üçün önəmli deyil — yalnız "nə təmin edir" vacibdir. `List` interface-ini istifadə edəndə onun interface və ya class olduğu onsuz da maraq etmir. `I` prefix C# və .NET konvensiyasıdır.

**S3: `static final` sahə üçün hansı naming istifadə olunur?**
> `UPPER_SNAKE_CASE` — bütün hərflər böyük, sözlər `_` ilə ayrılır. Məsələn: `MAX_USERS`, `DEFAULT_TIMEOUT`. Bu yalnız **sabitlər** üçündür — immutable dəyər olmalıdır. Sadəcə `final` instance sahəsi isə `camelCase` qalır.

**S4: Boolean getter metodunda hansı prefix olur?**
> `is` prefixi — `isActive()`, `isValid()`. JavaBeans specification bunu tələb edir. `get` prefixi də qəbul edilir (`getActive()`), amma `is` daha dəqiq və yaygındır. Setter isə həmişə `set` ilə başlayır: `setActive(boolean)`.

**S5: Oracle və Google Java Style arasında ən vacib fərq hansılardır?**
> (1) Indent — Oracle 4 boşluq, Google 2 boşluq. (2) Line limit — Oracle 80, Google 100. (3) Wildcard import — Oracle icazə verir, Google qadağan edir. (4) Acronym — Google `HttpClient` (PascalCase), Oracle hər ikisini qəbul edir.

**S6: Package adı niyə `com.example.user-service` ola bilməz?**
> Java identifier qaydasına görə `-` simvol identifier-də ola bilməz. Package adı Java compiler üçün identifier kimi davranır. Həmçinin `_` texniki olaraq icazəlidir, amma konvensiya heç bir ayırıcı istifadə etməməkdir — yalnız nöqtə ilə hierarchy yaratmaqdır.

**S7: `UserServiceImpl` adlandırması niyə sub-optimal hesab olunur?**
> `Impl` suffixi **heç bir informasiya vermir** — sadəcə "implementation" bildirir. Daha yaxşı yanaşma implementation texnologiyasını göstərməkdir: `JpaUserRepository`, `RedisUserCache`, `InMemoryUserStore`. Bu həm oxunaqlı, həm də eyni anda bir neçə implementation olduğunu bildirir.

**S8: Test metod adı niyə uzun ola bilər?**
> Test adı test-in nə yoxladığını aydın göstərməlidir — bir baxışda başa düşülməlidir. `test1()` heç nə demir; `shouldThrowException_whenBalanceIsNegative()` dərhal aydınlıq verir. Production kodda qısalıq vacibdir, test kodunda oxunaqlıq vacibdir.

**S9: Niyə wildcard import (`import java.util.*`) tövsiyə olunmur?**
> (1) Hansı siniflərin istifadə edildiyi aydın olmur. (2) Ad kolliziyaları yaşana bilər — məsələn `java.util.Date` və `java.sql.Date`. (3) Kompiler daha yavaş işləyir. (4) Code review-də izləmə çətinləşir. Google Java Style bunu tamamilə qadağan edir.

**S10: `.editorconfig` nə üçün lazımdır?**
> Komandada fərqli IDE (IntelliJ, VS Code, Eclipse) və əməliyyat sistemləri istifadə olunur. `.editorconfig` faylı indent, line ending, encoding kimi parametrləri mərkəzləşdirir — hər IDE onu avtomatik oxuyur. Bu formatting inconsistency-ni (git diff-də gərəksiz dəyişikliklər) aradan qaldırır.
