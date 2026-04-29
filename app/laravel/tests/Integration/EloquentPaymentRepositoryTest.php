<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Payment\Domain\Entities\Payment;
use Src\Payment\Domain\ValueObjects\PaymentId;
use Src\Payment\Domain\ValueObjects\PaymentMethod;
use Src\Payment\Infrastructure\Repositories\EloquentPaymentRepository;
use Src\Product\Domain\ValueObjects\Money;
use Src\Shared\Infrastructure\Bus\EventDispatcher;
use Tests\TestCase;

/**
 * ELOQUENT PAYMENT REPOSITORY İNTEQRASİYA TESTLƏRİ
 * ==================================================
 * Bu testlər EloquentPaymentRepository-nin real verilənlər bazası ilə düzgün işlədiyini yoxlayır.
 *
 * STATUS KEÇİDLƏRİ:
 *   PENDING → PROCESSING → COMPLETED
 *   PENDING → PROCESSING → FAILED
 *
 * Yoxlananlar:
 * - save() + findById() round-trip
 * - process() status dəyişikliyi persist olunur
 * - complete() COMPLETED statusu + transactionId persist olunur
 * - fail() FAILED statusu + failureReason persist olunur
 * - findByOrderId() orderId ilə ödəniş tapılır
 * - Mövcud olmayan ID üçün null qaytarılır
 *
 * RefreshDatabase trait hər testdən əvvəl bazanı təmizləyir.
 */
class EloquentPaymentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentPaymentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentPaymentRepository(new EventDispatcher());
    }

    private function makePayment(string $orderId = null): Payment
    {
        return Payment::create(
            orderId: $orderId ?? fake()->uuid(),
            amount: new Money(99.99, 'AZN'),
            method: PaymentMethod::creditCard(),
        );
    }

    // ============================================================
    // save() + findById() TESTLƏR
    // ============================================================

    public function test_save_persists_new_payment_to_database(): void
    {
        $orderId = fake()->uuid();
        $payment = $this->makePayment($orderId);

        $this->repository->save($payment);

        $found = $this->repository->findById($payment->paymentId());
        $this->assertNotNull($found);
        $this->assertEquals($orderId, $found->orderId());
        $this->assertEquals(99.99, $found->amount()->amount());
        $this->assertEquals('AZN', $found->amount()->currency());
        $this->assertTrue($found->status()->isPending());
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $found = $this->repository->findById(PaymentId::generate());
        $this->assertNull($found);
    }

    // ============================================================
    // STATUS KEÇID TESTLƏRİ
    // ============================================================

    public function test_process_status_is_persisted(): void
    {
        $payment = $this->makePayment();
        $this->repository->save($payment);

        $payment->process();
        $this->repository->save($payment);

        $found = $this->repository->findById($payment->paymentId());
        $this->assertNotNull($found);
        $this->assertTrue($found->status()->isProcessing());
    }

    public function test_complete_status_and_transaction_id_are_persisted(): void
    {
        $payment = $this->makePayment();
        $this->repository->save($payment);

        $payment->process();
        $payment->complete('stripe_txn_abc123');
        $this->repository->save($payment);

        $found = $this->repository->findById($payment->paymentId());
        $this->assertNotNull($found);
        $this->assertTrue($found->status()->isCompleted());
        $this->assertEquals('stripe_txn_abc123', $found->transactionId());
    }

    public function test_fail_status_and_reason_are_persisted(): void
    {
        $payment = $this->makePayment();
        $this->repository->save($payment);

        $payment->process();
        $payment->fail('Kart rədd edildi');
        $this->repository->save($payment);

        $found = $this->repository->findById($payment->paymentId());
        $this->assertNotNull($found);
        $this->assertTrue($found->status()->isFailed());
        $this->assertEquals('Kart rədd edildi', $found->failureReason());
    }

    // ============================================================
    // findByOrderId() TESTLƏRİ
    // ============================================================

    public function test_find_by_order_id_returns_payment(): void
    {
        $orderId = fake()->uuid();
        $payment = $this->makePayment($orderId);
        $this->repository->save($payment);

        $found = $this->repository->findByOrderId($orderId);
        $this->assertNotNull($found);
        $this->assertEquals($orderId, $found->orderId());
    }

    public function test_find_by_order_id_returns_null_when_not_found(): void
    {
        $found = $this->repository->findByOrderId(fake()->uuid());
        $this->assertNull($found);
    }
}
