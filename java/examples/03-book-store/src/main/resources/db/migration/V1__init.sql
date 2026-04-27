CREATE TABLE authors (
    id      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name    VARCHAR(255) NOT NULL,
    bio     TEXT
);

CREATE TABLE books (
    id        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title     VARCHAR(255) NOT NULL,
    isbn      VARCHAR(20)  UNIQUE,
    year      INT,
    author_id BIGINT NOT NULL REFERENCES authors(id)
);

-- Seed data
INSERT INTO authors (name, bio) VALUES
  ('Robert C. Martin', 'Software engineer, author of Clean Code'),
  ('Martin Kleppmann', 'Distributed systems researcher');

INSERT INTO books (title, isbn, year, author_id) VALUES
  ('Clean Code',                      '978-0132350884', 2008, 1),
  ('Clean Architecture',              '978-0134494166', 2017, 1),
  ('Designing Data-Intensive Applications', '978-1449373320', 2017, 2);
