# Healthcare / EHR — DB Design (Lead ⭐⭐⭐⭐)

## Tövsiyə olunan DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                  Healthcare / EHR DB Stack                       │
├──────────────────────┬──────────────────────────────────────────┤
│ PostgreSQL           │ Patient records, appointments, diagnoses │
│ Redis                │ Session, cache, real-time alerts         │
│ Elasticsearch        │ Clinical search, ICD-10/SNOMED codes     │
│ Amazon S3            │ Medical images (DICOM), documents        │
│ Kafka                │ Clinical events, audit stream            │
│ ClickHouse           │ Population health analytics              │
└──────────────────────┴──────────────────────────────────────────┘

Xüsusi tələblər:
  HIPAA (US): Protected Health Information (PHI) qorunması
  GDPR (EU): Personal health data processing
  HL7 FHIR: Interoperability standard
  Audit: hər data access log edilməlidir
```

---

## HIPAA Compliance Tələbləri

```
HIPAA = Health Insurance Portability and Accountability Act

PHI (Protected Health Information):
  Ad, ünvan, doğum tarixi, SSN
  Tibbi tarix, diaqnoz, müalicə
  Ödəniş məlumatları
  
Texniki tədbirlər:
  ✓ Encryption at rest (AES-256)
  ✓ Encryption in transit (TLS 1.2+)
  ✓ Access controls (RBAC)
  ✓ Audit logs (who accessed what, when)
  ✓ Automatic logoff (session timeout)
  ✓ Breach notification (60 days)
  
DB implications:
  Sensitive columns → encrypted
  All SELECT/UPDATE/DELETE → audit log
  Row-level security → doctor sees only own patients
  Data retention: 6-7 years minimum
```

---

## Schema Design

```sql
-- ==================== PATIENTS ====================
CREATE TABLE patients (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    mrn             VARCHAR(20) UNIQUE NOT NULL,  -- Medical Record Number
    
    -- Demographics (PHI - encrypted at application level)
    first_name      TEXT NOT NULL,     -- encrypted
    last_name       TEXT NOT NULL,     -- encrypted
    date_of_birth   DATE NOT NULL,     -- encrypted
    gender          VARCHAR(20),
    
    -- Contact (PHI)
    phone           TEXT,              -- encrypted
    email           TEXT,              -- encrypted
    address         TEXT,              -- encrypted
    
    -- Insurance
    insurance_id    TEXT,              -- encrypted
    insurance_provider VARCHAR(100),
    
    -- Emergency contact
    emergency_contact JSONB,
    
    -- Language preference
    preferred_language VARCHAR(10) DEFAULT 'en',
    
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== PRACTITIONERS ====================
CREATE TABLE practitioners (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    npi         VARCHAR(10) UNIQUE,   -- National Provider Identifier
    user_id     BIGINT REFERENCES users(id),
    
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    
    role        ENUM('physician', 'nurse', 'specialist',
                     'pharmacist', 'admin') NOT NULL,
    
    -- Credentials
    specialty   VARCHAR(100),
    license_number VARCHAR(50),
    license_state  CHAR(2),
    
    department  VARCHAR(100),
    facility_id UUID REFERENCES facilities(id),
    
    is_active   BOOLEAN DEFAULT TRUE
);

-- ==================== APPOINTMENTS ====================
CREATE TABLE appointments (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id      UUID NOT NULL REFERENCES patients(id),
    practitioner_id UUID NOT NULL REFERENCES practitioners(id),
    facility_id     UUID NOT NULL REFERENCES facilities(id),
    
    type            ENUM('routine', 'urgent', 'follow_up',
                         'telehealth', 'emergency') NOT NULL,
    
    scheduled_at    TIMESTAMPTZ NOT NULL,
    duration_min    SMALLINT DEFAULT 30,
    
    status          ENUM('scheduled', 'confirmed', 'checked_in',
                         'in_progress', 'completed', 'cancelled',
                         'no_show') DEFAULT 'scheduled',
    
    chief_complaint TEXT,
    notes           TEXT,
    
    -- Telehealth
    meeting_url     TEXT,
    
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    
    INDEX idx_patient     (patient_id, scheduled_at DESC),
    INDEX idx_practitioner (practitioner_id, scheduled_at DESC),
    INDEX idx_schedule    (scheduled_at, facility_id)
);

-- ==================== ENCOUNTERS (Visits) ====================
-- Her appointment completed → encounter record
CREATE TABLE encounters (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    appointment_id  UUID REFERENCES appointments(id),
    patient_id      UUID NOT NULL REFERENCES patients(id),
    practitioner_id UUID NOT NULL REFERENCES practitioners(id),
    
    encounter_type  ENUM('outpatient', 'inpatient', 'emergency',
                         'telehealth') NOT NULL,
    
    start_time      TIMESTAMPTZ NOT NULL,
    end_time        TIMESTAMPTZ,
    
    -- Clinical notes (SOAP format)
    subjective      TEXT,   -- Patient's complaints
    objective       TEXT,   -- Examination findings
    assessment      TEXT,   -- Diagnosis summary
    plan            TEXT,   -- Treatment plan
    
    discharge_notes TEXT,
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== DIAGNOSES ====================
CREATE TABLE diagnoses (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    encounter_id    UUID NOT NULL REFERENCES encounters(id),
    patient_id      UUID NOT NULL REFERENCES patients(id),
    
    -- ICD-10 code (International Classification of Diseases)
    icd10_code      VARCHAR(10) NOT NULL,  -- e.g., 'J06.9' (Upper respiratory infection)
    icd10_description TEXT NOT NULL,
    
    diagnosis_type  ENUM('primary', 'secondary', 'comorbidity') NOT NULL,
    
    -- Status
    status          ENUM('active', 'resolved', 'chronic', 'rule_out') NOT NULL,
    
    onset_date      DATE,
    resolved_date   DATE,
    
    noted_by        UUID NOT NULL REFERENCES practitioners(id),
    noted_at        TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== MEDICATIONS ====================
CREATE TABLE medications (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    encounter_id    UUID REFERENCES encounters(id),
    patient_id      UUID NOT NULL REFERENCES patients(id),
    
    -- RxNorm code (standard medication identifier)
    rxnorm_code     VARCHAR(20),
    drug_name       VARCHAR(255) NOT NULL,
    generic_name    VARCHAR(255),
    
    -- Prescription details
    dose            VARCHAR(50),        -- '500mg'
    route           VARCHAR(50),        -- 'oral', 'IV', 'topical'
    frequency       VARCHAR(50),        -- 'twice daily', 'every 8 hours'
    duration_days   SMALLINT,
    refills         SMALLINT DEFAULT 0,
    
    -- Status
    status          ENUM('active', 'discontinued', 'completed',
                         'on_hold') DEFAULT 'active',
    
    prescribed_by   UUID NOT NULL REFERENCES practitioners(id),
    prescribed_at   TIMESTAMPTZ DEFAULT NOW(),
    start_date      DATE,
    end_date        DATE,
    
    INDEX idx_patient (patient_id, status)
);

-- ==================== ALLERGIES ====================
CREATE TABLE allergies (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id      UUID NOT NULL REFERENCES patients(id),
    
    allergen        VARCHAR(255) NOT NULL,  -- 'Penicillin', 'Peanuts'
    allergen_type   ENUM('drug', 'food', 'environmental', 'other'),
    
    reaction        TEXT,           -- 'Anaphylaxis', 'Rash'
    severity        ENUM('mild', 'moderate', 'severe', 'life_threatening'),
    
    status          ENUM('active', 'inactive', 'entered_in_error') DEFAULT 'active',
    
    recorded_by     UUID REFERENCES practitioners(id),
    recorded_at     TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== VITALS ====================
CREATE TABLE vitals (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    encounter_id    UUID NOT NULL REFERENCES encounters(id),
    patient_id      UUID NOT NULL REFERENCES patients(id),
    
    -- LOINC codes used in practice
    weight_kg       NUMERIC(5,2),
    height_cm       NUMERIC(5,1),
    bmi             NUMERIC(4,1),
    temperature_c   NUMERIC(4,1),
    
    blood_pressure_systolic  SMALLINT,
    blood_pressure_diastolic SMALLINT,
    
    heart_rate      SMALLINT,       -- bpm
    respiratory_rate SMALLINT,      -- breaths/min
    oxygen_saturation NUMERIC(4,1), -- SpO2 %
    
    blood_glucose   NUMERIC(5,1),   -- mg/dL
    
    recorded_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    INDEX idx_patient_time (patient_id, recorded_at DESC)
);

-- ==================== LAB RESULTS ====================
CREATE TABLE lab_orders (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    encounter_id    UUID REFERENCES encounters(id),
    patient_id      UUID NOT NULL REFERENCES patients(id),
    ordered_by      UUID NOT NULL REFERENCES practitioners(id),
    
    panel_name      VARCHAR(200) NOT NULL,  -- 'Complete Blood Count', 'HbA1c'
    loinc_code      VARCHAR(20),            -- LOINC standard code
    
    status          ENUM('ordered', 'collected', 'processing',
                         'resulted', 'cancelled') DEFAULT 'ordered',
    
    ordered_at      TIMESTAMPTZ DEFAULT NOW(),
    resulted_at     TIMESTAMPTZ
);

CREATE TABLE lab_results (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    lab_order_id    UUID NOT NULL REFERENCES lab_orders(id),
    
    test_name       VARCHAR(200) NOT NULL,
    loinc_code      VARCHAR(20),
    
    value           TEXT NOT NULL,        -- '14.2' or 'Negative'
    unit            VARCHAR(50),          -- 'g/dL', 'mg/dL'
    
    reference_low   NUMERIC(10,3),
    reference_high  NUMERIC(10,3),
    
    interpretation  ENUM('normal', 'low', 'high',
                         'critical_low', 'critical_high', 'abnormal'),
    
    -- Critical values → immediate notification
    is_critical     BOOLEAN DEFAULT FALSE,
    
    resulted_at     TIMESTAMPTZ DEFAULT NOW()
);
```

---

## Audit Log (HIPAA Required)

```sql
-- BÜTÜN PHI data erişimi log edilməlidir

CREATE TABLE audit_logs (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    
    user_id         UUID NOT NULL,        -- kim
    patient_id      UUID,                 -- hansı xəstənin datası
    
    action          VARCHAR(50) NOT NULL, -- 'read', 'update', 'delete', 'print', 'export'
    resource_type   VARCHAR(50) NOT NULL, -- 'encounter', 'diagnosis', 'medication'
    resource_id     UUID,
    
    -- Context
    ip_address      INET,
    user_agent      TEXT,
    session_id      VARCHAR(100),
    
    -- What changed (for updates)
    old_values      JSONB,
    new_values      JSONB,
    
    -- Result
    success         BOOLEAN NOT NULL,
    error_message   TEXT,
    
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
) PARTITION BY RANGE (created_at);

-- Monthly partitions for performance
CREATE TABLE audit_logs_2024_01 PARTITION OF audit_logs
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

CREATE INDEX ON audit_logs (patient_id, created_at DESC);
CREATE INDEX ON audit_logs (user_id, created_at DESC);
```

---

## Row-Level Security (RLS)

```sql
-- Practitioner yalnız öz xəstələrini görə bilsin
ALTER TABLE encounters ENABLE ROW LEVEL SECURITY;

CREATE POLICY encounter_access ON encounters
    FOR SELECT
    USING (
        -- Admin: hamısını görür
        current_user_role() = 'admin'
        OR
        -- Practitioner: öz encounter-larını
        practitioner_id = current_user_id()
        OR
        -- Patient's care team: əlaqəli practitioner-lər
        EXISTS (
            SELECT 1 FROM care_team ct
            WHERE ct.patient_id = encounters.patient_id
              AND ct.practitioner_id = current_user_id()
        )
    );

-- patients table da eyni şəkildə
```

---

## HL7 FHIR Integration

```
FHIR = Fast Healthcare Interoperability Resources
  REST API standard for healthcare data exchange
  Hospital A-dan Hospital B-yə xəstə məlumatı göndər

FHIR Resources = bizim cədvəllər:
  Patient    → patients table
  Encounter  → encounters table
  Condition  → diagnoses table
  MedicationRequest → medications table
  Observation → vitals + lab_results

FHIR endpoint:
  GET  /fhir/Patient/{id}
  GET  /fhir/Patient/{id}/Encounter
  GET  /fhir/Patient/{id}/MedicationRequest?status=active
  POST /fhir/Patient (yeni xəstə yarat)

DB: FHIR JSON-u JSONB-də saxlaya bilərik
  CREATE TABLE fhir_resources (
      id          UUID PRIMARY KEY,
      resource_type VARCHAR(50),
      resource    JSONB NOT NULL,   -- FHIR JSON
      version_id  INT DEFAULT 1,
      last_updated TIMESTAMPTZ DEFAULT NOW()
  );
  
  CREATE INDEX ON fhir_resources USING GIN (resource);
```

---

## Best Practices

```
✓ Encrypt PHI at application level (not just disk)
✓ Audit log ALL data access (not just writes)
✓ Row-level security → practitioners see only their patients
✓ ICD-10/LOINC/RxNorm standard codes → interoperability
✓ Soft delete only (never hard delete medical records)
✓ Version history for clinical notes
✓ FHIR-compatible API design

Anti-patterns:
✗ Logging passwords, full SSN in application logs
✗ Querying PHI without audit trail
✗ Sharing patient data without consent tracking
✗ Using auto-increment ID (guessable) → UUID
✗ No data retention policy (6-7 year minimum)
✗ PHI in URL parameters (server logs capture it)
```
