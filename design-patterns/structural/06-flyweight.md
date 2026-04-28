# Flyweight (Lead ⭐⭐⭐⭐)

## İcmal
Flyweight pattern, çoxlu oxşar object-lərin paylaşılan state-ini bir yerdə saxlayaraq yaddaş istehlakını azaldır. Hər object-in özündə saxlayacağı dəyərlər iki hissəyə bölünür: hamı ilə paylaşılan immutable intrinsic state (flyweight-də), hər instance üçün fərqli extrinsic state (çöldən ötürülür).

## Niyə Vacibdir
PHP tətbiqlərinin yaddaş limiti (128MB–256MB) var. 10,000 oxşar object yaratdıqda hər biri duplicate metadata saxlayırsa, yaddaş sürətlə tükənir. Flyweight ilə 10,000 position reference + 1 shared flyweight istifadə olunur. Laravel-in özündə bu ideya müxtəlif yerlərdə tətbiq edilir: compiled view caching, connection pooling, config singleton-lar.

## Əsas Anlayışlar
- **Intrinsic state**: flyweight object-ində saxlanılan, bütün kontekstlər arasında paylaşılan, immutable data (character font/size, country metadata, icon SVG)
- **Extrinsic state**: kontekstə xas data; flyweight-ə method çağırışı zamanı parametr kimi ötürülür (x/y position, user-specific data)
- **FlyweightFactory**: mövcud flyweight-lərə cache; eyni key ilə yeni yaratmaq əvəzinə mövcudu qaytarır
- **Client**: extrinsic state-i özündə saxlayır, intrinsic state-ə flyweight vasitəsilə çatır
- **Object identity illusion**: client hər flyweight-i ayrı object kimi görsə də, faktiki olaraq shared reference istifadə edir

## Praktik Baxış
- **Real istifadə**: text editor (hər simvol üçün ayrı font object-i yaratmamaq), game engine (eyni tree/grass sprite minlərlə yerə render), geographic data (ölkə metadata), icon library, database connection pool, compiled template cache
- **Trade-off-lar**: significant memory reduction (10,000 → 1 object); lakin code mürəkkəbliyi artır; extrinsic/intrinsic ayrımını izləmək çətin olur; extrinsic state-i hər çağırışda ötürmək convenience-ı azaldır
- **İstifadə etməmək**: object sayı azdırsa (premature optimization); extrinsic state intrinsic state-dən böyükdürsə (gain yoxdur); object-lər unikal dataya malikdirsə (paylaşmaq mümkün deyil)
- **Common mistakes**: intrinsic state-i mutable etmək (paylaşılan state dəyişsə hamı təsirlənər); flyweight-i singleton ilə qarışdırmaq (singleton tək instance, flyweight kategori başına bir instance); factory olmadan istifadə etmək (sharing işləmir — hər çağırışda yeni object yaranır)

### Anti-Pattern Nə Zaman Olur?
Flyweight **premature optimization** kontekstdə anti-pattern-ə çevrilir:

- **Problem olmadan tətbiq etmək**: Yaddaş problemi olmayan yerdə Flyweight qurmaq — code mürəkkəbliyi artır, heç bir real fayda yoxdur. `memory_get_peak_usage()` ilə ölçmədən Flyweight tətbiq etmək premature optimization-dur.
- **Intrinsic state-i mutable etmək**: `$flyweight->color = 'red'` — bu flyweight-i paylaşan bütün client-lər indi qırmızı görür. `final` + `readonly` istifadə etməyin əsas səbəbi budur.
- **Extrinsic state-i flyweight içinə sızmaq**: Constructor-a `$userId` kimi extrinsic data ötürmək — bu flyweight-i həmin user üçün lock edir. Başqa user paylaşa bilmir.
- **Thread safety göz ardı etmək**: Factory-nin `$cache` array-i static property-dirsə, PHP-FPM worker-lar arasında paylaşılmır (PHP process-per-request). Amma long-running proseslərdə (Octane, RoadRunner) race condition yarana bilər.
- **Yanlış qatlamaq**: Flyweight + Composite + Decorator eyni yerdə — bu 3 pattern birlikdə debuggability-i məhv edir. Hər pattern-i ayrıca tətbiq edin.

## Nümunələr

### Ümumi Nümunə
Meşə simulyasiyasını düşünün: 10,000 ağac var. Hər ağacın `TreeType` (ad, rəng, texture — 2MB data) var, lakin 3 növ ağac var. 10,000 ağac üçün 10,000 TreeType = 20GB; Flyweight ilə 3 TreeType + 10,000 position = ~6MB. Position (x, y) extrinsic state-dir — hər ağaca xasdır. TreeType intrinsic — hamı paylaşır.

### PHP/Laravel Nümunəsi

```php
<?php

namespace App\Flyweight;

// ─────────────────────────────────────────────
// FLYWEIGHT — intrinsic state
// final + readonly: immutability şərtdir
// ─────────────────────────────────────────────

final class CharacterStyle
{
    // Bu data bütün eyni-stilli simvollar arasında paylaşılır
    // readonly: yaradıldıqdan sonra dəyişdirilə bilməz — thread safe
    public function __construct(
        public readonly string $fontFamily,  // 'Arial', 'Times New Roman'
        public readonly int    $fontSize,    // 12, 14, 16
        public readonly bool   $isBold,
        public readonly bool   $isItalic,
        public readonly string $color,       // '#000000'
    ) {}

    // Extrinsic state (char, x, y) — hər çağırışda parametr kimi gəlir
    // Flyweight özündə saxlamır — bu, "sharing" mümkün edən əsas prinsipdir
    public function render(string $char, int $x, int $y): string
    {
        $styles = [];
        if ($this->isBold)   $styles[] = 'font-weight:bold';
        if ($this->isItalic) $styles[] = 'font-style:italic';
        $styles[] = "font-family:{$this->fontFamily}";
        $styles[] = "font-size:{$this->fontSize}px";
        $styles[] = "color:{$this->color}";

        $style = implode(';', $styles);
        return "<span style='{$style}' data-x='{$x}' data-y='{$y}'>{$char}</span>";
    }
}

// ─────────────────────────────────────────────
// FLYWEIGHT FACTORY — cache + create
// Eyni key üçün həmişə eyni instance qaytarır
// ─────────────────────────────────────────────

class CharacterStyleFactory
{
    /** @var array<string, CharacterStyle> */
    private array $styles = [];

    public function getStyle(
        string $fontFamily,
        int    $fontSize,
        bool   $isBold   = false,
        bool   $isItalic = false,
        string $color    = '#000000'
    ): CharacterStyle {
        // Key: intrinsic state-in unikal kombinasiyası
        $key = "{$fontFamily}_{$fontSize}_{$isBold}_{$isItalic}_{$color}";

        if (!isset($this->styles[$key])) {
            // Yalnız yeni kombinasiya üçün yaradılır
            $this->styles[$key] = new CharacterStyle($fontFamily, $fontSize, $isBold, $isItalic, $color);
        }

        return $this->styles[$key]; // mövcud flyweight qaytarılır — yeni object yoxdur
    }

    public function getStyleCount(): int
    {
        return count($this->styles); // nə qədər unikal style object mövcuddur
    }
}

// ─────────────────────────────────────────────
// CLIENT — extrinsic state-i özündə saxlayır
// ─────────────────────────────────────────────

class TextCharacter
{
    // Extrinsic state — bu instance-a xas
    public function __construct(
        private readonly string         $char,   // 'H', 'e', 'l', ...
        private int                     $x,      // pixel position — extrinsic
        private int                     $y,
        private readonly CharacterStyle $style,  // shared flyweight — reference
    ) {}

    public function render(): string
    {
        // char, x, y — extrinsic; style — shared intrinsic
        return $this->style->render($this->char, $this->x, $this->y);
    }

    public function moveTo(int $x, int $y): void
    {
        // Position dəyişir — amma style dəyişmir, shared qalır
        $this->x = $x;
        $this->y = $y;
    }
}

// ─────────────────────────────────────────────
// USAGE — memory comparison demo
// ─────────────────────────────────────────────

class DocumentEditor
{
    /** @var TextCharacter[] */
    private array $characters = [];
    private CharacterStyleFactory $styleFactory;

    public function __construct()
    {
        $this->styleFactory = new CharacterStyleFactory();
    }

    public function addText(string $text, string $fontFamily, int $fontSize, bool $bold = false): void
    {
        // Bu text üçün style bir dəfə alınır — bütün simvollar paylaşır
        $style = $this->styleFactory->getStyle($fontFamily, $fontSize, $bold);

        $x = count($this->characters) * 8;
        foreach (str_split($text) as $i => $char) {
            // Hər simvol öz position-unu saxlayır (extrinsic)
            // Style-ı paylaşır (intrinsic) — yeni CharacterStyle yaranmır
            $this->characters[] = new TextCharacter($char, $x + ($i * 8), 0, $style);
        }
    }

    public function getStats(): array
    {
        return [
            'total_characters' => count($this->characters),
            'unique_styles'    => $this->styleFactory->getStyleCount(),
            'memory_saved'     => sprintf(
                "Without Flyweight: ~%d style objects; With Flyweight: %d style objects",
                count($this->characters),
                $this->styleFactory->getStyleCount()
            ),
        ];
    }
}

// Demo
$editor = new DocumentEditor();
$editor->addText("Hello, World!", "Arial", 14, bold: true);
$editor->addText("This is normal text with many characters repeated.", "Arial", 14);
$editor->addText("Some italic text here as well.", "Arial", 14, bold: false);

$stats = $editor->getStats();
// total_characters: 90+
// unique_styles: 2 (bold=true + bold=false) — 90 simvol, 2 CharacterStyle object
```

**Geographic Data Flyweight — real Laravel nümunəsi:**

```php
<?php

// Country metadata — minlərlə user profili üçün paylaşılan data
// final + readonly: mutable edilsə hamı təsirlənər
final class CountryFlyweight
{
    public function __construct(
        public readonly string $code,        // 'AZ'
        public readonly string $name,        // 'Azerbaijan'
        public readonly string $phonePrefix, // '+994'
        public readonly string $currency,    // 'AZN'
        public readonly string $flagEmoji,   // '🇦🇿'
        public readonly array  $regions,     // ['Baku', 'Ganja', ...]
    ) {}

    // Extrinsic state ($localNumber) parametr kimi gəlir
    public function formatPhone(string $localNumber): string
    {
        return $this->phonePrefix . ' ' . $localNumber;
    }
}

class CountryFlyweightFactory
{
    /** @var array<string, CountryFlyweight> */
    private static array $countries = []; // static: request boyunca paylaşılır

    public static function get(string $countryCode): CountryFlyweight
    {
        $code = strtoupper($countryCode);

        if (!isset(self::$countries[$code])) {
            // Lazy load — yalnız ilk tələbdə DB-dən yüklənir
            // 250 ölkə var — hamısını eager yükləmək israfedicidir
            $data = \DB::table('countries')->where('code', $code)->first();

            self::$countries[$code] = new CountryFlyweight(
                code:        $data->code,
                name:        $data->name,
                phonePrefix: $data->phone_prefix,
                currency:    $data->currency,
                flagEmoji:   $data->flag_emoji,
                regions:     json_decode($data->regions, true),
            );
        }

        return self::$countries[$code]; // eyni instance qaytarılır
    }

    public static function clearCache(): void
    {
        self::$countries = []; // test-lərdə lazımdır
    }
}

// UserProfile — extrinsic state-i özündə saxlayır
class UserProfile
{
    public function __construct(
        public readonly int    $userId,
        public readonly string $name,
        public readonly string $countryCode,  // flyweight-in key-i — tam metadata deyil
        public readonly string $localPhone,   // user-specific, extrinsic
    ) {}

    public function getCountry(): CountryFlyweight
    {
        // Flyweight-i factory-dən al — yeni object yaranmır
        return CountryFlyweightFactory::get($this->countryCode);
    }

    public function getFormattedPhone(): string
    {
        // Extrinsic ($localPhone) flyweight-ə ötürülür
        return $this->getCountry()->formatPhone($this->localPhone);
    }

    public function getCountryName(): string
    {
        return $this->getCountry()->name;
    }
}

// 10,000 user üçün yalnız unikal ölkə sayı qədər CountryFlyweight yaranır
// Azərbaycanlı 5,000 user → 1 AZ flyweight; digər ölkələr üçün ayrıca
```

**PHP Static Property — sadə Flyweight cache:**

```php
<?php

class IconRenderer
{
    // SVG content: intrinsic — bütün render call-ları paylaşır
    private static array $svgCache = [];

    public static function render(string $iconName, int $size, string $color): string
    {
        // Intrinsic state: SVG content — bir dəfə disk-dən oxunur
        if (!isset(self::$svgCache[$iconName])) {
            self::$svgCache[$iconName] = file_get_contents(
                resource_path("icons/{$iconName}.svg")
            );
        }

        $svg = self::$svgCache[$iconName];

        // Extrinsic state: size, color — hər render üçün fərqli, saxlanmır
        return str_replace(
            ['width="24"', 'height="24"', 'fill="currentColor"'],
            ["width=\"{$size}\"", "height=\"{$size}\"", "fill=\"{$color}\""],
            $svg
        );
    }
}

// 1000 icon render olsa da, hər icon SVG-si yalnız bir dəfə oxunur
// 1000 file_get_contents → 1 file_get_contents
```

**Memory benchmark — Flyweight-in faydasını ölçmək:**

```php
<?php

// Flyweight olmadan
$beforeMemory = memory_get_usage();

$profiles = [];
for ($i = 0; $i < 10000; $i++) {
    // Hər user üçün ayrı country array saxlanır
    $profiles[] = [
        'user_id' => $i,
        'country' => [
            'code' => 'AZ',
            'name' => 'Azerbaijan',
            'phone_prefix' => '+994',
            'currency' => 'AZN',
            'regions' => ['Baku', 'Ganja', 'Sumgayit', /* ... 70 region ... */],
        ],
    ];
}

$withoutFlyweight = memory_get_usage() - $beforeMemory;

// Flyweight ilə
$beforeMemory = memory_get_usage();

$profilesFW = [];
CountryFlyweightFactory::clearCache();
for ($i = 0; $i < 10000; $i++) {
    // Yalnız countryCode saxlanır — tam metadata flyweight-dədir
    $profilesFW[] = new UserProfile($i, "User {$i}", 'AZ', "50{$i}00{$i}0");
}

$withFlyweight = memory_get_usage() - $beforeMemory;

echo "Without Flyweight: " . round($withoutFlyweight / 1024 / 1024, 2) . " MB\n";
echo "With Flyweight:    " . round($withFlyweight / 1024 / 1024, 2) . " MB\n";
echo "Saved:             " . round(($withoutFlyweight - $withFlyweight) / 1024 / 1024, 2) . " MB\n";
```

## Praktik Tapşırıqlar
1. `\DB::table('currencies')` cədvəlindən `CurrencyFlyweight` yaradın: code, symbol, decimal_places, name. 10,000 transaction üçün flyweight istifadə edin; `memory_get_peak_usage()` ilə Flyweight olmayan versiya ilə müqayisə edin — fərqi log edin.
2. `PermissionFlyweight` qurun: `$permission = PermissionFactory::get('post.edit')` — hər permission string yalnız bir dəfə obyektə çevrilir; 1000 UserPermission object onları paylaşır. Factory-nin `getCount()` metodu unikal permission sayını göstərsin.
3. Laravel Blade view-larda icon Flyweight yazın: `@icon('check', 20, 'green')` — SVG file bir dəfə oxunur; məzmun static cache-lənir; size/color extrinsic state kimi render-ə ötürülür.
4. Benchmark yazın: 10,000 `new ProductCategory($id, $name, $metadata)` vs Flyweight; `memory_get_usage()` hər iki halda ölçün; fərqi log edin; həmin benchmark-ı PHPUnit-ə əlavə edin.

## Əlaqəli Mövzular
- [../creational/01-singleton.md](../creational/01-singleton.md) — Singleton tək instance; Flyweight factory başına bir instance; oxşar cache mexanizmi, fərqli məqsəd
- [../creational/02-factory-method.md](../creational/02-factory-method.md) — FlyweightFactory öz daxilində Factory Method istifadə edir
- [../creational/03-abstract-factory.md](../creational/03-abstract-factory.md) — FlyweightFactory yaratmaq üçün Factory pattern istifadə olunur
- [../creational/06-object-pool.md](../creational/06-object-pool.md) — Object Pool da paylaşımlı instance-lar yaradır; fərq: Pool geri qaytarılır, Flyweight shared-dir
- [05-composite.md](05-composite.md) — Flyweight tez-tez composite structure-larda (ağac, sənəd) istifadə olunur
- [04-proxy.md](04-proxy.md) — hər ikisi object access-i idarə edir; Flyweight sharing üçün, Proxy delegation üçün
- [03-decorator.md](03-decorator.md) — Decorator ilə Flyweight birgə istifadə: shared flyweight üzərinə decorator qatı
- [../behavioral/09-visitor.md](../behavioral/09-visitor.md) — Visitor Flyweight node-larına davranış əlavə etmək üçün istifadə olunur
- [../architecture/02-solid-principles.md](../architecture/02-solid-principles.md) — Single Responsibility: Flyweight yalnız intrinsic state-i saxlayır, extrinsic state client-dədir
