# Authorization — RBAC, ABAC, ReBAC

## Mündəricat
1. [Authentication vs Authorization](#authentication-vs-authorization)
2. [RBAC — Role-Based Access Control](#rbac--role-based-access-control)
3. [ABAC — Attribute-Based Access Control](#abac--attribute-based-access-control)
4. [ReBAC — Relationship-Based (Google Zanzibar)](#rebac--relationship-based-google-zanzibar)
5. [Policy Decision Point (PDP) vs Policy Enforcement Point (PEP)](#pdp-vs-pep)
6. [Laravel Gate/Policy](#laravel-gatepolicy)
7. [Symfony Voter](#symfony-voter)
8. [OPA (Open Policy Agent)](#opa-open-policy-agent)
9. [Casbin — cross-framework](#casbin--cross-framework)
10. [Best practices](#best-practices)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Authentication vs Authorization

```
Authentication (AuthN) — "Sən kimsən?"
  Login, password, token, OAuth, passwordless
  Nəticə: user identity

Authorization (AuthZ) — "Nə etməyə icazən var?"
  User X action Y resource Z üzərində edə bilər?
  Nəticə: allow / deny

Qaydada:
  AuthN → AuthZ → Action

Tipik PHP:
  AuthN: Laravel Breeze/Sanctum, Symfony Security login
  AuthZ: Gate/Policy, Voter, custom middleware
```

---

## RBAC — Role-Based Access Control

```
RBAC — ən məşhur authorization modeli.
User-lərə ROLE, role-lara PERMISSION verilir.

  User ──has── Role ──has── Permission
  
  Ali     → admin        → create-post, delete-post, manage-users
  Leyla   → editor       → create-post, edit-post
  Orxan   → viewer       → view-post

3-səviyyəli NİST RBAC:
  RBAC0 — Flat RBAC (basit)
  RBAC1 — Role hierarchy (admin > editor > viewer)
  RBAC2 — Constraint (separation of duty: "auditor" və "editor" eyni user olmasın)
  RBAC3 — Hierarchy + constraint

Üstünlük:
  ✓ Sadə, asan idarə olunur
  ✓ Audit asan ("kim nə icazəyə malikdir?")
  ✓ UI / database model bilinəndir

Çatışmazlıq:
  ✗ Role explosion (hər xüsusi hal yeni role)
  ✗ Contextual rules (zaman, IP, owner) çətin
  ✗ "Owner-only" modellər — per-resource permission lazım
```

```sql
-- RBAC schema
CREATE TABLE users (id BIGINT PK, email VARCHAR);
CREATE TABLE roles (id BIGINT PK, name VARCHAR);
CREATE TABLE permissions (id BIGINT PK, name VARCHAR);

CREATE TABLE user_roles (user_id, role_id);       -- many-to-many
CREATE TABLE role_permissions (role_id, perm_id); -- many-to-many
```

```php
<?php
// Spatie Laravel Permission — populyar RBAC paketi
use Spatie\Permission\Traits\HasRoles;

class User extends Model
{
    use HasRoles;
}

// Permission + role
Permission::create(['name' => 'edit articles']);
Permission::create(['name' => 'delete articles']);

$editorRole = Role::create(['name' => 'editor']);
$editorRole->givePermissionTo(['edit articles']);

$user->assignRole('editor');

// Check
$user->can('edit articles');          // true
$user->hasRole('editor');             // true

// Middleware
Route::get('/articles/{id}/edit', ...)->middleware('permission:edit articles');
```

---

## ABAC — Attribute-Based Access Control

```
ABAC — qərarlar ATTRIBUTE-lara əsaslanır (user, resource, environment).

Formula:
  allow = policy(subject_attrs, resource_attrs, action, context)

Nümunə policy:
  "User document-i yalnız öz department-indədirsə edit edə bilər
   AND iş saatlarındadır
   AND IP office network-dən gəlir"

Attributes:
  Subject (user):      department, role, clearance_level, age
  Resource (doc):      owner, sensitivity, department, created_at
  Action:              read, write, delete
  Environment:         time, ip, location, mfa_verified

Nümunə qayda (pseudo-code):
  allow edit on Document
  when
    user.department == doc.department
    and user.clearance >= doc.sensitivity
    and now.time in workhours
    and request.ip in office_network

Üstünlük:
  ✓ Çox çevik (complex rules)
  ✓ Scalable (milyonlarla resource)
  ✓ Context-aware

Çatışmazlıq:
  ✗ Mürəkkəb (policy writing əziyyətli)
  ✗ Debug çətin
  ✗ Performance — hər request-də attribute fetch lazım
  ✗ Audit çətin ("X kim edə bilər?" sualına cavab çətin)
```

```php
<?php
// Laravel Gate ABAC nümunəsi
Gate::define('edit-document', function (User $user, Document $doc) {
    return $user->department_id === $doc->department_id
        && $user->clearance_level >= $doc->sensitivity
        && Carbon::now()->isBetween(Carbon::parse('09:00'), Carbon::parse('18:00'))
        && in_array(request()->ip(), config('office.ips'));
});

$user->can('edit-document', $doc);   // boolean
```

---

## ReBAC — Relationship-Based (Google Zanzibar)

```
ReBAC — Relationship-based Access Control.
Google Zanzibar (2019) — Drive, YouTube, Calendar permission sistemi.

İdeya:
  Permission-ları "relationship" kimi modellə.
  
  Tuple: <object>#<relation>@<subject>
  
  Nümunə:
    document:readme#reader@user:alice    → Alice readme-ni oxuya bilər
    document:readme#writer@user:bob      → Bob yaza bilər
    folder:docs#owner@user:alice         → Alice docs folder sahibidir
    document:readme#parent@folder:docs   → readme docs folder-dədir

Inheritance (via relationships):
  "Folder-in owner-i bütün folder-dəki document-ləri oxuya/yaza bilər"
  
  Config:
    document.reader = direct assignment 
                     + folder.owner (parent folder-dan inherit)

Nümunə: GitHub-in ReBAC modeli
  org:acme#owner@user:alice
  org:acme#member@user:bob
  repo:acme/api#parent@org:acme
  repo:acme/api#admin@user:alice     (inherited from org owner)

Üstünlük:
  ✓ Rich permission model (GitHub, Google Drive kimi)
  ✓ Inheritance avtomatik
  ✓ Scalable (Zanzibar — milyardlarla tuple)

Çatışmazlıq:
  ✗ Çox kompleks mental model
  ✗ Custom store / DB dizaynı lazım
  ✗ Yeni konsept — team-ə öyrətmək vaxt alır
```

```
ReBAC tool-ları:
  SpiceDB (authzed)      — Open-source Zanzibar
  OpenFGA (Auth0/Okta)    — Open-source Zanzibar
  Ory Keto                — Zanzibar PoC
  Permit.io               — Managed
```

---

## PDP vs PEP

```
Enterprise authorization architecture:

  PAP (Policy Administration Point)
    Rules-i yazır/idarə edir
    UI, config file, OPA Rego
  
  PDP (Policy Decision Point)
    Rules-i yükləyir, qərar verir
    "Bu user X action edə bilər?" sualına cavab
  
  PEP (Policy Enforcement Point)
    Qərarı məcbur edir (allow/deny)
    App middleware, API gateway, proxy
  
  PIP (Policy Information Point)
    Attribute-ları təmin edir (user, resource)
    User DB, LDAP, metadata service

Flow:
  1. User request → PEP
  2. PEP → PDP: "can?"
  3. PDP → PIP: attribute al
  4. PDP → PEP: allow/deny
  5. PEP → request ya davam, ya rədd

Nümunələr:
  PEP: API gateway (Envoy, Kong), app middleware
  PDP: OPA server, Casbin enforcer
  PIP: user DB, LDAP, JWT claims
```

---

## Laravel Gate/Policy

```php
<?php
// GATE — sadə closure-based authorization
use Illuminate\Support\Facades\Gate;

// AuthServiceProvider
Gate::define('update-post', function (User $user, Post $post) {
    return $user->id === $post->user_id;
});

Gate::define('delete-post', function (User $user, Post $post) {
    return $user->id === $post->user_id || $user->isAdmin();
});

// Controller-də
if (Gate::allows('update-post', $post)) { /* ... */ }
Gate::authorize('update-post', $post);   // auto 403 on fail

// Blade
@can('update-post', $post)
    <a href="/posts/{{ $post->id }}/edit">Edit</a>
@endcan

// POLICY — class-based
php artisan make:policy PostPolicy --model=Post

namespace App\Policies;

class PostPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Post $post): bool { return true; }
    
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }
    
    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }
    
    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->isAdmin();
    }
    
    // Gate::before hook
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) return true;
        return null;   // normal flow davam etsin
    }
}

// Auto-discovery (Laravel 5.8+) — Policy automatically Post model-ə bağlanır

// Controller
class PostController
{
    public function update(Request $req, Post $post): Response
    {
        $this->authorize('update', $post);   // Policy çağrılır
        // ...
    }
}

// Route-level
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('can:update,post');
```

---

## Symfony Voter

```php
<?php
// Symfony — Voter-lar AccessDecisionManager-i dəstəkləyir
namespace App\Security\Voter;

use App\Entity\Post;
use App\Entity\User;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class PostVoter extends Voter
{
    const VIEW   = 'view';
    const EDIT   = 'edit';
    const DELETE = 'delete';
    
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Post;
    }
    
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) return false;
        
        /** @var Post $post */
        $post = $subject;
        
        return match ($attribute) {
            self::VIEW   => true,
            self::EDIT   => $post->getAuthor() === $user,
            self::DELETE => $post->getAuthor() === $user || $this->isAdmin($user),
        };
    }
}

// Controller
class PostController extends AbstractController
{
    #[Route('/posts/{id}/edit')]
    public function edit(Post $post): Response
    {
        $this->denyAccessUnlessGranted('edit', $post);
        // ...
    }
}
```

---

## OPA (Open Policy Agent)

```
OPA — Cloud-native policy engine (CNCF).
Rego language ilə policy yazırsan, OPA server-inə sorğu atırsan.

Fayda:
  ✓ Language-agnostic (PHP, Go, Java, Python hamısı çağıra bilir)
  ✓ Microservice decoupled (PDP ayrıca servis)
  ✓ Policy as Code (git-də version control)
  ✓ Test edilə bilən (policy unit test)
```

```rego
# policy.rego
package app.authz

import future.keywords.if

default allow := false

# Admin hər şeyi edə bilər
allow if {
    input.user.role == "admin"
}

# User öz post-unu edit edə bilər
allow if {
    input.action == "edit"
    input.resource.type == "post"
    input.user.id == input.resource.owner_id
}

# Premium user paid content-i görə bilər
allow if {
    input.action == "view"
    input.resource.type == "content"
    input.resource.paid == true
    input.user.subscription == "premium"
}
```

```php
<?php
// PHP-dən OPA-ya sorğu
$http = new GuzzleHttp\Client(['base_uri' => 'http://opa:8181']);

$response = $http->post('/v1/data/app/authz/allow', [
    'json' => [
        'input' => [
            'user' => [
                'id' => $userId,
                'role' => $role,
                'subscription' => $sub,
            ],
            'action' => 'edit',
            'resource' => [
                'type' => 'post',
                'id' => $postId,
                'owner_id' => $ownerId,
            ],
        ],
    ],
]);

$result = json_decode($response->getBody(), true);
// $result['result'] = true / false
```

---

## Casbin — cross-framework

```bash
composer require casbin/casbin
```

```ini
# RBAC model (model.conf)
[request_definition]
r = sub, obj, act

[policy_definition]
p = sub, obj, act

[role_definition]
g = _, _

[policy_effect]
e = some(where (p.eft == allow))

[matchers]
m = g(r.sub, p.sub) && r.obj == p.obj && r.act == p.act
```

```csv
# Policies (policy.csv)
p, admin, data1, read
p, admin, data1, write
p, editor, data1, read

g, alice, admin
g, bob, editor
```

```php
<?php
use Casbin\Enforcer;

$e = new Enforcer('model.conf', 'policy.csv');

$e->enforce('alice', 'data1', 'read');    // true
$e->enforce('bob', 'data1', 'write');     // false

// Dinamik policy
$e->addPolicy('admin', 'data2', 'write');
$e->addRoleForUser('charlie', 'editor');
$e->savePolicy();
```

---

## Best practices

```
✓ Deny by default — şübhədə rədd et
✓ Policy central — kod hər yerində səpələmə
✓ Policy unit test yaz (Rego / PHPUnit)
✓ Audit log — "kim nə edə bildi" qeydə al
✓ Minimum privilege — user yalnız lazım olan icazəyə malik olsun
✓ Time-based permissions (temporary access)
✓ Resource-level caching (permission cache 5-10s)
✓ ABAC-da attribute invalidation düşün

❌ Hardcoded role check (`if ($user->role === 'admin')`) — pattern dağılır
❌ Permission-ları client-side yoxla (yalnız server-side)
❌ God permission — "super admin can anything" — audit boşluğu
❌ Deep permission nesting — debugging kabus
❌ JWT-də həddindən çox claim (token böyüyür)
```

---

## İntervyu Sualları

- Authentication və Authorization arasındakı fərq nədir?
- RBAC və ABAC arasında nə vaxt hansı seçilməlidir?
- "Role explosion" nədir?
- ReBAC (Google Zanzibar) hansı problemi həll edir?
- PDP, PEP, PIP, PAP nə deməkdir?
- OPA Rego dilinin faydası nədir?
- Laravel Gate və Policy fərqi nədir?
- Symfony Voter necə işləyir?
- "Owner-only" permission RBAC-da necə modelləşdirilir?
- JWT-də role claim-lərinin sürəkliliyi ilə problem nədir?
- ABAC-da attribute-ları nə vaxt cache etmək olar?
- Deny-by-default niyə vacib prinsipdir?
