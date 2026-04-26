# Symfony Console (Middle)

## Mündəricat
1. [Symfony Console nədir?](#symfony-console-nədir)
2. [Standalone command](#standalone-command)
3. [Application & Command Loader](#application--command-loader)
4. [Input/Output components](#inputoutput-components)
5. [SymfonyStyle helper](#symfonystyle-helper)
6. [Process component](#process-component)
7. [Lock component (job overlap)](#lock-component-job-overlap)
8. [Single command application](#single-command-application)
9. [Testing commands](#testing-commands)
10. [Advanced — event dispatcher, exit codes](#advanced--event-dispatcher-exit-codes)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Symfony Console nədir?

```
Symfony Console — PHP üçün CLI framework.
Laravel Artisan, Composer, PHPUnit hamısı bunun üzərindədir.

Komponentlər:
  - Application
  - Command
  - Input (Argument, Option, Question)
  - Output (formatter, helper)
  - Style (SymfonyStyle facade)
  - Process (system call)
  - Lock (concurrency control)
  - Filesystem
  - Finder

Use case:
  - Standalone CLI tool (composer.phar kimi)
  - Cron job
  - Background worker
  - Migration script
  - Developer tooling
```

```bash
composer require symfony/console
composer require symfony/process symfony/lock
```

---

## Standalone command

```php
<?php
// bin/console — entry point
#!/usr/bin/env php
<?php
require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use App\Command\GreetCommand;

$app = new Application('My Tool', '1.0.0');
$app->add(new GreetCommand());
$app->run();

// chmod +x bin/console
// ./bin/console greet --name=Ali
```

```php
<?php
// src/Command/GreetCommand.php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:greet',
    description: 'Greet someone',
    aliases: ['greet'],
)]
class GreetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setHelp('This command greets a person...')
            ->addArgument('name', InputArgument::REQUIRED, 'Person name')
            ->addOption('yell', 'y', InputOption::VALUE_NONE, 'Yell?')
            ->addOption('lang', 'l', InputOption::VALUE_REQUIRED, 'Language', 'en');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $yell = $input->getOption('yell');
        $lang = $input->getOption('lang');
        
        $greetings = [
            'en' => 'Hello',
            'az' => 'Salam',
            'ru' => 'Привет',
        ];
        
        $msg = ($greetings[$lang] ?? 'Hello') . ", $name!";
        if ($yell) {
            $msg = strtoupper($msg);
        }
        
        $output->writeln("<info>$msg</info>");
        return Command::SUCCESS;
    }
}
```

```bash
./bin/console greet Ali
./bin/console greet Ali --yell --lang=az
./bin/console greet Ali -y -l az
```

---

## Application & Command Loader

```php
<?php
// Lazy command loader — performans
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();
$container->register('app.command.greet', GreetCommand::class);

$loader = new ContainerCommandLoader($container, [
    'app:greet' => 'app.command.greet',
]);

$app = new Application();
$app->setCommandLoader($loader);
// Command yalnız çağırılanda instantiate olunur (lazy)
```

---

## Input/Output components

```php
<?php
// INPUT
$arg     = $input->getArgument('name');
$option  = $input->getOption('verbose');
$args    = $input->getArguments();
$opts    = $input->getOptions();
$bind    = $input->bind($definition);

// Argument modes
InputArgument::REQUIRED       // mütləq
InputArgument::OPTIONAL        // optional
InputArgument::IS_ARRAY        // array (multiple values)

// Option modes
InputOption::VALUE_NONE         // boolean flag (--foo)
InputOption::VALUE_REQUIRED     // dəyər tələb olunur (--foo=bar)
InputOption::VALUE_OPTIONAL     // dəyər ola/olmaya bilər
InputOption::VALUE_IS_ARRAY     // çoxlu dəyər (--foo=a --foo=b)
InputOption::VALUE_NEGATABLE    // --foo / --no-foo (Symfony 6+)

// OUTPUT
$output->write('hello ');           // newline yoxdur
$output->writeln('hello');           // newline ilə
$output->writeln(['line1', 'line2']);

// Verbosity levels
$output->writeln('debug', OutputInterface::VERBOSITY_DEBUG);    // -vvv
$output->writeln('verb',  OutputInterface::VERBOSITY_VERBOSE);  // -v
$output->writeln('quiet', OutputInterface::VERBOSITY_NORMAL);   // default

// Format tags
$output->writeln('<info>green</info>');
$output->writeln('<comment>yellow</comment>');
$output->writeln('<question>cyan</question>');
$output->writeln('<error>red</error>');
$output->writeln('<fg=blue;bg=white;options=bold>blue</>');
```

---

## SymfonyStyle helper

```php
<?php
use Symfony\Component\Console\Style\SymfonyStyle;

protected function execute(InputInterface $input, OutputInterface $output): int
{
    $io = new SymfonyStyle($input, $output);
    
    // Section headers
    $io->title('Application Setup');
    $io->section('Configuring database');
    
    // Messages
    $io->success('Done');
    $io->warning('Check this');
    $io->error('Failed');
    $io->info('FYI');
    $io->note('Just a note');
    $io->caution('Be careful');
    
    // Lists
    $io->listing(['item 1', 'item 2', 'item 3']);
    
    // Definition list
    $io->definitionList(
        'Configuration:',
        ['env' => 'production', 'debug' => 'false']
    );
    
    // Table
    $io->table(
        ['ID', 'Name'],
        [[1, 'Ali'], [2, 'Bob']]
    );
    
    // Horizontal table
    $io->horizontalTable(['ID', 'Name'], [[1, 'Ali']]);
    
    // Questions
    $name = $io->ask('Name?', 'default-value');
    $secret = $io->askHidden('Password:');
    $valid = $io->ask('Email?', null, function ($v) {
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid email');
        }
        return $v;
    });
    
    $confirm = $io->confirm('Continue?', true);
    $choice  = $io->choice('Color?', ['red', 'green'], 'red');
    
    // Multi choice
    $tags = $io->choice('Tags?', ['a', 'b', 'c'], null, true);
    
    // Progress
    $io->progressStart(100);
    for ($i = 0; $i < 100; $i++) {
        $io->progressAdvance();
        usleep(10_000);
    }
    $io->progressFinish();
    
    return Command::SUCCESS;
}
```

---

## Process component

```php
<?php
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

// Sadə proses
$process = new Process(['ls', '-la', '/tmp']);
$process->run();

if (!$process->isSuccessful()) {
    throw new ProcessFailedException($process);
}

echo $process->getOutput();
echo $process->getErrorOutput();
echo $process->getExitCode();

// Real-time output streaming
$process = new Process(['ping', '-c', '5', 'google.com']);
$process->run(function ($type, $buffer) {
    if (Process::ERR === $type) {
        echo "ERR > $buffer";
    } else {
        echo "OUT > $buffer";
    }
});

// Async — start, sonra wait
$process = new Process(['npm', 'install']);
$process->start();
// digər işlər...
$process->wait();

// Timeout
$process = new Process(['long-task']);
$process->setTimeout(60);    // 60s, sonra kill
$process->run();

// Environment variable
$process = new Process(['echo', '$FOO'], null, ['FOO' => 'bar']);

// Shell-də
$process = Process::fromShellCommandline('cat file.txt | grep hello');

// Multiple parallel processes
$processes = [];
foreach ($urls as $url) {
    $p = new Process(['curl', $url]);
    $p->start();
    $processes[] = $p;
}
foreach ($processes as $p) {
    $p->wait();
    echo $p->getOutput();
}
```

---

## Lock component (job overlap)

```php
<?php
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\RedisStore;

// File-based lock
$store = new FlockStore('/var/locks');
// Redis-based (distributed)
$store = new RedisStore(new \Redis());

$factory = new LockFactory($store);
$lock = $factory->createLock('my-job', ttl: 300);   // 5 dəq

if (!$lock->acquire()) {
    $output->writeln('Already running');
    return Command::FAILURE;
}

try {
    // long-running work
    $this->doImport();
} finally {
    $lock->release();
}

// Auto-release with finally
// Use case: cron job overlap qarşı, distributed worker coordination
```

---

## Single command application

```php
<?php
// bin/mytool — bir command-lı app
use Symfony\Component\Console\SingleCommandApplication;

(new SingleCommandApplication())
    ->setName('My Tool')
    ->setVersion('1.0.0')
    ->addArgument('input', InputArgument::REQUIRED)
    ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, '', 1)
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->success('Done');
        return Command::SUCCESS;
    })
    ->run();

// İstifadə:
// ./bin/mytool myinput --count=5
// ./bin/mytool --help
```

---

## Testing commands

```php
<?php
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GreetCommandTest extends TestCase
{
    public function test_greet(): void
    {
        $app = new Application();
        $app->add(new GreetCommand());
        
        $command = $app->find('app:greet');
        $tester = new CommandTester($command);
        
        $tester->execute([
            'name' => 'Ali',
            '--yell' => true,
        ]);
        
        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
        $this->assertStringContainsString('HELLO, ALI!', $output);
    }
    
    public function test_interactive(): void
    {
        $tester = new CommandTester($command);
        $tester->setInputs(['Ali', 'yes']);   // ask, confirm
        
        $tester->execute([]);
        $this->assertCommandIsSuccessful();
    }
}
```

---

## Advanced — event dispatcher, exit codes

```php
<?php
// Console event-lər
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

$dispatcher = new EventDispatcher();

$dispatcher->addListener('console.command', function (ConsoleCommandEvent $event) {
    $cmd = $event->getCommand();
    echo "Running: " . $cmd->getName() . "\n";
});

$dispatcher->addListener('console.error', function (ConsoleErrorEvent $event) {
    $error = $event->getError();
    error_log($error->getMessage());
});

$dispatcher->addListener('console.terminate', function (ConsoleTerminateEvent $event) {
    echo "Exit: " . $event->getExitCode() . "\n";
});

$app = new Application();
$app->setDispatcher($dispatcher);
$app->run();

// Standard exit codes (POSIX)
Command::SUCCESS  = 0;
Command::FAILURE  = 1;
Command::INVALID  = 2;     // bad input

// Custom (recommended < 256)
const EXIT_DB_ERROR = 64;
const EXIT_NETWORK  = 65;
```

---

## İntervyu Sualları

- Symfony Console hansı tool-larda istifadə olunur?
- `InputOption::VALUE_NEGATABLE` nədir?
- SymfonyStyle adi `OutputInterface` ilə fərqi nədir?
- Process komponenti ilə `exec()` arasında fərq?
- Lock komponenti niyə cron job-larda lazımdır?
- Lazy command loader nə fayda verir?
- Console event dispatcher hansı problem həll edir?
- Real-time output streaming necə implementasiya olunur?
- Async Process — `start()` və `wait()` necə birlikdə?
- Single command application nə vaxt seçilir?
- CommandTester interactive prompt-ları necə test edir?
- Verbosity level-lər necə işləyir? `-v -vv -vvv` fərqi?
