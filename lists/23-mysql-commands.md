## Connection / client

mysql -h host -P 3306 -u user -p — qoşul
mysql -h host -u user -p dbname — birbaşa DB-yə
mysql --defaults-file=~/.my.cnf — config faylı
mysql -e "SELECT VERSION()" — tək query
mysql -e "..." -B — batch (tab-separated, no formatting)
mysql -ss -e "..." — silent (heading yox)
mysql --ssl-mode=REQUIRED -h ...
mysql --protocol=SOCKET -S /var/run/mysqld/mysqld.sock
\q / exit / quit — çıx
\h / help — kömək
\s / status — server status
\T file — output fayla yaz (tee)
\t — tee dayandır
\. file.sql / source file.sql — fayldan exec
\G — query suffix kimi: vertikal output
\c — query ləğv et

## Database / schema

CREATE DATABASE mydb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS mydb;
DROP DATABASE mydb;
ALTER DATABASE mydb CHARACTER SET utf8mb4;
SHOW DATABASES;
USE mydb;
SELECT DATABASE();
SHOW CREATE DATABASE mydb;

## Tables / DDL

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ...
CREATE TEMPORARY TABLE tmp AS SELECT ...
CREATE TABLE new_tbl LIKE old_tbl; — schema kopyala
CREATE TABLE new_tbl AS SELECT ... — data + schema (constraint-siz)
DROP TABLE tbl;
DROP TABLE IF EXISTS tbl;
TRUNCATE TABLE tbl; — instant, AUTO_INCREMENT reset
RENAME TABLE old TO new;
ALTER TABLE tbl ADD COLUMN c VARCHAR(50) AFTER other_col;
ALTER TABLE tbl DROP COLUMN c;
ALTER TABLE tbl MODIFY COLUMN c BIGINT NOT NULL;
ALTER TABLE tbl CHANGE old_name new_name VARCHAR(100); — rename + type
ALTER TABLE tbl ADD INDEX idx_name (col);
ALTER TABLE tbl ADD UNIQUE (col);
ALTER TABLE tbl ADD FOREIGN KEY (uid) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE tbl DROP INDEX idx_name;
ALTER TABLE tbl DROP FOREIGN KEY fk_name;
ALTER TABLE tbl ENGINE=InnoDB; — convert engine / rebuild
ALTER TABLE tbl AUTO_INCREMENT=1000;
ALTER TABLE tbl ALGORITHM=INPLACE, LOCK=NONE; — online DDL hint
SHOW TABLES;
SHOW TABLES LIKE 'user%';
SHOW CREATE TABLE tbl;
SHOW COLUMNS FROM tbl; / DESCRIBE tbl; / DESC tbl;
SHOW INDEX FROM tbl;
SHOW TABLE STATUS LIKE 'tbl';

## Query basics

SELECT col FROM tbl WHERE ... ORDER BY ... LIMIT 10 OFFSET 20;
SELECT SQL_CALC_FOUND_ROWS * FROM tbl LIMIT 10; SELECT FOUND_ROWS(); -- legacy, deprecated
LIMIT 10, 20 — alternative offset syntax (offset, count)
WHERE col LIKE 'abc%' / 'a_c'
WHERE col IN (...) / NOT IN
WHERE col BETWEEN x AND y
WHERE col REGEXP 'pattern' / RLIKE
WHERE col IS NULL / IS NOT NULL
ORDER BY col ASC/DESC, col2 ASC
GROUP BY col HAVING COUNT(*) > 5
GROUP BY col WITH ROLLUP
DISTINCT col
UNION / UNION ALL
WITH cte AS (SELECT ...) SELECT * FROM cte; -- 8.0+
WITH RECURSIVE -- 8.0+
WINDOW: ROW_NUMBER() OVER (PARTITION BY ... ORDER BY ...) -- 8.0+
RANK() / DENSE_RANK() / LAG() / LEAD() / FIRST_VALUE() -- 8.0+

## Insert / update / delete / upsert

INSERT INTO tbl (a,b) VALUES (1,'x'), (2,'y');
INSERT INTO tbl SET a=1, b='x';
INSERT INTO tbl SELECT ... FROM other;
INSERT IGNORE INTO tbl ... -- duplicate skip
INSERT INTO tbl (a,b) VALUES (1,'x') ON DUPLICATE KEY UPDATE b=VALUES(b); -- legacy
INSERT INTO tbl (a,b) VALUES (1,'x') AS new ON DUPLICATE KEY UPDATE b=new.b; -- 8.0.19+
REPLACE INTO tbl (a,b) VALUES (...); -- DELETE + INSERT (riskli — FK cascade)
UPDATE tbl SET a=1 WHERE id=2 LIMIT 1;
UPDATE t1 JOIN t2 ON t1.id=t2.id SET t1.x=t2.x;
DELETE FROM tbl WHERE id=1 LIMIT 1;
DELETE t1 FROM t1 JOIN t2 ON t1.id=t2.id WHERE t2.flag=1;
LOAD DATA INFILE 'file.csv' INTO TABLE tbl FIELDS TERMINATED BY ',' IGNORE 1 LINES;
LOAD DATA LOCAL INFILE ... -- client-side fayl

## Joins

INNER JOIN / JOIN
LEFT JOIN / LEFT OUTER JOIN
RIGHT JOIN
CROSS JOIN
STRAIGHT_JOIN -- optimizer hint (joins t1 first)
USING (col)
ON t1.x = t2.x
-- MySQL FULL OUTER JOIN dəstəkləmir → UNION ilə emulate
SELF JOIN — eyni table

## Indexes

CREATE INDEX idx ON tbl (col1, col2);
CREATE UNIQUE INDEX idx ON tbl (col);
CREATE FULLTEXT INDEX idx ON tbl (text_col); -- MATCH ... AGAINST
CREATE SPATIAL INDEX idx ON tbl (geom_col);
CREATE INDEX idx ON tbl (col(20)); -- prefix index (string columns)
ALTER TABLE tbl ADD INDEX idx ((LOWER(email))); -- functional index 8.0+
ALTER TABLE tbl ADD INDEX idx (col) INVISIBLE; -- testing 8.0+
DROP INDEX idx ON tbl;
SHOW INDEX FROM tbl;
ANALYZE TABLE tbl; -- stats refresh
OPTIMIZE TABLE tbl; -- defragment (InnoDB rebuilds)
-- B-tree (default), Hash (Memory), Fulltext, Spatial
-- Composite: leftmost-prefix rule
-- InnoDB: PK = clustered index; secondary indexes contain PK

## Transactions / locking

START TRANSACTION; / BEGIN;
COMMIT;
ROLLBACK;
SAVEPOINT sp; / ROLLBACK TO SAVEPOINT sp; / RELEASE SAVEPOINT sp;
SET autocommit=0/1;
SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
-- Levels: READ UNCOMMITTED, READ COMMITTED, REPEATABLE READ (default), SERIALIZABLE
SELECT ... FOR UPDATE; -- exclusive lock
SELECT ... FOR UPDATE NOWAIT / SKIP LOCKED; -- 8.0+
SELECT ... LOCK IN SHARE MODE / FOR SHARE; -- shared lock
LOCK TABLES tbl WRITE / READ; UNLOCK TABLES;
GET_LOCK('name', timeout) / RELEASE_LOCK('name'); -- advisory
-- InnoDB row-level lock (gap lock + record lock = next-key lock)
-- Deadlock auto-detected, victim rolled back
SHOW ENGINE INNODB STATUS\G -- "LATEST DETECTED DEADLOCK"

## Storage engines

InnoDB — default; ACID, FK, row-lock, MVCC, clustered PK
MyISAM — table-lock, full-text (legacy), no FK/transactions
Memory / Heap — RAM-only, hash index
Archive — write-only compressed
NDB — cluster
ROCKSDB (MyRocks) — write-heavy compressed
SHOW ENGINES;
SHOW TABLE STATUS WHERE Engine='InnoDB';

## EXPLAIN / performance

EXPLAIN SELECT ...; -- query plan
EXPLAIN FORMAT=JSON SELECT ...; -- detailed
EXPLAIN ANALYZE SELECT ...; -- 8.0.18+ real execution
EXPLAIN FOR CONNECTION <id>; -- başqa session-ın query-si
SHOW WARNINGS; -- EXPLAIN-dən sonra rewrite görmək
-- type column (yaxşı→pis): const, eq_ref, ref, range, index, ALL
-- key: istifadə olunan index
-- rows: təxmini scan ediləcək
-- Extra: Using index, Using filesort, Using temporary, Using where
SHOW PROFILES; / SHOW PROFILE FOR QUERY n; -- legacy, deprecated
SHOW STATUS LIKE 'Handler%'; -- per-query handler stats
SHOW PROCESSLIST; / SHOW FULL PROCESSLIST;
KILL <id>; / KILL QUERY <id>;
SHOW ENGINE INNODB STATUS\G

## performance_schema / sys schema

SELECT * FROM sys.statements_with_runtimes_in_95th_percentile;
SELECT * FROM sys.schema_unused_indexes;
SELECT * FROM sys.statement_analysis ORDER BY total_latency DESC LIMIT 20;
SELECT * FROM sys.innodb_lock_waits;
SELECT * FROM performance_schema.events_statements_summary_by_digest ORDER BY SUM_TIMER_WAIT DESC LIMIT 10;
SELECT * FROM information_schema.PROCESSLIST WHERE TIME > 60;
SELECT * FROM information_schema.INNODB_TRX; -- aktiv transactions
SELECT * FROM information_schema.INNODB_LOCKS; -- 5.7 / data_locks 8.0+

## Replication

-- Source (primary)
SHOW MASTER STATUS; / SHOW BINARY LOG STATUS; -- 8.4+
SHOW BINLOG EVENTS;
SHOW BINARY LOGS;
PURGE BINARY LOGS BEFORE NOW() - INTERVAL 7 DAY;
-- Replica
CHANGE REPLICATION SOURCE TO SOURCE_HOST='...', SOURCE_USER='repl', SOURCE_LOG_FILE='...', SOURCE_LOG_POS=N;
START REPLICA; / STOP REPLICA;
SHOW REPLICA STATUS\G -- Seconds_Behind_Source, Last_Error
-- 8.0-dan əvvəl: CHANGE MASTER, START SLAVE, SHOW SLAVE STATUS
-- GTID: gtid_mode=ON, enforce_gtid_consistency=ON
-- Async / semi-sync / Group Replication (synchronous, multi-primary)
-- ProxySQL / MaxScale / Vitess — proxy/sharding

## mysqldump / restore

mysqldump -h host -u user -p db > dump.sql
mysqldump -u user -p db tbl1 tbl2 > tables.sql
mysqldump -u user -p --all-databases > all.sql
mysqldump -u user -p --databases db1 db2 > dbs.sql
mysqldump --single-transaction --quick --triggers --routines --events db > dump.sql -- canonical
mysqldump --no-data db > schema.sql
mysqldump --no-create-info db > data.sql
mysqldump --where="created>='2025-01-01'" db tbl > recent.sql
mysqldump --master-data=2 --single-transaction db > dump.sql -- replica setup
mysqldump --hex-blob — binary safe
mysqldump --compact --skip-comments
mysql -u user -p db < dump.sql -- restore
mysqlpump — parallel dump (8.0; deprecated 8.4+)
mysqlsh util.dumpInstance("/backup", {threads:8}) -- modern parallel
mysqlsh util.loadDump("/backup")
xtrabackup --backup --target-dir=/bak -- physical hot backup

## Users / privileges

CREATE USER 'app'@'%' IDENTIFIED BY 'pass';
CREATE USER 'app'@'10.0.%.%' IDENTIFIED WITH caching_sha2_password BY 'pass';
ALTER USER 'app'@'%' IDENTIFIED BY 'newpass';
ALTER USER 'app'@'%' PASSWORD EXPIRE NEVER;
ALTER USER 'app'@'%' WITH MAX_USER_CONNECTIONS 50;
DROP USER 'app'@'%';
RENAME USER old TO new;
SHOW CREATE USER 'app'@'%';
SHOW GRANTS FOR 'app'@'%';
GRANT SELECT, INSERT, UPDATE ON db.* TO 'app'@'%';
GRANT ALL PRIVILEGES ON db.* TO 'app'@'%';
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%';
REVOKE INSERT ON db.* FROM 'app'@'%';
FLUSH PRIVILEGES; -- usually auto-applied with GRANT
-- Roles (8.0+)
CREATE ROLE 'app_read';
GRANT SELECT ON db.* TO 'app_read';
GRANT 'app_read' TO 'user'@'%';
SET DEFAULT ROLE 'app_read' TO 'user'@'%';

## Configuration

SHOW VARIABLES LIKE 'max_connections';
SHOW GLOBAL VARIABLES LIKE 'innodb%';
SHOW STATUS LIKE 'Threads%';
SHOW GLOBAL STATUS;
SET GLOBAL max_connections=500;
SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE';
SET PERSIST max_connections=500; -- 8.0+ persist to mysqld-auto.cnf
SET PERSIST_ONLY ...
RESET PERSIST var_name;
-- Key configs
innodb_buffer_pool_size -- ~70% RAM
innodb_log_file_size / innodb_redo_log_capacity (8.0.30+)
innodb_flush_log_at_trx_commit -- 1 (durable), 2, 0
innodb_flush_method=O_DIRECT
sync_binlog=1
max_connections, thread_cache_size
slow_query_log=ON, long_query_time=1, slow_query_log_file=...
binlog_format=ROW (default)
sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'
default_authentication_plugin=caching_sha2_password
character_set_server=utf8mb4 / collation_server=utf8mb4_0900_ai_ci

## Useful queries

-- Table sizes
SELECT table_schema, table_name,
       ROUND((data_length+index_length)/1024/1024, 2) AS size_mb
FROM information_schema.tables ORDER BY size_mb DESC LIMIT 20;

-- Long-running queries
SELECT id, user, time, state, LEFT(info,80) FROM information_schema.PROCESSLIST WHERE time>10 ORDER BY time DESC;

-- InnoDB lock waits
SELECT * FROM sys.innodb_lock_waits;

-- Unused indexes
SELECT * FROM sys.schema_unused_indexes;

-- Top slow statements
SELECT digest_text, count_star, ROUND(avg_timer_wait/1e9,2) AS avg_ms
FROM performance_schema.events_statements_summary_by_digest
ORDER BY sum_timer_wait DESC LIMIT 20;

-- Buffer pool hit ratio
SELECT (1 - (Innodb_buffer_pool_reads / Innodb_buffer_pool_read_requests)) * 100 AS hit_ratio
FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME IN ('Innodb_buffer_pool_reads','Innodb_buffer_pool_read_requests');

## mysqladmin / mysqlcheck

mysqladmin -u root -p ping
mysqladmin -u root -p status / extended-status
mysqladmin -u root -p processlist
mysqladmin -u root -p flush-logs / flush-hosts / flush-privileges
mysqladmin -u root -p shutdown
mysqladmin -u root -p variables
mysqladmin -u root -p create dbname / drop dbname
mysqlcheck -u root -p --all-databases --check
mysqlcheck -u root -p db --repair / --analyze / --optimize

## Online schema change

-- Native online DDL (8.0)
ALTER TABLE tbl ADD COLUMN c INT, ALGORITHM=INSTANT;  -- O(1) for some changes
ALTER TABLE tbl ADD INDEX idx (col), ALGORITHM=INPLACE, LOCK=NONE;
-- External tools
pt-online-schema-change --alter "ADD COLUMN ..." D=db,t=tbl
gh-ost --alter="ADD COLUMN ..." --database=db --table=tbl --execute

## MySQL vs PostgreSQL fərqləri (qısa)

AUTO_INCREMENT vs SERIAL/IDENTITY
LIMIT n OFFSET m — hər ikisi
ON DUPLICATE KEY UPDATE vs ON CONFLICT DO UPDATE
no FULL OUTER JOIN (UNION ilə emulate)
JSON tipi var, amma JSONB yox; sıralı object key saxlamır
RETURNING yox (8.0-da hələ də yox; INSERT...RETURNING 10.5+ MariaDB-də var)
no array tipi, no native enum-as-type (CREATE TYPE yox)
CTE/Window — 8.0+ var
sql_mode konfiq əhəmiyyətlidir (strict mode default deyil legacy DB-lərdə)
