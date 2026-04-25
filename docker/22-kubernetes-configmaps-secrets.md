# Kubernetes ConfigMaps və Secrets

> **Səviyyə (Level):** ⭐⭐ Middle

## Nədir? (What is it?)

ConfigMap və Secret — Kubernetes-də konfiqurasiya datanı konteynerdən ayırmaq üçün istifadə olunan resurslardır. ConfigMap adi konfiqurasiya üçün (APP_ENV, DB_HOST), Secret isə həssas data üçün (password, API key, certificate) istifadə olunur.

Bu yanaşma "12-Factor App" prinsipinə uyğundur — konfiqurasiya koddan ayrılır. Eyni image müxtəlif mühitlərdə (dev, staging, production) fərqli konfiqurasiya ilə istifadə oluna bilər.

## Əsas Konseptlər

### 1. ConfigMap

```yaml
# configmap.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: laravel-config
  namespace: production
data:
  # Key-value cütləri
  APP_NAME: "My Laravel App"
  APP_ENV: "production"
  APP_DEBUG: "false"
  APP_URL: "https://app.example.com"
  DB_CONNECTION: "mysql"
  DB_HOST: "mysql-service"
  DB_PORT: "3306"
  DB_DATABASE: "laravel_prod"
  CACHE_DRIVER: "redis"
  SESSION_DRIVER: "redis"
  QUEUE_CONNECTION: "redis"
  REDIS_HOST: "redis-service"
  LOG_CHANNEL: "stderr"

  # Fayl olaraq (multi-line)
  php.ini: |
    memory_limit = 256M
    upload_max_filesize = 50M
    post_max_size = 55M
    max_execution_time = 60
    opcache.enable = 1
    opcache.validate_timestamps = 0

  nginx.conf: |
    server {
        listen 80;
        server_name _;
        root /var/www/html/public;
        index index.php;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass php-fpm-service:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }
    }
```

```bash
# ConfigMap yaratma yolları

# YAML fayldan
kubectl apply -f configmap.yaml

# Literal dəyərlərdən
kubectl create configmap app-config \
    --from-literal=APP_ENV=production \
    --from-literal=DB_HOST=mysql-service

# Fayldan
kubectl create configmap nginx-config \
    --from-file=nginx.conf=./docker/nginx/default.conf

# .env fayldan
kubectl create configmap laravel-env \
    --from-env-file=.env.production

# ConfigMap-ı görmək
kubectl get configmap laravel-config -o yaml
kubectl describe configmap laravel-config
```

### 2. ConfigMap İstifadə Yolları

#### Environment Variable olaraq

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 3
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

          # Bütün key-ləri env olaraq inject et
          envFrom:
            - configMapRef:
                name: laravel-config

          # Və ya tək-tək key seç
          env:
            - name: APP_ENV
              valueFrom:
                configMapKeyRef:
                  name: laravel-config
                  key: APP_ENV
            - name: DATABASE_HOST
              valueFrom:
                configMapKeyRef:
                  name: laravel-config
                  key: DB_HOST
```

#### Volume olaraq Mount

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 3
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
            - name: php-config
              mountPath: /usr/local/etc/php/conf.d/custom.ini
              subPath: php.ini        # Yalnız bu key-i mount et
            - name: nginx-config
              mountPath: /etc/nginx/conf.d/default.conf
              subPath: nginx.conf
      volumes:
        - name: php-config
          configMap:
            name: laravel-config
            items:
              - key: php.ini
                path: php.ini
        - name: nginx-config
          configMap:
            name: laravel-config
            items:
              - key: nginx.conf
                path: nginx.conf
```

### 3. Secret

```yaml
# secret.yaml
apiVersion: v1
kind: Secret
metadata:
  name: laravel-secret
  namespace: production
type: Opaque
data:
  # base64 encoded dəyərlər
  APP_KEY: YmFzZTY0OmtleS1oZXJl
  DB_PASSWORD: cHJvZHVjdGlvbi1wYXNz
  DB_USERNAME: bGFyYXZlbA==
  REDIS_PASSWORD: cmVkaXMtcGFzcw==
  MAIL_PASSWORD: bWFpbC1wYXNz
  AWS_SECRET_ACCESS_KEY: YXdzLXNlY3JldC1rZXk=

---
# stringData ilə (base64 encode lazım deyil)
apiVersion: v1
kind: Secret
metadata:
  name: laravel-secret-v2
type: Opaque
stringData:
  APP_KEY: "base64:your-key-here"
  DB_PASSWORD: "production-pass"
  DB_USERNAME: "laravel"
```

```bash
# Secret yaratma yolları

# Literal-dən
kubectl create secret generic laravel-secret \
    --from-literal=APP_KEY=base64:key-here \
    --from-literal=DB_PASSWORD=secret123

# Fayldan
kubectl create secret generic tls-cert \
    --from-file=tls.crt=./certs/server.crt \
    --from-file=tls.key=./certs/server.key

# TLS secret
kubectl create secret tls app-tls \
    --cert=./certs/server.crt \
    --key=./certs/server.key

# Docker registry secret
kubectl create secret docker-registry regcred \
    --docker-server=registry.example.com \
    --docker-username=user \
    --docker-password=pass

# base64 encode/decode
echo -n "secret123" | base64           # c2VjcmV0MTIz
echo "c2VjcmV0MTIz" | base64 -d       # secret123

# Secret-i görmək (dəyərlər base64-dədir)
kubectl get secret laravel-secret -o yaml

# Dəyəri decode etmək
kubectl get secret laravel-secret -o jsonpath='{.data.DB_PASSWORD}' | base64 -d
```

**Secret Tipləri:**

| Tip | İstifadə |
|-----|----------|
| Opaque | Ümumi (default) |
| kubernetes.io/tls | TLS certificate |
| kubernetes.io/dockerconfigjson | Docker registry auth |
| kubernetes.io/basic-auth | Basic auth |
| kubernetes.io/ssh-auth | SSH key |

### 4. Secret İstifadə Yolları

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
    spec:
      # Private registry üçün
      imagePullSecrets:
        - name: regcred

      containers:
        - name: php-fpm
          image: registry.example.com/laravel:1.0.0

          # Bütün secret-ləri env olaraq
          envFrom:
            - secretRef:
                name: laravel-secret

          # Tək-tək
          env:
            - name: APP_KEY
              valueFrom:
                secretKeyRef:
                  name: laravel-secret
                  key: APP_KEY

          # Volume olaraq mount
          volumeMounts:
            - name: secrets
              mountPath: /etc/secrets
              readOnly: true
      volumes:
        - name: secrets
          secret:
            secretName: laravel-secret
            defaultMode: 0400    # Yalnız owner read
```

### 5. ConfigMap və Secret-in Avtomatik Yenilənməsi

```yaml
# ConfigMap volume olaraq mount olunduqda, dəyişiklik avtomatik yansıyır
# (kubelet sync period: ~1 dəqiqə)

# ENV olaraq istifadə olunduqda, POD RESTART lazımdır!

# Workaround: ConfigMap adını versiyalamaq
apiVersion: v1
kind: ConfigMap
metadata:
  name: laravel-config-v2    # Versiya dəyişdikdə yeni ConfigMap
data:
  APP_ENV: "production"

---
# Deployment-da reference dəyişdirmək → rolling update başlayır
# Və ya hash annotation istifadə etmək:
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  template:
    metadata:
      annotations:
        configmap-hash: "abc123"    # ConfigMap dəyişdikdə bu hash-ı dəyişin
```

### 6. External Secret Management

#### HashiCorp Vault ilə

```yaml
# External Secrets Operator (ESO) quraşdırma:
# helm install external-secrets external-secrets/external-secrets

# SecretStore — Vault-a qoşulma
apiVersion: external-secrets.io/v1beta1
kind: SecretStore
metadata:
  name: vault-backend
spec:
  provider:
    vault:
      server: "https://vault.example.com"
      path: "secret"
      version: "v2"
      auth:
        kubernetes:
          mountPath: "kubernetes"
          role: "laravel-app"

---
# ExternalSecret — Vault-dan Secret yaratma
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: laravel-vault-secret
spec:
  refreshInterval: 1h
  secretStoreRef:
    name: vault-backend
    kind: SecretStore
  target:
    name: laravel-secret        # Yaradılacaq K8s Secret adı
    creationPolicy: Owner
  data:
    - secretKey: APP_KEY
      remoteRef:
        key: laravel/production
        property: app_key
    - secretKey: DB_PASSWORD
      remoteRef:
        key: laravel/production
        property: db_password
```

#### AWS Secrets Manager ilə

```yaml
# SecretStore — AWS Secrets Manager
apiVersion: external-secrets.io/v1beta1
kind: SecretStore
metadata:
  name: aws-secrets
spec:
  provider:
    aws:
      service: SecretsManager
      region: eu-west-1
      auth:
        jwt:
          serviceAccountRef:
            name: external-secrets-sa

---
# ExternalSecret
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: laravel-aws-secret
spec:
  refreshInterval: 30m
  secretStoreRef:
    name: aws-secrets
    kind: SecretStore
  target:
    name: laravel-secret
  data:
    - secretKey: DB_PASSWORD
      remoteRef:
        key: production/laravel
        property: db_password
```

### 7. Sealed Secrets (Git-safe Secrets)

```bash
# Sealed Secrets Controller quraşdırma
helm install sealed-secrets sealed-secrets/sealed-secrets

# kubeseal CLI ilə encrypt
echo -n "secret123" | kubectl create secret generic mysecret \
    --from-file=password=/dev/stdin --dry-run=client -o yaml | \
    kubeseal --format yaml > sealed-secret.yaml

# sealed-secret.yaml Git-ə commit oluna bilər (encrypted)
```

```yaml
# sealed-secret.yaml
apiVersion: bitnami.com/v1alpha1
kind: SealedSecret
metadata:
  name: laravel-secret
spec:
  encryptedData:
    DB_PASSWORD: AgBq8O...long-encrypted-string...
    APP_KEY: AgCx9P...long-encrypted-string...
```

## Praktiki Nümunələr

### Laravel Full ConfigMap + Secret Setup

```yaml
---
# ConfigMap — non-sensitive config
apiVersion: v1
kind: ConfigMap
metadata:
  name: laravel-config
  namespace: production
data:
  APP_NAME: "Production App"
  APP_ENV: "production"
  APP_DEBUG: "false"
  APP_URL: "https://app.example.com"
  DB_CONNECTION: "mysql"
  DB_HOST: "mysql-service"
  DB_PORT: "3306"
  DB_DATABASE: "laravel_prod"
  CACHE_DRIVER: "redis"
  SESSION_DRIVER: "redis"
  QUEUE_CONNECTION: "redis"
  REDIS_HOST: "redis-service"
  REDIS_PORT: "6379"
  MAIL_MAILER: "smtp"
  MAIL_HOST: "smtp.example.com"
  MAIL_PORT: "587"
  LOG_CHANNEL: "stderr"
  LOG_LEVEL: "info"

---
# Secret — sensitive data
apiVersion: v1
kind: Secret
metadata:
  name: laravel-secret
  namespace: production
type: Opaque
stringData:
  APP_KEY: "base64:your-app-key-here"
  DB_USERNAME: "laravel_user"
  DB_PASSWORD: "super-secret-db-pass"
  REDIS_PASSWORD: "redis-pass"
  MAIL_USERNAME: "smtp-user"
  MAIL_PASSWORD: "smtp-pass"
  AWS_ACCESS_KEY_ID: "AKIA..."
  AWS_SECRET_ACCESS_KEY: "secret..."

---
# Deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
  namespace: production
spec:
  replicas: 3
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
          ports:
            - containerPort: 9000
          envFrom:
            - configMapRef:
                name: laravel-config
            - secretRef:
                name: laravel-secret
          resources:
            requests:
              memory: "128Mi"
              cpu: "250m"
            limits:
              memory: "512Mi"
              cpu: "1000m"
```

## PHP/Laravel ilə İstifadə

### Laravel .env-i ConfigMap/Secret-ə Çevirmək

```bash
# .env faylından ConfigMap yaratma script-i
#!/bin/bash

# Non-sensitive keys
kubectl create configmap laravel-config \
    --from-literal=APP_NAME="$(grep APP_NAME .env | cut -d= -f2)" \
    --from-literal=APP_ENV=production \
    --from-literal=DB_HOST=mysql-service \
    --from-literal=CACHE_DRIVER=redis \
    --dry-run=client -o yaml > configmap.yaml

# Sensitive keys
kubectl create secret generic laravel-secret \
    --from-literal=APP_KEY="$(grep APP_KEY .env | cut -d= -f2)" \
    --from-literal=DB_PASSWORD="$(grep DB_PASSWORD .env | cut -d= -f2)" \
    --dry-run=client -o yaml > secret.yaml
```

### Config Cache ilə ConfigMap

```dockerfile
# Dockerfile — config:cache ConfigMap env-dən istifadə edə bilmir
# Çünki config:cache build zamanı env-ləri hard-code edir
# Həll: runtime-da cache yaratmaq

FROM php:8.3-fpm-alpine
# ...
COPY . /var/www/html

# config:cache-i STARTUP-da çağırın, build-da deyil
CMD ["sh", "-c", "php artisan config:cache && php-fpm"]
```

### Mühitlər Arası Konfiqurasiya

```
production/
  ├── configmap.yaml      # APP_ENV=production, DB_HOST=prod-mysql
  └── secret.yaml         # Prod passwords

staging/
  ├── configmap.yaml      # APP_ENV=staging, DB_HOST=staging-mysql
  └── secret.yaml         # Staging passwords

# Kustomize ilə
kustomize/
  ├── base/
  │   ├── deployment.yaml
  │   └── kustomization.yaml
  ├── staging/
  │   ├── configmap.yaml
  │   └── kustomization.yaml
  └── production/
      ├── configmap.yaml
      └── kustomization.yaml
```

## İntervyu Sualları

### S1: ConfigMap ilə Secret arasında fərq nədir?
**C:** ConfigMap adi konfiqurasiya datası üçün (APP_ENV, DB_HOST), Secret isə həssas data üçün (password, API key). Secret-lər base64 encoded saxlanır (encryption deyil!), RBAC ilə ayrıca control oluna bilər, etcd-də encryption-at-rest konfiqurasiya oluna bilər. Amma default olaraq Secret-lər çox da "secret" deyil — əlavə təhlükəsizlik tədbirləri lazımdır.

### S2: Secret-lər Kubernetes-də necə qorunur?
**C:** Default olaraq yalnız base64 encoded-dir (şifrələnmə deyil). Qoruma üçün: 1) RBAC ilə access control, 2) etcd encryption at rest aktivləşdirmək, 3) External secret management (Vault, AWS Secrets Manager), 4) Sealed Secrets (Git-safe), 5) Secret-ləri volume olaraq mount edib `readOnly: true` və `defaultMode: 0400` istifadə etmək.

### S3: ConfigMap dəyişdikdə Pod-lar necə yenilənir?
**C:** Volume mount olunduqda: kubelet avtomatik yeniləyir (~1 dəq). Env var olaraq: Pod restart lazımdır. Praktikada: ConfigMap adını versiyalamaq (config-v2) və ya Pod template annotation dəyişmək rolling update trigger edir. Helm istifadə olunursa, `checksum/config` annotation ilə avtomatik həll olunur.

### S4: External Secrets Operator (ESO) nədir?
**C:** Xarici secret management sistemlərindən (Vault, AWS Secrets Manager, Azure Key Vault) K8s Secret-lərini avtomatik yaratmaq və sync etmək üçün operator-dur. Secret-lər source of truth-da saxlanır, ESO müntəzəm olaraq sync edir. Secret rotation avtomatik olur.

### S5: Sealed Secrets nədir və nə problem həll edir?
**C:** Secret YAML-ları Git-ə commit etmək təhlükəlidir (base64 — plain text kimidir). Sealed Secrets encrypt edir — yalnız cluster-dəki controller decrypt edə bilər. Encrypted SealedSecret YAML-ı təhlükəsiz Git-ə commit oluna bilər. GitOps workflow üçün vacibdir.

### S6: envFrom ilə env arasında fərq nədir?
**C:** `envFrom` ConfigMap/Secret-in BÜTÜN key-lərini env var olaraq inject edir. `env` ilə isə tək-tək key seçilir, adı dəyişdirilə bilər. `envFrom` daha sadədir, amma hansı env-lərin haradan gəldiyini izləmək çətindir. `env` daha explicit-dir.

### S7: ConfigMap-da fayl saxlamağın üstünlüyü nədir?
**C:** php.ini, nginx.conf kimi konfiqurasiya fayllarını image-ə build etmədən idarə etmək. Image dəyişmədən konfiqurasiya yenilənə bilər. Mühitlər arası fərqli konfiqurasiya mümkün olur. Volume mount ilə konteynerə inject olunur.

## Best Practices

1. **Həssas datanı ConfigMap-da saxlamayın** — Secret istifadə edin
2. **Secret-ləri Git-ə commit etməyin** — Sealed Secrets və ya ESO istifadə edin
3. **etcd encryption at rest aktivləşdirin**
4. **RBAC ilə Secret access-i məhdudlaşdırın**
5. **External secret manager istifadə edin** — Vault, AWS Secrets Manager
6. **ConfigMap/Secret-ləri namespace-ə ayırın** — mühitlər üçün
7. **envFrom istifadə edin** — sadə konfiqurasiya üçün
8. **immutable ConfigMap/Secret** — `immutable: true` ilə yanlış dəyişikliyin qarşısını alın
9. **Secret rotation plan qurun** — müntəzəm dəyişdirin
10. **Kustomize/Helm ilə mühit konfiqurasiyasını idarə edin**
