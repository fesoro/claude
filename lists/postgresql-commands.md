## Connection / psql basics

psql -h host -p 5432 -U user -d dbname — qoşul
psql "postgresql://user:pass@host:5432/db" — connection URI
psql -W — parol soruş
psql -c "SELECT 1" — tək komanda
psql -f script.sql — fayldan icra et
psql -X — .psqlrc oxuma
\q — çıx
\? — psql komandalar siyahısı
\h SELECT — SQL syntax help

## psql meta-commands

\l / \list — databaze siyahısı
\c dbname — databaze dəyiş
\dn — schema siyahısı
\dt — cədvəllər (cari schema)
\dt schema.* — konkret schema
\dt *.* — bütün schema
\dv — view siyahısı
\dm — materialized view
\di — index siyahısı
\ds — sequence
\df — function siyahısı
\df+ fn — function mənbə kodu
\dx — extension siyahısı
\du / \dg — rol/qrup siyahısı
\dp / \z — privilege göstər
\dT — tip siyahısı
\dy — event trigger
\d tablename — cədvəl strukturu
\d+ tablename — ətraflı (ölçü, description)
\sv viewname — view mənbə
\x — expanded display (vertikal)
\x auto — avtomatik
\timing — query vaxtı göstər
\e — query-ni editor-da aç
\ef fnname — function edit
\g — son query-ni təkrarla
\watch 2 — hər 2s-də təkrarla
\i script.sql — fayldan icra et
\o file.txt — output fayla yönəlt
\copy (SELECT ...) TO 'file.csv' CSV HEADER — client-side export
\copy tbl FROM 'file.csv' CSV HEADER — import
\! shell-cmd — shell komanda
\conninfo — qoşulma məlumatı
\password — parol dəyiş
\set VAR value — psql dəyişən
\echo :VAR

## Database / schema

CREATE DATABASE mydb OWNER myuser ENCODING 'UTF8' TEMPLATE template0;
CREATE DATABASE mydb WITH LC_COLLATE='en_US.UTF-8';
DROP DATABASE mydb;
ALTER DATABASE mydb RENAME TO newname;
ALTER DATABASE mydb OWNER TO newowner;
ALTER DATABASE mydb SET search_path TO schema1, public;
CREATE SCHEMA myschema;
CREATE SCHEMA IF NOT EXISTS myschema AUTHORIZATION myuser;
DROP SCHEMA myschema CASCADE;
SET search_path TO myschema, public;
SHOW search_path;

## Tables

CREATE TABLE users (id SERIAL PRIMARY KEY, name TEXT NOT NULL);
CREATE TABLE users (id BIGSERIAL / id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY);
CREATE TABLE users (id UUID DEFAULT gen_random_uuid() PRIMARY KEY); -- pgcrypto
CREATE TABLE IF NOT EXISTS ...
CREATE TEMP TABLE / UNLOGGED TABLE — session / no WAL
CREATE TABLE new_tbl (LIKE old_tbl INCLUDING ALL);
CREATE TABLE new_tbl AS SELECT ... WITH NO DATA;
DROP TABLE tbl / DROP TABLE IF EXISTS tbl CASCADE;
TRUNCATE tbl / TRUNCATE tbl RESTART IDENTITY CASCADE;
ALTER TABLE tbl RENAME TO newtbl;
ALTER TABLE tbl ADD COLUMN c TEXT;
ALTER TABLE tbl DROP COLUMN c;
ALTER TABLE tbl ALTER COLUMN c TYPE INT USING c::int;
ALTER TABLE tbl ALTER COLUMN c SET NOT NULL / DROP NOT NULL;
ALTER TABLE tbl ALTER COLUMN c SET DEFAULT 0 / DROP DEFAULT;
ALTER TABLE tbl ADD CONSTRAINT u UNIQUE (c);
ALTER TABLE tbl ADD CONSTRAINT fk FOREIGN KEY (a) REFERENCES other(id) ON DELETE CASCADE;
ALTER TABLE tbl ADD CONSTRAINT c CHECK (age > 0);
ALTER TABLE tbl DROP CONSTRAINT name;
ALTER TABLE tbl SET (fillfactor = 80);
ALTER TABLE tbl ENABLE / DISABLE TRIGGER name;
ALTER TABLE tbl SET LOGGED / UNLOGGED;

## Index

CREATE INDEX idx ON tbl (col);
CREATE INDEX idx ON tbl (col DESC NULLS LAST);
CREATE UNIQUE INDEX idx ON tbl (col);
CREATE INDEX CONCURRENTLY idx ON tbl (col); — lock-siz (prod)
CREATE INDEX idx ON tbl USING btree (col); — default
CREATE INDEX idx ON tbl USING hash (col); — equality only
CREATE INDEX idx ON tbl USING gin (col); — jsonb, array, full-text
CREATE INDEX idx ON tbl USING gist (col); — geometric, range
CREATE INDEX idx ON tbl USING brin (col); — large append-only
CREATE INDEX idx ON tbl USING spgist (col);
CREATE INDEX idx ON tbl (col) WHERE status='active'; — partial
CREATE INDEX idx ON tbl (lower(email)); — expression
CREATE INDEX idx ON tbl (col) INCLUDE (other); — covering (INCLUDE)
DROP INDEX idx / DROP INDEX CONCURRENTLY idx;
REINDEX INDEX idx / REINDEX TABLE tbl / REINDEX DATABASE db;
REINDEX CONCURRENTLY — 12+
\di+ — index ölçüsü

## Query basics

SELECT DISTINCT col FROM tbl;
SELECT col AS alias FROM tbl;
WHERE col = 'x' AND col2 IN (1,2,3)
WHERE col BETWEEN 1 AND 10
WHERE col LIKE 'abc%' / ILIKE — case-insensitive
WHERE col ~ 'regex' / ~* / !~
WHERE col IS NULL / IS NOT NULL / IS DISTINCT FROM
ORDER BY col ASC/DESC NULLS FIRST/LAST
LIMIT 10 OFFSET 20
FETCH FIRST 10 ROWS ONLY / WITH TIES
GROUP BY col HAVING count(*) > 5
GROUP BY GROUPING SETS / ROLLUP / CUBE
JOIN — INNER / LEFT / RIGHT / FULL / CROSS / LATERAL
USING (col) — join on same-named column
UNION / UNION ALL / INTERSECT / EXCEPT
WITH cte AS (...) SELECT ... FROM cte; — CTE
WITH RECURSIVE — iterative query
WINDOW functions — row_number() OVER (PARTITION BY ... ORDER BY ...)
rank() / dense_rank() / lag() / lead() / first_value() / nth_value()
sum(x) OVER (PARTITION BY y ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)

## Insert / update / delete / upsert

INSERT INTO tbl (a,b) VALUES (1, 'x');
INSERT INTO tbl (a) SELECT ... FROM other;
INSERT ... RETURNING id, *; — qaytardığı satır
INSERT ... ON CONFLICT (col) DO NOTHING;
INSERT ... ON CONFLICT (col) DO UPDATE SET c = EXCLUDED.c WHERE ...; — upsert
UPDATE tbl SET a=1 WHERE id=2 RETURNING *;
UPDATE tbl SET a=b.a FROM other b WHERE tbl.id=b.id;
DELETE FROM tbl WHERE ... RETURNING *;
DELETE FROM tbl USING other WHERE tbl.a=other.a;
MERGE INTO target USING source ON ... WHEN MATCHED ... WHEN NOT MATCHED ...; -- 15+
COPY tbl (a,b) FROM STDIN WITH CSV HEADER; — bulk insert
COPY (SELECT ...) TO STDOUT WITH CSV HEADER; — bulk export

## Types / JSONB / arrays

TEXT / VARCHAR(n) / CHAR(n)
INTEGER / BIGINT / SMALLINT / DECIMAL(p,s) / NUMERIC / REAL / DOUBLE PRECISION
BOOLEAN / BYTEA / UUID
DATE / TIME / TIMESTAMP / TIMESTAMPTZ / INTERVAL
JSON / JSONB — JSON sənəd
ARRAY — INT[], TEXT[] (col INT[])
ENUM — CREATE TYPE status AS ENUM ('a','b');
DOMAIN — CREATE DOMAIN email AS TEXT CHECK (...);
Composite — CREATE TYPE point AS (x INT, y INT);
Range — INT4RANGE, TSRANGE, DATERANGE
TEXT SEARCH — TSVECTOR, TSQUERY
JSONB operators — ->, ->>, #>, #>>, @>, <@, ?, ?|, ?&, ||
jsonb_path_query / jsonb_set / jsonb_build_object / to_jsonb
Array — arr[1], array_length(arr,1), arr @> ARRAY[1,2], unnest(arr)

## Transactions / locking

BEGIN; / START TRANSACTION;
COMMIT; / END;
ROLLBACK;
SAVEPOINT sp; / ROLLBACK TO SAVEPOINT sp; / RELEASE SAVEPOINT sp;
SET TRANSACTION ISOLATION LEVEL READ COMMITTED / REPEATABLE READ / SERIALIZABLE;
SET TRANSACTION READ ONLY;
LOCK TABLE tbl IN ACCESS EXCLUSIVE MODE;
SELECT ... FOR UPDATE / FOR NO KEY UPDATE / FOR SHARE / FOR KEY SHARE;
SELECT ... FOR UPDATE NOWAIT / SKIP LOCKED;
Advisory locks — pg_advisory_lock(n) / pg_try_advisory_lock / pg_advisory_unlock

## EXPLAIN / performance

EXPLAIN SELECT ... — plan göstər
EXPLAIN ANALYZE SELECT ... — real execution + vaxtlar
EXPLAIN (ANALYZE, BUFFERS) SELECT ... — buffer hit/miss
EXPLAIN (ANALYZE, BUFFERS, VERBOSE, SETTINGS, FORMAT JSON)
Seq Scan / Index Scan / Index Only Scan / Bitmap Heap Scan / Bitmap Index Scan
Hash Join / Merge Join / Nested Loop
Sort / Hash Aggregate / Group Aggregate
planning time / execution time / cost (start..total)
pg_stat_statements — top queries
auto_explain — slow query plans
pg_hint_plan — hint extension

## VACUUM / ANALYZE / maintenance

VACUUM — dead tuples təmizlə, boş yer qaytar
VACUUM FULL tbl; — rewrite table (exclusive lock!)
VACUUM (VERBOSE, ANALYZE) tbl;
VACUUM (FREEZE) tbl;
VACUUM (INDEX_CLEANUP ON/OFF, TRUNCATE OFF)
ANALYZE tbl; — stats yenilə
REINDEX / REINDEX CONCURRENTLY
CLUSTER tbl USING idx; — fiziki reorder
autovacuum — avtomatik (konfiq parametrləri)
pg_stat_user_tables.n_dead_tup — dead rows sayı
pg_stat_progress_vacuum — VACUUM status

## System catalogs / views

pg_stat_activity — aktiv sessiyalar, query, wait_event
pg_stat_statements — query statistics (extension)
pg_stat_user_tables / pg_stat_user_indexes
pg_stat_bgwriter / pg_stat_wal / pg_stat_database / pg_stat_archiver
pg_stat_replication — replication lag
pg_stat_subscription (logical repl)
pg_locks — lock siyahısı
pg_blocking_pids(pid) — kimi blok edir
pg_indexes / pg_index
pg_tables / pg_class / pg_attribute / pg_namespace
information_schema.tables / columns / routines / constraint_column_usage
pg_roles / pg_user / pg_shadow
pg_database / pg_settings / pg_available_extensions / pg_extension
pg_stat_ssl / pg_hba_file_rules

## Roles / privileges

CREATE ROLE user1 LOGIN PASSWORD 'xxx';
CREATE ROLE user1 WITH LOGIN CREATEDB SUPERUSER;
ALTER ROLE user1 WITH PASSWORD 'new';
ALTER ROLE user1 SET search_path TO myschema;
DROP ROLE user1;
GRANT SELECT ON tbl TO user1;
GRANT SELECT, INSERT ON ALL TABLES IN SCHEMA public TO user1;
GRANT USAGE ON SCHEMA public TO user1;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO user1;
REVOKE ALL ON tbl FROM user1;
GRANT user1 TO user2; — rol üzvlük
SET ROLE user1; / RESET ROLE;
Row-Level Security — ALTER TABLE tbl ENABLE ROW LEVEL SECURITY; CREATE POLICY ...

## pg_dump / pg_restore

pg_dump -h host -U user -d db > dump.sql — plain SQL
pg_dump -Fc -d db -f dump.pgbin — custom format (suggested)
pg_dump -Fd -d db -f dumpdir -j 4 — directory (parallel)
pg_dump -Ft -d db -f dump.tar — tar
pg_dump -t users -t orders -d db — konkret cədvəl
pg_dump -n schema -d db — konkret schema
pg_dump --data-only / --schema-only / --no-owner / --no-privileges
pg_dump --exclude-table-data=audit_log
pg_dumpall > all.sql — bütün cluster (global + databazalar)
pg_dumpall -g > globals.sql — yalnız roles/tablespaces
psql -d db < dump.sql — plain restore
pg_restore -d db dump.pgbin — custom
pg_restore -j 4 -d db dumpdir — parallel
pg_restore --clean --if-exists -d db dump.pgbin
pg_restore -l dump.pgbin — TOC göstər
pg_restore -L list.txt — seçilmiş items
pg_basebackup -h host -U replicator -D /data -P -Fp -R — physical backup

## Replication

Streaming replication (physical) — WAL shipping
Logical replication — publication / subscription
CREATE PUBLICATION pub FOR TABLE t1, t2;
CREATE SUBSCRIPTION sub CONNECTION 'host=... dbname=...' PUBLICATION pub;
ALTER PUBLICATION / ALTER SUBSCRIPTION ... REFRESH PUBLICATION;
pg_create_physical_replication_slot('slot1');
pg_create_logical_replication_slot('slot1', 'pgoutput');
SELECT * FROM pg_replication_slots;
SELECT * FROM pg_stat_replication;
SELECT pg_current_wal_lsn(), pg_last_wal_replay_lsn();
SELECT pg_is_in_recovery(); — primary/replica?
pg_promote() — replica → primary
Synchronous — synchronous_standby_names config
Hot standby — replica read-only query
Patroni / repmgr / pg_auto_failover — HA tools
pglogical / Debezium — CDC

## Partitioning

CREATE TABLE orders (id INT, created DATE) PARTITION BY RANGE (created);
CREATE TABLE orders_2025 PARTITION OF orders FOR VALUES FROM ('2025-01-01') TO ('2026-01-01');
PARTITION BY LIST (country) / HASH (user_id)
CREATE TABLE ... PARTITION OF ... DEFAULT — default partition
ATTACH PARTITION / DETACH PARTITION [CONCURRENTLY]
Partition pruning — planner skip
Declarative partitioning (10+)
pg_partman — extension for automation

## Functions / triggers / procedures

CREATE FUNCTION fn(x INT) RETURNS INT LANGUAGE sql AS $$ SELECT x+1 $$;
CREATE OR REPLACE FUNCTION fn() RETURNS void LANGUAGE plpgsql AS $$ BEGIN ... END $$;
CREATE PROCEDURE p() LANGUAGE plpgsql AS $$ BEGIN COMMIT; END $$; -- 11+
CALL p();
CREATE TRIGGER tg BEFORE INSERT ON tbl FOR EACH ROW EXECUTE FUNCTION fn();
Trigger levels — BEFORE / AFTER / INSTEAD OF; ROW / STATEMENT
Event triggers — DDL-ə
DROP FUNCTION / DROP TRIGGER
LANGUAGE plpgsql / sql / plpython3u / plperl

## Extensions

CREATE EXTENSION postgis;
CREATE EXTENSION pg_stat_statements;
CREATE EXTENSION pgcrypto; — gen_random_uuid
CREATE EXTENSION uuid-ossp;
CREATE EXTENSION hstore / citext / pg_trgm / btree_gin / btree_gist
CREATE EXTENSION timescaledb / pgvector / pg_partman / pg_cron
DROP EXTENSION name CASCADE;
ALTER EXTENSION name UPDATE;
\dx — installed list

## Config / admin

SHOW config_name; / SHOW ALL;
SET config_name = value; — session
SET LOCAL — transaction only
ALTER SYSTEM SET shared_buffers = '4GB'; — postgresql.auto.conf
SELECT pg_reload_conf(); — SIGHUP
pg_ctl reload / restart / stop / start
pg_ctl -D /data promote
postgres.conf / pg_hba.conf / pg_ident.conf
SELECT pg_cancel_backend(pid); — query cancel
SELECT pg_terminate_backend(pid); — connection kill
SELECT pg_size_pretty(pg_database_size('db'));
SELECT pg_size_pretty(pg_total_relation_size('tbl'));
SELECT pg_size_pretty(pg_indexes_size('tbl'));

## Useful queries

-- Running queries
SELECT pid, usename, state, query_start, wait_event, query FROM pg_stat_activity WHERE state != 'idle';

-- Lock analysis
SELECT * FROM pg_locks l JOIN pg_stat_activity a USING (pid) WHERE NOT granted;

-- Table size
SELECT relname, pg_size_pretty(pg_total_relation_size(relid)) FROM pg_stat_user_tables ORDER BY pg_total_relation_size(relid) DESC;

-- Unused indexes
SELECT relname, indexrelname, idx_scan FROM pg_stat_user_indexes WHERE idx_scan = 0;

-- Index bloat (approximate)
SELECT * FROM pg_stat_user_indexes WHERE idx_scan < 100;

-- Slow queries (pg_stat_statements)
SELECT query, calls, mean_exec_time, total_exec_time FROM pg_stat_statements ORDER BY total_exec_time DESC LIMIT 20;
