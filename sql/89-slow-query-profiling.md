# Slow Query Log və Query Profiling (Senior)

## Mündəricat
1. [Slow Query Log nədir?](#slow-query-log-nədir)
2. [MySQL Slow Query Log Konfiqurasiyası](#mysql-slow-query-log-konfiqurasiyası)
3. [PostgreSQL Slow Query Log Konfiqurasiyası](#postgresql-slow-query-log-konfiqurasiyası)
4. [Log Analiz Alətləri](#log-analiz-alətləri)
5. [EXPLAIN ANALYZE Dərin Analiz](#explain-analyze-dərin-analiz)
6. [MySQL PROFILING](#mysql-profiling)
7. [PostgreSQL pg_stat_statements](#postgresql-pg_stat_statements)
8. [Laravel Query Log və Debug](#laravel-query-log-və-debug)
9. [Real-World Nümunələr](#real-world-nümunələr)
10. [Automated Slow Query Detection](#automated-slow-query-detection)
11. [PHP İmplementasiyası](#php-implementasiyası)
12. [İntervyu Sualları](#intervyu-sualları)

---

## Slow Query Log nədir?

Slow Query Log, verilənlər bazası tərəfindən icra olunan və müəyyən vaxt həddini aşan sorğuların avtomatik qeydə alınması mexanizmidir. Bu, production mühitində performans problemlərini aşkar etmək üçün ən əsas vasitələrdən biridir.

```
Slow Query Log İş Prinsipi:
                                                          
  +------------------+     +------------------+     +-------------------+
  |   Tətbiq (PHP)   |---->|  Verilənlər Bazası|---->|  Query Executor   |
  |   Laravel/etc    |     |  MySQL/PostgreSQL |     |                   |
  +------------------+     +------------------+     +--------+----------+
                                                             |
                                                    Vaxt ölçülür
                                                             |
                                                    +--------v----------+
                                                    | Vaxt > Threshold?  |
                                                    |   (məs. 1 saniyə) |
                                                    +--------+----------+
                                                             |
                                              +--------------+--------------+
                                              |                             |
                                         Bəli (Yavaş)                 Xeyr (Normal)
                                              |                             |
                                    +---------v---------+         +---------v---------+
                                    | Slow Query Log-a  |         |  Heç bir qeyd     |
                                    | yazılır           |         |  yazılmır          |
                                    +-------------------+         +-------------------+
```

Slow Query Log-un əsas məqsədləri:

- **Performans bottleneck-ların tapılması**: Hansı sorğular sistemi yavaşladır?
- **İndeks çatışmazlıqlarının aşkarlanması**: Hansı sorğular full table scan edir?
- **N+1 problemlərinin müəyyənləşdirilməsi**: Hansı sorğular lazımsız yerə təkrarlanır?
- **Resurs istehlakının analizi**: Hansı sorğular ən çox CPU/IO istifadə edir?
- **Capacity planning**: Yük artdıqca hansı sorğular problem yaradacaq?

```
Tipik Slow Query Log Yaşam Dövrü:

  +-----------+     +------------+     +------------+     +-----------+     +----------+
  |  Sorğu    |---->|  Log-a     |---->|  Analiz    |---->| Optimallaş|---->| Yenidən  |
  |  İcra olur|     |  Yazılır   |     |  Edilir    |     | dırılır   |     | Test     |
  +-----------+     +------------+     +------------+     +-----------+     +----------+
                                            |
                                   +--------+--------+
                                   |                  |
                              pt-query-digest     pgBadger
                              mysqldumpslow       pg_stat_statements
```

### MySQL vs PostgreSQL Slow Query Log Müqayisəsi

```
+------------------------+---------------------------+---------------------------+
|       Xüsusiyyət       |         MySQL             |       PostgreSQL          |
+------------------------+---------------------------+---------------------------+
| Konfiqurasiya faylı    | my.cnf / my.ini           | postgresql.conf           |
| Minimum vaxt dəqiqliyi | Mikrosaniyə               | Millisaniyə              |
| Default threshold      | 10 saniyə                 | Söndürülmüş              |
| Log formatı            | Xüsusi format             | CSV / stderr             |
| Runtime dəyişiklik     | SET GLOBAL                | ALTER SYSTEM / pg_reload  |
| Sorğu planı logu       | Yoxdur (ayrıca EXPLAIN)   | auto_explain modulu       |
| Parametr qeydiyyatı    | Tam sorğu yazılır         | Parametrlər ayrıca        |
| Analiz aləti           | pt-query-digest           | pgBadger                  |
| Lock vaxtı qeydiyyatı  | Bəli (lock_time)          | Bəli (log_lock_waits)     |
| Temp table istifadəsi  | Bəli                      | Bəli (log_temp_files)     |
+------------------------+---------------------------+---------------------------+
```

---

## MySQL Slow Query Log Konfiqurasiyası

### my.cnf / my.ini Parametrləri

MySQL-də slow query log-u aktivləşdirmək üçün `my.cnf` faylında aşağıdakı parametrlər tənzimlənir:

```ini
# /etc/mysql/my.cnf və ya /etc/my.cnf

[mysqld]
# Slow query log-u aktivləşdir
slow_query_log = 1

# Log faylının yeri
slow_query_log_file = /var/log/mysql/slow-query.log

# Threshold - bu vaxtdan yavaş olan sorğular qeydə alınır (saniyə ilə)
# 0 = bütün sorğular, 1 = 1 saniyədən yavaş olanlar
long_query_time = 1

# İndeks istifadə etməyən sorğuları da qeydə al
log_queries_not_using_indexes = 1

# İndeks istifadə etməyən sorğuların throttle-u (dəqiqədə maks)
log_throttle_queries_not_using_indexes = 60

# Admin sorğularını da qeydə al (OPTIMIZE TABLE, ANALYZE TABLE, və s.)
log_slow_admin_statements = 1

# Slave/replica-da icra olunan sorğuları da qeydə al
log_slow_slave_statements = 1

# Minimum nəticə sətri sayı (bu saydan az nəticə qaytaran sorğular qeydə alınmır)
min_examined_row_limit = 0
```

### Runtime-da Aktivləşdirmə (restart tələb etmir)

```sql
-- Slow query log-u canlı aktivləşdir
SET GLOBAL slow_query_log = 'ON';

-- Threshold-u 0.5 saniyəyə endirmək
SET GLOBAL long_query_time = 0.5;

-- İndeks istifadə etməyən sorğuları da qeydə al
SET GLOBAL log_queries_not_using_indexes = 'ON';

-- Cari parametrləri yoxla
SHOW VARIABLES LIKE 'slow_query%';
SHOW VARIABLES LIKE 'long_query_time';

-- Nəticə:
-- +---------------------+----------------------------------+
-- | Variable_name       | Value                            |
-- +---------------------+----------------------------------+
-- | slow_query_log      | ON                               |
-- | slow_query_log_file | /var/log/mysql/slow-query.log    |
-- +---------------------+----------------------------------+

-- Slow query statistikası
SHOW GLOBAL STATUS LIKE 'Slow_queries';

-- Nəticə:
-- +---------------+-------+
-- | Variable_name | Value |
-- +---------------+-------+
-- | Slow_queries  | 1432  |
-- +---------------+-------+
```

### MySQL Slow Query Log Formatı

```
# Tipik MySQL slow query log girişi:

# Time: 2026-04-11T10:15:23.456789Z
# User@Host: laravel_user[laravel_user] @ app-server-01 [10.0.1.15]  Id: 42531
# Query_time: 3.456789  Lock_time: 0.000123  Rows_sent: 150  Rows_examined: 2500000
SET timestamp=1712831723;
SELECT o.*, u.name, u.email
FROM orders o
JOIN users u ON u.id = o.user_id
WHERE o.created_at BETWEEN '2025-01-01' AND '2025-12-31'
  AND o.status = 'completed'
ORDER BY o.total_amount DESC
LIMIT 150;
```

```
MySQL Slow Query Log Sahələrinin İzahı:

+-------------------+----------------------------------------------------------+
|      Sahə         |                      İzah                                |
+-------------------+----------------------------------------------------------+
| Time              | Sorğunun tamamlandığı vaxt (UTC)                         |
| User@Host         | Sorğunu icra edən istifadəçi və host                     |
| Id                | Bağlantı identifikatoru (connection ID)                  |
| Query_time        | Sorğunun icra müddəti (saniyə.mikrosaniyə)              |
| Lock_time         | Row lock gözləmə müddəti                                |
| Rows_sent         | Klientə göndərilən sətir sayı                           |
| Rows_examined     | Sorğu icrasında yoxlanılan sətir sayı                    |
+-------------------+----------------------------------------------------------+

DİQQƏT: Rows_examined >> Rows_sent olduqda, bu adətən
         indeks çatışmazlığına və ya pis sorğu planına işarə edir.
```

### Faydalı MySQL Slow Query Filtrləmə

```sql
-- Son 24 saatda ən yavaş 10 sorğu (Performance Schema vasitəsilə)
SELECT 
    DIGEST_TEXT AS query_pattern,
    COUNT_STAR AS exec_count,
    ROUND(AVG_TIMER_WAIT / 1000000000, 2) AS avg_ms,
    ROUND(SUM_TIMER_WAIT / 1000000000, 2) AS total_ms,
    SUM_ROWS_EXAMINED AS rows_examined,
    SUM_ROWS_SENT AS rows_sent,
    FIRST_SEEN,
    LAST_SEEN
FROM performance_schema.events_statements_summary_by_digest
WHERE LAST_SEEN > NOW() - INTERVAL 24 HOUR
ORDER BY AVG_TIMER_WAIT DESC
LIMIT 10;

-- Full table scan edən sorğuları tap
SELECT 
    DIGEST_TEXT,
    COUNT_STAR,
    SUM_NO_INDEX_USED,
    SUM_NO_GOOD_INDEX_USED
FROM performance_schema.events_statements_summary_by_digest
WHERE SUM_NO_INDEX_USED > 0
ORDER BY SUM_NO_INDEX_USED DESC
LIMIT 20;
```

---

## PostgreSQL Slow Query Log Konfiqurasiyası

### postgresql.conf Parametrləri

```ini
# /etc/postgresql/16/main/postgresql.conf

# Sorğuların loglanması
log_destination = 'csvlog'           # CSV formatında log (analiz üçün rahat)
logging_collector = on               # Log collector daemon-u aktivləşdir

# Yavaş sorğu threshold-u (millisaniyə ilə)
log_min_duration_statement = 500     # 500ms-dən yavaş sorğuları logla
                                     # -1 = söndürülmüş, 0 = hamısını logla

# Alternativ: yalnız müəyyən faiz sorğuları logla (sampling)
# log_min_duration_sample = 100      # 100ms-dən yavaş sorğulardan
# log_statement_sample_rate = 0.5    # 50%-ni logla (yüksək yükdə faydalı)

# Log formatı
log_line_prefix = '%t [%p-%l] %q%u@%d '   # vaxt, PID, istifadəçi, DB
log_statement = 'none'                      # 'none', 'ddl', 'mod', 'all'

# Əlavə məlumat
log_duration = off                   # Hər sorğunun müddətini logla
log_lock_waits = on                  # Lock gözləmələrini logla
deadlock_timeout = 1s                # Deadlock aşkarlama vaxtı
log_temp_files = 0                   # Temp fayl istifadəsini logla (bayt; 0=hamısı)
log_checkpoints = on                 # Checkpoint məlumatlarını logla

# auto_explain modulu (EXPLAIN-i avtomatik logla)
shared_preload_libraries = 'auto_explain, pg_stat_statements'

# auto_explain parametrləri
auto_explain.log_min_duration = 1000    # 1 saniyədən yavaş sorğuların planını logla
auto_explain.log_analyze = true          # EXPLAIN ANALYZE (actual times) daxil et
auto_explain.log_buffers = true          # Buffer istifadəsini daxil et
auto_explain.log_timing = true           # Vaxt məlumatını daxil et
auto_explain.log_nested_statements = true # Funksiya daxilindəki sorğuları da logla
auto_explain.log_format = 'json'         # JSON formatda (analiz üçün rahat)
```

### Runtime-da Dəyişiklik

```sql
-- Cari sessiya üçün
SET log_min_duration_statement = 200;   -- 200ms

-- Bütün yeni sesiyalar üçün (restart tələb etmir, reload kifayətdir)
ALTER SYSTEM SET log_min_duration_statement = 200;
SELECT pg_reload_conf();

-- Cari parametrləri yoxla
SHOW log_min_duration_statement;
SHOW log_statement;

-- auto_explain-i cari sessiya üçün aktivləşdir
LOAD 'auto_explain';
SET auto_explain.log_min_duration = 0;  -- Bütün sorğuların planını göstər
```

### PostgreSQL Log Formatı

```
# Tipik PostgreSQL slow query log girişi (CSV formatda):

2026-04-11 10:15:23.456 UTC,"laravel_user","ecommerce_db",42531,
"10.0.1.15:54321",66183af3.a623,1,"SELECT",2026-04-11 10:15:20.000 UTC,
4/1234,0,LOG,00000,
"duration: 3456.789 ms  statement: SELECT o.*, u.name, u.email 
FROM orders o JOIN users u ON u.id = o.user_id 
WHERE o.created_at BETWEEN '2025-01-01' AND '2025-12-31' 
AND o.status = 'completed' ORDER BY o.total_amount DESC LIMIT 150",,,,,,,,,"app-server"

# auto_explain ilə (JSON formatda):
2026-04-11 10:15:23.456 UTC [42531] laravel_user@ecommerce_db LOG:  
duration: 3456.789 ms  plan:
{
  "Query Text": "SELECT o.*, ...",
  "Plan": {
    "Node Type": "Limit",
    "Actual Rows": 150,
    "Actual Total Time": 3456.123,
    "Plans": [
      {
        "Node Type": "Sort",
        "Sort Key": ["o.total_amount DESC"],
        "Plans": [
          {
            "Node Type": "Hash Join",
            "Hash Cond": "(o.user_id = u.id)",
            "Actual Rows": 85432
          }
        ]
      }
    ]
  }
}
```

```
PostgreSQL Log Parametrlərinin Təsir Diaqramı:

log_min_duration_statement = 500ms

  Sorğular:
  
  Query A: 50ms    -----> Loglanmır
  Query B: 200ms   -----> Loglanmır  
  Query C: 499ms   -----> Loglanmır
  Query D: 500ms   -----> LOGLANIR  (tam threshold-da)
  Query E: 1200ms  -----> LOGLANIR
  Query F: 5000ms  -----> LOGLANIR
  
  log_min_duration_statement = 0 olduqda:
  BÜTÜN sorğular loglanır (development/debug üçün)
  
  log_min_duration_statement = -1 olduqda:
  HEÇ BİR sorğu loglanmır (söndürülmüş)
```

---

## Log Analiz Alətləri

### pt-query-digest (Percona Toolkit - MySQL)

`pt-query-digest` MySQL slow query log-larını analiz edən ən güclü alətdir. Sorğuları qruplaşdırır, statistika çıxarır və ən problemli sorğuları prioritetləşdirir.

```bash
# Quraşdırma
sudo apt-get install percona-toolkit
# və ya
wget https://downloads.percona.com/downloads/percona-toolkit/LATEST/binary/debian/jammy/x86_64/percona-toolkit_3.6.0-1.jammy_amd64.deb
sudo dpkg -i percona-toolkit_*.deb

# Əsas istifadə
pt-query-digest /var/log/mysql/slow-query.log

# Son 1 saatın loglarını analiz et
pt-query-digest --since '1h' /var/log/mysql/slow-query.log

# Yalnız müəyyən verilənlər bazası üçün
pt-query-digest --filter '$event->{db} eq "ecommerce"' /var/log/mysql/slow-query.log

# Nəticəni fayla yaz
pt-query-digest /var/log/mysql/slow-query.log --output=report > /tmp/slow-query-report.txt

# İki vaxt arasını müqayisə et (regression tapma)
pt-query-digest --since '2026-04-10' --until '2026-04-11' /var/log/mysql/slow-query.log

# Ən yavaş 5 sorğu pattern-ini göstər
pt-query-digest --limit 5 /var/log/mysql/slow-query.log

# TCP trafikdən canlı analiz (tcpdump ilə)
tcpdump -s 65535 -x -nn -q -tttt -i any -c 10000 port 3306 | \
  pt-query-digest --type tcpdump
```

```
pt-query-digest Nəticə Nümunəsi:

# 290ms user time, 10ms system time, 28.06M rss, 217.34M vsz
# Current date: Sat Apr 11 10:30:00 2026
# Hostname: db-master-01
# Files: /var/log/mysql/slow-query.log
# Overall: 15.43k total, 127 unique, 4.28 QPS, 2.15x concurrency ________
# Time range: 2026-04-10T00:00:00 to 2026-04-11T00:00:00
# Attribute          total     min     max     avg     95%  stddev  median
# ============     ======= ======= ======= ======= ======= ======= =======
# Exec time         7750s   100ms    45s   502ms      2s   851ms   287ms
# Lock time           15s       0   512ms   973us     1ms    12ms   103us
# Rows sent        425.13k       0  10.50k   28.25   97.36  312.52    0.99
# Rows examine     985.42M       0   5.25M  65.43k 462.31k 251.13k   1.96k
# Query size         5.82M      21   8.19k  395.42   2.06k  623.15  166.51

# Profile
# Rank Query ID                           Response time  Calls R/Call V/M
# ==== ================================== ============== ===== ====== ====
#    1 0xABC123DEF456 SELECT orders JOIN   2145.2 27.7%  4521 0.4745 0.12
#    2 0x789GHI012JKL SELECT products W... 1532.8 19.8%  2156 0.7109 0.31
#    3 0xMNO345PQR678 UPDATE inventory S.. 1021.5 13.2%   892 1.1453 0.55
#    4 0xSTU901VWX234 SELECT users WHERE.. 876.3  11.3%  3215 0.2726 0.08
#    5 0xYZA567BCD890 INSERT INTO order_..  654.1  8.4%  1823 0.3588 0.15

# Query 1: 4.52k QPS, 2.14x concurrency, ID 0xABC123DEF456 at byte 1234567
# This item is included in the report because it matches --limit.
# Scores: V/M = 0.12
# Time range: 2026-04-10T00:00:01 to 2026-04-11T23:59:58
# Attribute    pct   total     min     max     avg     95%  stddev  median
# ============ === ======= ======= ======= ======= ======= ======= =======
# Count         29    4521
# Exec time     27   2145s   102ms    12s   474ms      1s   412ms   312ms
# Lock time     12      2s    45us   125ms   389us   626us     5ms   103us
# Rows sent     35 150.23k       1     150   34.05  136.99   42.31    9.83
# Rows examine  45 446.32M   1.23k   5.25M 101.12k 462.31k 152.43k  52.31k
# String:
# Databases    ecommerce
# Hosts        10.0.1.15 (2105), 10.0.1.16 (1512), 10.0.1.17 (904)
# Users        laravel_user
# Query_time distribution
#   1us
#  10us
# 100us
#   1ms
#  10ms
# 100ms  ################################################################
#    1s   ##################
#  10s+   #
# EXPLAIN
SELECT o.*, u.name, u.email
FROM orders o
JOIN users u ON u.id = o.user_id
WHERE o.created_at BETWEEN '2025-01-01' AND '2025-12-31'
  AND o.status = 'completed'
ORDER BY o.total_amount DESC
LIMIT 150\G
```

### pgBadger (PostgreSQL)

pgBadger PostgreSQL log-larını analiz edən güclü bir vasitədir. HTML formatda ətraflı hesabat generasiya edir.

```bash
# Quraşdırma
sudo apt-get install pgbadger
# və ya
pip install pgbadger
# və ya mənbədən
git clone https://github.com/darold/pgbadger.git
cd pgbadger && perl Makefile.PL && make && sudo make install

# Əsas istifadə - HTML hesabat yaradır
pgbadger /var/log/postgresql/postgresql-16-main.log -o /tmp/pg_report.html

# CSV log üçün
pgbadger --format csv /var/log/postgresql/postgresql-16-main.csv

# Müəyyən tarix aralığı
pgbadger --begin '2026-04-10 00:00:00' --end '2026-04-11 00:00:00' \
  /var/log/postgresql/postgresql-16-main.log

# İncremental analiz (böyük loglar üçün)
pgbadger --incremental --outdir /var/www/pgbadger/ \
  /var/log/postgresql/postgresql-16-main.log

# Yalnız yavaş sorğuları analiz et
pgbadger --top 20 /var/log/postgresql/postgresql-16-main.log

# Paralel analiz (böyük loglar üçün sürətli)
pgbadger -j 4 /var/log/postgresql/postgresql-16-main.log

# Gündəlik avtomatik hesabat (cron job)
# 0 1 * * * pgbadger --incremental --outdir /var/www/pgbadger/ \
#   /var/log/postgresql/postgresql-16-main.log
```

```
pgBadger Hesabat Strukturu:

+----------------------------------------------------------------------+
|                    pgBadger PostgreSQL Report                         |
+----------------------------------------------------------------------+
|                                                                      |
|  Overview                                                            |
|  +--------------------+  +--------------------+  +------------------+|
|  | Total Queries:     |  | Avg Query Time:    |  | Slow Queries:    ||
|  | 2,456,789          |  | 12.5ms             |  | 1,432            ||
|  +--------------------+  +--------------------+  +------------------+|
|                                                                      |
|  Top Slow Queries (by total time)                                    |
|  +----------------------------------------------------------------+  |
|  | #  | Calls  | Total Time | Avg Time | Query                    |  |
|  |----|--------|------------|----------|--------------------------|  |
|  | 1  | 4,521  | 2145.2s    | 474ms    | SELECT o.*, u.name ...   |  |
|  | 2  | 2,156  | 1532.8s    | 711ms    | SELECT * FROM products...|  |
|  | 3  |   892  | 1021.5s    | 1145ms   | UPDATE inventory SET...  |  |
|  +----------------------------------------------------------------+  |
|                                                                      |
|  Hourly Statistics                                                   |
|  Queries/hour  |  Errors/hour  |  Avg Duration/hour                  |
|  [Bar Charts]  |  [Bar Charts] |  [Line Chart]                      |
|                                                                      |
|  Lock Analysis | Temp Files | Checkpoints | Connections              |
+----------------------------------------------------------------------+
```

### mysqldumpslow (MySQL Built-in)

```bash
# MySQL ilə birlikdə gələn sadə analiz aləti

# Ən yavaş 10 sorğu
mysqldumpslow -s t -t 10 /var/log/mysql/slow-query.log

# Ən çox təkrarlanan 10 sorğu
mysqldumpslow -s c -t 10 /var/log/mysql/slow-query.log

# Ən çox sətir yoxlayan sorğular
mysqldumpslow -s r -t 10 /var/log/mysql/slow-query.log

# Parametrləri göstər:
# -s: sıralama növü (t=time, c=count, r=rows, l=lock time)
#     at=average time, ac=average count, ar=average rows
# -t: yalnız ilk N nəticə
# -g: regex ilə filtr
mysqldumpslow -s at -t 5 -g "orders" /var/log/mysql/slow-query.log
```

```
Alətlərin Müqayisəsi:

+----------------------+------------------+------------------+------------------+
|     Xüsusiyyət       | pt-query-digest  |    pgBadger      | mysqldumpslow    |
+----------------------+------------------+------------------+------------------+
| Verilənlər bazası    | MySQL            | PostgreSQL       | MySQL            |
| Çıxış formatı        | Mətn             | HTML/JSON/Mətn   | Mətn             |
| Sorğu qruplaşdırma   | Bəli (fingerprint)| Bəli            | Bəli (sadə)     |
| Vaxt aralığı filtri  | Bəli             | Bəli             | Xeyr             |
| İncremental analiz   | Xeyr             | Bəli             | Xeyr             |
| Qrafiklər            | Xeyr             | Bəli (interaktiv)| Xeyr             |
| Regex filtrləmə      | Bəli             | Bəli             | Bəli             |
| Çətin quraşdırma     | Orta             | Asan             | Quraşdırma yox   |
| Dərinlik             | Çox dərin        | Dərin            | Səthi            |
| Production istifadə  | Ən yaxşı         | Ən yaxşı         | Tez baxış üçün   |
+----------------------+------------------+------------------+------------------+
```

---

## EXPLAIN ANALYZE Dərin Analiz

EXPLAIN ANALYZE sorğunun necə icra olunduğunu ətraflı göstərir. Slow query-lərin səbəbini anlamaq üçün vacibdir.

### Scan Tipləri

```
PostgreSQL Scan Tipləri və Performans Xüsusiyyətləri:

+------------------+----------+--------------------------------------------------+
|   Scan Tipi      | Sürət    |                    İzah                          |
+------------------+----------+--------------------------------------------------+
| Index Only Scan  | Ən sürətli| Yalnız indeksdən oxuyur, cədvələ baxmır          |
| Index Scan       | Sürətli  | İndekslə tapır, cədvəldən əlavə məlumat oxuyur   |
| Bitmap Index Scan| Orta-Sürətli| Çoxlu uyğunluq olduqda, bitmap yaradıb scan edir|
| Bitmap Heap Scan | Orta     | Bitmap scan-dan sonra cədvəldən oxuma             |
| Seq Scan         | Yavaş*   | Bütün cədvəli sətir-sətir oxuyur                 |
+------------------+----------+--------------------------------------------------+

* Seq Scan kiçik cədvəllərdə və ya nəticə cədvəlin böyük hissəsini 
  əhatə etdikdə (>10-15%) ən optimal seçim ola bilər!


Scan Tipləri Vizuallaşdırma:

1. Sequential Scan (Seq Scan):
   Cədvəl: [1][2][3][4][5][6][7][8][9][10]...[1000000]
            ^  ^  ^  ^  ^  ^  ^  ^  ^  ^        ^
            Hər bir sətir yoxlanılır (O(n))
            
   Nə vaxt normal: SELECT COUNT(*) FROM users (bütün cədvəl lazımdır)
   Nə vaxt problem: SELECT * FROM users WHERE email = 'test@test.com'

2. Index Scan:
   İndeks B-Tree:
                    [M]
                   /   \
                [D]     [T]
               / \     / \
             [A] [G] [P] [W]
              |   |   |   |
   Cədvəl:  [1] [4] [7] [9]  --> Birbaşa lazımi sətrə gedir
   
   Sürət: O(log n) - çox sürətli

3. Bitmap Index Scan + Bitmap Heap Scan:
   Addım 1 - Bitmap yaradılır:
   İndeks scan --> Bitmap: [1,0,0,1,0,1,0,0,1,1]
                           (hər bit bir page-ə uyğundur)
   
   Addım 2 - Bitmap Heap Scan:
   Yalnız bitmap-da 1 olan page-lər oxunur:
   Pages: [P1][P2][P3][P4][P5][P6][P7][P8][P9][P10]
           ^              ^        ^              ^  ^
           Yalnız bu page-lər oxunur
   
   Nə vaxt istifadə olunur: Çoxlu nəticə olduqda (>bir neçə %)
   Üstünlük: Random I/O əvəzinə sequential I/O
```

### Join Tipleri

```
PostgreSQL Join Tipləri:

1. Nested Loop Join:
   +---------+     +---------+
   | Table A |     | Table B |
   | (outer) |     | (inner) |
   +---------+     +---------+
      |                |
      | Hər A sətri    |
      | üçün B-ni      |
      | tam scan et    |
      v                v
   A[1] --> B[1], B[2], B[3], ..., B[n]  --> Uyğun olanları qaytar
   A[2] --> B[1], B[2], B[3], ..., B[n]  --> Uyğun olanları qaytar
   A[3] --> B[1], B[2], B[3], ..., B[n]  --> Uyğun olanları qaytar
   ...
   
   Mürəkkəblik: O(n * m)
   Ən yaxşı: Kiçik cədvəllər və ya inner table-da index olduqda
              (Index Nested Loop --> O(n * log m))

2. Hash Join:
   Addım 1: Hash Table yarat (kiçik cədvəldən)
   Table B --> Hash Table:
   +--------+--------+
   | Hash   | Row    |
   +--------+--------+
   | h(1)   | B[3]   |
   | h(2)   | B[7]   |
   | h(3)   | B[1]   |
   | ...    | ...    |
   +--------+--------+
   
   Addım 2: Böyük cədvəli scan et və hash table-dan tap
   A[1] --> hash(A[1].key) --> Hash Table-dan birbaşa tap --> O(1)
   A[2] --> hash(A[2].key) --> Hash Table-dan birbaşa tap --> O(1)
   ...
   
   Mürəkkəblik: O(n + m)
   Ən yaxşı: Böyük cədvəllər, equality join (= şərti)
   Məhdudiyyət: work_mem yetərsiz olduqda disk-ə yazılır

3. Merge Join:
   Hər iki cədvəl sıralanır, sonra paralel scan edilir:
   
   Sorted A: [1, 3, 5, 7, 9]      Sorted B: [2, 3, 5, 6, 9]
              ^                               ^
              |   3 == 3 --> MATCH!            |
              |-->^                        ^<--|
              |       5 == 5 --> MATCH!    |
              |------>^                ^<--|
              |           9 == 9 --> MATCH!
              
   Mürəkkəblik: O(n*log(n) + m*log(m)) sıralama + O(n+m) birləşmə
   Ən yaxşı: Hər iki cədvəl artıq sıralıdır (index üzrə)

+------------------+--------------------+---------------------+-------------------+
|   Join Tipi      |  Ən Yaxşı Hal      |  Ən Pis Hal         |  Yaddaş İstifadəsi|
+------------------+--------------------+---------------------+-------------------+
| Nested Loop      | Kiçik outer +      | Böyük cədvəllər,    | Minimal           |
|                  | indexed inner      | index yoxdur        |                   |
+------------------+--------------------+---------------------+-------------------+
| Hash Join        | Böyük cədvəllər,   | Hash table yaddaşa  | work_mem qədər    |
|                  | equality join      | sığmır (disk spill)  | (və ya disk)      |
+------------------+--------------------+---------------------+-------------------+
| Merge Join       | Hər iki tərəf      | Sıralama lazımdır    | Sort üçün         |
|                  | sıralıdır          | (böyük sort cost)    | work_mem          |
+------------------+--------------------+---------------------+-------------------+
```

### EXPLAIN ANALYZE Nümunəsi (Ətraflı)

```sql
-- PostgreSQL
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT 
    o.id, o.total_amount, o.created_at,
    u.name, u.email,
    COUNT(oi.id) as item_count
FROM orders o
JOIN users u ON u.id = o.user_id
JOIN order_items oi ON oi.order_id = o.id
WHERE o.created_at >= '2025-01-01'
  AND o.status = 'completed'
GROUP BY o.id, o.total_amount, o.created_at, u.name, u.email
ORDER BY o.total_amount DESC
LIMIT 100;
```

```
Nəticə (annotasiyalı):

Limit  (cost=45123.45..45123.70 rows=100 width=72)
       (actual time=3421.123..3421.456 rows=100 loops=1)
       Buffers: shared hit=12543 read=8765
  -> Sort  (cost=45123.45..45334.56 rows=84445 width=72)
           (actual time=3421.120..3421.345 rows=100 loops=1)
           Sort Key: o.total_amount DESC
           Sort Method: top-N heapsort  Memory: 45kB
     -> HashAggregate  (cost=38765.43..39609.88 rows=84445 width=72)
                       (actual time=2987.654..3345.678 rows=84445 loops=1)
                       Group Key: o.id, o.total_amount, o.created_at, u.name, u.email
                       Batches: 1  Memory Usage: 24577kB
        -> Hash Join  (cost=3456.78..35123.45 rows=234567 width=64)
                      (actual time=123.456..1876.543 rows=234567 loops=1)
                      Hash Cond: (oi.order_id = o.id)
                      Buffers: shared hit=8765 read=5432
           -> Seq Scan on order_items oi  (cost=0.00..18765.00 rows=1000000 width=12)
                                          (actual time=0.012..456.789 rows=1000000 loops=1)
                                          Buffers: shared hit=5432 read=3210
           -> Hash  (cost=2987.65..2987.65 rows=84445 width=60)
                    (actual time=123.321..123.321 rows=84445 loops=1)
                    Buckets: 131072  Batches: 1  Memory Usage: 7654kB
              -> Hash Join  (cost=1234.56..2987.65 rows=84445 width=60)
                            (actual time=45.678..98.765 rows=84445 loops=1)
                            Hash Cond: (o.user_id = u.id)
                 -> Index Scan using idx_orders_created_status on orders o
                            (cost=0.43..1654.32 rows=84445 width=28)
                            (actual time=0.023..34.567 rows=84445 loops=1)
                            Index Cond: (created_at >= '2025-01-01')
                            Filter: (status = 'completed')
                            Rows Removed by Filter: 15678
                            Buffers: shared hit=2345 read=1234
                 -> Hash  (cost=876.00..876.00 rows=50000 width=36)
                          (actual time=45.123..45.123 rows=50000 loops=1)
                          Buckets: 65536  Batches: 1  Memory Usage: 3456kB
                    -> Seq Scan on users u  (cost=0.00..876.00 rows=50000 width=36)
                                            (actual time=0.008..23.456 rows=50000 loops=1)
                                            Buffers: shared hit=876

Planning Time: 2.345 ms
Execution Time: 3425.678 ms
```

```
Yuxarıdakı Planın Oxunma Qaydası (Aşağıdan Yuxarıya):

Addım 1: users cədvəlində Seq Scan (50K sətir - kiçik cədvəl, Seq Scan normaldır)
    |
Addım 2: users üçün Hash Table yaradılır (3.4MB yaddaş)
    |
Addım 3: orders cədvəlində Index Scan (idx_orders_created_status)
    |     84K sətir tapılır, 15K sətir filter ilə atılır
    |     --> PROBLEM: Filter ilə atılan sətrlər çoxdur!
    |         Həll: Composite index (created_at, status) əlavə et
    |
Addım 4: orders + users Hash Join (84K sətir)
    |
Addım 5: order_items-də Seq Scan (1M sətir!)
    |     --> PROBLEM: Full table scan! 
    |         Həll: order_items.order_id üzrə index əlavə et
    |
Addım 6: order_items + (orders+users) Hash Join (234K sətir)
    |
Addım 7: HashAggregate - GROUP BY (84K qrup)
    |
Addım 8: Sort - ORDER BY total_amount DESC
    |     top-N heapsort istifadə olunur (yalnız 100 lazımdır - effektiv)
    |
Addım 9: Limit 100

Ümumi vaxt: 3425ms
Ən çox vaxt: order_items Seq Scan (456ms) + Hash Join (1876ms)
```

### EXPLAIN ANALYZE Əsas Metriklər

```
EXPLAIN ANALYZE çıxışında diqqət ediləcək sahələr:

+------------------------+---------------------------------------------------+
|        Metrik          |                     İzah                          |
+------------------------+---------------------------------------------------+
| cost=X..Y              | X = başlama xərci, Y = tam xərc (şərti vahid)    |
| actual time=X..Y       | X = ilk sətirə vaxt, Y = bütün sətirlər (ms)     |
| rows=N                 | Gözlənilən/actual sətir sayı                      |
| loops=N                | Bu node neçə dəfə icra olunub                     |
| Buffers: shared hit    | Yaddaşdan (cache) oxunan page sayı                |
| Buffers: shared read   | Diskdən oxunan page sayı (yavaş!)                 |
| Rows Removed by Filter | İndeksdən sonra filter ilə atılan sətrlər         |
| Sort Method            | quicksort, top-N heapsort, external merge          |
| Memory Usage           | İstifadə olunan yaddaş                             |
| Batches                | Hash table neçə hissəyə bölünüb (1=yaddaşa sığır) |
+------------------------+---------------------------------------------------+

Qırmızı Bayraqlar (Red Flags):
 [!] actual rows >> estimated rows  --> Statistika köhnədir (ANALYZE çalışdır)
 [!] Seq Scan on böyük cədvəl       --> İndeks əlavə et
 [!] Rows Removed by Filter çoxdur  --> Daha yaxşı indeks lazımdır
 [!] Buffers: shared read çoxdur    --> Yaddaş yetərsizdir və ya cold cache
 [!] Sort Method: external merge    --> work_mem artır və ya sorğunu dəyiş
 [!] Batches > 1                    --> Hash table yaddaşa sığmır, work_mem artır
 [!] Nested Loop + Seq Scan         --> İndeks əlavə et (inner table-a)
```

---

## MySQL PROFILING

MySQL PROFILING sorğunun icrasının hər mərhələsini ətraflı göstərir. Bu, sorğunun harada vaxt itirdiyini dəqiq müəyyən etməyə kömək edir.

### Əsas İstifadə

```sql
-- Profiling-i aktivləşdir (cari sessiya üçün)
SET profiling = 1;

-- Sorğu icra et
SELECT o.*, u.name
FROM orders o
JOIN users u ON u.id = o.user_id
WHERE o.created_at >= '2025-01-01'
ORDER BY o.total_amount DESC
LIMIT 100;

-- İcra olunan sorğuların siyahısı
SHOW PROFILES;

-- Nəticə:
-- +----------+------------+------------------------------------------+
-- | Query_ID | Duration   | Query                                    |
-- +----------+------------+------------------------------------------+
-- |        1 | 0.00012300 | SET profiling = 1                        |
-- |        2 | 2.34567800 | SELECT o.*, u.name FROM orders o ...     |
-- |        3 | 0.00034500 | SHOW WARNINGS                            |
-- +----------+------------+------------------------------------------+

-- Müəyyən sorğunun detallı profili
SHOW PROFILE FOR QUERY 2;

-- Nəticə:
-- +------------------------+-----------+
-- | Status                 | Duration  |
-- +------------------------+-----------+
-- | starting               | 0.000123  |
-- | checking permissions   | 0.000012  |
-- | Opening tables         | 0.000045  |
-- | init                   | 0.000034  |
-- | System lock            | 0.000008  |
-- | optimizing             | 0.000056  |
-- | statistics             | 0.000234  |
-- | preparing              | 0.000023  |
-- | Sorting result         | 0.000012  |
-- | executing              | 0.000003  |
-- | Sending data           | 2.344567  |  <-- Əsas vaxt burada!
-- | end                    | 0.000012  |
-- | query end              | 0.000008  |
-- | closing tables         | 0.000015  |
-- | freeing items          | 0.000234  |
-- | cleaning up            | 0.000012  |
-- +------------------------+-----------+

-- CPU və Block I/O məlumatları ilə
SHOW PROFILE CPU, BLOCK IO FOR QUERY 2;

-- Nəticə:
-- +------------------------+-----------+-----------+------------+--------------+--------------+
-- | Status                 | Duration  | CPU_user  | CPU_system | Block_ops_in | Block_ops_out|
-- +------------------------+-----------+-----------+------------+--------------+--------------+
-- | starting               | 0.000123  | 0.000089  | 0.000023   |            0 |            0 |
-- | Sending data           | 2.344567  | 1.234567  | 0.345678   |        12456 |          234 |
-- | Sorting result         | 0.123456  | 0.098765  | 0.012345   |          456 |           12 |
-- +------------------------+-----------+-----------+------------+--------------+--------------+

-- Bütün məlumatlar
SHOW PROFILE ALL FOR QUERY 2;

-- Profiling-i söndür
SET profiling = 0;
```

```
SHOW PROFILE Status Sahələrinin İzahı:

+-------------------------+----------------------------------------------------------+
|       Status            |                      İzah                                |
+-------------------------+----------------------------------------------------------+
| starting                | Sorğunun başlaması                                       |
| checking permissions    | İstifadəçi icazələrinin yoxlanması                       |
| Opening tables          | Cədvəllərin açılması                                     |
| init                    | Sorğu planlaşdırıcısının inisializasiyası                |
| System lock             | Sistem lock-unun alınması                                |
| optimizing              | Sorğunun optimallaşdırılması                             |
| statistics              | Statistika məlumatlarının toplanması                     |
| preparing               | İcra planının hazırlanması                               |
| executing               | Sorğunun icra olunması                                   |
| Sending data            | Nəticələrin oxunması və klientə göndərilməsi             |
|                         | (ən çox vaxt burada keçir!)                              |
| Sorting result          | Nəticələrin sıralanması                                  |
| Creating tmp table      | Müvəqqəti cədvəlin yaradılması (problem əlaməti!)        |
| Copying to tmp table    | Müvəqqəti cədvələ kopyalama (problem əlaməti!)           |
| on disk                 | Müvəqqəti cədvəl diskə yazılır (CİDDİ PROBLEM!)         |
| query end               | Sorğunun bitməsi                                         |
| freeing items           | Resursların azad edilməsi                                |
+-------------------------+----------------------------------------------------------+
```

### Performance Schema (MySQL 5.7+/8.0 - Profiling əvəzi)

```sql
-- Performance Schema daha müasir və ətraflı alternativdir

-- Stage hadisələrini aktiv et
UPDATE performance_schema.setup_instruments 
SET ENABLED = 'YES', TIMED = 'YES' 
WHERE NAME LIKE 'stage/%';

UPDATE performance_schema.setup_consumers 
SET ENABLED = 'YES' 
WHERE NAME LIKE '%stage%';

-- Sorğu icra et
SELECT o.*, u.name FROM orders o JOIN users u ON u.id = o.user_id LIMIT 100;

-- Son sorğunun stage-lərini gör
SELECT 
    EVENT_NAME AS stage,
    ROUND(TIMER_WAIT / 1000000000, 6) AS duration_ms,
    ROUND(TIMER_WAIT / (SELECT SUM(TIMER_WAIT) 
        FROM performance_schema.events_stages_history_long 
        WHERE THREAD_ID = ps.THREAD_ID AND EVENT_ID > ps.EVENT_ID - 20
    ) * 100, 2) AS percentage
FROM performance_schema.events_stages_history_long ps
WHERE THREAD_ID = (SELECT THREAD_ID FROM performance_schema.threads 
                   WHERE PROCESSLIST_ID = CONNECTION_ID())
ORDER BY EVENT_ID DESC
LIMIT 20;

-- Ən çox vaxt alan stage-lər (bütün sorğular üzrə)
SELECT 
    EVENT_NAME,
    COUNT_STAR AS total_count,
    ROUND(SUM_TIMER_WAIT / 1000000000, 2) AS total_ms,
    ROUND(AVG_TIMER_WAIT / 1000000000, 2) AS avg_ms,
    ROUND(MAX_TIMER_WAIT / 1000000000, 2) AS max_ms
FROM performance_schema.events_stages_summary_global_by_event_name
WHERE SUM_TIMER_WAIT > 0
ORDER BY SUM_TIMER_WAIT DESC
LIMIT 10;
```

---

## PostgreSQL pg_stat_statements

`pg_stat_statements` PostgreSQL-in ən güclü sorğu monitorinq moduludur. Bütün sorğuların statistikasını toplayır və ən problemli olanları tapmağa kömək edir.

### Quraşdırma və Konfiqurasiya

```ini
# postgresql.conf
shared_preload_libraries = 'pg_stat_statements'

# pg_stat_statements parametrləri
pg_stat_statements.max = 10000          # Ən çox izlənilən unikal sorğu sayı
pg_stat_statements.track = top          # top, all, none
pg_stat_statements.track_utility = on   # DDL/utility sorğuları da izlə
pg_stat_statements.track_planning = on  # Planlaşdırma vaxtını da izlə (PG 13+)
pg_stat_statements.save = on            # Restart-da statistikanı saxla
```

```sql
-- Extension-u yarat
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

-- PostgreSQL-i restart et (shared_preload_libraries dəyişikliyi üçün)
-- sudo systemctl restart postgresql

-- Statistikanı sıfırla
SELECT pg_stat_statements_reset();
```

### Əsas Sorğular

```sql
-- 1. Ən çox vaxt alan sorğular (Top Time Consumers)
SELECT 
    LEFT(query, 100) AS short_query,
    calls,
    ROUND(total_exec_time::numeric, 2) AS total_ms,
    ROUND(mean_exec_time::numeric, 2) AS avg_ms,
    ROUND(max_exec_time::numeric, 2) AS max_ms,
    ROUND(stddev_exec_time::numeric, 2) AS stddev_ms,
    rows,
    ROUND((100.0 * total_exec_time / 
        SUM(total_exec_time) OVER ())::numeric, 2) AS pct_total_time
FROM pg_stat_statements
WHERE userid = (SELECT usesysid FROM pg_user WHERE usename = 'laravel_user')
ORDER BY total_exec_time DESC
LIMIT 20;

-- 2. Ən yavaş orta vaxtlı sorğular
SELECT 
    LEFT(query, 120) AS short_query,
    calls,
    ROUND(mean_exec_time::numeric, 2) AS avg_ms,
    ROUND(min_exec_time::numeric, 2) AS min_ms,
    ROUND(max_exec_time::numeric, 2) AS max_ms,
    rows AS total_rows,
    ROUND((rows::numeric / NULLIF(calls, 0)), 2) AS avg_rows
FROM pg_stat_statements
WHERE calls > 10  -- Ən az 10 dəfə çağırılmış
ORDER BY mean_exec_time DESC
LIMIT 20;

-- 3. Ən çox I/O istifadə edən sorğular (buffer hit vs read)
SELECT 
    LEFT(query, 100) AS short_query,
    calls,
    shared_blks_hit AS cache_hits,
    shared_blks_read AS disk_reads,
    ROUND(
        100.0 * shared_blks_hit / 
        NULLIF(shared_blks_hit + shared_blks_read, 0), 2
    ) AS cache_hit_ratio,
    temp_blks_written AS temp_disk_writes
FROM pg_stat_statements
WHERE shared_blks_read > 100
ORDER BY shared_blks_read DESC
LIMIT 20;

-- 4. N+1 problem şübhəlisi (çox çağırılan, az nəticə qaytaran)
SELECT 
    LEFT(query, 120) AS short_query,
    calls,
    ROUND(mean_exec_time::numeric, 2) AS avg_ms,
    ROUND(total_exec_time::numeric, 2) AS total_ms,
    ROUND((rows::numeric / NULLIF(calls, 0)), 2) AS avg_rows_per_call
FROM pg_stat_statements
WHERE calls > 1000
  AND ROUND((rows::numeric / NULLIF(calls, 0)), 2) <= 1
ORDER BY calls DESC
LIMIT 20;

-- 5. Planlaşdırma vaxtı yüksək olan sorğular (PG 13+)
SELECT 
    LEFT(query, 100) AS short_query,
    calls,
    ROUND(mean_plan_time::numeric, 2) AS avg_plan_ms,
    ROUND(mean_exec_time::numeric, 2) AS avg_exec_ms,
    ROUND((mean_plan_time / NULLIF(mean_plan_time + mean_exec_time, 0) * 100)::numeric, 2) 
        AS plan_pct
FROM pg_stat_statements
WHERE mean_plan_time > 1  -- Planlaşdırma 1ms-dən çox
ORDER BY mean_plan_time DESC
LIMIT 10;

-- 6. Temp disk istifadəsi yüksək olan sorğular (sort/hash problem)
SELECT 
    LEFT(query, 100) AS short_query,
    calls,
    temp_blks_read,
    temp_blks_written,
    ROUND(mean_exec_time::numeric, 2) AS avg_ms
FROM pg_stat_statements
WHERE temp_blks_written > 0
ORDER BY temp_blks_written DESC
LIMIT 10;

-- 7. WAL generasiyası ən çox olan sorğular (PG 13+, write-heavy)
SELECT 
    LEFT(query, 100) AS short_query,
    calls,
    wal_records,
    wal_bytes,
    pg_size_pretty(wal_bytes) AS wal_size
FROM pg_stat_statements
WHERE wal_bytes > 0
ORDER BY wal_bytes DESC
LIMIT 10;
```

```
pg_stat_statements ilə Tipik Analiz İş Axını:

  +-----------------+
  | 1. Statistikanı |
  |    Sıfırla      |
  +--------+--------+
           |
  +--------v--------+
  | 2. Normal yükü  |
  |    gözlə        |
  |    (1-24 saat)   |
  +--------+--------+
           |
  +--------v---------+     +-------------------+
  | 3. Top sorğuları |---->| Çox çağırılan?    |---> N+1 problem
  |    analiz et     |     | (calls > 10000)   |     Eager loading
  +--------+---------+     +-------------------+
           |
           |               +-------------------+
           +-------------->| Yavaş orta vaxt?  |---> EXPLAIN ANALYZE
                           | (avg_ms > 100)    |     İndeks əlavə et
                           +-------------------+
           |
           |               +-------------------+
           +-------------->| Cache hit az?     |---> shared_buffers artır
                           | (ratio < 99%)     |     və ya indeks problem
                           +-------------------+
           |
           |               +-------------------+
           +-------------->| Temp disk var?    |---> work_mem artır
                           | (temp_blks > 0)   |     Sorğunu sadələşdir
                           +-------------------+
```

---

## Laravel Query Log və Debug

### DB::enableQueryLog

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function index()
    {
        // Query log-u aktivləşdir
        DB::enableQueryLog();
        
        // Sorğuları icra et
        $orders = Order::with(['user', 'items'])
            ->where('status', 'completed')
            ->where('created_at', '>=', '2025-01-01')
            ->orderByDesc('total_amount')
            ->paginate(20);
        
        // Logları al
        $queries = DB::getQueryLog();
        
        // Logları göstər/yaz
        foreach ($queries as $query) {
            Log::debug('SQL Query', [
                'sql'      => $query['query'],
                'bindings' => $query['bindings'],
                'time_ms'  => $query['time'],
            ]);
        }
        
        // Ümumi statistika
        $totalTime = collect($queries)->sum('time');
        $queryCount = count($queries);
        
        Log::info("Request query stats", [
            'total_queries' => $queryCount,
            'total_time_ms' => $totalTime,
            'avg_time_ms'   => $queryCount > 0 ? round($totalTime / $queryCount, 2) : 0,
        ]);
        
        // Query log-u söndür (yaddaş istehlakını azaltmaq üçün)
        DB::disableQueryLog();
        DB::flushQueryLog();
        
        return view('orders.index', compact('orders'));
    }
}
```

### DB::listen ilə Real-Time Monitoring

```php
<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class QueryLogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Yalnız development/staging mühitlərində
        if (! app()->environment('production')) {
            $this->enableDetailedQueryLogging();
        }
        
        // Production-da yalnız yavaş sorğuları logla
        if (app()->environment('production')) {
            $this->enableSlowQueryLogging();
        }
    }
    
    private function enableDetailedQueryLogging(): void
    {
        DB::listen(function (QueryExecuted $event) {
            $sql = $event->sql;
            $bindings = $event->bindings;
            $timeMs = $event->time;
            $connection = $event->connectionName;
            
            // Prepared statement-i tam SQL-ə çevir
            $fullSql = $this->interpolateQuery($sql, $bindings);
            
            // Rəng kodları log-da
            $level = match (true) {
                $timeMs > 1000 => 'error',    // 1s+ --> Qırmızı
                $timeMs > 500  => 'warning',  // 500ms+ --> Sarı
                $timeMs > 100  => 'notice',   // 100ms+ --> Mavi
                default        => 'debug',    // Normal
            };
            
            Log::channel('query')->{$level}("Query [{$connection}] ({$timeMs}ms)", [
                'sql'      => $fullSql,
                'time_ms'  => $timeMs,
                'caller'   => $this->getCallerInfo(),
            ]);
        });
    }
    
    private function enableSlowQueryLogging(): void
    {
        $threshold = config('database.slow_query_threshold', 500); // ms
        
        DB::listen(function (QueryExecuted $event) use ($threshold) {
            if ($event->time >= $threshold) {
                Log::channel('slow-queries')->warning("Slow Query ({$event->time}ms)", [
                    'sql'        => $event->sql,
                    'bindings'   => $event->bindings,
                    'time_ms'    => $event->time,
                    'connection' => $event->connectionName,
                    'caller'     => $this->getCallerInfo(),
                    'request'    => [
                        'url'    => request()->fullUrl(),
                        'method' => request()->method(),
                        'ip'     => request()->ip(),
                    ],
                ]);
            }
        });
    }
    
    private function interpolateQuery(string $sql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            $value = match (true) {
                is_null($binding)   => 'NULL',
                is_bool($binding)   => $binding ? 'TRUE' : 'FALSE',
                is_int($binding)    => (string) $binding,
                is_float($binding)  => (string) $binding,
                default             => "'" . addslashes((string) $binding) . "'",
            };
            
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }
        
        return $sql;
    }
    
    private function getCallerInfo(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            
            // Framework fayllarını keç, tətbiq kodunu tap
            if (
                str_contains($file, '/app/') &&
                ! str_contains($file, '/Providers/') &&
                ! str_contains($file, '/vendor/')
            ) {
                return basename($file) . ':' . ($frame['line'] ?? '?');
            }
        }
        
        return 'unknown';
    }
}
```

### Laravel Telescope

```php
<?php

// config/telescope.php - Telescope konfiqurasiyası
return [
    'enabled' => env('TELESCOPE_ENABLED', true),
    
    'watchers' => [
        // Sorğu izləmə
        \Laravel\Telescope\Watchers\QueryWatcher::class => [
            'enabled' => true,
            'slow'    => 100, // 100ms-dən yavaş sorğuları "slow" kimi işarələ
        ],
        
        // Model izləmə (N+1 aşkarlaması)
        \Laravel\Telescope\Watchers\ModelWatcher::class => [
            'enabled'  => true,
            'events'   => ['eloquent.*'],
            'hydrations' => true, // Model yaradılmasını izlə
        ],
        
        // Request izləmə
        \Laravel\Telescope\Watchers\RequestWatcher::class => [
            'enabled'    => true,
            'size_limit' => 64, // KB
        ],
        
        // Cache izləmə
        \Laravel\Telescope\Watchers\CacheWatcher::class => [
            'enabled' => true,
        ],
    ],
    
    // Yalnız yavaş sorğuları saxla (production üçün)
    'filter_using' => [
        // App\Telescope\Filters\SlowQueryFilter::class,
    ],
];
```

```php
<?php

// Telescope filtri - yalnız problemli sorğuları saxla
namespace App\Telescope\Filters;

use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

class SlowQueryFilter
{
    public function __invoke(IncomingEntry $entry): bool
    {
        // Query tipli girişlər üçün
        if ($entry->type === 'query') {
            // 100ms-dən yavaş sorğuları saxla
            return ($entry->content['time'] ?? 0) >= 100;
        }
        
        // Request tipli girişlər üçün
        if ($entry->type === 'request') {
            // 1 saniyədən uzun requestləri saxla
            return ($entry->content['duration'] ?? 0) >= 1000;
        }
        
        // Xətaları həmişə saxla
        if ($entry->type === 'exception') {
            return true;
        }
        
        return false;
    }
}
```

### Laravel Debugbar

```php
<?php

// composer require barryvdh/laravel-debugbar --dev

// config/debugbar.php
return [
    'enabled' => env('DEBUGBAR_ENABLED', false),
    
    'collectors' => [
        'db'        => true,  // Sorğu kollektoru
        'models'    => true,  // Model kollektoru  
        'time'      => true,  // Vaxt kollektoru
        'memory'    => true,  // Yaddaş kollektoru
        'cache'     => true,  // Cache kollektoru
    ],
    
    'options' => [
        'db' => [
            'with_params'       => true,   // Parametrləri göstər
            'backtrace'         => true,   // Çağırış yığınını göstər
            'backtrace_limit'   => 10,     // Yığın dərinliyi
            'timeline'          => true,   // Zaman xətti
            'duration_background' => true, // Vaxt rəngləri
            'explain' => [
                'enabled' => true,         // EXPLAIN avtomatik göstər
                'types'   => ['SELECT'],   // Hansı sorğu tipləri üçün
            ],
            'hints'             => true,   // Optimallaşdırma təklifləri
            'show_copy'         => true,   // Sorğu kopyalama düyməsi
            'slow_threshold'    => 500,    // ms - yavaş sorğu həddi
        ],
    ],
];
```

```
Laravel Debug Alətlərinin Müqayisəsi:

+------------------------+------------------+-----------------+------------------+
|     Xüsusiyyət         | DB::queryLog     |   Telescope     |    Debugbar      |
+------------------------+------------------+-----------------+------------------+
| Mühit                  | Bütün            | Dev/Staging     | Yalnız Dev       |
| UI                     | Yoxdur           | Web Panel       | Browser Bar      |
| N+1 aşkarlama         | Manual           | Avtomatik       | Avtomatik        |
| EXPLAIN göstərmə       | Manual           | Manual          | Avtomatik        |
| Sorğu zamanxətti       | Yoxdur           | Bəli            | Bəli             |
| Yaddaş istifadəsi      | Yüksək*          | Orta (DB)       | Orta             |
| API sorğuları           | Bəli             | Bəli            | Xeyr (HTML only) |
| Persistent saxlama     | Yoxdur           | Bəli (DB)       | Yoxdur           |
| Production uyğunluq    | Diqqətlə         | Filtrlə bəli    | Xeyr             |
+------------------------+------------------+-----------------+------------------+

* DB::enableQueryLog bütün sorğuları yaddaşda saxlayır!
  Uzunmüddətli proseslərdə (queue worker) yaddaş sızıntısına səbəb ola bilər.
  Həmişə DB::flushQueryLog() ilə təmizləyin.
```

---

## Real-World Nümunələr

### 1. N+1 Probleminin Aşkarlanması və Həlli

```php
<?php

// PROBLEM: N+1 Query
// Slow query log-da eyni sorğunun yüzlərlə dəfə təkrarlandığını görürsünüz:

// Slow Query Log girişi:
// # Query_time: 0.002345 Lock_time: 0.000012 Rows_sent: 1 Rows_examined: 1
// SELECT * FROM users WHERE id = 42;
// (bu sorğu 500 dəfə təkrarlanır!)

// Problemli kod:
class OrderController extends Controller
{
    // YANLIŞ - N+1 problem
    public function index()
    {
        $orders = Order::where('status', 'completed')
            ->latest()
            ->paginate(50);
        
        // Hər order üçün ayrı user sorğusu (50 əlavə sorğu!)
        // Blade template-də:
        // @foreach($orders as $order)
        //     {{ $order->user->name }}     <-- Hər dəfə SELECT * FROM users
        //     {{ $order->user->email }}
        //     @foreach($order->items as $item)  <-- 50 x items sorğusu!
        //         {{ $item->product->name }}     <-- 50 x N product sorğusu!
        //     @endforeach
        // @endforeach
        
        return view('orders.index', compact('orders'));
    }
    
    // DOĞRU - Eager Loading
    public function indexOptimized()
    {
        $orders = Order::with([
            'user:id,name,email',           // Yalnız lazımi sütunlar
            'items:id,order_id,product_id,quantity,price',
            'items.product:id,name,sku',    // Nested eager loading
        ])
        ->where('status', 'completed')
        ->latest()
        ->paginate(50);
        
        // İndi yalnız 4 sorğu icra olunur:
        // 1. SELECT * FROM orders WHERE status = 'completed' ORDER BY... LIMIT 50
        // 2. SELECT id, name, email FROM users WHERE id IN (1, 2, 3, ..., 50)
        // 3. SELECT id, order_id, product_id, ... FROM order_items WHERE order_id IN (...)
        // 4. SELECT id, name, sku FROM products WHERE id IN (...)
        
        return view('orders.index', compact('orders'));
    }
}

// N+1 problemini avtomatik aşkar etmək üçün:
// AppServiceProvider boot() metodu:
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    // Development mühitində lazy loading-ə icazə vermə
    Model::preventLazyLoading(! app()->isProduction());
    
    // Production-da lazy loading-i logla (exception atmadan)
    Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
        Log::warning("N+1 Query Detected", [
            'model'    => get_class($model),
            'relation' => $relation,
            'caller'   => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
        ]);
    });
}
```

```
N+1 Problem Vizuallaşdırma:

YANLIŞ (N+1): 51 sorğu, ~120ms
  Sorğu 1:  SELECT * FROM orders LIMIT 50                    -- 5ms
  Sorğu 2:  SELECT * FROM users WHERE id = 1                 -- 2ms
  Sorğu 3:  SELECT * FROM users WHERE id = 2                 -- 2ms
  Sorğu 4:  SELECT * FROM users WHERE id = 3                 -- 2ms
  ...       ...
  Sorğu 51: SELECT * FROM users WHERE id = 50                -- 2ms
  Toplam:   1 + 50 = 51 sorğu

DOĞRU (Eager Loading): 2 sorğu, ~8ms  
  Sorğu 1:  SELECT * FROM orders LIMIT 50                    -- 5ms
  Sorğu 2:  SELECT * FROM users WHERE id IN (1,2,3,...,50)   -- 3ms
  Toplam:   2 sorğu

  Performans qazancı: ~15x daha sürətli!
  
  Daha dərin N+1 (orders -> items -> products):
  
  YANLIŞ: 1 + 50 + (50 * avg_items) + (50 * avg_items) sorğu
          = 1 + 50 + 150 + 150 = 351 sorğu!
          
  DOĞRU:  1 + 1 + 1 + 1 = 4 sorğu
```

### 2. Missing Index Aşkarlanması

```php
<?php

// Slow query log-da bu sorğu görünür:
// # Query_time: 4.567890 Lock_time: 0.000012 Rows_sent: 15 Rows_examined: 2500000
// SELECT * FROM orders WHERE status = 'pending' AND created_at > '2025-01-01';
//
// DİQQƏT: Rows_examined (2.5M) >> Rows_sent (15)
// Bu, full table scan əlamətidir!

// EXPLAIN nəticəsi:
// +----+------+------+---------+------+---------+----------+----------+-------------+
// | id | type | table| key     | rows | filtered| Extra                              |
// +----+------+------+---------+------+---------+----------+----------+-------------+
// |  1 | ALL  | orders| NULL   | 2500000| 0.6  | Using where; Using filesort         |
// +----+------+------+---------+------+---------+----------+----------+-------------+
// type=ALL --> Full table scan!
// key=NULL --> Heç bir indeks istifadə olunmur!

// Həll 1: Migration ilə composite index əlavə et
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Composite index - sıra vacibdir!
            // status birinci (equality), created_at ikinci (range)
            $table->index(['status', 'created_at'], 'idx_orders_status_created');
        });
    }
    
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_status_created');
        });
    }
};

// Həll 2: Partial index (PostgreSQL) - daha effektiv
// Yalnız 'pending' statuslu sifarişlər üçün
DB::statement("
    CREATE INDEX CONCURRENTLY idx_orders_pending_created 
    ON orders (created_at) 
    WHERE status = 'pending'
");

// İndeksdən sonra EXPLAIN nəticəsi:
// +----+-------+------+------------------------+------+----------+-------------+
// | id | type  | table| key                    | rows | filtered | Extra       |
// +----+-------+------+------------------------+------+----------+-------------+
// |  1 | range | orders| idx_orders_status_created| 15 | 100.00  | Using index |
// +----+-------+------+------------------------+------+----------+-------------+
// Rows_examined: 15 (əvvəlki 2.5M əvəzinə!)
```

```
İndeks Seçimi Qərar Ağacı:

Sorğunuza uyğun indeks necə seçilir?

  +---------------------------+
  | WHERE şərtlərini analiz et|
  +---------------------------+
            |
     +------v------+
     | Equality    |     Equality (=, IN) sütunları indeksdə
     | şərti var?  |---> BİRİNCİ olmalıdır
     +------+------+
            |
     +------v------+
     | Range       |     Range (<, >, BETWEEN) sütunları
     | şərti var?  |---> İKİNCİ olmalıdır
     +------+------+
            |
     +------v------+
     | ORDER BY    |     ORDER BY sütunu range-dən
     | var?        |---> SONRA olmalıdır (əgər mümkünsə)
     +------+------+
            |
     +------v------+
     | SELECT      |     Covering index üçün SELECT
     | sütunları?  |---> sütunlarını da əlavə et (INCLUDE)
     +------+------+

  Misal: WHERE status = 'pending' AND created_at > '2025-01-01' ORDER BY total DESC
  
  İdeal indeks: (status, created_at, total)
                 equality  range       sort
                 
  PostgreSQL INCLUDE ilə:
  CREATE INDEX idx ON orders (status, created_at, total) INCLUDE (id, user_id);
```

### 3. Full Table Scan Problemləri

```php
<?php

// PROBLEM 1: LIKE '%...%' ilə axtarış (indeks istifadə edə bilmir)
// Slow query: SELECT * FROM products WHERE name LIKE '%laptop%'
// Full table scan! 

// HƏLL: Full-text search istifadə et
class ProductSearchService
{
    // MySQL Full-Text Search
    public function searchMySQL(string $term): Collection
    {
        return Product::whereRaw(
            "MATCH(name, description) AGAINST(? IN BOOLEAN MODE)",
            [$term]
        )->get();
    }
    
    // PostgreSQL Full-Text Search
    public function searchPostgreSQL(string $term): Collection
    {
        return Product::whereRaw(
            "to_tsvector('english', name || ' ' || description) @@ plainto_tsquery('english', ?)",
            [$term]
        )->get();
    }
    
    // Ən yaxşı həll: Scout + Meilisearch/Algolia
    // Product modelinə Searchable trait əlavə et
}

// PROBLEM 2: Funksiya istifadəsi indeksi əlil edir
// YANLIŞ: WHERE YEAR(created_at) = 2025 --> Full table scan!
$orders = Order::whereRaw('YEAR(created_at) = 2025')->get();

// DOĞRU: Range query istifadə et --> İndeks istifadə olunur
$orders = Order::whereBetween('created_at', [
    '2025-01-01 00:00:00',
    '2025-12-31 23:59:59',
])->get();

// PROBLEM 3: OR şərti ilə indeks problemi
// YANLIŞ: İndeks istifadə olunmaya bilər
$results = Order::where('status', 'pending')
    ->orWhere('priority', 'high')
    ->get();

// DOĞRU: UNION istifadə et
$pending = Order::where('status', 'pending');
$highPriority = Order::where('priority', 'high');
$results = $pending->union($highPriority)->get();

// PROBLEM 4: Böyük IN() siyahıları
// YANLIŞ: 10000 ID ilə IN clause
$orders = Order::whereIn('id', $hugeArrayOfIds)->get(); // Yavaş!

// DOĞRU: Chunk ilə işlə
$results = collect();
foreach (array_chunk($hugeArrayOfIds, 500) as $chunk) {
    $results = $results->merge(
        Order::whereIn('id', $chunk)->get()
    );
}

// Və ya temporary table ilə join
DB::statement('CREATE TEMPORARY TABLE tmp_ids (id BIGINT PRIMARY KEY)');
foreach (array_chunk($hugeArrayOfIds, 1000) as $chunk) {
    $values = implode(',', array_map(fn($id) => "($id)", $chunk));
    DB::statement("INSERT INTO tmp_ids VALUES $values");
}
$orders = Order::join('tmp_ids', 'orders.id', '=', 'tmp_ids.id')->get();
DB::statement('DROP TEMPORARY TABLE tmp_ids');
```

### 4. Yavaş Aggregation Sorğuları

```php
<?php

// Slow query log-da:
// # Query_time: 12.345678 Rows_examined: 5000000
// SELECT DATE(created_at) as date, COUNT(*), SUM(total_amount)
// FROM orders
// WHERE created_at >= '2025-01-01'
// GROUP BY DATE(created_at)
// ORDER BY date;

// PROBLEM: Hər gün üçün 5M sətir scan edir

// HƏLL 1: Summary cədvəli (materialized view)
class DailyOrderSummary extends Model
{
    protected $table = 'daily_order_summaries';
    protected $fillable = ['date', 'order_count', 'total_revenue', 'avg_order_value'];
}

// Cron job ilə gündəlik yenilə
class UpdateDailyOrderSummary extends Command
{
    protected $signature = 'orders:update-daily-summary {--date=}';
    
    public function handle(): void
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();
        
        $stats = DB::table('orders')
            ->whereDate('created_at', $date)
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as order_count,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order_value
            ')
            ->first();
        
        DailyOrderSummary::updateOrCreate(
            ['date' => $date],
            [
                'order_count'     => $stats->order_count,
                'total_revenue'   => $stats->total_revenue,
                'avg_order_value' => $stats->avg_order_value,
            ]
        );
    }
}

// İndi sorğu:
$summaries = DailyOrderSummary::whereBetween('date', ['2025-01-01', '2025-12-31'])
    ->orderBy('date')
    ->get();
// 365 sətir scan edir (5M əvəzinə!)

// HƏLL 2: PostgreSQL Materialized View
DB::statement("
    CREATE MATERIALIZED VIEW mv_daily_order_stats AS
    SELECT 
        DATE(created_at) AS date,
        COUNT(*) AS order_count,
        SUM(total_amount) AS total_revenue,
        AVG(total_amount) AS avg_order_value
    FROM orders
    GROUP BY DATE(created_at)
    WITH DATA
");

DB::statement("CREATE UNIQUE INDEX idx_mv_daily_date ON mv_daily_order_stats (date)");

// Yeniləmə (CONCURRENTLY - bloklamadan)
DB::statement("REFRESH MATERIALIZED VIEW CONCURRENTLY mv_daily_order_stats");
```

---

## Automated Slow Query Detection

### Proaktiv Slow Query Monitorinq Sistemi

```php
<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SlowQueryAlert;

class SlowQueryDetector
{
    private array $queryBuffer = [];
    private float $requestStartTime;
    
    // Threshold-lar (ms)
    private const SLOW_QUERY_THRESHOLD = 500;
    private const CRITICAL_QUERY_THRESHOLD = 2000;
    private const MAX_QUERIES_PER_REQUEST = 50;
    private const REQUEST_TIME_THRESHOLD = 3000;
    
    // N+1 aşkarlama
    private const DUPLICATE_QUERY_THRESHOLD = 5;
    
    public function __construct(
        private readonly QueryAnalyzer $analyzer,
        private readonly AlertService $alertService,
    ) {
        $this->requestStartTime = microtime(true);
    }
    
    /**
     * DB::listen callback-i ilə çağırılır
     */
    public function recordQuery(string $sql, array $bindings, float $timeMs, string $connection): void
    {
        $queryData = [
            'sql'        => $sql,
            'bindings'   => $bindings,
            'time_ms'    => $timeMs,
            'connection' => $connection,
            'timestamp'  => microtime(true),
            'normalized' => $this->normalizeQuery($sql),
            'caller'     => $this->getCallerInfo(),
        ];
        
        $this->queryBuffer[] = $queryData;
        
        // Real-time yavaş sorğu aşkarlaması
        if ($timeMs >= self::CRITICAL_QUERY_THRESHOLD) {
            $this->handleCriticalQuery($queryData);
        } elseif ($timeMs >= self::SLOW_QUERY_THRESHOLD) {
            $this->handleSlowQuery($queryData);
        }
    }
    
    /**
     * Request sonunda çağırılır (middleware terminate)
     */
    public function analyzeRequest(): array
    {
        $totalTime = (microtime(true) - $this->requestStartTime) * 1000;
        $queryCount = count($this->queryBuffer);
        $totalQueryTime = array_sum(array_column($this->queryBuffer, 'time_ms'));
        
        $analysis = [
            'total_queries'     => $queryCount,
            'total_query_time'  => round($totalQueryTime, 2),
            'request_time'      => round($totalTime, 2),
            'slow_queries'      => [],
            'n_plus_one'        => [],
            'duplicate_queries' => [],
            'recommendations'   => [],
        ];
        
        // Yavaş sorğuları topla
        $analysis['slow_queries'] = array_filter(
            $this->queryBuffer,
            fn($q) => $q['time_ms'] >= self::SLOW_QUERY_THRESHOLD
        );
        
        // N+1 aşkarlama
        $analysis['n_plus_one'] = $this->detectNPlusOne();
        
        // Dublikat sorğular
        $analysis['duplicate_queries'] = $this->detectDuplicateQueries();
        
        // Tövsiyələr
        $analysis['recommendations'] = $this->generateRecommendations($analysis);
        
        // Problem varsa alert göndər
        if ($this->shouldAlert($analysis)) {
            $this->sendAlert($analysis);
        }
        
        // Statistikanı saxla
        $this->storeStatistics($analysis);
        
        return $analysis;
    }
    
    /**
     * N+1 sorğu pattern-ini aşkar edir
     * Eyni sorğu pattern-inin çox təkrarlanmasını tapır
     */
    private function detectNPlusOne(): array
    {
        $normalized = [];
        
        foreach ($this->queryBuffer as $query) {
            $pattern = $query['normalized'];
            
            if (! isset($normalized[$pattern])) {
                $normalized[$pattern] = [
                    'pattern'  => $pattern,
                    'count'    => 0,
                    'total_ms' => 0,
                    'examples' => [],
                ];
            }
            
            $normalized[$pattern]['count']++;
            $normalized[$pattern]['total_ms'] += $query['time_ms'];
            
            if (count($normalized[$pattern]['examples']) < 3) {
                $normalized[$pattern]['examples'][] = $query['sql'];
            }
        }
        
        // N+1 şübhəlilərini filtrə et
        return array_filter(
            $normalized,
            fn($group) => $group['count'] >= self::DUPLICATE_QUERY_THRESHOLD
                && str_starts_with(strtoupper(trim($group['pattern'])), 'SELECT')
        );
    }
    
    /**
     * Tam eyni sorğuların təkrarını tapır (cache lazım ola bilər)
     */
    private function detectDuplicateQueries(): array
    {
        $exact = [];
        
        foreach ($this->queryBuffer as $query) {
            $key = md5($query['sql'] . serialize($query['bindings']));
            
            if (! isset($exact[$key])) {
                $exact[$key] = [
                    'sql'     => $query['sql'],
                    'count'   => 0,
                    'total_ms'=> 0,
                ];
            }
            
            $exact[$key]['count']++;
            $exact[$key]['total_ms'] += $query['time_ms'];
        }
        
        return array_filter($exact, fn($q) => $q['count'] > 1);
    }
    
    /**
     * Sorğunu normallaşdırır (parametrləri ? ilə əvəz edir)
     */
    private function normalizeQuery(string $sql): string
    {
        // Rəqəmləri əvəz et
        $sql = preg_replace('/\b\d+\b/', '?', $sql);
        
        // String-ləri əvəz et
        $sql = preg_replace("/'[^']*'/", '?', $sql);
        
        // IN (...) siyahılarını sadələşdir
        $sql = preg_replace('/IN\s*\([?,\s]+\)/', 'IN (?)', $sql);
        
        // Əlavə boşluqları sil
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        
        return $sql;
    }
    
    private function handleCriticalQuery(array $queryData): void
    {
        Log::channel('slow-queries')->error('CRITICAL Slow Query', [
            'sql'     => $queryData['sql'],
            'time_ms' => $queryData['time_ms'],
            'caller'  => $queryData['caller'],
            'url'     => request()->fullUrl(),
        ]);
        
        // Rate limiting ilə alert
        $cacheKey = 'critical_query_alert:' . md5($queryData['normalized']);
        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addMinutes(5));
            $this->alertService->sendCriticalQueryAlert($queryData);
        }
    }
    
    private function handleSlowQuery(array $queryData): void
    {
        Log::channel('slow-queries')->warning('Slow Query', [
            'sql'     => $queryData['sql'],
            'time_ms' => $queryData['time_ms'],
            'caller'  => $queryData['caller'],
        ]);
    }
    
    private function generateRecommendations(array $analysis): array
    {
        $recommendations = [];
        
        if ($analysis['total_queries'] > self::MAX_QUERIES_PER_REQUEST) {
            $recommendations[] = [
                'type'    => 'too_many_queries',
                'message' => "Bu request {$analysis['total_queries']} sorğu icra edir. "
                    . "Eager loading və ya caching istifadə edin.",
                'severity'=> 'high',
            ];
        }
        
        if (! empty($analysis['n_plus_one'])) {
            foreach ($analysis['n_plus_one'] as $pattern) {
                $recommendations[] = [
                    'type'    => 'n_plus_one',
                    'message' => "N+1 problem: '{$pattern['pattern']}' sorğusu "
                        . "{$pattern['count']} dəfə təkrarlanır. with() istifadə edin.",
                    'severity'=> 'high',
                ];
            }
        }
        
        if (! empty($analysis['duplicate_queries'])) {
            $recommendations[] = [
                'type'    => 'duplicate_queries',
                'message' => count($analysis['duplicate_queries']) . " dublikat sorğu var. "
                    . "Cache istifadəsini nəzərdən keçirin.",
                'severity'=> 'medium',
            ];
        }
        
        if ($analysis['total_query_time'] > self::REQUEST_TIME_THRESHOLD) {
            $recommendations[] = [
                'type'    => 'high_query_time',
                'message' => "Toplam sorğu vaxtı {$analysis['total_query_time']}ms - "
                    . "yavaş sorğuları optimallaşdırın.",
                'severity'=> 'high',
            ];
        }
        
        return $recommendations;
    }
    
    private function shouldAlert(array $analysis): bool
    {
        return ! empty($analysis['slow_queries'])
            || ! empty($analysis['n_plus_one'])
            || $analysis['total_queries'] > self::MAX_QUERIES_PER_REQUEST * 2;
    }
    
    private function sendAlert(array $analysis): void
    {
        $cacheKey = 'slow_query_alert:' . md5(request()->fullUrl());
        
        // Eyni URL üçün 10 dəqiqədə bir alert
        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addMinutes(10));
            
            Log::channel('slow-queries')->alert('Request Performance Alert', [
                'url'              => request()->fullUrl(),
                'method'           => request()->method(),
                'total_queries'    => $analysis['total_queries'],
                'total_query_time' => $analysis['total_query_time'],
                'slow_count'       => count($analysis['slow_queries']),
                'n_plus_one_count' => count($analysis['n_plus_one']),
                'recommendations'  => $analysis['recommendations'],
            ]);
        }
    }
    
    private function storeStatistics(array $analysis): void
    {
        // Redis-ə əsas statistikanı yaz (dashboard üçün)
        $key = 'query_stats:' . date('Y-m-d:H');
        
        Cache::store('redis')->increment("{$key}:total_requests");
        Cache::store('redis')->increment("{$key}:total_queries", $analysis['total_queries']);
        
        if (! empty($analysis['slow_queries'])) {
            Cache::store('redis')->increment(
                "{$key}:slow_queries",
                count($analysis['slow_queries'])
            );
        }
        
        // TTL: 48 saat
        Cache::store('redis')->put("{$key}:ttl_marker", true, now()->addHours(48));
    }
    
    private function getCallerInfo(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);
        
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            if (str_contains($file, '/app/') && ! str_contains($file, '/Providers/')) {
                return basename($file) . ':' . ($frame['line'] ?? '?');
            }
        }
        
        return 'unknown';
    }
}
```

```
Automated Detection İş Axını:

  +----------+     +------------------+     +---------------------+
  | HTTP     |---->| QueryMonitor     |---->| SlowQueryDetector   |
  | Request  |     | Middleware       |     | (DB::listen)        |
  +----------+     +------------------+     +----------+----------+
                                                       |
                            Hər sorğu üçün:            |
                   +-----------------------------------+
                   |                                   |
          +--------v--------+               +----------v----------+
          | Real-time Check |               | Buffer-ə Əlavə Et  |
          | (threshold)     |               | (analiz üçün)       |
          +--------+--------+               +----------+----------+
                   |                                   |
          +--------v--------+               +----------v----------+
          | Critical? Alert!|               | Request Bitdikdə:   |
          | (2000ms+)       |               | analyzeRequest()    |
          +-----------------+               +----------+----------+
                                                       |
                                            +----------v----------+
                                            | N+1 Detection       |
                                            | Duplicate Detection |
                                            | Recommendations     |
                                            +----------+----------+
                                                       |
                                            +----------v----------+
                                            | Alert? Log? Store?  |
                                            +---------------------+
```

---

## PHP İmplementasiyası

### Query Monitor Middleware

```php
<?php

namespace App\Http\Middleware;

use App\Services\Database\SlowQueryDetector;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class QueryMonitorMiddleware
{
    public function __construct(
        private readonly SlowQueryDetector $detector,
    ) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        // Query dinləyicisini quraşdır
        DB::listen(function (QueryExecuted $event) {
            $this->detector->recordQuery(
                sql: $event->sql,
                bindings: $event->bindings,
                timeMs: $event->time,
                connection: $event->connectionName,
            );
        });
        
        return $next($request);
    }
    
    /**
     * Request bitdikdən sonra çağırılır
     */
    public function terminate(Request $request, Response $response): void
    {
        $analysis = $this->detector->analyzeRequest();
        
        // Response header-lərinə statistika əlavə et (development)
        if (app()->isLocal()) {
            // Header-lər artıq göndərilib, amma log-a yaz
            Log::debug('Request Query Analysis', [
                'url'           => $request->fullUrl(),
                'total_queries' => $analysis['total_queries'],
                'total_time_ms' => $analysis['total_query_time'],
                'slow_count'    => count($analysis['slow_queries']),
                'n_plus_one'    => count($analysis['n_plus_one']),
            ]);
        }
        
        // Ciddi problemlər varsa - xəbərdarlıq
        if (! empty($analysis['recommendations'])) {
            foreach ($analysis['recommendations'] as $rec) {
                if ($rec['severity'] === 'high') {
                    Log::channel('slow-queries')->warning($rec['message'], [
                        'url'    => $request->fullUrl(),
                        'method' => $request->method(),
                        'type'   => $rec['type'],
                    ]);
                }
            }
        }
    }
}
```

### EXPLAIN Avtomatik İcra

```php
<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryAnalyzer
{
    /**
     * Sorğunun EXPLAIN planını alır
     */
    public function explain(string $sql, array $bindings = [], string $connection = null): array
    {
        $conn = DB::connection($connection);
        $driver = $conn->getDriverName();
        
        try {
            if ($driver === 'mysql') {
                return $this->explainMySQL($conn, $sql, $bindings);
            }
            
            if ($driver === 'pgsql') {
                return $this->explainPostgreSQL($conn, $sql, $bindings);
            }
        } catch (\Exception $e) {
            Log::warning('EXPLAIN failed', [
                'error' => $e->getMessage(),
                'sql'   => $sql,
            ]);
        }
        
        return [];
    }
    
    private function explainMySQL($conn, string $sql, array $bindings): array
    {
        $results = $conn->select("EXPLAIN FORMAT=JSON $sql", $bindings);
        $plan = json_decode($results[0]->EXPLAIN ?? '{}', true);
        
        $analysis = [
            'type'          => 'mysql',
            'raw_plan'      => $plan,
            'warnings'      => [],
            'scan_type'     => null,
            'rows_examined' => null,
            'using_index'   => false,
            'using_filesort'=> false,
            'using_tmpfile' => false,
        ];
        
        // Plan-ı analiz et
        if (isset($plan['query_block']['table'])) {
            $table = $plan['query_block']['table'];
            $analysis['scan_type'] = $table['access_type'] ?? 'unknown';
            $analysis['rows_examined'] = $table['rows_examined_per_scan'] ?? null;
            $analysis['using_index'] = isset($table['key']);
        }
        
        // Xəbərdarlıqlar
        if (($analysis['scan_type'] ?? '') === 'ALL') {
            $analysis['warnings'][] = 'Full table scan aşkarlandı. İndeks əlavə etməyi düşünün.';
        }
        
        return $analysis;
    }
    
    private function explainPostgreSQL($conn, string $sql, array $bindings): array
    {
        $results = $conn->select("EXPLAIN (FORMAT JSON, ANALYZE false, BUFFERS false) $sql", $bindings);
        $plan = json_decode($results[0]->{'QUERY PLAN'} ?? '[]', true);
        
        $analysis = [
            'type'       => 'postgresql',
            'raw_plan'   => $plan,
            'warnings'   => [],
            'node_types' => [],
            'total_cost' => null,
        ];
        
        if (! empty($plan) && isset($plan[0]['Plan'])) {
            $rootPlan = $plan[0]['Plan'];
            $analysis['total_cost'] = $rootPlan['Total Cost'] ?? null;
            
            // Bütün node-ları topla
            $this->collectNodes($rootPlan, $analysis['node_types']);
            
            // Seq Scan varsa xəbərdarlıq
            if (in_array('Seq Scan', $analysis['node_types'])) {
                $analysis['warnings'][] = 'Sequential Scan aşkarlandı. Böyük cədvəl üçün indeks lazım ola bilər.';
            }
        }
        
        return $analysis;
    }
    
    private function collectNodes(array $plan, array &$types): void
    {
        if (isset($plan['Node Type'])) {
            $types[] = $plan['Node Type'];
        }
        
        foreach ($plan['Plans'] ?? [] as $subPlan) {
            $this->collectNodes($subPlan, $types);
        }
    }
    
    /**
     * Sorğunun potensial problemlərini analiz edir
     */
    public function analyzeQueryPatterns(string $sql): array
    {
        $warnings = [];
        $upperSql = strtoupper($sql);
        
        // SELECT * istifadəsi
        if (preg_match('/SELECT\s+\*\s+FROM/i', $sql)) {
            $warnings[] = [
                'type'    => 'select_star',
                'message' => 'SELECT * əvəzinə yalnız lazımi sütunları seçin.',
                'severity'=> 'low',
            ];
        }
        
        // LIKE '%...%' pattern
        if (preg_match('/LIKE\s+[\'"]%/i', $sql)) {
            $warnings[] = [
                'type'    => 'leading_wildcard',
                'message' => "LIKE '%...' indeks istifadə edə bilmir. Full-text search nəzərdən keçirin.",
                'severity'=> 'high',
            ];
        }
        
        // Funksiya WHERE şərtində
        if (preg_match('/WHERE.*(?:YEAR|MONTH|DATE|LOWER|UPPER|CAST)\s*\(/i', $sql)) {
            $warnings[] = [
                'type'    => 'function_in_where',
                'message' => 'WHERE şərtində funksiya istifadəsi indeksi əlil edə bilər.',
                'severity'=> 'medium',
            ];
        }
        
        // ORDER BY RAND()
        if (str_contains($upperSql, 'ORDER BY RAND()')) {
            $warnings[] = [
                'type'    => 'order_by_rand',
                'message' => 'ORDER BY RAND() bütün cədvəli scan edir. Alternativ yanaşma istifadə edin.',
                'severity'=> 'high',
            ];
        }
        
        // Böyük OFFSET
        if (preg_match('/OFFSET\s+(\d+)/i', $sql, $matches) && (int)$matches[1] > 10000) {
            $warnings[] = [
                'type'    => 'large_offset',
                'message' => "Böyük OFFSET ({$matches[1]}) performansı ciddi azaldır. Cursor pagination istifadə edin.",
                'severity'=> 'high',
            ];
        }
        
        // NOT IN / NOT EXISTS ilə subquery
        if (preg_match('/NOT\s+IN\s*\(\s*SELECT/i', $sql)) {
            $warnings[] = [
                'type'    => 'not_in_subquery',
                'message' => 'NOT IN (SELECT ...) əvəzinə LEFT JOIN ... WHERE IS NULL istifadə edin.',
                'severity'=> 'medium',
            ];
        }
        
        return $warnings;
    }
}
```

### Artisan Command - Slow Query Hesabatı

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SlowQueryReportCommand extends Command
{
    protected $signature = 'db:slow-query-report 
        {--hours=24 : Neçə saatlıq məlumat} 
        {--limit=20 : Ən çox neçə sorğu göstərilsin}
        {--min-time=100 : Minimum orta vaxt (ms)}';
    
    protected $description = 'pg_stat_statements-dən yavaş sorğu hesabatı generasiya edir';
    
    public function handle(): int
    {
        $this->info('Yavaş Sorğu Hesabatı Generasiya Edilir...');
        $this->newLine();
        
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            return $this->postgresReport();
        }
        
        if ($driver === 'mysql') {
            return $this->mysqlReport();
        }
        
        $this->error("Dəstəklənməyən driver: {$driver}");
        return self::FAILURE;
    }
    
    private function postgresReport(): int
    {
        $limit = (int) $this->option('limit');
        $minTime = (float) $this->option('min-time');
        
        // pg_stat_statements mövcudluğunu yoxla
        $exists = DB::selectOne(
            "SELECT EXISTS(SELECT 1 FROM pg_extension WHERE extname = 'pg_stat_statements') as exists"
        );
        
        if (! $exists->exists) {
            $this->error('pg_stat_statements extension quraşdırılmayıb!');
            $this->info('Quraşdırma: CREATE EXTENSION pg_stat_statements;');
            return self::FAILURE;
        }
        
        // Ən yavaş sorğular
        $this->info('=== Ən Yavaş Sorğular (orta vaxt üzrə) ===');
        
        $slowQueries = DB::select("
            SELECT 
                LEFT(query, 80) AS query_preview,
                calls,
                ROUND(mean_exec_time::numeric, 2) AS avg_ms,
                ROUND(max_exec_time::numeric, 2) AS max_ms,
                ROUND(total_exec_time::numeric, 2) AS total_ms,
                rows,
                ROUND((shared_blks_hit::numeric / NULLIF(shared_blks_hit + shared_blks_read, 0) * 100), 1) AS cache_hit_pct
            FROM pg_stat_statements
            WHERE mean_exec_time >= ?
              AND calls > 5
              AND query NOT LIKE '%pg_stat%'
            ORDER BY mean_exec_time DESC
            LIMIT ?
        ", [$minTime, $limit]);
        
        $this->table(
            ['Sorğu', 'Çağırış', 'Ort. ms', 'Maks. ms', 'Toplam ms', 'Sətirlər', 'Cache %'],
            collect($slowQueries)->map(fn($q) => [
                $q->query_preview,
                number_format($q->calls),
                $q->avg_ms,
                $q->max_ms,
                number_format($q->total_ms, 0),
                number_format($q->rows),
                ($q->cache_hit_pct ?? 'N/A') . '%',
            ])
        );
        
        // N+1 şübhəlilər
        $this->newLine();
        $this->info('=== N+1 Şübhəliləri (çox çağırılan, az nəticə) ===');
        
        $nPlusOne = DB::select("
            SELECT 
                LEFT(query, 80) AS query_preview,
                calls,
                ROUND(mean_exec_time::numeric, 2) AS avg_ms,
                ROUND(total_exec_time::numeric, 2) AS total_ms,
                ROUND((rows::numeric / NULLIF(calls, 0)), 2) AS avg_rows
            FROM pg_stat_statements
            WHERE calls > 1000
              AND ROUND((rows::numeric / NULLIF(calls, 0)), 2) <= 1
              AND query NOT LIKE '%pg_stat%'
              AND query ~* '^SELECT'
            ORDER BY calls DESC
            LIMIT 10
        ");
        
        if (empty($nPlusOne)) {
            $this->info('N+1 şübhəlisi tapılmadı.');
        } else {
            $this->table(
                ['Sorğu', 'Çağırış', 'Ort. ms', 'Toplam ms', 'Ort. Sətir'],
                collect($nPlusOne)->map(fn($q) => [
                    $q->query_preview,
                    number_format($q->calls),
                    $q->avg_ms,
                    number_format($q->total_ms, 0),
                    $q->avg_rows,
                ])
            );
        }
        
        // Ümumi statistika
        $this->newLine();
        $this->info('=== Ümumi Statistika ===');
        
        $stats = DB::selectOne("
            SELECT 
                SUM(calls) AS total_calls,
                ROUND(SUM(total_exec_time)::numeric, 2) AS total_time_ms,
                ROUND(AVG(mean_exec_time)::numeric, 2) AS avg_query_ms,
                COUNT(*) AS unique_queries
            FROM pg_stat_statements
            WHERE query NOT LIKE '%pg_stat%'
        ");
        
        $this->table(['Metrik', 'Dəyər'], [
            ['Unikal sorğu sayı', number_format($stats->unique_queries)],
            ['Toplam çağırış', number_format($stats->total_calls)],
            ['Toplam icra vaxtı', number_format($stats->total_time_ms, 0) . ' ms'],
            ['Orta sorğu vaxtı', $stats->avg_query_ms . ' ms'],
        ]);
        
        return self::SUCCESS;
    }
    
    private function mysqlReport(): int
    {
        $limit = (int) $this->option('limit');
        $minTime = (float) $this->option('min-time');
        
        $this->info('=== MySQL Performance Schema - Ən Yavaş Sorğular ===');
        
        $slowQueries = DB::select("
            SELECT 
                LEFT(DIGEST_TEXT, 80) AS query_preview,
                COUNT_STAR AS calls,
                ROUND(AVG_TIMER_WAIT / 1000000000, 2) AS avg_ms,
                ROUND(MAX_TIMER_WAIT / 1000000000, 2) AS max_ms,
                ROUND(SUM_TIMER_WAIT / 1000000000, 2) AS total_ms,
                SUM_ROWS_SENT AS rows_sent,
                SUM_ROWS_EXAMINED AS rows_examined,
                SUM_NO_INDEX_USED AS no_index_count
            FROM performance_schema.events_statements_summary_by_digest
            WHERE ROUND(AVG_TIMER_WAIT / 1000000000, 2) >= ?
              AND COUNT_STAR > 5
            ORDER BY AVG_TIMER_WAIT DESC
            LIMIT ?
        ", [$minTime, $limit]);
        
        $this->table(
            ['Sorğu', 'Çağırış', 'Ort. ms', 'Maks. ms', 'Sətir Gönd.', 'Sətir Yoxl.', 'No Index'],
            collect($slowQueries)->map(fn($q) => [
                $q->query_preview,
                number_format($q->calls),
                $q->avg_ms,
                $q->max_ms,
                number_format($q->rows_sent),
                number_format($q->rows_examined),
                $q->no_index_count,
            ])
        );
        
        return self::SUCCESS;
    }
}
```

### Log Kanalı Konfiqurasiyası

```php
<?php

// config/logging.php
return [
    'channels' => [
        // ... mövcud kanallar
        
        'slow-queries' => [
            'driver'  => 'daily',
            'path'    => storage_path('logs/slow-queries.log'),
            'level'   => 'debug',
            'days'    => 30,
        ],
        
        'query' => [
            'driver'  => 'daily',
            'path'    => storage_path('logs/queries.log'),
            'level'   => 'debug',
            'days'    => 7,
        ],
    ],
];

// config/database.php - slow query threshold əlavə et
return [
    // ... mövcud konfiqurasiya
    
    'slow_query_threshold' => env('DB_SLOW_QUERY_THRESHOLD', 500), // ms
];
```

### Kernel/Middleware Qeydiyyatı

```php
<?php

// bootstrap/app.php (Laravel 11+)
use App\Http\Middleware\QueryMonitorMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(QueryMonitorMiddleware::class);
    })
    ->create();

// Və ya app/Http/Kernel.php (Laravel 10)
protected $middleware = [
    // ...
    \App\Http\Middleware\QueryMonitorMiddleware::class,
];
```

### Query Performance Dashboard Endpoint

```php
<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QueryPerformanceController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $hours = request()->input('hours', 24);
        
        $stats = [];
        for ($i = 0; $i < $hours; $i++) {
            $key = 'query_stats:' . now()->subHours($i)->format('Y-m-d:H');
            $stats[] = [
                'hour'           => now()->subHours($i)->format('H:00'),
                'total_requests' => (int) Cache::store('redis')->get("{$key}:total_requests", 0),
                'total_queries'  => (int) Cache::store('redis')->get("{$key}:total_queries", 0),
                'slow_queries'   => (int) Cache::store('redis')->get("{$key}:slow_queries", 0),
            ];
        }
        
        // pg_stat_statements-dən canlı məlumat
        $topSlow = [];
        if (DB::getDriverName() === 'pgsql') {
            $topSlow = DB::select("
                SELECT 
                    LEFT(query, 100) AS query,
                    calls,
                    ROUND(mean_exec_time::numeric, 2) AS avg_ms,
                    ROUND(total_exec_time::numeric, 2) AS total_ms
                FROM pg_stat_statements
                WHERE query NOT LIKE '%pg_stat%'
                ORDER BY total_exec_time DESC
                LIMIT 10
            ");
        }
        
        return response()->json([
            'hourly_stats' => array_reverse($stats),
            'top_slow'     => $topSlow,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
```

```
Tam Monitorinq Arxitekturası:

  +----------------+     +------------------+     +---------------------+
  |  Laravel App   |     | QueryMonitor     |     | SlowQueryDetector   |
  |  (Request)     |---->| Middleware        |---->| Service             |
  +----------------+     +------------------+     +----------+----------+
                                                             |
                              +------------------------------+----+
                              |                              |    |
                    +---------v---------+         +----------v--+ |
                    | slow-queries.log  |         | Redis Stats | |
                    | (daily rotate)    |         | (hourly)    | |
                    +-------------------+         +-------------+ |
                                                                  |
                    +-------------------+         +---------------v-+
                    | Artisan Command   |         | Alert Service   |
                    | db:slow-query     |         | (Slack/Email)   |
                    | -report           |         +-----------------+
                    +-------------------+
                              |
                    +---------v---------+
                    | pg_stat_statements|
                    | Performance Schema|
                    +-------------------+
```

---

## İntervyu Sualları

### Sual 1: Slow Query Log nədir və production-da necə istifadə olunmalıdır?

**Cavab:**

Slow Query Log, verilənlər bazası tərəfindən müəyyən vaxt həddini (threshold) aşan sorğuların avtomatik qeydə alınması mexanizmidir.

Production-da istifadə qaydaları:

1. **Threshold düzgün seçilməlidir**: Çox aşağı threshold (məs. 10ms) log fayllarını şişirdir və disk I/O yaradır. Adətən 500ms-1s arası optimal başlanğıc nöqtəsidir.

2. **Log rotation vacibdir**: Log faylları tez böyüyə bilər. MySQL-də `logrotate`, PostgreSQL-də `logging_collector` ilə avtomatik rotation qurulmalıdır.

3. **Sampling istifadə edilə bilər**: Yüksək yükdə bütün yavaş sorğuları loglamaq əvəzinə, PostgreSQL-in `log_statement_sample_rate` parametri ilə nümunələmə etmək mümkündür.

4. **Analiz alətləri ilə birlikdə istifadə olunmalıdır**: Ham log-lar böyük ola bilər. pt-query-digest (MySQL) və pgBadger (PostgreSQL) sorğuları qruplaşdırır, prioritetləşdirir.

5. **İndeks istifadə etməyən sorğuları ayrıca izləyin**: MySQL-də `log_queries_not_using_indexes`, PostgreSQL-də `auto_explain` modulu ilə bu sorğuları müəyyən edin.

6. **Performance Schema / pg_stat_statements**: Log fayllarından əlavə, bu built-in alətlər daha strukturlaşdırılmış statistika verir və log rotasiyasından asılı deyil.

### Sual 2: EXPLAIN ANALYZE çıxışında hansı "red flag"-lar axtarılmalıdır?

**Cavab:**

Əsas "red flag"-lar:

1. **Seq Scan böyük cədvəldə**: 100K+ sətirli cədvəldə Sequential Scan adətən indeks çatışmazlığına işarə edir. Lakin kiçik cədvəllərdə və ya nəticənin cədvəlin 10-15%-dən çoxunu əhatə etdiyi hallarda Seq Scan normaldır.

2. **Rows Removed by Filter yüksəkdir**: İndeks tapır, amma sonra çoxlu sətirləri filter ilə atır. Bu, daha dəqiq composite indeks lazım olduğunu göstərir.

3. **Actual rows >> Estimated rows**: Planner-in statistikası köhnədir. `ANALYZE` əmri ilə cədvəl statistikasını yeniləmək lazımdır. Bu, sub-optimal plan seçiminə səbəb ola bilər.

4. **Nested Loop + Seq Scan on inner**: Inner cədvəldə indeks yoxdur. Hər outer sətir üçün bütün inner cədvəl scan olunur (O(n*m)).

5. **Sort Method: external merge**: Sort üçün yaddaş yetərli deyil, disk istifadə olunur. `work_mem` parametrini artırmaq və ya indeks əlavə etmək lazımdır.

6. **Buffers: shared read >> shared hit**: Cache hit ratio azdır. Ya `shared_buffers` yetərli deyil, ya da sorğu çox geniş data set-ə müraciət edir.

7. **Hash Batches > 1**: Hash table yaddaşa sığmır, diskə yazılır. `work_mem` artırmaq lazımdır.

### Sual 3: N+1 query problemi nədir, necə aşkarlanır və necə həll olunur?

**Cavab:**

N+1 problemi, bir əsas sorğu (1) və hər nəticə sətri üçün əlavə bir sorğu (N) icra olunmasıdır. Məsələn, 50 sifariş göstərmək üçün 1 (sifarişlər) + 50 (hər sifarişin istifadəçisi) = 51 sorğu.

**Aşkarlama yolları:**

1. **Slow Query Log**: Eyni pattern-li sorğunun yüzlərlə dəfə təkrarlandığını görmək (pt-query-digest ilə)
2. **pg_stat_statements**: `calls` çox yüksək, `rows/calls` = 1 olan SELECT sorğuları
3. **Laravel Telescope/Debugbar**: Sorğu sayını vizual görmək
4. **`Model::preventLazyLoading()`**: Laravel-də lazy loading zamanı exception atır
5. **Custom middleware**: DB::listen ilə eyni normalized sorğunun təkrar sayını izləmək

**Həll yolları:**

1. **Eager Loading**: `with()` metodu ilə əlaqəli məlumatları əvvəlcədən yükləmək (`Order::with('user')->get()`)
2. **Lazy Eager Loading**: Artıq yüklənmiş kolleksiyaya `$orders->load('items')` ilə əlavə əlaqə yükləmək
3. **Select sütunlarını məhdudlaşdır**: `with('user:id,name')` - yalnız lazımi sütunlar
4. **Subquery ilə**: `addSelect` ilə subquery əlavə etmək bəzən daha effektiv olur
5. **Cache**: Tez-tez oxunan əlaqələri cache-ləmək

### Sual 4: MySQL PROFILING və PostgreSQL pg_stat_statements arasında fərq nədir?

**Cavab:**

**MySQL PROFILING:**
- Sessiya səviyyəsindədir (yalnız cari bağlantının sorğularını izləyir)
- Sorğunun icrasının hər mərhələsini (stage) ətraflı göstərir (opening tables, sending data, sorting, və s.)
- Bir sorğunun "harada vaxt itirdiyini" dəqiq müəyyən etmək üçün istifadə olunur
- `SET profiling=1` ilə aktivləşir, `SHOW PROFILE FOR QUERY N` ilə baxılır
- Production-da istifadə üçün nəzərdə tutulmayıb (overhead var)
- MySQL 8.0-da deprecated olub, Performance Schema tövsiyə edilir

**PostgreSQL pg_stat_statements:**
- Server səviyyəsindədir (bütün bağlantıların sorğularını izləyir)
- Sorğuları normallaşdırır və qruplaşdırır (parametrləri `$1, $2` ilə əvəz edir)
- Hər unikal sorğu üçün kumulativ statistika toplayır: çağırış sayı, orta/min/max vaxt, I/O, WAL
- Production-da istifadə üçün əladır (minimal overhead)
- `shared_preload_libraries`-ə əlavə edilməlidir (restart lazımdır)
- `pg_stat_statements_reset()` ilə statistika sıfırlanır

**Əsas fərq:** PROFILING bir sorğunun daxili mərhələlərini göstərir ("bu sorğu niyə yavaşdır?"), pg_stat_statements isə bütün sorğuların statistikasını toplayır ("hansı sorğular problematikdir?"). Onlar bir-birini tamamlayır.

### Sual 5: Production-da sorğu performansını necə monitorinq edərsiniz?

**Cavab:**

Production-da sorğu performans monitorinqi çoxqatlı yanaşma tələb edir:

**Verilənlər Bazası Səviyyəsi:**
- Slow query log aktiv olmalıdır (threshold: 500ms-1s)
- pg_stat_statements / Performance Schema aktivdir
- `auto_explain` modulu ciddi yavaş sorğuların planını avtomatik logla
- `log_lock_waits` aktiv olmalıdır (deadlock/lock problemlərini aşkarlamaq üçün)

**Tətbiq Səviyyəsi:**
- DB::listen ilə middleware-dən sorğuları izləyirik
- Threshold aşan sorğular ayrıca log kanalına yazılır
- N+1 aşkarlama: normallaşdırılmış sorğuların təkrar sayını izləyirik
- Request başına sorğu sayı və toplam vaxtı metrik kimi toplanır

**Alert Mexanizmi:**
- Kritik yavaş sorğularda (2s+) dərhal Slack/PagerDuty bildirişi
- Rate limiting ilə (eyni sorğu üçün 5 dəqiqədə bir alert)
- Gündəlik hesabat (pt-query-digest / pgBadger)

**Dashboard:**
- Redis-dən saatlıq statistika (total_queries, slow_queries)
- pg_stat_statements-dən Top-N yavaş sorğular
- Cache hit ratio izləmə
- Grafana/DataDog inteqrasiyası

**Proaktiv Yanaşmalar:**
- `Model::preventLazyLoading()` staging mühitində
- CI/CD pipeline-da sorğu sayı testləri
- Load testing zamanı slow query analizi
- Yeni deployment-dan sonra sorğu performansının müqayisəsi (regression detection)

### Sual 6: Hash Join, Nested Loop və Merge Join arasında fərq nədir? Planner hansını nə zaman seçir?

**Cavab:**

**Nested Loop Join:**
- Outer cədvəlin hər sətri üçün inner cədvəli axtarır
- Mürəkkəblik: O(n * m), inner-da index varsa O(n * log m)
- **Planner nə vaxt seçir:** Outer cədvəl kiçikdir, inner-da effektiv indeks var, və ya nəticə az sətir gözlənilir. Inequality join-lar üçün (>, <, BETWEEN) tez-tez istifadə olunur.

**Hash Join:**
- Kiçik cədvəldən yaddaşda hash table yaradılır, böyük cədvəl scan edilərkən hash table-dan axtarılır
- Mürəkkəblik: O(n + m) - ən sürətli böyük cədvəl birləşmə metodu
- **Planner nə vaxt seçir:** Hər iki cədvəl böyükdür, equality join (=) şərti var, hash table `work_mem`-ə sığır. Əgər sığmazsa "batches" istifadə olunur (diskə yazılır) və performans azalır.

**Merge Join:**
- Hər iki cədvəl join key üzrə sıralanır, sonra paralel scan edilir (zipper kimi)
- Mürəkkəblik: O(n*log(n) + m*log(m)) sıralama + O(n+m) birləşmə
- **Planner nə vaxt seçir:** Hər iki cədvəl artıq sıralıdır (indeks üzrə), çox böyük data set-lər, və ya hash table yaddaşa sığmır. Sort + merge bəzən hash-dən daha effektiv ola bilər.

**Planner qərarına təsir edən faktorlar:**
- Cədvəl ölçüləri və statistikaları (ANALYZE ilə yenilənir)
- Mövcud indekslər
- `work_mem` parametri (hash table və sort üçün yaddaş)
- `random_page_cost` və `seq_page_cost` parametrləri
- Gözlənilən nəticə sətir sayı
- JOIN şərti tipi (equality vs inequality)
