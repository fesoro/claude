<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Specification;

/**
 * UNIT TEST NƏDİR?
 * =================
 * Unit Test — proqramın ən kiçik hissəsini (metod, sinif) TƏK BAŞINA test edən testdir.
 * Verilənlər bazası, API, fayl sistemi kimi xarici asılılıqlar İSTİFADƏ OLUNMUR.
 * Ona görə çox sürətli işləyir (millisaniyələrlə).
 *
 * Unit Test yalnız BİR şeyi yoxlayır:
 * - Bu metod düzgün nəticə qaytarırmı?
 * - Bu sinif düzgün davranırmı?
 * - Xəta halında düzgün Exception atılırmı?
 *
 * AAA PATTERN (Arrange-Act-Assert):
 * ===================================
 * Hər test 3 hissədən ibarətdir:
 *
 * 1. ARRANGE (Hazırla) — Test üçün lazım olan obyektləri yarat, datanı hazırla.
 *    Məsələn: $spec = new AlwaysTrueSpec();
 *
 * 2. ACT (İcra et) — Test olunan metodu çağır.
 *    Məsələn: $result = $spec->isSatisfiedBy('test');
 *
 * 3. ASSERT (Yoxla) — Nəticənin gözlənilən dəyərə uyğun olduğunu yoxla.
 *    Məsələn: $this->assertTrue($result);
 *
 * TEST ADLANDIRMA QAYDASI (Naming Convention):
 * ==============================================
 * Test metodunun adı NƏYİ test etdiyini aydın göstərməlidir:
 * - test_and_returns_true_when_both_specs_are_satisfied
 * - test_or_returns_false_when_both_specs_are_not_satisfied
 *
 * İki yanaşma var:
 * 1. test_ prefiksi: public function test_metod_adi()
 *    - PHPUnit test_ ilə başlayan metodları avtomatik test kimi tanıyır.
 *
 * 2. @test annotasiyası:
 *    /** @test * /
 *    public function metod_adi()
 *    - @test annotasiyası ilə test_ prefiksi lazım deyil.
 *    - Hər iki yanaşma eyni işi görür, komanda daxilində birini seçib ardıcıl istifadə etmək yaxşıdır.
 *
 * Bu test faylında test_ prefiksi istifadə edirik.
 *
 * QEYD: TestCase kimi PHPUnit\Framework\TestCase istifadə edirik (Laravel-in TestCase-i deyil),
 * çünki Unit Test-də Laravel framework-ə ehtiyac yoxdur.
 */
class SpecificationTest extends TestCase
{
    // ===========================
    // AND() KOMBİNASİYASI TESTLƏRİ
    // ===========================

    /**
     * and() metodu — hər iki specification doğru olduqda true qaytarmalıdır.
     * Məsələn: İstifadəçi aktiv VƏ balansı var → true
     */
    public function test_and_returns_true_when_both_specs_are_satisfied(): void
    {
        // Arrange — iki "həmişə doğru" specification yaradırıq
        $trueSpec1 = new AlwaysTrueSpec();
        $trueSpec2 = new AlwaysTrueSpec();

        // Act — and() ilə birləşdirib, nəticəni alırıq
        $combined = $trueSpec1->and($trueSpec2);
        $result = $combined->isSatisfiedBy('test');

        // Assert — nəticə true olmalıdır
        $this->assertTrue($result);
    }

    /**
     * and() — birinci doğru, ikincisi yanlışdırsa false qaytarmalıdır.
     * Məsələn: İstifadəçi aktiv VƏ balansı yoxdur → false
     */
    public function test_and_returns_false_when_first_is_true_second_is_false(): void
    {
        // Arrange
        $trueSpec = new AlwaysTrueSpec();
        $falseSpec = new AlwaysFalseSpec();

        // Act
        $combined = $trueSpec->and($falseSpec);
        $result = $combined->isSatisfiedBy('test');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * and() — birincisi yanlış, ikincisi doğrudursa false qaytarmalıdır.
     */
    public function test_and_returns_false_when_first_is_false_second_is_true(): void
    {
        // Arrange
        $falseSpec = new AlwaysFalseSpec();
        $trueSpec = new AlwaysTrueSpec();

        // Act
        $result = $falseSpec->and($trueSpec)->isSatisfiedBy('test');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * and() — hər ikisi yanlışdırsa false qaytarmalıdır.
     */
    public function test_and_returns_false_when_both_specs_are_not_satisfied(): void
    {
        // Arrange
        $falseSpec1 = new AlwaysFalseSpec();
        $falseSpec2 = new AlwaysFalseSpec();

        // Act
        $result = $falseSpec1->and($falseSpec2)->isSatisfiedBy('test');

        // Assert
        $this->assertFalse($result);
    }

    // ===========================
    // OR() KOMBİNASİYASI TESTLƏRİ
    // ===========================

    /**
     * or() — ən azı biri doğru olduqda true qaytarmalıdır.
     * Məsələn: Admin VƏ YA sifariş sahibi → icazə var
     */
    public function test_or_returns_true_when_both_specs_are_satisfied(): void
    {
        // Arrange
        $trueSpec1 = new AlwaysTrueSpec();
        $trueSpec2 = new AlwaysTrueSpec();

        // Act
        $result = $trueSpec1->or($trueSpec2)->isSatisfiedBy('test');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * or() — birincisi doğru, ikincisi yanlışdırsa true qaytarmalıdır.
     */
    public function test_or_returns_true_when_first_is_true_second_is_false(): void
    {
        // Arrange
        $trueSpec = new AlwaysTrueSpec();
        $falseSpec = new AlwaysFalseSpec();

        // Act
        $result = $trueSpec->or($falseSpec)->isSatisfiedBy('test');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * or() — birincisi yanlış, ikincisi doğrudursa true qaytarmalıdır.
     */
    public function test_or_returns_true_when_first_is_false_second_is_true(): void
    {
        // Arrange
        $falseSpec = new AlwaysFalseSpec();
        $trueSpec = new AlwaysTrueSpec();

        // Act
        $result = $falseSpec->or($trueSpec)->isSatisfiedBy('test');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * or() — hər ikisi yanlışdırsa false qaytarmalıdır.
     */
    public function test_or_returns_false_when_both_specs_are_not_satisfied(): void
    {
        // Arrange
        $falseSpec1 = new AlwaysFalseSpec();
        $falseSpec2 = new AlwaysFalseSpec();

        // Act
        $result = $falseSpec1->or($falseSpec2)->isSatisfiedBy('test');

        // Assert
        $this->assertFalse($result);
    }

    // ===========================
    // NOT() KOMBİNASİYASI TESTLƏRİ
    // ===========================

    /**
     * not() — doğru specification-ı tərsinə çevirib false qaytarmalıdır.
     * Məsələn: İstifadəçi aktiv DEYİL → aktiv istifadəçi üçün false
     */
    public function test_not_returns_false_when_spec_is_satisfied(): void
    {
        // Arrange
        $trueSpec = new AlwaysTrueSpec();

        // Act
        $result = $trueSpec->not()->isSatisfiedBy('test');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * not() — yanlış specification-ı tərsinə çevirib true qaytarmalıdır.
     */
    public function test_not_returns_true_when_spec_is_not_satisfied(): void
    {
        // Arrange
        $falseSpec = new AlwaysFalseSpec();

        // Act
        $result = $falseSpec->not()->isSatisfiedBy('test');

        // Assert
        $this->assertTrue($result);
    }

    // ====================================
    // MÜRƏKKƏB KOMBİNASİYA TESTLƏRİ
    // ====================================

    /**
     * Üçlü kombinasiya: (true AND false) OR true → true
     * Mürəkkəb biznes qaydalarını Specification ilə ifadə edə bilərik.
     */
    public function test_complex_combination_and_then_or(): void
    {
        // Arrange
        $trueSpec = new AlwaysTrueSpec();
        $falseSpec = new AlwaysFalseSpec();

        // Act — (true AND false) OR true
        // Əvvəlcə and() false qaytarır, sonra or() true qaytarır
        $combined = $trueSpec->and($falseSpec)->or(new AlwaysTrueSpec());
        $result = $combined->isSatisfiedBy('test');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * İkiqat not: NOT(NOT(true)) → true
     * İki dəfə tərsinə çevirmək əslini qaytarmalıdır.
     */
    public function test_double_not_returns_original_result(): void
    {
        // Arrange
        $trueSpec = new AlwaysTrueSpec();

        // Act — NOT(NOT(true)) = true
        $result = $trueSpec->not()->not()->isSatisfiedBy('test');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * NOT ilə AND kombinasiyası: NOT(true) AND true → false
     */
    public function test_not_combined_with_and(): void
    {
        // Arrange
        $trueSpec = new AlwaysTrueSpec();

        // Act — NOT(true) AND true = false AND true = false
        $result = $trueSpec->not()->and(new AlwaysTrueSpec())->isSatisfiedBy('test');

        // Assert
        $this->assertFalse($result);
    }
}

// ===========================
// TEST ÜÇÜN KÖMƏKÇI SPECİFİCATİON-LAR
// ===========================

/**
 * Həmişə true qaytaran test specification-ı.
 * Bu, real specification-ların yerinə istifadə olunur (test double).
 */
class AlwaysTrueSpec extends Specification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return true;
    }
}

/**
 * Həmişə false qaytaran test specification-ı.
 */
class AlwaysFalseSpec extends Specification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return false;
    }
}
