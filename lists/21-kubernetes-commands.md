## Context / namespace

kubectl config view — kubeconfig göstər
kubectl config get-contexts — bütün context-lər
kubectl config current-context — aktiv context
kubectl config use-context <name> — context dəyiş
kubectl config set-context --current --namespace=<ns> — default namespace
kubectl config rename-context old new
kubectl config delete-context <name>
kubectl get namespaces / ns
kubectl create namespace my-ns
kubectl delete namespace my-ns
kubectx / kubens — context/namespace switcher (3rd-party)
KUBECONFIG env var — çoxlu kubeconfig faylı birləşdir

## Get / list

kubectl get all — bütün standard resurslar
kubectl get pods — işləyən pod-lar
kubectl get pods -A / --all-namespaces
kubectl get pods -n <ns>
kubectl get pods -o wide — node, IP, container
kubectl get pods -o yaml / -o json
kubectl get pods -l app=web — label selector
kubectl get pods --field-selector status.phase=Running
kubectl get pods --sort-by=.metadata.creationTimestamp
kubectl get pods -w / --watch — real-time
kubectl get pod <name> -o jsonpath='{.status.phase}'
kubectl get pod <name> -o custom-columns=NAME:.metadata.name,STATUS:.status.phase
kubectl get deploy / svc / ingress / configmap / secret / pvc / pv / node / sa / job / cronjob / statefulset / daemonset / hpa / pdb
kubectl api-resources — mövcud resurs tipləri
kubectl api-versions — API version-lar

## Describe / inspect

kubectl describe pod <name>
kubectl describe node <name>
kubectl describe deployment <name>
kubectl explain pod — schema doc
kubectl explain pod.spec.containers — nested
kubectl explain pod --recursive

## Logs

kubectl logs <pod> — default container
kubectl logs <pod> -c <container> — multi-container
kubectl logs <pod> -f / --follow — tail -f
kubectl logs <pod> --tail=100
kubectl logs <pod> --since=1h
kubectl logs <pod> -p / --previous — crash-dan əvvəlki container
kubectl logs -l app=web --max-log-requests=10 — label ilə çoxlu
kubectl logs deploy/<name> — deployment logs
stern <pattern> — 3rd-party multi-pod tail
kubetail — oxşar

## Exec / port-forward / cp

kubectl exec -it <pod> -- /bin/sh — interactive shell
kubectl exec <pod> -- ls /app — tək komanda
kubectl exec <pod> -c <container> -- env
kubectl port-forward <pod> 8080:80 — localhost:8080 → pod:80
kubectl port-forward svc/<name> 8080:80 — service
kubectl port-forward deploy/<name> 8080
kubectl port-forward --address 0.0.0.0 svc/x 8080:80 — hər IP
kubectl cp <pod>:/src /local/dst — pod-dan kopya
kubectl cp /local/src <pod>:/dst — pod-a kopya
kubectl cp <pod>:/src /local -c <container>
kubectl attach <pod> -c <container> — running process-ə qoşul
kubectl debug <pod> --image=busybox -it — ephemeral debug container (1.25+)
kubectl debug node/<node> -it --image=ubuntu

## Apply / create / delete

kubectl apply -f file.yaml — declarative (server-side)
kubectl apply -f dir/ -R — recursive
kubectl apply -k overlays/prod — kustomize
kubectl create -f file.yaml — imperative
kubectl create deployment web --image=nginx --replicas=3
kubectl create configmap my-cm --from-file=./config.yaml
kubectl create configmap my-cm --from-literal=key=value
kubectl create secret generic my-s --from-literal=pwd=abc
kubectl create secret tls my-tls --cert=crt.pem --key=key.pem
kubectl create secret docker-registry my-reg --docker-server=... --docker-username=... --docker-password=...
kubectl create job my-job --image=busybox -- echo hi
kubectl create cronjob my-cron --image=busybox --schedule="0 * * * *" -- echo hi
kubectl delete -f file.yaml
kubectl delete pod <name>
kubectl delete pod <name> --force --grace-period=0 — zorla
kubectl delete all -l app=web — label ilə
kubectl delete ns <name> — cascade delete

## Edit / patch / replace

kubectl edit deploy <name> — $EDITOR aç
kubectl edit deploy <name> -o yaml
kubectl patch deploy <name> -p '{"spec":{"replicas":5}}'
kubectl patch deploy <name> --type=json -p='[{"op":"replace","path":"/spec/replicas","value":5}]'
kubectl patch deploy <name> --type=strategic -p '{"spec":{"template":{"spec":{"containers":[{"name":"web","image":"nginx:1.25"}]}}}}'
kubectl replace -f file.yaml — köhnəni əvəzlə
kubectl replace --force -f file.yaml — sil + yarat (downtime)
kubectl set image deploy/<name> <container>=<image>:<tag>
kubectl set env deploy/<name> KEY=VALUE
kubectl set resources deploy/<name> --limits=cpu=1,memory=512Mi
kubectl annotate pod <name> key=value
kubectl label pod <name> env=prod
kubectl label pod <name> env- — label sil

## Scale / rollout

kubectl scale deploy <name> --replicas=5
kubectl scale --current-replicas=3 --replicas=5 deploy/<name>
kubectl autoscale deploy <name> --min=2 --max=10 --cpu-percent=80 — HPA yarat
kubectl rollout status deploy/<name> — deployment gözlə
kubectl rollout history deploy/<name> — revision-lar
kubectl rollout history deploy/<name> --revision=2
kubectl rollout undo deploy/<name> — əvvəlki revision-a
kubectl rollout undo deploy/<name> --to-revision=2
kubectl rollout restart deploy/<name> — pod-ları yenidən yarat
kubectl rollout pause deploy/<name> — canary üçün
kubectl rollout resume deploy/<name>

## Events / top / troubleshoot

kubectl get events — bütün event
kubectl get events --sort-by=.metadata.creationTimestamp
kubectl get events -n <ns> --field-selector type=Warning
kubectl get events --for=pod/<name>
kubectl top node — CPU/memory (metrics-server lazımdır)
kubectl top pod / pods
kubectl top pod --containers
kubectl top pod -l app=web --sort-by=cpu
kubectl get pod <name> -o yaml | less — raw spec + status
kubectl get componentstatuses (deprecated)
kubectl cluster-info / cluster-info dump
kubectl version — client + server
kubectl auth can-i create pods — RBAC check
kubectl auth can-i --list — hər şey
kubectl auth can-i get pods --as=user@example.com --namespace=default — impersonate

## Node management

kubectl cordon <node> — yeni pod schedule etmə
kubectl uncordon <node>
kubectl drain <node> --ignore-daemonsets --delete-emptydir-data — pod-ları köçür
kubectl taint node <name> key=value:NoSchedule
kubectl taint node <name> key:NoSchedule- — taint sil

## Diff / dry-run

kubectl diff -f file.yaml — server-side diff
kubectl apply -f file.yaml --dry-run=client -o yaml
kubectl apply -f file.yaml --dry-run=server
kubectl create -f ... --dry-run=client — validate only

## Wait

kubectl wait --for=condition=Ready pod/<name> --timeout=60s
kubectl wait --for=condition=Available deploy/<name> --timeout=300s
kubectl wait --for=delete pod/<name> --timeout=60s
kubectl wait --for=jsonpath='{.status.phase}'=Running pod/<name>

## Certificates / kubeconfig

kubectl config set-credentials <user> --client-certificate=cert --client-key=key
kubectl config set-cluster <name> --server=... --certificate-authority=ca.crt
kubectl config set-context <name> --cluster=... --user=... --namespace=...
kubectl certificate approve <csr>
kubectl get csr — CertificateSigningRequest

## Resources shorthand

po — pods
svc — services
deploy — deployments
rs — replicasets
ds — daemonsets
sts — statefulsets
ing — ingress
cm — configmaps
ns — namespaces
no — nodes
pv / pvc — persistent volume / claim
sa — serviceaccount
ep — endpoints
netpol — networkpolicy
hpa — horizontal pod autoscaler
pdb — pod disruption budget
crd — custom resource definition

## Helm

helm version
helm repo add bitnami https://charts.bitnami.com/bitnami
helm repo update / list / remove
helm search repo <name> / search hub <name>
helm install <release> <chart> — install
helm install <release> <chart> -f values.yaml --set key=value
helm install <release> <chart> --namespace <ns> --create-namespace
helm upgrade <release> <chart> -f values.yaml
helm upgrade --install <release> <chart> — upsert
helm uninstall <release>
helm list / list -A
helm status <release>
helm history <release>
helm rollback <release> <revision>
helm get values <release> / get manifest <release>
helm template <release> <chart> — render YAML
helm pull <chart> --untar
helm create <chart-name> — scaffold
helm package <chart>
helm lint <chart>
helm dep update / dep build — sub-chart
helm show values <chart>
hooks — pre-install, post-install, pre-upgrade, etc.

## Kustomize

kustomize build overlays/prod — render
kubectl apply -k overlays/prod — built-in (1.14+)
kustomization.yaml — resources, patches, configMapGenerator, secretGenerator, namespace, namePrefix, commonLabels, images
overlays pattern — base + dev/staging/prod
strategic merge patch / JSON 6902 patch
components — shareable partial

## Useful plugins (krew)

kubectl krew install <plugin>
kubectl ctx / ns — context/namespace switch (like kubectx/kubens)
kubectl neat — cleaned output (no status/managedFields)
kubectl tree — ownership tree
kubectl tail / stern — multi-pod logs
kubectl images — images in pods
kubectl node-shell — SSH-like to node
kubectl view-secret / view-cert
kubectl whoami
kubectl score — deployment scoring
kubectl popeye — cluster sanity report
kubectl rbac-lookup / who-can — RBAC inspector

## Cluster tools

k9s — terminal UI
Lens / OpenLens — desktop UI
Headlamp — web UI
kubectl-ai / kubectl-neat / kubectl-view-allocations
Velero — backup/restore
ArgoCD / Flux — GitOps
Argo Rollouts — progressive delivery (canary, blue/green)
cert-manager — TLS automation
external-dns — DNS automation
ingress-nginx / traefik / gateway-api
metrics-server — top komandası üçün
Prometheus + Grafana — monitoring
Loki — logs
Jaeger / Tempo — tracing
Istio / Linkerd — service mesh
KEDA — event-driven autoscaling
kustomize / helm — templating
Crossplane — cloud resources via K8s CRDs

## Cluster admin

kubeadm init / join — cluster setup
kubeadm token create / list
kubeadm reset
kubeadm upgrade plan / apply
kubelet — node agent
kube-proxy — network rules
kube-apiserver / controller-manager / scheduler / etcd — control plane
crictl ps / logs / inspect — CRI-O / containerd CLI (no docker)
journalctl -u kubelet -f — kubelet logs
/var/log/pods/ — pod log dizini

## Debugging patterns

CrashLoopBackOff — kubectl logs -p + describe events
ImagePullBackOff — describe → secret, registry
Pending — describe → events (PVC, taints, resources)
OOMKilled — describe → memory limit
Evicted — node resource pressure
Init container failure — kubectl logs <pod> -c <init>
Readiness failing — kubectl describe pod → probe output
DNS — kubectl exec busybox -- nslookup kubernetes.default
Network — kubectl exec -- wget -O- http://svc-name
kubectl debug — ephemeral container inject
