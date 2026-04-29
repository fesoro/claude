<?php

declare(strict_types=1);

namespace Src\Product\Infrastructure\Repositories;

use Src\Product\Domain\Entities\Product;
use Src\Product\Domain\Repositories\ProductRepositoryInterface;
use Src\Product\Domain\ValueObjects\ProductId;
use Src\Product\Domain\ValueObjects\ProductName;
use Src\Product\Domain\ValueObjects\Money;
use Src\Product\Domain\ValueObjects\Stock;
use Src\Shared\Infrastructure\Bus\EventDispatcher;
use Illuminate\Support\Facades\DB;

/**
 * EloquentProductRepository - Laravel Eloquent/DB ilə işləyən repository implementasiyası.
 *
 * Bu sinif ProductRepositoryInterface-i implementasiya edir.
 * Domain qatı bu sinifi birbaşa tanımır - yalnız interfeysi tanıyır.
 *
 * Infrastructure qatı nədir?
 * - Texniki detallar burada yerləşir: verilənlər bazası, cache, API çağırışları.
 * - Domain qatı bu detallardan xəbərsizdir.
 * - Bu sinifi başqa implementasiya ilə əvəz edə bilərik (məsələn: API-dən oxuyan repository).
 *
 * Mapping (Xəritələmə):
 * - Verilənlər bazası sətirini (row) Domain Entity-yə çeviririk.
 * - Domain Entity-ni verilənlər bazası sətrinə çeviririk.
 * - Bu, "Data Mapper" pattern-dir.
 */
class EloquentProductRepository implements ProductRepositoryInterface
{
    private const TABLE = 'products';

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {
    }

    /**
     * ID-yə görə məhsul tapır.
     * Verilənlər bazası sətirini Domain Entity-yə çevirir.
     */
    public function findById(ProductId $id): ?Product
    {
        $row = DB::table(self::TABLE)
            ->where('id', $id->value())
            ->first();

        if ($row === null) {
            return null;
        }

        // Verilənlər bazası sətirindən Domain Entity yaradırıq
        return $this->toDomainEntity($row);
    }

    /**
     * Məhsulu verilənlər bazasına saxlayır.
     * updateOrInsert istifadə edirik - varsa yeniləyir, yoxdursa yaradır (upsert).
     */
    public function save(Product $product): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            ['id' => $product->id()->value()],
            [
                'name' => $product->name()->value(),
                'price_amount' => $product->price()->amount(),
                'price_currency' => $product->price()->currency(),
                'stock' => $product->stock()->quantity(),
                'updated_at' => now(),
            ]
        );

        $this->eventDispatcher->dispatch($product->pullDomainEvents());
    }

    /**
     * Bütün məhsulları qaytarır.
     *
     * @return Product[] Domain Entity-lərin massivi
     */
    public function findAll(): array
    {
        $rows = DB::table(self::TABLE)->get();

        // Hər sətri Domain Entity-yə çeviririk
        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    /**
     * Verilənlər bazası sətirini Domain Entity-yə çevirir.
     *
     * Bu "mapping" (xəritələmə) prosesidir:
     * - DB sətri (stdClass) -> ValueObject-lər -> Entity
     * - Hər sahə müvafiq ValueObject-ə çevrilir.
     *
     * Niyə Product::create() əvəzinə birbaşa yaradırıq?
     * - create() yeni məhsul üçündür və event qeydə alır.
     * - Bu isə mövcud məhsulu yenidən qurur (reconstitute) - event lazım deyil.
     *
     * Reflection istifadə edirik çünki Product-ın constructor-u private-dır.
     */
    private function toDomainEntity(object $row): Product
    {
        // Private constructor-a müraciət üçün Reflection istifadə edirik
        // Bu, Infrastructure qatında normal bir yanaşmadır
        $reflection = new \ReflectionClass(Product::class);
        $product = $reflection->newInstanceWithoutConstructor();

        // Private xüsusiyyətlərə dəyər təyin edirik
        $idProp = $reflection->getProperty('id');
        $idProp->setValue($product, ProductId::fromString($row->id));

        $nameProp = $reflection->getProperty('name');
        $nameProp->setValue($product, new ProductName($row->name));

        $priceProp = $reflection->getProperty('price');
        $priceProp->setValue($product, new Money($row->price_amount, $row->price_currency));

        $stockProp = $reflection->getProperty('stock');
        $stockProp->setValue($product, new Stock($row->stock));

        return $product;
    }
}
