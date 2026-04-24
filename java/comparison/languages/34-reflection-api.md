# Reflection API

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Reflection (əks etdirmə) proqramın öz strukturunu runtime-da analiz etmə və dəyişdirmə qabiliyyətidir. Bu, siniflərin, metodların, xüsusiyyətlərin və konstruktorların runtime-da yoxlanılmasını, hətta private üzvlərə müdaxilə edilməsini mümkün edir. Reflection müasir framework-ların (Spring, Laravel, Symfony) əsasını təşkil edir - Dependency Injection, ORM, routing, validation kimi funksionallıqlar reflection olmadan mümkün olmazdı.

## Java-da istifadəsi

### Class obyektini əldə etmə

```java
import java.lang.reflect.*;

class İstifadəçi {
    private String ad;
    private int yaş;
    public String email;

    public İstifadəçi() {}

    public İstifadəçi(String ad, int yaş) {
        this.ad = ad;
        this.yaş = yaş;
    }

    private String gizliMetod() {
        return "Bu gizli metoddur";
    }

    public String getAd() {
        return ad;
    }

    public void setAd(String ad) {
        this.ad = ad;
    }

    public String salamla(String mesaj) {
        return mesaj + ", " + ad + "!";
    }
}

// Class obyektini əldə etmənin 3 yolu
Class<?> sinif1 = İstifadəçi.class;                      // sinif adından
Class<?> sinif2 = new İstifadəçi().getClass();            // obyektdən
Class<?> sinif3 = Class.forName("com.example.İstifadəçi"); // string-dən

// Sinif haqqında məlumat
System.out.println(sinif1.getName());          // com.example.İstifadəçi
System.out.println(sinif1.getSimpleName());    // İstifadəçi
System.out.println(sinif1.getPackageName());   // com.example
System.out.println(sinif1.getSuperclass());    // class java.lang.Object
System.out.println(sinif1.isInterface());      // false
System.out.println(sinif1.isEnum());           // false
System.out.println(sinif1.isRecord());         // false (Java 16+)

// İnterfeyslər
Class<?>[] interfeyslar = sinif1.getInterfaces();
for (Class<?> i : interfeyslar) {
    System.out.println("İnterfeys: " + i.getName());
}

// Modifikatorlar
int mod = sinif1.getModifiers();
System.out.println(Modifier.isPublic(mod));    // true
System.out.println(Modifier.isAbstract(mod));  // false
System.out.println(Modifier.isFinal(mod));     // false
```

### Field (sahə) ilə işləmə

```java
// Public sahələr
Field[] publicSahələr = İstifadəçi.class.getFields();

// Bütün sahələr (private daxil)
Field[] bütünSahələr = İstifadəçi.class.getDeclaredFields();

for (Field sahə : bütünSahələr) {
    System.out.printf("Sahə: %s, Tip: %s, Modifikator: %s%n",
        sahə.getName(),
        sahə.getType().getSimpleName(),
        Modifier.toString(sahə.getModifiers())
    );
}
// Sahə: ad, Tip: String, Modifikator: private
// Sahə: yaş, Tip: int, Modifikator: private
// Sahə: email, Tip: String, Modifikator: public

// Private sahəyə müdaxilə
İstifadəçi ist = new İstifadəçi("Orxan", 25);
Field adSahəsi = İstifadəçi.class.getDeclaredField("ad");
adSahəsi.setAccessible(true); // private-a giriş aç

String dəyər = (String) adSahəsi.get(ist);
System.out.println(dəyər); // "Orxan"

adSahəsi.set(ist, "Əli"); // dəyəri dəyişdir
System.out.println(ist.getAd()); // "Əli"
```

### Method (metod) ilə işləmə

```java
// Public metodlar (miras alınmışlar daxil)
Method[] publicMetodlar = İstifadəçi.class.getMethods();

// Yalnız bu sinifdə təyin olunmuş metodlar
Method[] öz_metodlar = İstifadəçi.class.getDeclaredMethods();

for (Method metod : öz_metodlar) {
    System.out.printf("Metod: %s, Qaytarma: %s, Parametrlər: %d%n",
        metod.getName(),
        metod.getReturnType().getSimpleName(),
        metod.getParameterCount()
    );
}

// Konkret metodu tapmaq
Method getAdMetodu = İstifadəçi.class.getMethod("getAd");
Method salamlaMetodu = İstifadəçi.class.getMethod("salamla", String.class);

// Metodu çağırmaq (invoke)
İstifadəçi ist = new İstifadəçi("Orxan", 25);
Object ad = getAdMetodu.invoke(ist);
System.out.println(ad); // "Orxan"

Object nəticə = salamlaMetodu.invoke(ist, "Salam");
System.out.println(nəticə); // "Salam, Orxan!"

// Private metodu çağırmaq
Method gizli = İstifadəçi.class.getDeclaredMethod("gizliMetod");
gizli.setAccessible(true);
Object gizliNəticə = gizli.invoke(ist);
System.out.println(gizliNəticə); // "Bu gizli metoddur"

// Parametr məlumatları
for (Parameter param : salamlaMetodu.getParameters()) {
    System.out.printf("Parametr: %s, Tip: %s%n",
        param.getName(), param.getType().getSimpleName());
}
```

### Constructor ilə işləmə

```java
// Konstruktorları almaq
Constructor<?>[] konstruktorlar = İstifadəçi.class.getDeclaredConstructors();

for (Constructor<?> k : konstruktorlar) {
    System.out.printf("Konstruktor: %s, Parametr sayı: %d%n",
        k.getName(), k.getParameterCount());
}

// Konstruktor ilə obyekt yaratmaq
Constructor<İstifadəçi> boşKonstruktor = İstifadəçi.class.getDeclaredConstructor();
İstifadəçi yeni1 = boşKonstruktor.newInstance();

Constructor<İstifadəçi> parametrli = İstifadəçi.class.getDeclaredConstructor(
    String.class, int.class
);
İstifadəçi yeni2 = parametrli.newInstance("Orxan", 25);
System.out.println(yeni2.getAd()); // "Orxan"
```

### Annotation (annotasiya) emalı

```java
import java.lang.annotation.*;

// Xüsusi annotasiya təyin etmə
@Retention(RetentionPolicy.RUNTIME) // runtime-da mövcud olsun
@Target({ElementType.TYPE, ElementType.METHOD, ElementType.FIELD})
@interface Validasiya {
    String mesaj() default "Düzgün deyil";
    int minUzunluq() default 0;
    int maxUzunluq() default Integer.MAX_VALUE;
}

@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.FIELD)
@interface Tələb_olunur {
    String mesaj() default "Bu sahə tələb olunur";
}

// Annotasiya istifadəsi
class Qeydiyyat {
    @Tələb_olunur(mesaj = "Ad boş ola bilməz")
    @Validasiya(minUzunluq = 2, maxUzunluq = 50)
    private String ad;

    @Tələb_olunur
    @Validasiya(mesaj = "Email düzgün deyil")
    private String email;

    private int yaş;
}

// Runtime-da annotasiyaları oxumaq
class Validator {
    public static List<String> yoxla(Object obj) throws Exception {
        List<String> xətalar = new ArrayList<>();
        Class<?> sinif = obj.getClass();

        for (Field sahə : sinif.getDeclaredFields()) {
            sahə.setAccessible(true);
            Object dəyər = sahə.get(obj);

            // @Tələb_olunur yoxlaması
            if (sahə.isAnnotationPresent(Tələb_olunur.class)) {
                if (dəyər == null || dəyər.toString().isEmpty()) {
                    Tələb_olunur ann = sahə.getAnnotation(Tələb_olunur.class);
                    xətalar.add(sahə.getName() + ": " + ann.mesaj());
                }
            }

            // @Validasiya yoxlaması
            if (sahə.isAnnotationPresent(Validasiya.class)) {
                Validasiya val = sahə.getAnnotation(Validasiya.class);
                if (dəyər instanceof String s) {
                    if (s.length() < val.minUzunluq()) {
                        xətalar.add(sahə.getName() +
                            ": minimum " + val.minUzunluq() + " simvol olmalıdır");
                    }
                    if (s.length() > val.maxUzunluq()) {
                        xətalar.add(sahə.getName() +
                            ": maksimum " + val.maxUzunluq() + " simvol ola bilər");
                    }
                }
            }
        }
        return xətalar;
    }
}
```

### Generic tiplər və Reflection

```java
import java.lang.reflect.ParameterizedType;

// Generic tipi runtime-da əldə etmək
abstract class BazaRepo<T> {
    private final Class<T> entityTipi;

    @SuppressWarnings("unchecked")
    public BazaRepo() {
        ParameterizedType tip = (ParameterizedType) getClass().getGenericSuperclass();
        this.entityTipi = (Class<T>) tip.getActualTypeArguments()[0];
    }

    public Class<T> getEntityTipi() {
        return entityTipi;
    }
}

class İstifadəçiRepo extends BazaRepo<İstifadəçi> {
    // entityTipi avtomatik olaraq İstifadəçi.class olacaq
}

İstifadəçiRepo repo = new İstifadəçiRepo();
System.out.println(repo.getEntityTipi()); // class İstifadəçi
```

## PHP-də istifadəsi

### ReflectionClass

```php
class İstifadəçi {
    private string $ad;
    private int $yaş;
    public string $email;

    public function __construct(string $ad, int $yaş) {
        $this->ad = $ad;
        $this->yaş = $yaş;
    }

    private function gizliMetod(): string {
        return "Bu gizli metoddur";
    }

    public function getAd(): string {
        return $this->ad;
    }

    public function setAd(string $ad): void {
        $this->ad = $ad;
    }

    public function salamla(string $mesaj): string {
        return "$mesaj, {$this->ad}!";
    }
}

// ReflectionClass yaratma
$ref = new ReflectionClass(İstifadəçi::class);
// və ya
$ref = new ReflectionClass('İstifadəçi');
// və ya obyektdən
$ref = new ReflectionClass(new İstifadəçi('Orxan', 25));

// Sinif haqqında məlumat
echo $ref->getName();           // İstifadəçi
echo $ref->getShortName();      // İstifadəçi (namespace olmadan)
echo $ref->getNamespaceName();  // namespace
echo $ref->getFileName();       // fayl yolu
echo $ref->getStartLine();      // sinifin başladığı sətir
echo $ref->getEndLine();        // sinifin bitdiyi sətir

var_dump($ref->isAbstract());      // false
var_dump($ref->isFinal());         // false
var_dump($ref->isInterface());     // false
var_dump($ref->isInstantiable());  // true
var_dump($ref->isInternal());      // false (PHP daxili sinif deyil)

// Valideyn sinif və interfeyslar
$parent = $ref->getParentClass();
$interfaces = $ref->getInterfaces();
$traits = $ref->getTraits();

// Konstruktor olmadan obyekt yaratmaq
$boşObyekt = $ref->newInstanceWithoutConstructor();

// Konstruktor ilə obyekt yaratmaq
$yeniObyekt = $ref->newInstanceArgs(['Orxan', 25]);
echo $yeniObyekt->getAd(); // "Orxan"
```

### ReflectionProperty

```php
// Bütün xüsusiyyətlər
$xüsusiyyətlər = $ref->getProperties();

// Filtr ilə
$publicXüs = $ref->getProperties(ReflectionProperty::IS_PUBLIC);
$privateXüs = $ref->getProperties(ReflectionProperty::IS_PRIVATE);

foreach ($ref->getProperties() as $prop) {
    echo sprintf(
        "Xüsusiyyət: %s, Tip: %s, Görünürlük: %s\n",
        $prop->getName(),
        $prop->getType()?->getName() ?? 'mixed',
        $prop->isPublic() ? 'public' :
            ($prop->isProtected() ? 'protected' : 'private')
    );
}
// Xüsusiyyət: ad, Tip: string, Görünürlük: private
// Xüsusiyyət: yaş, Tip: int, Görünürlük: private
// Xüsusiyyət: email, Tip: string, Görünürlük: public

// Private xüsusiyyətə giriş
$ist = new İstifadəçi('Orxan', 25);
$adProp = $ref->getProperty('ad');
$adProp->setAccessible(true); // PHP 8.1-dən etibarən lazım deyil

$dəyər = $adProp->getValue($ist);
echo $dəyər; // "Orxan"

$adProp->setValue($ist, 'Əli');
echo $ist->getAd(); // "Əli"

// Tip məlumatları
$tip = $adProp->getType();
echo $tip->getName();        // string
echo $tip->allowsNull();    // false
echo $tip->isBuiltin();     // true (PHP daxili tip)
```

### ReflectionMethod

```php
// Bütün metodlar
$metodlar = $ref->getMethods();

// Filtr ilə
$publicMetodlar = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
$privateMetodlar = $ref->getMethods(ReflectionMethod::IS_PRIVATE);

foreach ($ref->getMethods() as $metod) {
    $parametrlər = array_map(
        fn($p) => $p->getType()?->getName() . ' $' . $p->getName(),
        $metod->getParameters()
    );
    echo sprintf(
        "%s %s(%s): %s\n",
        $metod->isPublic() ? 'public' : 'private',
        $metod->getName(),
        implode(', ', $parametrlər),
        $metod->getReturnType()?->getName() ?? 'void'
    );
}

// Metodu çağırmaq (invoke)
$ist = new İstifadəçi('Orxan', 25);
$salamla = $ref->getMethod('salamla');
$nəticə = $salamla->invoke($ist, 'Salam');
echo $nəticə; // "Salam, Orxan!"

// Private metodu çağırmaq
$gizli = $ref->getMethod('gizliMetod');
$gizli->setAccessible(true);
echo $gizli->invoke($ist); // "Bu gizli metoddur"

// Parametr məlumatları
$salamlaMetod = $ref->getMethod('salamla');
foreach ($salamlaMetod->getParameters() as $param) {
    echo "Parametr: " . $param->getName() . "\n";
    echo "Tip: " . $param->getType()->getName() . "\n";
    echo "Mövqe: " . $param->getPosition() . "\n";
    echo "Default var?: " . ($param->isDefaultValueAvailable() ? 'bəli' : 'xeyr') . "\n";
    echo "Nullable?: " . ($param->allowsNull() ? 'bəli' : 'xeyr') . "\n";

    if ($param->isDefaultValueAvailable()) {
        echo "Default dəyər: " . $param->getDefaultValue() . "\n";
    }
}
```

### ReflectionAttribute (PHP 8.0+)

```php
// PHP 8.0 Attributes (Java annotasiyalarının analoqu)

#[Attribute(Attribute::TARGET_PROPERTY)]
class TələbOlunur {
    public function __construct(
        public string $mesaj = 'Bu sahə tələb olunur'
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Validasiya {
    public function __construct(
        public int $minUzunluq = 0,
        public int $maxUzunluq = PHP_INT_MAX,
        public string $mesaj = 'Düzgün deyil'
    ) {}
}

#[Attribute(Attribute::TARGET_CLASS)]
class Entity {
    public function __construct(
        public string $tablo = ''
    ) {}
}

// Attribute istifadəsi
#[Entity(tablo: 'istifadeciler')]
class Qeydiyyat {
    #[TələbOlunur(mesaj: 'Ad boş ola bilməz')]
    #[Validasiya(minUzunluq: 2, maxUzunluq: 50)]
    public string $ad = '';

    #[TələbOlunur]
    #[Validasiya(mesaj: 'Email düzgün deyil')]
    public string $email = '';

    public int $yaş = 0;
}

// Attribute-ları oxumaq
class Validator {
    public static function yoxla(object $obj): array {
        $xətalar = [];
        $ref = new ReflectionClass($obj);

        foreach ($ref->getProperties() as $prop) {
            $prop->setAccessible(true);
            $dəyər = $prop->getValue($obj);

            // TələbOlunur attribute-unu yoxla
            $tələbAttr = $prop->getAttributes(TələbOlunur::class);
            if (!empty($tələbAttr)) {
                $tələb = $tələbAttr[0]->newInstance();
                if (empty($dəyər)) {
                    $xətalar[] = $prop->getName() . ': ' . $tələb->mesaj;
                }
            }

            // Validasiya attribute-unu yoxla
            $valAttr = $prop->getAttributes(Validasiya::class);
            if (!empty($valAttr)) {
                $val = $valAttr[0]->newInstance();
                if (is_string($dəyər)) {
                    if (mb_strlen($dəyər) < $val->minUzunluq) {
                        $xətalar[] = $prop->getName() .
                            ": minimum {$val->minUzunluq} simvol";
                    }
                    if (mb_strlen($dəyər) > $val->maxUzunluq) {
                        $xətalar[] = $prop->getName() .
                            ": maksimum {$val->maxUzunluq} simvol";
                    }
                }
            }
        }
        return $xətalar;
    }
}

// İstifadə
$form = new Qeydiyyat();
$form->ad = 'O'; // çox qısa
$form->email = '';
$xətalar = Validator::yoxla($form);
// ['ad: minimum 2 simvol', 'email: Bu sahə tələb olunur']

// Sinif səviyyəsində attribute
$sinifRef = new ReflectionClass(Qeydiyyat::class);
$entityAttr = $sinifRef->getAttributes(Entity::class);
if (!empty($entityAttr)) {
    $entity = $entityAttr[0]->newInstance();
    echo "Tablo: " . $entity->tablo; // "istifadeciler"
}
```

### Framework-ların Reflection istifadəsi - Dependency Injection

```php
// Sadə DI Container nümunəsi
class Container {
    private array $bağlamalar = [];
    private array $singletonlar = [];

    public function bind(string $abstrakt, string|Closure $konkret): void {
        $this->bağlamalar[$abstrakt] = $konkret;
    }

    public function singleton(string $abstrakt, string|Closure $konkret): void {
        $this->bağlamalar[$abstrakt] = $konkret;
        $this->singletonlar[$abstrakt] = true;
    }

    public function make(string $abstrakt): object {
        // Singleton yoxlaması
        if (isset($this->singletonlar[$abstrakt]) && isset($this->nüsxələr[$abstrakt])) {
            return $this->nüsxələr[$abstrakt];
        }

        $konkret = $this->bağlamalar[$abstrakt] ?? $abstrakt;

        if ($konkret instanceof Closure) {
            $obyekt = $konkret($this);
        } else {
            $obyekt = $this->qur($konkret);
        }

        if (isset($this->singletonlar[$abstrakt])) {
            $this->nüsxələr[$abstrakt] = $obyekt;
        }

        return $obyekt;
    }

    private function qur(string $sinif): object {
        $ref = new ReflectionClass($sinif);

        if (!$ref->isInstantiable()) {
            throw new Exception("$sinif instantiate edilə bilməz");
        }

        $konstruktor = $ref->getConstructor();
        if ($konstruktor === null) {
            return $ref->newInstance();
        }

        $parametrlər = $konstruktor->getParameters();
        $asılılıqlar = [];

        foreach ($parametrlər as $param) {
            $tip = $param->getType();

            if ($tip === null) {
                if ($param->isDefaultValueAvailable()) {
                    $asılılıqlar[] = $param->getDefaultValue();
                } else {
                    throw new Exception(
                        "Parametr {$param->getName()} üçün tip göstərilməyib"
                    );
                }
                continue;
            }

            $tipAdı = $tip->getName();

            if ($tip->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $asılılıqlar[] = $param->getDefaultValue();
                } else {
                    throw new Exception(
                        "Primitiv tip {$tipAdı} avtomatik həll edilə bilməz"
                    );
                }
            } else {
                // Rekursiv olaraq asılılığı yarat
                $asılılıqlar[] = $this->make($tipAdı);
            }
        }

        return $ref->newInstanceArgs($asılılıqlar);
    }

    private array $nüsxələr = [];
}

// İstifadə nümunəsi
interface LoggerInterface {
    public function log(string $mesaj): void;
}

class FileLogger implements LoggerInterface {
    public function log(string $mesaj): void {
        echo "[LOG] $mesaj\n";
    }
}

class İstifadəçiServisi {
    public function __construct(
        private LoggerInterface $logger,
        private İstifadəçiReposu $repo,
    ) {}

    public function qeydiyyat(string $ad): void {
        $this->logger->log("Yeni istifadəçi: $ad");
        $this->repo->yarat($ad);
    }
}

class İstifadəçiReposu {
    public function yarat(string $ad): void {
        echo "Yaradıldı: $ad\n";
    }
}

// Container quraşdırma
$container = new Container();
$container->bind(LoggerInterface::class, FileLogger::class);

// Avtomatik həll - Constructor parametrləri reflection ilə oxunur
$servis = $container->make(İstifadəçiServisi::class);
$servis->qeydiyyat("Orxan");
// [LOG] Yeni istifadəçi: Orxan
// Yaradıldı: Orxan
```

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Əsas sinif | `Class<T>` | `ReflectionClass` |
| Metod | `Method` | `ReflectionMethod` |
| Sahə | `Field` | `ReflectionProperty` |
| Konstruktor | `Constructor<T>` | `ReflectionMethod` (constructor) |
| Annotasiya/Attribute | `@Annotation` (Java 5+) | `#[Attribute]` (PHP 8.0+) |
| Private giriş | `setAccessible(true)` | `setAccessible(true)` (PHP <8.1) |
| Generics | Runtime-da type erasure | Generics yoxdur |
| Performans | Nisbətən yavaş | Nisbətən yavaş |
| Dinamik sinif yüklənməsi | `Class.forName()` | `new ReflectionClass($className)` |
| Proxy yaratma | `java.lang.reflect.Proxy` | Yoxdur (daxili), kitabxanalarla mümkün |

## Niyə belə fərqlər var?

### Java-nın yanaşması

Java reflection güclü amma mürəkkəbdir:

1. **Güclü tip sistemi**: Java-nın statik tip sistemi compile-time-da çox şeyi yoxlayır. Reflection bu yoxlamaları bypass edir, ona görə onu ehtiyatla istifadə etmək lazımdır. `setAccessible(true)` ilə private üzvlərə giriş Java-nın encapsulation prinsipini pozur.

2. **Type erasure**: Java generics runtime-da silinir (`List<String>` runtime-da sadəcə `List` olur). Bu, reflection ilə generic tip məlumatını əldə etməyi çətinləşdirir. Bunun səbəbi geriyə uyğunluqdur - Java 5-dən əvvəlki kodla uyğunluq saxlanmalıdır.

3. **Annotasiya sistemi**: Java annotasiyaları çox güclüdür - `@Retention`, `@Target`, `@Inherited` kimi meta-annotasiyalarla annotasiyanın davranışı dəqiq tənzimlənir. Spring, Hibernate kimi framework-lar annotasiyalara güclü şəkildə əsaslanır.

4. **Security Manager**: Java-da `SecurityManager` reflection-un nə edə biləcəyini məhdudlaşdıra bilər. Bu, etibarsız kodun private sahələrə girişinin qarşısını almaq üçündür.

### PHP-nin yanaşması

PHP reflection Java-dakı ilə konseptual olaraq eynidir, amma daha sadədir:

1. **Dinamik dil**: PHP dinamik tipli dil olduğu üçün reflection daha təbii hiss olunur. Artıq runtime-da çox şey dinamikdir, reflection sadəcə buna əlavədir.

2. **Attribute-lar gec gəldi**: PHP 8.0-a qədər annotasiya sistemi yox idi, framework-lar bunun əvəzinə PHPDoc comment-lərdən istifadə edirdi (`@var`, `@param` kimi). PHP 8.0 ilə gələn `#[Attribute]` sistemi Java-nın annotasiyalarına çox bənzəyir.

3. **DI Container-lərin əsası**: Laravel-in Service Container-i, Symfony-nin DI komponenti tamamilə reflection üzərində qurulub. Constructor parametrlərinin tiplərini oxuyaraq asılılıqları avtomatik həll edirlər.

4. **PHP 8.1+ sadələşdirmə**: PHP 8.1-dən etibarən `setAccessible(true)` çağırmaq lazım deyil - bütün reflection əməliyyatları avtomatik olaraq bütün üzvlərə giriş verir. Bu, API-ni sadələşdirir.

### Nəticə

Reflection hər iki dildə framework-ların əsasını təşkil edir. Java-da annotasiyalar və reflection güclü tip sistemi ilə birləşərək ciddi enterprise tətbiqlərin skeletini qurur. PHP-də isə Attribute-lar (PHP 8.0+) və reflection DI container-lərin, ORM-lərin və routing sistemlərinin işləməsini təmin edir. Hər iki dildə reflection performans baxımından bahalıdır və nəticələr adətən cache olunur.
