# Policy as Code (OPA, Rego, Gatekeeper)

## Nədir? (What is it?)

Policy as Code – təşkilatın təhlükəsizlik, compliance, konfiqurasiya qaydalarını **kod** kimi yazmaq, versiyalamaq, test etmək və avtomatik tətbiq etmək yanaşmasıdır. "Production-da S3 bucket public olmamalıdır", "Pod `root` kimi işləməsin", "Bütün resurslar `owner` tag-i ilə olsun" kimi qaydalar PDF-də deyil, `policy.rego` faylında yaşayır və CI/CD, Kubernetes admission, Terraform plan mərhələlərində avtomatik yoxlanır. **OPA (Open Policy Agent)** bu sahənin de-facto standartıdır – CNCF graduated project. Rego – OPA-nın policy dilidir (deklarativ, Datalog-dan ilhamlanıb). Alternativ alətlər: HashiCorp Sentinel (Terraform Enterprise), AWS Config, Azure Policy, Kyverno. Policy as Code "security left shift" mədəniyyətinin təməl sütunudur.

## Əsas Konseptlər (Key Concepts)

### Policy as Code üstünlükləri

```
Ənənəvi             →  Policy as Code
─────────────────────────────────────────
PDF, wiki           →  Git repo
Review manual       →  Automated (CI gate)
Production-da yoxla →  Plan mərhələsində yoxla
Her komanda ayrı    →  Mərkəzi policy library
Audit – skan et     →  Audit – policy log
```

### OPA (Open Policy Agent)

```
Nə ilə inteqrasiya olunur:
- Kubernetes (Admission Controller via Gatekeeper, Kyverno alternativi)
- Terraform (Conftest, plan JSON-u yoxla)
- Envoy/Istio (authz filter)
- HTTP API (microservice authorization)
- Docker, SQL, CI pipeline (Conftest)
- Kafka, SSH, GraphQL

Arxitektura:
- OPA serveri REST API ilə query qəbul edir
- Sidecar olaraq application-a yaxın işləyir
- Policy-lər Rego dilində, bundle kimi yüklənir
- Data (JSON) input – policy qərar verir
```

### Rego dili

```
package kubernetes.admission

# Deny qaydası
deny[msg] {
    input.request.kind.kind == "Pod"
    input.request.object.spec.containers[_].securityContext.privileged == true
    msg := "Privileged container qadağandır"
}

# Əsas konseptlər:
- package        – namespace
- import         – başqa policy-dən istifadə
- rule           – şərtli qərar (`allow`, `deny`, `violation`)
- default        – default dəyər
- variable       – `:=` ilə təyin
- iteration      – `[_]` ilə
- comprehension  – `[x | x := ...]`
- function       – yenidən istifadə oluna bilən məntiq
- built-ins      – strings, json, time, http, crypto
```

### Gatekeeper (Kubernetes)

```
Policy Controller = Kubernetes Admission Webhook + OPA

ConstraintTemplate (policy məntiq – Rego)
         ↓
Constraint (policy instance – parametrlərlə)
         ↓
Admission Request (CREATE/UPDATE)
         ↓
allow yoxsa deny
```

### Conftest

```
Lokal/CI-da hər JSON/YAML faylı Rego ilə yoxla:

conftest test deployment.yaml --policy policy/
conftest test terraform-plan.json --policy policy/terraform/
```

### Sentinel (HashiCorp)

```
Terraform Cloud/Enterprise, Vault, Consul, Nomad üçün.
Rego-dan fərqli dil (imperativ, function-based).
Enforcement levels:
  - advisory     – warn amma icazə ver
  - soft-mandatory – override olunabilən
  - hard-mandatory – override yox
```

## Praktiki Nümunələr

### Rego: Kubernetes Pod Security Policy

```rego
# policies/k8s/pod-security.rego
package kubernetes.pod_security

import future.keywords.contains
import future.keywords.if
import future.keywords.in

# 1) Privileged container qadağan
violation[{"msg": msg}] {
    input.review.object.kind == "Pod"
    container := input.review.object.spec.containers[_]
    container.securityContext.privileged == true
    msg := sprintf("Container %v privileged istifadə edir", [container.name])
}

# 2) runAsNonRoot məcburidir
violation[{"msg": msg}] {
    input.review.object.kind == "Pod"
    not input.review.object.spec.securityContext.runAsNonRoot == true
    msg := "Pod-da spec.securityContext.runAsNonRoot: true olmalıdır"
}

# 3) Image tag 'latest' qadağan
violation[{"msg": msg}] {
    input.review.object.kind == "Pod"
    container := input.review.object.spec.containers[_]
    endswith(container.image, ":latest")
    msg := sprintf("Container %v üçün 'latest' tag qadağandır (%v)", [container.name, container.image])
}

# 4) Icazə verilən registry-lər
allowed_registries := {
    "myregistry.io",
    "ghcr.io/mycompany",
    "123456789012.dkr.ecr.eu-central-1.amazonaws.com",
}

violation[{"msg": msg}] {
    input.review.object.kind == "Pod"
    container := input.review.object.spec.containers[_]
    not registry_allowed(container.image)
    msg := sprintf("Registry icazəli deyil: %v", [container.image])
}

registry_allowed(image) if {
    some registry in allowed_registries
    startswith(image, registry)
}

# 5) Resource limits məcburidir
violation[{"msg": msg}] {
    input.review.object.kind == "Pod"
    container := input.review.object.spec.containers[_]
    not container.resources.limits.memory
    msg := sprintf("Container %v üçün memory limit yoxdur", [container.name])
}

violation[{"msg": msg}] {
    input.review.object.kind == "Pod"
    container := input.review.object.spec.containers[_]
    not container.resources.limits.cpu
    msg := sprintf("Container %v üçün CPU limit yoxdur", [container.name])
}

# 6) HostPath volume qadağan
violation[{"msg": msg}] {
    input.review.object.kind == "Pod"
    volume := input.review.object.spec.volumes[_]
    volume.hostPath
    msg := sprintf("hostPath volume qadağandır: %v", [volume.name])
}
```

### Gatekeeper ConstraintTemplate və Constraint

```yaml
# constraint-template.yaml
apiVersion: templates.gatekeeper.sh/v1
kind: ConstraintTemplate
metadata:
  name: k8srequiredlabels
spec:
  crd:
    spec:
      names:
        kind: K8sRequiredLabels
      validation:
        openAPIV3Schema:
          type: object
          properties:
            labels:
              type: array
              items:
                type: string
  targets:
    - target: admission.k8s.gatekeeper.sh
      rego: |
        package k8srequiredlabels

        violation[{"msg": msg}] {
            required := input.parameters.labels[_]
            not input.review.object.metadata.labels[required]
            msg := sprintf("Label '%v' tələb olunur", [required])
        }
---
# constraint.yaml
apiVersion: constraints.gatekeeper.sh/v1beta1
kind: K8sRequiredLabels
metadata:
  name: must-have-owner-env
spec:
  match:
    kinds:
      - apiGroups: [""]
        kinds: ["Namespace"]
      - apiGroups: ["apps"]
        kinds: ["Deployment"]
  parameters:
    labels: ["owner", "environment", "cost-center"]
```

### Rego: Terraform Plan Validation

```rego
# policies/terraform/aws-s3.rego
package terraform.aws.s3

import future.keywords.contains
import future.keywords.if
import future.keywords.in

# Plan JSON-da yaradılan/dəyişən S3 bucket-lar
s3_buckets[bucket] {
    some resource in input.resource_changes
    resource.type == "aws_s3_bucket"
    resource.change.actions[_] in {"create", "update"}
    bucket := resource.change.after
}

# 1) S3 bucket public olmamalıdır
deny[msg] {
    bucket := s3_buckets[_]
    bucket.acl == "public-read"
    msg := sprintf("S3 bucket %v public-read ACL istifadə edir", [bucket.bucket])
}

# 2) Encryption at-rest məcburidir
deny[msg] {
    resource := input.resource_changes[_]
    resource.type == "aws_s3_bucket_server_side_encryption_configuration"
    count(resource.change.after.rule) == 0
    msg := "S3 bucket-də server-side encryption olmalıdır"
}

# 3) Versiyalama məcburidir production-da
deny[msg] {
    resource := input.resource_changes[_]
    resource.type == "aws_s3_bucket_versioning"
    resource.change.after.versioning_configuration[0].status != "Enabled"
    environment := input.variables.environment.value
    environment == "production"
    msg := "Production S3 bucket-də versioning enabled olmalıdır"
}

# 4) Required tags
required_tags := {"Owner", "Environment", "CostCenter", "Project"}

deny[msg] {
    bucket := s3_buckets[_]
    missing := required_tags - {k | bucket.tags[k]}
    count(missing) > 0
    msg := sprintf("S3 bucket %v tag-lərsizdir: %v", [bucket.bucket, missing])
}
```

### Conftest ilə CI-da Terraform yoxla

```bash
# 1) Terraform plan JSON format
terraform plan -out=plan.tfplan
terraform show -json plan.tfplan > plan.json

# 2) Conftest ilə policy yoxla
conftest test plan.json --policy policies/terraform/ --output table

# 3) Nümunə output:
# FAIL - plan.json - Production S3 bucket-də versioning enabled olmalıdır
# FAIL - plan.json - S3 bucket my-logs tag-lərsizdir: {"CostCenter"}
# 2 tests, 0 passed, 0 warnings, 2 failures
```

### GitHub Actions Pipeline

```yaml
name: Policy Check

on: [pull_request]

jobs:
  opa-check:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup Terraform
        uses: hashicorp/setup-terraform@v3

      - name: Terraform Init & Plan
        run: |
          terraform init
          terraform plan -out=plan.tfplan
          terraform show -json plan.tfplan > plan.json

      - name: Setup Conftest
        run: |
          wget https://github.com/open-policy-agent/conftest/releases/download/v0.50.0/conftest_0.50.0_Linux_x86_64.tar.gz
          tar xzf conftest_0.50.0_Linux_x86_64.tar.gz
          sudo mv conftest /usr/local/bin/

      - name: OPA test (unit tests)
        run: |
          wget https://openpolicyagent.org/downloads/latest/opa_linux_amd64_static
          chmod +x opa_linux_amd64_static
          sudo mv opa_linux_amd64_static /usr/local/bin/opa
          opa test policies/ -v

      - name: Terraform plan validate
        run: conftest test plan.json --policy policies/terraform/ --output github

      - name: K8s manifest validate
        run: conftest test k8s/*.yaml --policy policies/k8s/
```

### Rego Unit Test

```rego
# policies/k8s/pod-security_test.rego
package kubernetes.pod_security

test_deny_privileged {
    violation[_] with input as {
        "review": {
            "object": {
                "kind": "Pod",
                "spec": {
                    "containers": [{
                        "name": "nginx",
                        "image": "nginx:1.25",
                        "securityContext": {"privileged": true}
                    }]
                }
            }
        }
    }
}

test_allow_safe_pod {
    count(violation) == 0 with input as {
        "review": {
            "object": {
                "kind": "Pod",
                "spec": {
                    "securityContext": {"runAsNonRoot": true},
                    "containers": [{
                        "name": "app",
                        "image": "myregistry.io/app:v1.2.3",
                        "resources": {
                            "limits": {"cpu": "500m", "memory": "512Mi"}
                        }
                    }]
                }
            }
        }
    }
}
```

### HashiCorp Sentinel (Terraform Cloud)

```python
# policies/require-tags.sentinel
import "tfplan/v2" as tfplan

required_tags = ["Owner", "Environment", "CostCenter"]

ec2_instances = filter tfplan.resource_changes as _, rc {
    rc.type is "aws_instance" and
    rc.change.actions contains "create"
}

missing_tags = func(instance) {
    tags = keys(instance.change.after.tags)
    missing = []
    for required_tags as t {
        if t not in tags {
            append(missing, t)
        }
    }
    return missing
}

main = rule {
    all ec2_instances as _, instance {
        length(missing_tags(instance)) is 0
    }
}
```

## PHP/Laravel ilə İstifadə

### Laravel API-də OPA ilə autorizasiya

```php
// composer require guzzlehttp/guzzle

// app/Services/OpaAuthorizer.php
namespace App\Services;

use GuzzleHttp\Client;

class OpaAuthorizer
{
    public function __construct(
        private readonly Client $http,
        private readonly string $opaUrl = 'http://opa:8181'
    ) {}

    public function authorize(array $input, string $policyPath): bool
    {
        $response = $this->http->post(
            "{$this->opaUrl}/v1/data/{$policyPath}",
            ['json' => ['input' => $input], 'timeout' => 2]
        );

        $body = json_decode($response->getBody()->getContents(), true);
        return $body['result']['allow'] ?? false;
    }
}

// app/Http/Middleware/OpaAuthorization.php
namespace App\Http\Middleware;

use App\Services\OpaAuthorizer;
use Closure;

class OpaAuthorization
{
    public function __construct(private OpaAuthorizer $opa) {}

    public function handle($request, Closure $next)
    {
        $allowed = $this->opa->authorize([
            'user'   => $request->user()?->toArray() ?? [],
            'action' => $request->method(),
            'path'   => $request->path(),
            'resource' => $request->route()->parameters(),
            'tenant' => $request->header('X-Tenant'),
        ], 'httpapi/authz');

        if (!$allowed) {
            abort(403, 'Policy qadağan etdi');
        }
        return $next($request);
    }
}
```

### Rego Policy: Laravel API

```rego
# policies/httpapi/authz.rego
package httpapi.authz

import future.keywords.if
import future.keywords.in

default allow := false

# Admin-lər hər şey edə bilər (öz tenant-ı daxilində)
allow if {
    input.user.role == "admin"
    input.user.tenant_id == input.tenant
}

# GET endpoint-lər authenticated user-lər üçün
allow if {
    input.action == "GET"
    input.user.id
    startswith(input.path, "api/")
    not starts_with_restricted
}

# User öz resource-u üstündə yaza bilər
allow if {
    input.action in {"PUT", "PATCH", "DELETE"}
    input.path == sprintf("api/users/%v", [input.user.id])
}

# Billing yalnız finance rol-u
allow if {
    startswith(input.path, "api/billing/")
    input.user.role in {"finance", "admin"}
}

starts_with_restricted if {
    startswith(input.path, "api/admin/")
}
```

### Laravel Deployment-da Policy Check

```yaml
# .gitlab-ci.yml
stages: [test, policy, deploy]

opa-test:
  stage: policy
  image: openpolicyagent/opa:latest
  script:
    - opa test policies/ -v

k8s-manifest-check:
  stage: policy
  image: openpolicyagent/conftest:latest
  script:
    - conftest test k8s/*.yaml --policy policies/k8s/
  rules:
    - changes: ["k8s/**/*.yaml"]

terraform-check:
  stage: policy
  image: hashicorp/terraform:1.7
  script:
    - terraform plan -out=tfplan
    - terraform show -json tfplan > plan.json
    - conftest test plan.json --policy policies/terraform/
```

## Interview Sualları (Q&A)

**S1: Policy as Code niyə "sol istiqamət" (shift-left) üçün kritikdir?**
C: Policy-ni **production-da** yoxlamaq çox gec olur – problem artıq deploy edilib. Shift-left: policy-ni **CI pipeline-da** (plan mərhələsində) yoxlayırsan, pull request-də fail olsa, problem production-a çatmır. Developer dərhal feedback alır – "bu S3 bucket public açıq qalacaq, düzəlt". Bu (1) security risk-i azaldır, (2) auditor üçün avtomatik sübut təmin edir, (3) compliance overhead-ını azaldır.

**S2: OPA və Kyverno fərqi nədir?**
C: **OPA (Gatekeeper ilə K8s-də)** – universal policy engine, hər yerdə işləyir (K8s, Terraform, API, Envoy), Rego dili öyrənmək lazımdır. **Kyverno** – **yalnız Kubernetes** üçün, policy-lər YAML-dir (Rego yoxdur), K8s native hiss olunur, validate + mutate + generate hamısı bir yerdə. Kyverno sadədir, yalnız K8s lazımdırsa üstünlük var; OPA multi-platform policy-lər üçün daha güclüdür.

**S3: Gatekeeper-də ConstraintTemplate və Constraint fərqi nədir?**
C: **ConstraintTemplate** – policy məntiq (Rego) və parametr sxemi. **Constraint** – ConstraintTemplate-in instansiyası konkret parametrlərlə. Məsələn, `K8sRequiredLabels` ConstraintTemplate universal məntiqdir "bu label-lar olmalıdır"; sonra `K8sRequiredLabels` kind-ində `must-have-owner` constraint-i konkret `["owner"]` label-ı tələb edir. Bu ayrılıq policy-ni yenidən istifadə etməyə imkan verir.

**S4: Rego-da `deny` və `allow` fərqi nədir?**
C: Hər ikisi rule-dur, konvensiya fərqlidir. **`allow`** – default `false`, yalnız şərt ödənəndə `true` (positive list). **`deny`** – şərt ödənəndə pozuntu qeyd edir, bütün rule-ları toplayır (negative list). Authorization üçün `allow` uyğundur (yalnız icazəli olanlar keçsin), validation üçün `deny[msg]` və ya `violation[{"msg": msg}]` uyğundur (bütün pozuntuları göstər). Gatekeeper `violation` pattern-ini istifadə edir.

**S5: OPA bundle nədir və necə işləyir?**
C: OPA policy və data-nı tar.gz paketində (bundle) paylayır. Bundle HTTP endpoint-dən (S3, Nexus, OCI registry) OPA tərəfindən periodik çəkilir. Faydaları: (1) mərkəzi policy management – bir yerdən paylamaq, (2) versiyalama – SHA check, (3) OPA restart etmədən policy yeniləmək mümkündür, (4) rəqəmsal imza (signed bundle) ilə inteqrity yoxlanışı. CI policy-ləri bundle kimi build edib registry-yə göndərir, OPA avtomatik çəkir.

**S6: Sentinel və OPA arasında fərq nədir?**
C: **Sentinel** – HashiCorp-un proprietary policy engine-i, yalnız Terraform Cloud/Enterprise, Vault, Consul, Nomad ilə işləyir. Öz dili var (imperativ, Python-a bənzər). **OPA** – açıq mənbə, CNCF graduated, hər yerdə (K8s, Docker, API, SQL), Rego deklarativdir. OSS Terraform istifadə edən komanda Conftest/OPA seçir; Terraform Cloud Enterprise paketi alan şirkət Sentinel-ə keçə bilər. OPA ekosistemi daha geniş və vendor-neutraldır.

**S7: Rego-da `comprehension` nədir? Nümunə göstər.**
C: Comprehension – set/array/object generate edən ifadə, SQL-də `SELECT`-ə bənzər. Nümunə:
```rego
# Bütün privileged container adlarını topla
privileged_names := {name |
    container := input.spec.containers[_]
    container.securityContext.privileged == true
    name := container.name
}
```
Bu "bütün container-ləri gəz, privileged olanı süz, adını al, set-ə at" deməkdir. Array (`[x | ...]`), set (`{x | ...}`), object (`{k: v | ...}`) formaları var. Sorgu logic-ini təmiz yazmaq üçün vacibdir.

**S8: Policy-ləri necə test etmək lazımdır?**
C: **Rego unit test** – hər policy faylı üçün `_test.rego` faylı yaz. `test_` ilə başlayan rule-lar avtomatik sınanır:
```rego
test_deny_privileged {
    violation[_] with input as {"review": {"object": {...}}}
}
```
`opa test policies/ -v` əmri ilə icra et. **Coverage** – `opa test --coverage` hansı sətirlərin sınandığını göstərir. CI-da hər commit-də test-lər icra olunmalıdır. İnteqrasiya testləri Conftest ilə real YAML/JSON faylları yoxlayır.

**S9: OPA performance necədir? Admission latency-ə necə təsir edir?**
C: OPA lokalda (in-process və ya sidecar kimi) işləyir, HTTP round-trip olmur. Policy evaluation tipik 1-5ms-dir – admission üçün qəbul olunabiləndir. Optimallaşdırma: (1) policy-ni sadə saxla, loop azalt, (2) `partial evaluation` – dinamik hissələri əvvəlcədən hesabla, (3) bundle-da data böyük olarsa yaddaşa töv, (4) "scope" və "exclude" match ilə lazımsız resource-ları Gatekeeper-ə göndərmə. High-volume klasterlərdə daha sadə policy-lər (Kyverno) üstünlük qazana bilər.

**S10: "Audit" və "enforce" mode fərqi nədir?**
C: **Enforce** – policy pozuntusunda request **rədd edilir** (API qaytarır 403). **Audit** – pozuntu **log edilir** amma request keçir (warn-yalnız). Gatekeeper-də `spec.enforcementAction: warn` və ya `dryrun` audit mode-u verir. Ən yaxşı yanaşma: yeni policy-ni 1-2 həftə audit mode-da işlət, log-ları analiz et, false positive-ləri düzəlt, sonra enforce-ə keçir. Bu mövcud iş yükünü dağıtmadan policy-ni tətbiq etməyə imkan verir.

## Best Practices

1. **Mərkəzi policy library** saxla – Git repo, versiyalama, review.
2. **Policy-lər unit test ilə əhatə olunsun** – `opa test` CI-da məcburi.
3. **Audit mode-dan başla** – yeni policy-ni dərhal enforce etmə, 1-2 həftə müşahidə.
4. **Policy bundle** ilə paylaş – OCI registry, S3, Nexus.
5. **Sensitive data-nı** policy-də sərt kodlama – data dokumenti kimi ayır.
6. **Exception mexanizmi** – `exemption` annotation və ya label-lar üçün dəstək.
7. **Meaningful error mesajları** – developer-ə "niyə" və "necə düzəltmək" söylə.
8. **Policy catalog** – CIS, PCI-DSS, NIST üçün hazır policy-lərdən başla.
9. **Shift-left** – Terraform plan, K8s manifest, IaC faylları CI-da yoxla.
10. **Gatekeeper admission review** + **audit controller** hər ikisini aktiv et (runtime violation-ları da gör).
11. **Minimal match** – policy-ni lazım olmayan resurslar üçün işlətmə (performance).
12. **Policy yeniləmə** `GitOps` axını ilə – PR review, approved olanda auto-deploy.
13. **Metrics və Logs** – Gatekeeper/OPA metrics-lərini Prometheus-da izlə.
14. **RBAC** – kim policy yaza bilər, kim exception verə bilər, ayır.
15. **Regular review** – policy-ləri kvartalda bir dəfə baxış, köhnəlmiş olanları sil.
