package az.ecommerce.unit.product;

import az.ecommerce.product.domain.valueobject.Stock;
import az.ecommerce.shared.domain.exception.DomainException;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.*;

class StockValueObjectTest {

    @Test
    void shouldCreateValidStock() {
        Stock stock = Stock.of(50);
        assertEquals(50, stock.quantity());
        assertFalse(stock.isOutOfStock());
        assertFalse(stock.isLow());
    }

    @Test
    void shouldRejectNegativeStock() {
        assertThrows(DomainException.class, () -> Stock.of(-1));
    }

    @Test
    void shouldDecreaseStock() {
        Stock stock = Stock.of(10).decrease(3);
        assertEquals(7, stock.quantity());
    }

    @Test
    void shouldRejectDecreaseBeyondAvailable() {
        Stock stock = Stock.of(5);
        assertThrows(DomainException.class, () -> stock.decrease(10));
    }

    @Test
    void shouldDetectLowStock() {
        assertTrue(Stock.of(3).isLow());
        assertFalse(Stock.of(10).isLow());
    }

    @Test
    void shouldDetectOutOfStock() {
        assertTrue(Stock.of(0).isOutOfStock());
    }
}
