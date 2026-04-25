# Composite (Senior ⭐⭐⭐)

## İcmal
Composite pattern tree-like hierarchical struktur qurmağa imkan verir: leaf (sadə) object-lər və composite (qrup) object-lər eyni interface-i implement edir. Client leaf-mi, composite-mi olduğunu bilmədən eyni kod ilə işləyə bilir. "Bir şeyi" idarə etmək ilə "qrup şeyi" idarə etmək arasındakı fərqi aradan qaldırır.

## Niyə Vacibdir
Permission sistemi, menu tree, product category hierarchy, file system — hamısı Composite pattern-ə nümunədir. Eloquent-in nested sets (category tree), Laravel Gates-in permission qruplaşdırması bu pattern olmadan mürəkkəbləşir. Recursive traversal logic-i client-dən gizlətmək real layihələrdə kritikdir.

## Əsas Anlayışlar
- **Component interface**: həm Leaf, həm Composite-in implement etdiyi ortaq contract
- **Leaf**: heç bir child-ı olmayan terminal node (DirectPermission, MenuItem, File)
- **Composite**: child-ları olan, uşaqlar üzərindən iterate edən node (PermissionGroup, MenuSection, Directory)
- **Recursive composition**: Composite başqa Composite-ləri də child kimi saxlaya bilər
- **Transparency**: client Leaf ilə Composite-i eyni şəkildə işlədə bilir
- **Safety**: Leaf-ə `addChild()` çağırmaq mənasızdır — interface dizaynında balance lazımdır

## Praktik Baxış
- **Real istifadə**: RBAC permission tree (role → permission group → permission), navigation menu (category → subcategory → menu item), e-commerce category hierarchy, file/folder structure, organizational chart (manager → team members)
- **Trade-off-lar**: Leaf ilə Composite-i type-checking olmadan ayırmaq çətin olur; Component interface çox generic olur — leaf-ə `addChild()` call etmək mənasız amma interface-də görünür; çox dərin tree-lərdə recursion stack overflow riski
- **İstifadə etməmək**: structure həmişə iki level-ə sahib olacaqsa (sadə parent-child kifayətdir); tree-nin depth-i dynamically dəyişmirsə; sadə flat listlər üçün
- **Common mistakes**: Composite-ə leaf-specific behavior qoymaq; tree-ni infinite recursion risk-i ilə qurmaq; Composite-in child-larını cache etməmək (hər `check()` call-ında recursion)

## Nümunələr

### Ümumi Nümunə
Şirkətin iş strukturunu düşünün. CEO → Division Head → Team Lead → Developer. Eyni `calculateSalary()` metodu hər level-dəki şəxs üçün işləyir: Developer sadəcə öz maaşını qaytarır; Team Lead öz + team üzvlərinin cəmini qaytarır; Division Head öz + bütün team-lərin cəmini qaytarır. Client hansı level olduğunu bilmir.

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
class DirectPermission implements Permission
{
    public function __construct(
        private readonly string $name,
        private readonly string $resource, // 'orders', 'users', 'reports'
        private readonly string $action,   // 'create', 'read', 'update', 'delete'
    ) {}

    public function check(User $user): bool
    {
        return $user->permissions()->where('name', $this->name)->exists();
    }

    public function getName(): string { return $this->name; }

    public function getAll(): array { return [$this->name]; }
}

// Composite — permission-ların qrupu
class PermissionGroup implements Permission
{
    private array $children = [];

    public function __construct(private readonly string $name) {}

    public function add(Permission $permission): self
    {
        $this->children[] = $permission;
        return $this;
    }

    public function remove(Permission $permission): void
    {
        $this->children = array_filter(
            $this->children,
            fn($p) => $p !== $permission
        );
    }

    // AND məntiqli: hamısı true olmalıdır
    public function check(User $user): bool
    {
        foreach ($this->children as $child) {
            if (!$child->check($user)) {
                return false;
            }
        }
        return true;
    }

    public function getName(): string { return $this->name; }

    public function getAll(): array
    {
        return array_merge(...array_map(fn($child) => $child->getAll(), $this->children));
    }
}

// OR məntiqli composite: heç olmasa biri true olmalıdır
class AnyPermissionGroup implements Permission
{
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
                return true; // biri yetərlidir
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
// Factory — permission tree yaratmaq
class PermissionTreeBuilder
{
    public static function forEditor(): Permission
    {
        $readContent = new PermissionGroup('read-content');
        $readContent
            ->add(new DirectPermission('posts.read',     'posts',    'read'))
            ->add(new DirectPermission('comments.read',  'comments', 'read'));

        $writeContent = new PermissionGroup('write-content');
        $writeContent
            ->add(new DirectPermission('posts.create',   'posts',    'create'))
            ->add(new DirectPermission('posts.update',   'posts',    'update'))
            ->add(new DirectPermission('comments.delete','comments', 'delete'));

        $editorGroup = new PermissionGroup('editor');
        $editorGroup
            ->add($readContent)   // nested group
            ->add($writeContent); // nested group

        return $editorGroup;
    }

    public static function forAdmin(): Permission
    {
        $editorPermissions = self::forEditor();

        $adminExtra = new PermissionGroup('admin-extra');
        $adminExtra
            ->add(new DirectPermission('users.manage', 'users', 'manage'))
            ->add(new DirectPermission('settings.update', 'settings', 'update'));

        $adminGroup = new PermissionGroup('admin');
        $adminGroup
            ->add($editorPermissions) // admin = editor + extra
            ->add($adminExtra);

        return $adminGroup;
    }
}

// İstifadəsi — client tree depth-ini bilmir
$user = User::find(1);

$editorPerms = PermissionTreeBuilder::forEditor();
if ($editorPerms->check($user)) {
    echo "User is editor";
}

// Bütün permission-ları görmək
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

// Leaf — terminal menu item
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
        if ($this->requiredPermission === null) {
            return true;
        }
        return $user->can($this->requiredPermission);
    }

    public function render(User $user): ?array
    {
        if (!$this->isVisible($user)) {
            return null;
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

// Menu qurmaq
class MenuBuilder
{
    public static function build(): MenuItem
    {
        $content = (new MenuSection('Content', 'folder'))
            ->addItem(new LeafMenuItem('Posts',    'posts.index',    'posts.read'))
            ->addItem(new LeafMenuItem('Comments', 'comments.index', 'comments.read'));

        $admin = (new MenuSection('Administration', 'settings'))
            ->addItem(new LeafMenuItem('Users',    'admin.users',    'users.manage'))
            ->addItem(new LeafMenuItem('Settings', 'admin.settings', 'settings.update'))
            ->addItem(
                (new MenuSection('Reports', 'chart'))
                    ->addItem(new LeafMenuItem('Sales',    'reports.sales',    'reports.view'))
                    ->addItem(new LeafMenuItem('Traffic',  'reports.traffic',  'reports.view'))
            );

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
        return response()->json($menu->render($request->user()));
    }
}
```

**Eloquent ilə nested category tree:**

```php
// Laravel Eloquent + Composite recursive
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

    // Composite metodu: bütün descendants
    public function getAllDescendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids   = array_merge($ids, $child->getAllDescendantIds()); // recursion
        }
        return $ids;
    }

    // Composite metodu: özü + bütün uşaqların product count
    public function getTotalProductCount(): int
    {
        $count = $this->products()->count();
        foreach ($this->children as $child) {
            $count += $child->getTotalProductCount(); // recursion
        }
        return $count;
    }
}

// Controller
$category = Category::with('children.children.children')->find(1);
$allIds   = $category->getAllDescendantIds();
$products = Product::whereIn('category_id', [$category->id, ...$allIds])->get();
```

## Praktik Tapşırıqlar
1. `FileSystem` Composite qurun: `File` (leaf) + `Directory` (composite), `Directory::getTotalSize()` bütün nested faylların ölçüsünü qaytarsın
2. `Permission` tree-si üçün JSON-dan (config fayldan) dinamik tree build edən `PermissionTreeFactory` yazın
3. Mövcud flat category table-ını Composite pattern ilə tree-yə çevirin; `getAllDescendantIds()` metodu ilə recursive product query qurun

## Əlaqəli Mövzular
- [13-iterator.md](13-iterator.md) — Composite tree-nin bütün node-larını iterate etmək
- [06-decorator.md](06-decorator.md) — Decorator da tree-like composition istifadə edir
- [17-proxy.md](17-proxy.md) — Proxy da Component interface-i implement edir
- [03-abstract-factory.md](03-abstract-factory.md) — Composite node-larını factory ilə yaratmaq
