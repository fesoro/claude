# DevOps (Backend Developer üçün)

Bu qovluq backend developer-in (PHP/Laravel) bilməli olduğu DevOps mövzularını əhatə edir. Linux-dan başlayaraq cloud, CI/CD, monitoring, IaC, reliability mövzularına qədər Junior→Architect sıralaması ilə qurulub.

## Mövzular

### ⭐ Junior

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [01-linux-basics.md](01-linux-basics.md) | Linux Əsasları — fayl sistemi, icazələr, əsas komandalar |
| 02 | [02-networking-basics.md](02-networking-basics.md) | Şəbəkə Əsasları — OSI, TCP/IP, DNS, HTTP/HTTPS, subnet |
| 03 | [03-cicd-concepts.md](03-cicd-concepts.md) | CI/CD Konseptləri — pipeline stages, trunk-based, feature flags |

### ⭐⭐ Middle

| # | Fayl | Mövzu |
|---|------|-------|
| 04 | [04-github-actions.md](04-github-actions.md) | GitHub Actions — workflow syntax, triggers, Laravel CI/CD |
| 05 | [05-gitlab-ci.md](05-gitlab-ci.md) | GitLab CI/CD — .gitlab-ci.yml, stages, Laravel pipeline |
| 06 | [06-jenkins.md](06-jenkins.md) | Jenkins — Jenkinsfile, pipeline, Laravel deploy |
| 07 | [07-linux-process-management.md](07-linux-process-management.md) | Linux Proses İdarəetmə — systemd, cron, PHP-FPM |
| 08 | [08-linux-networking.md](08-linux-networking.md) | Linux Şəbəkə — firewall, DNS, server setup |
| 09 | [09-linux-disk-storage.md](09-linux-disk-storage.md) | Linux Disk & Yaddaş — LVM, RAID, filesystem |
| 10 | [10-linux-shell-scripting.md](10-linux-shell-scripting.md) | Shell Scripting — bash, sed, awk, deployment scripts |
| 11 | [11-nginx.md](11-nginx.md) | Nginx — reverse proxy, load balancing, PHP-FPM |
| 12 | [12-apache.md](12-apache.md) | Apache — virtual hosts, mod_rewrite, Laravel setup |
| 13 | [13-ssl-tls.md](13-ssl-tls.md) | SSL/TLS — certificates, Let's Encrypt, HTTPS |
| 14 | [14-aws-basics.md](14-aws-basics.md) | AWS Əsasları — EC2, S3, RDS, VPC, IAM |
| 15 | [15-gcp-basics.md](15-gcp-basics.md) | GCP Əsasları — Compute, GKE, Cloud SQL, IAM |
| 16 | [16-azure-basics.md](16-azure-basics.md) | Azure Əsasları — VM, AKS, App Service, Entra ID |
| 17 | [17-backup-strategies.md](17-backup-strategies.md) | Backup Strategiyaları — DB/fayl backup, disaster recovery |
| 38 | [38-logging-monitoring.md](38-logging-monitoring.md) | Logging & Monitoring — structured logs, metrics, alerting, Telescope, Pulse |
| 39 | [39-cicd-deployment.md](39-cicd-deployment.md) | CI/CD Deployment — pipeline design, artifact, deploy stages |
| 40 | [40-twelve-factor-app.md](40-twelve-factor-app.md) | Twelve-Factor App — 12 faktor metodologiyası, Laravel tətbiqi |

### ⭐⭐⭐ Senior

| # | Fayl | Mövzu |
|---|------|-------|
| 18 | [18-monitoring-prometheus.md](18-monitoring-prometheus.md) | Prometheus — metrics, PromQL, exporters, alerting |
| 19 | [19-monitoring-grafana.md](19-monitoring-grafana.md) | Grafana — dashboards, panels, RED/USE method, Laravel monitoring |
| 20 | [20-elk-stack.md](20-elk-stack.md) | ELK Stack — Elasticsearch, Logstash, Kibana, log shipping |
| 21 | [21-opentelemetry.md](21-opentelemetry.md) | OpenTelemetry — traces/metrics/logs, OTel Collector, Laravel SDK |
| 22 | [22-distributed-tracing.md](22-distributed-tracing.md) | Distributed Tracing — Jaeger, Tempo, spans, context propagation |
| 23 | [23-terraform-basics.md](23-terraform-basics.md) | Terraform Əsasları — IaC, providers, resources, state |
| 24 | [24-terraform-advanced.md](24-terraform-advanced.md) | Terraform Advanced — modules, workspaces, remote state |
| 25 | [25-ansible.md](25-ansible.md) | Ansible — playbooks, roles, Laravel server provisioning |
| 26 | [26-aws-advanced.md](26-aws-advanced.md) | AWS Advanced — ECS, Lambda, SQS, CloudWatch, serverless |
| 27 | [27-infrastructure-patterns.md](27-infrastructure-patterns.md) | Immutable Infrastructure & PHP Deploy Tools — Packer, Deployer, Envoyer |
| 28 | [28-secrets-management.md](28-secrets-management.md) | Secrets Management — Vault, Secrets Manager, encryption |
| 29 | [29-container-security.md](29-container-security.md) | Container Security — image scanning, pod security, RBAC |
| 30 | [30-performance-tuning.md](30-performance-tuning.md) | Performance Tuning — Linux, PHP-FPM, OPcache, MySQL |
| 31 | [31-incident-response.md](31-incident-response.md) | Incident Response — on-call, SEV1-4, runbook, postmortem |
| 41 | [41-zero-downtime-deployment.md](41-zero-downtime-deployment.md) | Zero-Downtime Deployment — rolling, blue-green, DB migrations |
| 42 | [42-observability.md](42-observability.md) | Observability — pillars (metrics/logs/traces), maturity model |
| 43 | [43-sla-slo-sli.md](43-sla-slo-sli.md) | SLA / SLO / SLI — praktik hesablama, error budget, burn rate |
| 44 | [44-deployment-strategies.md](44-deployment-strategies.md) | Deployment Strategies — canary, shadow, A/B, traffic splitting, Argo Rollouts |
| 46 | [46-load-testing.md](46-load-testing.md) | Load Testing — k6, Apache Bench, Locust, stress/spike/soak test |

### ⭐⭐⭐⭐ Lead

| # | Fayl | Mövzu |
|---|------|-------|
| 32 | [32-service-mesh.md](32-service-mesh.md) | Service Mesh — Istio, Envoy, sidecar, mTLS, traffic mgmt |
| 33 | [33-chaos-engineering.md](33-chaos-engineering.md) | Chaos Engineering — Chaos Monkey, failure injection, game days |
| 34 | [34-site-reliability.md](34-site-reliability.md) | Site Reliability Engineering — SLI/SLO/SLA, error budgets |
| 35 | [35-gitops.md](35-gitops.md) | GitOps — Argo CD, Flux CD, progressive delivery |
| 36 | [36-finops.md](36-finops.md) | FinOps — cloud cost, RI/SP, spot, right-sizing, Kubecost |
| 37 | [37-multi-cloud.md](37-multi-cloud.md) | Multi-Cloud — active-active, DR, Crossplane, hybrid |
| 45 | [45-dora-metrics.md](45-dora-metrics.md) | DORA Metrics — deploy frequency, lead time, MTTR, change failure |

## Reading Paths

### Backend Dev (PHP/Laravel) — DevOps Başlangıcı
01 → 02 → 03 → 04 → 07 → 08 → 11 → 13 → 14 → 17 → 38 → 39 → 40

### CI/CD Mütəxəssisi
03 → 04 → 05 → 06 → 39 → 23 → 24 → 25 → 35 → 45

### Monitoring & Observability
18 → 19 → 20 → 21 → 22 → 38 → 42 → 43 → 31 → 34 → 46

### Cloud & Infrastructure
02 → 14 → 15 → 16 → 23 → 24 → 25 → 26 → 37

### SRE / Reliability
27 → 28 → 30 → 31 → 33 → 34 → 35 → 36 → 41 → 43 → 44 → 45

### Deployment & Zero-Downtime
40 → 39 → 41 → 44 → 27 → 35

### Performance & Testing
30 → 46 → 18 → 42 → 43
