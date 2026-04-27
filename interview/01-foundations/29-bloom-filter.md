# Bloom Filter (Lead ⭐⭐⭐⭐)

## İcmal
Bloom Filter — space-efficient probabilistic data structure-dur: bir element-in set-də olub-olmadığını yoxlayır. False positive mümkündür (var deyə cavab verir, amma yoxdur), false negative isə mümkün deyil (yoxdur deyirsə, həqiqətən yoxdur). k hash funksiyası + bit array-dən ibarətdir. Bu özəllik onu cache miss azaltmaq, duplicate detection, URL blacklist kimi problemlər üçün ideal edir. Memory: classical set-dən 10-50x daha az.

## Niyə Vacibdir
Bloom filter — "big data" dünyasında yer tutmayan set-lər üçün fundamental data structure-dur. HBase, Cassandra, LevelDB (RocksDB) Bloom filter istifadə edərək disk I/O-nu dramatik şəkildə azaldır: "key yoxdur" halında disk-ə getmədən O(1)-də cavab. Google Chrome-un safe browsing URL check-i, Medium-un "seninə artıq göstərildi" sistemi, Akamai CDN-in one-hit-wonder cache sistemi Bloom filter istifadə edir. Lead interview-larında "veri axtarış sistemini necə optimallaşdırarsınız?" sualına düzgün cavab Bloom filter-i ehtiva edir.

## Əsas Anlayışlar

### Strukturu
- **Bit array**: m bitlik, başlanğıcda hamısı 0
- **k hash funksiyaları**: `h₁, h₂, ..., hₖ` — hər biri `[0, m)` aralığına map edir
- **Insert(x)**: `h₁(x), h₂(x), ..., hₖ(x)` mövqelərini 1-ə çevir
- **Contains(x)**: `h₁(x), ..., hₖ(x)` mövqelərinin hamısı 1-dirsə → "possibly in set", əks halda → "definitely not in set"

### False Positive Ehtimalı
`p = (1 - e^(-kn/m))^k`

Burada:
- m = bit array ölçüsü
- n = set-ə əlavə edilmiş element sayı
- k = hash funksiyası sayı

**Optimal k**: `k = (m/n) * ln(2) ≈ 0.693 * (m/n)`

**Əməli nisbətlər**:
| Bits per element (m/n) | FP rate (optimal k ilə) |
|------------------------|-------------------------|
| 8 | ~2.3% |
| 10 | ~1.2% |
| 16 | ~0.4% |
| 20 | ~0.1% |
| 32 | ~0.002% |

### Delete Əməliyyatı
Standard Bloom filter **element silmə dəstəkləmir** — bit 1-dir, amma həmin bit başqa elementin hash-indən gəlmiş ola bilər. Silmə = false negative riski.

**Counting Bloom Filter**: hər bit əvəzinə counter (4-bit) saxla. Counter artır/azalır. Silinmə mümkün. Amma 4x daha çox yaddaş.

### Optimal Parametrlər Hesablamaq
İstədiyimiz FP ehtimalı `p` və gözlənilən n üçün:
- `m = -n * ln(p) / (ln(2))²`
- `k = (m/n) * ln(2)`

Nümunə: n=1M, p=0.01 (1%) → m ≈ 9.6M bits ≈ 1.2 MB, k ≈ 7 hash.

### Bloom Filter Variantları

**Scalable Bloom Filter**:
- Set böyüdükcə yeni Bloom filter əlavə et
- FP rate azalmır, yaddaş elastik böyüyür
- Hər yeni filter bir az daha böyükdür (geometric growth)

**Cuckoo Filter**:
- Bloom filter-in evolution-u
- Deletion dəstəkləyir
- Daha yaxşı FP rate (bit başına)
- Cache-friendly
- Birdən çox hash yox, cuckoo hashing mexanizmi

**Quotient Filter**:
- Cache-friendly, deletion dəstəkli
- Merge əməliyyatını dəstəkləyir
- Database sistemlərindəki variantlarda istifadə olunur

**Spectral Bloom Filter**:
- Multiset — element sayını izlə
- Bir element neçə dəfə daxil edilib? → minimum counter dəyəri

### Hash Funksiyası Seçimi
- Kriptografik hash (SHA-256) lazım deyil — çox yavaş
- Non-cryptographic: MurmurHash3, xxHash, FNV — sürətli, yaxşı distribution
- k hash funksiyasını bir hash-dən türetmək: `h_i(x) = (h1(x) + i * h2(x)) % m` (double hashing)

### Real Dünya Tətbiqləri

**Database (RocksDB/LevelDB/Cassandra)**:
- Hər SSTable/LSM kompakt fayl üçün Bloom filter saxla
- Read: key var-yoxsa əvvəlcə filter yoxla
- "Yox" deyirsə: disk I/O-ya getmə
- "Var" deyirsə: diske get, real yoxla (FP olarsa boşuna, amma nadir)
- Result: disk read-lərini ~90% azaldır

**Web Crawler (URL deduplication)**:
- Milyardlarla URL → memory-yə sığmır
- Bloom filter: "bu URL-i görübmüyəm?" → O(k) time, sabit memory

**Spell Checker / Password Breach Check**:
- 600M breach password → Bloom filter
- Yeni şifrə girildikdə yoxla
- FP: "bu şifrə breach-ə uğramış" deyir, amma belə deyil → kabul edilə bilər
- FN: heç vaxt olmur → kritik şərt

**Weak Password Blacklist**:
- "password123", "qwerty" → filter
- Login attempt-də O(1) yoxlama

### Cache Aside Pattern + Bloom Filter
```
// Cache miss azaltmaq
if bloom_filter.contains(key):
    result = cache.get(key) or db.get(key)
    cache.set(key, result)
else:
    return null  # disk-ə getmə, yoxdur
```

## Praktik Baxış

### Interview Yanaşması
1. "Memory-efficient set membership" → Bloom filter
2. FP tolerable, FN kabul edilməz → Bloom filter uyğundur
3. Deletion lazımdırsa → Counting Bloom Filter ya da Cuckoo Filter
4. Parametrləri (m, k) optimal seçimi haqqında danış

### Nədən Başlamaq
- Use case-i aydınlaşdır: FP rate nə qədər kabul edilə bilər?
- Gözlənilən element sayı n nədir?
- Həmin n və p üçün m, k hesabla
- Hash funksiyası seçimini izah et

### Ümumi Follow-up Suallar
- "False positive rate-i azaltmaq üçün nə edərdiniz?"
- "Bloom filter-dən element silmək lazım olsa nə edərdiniz?"
- "Distributed sistemdə Bloom filter-i necə saxlayardınız?"
- "Bloom filter vs Hash Set — nə zaman hansını seçərdiniz?"

### Namizədlərin Ümumi Səhvləri
- False negative mümkündür demək (olmaz — bu Bloom filter-in əsas xüsusiyyətidir)
- Deletion mümkündür demək (standard BF-də olmaz)
- Hash funksiyası sayının (k) optimal dəyərini bilməmək
- FP rate hesablama formulunu bilməmək

### Yaxşı → Əla Cavab
- Yaxşı: Bloom filter-in FP/FN davranışını, insert/query-ni izah edir
- Əla: optimal k, m formullarını bilir, Counting/Cuckoo variantlarını qeyd edir, RocksDB kimi real sistemlərə bağlayır, FP rate-i system requirement-ə görə seçir

## Nümunələr

### Tipik Interview Sualı
"Design sistemi: milyardlarla URL-i ziyarət etmiş web crawler üçün 'bu URL-i görübmüyəm?' sualını O(1) time, minimal memory ilə cavablandırın."

### Güclü Cavab
Bu problemi Bloom filter ilə həll edərdim. Gözlənilən URL sayı 10 milyarddırsa (10^10) və 1% FP rate qəbul edirsəm, optimal parametrlər:
- `m = -10^10 * ln(0.01) / (ln(2))² ≈ 96 milyar bit ≈ 12 GB`
- `k ≈ 7 hash funksiyası`

12 GB — 10 milyard URL-i sabit string olaraq saxlamaqdan (ortalama 50 byte × 10^10 = 500 GB) çox daha az.

Davranış: "bu URL yoxdur" deyirsə — keçmişə baxmadan ata. "Var" deyirsə — həqiqəti yoxlamaq üçün persistent storage-a bax. 1% FP rate o deməkdir ki, hər 100 URL-dən 1-i yenidən işlənə bilər — bu qəbulolunan.

FP rate-i azaltmaq üçün: daha böyük bit array istifadə et, ya da Cuckoo Filter-a keç (daha yaxşı FP/space trade-off).

Distributed crawler üçün: hər crawler node-u öz Bloom filter saxlayır, periodically bütün filter-ları merge et (bit-wise OR).

### Kod Nümunəsi
```python
import hashlib
import math

class BloomFilter:
    def __init__(self, expected_elements: int, false_positive_rate: float):
        """
        n = expected_elements
        p = false_positive_rate (0.01 = 1%)
        m = bit array ölçüsü
        k = hash funksiyası sayı
        """
        self.n = expected_elements
        self.p = false_positive_rate
        # Optimal m
        self.m = int(-expected_elements * math.log(false_positive_rate) / (math.log(2) ** 2))
        # Optimal k
        self.k = int((self.m / expected_elements) * math.log(2))
        # Bit array (Python-da integer bitmask olaraq)
        self.bit_array = bytearray(math.ceil(self.m / 8))
        self.element_count = 0

    def _get_hash_positions(self, item: str):
        """k hash funksiyası üçün double hashing"""
        h1 = int(hashlib.md5(item.encode()).hexdigest(), 16)
        h2 = int(hashlib.sha1(item.encode()).hexdigest(), 16)
        return [(h1 + i * h2) % self.m for i in range(self.k)]

    def _set_bit(self, pos: int):
        self.bit_array[pos // 8] |= (1 << (pos % 8))

    def _get_bit(self, pos: int) -> bool:
        return bool(self.bit_array[pos // 8] & (1 << (pos % 8)))

    def add(self, item: str):
        for pos in self._get_hash_positions(item):
            self._set_bit(pos)
        self.element_count += 1

    def contains(self, item: str) -> bool:
        """True: 'possibly in set', False: 'definitely not in set'"""
        return all(self._get_bit(pos) for pos in self._get_hash_positions(item))

    @property
    def current_fp_rate(self) -> float:
        """Cari false positive ehtimalı"""
        return (1 - math.exp(-self.k * self.element_count / self.m)) ** self.k

    def __repr__(self):
        return (f"BloomFilter(m={self.m} bits, k={self.k} hashes, "
                f"n={self.element_count}, fp_rate≈{self.current_fp_rate:.4f})")

# Counting Bloom Filter (deletion dəstəkli)
class CountingBloomFilter:
    def __init__(self, m: int, k: int):
        self.m = m
        self.k = k
        self.counters = [0] * m  # hər mövqe üçün sayac

    def _positions(self, item):
        h1 = hash(item)
        h2 = hash(item + "salt")
        return [(h1 + i * h2) % self.m for i in range(self.k)]

    def add(self, item):
        for pos in self._positions(item):
            self.counters[pos] += 1

    def remove(self, item):
        if not self.contains(item):
            raise ValueError("Element set-də yoxdur")
        for pos in self._positions(item):
            self.counters[pos] -= 1

    def contains(self, item) -> bool:
        return all(self.counters[pos] > 0 for pos in self._positions(item))

# İstifadə nümunəsi
if __name__ == "__main__":
    bf = BloomFilter(expected_elements=1_000_000, false_positive_rate=0.01)
    print(bf)  # m, k parametrlərini göstər

    urls = ["https://example.com", "https://google.com", "https://test.org"]
    for url in urls:
        bf.add(url)

    # Test
    assert bf.contains("https://example.com") == True   # True positive
    assert bf.contains("https://notadded.com") == False  # True negative (almost always)
    # bf.contains("https://other.com") possibly False Positive
```

## Praktik Tapşırıqlar
- **Implement**: Bloom Filter class — m, k, insert, lookup. FP rate test et: 1000 element əlavə et, 1000 əlavə edilməmiş elementi yoxla, neçəsi false positive?
- **Parametr hesabla**: n=100K URL, FP ≤ 0.1% üçün optimal m, k tap
- **Design**: LevelDB-nin Bloom filter istifadəsini araşdır. SSTable per-filter niyə daha yaxşı?
- **Counting BF**: Implement et, deletion sonra FP rate-in dəyişdiyini nümunə ilə göstər
- LeetCode 1206 — Design Skiplist (Bloom filter ilə əlaqəli probabilistic structure)
- **Real sistem design**: Spam URL detection sistemi — 10 milyar URL, 99.9% dəqiqlik, 100ms latency, minimal memory

## Əlaqəli Mövzular
- **Hashing** — hash funksiyası seçimi Bloom filter-in kalibrasiyasının əsasıdır
- **Bit Manipulation** — Bloom filter bit array üzərindəki bit operasiyalarıdır
- **Cache Systems** — Bloom filter cache-in önündə "önfiltr" rolunu oynayır
- **Probabilistic Data Structures** — HyperLogLog (count distinct), Count-Min Sketch (frequency estimation) analog struktur ailədəndir
- **LSM Tree / RocksDB** — Bloom filter-in ən mühüm real tətbiqi disk-based storage-da
