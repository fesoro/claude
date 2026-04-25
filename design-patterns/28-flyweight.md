# Flyweight (Lead ⭐⭐⭐⭐)

## İcmal
Flyweight pattern, çoxlu oxşar object-lərin paylaşılan state-ini bir yerdə saxlayaraq yaddaş istehlakını azaldır. Hər object-in özündə saxlayacağı dəyərlər iki hissəyə bölünür: hamı ilə paylaşılan immutable intrinsic state (flyweight-də), hər instance üçün fərqli extrinsic state (çöldən ötürülür).

## Niyə Vacibdir
PHP tətbiqlərinin yaddaş limiti (128MB–256MB) var. 10,000 oxşar object yaratdıqda hər biri duplicate metadata saxlayırsa, yaddaş sürətlə tükənir. Flyweight ilə 10,000 position reference + 1 shared flyweight istifadə olunur. Laravel-in özündə bu ideya müxtəlif yerlərdə tətbiq edilir: compiled view caching, connection pooling, config singleton-lar.

## Əsas Anlayışlar
- **Intrinsic state**: flyweight object-ində saxlanılan, bütün kontekstlər arasında paylaşılan, immutable data (character font/size, country metadata, icon data)
- **Extrinsic state**: kontekstə xas data; flyweight-ə method çağırışı zamanı parametr kimi ötürülür (x/y position, user-specific data)
- **FlyweightFactory**: mövcud flyweight-lərə cache; eyni key ilə yeni yaratmaq əvəzinə mövcudu qaytarır
- **Client**: extrinsic state-i özündə saxlayır, intrinsic state-ə flyweight vasitəsilə çatır
- **Object identity illusion**: client hər flyweight-i ayrı object kimi görsə də, faktiki olaraq shared reference istifadə edir

## Praktik Baxış
- **Real istifadə**: text editor (hər simvol üçün ayrı font object-i yaratmamaq), game engine (eyni tree/grass sprite minlərlə yerə render), geographic data (ölkə metadata), icon library, database connection pool, compiled template cache
- **Trade-off-lar**: significant memory reduction (10,000 → 1 object); lakin code mürəkkəbliyi artır; extrinsic/intrinsic ayrımını izləmək çətin olur; extrinsic state-i çöldən ötürmək convenience-ı azaldır
- **İstifadə etməmək**: object sayı azdırsa (premature optimization); extrinsic state intrinsic state-dən böyükdürsə (gain yoxdur); object-lər unikal dataya malikdirsə (paylaşmaq mümkün deyil)
- **Common mistakes**: intrinsic state-i mutable etmək (paylaşılan state dəyişsə hamı təsirlənər); flyweight-i singleton ilə qarışdırmaq (singleton tək instance, flyweight kategori başına bir instance); factory olmadan istifadə etmək (sharing işləmir)

## Nümunələr

### Ümumi Nümunə
Meşə simulyasiyasını düşünün: 10,000 ağac var. Hər ağacın `TreeType` (ad, rəng, texture — 2MB data) var, lakin 3 növ ağac var. 10,000 ağac üçün 10,000 TreeType = 20GB; Flyweight ilə 3 TreeType + 10,000 position = ~6MB. Position (x, y) extrinsic state-dir — hər ağaca xasdır. TreeType intrinsic — hamı paylaşır.

### PHP/Laravel Nümunəsi

```php
<?php

namespace App\Flyweight;

// ─────────────────────────────────────────────
// FLYWEIGHT — intrinsic state
// ─────────────────────────────────────────────

final class CharacterStyle
{
    // Bu data bütün eyni-stilli simvollar arasında paylaşılır
    public function __construct(
        public readonly string $fontFamily,  // 'Arial', 'Times New Roman'
        public readonly int    $fontSize,    // 12, 14, 16
        public readonly bool   $isBold,
        public readonly bool   $isItalic,
        public readonly string $color,       // '#000000'
    ) {}

    // Extrinsic state (char, x, y) parametr kimi gəlir
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
        $key = "{$fontFamily}_{$fontSize}_{$isBold}_{$isItalic}_{$color}";

        if (!isset($this->styles[$key])) {
            $this->styles[$key] = new CharacterStyle($fontFamily, $fontSize, $isBold, $isItalic, $color);
        }

        return $this->styles[$key];  // mövcud flyweight qaytarılır
    }

    public function getStyleCount(): int
    {
        return count($this->styles);  // nə qədər unikal style var
    }
}

// ─────────────────────────────────────────────
// CLIENT — extrinsic state-i özündə saxlayır
// ─────────────────────────────────────────────

class TextCharacter
{
    // Extrinsic state — bu instance-a xas
    public function __construct(
        private readonly string         $char,
        private int                     $x,
        private int                     $y,
        private readonly CharacterStyle $style,  // shared flyweight
    ) {}

    public function render(): string
    {
        return $this->style->render($this->char, $this->x, $this->y);
    }

    public function moveTo(int $x, int $y): void
    {
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
        // Bir dəfə style al — eyni style 1000 simvol üçün paylaşılır
        $style = $this->styleFactory->getStyle($fontFamily, $fontSize, $bold);

        $x = count($this->characters) * 8;
        foreach (str_split($text) as $i => $char) {
            $this->characters[] = new TextCharacter($char, $x + ($i * 8), 0, $style);
        }
    }

    public function getStats(): array
    {
        return [
            'total_characters'  => count($this->characters),
            'unique_styles'     => $this->styleFactory->getStyleCount(),
            'memory_saved'      => sprintf(
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
// unique_styles: 2 (bold + normal) — 90 simvol, 2 style object
```

**Geographic Data Flyweight — real Laravel nümunəsi:**

```php
<?php

// Country metadata — minlərlə user profili üçün paylaşılan data
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

    public function formatPhone(string $localNumber): string
    {
        return $this->phonePrefix . ' ' . $localNumber;
    }
}

class CountryFlyweightFactory
{
    /** @var array<string, CountryFlyweight> */
    private static array $countries = [];

    public static function get(string $countryCode): CountryFlyweight
    {
        $code = strtoupper($countryCode);

        if (!isset(self::$countries[$code])) {
            // Lazy load — lazım olduqda DB-dən və ya config-dən yüklə
            $data = \DB::table('countries')->where('code', $code)->first();

            self::$countries[$code] = new CountryFlyweight(
                code: $data->code,
                name: $data->name,
                phonePrefix: $data->phone_prefix,
                currency: $data->currency,
                flagEmoji: $data->flag_emoji,
                regions: json_decode($data->regions, true),
            );
        }

        return self::$countries[$code];
    }

    public static function clearCache(): void
    {
        self::$countries = [];
    }
}

// UserProfile — extrinsic state-i özündə saxlayır
class UserProfile
{
    public function __construct(
        public readonly int    $userId,
        public readonly string $name,
        public readonly string $countryCode,  // shared flyweight-in key-i
        public readonly string $localPhone,   // user-specific, extrinsic
    ) {}

    public function getCountry(): CountryFlyweight
    {
        return CountryFlyweightFactory::get($this->countryCode);
    }

    public function getFormattedPhone(): string
    {
        return $this->getCountry()->formatPhone($this->localPhone);
    }

    public function getCountryName(): string
    {
        return $this->getCountry()->name;
    }
}

// 10,000 user üçün yalnız ~250 country flyweight yaranır
// Country metadata hər user-də deyil, country başına bir dəfə saxlanır
```

**PHP Static Property — sadə Flyweight cache:**

```php
<?php

// Framework olmadan sadə flyweight
class IconRenderer
{
    // Intrinsic: SVG data bir dəfə yüklənir
    private static array $svgCache = [];

    public static function render(string $iconName, int $size, string $color): string
    {
        // Intrinsic state: SVG content (ağır, paylaşılır)
        if (!isset(self::$svgCache[$iconName])) {
            self::$svgCache[$iconName] = file_get_contents(
                resource_path("icons/{$iconName}.svg")
            );
        }

        $svg = self::$svgCache[$iconName];

        // Extrinsic state: size, color — hər render üçün fərqli
        return str_replace(
            ['width="24"', 'height="24"', 'fill="currentColor"'],
            ["width=\"{$size}\"", "height=\"{$size}\"", "fill=\"{$color}\""],
            $svg
        );
    }
}

// 1000 icon render olsa da, hər icon SVG-si bir dəfə oxunur
```

## Praktik Tapşırıqlar
1. `\DB::table('currencies')` cədvəlindən `CurrencyFlyweight` yaradın: code, symbol, decimal_places, name. 10,000 transaction üçün flyweight istifadə edin; memory_get_peak_usage() ilə Flyweight olmayan versiya ilə müqayisə edin
2. `PermissionFlyweight` qurun: `$permission = PermissionFactory::get('post.edit')` — hər permission string yalnız bir dəfə obyektə çevrilir; 1000 UserPermission object onları paylaşır
3. Laravel Blade view-larda icon Flyweight yazın: `@icon('check', 20, 'green')` — SVG file bir dəfə oxunur; məzmun cache-lənir; size/color extrinsic state
4. Benchmark yazın: 10,000 `new ProductCategory($id, $name, $metadata)` vs Flyweight; memory_get_usage() hər iki halda ölçün; fərqi log edin

## Əlaqəli Mövzular
- [Singleton](01-singleton.md) — Singleton tək instance; Flyweight factory başına bir instance; oxşar cache mexanizmi
- [Factory](03-abstract-factory.md) — FlyweightFactory yaratmaq üçün Factory pattern istifadə olunur
- [Composite](09-composite.md) — Flyweight tez-tez composite structure-larda (ağac, sənəd) istifadə olunur
- [Proxy](../design-patterns/) — hər ikisi object access-i idarə edir; Flyweight sharing üçün, Proxy delegation üçün
