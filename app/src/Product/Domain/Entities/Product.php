<?php

declare(strict_types=1);

namespace Src\Product\Domain\Entities;

use Src\Shared\Domain\AggregateRoot;
use Src\Product\Domain\ValueObjects\ProductId;
use Src\Product\Domain\ValueObjects\ProductName;
use Src\Product\Domain\ValueObjects\Money;
use Src\Product\Domain\ValueObjects\Stock;
use Src\Product\Domain\Events\ProductCreatedEvent;
use Src\Product\Domain\Events\StockDecreasedEvent;
use Src\Product\Domain\Events\LowStockIntegrationEvent;

/**
 * Product - Məhsul Aggregate Root.
 *
 * AggregateRoot nədir?
 * - Bir qrup əlaqəli obyektin "kök" (root) obyektidir.
 * - Bütün dəyişikliklər AggregateRoot vasitəsilə edilir.
 * - Xaricdən birbaşa daxili obyektlərə müraciət etmək OLMAZ.
 *
 * Məsələn:
 * - Product (AggregateRoot) -> ProductName, Money, Stock (daxili ValueObject-lər)
 * - Stoku dəyişmək üçün birbaşa Stock-a yox, Product-a müraciət edirik.
 * - Bu, biznes qaydalarının pozulmasının qarşısını alır.
 *
 * Bu sinif Domain Event-ləri qeydə alır (recordEvent).
 * Event-lər AggregateRoot saxlananda (save) göndərilir (dispatch).
 *
 * Factory Method pattern:
 * - create() statik metodu constructor əvəzinə istifadə olunur.
 * - Niyə? Çünki yaradılma zamanı event qeydə almaq lazımdır.
 * - Constructor yalnız obyektin vəziyyətini təyin edir, əlavə məntiq ehtiva etmir.
 */
final class Product extends AggregateRoot
{
    /**
     * Private constructor - xaricdən birbaşa "new Product()" yazmaq olmaz.
     * Yalnız create() metodu vasitəsilə yaradıla bilər.
     */
    private function __construct(
        private readonly ProductId $id,
        private ProductName $name,
        private Money $price,
        private Stock $stock,
    ) {
    }

    /**
     * Yeni məhsul yaradır (Factory Method).
     *
     * Bu metod:
     * 1. Yeni Product obyekti yaradır.
     * 2. ProductCreatedEvent qeydə alır.
     * 3. Yaradılmış Product-ı qaytarır.
     *
     * @param ProductId   $id    Məhsulun unikal identifikatoru
     * @param ProductName $name  Məhsulun adı
     * @param Money       $price Məhsulun qiyməti
     * @param Stock       $stock Anbardakı miqdar
     */
    public static function create(
        ProductId $id,
        ProductName $name,
        Money $price,
        Stock $stock,
    ): self {
        $product = new self($id, $name, $price, $stock);

        // Domain Event qeydə alırıq - "Yeni məhsul yaradıldı"
        $product->recordEvent(new ProductCreatedEvent(
            productId: $id->value(),
            productName: $name->value(),
            priceAmount: $price->amount(),
            priceCurrency: $price->currency(),
            stock: $stock->quantity(),
        ));

        return $product;
    }

    /**
     * Məhsulun stokunu azaldır.
     *
     * Bu metod:
     * 1. Stock ValueObject-in decrease() metodunu çağırır.
     * 2. StockDecreasedEvent qeydə alır.
     * 3. Əgər stok 5-dən aşağı düşübsə, LowStockIntegrationEvent qeydə alır.
     *
     * Diqqət: Stock ValueObject immutable-dır, ona görə yeni Stock yaradılır.
     *
     * @param int $amount Azaldılacaq miqdar
     */
    public function decreaseStock(int $amount): void
    {
        $previousQuantity = $this->stock->quantity();

        // Stock.decrease() yeni Stock qaytarır (immutable)
        $this->stock = $this->stock->decrease($amount);

        // Domain Event qeydə alırıq
        $this->recordEvent(new StockDecreasedEvent(
            productId: $this->id->value(),
            previousStock: $previousQuantity,
            newStock: $this->stock->quantity(),
            decreasedBy: $amount,
        ));

        // Stok aşağı həddin altına düşübsə, inteqrasiya hadisəsi qeydə alırıq
        // Bu, digər bounded context-ləri xəbərdar etmək üçündür
        if ($this->stock->quantity() < LowStockIntegrationEvent::LOW_STOCK_THRESHOLD) {
            $this->recordEvent(new LowStockIntegrationEvent(
                productId: $this->id->value(),
                productName: $this->name->value(),
                currentStock: $this->stock->quantity(),
            ));
        }
    }

    /**
     * Məhsulun stokunu artırır.
     *
     * @param int $amount Artırılacaq miqdar
     */
    public function increaseStock(int $amount): void
    {
        // Stock.increase() yeni Stock qaytarır (immutable)
        $this->stock = $this->stock->increase($amount);
    }

    // === Getter metodları - obyektin dəyərlərini oxumaq üçün ===

    public function id(): ProductId
    {
        return $this->id;
    }

    public function name(): ProductName
    {
        return $this->name;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function stock(): Stock
    {
        return $this->stock;
    }
}
