# 005 — Spring Boot Embedded Server
**Səviyyə:** Orta


## Mündəricat
1. [Embedded Server nədir?](#nedir)
2. [Tomcat, Jetty, Undertow — seçim](#secim)
3. [Server konfiqurasiyası](#konfig)
4. [SSL/TLS konfiqurasiyası](#ssl)
5. [Tomcat thread pool tuning](#thread-pool)
6. [Graceful shutdown](#graceful)
7. [Server compression](#compression)
8. [Proqramlı konfiqurasiya](#programmatic)
9. [İntervyu Sualları](#intervyu)

---

## 1. Embedded Server nədir? {#nedir}

Ənənəvi Java web tətbiqlərindən fərqli olaraq, Spring Boot serveri tətbiqin içinə
yerləşdirir (embed edir). WAR faylı yaratmaq, xarici Tomcat quraşdırmaq lazım deyil.

```
Ənənəvi üsul:            Spring Boot üsulu:
─────────────────        ──────────────────────────────────────
Tomcat quraşdır          java -jar myapp.jar   ← sadəcə bunu!
  ↓
WAR faylı yarat
  ↓
webapps/ qovluğuna kopyala
  ↓
Tomcat-ı işə sal
```

### Üstünlükləri:

- Sadə deploy (tək jar faylı)
- Mühit fərqi yoxdur (developer + istehsal eyni)
- Server konfiqurasiyası kod içindədir → versiya kontrolu
- Mikroservislər üçün ideal

---

## 2. Tomcat, Jetty, Undertow — seçim {#secim}

Default olaraq Tomcat gəlir. Dəyişmək üçün:

### Jetty-yə keçid:

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
    <exclusions>
        <!-- Tomcat-ı çıxar -->
        <exclusion>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-tomcat</artifactId>
        </exclusion>
    </exclusions>
</dependency>

<!-- Jetty əlavə et -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-jetty</artifactId>
</dependency>
```

### Undertow-a keçid:

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
    <exclusions>
        <exclusion>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-tomcat</artifactId>
        </exclusion>
    </exclusions>
</dependency>

<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-undertow</artifactId>
</dependency>
```

### Müqayisə:

| Xüsusiyyət | Tomcat | Jetty | Undertow |
|---|---|---|---|
| Default | Bəli | Xeyr | Xeyr |
| Thread model | Thread-per-request | Thread-per-request | Non-blocking |
| Yaddaş istifadəsi | Orta | Az | Ən az |
| Performans | Yaxşı | Yaxşı | Ən yaxşı |
| WebSocket | Bəli | Bəli | Bəli |
| İstifadə rahatlığı | Ən asan | Asan | Asan |

---

## 3. Server konfiqurasiyası {#konfig}

```properties
# Port konfiqurasiyası
server.port=8080
server.port=0   # 0 = təsadüfi boş port tap (test üçün faydalı)

# Context path (tətbiqin bazə URL-i)
server.servlet.context-path=/api
# İndi bütün endpoint-lər /api/... ilə başlayır

# Servlet encoding
server.servlet.encoding.charset=UTF-8
server.servlet.encoding.enabled=true
server.servlet.encoding.force=true

# Session konfiqurasiyası
server.servlet.session.timeout=30m          # 30 dəqiqə
server.servlet.session.cookie.http-only=true
server.servlet.session.cookie.secure=true   # yalnız HTTPS

# Error səhifəsi
server.error.include-message=always
server.error.include-stacktrace=on-param    # ?trace=true ilə göstər
server.error.include-binding-errors=always

# Max header ölçüsü
server.max-http-request-header-size=8KB

# Tomcat spesifik — max sorğu ölçüsü
server.tomcat.max-http-form-post-size=2MB
```

### Profillərə görə port dəyişikliyi:

```properties
# application-dev.properties
server.port=8080

# application-prod.properties
server.port=443
```

---

## 4. SSL/TLS konfiqurasiyası {#ssl}

### Keystore yaratmaq (özündən imzalanmış sertifikat — test üçün):

```bash
# JDK keytool ilə keystore yarat
keytool -genkeypair \
  -alias myapp \
  -keyalg RSA \
  -keysize 2048 \
  -storetype PKCS12 \
  -keystore keystore.p12 \
  -validity 365 \
  -storepass mysecretpassword
```

### application.properties SSL konfiqurasiyası:

```properties
# HTTPS aktivləşdir
server.port=8443
server.ssl.enabled=true

# Keystore fayl yolu
server.ssl.key-store=classpath:keystore.p12
# Və ya tam yol:
# server.ssl.key-store=/etc/ssl/myapp/keystore.p12

server.ssl.key-store-password=mysecretpassword
server.ssl.key-store-type=PKCS12
server.ssl.key-alias=myapp

# TLS versiyası
server.ssl.protocol=TLS
server.ssl.enabled-protocols=TLSv1.2,TLSv1.3

# Cipher suite-lər (yalnız güclü olanlar)
server.ssl.ciphers=TLS_ECDHE_RSA_WITH_AES_256_GCM_SHA384,TLS_ECDHE_RSA_WITH_AES_128_GCM_SHA256
```

### HTTP-dən HTTPS-ə yönləndirmə:

```java
@Configuration
public class HttpsRedirectConfig {

    // HTTP port açıq saxla, amma HTTPS-ə yönləndir
    @Bean
    public TomcatServletWebServerFactory tomcatServletWebServerFactory() {
        TomcatServletWebServerFactory factory = new TomcatServletWebServerFactory() {
            @Override
            protected void postProcessContext(Context context) {
                // HTTP sorğuları HTTPS-ə yönləndir
                SecurityConstraint securityConstraint = new SecurityConstraint();
                securityConstraint.setUserConstraint("CONFIDENTIAL");
                SecurityCollection collection = new SecurityCollection();
                collection.addPattern("/*");
                securityConstraint.addCollection(collection);
                context.addConstraint(securityConstraint);
            }
        };

        // HTTP connector əlavə et (yalnız redirect üçün)
        factory.addAdditionalTomcatConnectors(httpConnector());
        return factory;
    }

    private Connector httpConnector() {
        Connector connector = new Connector(TomcatServletWebServerFactory.DEFAULT_PROTOCOL);
        connector.setScheme("http");
        connector.setPort(8080);           // HTTP portu
        connector.setSecure(false);
        connector.setRedirectPort(8443);   // HTTPS portuna yönləndir
        return connector;
    }
}
```

---

## 5. Tomcat thread pool tuning {#thread-pool}

```properties
# Tomcat thread pool konfiqurasiyası

# Maksimum worker thread sayı (default: 200)
server.tomcat.threads.max=200

# Minimum daima hazır thread sayı
server.tomcat.threads.min-spare=10

# Əlaqə gözləmə növbəsi ölçüsü (max-threads dolduqdan sonra)
server.tomcat.accept-count=100

# Bağlantı timeout-u (millisaniyə)
server.tomcat.connection-timeout=20000

# Keep-alive bağlantıları üçün max sorğu
server.tomcat.keep-alive-timeout=20000
server.tomcat.max-keep-alive-requests=100

# Max bağlantı sayı
server.tomcat.max-connections=8192

# Connection pool
server.tomcat.uri-encoding=UTF-8
```

### Yük profilinə görə tövsiyələr:

```properties
# Yüksək concurrent sorğular üçün:
server.tomcat.threads.max=400
server.tomcat.accept-count=200
server.tomcat.max-connections=10000

# Yaddaşa qənaət üçün (az concurrent):
server.tomcat.threads.max=50
server.tomcat.threads.min-spare=5
server.tomcat.accept-count=50
```

### Undertow NIO konfiqurasiyası:

```properties
# Undertow thread pool
server.undertow.threads.io=4           # I/O thread sayı (CPU çəkidinə görə)
server.undertow.threads.worker=32      # Worker thread sayı

# Buffer ölçüsü
server.undertow.buffer-size=1024

# Direct bufferler istifadə et (GC yükü azalır)
server.undertow.direct-buffers=true
```

---

## 6. Graceful Shutdown {#graceful}

Graceful shutdown — tətbiq dayanarkən mövcud sorğuların tamamlanmasını gözləyir.

```properties
# Graceful shutdown aktivləşdir (default: immediate)
server.shutdown=graceful

# Gözləmə müddəti — bu müddəti bitmiş sorğuları kill et
spring.lifecycle.timeout-per-shutdown-phase=30s
```

### Necə işləyir:

```
1. SIGTERM siqnalı alınır (Kubernetes pod ölçülər, kill -TERM)
2. Server yeni sorğuları qəbul etməyi dayandırır
3. Mövcud sorğuların tamamlanmasını gözləyir
4. timeout-per-shutdown-phase müddəti keçərsə — zorla bağlanır
5. ApplicationContext bağlanır
6. Proses sonlanır
```

### Kubernetes ilə graceful shutdown:

```yaml
# k8s deployment.yaml
spec:
  template:
    spec:
      containers:
        - name: myapp
          lifecycle:
            preStop:
              exec:
                command: ["sleep", "5"]  # traffic axmağı dayandır, sonra SIGTERM
      terminationGracePeriodSeconds: 60   # k8s SIGKILL-dən əvvəl gözlər
```

---

## 7. Server compression {#compression}

```properties
# HTTP response kompressiyası aktivləşdir
server.compression.enabled=true

# Minimum kompressiya ölçüsü (bu ölçüdən kiçiksə kompressiya etmə)
server.compression.min-response-size=1024    # 1 KB

# Kompressiya ediləcək MIME tiplər
server.compression.mime-types=\
  text/html,\
  text/xml,\
  text/plain,\
  text/css,\
  text/javascript,\
  application/javascript,\
  application/json,\
  application/xml
```

### Kompressiya nəticəsi:

```
JSON cavabı (100KB) → Gzip ilə → ~15KB (85% azalma)
HTML cavabı (50KB)  → Gzip ilə → ~10KB (80% azalma)
Kiçik JSON (500B)  → kompressiya YOX (overhead daha çox olardı)
```

---

## 8. Proqramlı konfiqurasiya {#programmatic}

```java
@Configuration
public class WebServerConfig implements WebServerFactoryCustomizer<TomcatServletWebServerFactory> {

    @Override
    public void customize(TomcatServletWebServerFactory factory) {
        // Tomcat connector-u fərdiləşdir
        factory.addConnectorCustomizers(connector -> {
            connector.setProperty("maxKeepAliveRequests", "200");
            connector.setProperty("keepAliveTimeout", "30000");
        });

        // Xüsusi error valve əlavə et
        factory.addContextCustomizers(context -> {
            // Custom valve əlavə etmək
            context.addValve(new AccessLogValve());
        });

        // Protocol handler fərdiləşdir
        factory.addConnectorCustomizers(connector -> {
            if (connector.getProtocolHandler() instanceof Http11NioProtocol protocol) {
                protocol.setMaxThreads(300);
                protocol.setMinSpareThreads(20);
                protocol.setAcceptCount(150);
                protocol.setConnectionTimeout(20000);
            }
        });
    }
}
```

### Undertow fərdiləşdirmə:

```java
@Configuration
public class UndertowConfig implements WebServerFactoryCustomizer<UndertowServletWebServerFactory> {

    @Override
    public void customize(UndertowServletWebServerFactory factory) {
        // Undertow builder-i fərdiləşdir
        factory.addBuilderCustomizers(builder -> {
            builder.setIoThreads(4);            // I/O thread sayı
            builder.setWorkerThreads(64);       // Worker thread sayı
            builder.setBufferSize(16384);       // 16KB buffer
            builder.setDirectBuffers(true);     // Direct memory istifadə et
        });
    }
}
```

---

## 9. Xüsusi hallar

### Eyni anda iki port (HTTP + HTTPS):

```java
@Bean
public ServletWebServerFactory servletContainer() {
    TomcatServletWebServerFactory tomcat = new TomcatServletWebServerFactory();

    // HTTP connector (8080)
    Connector httpConnector = new Connector("HTTP/1.1");
    httpConnector.setPort(8080);

    // HTTPS connector (8443) — SSL konfiqurasiyası application.properties-dən
    tomcat.addAdditionalTomcatConnectors(httpConnector);

    return tomcat;
}
```

### Embedded serveri tamamilə söndürmək:

```properties
# Web server olmadan Spring Boot işə sal (batch işlər üçün)
spring.main.web-application-type=none
```

---

## İntervyu Sualları {#intervyu}

**S: Spring Boot-da default server hansıdır və necə dəyişmək olar?**
C: Default Tomcat-dır. Dəyişmək üçün `spring-boot-starter-tomcat`-ı exclusion etmək, sonra `spring-boot-starter-jetty` və ya `spring-boot-starter-undertow` əlavə etmək lazımdır.

**S: Tomcat thread pool-u necə konfiqurasiya edilir?**
C: `server.tomcat.threads.max` (default 200), `server.tomcat.threads.min-spare` (minimum daima hazır thread), `server.tomcat.accept-count` (növbə ölçüsü). Yüksək yük üçün `max=400`, `accept-count=200` tövsiyə edilir.

**S: Graceful shutdown nədir?**
C: `server.shutdown=graceful` ilə SIGTERM alındıqda yeni sorğuları qəbul etməyi dayandırır, amma mövcud sorğuların tamamlanmasını gözləyir. `spring.lifecycle.timeout-per-shutdown-phase=30s` ilə maksimum gözləmə vaxtı təyin edilir.

**S: SSL/TLS konfiqurasiyası üçün hansı addımlar lazımdır?**
C: `keytool` ilə PKCS12 keystore yaratmaq, `server.ssl.*` property-lərini konfiqurasiya etmək (key-store, key-store-password, key-alias), port 8443-ə dəyişmək. İstehsalda Let's Encrypt sertifikatı tövsiyə edilir.

**S: Server kompressiyası necə aktivləşdirilir?**
C: `server.compression.enabled=true`, `server.compression.min-response-size=1024` (1KB-dan kiçik cavabları kompressiya etmə), `server.compression.mime-types` ilə hansı MIME tiplərinin kompressiya ediləcəyi təyin edilir.
