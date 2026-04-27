# Livewire & Inertia.js (Middle)

## Mündəricat
1. [Frontend yanaşma seçimləri](#frontend-yanaşma-seçimləri)
2. [Livewire — server-driven UI](#livewire--server-driven-ui)
3. [Inertia.js — modern monolith](#inertiajs--modern-monolith)
4. [Müqayisə](#müqayisə)
5. [Real-world performance](#real-world-performance)
6. [Volt (single-file Livewire)](#volt-single-file-livewire)
7. [Alpine.js — client-side glue](#alpinejs--client-side-glue)
8. [SSR (Inertia)](#ssr-inertia)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Frontend yanaşma seçimləri

```
1. CLASSIC SSR (Blade only)
   Server hər request-də full HTML render edir
   Pros: SEO, sadə
   Cons: Hər action page reload, UX zəif

2. SPA (Vue/React standalone)
   Backend pure API
   Pros: Rich UX
   Cons: Auth, routing, state — duplikat (frontend + backend)

3. LIVEWIRE (server-driven SPA)
   Server PHP-də component, AJAX ilə dynamic
   Pros: Pure PHP, sadə model
   Cons: Latency hər click

4. INERTIA (modern monolith)
   Vue/React component, amma backend Laravel route + props
   Pros: SPA UX + monolith simplicity
   Cons: JS bilmə tələb edilir

5. NEXT.JS / NUXT FRONTEND
   Ayrı SSR frontend, Laravel API
   Pros: Ən rich UX, edge SSR
   Cons: 2 layihə, 2 deploy, çox kompleks
```

---

## Livewire — server-driven UI

```bash
composer require livewire/livewire
php artisan livewire:install
```

```php
<?php
// app/Livewire/Counter.php
namespace App\Livewire;

use Livewire\Component;

class Counter extends Component
{
    public int $count = 0;
    
    public function increment(): void
    {
        $this->count++;
    }
    
    public function decrement(): void
    {
        $this->count--;
    }
    
    public function render()
    {
        return view('livewire.counter');
    }
}
```

```blade
{{-- resources/views/livewire/counter.blade.php --}}
<div>
    <button wire:click="decrement">-</button>
    <span>{{ $count }}</span>
    <button wire:click="increment">+</button>
</div>
```

```blade
{{-- Page-də istifadə --}}
<x-app-layout>
    <livewire:counter />
</x-app-layout>
```

```
Livewire workflow (hər click-də):
  1. Browser AJAX → /livewire/update
  2. Snapshot + action göndərir (component state)
  3. Server: Counter::increment() çağırılır, $count++
  4. Server: render() → yeni HTML
  5. Browser: morphdom ilə DOM diff (yalnız dəyişən hissə)
```

```php
<?php
// Form & validation
class CreatePost extends Component
{
    public string $title = '';
    public string $body = '';
    
    protected $rules = [
        'title' => 'required|min:3',
        'body'  => 'required|min:10',
    ];
    
    // Real-time validation
    public function updated($field): void
    {
        $this->validateOnly($field);
    }
    
    public function save(): void
    {
        $this->validate();
        
        Post::create([
            'title' => $this->title,
            'body' => $this->body,
            'user_id' => auth()->id(),
        ]);
        
        session()->flash('message', 'Post created');
        $this->reset();
    }
    
    public function render()
    {
        return view('livewire.create-post');
    }
}

// Lazy loading — heavy components
class ExpensiveTable extends Component
{
    public function placeholder()
    {
        return <<<'HTML'
        <div>Loading...</div>
        HTML;
    }
    
    public function render()
    {
        $data = ExpensiveQuery::run();   // DB heavy
        return view('livewire.table', compact('data'));
    }
}
// <livewire:expensive-table lazy />
```

---

## Inertia.js — modern monolith

```bash
composer require inertiajs/inertia-laravel
npm install @inertiajs/vue3 vue@latest
# ya React: @inertiajs/react react react-dom
```

```php
<?php
// routes/web.php — Inertia controller response
use Inertia\Inertia;

Route::get('/users', function () {
    $users = User::paginate(10);
    return Inertia::render('Users/Index', [
        'users' => $users,
    ]);
});

Route::get('/users/{user}', function (User $user) {
    return Inertia::render('Users/Show', [
        'user' => $user,
        'posts' => $user->posts()->latest()->paginate(5),
    ]);
});

Route::post('/users', function (Request $req) {
    $req->validate([
        'name'  => 'required',
        'email' => 'required|email|unique:users',
    ]);
    
    User::create($req->only('name', 'email'));
    return redirect()->route('users.index')->with('success', 'User created');
});
```

```vue
<!-- resources/js/Pages/Users/Index.vue -->
<script setup>
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
    users: Object,
});

const form = useForm({
    name: '',
    email: '',
});

function submit() {
    form.post('/users', {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <h1>Users</h1>
    
    <form @submit.prevent="submit">
        <input v-model="form.name" placeholder="Name">
        <span v-if="form.errors.name">{{ form.errors.name }}</span>
        
        <input v-model="form.email" placeholder="Email">
        <button :disabled="form.processing">Save</button>
    </form>
    
    <div v-for="user in users.data" :key="user.id">
        <Link :href="`/users/${user.id}`">{{ user.name }}</Link>
    </div>
    
    <!-- Pagination -->
    <Link
        v-for="link in users.links"
        :key="link.label"
        :href="link.url"
        v-html="link.label"
    />
</template>
```

```
Inertia workflow:
  1. İlk request → server full HTML (Vue mount)
  2. Sonrakı navigation → AJAX
  3. Response: JSON (page name + props)
  4. Vue/React component swap, no page reload
  5. URL dəyişir (history API)
  
  Server side: Laravel route + controller (heç dəyişmir!)
  Client side: SPA UX (no flash, fast nav)
  
  "Modern monolith" — backend monolith, frontend modern
```

---

## Müqayisə

| Feature | Livewire | Inertia.js |
|---------|----------|------------|
| Frontend lang | PHP (mostly) | Vue/React (JS) |
| State | Server-side | Client-side |
| Skill barrier | Low (Laravel devs) | Medium (JS dev lazım) |
| Network round-trip per action | Yes (AJAX) | Yes (form submit) |
| Real-time DOM update | Server diff + morphdom | Vue/React reactive |
| SEO | Good (Blade rendered) | Good (SSR ilə əla) |
| Reusable JS components | Limited (Alpine glue) | Full (npm ekosistem) |
| Mobile API reuse | Hard | Same controllers (with `Inertia::render` vs `response()->json()`) |
| Latency sensitive | Hər action server gedir | Yalnız form/data |
| Best for | Forms, admin panel, dashboards | SaaS app, complex UI |

---

## Real-world performance

```
Network impact:

Livewire:
  Hər click → ~5-10 KB AJAX (snapshot + diff)
  100ms latency (server roundtrip)
  P99: 200-400ms (connection slow olarsa)
  WebSocket alternativi yox

Inertia:
  İlk page load: ~200 KB JS bundle
  Sonrakı navigation: ~5-20 KB JSON
  Optimistic UI mümkün (form.processing)
  
Server load:
  Livewire: hər action PHP request
  Inertia: yalnız form submit + page nav PHP request

Verdict:
  Livewire: low-traffic, internal app, dashboard
  Inertia:  high-traffic, public app, mobile API reuse
```

---

## Volt (single-file Livewire)

```bash
composer require livewire/volt
php artisan volt:install
```

```php
<?php
// resources/views/pages/counter.blade.php
// Volt = component logic + template tək faylda

use function Livewire\Volt\{state, computed};

state(['count' => 0]);

$increment = fn () => $this->count++;
$decrement = fn () => $this->count--;

$double = computed(fn () => $this->count * 2);

?>

<div>
    <button wire:click="decrement">-</button>
    <span>{{ $count }} (double: {{ $this->double }})</span>
    <button wire:click="increment">+</button>
</div>
```

```
Volt fayda:
  ✓ "Single file component" — Vue SFC kimi
  ✓ Class boilerplate yox
  ✓ Functional API (modern)
  ✓ Folder = route auto (php artisan volt:install)
  
Volt çatışmaz:
  ✗ Yenidir, ekosistem hələ qurulur
  ✗ Class-based-dan migrate təcrübə tələb edir
```

---

## Alpine.js — client-side glue

```html
<!-- Livewire ilə tez-tez Alpine.js -->
<div x-data="{ open: false }">
    <button @click="open = !open">Toggle</button>
    <div x-show="open" x-transition>
        Content
    </div>
</div>
```

```
Alpine.js — yüngül "Vue-like" framework (~15 KB).
Livewire-in client-side gap-ini doldurmaq üçün:
  - Modal toggle
  - Dropdown
  - Tabs
  - Tooltips
  - Form interactivity (real-time validation)

Wire vs Alpine:
  wire:click → server roundtrip
  @click     → client-side, instant
  
  "Sürətli UI" → Alpine
  "DB-yə təsir edən" → Livewire
```

---

## SSR (Inertia)

```bash
npm install @inertiajs/server
node bootstrap/ssr/ssr.mjs
# ya Octane ilə Inertia SSR
```

```js
// resources/js/ssr.js
import { createInertiaApp } from '@inertiajs/vue3/server';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        resolve: (name) => import(`./Pages/${name}.vue`),
        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) }).use(plugin);
        },
    })
);
```

```
SSR niyə?
  ✓ SEO (full HTML response)
  ✓ Faster First Contentful Paint
  ✓ Social media preview (OG tags)
  
Tradeoff:
  ✗ Node.js process lazım
  ✗ Hydration mismatch riski
  ✗ Deploy mürəkkəb (Laravel + Node)
```

---

## İntervyu Sualları

- Livewire ilə Inertia arasındakı əsas fərq nədir?
- Livewire-də hər click niyə server-ə gedir?
- Inertia "modern monolith" niyə adlanır?
- N+1 problem Livewire-də necə baş verə bilər?
- Alpine.js Livewire ilə birgə niyə tövsiyə olunur?
- Mobile app + web SPA üçün hansı yanaşma?
- Inertia SSR nə vaxt lazımdır?
- Livewire morphdom necə işləyir? Performance impact?
- Volt single-file syntax nə üçündür?
- Lazy loading Livewire-də hansı problemi həll edir?
- Inertia form submission `useForm` niyə təklif olunur?
- Livewire vs SPA — hansı UX baxımdan üstündür?
