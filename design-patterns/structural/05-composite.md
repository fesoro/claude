# Composite (Senior ⭐⭐⭐)

## İcmal
Composite pattern tree-like hierarchical struktur qurmağa imkan verir: leaf (sadə) object-lər və composite (qrup) object-lər eyni interface-i implement edir. Client leaf-mi, composite-mi olduğunu bilmədən eyni kod ilə işləyə bilir. "Bir şeyi" idarə etmək ilə "qrup şeyi" idarə etmək arasındakı fərqi aradan qaldırır.

## Niyə Vacibdir
Permission sistemi, menu tree, product category hierarchy, file system — hamısı Composite pattern-ə nümunədir. Eloquent-in nested sets (category tree), Laravel Gates-in permission qruplaşdırması bu pattern olmadan mürəkkəbləşir. Recursive traversal logic-i client-dən gizlətmək real layihələrdə kritikdir — `if (is_array($item))` tipli yoxlamalar kodu çirkinləşdirir.

## Əsas Anlayışlar
- **Component interface**: həm Leaf, həm Composite-in implement etdiyi ortaq contract
- **Leaf**: heç bir child-ı olmayan terminal node (DirectPermission, MenuItem, File)
- **Composite**: child-ları olan, uşaqlar üzərindən iterate edən node (PermissionGroup, MenuSection, Directory)
- **Recursive composition**: Composite başqa Composite-ləri də child kimi saxlaya bilər — tree depth-i limitsizdir
- **Transparency**: client Leaf ilə Composite-i eyni şəkildə işlədə bilir — hansı olduğunu bilmir
- **Safety vs Transparency**: Leaf-ə `addChild()` çağırmaq mənasızdır — interface dizaynında balance lazımdır

## Praktik Baxış
- **Real istifadə**: RBAC permission tree (role → permission group → permission), navigation menu (category → subcategory → menu item), e-commerce category hierarchy, file/folder structure, organizational chart (manager → team members)
- **Trade-off-lar**: Leaf ilə Composite-i type-checking olmadan ayırmaq çətin olur; Component interface çox generic olur — leaf-ə `addChild()` call etmək mənasız amma interface-də görünür; çox dərin tree-lərdə recursion stack overflow riski var
- **İstifadə etməmək**: structure həmişə iki level-ə sahib olacaqsa (sadə parent-child kifayətdir); tree-nin depth-i dynamically dəyişmirsə; sadə flat listlər üçün — Composite over-engineering olur
- **Common mistakes**: Composite-ə leaf-specific behavior qoymaq; tree-ni infinite recursion riski ilə qurmaq (circular reference); Composite-in child-larını cache etməmək (hər `check()` call-ında bütün tree traversal)

### Anti-Pattern Nə Zaman Olur?
Composite **süni uniformluq** tətbiq etdikdə — aslında uniform olmayan şeyləri eyni interface-ə sıxışdırdıqda — anti-pattern olur:

- **Mənasız interface genişliyi**: `Component` interface-inə `addChild()`, `removeChild()`, `getChildren()` əlavə etmək — Leaf bu metodları "throw new UnsupportedOperationException" kimi implement edir. Bu, interface pollution-dur. Leaf-ə bu metodlar aidiyyətsizdir.
- **Yanlış uniformluq**: Məhsul (`Product`) ilə kateqoriya (`Category`) eyni interface-ə sıxışdırılır: `calculate()`. Amma məhsulun hesablaması qiymətdir, kateqoriyanın hesablaması sub-kateqoriyaların toplam məhsul sayıdır — bunlar həqiqətən eyni operation-dırmı? Bəzən fərqli interface-lər daha düzgündür.
- **Çox dərin recursion**: 10+ level dərin tree-də `check()` çağırışı stəki tükəndirir. Çözüm: iterative DFS ya da tree-ni flat array-ə əvvəlcədən hazırlamaq.
- **Mutable shared nodes**: Bir `PermissionGroup` fərqli ağaclarda paylaşılırsa, birinin dəyişikliyi digərini təsir edir. Composite node-ları immutable etmək ya da deep clone istifadə etmək lazımdır.

## Nümunələr

### Ümumi Nümunə
Şirkətin iş strukturunu düşünün. CEO → Division Head → Team Lead → Developer. Eyni `calculateSalary()` metodu hər level-dəki şəxs üçün işləyir: Developer sadəcə öz maaşını qaytarır; Team Lead öz + team üzvlərinin cəmini qaytarır; Division Head öz + bütün team-lərin cəmini qaytarır. Client hansı level olduğunu bilmir — recursion gizlidir.

### PHP/Laravel Nümunəsi

**Permission system — Composite pattern real use case:**

```php
<?php

// Component interface — həm Leaf, həm Composite implement edir
interface Permission
{
    public function check(User $user): bool;
    public function getName(): string;
    public function getAll(): array; // flattened permission list
}

// Leaf — sadə, bölünməz permission
// addChild yoxdur — leaf child saxlaya bilməz
class DirectPermission implements Permission
{
    public function __construct(
        private readonly string $name,
        private readonly string $resource, // 'orders', 'users', 'reports'
        private readonly string $action,   // 'create', 'read', 'update', 'delete'
    ) {}

    public function check(User $user): bool
    {
        // DB-yə bir sorğu — sadə yoxlama
        return $user->permissions()->where('name', $this->name)->exists();
    }

    public function getName(): string { return $this->name; }
    public function getAll(): array   { return [$this->name]; }
}

// Composite — AND məntiqli: bütün child-lar true olmalıdır
class PermissionGroup implements Permission
{
    /** @var Permission[] */
    private array $children = [];

    public function __construct(private readonly string $name) {}

    public function add(Permission $permission): self
    {
        $this->children[] = $permission;
        return $this; // fluent interface
    }

    public function remove(Permission $permission): void
    {
        $this->children = array_filter(
            $this->children,
            fn($p) => $p !== $permission
        );
    }

    // AND: hamısı true olmalıdır — hər child öz check() metodunu bilir
    public function check(User $user): bool
    {
        foreach ($this->children as $child) {
            if (!$child->check($user)) {
                return false; // biri yetərsizdir — saxla, hamısını yoxlama
            }
        }
        return true;
    }

    public function getName(): string { return $this->name; }

    // Recursive flattening — bütün leaf node-ları topla
    public function getAll(): array
    {
        return array_merge(...array_map(fn($child) => $child->getAll(), $this->children));
    }
}

// OR məntiqli composite: heç olmasa biri true olmalıdır
// "Bunlardan birini edə bilər" — editor OR admin
class AnyPermissionGroup implements Permission
{
    /** @var Permission[] */
    private array $children = [];

    public function __construct(private readonly string $name) {}

    public function add(Permission $permission): self
    {
        $this->children[] = $permission;
        return $this;
    }

    public function check(User $user): bool
    {
        foreach ($this->children as $child) {
            if ($child->check($user)) {
                return true; // biri yetərlidir — qalan yoxlanmır
            }
        }
        return false;
    }

    public function getName(): string { return $this->name; }
    public function getAll(): array
    {
        return array_merge(...array_map(fn($child) => $child->getAll(), $this->children));
    }
}
```

**Permission tree qurmaq:**

```php
// Factory — permission tree-sini kompozisiya ilə qurur
class PermissionTreeBuilder
{
    public static function forEditor(): Permission
    {
        // Leaf node-lar — ən alt səviyyə
        $readContent = new PermissionGroup('read-content');
        $readContent
            ->add(new DirectPermission('posts.read',    'posts',    'read'))
            ->add(new DirectPermission('comments.read', 'comments', 'read'));

        $writeContent = new PermissionGroup('write-content');
        $writeContent
            ->add(new DirectPermission('posts.create',    'posts',    'create'))
            ->add(new DirectPermission('posts.update',    'posts',    'update'))
            ->add(new DirectPermission('comments.delete', 'comments', 'delete'));

        // Composite içinə composite qoymaq — nested tree
        $editorGroup = new PermissionGroup('editor');
        $editorGroup
            ->add($readContent)   // group içinə group
            ->add($writeContent);

        return $editorGroup;
    }

    public static function forAdmin(): Permission
    {
        $editorPermissions = self::forEditor(); // editor-un hamısını al

        $adminExtra = new PermissionGroup('admin-extra');
        $adminExtra
            ->add(new DirectPermission('users.manage',    'users',    'manage'))
            ->add(new DirectPermission('settings.update', 'settings', 'update'));

        // Admin = editor + admin-extra
        $adminGroup = new PermissionGroup('admin');
        $adminGroup
            ->add($editorPermissions) // tam editor tree-sini daxil et
            ->add($adminExtra);

        return $adminGroup;
    }
}

// İstifadəsi — client tree depth-ini bilmir, sadəcə check() çağırır
$user = User::find(1);

$editorPerms = PermissionTreeBuilder::forEditor();
if ($editorPerms->check($user)) {
    echo "User editor işlərini görə bilər";
}

// Bütün permission adlarını əldə etmək — flat list
$allPerms = $editorPerms->getAll();
// ['posts.read', 'comments.read', 'posts.create', 'posts.update', 'comments.delete']
```

**Navigation menu — Composite pattern:**

```php
interface MenuItem
{
    public function render(User $user): ?array;
    public function isVisible(User $user): bool;
}

// Leaf — terminal menu item, child yoxdur
class LeafMenuItem implements MenuItem
{
    public function __construct(
        private readonly string  $label,
        private readonly string  $route,
        private readonly ?string $requiredPermission = null,
        private readonly ?string $icon = null,
    ) {}

    public function isVisible(User $user): bool
    {
        // permission yoxdursa həmişə görünür; varsa user-in icazəsini yoxla
        if ($this->requiredPermission === null) {
            return true;
        }
        return $user->can($this->requiredPermission);
    }

    public function render(User $user): ?array
    {
        if (!$this->isVisible($user)) {
            return null; // null = bu item göstərilmir
        }

        return [
            'label' => $this->label,
            'route' => $this->route,
            'icon'  => $this->icon,
            'type'  => 'item',
        ];
    }
}

// Composite — child menu item-ləri olan section
class MenuSection implements MenuItem
{
    /** @var MenuItem[] */
    private array $children = [];

    public function __construct(
        private readonly string $label,
        private readonly ?string $icon = null,
    ) {}

    public function addItem(MenuItem $item): self
    {
        $this->children[] = $item;
        return $this;
    }

    public function isVisible(User $user): bool
    {
        // Heç olmasa bir child görünürsə section görünür
        // Boş section göstərməmək üçün
        foreach ($this->children as $child) {
            if ($child->isVisible($user)) {
                return true;
            }
        }
        return false;
    }

    public function render(User $user): ?array
    {
        if (!$this->isVisible($user)) {
            return null;
        }

        // null qaytaran child-ları filtrələ (icazəsiz item-lər)
        $renderedChildren = array_filter(
            array_map(fn($child) => $child->render($user), $this->children)
        );

        return [
            'label'    => $this->label,
            'icon'     => $this->icon,
            'type'     => 'section',
            'children' => array_values($renderedChildren),
        ];
    }
}

// Menu qurmaq — tree hierarchisi
class MenuBuilder
{
    public static function build(): MenuItem
    {
        $content = (new MenuSection('Content', 'folder'))
            ->addItem(new LeafMenuItem('Posts',    'posts.index',    'posts.read'))
            ->addItem(new LeafMenuItem('Comments', 'comments.index', 'comments.read'));

        $reports = (new MenuSection('Reports', 'chart'))
            ->addItem(new LeafMenuItem('Sales',   'reports.sales',   'reports.view'))
            ->addItem(new LeafMenuItem('Traffic', 'reports.traffic', 'reports.view'));

        $admin = (new MenuSection('Administration', 'settings'))
            ->addItem(new LeafMenuItem('Users',    'admin.users',    'users.manage'))
            ->addItem(new LeafMenuItem('Settings', 'admin.settings', 'settings.update'))
            ->addItem($reports); // nested section — section içinə section

        $root = new MenuSection('Root');
        $root->addItem(new LeafMenuItem('Dashboard', 'dashboard'));
        $root->addItem($content);
        $root->addItem($admin);

        return $root;
    }
}

// Controller-da istifadə — user-ə görə filtered menu
class LayoutController
{
    public function navigation(Request $request): JsonResponse
    {
        $menu = MenuBuilder::build();
        // render() recursive olaraq hər child-ı yoxlayır
        // icazəsiz item-lər null qaytarır, filter edir
        return response()->json($menu->render($request->user()));
    }
}
```

**Eloquent ilə nested category tree:**

```php
class Category extends Model
{
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Composite metodu: bütün descendants-ı topla — recursive
    public function getAllDescendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            // child özü də Composite ola bilər — recursive çağırış
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }
        return $ids;
    }

    // Composite metodu: özü + bütün uşaqların product sayı
    public function getTotalProductCount(): int
    {
        $count = $this->products()->count(); // bu kateqoriyanın məhsulları
        foreach ($this->children as $child) {
            $count += $child->getTotalProductCount(); // recursion
        }
        return $count;
    }
}

// Qeyd: eager loading — N+1 problemi önlənir
$category = Category::with('children.children.children')->find(1);
$allIds   = $category->getAllDescendantIds();
// Kateqoriya + bütün sub-kateqoriyaların məhsulları bir query ilə
$products = Product::whereIn('category_id', [$category->id, ...$allIds])->get();
```

## Praktik Tapşırıqlar
1. `FileSystem` Composite qurun: `File` (leaf — `getSize()`) + `Directory` (composite — `getSize()` bütün nested faylların ölçüsünü qaytarsın). `Directory::countFiles()` metodunu da əlavə edin.
2. `Permission` tree-si üçün JSON config-dən dinamik tree build edən `PermissionTreeFactory` yazın: `['type' => 'group', 'name' => 'editor', 'children' => [...]]` strukturunu oxuyub tree qursun.
3. Mövcud flat category table-ını Composite pattern ilə tree-yə çevirin; `getAllDescendantIds()` metodu ilə recursive product query qurun; N+1 problemi olmadan eager loading strategiyası seçin.

## Əlaqəli Mövzular
- [03-decorator.md](03-decorator.md) — Decorator da composition istifadə edir; amma tree deyil, chain-dir
- [04-proxy.md](04-proxy.md) — Proxy da Component interface-i implement edir; Composite-dən fərqli məqsəd
- [06-flyweight.md](06-flyweight.md) — Flyweight Composite tree node-larını paylaşımlı etmək üçün tez-tez birgə istifadə olunur
- [../creational/03-abstract-factory.md](../creational/03-abstract-factory.md) — Composite node-larını factory ilə yaratmaq
- [../creational/04-builder.md](../creational/04-builder.md) — Builder mürəkkəb Composite tree-lərini addım-addım qurmaq üçün istifadə olunur
- [../behavioral/09-visitor.md](../behavioral/09-visitor.md) — Visitor pattern Composite tree-nin bütün node-larını ziyarət edir
- [../behavioral/05-iterator.md](../behavioral/05-iterator.md) — Iterator Composite tree-ni gəzmək üçün istifadə olunur
- [../behavioral/02-strategy.md](../behavioral/02-strategy.md) — Composite node-larının check/calculate davranışı Strategy ilə dəyişdirilə bilər
- [../laravel/04-specification.md](../laravel/04-specification.md) — Specification pattern Composite kimi AND/OR/NOT qatlanması istifadə edir
- [../architecture/02-solid-principles.md](../architecture/02-solid-principles.md) — Open/Closed: yeni node tipi əlavə etmək interface-i dəyişmir
- [../architecture/05-hexagonal-architecture.md](../architecture/05-hexagonal-architecture.md) — Domain modellərdə Composite tree-lər Hexagonal port-larını keçmək üçün istifadə olunur
