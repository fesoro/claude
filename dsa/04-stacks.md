# Stacks (Yiginlar)

## Konsept (Concept)

Stack LIFO (Last In, First Out) prinsipi ile isleyen data strukturudur. Son elave olunan element ilk cixarilir -- bosgab yigini kimi dusun.

```
         +-----+
         |  5  |  <- top (push/pop buradan)
         +-----+
         |  4  |
         +-----+
         |  3  |
         +-----+
         |  2  |
         +-----+
         |  1  |
         +-----+

push(6):          pop():
  +-----+         +-----+
  |  6  | <- top  |  4  | <- top
  +-----+         +-----+
  |  5  |         |  3  |
  +-----+         +-----+
  |  4  |         |  2  |
  +-----+         +-----+
  ...              ...
```

## Nece Isleyir? (How does it work?)

Esas emeliyyatlar:
1. **push(x)** - Uste element elave et: O(1)
2. **pop()** - Ustten element cixar: O(1)
3. **peek/top()** - Ust elementi gos (silme): O(1)
4. **isEmpty()** - Boshdurmu yoxla: O(1)

Stack iki usulla realize oluna biler:
- **Array-based:** Massiv + top index
- **Linked-list-based:** Her node evvelki node-a isare edir

## Implementasiya (Implementation)

### Array-based Stack
```php
<?php
class Stack {
    private array $data = [];
    private int $top = -1;

    public function push(mixed $val): void {
        $this->data[++$this->top] = $val;
    }

    public function pop(): mixed {
        if ($this->isEmpty()) {
            throw new UnderflowException('Stack is empty');
        }
        $val = $this->data[$this->top];
        unset($this->data[$this->top--]);
        return $val;
    }

    public function peek(): mixed {
        if ($this->isEmpty()) {
            throw new UnderflowException('Stack is empty');
        }
        return $this->data[$this->top];
    }

    public function isEmpty(): bool {
        return $this->top === -1;
    }

    public function size(): int {
        return $this->top + 1;
    }
}
```

### PHP SplStack
```php
<?php
$stack = new SplStack();

$stack->push(1);
$stack->push(2);
$stack->push(3);

echo $stack->top();    // 3
echo $stack->pop();    // 3
echo $stack->count();  // 2

// SplStack iterate LIFO (reversed):
foreach ($stack as $val) {
    echo $val . " "; // 2 1
}
```

### Balanced Parentheses
```php
<?php
function isValid(string $s): bool {
    $stack = [];
    $map = [')' => '(', '}' => '{', ']' => '['];

    for ($i = 0; $i < strlen($s); $i++) {
        $char = $s[$i];

        if (in_array($char, ['(', '{', '['])) {
            $stack[] = $char;
        } else {
            if (empty($stack) || end($stack) !== $map[$char]) {
                return false;
            }
            array_pop($stack);
        }
    }

    return empty($stack);
}

// Misallar:
// "({[]})" -> true
// "({[}])" -> false
// "((("    -> false
```

### Expression Evaluation (Postfix)
```php
<?php
// Postfix (Reverse Polish Notation) hesablama
// "3 4 + 2 *" = (3 + 4) * 2 = 14
function evalPostfix(string $expression): int {
    $stack = [];
    $tokens = explode(' ', $expression);

    foreach ($tokens as $token) {
        if (is_numeric($token)) {
            $stack[] = (int)$token;
        } else {
            $b = array_pop($stack);
            $a = array_pop($stack);
            switch ($token) {
                case '+': $stack[] = $a + $b; break;
                case '-': $stack[] = $a - $b; break;
                case '*': $stack[] = $a * $b; break;
                case '/': $stack[] = intdiv($a, $b); break;
            }
        }
    }

    return end($stack);
}

// Infix -> Postfix (Shunting Yard Algorithm)
function infixToPostfix(string $expression): string {
    $output = [];
    $opStack = [];
    $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
    $tokens = str_split(preg_replace('/\s+/', '', $expression));

    foreach ($tokens as $token) {
        if (is_numeric($token)) {
            $output[] = $token;
        } elseif ($token === '(') {
            $opStack[] = $token;
        } elseif ($token === ')') {
            while (end($opStack) !== '(') {
                $output[] = array_pop($opStack);
            }
            array_pop($opStack); // '(' sil
        } else {
            while (!empty($opStack) && end($opStack) !== '(' &&
                   ($precedence[end($opStack)] ?? 0) >= ($precedence[$token] ?? 0)) {
                $output[] = array_pop($opStack);
            }
            $opStack[] = $token;
        }
    }

    while (!empty($opStack)) {
        $output[] = array_pop($opStack);
    }

    return implode(' ', $output);
}
```

### Min Stack
```php
<?php
// Her emeliyyat O(1) olan stack + minimum deyeri izlemek
class MinStack {
    private array $stack = [];
    private array $minStack = [];

    public function push(int $val): void {
        $this->stack[] = $val;
        if (empty($this->minStack) || $val <= end($this->minStack)) {
            $this->minStack[] = $val;
        }
    }

    public function pop(): void {
        $val = array_pop($this->stack);
        if ($val === end($this->minStack)) {
            array_pop($this->minStack);
        }
    }

    public function top(): int {
        return end($this->stack);
    }

    public function getMin(): int {
        return end($this->minStack);
    }
}

// Misal:
// push(5) -> stack=[5], min=[5]
// push(3) -> stack=[5,3], min=[5,3]
// push(7) -> stack=[5,3,7], min=[5,3]
// getMin() -> 3
// pop()   -> stack=[5,3], min=[5,3]
// pop()   -> stack=[5], min=[5]
// getMin() -> 5
```

### Monotonic Stack
```php
<?php
// Next Greater Element: her element ucun sagdaki ilk boyuk elementi tap
function nextGreaterElement(array $arr): array {
    $n = count($arr);
    $result = array_fill(0, $n, -1);
    $stack = []; // indeksleri saxlayir

    for ($i = 0; $i < $n; $i++) {
        while (!empty($stack) && $arr[end($stack)] < $arr[$i]) {
            $idx = array_pop($stack);
            $result[$idx] = $arr[$i];
        }
        $stack[] = $i;
    }

    return $result;
}

// Misal: [4, 5, 2, 10, 8]
// Cavab: [5, 10, 10, -1, -1]
//
// i=0: stack=[0(4)]
// i=1: 5>4, pop 0, result[0]=5. stack=[1(5)]
// i=2: 2<5, stack=[1(5), 2(2)]
// i=3: 10>2, pop 2, result[2]=10. 10>5, pop 1, result[1]=10. stack=[3(10)]
// i=4: 8<10, stack=[3(10), 4(8)]
```

### Undo/Redo sistemi
```php
<?php
class UndoRedoSystem {
    private array $undoStack = [];
    private array $redoStack = [];
    private string $current = '';

    public function type(string $text): void {
        $this->undoStack[] = $this->current;
        $this->current .= $text;
        $this->redoStack = []; // Redo temizle
    }

    public function undo(): void {
        if (!empty($this->undoStack)) {
            $this->redoStack[] = $this->current;
            $this->current = array_pop($this->undoStack);
        }
    }

    public function redo(): void {
        if (!empty($this->redoStack)) {
            $this->undoStack[] = $this->current;
            $this->current = array_pop($this->redoStack);
        }
    }

    public function getText(): string {
        return $this->current;
    }
}
```

### Stack ile DFS
```php
<?php
// Iterative DFS (recursion stack evezine explicit stack)
function dfsIterative(array $graph, int $start): array {
    $visited = [];
    $stack = [$start];
    $result = [];

    while (!empty($stack)) {
        $node = array_pop($stack);
        if (isset($visited[$node])) continue;

        $visited[$node] = true;
        $result[] = $node;

        foreach (array_reverse($graph[$node] ?? []) as $neighbor) {
            if (!isset($visited[$neighbor])) {
                $stack[] = $neighbor;
            }
        }
    }

    return $result;
}
```

## Vaxt ve Yaddas Murekkebliyi (Time & Space Complexity)

| Emeliyyat | Array-based | Linked-list-based |
|-----------|-------------|-------------------|
| push | O(1) amortized | O(1) |
| pop | O(1) | O(1) |
| peek/top | O(1) | O(1) |
| isEmpty | O(1) | O(1) |
| search | O(n) | O(n) |

Space: O(n)

## Tipik Meseleler (Common Problems)

### 1. Daily Temperatures
```php
<?php
function dailyTemperatures(array $temps): array {
    $n = count($temps);
    $result = array_fill(0, $n, 0);
    $stack = [];

    for ($i = 0; $i < $n; $i++) {
        while (!empty($stack) && $temps[end($stack)] < $temps[$i]) {
            $idx = array_pop($stack);
            $result[$idx] = $i - $idx;
        }
        $stack[] = $i;
    }

    return $result;
}
// [73,74,75,71,69,72,76,73] -> [1,1,4,2,1,1,0,0]
```

### 2. Largest Rectangle in Histogram
```php
<?php
function largestRectangle(array $heights): int {
    $stack = [];
    $maxArea = 0;
    $heights[] = 0; // Sentinel

    for ($i = 0; $i < count($heights); $i++) {
        while (!empty($stack) && $heights[end($stack)] > $heights[$i]) {
            $h = $heights[array_pop($stack)];
            $w = empty($stack) ? $i : $i - end($stack) - 1;
            $maxArea = max($maxArea, $h * $w);
        }
        $stack[] = $i;
    }

    return $maxArea;
}
```

### 3. Decode String: "3[a2[c]]" -> "accaccacc"
```php
<?php
function decodeString(string $s): string {
    $numStack = [];
    $strStack = [];
    $currentStr = '';
    $currentNum = 0;

    for ($i = 0; $i < strlen($s); $i++) {
        $ch = $s[$i];
        if (is_numeric($ch)) {
            $currentNum = $currentNum * 10 + (int)$ch;
        } elseif ($ch === '[') {
            $numStack[] = $currentNum;
            $strStack[] = $currentStr;
            $currentNum = 0;
            $currentStr = '';
        } elseif ($ch === ']') {
            $num = array_pop($numStack);
            $prev = array_pop($strStack);
            $currentStr = $prev . str_repeat($currentStr, $num);
        } else {
            $currentStr .= $ch;
        }
    }

    return $currentStr;
}
```

## Interview Suallari

**S: Stack ne vaxt istifade olunur?**
C: LIFO lazim olduqda: function call stack, undo sistemi, motten yoxlama (parentheses), DFS, expression evaluation, browser back button.

**S: Stack vs Queue ferqi?**
C: Stack LIFO (son giren, ilk cixir), Queue FIFO (ilk giren, ilk cixir). Stack DFS ucun, Queue BFS ucun istifade olunur.

**S: Monotonic stack nedir?**
C: Elementlerin artan ve ya azalan sirada saxlanildigi stack. "Next greater/smaller element" kimi meseleleri O(n)-de hell edir.

**S: Call stack ne ucun muhimdur?**
C: Her funksiya cagirisi stack frame elave edir. Recursion derinliyi stack overflow yarada biler. PHP default limit ~256KB (xdebug ile ~100 nesting).

## PHP/Laravel ile Elaqe

```php
<?php
// 1. PHP Call Stack - recursion ve function calls
function factorial(int $n): int {
    if ($n <= 1) return 1;
    return $n * factorial($n - 1);
    // Her cagiri stack frame yaradir
    // Stack overflow riski var (PHP ~1000-10000 derinlik)
}

// 2. Laravel Middleware Pipeline
// Stack kimi isleyir: her middleware novbetini cagrir,
// sonra geriye donus (response) terse isleyir
// Request:  Global -> Auth -> Throttle -> Controller
// Response: Controller -> Throttle -> Auth -> Global

// 3. SplStack istifadesi
$stack = new SplStack();
// SplDoublyLinkedList-in LIFO modudur

// 4. Error/Exception handling - stack trace
try {
    throw new Exception('Test');
} catch (Exception $e) {
    echo $e->getTraceAsString();
    // #0 file.php(10): functionA()
    // #1 file.php(5): functionB()
    // ... call stack gosterilir
}

// 5. Blade template inheritance - stack kimi isleyir
// @push('scripts')   -> stack-e elave
// @stack('scripts')  -> stack-i render
```
