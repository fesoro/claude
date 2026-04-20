# Set Up / Install / Configure / Deploy / Provision — Qurmaq Fellər

## Səviyyə
B1-B2 (tech kontekstdə vacib!)

---

## Əsas Cədvəl

| Söz | Kontekst |
|-----|----------|
| **set up** | qur / hazır et (ümumi) |
| **install** | yüklə (proqram) |
| **configure** | tənzim et (settings) |
| **deploy** | production-a çıxar |
| **provision** | resurs ayır |
| **launch** | işə sal (app) |
| **run** | işlət |

> **Qısa qayda:**
> - **set up** = qur (ümumi)
> - **install** = yüklə (proqram, fiziki şey)
> - **configure** = parametrlər / settings
> - **deploy** = production-a çıxar
> - **provision** = cloud resurs ayır

---

## 1. Set Up — Qurmaq (Ümumi)

Ən ümumi. Hər şey üçün.

### Nümunələr

- **Set up** a new laptop.
- **Set up** a meeting.
- **Set up** the environment.
- **Set up** the database.

### Struktur

- **set up** + N
- **set up** V-ing

### İsim

- **Setup** (bir söz isim).
- A **setup** guide.

### Tech kontekst

- **Set up** dev environment. ✓
- **Set up** CI/CD. ✓
- **Set up** monitoring. ✓
- **Set up** a new project. ✓

### Set up vs Install

- **set up** = ümumi (yüklə + konfig + test)
- **install** = yalnız yüklə

- "**Install** Docker." (just install)
- "**Set up** Docker." (install + configure + verify)

---

## 2. Install — Yüklə (Proqram)

Fiziki və ya proqram yükləmə.

### Nümunələr

- **Install** Node.js.
- **Install** the package.
- **Install** a printer.
- **Install** a library.

### Tech kontekst

- `npm install`
- `pip install`
- **Install** dependencies.
- **Install** Docker.
- **Install** a plugin.

### Install vs Set up

- **install** = tək addım (yüklə)
- **set up** = bütün proses (install + configure)

- "I **installed** Python." (bir addım)
- "I **set up** my Python environment." (install + packages + config)

### İsim

- **Installation** = yüklənmə.
- Fresh **installation**.
- **Installer** = quraşdırıcı.

---

## 3. Configure — Tənzim Et

Parametrləri / settings-i təyin et.

### Nümunələr

- **Configure** the settings.
- **Configure** the server.
- **Configure** the API keys.
- **Configure** environment variables.

### Tech kontekst

- **Configure** nginx.
- **Configure** webpack.
- **Configure** the database.
- **Config** file (short).

### Install vs Configure

- **install** = yüklə (fayllar)
- **configure** = parametrləri təyin et

Sıra:
1. **Install** the software.
2. **Configure** it for your needs.
3. Test.

- "I **installed** MySQL and **configured** it." ✓

### İsim

- **Configuration** = konfiqurasiya.
- **Config** = kısa forma.
- Configuration **file**.

---

## 4. Deploy — Production-a Çıxar

Kodu / tətbiqi aktiv etmək.

### Nümunələr

- **Deploy** to production.
- **Deploy** the app.
- **Deploy** the update.

→ Ətraflı: [deploy-ship-release-rollout.md](deploy-ship-release-rollout.md)

### Set up vs Deploy

- **set up** = develop / configure stage
- **deploy** = production-a çıx

- "**Set up** the CI/CD pipeline." (build sistem)
- "**Deploy** via the pipeline." (production-a)

---

## 5. Provision — Resurs Ayır

Cloud / infrastructure. Resurs yaratmaq.

### Nümunələr

- **Provision** a server.
- **Provision** cloud resources.
- **Provision** a database.
- **Provisioning** script.

### Set up vs Provision

- **set up** = ümumi
- **provision** = specific cloud resurs

- "**Provision** an EC2 instance." (AWS)
- "**Set up** the application on it." (sonra)

### Sequence

1. **Provision** infrastructure (Terraform)
2. **Install** software
3. **Configure** settings
4. **Deploy** the app

---

## 6. Launch — İşə Sal

App / product başlatmaq.

### Nümunələr

- **Launch** the app.
- **Launch** the product.
- **Launch** a campaign.

### Tech kontekst

- **Launch** a new service.
- Product **launch**.

→ [deploy-ship-release-rollout.md](deploy-ship-release-rollout.md)

---

## 7. Run — İşlət

Aktiv et.

### Nümunələr

- **Run** the server.
- **Run** the tests.
- **Run** a query.
- **Run** the command.

### Configure vs Run

- **configure** = tənzim et
- **run** = icra et

- **Configure** then **run**.

---

## Tam Sıra — Real Nümunə

**Deploy a web app:**

1. **Provision** cloud infrastructure (Terraform)
2. **Install** dependencies (npm install)
3. **Configure** environment variables (.env file)
4. **Set up** the database (migrations, seeds)
5. **Run** tests
6. **Build** the app
7. **Deploy** to production
8. **Launch** / announce

---

## Test

Hansı söz uyğundur?

1. Let me ______ Docker first. (yüklə)
2. ______ the nginx.conf file. (parametr)
3. ______ a development environment. (ümumi)
4. ______ to production after testing. (production)
5. ______ an AWS EC2 instance. (cloud resurs)

**Cavablar:**
1. install
2. Configure
3. Set up
4. Deploy
5. Provision

---

## Tech / İş Kontekstində

### Set up (ümumi)

- "**Set up** the dev environment." ✓
- "**Set up** monitoring." ✓
- "**Set up** a new service." ✓

### Install

- "**Install** dependencies." ✓
- "**Install** Docker." ✓
- `npm install`, `pip install`.

### Configure

- "**Configure** nginx." ✓
- "**Configure** CI/CD." ✓
- "**Configure** environment variables." ✓

### Deploy

- "**Deploy** to staging." ✓
- "**Deploy** via GitHub Actions." ✓

### Provision

- "**Provision** infrastructure with Terraform." ✓
- "**Provisioning** script." ✓

### Run

- "**Run** the tests." ✓
- "**Run** the migration." ✓

---

## Interview Nümunələri

- "I **set up** the development environment for the team." ✓
- "I **configure** CI/CD pipelines." ✓
- "I **deployed** microservices to K8s." ✓
- "I **provisioned** cloud infra with Terraform." ✓

---

## Common Phrasal / Compound

### Setup guide

- "Follow the **setup guide**."

### Installation wizard

- "Run the **installation wizard**."

### Config file

- "Edit the **config file**."

### Deploy pipeline

- "CI/CD **deploy pipeline**."

### Provisioning script

- "Write a **provisioning script**."

---

## Azərbaycanlı Səhvləri

- ✗ I installed the config. (configure)
- ✓ I **configured** it.

- ✗ Setup new environment. (fel forması = set up iki söz)
- ✓ **Set up** new environment. (fel)
- ✓ **Setup** of the environment. (isim - bir söz)

- ✗ Install the server. (provision?)
- ✓ **Provision** the server. (cloud)
- ✓ **Install** software on the server.

---

## Set Up vs Setup (Yazı Qaydası)

| Forma | Tipi |
|-------|------|
| **set up** (two words) | fel |
| **setup** (one word) | isim / sifət |

- I **set up** the config. (fel — iki söz)
- Follow the **setup** guide. (isim — bir söz)
- Easy **setup**. (sifət)

---

## Xatırlatma

| Söz | Bir sözdə |
|-----|-----------|
| **set up** | qur (ümumi) |
| **install** | yüklə (software) |
| **configure** | tənzim (settings) |
| **deploy** | production-a çıxar |
| **provision** | cloud resurs ayır |
| **launch** | işə sal (product) |
| **run** | icra et |

**Tech sequence:**
Provision → Install → Configure → Set up → Deploy → Run.

→ Related: [deploy-ship-release-rollout.md](deploy-ship-release-rollout.md), [devops-vocabulary.md](../../vocabulary/topics/devops-vocabulary.md)
