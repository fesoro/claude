package com.example.scheduler.entity;

import jakarta.persistence.*;
import java.time.Duration;
import java.time.Instant;

@Entity
@Table(name = "job_records")
public class JobRecord {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private String jobType;

    @Enumerated(EnumType.STRING)
    @Column(nullable = false)
    private JobStatus status;

    @Column(updatable = false)
    private Instant startedAt = Instant.now();

    private Instant finishedAt;

    private String errorMessage;

    private Integer processedCount;

    public static JobRecord start(String jobType) {
        JobRecord r = new JobRecord();
        r.jobType = jobType;
        r.status  = JobStatus.RUNNING;
        return r;
    }

    public void success(int processed) {
        this.status         = JobStatus.SUCCESS;
        this.finishedAt     = Instant.now();
        this.processedCount = processed;
    }

    public void fail(String error) {
        this.status        = JobStatus.FAILED;
        this.finishedAt    = Instant.now();
        this.errorMessage  = error;
    }

    public Duration duration() {
        if (finishedAt == null) return null;
        return Duration.between(startedAt, finishedAt);
    }

    public Long getId()              { return id; }
    public String getJobType()       { return jobType; }
    public JobStatus getStatus()     { return status; }
    public Instant getStartedAt()    { return startedAt; }
    public Instant getFinishedAt()   { return finishedAt; }
    public String getErrorMessage()  { return errorMessage; }
    public Integer getProcessedCount() { return processedCount; }
}
