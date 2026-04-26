# Object-Oriented Design (Senior)

## İcmal

**Object-Oriented Design (OOD)** real-world problemləri **obyekt**lər, onların **atribut**ları (data) və **davranış**ları (methods) vasitəsilə modelləşdirmə prosesidir. Interview-larda OOD sualları namizədin **abstraksiya**, **encapsulation**, **inheritance**, **polymorphism** və **design pattern**lərdən istifadə bacarığını yoxlayır.

Tipik OOD interview sualları: Parking Lot, Elevator, Vending Machine, Library Management, ATM, Tic-Tac-Toe, Chess.

### OOD problem həlli addımları

1. **Requirement-ləri aydınlaşdır** - Functional və non-functional tələblər.
2. **Use case-ləri müəyyən et** - Aktyorlar və əməliyyatlar.
3. **Class-ları tap** - İsimlərdən class (Car, ParkingSpot).
4. **Əlaqələri tap** - Association, aggregation, composition, inheritance.
5. **Method-ları yaz** - Fe'llərdən method (park, leave).
6. **Design Pattern tətbiq et** - Strategy, State, Observer, Factory.
7. **SOLID** - Extensible code.


## Niyə Vacibdir

LLD (Low-Level Design) interview-larında parking lot, elevator, chess kimi OOD sualları soruşulur. SOLID prinsiplər, design pattern-lar — real PHP/Laravel layihəsinin strukturunu müəyyən edir. Refactoring, testability, maintainability hamısı bu prinsiplərə əsaslanır.

## Əsas Anlayışlar

### SOLID Principles

| Prinsip | İzahı |
|---------|-------|
| **S**ingle Responsibility | Hər class yalnız bir səbəbə görə dəyişməlidir |
| **O**pen/Closed | Genişlənməyə açıq, dəyişdirməyə bağlı |
| **L**iskov Substitution | Subclass parent-lə əvəz oluna bilməlidir |
| **I**nterface Segregation | İstifadə olunmayan interface-dən asılı olma |
| **D**ependency Inversion | High-level low-level-dən asılı olmamalıdır |

### Tez-tez istifadə olunan Design Pattern-lər

- **Strategy** - Alqoritmləri encapsulate et (fee calculation).
- **State** - State-ə görə davranış dəyişdir (ATM, elevator).
- **Observer** - Notification, event broadcasting.
- **Factory** - Obyekt yaradılması (Vehicle factory).
- **Singleton** - Tək instance (ParkingLotManager).
- **Command** - Əmrləri obyekt kimi (ATM transactions).

## Arxitektura

### Class Relationships

```
Association  : "uses-a"       (Student uses Course)
Aggregation  : "has-a" (weak) (Department has Professors)
Composition  : "has-a" (strong) (Car has Engine)
Inheritance  : "is-a"         (Car is a Vehicle)
```

### Parking Lot - Yüksək Səviyyəli Dizayn

```
ParkingLot (Singleton) → ParkingFloor → ParkingSpot (abstract)
                                         ├── CompactSpot
                                         ├── LargeSpot
                                         └── MotorcycleSpot
ParkingLot → Ticket (many)
```

## Nümunələr

### 1. Parking Lot (Strategy + Singleton)

```php
<?php
enum VehicleType: string { case Motorcycle='m'; case Car='c'; case Truck='t'; }
abstract class Vehicle {
    public function __construct(protected string $plate, protected VehicleType $type) {}
    public function getType(): VehicleType { return $this->type; }
}
class Car extends Vehicle { public function __construct(string $p) { parent::__construct($p, VehicleType::Car); } }
class Truck extends Vehicle { public function __construct(string $p) { parent::__construct($p, VehicleType::Truck); } }

abstract class ParkingSpot {
    protected ?Vehicle $vehicle = null;
    public function __construct(protected int $id, protected VehicleType $supports) {}
    public function canFit(Vehicle $v): bool { return !$this->vehicle && $this->supports === $v->getType(); }
    public function park(Vehicle $v): void { $this->vehicle = $v; }
    public function remove(): void { $this->vehicle = null; }
    public function getId(): int { return $this->id; }
}
class CompactSpot extends ParkingSpot { public function __construct(int $id) { parent::__construct($id, VehicleType::Car); } }
class LargeSpot extends ParkingSpot { public function __construct(int $id) { parent::__construct($id, VehicleType::Truck); } }

class Ticket {
    public function __construct(public readonly string $id, public readonly int $spotId,
        public readonly Vehicle $vehicle, public readonly \DateTimeImmutable $entryTime) {}
}
// Strategy Pattern
interface FeeStrategy { public function calculate(Ticket $t, \DateTimeImmutable $exit): float; }

class HourlyFeeStrategy implements FeeStrategy {
    private array $rates = ['m'=>2.0, 'c'=>5.0, 't'=>10.0];
    public function calculate(Ticket $t, \DateTimeImmutable $exit): float {
        $hours = max(1, ceil(($exit->getTimestamp() - $t->entryTime->getTimestamp()) / 3600));
        return $hours * $this->rates[$t->vehicle->getType()->value];
    }
}

// Singleton
class ParkingLot {
    private static ?self $instance = null;
    private array $spots = []; private array $tickets = [];
    private function __construct(private FeeStrategy $fee) {}
    public static function getInstance(FeeStrategy $s = null): self {
        return self::$instance ??= new self($s ?? new HourlyFeeStrategy());
    }
    public function addSpot(ParkingSpot $spot): void { $this->spots[$spot->getId()] = $spot; }
    public function park(Vehicle $v): Ticket {
        foreach ($this->spots as $spot) {
            if ($spot->canFit($v)) {
                $spot->park($v);
                $t = new Ticket(uniqid('T_'), $spot->getId(), $v, new \DateTimeImmutable());
                $this->tickets[$t->id] = $t;
                return $t;
            }
        }
        throw new \RuntimeException("Full");
    }
    public function leave(string $tid): float {
        $t = $this->tickets[$tid];
        $this->spots[$t->spotId]->remove();
        unset($this->tickets[$tid]);
        return $this->fee->calculate($t, new \DateTimeImmutable());
    }
}
```

### 2. Elevator (State Pattern)

```php
<?php
interface ElevatorState { public function request(Elevator $e, int $floor): void; }
class IdleState implements ElevatorState {
    public function request(Elevator $e, int $floor): void {
        if ($floor > $e->getFloor()) $e->setState(new MovingUpState());
        elseif ($floor < $e->getFloor()) $e->setState(new MovingDownState());
        $e->addRequest($floor);
    }
}
class MovingUpState implements ElevatorState {
    public function request(Elevator $e, int $floor): void { $e->addRequest($floor); }
}
class MovingDownState implements ElevatorState {
    public function request(Elevator $e, int $floor): void { $e->addRequest($floor); }
}
class Elevator {
    private int $floor = 0; private ElevatorState $state;
    private array $upQueue = []; private array $downQueue = [];
    public function __construct(public readonly int $id) { $this->state = new IdleState(); }
    public function getFloor(): int { return $this->floor; }
    public function setState(ElevatorState $s): void { $this->state = $s; }
    public function request(int $f): void { $this->state->request($this, $f); }
    public function addRequest(int $f): void {
        if ($f > $this->floor) { $this->upQueue[] = $f; sort($this->upQueue); }
        elseif ($f < $this->floor) { $this->downQueue[] = $f; rsort($this->downQueue); }
    }
    public function step(): void {
        if ($this->state instanceof MovingUpState && $this->upQueue) {
            $this->floor++;
            if ($this->floor === $this->upQueue[0]) array_shift($this->upQueue);
            if (!$this->upQueue) $this->state = $this->downQueue ? new MovingDownState() : new IdleState();
        } elseif ($this->state instanceof MovingDownState && $this->downQueue) {
            $this->floor--;
            if ($this->floor === $this->downQueue[0]) array_shift($this->downQueue);
            if (!$this->downQueue) $this->state = $this->upQueue ? new MovingUpState() : new IdleState();
        }
    }
}
```

### 3. Vending Machine (State)

```php
<?php
class Product { public function __construct(public string $code, public float $price, public int $stock) {} }

interface VMState {
    public function insertCoin(VendingMachine $vm, float $a): void;
    public function select(VendingMachine $vm, string $code): void;
    public function dispense(VendingMachine $vm): Product;
}
class IdleVMState implements VMState {
    public function insertCoin(VendingMachine $vm, float $a): void { $vm->addBalance($a); $vm->setState(new HasMoneyState()); }
    public function select(VendingMachine $vm, string $c): void { throw new \RuntimeException("Insert coin"); }
    public function dispense(VendingMachine $vm): Product { throw new \RuntimeException("No selection"); }
}
class HasMoneyState implements VMState {
    public function insertCoin(VendingMachine $vm, float $a): void { $vm->addBalance($a); }
    public function select(VendingMachine $vm, string $c): void {
        $p = $vm->getProduct($c);
        if ($p->stock <= 0) throw new \RuntimeException("Out of stock");
        if ($vm->getBalance() < $p->price) throw new \RuntimeException("Insufficient");
        $vm->setSelected($p); $vm->setState(new DispensingState());
    }
    public function dispense(VendingMachine $vm): Product { throw new \RuntimeException("Select"); }
}
class DispensingState implements VMState {
    public function insertCoin(VendingMachine $vm, float $a): void { throw new \RuntimeException("Busy"); }
    public function select(VendingMachine $vm, string $c): void { throw new \RuntimeException("Busy"); }
    public function dispense(VendingMachine $vm): Product {
        $p = $vm->getSelected(); $p->stock--; $vm->deduct($p->price);
        $vm->resetBalance(); $vm->setState(new IdleVMState());
        return $p;
    }
}
class VendingMachine {
    private array $products = []; private float $balance = 0;
    private ?Product $selected = null; private VMState $state;
    public function __construct() { $this->state = new IdleVMState(); }
    public function load(Product $p): void { $this->products[$p->code] = $p; }
    public function getProduct(string $c): Product { return $this->products[$c]; }
    public function setState(VMState $s): void { $this->state = $s; }
    public function getBalance(): float { return $this->balance; }
    public function addBalance(float $a): void { $this->balance += $a; }
    public function deduct(float $a): void { $this->balance -= $a; }
    public function resetBalance(): void { $this->balance = 0; }
    public function setSelected(Product $p): void { $this->selected = $p; }
    public function getSelected(): Product { return $this->selected; }
    public function insertCoin(float $a): void { $this->state->insertCoin($this, $a); }
    public function select(string $c): void { $this->state->select($this, $c); }
    public function dispense(): Product { return $this->state->dispense($this); }
}
```

### 4. Tic-Tac-Toe

```php
<?php
enum Mark: string { case X='X'; case O='O'; case Empty='-'; }
class Player { public function __construct(public string $name, public Mark $mark) {} }

class Board {
    private array $grid;
    public function __construct(public readonly int $n = 3) {
        $this->grid = array_fill(0, $n, array_fill(0, $n, Mark::Empty));
    }
    public function place(int $r, int $c, Mark $m): void {
        if ($this->grid[$r][$c] !== Mark::Empty) throw new \RuntimeException("Taken");
        $this->grid[$r][$c] = $m;
    }
    public function hasWinner(Mark $m): bool {
        for ($i = 0; $i < $this->n; $i++) {
            $rw = true; $cw = true;
            for ($j = 0; $j < $this->n; $j++) {
                if ($this->grid[$i][$j] !== $m) $rw = false;
                if ($this->grid[$j][$i] !== $m) $cw = false;
            }
            if ($rw || $cw) return true;
        }
        $d1 = true; $d2 = true;
        for ($i = 0; $i < $this->n; $i++) {
            if ($this->grid[$i][$i] !== $m) $d1 = false;
            if ($this->grid[$i][$this->n-1-$i] !== $m) $d2 = false;
        }
        return $d1 || $d2;
    }
}

class TicTacToe {
    private Board $board; private int $turn = 0; private array $players;
    public function __construct(Player $p1, Player $p2) {
        $this->board = new Board(); $this->players = [$p1, $p2];
    }
    public function move(int $r, int $c): ?Player {
        $p = $this->players[$this->turn];
        $this->board->place($r, $c, $p->mark);
        if ($this->board->hasWinner($p->mark)) return $p;
        $this->turn = 1 - $this->turn;
        return null;
    }
}
```

### 5. Chess Piece Hierarchy

```php
<?php
enum Color: string { case White='w'; case Black='b'; }
class Position { public function __construct(public int $row, public int $col) {} }

abstract class Piece {
    public function __construct(public Color $color, public Position $pos) {}
    abstract public function canMove(Position $to, ChessBoard $board): bool;
}
class Pawn extends Piece {
    public function canMove(Position $to, ChessBoard $b): bool {
        $dir = $this->color === Color::White ? 1 : -1;
        return $to->col === $this->pos->col && $to->row - $this->pos->row === $dir && $b->isEmpty($to);
    }
}
class Knight extends Piece {
    public function canMove(Position $to, ChessBoard $b): bool {
        $dr = abs($to->row - $this->pos->row); $dc = abs($to->col - $this->pos->col);
        return ($dr === 2 && $dc === 1) || ($dr === 1 && $dc === 2);
    }
}
// Rook, Bishop, Queen, King similar - path-based validation.
```

## Real-World Nümunələr

- **Parking Lot** - SpotHero, ParkWhiz commercial parking apps.
- **Elevator** - Otis, KONE smart elevator scheduling.
- **Vending Machine** - Coca-Cola Freestyle - state machines.
- **ATM** - Banking: Idle → CardInserted → Authenticated → Transaction.
- **Chess** - Chess.com, Lichess engines use OOD for movement validation.

## Praktik Tapşırıqlar

### 1. Parking Lot-da Strategy Pattern niyə?

**Cavab:** Fee calculation müxtəlif ola bilər - hourly, daily, flat, weekend. Strategy Pattern alqoritmləri runtime-da dəyişdirməyə imkan verir. `FeeStrategy` interface + `HourlyFeeStrategy`, `DailyFeeStrategy` - `ParkingLot` class-ına toxunmadan (Open/Closed Principle).

### 2. Elevator sistemində State Pattern-in faydası?

**Cavab:** Davranış state-ə görə dəyişir: `IdleState` istənilən yön, `MovingUp` yalnız yuxarı request-lər. State Pattern bu davranışları ayrı class-larda encapsulate edir, nəhəng `if-else` zəncirini aradan qaldırır. Yeni state (`MaintenanceState`) əlavə etmək asandır.

### 3. Vending Machine Singleton olmalıdırmı?

**Cavab:** Ümumiyyətlə **yox**. Bir binada bir neçə vending machine ola bilər. Singleton yalnız həqiqətən tək instance lazım olduqda (`ParkingLotManager`) tətbiq edilir. Vending Machine üçün **Factory Pattern** daha uyğundur.

### 4. Chess-də inheritance və composition-u necə balanslaşdırırsan?

**Cavab:**
- **Inheritance** piece növləri üçün (`Pawn extends Piece`) - "is-a".
- **Composition** movement rules üçün: `Piece` içində `MovementStrategy` ola bilər.
- Simple chess üçün override məqbuldur, mürəkkəb variant-lar (en passant, promotion) üçün Strategy Pattern.
- **Composition over inheritance** - çevik dizayn üçün.

### 5. ATM-də hansı design pattern-lər?

**Cavab:**
- **State**: Idle, CardInserted, PINEntered, TransactionSelected, Dispensing.
- **Command**: Withdraw, Deposit, CheckBalance ayrı `Command` obyekt.
- **Chain of Responsibility**: Transaction validation pipeline.
- **Facade**: `ATMFacade` external banking system ilə simple interface.
- **Observer**: Transaction log-ları subscriber-lərə.

### 6. Library System-də kitab, üzv və loan əlaqəsi?

**Cavab:**
```php
class Book { /* title, author, ISBN */ }
class BookItem extends Book { /* barcode, status */ } // physical copy
class Member { /* id, loans[] */ }
class Loan { private Member $m; private BookItem $i; private DateTime $due; }
```
- **Aggregation**: Member has Loans (ayrı yaşaya bilər).
- **Composition**: Book has BookItems.
- **Inheritance**: `Member` → `Student`, `Faculty` (fərqli limit).

### 7. Tic-Tac-Toe NxN üçün necə ümumiləşdirilir?

**Cavab:** `Board` class `n` parametri qəbul edir, loops `for ($i=0; $i<$n; $i++)` işləyir. Connect-K üçün `checkSequence(int $k)` helper əlavə. Performance: hər move-dan sonra bütün board əvəzinə yalnız sonuncu move ətrafını yoxla (O(n)).

### 8. Vending Machine-də concurrency?

**Cavab:**
- **Pessimistic lock** - `SELECT ... FOR UPDATE` stock row.
- **Optimistic lock** - Version number ilə stock update.
- **Atomic** - Redis `DECR` stock counter.
- **State lock** - `DispensingState`-də yeni selection qəbul etmir.

### 9. OOD interview-da vaxt bölgüsü?

**Cavab:**
1. **5 dəq** requirement clarification.
2. **5 dəq** use case list.
3. **10 dəq** class diagram + relationships.
4. **15 dəq** core flow kod yaz.
5. **5 dəq** design pattern justify.
6. **5 dəq** extensibility müzakirə.

### 10. Chess "Check/Checkmate" hara yerləşir?

**Cavab:** `ChessBoard`-da **yox** (SRP pozular). Ayrı `CheckDetector` service:
```php
class CheckDetector {
    public function isInCheck(ChessBoard $b, Color $c): bool;
    public function isCheckmate(ChessBoard $b, Color $c): bool;
}
```
**Separation of Concerns** - board data saxlayır, detector məntiq.

## Praktik Baxış

1. **Requirement-ləri aydınlaşdır** - Fərziyyələrlə başlama.
2. **Interface vs Abstract class** - Contract vs partial implementation.
3. **Composition > Inheritance** - Dərin tree-lərdən qaç.
4. **SOLID** - Hər class-da yoxla.
5. **Enum** - Magic string əvəzinə (PHP 8.1+).
6. **Immutable** - Value obyektlər `readonly`.
7. **Early validation** - Constructor-da invalid state-ə icazə vermə.
8. **Thread safety** - Shared state üçün lock və ya atomic.
9. **Testability** - Dependency Injection.
10. **Design pattern over-engineering** - Lazım olanda.
11. **UML diagram** - Interview zamanı whiteboard.
12. **Extensibility vurğula** - "Yeni feature necə əlavə olunar?"


## Əlaqəli Mövzular

- [Microservices](10-microservices.md) — servis sərhədi OOD ilə müəyyən olunur
- [Database Design](09-database-design.md) — domain model ilə DB şeması
- [API Design](55-api-design-patterns.md) — OOD prinsipləri API-ə tətbiqi
- [Auth](14-authentication-authorization.md) — role/permission OOD modeli
- [E-Commerce Design](24-e-commerce-design.md) — domain model nümunəsi
