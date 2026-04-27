# Security in CI/CD Pipeline (Lead ⭐⭐⭐⭐)

## İcmal
Security in CI/CD — "Shift Left Security" yanaşması ilə təhlükəsizlik yoxlamalarını deployment-dan əvvəl, development prosesinin ən erkən mərhələsinə gətirməkdir. DevSecOps adlanan bu yanaşma code commit-dən production-a qədər hər addımda avtomatik security gate-lər qoyur. Interview-da bu mövzu Lead developer-ın komanda və proses səviyyəsindəki security düşüncəsini, pipeline dizaynını nə dərəcədə başa düşdüyünü yoxlayır.

## Niyə Vacibdir
Breach-lərin böyük hissəsi kod yazılarkən tətbiq olunan zəifliklərdən qaynaqlanır. Production-da tapılan security bug-u düzəltmək development zamanı tapılandan 30x baha başa gəlir. CI/CD pipeline-da avtomatik security gate-lər olmadan hər deployment potensial risk daşıyır. İnterviewerlər Lead/Senior candidate-dən yalnız texniki biliyi deyil, security prosesini komandaya necə tətbiq etdiyini, false positive-ləri necə idarə etdiyini, pipeline-ı yavaşlatmadan security-ni necə qoruduğunu bilmək istəyir.

## Əsas Anlayışlar

**Shift Left Security:**
- **SAST (Static Application Security Testing)**: Kodu işə salmadan analiz edir — SQL injection, XSS, hardcoded credential axtarır. Semgrep, SonarQube, Snyk Code
- **DAST (Dynamic Application Security Testing)**: Çalışan tətbiqi test edir — OWASP ZAP, Burp Suite, Nuclei. Staging mühitinə qarşı avtomatik scan
- **IAST (Interactive Application Security Testing)**: SAST + DAST kombinasiyası — kod çalışarkən agent vasitəsilə analiz
- **SCA (Software Composition Analysis)**: Third-party dependency-lərdəki CVE-ləri axtarır — Snyk, Dependabot, OWASP Dependency-Check

**Secret Detection:**
- **Secret scanning**: Code-a commit edilmiş API key, password, token axtarır — GitGuardian, TruffleHog, Gitleaks, GitHub Secret Scanning
- **Pre-commit hooks**: Local maşında commit edilməzdən əvvəl secret yoxlaması — detect-secrets, pre-commit framework
- **Git history scan**: Köhnə commit-lərdə saxlanmış secret-lər — silmək üçün `git filter-branch`, BFG Repo Cleaner

**Container Security:**
- **Container image scanning**: Dockerfile-dakı base image, quraşdırılmış paket CVE-ləri — Trivy, Snyk Container, Anchore, Clair
- **Distroless/minimal base images**: `debian:slim`, `alpine`, `gcr.io/distroless` — attack surface azaldır
- **Non-root container**: Container root kimi işləməsin — `USER 1000` Dockerfile-da
- **Image signing**: Signed image-ların deploy edilməsi — Cosign, Notary (supply chain security)

**Infrastructure as Code (IaC) Security:**
- **IaC scanning**: Terraform, Ansible, Helm chart-larındakı security misconfiguration — Checkov, tfsec, kics
- **Policy as Code**: OPA (Open Policy Agent), Kyverno — Kubernetes-ə deploy ediləcək resursların policy uyğunluğunu yoxlamaq

**Pipeline security:**
- **Pipeline least privilege**: CI/CD runner yalnız lazım olan icazələrə malik olsun — production database-ə birbaşa çatış yoxdur
- **OIDC-based authentication**: CI/CD-dən cloud-a uzunmüddətli key əvəzinə OIDC token — GitHub Actions + AWS OIDC
- **Artifact signing**: Build artifact-larının imzalanması — SLSA (Supply chain Levels for Software Artifacts) framework
- **Environment separation**: Dev, staging, production mühitlərinin tamamilə ayrı credential-ları
- **Approval gates**: Production-a deploy üçün insan təsdiqi — automated security pass + manual approval
- **Dependency pinning**: `composer.lock`, `package-lock.json`, `go.sum` — supply chain attack-ların qarşısı
- **SBOM (Software Bill of Materials)**: Tətbiqin bütün dependency-lərinin inventarı — SPDX, CycloneDX format

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Bu mövzuda yalnız tool adları saymaq kifayət deyil. Güclü cavab pipeline-ın hər addımında konkret gate-in nə iş gördüyünü, false positive-lərin komandanı necə yavaşlatdığını və bunu necə balansladığınızı izah edir. "Security-ni pipeline-da məcburi etmək vs developer experience" tradeoff-unu müzakirə etmək Lead səviyyəsinin əlamətidir.

**Hansı konkret nümunələr gətirmək:**
- "Biz Semgrep ilə SAST etdik, ilk həftə 200+ issue tapıldı — hamısını blocker etmədik, kritikləri blocker, qalanlarını warning etdik"
- "Dependabot PR-ları avtomatik açır, security patch-lar üçün auto-merge aktivdir — lakin major version bump-lar manual review tələb edir"
- "GitHub Actions OIDC ilə AWS-ə autentifikasiya edirik — heç bir long-lived credential saxlanmır"

**Follow-up suallar:**
- "Security tool çox false positive verirsə nə edərsiniz?"
- "Developer security check-i bypass etməyə çalışırsa nə edərsiniz?"
- "Supply chain attack (SolarWinds kimi) pipline-ınıza nə dərəcədə təsir edə bilər?"

**Red flags (pis cavab əlamətləri):**
- "Security-ni production-da QA test edir" — shift left anlayışı yoxdur
- Pipeline-da hardcoded secret-lər (`AWS_SECRET_ACCESS_KEY=abc123`)
- Bütün security check-ləri blocker etmək — developer-lar bypass yolları axtarar
- "Bizim pipeline-da security tool yoxdur" — komanda lead-i üçün ciddi red flag

## Nümunələr

### Tipik Interview Sualı
"Sıfırdan bir CI/CD pipeline qurursunuz. Security-ni bu pipeline-a necə inteqrasiya edərdiniz? Hansı mərhələdə hansı yoxlamaları aparardınız?"

### Güclü Cavab
"Security pipeline-ı shift left prinsipinə görə qurardım — hər problem nə qədər erkən tutulsa, düzəltmə o qədər ucuz olur.

**Developer maşınında (pre-commit):**
`pre-commit` hook-ları ilə secret scanning (detect-secrets) və basic linting. Commit-dən əvvəl developer öz maşınında yoxlayır — CI-ya getmədən.

**PR açılanda (PR checks):**
SAST — Semgrep ilə custom rule-lar. SCA — Snyk ilə dependency vulnerability scan. Secret scanning — ikinci layer olaraq. Bu stage-ləri non-blocking warning kimi başlayardım, 2 həftə sonra kritik severity-ləri blocker edərdim.

**Merge sonrası (CI):**
Container image scan — Trivy ilə base image CVE-ləri. IaC scan — Checkov ilə Terraform. SBOM generation.

**Staging deploy sonrası:**
DAST — OWASP ZAP ilə avtomatik scan. Performance + security regression test-ləri.

**Production-a deploy üçün:**
Approval gate — security scan pass + manual lead/architect təsdiqi. Signed artifact-ların deploy edilməsi.

Tool seçimində developer experience vacibdir — çox yavaş ya çox false positive olan tool komanda tərəfindən bypass edilir."

### Konfiqurasiya / Kod Nümunəsi

**GitHub Actions — security pipeline:**
```yaml
# .github/workflows/security.yml
name: Security Pipeline

on:
  pull_request:
  push:
    branches: [main, develop]

permissions:
  contents: read
  security-events: write

jobs:
  secret-scan:
    name: Secret Detection
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0  # Git history-ni də tara

      - name: TruffleHog secret scan
        uses: trufflesecurity/trufflehog@main
        with:
          path: ./
          base: ${{ github.event.repository.default_branch }}
          extra_args: --only-verified

  sast:
    name: Static Analysis (SAST)
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Semgrep SAST
        uses: semgrep/semgrep-action@v1
        with:
          config: >-
            p/php
            p/laravel
            p/owasp-top-ten
        env:
          SEMGREP_APP_TOKEN: ${{ secrets.SEMGREP_APP_TOKEN }}

  sca:
    name: Dependency Vulnerability (SCA)
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Snyk PHP vulnerability scan
        uses: snyk/actions/php@master
        env:
          SNYK_TOKEN: ${{ secrets.SNYK_TOKEN }}
        with:
          args: --severity-threshold=high  # Yalnız high/critical blocker

  container-scan:
    name: Container Image Scan
    runs-on: ubuntu-latest
    needs: [secret-scan, sast]
    steps:
      - uses: actions/checkout@v4

      - name: Build image
        run: docker build -t app:${{ github.sha }} .

      - name: Trivy container scan
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: app:${{ github.sha }}
          format: sarif
          output: trivy-results.sarif
          severity: CRITICAL,HIGH
          exit-code: '1'  # CRITICAL/HIGH-da pipeline-ı dayandır

      - name: Upload results to GitHub Security
        uses: github/codeql-action/upload-sarif@v3
        with:
          sarif_file: trivy-results.sarif

  iac-scan:
    name: IaC Security Scan
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Checkov Terraform scan
        uses: bridgecrewio/checkov-action@master
        with:
          directory: infrastructure/
          framework: terraform
          soft_fail: false
          check: CKV_AWS_*  # Yalnız AWS security check-ləri

  production-deploy:
    name: Deploy to Production
    runs-on: ubuntu-latest
    needs: [secret-scan, sast, sca, container-scan, iac-scan]
    environment:
      name: production
      url: https://app.example.com
    # GitHub Environment protection rules: required reviewers = 2
    steps:
      - name: AWS OIDC authentication (long-lived key yoxdur)
        uses: aws-actions/configure-aws-credentials@v4
        with:
          role-to-assume: arn:aws:iam::123456789:role/github-actions-deploy
          aws-region: us-east-1

      - name: Deploy
        run: |
          # Deploy skriptlər
          echo "Deploying signed artifact..."
```

**Pre-commit konfiqurasiyası:**
```yaml
# .pre-commit-config.yaml
repos:
  - repo: https://github.com/Yelp/detect-secrets
    rev: v1.4.0
    hooks:
      - id: detect-secrets
        args: ['--baseline', '.secrets.baseline']

  - repo: https://github.com/trufflesecurity/trufflehog
    rev: v3.63.0
    hooks:
      - id: trufflehog
        name: TruffleHog secret scan
        entry: trufflehog git file://. --only-verified
        language: system
        pass_filenames: false

  - repo: https://github.com/returntocorp/semgrep
    rev: v1.45.0
    hooks:
      - id: semgrep
        args: ['--config', 'p/php', '--error']
```

**SBOM generation:**
```yaml
  sbom:
    name: Generate SBOM
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Generate SBOM with Syft
        uses: anchore/sbom-action@v0
        with:
          image: app:${{ github.sha }}
          format: cyclonedx-json
          output-file: sbom.cyclonedx.json

      - name: Attest SBOM
        uses: actions/attest-sbom@v1
        with:
          subject-name: app
          sbom-path: sbom.cyclonedx.json
```

## Praktik Tapşırıqlar

**Özünütəst sualları:**
- Mövcud CI/CD pipeline-ınızda SAST, SCA, secret scanning var? Hansı tool-lar istifadə olunur?
- CI/CD runner-ın production-a hansı icazələri var? Bu icazələr PoLP-a uyğundur?
- Yeni kritik CVE (CVSS 9.0+) tapıldıqda pipeline-ınız avtomatik xəbərdar edirmi?
- Developer bir security check-i `# nosec` ilə keçmişdir — bunu necə idarə edirsiniz?

**Scenarios to think through:**
- Komandanın 10 developer-i var, hamısı CI security check-lərini "çox yavaşladır" deyə şikayət edir. Nə edərdiniz?
- NPM/Composer package-i ele keçirildi (supply chain attack). Pipeline-ınız bunu detect edə bilirmi?
- Security team yeni Semgrep rule əlavə etdi, 500+ false positive verdi. Komanda pipeline-ı bypass etməyə başladı. Problemi necə həll edərdiniz?
- DAST scan staging-də 3 critical tapdı, deployment window 2 saatdır. Qərar prosesiniz necə olardı?

## Əlaqəli Mövzular
- `08-secrets-management.md` — Pipeline-da secret idarəsi CI/CD security-nin əsas hissəsidir
- `11-least-privilege.md` — CI/CD runner icazələrinin PoLP prinsipi ilə qurulması
- `12-audit-logging.md` — Deploy event-lərinin audit edilməsi
- `13-data-encryption.md` — Container image-larda, artifact-larda şifrələmə
- `15-threat-modeling.md` — Supply chain threat-ları CI/CD pipeline threat model-inin əsas vektoru
