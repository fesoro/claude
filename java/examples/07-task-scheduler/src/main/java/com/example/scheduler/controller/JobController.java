package com.example.scheduler.controller;

import com.example.scheduler.entity.JobRecord;
import com.example.scheduler.scheduler.CleanupScheduler;
import com.example.scheduler.scheduler.ReportScheduler;
import com.example.scheduler.service.JobService;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/api/jobs")
public class JobController {

    private final JobService service;
    private final ReportScheduler reportScheduler;
    private final CleanupScheduler cleanupScheduler;

    public JobController(JobService service, ReportScheduler report, CleanupScheduler cleanup) {
        this.service          = service;
        this.reportScheduler  = report;
        this.cleanupScheduler = cleanup;
    }

    @GetMapping
    public List<JobRecord> list(@RequestParam(required = false) String type) {
        return service.findAll(type);
    }

    @GetMapping("/stats")
    public List<Map<String, Object>> stats() {
        return service.stats();
    }

    // Manual trigger — admin panel və ya test üçün
    @PostMapping("/report/trigger")
    @ResponseStatus(HttpStatus.ACCEPTED)
    public Map<String, String> triggerReport() {
        reportScheduler.execute();
        return Map.of("message", "Report job başladıldı");
    }

    @PostMapping("/cleanup/trigger")
    @ResponseStatus(HttpStatus.ACCEPTED)
    public Map<String, String> triggerCleanup() {
        cleanupScheduler.execute();
        return Map.of("message", "Cleanup job başladıldı");
    }
}
