# Helm Chart Consumer Guide (Backend Dev üçün)

> **Səviyyə (Level):** ⭐⭐⭐ Senior
> **Oxu müddəti:** ~20-25 dəqiqə
> **Kateqoriya:** Kubernetes / Helm

## Nədir? (What is it?)

Helm — Kubernetes üçün paket menecer-dir. Bu fayl Helm chart **istifadəçisi (consumer)** üçündür — yəni backend developer-sən və team-in DevOps-u tərəfindən yazılmış chart-ı işlədirsən, və ya Bitnami kimi public chart-dan asılısan. Chart **author-u** olmaq ayrı mövzudur.

Backend dev üçün tipik ssenariləri:
- "PostgreSQL-i dev cluster-da qaldır" → `helm install pg bitnami/postgresql`
- "Redis-i prod-a deploy etmək lazımdır" → DevOps chart verir, sən `values.prod.yaml`-ı yeniləyirsən
- "Öz Laravel app-ına minimum chart yaz" → `helm create laravel`
- "Staging-də Kafka lazımdır" → `helm install kafka bitnami/kafka -f values.staging.yaml`

Chart-ın içinə çox dərinlemə girməyə ehtiyac yoxdur — əsas odur ki, values-ları düzgün verə biləsən və debug edə biləsən.

## Əsas Konseptlər

### 1. Chart Anatomiyası (Consumer baxışı)

```
postgresql/
├── Chart.yaml           ← metadata (ad, versiya, dependencies)
├── values.yaml          ← DEFAULT dəyərlər (senin override edəcəklərin)
├── values.schema.json   ← (optional) values-un strukturunun validasiyası
├── templates/           ← Go template-lər (backend dev nadirən toxunur)
│   ├── deployment.yaml
│   ├── service.yaml
│   ├── configmap.yaml
│   └── _helpers.tpl
├── charts/              ← sub-chart-lar (dependencies)
└── README.md            ← İSTİFADƏ təlimatları — həmişə oxu!
```

Backend dev kimi **əsas diqqət edəcəyin fayl: `values.yaml`**. Bu faylı dəyişdirərək (və ya öz `values.prod.yaml`-ını yazaraq) deployment-i konfiqurə edirsən.

### 2. Chart, Release, Repository — Terminologiya

- **Chart** — paket özü (template-lər + default values). Versiyası var (`1.2.3`).
- **Release** — cluster-də chart-ın instance-ı. Eyni chart-dan bir neçə release ola bilər (`pg-prod`, `pg-staging`).
- **Repository** — chart-ların saxlandığı HTTP server (məsələn Bitnami, Artifact Hub). Docker Hub-ın ekvivalentidir.
- **Values** — chart konfiqurasiyası (sənin override-ların).

### 3. Repository İdarəsi

```bash
# Repo əlavə etmək
helm repo add bitnami https://charts.bitnami.com/bitnami
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx

# Repo-ları siyahılamaq
helm repo list

# Repo yeniləmək (yeni chart versiyalarını çəkmək)
helm repo update

# Axtarmaq
helm search repo postgres
helm search repo bitnami/redis --versions  # bütün versiyalar

# Hub-da axtarmaq (artifacthub.io)
helm search hub wordpress
```

### 4. Install, Upgrade, Rollback

```bash
# Minimum install
helm install pg bitnami/postgresql -n db --create-namespace

# Values ilə
helm install pg bitnami/postgresql \
  -n db \
  --create-namespace \
  --set auth.postgresPassword=secret123 \
  --set auth.database=laravel \
  --set primary.persistence.size=20Gi

# Values file ilə (tövsiyə olunan)
helm install pg bitnami/postgresql \
  -n db \
  -f values.prod.yaml

# İdempotent install + upgrade (CI/CD-də istifadə et)
helm upgrade --install pg bitnami/postgresql \
  -n db --create-namespace \
  -f values.prod.yaml \
  --version 15.5.12 \
  --wait \
  --timeout 5m

# Upgrade
helm upgrade pg bitnami/postgresql \
  -n db \
  -f values.prod.yaml \
  --version 15.6.0

# Rollback
helm history pg -n db
# REVISION  STATUS      CHART              APP VERSION  DESCRIPTION
# 1         superseded  postgresql-15.5.12 16.2.0       Install complete
# 2         deployed    postgresql-15.6.0  16.3.0       Upgrade complete

helm rollback pg 1 -n db  # revision 1-ə qayıt

# Uninstall
helm uninstall pg -n db
```

**Vacib:** `helm upgrade --install` (bəzən `helm upgrade -i` yazılır) — idempotent komandadır. CI/CD pipeline-da həmişə bunu işlət, `helm install`-ı yox. İlk dəfə install edir, sonrakılarda upgrade edir.

### 5. Values Faylları və Override Qaydası

Helm values-ları bir neçə mənbədən birləşdirir. Prioritet sırası (yuxarı daha yüksək):

```
1. CLI --set (ən yüksək prioritet)
2. -f values-prod.yaml (son -f ən yüksək)
3. -f values-staging.yaml
4. -f values.yaml
5. chart-ın default values.yaml-ı (ən aşağı prioritet)
```

Nümunə:

```bash
helm install pg bitnami/postgresql \
  -f values.common.yaml \     # ümumi dəyərlər
  -f values.prod.yaml \       # prod-specifik (common-i override edir)
  --set auth.postgresPassword=$SECRET  # runtime secret (hər ikisini override)
```

**Nümunə values.common.yaml:**

```yaml
image:
  pullPolicy: IfNotPresent
metrics:
  enabled: true
primary:
  persistence:
    enabled: true
    size: 10Gi
```

**Nümunə values.prod.yaml:**

```yaml
primary:
  persistence:
    size: 100Gi               # common-dəki 10Gi-ni override edir
    storageClass: gp3
  resources:
    requests:
      cpu: 500m
      memory: 2Gi
    limits:
      memory: 4Gi
replication:
  enabled: true
  readReplicas: 2
```

### 6. Chart-ın Dəyərlərini Tapmaq

Chart-da hansı values mövcud olduğunu bilmirsən?

```bash
# Default values.yaml-ı gör
helm show values bitnami/postgresql > default-values.yaml

# Chart metadata-sı
helm show chart bitnami/postgresql

# README (istifadə sənədi)
helm show readme bitnami/postgresql

# Hər şey
helm show all bitnami/postgresql
```

### 7. Debug və İnspeksiya

Ən faydalı komandalar:

```bash
# Template-i render et (apply etmədən, YAML çıxışını gör)
helm template pg bitnami/postgresql -f values.prod.yaml > rendered.yaml
cat rendered.yaml  # nə yaradılacaq?

# Dry-run ilə install
helm install pg bitnami/postgresql \
  -f values.prod.yaml \
  --dry-run --debug

# Release-in values-larını gör (cluster-dən)
helm get values pg -n db

# Bütün values (default + override)
helm get values pg -n db --all

# Release-in template-lərini gör
helm get manifest pg -n db

# Release-in notları (install vaxtı çap edilən mətn)
helm get notes pg -n db

# Status
helm status pg -n db
```

**`helm diff` plugin** (çox faydalı):

```bash
helm plugin install https://github.com/databus23/helm-diff

# Upgrade-in nə dəyişəcəyini gör (apply etmədən)
helm diff upgrade pg bitnami/postgresql -f values.prod.yaml
```

### 8. Version Pinning (Vacib!)

```bash
# PİS: versiya yoxdur → "latest" çəkilir, gələcəkdə sınıq deployment
helm install pg bitnami/postgresql

# YAXŞI: versiya pin olunub
helm install pg bitnami/postgresql --version 15.5.12
```

`--version` chart versiyasıdır (app versiyası yox). Niyə vacibdir:
- Team-in hamısı eyni nəticə alsın
- CI/CD reproducible olsun
- Chart author-u breaking change edəndə xəbərin olsun

**Ən yaxşı praktika:** CI/CD-də `--version X.Y.Z` həmişə açıq yaz. Dependabot / Renovate ilə chart versiyalarını avtomatik yenilə.

### 9. Subchart-lar və Dependency-lər

Öz chart-ın başqa chart-a asılıdırsa:

```yaml
# Chart.yaml
dependencies:
  - name: postgresql
    version: "15.5.12"
    repository: "https://charts.bitnami.com/bitnami"
    condition: postgresql.enabled
  - name: redis
    version: "19.5.2"
    repository: "https://charts.bitnami.com/bitnami"
```

```bash
# Dependency-ləri yüklə (Chart.lock-u yaradır)
helm dependency update

# Build (Chart.lock-dan)
helm dependency build
```

**Subchart values necə ötürülür:**

```yaml
# values.yaml
postgresql:                   # subchart adı
  auth:
    postgresPassword: secret  # postgresql-in öz values-una ötürülür
  primary:
    persistence:
      size: 20Gi

redis:                        # başqa subchart
  auth:
    enabled: false
```

**Gotcha:** Subchart values-larını root-da vermirsən — subchart adının altında yuvalamalısan. Çox gənc dev bunu səhv edir.

### 10. Sənin Laravel App-ın üçün Minimum Chart

```bash
# Scaffold yarat
helm create laravel

# Yaradılan strukturu sadələşdir: bir çox artıq template var
cd laravel
ls templates/
# NOTES.txt deployment.yaml _helpers.tpl hpa.yaml ingress.yaml service.yaml serviceaccount.yaml tests/
```

**Minimum template-lər:** `deployment.yaml`, `service.yaml`, `configmap.yaml`, `ingress.yaml`, `_helpers.tpl`.

**values.yaml (sadələşdirilmiş):**

```yaml
replicaCount: 2

image:
  repository: mycompany/laravel
  tag: "1.0.0"
  pullPolicy: IfNotPresent

service:
  type: ClusterIP
  port: 80

ingress:
  enabled: true
  className: nginx
  hosts:
    - host: api.example.com
      paths:
        - path: /
          pathType: Prefix

resources:
  requests:
    cpu: 250m
    memory: 256Mi
  limits:
    memory: 512Mi

env:
  APP_ENV: production
  DB_HOST: pg-postgresql
  REDIS_HOST: redis-master

# Secret-lər ayrıca (plain text-də yox!)
secretRefs:
  - laravel-secret
```

**templates/deployment.yaml:**

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: {{ include "laravel.fullname" . }}
  labels:
    {{- include "laravel.labels" . | nindent 4 }}
spec:
  replicas: {{ .Values.replicaCount }}
  selector:
    matchLabels:
      {{- include "laravel.selectorLabels" . | nindent 6 }}
  template:
    metadata:
      labels:
        {{- include "laravel.selectorLabels" . | nindent 8 }}
    spec:
      containers:
        - name: app
          image: "{{ .Values.image.repository }}:{{ .Values.image.tag }}"
          imagePullPolicy: {{ .Values.image.pullPolicy }}
          ports:
            - containerPort: 80
          env:
            {{- range $key, $value := .Values.env }}
            - name: {{ $key }}
              value: {{ $value | quote }}
            {{- end }}
          envFrom:
            {{- range .Values.secretRefs }}
            - secretRef:
                name: {{ . }}
            {{- end }}
          resources:
            {{- toYaml .Values.resources | nindent 12 }}
```

### 11. Helm Test

Chart install-dan sonra smoke test çalışdır:

```yaml
# templates/tests/test-connection.yaml
apiVersion: v1
kind: Pod
metadata:
  name: "{{ include "laravel.fullname" . }}-test-connection"
  annotations:
    "helm.sh/hook": test
spec:
  containers:
    - name: curl
      image: curlimages/curl:latest
      command:
        - curl
        - "-f"
        - "http://{{ include "laravel.fullname" . }}:{{ .Values.service.port }}/up"
  restartPolicy: Never
```

```bash
helm test laravel -n production
```

### 12. Secret Management — VACİB

`values.yaml`-da plain password saxlamaq pis ideyadır (git-ə düşür, log-lara düşür).

**Variantlar:**

1. **External Secrets Operator** — AWS Secrets Manager, Vault, GCP Secret Manager-dən çəkir
2. **Sealed Secrets** — Bitnami-nin SealedSecret CRD-si (git-ə qoymaq üçün encrypted)
3. **SOPS + helm-secrets plugin** — values-u encrypt edir
4. **`--set` ilə runtime-da ötür** — CI/CD-dən environment variable kimi

```bash
# CI/CD-də
helm upgrade --install pg bitnami/postgresql \
  -f values.prod.yaml \
  --set auth.postgresPassword=$POSTGRES_PASSWORD \
  -n db
```

### 13. App-of-Apps Pattern (Argo CD ilə)

Bir neçə Helm release-i bir yerdə idarə etmək üçün GitOps pattern. Qısaca: bir Argo Application, digər Application-ları deploy edir. Backend dev-in bunu bilməsi kifayətdir — DevOps təfsilatlarını idarə edir.

## Best Practices

1. **Həmişə `helm upgrade --install`** — idempotent
2. **Həmişə `--version X.Y.Z`** — pin et
3. **`values.prod.yaml` git-də saxla** — amma secret-lər plain yox
4. **`helm diff upgrade`** prod-a apply etməzdən əvvəl
5. **`helm template`** ilə CI-də lint et
6. **`helm.sh/hook: pre-install` / `post-upgrade`** — migration-lar üçün
7. **Values.schema.json** — chart author-usansa, values-ı validate et
8. **README yaz** — chart-ın necə istifadə olunacağı
9. **Chart versiyalarını Renovate/Dependabot ilə yenilə**
10. **Namespace-i chart-da hardcode etmə** — consumer qərar versin

## Tələlər (Gotchas)

### 1. `helm install` dublikat error verir
Release artıq mövcuddur. Yerinə `helm upgrade --install` işlət.

### 2. Subchart values ötürülmür
Root values-da `postgresql.auth.password` yazmağı unutdun. Root-dakı `auth.password` subchart-a çatmır.

### 3. `helm upgrade` restart etmir (image dəyişməyib)
Manifest həqiqətən dəyişməyibsə, K8s restart etmir. ConfigMap dəyişibsə, annotation ilə checksum qoy:
```yaml
annotations:
  checksum/config: {{ include (print $.Template.BasePath "/configmap.yaml") . | sha256sum }}
```

### 4. Default values user-in override-ını üstələyir
Helm-in "missing field" davranışı: `values.yaml`-da field yoxdursa, default işlədilir. Amma `null` verirsə, null olur. Diqqətli ol.

### 5. `helm uninstall` PVC-ni silmir
`StatefulSet`-lə yaradılan PVC-lər `helm uninstall`-dan sonra da qalır. Əl ilə silmək lazım gəlir.

### 6. `values.yaml`-da secret plain text
`.gitignore`-a əlavə et. `values.example.yaml` qoy şablon kimi.

### 7. Chart versiyası vs app versiyası
Chart.yaml-da `version` chart-ın özünün versiyasıdır, `appVersion` içindəki app-ın. Qarışdırma.

### 8. `--wait` timeout
`helm upgrade --install --wait --timeout 5m` bəzən 5 dəqiqədə bitmir (build slow, probe yavaş). `--timeout 10m` qoy və probe-ları tune et.

## Müsahibə Sualları

### S1: Helm nədir və niyə K8s-a manual YAML-dan yaxşıdır?
**C:** Helm K8s üçün paket menecer-dir. Templating ilə bir deployment-i bir neçə environment-də (dev/staging/prod) eyni template + fərqli values ilə deploy etməyə imkan verir. Versiya tarixçəsi, rollback, dependency management təklif edir. Raw YAML çox təkrarlanır və idempotent deyil.

### S2: Release və Chart arasında fərq nədir?
**C:** **Chart** paketdir — template + default values (Docker image-ə bənzər). **Release** cluster-də chart-ın instance-ıdır (Docker container-ə bənzər). Eyni chart-dan `pg-prod`, `pg-staging` adı ilə iki release olar.

### S3: `helm install` və `helm upgrade --install` arasında fərq nədir?
**C:** `helm install` release artıq mövcuddursa xəta verir. `helm upgrade --install` idempotent-dir — release yoxdursa install edir, varsa upgrade edir. CI/CD-də həmişə ikincini işlət.

### S4: Values override sırası necədir?
**C:** Prioritet (yüksəkdən aşağı): CLI `--set` → son `-f file.yaml` → əvvəlki `-f file.yaml` → chart-ın default `values.yaml`-ı. Yəni `--set` hər şeyi override edir. Bu, runtime secret-ləri ötürmək üçün istifadə olunur.

### S5: Helm rollback necə işləyir?
**C:** Helm hər release-in revision tarixçəsini saxlayır (default son 10). `helm history release-name` revision-ları göstərir, `helm rollback release-name 3` release-i 3-cü revision-a qaytarır. Rollback yeni revision yaradır (immutable tarixçə).

### S6: Secret-ləri chart-da necə düzgün idarə edirsən?
**C:** Plain text values.yaml-da SAXLAMA. Variantlar: 1) External Secrets Operator (Vault/AWS SM-dən çəkir), 2) Sealed Secrets (git-ə qoymaq üçün encrypt), 3) helm-secrets + SOPS, 4) CI/CD-də `--set auth.password=$SECRET` ilə runtime-da ötür.

### S7: `helm diff` nə üçündür?
**C:** Üçüncü tərəf plugin. `helm diff upgrade release chart -f values.yaml` işlədəndə apply etmədən göstərir ki, cluster-də nə dəyişəcək — hansı field-lər update, hansı resurslar əlavə/silinəcək. Prod-a apply etməzdən əvvəl mütləq işlət.

### S8: Chart versiyası və app versiyası arasında fərq nədir?
**C:** `Chart.yaml`-da `version` chart-ın özünün semantic versiyasıdır (template-lər dəyişəndə artır). `appVersion` chart-ın içindəki tətbiqin versiyasıdır (məs: PostgreSQL 16.2.0). Bir-birindən asılı deyil — chart versiyası breaking change-də major artır, appVersion isə underlying image tag.
