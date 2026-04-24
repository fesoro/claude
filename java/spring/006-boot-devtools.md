# 006 — Spring Boot DevTools
**Səviyyə:** Orta


## Mündəricat
1. [DevTools nədir?](#nedir)
2. [Quraşdırma](#qurashma)
3. [Avtomatik restart](#restart)
4. [LiveReload — brauzerin avtomatik yenilənməsi](#livereload)
5. [Property defaults — inkişaf mühiti üçün](#properties)
6. [Exclusion patterns — nəyi izləmə?](#exclusion)
7. [H2 Console](#h2-console)
8. [Remote DevTools](#remote)
9. [DevTools scope — developmentOnly](#scope)
10. [İntervyu Sualları](#intervyu)

---

## 1. DevTools nədir? {#nedir}

**Spring Boot DevTools** — inkişaf prosesini sürətləndirən alətlər toplusudur:
- **Avtomatik restart** — kod dəyişdikdə tətbiq yenidən başlayır
- **LiveReload** — brauzer səhifəsi avtomatik yenilənir
- **Property defaults** — inkişafda cache-ləri söndürür
- **H2 Console** — H2 verilənlər bazası idarəetmə paneli

```xml
<!-- pom.xml — yalnız runtime-da lazımdır -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-devtools</artifactId>
    <scope>runtime</scope>       <!-- runtime, compile yox -->
    <optional>true</optional>   <!-- asılılıq ötürülmür -->
</dependency>
```

---

## 2. Quraşdırma {#qurashma}

DevTools avtomatik işləyir — heç nə konfiqurasiya lazım deyil. Ancaq fərdiləşdirə bilərsiniz:

```properties
# application.properties — DevTools konfiqurasiyası

# Restart-ı aktivləşdir/deaktiv et
spring.devtools.restart.enabled=true

# LiveReload-u aktivləşdir/deaktiv et
spring.devtools.livereload.enabled=true

# Remote DevTools-u deaktiv et (təhlükəsizlik üçün)
spring.devtools.remote.secret=   # boş buraxmaq = deaktiv

# Polling intervalı (millisaniyə)
spring.devtools.restart.poll-interval=1000

# Sakit qalmadan əvvəl gözlə
spring.devtools.restart.quiet-period=400
```

---

## 3. Avtomatik restart {#restart}

DevTools classpath-i iki classloader ilə izləyir:

```
┌─────────────────────────────────────────────┐
│  Base ClassLoader                           │
│  (dəyişməyən kitabxana sinifləri)           │
│  spring-*.jar, jackson-*.jar, ...           │
│  → restart zamanı yenidən yüklənmir         │
└─────────────────────────────────────────────┘
                    +
┌─────────────────────────────────────────────┐
│  Restart ClassLoader                        │
│  (sizin kodunuz)                            │
│  com.example.*, target/classes/...          │
│  → hər dəfə yenidən yüklənir               │
└─────────────────────────────────────────────┘
```

### İş prinsipi:

```
1. IDE kod dəyişikliyini saxlayır
2. .class faylı target/classes/-ə yazılır
3. DevTools classpath dəyişikliyini aşkarlayır
4. Yalnız Restart ClassLoader yenidən yüklənir
5. Tətbiq ~1-2 saniyədə yenidən başlayır (tam restart-dan 5-10x sürətli)
```

### IntelliJ IDEA ilə işləmə:

```
# IntelliJ IDEA-da avtomatik build:
Settings → Build, Execution, Deployment → Compiler
→ "Build project automatically" — aktiv et

# Və ya:
Settings → Advanced Settings
→ "Allow auto-make to start even if developed application is currently running"
→ aktiv et
```

### Trigger file:

```properties
# Yalnız bu fayl dəyişdikdə restart et
# (hər .class dəyişikliyinə görə restart etmə)
spring.devtools.restart.trigger-file=.reloadtrigger
```

```bash
# Bu faylı əl ilə dəyişdirərək restart tetikləmək:
touch .reloadtrigger
```

---

## 4. LiveReload — brauzerin avtomatik yenilənməsi {#livereload}

### Necə işləyir:

```
1. DevTools 35729 portunda LiveReload server başladır
2. Brauzer LiveReload extension-u quraşdırılır
3. Kod dəyişir → restart → LiveReload siqnal göndərir → brauzer yenilənir
```

### Brauzer extension quraşdırma:

- Chrome: "LiveReload" extension-unu yükləyin
- Firefox: "LiveReload" əlavəsini yükləyin
- Extension-da "http://localhost:8080" üçün aktivləşdirin

### LiveReload-u söndürmək:

```properties
# Yalnız LiveReload-u söndür (restart hələ işləyir):
spring.devtools.livereload.enabled=false
```

### Statik resurslar üçün:

```
src/main/resources/
├── static/          ← HTML, CSS, JS — dəyişdikdə brauzer yenilənir
├── templates/       ← Thymeleaf/FreeMarker şablonları
└── application.properties
```

---

## 5. Property defaults — inkişaf mühiti üçün {#properties}

DevTools aşağıdakı **default** konfiqurasiyaları tətbiq edir (siz yazmadan):

```properties
# DevTools tərəfindən avtomatik tətbiq edilən property-lər:

# Thymeleaf cache-i söndür (şablon dəyişiklikləri dərhal görünsün)
spring.thymeleaf.cache=false

# Freemarker cache-i söndür
spring.freemarker.cache=false

# Web resurs cache-i söndür
spring.web.resources.cache.period=0
spring.web.resources.chain.cache=false

# MVC şablon cache-i söndür
spring.mvc.template-cache=false

# Groovy şablon cache-i söndür
spring.groovy.template.cache=false

# Mustache cache-i söndür
spring.mustache.cache=false

# SQL formatlaması aktivləşdir (inkişafda rahat oxumaq üçün)
spring.jpa.show-sql=true
```

### YANLIŞ — bu property-ləri əl ilə söndürməyə ehtiyac yoxdur:

```properties
# YANLIŞ — DevTools artıq bunu edir, ikiləşdirmə:
spring.thymeleaf.cache=false  # DevTools artıq bunu tətbiq edir

# DOĞRU — yalnız DevTools olmayan mühitdə bunu əlavə et:
# application-dev.properties faylına yaz (DevTools aktiv deyilsə)
```

### DevTools property-lərini ləğv etmək:

```properties
# Əgər DevTools olsa da cache istəyirsinizsə:
spring.thymeleaf.cache=true  # DevTools default-unu override edir
```

---

## 6. Exclusion patterns — nəyi izləmə? {#exclusion}

Bəzən bəzi qovluqların dəyişikliyi restart tetikləməməlidir:

```properties
# Default olaraq izlənilməyən qovluqlar:
# .git, .idea, target, *.class olmayan sair fayllar

# Əlavə exclusion:
spring.devtools.restart.exclude=static/**,public/**,templates/**
# Bu qovluqlardakı dəyişikliklər restart etmir (LiveReload isə edir)

# Mövcud exclusion-lara əlavə et (onları silmə):
spring.devtools.restart.additional-exclude=config/**

# Əlavə izlənəcək qovluqlar (classpath xaricindəkilər):
spring.devtools.restart.additional-paths=/path/to/watch
```

### Nümunə — statik resurslar:

```properties
# Static fayllar dəyişdikdə yalnız LiveReload tetikləsin, restart yox:
spring.devtools.restart.exclude=static/**,public/**

# Beləliklə:
# CSS/JS dəyişir → LiveReload → brauzer yenilənir (restart YOX)
# Java dəyişir → Restart → Tətbiq yenidən başlayır
```

---

## 7. H2 Console {#h2-console}

DevTools aktiv olduqda H2 console avtomatik açılır:

```properties
# H2 database konfiqurasiyası
spring.datasource.url=jdbc:h2:mem:testdb   # in-memory H2 DB
spring.datasource.driver-class-name=org.h2.Driver
spring.datasource.username=sa
spring.datasource.password=

# H2 Console aktivləşdirməsi (DevTools varsa avtomatik):
spring.h2.console.enabled=true
spring.h2.console.path=/h2-console          # URL: http://localhost:8080/h2-console
spring.h2.console.settings.web-allow-others=false  # yalnız localhost

# JPA schema yaratma:
spring.jpa.hibernate.ddl-auto=create-drop   # tətbiq başlayanda cədvəl yarat, bağlananda sil
```

### H2 Console-a qoşulma:

```
URL: http://localhost:8080/h2-console
JDBC URL: jdbc:h2:mem:testdb
Username: sa
Password: (boş)
```

### Security ilə H2 Console:

```java
// Spring Security varsa H2 Console-u icazə ver:
@Configuration
public class SecurityConfig {

    @Bean
    public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        http
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/h2-console/**").permitAll()  // H2 console-a icazə
                .anyRequest().authenticated()
            )
            .csrf(csrf -> csrf
                .ignoringRequestMatchers("/h2-console/**")  // H2 CSRF tələb etmir
            )
            .headers(headers -> headers
                .frameOptions(frame -> frame.sameOrigin())  // H2 iframe istifadə edir
            );

        return http.build();
    }
}
```

---

## 8. Remote DevTools {#remote}

Uzaq serverdəki tətbiqi lokal inkişaf alətləri ilə idarə etmək üçün:

```properties
# application.properties — serverdə:
spring.devtools.remote.secret=mysecretkey123  # məcburi secret key

# Remote restart-ı aktivləşdir:
spring.devtools.restart.enabled=true
```

```
# Lokal maşında — Remote DevTools clienti işə sal:
# IDE-dən Run Configuration yaradın:
# Main class: org.springframework.boot.devtools.RemoteSpringApplication
# Arguments: https://my-remote-server.com
# Environment: SPRING_DEVTOOLS_REMOTE_SECRET=mysecretkey123
```

### Necə işləyir:

```
1. Lokal kodunuzu dəyişirsiniz
2. Remote DevTools client dəyişikliyi aşkarlayır
3. .class fayllarını remote servera yükləyir
4. Remote server restart edir
```

### Təhlükəsizlik xəbərdarlığı:

```
⚠️  Remote DevTools YALNIZ inkişaf mühitindəki serverlər üçün istifadə edilməlidir.
İstehsal serverinə heç vaxt aktivləşdirməyin!
```

---

## 9. DevTools scope — developmentOnly {#scope}

### Maven:

```xml
<!-- DevTools runtime-da mövcuddur, amma istehsal jar-ına daxil edilmir -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-devtools</artifactId>
    <scope>runtime</scope>
    <optional>true</optional>
</dependency>
```

### Gradle:

```groovy
// Gradle-də developmentOnly konfigurasiyası:
configurations {
    developmentOnly    // xüsusi konfiqurasiya
    runtimeClasspath {
        extendsFrom developmentOnly
    }
}

dependencies {
    developmentOnly 'org.springframework.boot:spring-boot-devtools'
}
```

### DevTools-un avtomatik deaktiv olduğu hallar:

```
1. java -jar myapp.jar ilə işə salındıqda (fat jar)
   → DevTools jar-da tanınır, deaktiv edilir

2. Xüsusi ClassLoader istifadə edildikdə
   → app server-lərə deploy (Tomcat, JBoss...)

3. java.io.InputStream.class yenilənmiş classpath-də tapılmadıqda
   → sistem dayanıqlı hesab edilir
```

### Bunu proqramlı yoxlamaq:

```java
@Component
public class DevToolsStatusLogger implements ApplicationRunner {

    @Override
    public void run(ApplicationArguments args) {
        // DevTools aktiv olduqda "restart" system property set olur
        String restartEnabled = System.getProperty(
            "spring.devtools.restart.enabled", "unknown"
        );

        boolean isDevToolsActive = DevToolsEnablement.isEnabled();
        System.out.println("DevTools aktiv: " + isDevToolsActive);
    }
}
```

---

## 10. Global DevTools konfiqurasiyası

```properties
# ~/.config/spring-boot/spring-boot-devtools.properties
# Bütün Spring Boot layihələri üçün global konfiqurasiya

spring.devtools.restart.trigger-file=.trigger
spring.devtools.livereload.enabled=true
spring.devtools.restart.poll-interval=2000
```

---

## İntervyu Sualları {#intervyu}

**S: Spring Boot DevTools-un əsas xüsusiyyətləri hansılardır?**
C: Avtomatik restart (classpath dəyişikliyini izləyir), LiveReload (brauzeri yeniləyir), property defaults (cache-ləri söndürür), H2 Console, Remote DevTools.

**S: DevTools-un restart mexanizmi niyə tam restartdan sürətlidir?**
C: İki classloader istifadə edir. Kitabxana sinifləri (spring-*.jar, jackson-*.jar) "Base ClassLoader"-da qalır — yenidən yüklənmir. Yalnız sizin kodunuz olan "Restart ClassLoader" yenidən yüklənir. Buna görə restart 5-10 dəfə sürətlidir.

**S: DevTools istehsal jar-ına daxil olurmu?**
C: Xeyr. `java -jar` ilə işə salındıqda DevTools özünü deaktiv edir. Maven-də `<optional>true</optional>` ilə tranzitiv asılılıq ötürülmür.

**S: LiveReload ilə avtomatik restart arasındakı fərq nədir?**
C: Restart — Spring tətbiqini yenidən başladır (Java dəyişikliklərində). LiveReload — yalnız brauzeri yeniləyir (HTML/CSS/JS dəyişikliklərində, restart olmadan). Statik resurslara `exclude` qoymaq onları yalnız LiveReload-a həvalə edir.

**S: DevTools hansı property-ləri avtomatik tətbiq edir?**
C: Thymeleaf, FreeMarker, Mustache kimi şablon mühərriklərinin cache-lərini söndürür (`*.cache=false`). Web resurs cache-ini söndürür. Bu default-lar development üçün rahat debugginq təmin edir.
