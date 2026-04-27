# Write-Ahead Logging (Lead ⭐⭐⭐⭐)

## İcmal
WAL (Write-Ahead Log) — database-in hər dəyişikliyi disk-ə yazmadan əvvəl log-a yazmasını tələb edən mexanizmdir. Durability, crash recovery, replication — hamısı WAL üzərindədir. Bu mövzu Lead/Architect interview-larda database internals-ı nə dərəcədə başa düşdüyünüzü yoxlamaq üçün çıxır.

## Niyə Vacibdir
WAL-u başa düşmək database-in "qaydaları" mövzusunda dərin bilik deməkdir: performans tuning (checkpoint, fsync), replication dizaynı, backup strategiyası, CDC (Change Data Capture) — hamısı WAL-a söykənir. İnterviewer bu sualla sizin "niyə işləyir" sualını cavablaya bildiyinizi yoxlayır.

## Əsas Anlayışlar

- **WAL Əsas Prinsipi:** Log record-ları actual data page-lərindən əvvəl disk-ə yazılır — "log-first" qaydası. Bu atomicity-ni və durability-ni zəmanətləndirir
- **LSN (Log Sequence Number):** WAL-dakı hər record-un unikal, monoton artan mövqeyi. Replication lag ölçmək, recovery point tapmaq, CDC sync üçün əsas referans nöqtəsidir
- **WAL Segment:** Default 16MB-lıq fayl — `000000010000000000000001` formatında adlandırılır. `pg_wal/` direktoriyasında saxlanır
- **Checkpoint:** Dirty page-ləri (memory-dəki dəyişdirilmiş data) disk-ə flush edən mərhələ. Bu nöqtədən əvvəlki WAL artıq recovery üçün lazım deyil — silinə bilər
- **Checkpoint Tuning:** `checkpoint_timeout` (default 5 dəq) və `max_wal_size` (default 1GB) checkpointin nə zaman başlayacağını müəyyən edir. Çox tez-tez checkpoint → I/O spike. Çox nadir → crash recovery uzun çəkir
- **Background Writer:** Dirty page-ləri checkpoint gözləmədən yavaş-yavaş disk-ə yazır — checkpoint spike-ını azaldır, I/O-nu yayır
- **fsync:** OS buffer-ı fiziki disk-ə yazır. `fsync=off` → sürətli amma power failure-da committed transaction-lar itirilə bilər. Production-da `fsync=on` mütləqdir
- **synchronous_commit:** `on` = commit cavabı fsync tamamlanandan sonra. `off` = cavab daha tez, lakin son bir neçə transaction itirilə bilər (crash halında). `local` = local disk üçün sync, replica-ya async
- **Crash Recovery:** Restart zamanı PostgreSQL son checkpoint-i tapır, sonra WAL-ı replay edir. Commit olan transaction-lar redo edilir, commit olmayan-lar rollback olunur — "redo log" yanaşması
- **WAL Archiving:** Tamamlanan WAL segment-lərinin kopyalanması (S3, NFS, başqa path). `archive_command` ilə konfiqurasiya edilir. PITR-in əsasıdır
- **PITR (Point-in-Time Recovery):** Base backup + WAL arxivindən istənilən zamana geri qayıtmaq — "cümə axşamı saat 14:35-dəki vəziyyətə qayıt." Accidental DELETE/DROP-dan qurtarmaq üçün kritik
- **Streaming Replication:** Primary-dən replica-ya WAL record-larını real-time göndərmək. Replica WAL-ı apply edərək primary ilə sinxron qalır
- **Logical Decoding:** WAL-dan row-level dəyişiklikləri çıxarmaq — CDC, Debezium, pgoutput plugin. `wal_level=logical` tələb edir
- **WAL Level:** `minimal` (az WAL, sadəcə crash recovery), `replica` (streaming replication üçün), `logical` (CDC/Debezium üçün). Hər level bir öncəkini əhatə edir
- **wal_buffers:** WAL-ın yazılmadan əvvəl shared memory-də saxlandığı buffer. Default auto (4MB). Write-heavy sistemlərdə 64MB-a qaldırmaq I/O-nu azaldır
- **commit_delay + commit_siblings:** Bir transaction commit edəndə digər aktiv transaction-ların da commit-ə "qatılması" üçün qısa gözləmə. WAL write sayını azaldır, throughput artırır
- **full_page_writes:** Checkpoint-dən sonra bir page-ə ilk dəfə yazılanda page-in tam kopyasını WAL-a yaz. "Torn page" problemindən qoruyur. Dəyişdirilsə, PGDATA corrupt ola bilər — default `on` saxlanmalıdır
- **Replication Slot:** Consumer-ın oxuduğu LSN-ə qədər WAL-ı silməyən mexanizm. İstifadə edilməyən slot disk-i doldurar — monitoring vacibdir
- **WAL Compression:** `wal_compression=on` ilə WAL I/O-nu azaltmaq mümkündür, CPU xərci artır

## Praktik Baxış

**Interview-da yanaşma:**
- "WAL niyə lazımdır?" — "Disk üzərinə birbaşa yazmaq atomic deyil; WAL crash recovery-ni mümkün edir, replication-ın əsasıdır"
- Replication ilə WAL bağlantısını izah edin: "Primary WAL yazır, replica WAL oxuyub apply edir"
- CDC/Debezium context-inde `wal_level=logical` tələbini bildirin

**Follow-up suallar:**
- "Checkpoint nə vaxt baş verir?" — `checkpoint_timeout` bitdikdə ya da `max_wal_size` keçdikdə
- "fsync-i söndürmək nə risk yaradır?" — Power failure-da committed transaction-lar itirilər, data corruption mümkün
- "Logical replication ilə streaming replication fərqi?" — WAL level fərqi: `replica` vs `logical`, physical bytes vs row-level events
- "Replication slot izlənməsə nə baş verər?" — WAL silinmir, disk dolar, PostgreSQL dayandırılır
- "PITR nə vaxt lazım olur?" — Accidental data deletion, yanlış migration, compliance (audit to specific time)

**Ümumi səhvlər:**
- WAL-ı yalnız "log faylı" kimi izah etmək — mexanizmi başa düşməmək
- Checkpoint-i bilməmək — "WAL necə saxlanılır, nə vaxt silinir?" sualına cavab verə bilməmək
- `fsync=off`-un production-da nə risk olduğunu qeyd etməmək
- Replication slot-un disk riski yaradan tərəfini unudmaq
- `synchronous_commit=off`-un data itkisi riskini bilməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- LSN-ni izah etmək və replication lag ölçümündə istifadəsini bilmək
- Logical decoding ilə CDC bağlantısını bilmək
- PITR-i praktik nümunə ilə izah etmək — "backup + WAL arxivi = istənilən nöqtəyə qayıt"
- `synchronous_commit` seçimlərinin performance vs durability trade-off-unu bilmək

## Nümunələr

### Tipik Interview Sualı
"PostgreSQL-in crash recovery mexanizmi necə işləyir? WAL bunda nə rolu oynayır? fsync-in rolu nədir?"

### Güclü Cavab
Bir transaction commit edəndə data həmişə birbaşa disk-ə yazılmır — buffer pool-da qalır. Lakin commit edilmiş WAL record-u mütləq disk-ə yazılır (`fsync` ilə). Əgər server crash olursa, restart zamanı PostgreSQL son checkpoint-i tapır, sonra həmin checkpoint-dən sonrakı bütün WAL record-larını replay edir. Commit olmuş transaction-lar redo edilir — sanki crash olmayıb. Commit olmayan transaction-lar isə WAL-da `COMMIT` record-u olmadığından rollback hesab edilir.

`fsync=off` halında OS buffer-ı physical disk-ə yazılmır. Power failure olsa, WAL buffer-dakı son transaction-lar itirilər — bu "durability" zəmanətini pozur. Production-da `fsync=on` mütləqdir.

Checkpoint prosesi müntəzəm olaraq dirty page-ləri disk-ə flush edir. Checkpoint-dən əvvəlki WAL-a artıq replay lazım deyil — silinə bilər. `checkpoint_timeout` çox uzun olsa → crash recovery uzun çəkir; çox qısa olsa → davamlı I/O spike.

### Kod Nümunəsi
```sql
-- WAL konfigurasiyanı yoxla
SHOW wal_level;            -- minimal / replica / logical
SHOW fsync;                -- on (production mütləq on)
SHOW synchronous_commit;   -- on / off / local / remote_write
SHOW checkpoint_timeout;   -- default 5min
SHOW max_wal_size;         -- checkpoint-i trigger edən wal ölçüsü
SHOW wal_buffers;          -- WAL memory buffer ölçüsü
SHOW full_page_writes;     -- torn page qoruması

-- WAL aktivliyini izlə
SELECT pg_current_wal_lsn();                           -- Cari LSN
SELECT pg_current_wal_insert_lsn();                    -- Insert position
SELECT pg_walfile_name(pg_current_wal_lsn());          -- Cari WAL fayl adı
SELECT pg_size_pretty(
  pg_wal_lsn_diff(pg_current_wal_lsn(), '0/0')
) AS total_wal_generated;

-- Checkpoint statistikaları
SELECT
  checkpoints_timed,
  checkpoints_req,
  pg_size_pretty(checkpoint_write_time::bigint * 1000) AS write_time_ms,
  pg_size_pretty(checkpoint_sync_time::bigint * 1000)  AS sync_time_ms,
  buffers_checkpoint,
  buffers_clean,
  buffers_backend
FROM pg_stat_bgwriter;

-- Replication lag ölç (primary-dən)
SELECT
  client_addr,
  state,
  sent_lsn,
  write_lsn,
  flush_lsn,
  replay_lsn,
  pg_size_pretty(
    pg_wal_lsn_diff(sent_lsn, replay_lsn)
  ) AS replay_lag
FROM pg_stat_replication;

-- Replication slot monitorinqi (disk riski!)
SELECT
  slot_name,
  active,
  restart_lsn,
  pg_size_pretty(
    pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn)
  ) AS wal_behind
FROM pg_replication_slots;
-- wal_behind böyüyürsə → slot consumer durub → disk dolur!
```

```bash
# postgresql.conf — WAL tuning
# wal_level = replica           # Streaming replication üçün
# wal_level = logical           # CDC/Debezium üçün
# fsync = on                    # Production-da MÜTLƏQ on
# synchronous_commit = on       # Durability
# synchronous_commit = off      # ~30% daha sürətli, az durability
# wal_buffers = 64MB            # Write-heavy sistemlər üçün
# checkpoint_timeout = 10min    # Daha az checkpoint spikes
# max_wal_size = 4GB            # Böyük sistemlər üçün
# min_wal_size = 1GB            # WAL faylları silmədən saxla
# full_page_writes = on         # Torn page qoruması — default on

# WAL archiving
# archive_mode = on
# archive_command = 'cp %p /var/lib/postgresql/wal_archive/%f'
# Yaxud S3-ə (pgBackRest):
# archive_command = 'pgbackrest --stanza=main archive-push %p'
# archive_command = 'aws s3 cp %p s3://my-bucket/wal/%f'

# Base backup + WAL arxivi ilə PITR
pg_basebackup -h localhost -U replicator -D /backup/base \
  -P -Xs -R --checkpoint=fast

# WAL arxivini listə
ls -la /var/lib/postgresql/wal_archive/ | tail -20
```

```bash
# PITR recovery — PostgreSQL 12+ (postgresql.conf içinə)
# restore_command = 'cp /var/lib/postgresql/wal_archive/%f %p'
# recovery_target_time = '2025-01-15 14:35:00 UTC'
# recovery_target_action = 'promote'
# Sonra: touch /var/lib/postgresql/data/recovery.signal
# pg_ctl start

# Nə baş verir:
# 1. PostgreSQL backup-dan başlayır
# 2. WAL fayllarını bir-bir apply edir
# 3. recovery_target_time-a çatanda dayanır
# 4. promote ilə read-write mode-a keçir
```

```sql
-- Logical Replication / CDC
-- wal_level = logical mütləq lazımdır

-- Publication yarat (hansı table-ları expose et)
CREATE PUBLICATION my_pub
FOR TABLE orders, customers, products;

-- Replication slot yarat
SELECT pg_create_logical_replication_slot(
  'debezium_slot', 'pgoutput'
);

-- WAL-dan change-ləri oxu (test üçün)
SELECT lsn, xid, data
FROM pg_logical_slot_peek_changes(
  'debezium_slot', NULL, 10,
  'proto_version', '1',
  'publication_names', 'my_pub'
);

-- Aktiv olmayan slotu sil (disk dolmasın!)
SELECT pg_drop_replication_slot('inactive_slot');

-- WAL generasiyasını ölç (benchmark üçün)
SELECT
  NOW() AS start_time,
  pg_current_wal_lsn() AS start_lsn;
-- Əməliyyatlar et...
SELECT
  NOW() AS end_time,
  pg_current_wal_lsn() AS end_lsn,
  pg_size_pretty(
    pg_wal_lsn_diff(
      pg_current_wal_lsn(), 'START_LSN_HERE'
    )
  ) AS wal_generated;
```

```python
# Python psycopg2 ilə logical replication (Debezium alternativ)
import psycopg2
from psycopg2.extras import LogicalReplicationConnection

conn = psycopg2.connect(
    dsn="host=localhost dbname=mydb user=replicator",
    connection_factory=LogicalReplicationConnection
)
cur = conn.cursor()

# Slot yoxdursa yarat
try:
    cur.create_replication_slot(
      'my_slot', output_plugin='pgoutput'
    )
except psycopg2.errors.DuplicateObject:
    pass  # Artıq var

# Replication stream-ini başlat
cur.start_replication(
    slot_name='my_slot',
    decode=True,
    options={
      'proto_version': '1',
      'publication_names': 'my_pub'
    }
)

def consume(msg):
    print(f"LSN: {msg.data_start}, Change: {msg.payload[:200]}")
    # LSN-i advance et — bu nöqtəyə qədər WAL saxlanılmayacaq
    msg.cursor.send_feedback(flush_lsn=msg.data_start)

cur.consume_stream(consume)
```

### İkinci Nümunə — synchronous_commit Trade-off

```sql
-- Scenario: Yüksək yazma yükü, az durability tələbi (log events)

-- Session-da söndür (table-specific)
ALTER TABLE api_logs SET (
  autovacuum_enabled = off  -- Log table-larda autovacuum da yavaşladır
);

-- Transaction səviyyəsindəki yanaşma
BEGIN;
SET LOCAL synchronous_commit = off;  -- Bu transaction üçün async commit
INSERT INTO api_logs (endpoint, status, duration_ms, created_at)
VALUES ('/api/products', 200, 45, NOW());
COMMIT;
-- Commit cavabı dərhal gəlir, amma WAL hələ disk-ə yazılmayıb
-- Crash olsa son bir neçə saniyəlik log itirilə bilər
-- Məqbul: log events üçün; məqbul deyil: maliyyə əməliyyatları üçün

-- Benchmark: synchronous_commit on vs off
-- ON:  ~2000 INSERT/saniyə
-- OFF: ~15000 INSERT/saniyə (7.5x artım)
-- Trade-off: son 200ms-lik commitlər itirilə bilər
```

## Praktik Tapşırıqlar

- `wal_level = logical` ilə PostgreSQL çalışdırın, publication + replication slot yaradın, bir neçə INSERT/UPDATE edin, `pg_logical_slot_peek_changes`-dən change-ləri oxuyun
- PITR test edin: base backup götürün, 1000 row insert edin, `recovery_target_time` ilə 500 row-luq nöqtəyə qayıdın
- `pg_stat_bgwriter`-dən `checkpoints_req` vs `checkpoints_timed` nisbətini izləyin — `req` çox olarsa `max_wal_size` artırın
- Replication slot yaradıb "consumer" prosesinizi durdurun, 5 dəqiqə gözləyin, `pg_replication_slots`-dan `wal_behind` artmasını izləyin
- `synchronous_commit = off` ilə `on`-u benchmark edin: `pgbench` ilə eyni iş yükündə TPS fərqini ölçün
- `full_page_writes = off` ilə intensive update zamanı WAL ölçüsünü ölçün (test env-da)
- `archive_command` yazın, WAL arxivini S3-ə yükləyin, `pgBackRest` ilə verify edin

## Əlaqəli Mövzular
- `10-database-replication.md` — WAL streaming replication-ın əsasıdır
- `12-mvcc.md` — MVCC ilə WAL paralel işləyir; hər update WAL-a dead tuple məlumatı yazır
- `02-acid-properties.md` — Durability WAL tərəfindən zəmanətlənir
- `16-database-migration-strategies.md` — Böyük migration WAL-ı şişirir, checkpoint tezliyinə təsir edir
