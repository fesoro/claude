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
public class ReportScheduler {

    private static final Logger log = LoggerFactory.getLogger(ReportScheduler.class);
    private static final String JOB_TYPE = "REPORT";

    private final JobService jobService;

    public ReportScheduler(JobService jobService) { this.jobService = jobService; }

    // Cron application.yml-dən oxunur — dev-də hər 30 saniyə, prod-da hər gün 08:00
    @Async
    @Scheduled(cron = "${app.scheduler.report-cron}")
    public void run() {
        execute();
    }

    // Manual trigger üçün — controller-dən çağırılır
    public void execute() {
        JobRecord record = jobService.startJob(JOB_TYPE);
        if (record == null) return;  // artıq işləyir

        try {
            log.info("[REPORT] başladı...");
            // Simulasiya: report yaratma (real app-da DB sorğuları, fayl yazma)
            int processedRows = simulateWork();
            jobService.finishSuccess(record, processedRows);
        } catch (Exception ex) {
            jobService.finishFailed(record, ex);
        }
    }

    private int simulateWork() throws InterruptedException {
        Thread.sleep(500);  // DB işini simulasiya et
        return ThreadLocalRandom.current().nextInt(50, 500);
    }
}
