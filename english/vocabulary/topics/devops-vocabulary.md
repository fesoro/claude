# DevOps Vocabulary — DevOps və İnfrastruktur Lüğəti

## Səviyyə
B1-B2 (DevOps / SRE / Backend interview)

---

## Niyə Vacibdir?

DevOps / SRE interview-ları spesifik lüğət tələb edir. Bu sənəd əsas terminləri toplayır.

---

## 1. Deployment Terms

### Deploy / Deployment

Kodu production-a çıxarma.
- **Deploy** to staging.
- **Deployment** pipeline.

### Ship / Shipping

Release (slang).
- **Ship** the feature.

### Release

Rəsmi versiya.
- **Release** v2.0.

### Rollout

Tədricən release.
- Gradual **rollout**.

### Rollback

Geri qaytarmaq.
- **Rollback** the deployment.

### Hotfix

Təcili production fix.
- Emergency **hotfix**.

### Cold deploy / Warm deploy

Sistem söndürülüb / aktiv.
- **Cold deploy** = with downtime.
- **Warm deploy** = no downtime.

### Blue-green deployment

İki eyni environment arasında keçid.
- **Blue-green** zero-downtime deploy.

### Canary deployment

Azlıq istifadəçiyə ilk release.
- **Canary** rollout to 1% first.

### Feature flag / Toggle

Özəllik açıb-söndürən.
- Use **feature flags** for gradual rollout.

→ Related: [deploy-ship-release-rollout.md](../../grammar/comparisons/deploy-ship-release-rollout.md)

---

## 2. Infrastructure

### Server

Sunucu.
- Web **server**, DB **server**.

### Cluster

Sunucular qrupu.
- Kubernetes **cluster**.

### Node

Cluster-də bir sunucu.
- Worker **node**.

### Pod (K8s)

Kubernetes-də container unit.

### Container

Izolyasiya edilmiş proses.
- Docker **container**.

### VM (Virtual Machine)

Virtual sunucu.
- Running on EC2 **VMs**.

### Bare metal

Fiziki sunucu.
- **Bare metal** servers.

### Serverless

Serversiz (abstracted).
- **Serverless** functions.

---

## 3. Networking

### Load balancer (LB)

Yük paylayan.
- **Load balancer** distributes traffic.

### Reverse proxy

Arxa proxy.
- Nginx as a **reverse proxy**.

### CDN (Content Delivery Network)

Kontent çatdırma şəbəkəsi.
- **CDN** for static assets.

### DNS

Domain name system.
- **DNS** resolution.

### TTL (Time To Live)

Yaşama müddəti.
- Cache **TTL** is 60 seconds.

### Subnet

Alt şəbəkə.
- Private **subnet**.

### VPC (Virtual Private Cloud)

Virtual özəl bulud.

### Firewall

Qoruyucu.
- Configure **firewall** rules.

### Security group

AWS firewall.
- **Security groups** control access.

---

## 4. Availability / Reliability

### Uptime

Sistemin işlək vaxtı.
- 99.9% **uptime**.

### Downtime

Sistemin düşdüyü vaxt.
- Minimize **downtime**.

### HA (High Availability)

Yüksək əlverişlilik.
- **HA** setup with replicas.

### Redundancy

Tədbir çoxluğu.
- Add **redundancy** to prevent single points of failure.

### Failover

Əsas kimi keç.
- Automatic **failover**.

### SPOF (Single Point of Failure)

Tək başarısız nöqtə.
- Avoid **SPOFs**.

### Disaster recovery (DR)

Fəlakətdən bərpa.
- **DR** plan.

### RTO (Recovery Time Objective)

Bərpa müddəti hədəfi.

### RPO (Recovery Point Objective)

Data itkisi hədəfi.

---

## 5. SLA / SLO / SLI

Vacib üçlük!

### SLA (Service Level Agreement)

Xidmət səviyyəsi anlaşması (müştəri ilə).
- 99.9% **SLA**.

### SLO (Service Level Objective)

Daxili hədəf (SLA-dan sərt).
- 99.95% **SLO**.

### SLI (Service Level Indicator)

Ölçü göstəricisi.
- Latency, error rate — **SLIs**.

### Example

- **SLA**: 99.9% uptime (ext. promise)
- **SLO**: 99.95% uptime (int. target)
- **SLI**: avg latency, error rate (metrics)

---

## 6. Monitoring / Observability

### Metrics

Ölçü göstəriciləri.
- CPU, memory **metrics**.

### Logs

Qeydlər.
- Application **logs**.

### Traces

İz (distributed).
- Distributed **tracing**.

### Alerts

Xəbərdarlıqlar.
- Set up **alerts** for errors.

### Dashboard

Paneli.
- Grafana **dashboard**.

### APM (Application Performance Monitoring)

Tətbiq performansı.
- Use **APM** tools like Datadog.

### Three Pillars of Observability

1. **Metrics** — rəqəmlər
2. **Logs** — qeydlər
3. **Traces** — izlər

→ Related: [monitor-observe-track-log.md](../../grammar/comparisons/monitor-observe-track-log.md)

---

## 7. Incidents

### Incident

Production hadisəsi.
- Major **incident**.

### Outage

Xidmət düşməsi.
- Full **outage**.

### Post-mortem

Sonrakı analiz.
- Write a **post-mortem**.

### RCA (Root Cause Analysis)

Əsas səbəb analizi.
- Conduct **RCA**.

### Incident commander

Hadisə rəhbəri.
- Who's the **incident commander**?

### Severity (SEV)

Ciddilik səviyyəsi.
- **SEV-1** = critical.
- **SEV-2** = major.
- **SEV-3** = minor.

### MTTR (Mean Time To Recover)

Orta bərpa müddəti.

### MTBF (Mean Time Between Failures)

İki fəsadsızlıq arası.

---

## 8. CI/CD

### CI (Continuous Integration)

Davamlı inteqrasiya.
- **CI** pipeline runs tests.

### CD (Continuous Delivery / Deployment)

Davamlı çatdırma / yayımlama.
- **CD** auto-deploys.

### Pipeline

Proses zənciri.
- CI/CD **pipeline**.

### Build

Kod kompilyasiya.
- **Build** failed.

### Stage

Pipeline mərhələsi.
- Build, test, deploy **stages**.

### Artifact

Kompilyasiya nəticəsi.
- Binary **artifact**.

### Registry

Image saxlama.
- Docker **registry**.

---

## 9. Scaling

### Scale up (Vertical)

Sunucu gücünü artırmaq.
- **Scale up** the DB.

### Scale out (Horizontal)

Sunucu sayını artırmaq.
- **Scale out** web servers.

### Auto-scaling

Avtomatik miqyaslama.
- **Auto-scaling** group.

### Elastic

Ehtiyaca görə dəyişən.
- **Elastic** compute.

### Capacity planning

Güc planlaması.
- **Capacity planning** for Black Friday.

---

## 10. Databases

### Replica

Kopyalama (oxu üçün).
- Read **replica**.

### Master / Primary

Əsas baza.
- **Primary** DB handles writes.

### Sharding

Böyütmə (data bölmək).
- **Shard** the DB by user_id.

### Backup

Yedək.
- Daily **backups**.

### Restore

Bərpa.
- **Restore** from backup.

### Migration

Schema dəyişikliyi.
- Run a **migration**.

### Seed

İlkin data.
- **Seed** data.

---

## 11. Cloud Providers

### AWS (Amazon)

- EC2 = compute
- S3 = storage
- RDS = DB
- Lambda = serverless
- CloudFront = CDN
- IAM = access

### GCP (Google)

- Compute Engine
- GKE = K8s
- BigQuery
- Cloud Functions

### Azure (Microsoft)

- VMs
- AKS = K8s
- Blob Storage
- Functions

---

## 12. Containers / K8s

### Docker

Container platforması.
- **Dockerize** the app.

### Dockerfile

Container tanıtmaq.
- Edit the **Dockerfile**.

### Image

Container şablonu.
- Docker **image**.

### Container

İşləyən instance.
- Run the **container**.

### Kubernetes (K8s)

Container orchestration.
- Deploy to **K8s**.

### Pod

K8s-də ən kiçik unit.

### Deployment

K8s resource.

### Service

K8s networking.

### Namespace

İzolyasiya.
- Dev, prod **namespaces**.

### Helm

K8s package manager.
- **Helm** chart.

---

## 13. IaC (Infrastructure as Code)

### Terraform

IaC tool.
- Manage infra with **Terraform**.

### CloudFormation

AWS-native IaC.

### Ansible

Config management.
- **Ansible** playbook.

### IaC

Infrastructure as Code.
- Write **IaC** for reproducibility.

---

## 14. Security

### Secret / Credential

Parol, key.
- Don't commit **secrets**.

### Vault

Secret storage.
- HashiCorp **Vault**.

### RBAC (Role-Based Access Control)

Rol əsaslı giriş.

### Least privilege

Minimum icazə.
- Follow **least privilege**.

### mTLS

Mutual TLS.
- **mTLS** between services.

### Penetration test / Pen test

Təhlükəsizlik testi.

---

## 15. Version Control

### Git

Source control.

### Repo (Repository)

Kod anbarı.
- Clone the **repo**.

### Branch

Qol.
- Feature **branch**.

### Merge / Rebase

Qol birləşdirmək.

### Tag

Version etiketi.
- **Tag** the release.

---

## Interview Kontekstində Top 30

1. **Uptime** / **downtime**
2. **HA** (High Availability)
3. **SLA** / **SLO** / **SLI**
4. **Load balancer**
5. **CDN**
6. **Auto-scaling**
7. **Blue-green deployment**
8. **Canary rollout**
9. **Feature flag**
10. **CI/CD pipeline**
11. **Rollback**
12. **Hotfix**
13. **Incident**
14. **Post-mortem**
15. **Root cause analysis**
16. **MTTR**
17. **Scale up / out**
18. **Replica**
19. **Sharding**
20. **Kubernetes / K8s**
21. **Docker container**
22. **Observability**
23. **Metrics / Logs / Traces**
24. **Alerts / Dashboard**
25. **IaC / Terraform**
26. **RBAC**
27. **Secret management**
28. **Backup / Restore**
29. **Disaster recovery**
30. **SPOF** (Single Point of Failure)

---

## Interview Nümunələri

- "We target **99.9% uptime** with a **multi-region** setup." ✓
- "Our **SLO** is 100ms p99 latency." ✓
- "We use **canary deployments** to minimize risk." ✓
- "I led the **incident response** and wrote the **post-mortem**." ✓
- "We **scale out** with K8s auto-scaling." ✓
- "No **SPOFs** — full redundancy." ✓

---

## Common Acronyms Cheat Sheet

- **CI/CD**: Continuous Integration / Delivery
- **HA**: High Availability
- **DR**: Disaster Recovery
- **SLA**: Service Level Agreement
- **SLO**: Service Level Objective
- **SLI**: Service Level Indicator
- **SPOF**: Single Point of Failure
- **MTTR**: Mean Time To Recover
- **RTO**: Recovery Time Objective
- **RPO**: Recovery Point Objective
- **RCA**: Root Cause Analysis
- **IaC**: Infrastructure as Code
- **VPC**: Virtual Private Cloud
- **CDN**: Content Delivery Network
- **LB**: Load Balancer
- **K8s**: Kubernetes
- **GKE/EKS/AKS**: Google/Elastic/Azure K8s Service
- **APM**: Application Performance Monitoring
- **RBAC**: Role-Based Access Control

---

## Azərbaycanlı Səhvləri

- ✗ "We have 99% uptime" (demək 3.5 gün/il downtime — pis)
- ✓ "99.9% uptime" or "three nines"

- ✗ "Database failed" (generic)
- ✓ "Primary DB **failover** to replica" (specific)

---

## Xatırlatma

**DevOps interview-də top 5:**
1. **Uptime / SLA / SLO**
2. **CI/CD pipeline**
3. **K8s / containers**
4. **Monitoring / observability**
5. **Incident management / post-mortem**

→ Related: [tech-idioms.md](../idioms/tech-idioms.md), [code-review-vocabulary.md](code-review-vocabulary.md), [deploy-ship-release-rollout.md](../../grammar/comparisons/deploy-ship-release-rollout.md)
