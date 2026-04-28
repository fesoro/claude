# Kubernetes Services (Kubernetes Servisləri)

> **Səviyyə (Level):** ⭐⭐ Middle

## Nədir? (What is it?)

Kubernetes Service — Pod-lara stabil şəbəkə endpointi təqdim edən abstraksiya-dır. Pod-lar dinamikdir — yaranıb silinə bilər, IP ünvanları dəyişir. Service isə sabit IP və DNS adı ilə Pod-lar qrupuna traffic yönləndirir.

Service olmadan bir Pod digərinə necə müraciət edəcək? Pod restart olduqda yeni IP alacaq. Service bu problemi həll edir — label selector vasitəsilə Pod-ları tapır və load balance edir.

## Əsas Konseptlər

### 1. Service Tipləri

```
┌─────────────────────────────────────────────────────────────┐
│ Service Tipləri                                             │
├──────────────┬──────────────────────────────────────────────┤
│ ClusterIP    │ Cluster daxilində (default). Xaricdən        │
│              │ əlçatmaz.                                    │
├──────────────┼──────────────────────────────────────────────┤
│ NodePort     │ Hər node-un IP:Port ilə xaricdən əlçatan.   │
│              │ Port aralığı: 30000-32767.                   │
├──────────────┼──────────────────────────────────────────────┤
│ LoadBalancer │ Cloud provider-in load balancer-ini yaradır. │
│              │ Xarici IP ilə əlçatan.                       │
├──────────────┼──────────────────────────────────────────────┤
│ ExternalName │ DNS CNAME redirect. Xarici servislərə        │
│              │ referans.                                     │
└──────────────┴──────────────────────────────────────────────┘

Traffic axını:

Internet → LoadBalancer → NodePort → ClusterIP → Pod
```

### 2. ClusterIP (Default)

Cluster daxilindəki servislər arasında əlaqə üçün.

```yaml
# clusterip-service.yaml
apiVersion: v1
kind: Service
metadata:
  name: laravel-service
spec:
  type: ClusterIP      # default, yazılmasa da olur
  selector:
    app: laravel
    component: php-fpm
  ports:
    - name: php-fpm
      port: 9000        # Service portu (digər Pod-lar bu portu istifadə edir)
      targetPort: 9000   # Pod-dakı konteyner portu
      protocol: TCP
```

```bash
# Service yaratmaq
kubectl apply -f clusterip-service.yaml

# DNS ilə access (cluster daxilindən)
# Format: <service-name>.<namespace>.svc.cluster.local
# Qısa: <service-name> (eyni namespace-də)
# laravel-service.production.svc.cluster.local
# laravel-service (eyni namespace-dən)

# Test etmək
kubectl run test --rm -it --image=busybox -- sh
# Daxildə:
nslookup laravel-service
wget -qO- http://laravel-service:9000
```

### 3. NodePort

Cluster xaricindən development/test üçün access.

```yaml
# nodeport-service.yaml
apiVersion: v1
kind: Service
metadata:
  name: laravel-nodeport
spec:
  type: NodePort
  selector:
    app: laravel
    component: nginx
  ports:
    - port: 80           # Cluster daxili port
      targetPort: 80     # Pod portu
      nodePort: 30080    # Xarici port (30000-32767)
      protocol: TCP
```

```bash
# Xaricdən access: http://<any-node-ip>:30080

# Node IP-ni tapmaq
kubectl get nodes -o wide

# Minikube-da
minikube service laravel-nodeport --url
```

### 4. LoadBalancer

Cloud mühitlərdə istifadə olunur (AWS, GCP, Azure).

```yaml
# loadbalancer-service.yaml
apiVersion: v1
kind: Service
metadata:
  name: laravel-lb
  annotations:
    # AWS-ə xüsusi annotation-lar
    service.beta.kubernetes.io/aws-load-balancer-type: "nlb"
    service.beta.kubernetes.io/aws-load-balancer-scheme: "internet-facing"
spec:
  type: LoadBalancer
  selector:
    app: laravel
    component: nginx
  ports:
    - name: http
      port: 80
      targetPort: 80
    - name: https
      port: 443
      targetPort: 443
```

```bash
# LoadBalancer yaradıldıqdan sonra External IP-ni görmək
kubectl get svc laravel-lb
# NAME         TYPE           CLUSTER-IP    EXTERNAL-IP      PORT(S)
# laravel-lb   LoadBalancer   10.0.10.50    203.0.113.100    80:31234/TCP

# External IP "pending" ola bilər (cloud LB yaradılana qədər)
kubectl get svc laravel-lb -w
```

### 5. ExternalName

Xarici servislərə DNS alias.

```yaml
# externalname-service.yaml
apiVersion: v1
kind: Service
metadata:
  name: external-database
spec:
  type: ExternalName
  externalName: mydb.example.amazonaws.com
```

```bash
# Cluster daxilindən external-database adı ilə access
# DNS CNAME: external-database → mydb.example.amazonaws.com
```

### 6. Ingress

Ingress — HTTP/HTTPS traffic-i cluster daxilindəki Service-lərə yönləndirən resource-dur. Virtual hosting, path-based routing, SSL termination təmin edir.

```yaml
# ingress.yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: laravel-ingress
  annotations:
    nginx.ingress.kubernetes.io/rewrite-target: /
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
spec:
  ingressClassName: nginx
  tls:
    - hosts:
        - app.example.com
        - api.example.com
      secretName: tls-secret
  rules:
    - host: app.example.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: laravel-web
                port:
                  number: 80
    - host: api.example.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: laravel-api
                port:
                  number: 80
```

**Path-based routing:**

```yaml
# path-ingress.yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: app-ingress
  annotations:
    nginx.ingress.kubernetes.io/use-regex: "true"
spec:
  ingressClassName: nginx
  rules:
    - host: app.example.com
      http:
        paths:
          - path: /api(/|$)(.*)
            pathType: ImplementationSpecific
            backend:
              service:
                name: api-service
                port:
                  number: 80
          - path: /admin
            pathType: Prefix
            backend:
              service:
                name: admin-service
                port:
                  number: 80
          - path: /
            pathType: Prefix
            backend:
              service:
                name: frontend-service
                port:
                  number: 80
```

### 7. Ingress Controller

Ingress resource tək başına işləmir — Ingress Controller lazımdır. Controller Ingress qaydalarını oxuyur və traffic yönləndirməni həyata keçirir.

```bash
# Nginx Ingress Controller quraşdırma (Helm ilə)
helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
helm install ingress-nginx ingress-nginx/ingress-nginx \
    --namespace ingress-nginx \
    --create-namespace

# Traefik Ingress Controller
helm repo add traefik https://traefik.github.io/charts
helm install traefik traefik/traefik

# Mövcud Ingress Controller-ləri görmək
kubectl get pods -n ingress-nginx
kubectl get ingressclass
```

**Populyar Ingress Controller-lər:**

| Controller | Xüsusiyyət |
|-----------|------------|
| NGINX Ingress | Ən populyar, geniş annotation dəstəyi |
| Traefik | Avtomatik config, Let's Encrypt dəstəyi |
| HAProxy | Yüksək performans |
| AWS ALB | AWS-ə native inteqrasiya |
| Istio Gateway | Service mesh ilə birlikdə |

### 8. DNS və Service Discovery

```
┌─────────────────────────────────────────────────────┐
│ Kubernetes DNS (CoreDNS)                             │
├─────────────────────────────────────────────────────┤
│                                                      │
│ Service DNS:                                         │
│   <service>.<namespace>.svc.cluster.local            │
│   laravel-service.production.svc.cluster.local       │
│                                                      │
│ Pod DNS:                                             │
│   <pod-ip>.<namespace>.pod.cluster.local             │
│   10-244-1-5.production.pod.cluster.local            │
│                                                      │
│ Headless Service (ClusterIP: None):                  │
│   <pod-name>.<service>.<namespace>.svc.cluster.local │
│   laravel-0.laravel-headless.prod.svc.cluster.local  │
│                                                      │
└─────────────────────────────────────────────────────┘
```

```yaml
# Headless Service (StatefulSet üçün)
apiVersion: v1
kind: Service
metadata:
  name: mysql-headless
spec:
  clusterIP: None     # Headless — hər Pod-un öz DNS record-u olur
  selector:
    app: mysql
  ports:
    - port: 3306
```

## Praktiki Nümunələr

### Laravel Full Stack Service Konfiqurasiyası

```yaml
---
# PHP-FPM Service (ClusterIP — yalnız Nginx-dən access)
apiVersion: v1
kind: Service
metadata:
  name: php-fpm-service
spec:
  selector:
    app: laravel
    component: php-fpm
  ports:
    - port: 9000
      targetPort: 9000

---
# Nginx Service (LoadBalancer — xarici access)
apiVersion: v1
kind: Service
metadata:
  name: nginx-service
spec:
  type: LoadBalancer
  selector:
    app: laravel
    component: nginx
  ports:
    - name: http
      port: 80
      targetPort: 80

---
# MySQL Service (ClusterIP — yalnız cluster daxilindən)
apiVersion: v1
kind: Service
metadata:
  name: mysql-service
spec:
  selector:
    app: mysql
  ports:
    - port: 3306
      targetPort: 3306

---
# Redis Service (ClusterIP)
apiVersion: v1
kind: Service
metadata:
  name: redis-service
spec:
  selector:
    app: redis
  ports:
    - port: 6379
      targetPort: 6379
```

### SSL/TLS Ingress (cert-manager ilə)

```bash
# cert-manager quraşdırma
kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.14.0/cert-manager.yaml
```

```yaml
# Let's Encrypt ClusterIssuer
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: admin@example.com
    privateKeySecretRef:
      name: letsencrypt-prod-key
    solvers:
      - http01:
          ingress:
            class: nginx

---
# TLS Ingress
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: laravel-tls-ingress
  annotations:
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
    nginx.ingress.kubernetes.io/ssl-redirect: "true"
spec:
  ingressClassName: nginx
  tls:
    - hosts:
        - app.example.com
      secretName: app-tls-secret
  rules:
    - host: app.example.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: nginx-service
                port:
                  number: 80
```

### Service Debugging

```bash
# Service endpoint-lərini yoxla
kubectl get endpoints laravel-service
# Endpoint boşdursa → selector label-lar uyğun gəlmir

# Service-dən Pod-a trafikin getdiyini yoxla
kubectl run test --rm -it --image=busybox -- sh
wget -qO- http://laravel-service:9000/health

# DNS resolution yoxla
kubectl run test --rm -it --image=busybox -- nslookup laravel-service

# kube-proxy log-larını yoxla
kubectl logs -n kube-system -l k8s-app=kube-proxy
```

## PHP/Laravel ilə İstifadə

### Laravel .env Service Connection-ları

```yaml
# ConfigMap — Laravel service adları ilə
apiVersion: v1
kind: ConfigMap
metadata:
  name: laravel-services
data:
  DB_HOST: "mysql-service"
  DB_PORT: "3306"
  REDIS_HOST: "redis-service"
  REDIS_PORT: "6379"
  CACHE_DRIVER: "redis"
  SESSION_DRIVER: "redis"
  QUEUE_CONNECTION: "redis"
  MAIL_HOST: "mailhog-service"
  ELASTICSEARCH_HOST: "elasticsearch-service:9200"
```

### Nginx ConfigMap (PHP-FPM Service ilə)

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: nginx-config
data:
  default.conf: |
    upstream php-fpm {
        server php-fpm-service:9000;
    }

    server {
        listen 80;
        server_name _;
        root /var/www/html/public;
        index index.php;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass php-fpm;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }
    }
```

### Laravel Health Check Endpoints

```php
// routes/web.php
Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

Route::get('/ready', function () {
    try {
        DB::connection()->getPdo();
        Redis::ping();
        return response()->json(['status' => 'ready'], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => 'not ready'], 503);
    }
});
```

## İntervyu Sualları

### S1: ClusterIP, NodePort və LoadBalancer arasında fərq nədir?
**C:** ClusterIP — yalnız cluster daxilindən əlçatan (default). NodePort — hər node-da sabit port açır (30000-32767), xaricdən əlçatan. LoadBalancer — cloud provider-in LB-sini yaradır, xarici IP verir. Hər tip əvvəlkini ehtiva edir: LoadBalancer → NodePort → ClusterIP.

### S2: Ingress nədir və Service-dən nə fərqi var?
**C:** Service L4 (TCP/UDP) səviyyəsində işləyir. Ingress L7 (HTTP/HTTPS) səviyyəsində işləyir və host-based routing, path-based routing, SSL termination, URL rewrite təqdim edir. Bir Ingress bir neçə Service-ə traffic yönləndirə bilər. Ingress işləməsi üçün Ingress Controller lazımdır.

### S3: Headless Service nədir və nə vaxt istifadə olunur?
**C:** `clusterIP: None` olan Service-dir. Load balancing etmir, hər Pod üçün ayrıca DNS record yaradır. StatefulSet ilə istifadə olunur — məsələn, MySQL primary/replica-da hər instance-a ayrıca müraciət lazım olduqda. DNS formatı: `<pod-name>.<service>.<namespace>.svc.cluster.local`.

### S4: Kubernetes-də service discovery necə işləyir?
**C:** İki yolla: 1) DNS — CoreDNS hər Service üçün DNS record yaradır (`service.namespace.svc.cluster.local`). Pod-lar Service adı ilə müraciət edə bilər. 2) Environment variables — hər Pod-a mövcud Service-lərin IP və port-ları env var olaraq inject olunur. DNS metodu daha çox istifadə olunur.

### S5: Ingress Controller nədir? Niyə ayrıca quraşdırmaq lazımdır?
**C:** Ingress resource-u yalnız deklarasiya-dır (istəyirik ki, traffic belə yönlənsin). Ingress Controller isə bu qaydaları faktiki olaraq həyata keçirən komponentdir (reverse proxy). K8s default olaraq Controller ilə gəlmir, ayrıca quraşdırılmalıdır. Ən populyar: NGINX Ingress Controller.

### S6: ExternalName Service nə üçün istifadə olunur?
**C:** Cluster daxilindəki tətbiqlərin xarici servislərə (məsələn, RDS database) DNS alias ilə müraciət etməsi üçün. Kod dəyişdirmədən external service endpoint-ini dəyişmək mümkün olur. DNS CNAME record yaradır, heç bir proxy etmir.

### S7: Service-in endpoint-ləri boş olanda nə etmək lazımdır?
**C:** `kubectl get endpoints <service>` boşdursa, Service-in selector-u heç bir Pod-a uyğun gəlmir. Yoxlanmalılar: 1) Service selector label-ları Pod label-ları ilə eyni olmalıdır, 2) Pod-lar `Running` statusda olmalıdır, 3) Pod-ların readiness probe-u keçməlidir, 4) Namespace düzgün olmalıdır.

## Best Practices

1. **Default olaraq ClusterIP istifadə edin** — xarici access lazım deyilsə
2. **Produksiyada LoadBalancer + Ingress** — NodePort istifadə etməyin
3. **Ingress ilə SSL termination edin** — cert-manager + Let's Encrypt
4. **Headless Service StatefulSet üçün** — database, message queue
5. **Service adlarını mənalı qoyun** — `mysql-service`, `redis-service`
6. **Health check endpoint-ləri yaradın** — readiness probe üçün
7. **Named ports istifadə edin** — `name: http`, `name: grpc`
8. **Network Policy ilə traffic-i məhdudlaşdırın** — yalnız lazım olan Service-lər əlçatan olsun
9. **ExternalName ilə xarici servisləri abstrakt edin** — migration asanlaşır
10. **Service annotation-larını bilin** — cloud provider-ə xüsusi konfiqurasiya


## Əlaqəli Mövzular

- [kubernetes-basics.md](18-kubernetes-basics.md) — K8s arxitekturası
- [networking.md](08-networking.md) — Docker şəbəkə arxitekturası
- [reverse-proxy-traefik-nginx-docker.md](39-reverse-proxy-traefik-nginx-docker.md) — Ingress/proxy setup
- [kubernetes-helm.md](23-kubernetes-helm.md) — Helm chart-larda service konfiq
