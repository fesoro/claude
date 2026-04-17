# Kubernetes Storage (Kubernetes-də Saxlama)

## Nədir? (What is it?)

Kubernetes Storage — konteynerlərdə data saxlanmasını idarə edən sistemdir. Konteynerlər ephemeral-dır (müvəqqəti) — restart olduqda data itirilir. PersistentVolume (PV) və PersistentVolumeClaim (PVC) abstraksiyaları ilə data konteynerin həyat dövrünündən müstəqil saxlanır.

Database, fayl upload-ları, session data kimi stateful tətbiqlər üçün persistent storage vacibdir.

## Əsas Konseptlər (Key Concepts)

### 1. Volume Tipləri

```
┌────────────────────────────────────────────────────────┐
│ Kubernetes Volume Tipləri                               │
├─────────────────┬──────────────────────────────────────┤
│ emptyDir        │ Pod ilə birlikdə yaranır/silinir.    │
│                 │ Pod daxilindəki container-lər arası   │
│                 │ data paylaşma üçün.                   │
├─────────────────┼──────────────────────────────────────┤
│ hostPath        │ Node-un fayl sistemindən mount.       │
│                 │ Test üçün. Produksiyada istifadə       │
│                 │ ETMƏYİN.                              │
├─────────────────┼──────────────────────────────────────┤
│ PersistentVolume│ Cluster-level storage resursu.        │
│ (PV)           │ Admin tərəfindən yaradılır.            │
├─────────────────┼──────────────────────────────────────┤
│ PersistentVolume│ PV-yə tələb. Developer tərəfindən     │
│ Claim (PVC)    │ yaradılır.                             │
├─────────────────┼──────────────────────────────────────┤
│ ConfigMap/Secret│ Konfiqurasiya fayllarını volume        │
│                 │ olaraq mount etmək.                    │
├─────────────────┼──────────────────────────────────────┤
│ Cloud Volumes   │ AWS EBS, GCP PD, Azure Disk           │
└─────────────────┴──────────────────────────────────────┘
```

### 2. emptyDir

```yaml
# Pod daxilindəki container-lər arası data paylaşma
apiVersion: v1
kind: Pod
metadata:
  name: laravel-pod
spec:
  containers:
    - name: php-fpm
      image: mycompany/laravel:1.0.0
      volumeMounts:
        - name: shared-files
          mountPath: /var/www/html/public
        - name: cache
          mountPath: /var/www/html/storage/framework/cache

    - name: nginx
      image: mycompany/nginx:1.0.0
      volumeMounts:
        - name: shared-files
          mountPath: /var/www/html/public

  volumes:
    - name: shared-files
      emptyDir: {}
    - name: cache
      emptyDir:
        medium: Memory      # RAM-da saxla (tmpfs) — daha sürətli
        sizeLimit: 100Mi
```

### 3. PersistentVolume (PV) və PersistentVolumeClaim (PVC)

```
┌──────────────────────────────────────────────────────┐
│                                                       │
│  Admin/StorageClass        Developer       Pod        │
│                                                       │
│  ┌──────────┐         ┌──────────┐    ┌──────────┐  │
│  │    PV    │◄───bind──│   PVC   │◄───│   Pod    │  │
│  │  (disk)  │         │ (tələb)  │    │(istifadə)│  │
│  └──────────┘         └──────────┘    └──────────┘  │
│                                                       │
└──────────────────────────────────────────────────────┘
```

**Manual PV yaratma:**

```yaml
# pv.yaml
apiVersion: v1
kind: PersistentVolume
metadata:
  name: mysql-pv
spec:
  capacity:
    storage: 20Gi
  accessModes:
    - ReadWriteOnce         # Bir node-dan read-write
  persistentVolumeReclaimPolicy: Retain   # PVC silinəndə PV-ni saxla
  storageClassName: standard
  hostPath:                  # Test üçün! Produksiyada cloud volume istifadə edin
    path: /data/mysql
```

**PVC yaratma:**

```yaml
# pvc.yaml
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: mysql-pvc
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 20Gi
  storageClassName: standard
```

**Pod-da istifadə:**

```yaml
# pod-with-pvc.yaml
apiVersion: v1
kind: Pod
metadata:
  name: mysql
spec:
  containers:
    - name: mysql
      image: mysql:8.0
      volumeMounts:
        - name: mysql-storage
          mountPath: /var/lib/mysql
      env:
        - name: MYSQL_ROOT_PASSWORD
          value: "secret"
  volumes:
    - name: mysql-storage
      persistentVolumeClaim:
        claimName: mysql-pvc
```

**Access Modes:**

| Mode | Qısa | Təsvir |
|------|------|--------|
| ReadWriteOnce | RWO | Bir node-dan read-write |
| ReadOnlyMany | ROX | Çox node-dan read-only |
| ReadWriteMany | RWX | Çox node-dan read-write |
| ReadWriteOncePod | RWOP | Yalnız bir Pod-dan read-write (K8s 1.27+) |

**Reclaim Policy:**

| Policy | Təsvir |
|--------|--------|
| Retain | PVC silinəndə PV və data saxlanır (manual təmizləmə) |
| Delete | PVC silinəndə PV və cloud disk silinir |
| Recycle | Data silinir, PV yenidən istifadə olunur (deprecated) |

### 4. StorageClass və Dynamic Provisioning

StorageClass avtomatik PV yaradılmasını təmin edir — admin manual PV yaratmır.

```yaml
# storageclass.yaml
apiVersion: storage.k8s.io/v1
kind: StorageClass
metadata:
  name: fast-ssd
provisioner: kubernetes.io/aws-ebs    # Cloud provider-ə uyğun
parameters:
  type: gp3
  iopsPerGB: "50"
  encrypted: "true"
reclaimPolicy: Delete
allowVolumeExpansion: true             # Online resize dəstəyi
volumeBindingMode: WaitForFirstConsumer
```

**Cloud provider StorageClass-ları:**

```yaml
# AWS EBS
apiVersion: storage.k8s.io/v1
kind: StorageClass
metadata:
  name: aws-ssd
provisioner: ebs.csi.aws.com
parameters:
  type: gp3
  encrypted: "true"

---
# GCP Persistent Disk
apiVersion: storage.k8s.io/v1
kind: StorageClass
metadata:
  name: gcp-ssd
provisioner: pd.csi.storage.gke.io
parameters:
  type: pd-ssd

---
# Azure Disk
apiVersion: storage.k8s.io/v1
kind: StorageClass
metadata:
  name: azure-ssd
provisioner: disk.csi.azure.com
parameters:
  skuName: Premium_LRS
```

**Dynamic provisioning ilə PVC:**

```yaml
# dynamic-pvc.yaml — PV avtomatik yaradılır
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: mysql-pvc
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 50Gi
  storageClassName: fast-ssd    # StorageClass adı
```

```bash
# PV-ləri görmək
kubectl get pv
# NAME       CAPACITY   ACCESS MODES   RECLAIM POLICY   STATUS   STORAGECLASS
# pvc-xxx    50Gi       RWO            Delete           Bound    fast-ssd

# PVC-ləri görmək
kubectl get pvc
# NAME        STATUS   VOLUME    CAPACITY   ACCESS MODES   STORAGECLASS
# mysql-pvc   Bound    pvc-xxx   50Gi       RWO            fast-ssd

# StorageClass-ları görmək
kubectl get storageclass
kubectl get sc
```

### 5. StatefulSet

StatefulSet — stateful tətbiqlər üçün (database, message queue). Hər Pod-un sabit identiteti, sabit storage-i və sıralı başlama/dayandırma prosesi var.

```yaml
# mysql-statefulset.yaml
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: mysql
spec:
  serviceName: mysql-headless    # Headless service lazımdır
  replicas: 3
  selector:
    matchLabels:
      app: mysql
  template:
    metadata:
      labels:
        app: mysql
    spec:
      containers:
        - name: mysql
          image: mysql:8.0
          ports:
            - containerPort: 3306
          volumeMounts:
            - name: mysql-data
              mountPath: /var/lib/mysql
          env:
            - name: MYSQL_ROOT_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: mysql-secret
                  key: root-password
          resources:
            requests:
              memory: "512Mi"
              cpu: "500m"
  volumeClaimTemplates:           # Hər Pod üçün ayrıca PVC yaradılır
    - metadata:
        name: mysql-data
      spec:
        accessModes: ["ReadWriteOnce"]
        storageClassName: fast-ssd
        resources:
          requests:
            storage: 50Gi

---
# Headless Service
apiVersion: v1
kind: Service
metadata:
  name: mysql-headless
spec:
  clusterIP: None
  selector:
    app: mysql
  ports:
    - port: 3306
```

```bash
# StatefulSet Pod-ları sabit adlara malik:
# mysql-0, mysql-1, mysql-2

# Hər Pod-un öz PVC-si var:
# mysql-data-mysql-0, mysql-data-mysql-1, mysql-data-mysql-2

# DNS adları:
# mysql-0.mysql-headless.default.svc.cluster.local
# mysql-1.mysql-headless.default.svc.cluster.local
# mysql-2.mysql-headless.default.svc.cluster.local

# Sıralı yaranma: mysql-0 ready olmadan mysql-1 yaranmır
# Sıralı silinmə: mysql-2, mysql-1, mysql-0 sırası ilə
```

**StatefulSet vs Deployment:**

| Xüsusiyyət | Deployment | StatefulSet |
|-------------|-----------|-------------|
| Pod adları | Random (app-xyz123) | Sıralı (app-0, app-1) |
| Storage | Paylaşılan PVC | Hər Pod-a ayrı PVC |
| Scaling | Paralel | Sıralı |
| DNS | Service DNS | Hər Pod-un öz DNS-i |
| İstifadə | Stateless app | Database, queue |

### 6. Volume Resize

```yaml
# StorageClass-da allowVolumeExpansion: true olmalıdır

# PVC-ni edit edin
kubectl edit pvc mysql-pvc
# spec.resources.requests.storage: 50Gi → 100Gi

# və ya patch
kubectl patch pvc mysql-pvc -p '{"spec":{"resources":{"requests":{"storage":"100Gi"}}}}'

# Status yoxlayın
kubectl get pvc mysql-pvc
```

## Praktiki Nümunələr (Practical Examples)

### Laravel File Storage (Shared Volume)

```yaml
# NFS-based shared storage (ReadWriteMany)
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: laravel-storage
spec:
  accessModes:
    - ReadWriteMany     # Bütün Pod-lar yaza bilər
  resources:
    requests:
      storage: 10Gi
  storageClassName: nfs-client

---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 5
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
    spec:
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.0.0
          volumeMounts:
            - name: storage
              mountPath: /var/www/html/storage/app/public
      volumes:
        - name: storage
          persistentVolumeClaim:
            claimName: laravel-storage
```

### MySQL with Backup CronJob

```yaml
# MySQL backup CronJob
apiVersion: batch/v1
kind: CronJob
metadata:
  name: mysql-backup
spec:
  schedule: "0 2 * * *"    # Hər gecə saat 2-də
  jobTemplate:
    spec:
      template:
        spec:
          containers:
            - name: backup
              image: mysql:8.0
              command:
                - /bin/sh
                - -c
                - |
                  mysqldump -h mysql-headless -u root -p$MYSQL_ROOT_PASSWORD \
                    --all-databases | gzip > /backup/backup-$(date +%Y%m%d).sql.gz
              env:
                - name: MYSQL_ROOT_PASSWORD
                  valueFrom:
                    secretKeyRef:
                      name: mysql-secret
                      key: root-password
              volumeMounts:
                - name: backup-storage
                  mountPath: /backup
          volumes:
            - name: backup-storage
              persistentVolumeClaim:
                claimName: backup-pvc
          restartPolicy: OnFailure
```

## PHP/Laravel ilə İstifadə (Usage with PHP/Laravel)

### Laravel Storage Konfiqurasiyası

```php
// config/filesystems.php
'disks' => [
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL') . '/storage',
        'visibility' => 'public',
    ],

    // Kubernetes PVC mount
    'uploads' => [
        'driver' => 'local',
        'root' => '/mnt/uploads',    // PVC mount path
        'visibility' => 'public',
    ],

    // S3 alternativi (PVC əvəzinə)
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
    ],
],
```

### Session və Cache Storage

```yaml
# Redis — session və cache üçün PVC əvəzinə
apiVersion: apps/v1
kind: Deployment
metadata:
  name: redis
spec:
  replicas: 1
  selector:
    matchLabels:
      app: redis
  template:
    metadata:
      labels:
        app: redis
    spec:
      containers:
        - name: redis
          image: redis:7-alpine
          ports:
            - containerPort: 6379
          command: ["redis-server", "--appendonly", "yes"]
          volumeMounts:
            - name: redis-data
              mountPath: /data
          resources:
            requests:
              memory: "128Mi"
      volumes:
        - name: redis-data
          persistentVolumeClaim:
            claimName: redis-pvc
```

## Interview Sualları (Interview Questions)

### S1: PersistentVolume ilə PersistentVolumeClaim arasında fərq nədir?
**C:** PV — cluster-level storage resursdur (disk). Admin və ya StorageClass tərəfindən yaradılır. PVC — developer-in storage tələbidir (nə qədər, hansı access mode). PVC yaradıldıqda uyğun PV-yə bind olur. Bu, storage provider-dən abstraksiya yaradır — developer disk detalllarını bilməli deyil.

### S2: Access mode-lar nə deməkdir?
**C:** ReadWriteOnce (RWO) — bir node-dan read/write. ReadOnlyMany (ROX) — çox node-dan yalnız read. ReadWriteMany (RWX) — çox node-dan read/write. Əksər cloud disk-lər (EBS, PD) yalnız RWO dəstəkləyir. RWX üçün NFS, EFS, CephFS lazımdır.

### S3: Dynamic provisioning nədir?
**C:** StorageClass vasitəsilə PVC yaradıldıqda PV-nin avtomatik yaradılmasıdır. Admin manual PV yaratmır. PVC-dəki StorageClass adına əsasən uyğun provisioner disk yaradır. Cloud mühitlərində standart yanaşmadır.

### S4: StatefulSet nə vaxt istifadə olunur?
**C:** Stateful tətbiqlər üçün: database (MySQL, PostgreSQL), message queue (Kafka, RabbitMQ), distributed cache (Redis Cluster). Hər Pod-un sabit adı, sabit storage-i və sıralı lifecycle-ı var. Stateless tətbiqlər üçün Deployment istifadə edin.

### S5: Reclaim policy-lər necə işləyir?
**C:** Retain — PVC silinəndə PV və data saxlanır, admin manual təmizləyir. Delete — PVC silinəndə PV və cloud disk avtomatik silinir. Produksiyada database üçün Retain istifadə edin — yanlışlıqla silinməsin. Müvəqqəti data üçün Delete uyğundur.

### S6: Volume resize necə edilir?
**C:** StorageClass-da `allowVolumeExpansion: true` olmalıdır. PVC-nin `spec.resources.requests.storage` artırılır. Bəzi provider-lər online resize dəstəkləyir (Pod restart lazım deyil), bəziləri offline (Pod restart lazımdır). Volume kiçiltmək mümkün deyil.

### S7: Kubernetes-də file upload-lar necə idarə olunur?
**C:** Üç yanaşma: 1) Shared PVC (ReadWriteMany — NFS/EFS) — bütün Pod-lar eyni diski görür. 2) Object storage (S3, GCS) — ən yaxşı seçim, scalable. 3) CDN + Object storage — böyük fayllar üçün. PVC (RWO) replicated deployment-da işləmir çünki yalnız bir node mount edə bilər.

## Best Practices

1. **Stateless dizayn edin** — mümkün qədər storage-dən asılılığı azaldın
2. **File upload üçün S3/GCS istifadə edin** — PVC əvəzinə
3. **Database üçün managed service istifadə edin** — RDS, Cloud SQL
4. **Dynamic provisioning istifadə edin** — manual PV yaratmayın
5. **Reclaim policy: Retain** — database storage üçün
6. **Backup CronJob qurun** — PVC data-sını backup edin
7. **Resource limits qoyun** — storage quota ilə
8. **VolumeBindingMode: WaitForFirstConsumer** — locality üçün
9. **emptyDir ilə tmp data** — Pod restart-da itməsi problem deyilsə
10. **Session/cache üçün Redis** — PVC əvəzinə in-memory store
