# System Design: URL Shortener (Senior)

## Mündəricat
1. [Tələblər](#tələblər)
2. [Yüksək Səviyyəli Dizayn](#yüksək-səviyyəli-dizayn)
3. [Komponent Dizaynı](#komponent-dizaynı)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional:
  URL qısaltmaq: uzun URL → qısa kod (6-8 simvol)
  Redirect: qısa URL → orijinal URL-ə redirect
  Custom alias: istifadəçi öz kodunu seçə bilər
  Analytics: neçə dəfə kliklənilib

Qeyri-funksional:
  Yüksək mövcudluq: 99.9% uptime
  Aşağı gecikmə: redirect < 10ms
  Ölçüm: gündə 100M redirect
  
Hesablamalar:
  Write: gündə 1M URL → saniyədə ~12 write
  Read:  gündə 100M redirect → saniyədə ~1160 read
  Read:Write ratio = 100:1

Storage:
  1M URL/gün × 365 gün × 5 il = ~1.8B URL
  Hər URL: 500 bytes → ~900 GB
```

---

## Yüksək Səviyyəli Dizayn

```
┌──────────┐    ┌─────────────┐    ┌──────────────┐
│  Client  │───►│  API GW /   │───►│  URL Service │
└──────────┘    │  Load Bal.  │    └──────┬───────┘
                └─────────────┘           │
                                    ┌─────┴──────┐
                                    │   Cache    │
                                    │  (Redis)   │
                                    └─────┬──────┘
                                          │ miss
                                    ┌─────▼──────┐
                                    │  Database  │
                                    │ (MySQL/PG) │
                                    └────────────┘

Redirect axını:
  1. GET /abc123 → URL Service
  2. Redis-dən bax (HIT → 302 Redirect)
  3. Miss → DB-dən bax
  4. DB-dən cache-ə yaz
  5. 302 Redirect

Analytics axını (async):
  Redirect baş verəndə → Kafka-ya event yaz
  Analytics Worker → event işlə → ClickHouse-a yaz
```

---

## Komponent Dizaynı

```
ID Generation — qısa kod necə yaradılır?

Seçim 1 — Base62 Encoding:
  [0-9a-zA-Z] = 62 simvol
  6 simvol → 62^6 = ~56 milyard unikal URL
  
  Snowflake ID → Base62 çevir
  18446744073 → "dnh4gG"

Seçim 2 — MD5 Hash (ilk 6 simvol):
  md5(longUrl)[:6] → "a3f9k2"
  Kolliziya riski! Zəifdir.

Seçim 3 — Counter (auto-increment):
  DB-dən növbəti ID al, Base62 çevir
  Problem: bottleneck, single point of failure

Seçim 4 — Distributed ID (Snowflake):
  Timestamp + machineId + sequence → unique
  Decentralized, no coordination

Collision handling:
  Yaranan kod mövcuddursa → yeni kod yarat (retry)
  Custom alias unikal olmalıdır → unique constraint

Redirect növləri:
  301: Permanent (browser cache edir → analytics itir)
  302: Temporary (hər dəfə server-ə gedir → analytics işləyir)
  → Analytics lazımdırsa 302 seçin

Cache strategiyası:
  Hot URLs (çox kliklənilib) → Redis-də saxla
  TTL: 24 saat (URL silinənə qədər)
  Cache-aside pattern
```

---

## PHP İmplementasiyası

```php
<?php
namespace App\UrlShortener;

class UrlShortenerService
{
    private const BASE62_CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const CODE_LENGTH  = 7;

    public function __construct(
        private UrlRepository  $repository,
        private CacheInterface $cache,
        private IdGenerator    $idGenerator,
    ) {}

    public function shorten(string $longUrl, ?string $customAlias = null): string
    {
        $this->validateUrl($longUrl);

        if ($customAlias !== null) {
            return $this->createWithAlias($longUrl, $customAlias);
        }

        // Mövcud qısa URL varmı?
        $existing = $this->repository->findByLongUrl($longUrl);
        if ($existing !== null) {
            return $existing->getCode();
        }

        $code = $this->generateUniqueCode();

        $url = new ShortUrl(
            code:    $code,
            longUrl: $longUrl,
        );

        $this->repository->save($url);
        return $code;
    }

    public function resolve(string $code): ?string
    {
        $cacheKey = "url:{$code}";

        // 1. Cache-dən yoxla
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 2. DB-dən yoxla
        $url = $this->repository->findByCode($code);
        if ($url === null) {
            return null;
        }

        // 3. Cache-ə yaz
        $this->cache->set($cacheKey, $url->getLongUrl(), ttl: 86400);

        return $url->getLongUrl();
    }

    private function generateUniqueCode(): string
    {
        $maxAttempts = 5;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $id   = $this->idGenerator->next(); // Snowflake ID
            $code = $this->toBase62($id);

            if (!$this->repository->codeExists($code)) {
                return $code;
            }
        }

        throw new CodeGenerationException("Unikal kod yaratmaq mümkün olmadı");
    }

    private function toBase62(int $num): string
    {
        $chars  = self::BASE62_CHARS;
        $result = '';

        while ($num > 0) {
            $result = $chars[$num % 62] . $result;
            $num    = intdiv($num, 62);
        }

        // Minimum uzunluq
        return str_pad($result, self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function createWithAlias(string $longUrl, string $alias): string
    {
        $this->validateAlias($alias);

        if ($this->repository->codeExists($alias)) {
            throw new AliasAlreadyTakenException("'{$alias}' artıq istifadə edilir");
        }

        $url = new ShortUrl(code: $alias, longUrl: $longUrl);
        $this->repository->save($url);

        return $alias;
    }

    private function validateUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException("Yanlış URL: {$url}");
        }
    }

    private function validateAlias(string $alias): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $alias)) {
            throw new InvalidAliasException("Alias 3-20 simvol, yalnız hərf/rəqəm/_/-");
        }
    }
}
```

```php
<?php
// Analytics — async (Kafka event)
class RedirectController
{
    public function redirect(string $code, Request $request): Response
    {
        $longUrl = $this->shortener->resolve($code);

        if ($longUrl === null) {
            return new Response('URL tapılmadı', 404);
        }

        // Async analytics (fire-and-forget)
        $this->analyticsQueue->publish(new UrlClickedEvent(
            code:      $code,
            ip:        $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
            referer:   $request->headers->get('Referer'),
            clickedAt: new \DateTimeImmutable(),
        ));

        // 302 — analytics üçün (browser cache etmir)
        return new RedirectResponse($longUrl, 302);
    }
}
```

---

## İntervyu Sualları

- 6 simvollu Base62 kod neçə unikal URL dəstəkləyir?
- 301 vs 302 redirect — analytics üçün hansı seçərdiniz?
- Yüksək yük altında DB bottleneck-ini necə azaldarsınız?
- Custom alias kolliziyasını necə idarə edirsiniz?
- Analytics data-sını real-time redirect-dən niyə ayırmalısınız?
- URL-ləri expire etmək üçün strategiyanız nədir?
