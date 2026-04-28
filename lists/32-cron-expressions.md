## Standard cron syntax (5-field, Linux)

┌──────── minute       (0-59)
│ ┌────── hour         (0-23)
│ │ ┌──── day of month (1-31)
│ │ │ ┌── month        (1-12 or JAN-DEC)
│ │ │ │ ┌ day of week  (0-7 or SUN-SAT; both 0 and 7 = Sunday)
│ │ │ │ │
* * * * * command

# Examples
*       /5 * * *     ← INVALID (no spaces in field)
*/5 * * * *           — every 5 min
0 * * * *             — every hour (top of hour)
0 0 * * *             — every day at 00:00
0 9 * * 1-5           — weekdays at 09:00
0 9-17 * * 1-5        — every hour 9..17 weekdays
30 8 1 * *            — 1st of month at 08:30
0 0 1 1 *             — Jan 1st 00:00
0 0 * * 0             — every Sunday midnight
*/15 9-17 * * 1-5     — every 15 min during business hours
0 2 * * 6             — 02:00 every Saturday
0 22 * * 1-5          — 22:00 weekdays
*/10 * * * *          — every 10 min
0 0 1,15 * *          — 1st and 15th of month
0 0 */2 * *           — every 2 days at midnight
0 6,18 * * *          — 06:00 and 18:00 daily

## Special characters

*       any value (every)
,       value list (1,3,5)
-       range (1-5)
/       step (every Nth)
?       no specific value (Quartz only — not standard cron)
L       last (Quartz: L in DOM = last day; 5L in DOW = last Friday)
W       weekday (Quartz: 15W = nearest weekday to 15th)
#       nth (Quartz: 2#1 = first Monday)

# Examples
0 0/5 * * * *         — Quartz: every 5 min starting at 0
*/5 0-12 * * *        — every 5 min from 00 to 12 hour
0 0 1-7 * 1           — first Monday-ish of month (DOW + DOM = OR!)
0 0 L * *             — last day of month (Quartz)
0 0 ? * 6L            — last Friday of month (Quartz)
0 0 15W * ?           — nearest weekday to 15th (Quartz)
0 0 ? * MON#1         — first Monday of month (Quartz)

## Predefined strings (most cron implementations)

@reboot         — system start
@yearly         = 0 0 1 1 *      (annually)
@annually       = 0 0 1 1 *
@monthly        = 0 0 1 * *
@weekly         = 0 0 * * 0
@daily          = 0 0 * * *      (midnight)
@midnight       = 0 0 * * *
@hourly         = 0 * * * *

## DOM + DOW gotcha (Vixie cron)

In standard cron: if BOTH day-of-month AND day-of-week are restricted, command runs when EITHER matches (OR, not AND).

0 0 1 * 1     → 1st of month OR every Monday  (NOT just "Mondays falling on the 1st")

To get AND semantics, use one or the other and shell-side guard:
0 0 * * 1 [ $(date +\%d) -le 7 ] && /script    — first Monday only

## Quartz cron (6-7 field — Java/Spring/Quartz Scheduler)

# Field order: seconds minutes hours day-of-month month day-of-week [year]
sec  min  hour  dom  mon  dow  [year]

# Examples
0 0 12 * * ?          — every day at 12:00:00 (noon)
0 15 10 ? * MON-FRI   — 10:15 every weekday
0 15 10 15 * ?        — 10:15 on 15th of every month
0 0 12 ? * 6#3        — 12:00 on third Friday of month
0 0 0 LW * ?          — last weekday of month
0 0 8-18/2 * * ?      — 08, 10, 12, 14, 16, 18 daily
0 0/30 9-17 * * MON-FRI — every 30 min during business hours
0 0 0 1 1/3 ?         — quarterly (Jan, Apr, Jul, Oct 1st)

# Note: Quartz requires '?' in DOM or DOW (one must be ?, the other a value)
# day-of-week: 1=SUN, 7=SAT (Quartz)  —  vs  Linux cron: 0/7=SUN, 6=SAT

## Spring @Scheduled cron

@Scheduled(cron = "0 0 12 * * ?")               — Quartz-style (6 fields)
@Scheduled(cron = "0 0 12 * * MON-FRI", zone = "Europe/Baku")
@Scheduled(fixedRate = 5000)                     — every 5s (overlap allowed)
@Scheduled(fixedDelay = 5000)                    — 5s after previous finishes
@Scheduled(initialDelay = 10000, fixedRate = 60000)
@Scheduled(cron = "${app.cron.cleanup}")         — externalized

## Linux crontab

crontab -e                 — current user's crontab
crontab -l                 — list
crontab -r                 — remove (DİQQƏT)
crontab -u user -e         — root edit başqa user-i
sudo crontab -e            — root crontab
/etc/crontab               — system-wide (extra USER field)
/etc/cron.d/myjob          — drop-in fragment
/etc/cron.{hourly,daily,weekly,monthly}/  — script qovluqları
/var/log/cron / /var/log/syslog            — log
journalctl -u cron / -u crond -f

# /etc/crontab format (extra user field):
#  m h dom mon dow user command
0 4 * * * root /usr/local/bin/backup.sh

# User crontab format:
0 4 * * * /usr/local/bin/backup.sh

## Best practices

- Always use absolute paths — cron has minimal $PATH
- Set explicit env at top of crontab: SHELL=/bin/bash, PATH=/usr/local/bin:/usr/bin
- Redirect both stdout and stderr: >> /var/log/job.log 2>&1
- Use flock to prevent overlap: /usr/bin/flock -n /tmp/job.lock /path/job.sh
- Add MAILTO=ops@example.com (or =""/blank to disable mail)
- Use a wrapper for env loading (cron has no .bashrc): source /etc/profile.d/myapp.sh
- Quote $variables and use full timestamps: date +\%Y-\%m-\%d (escape % in cron — % means newline!)
- Echo start/end markers in script for log analysis
- Health-check pings: curl -fsS https://hc-ping.com/uuid (Healthchecks.io / Cronitor / Better Uptime)
- For >1/min frequency, use systemd timer or daemon (cron min granularity = 1m)

## Timezone / DST gotchas

- Default timezone = system TZ (TZ env var)
- Set per-job: TZ=Europe/Baku at top of crontab (GNU cron)
- DST spring-forward: 02:30 jobs SKIP (clock jumps to 03:00)
- DST fall-back: 02:30 jobs may run TWICE
- Use UTC for critical jobs (TZ=UTC)
- systemd timer respects TZ but handles DST more reliably (Persistent=true catches missed)

## Laravel scheduler frequency methods

# In app/Console/Kernel.php (or routes/console.php in 11+)
$schedule->command('cleanup')->everyMinute()
                              ->everyTwoMinutes()
                              ->everyFiveMinutes()
                              ->everyTenMinutes()
                              ->everyFifteenMinutes()
                              ->everyThirtyMinutes()
                              ->hourly()
                              ->hourlyAt(15)
                              ->everyTwoHours()
                              ->everyThreeHours()
                              ->everyFourHours()
                              ->everySixHours()
                              ->daily()
                              ->dailyAt('13:00')
                              ->twiceDaily(1, 13)
                              ->weekly()
                              ->weeklyOn(1, '08:00')             // Monday 08:00
                              ->monthly()
                              ->monthlyOn(15, '08:00')
                              ->lastDayOfMonth('08:00')
                              ->quarterly() / quarterlyOn()
                              ->yearly() / yearlyOn()
                              ->cron('0 */2 * * *')              // raw cron

# Filters / chaining
->weekdays() / ->weekends()
->mondays() / ->tuesdays() / ...
->between('8:00', '17:00')
->unlessBetween('22:00', '6:00')
->when(fn () => Feature::active('cron'))
->skip(fn () => app()->isLocal())
->environments(['production'])
->timezone('Europe/Baku')
->withoutOverlapping(10)                 // mutex (10 min lock)
->onOneServer()                          // single execution across cluster
->runInBackground()
->name('cleanup')                        // for ->withoutOverlapping
->before(fn () => log('start'))
->after(fn () => log('end'))
->onSuccess(fn () => ...)
->onFailure(fn () => ...)
->emailOutputTo / emailOutputOnFailure
->pingBefore('https://hc-ping.com/...')
->thenPing('https://hc-ping.com/...')
->pingOnSuccess / pingOnFailure
->sendOutputTo($file) / appendOutputTo($file)

# Run scheduler from system cron (single line setup):
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1

# Or daemon (Laravel 11+)
php artisan schedule:work    — long-running watcher (use Supervisor/systemd)

## systemd timer (modern alternative)

# /etc/systemd/system/backup.service
[Service]
Type=oneshot
ExecStart=/usr/local/bin/backup.sh

# /etc/systemd/system/backup.timer
[Timer]
OnCalendar=*-*-* 04:00:00            ← daily 04:00
OnCalendar=Mon..Fri 09:00            ← weekdays 09:00
OnCalendar=hourly / daily / weekly / monthly
OnBootSec=15min                       ← N after boot
OnUnitActiveSec=1h                    ← every hour after last run
RandomizedDelaySec=300                ← spread load
Persistent=true                       ← run if missed (was offline)

[Install]
WantedBy=timers.target

# Commands
systemctl daemon-reload
systemctl enable --now backup.timer
systemctl list-timers --all
systemctl status backup.timer
journalctl -u backup.service -n 50

# OnCalendar syntax
2025-*-* 12:00:00          year-any-any 12:00
*-*-* *:00/15:00           every 15 minutes
Mon,Wed,Fri *-*-* 09:30    Mon/Wed/Fri 09:30
*-12-25 00:00              every Christmas

# Test calendar expressions:
systemd-analyze calendar "*-*-* 04:00:00"
systemd-analyze calendar --iterations 5 "Mon..Fri 09:00"

## Validation / debugging

crontab.guru                          — visual cron expression explainer
crontab -l | crontab -                — re-install (validate)
run-parts --test /etc/cron.daily/     — list scripts that would run

# Test a cron expression in shell (gnu date+cron utility "next")
ncron / cronie / fcron variants

# Verify your script works in cron environment
env -i bash -c 'cd / && /path/to/script.sh'      ← simulate cron's empty env

## Common pitfalls

1. % must be escaped — \% (literal %) in crontab; unescaped = newline marker
   `date +%Y-%m-%d` works in shell, FAILS in crontab; use `date +\%Y-\%m-\%d`
2. PATH minimal in cron — always use absolute paths or set PATH at top
3. No interactive shell → no aliases, no .bashrc; source explicitly if needed
4. Output goes to mail (if MAILTO set) or dropped — always redirect
5. DOM + DOW = OR semantics in standard cron (see above)
6. Sunday is both 0 and 7 in DOW
7. */N step — divides cleanly only when range is 0-(N*k); */7 in 0-23 hour gives 0,7,14,21
8. cron daemon must be running: systemctl status cron / crond
9. Editing /etc/crontab does NOT need crontab -e (manual edit)
10. crontab -r with no args = wipes ALL — confirm twice
