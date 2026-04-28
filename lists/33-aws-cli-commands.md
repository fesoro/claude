## Setup / config / auth

aws --version                                — versiya yoxla
aws configure                                 — interaktiv (key + region)
aws configure set region eu-west-1
aws configure list                            — aktiv config göstər
aws configure list-profiles
aws configure --profile prod                  — yeni profile
aws sts get-caller-identity                   — kim kimi imitasiya edir
aws sts get-caller-identity --profile prod
aws sts assume-role --role-arn arn:aws:iam::123:role/Admin --role-session-name s
aws sts get-session-token --serial-number arn:... --token-code 123456 — MFA

# AWS_PROFILE / AWS_REGION env-lar
export AWS_PROFILE=prod
export AWS_REGION=eu-west-1
export AWS_DEFAULT_REGION=us-east-1
export AWS_ACCESS_KEY_ID=...
export AWS_SECRET_ACCESS_KEY=...
export AWS_SESSION_TOKEN=...                  — temporary (assume-role)

# SSO (modern, MFA + temporary creds)
aws configure sso
aws sso login --profile prod
aws sso logout

# Files
~/.aws/config                                 — profile config
~/.aws/credentials                            — long-term keys (avoid; use SSO)

## Common flags

--profile prod                                — profile override
--region us-east-1
--output json|table|text|yaml|yaml-stream
--query 'Reservations[].Instances[].InstanceId'   — JMESPath filter
--no-cli-pager                                — disable less-style paging
--debug                                       — full HTTP trace
--dry-run                                     — supported on some EC2/IAM ops
--page-size 100 / --max-items 50              — pagination tuning

# Common JMESPath examples
--query 'Buckets[].Name'                                   — flat list
--query 'Buckets[?contains(Name, `prod`)].Name'            — filter by name
--query 'sort_by(Buckets, &CreationDate)[-3:].Name'        — last 3 created
--query 'length(Reservations[].Instances[])'               — count
--query 'Instances[].{ID:InstanceId,IP:PrivateIpAddress}'  — projection

## S3

aws s3 ls                                     — bucket-lar
aws s3 ls s3://mybucket/path/
aws s3 ls s3://mybucket --recursive --human-readable --summarize
aws s3 mb s3://mybucket --region us-east-1     — make bucket
aws s3 rb s3://mybucket --force                — remove (force = empty first)
aws s3 cp file.txt s3://mybucket/path/
aws s3 cp s3://mybucket/file.txt .
aws s3 cp s3://mybucket/dir/ ./local/ --recursive
aws s3 cp file s3://mybucket/file --acl public-read
aws s3 cp file s3://mybucket/ --storage-class STANDARD_IA
aws s3 cp file s3://mybucket/ --sse AES256                   — server-side encrypt
aws s3 cp file s3://mybucket/ --sse aws:kms --sse-kms-key-id arn:...
aws s3 sync ./dir/ s3://mybucket/dir/                         — incremental
aws s3 sync ./dir/ s3://mybucket/dir/ --delete                — mirror
aws s3 sync ./dir/ s3://mybucket/dir/ --exclude "*.tmp" --include "*.json"
aws s3 mv s3://a/x s3://b/x
aws s3 rm s3://mybucket/path/file
aws s3 rm s3://mybucket/path/ --recursive
aws s3 presign s3://mybucket/file --expires-in 3600           — signed URL

# s3api (lower-level)
aws s3api list-buckets
aws s3api list-objects-v2 --bucket mybucket --prefix logs/
aws s3api put-object --bucket mybucket --key file --body file --metadata foo=bar
aws s3api get-object --bucket mybucket --key file out.txt
aws s3api head-object --bucket mybucket --key file            — metadata only
aws s3api copy-object --bucket dst --key k --copy-source src/k
aws s3api put-bucket-versioning --bucket mybucket --versioning-configuration Status=Enabled
aws s3api put-bucket-policy --bucket mybucket --policy file://policy.json
aws s3api get-bucket-policy --bucket mybucket
aws s3api put-bucket-lifecycle-configuration --bucket mybucket --lifecycle-configuration file://lc.json
aws s3api list-multipart-uploads --bucket mybucket            — abandoned uploads
aws s3api abort-multipart-upload --bucket b --key k --upload-id ID

# Useful flags
--quiet / --only-show-errors / --no-progress
--dryrun
--metadata-directive REPLACE
--cache-control "max-age=3600"
--content-type "application/json"

## EC2

aws ec2 describe-instances
aws ec2 describe-instances --filters "Name=tag:Env,Values=prod" "Name=instance-state-name,Values=running"
aws ec2 describe-instances --instance-ids i-abc i-def
aws ec2 describe-instances --query 'Reservations[].Instances[].[InstanceId,PrivateIpAddress,Tags[?Key==`Name`]|[0].Value]' --output table
aws ec2 run-instances --image-id ami-... --count 1 --instance-type t3.micro --key-name mykey --security-group-ids sg-... --subnet-id subnet-...
aws ec2 start-instances --instance-ids i-abc
aws ec2 stop-instances --instance-ids i-abc
aws ec2 reboot-instances --instance-ids i-abc
aws ec2 terminate-instances --instance-ids i-abc
aws ec2 modify-instance-attribute --instance-id i-abc --instance-type Value=t3.large

aws ec2 describe-images --owners self --filters "Name=name,Values=app-*"
aws ec2 create-image --instance-id i-abc --name "snapshot-$(date +%F)" --no-reboot
aws ec2 create-snapshot --volume-id vol-... --description "..."
aws ec2 describe-volumes --filters "Name=status,Values=available"

aws ec2 describe-security-groups
aws ec2 authorize-security-group-ingress --group-id sg-... --protocol tcp --port 443 --cidr 0.0.0.0/0
aws ec2 revoke-security-group-ingress --group-id sg-... --protocol tcp --port 22 --cidr 1.2.3.4/32

aws ec2 describe-vpcs / subnets / route-tables / nat-gateways / internet-gateways
aws ec2 describe-key-pairs

aws ec2 get-password-data --instance-id i-abc --priv-launch-key key.pem  — Windows
aws ec2-instance-connect ssh --instance-id i-abc                          — temporary SSH (modern)
aws ssm start-session --target i-abc                                       — Session Manager (no SSH key)
aws ssm send-command --instance-ids i-abc --document-name AWS-RunShellScript --parameters 'commands=["uptime"]'

## IAM

aws iam list-users
aws iam list-roles
aws iam list-policies --scope Local
aws iam list-attached-user-policies --user-name alice
aws iam list-attached-role-policies --role-name app-role
aws iam list-role-policies --role-name app-role                 — inline
aws iam get-policy --policy-arn arn:aws:iam::aws:policy/...
aws iam get-policy-version --policy-arn arn:... --version-id v1
aws iam create-user --user-name alice
aws iam create-access-key --user-name alice                      — return AccessKeyId/SecretAccessKey
aws iam delete-access-key --user-name alice --access-key-id ...
aws iam create-role --role-name app --assume-role-policy-document file://trust.json
aws iam attach-role-policy --role-name app --policy-arn arn:...
aws iam put-role-policy --role-name app --policy-name inline --policy-document file://p.json
aws iam create-policy --policy-name X --policy-document file://p.json
aws iam simulate-principal-policy --policy-source-arn arn:...:user/alice --action-names s3:GetObject --resource-arns arn:aws:s3:::bucket/* — policy simulator
aws iam get-account-authorization-details                         — full export
aws iam list-account-aliases

# Roles / trust policy snippet (assume-role)
{ "Version": "2012-10-17", "Statement": [{ "Effect": "Allow", "Principal": { "Service": "lambda.amazonaws.com" }, "Action": "sts:AssumeRole" }] }

## RDS

aws rds describe-db-instances
aws rds describe-db-instances --query 'DBInstances[].[DBInstanceIdentifier,Engine,DBInstanceStatus,Endpoint.Address]' --output table
aws rds create-db-instance --db-instance-identifier mydb --db-instance-class db.t3.micro --engine postgres --master-username admin --master-user-password '...' --allocated-storage 20
aws rds modify-db-instance --db-instance-identifier mydb --apply-immediately --backup-retention-period 7
aws rds reboot-db-instance --db-instance-identifier mydb
aws rds delete-db-instance --db-instance-identifier mydb --skip-final-snapshot
aws rds describe-db-snapshots --db-instance-identifier mydb
aws rds create-db-snapshot --db-instance-identifier mydb --db-snapshot-identifier snap-$(date +%F)
aws rds restore-db-instance-from-db-snapshot --db-instance-identifier restored --db-snapshot-identifier snap-...
aws rds describe-db-clusters                                       — Aurora
aws rds describe-events --duration 60                              — last hour events

## Lambda

aws lambda list-functions
aws lambda get-function --function-name myfn
aws lambda create-function --function-name myfn --runtime python3.12 --role arn:... --handler app.handler --zip-file fileb://function.zip
aws lambda update-function-code --function-name myfn --zip-file fileb://function.zip
aws lambda update-function-configuration --function-name myfn --memory-size 512 --timeout 30 --environment "Variables={KEY=VAL}"
aws lambda invoke --function-name myfn --payload '{"k":"v"}' --cli-binary-format raw-in-base64-out out.json
aws lambda invoke --function-name myfn --invocation-type Event --payload ... out.json   — async
aws lambda invoke --function-name myfn --log-type Tail --query 'LogResult' --output text | base64 -d
aws lambda publish-version --function-name myfn
aws lambda create-alias --function-name myfn --name prod --function-version 5
aws lambda update-alias --function-name myfn --name prod --function-version 6
aws lambda list-event-source-mappings
aws lambda add-permission --function-name myfn --statement-id sns --action lambda:InvokeFunction --principal sns.amazonaws.com --source-arn arn:...

## SQS

aws sqs list-queues
aws sqs create-queue --queue-name my-queue --attributes VisibilityTimeout=60,MessageRetentionPeriod=345600
aws sqs get-queue-url --queue-name my-queue
aws sqs get-queue-attributes --queue-url URL --attribute-names All
aws sqs send-message --queue-url URL --message-body '{"k":"v"}'
aws sqs send-message --queue-url URL --message-body ... --message-group-id g1 --message-deduplication-id d1   — FIFO
aws sqs send-message-batch --queue-url URL --entries file://batch.json
aws sqs receive-message --queue-url URL --max-number-of-messages 10 --wait-time-seconds 20
aws sqs delete-message --queue-url URL --receipt-handle ...
aws sqs purge-queue --queue-url URL                                — empty
aws sqs delete-queue --queue-url URL
aws sqs set-queue-attributes --queue-url URL --attributes Policy=...,RedrivePolicy='{"deadLetterTargetArn":"arn:...","maxReceiveCount":"3"}'

## SNS

aws sns list-topics
aws sns create-topic --name my-topic
aws sns publish --topic-arn arn:... --message "hello" --subject "Test"
aws sns publish --phone-number +99450... --message "hi"             — SMS
aws sns subscribe --topic-arn arn:... --protocol email --notification-endpoint user@x.com
aws sns subscribe --topic-arn arn:... --protocol https --notification-endpoint https://hook.x.com
aws sns subscribe --topic-arn arn:... --protocol sqs --notification-endpoint arn:aws:sqs:...
aws sns confirm-subscription --topic-arn arn:... --token ...
aws sns list-subscriptions-by-topic --topic-arn arn:...
aws sns set-subscription-attributes --subscription-arn arn:... --attribute-name FilterPolicy --attribute-value '{"event":["created"]}'

## DynamoDB

aws dynamodb list-tables
aws dynamodb describe-table --table-name users
aws dynamodb create-table --table-name users \
  --attribute-definitions AttributeName=id,AttributeType=S \
  --key-schema AttributeName=id,KeyType=HASH \
  --billing-mode PAY_PER_REQUEST
aws dynamodb put-item --table-name users --item '{"id":{"S":"1"},"name":{"S":"Alice"}}'
aws dynamodb get-item --table-name users --key '{"id":{"S":"1"}}' --consistent-read
aws dynamodb update-item --table-name users --key '{"id":{"S":"1"}}' --update-expression "SET age=:a" --expression-attribute-values '{":a":{"N":"30"}}'
aws dynamodb delete-item --table-name users --key '{"id":{"S":"1"}}'
aws dynamodb query --table-name users --key-condition-expression "id = :id" --expression-attribute-values '{":id":{"S":"1"}}'
aws dynamodb scan --table-name users --filter-expression "age > :a" --expression-attribute-values '{":a":{"N":"18"}}'
aws dynamodb batch-write-item --request-items file://batch.json
aws dynamodb transact-write-items --transact-items file://tx.json
aws dynamodb describe-time-to-live --table-name users
aws dynamodb update-time-to-live --table-name users --time-to-live-specification "Enabled=true,AttributeName=ttl"
aws dynamodb create-backup --table-name users --backup-name b1
aws dynamodb export-table-to-point-in-time --table-arn arn:... --s3-bucket mybucket --export-format DYNAMODB_JSON

## CloudWatch / Logs

aws logs describe-log-groups
aws logs describe-log-streams --log-group-name /aws/lambda/myfn --order-by LastEventTime --descending --max-items 5
aws logs tail /aws/lambda/myfn --follow                            — live tail
aws logs tail /aws/lambda/myfn --since 10m
aws logs tail /aws/lambda/myfn --filter-pattern "ERROR"
aws logs tail /aws/lambda/myfn --format short
aws logs filter-log-events --log-group-name X --filter-pattern "ERROR" --start-time $(date -d '1 hour ago' +%s)000
aws logs put-retention-policy --log-group-name X --retention-in-days 30
aws logs delete-log-group --log-group-name X
aws logs start-query --log-group-name X --start-time ... --end-time ... --query-string 'fields @timestamp, @message | filter @message like /ERROR/ | sort @timestamp desc | limit 100'
aws logs get-query-results --query-id ...

# Metrics
aws cloudwatch list-metrics --namespace AWS/EC2
aws cloudwatch get-metric-statistics --namespace AWS/EC2 --metric-name CPUUtilization --dimensions Name=InstanceId,Value=i-abc --start-time ... --end-time ... --period 300 --statistics Average
aws cloudwatch put-metric-data --namespace MyApp --metric-name Visits --value 1
aws cloudwatch describe-alarms

## Secrets Manager

aws secretsmanager list-secrets
aws secretsmanager get-secret-value --secret-id myapp/db --query SecretString --output text
aws secretsmanager get-secret-value --secret-id myapp/db --version-stage AWSPREVIOUS
aws secretsmanager create-secret --name myapp/db --secret-string '{"user":"u","pass":"p"}'
aws secretsmanager put-secret-value --secret-id myapp/db --secret-string '{...}'
aws secretsmanager update-secret --secret-id myapp/db --description "..."
aws secretsmanager rotate-secret --secret-id myapp/db --rotation-lambda-arn arn:... --rotation-rules AutomaticallyAfterDays=30
aws secretsmanager delete-secret --secret-id myapp/db --recovery-window-in-days 7
aws secretsmanager restore-secret --secret-id myapp/db

## SSM Parameter Store

aws ssm get-parameter --name /myapp/db/host --with-decryption --query Parameter.Value --output text
aws ssm get-parameters --names /a /b --with-decryption
aws ssm get-parameters-by-path --path /myapp/ --recursive --with-decryption
aws ssm put-parameter --name /myapp/db/host --type String --value "db.example.com" --overwrite
aws ssm put-parameter --name /myapp/db/pass --type SecureString --value '...' --key-id alias/aws/ssm
aws ssm delete-parameter --name /myapp/db/host
aws ssm describe-parameters
aws ssm label-parameter-version --name X --labels prod

## ECR / ECS / EKS basics

# ECR (Docker registry)
aws ecr get-login-password --region eu-west-1 | docker login --username AWS --password-stdin 123456789012.dkr.ecr.eu-west-1.amazonaws.com
aws ecr create-repository --repository-name myapp
aws ecr describe-repositories
aws ecr list-images --repository-name myapp
aws ecr describe-images --repository-name myapp --image-ids imageTag=v1.0
aws ecr batch-delete-image --repository-name myapp --image-ids imageTag=old

# ECS
aws ecs list-clusters / list-services / list-tasks
aws ecs describe-services --cluster X --services Y
aws ecs update-service --cluster X --service Y --force-new-deployment
aws ecs run-task --cluster X --task-definition Z --launch-type FARGATE --network-configuration ...
aws ecs execute-command --cluster X --task TASK_ID --container app --interactive --command "/bin/sh"

# EKS
aws eks update-kubeconfig --name my-cluster --region eu-west-1   — generates kubeconfig
aws eks list-clusters / describe-cluster --name my-cluster

## VPC / Networking quick

aws ec2 describe-vpcs
aws ec2 describe-subnets --filters "Name=vpc-id,Values=vpc-..."
aws ec2 describe-security-groups --filters "Name=group-name,Values=web"
aws ec2 describe-route-tables
aws ec2 describe-vpc-endpoints
aws ec2 describe-network-interfaces --filters "Name=description,Values=*Lambda*"
aws ec2 describe-network-acls
aws ec2 describe-vpc-peering-connections

## Useful patterns

# Last 5 EC2 instances by launch time (table)
aws ec2 describe-instances --query 'Reservations[].Instances | sort_by([], &LaunchTime)[-5:].[InstanceId,InstanceType,LaunchTime]' --output table

# Tagging bulk
aws ec2 create-tags --resources i-abc i-def --tags Key=Env,Value=prod Key=Team,Value=backend

# S3 bucket size
aws s3 ls s3://bucket --recursive --human-readable --summarize | tail -2

# Empty all multipart uploads (cost saver)
aws s3api list-multipart-uploads --bucket b --query 'Uploads[].[Key,UploadId]' --output text \
  | xargs -L1 -P4 sh -c 'aws s3api abort-multipart-upload --bucket b --key "$0" --upload-id "$1"'

# Wait for async operation
aws cloudformation wait stack-create-complete --stack-name X
aws ecs wait services-stable --cluster C --services S
aws rds wait db-instance-available --db-instance-identifier mydb
aws lambda wait function-active-v2 --function-name myfn

# JSON output → jq
aws ec2 describe-instances --output json | jq '.Reservations[].Instances[] | {id: .InstanceId, ip: .PrivateIpAddress}'

# Concurrent invocations safe (separate profiles)
AWS_PROFILE=prod aws s3 ls
AWS_PROFILE=stage aws s3 ls

## Tooling around aws-cli

aws-vault — credentials in OS keyring (Mac/Linux/Win)
saml2aws / aws-sso-cli — IdP-based login
awscli-local / endpoint-url for LocalStack
aws-shell — interactive REPL with autocomplete
aws-okta — Okta-backed creds
ecs-cli (legacy) → use Copilot CLI for ECS
copilot — AWS App Runner / ECS deploy CLI
sam — Serverless Application Model (Lambda + API GW)
cdk — Cloud Development Kit (TypeScript/Python/Java/Go)

## Config / credentials file format

# ~/.aws/credentials
[default]
aws_access_key_id = AKIA...
aws_secret_access_key = ...

[prod]
aws_access_key_id = AKIA...
aws_secret_access_key = ...

# ~/.aws/config
[default]
region = eu-west-1
output = json

[profile prod]
region = us-east-1
role_arn = arn:aws:iam::123:role/Admin
source_profile = default
mfa_serial = arn:aws:iam::456:mfa/alice

[profile sso]
sso_session = corp
sso_account_id = 111
sso_role_name = AdminAccess
region = eu-west-1

[sso-session corp]
sso_start_url = https://corp.awsapps.com/start
sso_region = eu-west-1
sso_registration_scopes = sso:account:access
