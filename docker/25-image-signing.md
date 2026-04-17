# Docker Image Signing (Supply Chain Security)

## Nədir? (What is it?)

**Image signing** — container image-in originallığını və toxunulmazlığını təsdiq edən kriptoqrafik prosesdir. İmza təsdiq edir ki:
1. İmage doğrulanmış şəxs/təşkilat tərəfindən yaradılıb
2. İmage registry-də dəyişdirilməyib

**SLSA (Supply-chain Levels for Software Artifacts)** və **sigstore** layihələri container təhlükəsizliyinin müasir standartlarıdır.

## Əsas Konseptlər (Key Concepts)

### 1. Niyə İmage Signing?

```
Threat: Registry compromise
┌──────┐     ┌────────────┐     ┌─────────┐
│ Dev  │ ──► │  Registry  │ ──► │ K8s     │
└──────┘     │ (Hacked!)  │     │ Cluster │
             └────────────┘     └─────────┘
                    ↓
             Malicious image
             replaces legitimate
```

İmage signing olmasa:
- Registry hack-lənə bilər
- Man-in-the-middle atakı
- Typosquatting (`nginx` vs `nginxx`)

### 2. Signing Alətləri

| Alət | Təsvir | Status |
|------|--------|--------|
| Docker Content Trust (Notary v1) | Köhnə, TUF əsasında | Deprecated |
| Notary v2 | OCI-native signing | İnkişafda |
| Cosign (sigstore) | Müasir, keyless signing | De-facto standart |
| GPG | Generic GPG imzalama | Universal |

### 3. Cosign (Sigstore)

**Sigstore** — açıq mənbəli supply chain security layihəsidir. 3 əsas komponenti:

```
┌─────────────────────────────────┐
│  Cosign (CLI)                    │ ← Signing tool
├─────────────────────────────────┤
│  Fulcio (CA)                     │ ← Certificate authority
├─────────────────────────────────┤
│  Rekor (Transparency Log)        │ ← Immutable log
└─────────────────────────────────┘
```

## Praktiki Nümunələr (Practical Examples)

### Cosign Quraşdırma

```bash
# Linux
curl -O -L "https://github.com/sigstore/cosign/releases/latest/download/cosign-linux-amd64"
sudo mv cosign-linux-amd64 /usr/local/bin/cosign
sudo chmod +x /usr/local/bin/cosign

# macOS
brew install cosign

cosign version
```

### Keyless Signing (OIDC ilə)

```bash
# GitHub/Google identity istifadə edərək (açar yaratmadan)
cosign sign myregistry/laravel:1.0.0

# Browser açılır, Google/GitHub ilə login
# Fulcio qısamüddətli sertifikat verir
# İmza Rekor log-una yazılır
```

Verify:
```bash
# Dərc olunmuş imzanı yoxla
cosign verify myregistry/laravel:1.0.0 \
    --certificate-identity="user@example.com" \
    --certificate-oidc-issuer="https://accounts.google.com"
```

### Key-based Signing (Offline)

```bash
# Açar cütlüyü yarat
cosign generate-key-pair
# cosign.key (private) və cosign.pub (public) yaradılır

# İmage-i imzala
cosign sign --key cosign.key myregistry/laravel:1.0.0

# Yoxla
cosign verify --key cosign.pub myregistry/laravel:1.0.0
```

Environment variable ilə password:
```bash
export COSIGN_PASSWORD=mypassword
cosign sign --key cosign.key myregistry/laravel:1.0.0
```

### Kubernetes Secret ilə Cosign

```bash
# Cosign açarını K8s secret kimi yaradır
cosign generate-key-pair k8s://default/cosign-keys

# İmzala
cosign sign --key k8s://default/cosign-keys myregistry/laravel:1.0.0
```

### SBOM (Software Bill of Materials)

SBOM — image daxilindəki hər paket haqqında metadata-dır.

```bash
# Syft ilə SBOM yarat
syft myregistry/laravel:1.0.0 -o spdx-json > sbom.spdx.json

# Cosign ilə SBOM attest et
cosign attest --predicate sbom.spdx.json \
    --type spdx \
    --key cosign.key \
    myregistry/laravel:1.0.0

# Yoxla
cosign verify-attestation --key cosign.pub \
    --type spdx \
    myregistry/laravel:1.0.0 | jq .
```

### Vulnerability Attestation

```bash
# Trivy ilə vulnerability scan
trivy image --format cyclonedx -o vuln.json myregistry/laravel:1.0.0

# Attest et
cosign attest --predicate vuln.json \
    --type vuln \
    --key cosign.key \
    myregistry/laravel:1.0.0
```

### Policy Enforcement (Kyverno)

K8s-də yalnız imzalanmış image-lərin işləməsinə icazə:

```yaml
apiVersion: kyverno.io/v2beta1
kind: ClusterPolicy
metadata:
  name: check-image
spec:
  validationFailureAction: enforce
  background: false
  rules:
    - name: verify-signatures
      match:
        any:
          - resources:
              kinds:
                - Pod
      verifyImages:
        - imageReferences:
            - "myregistry/laravel:*"
          attestors:
            - entries:
                - keys:
                    publicKeys: |-
                      -----BEGIN PUBLIC KEY-----
                      MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE...
                      -----END PUBLIC KEY-----
```

Pod imzalanmayıbsa, admission controller rədd edir.

## PHP/Laravel ilə İstifadə

### CI/CD-də Laravel Image Signing

**GitHub Actions:**

```yaml
name: Build and Sign
on: push

jobs:
  build:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write
      id-token: write  # Keyless signing üçün vacib
    steps:
      - uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Install Cosign
        uses: sigstore/cosign-installer@v3

      - name: Install Syft (SBOM)
        uses: anchore/sbom-action/download-syft@v0

      - name: Install Trivy
        uses: aquasecurity/setup-trivy@v0.2.0

      - name: Login to GHCR
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Build and Push
        id: build
        uses: docker/build-push-action@v5
        with:
          push: true
          tags: ghcr.io/${{ github.repository }}/laravel:${{ github.sha }}

      - name: Sign image
        env:
          IMAGE: ghcr.io/${{ github.repository }}/laravel@${{ steps.build.outputs.digest }}
        run: |
          cosign sign --yes $IMAGE

      - name: Generate SBOM
        env:
          IMAGE: ghcr.io/${{ github.repository }}/laravel@${{ steps.build.outputs.digest }}
        run: |
          syft $IMAGE -o spdx-json > sbom.json
          cosign attest --yes --predicate sbom.json --type spdx $IMAGE

      - name: Vulnerability Scan
        env:
          IMAGE: ghcr.io/${{ github.repository }}/laravel@${{ steps.build.outputs.digest }}
        run: |
          trivy image --format cyclonedx -o vuln.json $IMAGE
          cosign attest --yes --predicate vuln.json --type vuln $IMAGE
```

### Deploy Script ilə Verify

```bash
#!/bin/bash
# deploy.sh

IMAGE="ghcr.io/myorg/laravel:1.0.0"

echo "Verifying image signature..."
cosign verify $IMAGE \
    --certificate-identity-regexp='https://github.com/myorg/.*' \
    --certificate-oidc-issuer='https://token.actions.githubusercontent.com' \
    || exit 1

echo "Verifying SBOM..."
cosign verify-attestation $IMAGE \
    --type spdx \
    --certificate-identity-regexp='https://github.com/myorg/.*' \
    --certificate-oidc-issuer='https://token.actions.githubusercontent.com' \
    || exit 1

echo "Signature verified. Deploying..."
kubectl apply -f k8s/
```

### SLSA Provenance

SLSA — build-in necə baş verdiyini kriptoqrafik olaraq təsdiq edir.

```yaml
# GitHub Actions-da SLSA provenance
- name: Generate SLSA provenance
  uses: slsa-framework/slsa-github-generator/.github/workflows/generator_container_slsa3.yml@v1.9.0
  with:
    image: ghcr.io/${{ github.repository }}/laravel
    digest: ${{ steps.build.outputs.digest }}
    registry-username: ${{ github.actor }}
  secrets:
    registry-password: ${{ secrets.GITHUB_TOKEN }}
```

Verify SLSA:
```bash
slsa-verifier verify-image $IMAGE \
    --source-uri github.com/myorg/myrepo \
    --source-tag v1.0.0
```

## Interview Sualları

**1. İmage signing niyə lazımdır?**
Registry compromise, MITM attack, typosquatting-dən qorunmaq üçün. İmza image-in mənbəyini və toxunulmazlığını təsdiq edir.

**2. Cosign nədir?**
Sigstore layihəsinin container signing aləti. Keyless signing (OIDC ilə) və key-based signing dəstəkləyir. İmzalar OCI registry-də saxlanır.

**3. Keyless signing necə işləyir?**
1. Developer OIDC ilə login olur (Google, GitHub)
2. Fulcio qısamüddətli (10 dəq) sertifikat verir
3. Bu sertifikatla imza edir
4. İmza metadata Rekor transparency log-una yazılır
5. Verify zamanı identity təsdiqlənir

**4. SBOM nədir?**
Software Bill of Materials — image daxilindəki hər paketin siyahısı (ad, versiya, license, hash). Format: SPDX, CycloneDX. Vulnerability tracking üçün kritikdir.

**5. SLSA nə deməkdir?**
Supply-chain Levels for Software Artifacts. Google tərəfindən yaradılmış framework. 4 səviyyə var: L1 (build prosesi), L2 (version control), L3 (isolated builds), L4 (two-person review).

**6. Docker Content Trust və Cosign arasında fərq?**
- DCT (Notary v1): TUF əsasında, kompleks, deprecated
- Cosign: Sigstore əsasında, sadə, OCI-native, keyless signing dəstəkləyir

**7. Rekor nədir?**
Sigstore-un append-only transparency log-dur. Bütün imzalar buraya yazılır — keçmiş yazıları dəyişmək olmaz. Public log olduğu üçün kim nə imzalayıb görmək olar.

**8. Kubernetes-də image signing policy necə tətbiq olunur?**
Kyverno, OPA Gatekeeper, Connaisseur kimi admission controller-lər istifadə olunur. Pod yaradılanda image-in imzası yoxlanır, imzasız pod rədd edilir.

## Best Practices

1. **Keyless signing istifadə et** — OIDC-ilə, açar idarə etməyə ehtiyac yoxdur
2. **SBOM yarat** — hər image üçün syft ilə
3. **Vulnerability attestation** — Trivy ilə scan et və attest et
4. **Admission control** — K8s-də imzasız image-ləri bloklaştır (Kyverno)
5. **Rekor verify** — imzaların transparency log-da olmasını yoxla
6. **SLSA L3 məqsəd götür** — isolated builds, provenance
7. **Image digest istifadə et** — tag yox, `@sha256:abc...` ilə deploy et
8. **Key rotation** — key-based signing-də açarları müntəzəm yenilə
9. **CI-də avtomatlaşdır** — hər build imzalansın, manual proses olmasın
10. **Incident response** — kompromis baş verərsə Rekor log-dan track et
