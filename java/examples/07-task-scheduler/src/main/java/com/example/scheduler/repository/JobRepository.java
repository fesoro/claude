package com.example.scheduler.repository;

import com.example.scheduler.entity.JobRecord;
import com.example.scheduler.entity.JobStatus;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;

import java.util.List;

public interface JobRepository extends JpaRepository<JobRecord, Long> {

    List<JobRecord> findByJobTypeOrderByStartedAtDesc(String jobType);

    // Aktiv (RUNNING) job varmı — ikinci instance başlamaması üçün
    boolean existsByJobTypeAndStatus(String jobType, JobStatus status);

    @Query("SELECT j.jobType, COUNT(j), SUM(CASE WHEN j.status='SUCCESS' THEN 1 ELSE 0 END) " +
           "FROM JobRecord j GROUP BY j.jobType")
    List<Object[]> statsGrouped();
}
