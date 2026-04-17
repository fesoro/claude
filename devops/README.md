# DevOps Interview Hazirliqi (Interview Preparation)

Bu qovluq DevOps movzularini ehtiva edir. Her fayl musahibe hazirligi ucun etrafli izahlar, praktiki numuneler ve interview suallari ile yazilmisdir.

## Movzular (Topics)

### CI/CD & Version Control
| # | Fayl | Movzu |
|---|------|-------|
| 01 | [01-cicd-concepts.md](01-cicd-concepts.md) | CI vs CD vs CD, pipeline stages, trunk-based development, feature flags |
| 02 | [02-github-actions.md](02-github-actions.md) | Workflow syntax, triggers, jobs, Laravel CI/CD with GitHub Actions |
| 03 | [03-jenkins.md](03-jenkins.md) | Jenkins architecture, Jenkinsfile, pipelines, Laravel Jenkins pipeline |
| 04 | [04-gitlab-ci.md](04-gitlab-ci.md) | .gitlab-ci.yml, stages, environments, Laravel GitLab CI |
| 22 | [22-git-advanced.md](22-git-advanced.md) | Branching strategies, rebase vs merge, hooks, monorepo |

### Linux Administration
| # | Fayl | Movzu |
|---|------|-------|
| 05 | [05-linux-basics.md](05-linux-basics.md) | File system, permissions, users/groups, pipes |
| 06 | [06-linux-process-management.md](06-linux-process-management.md) | Processes, systemd, cron, PHP-FPM process management |
| 07 | [07-linux-networking.md](07-linux-networking.md) | Networking commands, DNS, firewall, Laravel server setup |
| 08 | [08-linux-disk-storage.md](08-linux-disk-storage.md) | Disk management, LVM, RAID, filesystem types |
| 09 | [09-linux-shell-scripting.md](09-linux-shell-scripting.md) | Bash scripting, sed, awk, grep, Laravel deployment scripts |

### Web Servers & SSL
| # | Fayl | Movzu |
|---|------|-------|
| 10 | [10-nginx.md](10-nginx.md) | Nginx configuration, reverse proxy, load balancing, PHP-FPM |
| 11 | [11-apache.md](11-apache.md) | Apache configuration, virtual hosts, mod_rewrite, Laravel setup |
| 12 | [12-ssl-tls.md](12-ssl-tls.md) | SSL/TLS, certificates, Let's Encrypt, HTTPS setup |

### Monitoring & Logging
| # | Fayl | Movzu |
|---|------|-------|
| 13 | [13-monitoring-prometheus.md](13-monitoring-prometheus.md) | Prometheus metrics, PromQL, exporters, alerting |
| 14 | [14-monitoring-grafana.md](14-monitoring-grafana.md) | Grafana dashboards, panels, alerting, Laravel monitoring |
| 15 | [15-elk-stack.md](15-elk-stack.md) | Elasticsearch, Logstash, Kibana, Laravel log shipping |

### Infrastructure as Code
| # | Fayl | Movzu |
|---|------|-------|
| 16 | [16-terraform-basics.md](16-terraform-basics.md) | IaC, providers, resources, state management |
| 17 | [17-terraform-advanced.md](17-terraform-advanced.md) | Modules, workspaces, remote state, lifecycle rules |
| 18 | [18-ansible.md](18-ansible.md) | Playbooks, roles, tasks, Laravel server provisioning |

### Cloud & AWS
| # | Fayl | Movzu |
|---|------|-------|
| 19 | [19-aws-basics.md](19-aws-basics.md) | EC2, S3, RDS, VPC, IAM, Laravel on AWS |
| 20 | [20-aws-advanced.md](20-aws-advanced.md) | ECS, Lambda, SQS, CloudWatch, Laravel serverless |

### Networking & Security
| # | Fayl | Movzu |
|---|------|-------|
| 21 | [21-networking-basics.md](21-networking-basics.md) | OSI model, TCP/IP, DNS, HTTP/HTTPS, subnets |
| 24 | [24-secrets-management.md](24-secrets-management.md) | Vault, Secrets Manager, .env files, encryption |
| 25 | [25-container-security.md](25-container-security.md) | Image scanning, pod security, RBAC, network policies |

### Deployment & Reliability
| # | Fayl | Movzu |
|---|------|-------|
| 23 | [23-infrastructure-patterns.md](23-infrastructure-patterns.md) | Blue-green, canary, rolling update, Laravel Envoyer |
| 26 | [26-performance-tuning.md](26-performance-tuning.md) | Linux tuning, PHP-FPM, OPcache, MySQL tuning |
| 27 | [27-backup-strategies.md](27-backup-strategies.md) | Database/file backups, disaster recovery, Spatie Backup |
| 28 | [28-service-mesh.md](28-service-mesh.md) | Istio, Envoy, sidecar pattern, traffic management |
| 29 | [29-chaos-engineering.md](29-chaos-engineering.md) | Chaos Monkey, failure injection, game days |
| 30 | [30-site-reliability.md](30-site-reliability.md) | SRE principles, SLI/SLO/SLA, error budgets, incident management |
| 39 | [39-incident-response.md](39-incident-response.md) | On-call, PagerDuty/Opsgenie, SEV1-4, runbook, blameless postmortem |

### GitOps & Observability
| # | Fayl | Movzu |
|---|------|-------|
| 31 | [31-gitops.md](31-gitops.md) | GitOps principles, Argo CD, Flux CD, progressive delivery |
| 32 | [32-opentelemetry.md](32-opentelemetry.md) | OpenTelemetry traces/metrics/logs, OTel Collector, Laravel OTel SDK |
| 33 | [33-distributed-tracing.md](33-distributed-tracing.md) | Distributed tracing, Jaeger, Tempo, spans, context propagation |

### Cloud Providers
| # | Fayl | Movzu |
|---|------|-------|
| 34 | [34-gcp-basics.md](34-gcp-basics.md) | GCP – Compute, GKE, Cloud SQL, Cloud Storage, IAM |
| 35 | [35-azure-basics.md](35-azure-basics.md) | Azure – VM, AKS, App Service, Entra ID, Key Vault |
| 41 | [41-multi-cloud.md](41-multi-cloud.md) | Active-active, DR, vendor lock-in, Crossplane, hybrid cloud |

### Advanced Topics
| # | Fayl | Movzu |
|---|------|-------|
| 36 | [36-packer.md](36-packer.md) | HashiCorp Packer, AMI/Docker image build, multi-provider, CI integration |
| 37 | [37-policy-as-code.md](37-policy-as-code.md) | OPA, Rego, Gatekeeper, Conftest, Sentinel, K8s admission |
| 38 | [38-finops.md](38-finops.md) | Cloud cost optimization, RI/SP, spot, right-sizing, tagging, Kubecost |
| 40 | [40-ebpf.md](40-ebpf.md) | eBPF, Cilium, Pixie, Falco, Tetragon, XDP, kernel observability |
| 42 | [42-kubernetes-operators.md](42-kubernetes-operators.md) | Operator pattern, CRDs, kubebuilder, Prometheus/cert-manager/Strimzi |
