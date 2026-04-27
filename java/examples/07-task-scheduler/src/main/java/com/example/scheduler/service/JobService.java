package com.example.scheduler.service;

import com.example.scheduler.entity.JobRecord;
import com.example.scheduler.entity.JobStatus;
import com.example.scheduler.repository.JobRepository;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;

@Service
public class JobService {

    private static final Logger log = LoggerFactory.getLogger(JobService.class);
    private final JobRepository repo;

    public JobService(JobRepository repo) { this.repo = repo; }

    public List<JobRecord> findAll(String type) {
        if (type != null) return repo.findByJobTypeOrderByStartedAtDesc(type);
        return repo.findAll();
    }

    @Transactional
    public JobRecord startJob(String type) {
        // Eyni tip job artıq RUNNING-dirsə skip et
        if (repo.existsByJobTypeAndStatus(type, JobStatus.RUNNING)) {
            log.warn("[{}] artıq işləyir, skip edilir", type);
            return null;
        }
        JobRecord record = JobRecord.start(type);
        return repo.save(record);
    }

    @Transactional
    public void finishSuccess(JobRecord record, int processed) {
        record.success(processed);
        repo.save(record);
        log.info("[{}] uğurla tamamlandı. {} element emal edildi. Müddət: {}",
                record.getJobType(), processed, record.duration());
    }

    @Transactional
    public void finishFailed(JobRecord record, Exception ex) {
        record.fail(ex.getMessage());
        repo.save(record);
        log.error("[{}] uğursuz oldu: {}", record.getJobType(), ex.getMessage());
    }

    public List<Map<String, Object>> stats() {
        return repo.statsGrouped().stream().map(row -> Map.of(
                "jobType", row[0],
                "total",   row[1],
                "success", row[2]
        )).collect(Collectors.toList());
    }
}
