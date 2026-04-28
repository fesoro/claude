# AWS Advanced (Senior)

## Nədir? (What is it?)

AWS qabaqcıl xidmətləri – ECS/Fargate (konteyner orchestration), Lambda (serverless compute), SQS (message queue), SNS (notification service), ElastiCache (Redis/Memcached), CloudWatch (monitoring) və Auto Scaling – böyük miqyaslı Laravel tətbiqlərinin yüksək əlçatanlıq, elastiklik və performans ilə işləməsini təmin edir. Bu xidmətlər "managed services" kateqoriyasındadır, yəni AWS onların infrastrukturunu idarə edir, siz isə yalnız tətbiqinizi deploy edirsiniz.

## Əsas Konseptlər (Key Concepts)

### ECS (Elastic Container Service) və Fargate

```bash
# ECS = Docker konteynerlərini orchestrate edən xidmət
# Cluster = ECS-in işlədiyi virtual qrup
# Task Definition = Docker image + resurs + environment konfiqurasiyası
# Service = Task-ların uzun müddət işlədilməsini təmin edir
# Task = Çalışan konteyner instance-ı

# Launch types:
# - EC2: Öz EC2 instance-larınızda işləyir (ucuz, daha çox idarə)
# - Fargate: Serverless, AWS resursları idarə edir (rahat, bir az baha)

# Task Definition nümunəsi (Laravel)
cat > task-definition.json <<EOF
{
  "family": "laravel-app",
  "networkMode": "awsvpc",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "1024",
  "memory": "2048",
  "executionRoleArn": "arn:aws:iam::123456789:role/ecsTaskExecutionRole",
  "taskRoleArn": "arn:aws:iam::123456789:role/ecsTaskRole",
  "containerDefinitions": [
    {
      "name": "laravel",
      "image": "123456789.dkr.ecr.us-east-1.amazonaws.com/laravel:latest",
      "portMappings": [{"containerPort": 80, "protocol": "tcp"}],
      "environment": [
        {"name": "APP_ENV", "value": "production"},
        {"name": "DB_HOST", "value": "rds.example.com"}
      ],
      "secrets": [
        {"name": "DB_PASSWORD", "valueFrom": "arn:aws:secretsmanager:us-east-1:123456789:secret:db-pass"}
      ],
      "logConfiguration": {
        "logDriver": "awslogs",
        "options": {
          "awslogs-group": "/ecs/laravel-app",
          "awslogs-region": "us-east-1",
          "awslogs-stream-prefix": "ecs"
        }
      },
      "healthCheck": {
        "command": ["CMD-SHELL", "curl -f http://localhost/health || exit 1"],
        "interval": 30,
        "timeout": 5,
        "retries": 3
      }
    }
  ]
}
EOF

# Task Definition register
aws ecs register-task-definition --cli-input-json file://task-definition.json

# Service yaratmaq
aws ecs create-service \
  --cluster laravel-cluster \
  --service-name laravel-web \
  --task-definition laravel-app:1 \
  --desired-count 3 \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[subnet-123],securityGroups=[sg-456],assignPublicIp=ENABLED}"

# Service-i update etmək (yeni deployment)
aws ecs update-service --cluster laravel-cluster --service laravel-web --force-new-deployment
```

### Lambda (Serverless Compute)

```bash
# Lambda = Serverless function execution
# Trigger-lər: API Gateway, S3, SQS, SNS, CloudWatch Events
# Max execution: 15 dəqiqə
# Memory: 128MB - 10GB
# Cold start: İlk dəfə çağırışda gecikmə (1-3s)

# Lambda function yaratmaq
aws lambda create-function \
  --function-name image-resizer \
  --runtime provided.al2 \
  --role arn:aws:iam::123456789:role/lambda-role \
  --handler index.handler \
  --zip-file fileb://function.zip \
  --timeout 30 \
  --memory-size 512

# Trigger əlavə etmək (S3 bucket-dən)
aws lambda add-permission \
  --function-name image-resizer \
  --statement-id s3-trigger \
  --action lambda:InvokeFunction \
  --principal s3.amazonaws.com \
  --source-arn arn:aws:s3:::my-bucket
```

### SQS (Simple Queue Service)

```bash
# SQS = Message queue service
# Standard Queue: Unlimited throughput, at-least-once delivery
# FIFO Queue: 3000 msg/sec, exactly-once, order qorunur

# Queue yaratmaq
aws sqs create-queue \
  --queue-name laravel-jobs \
  --attributes '{
    "VisibilityTimeout": "300",
    "MessageRetentionPeriod": "1209600",
    "ReceiveMessageWaitTimeSeconds": "20"
  }'

# Dead Letter Queue (DLQ)
aws sqs create-queue \
  --queue-name laravel-jobs-dlq \
  --attributes '{"MessageRetentionPeriod": "1209600"}'

# Message göndərmək
aws sqs send-message \
  --queue-url https://sqs.us-east-1.amazonaws.com/123456789/laravel-jobs \
  --message-body '{"job":"SendEmail","data":{"to":"user@example.com"}}'

# Message qəbul etmək (long polling)
aws sqs receive-message \
  --queue-url https://sqs.us-east-1.amazonaws.com/123456789/laravel-jobs \
  --wait-time-seconds 20 \
  --max-number-of-messages 10
```

### SNS (Simple Notification Service)

```bash
# SNS = Pub/Sub messaging
# Topic-lərə subscribe olan endpoint-lər: SQS, Lambda, HTTP, Email, SMS

# Topic yaratmaq
aws sns create-topic --name order-events

# Subscribe (SQS-ə)
aws sns subscribe \
  --topic-arn arn:aws:sns:us-east-1:123456789:order-events \
  --protocol sqs \
  --notification-endpoint arn:aws:sqs:us-east-1:123456789:laravel-jobs

# Subscribe (Email)
aws sns subscribe \
  --topic-arn arn:aws:sns:us-east-1:123456789:order-events \
  --protocol email \
  --notification-endpoint admin@example.com

# Mesaj göndərmək (fan-out)
aws sns publish \
  --topic-arn arn:aws:sns:us-east-1:123456789:order-events \
  --message '{"order_id":123,"status":"created"}' \
  --subject "New Order"
```

### ElastiCache (Redis/Memcached)

```bash
# ElastiCache = Managed Redis/Memcached
# Redis: Persistence, pub/sub, data structures
# Memcached: Sadə, sürətli, multi-threaded

# Redis cluster yaratmaq
aws elasticache create-replication-group \
  --replication-group-id laravel-redis \
  --replication-group-description "Laravel cache and sessions" \
  --engine redis \
  --cache-node-type cache.t3.medium \
  --num-cache-clusters 2 \
  --automatic-failover-enabled \
  --multi-az-enabled \
  --cache-subnet-group-name laravel-subnet-group \
  --security-group-ids sg-123456
```

### CloudWatch (Monitoring)

```bash
# CloudWatch = Metrics, logs, alarms
# Metrics: CPU, Memory, Network, Disk, custom
# Logs: Application logs, VPC Flow Logs
# Alarms: Metriklərə əsaslanan xəbərdarlıq

# Alarm yaratmaq (CPU > 80%)
aws cloudwatch put-metric-alarm \
  --alarm-name laravel-high-cpu \
  --alarm-description "Laravel CPU > 80%" \
  --metric-name CPUUtilization \
  --namespace AWS/EC2 \
  --statistic Average \
  --period 300 \
  --threshold 80 \
  --comparison-operator GreaterThanThreshold \
  --evaluation-periods 2 \
  --alarm-actions arn:aws:sns:us-east-1:123456789:alerts

# Log group yaratmaq
aws logs create-log-group --log-group-name /ecs/laravel-app
aws logs put-retention-policy --log-group-name /ecs/laravel-app --retention-in-days 30

# Custom metric göndərmək
aws cloudwatch put-metric-data \
  --namespace Laravel/App \
  --metric-name QueueJobsProcessed \
  --value 42 \
  --unit Count
```

### Auto Scaling

```bash
# Auto Scaling = Yükə görə instance sayını avtomatik dəyişmək
# Target Tracking: Metrik target (məs. CPU 70%)
# Step Scaling: Threshold-a əsasən pilləli
# Scheduled: Vaxta görə (məs. iş saatlarında +2 instance)

# ECS Service üçün auto scaling
aws application-autoscaling register-scalable-target \
  --service-namespace ecs \
  --resource-id service/laravel-cluster/laravel-web \
  --scalable-dimension ecs:service:DesiredCount \
  --min-capacity 2 \
  --max-capacity 20

aws application-autoscaling put-scaling-policy \
  --policy-name laravel-cpu-scaling \
  --service-namespace ecs \
  --resource-id service/laravel-cluster/laravel-web \
  --scalable-dimension ecs:service:DesiredCount \
  --policy-type TargetTrackingScaling \
  --target-tracking-scaling-policy-configuration '{
    "TargetValue": 70.0,
    "PredefinedMetricSpecification": {"PredefinedMetricType": "ECSServiceAverageCPUUtilization"},
    "ScaleInCooldown": 300,
    "ScaleOutCooldown": 60
  }'
```

## Praktiki Nümunələr (Practical Examples)

### ECS Fargate ilə Laravel Deploy

```dockerfile
# Dockerfile
FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor \
    && docker-php-ext-install pdo_mysql opcache

COPY . /var/www/html
WORKDIR /var/www/html

RUN composer install --no-dev --optimize-autoloader \
    && php artisan config:cache \
    && php artisan route:cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 80
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
```

```bash
# ECR-ə push
aws ecr get-login-password | docker login --username AWS --password-stdin 123456789.dkr.ecr.us-east-1.amazonaws.com
docker build -t laravel:latest .
docker tag laravel:latest 123456789.dkr.ecr.us-east-1.amazonaws.com/laravel:latest
docker push 123456789.dkr.ecr.us-east-1.amazonaws.com/laravel:latest

# ECS deploy
aws ecs update-service --cluster laravel-cluster --service laravel-web --force-new-deployment
```

## PHP/Laravel ilə İstifadə

### Laravel Serverless with Bref

```bash
# Bref = PHP-ni AWS Lambda-da işlətmək
composer require bref/bref bref/laravel-bridge

php artisan vendor:publish --tag=serverless-config
```

```yaml
# serverless.yml
service: laravel-app

provider:
  name: aws
  region: us-east-1
  runtime: provided.al2
  environment:
    APP_ENV: production
    DB_CONNECTION: mysql
    DB_HOST: ${env:DB_HOST}
    CACHE_DRIVER: dynamodb
    SESSION_DRIVER: dynamodb
    QUEUE_CONNECTION: sqs
    SQS_QUEUE: ${env:SQS_QUEUE}

plugins:
  - ./vendor/bref/bref

functions:
  web:
    handler: public/index.php
    timeout: 28
    layers:
      - ${bref:layer.php-82-fpm}
    events:
      - httpApi: '*'
  
  artisan:
    handler: artisan
    timeout: 720
    layers:
      - ${bref:layer.php-82}
      - ${bref:layer.console}
  
  queue-worker:
    handler: Bref\LaravelBridge\Queue\QueueHandler
    timeout: 120
    layers:
      - ${bref:layer.php-82}
    events:
      - sqs:
          arn: arn:aws:sqs:us-east-1:123456789:laravel-queue
          batchSize: 1

package:
  patterns:
    - '!node_modules/**'
    - '!tests/**'
    - '!storage/**'
```

### Laravel SQS Queue konfiqurasiyası

```php
// config/queue.php
'connections' => [
    'sqs' => [
        'driver' => 'sqs',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/123456789'),
        'queue' => env('SQS_QUEUE', 'laravel-jobs'),
        'suffix' => env('SQS_SUFFIX'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'after_commit' => false,
    ],
],
```

```php
// app/Jobs/ProcessOrder.php
class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 120, 300];
    public $timeout = 120;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::findOrFail($this->orderId);
        // Process order...
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Order processing failed", [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage()
        ]);
    }
}

// Job dispatch
ProcessOrder::dispatch($order->id)->onQueue('laravel-jobs');
```

### Laravel ElastiCache (Redis)

```php
// config/database.php
'redis' => [
    'client' => 'phpredis',
    'default' => [
        'host' => env('REDIS_HOST', 'laravel-redis.abc123.ng.0001.use1.cache.amazonaws.com'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 0,
        'options' => [
            'cluster' => 'redis',
            'prefix' => env('APP_NAME') . ':',
        ],
    ],
],
```

### CloudWatch Logs Laravel inteqrasiyası

```php
// config/logging.php
'channels' => [
    'cloudwatch' => [
        'driver' => 'custom',
        'via' => App\Logging\CloudWatchLoggerFactory::class,
        'group' => '/ecs/laravel-app',
        'stream' => env('AWS_LOG_STREAM', 'app'),
        'retention' => 30,
        'level' => env('LOG_LEVEL', 'info'),
    ],
],
```

```php
// app/Logging/CloudWatchLoggerFactory.php
namespace App\Logging;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Logger;

class CloudWatchLoggerFactory
{
    public function __invoke(array $config): Logger
    {
        $client = new CloudWatchLogsClient([
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => 'latest',
        ]);

        $handler = new CloudWatch(
            $client,
            $config['group'],
            $config['stream'],
            $config['retention'],
            10000,
            [],
            $config['level']
        );

        $logger = new Logger('laravel');
        $logger->pushHandler($handler);
        return $logger;
    }
}
```

## Interview Sualları (5-10 Q&A)

**S1: ECS EC2 və Fargate arasında fərq nədir?**
C: ECS EC2-də siz EC2 instance-larını özünüz idarə edirsiniz (patching, scaling, capacity). Fargate-də AWS infrastrukturu idarə edir – yalnız task-ları təyin edirsiniz. Fargate daha baha amma rahat, EC2 daha ucuz amma əmək tələb edir. Yüksək kontrol və long-running workloads üçün EC2, spike workloads və kiçik komandalar üçün Fargate üstün olur.

**S2: Lambda cold start nədir və necə azaltmaq olar?**
C: Cold start – Lambda function-ın ilk dəfə çağırışında container yaradılması üçün gecikmə (PHP üçün 500ms-3s). Azaltma yolları: Provisioned Concurrency (pre-warmed instance-lər), daha kiçik deployment package, daha az dependencies, daha böyük memory (CPU-ya proporsionaldır), VPC-dən qaçmaq (əgər mümkünsə).

**S3: SQS Standard və FIFO queue fərqi nədir?**
C: Standard – unlimited throughput, at-least-once delivery (dublikat ola bilər), sıra qorunmur. FIFO – 3000 msg/sec (batching ilə), exactly-once delivery, sıra qorunur (MessageGroupId əsasında). Laravel-də əmrlərin ardıcıllığı vacib olmayan işlər üçün Standard, sifariş işləmə kimi vacib sıra üçün FIFO istifadə edilir.

**S4: SNS və SQS arasında fərq nədir?**
C: SNS – Pub/Sub, push model, fan-out pattern (bir mesajı birdən çox subscriber-ə göndərir). SQS – Queue, pull model, bir mesajı bir consumer alır. Birgə istifadə: SNS -> bir neçə SQS queue (microservice-lər üçün ideal pattern).

**S5: ElastiCache Redis replication vs cluster mode?**
C: Replication mode – primary + read replicas, failover üçün yaxşı, amma max memory = 1 node. Cluster mode – sharding ilə horizontal scaling, 500+ node-a qədər, amma multi-key operations məhdudlaşır. Laravel session/cache üçün replication mode kifayət edir, çox böyük data üçün cluster mode.

**S6: CloudWatch Logs Insights nədir?**
C: CloudWatch Logs-da log-ları sorgulamaq üçün query dili. SQL-ə bənzəyir amma optimallaşdırılıb. Məsələn: `fields @timestamp, @message | filter @message like /ERROR/ | stats count() by bin(5m)` – 5 dəqiqəlik intervalda error saylarını göstərir. Laravel error-larını analiz etmək üçün çox faydalıdır.

**S7: Auto Scaling target tracking və step scaling fərqi?**
C: Target tracking – bir metrik üçün hədəf təyin edirsən (məs. CPU 70%), AWS avtomatik scale edir. Sadə və effektiv. Step scaling – manual threshold-lar (CPU >80% +2 instance, >90% +5 instance). Daha çox idarə, amma konfiqurasiya baha başa gəlir. Əksər hallarda target tracking tövsiyə olunur.

**S8: Laravel Bref ilə Lambda-nın üstünlükləri nədir?**
C: Üstünlüklər: 0-a qədər scale, ancaq istifadə olunanda ödəmə, auto-scaling heç bir konfiqurasiyasız, yüksək əlçatanlıq. Mənfiləri: 15 dəq limit, cold start, database connection pooling çətinlik, file system ephemeral. Çox uyğun: API endpoints, scheduled jobs, queue workers. Uyğun deyil: long-running processes, websocket.

**S9: Dead Letter Queue (DLQ) nədir və niyə lazımdır?**
C: DLQ – uğursuz işləri saxlayan queue. SQS-də maxReceiveCount (məs. 3) aşılanda mesaj DLQ-yə köçürülür. Bu, "poison message"-lərin əsas queue-nu bloklamağına mane olur və uğursuz işləri analiz etməyə imkan verir. Laravel-də `failed_jobs` cədvəli analoji funksiya görür.

**S10: VPC Endpoint nədir və niyə lazımdır?**
C: VPC Endpoint – AWS xidmətlərinə (S3, DynamoDB, SQS və s.) internet üzərindən keçmədən private şəbəkədən qoşulmaq üçün istifadə olunur. Gateway Endpoint (S3, DynamoDB) pulsuzdur, Interface Endpoint (digər xidmətlər) saatlıq ödənir. Təhlükəsizlik və gecikməni yaxşılaşdırır, NAT Gateway xərclərini azaldır.

## Best Practices

1. **IAM Least Privilege**: Hər xidmət üçün yalnız lazım olan icazələri verin. Wildcard (`*`) istifadə etməyin.
2. **Multi-AZ deployment**: ECS task-larını ən azı 2 AZ-də yerləşdirin (high availability üçün).
3. **Secrets Manager istifadə edin**: Environment variable-lərdə şifrə saxlamayın, AWS Secrets Manager və ya Parameter Store istifadə edin.
4. **Auto Scaling konfiqurasiyası**: Min capacity >= 2, max capacity kifayət qədər yüksək, scale-out tez, scale-in yavaş.
5. **DLQ konfiqurasiya edin**: Bütün SQS queue-lər üçün DLQ quraşdırın, monitor edin.
6. **Lambda timeout optimization**: Real ehtiyaca uyğun timeout, default 3s çox aşağıdır, 15 dəq çox yüksəkdir.
7. **CloudWatch Alarms**: CPU, memory, error rate, latency üçün alarm qurun.
8. **Log retention policy**: CloudWatch Logs-da retention policy qurun (default "Never Expire" baha başa gəlir).
9. **Tagging strategy**: Bütün resource-lərə `Environment`, `Project`, `Owner`, `CostCenter` tag-ləri qoyun.
10. **Cost optimization**: Reserved Instances, Savings Plans, Spot istifadə edin. AWS Cost Explorer ilə monitorlayın.
11. **ElastiCache encryption**: Transit və at-rest encryption aktivləşdirin.
12. **ECS task definition versioning**: Hər deploy yeni revision yaradır, rollback üçün istifadə edin.
13. **Lambda concurrency limit**: Runaway function-lara qarşı reserved concurrency təyin edin.
14. **SQS Long Polling**: ReceiveMessageWaitTimeSeconds=20 qoyun (empty response sayını azaldır).
15. **Health Checks**: ALB + ECS health check-lər Laravel `/health` endpoint-inə yönəldilməlidir.

---

## Praktik Tapşırıqlar

1. ECS Fargate-də Laravel API deploy edin: task definition JSON (CPU: 512, Memory: 1024, env vars from Secrets Manager), service yaradın (desired count: 2), ALB target group, health check `/health`; `aws ecs update-service --force-new-deployment` ilə zero-downtime update edin
2. Lambda ilə SQS-i birləşdirin: Laravel Queue job-unu Bref ilə Lambda-ya çevirin, `handler.php` yazın; SQS trigger konfigurasiya edin (batch size: 10, visibility timeout: 60s); DLQ əlavə edin; Lambda log-larını CloudWatch-da izləyin
3. CloudWatch custom metric göndərin: Laravel-dən `aws cloudwatch put-metric-data` ilə `CheckoutDuration` metric-i (namespace: `Laravel/App`); `MetricAlarm` yaradın — ortalama > 2s olduqda SNS → email; alarm state-ni test edin
4. Auto Scaling target tracking qurun: ECS service üçün `cpu_utilization` target 60% — load test əsnasında (`ab -n 10000 -c 100`) scale-out baş verdiyini izləyin; `aws application-autoscaling describe-scaling-activities` ilə event log oxuyun
5. ElastiCache Redis cluster qurun: replication group (1 primary + 1 replica), multi-AZ failover; Laravel `REDIS_HOST`, `REDIS_PORT` konfigurasiyası; `redis-cli -h <endpoint> PING`, cache hit/miss ratio-nu CloudWatch-da görün; primary-ni failover edin
6. SQS FIFO queue ilə order processing qurun: `ContentBasedDeduplication`, `MessageGroupId = order_id` (eyni sifarişin paralel işlənməməsi); `MaxReceiveCount: 3` → DLQ; Laravel-də `ShouldQueue` + `onQueue('orders.fifo')` işləyin; `aws sqs get-queue-attributes` ilə ApproximateNumberOfMessages izləyin

## Əlaqəli Mövzular

- [AWS Əsasları](14-aws-basics.md) — EC2, S3, VPC, IAM əsasları
- [Container Security](29-container-security.md) — ECS task IAM role, secret injection
- [Secrets Management](28-secrets-management.md) — AWS Secrets Manager, Parameter Store
- [Terraform Əsasları](23-terraform-basics.md) — ECS/Lambda Terraform konfiqurasiyası
- [Logging & Monitoring](38-logging-monitoring.md) — CloudWatch Logs, structured logging
