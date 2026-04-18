<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * ENTITY (DDD Pattern)
 * ====================
 * Entity — unikal identifikatorla (ID) müəyyən edilən domen obyektidir.
 *
 * ENTITY vs VALUE OBJECT fərqi:
 * - Entity: ID ilə müqayisə olunur. İki Entity eyni ID-yə malikdirsə, eynidir.
 *   Məsələn: İki User — adları eyni olsa belə, ID-ləri fərqlidirsə, fərqli User-lərdir.
 *
 * - Value Object: Dəyərləri ilə müqayisə olunur. ID-si yoxdur.
 *   Məsələn: Money(100, 'USD') == Money(100, 'USD') — eynidir.
 */
abstract class Entity
{
    /**
     * Hər Entity-nin unikal identifikatoru.
     * String istifadə edirik ki, UUID, ULID və ya hər hansı ID formatını dəstəkləyək.
     */
    protected string $id;

    public function id(): string
    {
        return $this->id;
    }

    /**
     * İki Entity-ni müqayisə et.
     * Entity-lər YALNIZ ID-lərinə görə müqayisə olunur,
     * digər sahələri (ad, email və s.) nəzərə alınmır.
     */
    public function equals(Entity $other): bool
    {
        return $this->id === $other->id
            && static::class === get_class($other);
    }
}
