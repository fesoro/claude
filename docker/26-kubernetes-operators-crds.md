# Kubernetes Operators və CRDs

## Nədir? (What is it?)

**Custom Resource Definition (CRD)** — Kubernetes API-ni yeni resurs tipləri ilə genişləndirmək üçün mexanizmdir. Pod, Service, Deployment kimi standart resurslara əlavə, öz xüsusi resurslarımızı yarada bilərik (`MyApp`, `Database`, `Certificate` və s.).

**Operator** — spesifik tətbiqin idarəçiliyini avtomatlaşdıran K8s controller-idir. Operator = CRD + Controller. Operator tətbiq üçün domain knowledge-i kod şəklində ehtiva edir (backup, restore, scaling, upgrade).

## Əsas Konseptlər

### 1. Operator Pattern

Klassik yanaşma: DevOps engineer `kubectl apply` ilə manual idarə edir.

Operator yanaşması: Controller özü aşağıdakıları edir:
- Resurs yaradır
- Scale up/down
- Backup
- Failover
- Upgrade/rollback
- Health monitoring

```
┌────────────────────────────────────┐
│   Kubernetes Cluster               │
│                                    │
│  ┌──────────┐    ┌──────────────┐ │
│  │   CRD    │    │   Operator   │ │
│  │ Database │◄───│ (Controller) │ │
│  └──────────┘    └──────────────┘ │
│        │                │          │
│        ▼                ▼          │
│  ┌──────────────────────────┐    │
│  │  Managed Resources:       │    │
│  │  - StatefulSet            │    │
│  │  - Service                │    │
│  │  - Secret                 │    │
│  │  - PersistentVolumeClaim  │    │
│  └──────────────────────────┘    │
└────────────────────────────────────┘
```

### 2. Control Loop

Operator daimi işləyən control loop-dur:

```go
for {
    desired := getDesiredState()  // CRD-dən
    actual := getCurrentState()   // Cluster-dən
    if desired != actual {
        reconcile(desired, actual)  // Düzəliş et
    }
}
```

### 3. Operator Capability Level

| Level | Təsvir |
|-------|--------|
| Level 1 | Basic install |
| Level 2 | Seamless upgrades |
| Level 3 | Full lifecycle (backup, restore) |
| Level 4 | Deep insights (monitoring, alerts) |
| Level 5 | Auto pilot (auto-scaling, auto-healing) |

## Praktiki Nümunələr

### 1. Sadə CRD Yaratmaq

```yaml
# laravel-crd.yaml
apiVersion: apiextensions.k8s.io/v1
kind: CustomResourceDefinition
metadata:
  name: laravelapps.example.com
spec:
  group: example.com
  versions:
    - name: v1
      served: true
      storage: true
      schema:
        openAPIV3Schema:
          type: object
          properties:
            spec:
              type: object
              required: ["image", "replicas"]
              properties:
                image:
                  type: string
                  description: "Docker image for Laravel app"
                replicas:
                  type: integer
                  minimum: 1
                  maximum: 100
                database:
                  type: object
                  properties:
                    host:
                      type: string
                    name:
                      type: string
                redis:
                  type: object
                  properties:
                    enabled:
                      type: boolean
                    host:
                      type: string
            status:
              type: object
              properties:
                ready:
                  type: boolean
                replicas:
                  type: integer
      additionalPrinterColumns:
        - name: Replicas
          type: integer
          jsonPath: .spec.replicas
        - name: Image
          type: string
          jsonPath: .spec.image
        - name: Ready
          type: boolean
          jsonPath: .status.ready
  scope: Namespaced
  names:
    plural: laravelapps
    singular: laravelapp
    kind: LaravelApp
    shortNames: ["la"]
```

### 2. Custom Resource İstifadə

```yaml
# my-blog.yaml
apiVersion: example.com/v1
kind: LaravelApp
metadata:
  name: blog
  namespace: default
spec:
  image: myregistry/laravel:1.0.0
  replicas: 3
  database:
    host: mysql.default.svc.cluster.local
    name: blog_production
  redis:
    enabled: true
    host: redis.default.svc.cluster.local
```

```bash
kubectl apply -f laravel-crd.yaml
kubectl apply -f my-blog.yaml

kubectl get laravelapps
# NAME    REPLICAS   IMAGE                       READY
# blog    3          myregistry/laravel:1.0.0    true

kubectl get la  # short name
```

### 3. Operator SDK ilə Operator Yaratmaq

```bash
# Operator SDK quraşdır
curl -LO https://github.com/operator-framework/operator-sdk/releases/latest/download/operator-sdk_linux_amd64
chmod +x operator-sdk_linux_amd64
sudo mv operator-sdk_linux_amd64 /usr/local/bin/operator-sdk

# Yeni operator layihəsi
mkdir laravel-operator && cd laravel-operator
operator-sdk init --domain=example.com --repo=github.com/myorg/laravel-operator

# API + controller əlavə et
operator-sdk create api --group=apps --version=v1 --kind=LaravelApp --resource --controller
```

### 4. Reconcile Logic (Go)

```go
// controllers/laravelapp_controller.go
package controllers

import (
    "context"
    appsv1 "github.com/myorg/laravel-operator/api/v1"
    appsv1k8s "k8s.io/api/apps/v1"
    corev1 "k8s.io/api/core/v1"
    metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
    "k8s.io/apimachinery/pkg/api/errors"
    "k8s.io/apimachinery/pkg/runtime"
    ctrl "sigs.k8s.io/controller-runtime"
    "sigs.k8s.io/controller-runtime/pkg/client"
)

type LaravelAppReconciler struct {
    client.Client
    Scheme *runtime.Scheme
}

func (r *LaravelAppReconciler) Reconcile(ctx context.Context, req ctrl.Request) (ctrl.Result, error) {
    // 1. CRD-ni al
    app := &appsv1.LaravelApp{}
    if err := r.Get(ctx, req.NamespacedName, app); err != nil {
        return ctrl.Result{}, client.IgnoreNotFound(err)
    }

    // 2. Deployment yarat (yoxdursa)
    deploy := &appsv1k8s.Deployment{}
    err := r.Get(ctx, req.NamespacedName, deploy)
    if err != nil && errors.IsNotFound(err) {
        newDeploy := r.deploymentForLaravelApp(app)
        if err := r.Create(ctx, newDeploy); err != nil {
            return ctrl.Result{}, err
        }
        return ctrl.Result{Requeue: true}, nil
    }

    // 3. Replicas uyğunlaşdır
    if *deploy.Spec.Replicas != app.Spec.Replicas {
        *deploy.Spec.Replicas = app.Spec.Replicas
        r.Update(ctx, deploy)
    }

    // 4. Service yarat
    svc := &corev1.Service{}
    err = r.Get(ctx, req.NamespacedName, svc)
    if err != nil && errors.IsNotFound(err) {
        newSvc := r.serviceForLaravelApp(app)
        r.Create(ctx, newSvc)
    }

    // 5. Status yenilə
    app.Status.Ready = true
    app.Status.Replicas = app.Spec.Replicas
    r.Status().Update(ctx, app)

    return ctrl.Result{}, nil
}

func (r *LaravelAppReconciler) deploymentForLaravelApp(app *appsv1.LaravelApp) *appsv1k8s.Deployment {
    labels := map[string]string{"app": app.Name}
    replicas := app.Spec.Replicas

    return &appsv1k8s.Deployment{
        ObjectMeta: metav1.ObjectMeta{
            Name:      app.Name,
            Namespace: app.Namespace,
        },
        Spec: appsv1k8s.DeploymentSpec{
            Replicas: &replicas,
            Selector: &metav1.LabelSelector{MatchLabels: labels},
            Template: corev1.PodTemplateSpec{
                ObjectMeta: metav1.ObjectMeta{Labels: labels},
                Spec: corev1.PodSpec{
                    Containers: []corev1.Container{{
                        Name:  "laravel",
                        Image: app.Spec.Image,
                        Ports: []corev1.ContainerPort{{ContainerPort: 9000}},
                        Env: []corev1.EnvVar{
                            {Name: "DB_HOST", Value: app.Spec.Database.Host},
                            {Name: "DB_DATABASE", Value: app.Spec.Database.Name},
                        },
                    }},
                },
            },
        },
    }
}
```

### 5. Deploy Operator

```bash
# Docker image build
make docker-build docker-push IMG=myregistry/laravel-operator:0.1.0

# Deploy
make deploy IMG=myregistry/laravel-operator:0.1.0

# Custom Resource yarat
kubectl apply -f config/samples/apps_v1_laravelapp.yaml

# Yoxla
kubectl get laravelapps
kubectl describe laravelapp blog
```

## PHP/Laravel ilə İstifadə

### Database Operator (Laravel üçün PostgreSQL)

Məsələn Zalando Postgres Operator istifadə edib Laravel üçün auto-managed DB:

```yaml
apiVersion: acid.zalan.do/v1
kind: postgresql
metadata:
  name: laravel-db
spec:
  teamId: "laravel"
  volume:
    size: 10Gi
  numberOfInstances: 3  # 1 master + 2 replica
  users:
    laravel:
      - superuser
      - createdb
  databases:
    laravel: laravel
  postgresql:
    version: "15"
  resources:
    requests:
      cpu: 100m
      memory: 100Mi
    limits:
      cpu: 500m
      memory: 500Mi
  patroni:
    failsafe_mode: true
```

Operator avtomatik:
- StatefulSet yaradır
- Failover idarə edir
- Backup planlaşdırır
- Major version upgrade edir

Laravel bunu istifadə edir:
```env
DB_CONNECTION=pgsql
DB_HOST=laravel-db.default.svc.cluster.local
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=<operator-generated-secret>
```

### Cert-manager (Laravel HTTPS)

Cert-manager cos də bir operator-dir. Laravel ingress üçün avto TLS:

```yaml
apiVersion: cert-manager.io/v1
kind: Certificate
metadata:
  name: laravel-cert
  namespace: default
spec:
  secretName: laravel-tls
  issuerRef:
    name: letsencrypt-prod
    kind: ClusterIssuer
  dnsNames:
    - laravel.example.com

---
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: laravel
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod
spec:
  tls:
    - hosts: [laravel.example.com]
      secretName: laravel-tls
  rules:
    - host: laravel.example.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: laravel
                port:
                  number: 80
```

### Populyar Operator-lar

| Operator | Təsvir |
|----------|--------|
| Prometheus Operator | Prometheus + Alertmanager idarəçiliyi |
| cert-manager | TLS sertifikat avto idarəçiliyi |
| External Secrets Operator | Vault/AWS Secrets-dan inject |
| ArgoCD | GitOps, declarative deployment |
| Zalando Postgres Operator | PostgreSQL cluster |
| Strimzi | Kafka cluster |
| Redis Operator | Redis sentinel/cluster |
| Istio Operator | Service mesh |

## Interview Sualları

**1. CRD nədir?**
Custom Resource Definition — K8s API-ə yeni resurs tipi əlavə edir. Məs: `LaravelApp`, `Database`, `Certificate`. kubectl, RBAC, watch API hamısı custom resurslarda da işləyir.

**2. Operator nədir?**
CRD + Controller kombinasiyası. Controller daim cluster-i izləyir, CRD-də yazılmış "desired state"-ə çatdırır. Tətbiqin domain knowledge-i kod şəklindədir.

**3. Helm chart və Operator arasında fərq?**
- Helm: install/upgrade vaxtı templating yaradır, sonra heç nə etmir
- Operator: continuous reconciliation, runtime events-ə cavab verir (failover, backup, scaling)

**4. Operator SDK nədir?**
Red Hat-in yaratdığı CLI — operator yaratmağı asanlaşdırır. Go, Ansible, Helm əsasında operator template-ləri təqdim edir.

**5. Reconcile loop nə edir?**
Controller davamlı olaraq cluster-in cari vəziyyətini CRD-də yazılmış desired state ilə müqayisə edir. Fərq varsa, düzəliş edir (create, update, delete).

**6. Operator-un Capability Level-ləri?**
5 səviyyə: Basic install → Seamless upgrades → Full lifecycle → Deep insights → Auto pilot.

**7. OperatorHub nədir?**
operatorhub.io — açıq mənbəli operator-lar reqisteri. Prometheus, cert-manager, Strimzi və s. burada. OLM (Operator Lifecycle Manager) ilə install olunur.

**8. CRD OpenAPI schema niyə vacibdir?**
Validation üçün. User `replicas: -5` yazarsa, API-server rədd edir. Schema olmadan hər data-ni qəbul edər.

## Best Practices

1. **CRD-də `additionalPrinterColumns`** istifadə et — `kubectl get` daha informativ
2. **Status sub-resource** istifadə et — status yeniləmə RBAC-i ayırır
3. **Validation webhook** qur — mürəkkəb validation üçün
4. **Owner references** istifadə et — CRD silinərkən managed resurslar avto silinir
5. **Finalizers** — cleanup logic üçün (DB backup, external resource silinməsi)
6. **Metrics expose et** — Prometheus operator-un sağlamlığını izləsin
7. **Leader election** — HA üçün bir operator aktiv olsun
8. **Controller-runtime framework** istifadə et — low-level detallar əvəzinə
9. **OperatorHub-da var olanı yoxla** — təkrar ixtira etməyə dəyməz
10. **Unit + E2E test yaz** — kubebuilder test framework ilə
