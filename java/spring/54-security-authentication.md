# 54 — Spring Security Autentifikasiya

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [UserDetails İnterfeysi](#userdetails-i̇nterfeysi)
2. [UserDetailsService](#userdetailsservice)
3. [GrantedAuthority](#grantedauthority)
4. [PasswordEncoder](#passwordencoder)
5. [DaoAuthenticationProvider](#daoauthenticationprovider)
6. [Custom AuthenticationProvider](#custom-authenticationprovider)
7. [Remember-Me Autentifikasiyası](#remember-me-autentifikasiyası)
8. [Session İdarəetməsi](#session-i̇darəetməsi)
9. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## UserDetails İnterfeysi

`UserDetails` — Spring Security-nin istifadəçi məlumatlarını saxlamaq üçün istifadə etdiyi əsas interfeysdır. Öz `User` entity-nizi bu interfeysi implement edərək Spring Security ilə inteqrasiya edirsiniz.

```java
public interface UserDetails extends Serializable {
    // İstifadəçinin icazələri/rolları
    Collection<? extends GrantedAuthority> getAuthorities();

    // Şifrə (hash edilmiş olmalıdır)
    String getPassword();

    // İstifadəçi adı (unikal olmalıdır - login üçün istifadə olunur)
    String getUsername();

    // Hesabın müddəti bitibmi?
    boolean isAccountNonExpired();

    // Hesab bloklanıbmı?
    boolean isAccountNonLocked();

    // Şifrənin müddəti bitibmi?
    boolean isCredentialsNonExpired();

    // Hesab aktif/deaktivdir?
    boolean isEnabled();
}
```

### UserDetails İmplementasiyası

```java
// Seçim 1: User entity-ni birbaşa UserDetails implement etdir
@Entity
@Table(name = "users")
public class User implements UserDetails {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(unique = true, nullable = false)
    private String email;

    @Column(nullable = false)
    private String password; // BCrypt hash saxlanılır

    private boolean enabled = true;
    private boolean accountNonLocked = true;

    @ManyToMany(fetch = FetchType.EAGER)
    @JoinTable(name = "user_roles",
        joinColumns = @JoinColumn(name = "user_id"),
        inverseJoinColumns = @JoinColumn(name = "role_id"))
    private Set<Role> roles = new HashSet<>();

    @Override
    public Collection<? extends GrantedAuthority> getAuthorities() {
        // Rolları GrantedAuthority-ə çevir
        return roles.stream()
            .map(role -> new SimpleGrantedAuthority(role.getName()))
            .collect(Collectors.toList());
    }

    @Override
    public String getUsername() {
        return email; // Email-i username kimi istifadə edirik
    }

    @Override
    public boolean isAccountNonExpired() {
        return true; // Hesab heç vaxt expire olmur
    }

    @Override
    public boolean isAccountNonLocked() {
        return accountNonLocked;
    }

    @Override
    public boolean isCredentialsNonExpired() {
        return true; // Şifrə heç vaxt expire olmur (manual idarə edə bilərsiniz)
    }

    @Override
    public boolean isEnabled() {
        return enabled;
    }

    // Getter/Setter-lər...
}

// Seçim 2: Ayrı UserDetails wrapper sinfi yaratmaq (tövsiyə edilir)
public class CustomUserDetails implements UserDetails {

    private final User user; // Öz User entity-niz

    public CustomUserDetails(User user) {
        this.user = user;
    }

    @Override
    public Collection<? extends GrantedAuthority> getAuthorities() {
        return user.getRoles().stream()
            .map(role -> new SimpleGrantedAuthority("ROLE_" + role.getName()))
            .collect(Collectors.toList());
    }

    @Override
    public String getPassword() {
        return user.getPassword();
    }

    @Override
    public String getUsername() {
        return user.getEmail();
    }

    @Override
    public boolean isEnabled() {
        return user.isActive();
    }

    @Override public boolean isAccountNonExpired() { return true; }
    @Override public boolean isAccountNonLocked() { return true; }
    @Override public boolean isCredentialsNonExpired() { return true; }

    // Entity-yə birbaşa müraciət üçün
    public User getUser() {
        return user;
    }
}
```

---

## UserDetailsService

`UserDetailsService` — istifadəçini veritabanından tapan interfeysdır. `DaoAuthenticationProvider` bu interfeysi çağırır.

```java
public interface UserDetailsService {
    // İstifadəçi tapılmasa - UsernameNotFoundException atılmalıdır
    UserDetails loadUserByUsername(String username) throws UsernameNotFoundException;
}
```

### UserDetailsService İmplementasiyası

```java
@Service
public class CustomUserDetailsService implements UserDetailsService {

    private final UserRepository userRepository;

    public CustomUserDetailsService(UserRepository userRepository) {
        this.userRepository = userRepository;
    }

    @Override
    public UserDetails loadUserByUsername(String username) throws UsernameNotFoundException {
        // Email ilə istifadəçini tap
        User user = userRepository.findByEmail(username)
            .orElseThrow(() -> new UsernameNotFoundException(
                "İstifadəçi tapılmadı: " + username
            ));

        // UserDetails qaytarır
        return new CustomUserDetails(user);
    }
}

// UserRepository:
public interface UserRepository extends JpaRepository<User, Long> {
    Optional<User> findByEmail(String email);
}
```

### YANLIŞ vs DOĞRU

```java
// YANLIŞ: Spesifik xəta mesajı vermək (security riski!)
@Override
public UserDetails loadUserByUsername(String username) throws UsernameNotFoundException {
    return userRepository.findByEmail(username)
        .map(CustomUserDetails::new)
        .orElseThrow(() -> new UsernameNotFoundException(
            "Bu email mövcud deyil: " + username  // Hacker email-in mövcudluğunu bilir!
        ));
}

// DOGRU: Ümumi xəta mesajı
@Override
public UserDetails loadUserByUsername(String username) throws UsernameNotFoundException {
    return userRepository.findByEmail(username)
        .map(CustomUserDetails::new)
        .orElseThrow(() -> new UsernameNotFoundException(
            "Yanlış istifadəçi adı və ya şifrə"  // Heç bir məlumat sızmır
        ));
}
```

---

## GrantedAuthority

`GrantedAuthority` — istifadəçinin bir icazəsini (rol və ya hüquq) təmsil edən interfeysdır.

```java
public interface GrantedAuthority extends Serializable {
    String getAuthority(); // Məsələn: "ROLE_ADMIN", "READ_PRIVILEGE"
}

// Əsas implementasiya:
GrantedAuthority authority = new SimpleGrantedAuthority("ROLE_ADMIN");

// Rol vs İcazə:
// ROL    → "ROLE_ADMIN", "ROLE_USER"    (geniş)
// İCAZƏ  → "READ_USER", "DELETE_POST"  (dəqiq)

@Entity
public class Role {
    @Id
    private Long id;
    private String name; // "ADMIN", "USER", "MODERATOR"

    // Rol üçün icazələr (opsional)
    @ManyToMany
    private Set<Permission> permissions;
}

// İstifadəçinin authority-lərini rolelardan və permissionlardan yarat:
@Override
public Collection<? extends GrantedAuthority> getAuthorities() {
    List<GrantedAuthority> authorities = new ArrayList<>();

    for (Role role : user.getRoles()) {
        // Rol əlavə et
        authorities.add(new SimpleGrantedAuthority("ROLE_" + role.getName()));

        // Rola aid icazələri əlavə et
        for (Permission permission : role.getPermissions()) {
            authorities.add(new SimpleGrantedAuthority(permission.getName()));
        }
    }

    return authorities;
}
```

---

## PasswordEncoder

Şifrəni heç vaxt açıq mətnlə saxlamayın! Spring Security güclü hash alqoritmləri təqdim edir.

### BCryptPasswordEncoder

```java
// BCrypt - tövsiyə edilən standart encoder
@Bean
public PasswordEncoder passwordEncoder() {
    // strength: 4-31 arası (default: 10)
    // Yüksək strength = daha yavaş hash = daha güclü
    return new BCryptPasswordEncoder(12); // 12 = yaxşı balans
}

// İstifadəsi:
@Service
public class UserService {

    @Autowired
    private PasswordEncoder passwordEncoder;

    @Autowired
    private UserRepository userRepository;

    public User registerUser(RegisterRequest request) {
        User user = new User();
        user.setEmail(request.getEmail());
        // Şifrəni hash et və saxla
        user.setPassword(passwordEncoder.encode(request.getPassword()));
        return userRepository.save(user);
    }

    // Şifrə yoxlama (login zamanı Spring Security özü edir, buna ehtiyac yoxdur)
    public boolean checkPassword(String rawPassword, String encodedPassword) {
        return passwordEncoder.matches(rawPassword, encodedPassword);
    }
}
```

### YANLIŞ vs DOĞRU

```java
// YANLIŞ: Şifrəni açıq mətndə saxlamaq
@Entity
public class User {
    private String password; // "mypassword123" - açıq mətn! BÖYÜK RİSK!
}

// YANLIŞ: Zəif MD5 hash istifadə etmək
String weakHash = DigestUtils.md5Hex(rawPassword); // MD5 çox asanlıqla sındırılır

// YANLIŞ: PasswordEncoder-siz saxlamaq
user.setPassword(request.getPassword()); // Açıq mətn veritabanına gedir!

// DOGRU: BCrypt ilə hash
user.setPassword(passwordEncoder.encode(request.getPassword()));
// Nəticə: "$2a$12$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy"

// DOGRU: DelegatingPasswordEncoder - köhnə sistemlər üçün
@Bean
public PasswordEncoder passwordEncoder() {
    // Fərqli hash-ları dəstəkləyir, BCrypt default-dur
    return PasswordEncoderFactories.createDelegatingPasswordEncoder();
}
```

### Digər Encoder-lər

```java
// Argon2 - daha müasir (memory-hard)
@Bean
public PasswordEncoder argon2PasswordEncoder() {
    return new Argon2PasswordEncoder(16, 32, 1, 60000, 10);
}

// SCrypt - memory-hard
@Bean
public PasswordEncoder scryptPasswordEncoder() {
    return SCryptPasswordEncoder.defaultsForSpringSecurity_v5_8();
}

// PBKDF2 - FIPS uyğun sistemlər üçün
@Bean
public PasswordEncoder pbkdf2PasswordEncoder() {
    return Pbkdf2PasswordEncoder.defaultsForSpringSecurity_v5_8();
}
```

---

## DaoAuthenticationProvider

`DaoAuthenticationProvider` — `UserDetailsService` və `PasswordEncoder` istifadə edərək autentifikasiya edən standart providerdir.

```java
@Configuration
@EnableWebSecurity
public class SecurityConfig {

    @Autowired
    private CustomUserDetailsService userDetailsService;

    @Bean
    public PasswordEncoder passwordEncoder() {
        return new BCryptPasswordEncoder(12);
    }

    @Bean
    public DaoAuthenticationProvider authenticationProvider() {
        DaoAuthenticationProvider provider = new DaoAuthenticationProvider();
        // İstifadəçi məlumatları servisi
        provider.setUserDetailsService(userDetailsService);
        // Şifrə encoderi
        provider.setPasswordEncoder(passwordEncoder());
        // İstifadəçi tapılmadıqda username-i gizlətmək (default: true)
        provider.setHideUserNotFoundExceptions(true);
        return provider;
    }

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            .authenticationProvider(authenticationProvider())
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/api/auth/**").permitAll()
                .anyRequest().authenticated()
            );
        return http.build();
    }
}
```

---

## Custom AuthenticationProvider

Bəzən standart provider kifayət etmir. Məsələn, OTP (One-Time Password), LDAP, və ya xüsusi token sistemi üçün custom provider lazımdır.

```java
// OTP autentifikasiya nümunəsi:
@Component
public class OtpAuthenticationProvider implements AuthenticationProvider {

    private final UserRepository userRepository;
    private final OtpService otpService;

    public OtpAuthenticationProvider(UserRepository userRepository,
                                      OtpService otpService) {
        this.userRepository = userRepository;
        this.otpService = otpService;
    }

    @Override
    public Authentication authenticate(Authentication authentication)
            throws AuthenticationException {

        // OtpAuthenticationToken-dan məlumatları al
        OtpAuthenticationToken otpToken = (OtpAuthenticationToken) authentication;
        String phone = otpToken.getPhone();
        String otp = otpToken.getOtp();

        // Telefonu yoxla
        User user = userRepository.findByPhone(phone)
            .orElseThrow(() -> new BadCredentialsException("Telefon tapılmadı"));

        // OTP-ni yoxla
        if (!otpService.isValid(phone, otp)) {
            throw new BadCredentialsException("OTP yanlışdır və ya müddəti bitib");
        }

        // Uğurlu - CustomUserDetails-i qaytar
        CustomUserDetails userDetails = new CustomUserDetails(user);
        return new UsernamePasswordAuthenticationToken(
            userDetails, null, userDetails.getAuthorities()
        );
    }

    @Override
    public boolean supports(Class<?> authentication) {
        // Yalnız OtpAuthenticationToken üçün işlə
        return OtpAuthenticationToken.class.isAssignableFrom(authentication);
    }
}

// Custom Authentication Token:
public class OtpAuthenticationToken extends AbstractAuthenticationToken {

    private final String phone;
    private final String otp;

    // Autentifikasiyadan əvvəl
    public OtpAuthenticationToken(String phone, String otp) {
        super(null);
        this.phone = phone;
        this.otp = otp;
        setAuthenticated(false);
    }

    // Autentifikasiyadan sonra
    public OtpAuthenticationToken(Object principal,
                                   Collection<? extends GrantedAuthority> authorities) {
        super(authorities);
        this.phone = null;
        this.otp = null;
        setPrincipal(principal);
        setAuthenticated(true);
    }

    public String getPhone() { return phone; }
    public String getOtp() { return otp; }

    @Override
    public Object getCredentials() { return otp; }

    @Override
    public Object getPrincipal() { return phone; }
}
```

---

## Remember-Me Autentifikasiyası

Remember-Me — istifadəçinin brauzer bağlandıqdan sonra da giriş vəziyyətinin qalmasını təmin edir.

```java
@Configuration
@EnableWebSecurity
public class SecurityConfig {

    @Autowired
    private UserDetailsService userDetailsService;

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            .formLogin(form -> form
                .loginPage("/login")
                .defaultSuccessUrl("/dashboard")
            )
            // Remember-Me konfiqurasiyası
            .rememberMe(rememberMe -> rememberMe
                .key("myUniqueSecretKey123!")   // Cookie-ni imzalamaq üçün gizli açar
                .tokenValiditySeconds(7 * 24 * 60 * 60) // 7 gün (saniyə ilə)
                .rememberMeParameter("remember-me")      // Form checkbox adı
                .userDetailsService(userDetailsService)
            );
        return http.build();
    }
}

// Daha təhlükəsiz variant: Persistent Token (veritabanında saxlanılır)
@Configuration
public class SecurityConfig {

    @Autowired
    private DataSource dataSource;

    @Bean
    public PersistentTokenRepository persistentTokenRepository() {
        JdbcTokenRepositoryImpl tokenRepository = new JdbcTokenRepositoryImpl();
        tokenRepository.setDataSource(dataSource);
        // tokenRepository.setCreateTableOnStartup(true); // İlk dəfə cədvəl yarat
        return tokenRepository;
    }

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http.rememberMe(rememberMe -> rememberMe
            .tokenRepository(persistentTokenRepository())  // DB-də saxla
            .tokenValiditySeconds(7 * 24 * 60 * 60)
        );
        return http.build();
    }
}
```

```sql
-- persistent_logins cədvəli (PostgreSQL):
CREATE TABLE persistent_logins (
    username  VARCHAR(64) NOT NULL,
    series    VARCHAR(64) PRIMARY KEY,
    token     VARCHAR(64) NOT NULL,
    last_used TIMESTAMP   NOT NULL
);
```

---

## Session İdarəetməsi

### Session Yaratma Siyasəti

```java
@Bean
public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
    http.sessionManagement(session -> session
        // Mövcud session-ı istifadə et, yoxdursa yarat
        .sessionCreationPolicy(SessionCreationPolicy.IF_REQUIRED) // default

        // Heç vaxt session yaratma, amma varsa istifadə et
        .sessionCreationPolicy(SessionCreationPolicy.NEVER)

        // Session yaratma, varsa da istifadə etmə (tam stateless - JWT üçün)
        .sessionCreationPolicy(SessionCreationPolicy.STATELESS)

        // Həmişə yeni session yarat
        .sessionCreationPolicy(SessionCreationPolicy.ALWAYS)
    );
    return http.build();
}
```

### Session Fixation Qoruması

```java
http.sessionManagement(session -> session
    // Session Fixation hücumundan qorumaq üçün:

    // Yeni session yarat, məlumatları köçür (default - tövsiyə edilir)
    .sessionFixation(fixation -> fixation.migrateSession())

    // Yeni session yarat, məlumatları köçürmə
    .sessionFixation(fixation -> fixation.newSession())

    // Session ID-ni dəyişdirmə (az qoruma)
    .sessionFixation(fixation -> fixation.none())
);
```

### Concurrent Session Nəzarəti

```java
@Configuration
@EnableWebSecurity
public class SecurityConfig {

    @Bean
    public HttpSessionEventPublisher httpSessionEventPublisher() {
        // Session hadisələrini Spring-ə bildirmək üçün lazımdır
        return new HttpSessionEventPublisher();
    }

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http.sessionManagement(session -> session
            // Maksimum eyni vaxtda neçə session açıq ola bilər?
            .maximumSessions(1)
            // true: köhnə session silinsin (yeni giriş qazanır)
            // false: yeni girişə icazə verilməsin (köhnə session qorunur)
            .maxSessionsPreventsLogin(false)
            // Maksimum session aşıldıqda yönləndirmə
            .expiredUrl("/session-expired")
        );
        return http.build();
    }
}
```

### YANLIŞ vs DOĞRU

```java
// YANLIŞ: JWT ilə session istifadə etmək
@Bean
public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
    http
        // JWT istifadə edirik amma session açıqdır - resurs itkisi!
        .addFilterBefore(jwtFilter, UsernamePasswordAuthenticationFilter.class)
        .authorizeHttpRequests(auth -> auth.anyRequest().authenticated());
    // Session siyasəti qoyulmayıb - gereksiz session-lar yaradılır
    return http.build();
}

// DOGRU: JWT ilə stateless session
@Bean
public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
    http
        .sessionManagement(session -> session
            .sessionCreationPolicy(SessionCreationPolicy.STATELESS) // Session yoxdur
        )
        .addFilterBefore(jwtFilter, UsernamePasswordAuthenticationFilter.class)
        .authorizeHttpRequests(auth -> auth.anyRequest().authenticated());
    return http.build();
}
```

---

## İntervyu Sualları

**S: UserDetails ilə UserDetailsService arasındakı fərq nədir?**

C: `UserDetails` — istifadəçinin məlumatlarını (username, password, authorities, account status) saxlayan interfeysdır. `UserDetailsService` — istifadəçini username-ə görə veritabanından tapan və `UserDetails` qaytaran interfeysdır. `DaoAuthenticationProvider` autentifikasiya zamanı `UserDetailsService.loadUserByUsername()` çağırır, nəticəni `UserDetails` kimi alır və şifrəni yoxlayır.

---

**S: BCrypt niyə MD5-dən daha yaxşıdır?**

C: MD5 kriptografik cəhətdən zəifdir, GPU-lar vasitəsilə saniyədə milyardlarla hash hesablanır. BCrypt isə əsasən üç səbəbdən üstündür: 1) **Salt** — hər hash üçün random dəyər əlavə edir, eyni şifrə fərqli hash verir; 2) **Yavaşlıq** — `cost factor` (strength) parametri ilə hash hesablama qəsdən yavaşladılır; 3) **Adaptive** — vaxt keçdikcə strength artırıla bilər.

---

**S: `hideUserNotFoundExceptions` nədir?**

C: `DaoAuthenticationProvider`-in bir xüsusiyyətidir. Default olaraq `true`-dur. Bu aktiv olduqda, `loadUserByUsername()` `UsernameNotFoundException` atsa belə, xarici dünyaya `BadCredentialsException` göstərilir. Bu sayədə hücumçular email-in veritabanında mövcud olub-olmadığını login xətasından anlaya bilmirlər.

---

**S: Persistent Remember-Me token Simple Remember-Me-dən nə ilə fərqlənir?**

C: Simple Remember-Me cookie-yə username, expiration time və hash yazır — veritabanı lazım deyil. Persistent Remember-Me isə cookie-yə yalnız series+token yazır, əsl məlumat veritabanındadır. Bu daha təhlükəsizdir, çünki: token oğurlanıb istifadə edilsə sistem bunu aşkarlayır (yenilənmiş tokenla yeni token uyğun gəlmir), bütün sessiyalar server tərəfindən ləğv edilə bilər.

---

**S: Session Fixation hücumu nədir?**

C: Hücumçu öncədən bir session ID əldə edib qurbana göndərir. Qurban bu session ilə login olarsa, hücumçu həmin session ilə artıq autentifikalı giriş əldə edə bilər. Spring Security bu hücumdan `.sessionFixation().migrateSession()` ilə qorunur — login zamanı yeni session ID yaradılır, köhnə session ID-si artıq keçərsizdir.
