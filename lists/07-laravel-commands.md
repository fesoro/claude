## Artisan basics

php artisan list — bütün komandalar
php artisan help <command> — komanda help
php artisan --version
php artisan tinker — interactive REPL (PsySH)
php artisan about — app + env + versions overview

## Code generation (make:*)

php artisan make:model Post
php artisan make:model Post -mfsc — model + migration + factory + seeder + controller
php artisan make:controller PostController
php artisan make:controller PostController --resource — CRUD metodları
php artisan make:controller PostController --api — resource minus create/edit
php artisan make:controller PostController --invokable — tək __invoke metodu
php artisan make:migration create_posts_table
php artisan make:migration add_status_to_posts_table --table=posts
php artisan make:seeder PostSeeder
php artisan make:factory PostFactory
php artisan make:request StorePostRequest
php artisan make:resource PostResource
php artisan make:resource PostCollection --collection
php artisan make:policy PostPolicy
php artisan make:policy PostPolicy --model=Post
php artisan make:middleware CheckAge
php artisan make:job ProcessPodcast
php artisan make:job ProcessPodcast --sync
php artisan make:event PodcastProcessed
php artisan make:listener SendPodcastNotification --event=PodcastProcessed
php artisan make:mail OrderShipped
php artisan make:mail OrderShipped --markdown=emails.orders.shipped
php artisan make:notification InvoicePaid
php artisan make:channel OrderChannel
php artisan make:rule Uppercase
php artisan make:cast Json
php artisan make:observer PostObserver --model=Post
php artisan make:scope AncientScope
php artisan make:command SendEmails
php artisan make:provider AppServiceProvider
php artisan make:exception PostNotFoundException
php artisan make:interface PostRepositoryInterface
php artisan make:trait HasTimestamps
php artisan make:view users.index
php artisan make:test PostTest
php artisan make:test PostTest --unit
php artisan make:enum Status

## Database

php artisan migrate — pending migrasiyaları işlət
php artisan migrate --force — prod-da təsdiqsiz çalışdır
php artisan migrate:status — migrasiya statusu
php artisan migrate:rollback — son batch geri al
php artisan migrate:rollback --step=3 — 3 migrasiya geri al
php artisan migrate:reset — bütün migrasiyaları geri al
php artisan migrate:refresh — reset + migrate (data itirilir)
php artisan migrate:fresh — drop all tables + migrate (data itirilir)
php artisan migrate:fresh --seed — migrate + seed
php artisan db:seed — default DatabaseSeeder-i çalışdır
php artisan db:seed --class=PostSeeder
php artisan db:wipe — bütün cədvəlləri sil
php artisan schema:dump — SQL dump yarat
php artisan db:show — DB əlaqə məlumatı
php artisan db:table posts — cədvəl strukturu
php artisan db:monitor — connection pool monitoring
php artisan db:query "SELECT * FROM users" — SQL çalışdır (Laravel 11)

## Cache / config / route / view

php artisan cache:clear — application cache
php artisan config:clear — config cache sil
php artisan config:cache — config cache yarat (prod)
php artisan route:clear — route cache sil
php artisan route:cache — route cache yarat (prod)
php artisan view:clear — compiled views sil
php artisan view:cache — bütün views kompil et
php artisan optimize — config + route + view cache (prod)
php artisan optimize:clear — bütün cache-lər sil
php artisan event:cache — event→listener map cache
php artisan event:clear

## Route

php artisan route:list — bütün route-lar
php artisan route:list --path=api — filter by path
php artisan route:list --name=users — filter by name
php artisan route:list -v — method, middleware də göstər

## Queue

php artisan queue:work — default queue worker
php artisan queue:work redis — spesifik connection
php artisan queue:work --queue=high,default — priority queues
php artisan queue:work --tries=3 — max retry
php artisan queue:work --timeout=90 — timeout (saniyə)
php artisan queue:work --sleep=3 — boş queue-da gözləmə
php artisan queue:work --max-jobs=1000 — N job-dan sonra restart
php artisan queue:work --max-time=3600 — N saniyədən sonra restart
php artisan queue:work --stop-when-empty
php artisan queue:listen — hər job-dan sonra yenidən başla (dev)
php artisan queue:restart — worker-ləri yenidən başlat (deploy sonrası)
php artisan queue:failed — failed job-lar
php artisan queue:retry all — hamısını yenidən işlət
php artisan queue:retry <id> — konkret job
php artisan queue:forget <id> — failed job sil
php artisan queue:flush — bütün failed job-ları sil
php artisan queue:monitor redis:default,redis:deployments — threshold alert
php artisan queue:prune-failed --hours=48

## Horizon (Redis queue dashboard)

php artisan horizon — Horizon başlat
php artisan horizon:terminate — graceful shutdown
php artisan horizon:pause — pause (deploy)
php artisan horizon:continue
php artisan horizon:status
php artisan horizon:list
php artisan horizon:purge — tamamlanmış process-ləri sil
php artisan horizon:forget <id>
php artisan horizon:snapshot — metrics snapshot yaz

## Scheduler

php artisan schedule:run — bir dəfə bütün due task-ları işlət
php artisan schedule:work — local-da daemon kimi işlət
php artisan schedule:list — planlaşdırılmış task-lar
php artisan schedule:test — konret task test et
php artisan schedule:interrupt — schedule:work dayandır

## Storage / filesystem

php artisan storage:link — public/storage symlink yarat
php artisan storage:unlink

## Maintenance mode

php artisan down — maintenance mode
php artisan down --refresh=15 — 15s-da bir auto-refresh
php artisan down --secret=bypass-token — secret URL ilə keçid
php artisan down --render="errors::503"
php artisan up — maintenance mode-dan çıx

## Event / listener

php artisan event:list — bütün event→listener mapping
php artisan event:generate — EventServiceProvider-dən yarad

## Key / env

php artisan key:generate — APP_KEY yarat
php artisan key:generate --show — .env-ə yazmadan göstər

## Telescope

php artisan telescope:install
php artisan telescope:publish
php artisan telescope:clear
php artisan telescope:prune --hours=48

## Pulse

php artisan pulse:install
php artisan pulse:work — Pulse aggregation worker
php artisan pulse:restart
php artisan pulse:clear

## Testing

php artisan test — PHPUnit/Pest testləri çalışdır
php artisan test --filter=PostTest — filtr
php artisan test --parallel — paralel
php artisan test --coverage — kod əhatəsi
php artisan test --profile — yavaş testlər

## Misc

php artisan lang:publish — dil faylları publish et
php artisan vendor:publish — package faylları publish et
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --tag=config
php artisan channel:list — broadcast channel-lar
php artisan model:show Post — model məlumatı (relations, casts, attributes)
php artisan model:prune — prunable model-ləri sil
php artisan model:prune --pretend — nə silinəcəyini göstər
php artisan auth:clear-resets — expired password resets
php artisan cache:forget key — konkret key sil
php artisan cache:table — database cache migration
php artisan session:table — database session migration
php artisan queue:table — database queue migration
php artisan notifications:table
