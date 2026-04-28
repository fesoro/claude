## Lifecycle commands

terraform version
terraform init                                — backend + provider yüklə
terraform init -upgrade                       — provider versiyasını yenilə
terraform init -reconfigure                    — backend dəyişib reset et
terraform init -migrate-state                  — backend miqrasiyası
terraform init -backend=false                  — yalnız provider (CI lint üçün)
terraform init -backend-config=key=value
terraform init -backend-config=backend.hcl

terraform fmt                                  — format (in-place)
terraform fmt -recursive
terraform fmt -check -diff                     — CI: format check

terraform validate                             — sintaksis + tip yoxla (no plan)

terraform plan                                 — proposed changes
terraform plan -out=tfplan                     — fayla yaz
terraform plan -var "name=value"
terraform plan -var-file="prod.tfvars"
terraform plan -target=aws_instance.web        — yalnız bir resurs
terraform plan -refresh=false                  — state refresh skip
terraform plan -destroy                        — destroy plan
terraform plan -compact-warnings
terraform plan -detailed-exitcode              — 0 no-changes, 1 error, 2 changes

terraform apply                                — interactive yes
terraform apply -auto-approve                  — CI üçün
terraform apply tfplan                         — saved plan
terraform apply -target=aws_instance.web
terraform apply -replace=aws_instance.web      — recreate (1.1+, taint əvəzi)
terraform apply -parallelism=10                — concurrent ops (default 10)

terraform destroy                              — hər şeyi sil (DİQQƏT)
terraform destroy -target=aws_instance.web
terraform destroy -auto-approve

terraform refresh                              — state ↔ real infra sync (deprecated → use plan -refresh-only)
terraform plan -refresh-only                   — drift detect
terraform apply -refresh-only                   — state-i real infra-ya görə yenilə

## State commands

terraform state list                           — bütün resources
terraform state list aws_instance.*
terraform state show aws_instance.web
terraform state mv aws_instance.web aws_instance.api    — resource yenidən adlandır
terraform state mv 'module.old' 'module.new'
terraform state rm aws_instance.web            — state-dən çıxar (silmir)
terraform state replace-provider hashicorp/aws registry.example.com/aws

terraform import aws_instance.web i-abc123     — mövcud resursu state-ə əlavə et
terraform import 'aws_security_group_rule.allow_ssh' sg-abc_ingress_tcp_22_22_0.0.0.0/0
# Modern (1.5+): import block in HCL — declarative
import {
  to = aws_instance.web
  id = "i-abc123"
}

terraform output                                — bütün outputs
terraform output db_password
terraform output -raw db_password               — quote-suz (CI üçün)
terraform output -json | jq '.db_password.value'

terraform show                                  — current state
terraform show -json                            — JSON
terraform show tfplan                           — saved plan
terraform show -json tfplan | jq

## Workspaces (state isolation, same code)

terraform workspace list
terraform workspace show
terraform workspace new dev / staging / prod
terraform workspace select dev
terraform workspace delete dev

# Use in code
locals {
  env = terraform.workspace
  prefix = "${var.project}-${terraform.workspace}"
}

## Console / debug

terraform console                              — REPL (test expressions)
> length(["a","b"])
> aws_instance.web.public_ip
> file("./script.sh")

TF_LOG=DEBUG terraform plan                     — provider debug
TF_LOG=TRACE terraform apply                    — full RPC trace
TF_LOG_PATH=tf.log
TF_LOG_PROVIDER=DEBUG                           — yalnız provider

terraform graph                                 — DAG (DOT format)
terraform graph | dot -Tsvg > graph.svg
terraform graph -type=plan-destroy

terraform providers                             — provider tree
terraform providers lock -platform=linux_amd64 -platform=darwin_arm64
terraform providers schema -json

## Modules

terraform get                                   — modules yüklə
terraform get -update
terraform init                                  — get + provider (preferred)

# Module usage
module "vpc" {
  source  = "terraform-aws-modules/vpc/aws"
  version = "~> 5.0"

  name = "prod-vpc"
  cidr = "10.0.0.0/16"
}
# Local module:    source = "./modules/vpc"
# Git:             source = "git::https://github.com/x/y.git//module?ref=v1.0"
# Registry:        source = "namespace/name/provider"
# S3 / GCS:        source = "s3::https://s3.eu-west-1.amazonaws.com/bucket/key"

## HCL — variables / outputs / locals

# variables.tf
variable "region" {
  type        = string
  default     = "eu-west-1"
  description = "AWS region"
}

variable "instance_count" {
  type    = number
  default = 1
  validation {
    condition     = var.instance_count > 0 && var.instance_count <= 10
    error_message = "1-10 olmalıdır."
  }
}

variable "tags" {
  type    = map(string)
  default = {}
}

variable "subnets" {
  type = list(object({
    cidr = string
    az   = string
  }))
  sensitive = false
}

# locals.tf
locals {
  common_tags = merge(var.tags, { Project = "myapp", ManagedBy = "terraform" })
  prefix      = "${var.project}-${terraform.workspace}"
}

# outputs.tf
output "vpc_id" {
  value       = aws_vpc.this.id
  description = "VPC ID"
}

output "db_password" {
  value     = aws_db_instance.this.password
  sensitive = true
}

# Variable precedence (highest first):
# 1. -var / -var-file CLI
# 2. *.auto.tfvars / *.auto.tfvars.json (alphabetical)
# 3. terraform.tfvars / terraform.tfvars.json
# 4. TF_VAR_<name> environment
# 5. Default in variable block

## Backends (remote state)

# S3 + DynamoDB lock (canonical AWS setup)
terraform {
  backend "s3" {
    bucket         = "my-tfstate"
    key            = "prod/terraform.tfstate"
    region         = "eu-west-1"
    encrypt        = true
    dynamodb_table = "terraform-locks"
    use_lockfile   = true        # 1.10+ alternative to dynamodb
  }
}

# GCS
terraform { backend "gcs" { bucket = "tfstate"; prefix = "prod" } }

# Azure
terraform { backend "azurerm" { resource_group_name = "rg"; storage_account_name = "sa"; container_name = "tfstate"; key = "prod.tfstate" } }

# HCP / Terraform Cloud
terraform { cloud { organization = "myorg"; workspaces { name = "prod" } } }

# Initialize backend with vars (avoid hard-coding secrets)
terraform init -backend-config="bucket=my-tfstate" -backend-config="key=prod/terraform.tfstate"

## Common HCL constructs

# count
resource "aws_instance" "web" {
  count         = var.instance_count
  ami           = var.ami
  instance_type = "t3.micro"
  tags = { Name = "web-${count.index}" }
}

# for_each (preferred over count for unique items)
resource "aws_iam_user" "team" {
  for_each = toset(["alice","bob","charlie"])
  name     = each.key
}

# Map iteration
resource "aws_s3_bucket" "lake" {
  for_each = var.buckets    # map(object)
  bucket   = each.key
  tags     = each.value.tags
}

# Dynamic block
resource "aws_security_group" "web" {
  dynamic "ingress" {
    for_each = var.ports
    content {
      from_port = ingress.value
      to_port   = ingress.value
      protocol  = "tcp"
      cidr_blocks = ["0.0.0.0/0"]
    }
  }
}

# Conditional / ternary
instance_type = var.env == "prod" ? "t3.large" : "t3.micro"

# Splat
all_ips = aws_instance.web[*].private_ip
all_ids = values(aws_instance.web)[*].id        # for for_each

# For expressions
[for n in var.names : upper(n)]
{ for k, v in var.users : k => v.email }
[for v in var.list : v if v.enabled]

# lifecycle
resource "aws_db_instance" "this" {
  # ...
  lifecycle {
    create_before_destroy = true
    prevent_destroy       = true
    ignore_changes        = [password, tags["LastModified"]]
    replace_triggered_by  = [null_resource.deploy_id]   # 1.2+
    precondition {
      condition     = var.password != ""
      error_message = "password lazımdır"
    }
    postcondition {
      condition     = self.endpoint != ""
      error_message = "endpoint boşdur"
    }
  }
}

# data source (read-only)
data "aws_ami" "ubuntu" {
  most_recent = true
  owners      = ["099720109477"]
  filter { name = "name"; values = ["ubuntu/images/hvm-ssd/ubuntu-jammy-22.04-amd64-server-*"] }
}

# moved block (refactor without import — 1.1+)
moved {
  from = aws_instance.web
  to   = aws_instance.api
}

# removed block (1.7+, replaces state rm)
removed {
  from = aws_instance.legacy
  lifecycle { destroy = false }       # state-dən çıxar, real infra qalsın
}

# import block (1.5+, declarative)
import {
  to = aws_s3_bucket.legacy
  id = "my-old-bucket"
}

## Provider

terraform {
  required_version = ">= 1.5"
  required_providers {
    aws = { source = "hashicorp/aws"; version = "~> 5.0" }
  }
}

provider "aws" {
  region  = var.region
  profile = "prod"
  default_tags {
    tags = { ManagedBy = "terraform", Project = "myapp" }
  }
}

# Multiple instances (alias)
provider "aws" { alias = "us"; region = "us-east-1" }
resource "aws_..." "x" { provider = aws.us; ... }

## Useful flags / env

TF_VAR_region=eu-west-1                         — variable from env
TF_INPUT=0                                      — disable interactive
TF_IN_AUTOMATION=true                            — CI mode (less verbose)
TF_CLI_ARGS_apply="-auto-approve"                — implicit args
TF_DATA_DIR=.terraform                           — data dir override
TF_PLUGIN_CACHE_DIR=~/.terraform.d/plugin-cache  — share providers
TF_REGISTRY_DISCOVERY_RETRY=5
TF_REGISTRY_CLIENT_TIMEOUT=15

## Common workflows

# CI lint check
terraform fmt -check -recursive
terraform init -backend=false
terraform validate

# Plan-then-apply (CI/CD)
terraform plan -out=tfplan -input=false
terraform apply -input=false -auto-approve tfplan

# Drift detection (cron)
terraform plan -refresh-only -detailed-exitcode

# Destroy single resource
terraform destroy -target=aws_instance.web -auto-approve

# Recreate resource (modern, 1.1+)
terraform apply -replace=aws_instance.web

# Old way (deprecated)
terraform taint aws_instance.web
terraform untaint aws_instance.web

# Force unlock (after CI crash leaves lock)
terraform force-unlock LOCK_ID

# State backup / restore
cp terraform.tfstate terraform.tfstate.bak
terraform state pull > current.tfstate
terraform state push fixed.tfstate              — DANGEROUS

## Terragrunt (DRY wrapper)

terragrunt init / plan / apply / destroy        — same as terraform
terragrunt run-all apply                         — apply across all modules
terragrunt run-all plan
terragrunt validate-inputs
terragrunt graph-dependencies
# terragrunt.hcl per module:
include "root" { path = find_in_parent_folders() }
inputs = { region = "eu-west-1", env = "prod" }

## Tooling around Terraform

tflint                                          — linter (best practices, deprecated args)
tfsec / trivy config                            — security scan
checkov                                          — policy/compliance scan
terrascan                                        — OPA-based policy scan
terraform-docs                                   — generate README from variables/outputs
infracost                                        — cost estimate from plan
atlantis                                         — PR-based GitOps workflow
spacelift / env0 / scalr                          — managed Terraform
opentofu (tofu)                                   — open-source fork (1.6+)

## Common gotchas

- Never commit terraform.tfstate (contains secrets, IDs); use remote backend
- Never edit state file by hand — use terraform state commands
- count vs for_each: count uses index (fragile if list reorders), for_each uses key (stable)
- depends_on yalnız implicit dependency çatmadıqda istifadə et
- ${...} interpolation 0.12+ artıq lazım deyil — to_var (var.x) directly
- Provider config is global per-alias; can't be conditional on resource
- Module versioning: always pin (~> 5.0 yox >= 5.0)
- terraform apply during plan → race; use -lock=true (default) + remote backend with locks
- Sensitive outputs in CI logs: -json əvəzinə -raw + redact
- Drift between TF and console-edited resources → plan -refresh-only catches
