# HashiCorp Packer

## Nədir? (What is it?)

HashiCorp Packer – eyni mənbə konfiqurasiyasından çoxlu platforma üçün **identik maşın image-ləri** yaradan açıq mənbəli alətdir. Bir dəfə yazılan HCL/JSON template VM image (AMI, Azure Managed Image, GCP Image, vSphere OVA), container image (Docker) və virtual maşın image (Vagrant box, QEMU) kimi artefaktları paralel şəkildə bişirir. Packer "immutable infrastructure" yanaşmasının təməl alətidir – serverə `apt-get install` etmək əvəzinə serverin özünü (OS + dependencies + app) bir image kimi qablaşdırırsan, sonra o image-dən instance-lar açırsan. Terraform infra yaradır, Packer isə o infra üçün image-ləri hazırlayır. Golden image pipeline-ın əsasıdır.

## Əsas Konseptlər (Key Concepts)

### İş axını (Workflow)

```
Template (.pkr.hcl)
   ↓
Source (builder) – hansı platforma üçün image
   ↓
Build başlayır – müvəqqəti instance açılır
   ↓
Provisioners (shell, Ansible, Chef, Puppet) – konfiqurasiya
   ↓
Post-processors – tag, upload, manifest
   ↓
Artifact – AMI ID, image URI, Docker tag
   ↓
Müvəqqəti instance silinir
```

### Builder-lər (Mənbələr)

```
Cloud builders:
- amazon-ebs / amazon-ebssurrogate / amazon-chroot (AWS AMI)
- azure-arm (Azure Managed Image)
- googlecompute (GCP Image)
- digitalocean, alicloud, oracle-oci, vultr

Virtualization:
- virtualbox-iso / virtualbox-ovf
- vmware-iso / vmware-vmx
- qemu (KVM, raw)
- parallels-iso
- proxmox-iso, hyperone, hyperv-iso

Container:
- docker (Docker image)
- lxc, lxd

On-prem:
- vsphere-iso / vsphere-clone (VMware vSphere)
- null (yalnız provisioner icra et, image qurma)
```

### Provisioners

```
shell             – bash script icra et
shell-local       – host (Packer çalışan maşın) üzərində icra
file              – fayl köçür
ansible / ansible-local – Ansible playbook
chef-client / chef-solo
puppet-masterless / puppet-server
powershell / windows-shell (Windows)
inspec            – compliance test
breakpoint        – debug üçün pause
```

### Post-processors

```
amazon-import     – VMDK → AMI
docker-tag / docker-push
vagrant           – box fayl yarat
compress          – tar.gz/zip
manifest          – build məlumatı JSON-a yaz
shell-local       – son komanda
checksum          – SHA256 hesabla
```

### Variables və Secrets

```
variable (default, type, sensitive)
local (hesablanmış dəyər)
data (dynamic source – AWS AMI filter)
packer.pkrvars.hcl (variable file)
PKR_VAR_xxx env (environment variable)
```

## Praktiki Nümunələr

### AWS AMI Builder (Ubuntu + PHP + Laravel)

```hcl
# laravel-ami.pkr.hcl
packer {
  required_plugins {
    amazon = {
      version = ">= 1.3.0"
      source  = "github.com/hashicorp/amazon"
    }
    ansible = {
      version = ">= 1.1.0"
      source  = "github.com/hashicorp/ansible"
    }
  }
}

variable "region" {
  type    = string
  default = "eu-central-1"
}

variable "php_version" {
  type    = string
  default = "8.3"
}

variable "app_version" {
  type    = string
  default = "v1.0.0"
}

locals {
  timestamp = formatdate("YYYYMMDD-hhmm", timestamp())
  ami_name  = "laravel-${var.app_version}-${local.timestamp}"
}

data "amazon-ami" "ubuntu_2204" {
  filters = {
    name                = "ubuntu/images/hvm-ssd/ubuntu-jammy-22.04-amd64-server-*"
    root-device-type    = "ebs"
    virtualization-type = "hvm"
  }
  most_recent = true
  owners      = ["099720109477"]  # Canonical
  region      = var.region
}

source "amazon-ebs" "laravel" {
  region         = var.region
  instance_type  = "t3.medium"
  ssh_username   = "ubuntu"
  ami_name       = local.ami_name
  ami_description = "Laravel ${var.app_version} on Ubuntu 22.04 (PHP ${var.php_version})"
  source_ami     = data.amazon-ami.ubuntu_2204.id

  tags = {
    Name        = local.ami_name
    Environment = "production"
    App         = "laravel"
    AppVersion  = var.app_version
    BuiltBy     = "packer"
    BuildDate   = local.timestamp
  }

  run_tags = {
    Name = "packer-${local.ami_name}"
  }
}

build {
  name = "laravel"
  sources = ["source.amazon-ebs.laravel"]

  # 1) Fayl köçür
  provisioner "file" {
    source      = "files/nginx.conf"
    destination = "/tmp/nginx.conf"
  }

  provisioner "file" {
    source      = "files/app.tar.gz"
    destination = "/tmp/app.tar.gz"
  }

  # 2) Shell - PHP və Nginx qur
  provisioner "shell" {
    environment_vars = [
      "PHP_VERSION=${var.php_version}",
      "DEBIAN_FRONTEND=noninteractive",
    ]
    scripts = [
      "scripts/00-update.sh",
      "scripts/10-php.sh",
      "scripts/20-nginx.sh",
      "scripts/30-app.sh",
    ]
  }

  # 3) Ansible - confiqurasiya
  provisioner "ansible" {
    playbook_file = "ansible/harden.yml"
    extra_arguments = [
      "--extra-vars", "php_version=${var.php_version}",
    ]
  }

  # 4) Yoxlama
  provisioner "shell" {
    inline = [
      "php -v | grep -q ${var.php_version}",
      "systemctl is-enabled nginx",
      "systemctl is-enabled php${var.php_version}-fpm",
    ]
  }

  # 5) Cleanup
  provisioner "shell" {
    inline = [
      "sudo apt-get clean",
      "sudo rm -rf /var/lib/apt/lists/*",
      "sudo rm -f /root/.ssh/authorized_keys",
      "sudo rm -f /home/ubuntu/.ssh/authorized_keys",
      "history -c",
    ]
  }

  post-processor "manifest" {
    output     = "manifest.json"
    strip_path = true
  }

  post-processor "shell-local" {
    inline = ["echo 'Built AMI ID: {{.ArtifactId}}'"]
  }
}
```

### Shell Scripts

```bash
# scripts/10-php.sh
#!/usr/bin/env bash
set -euxo pipefail

sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update -y
sudo apt-get install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-redis \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-gd \
    composer unzip

sudo systemctl enable php${PHP_VERSION}-fpm
```

### Docker Builder

```hcl
# docker-laravel.pkr.hcl
source "docker" "laravel" {
  image  = "php:8.3-fpm-alpine"
  commit = true
  changes = [
    "WORKDIR /var/www",
    "EXPOSE 9000",
    "CMD [\"php-fpm\"]",
  ]
}

build {
  sources = ["source.docker.laravel"]

  provisioner "shell" {
    inline = [
      "apk add --no-cache git unzip mysql-client redis",
      "docker-php-ext-install pdo_mysql bcmath",
    ]
  }

  provisioner "file" {
    source      = "app/"
    destination = "/var/www"
  }

  provisioner "shell" {
    inline = [
      "cd /var/www && composer install --no-dev --optimize-autoloader",
      "php artisan config:cache",
      "php artisan route:cache",
    ]
  }

  post-processor "docker-tag" {
    repository = "myregistry.io/laravel"
    tags       = ["latest", "v1.0.0"]
  }

  post-processor "docker-push" {
    login          = true
    login_server   = "myregistry.io"
    login_username = "{{ env `REGISTRY_USER` }}"
    login_password = "{{ env `REGISTRY_PASS` }}"
  }
}
```

### Multi-Provider Build (AWS + Azure + GCP)

```hcl
source "amazon-ebs" "laravel_aws" {
  region        = "eu-central-1"
  instance_type = "t3.medium"
  ami_name      = "laravel-{{timestamp}}"
  source_ami    = data.amazon-ami.ubuntu_2204.id
  ssh_username  = "ubuntu"
}

source "azure-arm" "laravel_azure" {
  client_id       = var.azure_client_id
  client_secret   = var.azure_client_secret
  subscription_id = var.azure_subscription_id
  tenant_id       = var.azure_tenant_id

  managed_image_name                = "laravel-${local.timestamp}"
  managed_image_resource_group_name = "rg-images"
  os_type                           = "Linux"
  image_publisher                   = "Canonical"
  image_offer                       = "0001-com-ubuntu-server-jammy"
  image_sku                         = "22_04-lts-gen2"
  location                          = "West Europe"
  vm_size                           = "Standard_D2s_v3"
}

source "googlecompute" "laravel_gcp" {
  project_id          = var.gcp_project
  source_image_family = "ubuntu-2204-lts"
  zone                = "europe-west1-b"
  image_name          = "laravel-${local.timestamp}"
  ssh_username        = "ubuntu"
}

build {
  # Eyni provisioner-lər 3 platforma üçün paralel icra olunur
  sources = [
    "source.amazon-ebs.laravel_aws",
    "source.azure-arm.laravel_azure",
    "source.googlecompute.laravel_gcp",
  ]

  provisioner "ansible" {
    playbook_file = "ansible/laravel.yml"
  }
}
```

## PHP/Laravel ilə İstifadə

### GitHub Actions ilə Packer Build Pipeline

```yaml
# .github/workflows/packer.yml
name: Build Laravel AMI

on:
  push:
    tags: ['v*']
  workflow_dispatch:

permissions:
  id-token: write
  contents: read

jobs:
  packer:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup Packer
        uses: hashicorp/setup-packer@v3
        with:
          version: "1.11.2"

      - name: Configure AWS OIDC
        uses: aws-actions/configure-aws-credentials@v4
        with:
          role-to-assume: arn:aws:iam::123456789012:role/packer-builder
          aws-region: eu-central-1

      - name: Packer Init
        run: packer init laravel-ami.pkr.hcl

      - name: Packer Validate
        run: packer validate -var "app_version=${{ github.ref_name }}" laravel-ami.pkr.hcl

      - name: Packer Build
        run: |
          packer build \
            -var "app_version=${{ github.ref_name }}" \
            -var "php_version=8.3" \
            laravel-ami.pkr.hcl

      - name: Upload manifest
        uses: actions/upload-artifact@v4
        with:
          name: packer-manifest
          path: manifest.json

      - name: Extract AMI ID
        id: ami
        run: |
          AMI=$(jq -r '.builds[-1].artifact_id' manifest.json | cut -d: -f2)
          echo "ami_id=$AMI" >> $GITHUB_OUTPUT

      - name: Trigger Terraform deploy
        uses: peter-evans/repository-dispatch@v3
        with:
          event-type: deploy-ami
          client-payload: '{"ami_id": "${{ steps.ami.outputs.ami_id }}"}'
```

### Ansible Playbook (Laravel üçün)

```yaml
# ansible/laravel.yml
- hosts: all
  become: true
  vars:
    php_version: "8.3"
    app_dir: /var/www/laravel
  tasks:
    - name: Install OS packages
      apt:
        name: ["nginx", "git", "redis-server", "supervisor", "unzip"]
        state: present
        update_cache: yes

    - name: Nginx config
      copy:
        src: files/nginx-laravel.conf
        dest: /etc/nginx/sites-available/laravel.conf
      notify: restart nginx

    - name: PHP-FPM pool
      template:
        src: templates/www.conf.j2
        dest: /etc/php/{{ php_version }}/fpm/pool.d/www.conf
      notify: restart php-fpm

    - name: Horizon supervisor
      template:
        src: templates/horizon.conf.j2
        dest: /etc/supervisor/conf.d/horizon.conf

    - name: Disable default Nginx site
      file: path=/etc/nginx/sites-enabled/default state=absent

  handlers:
    - name: restart nginx
      systemd: name=nginx state=restarted enabled=yes
    - name: restart php-fpm
      systemd: name=php{{ php_version }}-fpm state=restarted enabled=yes
```

### Packer + Terraform İnteqrasiyası

```hcl
# terraform/main.tf
data "aws_ami" "laravel" {
  most_recent = true
  owners      = ["self"]
  filter {
    name   = "tag:App"
    values = ["laravel"]
  }
  filter {
    name   = "tag:AppVersion"
    values = [var.app_version]
  }
}

resource "aws_launch_template" "laravel" {
  name_prefix   = "laravel-"
  image_id      = data.aws_ami.laravel.id
  instance_type = "t3.medium"
  # Image-də hər şey artıq var, user_data sadəcə env
}

resource "aws_autoscaling_group" "laravel" {
  desired_capacity = 4
  min_size         = 2
  max_size         = 12

  launch_template {
    id      = aws_launch_template.laravel.id
    version = "$Latest"
  }
}
```

### Composer artefact-ı image-ə əvvəlcədən qatmaq

```bash
# scripts/30-app.sh
#!/usr/bin/env bash
set -euxo pipefail

sudo mkdir -p /var/www/laravel
sudo tar -xzf /tmp/app.tar.gz -C /var/www/laravel
sudo chown -R www-data:www-data /var/www/laravel

cd /var/www/laravel
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Runtime-da dəyişən env-lər:
sudo rm -f /var/www/laravel/.env
```

## Interview Sualları (Q&A)

**S1: Packer və Ansible arasında fərq nədir? Hansı nə vaxt istifadə olunur?**
C: **Packer** golden image (AMI, Docker image) *bişirir* – nəticə immutable artefaktdır. **Ansible** işləyən serverlərin konfiqurasiyasını idarə edir (config drift, day-2 operations). Müasir yanaşma: **Packer image quraşdırır + Ansible provisioner kimi Packer daxilində istifadə olunur**. Sonra runtime-da yeni server lazım olanda Ansible yox, Packer image-indən instance açırsan. Əgər infrastructure mutable olacaqsa – saf Ansible; əgər immutable olacaqsa – Packer + Ansible kombinasiyası.

**S2: Immutable infrastructure niyə vacibdir?**
C: Server yaradıldıqdan sonra **dəyişdirilmir** – yeniləmə lazım olanda yeni image build edilir, yeni server açılır, köhnəsi silinir. Faydaları: (1) config drift yoxdur – bütün serverlər eynidir, (2) rollback asan – əvvəlki image-ə qayıt, (3) test edilmiş artefakt production-a çatır, (4) security – server "canlı" dəyişdirilmir, patching image səviyyəsində olur. Packer bu yanaşmanı real edir.

**S3: Packer template-də sensitive variable necə idarə olunur?**
C: `variable "db_password" { sensitive = true }` sətri logda maskalayır. Dəyər özü (1) `PKR_VAR_db_password` env dəyişənindən, (2) Vault-dan (`vault()` function), (3) AWS Secrets Manager-dən (`aws_secretsmanager_secret_version` data source) alına bilər. Packer template-də plain text saxlama; Git-ə `*.pkrvars.hcl` əlavə etmə; CI-da OIDC/IAM role-dan alınan credential-lar istifadə et.

**S4: Packer build-də `sources` siyahısı verərək nə qazanırıq?**
C: Paralel multi-builder. Məsələn, eyni app üçün həm AWS AMI, həm Azure Managed Image, həm Docker image-i bir əmrlə paralel bişirmək olar. Provisioner kodu bir dəfə yazılır, 3 platformaya tətbiq olunur. Multi-cloud strategiyası üçün kritik – build time qısalır, eyni versiyalı image-lər hər platformda eyni vaxtda hazır olur.

**S5: Post-processor-lar nə üçündür? Nümunə ver.**
C: Build bitəndən sonra artefakt üzərində əlavə iş: **manifest** (build metadata JSON), **docker-tag/push** (registry-yə göndər), **vagrant** (box faylı yarat), **amazon-import** (VMDK → AMI), **shell-local** (CI-da növbəti step üçün əmr icra et), **checksum**. Zəncirvari ola bilər – məsələn, `checksum` → `compress` → `artifice` → `vagrant`. Build artefaktı istifadə olunan yerə çatdırır.

**S6: `packer init`, `validate`, `build` fərqləri nədir?**
C: **`packer init`** – template-də elan olunmuş plugin-ləri yükləyir (Terraform init kimi). **`packer validate`** – sintaksis və variable yoxlaması, builder açmadan. **`packer build`** – həqiqi image build edir (ən uzun). CI-da ardıcıllıq: `init` → `fmt -check` → `validate` → `build`. Debug üçün `packer build -debug` hər step-dən sonra dayandırır, SSH ilə instance-a giriş imkanı verir.

**S7: Packer build dəfələrlə uğursuz olur – necə debug edilir?**
C: (1) `PACKER_LOG=1 packer build ...` – ətraflı log, (2) `-debug` flagi ilə hər step-dən sonra "Enter basılana qədər" dayanır – tempə açılmış instance-a SSH ilə bağlan, problemi araşdır, (3) `-on-error=ask` və ya `=abort` – error baş verdikdə instance-ı saxla, (4) `breakpoint` provisioner – script-in müəyyən yerində pause, (5) build log-da Packer-in SSH/WinRM key-lərini tap, öz-özünə sına.

**S8: Packer-də "ephemeral build instance" nəyə deyilir?**
C: Packer build zamanı müvəqqəti VM (və ya container) yaradır – provisioner-lər bu VM üzərində icra olunur. Sonda Packer VM-dən image çıxarır və VM-i silir – ona görə **ephemeral**. VM yaradılan VPC/subnet, security group, IAM role, SSH key – hamısı konfiqurasiya oluna bilər. Tam-dəstəkli auto-cleanup vacibdir ki, failed build-lər də resurs qoymasın (`run_tags`, `temporary_security_group_source_cidrs`).

**S9: Packer-də caching və build sürəti necə optimallaşdırılır?**
C: (1) **Parent image** seçimi – minimal base (Ubuntu minimal, Alpine) daha tez, (2) **Layered approach** – OS base image ayrı, app image parent-dən başlayır, (3) **Spot instance** – `spot_instance_types` parametri AWS-də, (4) paralel `sources` – birdən çox platformanı eyni zamanda bişir, (5) provisioner-ləri birləşdir (10 shell əvəzinə 1 script), (6) `apt-get install -y` mərhələsində paketləri cache edərək `tmpfs`-dən istifadə, (7) build artefaktları registry-də keşlə (Docker builder üçün).

**S10: Packer nəticəsini necə reproducible etmək olar?**
C: (1) Parent image-i **versiyası ilə pin et** (AMI ID və ya `most_recent=false` və strict filter), (2) `apt-get install -y package=version` – paket versiyalarını kilitlə, (3) Composer/npm üçün `composer.lock`, `package-lock.json` istifadə et, (4) environment variable-ları `variable` olaraq elan et, defaultlar dəqiq olsun, (5) timestamp əvəzinə **git commit SHA** ilə image adlandır, (6) build çıxışında `manifest.json` saxla – nə zaman, hansı versiya, hansı AMI ID, (7) eyni template ilə 2 build eyni nəticə verməlidir.

## Best Practices

1. **Packer + Terraform + Ansible zənciri** – Packer golden image, Terraform infra, Ansible day-2.
2. **Versiyalı image adları** – `laravel-v1.2.3-20261201-1430-abc123` (app version + timestamp + git SHA).
3. **Immutable infrastructure** prinsipi – image dəyişmir, yeni image build et, yeni instance aç.
4. **Minimum base image** – Ubuntu Minimal, Alpine – kiçik attack surface, tez build.
5. **Provisioner-ləri idempotent et** – təkrar işləsə eyni nəticə (Ansible təbii idempotent).
6. **Secrets ifşa etmə** – SSH key-ləri, API key-lər cleanup step-də sil.
7. **Hardening** – CIS Benchmark, SSH config, firewall qaydaları image-ə daxil et.
8. **Pre-baked dependencies** – Composer, npm install image-də olsun, runtime-da yox.
9. **Build testing** – InSpec, Goss ilə image-i validate et (port açıq, servis işləyir).
10. **Manifest saxla** – build metadata CI artefaktı kimi, Terraform input-u olacaq.
11. **CI/CD inteqrasiyası** – OIDC ilə cloud auth, secret-lər Vault/Secrets Manager-da.
12. **Image lifecycle** – 30 gündən köhnə AMI-ləri sil (Lambda + CloudWatch Events).
13. **Multi-region replikasiya** – `ami_regions = ["eu-central-1", "us-east-1"]`.
14. **Tagging strategy** – App, Version, Environment, GitSHA, BuildDate, Owner, CostCenter.
15. **Validation pipeline** – `packer validate`, `packer fmt -check`, `hadolint`, `trivy image scan` hər build-dən əvvəl.
