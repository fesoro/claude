# Microsoft (Architect)

## Ümumi baxış
- **Nə edir:** Enterprise proqram təminatı (Windows, Office, SQL Server), cloud platforma (Azure), developer alətləri (VS Code, GitHub, TypeScript), oyun (Xbox), AI (Copilot, Azure OpenAI).
- **Yaradılıb:** 1975-ci ildə Bill Gates və Paul Allen tərəfindən.
- **Miqyas (2024):**
  - Azure: AWS-dən sonra dünyanın ikinci ən böyük cloud platforması (~22–25% bazar payı).
  - Microsoft 365: 400M+ ödənişli istifadəçi.
  - Teams: 320M+ aylıq aktiv istifadəçi.
  - VS Code: 73%+ developer istifadə nisbəti (Stack Overflow 2023 Survey).
  - Bazar dəyəri: 2024-də $3T+ (dünyanın ən qiymətli şirkətlərindən biri).
- **Əsas tarixi anlar:**
  - 1975: Bill Gates + Paul Allen — BASIC interpretatoru, IBM-in DOS kontraktı.
  - 1990: Windows 3.0 — desktop dominantlıq başlayır.
  - 2000: Ballmer CEO olur; "Developers, developers, developers".
  - 2008: Azure işə salındı (Windows Azure adı ilə).
  - 2014: **Satya Nadella CEO olur** — "Mobile-first, cloud-first" pivotu.
  - 2015: VS Code buraxıldı (cross-platform, open-source).
  - 2016: LinkedIn $26.2B-a alındı.
  - 2016: TypeScript 2.0 — sənaye tərəfindən qəbul sürətlənir.
  - 2018: **GitHub $7.5B-a alındı** — developer ekosistemi xətti.
  - 2020: Teams pandemiya zamanı partladı (75M → 320M istifadəçi).
  - 2022: Activision Blizzard $68.7B-a alındı.
  - 2023: **OpenAI-a $13B investisiya**; Azure OpenAI Service, Copilot süiti.

Microsoft **"developer-first" cloud şirkətinə çevrilmənin** ən böyük case study-sidir. Satya Nadella-nın 2014-dəki pivotu texnoloji tarixdə ən uğurlu korporativ transformasiyalardan biridir.

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Cloud platform | **Azure** (VM, AKS, Functions, Service Bus, CosmosDB, SQL) | Tam cloud ekosistemi |
| Languages | **C#, C++, TypeScript, Python, Go** | C# .NET core, C++ perf, TS web |
| Runtime | **.NET (Core → NET 5/6/7/8)** | Cross-platform, yüksək performans |
| Web framework | **ASP.NET Core** | Minimal API, high RPS, open-source |
| Primary DB | **Azure SQL (SQL Server)**, **Cosmos DB** | SQL: ACID; Cosmos: qlobal multi-model |
| Cache | **Azure Cache for Redis** | Managed Redis |
| Messaging | **Azure Service Bus, Event Hub** (Kafka-uyğun), **Azure Storage Queue** | Event-driven, enterprise messaging |
| Search | **Azure Cognitive Search** (Elasticsearch əsaslı) | Managed full-text + vector search |
| AI | **Azure OpenAI Service**, **Azure AI Studio** | GPT-4, Claude, Mistral managed endpoint |
| Frontend | **TypeScript + React** (web), **MAUI** (mobile/desktop) | TS hər yerdə |
| Real-time | **Azure SignalR Service**, **WebSocket** | Teams, bi-directional communication |
| Infrastructure | **Azure Kubernetes Service (AKS)**, **Azure Functions** | Managed K8s, serverless |
| Monitoring | **Azure Monitor, Application Insights, Grafana** | Full observability stack |

## Dillər — Nə və niyə

### C# / .NET
Microsoft-un əsas dilidir. .NET Framework (2002) Windows-only idi; Nadella dövrünün ən böyük texniki qərarlarından biri **cross-platform .NET Core** (2016) idi — Linux, macOS, Windows-da eyni kod.

**ASP.NET Core** PHP developer-lərin maraqlanmalı olduğu yer:
- Raw benchmark-lərdə Laravel-dən 10–20x daha yüksək RPS (TechEmpower benchmark-ləri).
- Minimal API: `app.MapGet("/", () => "Hello!")` — FastAPI bənzər syntax.
- Dependency injection daxilə qurulub.
- gRPC dəstəyi.

### TypeScript
Microsoft **2012-də TypeScript-i ixtira etdi** — JavaScript-ə statik tiplər əlavə etmək üçün. Bu gün:
- Angular (Google): TypeScript-first.
- React ekosistemi: TypeScript defolt.
- Node.js proyektlərinin əksəriyyəti: TypeScript.
- PHP developer-lər üçün əlaqə: TypeScript-in `interface`, `type`, generics konsepsiyaları PHP 8-in union types, named arguments, intersection types ilə müqayisə edilə bilər.

### C++
Windows kernel, Office, Xbox, SQL Server engine, VS Code-un bəzi hissələri C++-dadır. Performance-critical infrastructure üçün.

### Python
Azure Machine Learning, Cognitive Services SDK-ları, ML pipeline-lar.

### Go
Bazı Azure control plane servislər (Kubernetes ekosistemi ilə uyğunluq üçün).

## Framework seçimləri — Nə və niyə

### ASP.NET Core — enterprise web framework
- **Minimal API** (NET 6+): Laravel Route-bənzər routing.
- **Controller əsaslı API**: daha klassik MVC-styl.
- **Blazor**: C# ilə frontend — JavaScript olmadan interactive web UI.
- **SignalR**: WebSocket abstraction — Teams-in real-time infrastrukturunun özəyidir.

### MAUI (Multi-platform App UI)
- Xamarin-in varisi. Tək C# kod bazasından iOS, Android, Windows, macOS.

### Azure Functions
- Serverless, event-triggered functions. AWS Lambda-ya bənzər. PHP-dən C#-a keçən developer-lər üçün qapı.

## Verilənlər bazası seçimləri — Nə və niyə

### Azure SQL (SQL Server)
- Enterprise relational DB. ACID, JSON dəstəyi, qurulmuş full-text axtarış.
- PHP developer-lər üçün: `pdo_sqlsrv` ilə Laravel SQL Server-i dəstəkləyir.
- **Azure SQL Hyperscale**: on-demand miqyaslanan managed SQL — istifadə olunan qədər ödə.

### Cosmos DB — Microsoft-un global multi-model DB
- **Wire protocol uyğunluğu**: MongoDB, Cassandra, Gremlin (qraf), Table Storage API-ləri eyni Cosmos DB üzərindən.
- Multi-region active-active, tunable consistency (5 hədd: Strong → Eventual).
- Guaranteed 10ms SLA.
- **Nə vaxt istifadə etmək:** global multi-region data, variable schema, IoT telemetriyası.
- **Nə vaxt istifadə etməmək:** güclü JOIN-lər lazımdırsa, kompleks transaction-lar tələb olunursa.

### Azure Event Hub + Service Bus
- **Event Hub**: Kafka-uyğun API. Mövcud Kafka producer/consumer kodunu dəyişməz Azure-da işlədə bilərsiniz.
- **Service Bus**: enterprise messaging — dead-letter queue, message sessions, transactions.

## Proqram arxitekturası

### Microsoft Teams — real-time enterprise messaging
Teams Microsoft-un ən mürəkkəb arxitektura case study-sidir.

```
  [Teams Client: Electron + React (Desktop), WebView (Mobile)]
         |
   [Azure Front Door — global traffic routing]
         |
   [Teams API Gateway — ASP.NET Core]
         |
   +-----+-----+------+-------+
   |           |      |       |
[Chat      [Presence][Video] [Files]
 Service]  Service]  (Media) (SharePoint)
   |           |      
[Azure    [Azure     
 Cosmos DB] SignalR   
           Service]  
         |
  [Azure Service Bus — async events]
  [Azure Event Hub — telemetry]
  [Azure Storage — blob, media]
```

**Real-time presence:**
- SignalR ilə WebSocket bağlantıları.
- Hər client bağlandıqda, presence servisi onun online/offline vəziyyətini yayır.
- 320M MAU-nu idarə etmək üçün Azure-un global regionları.

### Azure — global cloud platform

```
  [Customer traffic]
         |
  [Azure Front Door (Anycast + WAF)]
         |
  [Azure App Service / AKS cluster]
         |
  +------+--------+----------+
  |               |          |
[Azure SQL]  [Cosmos DB] [Event Hub]
  |               |          |
[Read replicas][Global    [Stream Analytics
  |             replicas]   / Functions]
[Azure Cache
 for Redis]
```

### Copilot / AI arxitekturası (2023+)
Microsoft-un AI məhsulları Azure OpenAI Service üzərindədir:

```
  [User (Word, Teams, VS Code, Bing)]
         |
  [Copilot orchestration layer (Semantic Kernel)]
         |
  [Azure OpenAI Service — GPT-4 endpoint]
         |
  [Plugins / tools: Graph API, Search, Code interpreter]
         |
  [Azure Cognitive Search (Vector + keyword hybrid)]
```

**Semantic Kernel** — Microsoft-un agent orchestration framework-u (Python, C#, Java). LangChain-ə alternativdir.

## İnfrastruktur və deploy

- **Azure DevOps** (TFS-dən gəlir) + **GitHub Actions** — CI/CD.
- **Azure Kubernetes Service (AKS)** — managed K8s. Microsoft Kubernetes-ə ən böyük töhfəçilərdən biridir.
- **Azure Container Registry** — private Docker registry.
- Global data mərkəzləri: 60+ region, 140+ country.
- **ARM şablonları + Bicep** — infrastructure-as-code. Terraform-a Microsoft-un cavabı.

## Arxitekturanın təkamülü

| İl | Dəyişiklik |
|------|--------|
| 1975 | BASIC interpretatoru, DOS |
| 1990 | Windows 3.0 — desktop hegemonluğu |
| 2002 | .NET Framework, C# — Java alternativ |
| 2008 | Windows Azure işə salındı |
| 2014 | Nadella CEO: "Cloud-first, Mobile-first" pivotu |
| 2015 | VS Code (open-source, Electron); .NET Foundation |
| 2016 | .NET Core (Linux-da C#), TypeScript 2.0 geniş qəbul |
| 2016 | LinkedIn $26.2B alındı |
| 2018 | GitHub $7.5B alındı |
| 2020 | Teams pandemiya partlayışı; M365 cloud dominantlığı |
| 2022 | Activision Blizzard $68.7B |
| 2023 | OpenAI $13B; Copilot süiti; Azure OpenAI Service |

## Əsas texniki qərarlar

### 1. .NET Core — "Windows-only" zəncirini qırmaq
**Problem:** .NET Framework yalnız Windows-da işləyirdi. Linux-da server bazarı büyüyürdü, developer-lər macOS-a keçirdi.
**Seçim:** Sıfırdan cross-platform .NET Core (2016). 20+ illik legacy kodu yenidən yazmaq.
**Kompromislər:** Geri uyğunluq yarıldı; bir çox Windows-specific API-lər silindi; migration-lar ağrılı idi.
**Sonra nə oldu:** .NET 5/6/7/8 ilə birləşdirildilər. ASP.NET Core Java Spring ilə benchmark-lərdə rəqabət edir. Geniş Linux deployment mümkün oldu.

### 2. TypeScript ixtirası — JavaScript-i enterprise-ready etmək
**Problem:** Böyük kod bazaları JavaScript-də idarə olunmazdı — run-time xəta, refactor çətinliyi.
**Seçim:** Anders Hejlsberg (C#-ın dizayneri) TypeScript-i yaratdı: JavaScript superset, opt-in static types.
**Kompromislər:** "Daha bir dil" — yeni öyrənmə yükü; build addımı tələb edir.
**Sonra nə oldu:** TypeScript 2024-də JavaScript ekosisteminin de-facto standartı oldu. Angular, React, Vue, Node.js hamısı TypeScript-ə keçdi. Stack Overflow survey-lərdə ən çox sevilen dillər arasında.

### 3. GitHub alışı — developer trust qazanmaq
**Problem:** Microsoft developer-lər tərəfindən rəqib kimi görülürdü. Developer mindshare azalırdı.
**Seçim:** GitHub $7.5B-a alındı (2018). Kritik qərar: GitHub-u müstəqil saxlamaq, Microsoft branding-ini minimuma endirmək.
**Kompromislər:** $7.5B böyük mərc idi; developer-lər "Microsoft GitHub-u məhv edəcək" deyirdi.
**Sonra nə oldu:** GitHub-un mühəndislik mədəniyyəti qorundu. GitHub Actions, Copilot, Codespaces Microsoft investisiyası ilə yaradıldı. Developer trust artdı.

### 4. OpenAI partnerlüyü — AI cloud race-i
**Problem:** AWS market lideri idi; Google-un GCP daha çox AI tədqiqatı var idi.
**Seçim:** 2019-2023: OpenAI-a $13B investisiya. Azure OpenAI Service: GPT-4 API-nin exclusiv early access.
**Kompromislər:** Vendor lock-in OpenAI-a; Model alignment riskləri; AGI safety qeyri-müəyyənliyi.
**Sonra nə oldu:** Azure OpenAI 2023-ün ən böyük cloud xüsusiyyəti oldu. Copilot for M365, GitHub Copilot, Bing Chat — hamısı GPT-4 üzərindədir.

### 5. Cosmos DB — "one DB, any API" ideyası
**Problem:** Müştərilər müxtəlif DB-lər istəyirdi (Mongo, Cassandra, SQL), amma operations-ı birləşdirmək istəyirdilər.
**Seçim:** Wire-protocol compatible multi-model DB: eyni Cosmos backendi Mongo, Cassandra, SQL, Gremlin API-ləri dəstəkləyir.
**Kompromislər:** Hər API native DB-nin bütün feature-larını dəstəkləmir; qiymət yüksək ola bilər.
**Sonra nə oldu:** Enterprise cloud migration-ları üçün "existing code-u dəyişmədən cloud-a keç" kimi satıldı.

## Müsahibədə necə istinad etmək

1. **"Enterprise real-time messaging dizayn edin" (Teams bənzəri):** "Microsoft Teams SignalR (WebSocket abstraction) + Azure Service Bus (async events) + Cosmos DB (multi-region state) kombinasiyasını istifadə edir. Key qərar: WebSocket-i bacardığınız qədər az bağlantıda saxlayın — Teams hər client üçün bir WebSocket bağlantısı açır, amma presence fan-out async Service Bus-dan keçir."

2. **"TypeScript niyə seçdiniz?"** "Microsoft 2012-də TypeScript-i böyük kod bazalarında JavaScript-in tip xəsisliyi problemini həll etmək üçün yaratdı. PHP 8-in enums, union types, generics-ə doğru irəliləyişi eyni motivasiya ilə paralleldir — büyüyən kod bazasını daha güvənli etmək."

3. **"Global distributed DB":** "Cosmos DB-nin tunable consistency modeli (5 hədd: Strong, Bounded Staleness, Session, Consistent Prefix, Eventual) trade-off-ları izah etmək üçün əla nümunədir. Session consistency: öz yazmalarını oxuyursan, amma başqalarının yazmalarında lag ola bilər — çox web app üçün praktik optimumdur."

4. **".NET Core miqrasiyası pattern-i:** Sıfırdan yenidən yazma əvəzinə, Microsoft strangler-fig pattern-i istifadə etdi: legacy .NET Framework kodu yavaş-yavaş .NET Core-a miqrasiya edildi. Eyni pattern böyük PHP 5 → PHP 8 miqrasiyaları üçün tətbiq olunur.

5. **"Necə iki iddialı cloud competitor ilə rəqabət edirsiniz?"** "Microsoft-un cavabı diferensiasiyadır: GitHub + VS Code + TypeScript + Azure = developer-in bütün iş axınını əhatə edir. Vendor lock-in toolchain-dədir, infrastrukturda deyil — AWS-in market payını almaq üçün əlverişli strategiya."

6. **Copilot müsahibə kontekstdə:** "Semantic Kernel Microsoft-un agent orchestration framework-udur — LangChain/LlamaIndex-ə alternativ. C#, Python, Java-da işləyir. Azure OpenAI Service: endpoint, rate limit, content filtering — GPT-4-ü production-a çıxarmaq üçün infrastructure concerns-i həll edir."

## Əlavə oxu üçün
- Blog: Microsoft DevBlogs — .NET, Azure, TypeScript, GitHub
- Book: *Site Reliability Workbook* (Microsoft tərəfindən SRE töhfəsi var)
- Talk: Satya Nadella *"Hit Refresh"* (Microsoft transformasiyası)
- Paper: *Cosmos DB: A Cloud-Native Document Database* (Microsoft Research)
- Docs: Azure Architecture Center (microsoft.com/azure/architecture) — reference patterns
- Blog: *"TypeScript: Why I Chose It"* — Anders Hejlsberg çıxışları
- Open source: TypeScript, VS Code, .NET Core, Semantic Kernel (GitHub/microsoft)
- Talk: *"Building Real-time Web Apps with SignalR"* (ASP.NET community standups)
- Blog: GitHub Engineering Blog — Copilot, Actions, Codespaces arxitekturası
