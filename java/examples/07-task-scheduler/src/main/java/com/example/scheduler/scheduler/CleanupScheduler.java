package com.example.scheduler.scheduler;

import com.example.scheduler.entity.JobRecord;
import com.example.scheduler.service.JobService;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.scheduling.annotation.Async;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;

import java.util.concurrent.ThreadLocalRandom;

@Component
public class CleanupScheduler {

    private static final Logger log = LoggerFactory.getLogger(CleanupScheduler.class);
    private static final String JOB_TYPE = "CLEANUP";

    private final JobService jobService;

    public CleanupScheduler(JobService jobService) { this.jobService = jobService; }

    @Async
    @Scheduled(cron = "${app.scheduler.cleanup-cron}")
    public void run() {
        execute();
    }

    public void execute() {
        JobRecord record = jobService.startJob(JOB_TYPE);
        if (record == null) return;

        try {
            log.info("[CLEANUP] köhnə data təmizlənir...");
            // Simulasiya: real app-da köhnə temp faylları, expired session-ları sil
            int deleted = ThreadLocalRandom.current().nextInt(0, 20);
            Thread.sleep(200);
            jobService.finishSuccess(record, deleted);
        } catch (Exception ex) {
            jobService.finishFailed(record, ex);
        }
    }
}
