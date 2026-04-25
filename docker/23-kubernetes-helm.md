# Kubernetes Helm

> **Səviyyə (Level):** ⭐⭐ Middle

## Nədir? (What is it?)

Helm — Kubernetes üçün paket menecer-dir. Linux-da apt/yum, Node.js-da npm nədirsə, Kubernetes üçün Helm odur. Helm "chart" adlanan paketlər vasitəsilə K8s tətbiqlərini quraşdırmağı, yeniləməyi və idarə etməyi asanlaşdırır.

Kubernetes YAML faylları böyük və təkrarlanan olur. Helm templating ilə bu problemi həll edir — dəyişənlər, şərtlər, dövrələr istifadə edərək dinamik YAML yaratmaq mümkündür.

### Helm olmadan vs Helm ilə

```
Helm olmadan:                    Helm ilə:
├── deployment.yaml              helm install myapp ./chart \
├── service.yaml                     --set replicas=3 \
├── configmap.yaml                   --set image.tag=1.0.0
├── secret.yaml
├── ingress.yaml
├── hpa.yaml
├── pvc.yaml
└── ... (hər mühit üçün ayrı)
```

## Əsas Konseptlər

### 1. Helm Komponentləri

```
┌──────────────────────────────────────────────────┐
│ Helm Komponentləri                                │
├──────────────┬───────────────────────────────────┤
│ Chart        │ K8s resource-lar paketi            │
│ Repository   │ Chart-ların saxlandığı yer          │
│ Release      │ Chart-ın cluster-dəki instance-ı    │
│ Values       │ Chart konfiqurasiyası (values.yaml) │
│ Template     │ Go template ilə K8s YAML-ları       │
└──────────────┴───────────────────────────────────┘
```

### 2. Helm Əmrləri

```bash
# Helm versiyası
helm version

# Repository əlavə etmək
helm repo add bitnami https://charts.bitnami.com/bitnami
helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
helm repo update

# Chart axtarış
helm search repo nginx
helm search hub wordpress    # Artifact Hub-da axtarış

# Chart məlumatı
helm show chart bitnami/mysql
helm show values bitnami/mysql    # Default values.yaml

# Quraşdırma
helm install my-mysql bitnami/mysql \
    --namespace database \
    --create-namespace \
    --set auth.rootPassword=secret \
    --set auth.database=laravel

# Custom values faylı ilə
helm install my-mysql bitnami/mysql \
    -f custom-values.yaml \
    -n database

# Release-ləri görmək
helm list
helm list -n database
helm list --all-namespaces

# Yeniləmə
helm upgrade my-mysql bitnami/mysql \
    --set auth.rootPassword=newsecret \
    -n database

# Install + upgrade (idempotent)
helm upgrade --install my-mysql bitnami/mysql \
    -f values.yaml -n database

# Rollback
helm rollback my-mysql 1 -n database    # Revision 1-ə

# Tarixçə
helm history my-mysql -n database

# Silmə
helm uninstall my-mysql -n database

# Dry-run (nə yaradılacağını görmək)
helm install my-mysql bitnami/mysql \
    --dry-run --debug \
    -f values.yaml

# Template render (YAML çıxışı)
helm template my-mysql bitnami/mysql -f values.yaml
```

### 3. Chart Strukturu

```
mychart/
├── Chart.yaml           # Chart metadata (ad, versiya, təsvir)
├── Chart.lock           # Dependency lock file
├── values.yaml          # Default konfiqurasiya dəyərləri
├── values.schema.json   # Values validation schema (optional)
├── .helmignore          # Paketləmədən çıxarılan fayllar
├── templates/           # K8s YAML template-ləri
│   ├── _helpers.tpl     # Template helper funksiyaları
│   ├── deployment.yaml
│   ├── service.yaml
│   ├── configmap.yaml
│   ├── secret.yaml
│   ├── ingress.yaml
│   ├── hpa.yaml
│   ├── serviceaccount.yaml
│   ├── NOTES.txt        # Quraşdırmadan sonra göstərilən mesaj
│   └── tests/
│       └── test-connection.yaml
└── charts/              # Dependency chart-lar
    └── mysql/
```

### 4. Chart.yaml

```yaml
# Chart.yaml
apiVersion: v2
name: laravel-app
description: A Helm chart for Laravel application
type: application
version: 1.0.0              # Chart versiyası
appVersion: "1.2.0"         # Tətbiq versiyası
maintainers:
  - name: DevOps Team
    email: devops@example.com
dependencies:
  - name: mysql
    version: "9.x.x"
    repository: "https://charts.bitnami.com/bitnami"
    condition: mysql.enabled
  - name: redis
    version: "18.x.x"
    repository: "https://charts.bitnami.com/bitnami"
    condition: redis.enabled
```

### 5. values.yaml

```yaml
# values.yaml — default dəyərlər
replicaCount: 3

image:
  repository: mycompany/laravel
  tag: "1.0.0"
  pullPolicy: IfNotPresent

imagePullSecrets:
  - name: regcred

nameOverride: ""
fullnameOverride: ""

serviceAccount:
  create: true
  name: ""

service:
  type: ClusterIP
  port: 80

ingress:
  enabled: true
  className: nginx
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod
  hosts:
    - host: app.example.com
      paths:
        - path: /
          pathType: Prefix
  tls:
    - secretName: app-tls
      hosts:
        - app.example.com

resources:
  requests:
    cpu: 250m
    memory: 256Mi
  limits:
    cpu: 1000m
    memory: 512Mi

autoscaling:
  enabled: true
  minReplicas: 3
  maxReplicas: 20
  targetCPUUtilizationPercentage: 70

# Laravel config
laravel:
  env: production
  debug: false
  appKey: ""              # --set ilə veriləcək
  dbHost: mysql-service
  dbPort: "3306"
  dbDatabase: laravel
  dbUsername: laravel
  dbPassword: ""          # --set ilə veriləcək
  cacheDriver: redis
  sessionDriver: redis
  queueConnection: redis
  redisHost: redis-service

# Queue worker
queueWorker:
  enabled: true
  replicas: 2
  command: ["php", "artisan", "queue:work", "--tries=3"]

# Scheduler
scheduler:
  enabled: true

# Dependencies
mysql:
  enabled: true
  auth:
    rootPassword: ""
    database: laravel
    username: laravel
    password: ""

redis:
  enabled: true
  auth:
    enabled: false

# Probes
livenessProbe:
  httpGet:
    path: /health
    port: http
  initialDelaySeconds: 30
  periodSeconds: 10

readinessProbe:
  httpGet:
    path: /ready
    port: http
  initialDelaySeconds: 5
  periodSeconds: 5
```

### 6. Templates

#### _helpers.tpl

```yaml
{{/* templates/_helpers.tpl */}}

{{/* Chart adı */}}
{{- define "laravel.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/* Tam ad */}}
{{- define "laravel.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}

{{/* Labels */}}
{{- define "laravel.labels" -}}
helm.sh/chart: {{ .Chart.Name }}-{{ .Chart.Version }}
app.kubernetes.io/name: {{ include "laravel.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/* Selector labels */}}
{{- define "laravel.selectorLabels" -}}
app.kubernetes.io/name: {{ include "laravel.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}
```

#### deployment.yaml

```yaml
{{/* templates/deployment.yaml */}}
apiVersion: apps/v1
kind: Deployment
metadata:
  name: {{ include "laravel.fullname" . }}
  labels:
    {{- include "laravel.labels" . | nindent 4 }}
spec:
  {{- if not .Values.autoscaling.enabled }}
  replicas: {{ .Values.replicaCount }}
  {{- end }}
  selector:
    matchLabels:
      {{- include "laravel.selectorLabels" . | nindent 6 }}
  template:
    metadata:
      annotations:
        checksum/config: {{ include (print $.Template.BasePath "/configmap.yaml") . | sha256sum }}
        checksum/secret: {{ include (print $.Template.BasePath "/secret.yaml") . | sha256sum }}
      labels:
        {{- include "laravel.selectorLabels" . | nindent 8 }}
    spec:
      {{- with .Values.imagePullSecrets }}
      imagePullSecrets:
        {{- toYaml . | nindent 8 }}
      {{- end }}
      initContainers:
        - name: migrate
          image: "{{ .Values.image.repository }}:{{ .Values.image.tag }}"
          command: ["php", "artisan", "migrate", "--force"]
          envFrom:
            - configMapRef:
                name: {{ include "laravel.fullname" . }}-config
            - secretRef:
                name: {{ include "laravel.fullname" . }}-secret
      containers:
        - name: php-fpm
          image: "{{ .Values.image.repository }}:{{ .Values.image.tag }}"
          imagePullPolicy: {{ .Values.image.pullPolicy }}
          ports:
            - name: http
              containerPort: 9000
              protocol: TCP
          envFrom:
            - configMapRef:
                name: {{ include "laravel.fullname" . }}-config
            - secretRef:
                name: {{ include "laravel.fullname" . }}-secret
          {{- with .Values.livenessProbe }}
          livenessProbe:
            {{- toYaml . | nindent 12 }}
          {{- end }}
          {{- with .Values.readinessProbe }}
          readinessProbe:
            {{- toYaml . | nindent 12 }}
          {{- end }}
          resources:
            {{- toYaml .Values.resources | nindent 12 }}
```

#### configmap.yaml

```yaml
{{/* templates/configmap.yaml */}}
apiVersion: v1
kind: ConfigMap
metadata:
  name: {{ include "laravel.fullname" . }}-config
  labels:
    {{- include "laravel.labels" . | nindent 4 }}
data:
  APP_NAME: {{ .Values.laravel.env | quote }}
  APP_ENV: {{ .Values.laravel.env | quote }}
  APP_DEBUG: {{ .Values.laravel.debug | quote }}
  DB_CONNECTION: "mysql"
  DB_HOST: {{ .Values.laravel.dbHost | quote }}
  DB_PORT: {{ .Values.laravel.dbPort | quote }}
  DB_DATABASE: {{ .Values.laravel.dbDatabase | quote }}
  CACHE_DRIVER: {{ .Values.laravel.cacheDriver | quote }}
  SESSION_DRIVER: {{ .Values.laravel.sessionDriver | quote }}
  QUEUE_CONNECTION: {{ .Values.laravel.queueConnection | quote }}
  REDIS_HOST: {{ .Values.laravel.redisHost | quote }}
  LOG_CHANNEL: "stderr"
```

#### secret.yaml

```yaml
{{/* templates/secret.yaml */}}
apiVersion: v1
kind: Secret
metadata:
  name: {{ include "laravel.fullname" . }}-secret
  labels:
    {{- include "laravel.labels" . | nindent 4 }}
type: Opaque
data:
  APP_KEY: {{ .Values.laravel.appKey | b64enc | quote }}
  DB_USERNAME: {{ .Values.laravel.dbUsername | b64enc | quote }}
  DB_PASSWORD: {{ .Values.laravel.dbPassword | b64enc | quote }}
```

#### service.yaml

```yaml
{{/* templates/service.yaml */}}
apiVersion: v1
kind: Service
metadata:
  name: {{ include "laravel.fullname" . }}
  labels:
    {{- include "laravel.labels" . | nindent 4 }}
spec:
  type: {{ .Values.service.type }}
  ports:
    - port: {{ .Values.service.port }}
      targetPort: http
      protocol: TCP
      name: http
  selector:
    {{- include "laravel.selectorLabels" . | nindent 4 }}
```

#### queue-worker.yaml (conditional)

```yaml
{{/* templates/queue-worker.yaml */}}
{{- if .Values.queueWorker.enabled }}
apiVersion: apps/v1
kind: Deployment
metadata:
  name: {{ include "laravel.fullname" . }}-queue
  labels:
    {{- include "laravel.labels" . | nindent 4 }}
    component: queue-worker
spec:
  replicas: {{ .Values.queueWorker.replicas }}
  selector:
    matchLabels:
      {{- include "laravel.selectorLabels" . | nindent 6 }}
      component: queue-worker
  template:
    metadata:
      labels:
        {{- include "laravel.selectorLabels" . | nindent 8 }}
        component: queue-worker
    spec:
      containers:
        - name: queue-worker
          image: "{{ .Values.image.repository }}:{{ .Values.image.tag }}"
          command:
            {{- toYaml .Values.queueWorker.command | nindent 12 }}
          envFrom:
            - configMapRef:
                name: {{ include "laravel.fullname" . }}-config
            - secretRef:
                name: {{ include "laravel.fullname" . }}-secret
          resources:
            requests:
              cpu: 100m
              memory: 128Mi
            limits:
              cpu: 500m
              memory: 256Mi
{{- end }}
```

## Praktiki Nümunələr

### Chart Yaratma və İstifadə

```bash
# Yeni chart yaratmaq (scaffold)
helm create laravel-app

# Dependency-ləri yükləmək
cd laravel-app
helm dependency update

# Lint (validation)
helm lint .

# Template render (debug)
helm template myrelease . -f values.yaml

# Dry-run install
helm install myrelease . --dry-run --debug -f values.yaml

# Quraşdırma
helm install laravel-prod . \
    -f values-production.yaml \
    --set laravel.appKey="base64:key-here" \
    --set laravel.dbPassword="secret" \
    --set mysql.auth.rootPassword="rootsecret" \
    -n production --create-namespace

# Yeniləmə
helm upgrade laravel-prod . \
    -f values-production.yaml \
    --set image.tag="1.1.0" \
    -n production
```

### Mühitlər üçün Fərqli Values

```yaml
# values-staging.yaml
replicaCount: 1

image:
  tag: "latest"

ingress:
  hosts:
    - host: staging.example.com
      paths:
        - path: /
          pathType: Prefix

laravel:
  env: staging
  debug: true

autoscaling:
  enabled: false

mysql:
  enabled: true
  auth:
    rootPassword: staging-root
    password: staging-pass

---
# values-production.yaml
replicaCount: 5

image:
  tag: "1.2.0"

ingress:
  hosts:
    - host: app.example.com
      paths:
        - path: /
          pathType: Prefix

laravel:
  env: production
  debug: false

autoscaling:
  enabled: true
  minReplicas: 5
  maxReplicas: 30

mysql:
  enabled: false    # Managed RDS istifadə olunur

redis:
  enabled: false    # ElastiCache istifadə olunur
```

```bash
# Staging deploy
helm upgrade --install laravel-staging ./laravel-app \
    -f values-staging.yaml -n staging --create-namespace

# Production deploy
helm upgrade --install laravel-prod ./laravel-app \
    -f values-production.yaml \
    --set laravel.dbHost=prod-rds.xxx.rds.amazonaws.com \
    --set laravel.redisHost=prod-redis.xxx.cache.amazonaws.com \
    -n production --create-namespace
```

### Chart-ı Package və Publish Etmək

```bash
# Chart-ı paketləmək
helm package laravel-app/
# laravel-app-1.0.0.tgz

# GitHub Pages ilə repo
# Chart-ı gh-pages branch-ə push edin
helm repo index . --url https://mycompany.github.io/charts

# OCI registry-ə push (Helm 3.8+)
helm push laravel-app-1.0.0.tgz oci://ghcr.io/mycompany/charts
```

## PHP/Laravel ilə İstifadə

### Artisan Komandaları üçün Job Template

```yaml
{{/* templates/migrate-job.yaml */}}
{{- if .Values.migrations.enabled }}
apiVersion: batch/v1
kind: Job
metadata:
  name: {{ include "laravel.fullname" . }}-migrate-{{ .Release.Revision }}
  annotations:
    "helm.sh/hook": pre-upgrade,pre-install
    "helm.sh/hook-weight": "-5"
    "helm.sh/hook-delete-policy": before-hook-creation
spec:
  template:
    spec:
      containers:
        - name: migrate
          image: "{{ .Values.image.repository }}:{{ .Values.image.tag }}"
          command: ["php", "artisan", "migrate", "--force"]
          envFrom:
            - configMapRef:
                name: {{ include "laravel.fullname" . }}-config
            - secretRef:
                name: {{ include "laravel.fullname" . }}-secret
      restartPolicy: Never
  backoffLimit: 3
{{- end }}
```

### Laravel Scheduler CronJob

```yaml
{{/* templates/scheduler.yaml */}}
{{- if .Values.scheduler.enabled }}
apiVersion: batch/v1
kind: CronJob
metadata:
  name: {{ include "laravel.fullname" . }}-scheduler
spec:
  schedule: "* * * * *"
  concurrencyPolicy: Forbid
  jobTemplate:
    spec:
      template:
        spec:
          containers:
            - name: scheduler
              image: "{{ .Values.image.repository }}:{{ .Values.image.tag }}"
              command: ["php", "artisan", "schedule:run"]
              envFrom:
                - configMapRef:
                    name: {{ include "laravel.fullname" . }}-config
                - secretRef:
                    name: {{ include "laravel.fullname" . }}-secret
          restartPolicy: OnFailure
{{- end }}
```

## İntervyu Sualları

### S1: Helm nədir və niyə lazımdır?
**C:** Helm — K8s paket meneceridir. Bir neçə problemi həll edir: 1) Çoxlu YAML fayllarını bir paket (chart) olaraq idarə etmək, 2) Templating ilə mühitlər arası fərqli konfiqurasiya, 3) Versiyalama və rollback, 4) Dependency management (MySQL, Redis chart-ları), 5) Release lifecycle (install, upgrade, rollback, uninstall).

### S2: Chart, release və repository arasında fərq nədir?
**C:** Chart — K8s resource template-lərinin paketi (blueprint). Release — chart-ın cluster-dəki işləyən instance-ı (bir chart-dan çoxlu release yaratmaq olar). Repository — chart-ların saxlandığı yer (npm registry kimi). Məsələn: `bitnami/mysql` chart-ından `my-mysql` release yaradılır.

### S3: `helm upgrade --install` nə edir?
**C:** İdempotent əmrdir — release yoxdursa yaradır (install), varsa yeniləyir (upgrade). CI/CD pipeline-larda istifadə olunur çünki əvvəlcədən release-in olub-olmadığını yoxlamaq lazım deyil. `--atomic` flag ilə ugursuz upgrade avtomatik rollback olur.

### S4: Helm hook-lar nə üçün istifadə olunur?
**C:** Release lifecycle-ın müəyyən anlarında iş görmək üçün: `pre-install` (quraşdırmadan əvvəl), `post-install`, `pre-upgrade`, `post-upgrade`, `pre-delete`. Laravel-də: database migration pre-upgrade hook ilə çağırılır. `helm.sh/hook` annotation ilə təyin olunur.

### S5: values.yaml-ı necə override etmək olar?
**C:** Üç yol: 1) `-f custom-values.yaml` ilə fayl, 2) `--set key=value` ilə CLI, 3) `--set-file key=filepath` ilə fayl məzmunu. Prioritet sırası: `--set` > son `-f` fayl > əvvəlki `-f` fayl > default values.yaml.

### S6: Helm chart-ı necə debug edirsiniz?
**C:** `helm template` — render olunmuş YAML-ı görür. `helm install --dry-run --debug` — server-side validation ilə. `helm lint` — chart-da syntax xətaları. `helm get manifest <release>` — install olunmuş release-in manifest-i. `helm get values <release>` — istifadə olunan dəyərlər.

### S7: Helmfile nədir?
**C:** Bir neçə Helm release-i deklarativ olaraq idarə etmək üçün alətdir. `helmfile.yaml`-da bütün release-lər, repository-lər və dəyərlər təyin olunur. `helmfile sync` ilə bütün release-lər eyni anda deploy olunur. Böyük cluster-lərdə çoxlu chart-ları idarə etmək üçün istifadə olunur.

## Best Practices

1. **Chart versiyasını dəyişdirin** — hər dəyişiklikdə Chart.yaml version artırın
2. **values.schema.json yazın** — input validation üçün
3. **`helm upgrade --install --atomic` istifadə edin** — CI/CD-də
4. **Sensitive dəyərləri `--set` ilə verin** — values.yaml-da saxlamayın
5. **_helpers.tpl istifadə edin** — təkrarlanan template-lər üçün
6. **checksum annotation əlavə edin** — ConfigMap dəyişdikdə Pod restart
7. **NOTES.txt yazın** — istifadəçi üçün post-install təlimatlar
8. **Dependency-ləri Chart.lock ilə kilidləyin**
9. **Mühitlər üçün ayrı values faylları** — values-staging.yaml, values-production.yaml
10. **Chart-ı OCI registry-də saxlayın** — Helm 3.8+
