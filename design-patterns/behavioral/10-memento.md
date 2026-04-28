# Memento (Middle ⭐⭐)

## İcmal
Memento pattern bir object-in daxili state-ini encapsulation-ı pozmadan xarici olaraq saxlayır və lazım olduqda geri qaytarır. Undo/redo əməliyyatları, multi-step form wizard-ların state-i, batch əməliyyatdan əvvəl snapshot almaq — hamısı bu pattern üzərindədir. Object-in özündən başqası onun daxili state detallarını bilmir.

## Niyə Vacibdir
Real layihələrdə iki ümumi problem var: 1) İstifadəçi bir neçə dəyişiklik etdikdən sonra "Undo" etmək istəyir — state-i haradan gətiririk? 2) Batch əməliyyat işləyərkən xəta baş verir — əvvəlki vəziyyətə necə qayıdırıq? DB transaction bəzi hallarda kifayət edir, amma in-memory object state-i üçün Memento lazımdır. Laravel-də form wizard-ın addımlar arasında state saxlaması, model-in batch update-dən əvvəl snapshot-ı da eyni problemi həll edir.

## Əsas Anlayışlar
- **Originator**: state-i saxlayan object; `createMemento()` ilə snapshot verir, `restoreFromMemento()` ilə geri qaytarır
- **Memento**: state-in snapshot-ı; yalnız Originator-ın oxuya biləcəyi şəkildə state-i saxlayır — başqa class-lar bu dataya birbaşa çıxış əldə etmir
- **Caretaker**: Memento-ları idarə edir (siyahıda saxlayır, undo üçün verir); Memento-nun içinə baxmır — onu "opaque token" kimi işlədır
- **Encapsulation qorunur**: Caretaker Originator-ın state detallarını bilmir; yalnız Originator öz Memento-sunu interpret edir
- **History stack**: Caretaker undo/redo üçün Memento-ları stack-də saxlayır — `array_push` / `array_pop`
- **Shallow vs Deep copy**: Memento object reference-ları saxladıqda shallow copy — dəyişiklik Memento-nu da təsir edir; value object-lər ya `clone` istifadə et

## Praktik Baxış
- **Real istifadə**: text editor undo/redo, multi-step form wizard state (addımlar arasında get/back), expensive əməliyyatdan əvvəl model snapshot, database migration rollback simulasiyası, oyun save/load
- **Trade-off-lar**: hər snapshot memory istehlak edir — dərin undo history böyük obyektlər üçün memory bloat; snapshot alınma tezliyi ilə memory arasında balans lazımdır; kompleks object qrafları üçün deep copy bahalıdır
- **İstifadə etməmək**: state çox böyük və tez-tez dəyişirsə (incremental diff saxla); DB transaction ilə həll olunan hallarda (DB özü rollback edir); state-in serialization-ı mürəkkəbdirsə (circular reference, closure)
- **Common mistakes**: Caretaker-in Memento içinə baxması — encapsulation pozulur, Originator-ın daxili detalları leak edir; Memento-nu mutable etmək — state dəyişə bilər, undo pozulur; çox sayda Memento-nu memory-də saxlamaq — limit qoy (max N undo step)
- **Anti-Pattern Nə Zaman Olur?**: Object-in bütün state-ini hər dəyişiklikdə saxlamaq — 10 MB-lıq model üçün hər keystroke-da snapshot memory-i sürətlə tükəndirir. Həll: diff-based snapshot (yalnız dəyişən field-ləri saxla) ya da yalnız kritik nöqtələrdə (wizard step tamamlandıqda, batch başlanmazdan əvvəl) snapshot al. Digər problem: Memento-ya business logic qoymaq — Memento yalnız "data container"-dir, heç bir method-u olmamalıdır.

## Nümunələr

### Ümumi Nümunə
Text editor düşünün. İstifadəçi hər dəfə "Save Checkpoint" edəndə editor öz state-inin (mətn, cursor mövqeyi, seçim) snapshot-ını yaradır. "Undo" dedikdə editor son snapshot-a qayıdır. Editor (Originator) state detallarını bilir; Checkpoint Manager (Caretaker) isə snapshot-ları saxlayır amma içinə baxa bilmir.

### PHP/Laravel Nümunəsi

**Klassik Memento — Form Wizard:**

```php
<?php

// Memento — state-in snapshot-ı; yalnız FormWizard oxuya bilər
// readonly class — immutable olduğundan undo güvənilirdir
final class FormWizardMemento
{
    public function __construct(
        private readonly array $stepData,    // hər addımın data-sı
        private readonly int   $currentStep, // hazırkı addım indeksi
        private readonly array $validatedSteps // hansı addımlar validate olunub
    ) {}

    // Yalnız Originator bu metodları çağırır
    public function getStepData(): array       { return $this->stepData; }
    public function getCurrentStep(): int      { return $this->currentStep; }
    public function getValidatedSteps(): array { return $this->validatedSteps; }
}

// Originator — state-i saxlayan object
class FormWizard
{
    private array $stepData       = [];
    private int   $currentStep    = 1;
    private array $validatedSteps = [];

    public function fillStep(int $step, array $data): void
    {
        // Wizard state-ini yenilə
        $this->stepData[$step] = $data;
        $this->currentStep     = $step;
    }

    public function markStepValidated(int $step): void
    {
        $this->validatedSteps[] = $step;
    }

    public function goToStep(int $step): void
    {
        $this->currentStep = $step;
    }

    public function getCurrentStep(): int
    {
        return $this->currentStep;
    }

    public function getStepData(int $step): array
    {
        return $this->stepData[$step] ?? [];
    }

    public function isComplete(): bool
    {
        // Bütün addımlar (1-5) validate olunmuşsa
        return count($this->validatedSteps) >= 5;
    }

    // Snapshot yarat — state-i Memento-ya köçür
    public function createMemento(): FormWizardMemento
    {
        return new FormWizardMemento(
            stepData:       $this->stepData,
            currentStep:    $this->currentStep,
            validatedSteps: $this->validatedSteps
        );
    }

    // Snapshot-dan bərpa et — Memento-dan state-i al
    public function restoreFromMemento(FormWizardMemento $memento): void
    {
        $this->stepData       = $memento->getStepData();
        $this->currentStep    = $memento->getCurrentStep();
        $this->validatedSteps = $memento->getValidatedSteps();
    }
}

// Caretaker — Memento-ları saxlayır, içinə baxa bilmir
class WizardHistory
{
    /** @var FormWizardMemento[] */
    private array $history = [];
    private int   $maxHistory;

    public function __construct(int $maxHistory = 10)
    {
        $this->maxHistory = $maxHistory;
    }

    public function save(FormWizardMemento $memento): void
    {
        $this->history[] = $memento;

        // Memory bloat-un qarşısını al — köhnə snapshot-ları sil
        if (count($this->history) > $this->maxHistory) {
            array_shift($this->history); // ən köhnəni sil
        }
    }

    public function undo(): ?FormWizardMemento
    {
        if (count($this->history) <= 1) {
            return null; // undo üçün əvvəlki state yoxdur
        }
        array_pop($this->history); // cari state-i sil
        return end($this->history) ?: null; // əvvəlki state-ə qayıt
    }

    public function hasHistory(): bool
    {
        return count($this->history) > 1;
    }
}

// İstifadəsi
$wizard  = new FormWizard();
$history = new WizardHistory(maxHistory: 5);

// Addım 1: personal info
$wizard->fillStep(1, ['name' => 'Əli', 'email' => 'ali@example.com']);
$wizard->markStepValidated(1);
$history->save($wizard->createMemento()); // checkpoint

// Addım 2: shipping
$wizard->fillStep(2, ['address' => 'Nizami küç. 15', 'city' => 'Bakı']);
$wizard->markStepValidated(2);
$history->save($wizard->createMemento()); // checkpoint

// Addım 3: payment — istifadəçi geri qayıtmaq istəyir
$wizard->fillStep(3, ['card' => '4111...']);
// Oops — geri qayıt
if ($history->hasHistory()) {
    $previous = $history->undo();
    $wizard->restoreFromMemento($previous);
    // İndi wizard addım 2-dədir, addım 3 data-sı unudulub
    echo $wizard->getCurrentStep(); // 2
}
```

**Laravel-də session ilə wizard state:**

```php
// Session-da Memento saxlamaq — multi-page form wizard
class OrderWizardController extends Controller
{
    public function saveStep(Request $request, int $step): RedirectResponse
    {
        $wizard = $this->getWizardFromSession();

        // Əvvəlki state-i saxla (undo üçün)
        $history   = session('wizard_history', []);
        $history[] = serialize($wizard->createMemento()); // serialize — session storage
        session(['wizard_history' => array_slice($history, -5)]); // son 5 checkpoint

        // Yeni addımı doldur
        $wizard->fillStep($step, $request->validated());
        $wizard->markStepValidated($step);

        session(['wizard_state' => serialize($wizard->createMemento())]);

        return redirect()->route('order.wizard.step', $step + 1);
    }

    public function goBack(): RedirectResponse
    {
        $history = session('wizard_history', []);

        if (empty($history)) {
            return redirect()->route('order.wizard.step', 1);
        }

        $previousMementoData = array_pop($history);
        session(['wizard_history' => $history]);

        $wizard  = new FormWizard();
        $memento = unserialize($previousMementoData);
        $wizard->restoreFromMemento($memento);

        session(['wizard_state' => serialize($wizard->createMemento())]);

        return redirect()->route('order.wizard.step', $wizard->getCurrentStep());
    }

    private function getWizardFromSession(): FormWizard
    {
        $wizard = new FormWizard();
        $data   = session('wizard_state');

        if ($data) {
            $wizard->restoreFromMemento(unserialize($data));
        }

        return $wizard;
    }
}
```

**Model snapshot — batch update-dən əvvəl:**

```php
// Eloquent model state-ini batch əməliyyatdan əvvəl saxla
class ProductPriceMemento
{
    public function __construct(
        private readonly int   $productId,
        private readonly float $price,
        private readonly float $discountPrice,
        private readonly bool  $isOnSale,
        private readonly \DateTimeImmutable $savedAt
    ) {}

    public function getProductId(): int    { return $this->productId; }
    public function getPrice(): float      { return $this->price; }
    public function getDiscountPrice(): float { return $this->discountPrice; }
    public function isOnSale(): bool       { return $this->isOnSale; }
    public function getSavedAt(): \DateTimeImmutable { return $this->savedAt; }
}

class Product extends Model
{
    // Snapshot yarat — cari qiymət vəziyyəti
    public function createPriceMemento(): ProductPriceMemento
    {
        return new ProductPriceMemento(
            productId:     $this->id,
            price:         $this->price,
            discountPrice: $this->discount_price,
            isOnSale:      $this->is_on_sale,
            savedAt:       new \DateTimeImmutable()
        );
    }

    // Snapshot-dan qiyməti bərpa et
    public function restorePriceFromMemento(ProductPriceMemento $memento): void
    {
        $this->update([
            'price'          => $memento->getPrice(),
            'discount_price' => $memento->getDiscountPrice(),
            'is_on_sale'     => $memento->isOnSale(),
        ]);
    }
}

// Batch price update service
class PriceUpdateService
{
    public function bulkUpdateWithRollback(array $productIds, float $increasePercentage): void
    {
        $products  = Product::whereIn('id', $productIds)->get();
        $snapshots = []; // Caretaker rolu

        // Başlamadan əvvəl hamısının snapshot-ını al
        foreach ($products as $product) {
            $snapshots[$product->id] = $product->createPriceMemento();
        }

        DB::beginTransaction();
        try {
            foreach ($products as $product) {
                $newPrice = $product->price * (1 + $increasePercentage / 100);
                $product->update(['price' => round($newPrice, 2)]);
            }

            // Validation: heç biri 0-dan aşağı düşməsin
            $invalid = $products->filter(fn($p) => $p->fresh()->price <= 0);
            if ($invalid->isNotEmpty()) {
                throw new \RuntimeException("Invalid prices detected after update");
            }

            DB::commit();
            Log::info("Bulk price update completed", ['products' => $productIds]);

        } catch (\Throwable $e) {
            DB::rollBack();

            // DB rollback oldu amma in-memory state bərpa et
            foreach ($products as $product) {
                if (isset($snapshots[$product->id])) {
                    $product->restorePriceFromMemento($snapshots[$product->id]);
                }
            }

            Log::error("Bulk price update failed, rolled back", [
                'error'    => $e->getMessage(),
                'products' => $productIds,
            ]);

            throw $e;
        }
    }
}
```

## Praktik Tapşırıqlar
1. Text editor simulyasiyası: `TextEditor` class-ı (mətn, cursor mövqeyi), `TextEditorMemento`, `EditorHistory` (max 20 undo); `type()`, `delete()`, `moveCursor()` metodları hər dəfə otomatik checkpoint yaratmalı; `undo()` son vəziyyətə qayıtmalı
2. Laravel session-da multi-step checkout wizard qurun: 4 addım (cart confirm → shipping → payment → review); hər "Back" düyməsi əvvəlki addımın state-ə qayıdır; final submit-dən əvvəl wizard-ın validation-ı tamamlanmalı
3. `UserSettingsMemento` yazın: `UserSettings` model üçün snapshot; admin user settings-i dəyişdirəndə avtomatik snapshot; son 10 dəyişikliyi `user_settings_history` cədvəlində saxla; restore endpoint-i yaz

## Əlaqəli Mövzular
- [03-command.md](03-command.md) — Command pattern undo/redo üçün Memento ilə birlikdə istifadə olunur; Command əməliyyatı saxlayır, Memento state-i saxlayır
- [07-state.md](07-state.md) — State dəyişiklikləri üçün Memento checkpoint yararlıdır
- [../integration/02-event-sourcing.md](../integration/02-event-sourcing.md) — Event Sourcing Memento-nun tam tarix saxlayan versiyasıdır; hər state dəyişikliyi event kimi yazılır
- [../general/01-dto.md](../general/01-dto.md) — Memento əslində immutable DTO-dur; state detallarını transfer edir
