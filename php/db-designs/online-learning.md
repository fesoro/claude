# Online Learning Platform — DB Design (Udemy / Coursera style)

## Tövsiyə olunan DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                Online Learning DB Stack                          │
├──────────────────────┬──────────────────────────────────────────┤
│ PostgreSQL           │ Courses, users, enrollments, progress    │
│ Redis                │ Progress cache, session, quiz state      │
│ Elasticsearch        │ Course search (title, topic, instructor) │
│ Amazon S3 + CDN      │ Video lectures, PDFs, resources          │
│ Kafka                │ Progress events, certificate generation  │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Schema Design

```sql
-- ==================== USERS ====================
CREATE TABLE users (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    email           VARCHAR(255) UNIQUE NOT NULL,
    username        VARCHAR(50) UNIQUE NOT NULL,
    
    -- Role (bir user həm student, həm instructor ola bilər)
    is_instructor   BOOLEAN DEFAULT FALSE,
    
    first_name      VARCHAR(100),
    last_name       VARCHAR(100),
    bio             TEXT,
    profile_pic     TEXT,
    
    -- Instructor stats
    total_students  INT DEFAULT 0,
    total_courses   INT DEFAULT 0,
    instructor_rating NUMERIC(3,2),
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== COURSES ====================
CREATE TABLE courses (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    instructor_id   BIGINT NOT NULL REFERENCES users(id),
    
    title           VARCHAR(200) NOT NULL,
    slug            VARCHAR(200) UNIQUE NOT NULL,
    subtitle        VARCHAR(300),
    description     TEXT,
    
    -- Classification
    category_id     INT REFERENCES categories(id),
    subcategory_id  INT REFERENCES categories(id),
    tags            TEXT[],
    level           ENUM('beginner', 'intermediate', 'advanced', 'all_levels'),
    language        VARCHAR(5) DEFAULT 'en',
    
    -- Pricing
    price           NUMERIC(8,2) NOT NULL DEFAULT 0,
    is_free         BOOLEAN GENERATED ALWAYS AS (price = 0) STORED,
    
    -- Requirements & outcomes
    requirements    TEXT[],   -- Prerequisites
    what_you_learn  TEXT[],   -- Learning outcomes
    
    -- Media
    thumbnail_url   TEXT,
    preview_video_url TEXT,
    
    -- Stats (denormalized)
    total_students  INT DEFAULT 0,
    rating          NUMERIC(3,2) DEFAULT 0,
    review_count    INT DEFAULT 0,
    total_lectures  SMALLINT DEFAULT 0,
    total_duration_sec INT DEFAULT 0,
    
    -- Status
    status          ENUM('draft', 'pending_review',
                         'published', 'archived') DEFAULT 'draft',
    
    published_at    TIMESTAMPTZ,
    updated_at      TIMESTAMPTZ DEFAULT NOW(),
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== CURRICULUM ====================
-- Section → Lectures (tree structure)

CREATE TABLE sections (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    course_id   BIGINT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    title       VARCHAR(200) NOT NULL,
    sort_order  SMALLINT NOT NULL,
    
    INDEX idx_course (course_id, sort_order)
);

CREATE TABLE lectures (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    section_id      BIGINT NOT NULL REFERENCES sections(id) ON DELETE CASCADE,
    course_id       BIGINT NOT NULL REFERENCES courses(id),
    
    title           VARCHAR(200) NOT NULL,
    sort_order      SMALLINT NOT NULL,
    
    type            ENUM('video', 'article', 'quiz', 'assignment',
                         'live_session') NOT NULL,
    
    -- Video lecture
    video_url       TEXT,
    video_duration_sec INT,
    
    -- Article
    content         TEXT,
    
    -- Resources
    resources       JSONB DEFAULT '[]',
    -- [{"name": "slides.pdf", "url": "...", "type": "pdf"}]
    
    -- Preview (free without enrollment)
    is_preview      BOOLEAN DEFAULT FALSE,
    
    INDEX idx_section (section_id, sort_order),
    INDEX idx_course  (course_id)
);

-- ==================== QUIZZES ====================
CREATE TABLE quizzes (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    lecture_id  BIGINT NOT NULL REFERENCES lectures(id),
    title       VARCHAR(200) NOT NULL,
    pass_percentage SMALLINT DEFAULT 75,
    time_limit_min SMALLINT   -- NULL = no limit
);

CREATE TABLE quiz_questions (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    quiz_id     BIGINT NOT NULL REFERENCES quizzes(id),
    question    TEXT NOT NULL,
    type        ENUM('single_choice', 'multiple_choice', 'true_false') NOT NULL,
    explanation TEXT,   -- açıqlama (cavabdan sonra göstərilir)
    sort_order  SMALLINT,
    
    INDEX idx_quiz (quiz_id, sort_order)
);

CREATE TABLE quiz_options (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    question_id BIGINT NOT NULL REFERENCES quiz_questions(id),
    option_text TEXT NOT NULL,
    is_correct  BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order  SMALLINT
);

-- ==================== ENROLLMENTS ====================
CREATE TABLE enrollments (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    user_id         BIGINT NOT NULL REFERENCES users(id),
    course_id       BIGINT NOT NULL REFERENCES courses(id),
    
    -- Purchase info
    price_paid      NUMERIC(8,2) NOT NULL DEFAULT 0,
    coupon_code     VARCHAR(50),
    
    -- Progress (denormalized)
    progress_pct    SMALLINT DEFAULT 0,   -- 0-100
    completed_lectures INT DEFAULT 0,
    
    -- Certificate
    is_completed    BOOLEAN DEFAULT FALSE,
    completed_at    TIMESTAMPTZ,
    certificate_id  UUID,
    
    -- Status
    status          ENUM('active', 'refunded') DEFAULT 'active',
    
    enrolled_at     TIMESTAMPTZ DEFAULT NOW(),
    last_accessed   TIMESTAMPTZ,
    
    UNIQUE (user_id, course_id),
    INDEX idx_user   (user_id, enrolled_at DESC),
    INDEX idx_course (course_id)
);

-- ==================== PROGRESS ====================
-- Hər lecture üçün ayrı progress record

CREATE TABLE lecture_progress (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    user_id         BIGINT NOT NULL REFERENCES users(id),
    lecture_id      BIGINT NOT NULL REFERENCES lectures(id),
    course_id       BIGINT NOT NULL REFERENCES courses(id),
    
    -- Video progress
    watched_sec     INT DEFAULT 0,       -- neçə saniyə izləndi
    total_sec       INT,
    last_position   INT DEFAULT 0,       -- "resume from here" pozisiyası
    
    is_completed    BOOLEAN DEFAULT FALSE,
    completed_at    TIMESTAMPTZ,
    
    UNIQUE (user_id, lecture_id),
    INDEX idx_user_course (user_id, course_id)
);

-- ==================== QUIZ ATTEMPTS ====================
CREATE TABLE quiz_attempts (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    user_id     BIGINT NOT NULL REFERENCES users(id),
    quiz_id     BIGINT NOT NULL REFERENCES quizzes(id),
    
    score_pct   SMALLINT NOT NULL,
    passed      BOOLEAN NOT NULL,
    
    -- Answers submitted
    answers     JSONB NOT NULL,
    -- [{"question_id": 1, "selected_option_ids": [3]}, ...]
    
    attempt_number SMALLINT NOT NULL DEFAULT 1,
    
    started_at  TIMESTAMPTZ NOT NULL,
    submitted_at TIMESTAMPTZ,
    
    INDEX idx_user_quiz (user_id, quiz_id)
);

-- ==================== REVIEWS ====================
CREATE TABLE reviews (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    enrollment_id BIGINT NOT NULL REFERENCES enrollments(id) UNIQUE,
    user_id     BIGINT NOT NULL REFERENCES users(id),
    course_id   BIGINT NOT NULL REFERENCES courses(id),
    
    rating      SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title       VARCHAR(200),
    body        TEXT,
    
    -- Instructor response
    response    TEXT,
    responded_at TIMESTAMPTZ,
    
    is_featured BOOLEAN DEFAULT FALSE,
    
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    
    INDEX idx_course (course_id, rating DESC)
);

-- ==================== CERTIFICATES ====================
CREATE TABLE certificates (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         BIGINT NOT NULL REFERENCES users(id),
    course_id       BIGINT NOT NULL REFERENCES courses(id),
    enrollment_id   BIGINT NOT NULL REFERENCES enrollments(id),
    
    -- Verification
    verification_url TEXT GENERATED ALWAYS AS (
        'https://udemy.com/certificate/' || id::TEXT
    ) STORED,
    
    -- Snapshot at issuance
    user_name       VARCHAR(200) NOT NULL,
    course_title    VARCHAR(200) NOT NULL,
    instructor_name VARCHAR(200) NOT NULL,
    completion_hours INT,
    
    issued_at       TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE (user_id, course_id)
);
```

---

## Progress Calculation

```sql
-- Kursun tamamlanma yüzdəsi
SELECT
    e.user_id,
    e.course_id,
    COUNT(l.id) AS total_lectures,
    COUNT(lp.id) FILTER (WHERE lp.is_completed = TRUE) AS completed,
    ROUND(
        COUNT(lp.id) FILTER (WHERE lp.is_completed = TRUE) * 100.0
        / NULLIF(COUNT(l.id), 0)
    ) AS progress_pct
FROM enrollments e
JOIN courses c ON c.id = e.course_id
JOIN sections s ON s.course_id = c.id
JOIN lectures l ON l.section_id = s.id
LEFT JOIN lecture_progress lp ON lp.lecture_id = l.id
    AND lp.user_id = e.user_id
WHERE e.user_id = :user_id AND e.course_id = :course_id
GROUP BY e.user_id, e.course_id;

-- "Resume from where I left off"
SELECT l.id, l.title, lp.last_position
FROM lecture_progress lp
JOIN lectures l ON l.id = lp.lecture_id
WHERE lp.user_id = :user_id
  AND lp.course_id = :course_id
  AND lp.is_completed = FALSE
ORDER BY lp.updated_at DESC
LIMIT 1;
```

---

## Redis: Progress & Quiz State

```
# Video progress (frequent updates — every 15 sec)
HSET progress:{user_id}:{lecture_id} position 1234 watched 1200 updated 1699000000
EXPIRE progress:{user_id}:{lecture_id} 86400

# Flush to DB (batch, every 5 minutes)
# Bütün Redis progress-lərini PostgreSQL-ə yaz

# Quiz in-progress state
SET quiz:state:{user_id}:{quiz_id} {answers_json} EX 7200  -- 2 saat

# Course access check (enrolled?)
SET enrolled:{user_id}:{course_id} 1 EX 3600

# Trending courses
ZINCRBY trending:courses:week 1 {course_id}
ZREVRANGE trending:courses:week 0 9  -- top 10
```

---

## Certificate Generation

```
Flow:
1. User lecture_progress-ın hamısı completed = true
2. Kafka: "course_completed" event
3. Certificate service:
   a. enrollments.is_completed = true
   b. certificates table insert
   c. PDF generate (user name + course + date + QR code)
   d. S3-ə yüklə
   e. Email göndər
   f. LinkedIn share link yarat

Verification:
  GET /certificate/{uuid}
  → certificates cədvəlindən oxu
  → Public page (user adı, kurs, tarix)
  → LinkedIn "Add to profile" button

Fraud prevention:
  UUID v4 (unpredictable)
  No sequential IDs
  Verification URL publick accessible (no login)
```

---

## Best Practices

```
✓ Separate lecture_progress table (frequent updates, not in enrollments)
✓ Redis for video position (write every 15 sec → DB-yə deyil)
✓ Batch flush progress Redis → PostgreSQL
✓ Certificate UUID (unforgeable, verifiable)
✓ Quiz answers in JSONB (flexible)
✓ Denormalized progress_pct in enrollments (dashboard performance)
✓ is_preview flag → free trial content

Anti-patterns:
✗ Storing video position in DB on every seek event (too frequent)
✗ Recalculating progress_pct on every page load (SQL heavy)
✗ Sequential certificate IDs (guessable)
✗ One quiz attempt → no retry mechanism
```
