# PHP CLI Application Development

## Mündəricat
1. [CLI vs Web PHP](#cli-vs-web-php)
2. [stdin/stdout/stderr](#stdinstdoutstderr)
3. [Exit Codes](#exit-codes)
4. [Argument Parsing](#argument-parsing)
5. [Signal Handling](#signal-handling)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## CLI vs Web PHP

```
Web PHP:                        CLI PHP:
  php-fpm işlədir                 php script.php işlədir
  Request/Response cycle          Stdin/Stdout/Stderr
  Timeout: 30-60s default         Timeout yoxdur (default)
  Memory limit: 128-256M          Fərqli memory limit
  $_GET, $_POST, $_SERVER         $argc, $argv, $GLOBALS
  HTTP headers                    Exit code
  Stateless per-request           Uzun müddət işləyə bilər

CLI istifadə halları:
  - Queue workers
  - Database migrations
  - Data import/export
  - Scheduled tasks (cron)
  - Admin utility scripts
  - Code generation
```

---

## stdin/stdout/stderr

```
3 standard stream:
  STDIN  (0) → input oxuma (pipe, keyboard)
  STDOUT (1) → normal output
  STDERR (2) → xəta/log output

Niyə stdout vs stderr ayırmaq lazımdır:
  Normal output → stdout → pipe-a ötürülə bilər
  Xəta mesajları → stderr → ayrıca log

  php script.php | grep "success"  ← yalnız stdout
  php script.php 2>/dev/null       ← stderr gizlət
  php script.php > output.txt 2>&1 ← hər ikisi fayla

PHP-də:
  echo "result";                      → stdout
  fwrite(STDOUT, "result\n");         → stdout
  fwrite(STDERR, "Error occurred\n"); → stderr
  error_log("debug info");            → stderr (default)
```

---

## Exit Codes

```
Exit code = prosesin tamamlanma statusu.
0  = uğurlu
1  = ümumi xəta
2  = usage xətası (yanlış argument)
127 = command not found
130 = Ctrl+C (SIGINT)

CI/CD pipeline-da vacibdir:
  if php migrate.php; then
      echo "Migration uğurlu"
  else
      echo "Migration xəta! Exit code: $?"
      exit 1
  fi

PHP-də:
  exit(0);  // uğurlu
  exit(1);  // xəta
  // Script sona çatsa → 0

Tələ:
  Exception tutulmursa → exit(255)
  PHP error (fatal) → exit(255)
  CI pipeline-da failure olaraq görünür — yaxşı!
```

---

## Argument Parsing

```
$argv — argument array
$argc — argument sayı

php script.php migrate --env=production --dry-run -v

$argv = ['script.php', 'migrate', '--env=production', '--dry-run', '-v']
$argc = 5

Manuel parse çətin → symfony/console istifadə et.

Symfony Console komponenti:
  Command class → Input/Output abstraction
  Options, arguments, flags
  Help text avtomatik
  Validation
  Color output
```

---

## Signal Handling

```
POSIX siqnalları:
  SIGTERM (15) → graceful shutdown
  SIGINT  (2)  → Ctrl+C
  SIGHUP  (1)  → reload
  SIGUSR1 (10) → custom

CLI PHP-də PCNTL:
  pcntl_signal(SIGTERM, $handler);
  pcntl_signal_dispatch(); // loop-da çağır

declare(ticks=1): Hər N opcode-da signal check et.
pcntl_async_signals(true): PHP 7.1+ — tick-siz async signal.
```

---

## PHP İmplementasiyası

```php
<?php
// Tam CLI command — Symfony Console
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class ImportUsersCommand extends Command
{
    protected static string $defaultName = 'users:import';

    protected function configure(): void
    {
        $this
            ->setDescription('CSV faylından user-ları import et')
            ->addArgument('file', InputArgument::REQUIRED, 'CSV fayl yolu')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Real import etmə')
            ->addOption('batch', 'b', InputOption::VALUE_OPTIONAL, 'Batch ölçüsü', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file    = $input->getArgument('file');
        $dryRun  = $input->getOption('dry-run');
        $batch   = (int) $input->getOption('batch');

        if (!file_exists($file)) {
            $output->writeln("<error>Fayl tapılmadı: {$file}</error>");
            return Command::FAILURE;
        }

        $rows = $this->readCsv($file);
        $progress = new ProgressBar($output, count($rows));
        $progress->start();

        $imported = 0;
        foreach (array_chunk($rows, $batch) as $chunk) {
            if (!$dryRun) {
                $this->userService->importBatch($chunk);
            }
            $imported += count($chunk);
            $progress->advance(count($chunk));
        }

        $progress->finish();
        $output->writeln('');
        $output->writeln("<info>{$imported} user import edildi</info>");

        return Command::SUCCESS;
    }
}
```

```php
<?php
// Signal handling ilə graceful CLI script
pcntl_async_signals(true);

$running = true;

pcntl_signal(SIGTERM, function() use (&$running) {
    fwrite(STDERR, "SIGTERM alındı, tamamlanır...\n");
    $running = false;
});

pcntl_signal(SIGINT, function() use (&$running) {
    fwrite(STDERR, "\nCtrl+C, tamamlanır...\n");
    $running = false;
});

$processed = 0;
while ($running) {
    $job = getNextJob();
    if (!$job) {
        sleep(1);
        continue;
    }

    processJob($job);
    $processed++;

    if ($processed % 100 === 0) {
        fwrite(STDOUT, "Processed: {$processed}\n");
    }
}

fwrite(STDOUT, "Graceful shutdown. Total: {$processed}\n");
exit(0);
```

---

## İntervyu Sualları

- CLI PHP-nin web PHP-dən fərqli nədir? Memory limit, timeout?
- `exit(0)` vs `exit(1)` CI/CD pipeline-da niyə vacibdir?
- stderr-ə yazmağın stdout-dan fərqi nədir?
- `pcntl_async_signals(true)` vs `declare(ticks=1)` fərqi?
- Symfony Console-da `Command::FAILURE` vs `exit(1)` eyni şeydir?
