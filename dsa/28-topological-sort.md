# Topological Sort (Topoloji Siralama)

## Konsept (Concept)

Topological sort DAG-da (Directed Acyclic Graph) vertex-leri ele siralar ki, her edge (u, v) ucun u v-den evvel gelir. Yalniz DAG ucun mumkundur (cycle varsa, topological order yoxdur).

```
DAG:     A -> B -> D
         |         ^
         v         |
         C --------+

Topological Orders (bir necesi ola biler):
  [A, B, C, D]
  [A, C, B, D]

Yalnislar (D B-den evvel gele bilmez):
  [A, D, B, C]  ✗

Real misal - Course Prerequisites:
  Fizika -> Riyaziyyat -> Analiz -> Differensial
  Fizika -> Mexanika
  Sira: [Fizika, Riyaziyyat, Mexanika, Analiz, Differensial]
```

### Iki yanasmaq:
1. **Kahn's Algorithm** (BFS-based): In-degree 0 olan node-lardan basla
2. **DFS-based**: DFS finish time-a gore reverse sirala

## Nece Isleyir? (How does it work?)

### Kahn's Algorithm (BFS):
```
Graph: A->B, A->C, B->D, C->D

In-degrees: A=0, B=1, C=1, D=2

Step 1: Queue = [A] (in-degree 0)
Step 2: Process A, B in-degree 0, C in-degree 0
        Queue = [B, C]
Step 3: Process B, D in-degree 1
        Queue = [C]
Step 4: Process C, D in-degree 0
        Queue = [D]
Step 5: Process D
        Queue = []

Result: [A, B, C, D]
Processed count = 4 = total vertices -> cycle yoxdur ✓
```

### DFS-based:
```
DFS from A:
  Visit A -> Visit B -> Visit D -> D finish (push D)
          -> B finish (push B)
  Visit A -> Visit C -> C finish (push C)
  A finish (push A)

Finish stack: [D, B, C, A]
Reverse: [A, C, B, D] -> topological order
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Kahn's Algorithm (BFS-based Topological Sort)
 * Time: O(V + E), Space: O(V)
 */
function topologicalSortKahn(array $graph): array
{
    // In-degree hesabla
    $inDegree = [];
    foreach (array_keys($graph) as $v) {
        $inDegree[$v] = 0;
    }
    foreach ($graph as $u => $neighbors) {
        foreach ($neighbors as $v) {
            $inDegree[$v]++;
        }
    }

    // In-degree 0 olanlari queue-ye elave et
    $queue = new SplQueue();
    foreach ($inDegree as $v => $degree) {
        if ($degree === 0) {
            $queue->enqueue($v);
        }
    }

    $result = [];
    while (!$queue->isEmpty()) {
        $u = $queue->dequeue();
        $result[] = $u;

        foreach ($graph[$u] as $v) {
            $inDegree[$v]--;
            if ($inDegree[$v] === 0) {
                $queue->enqueue($v);
            }
        }
    }

    // Cycle yoxlamasi
    if (count($result) !== count($graph)) {
        return []; // Cycle var, topological sort mumkun deyil
    }

    return $result;
}

/**
 * DFS-based Topological Sort
 * Time: O(V + E), Space: O(V)
 */
function topologicalSortDFS(array $graph): array
{
    $visited = [];
    $stack = [];
    $inProgress = []; // cycle detection ucun

    foreach (array_keys($graph) as $v) {
        if (!isset($visited[$v])) {
            if (!dfsTopo($graph, $v, $visited, $inProgress, $stack)) {
                return []; // Cycle tapildi
            }
        }
    }

    return array_reverse($stack);
}

function dfsTopo(array $graph, string $v, array &$visited, array &$inProgress, array &$stack): bool
{
    $inProgress[$v] = true;
    $visited[$v] = true;

    foreach ($graph[$v] ?? [] as $next) {
        if (isset($inProgress[$next])) {
            return false; // Cycle!
        }
        if (!isset($visited[$next])) {
            if (!dfsTopo($graph, $next, $visited, $inProgress, $stack)) {
                return false;
            }
        }
    }

    unset($inProgress[$v]);
    $stack[] = $v;
    return true;
}

/**
 * Course Schedule (LeetCode 207)
 * Butun kurslari bitirmek mumkundurmu?
 * Time: O(V + E), Space: O(V + E)
 */
function canFinish(int $numCourses, array $prerequisites): bool
{
    $graph = array_fill(0, $numCourses, []);
    $inDegree = array_fill(0, $numCourses, 0);

    foreach ($prerequisites as [$course, $prereq]) {
        $graph[$prereq][] = $course;
        $inDegree[$course]++;
    }

    $queue = new SplQueue();
    for ($i = 0; $i < $numCourses; $i++) {
        if ($inDegree[$i] === 0) $queue->enqueue($i);
    }

    $count = 0;
    while (!$queue->isEmpty()) {
        $u = $queue->dequeue();
        $count++;
        foreach ($graph[$u] as $v) {
            if (--$inDegree[$v] === 0) {
                $queue->enqueue($v);
            }
        }
    }

    return $count === $numCourses;
}

/**
 * Course Schedule II (LeetCode 210)
 * Kurslarin siralamasi
 * Time: O(V + E), Space: O(V + E)
 */
function findOrder(int $numCourses, array $prerequisites): array
{
    $graph = array_fill(0, $numCourses, []);
    $inDegree = array_fill(0, $numCourses, 0);

    foreach ($prerequisites as [$course, $prereq]) {
        $graph[$prereq][] = $course;
        $inDegree[$course]++;
    }

    $queue = new SplQueue();
    for ($i = 0; $i < $numCourses; $i++) {
        if ($inDegree[$i] === 0) $queue->enqueue($i);
    }

    $order = [];
    while (!$queue->isEmpty()) {
        $u = $queue->dequeue();
        $order[] = $u;
        foreach ($graph[$u] as $v) {
            if (--$inDegree[$v] === 0) {
                $queue->enqueue($v);
            }
        }
    }

    return count($order) === $numCourses ? $order : [];
}

/**
 * Alien Dictionary (LeetCode 269)
 * Yad dilde herf siralamasini tap
 * Time: O(C) C = total characters, Space: O(1) - 26 herf max
 */
function alienOrder(array $words): string
{
    $graph = [];
    $inDegree = [];

    // Butun herleri initialize et
    foreach ($words as $word) {
        for ($i = 0; $i < strlen($word); $i++) {
            if (!isset($graph[$word[$i]])) {
                $graph[$word[$i]] = [];
                $inDegree[$word[$i]] = 0;
            }
        }
    }

    // Qonsu sozleri muqayise et
    for ($i = 0; $i < count($words) - 1; $i++) {
        $w1 = $words[$i];
        $w2 = $words[$i + 1];
        $minLen = min(strlen($w1), strlen($w2));

        // "abc" > "ab" kimi halda invalid
        if (strlen($w1) > strlen($w2) && substr($w1, 0, $minLen) === substr($w2, 0, $minLen)) {
            return '';
        }

        for ($j = 0; $j < $minLen; $j++) {
            if ($w1[$j] !== $w2[$j]) {
                if (!in_array($w2[$j], $graph[$w1[$j]])) {
                    $graph[$w1[$j]][] = $w2[$j];
                    $inDegree[$w2[$j]]++;
                }
                break;
            }
        }
    }

    // Kahn's algorithm
    $queue = new SplQueue();
    foreach ($inDegree as $ch => $deg) {
        if ($deg === 0) $queue->enqueue($ch);
    }

    $result = '';
    while (!$queue->isEmpty()) {
        $ch = $queue->dequeue();
        $result .= $ch;
        foreach ($graph[$ch] as $next) {
            if (--$inDegree[$next] === 0) {
                $queue->enqueue($next);
            }
        }
    }

    return strlen($result) === count($graph) ? $result : '';
}

/**
 * All Topological Sorts (backtracking)
 * Time: O(V! * V), Space: O(V)
 */
function allTopologicalSorts(array $graph): array
{
    $inDegree = [];
    foreach (array_keys($graph) as $v) $inDegree[$v] = 0;
    foreach ($graph as $neighbors) {
        foreach ($neighbors as $v) $inDegree[$v]++;
    }

    $result = [];
    $visited = [];
    $current = [];
    allTopoHelper($graph, $inDegree, $visited, $current, $result);
    return $result;
}

function allTopoHelper(array &$graph, array &$inDegree, array &$visited, array &$current, array &$result): void
{
    $found = false;
    foreach (array_keys($graph) as $v) {
        if (!isset($visited[$v]) && $inDegree[$v] === 0) {
            $found = true;
            $visited[$v] = true;
            $current[] = $v;

            foreach ($graph[$v] as $next) $inDegree[$next]--;
            allTopoHelper($graph, $inDegree, $visited, $current, $result);
            foreach ($graph[$v] as $next) $inDegree[$next]++;

            array_pop($current);
            unset($visited[$v]);
        }
    }

    if (!$found) {
        $result[] = $current;
    }
}

/**
 * Build system dependency resolver
 */
function buildOrder(array $tasks, array $dependencies): array
{
    $graph = [];
    foreach ($tasks as $t) $graph[$t] = [];
    foreach ($dependencies as [$before, $after]) {
        $graph[$before][] = $after;
    }
    return topologicalSortKahn($graph);
}

// --- Test ---
$graph = [
    'A' => ['B', 'C'],
    'B' => ['D'],
    'C' => ['D'],
    'D' => [],
];

echo "Kahn's: " . implode(' -> ', topologicalSortKahn($graph)) . "\n";
echo "DFS:    " . implode(' -> ', topologicalSortDFS($graph)) . "\n";

echo "Can finish 4 courses: " . (canFinish(4, [[1,0],[2,0],[3,1],[3,2]]) ? 'yes' : 'no') . "\n";
echo "Course order: " . implode(', ', findOrder(4, [[1,0],[2,0],[3,1],[3,2]])) . "\n";

$order = buildOrder(
    ['compile', 'link', 'test', 'deploy', 'clean'],
    [['clean', 'compile'], ['compile', 'link'], ['link', 'test'], ['test', 'deploy']]
);
echo "Build order: " . implode(' -> ', $order) . "\n";

$allOrders = allTopologicalSorts($graph);
echo "All topological orders: " . count($allOrders) . "\n";
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Algoritm | Time | Space |
|----------|------|-------|
| Kahn's (BFS) | O(V + E) | O(V) |
| DFS-based | O(V + E) | O(V) |
| All topo sorts | O(V! * V) | O(V) |
| Cycle detection | O(V + E) | O(V) |

## Tipik Meseler (Common Problems)

### 1. Parallel Courses (minimum semester)
```php
<?php
function minimumSemesters(int $n, array $relations): int
{
    $graph = array_fill(1, $n, []);
    $inDegree = array_fill(1, $n, 0);

    foreach ($relations as [$prev, $next]) {
        $graph[$prev][] = $next;
        $inDegree[$next]++;
    }

    $queue = new SplQueue();
    for ($i = 1; $i <= $n; $i++) {
        if ($inDegree[$i] === 0) $queue->enqueue($i);
    }

    $semesters = 0;
    $studied = 0;

    while (!$queue->isEmpty()) {
        $semesters++;
        $size = $queue->count();
        for ($i = 0; $i < $size; $i++) {
            $course = $queue->dequeue();
            $studied++;
            foreach ($graph[$course] as $next) {
                if (--$inDegree[$next] === 0) $queue->enqueue($next);
            }
        }
    }

    return $studied === $n ? $semesters : -1;
}
```

### 2. Task Scheduler with Dependencies
```php
<?php
function taskScheduler(array $tasks, array $deps, array $durations): int
{
    $graph = [];
    foreach ($tasks as $t) $graph[$t] = [];
    $inDegree = array_fill_keys($tasks, 0);

    foreach ($deps as [$before, $after]) {
        $graph[$before][] = $after;
        $inDegree[$after]++;
    }

    $earliest = array_fill_keys($tasks, 0);
    $queue = new SplQueue();

    foreach ($inDegree as $t => $d) {
        if ($d === 0) $queue->enqueue($t);
    }

    while (!$queue->isEmpty()) {
        $t = $queue->dequeue();
        foreach ($graph[$t] as $next) {
            $earliest[$next] = max($earliest[$next], $earliest[$t] + $durations[$t]);
            if (--$inDegree[$next] === 0) $queue->enqueue($next);
        }
    }

    $maxTime = 0;
    foreach ($tasks as $t) {
        $maxTime = max($maxTime, $earliest[$t] + $durations[$t]);
    }

    return $maxTime;
}
```

## Interview Suallari

1. **Topological sort ne vaxt istifade olunur?**
   - Task/job scheduling, build systems, course prerequisites
   - Dependency resolution (npm, composer, pip)
   - Compiler instruction ordering

2. **Cycle varsa ne olur?**
   - Topological sort mumkun deyil
   - Kahn's: processed count < total vertices
   - DFS: back edge tapilirsa cycle var

3. **Kahn's vs DFS-based ferqi?**
   - Kahn's: BFS-based, level-by-level, parallel execution detect edir
   - DFS: stack/recursion-based, daha sade implementasiya
   - Kahn's cycle detection daha aydindi

4. **Nece cox topological order ola biler?**
   - Worst case O(V!) - tamam musteqil vertex-ler
   - Minimum 1 (tam xetti dependency chain)

## PHP/Laravel ile Elaqe

- **Composer**: Paket dependency resolution topological sort istifade edir
- **Laravel migrations**: Migration execution order
- **Service providers**: Boot order dependencies
- **Webpack/Vite**: Asset bundling dependency order
- **CI/CD pipeline**: Job/step ordering
- **Database seeds**: Seed execution order (foreign key dependencies)
