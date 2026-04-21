## DQL (Data Query)

SELECT ... FROM ... WHERE ...
DISTINCT — dublikatları sil
ORDER BY col ASC/DESC
GROUP BY col [HAVING agg_cond]
LIMIT n [OFFSET m] — MySQL/Postgres
FETCH FIRST n ROWS ONLY / OFFSET n ROWS — ANSI
TOP n — SQL Server

## Operators

AND / OR / NOT
IN / NOT IN
BETWEEN a AND b
LIKE 'ab%' / ILIKE (case-insensitive, Postgres)
SIMILAR TO / regex (~, !~, ~*)
IS NULL / IS NOT NULL
IS DISTINCT FROM / IS NOT DISTINCT FROM (NULL-safe =)
EXISTS / NOT EXISTS
ANY / ALL / SOME
COALESCE(a, b, c)
NULLIF(a, b)
CASE WHEN ... THEN ... ELSE ... END
CAST(x AS type) / x::type

## DML (Data Manipulation)

INSERT INTO t (c1, c2) VALUES (...)
INSERT INTO t SELECT ...
INSERT ... ON CONFLICT (col) DO NOTHING / DO UPDATE SET ... — Postgres upsert
INSERT ... ON DUPLICATE KEY UPDATE — MySQL upsert
MERGE INTO target USING source ON ... WHEN MATCHED / NOT MATCHED — ANSI/SQL Server
UPDATE t SET c = v WHERE ...
UPDATE t SET ... FROM other_t WHERE ... — Postgres/SQL Server
DELETE FROM t WHERE ...
TRUNCATE TABLE t — instant, no log (usually)
RETURNING * — Postgres / SQL Server OUTPUT

## Joins

INNER JOIN / JOIN
LEFT JOIN / LEFT OUTER JOIN
RIGHT JOIN / RIGHT OUTER JOIN
FULL OUTER JOIN
CROSS JOIN — Cartesian product
SELF JOIN — eyni table
NATURAL JOIN — eyni adlı sütunlara
USING (col) — qısa JOIN syntax
Anti-join (WHERE NOT EXISTS / LEFT JOIN ... WHERE other IS NULL)
Semi-join (EXISTS)
LATERAL JOIN / CROSS APPLY — əvvəlki row-a əsasən

## Set operations

UNION / UNION ALL (UNION ALL sürətli — dedup yoxdur)
INTERSECT / INTERSECT ALL
EXCEPT / MINUS (Oracle)

## Aggregates

COUNT(*) / COUNT(col) / COUNT(DISTINCT col)
SUM / AVG / MIN / MAX
STDDEV / VARIANCE
STRING_AGG / GROUP_CONCAT / LISTAGG (DB-spesifik)
ARRAY_AGG — Postgres
JSON_AGG / JSON_OBJECT_AGG — Postgres
FILTER (WHERE ...) — Postgres aggregate filter
GROUPING SETS / ROLLUP / CUBE — multi-dimensional aggregation

## Subqueries / CTE

Subquery (scalar, row, table)
Correlated Subquery — outer-ə istinad
IN (subquery), EXISTS (subquery)
WITH cte AS (SELECT ...) SELECT * FROM cte — CTE
WITH RECURSIVE cte AS (... UNION ALL ...) — recursive CTE (tree, hierarchy)
Materialized CTE (Postgres 12+ hint)

## Window functions

ROW_NUMBER() OVER (PARTITION BY ... ORDER BY ...)
RANK() — gap var (1,1,3)
DENSE_RANK() — gap yox (1,1,2)
NTILE(n)
LAG(col, offset, default) / LEAD
FIRST_VALUE(col) / LAST_VALUE / NTH_VALUE
SUM/AVG/COUNT OVER (...) — running/partitioned agg
PARTITION BY col
ORDER BY col
ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
RANGE BETWEEN INTERVAL '7 day' PRECEDING AND CURRENT ROW

## DDL (Data Definition)

CREATE TABLE t (c1 type [constraints], ...)
CREATE TABLE ... LIKE / AS SELECT
ALTER TABLE t ADD COLUMN / DROP COLUMN / ALTER COLUMN
ALTER TABLE t RENAME TO ...
ALTER TABLE t ADD CONSTRAINT ...
DROP TABLE t [CASCADE]
CREATE INDEX idx ON t (c1, c2) — B-tree default
CREATE UNIQUE INDEX
CREATE INDEX CONCURRENTLY — Postgres (no lock)
CREATE INDEX ... USING HASH / GIN / GiST / BRIN (Postgres)
DROP INDEX idx
CREATE VIEW v AS SELECT ...
CREATE MATERIALIZED VIEW — Postgres/Oracle
REFRESH MATERIALIZED VIEW [CONCURRENTLY]
CREATE SEQUENCE / NEXTVAL / CURRVAL
CREATE TYPE (enum, composite)
CREATE DOMAIN — Postgres constrained type
CREATE SCHEMA / DROP SCHEMA
CREATE DATABASE

## Constraints

PRIMARY KEY
FOREIGN KEY REFERENCES ... [ON DELETE CASCADE|SET NULL|RESTRICT] [ON UPDATE ...]
UNIQUE
NOT NULL
CHECK (cond)
DEFAULT val
EXCLUSION constraints (Postgres — non-overlap ranges)
Deferred constraints (INITIALLY DEFERRED)

## Indexing

B-tree (default) — range scan, =, <, >
Hash — yalnız =
GIN — inverted (full-text, JSONB, arrays)
GiST — geometry, full-text
BRIN — böyük sequential tables
Partial index (WHERE cond)
Expression / functional index (ON t (LOWER(email)))
Covering index (INCLUDE cols)
Clustered index (SQL Server, InnoDB default PK)
Unique index = unique constraint
Composite index column order (leftmost-prefix rule)
Index-only scan
Bitmap index (Postgres runtime, Oracle structural)
Full-text index (FULLTEXT, GIN tsvector)

## Transactions / isolation

BEGIN / START TRANSACTION
COMMIT / ROLLBACK
SAVEPOINT name / ROLLBACK TO SAVEPOINT
SET TRANSACTION ISOLATION LEVEL ...
Isolation levels: READ UNCOMMITTED, READ COMMITTED, REPEATABLE READ, SERIALIZABLE
Phenomena: Dirty read, Non-repeatable read, Phantom read, Serialization anomaly
ACID (Atomicity, Consistency, Isolation, Durability)
MVCC (Postgres, Oracle, MySQL InnoDB)
Locking: row lock, table lock, gap lock (MySQL)
SELECT ... FOR UPDATE [SKIP LOCKED / NOWAIT]
SELECT ... FOR SHARE
Optimistic locking (version column)
Pessimistic locking (FOR UPDATE)

## Query performance

EXPLAIN ... — query plan
EXPLAIN ANALYZE ... — real execution
EXPLAIN (BUFFERS, ANALYZE) — Postgres buffer stats
Sequential Scan vs Index Scan vs Bitmap Heap Scan
Nested Loop vs Hash Join vs Merge Join
Rows / Cost estimates
ANALYZE table — stats update
VACUUM / VACUUM FULL / AUTOVACUUM (Postgres)
REINDEX
pg_stat_statements — query stats
Slow query log (MySQL)
Query hints (MySQL USE INDEX, SQL Server OPTION)

## Data types

Numeric: INT, SMALLINT, BIGINT, DECIMAL(p,s), NUMERIC, REAL, DOUBLE PRECISION
String: VARCHAR(n), CHAR(n), TEXT
Boolean: BOOL / BOOLEAN / BIT
Date/time: DATE, TIME, TIMESTAMP [WITH TIME ZONE / WITHOUT], INTERVAL
Binary: BLOB, BYTEA, VARBINARY
JSON: JSON, JSONB (Postgres — binary, indexed)
UUID
Array (Postgres): INT[], TEXT[]
ENUM (Postgres, MySQL)
Geometry: POINT, LINESTRING, POLYGON (PostGIS, MySQL Spatial)
Network: INET, CIDR (Postgres)
Full-text: TSVECTOR, TSQUERY (Postgres)
XML
Range types (Postgres: int4range, tstzrange)

## JSON operations

Postgres:
  col->'key' (JSON), col->>'key' (text), col#>'{a,b}' (nested)
  jsonb_set, jsonb_path_query
  @> contains, ? key exists
MySQL: JSON_EXTRACT, ->>, JSON_SET, JSON_CONTAINS
SQL Server: JSON_VALUE, OPENJSON
Oracle: JSON_VALUE, JSON_TABLE

## Programming

Stored Procedure (CREATE PROCEDURE, CALL)
Function (CREATE FUNCTION, RETURNS)
Trigger (BEFORE/AFTER INSERT/UPDATE/DELETE FOR EACH ROW)
Cursor (DECLARE, FETCH, CLOSE)
Variables (DECLARE, SET)
Control flow (IF, LOOP, WHILE, CASE)
Exception handling (EXCEPTION WHEN ...)
PL/pgSQL / T-SQL / PL/SQL / MySQL stored procs
Event Scheduler (MySQL)
Dynamic SQL (EXECUTE)

## Security

GRANT / REVOKE
Roles
Row-Level Security (Postgres POLICY)
Column-Level Privilege
VIEW as security boundary
Parameterized query (SQL injection defense)
Least privilege principle

## Advanced patterns

Upsert (ON CONFLICT / ON DUPLICATE KEY / MERGE)
Bulk insert (COPY, LOAD DATA)
Pagination: offset vs keyset (seek)
Soft delete (deleted_at IS NULL)
Audit trigger
Tree: adjacency list, path enumeration, nested sets, closure table, ltree (Postgres)
Pivot / Unpivot (CROSSTAB, FILTER)
Deduplication (ROW_NUMBER window)
Top-N per group (ROW_NUMBER() <= N)
Gap detection (LAG, NOT EXISTS)
Running total (SUM OVER)
Sliding window average
Temporal / bitemporal tables (period FOR SYSTEM_TIME)

## Replication / scaling

Leader-follower (primary-replica) — read scale
Multi-leader (circular) — avoid in most cases
Leaderless (Cassandra, DynamoDB)
Sharding (hash, range, directory)
Read replica lag
Logical vs Physical replication
Synchronous vs Asynchronous replication
Quorum (R+W>N)
Partitioning (PARTITION BY RANGE/LIST/HASH)
Declarative partitioning (Postgres)

## Dialect differences

MySQL: AUTO_INCREMENT, LIMIT, BACKTICKS, ENGINE=InnoDB
Postgres: SERIAL/IDENTITY, RETURNING, arrays, JSONB, rich types, CTE, window
SQL Server: IDENTITY, TOP, OUTPUT, NVARCHAR
Oracle: SEQUENCE, DUAL, CONNECT BY (hierarchical)
SQLite: limited but flexible; flexible typing
