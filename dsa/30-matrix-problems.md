# Matrix Problems (Senior)

## Konsept (Concept)

Matrix (2D array) bir neçe sıra və sütundan ibarət olan strukturdur. Matrix meseleleri adətən traversal (dolanma), rotasiya, axtarış və ada (island) tapma problemləri daxil edir.

```
Matrix 3x4:
[ 1,  2,  3,  4]
[ 5,  6,  7,  8]
[ 9, 10, 11, 12]

rows = 3, cols = 4
matrix[i][j] => i = sira, j = sutun
```

### Əsas anlayışlar:
1. **Dimensions**: rows (m), cols (n)
2. **Neighbors**: 4-directional (yuxari, asagi, sol, sag) və ya 8-directional (diagonal daxil)
3. **Boundary**: Matrisden kenara cixmamaq ucun `0 <= i < m && 0 <= j < n`
4. **In-place modification**: Elave yaddas istifade etmeden matrisi deyismek

### Tipik problem növləri:
- **Traversal**: Spiral, diagonal, zigzag
- **Rotation**: 90, 180, 270 derece
- **Search**: Sorted matrix, word search
- **Island problems**: DFS/BFS ile baglı komponentler tapmaq
- **Flood fill**: Rəng doldurma, connected region-ləri işaretlemek

## Necə İşləyir? (How does it work?)

### Spiral Order Traversal:
```
Matrix:
[1, 2, 3]
[4, 5, 6]
[7, 8, 9]

Spiral: 1 -> 2 -> 3 -> 6 -> 9 -> 8 -> 7 -> 4 -> 5

Boundaries:
top=0, bottom=2, left=0, right=2

Steps:
1. Left->Right: 1,2,3 (top sıra). top++
2. Top->Bottom: 6,9 (right sütun). right--
3. Right->Left: 8,7 (bottom sıra). bottom--
4. Bottom->Top: 4 (left sütun). left++
5. Left->Right: 5. Bitdi.
```

### Matrix Rotation (90 derece saat əqrəbi):
```
Original:        Transpose:       Reverse rows:
[1, 2, 3]        [1, 4, 7]        [7, 4, 1]
[4, 5, 6]   ->   [2, 5, 8]   ->   [8, 5, 2]
[7, 8, 9]        [3, 6, 9]        [9, 6, 3]

Addim 1: Transpose (matrix[i][j] = matrix[j][i])
Addim 2: Her sirani reverse et
```

### Search in Sorted Matrix:
```
Matrix (her sira sortlanmış, sira başları da sortlanmış):
[ 1,  4,  7, 11]
[ 2,  5,  8, 12]
[ 3,  6,  9, 16]
[10, 13, 14, 17]

Target = 5

Start from top-right: (0, 3) = 11
11 > 5 -> sola get (0, 2) = 7
7 > 5 -> sola get (0, 1) = 4
4 < 5 -> asagi get (1, 1) = 5 FOUND!
```

### Number of Islands (DFS):
```
Grid:
[1, 1, 0, 0]
[1, 0, 0, 1]
[0, 0, 1, 1]
[0, 0, 0, 0]

Islands: 2
- Island 1: (0,0),(0,1),(1,0)
- Island 2: (1,3),(2,2),(2,3)

Her 1 tapdigda DFS ile butun baglı hüceyrələri 0 et.
```

## Implementasiya (Implementation)

```php
<?php

class MatrixProblems
{
    // Spiral Order Traversal
    public function spiralOrder(array $matrix): array
    {
        if (empty($matrix)) return [];

        $result = [];
        $top = 0;
        $bottom = count($matrix) - 1;
        $left = 0;
        $right = count($matrix[0]) - 1;

        while ($top <= $bottom && $left <= $right) {
            // Left -> Right
            for ($j = $left; $j <= $right; $j++) {
                $result[] = $matrix[$top][$j];
            }
            $top++;

            // Top -> Bottom
            for ($i = $top; $i <= $bottom; $i++) {
                $result[] = $matrix[$i][$right];
            }
            $right--;

            // Right -> Left (yoxla ki sıra qalıb)
            if ($top <= $bottom) {
                for ($j = $right; $j >= $left; $j--) {
                    $result[] = $matrix[$bottom][$j];
                }
                $bottom--;
            }

            // Bottom -> Top (yoxla ki sütun qalıb)
            if ($left <= $right) {
                for ($i = $bottom; $i >= $top; $i--) {
                    $result[] = $matrix[$i][$left];
                }
                $left++;
            }
        }

        return $result;
    }

    // Rotate Matrix 90 derece (clockwise) - in-place
    public function rotate(array &$matrix): void
    {
        $n = count($matrix);

        // Addim 1: Transpose
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                [$matrix[$i][$j], $matrix[$j][$i]] = [$matrix[$j][$i], $matrix[$i][$j]];
            }
        }

        // Addim 2: Her sırayi reverse et
        for ($i = 0; $i < $n; $i++) {
            $matrix[$i] = array_reverse($matrix[$i]);
        }
    }

    // Search in 2D sorted matrix (her sira ve ilk sutun sortlanmis)
    public function searchMatrix(array $matrix, int $target): bool
    {
        if (empty($matrix)) return false;

        $m = count($matrix);
        $n = count($matrix[0]);
        $i = 0;
        $j = $n - 1; // top-right küncden başla

        while ($i < $m && $j >= 0) {
            if ($matrix[$i][$j] === $target) {
                return true;
            } elseif ($matrix[$i][$j] > $target) {
                $j--; // sola
            } else {
                $i++; // asagi
            }
        }

        return false;
    }

    // Number of Islands (DFS)
    public function numIslands(array $grid): int
    {
        if (empty($grid)) return 0;

        $count = 0;
        $m = count($grid);
        $n = count($grid[0]);

        for ($i = 0; $i < $m; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($grid[$i][$j] === '1') {
                    $count++;
                    $this->dfsIsland($grid, $i, $j, $m, $n);
                }
            }
        }

        return $count;
    }

    private function dfsIsland(array &$grid, int $i, int $j, int $m, int $n): void
    {
        if ($i < 0 || $i >= $m || $j < 0 || $j >= $n || $grid[$i][$j] !== '1') {
            return;
        }

        $grid[$i][$j] = '0'; // ziyaret edildi işarələ

        $this->dfsIsland($grid, $i + 1, $j, $m, $n);
        $this->dfsIsland($grid, $i - 1, $j, $m, $n);
        $this->dfsIsland($grid, $i, $j + 1, $m, $n);
        $this->dfsIsland($grid, $i, $j - 1, $m, $n);
    }

    // Max Area of Island
    public function maxAreaOfIsland(array $grid): int
    {
        if (empty($grid)) return 0;

        $max = 0;
        $m = count($grid);
        $n = count($grid[0]);

        for ($i = 0; $i < $m; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($grid[$i][$j] === 1) {
                    $area = $this->dfsArea($grid, $i, $j, $m, $n);
                    $max = max($max, $area);
                }
            }
        }

        return $max;
    }

    private function dfsArea(array &$grid, int $i, int $j, int $m, int $n): int
    {
        if ($i < 0 || $i >= $m || $j < 0 || $j >= $n || $grid[$i][$j] !== 1) {
            return 0;
        }

        $grid[$i][$j] = 0;

        return 1
            + $this->dfsArea($grid, $i + 1, $j, $m, $n)
            + $this->dfsArea($grid, $i - 1, $j, $m, $n)
            + $this->dfsArea($grid, $i, $j + 1, $m, $n)
            + $this->dfsArea($grid, $i, $j - 1, $m, $n);
    }

    // Flood Fill
    public function floodFill(array $image, int $sr, int $sc, int $newColor): array
    {
        $oldColor = $image[$sr][$sc];
        if ($oldColor === $newColor) return $image;

        $this->fill($image, $sr, $sc, $oldColor, $newColor);
        return $image;
    }

    private function fill(array &$image, int $i, int $j, int $oldColor, int $newColor): void
    {
        if ($i < 0 || $i >= count($image) || $j < 0 || $j >= count($image[0])) return;
        if ($image[$i][$j] !== $oldColor) return;

        $image[$i][$j] = $newColor;

        $this->fill($image, $i + 1, $j, $oldColor, $newColor);
        $this->fill($image, $i - 1, $j, $oldColor, $newColor);
        $this->fill($image, $i, $j + 1, $oldColor, $newColor);
        $this->fill($image, $i, $j - 1, $oldColor, $newColor);
    }

    // Set Matrix Zeroes - 0 tapılan sıra ve sutunu sifirla
    public function setZeroes(array &$matrix): void
    {
        $m = count($matrix);
        $n = count($matrix[0]);
        $firstRowZero = false;
        $firstColZero = false;

        // Birinci sira/sutunu yoxla
        for ($j = 0; $j < $n; $j++) {
            if ($matrix[0][$j] === 0) $firstRowZero = true;
        }
        for ($i = 0; $i < $m; $i++) {
            if ($matrix[$i][0] === 0) $firstColZero = true;
        }

        // Birinci sira/sutunu marker kimi istifade et
        for ($i = 1; $i < $m; $i++) {
            for ($j = 1; $j < $n; $j++) {
                if ($matrix[$i][$j] === 0) {
                    $matrix[$i][0] = 0;
                    $matrix[0][$j] = 0;
                }
            }
        }

        // Marker-lere əsasen sifirla
        for ($i = 1; $i < $m; $i++) {
            for ($j = 1; $j < $n; $j++) {
                if ($matrix[$i][0] === 0 || $matrix[0][$j] === 0) {
                    $matrix[$i][$j] = 0;
                }
            }
        }

        if ($firstRowZero) {
            for ($j = 0; $j < $n; $j++) $matrix[0][$j] = 0;
        }
        if ($firstColZero) {
            for ($i = 0; $i < $m; $i++) $matrix[$i][0] = 0;
        }
    }
}

// Istifade
$mp = new MatrixProblems();

$matrix = [[1,2,3],[4,5,6],[7,8,9]];
print_r($mp->spiralOrder($matrix)); // [1,2,3,6,9,8,7,4,5]

$mp->rotate($matrix);
print_r($matrix); // [[7,4,1],[8,5,2],[9,6,3]]

$grid = [
    ['1','1','0','0'],
    ['1','0','0','1'],
    ['0','0','1','1'],
    ['0','0','0','0']
];
echo $mp->numIslands($grid); // 2
```

## Vaxt və Yaddaş Mürəkkəbliyi (Time & Space Complexity)

| Əməliyyat | Time | Space |
|-----------|------|-------|
| Spiral Order | O(m*n) | O(1) extra |
| Rotate Matrix | O(n^2) | O(1) |
| Search Sorted Matrix | O(m+n) | O(1) |
| Number of Islands | O(m*n) | O(m*n) recursion |
| Max Area of Island | O(m*n) | O(m*n) |
| Flood Fill | O(m*n) | O(m*n) |
| Set Matrix Zeroes | O(m*n) | O(1) optimal |

## Tipik Məsələlər (Common Problems)

### 1. Spiral Matrix II (1-den n*n-e qeder spiral dolduş)
```php
public function generateMatrix(int $n): array
{
    $matrix = array_fill(0, $n, array_fill(0, $n, 0));
    $top = 0; $bottom = $n - 1;
    $left = 0; $right = $n - 1;
    $num = 1;

    while ($top <= $bottom && $left <= $right) {
        for ($j = $left; $j <= $right; $j++) $matrix[$top][$j] = $num++;
        $top++;
        for ($i = $top; $i <= $bottom; $i++) $matrix[$i][$right] = $num++;
        $right--;
        for ($j = $right; $j >= $left; $j--) $matrix[$bottom][$j] = $num++;
        $bottom--;
        for ($i = $bottom; $i >= $top; $i--) $matrix[$i][$left] = $num++;
        $left++;
    }

    return $matrix;
}
```

### 2. Word Search (DFS + backtracking)
```php
public function exist(array $board, string $word): bool
{
    $m = count($board);
    $n = count($board[0]);

    for ($i = 0; $i < $m; $i++) {
        for ($j = 0; $j < $n; $j++) {
            if ($this->dfsWord($board, $word, 0, $i, $j)) return true;
        }
    }
    return false;
}

private function dfsWord(array &$board, string $word, int $idx, int $i, int $j): bool
{
    if ($idx === strlen($word)) return true;
    if ($i < 0 || $i >= count($board) || $j < 0 || $j >= count($board[0])) return false;
    if ($board[$i][$j] !== $word[$idx]) return false;

    $temp = $board[$i][$j];
    $board[$i][$j] = '#';

    $found = $this->dfsWord($board, $word, $idx+1, $i+1, $j)
          || $this->dfsWord($board, $word, $idx+1, $i-1, $j)
          || $this->dfsWord($board, $word, $idx+1, $i, $j+1)
          || $this->dfsWord($board, $word, $idx+1, $i, $j-1);

    $board[$i][$j] = $temp;
    return $found;
}
```

### 3. Number of Islands (BFS versiyasi)
```php
public function numIslandsBFS(array $grid): int
{
    $count = 0;
    $m = count($grid);
    $n = count($grid[0]);

    for ($i = 0; $i < $m; $i++) {
        for ($j = 0; $j < $n; $j++) {
            if ($grid[$i][$j] === '1') {
                $count++;
                $queue = new SplQueue();
                $queue->enqueue([$i, $j]);
                $grid[$i][$j] = '0';

                while (!$queue->isEmpty()) {
                    [$r, $c] = $queue->dequeue();
                    foreach ([[1,0],[-1,0],[0,1],[0,-1]] as [$dr, $dc]) {
                        $nr = $r + $dr; $nc = $c + $dc;
                        if ($nr >= 0 && $nr < $m && $nc >= 0 && $nc < $n && $grid[$nr][$nc] === '1') {
                            $queue->enqueue([$nr, $nc]);
                            $grid[$nr][$nc] = '0';
                        }
                    }
                }
            }
        }
    }
    return $count;
}
```

### 4. Diagonal Traverse
```php
public function findDiagonalOrder(array $matrix): array
{
    if (empty($matrix)) return [];
    $m = count($matrix); $n = count($matrix[0]);
    $result = [];

    for ($d = 0; $d < $m + $n - 1; $d++) {
        $temp = [];
        $r = $d < $n ? 0 : $d - $n + 1;
        $c = $d < $n ? $d : $n - 1;
        while ($r < $m && $c >= 0) {
            $temp[] = $matrix[$r][$c];
            $r++; $c--;
        }
        if ($d % 2 === 0) $temp = array_reverse($temp);
        $result = array_merge($result, $temp);
    }
    return $result;
}
```

### 5. Rotting Oranges (Multi-source BFS)
```php
public function orangesRotting(array $grid): int
{
    $queue = new SplQueue();
    $fresh = 0;
    $m = count($grid); $n = count($grid[0]);

    for ($i = 0; $i < $m; $i++) {
        for ($j = 0; $j < $n; $j++) {
            if ($grid[$i][$j] === 2) $queue->enqueue([$i, $j, 0]);
            if ($grid[$i][$j] === 1) $fresh++;
        }
    }

    $maxTime = 0;
    while (!$queue->isEmpty()) {
        [$r, $c, $t] = $queue->dequeue();
        $maxTime = max($maxTime, $t);
        foreach ([[1,0],[-1,0],[0,1],[0,-1]] as [$dr, $dc]) {
            $nr = $r + $dr; $nc = $c + $dc;
            if ($nr >= 0 && $nr < $m && $nc >= 0 && $nc < $n && $grid[$nr][$nc] === 1) {
                $grid[$nr][$nc] = 2;
                $fresh--;
                $queue->enqueue([$nr, $nc, $t + 1]);
            }
        }
    }
    return $fresh === 0 ? $maxTime : -1;
}
```

## Interview Sualları

1. **Sorted matrix-de axtarış O(m+n)-da niye mümkündür?**
   - Top-right (və ya bottom-left) küncden başlayıb hər addımda bir sira və ya sütunu elimine etmek olar.

2. **Matrix rotation üçün niye əvvəl transpose sonra reverse edirik?**
   - Transpose elementleri (i,j)->(j,i) mövqeyinə aparır. 90 derece rotation üçün elave olaraq horizontal flip (row reverse) lazımdır.

3. **Island problem-də DFS və BFS-in fərqi nədir?**
   - DFS sadə ve yığınlı (stack/recursion), çox dərin matrisdə stack overflow riski var.
   - BFS queue istifadə edir, yaddaş mürəkkəbliyi eyni O(m*n), amma iterativdir.

4. **In-place modification faydası?**
   - Elave yaddaş O(1)-dir. Lakin bazı hallarda ori­ginal məlumat itirilir, visitor pattern istifadə etmək lazım gəlir.

5. **Spiral traversal-da niye iki ekstra şərt lazımdır?**
   - top<=bottom və left<=right yoxlamaları təkrarçılığı önleyir (bir sıra və ya sütun qaldıqda).

6. **Flood fill və Number of Islands hansı algoritmden gelir?**
   - Connected components problemidir, adətən DFS və ya BFS ilə həll olunur.

## PHP/Laravel ilə Əlaqə

### Image processing:
```php
// GD library ile pixel matrix kimi düşünmek olar
$image = imagecreatefromjpeg('photo.jpg');
$width = imagesx($image);
$height = imagesy($image);

for ($y = 0; $y < $height; $y++) {
    for ($x = 0; $x < $width; $x++) {
        $rgb = imagecolorat($image, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        // pixel emeliyyatları (blur, edge detection və s.)
    }
}
```

### Grid-based games (Laravel app):
```php
// Tic-tac-toe və ya minesweeper üçün matrix
class GameBoard
{
    private array $grid;

    public function __construct(int $size)
    {
        $this->grid = array_fill(0, $size, array_fill(0, $size, 0));
    }

    public function revealArea(int $x, int $y): void
    {
        // Flood fill ile açıq hücreleri yay
    }
}
```

### Excel/CSV report:
```php
// Laravel Excel ile matrix kimi məlumat
use Maatwebsite\Excel\Concerns\FromArray;

class ReportExport implements FromArray
{
    public function array(): array
    {
        return [
            ['Ad', 'Yaş', 'Şəhər'],
            ['Ali', 25, 'Bakı'],
            ['Ayşe', 30, 'Gəncə']
        ];
    }
}
```

### Seat reservation system:
```php
// Kino/teatr yerləri 2D matrix kimi
$seats = DB::table('seats')
    ->where('hall_id', $hallId)
    ->orderBy('row')->orderBy('col')
    ->get()
    ->groupBy('row');

// Available seats tapmaq üçün matrix traversal
```

### Database table ilə əlaqə:
- PostgreSQL-də `ARRAY` tipi 2D matrisi saxlamağa imkan verir
- Redis-də hash of hash pattern ile 2D struktur
- ElasticSearch-də nested documents

### Real nümunələr:
1. **Map/location services**: Grid-based pathfinding
2. **Game development**: Chess, Go, card games board
3. **Matrix operations**: Machine learning features
4. **Reporting**: Cross-tab/pivot reports
5. **Image filters**: Blur, sharpen, edge detection

---

## Praktik Tapşırıqlar

1. **LeetCode 48** — Rotate Image (in-place 90° rotation)
2. **LeetCode 73** — Set Matrix Zeroes (O(1) space ilə)
3. **LeetCode 542** — 01 Matrix (multi-source BFS)
4. **LeetCode 240** — Search a 2D Matrix II (staircase axtarışı)
5. **LeetCode 329** — Longest Increasing Path in Matrix (DFS + memo)

### Step-by-step: Rotate Image 90°

```
matrix:
  1 2 3       7 4 1
  4 5 6  →    8 5 2
  7 8 9       9 6 3

Addım 1 — Transpose (swap matrix[i][j] ↔ matrix[j][i]):
  1 4 7
  2 5 8
  3 6 9

Addım 2 — Reverse hər sətri:
  7 4 1
  8 5 2
  9 6 3  ✓

In-place, O(1) extra space.
```

---

## Əlaqəli Mövzular

- [25-graphs-basics.md](25-graphs-basics.md) — Matrix = implicit graph, BFS/DFS
- [10-prefix-sum.md](10-prefix-sum.md) — 2D prefix sum (matrix range query)
- [22-backtracking.md](22-backtracking.md) — Matrix DFS + backtracking
- [44-sparse-table.md](44-sparse-table.md) — 2D range minimum query
