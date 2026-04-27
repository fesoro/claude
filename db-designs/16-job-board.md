# Job Board — DB Design (LinkedIn Jobs / Indeed style)

## Tövsiyə olunan DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                    Job Board DB Stack                            │
├──────────────────────┬──────────────────────────────────────────┤
│ PostgreSQL           │ Users, jobs, applications, companies     │
│ Elasticsearch        │ Job search (title, skills, location, salary)│
│ Redis                │ Session, job alerts cache, view counts   │
│ S3                   │ Resumes (PDF), company logos             │
│ Kafka                │ Application events, email notifications  │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Schema Design

```sql
-- ==================== USERS ====================
CREATE TABLE users (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    email           VARCHAR(255) UNIQUE NOT NULL,
    
    -- Profile
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    headline        VARCHAR(220),   -- "Senior PHP Developer at Acme"
    summary         TEXT,
    location        VARCHAR(100),
    
    -- Contact
    phone           VARCHAR(30),
    linkedin_url    VARCHAR(200),
    github_url      VARCHAR(200),
    portfolio_url   VARCHAR(200),
    
    -- Resume
    resume_url      TEXT,           -- S3 key
    resume_updated  TIMESTAMPTZ,
    
    -- Privacy
    is_open_to_work BOOLEAN DEFAULT FALSE,
    profile_visibility ENUM('public', 'recruiters_only', 'private') DEFAULT 'public',
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Work experience
CREATE TABLE experiences (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    user_id     BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    title       VARCHAR(200) NOT NULL,
    company     VARCHAR(200) NOT NULL,
    location    VARCHAR(100),
    is_current  BOOLEAN DEFAULT FALSE,
    start_date  DATE NOT NULL,
    end_date    DATE,   -- NULL if current
    description TEXT,
    
    INDEX idx_user (user_id)
);

-- Education
CREATE TABLE educations (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    user_id     BIGINT NOT NULL REFERENCES users(id),
    
    institution VARCHAR(200) NOT NULL,
    degree      VARCHAR(100),  -- 'Bachelor of Science'
    field       VARCHAR(100),  -- 'Computer Science'
    start_year  SMALLINT,
    end_year    SMALLINT,
    gpa         NUMERIC(3,2),
    
    INDEX idx_user (user_id)
);

-- Skills
CREATE TABLE skills (
    id    INT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name  VARCHAR(100) UNIQUE NOT NULL  -- 'PHP', 'PostgreSQL', 'Docker'
);

CREATE TABLE user_skills (
    user_id  BIGINT NOT NULL REFERENCES users(id),
    skill_id INT NOT NULL REFERENCES skills(id),
    years    SMALLINT,
    PRIMARY KEY (user_id, skill_id),
    INDEX idx_skill (skill_id)
);

-- ==================== COMPANIES ====================
CREATE TABLE companies (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(255) UNIQUE NOT NULL,
    
    -- Info
    description TEXT,
    industry    VARCHAR(100),
    company_size ENUM('1-10','11-50','51-200','201-500',
                      '501-1000','1001-5000','5000+'),
    founded_year SMALLINT,
    
    -- Location
    headquarters VARCHAR(200),
    
    -- Media
    logo_url    TEXT,
    website     VARCHAR(255),
    linkedin_url VARCHAR(255),
    
    -- Stats
    follower_count INT DEFAULT 0,
    job_count     INT DEFAULT 0,
    
    -- Verification
    is_verified BOOLEAN DEFAULT FALSE,
    
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- Company admin users
CREATE TABLE company_members (
    company_id  BIGINT NOT NULL REFERENCES companies(id),
    user_id     BIGINT NOT NULL REFERENCES users(id),
    role        ENUM('owner', 'admin', 'recruiter') NOT NULL,
    PRIMARY KEY (company_id, user_id)
);

-- ==================== JOBS ====================
CREATE TABLE jobs (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    company_id      BIGINT NOT NULL REFERENCES companies(id),
    posted_by       BIGINT NOT NULL REFERENCES users(id),
    
    title           VARCHAR(200) NOT NULL,
    
    -- Job details
    employment_type ENUM('full_time', 'part_time', 'contract',
                         'freelance', 'internship') NOT NULL,
    work_model      ENUM('onsite', 'remote', 'hybrid') NOT NULL,
    experience_level ENUM('entry', 'mid', 'senior', 'lead', 'executive'),
    
    -- Location
    location        VARCHAR(200),
    country         CHAR(2),
    is_remote       BOOLEAN DEFAULT FALSE,
    
    -- Compensation
    salary_min      INT,
    salary_max      INT,
    salary_currency CHAR(3) DEFAULT 'USD',
    salary_period   ENUM('hourly', 'monthly', 'annual'),
    
    -- Content
    description     TEXT NOT NULL,
    requirements    TEXT,
    benefits        TEXT,
    
    -- Skills required
    required_skills  INT[],   -- skill IDs
    preferred_skills INT[],
    
    -- Application settings
    application_url  TEXT,      -- external application URL
    application_email VARCHAR(255),
    
    -- Status
    status          ENUM('draft', 'active', 'paused',
                         'closed', 'expired') DEFAULT 'draft',
    
    -- Stats
    view_count      INT DEFAULT 0,
    application_count INT DEFAULT 0,
    
    -- Expiry
    expires_at      TIMESTAMPTZ DEFAULT NOW() + INTERVAL '30 days',
    
    published_at    TIMESTAMPTZ,
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    
    INDEX idx_company   (company_id, published_at DESC),
    INDEX idx_status    (status, published_at DESC),
    INDEX idx_location  (country, is_remote, status)
);

-- ==================== APPLICATIONS ====================
CREATE TABLE applications (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    job_id          BIGINT NOT NULL REFERENCES jobs(id),
    user_id         BIGINT NOT NULL REFERENCES users(id),
    
    -- Submitted docs (snapshot at application time)
    resume_url      TEXT NOT NULL,
    cover_letter    TEXT,
    
    -- Status pipeline
    status          ENUM(
        'submitted',
        'viewed',
        'screening',
        'interview_scheduled',
        'interviewed',
        'offer_extended',
        'hired',
        'rejected',
        'withdrawn'
    ) NOT NULL DEFAULT 'submitted',
    
    -- Recruiter notes (internal)
    recruiter_notes TEXT,
    rejection_reason VARCHAR(100),
    
    -- Anti-spam: bir iş üçün bir müraciət
    UNIQUE (job_id, user_id),
    
    applied_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW(),
    
    INDEX idx_job  (job_id, applied_at DESC),
    INDEX idx_user (user_id, applied_at DESC)
);

-- Application status history
CREATE TABLE application_events (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    application_id  BIGINT NOT NULL REFERENCES applications(id),
    old_status      VARCHAR(50),
    new_status      VARCHAR(50) NOT NULL,
    note            TEXT,
    changed_by      BIGINT REFERENCES users(id),
    changed_at      TIMESTAMPTZ DEFAULT NOW(),
    
    INDEX idx_application (application_id)
);

-- ==================== JOB ALERTS ====================
CREATE TABLE job_alerts (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    user_id     BIGINT NOT NULL REFERENCES users(id),
    
    -- Alert criteria
    keywords    VARCHAR(200),
    location    VARCHAR(100),
    country     CHAR(2),
    is_remote   BOOLEAN,
    employment_type TEXT[],
    salary_min  INT,
    skill_ids   INT[],
    
    frequency   ENUM('instant', 'daily', 'weekly') DEFAULT 'daily',
    is_active   BOOLEAN DEFAULT TRUE,
    
    last_sent_at TIMESTAMPTZ,
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

-- Saved jobs (bookmark)
CREATE TABLE saved_jobs (
    user_id    BIGINT NOT NULL REFERENCES users(id),
    job_id     BIGINT NOT NULL REFERENCES jobs(id),
    saved_at   TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (user_id, job_id)
);
```

---

## Elasticsearch: Job Search

```json
{
  "index": "jobs",
  "mappings": {
    "properties": {
      "job_id":         {"type": "long"},
      "title":          {"type": "text", "analyzer": "english"},
      "description":    {"type": "text", "analyzer": "english"},
      "company_name":   {"type": "text"},
      "employment_type":{"type": "keyword"},
      "work_model":     {"type": "keyword"},
      "experience_level":{"type": "keyword"},
      "location":       {"type": "geo_point"},
      "country":        {"type": "keyword"},
      "is_remote":      {"type": "boolean"},
      "salary_min":     {"type": "integer"},
      "salary_max":     {"type": "integer"},
      "required_skills":{"type": "keyword"},
      "status":         {"type": "keyword"},
      "published_at":   {"type": "date"}
    }
  }
}
```

```json
// "Remote PHP developer, $80K+, senior level"
{
  "query": {
    "bool": {
      "must": [
        {"match": {"title": "PHP developer"}},
        {"term": {"is_remote": true}}
      ],
      "filter": [
        {"term": {"experience_level": "senior"}},
        {"term": {"status": "active"}},
        {"range": {"salary_min": {"gte": 80000}}}
      ]
    }
  },
  "sort": [{"published_at": "desc"}]
}
```

---

## Job Alert Matching

```sql
-- Alert sisteminin sorğusu: hər gün yeni uyğun işlər

SELECT DISTINCT j.id
FROM jobs j
CROSS JOIN job_alerts ja
WHERE ja.user_id = :user_id
  AND ja.is_active = TRUE
  AND j.published_at > ja.last_sent_at
  AND j.status = 'active'
  -- Keyword match
  AND (ja.keywords IS NULL OR
       j.title ILIKE '%' || ja.keywords || '%')
  -- Location
  AND (ja.is_remote IS NULL OR j.is_remote = ja.is_remote)
  AND (ja.country IS NULL OR j.country = ja.country)
  -- Salary
  AND (ja.salary_min IS NULL OR j.salary_max >= ja.salary_min)
  -- Skills (array overlap)
  AND (ja.skill_ids IS NULL OR
       j.required_skills && ja.skill_ids);
```

---

## Best Practices

```
✓ Elasticsearch for job search (faceted filters, full-text, geo)
✓ Application status history → audit trail + timeline display
✓ UNIQUE(job_id, user_id) in applications → duplicate prevention
✓ Salary range (min/max) → range filter possible
✓ Job expiry (expires_at) → auto-close old listings
✓ required_skills as array → overlap query (&&)
✓ Resume URL snapshot at application time → resume changes later

Anti-patterns:
✗ Full-text job search on PostgreSQL (ilike '%PHP%') at scale
✗ No application status history (can't show timeline to candidate)
✗ Storing skills as comma-separated string
✗ No rate limiting on applications (spam)
```
