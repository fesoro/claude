# 87 — Java Modules (JPMS) — Geniş İzah

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [JPMS nədir?](#jpms-nədir)
2. [module-info.java](#module-infojava)
3. [exports və requires](#exports-və-requires)
4. [opens, uses, provides](#opens-uses-provides)
5. [Modul tipləri](#modul-tipləri)
6. [Spring Boot ilə modullar](#spring-boot-ilə-modullar)
7. [İntervyu Sualları](#intervyu-sualları)

---

## JPMS nədir?

**JPMS** (Java Platform Module System, Java 9, JEP 261) — Java-nın modul sistemi. `classpath`-in chaotic açıq strukturundan ayrı, güclü encapsulation.

```
Classpath problemi (Java 9-dan qabaq):
  ├── lib/
  │   ├── order-service.jar
  │   ├── payment-service.jar
  │   └── utils.jar
  
  - Bütün public sinif/paketlər hamıya açıqdır
  - "Jar hell" — version conflicts
  - JDK öz internal API-lərini (sun.misc.Unsafe) gizlədə bilmirdi
  - Hansı jar-ın hansı jar-a dependency olduğu bəlli deyil

JPMS ilə:
  - Hər modul hansı paketi export etdiyini açıq bəyan edir
  - Hər modul hansı modullara dependency olduğunu bəyan edir
  - Internal paketlər həqiqətən gizlənir
  - JDK özü de modullara bölündü (java.base, java.sql, vb.)
```

---

## module-info.java

```java
// ─── Yerləşmə: src/main/java/module-info.java ─────────
// (src/main/java/ root-da, hər hansı pakетdə deyil)

// Sadə modul
module com.example.orderservice {

    // Hansı paketlər xaricdən görünür?
    exports com.example.orderservice.api;
    exports com.example.orderservice.dto;

    // Hansı modullara bağlıyıq?
    requires java.base;           // default olaraq — yazılmasa da var
    requires java.sql;
    requires spring.context;
    requires com.example.common;
}

// ─── Modulun fayl strukturu ──────────────────────────
// src/
//   main/
//     java/
//       module-info.java          ← modul bəyannaməsi
//       com/
//         example/
//           orderservice/
//             api/                 ← exported
//               OrderController.java
//             dto/                 ← exported
//               OrderRequest.java
//             internal/            ← NOT exported (private)
//               OrderValidator.java
//               OrderMapper.java
//             OrderService.java    ← NOT exported (internal)

// ─── module-path vs classpath ────────────────────────
// Köhnə: java -cp "lib/*" com.example.Main
// Yeni:  java --module-path "lib" --module com.example.main/com.example.Main
```

---

## exports və requires

```java
// ─── exports ──────────────────────────────────────────
module com.example.orderservice {

    // Hamıya açıq
    exports com.example.orderservice.api;

    // Yalnız müəyyən modullara açıq (qualified exports)
    exports com.example.orderservice.internal.dto
        to com.example.orderprocessor,
           com.example.orderreporter;

    // Heç export edilməmiş paketlər tamamilə gizlidir
    // com.example.orderservice.internal → xaricdən görünmür
}

// ─── requires ─────────────────────────────────────────
module com.example.orderservice {

    // Sadə requires
    requires java.sql;
    requires com.example.common;

    // requires transitive — asılılığı irəliyə ötür
    // Əgər orderservice API-ları common tiplerinə istinad edirsə
    requires transitive com.example.common;
    // → Bizi istifadə edən modul da common-u istifadə edə bilər

    // requires static — yalnız compile-time (optional runtime)
    requires static org.slf4j;
    // SLF4J-ı istifadə edir amma runtime-da olmadıqda da işləyir
}

// ─── Nümunə: Multi-modul layihə ──────────────────────
// common modul:
module com.example.common {
    exports com.example.common.domain;
    exports com.example.common.events;
}

// order modul:
module com.example.orderservice {
    requires transitive com.example.common; // common domain-ını irəliyə ötür
    requires java.sql;

    exports com.example.orderservice.api;
}

// payment modul:
module com.example.paymentservice {
    requires com.example.orderservice; // orderservice + common (transitive)
    requires com.example.common; // Artıq transitive ilə gəlir — yenə yazıla bilər

    exports com.example.paymentservice.api;
}
```

---

## opens, uses, provides

```java
// ─── opens — reflection üçün ──────────────────────────
// exports: compile + runtime type access
// opens: runtime reflection access (Jackson, Hibernate, Spring)

module com.example.orderservice {

    // Jackson deserializasiyası üçün reflection lazımdır
    opens com.example.orderservice.dto to com.fasterxml.jackson.databind;

    // Hibernate entity-lər üçün reflection
    opens com.example.orderservice.domain to org.hibernate.orm.core;

    // Hamıya reflection (az tövsiyə edilir)
    opens com.example.orderservice.api;

    // Seçici export vs opens
    exports com.example.orderservice.api;      // Public type-lar görünür
    opens com.example.orderservice.domain;      // Bütün member-lar reflection ilə əlçatandır
}

// ─── uses + provides — Service Loader ─────────────────
// Service Provider Interface pattern

// İnterfeys modulda:
module com.example.orderprocessor {
    exports com.example.orderprocessor.spi;

    // Bu SPI-ı hanki modul implement edir?
    uses com.example.orderprocessor.spi.PaymentProvider;
}

// Implementation modulda:
module com.example.stripepayment {
    requires com.example.orderprocessor;
    provides com.example.orderprocessor.spi.PaymentProvider
        with com.example.stripepayment.StripePaymentProvider;
}

module com.example.paypalpayment {
    requires com.example.orderprocessor;
    provides com.example.orderprocessor.spi.PaymentProvider
        with com.example.paypalpayment.PayPalPaymentProvider;
}

// Runtime-da ServiceLoader ilə yüklənir:
ServiceLoader<PaymentProvider> providers =
    ServiceLoader.load(PaymentProvider.class);

providers.forEach(provider -> {
    System.out.println(provider.getName()); // "Stripe", "PayPal"
});
```

---

## Modul tipləri

```java
// ─── Named module ──────────────────────────────────────
// module-info.java var → named module
// Tam modul sistemi qaydaları tətbiq olunur

// ─── Automatic module ────────────────────────────────
// module-info.java YOX, amma --module-path-da
// MANIFEST.MF-dəki Automatic-Module-Name istifadə olunur
// Ya da jar adından (commons-lang3.jar → commons.lang3)
// Bütün paketlər export edilir, classpath modullarına requires edir

// ─── Unnamed module (classpath) ───────────────────────
// -cp (classpath) ilə yüklənən hər şey — unnamed module
// Bütün paketlər export olunur
// Named module-lar unnamed module-dan require edə bilməz!

// ─── Platform modules (JDK) ───────────────────────────
// java.base     → String, Object, Collections, vb. (həmişə var)
// java.sql      → JDBC
// java.desktop  → AWT, Swing
// java.logging  → java.util.logging
// java.naming   → JNDI
// java.net.http → HttpClient (Java 11+)
// jdk.crypto.ec → EC cryptography

// JDK modul qrafiki:
// java --list-modules
// java -d java.sql (java.sql-un dependencies)
```

---

## Spring Boot ilə modullar

```java
// ─── Spring Boot + JPMS ───────────────────────────────
// Spring Boot 3.x JPMS dəstəkləyir, amma hər dependency modul deyil

module com.example.orderapp {

    requires spring.context;
    requires spring.beans;
    requires spring.web;
    requires spring.boot;
    requires spring.boot.autoconfigure;

    // JPA
    requires jakarta.persistence;
    requires spring.data.jpa;
    requires org.hibernate.orm.core;

    // Validation
    requires jakarta.validation;
    requires org.hibernate.validator;

    // Jackson
    requires com.fasterxml.jackson.databind;
    requires com.fasterxml.jackson.datatype.jsr310;

    // Spring-ə reflection icazəsi
    opens com.example.orderapp to spring.core, spring.beans, spring.context;
    opens com.example.orderapp.domain to org.hibernate.orm.core;
    opens com.example.orderapp.dto to com.fasterxml.jackson.databind;
    opens com.example.orderapp.controller to spring.web;

    // API paketi
    exports com.example.orderapp.api;
}

// ─── Praktik problem: Spring reflectioni ─────────────
// Spring @Component, @Autowired siniflərini reflection-la tapır
// JPMS opens olmadan → InaccessibleObjectException

// Həll: bütün Spring sinifləri üçün opens
module com.example.orderapp {
    opens com.example.orderapp to spring.core;  // @ComponentScan üçün

    // Hər paket üçün ayrı-ayrı açmaq (tövsiyə edilir):
    opens com.example.orderapp.service to spring.beans, spring.context;
    opens com.example.orderapp.repository to spring.data.jpa;
}

// ─── Minimal Spring Boot modul setup ─────────────────
// Ən sadə yanaşma: komponent paketlərini spring-ə aç
module com.example.myapp {
    requires spring.boot.autoconfigure;
    requires spring.boot;
    requires spring.web;
    requires spring.context;
    requires spring.beans;
    requires spring.core;

    opens com.example.myapp to
        spring.core,
        spring.beans,
        spring.context,
        spring.web;

    exports com.example.myapp;
}
```

---

## İntervyu Sualları

### 1. JPMS nədir və niyə gəldi?
**Cavab:** Java 9 (JEP 261). Classpath-in problemlərini həll etmək üçün: (1) JDK-nın internal API-lərini (`sun.misc.Unsafe`) gizlətmək; (2) Jar hell — version conflict-ləri azaltmaq; (3) Güclü encapsulation — export edilməmiş paketlər tamamilə gizlidir (public deyil, həqiqətən xaricdən görünmür); (4) Performans — JVM yalnız lazım olan modülları yükləyir.

### 2. exports vs opens fərqi?
**Cavab:** `exports` — compile + runtime type access: başqa modullar tipin metodlarını çağıra bilər. `opens` — reflection access: runtime-da `getDeclaredFields()`, `setAccessible(true)` işlənə bilər. Jackson, Hibernate, Spring reflection istifadə edir — `opens` lazımdır. `exports` olmadan tip görünmür; `opens` olmadan reflection blokanır. Ikisi birlikdə ola bilər.

### 3. requires transitive nədir?
**Cavab:** Dependency-ni irəliyə ötürmə. `A requires transitive B` — A-dan istifadə edən C modülü avtomatik B-yə də giriş əldə edir. `C requires A` yetərlidir — `C requires B` yazmaq lazım deyil. API modulları öz return tipləri olduğu dependency-ləri transitive export etməlidir. Məs: `spring.context requires transitive spring.beans` — spring.context istifadə edənlər spring.beans tiplərinə də giriş əldə edir.

### 4. Named, Unnamed, Automatic modul fərqi?
**Cavab:** **Named** — `module-info.java` var, tam JPMS qaydaları. **Unnamed** — classpath-dəki jar-lar, hamısı bir "unnamed module"da, qaydalar yoxdur. **Automatic** — module-path-da amma `module-info.java` yoxdur; jar adından modul adı çıxarılır; bütün paketlər export olunur; backward compat üçün. Named module unnamed module-dan require edə bilməz — bu əsas məhdudiyyətdir.

### 5. Spring Boot ilə JPMS istifadəsi çətin mi?
**Cavab:** Bəli, kompleksdir. Spring reflection intensiv istifadə edir — hər komponent sinfini Spring-ə `opens` etmək lazımdır. Bütün third-party kitabxanalar (Hibernate, Jackson, vb.) ayrıca `opens` tələb edir. Çoxlu dependency-nin module-info.java-sı yoxdur (automatic module) — tam modul qrafini çox böyük olur. Böyük enterprise layihələrdə JPMS əvəzinə OSGi ya da service mesh daha çox istifadə olunur. JPMS daha çox library/framework müəllifləri üçün faydalıdır.

*Son yenilənmə: 2026-04-10*
