# Azure Əsasları (Middle)

## Nədir? (What is it?)

Microsoft Azure – Microsoft-un public cloud platformasıdır (2010 launch). AWS-dən sonra ikinci ən böyük cloud provayderidir. Enterprise seqmentində (xüsusilə Microsoft ecosystem – Windows Server, SQL Server, Active Directory istifadə edən şirkətlərdə) güclüdür. Hybrid cloud imkanları (Azure Arc, Azure Stack), .NET və Windows workload-larına dəstəyi, Office 365 və Dynamics ilə inteqrasiyası onu fərqləndirir. Lakin Linux və açıq mənbə workload-ları (Laravel, PHP, Kubernetes) də tam dəstəklənir.

## Əsas Konseptlər (Key Concepts)

### Azure Hiyerarşiyası

```
Management Group         (çox subscription üçün policy)
   └─ Subscription       (billing sərhədi)
       └─ Resource Group (resurs qrupu, lifecycle birliyi)
           └─ Resource   (VM, DB, storage)

Region: Azure datacenter yerləri (North Europe, West US, etc.)
Availability Zone: region daxilində fiziki ayrı datacenter
```

### Compute xidmətləri

```
VIRTUAL MACHINES (VM) – IaaS
   Windows və Linux
   Sizes: B (burstable), D (general), E (memory), F (compute)
   Availability Set (AZ-əvvəlki dövr)
   Availability Zone (müasir yanaşma)
   VM Scale Set: auto-scaling group

AKS (Azure Kubernetes Service) – Managed K8s
   Control plane free (yalnız node-lara pul)
   Integration: Entra ID, Azure Policy, Monitor
   Virtual nodes (ACI ilə serverless pod)

APP SERVICE (PaaS) – Web App Hosting
   Windows və Linux runtime
   PHP, .NET, Node, Python, Java, Ruby built-in
   Auto-scale, SSL, custom domain
   Deployment slots (blue-green)
   WordPress, Laravel, Django üçün rahat

AZURE FUNCTIONS – Serverless (FaaS)
   Trigger əsaslı: HTTP, Queue, Blob, Timer
   Consumption / Premium plan
   Durable Functions (stateful)

CONTAINER INSTANCES (ACI) – tek container
   AWS Fargate-in ekvivalenti
   Quick container launch

CONTAINER APPS – serverless containers
   Cloud Run-a bənzər
   KEDA əsaslı scaling (event-driven)
   Dapr inteqrasiyası
```

### Storage xidmətləri

```
AZURE BLOB STORAGE – Object storage (S3 ekvivalenti)
   Tiers:
   - Hot: tez-tez giriş
   - Cool: ayda 1 dəfə (30 gün)
   - Cold: rübdə 1 dəfə (90 gün)
   - Archive: ildə 1 dəfə (180 gün, rehydrate lazımdır)
   
   Redundancy:
   - LRS (locally redundant) – 3 kopya eyni DC
   - ZRS (zone redundant) – 3 zone
   - GRS (geo redundant) – başqa region
   - RA-GRS (read access GRS)

AZURE FILES – SMB/NFS managed
AZURE DISKS – VM üçün block storage (SSD, HDD, Premium, Ultra)
AZURE DATA LAKE STORAGE GEN2 – big data üçün hierarchical namespace
```

### Database xidmətləri

```
AZURE SQL DATABASE – Managed SQL Server
   DTU və vCore pricing modelləri
   Serverless, Hyperscale, Business Critical tier
   Auto-backup, PITR, failover groups

AZURE DATABASE FOR MYSQL / POSTGRESQL:
   Flexible Server (recommended) – HA, burstable, zone redundant
   Laravel üçün ideal

COSMOS DB – Global NoSQL
   Multi-model: document, key-value, graph, column
   Multi-region write
   5 consistency level-lər

AZURE CACHE FOR REDIS – managed Redis
```

### Networking

```
VIRTUAL NETWORK (VNet):
   IP range, subnet-lər
   Region-specific (AWS-ə bənzər, GCP-dən fərqli)
   VNet peering – VNet-ləri birləşdir

AZURE LOAD BALANCER (L4):
   Standard SKU (zonal, zone redundant)

APPLICATION GATEWAY (L7):
   HTTP(S) load balancer, WAF
   URL path routing, cookie affinity, SSL offload

FRONT DOOR:
   Global HTTP load balancer + CDN + WAF
   Anycast IP

AZURE CDN, AZURE FIREWALL, NSG (Network Security Group)
VPN GATEWAY, EXPRESSROUTE (dedicated)
PRIVATE ENDPOINT (PaaS service-ləri VNet içinə)
```

### Identity və Security

```
MICROSOFT ENTRA ID (əvvəlki Azure AD):
   Identity provider – user, group, application
   SSO, MFA, Conditional Access
   Enterprise Applications (SaaS SSO)
   B2B guest, B2C customer

ROLE-BASED ACCESS CONTROL (RBAC):
   Scope: Management Group / Subscription / Resource Group / Resource
   Built-in role-lar: Owner, Contributor, Reader, xidmət-specific
   Custom role: JSON ilə

KEY VAULT:
   Secrets, keys, certificates
   Managed HSM (FIPS 140-2 Level 3)
   Access: RBAC və/ya Access Policy
   Managed Identity ilə keysiz auth

MANAGED IDENTITY:
   System-assigned: resurs ilə eyni lifecycle
   User-assigned: müstəqil, çox resurs istifadə edə bilər
   Kod-da credential yoxdur
```

### DevOps və Monitoring

```
AZURE DEVOPS (Services):
   Boards (issue tracking)
   Repos (Git)
   Pipelines (CI/CD)
   Artifacts, Test Plans

GITHUB ACTIONS – Azure-ın müasir tövsiyəsi

AZURE MONITOR:
   Metrics, Logs, Application Insights (APM)
   Log Analytics (Kusto Query Language – KQL)

AZURE RESOURCE MANAGER (ARM):
   Resource provisioning layer
   ARM template (JSON) və ya BICEP (DSL)
```

## Praktiki Nümunələr (Practical Examples)

### Azure CLI

```bash
# Auth
az login
az account set --subscription "My Subscription"

# Resource group
az group create -n rg-laravel -l westeurope

# VM
az vm create \
  -g rg-laravel -n vm-web1 \
  --image Ubuntu2204 \
  --size Standard_D2s_v3 \
  --admin-username azureuser \
  --generate-ssh-keys

# App Service
az appservice plan create -g rg-laravel -n plan-laravel \
  --is-linux --sku P1v3
az webapp create -g rg-laravel -p plan-laravel -n laravel-app \
  --runtime "PHP:8.2"

# AKS
az aks create -g rg-laravel -n aks-laravel \
  --node-count 3 --enable-managed-identity --generate-ssh-keys

# MySQL Flexible Server
az mysql flexible-server create \
  -g rg-laravel -n laravel-db \
  --admin-user laraveladmin --admin-password 'S3cret!123' \
  --sku-name Standard_B1ms
```

### BICEP Template

```bicep
// main.bicep
param location string = resourceGroup().location
param appName string = 'laravel-app'
param sku string = 'P1v3'

resource plan 'Microsoft.Web/serverfarms@2023-12-01' = {
  name: '${appName}-plan'
  location: location
  sku: { name: sku }
  kind: 'linux'
  properties: { reserved: true }
}

resource webapp 'Microsoft.Web/sites@2023-12-01' = {
  name: appName
  location: location
  properties: {
    serverFarmId: plan.id
    httpsOnly: true
    siteConfig: {
      linuxFxVersion: 'PHP|8.2'
      alwaysOn: true
      appSettings: [
        { name: 'APP_ENV', value: 'production' }
        { name: 'APP_DEBUG', value: 'false' }
      ]
    }
  }
  identity: { type: 'SystemAssigned' }
}

resource mysql 'Microsoft.DBforMySQL/flexibleServers@2023-12-30' = {
  name: '${appName}-db'
  location: location
  sku: { name: 'Standard_B1ms', tier: 'Burstable' }
  properties: {
    administratorLogin: 'laraveladmin'
    administratorLoginPassword: 'S3cret!PAss'
    version: '8.0'
    storage: { storageSizeGB: 32 }
    backup: { backupRetentionDays: 7, geoRedundantBackup: 'Disabled' }
    highAvailability: { mode: 'ZoneRedundant' }
  }
}

output webappUrl string = 'https://${webapp.properties.defaultHostName}'
```

### Terraform Azure

```hcl
terraform {
  required_providers {
    azurerm = { source = "hashicorp/azurerm", version = "~> 3.100" }
  }
  backend "azurerm" {
    resource_group_name  = "rg-tfstate"
    storage_account_name = "tfstate12345"
    container_name       = "tfstate"
    key                  = "prod.terraform.tfstate"
  }
}

provider "azurerm" {
  features {}
}

resource "azurerm_resource_group" "rg" {
  name     = "rg-laravel"
  location = "West Europe"
}

resource "azurerm_service_plan" "plan" {
  name                = "plan-laravel"
  resource_group_name = azurerm_resource_group.rg.name
  location            = azurerm_resource_group.rg.location
  os_type             = "Linux"
  sku_name            = "P1v3"
}

resource "azurerm_linux_web_app" "app" {
  name                = "laravel-prod"
  resource_group_name = azurerm_resource_group.rg.name
  location            = azurerm_service_plan.plan.location
  service_plan_id     = azurerm_service_plan.plan.id

  site_config {
    application_stack { php_version = "8.2" }
    always_on = true
  }

  identity { type = "SystemAssigned" }

  app_settings = {
    "APP_ENV"       = "production"
    "WEBSITES_PORT" = "9000"
  }
}
```

## PHP/Laravel ilə İstifadə

### App Service-də Laravel

```bash
# Dəyişiklik (custom startup):
# App Service PHP default Apache istifadə edir
# Laravel üçün Nginx custom startup command:

# /home/site/nginx-root.conf
server {
    listen 8080;
    root /home/site/wwwroot/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

# Startup command:
az webapp config set -g rg-laravel -n laravel-app \
  --startup-file "cp /home/site/nginx-root.conf /etc/nginx/sites-available/default && service nginx reload"
```

### GitHub Actions → Azure Deploy

```yaml
name: Deploy Laravel to Azure
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2' }
      
      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader --no-interaction
      
      - run: php artisan config:cache
      - run: php artisan route:cache
      - run: php artisan view:cache
      
      - name: Login to Azure
        uses: azure/login@v2
        with:
          client-id: ${{ secrets.AZURE_CLIENT_ID }}
          tenant-id: ${{ secrets.AZURE_TENANT_ID }}
          subscription-id: ${{ secrets.AZURE_SUBSCRIPTION_ID }}
      
      - name: Deploy to App Service
        uses: azure/webapps-deploy@v3
        with:
          app-name: laravel-prod
          package: .
```

### Azure Blob Storage

```php
// composer require microsoft/azure-storage-blob

// config/filesystems.php
'disks' => [
    'azure' => [
        'driver'    => 'azure',
        'name'      => env('AZURE_STORAGE_NAME'),
        'key'       => env('AZURE_STORAGE_KEY'),
        'container' => env('AZURE_STORAGE_CONTAINER'),
        'url'       => env('AZURE_STORAGE_URL'),
    ],
],

// Istifadə (matthewbdaly/laravel-azure-storage paketi ilə):
Storage::disk('azure')->put('files/report.pdf', $content);
$url = Storage::disk('azure')->url('files/report.pdf');
```

### Key Vault + Managed Identity

```php
// composer require azure/keyvault-secrets

use MicrosoftAzure\Storage\Common\ServicesBuilder;

class KeyVaultService
{
    public function getSecret(string $name): string
    {
        $vaultUrl = env('AZURE_KEYVAULT_URL');
        
        // Managed Identity-dən token al
        $tokenEndpoint = 'http://169.254.169.254/metadata/identity/oauth2/token';
        $token = $this->getManagedIdentityToken('https://vault.azure.net');
        
        $url = "$vaultUrl/secrets/$name?api-version=7.4";
        $response = Http::withToken($token)->get($url);
        
        return $response->json('value');
    }
    
    private function getManagedIdentityToken(string $resource): string
    {
        $url = 'http://169.254.169.254/metadata/identity/oauth2/token'
             . '?api-version=2018-02-01&resource=' . urlencode($resource);
        
        return Http::withHeaders(['Metadata' => 'true'])
            ->get($url)
            ->json('access_token');
    }
}
```

### AKS üçün Helm Chart Dəyərləri

```yaml
# values-azure.yaml
image:
  repository: myacr.azurecr.io/laravel-app
  tag: "v2.0.0"

podIdentity:
  enabled: true
  identityName: laravel-identity

mysql:
  enabled: false  # Azure MySQL Flexible kənar

database:
  host: laravel-db.mysql.database.azure.com
  port: 3306
  ssl: true
  sslMode: VERIFY_IDENTITY

redis:
  host: laravel-cache.redis.cache.windows.net
  port: 6380
  ssl: true

ingress:
  enabled: true
  className: application-gateway
  host: app.example.com
  tls: true

autoscaling:
  enabled: true
  minReplicas: 3
  maxReplicas: 20
  targetCPU: 70
```

## Interview Sualları (Q&A)

**S1: App Service və AKS arasında necə seçim etmək?**
C: **App Service** PaaS – web app üçün ən sadə yanaşma. Built-in PHP, Node, .NET runtime, auto-scale, deployment slots, SSL. Laravel monolit üçün ideal. **AKS** Kubernetes tələb edən iş yükü – microservice, xüsusi networking, operator, CRD. AKS daha mürəkkəbdir amma daha çevik. Kiçik-orta layihə App Service, böyük microservice AKS.

**S2: Managed Identity nədir və niyə lazımdır?**
C: Managed Identity Azure resursuna (VM, App Service, AKS pod) Entra ID identity verir. Sonra resurs digər Azure xidmətlərinə (Key Vault, Storage) heç bir secret/key saxlamadan auth edir – Azure platforma arxada token verir. System-assigned resursla bağlıdır; user-assigned müstəqildir (çox resurs paylaşır). Secret rotation problemi həll edir.

**S3: Availability Set və Availability Zone fərqi nədir?**
C: **Availability Set** datacenter daxilində fiziki ayrı rack-lərə VM-ləri yerləşdirir (fault domain, update domain). Tək DC çöküşündən qoruyur. **Availability Zone** region daxilində fiziki ayrı datacenter-lərə VM-ləri yerləşdirir – DC çöküşündən belə qoruyur. Availability Zone müasir və daha güclü yanaşmadır, yenidə həmişə AZ istifadə edilməlidir.

**S4: ARM, BICEP və Terraform arasında necə seçim?**
C: **ARM template** JSON – verbose, oxumaq çətin, amma Azure native. **BICEP** DSL – ARM-a compile olunur, sintaksis təmizdir, Azure-only. **Terraform** multi-cloud, HCL, böyük ekosistem. Azure-only layihədə BICEP rahatdır; multi-cloud və ya komanda Terraform bilirsə – Terraform.

**S5: Entra ID, Azure AD və Microsoft Account arasında fərq nədir?**
C: **Microsoft Account** – şəxsi (outlook.com, xbox). **Entra ID** (əvvəlki Azure AD) – enterprise identity directory – user, group, SSO, MFA, Conditional Access. **Azure subscription** Entra ID tenant-a bağlıdır. Entra ID 2023-də rebrand olundu (Azure AD → Microsoft Entra ID), funksionallıq dəyişmədi.

**S6: Key Vault Access Policy və RBAC fərqi nədir?**
C: **Access Policy** – köhnə Key Vault-specific authorization modeli (get, list, set permissions). **RBAC** – müasir yanaşma, Azure-un ümumi RBAC modeli (Key Vault Secrets Officer, Reader rolları). RBAC tövsiyə olunur – mərkəzi idarəetmə, audit, Entra ID groups ilə inteqrasiya. Yeni Key Vault-ları RBAC ilə qur.

**S7: App Service deployment slots nədir?**
C: Deployment slot – App Service-in ayrı instance-ı (URL, config ayrı). Staging slot-a deploy edirsən, test edirsən, sonra production ilə swap edirsən – bu zero-downtime blue-green deployment-dir. Slot swap: traffic anında dəyişir, amma warm-up olur. Slot-specific app setting-lər (connection string-lər) slot ilə qalır, swap olmur.

**S8: Azure Front Door və Application Gateway fərqi nədir?**
C: **Application Gateway** regional L7 LB – WAF, URL routing, SSL offload. Yalnız bir region içində. **Front Door** global L7 LB + CDN + WAF – anycast IP bütün dünyada, multi-region traffic routing. Front Door global, Application Gateway regional. Multi-region app üçün Front Door + backend-lərdə Application Gateway.

**S9: Azure SQL DTU və vCore pricing fərqi?**
C: **DTU** (Database Throughput Unit) – CPU, memory, IO-nu bir ədədə birləşdirir. Sadə, amma fleksibel deyil. **vCore** – CPU sayı və memory ayrı-ayrı seçilir, konkret hardware specification. vCore tövsiyə olunur – daha çox kontrol, hybrid benefit (existing SQL Server lisenziyası), serverless və Hyperscale tier imkanları.

**S10: Managed Identity ilə Key Vault-dan Laravel-də secret necə oxunur?**
C: (1) App Service-də System-assigned Managed Identity aktiv et. (2) Key Vault-da identity-yə "Key Vault Secrets User" rolu ver. (3) Laravel startup-da identity endpoint-dən token al, Key Vault REST API-yə çağır, secret oxu. (4) Oxunan secret-i config-ə yaz və ya env-ə set et. Alternativ – App Service `@Microsoft.KeyVault(...)` referenced app setting, Azure platforma avtomatik Key Vault-dan oxuyur.

## Best Practices

1. **Resource Group naming convention** – mühit, region, workload (rg-prod-westeu-laravel).
2. **Tagging strategy** – Environment, Owner, CostCenter, Project.
3. **Managed Identity** istifadə et, service principal key-dən qaç.
4. **Key Vault** secrets saxla, .env-də production credential olmamalıdır.
5. **Private Endpoint** PaaS xidmətləri VNet içinə çıxar.
6. **Network Security Group (NSG)** minimum lazımi portlar açıq olsun.
7. **Azure Policy** guardrails qoy (məs. public IP qadağan).
8. **Azure Advisor** tövsiyələrini izlə.
9. **Cost Management** – budget alert, reservation, spot VM.
10. **Monitoring + Application Insights** bütün web app-larda aktiv.
11. **Availability Zone** production workload-larda məcburidir.
12. **Deployment slots** blue-green üçün istifadə et.
13. **Terraform/BICEP state-i** Storage Account-da saxla (locking, versioning).
14. **Microsoft Defender for Cloud** – security posture management.
15. **Backup və Disaster Recovery** – Azure Backup, Geo-redundancy, Failover Groups.
