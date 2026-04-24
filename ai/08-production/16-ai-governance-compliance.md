# 63 — AI Governance və Compliance: EU AI Act, GDPR, SOC 2 və ISO 42001

> **Oxucu kütləsi:** Senior developerlər, engineering lead-ləri, compliance/security officer-lər, legal tərəfdaşlar
> **Bu faylın 09, 11, 13-dən fərqi:** 09 — ümumi AI security (OWASP). 11 — PII redaction. 13 — content moderation. Bu fayl — **regulatory/governance layer**: EU AI Act risk tiers və obligations, GDPR intersection, US landscape (Colorado, NYC LL144, California), ISO 42001, NIST AI RMF, SOC 2 AI scope, model cards, AIBOM, DPIA template, vendor management, internal AI policy, ethics committee, Laravel-specific compliance checklist.

---

## Mündəricat

1. [Niyə Governance Kritikdir](#why)
2. [Governance Piramidası: Regulator-Enterprise-Product](#pyramid)
3. [EU AI Act Risk Tier-ləri](#eu-ai-act)
4. [EU AI Act Obligation-ları](#obligations)
5. [EU AI Act Timeline (2025-2027)](#timeline)
6. [GDPR və AI Kəsişməsi](#gdpr)
7. [US Regulatory Landscape](#us)
8. [ISO/IEC 42001](#iso)
9. [NIST AI RMF](#nist)
10. [SOC 2 və AI](#soc2)
11. [Model Cards və System Cards](#model-cards)
12. [AI Bill of Materials (AIBOM)](#aibom)
13. [DPIA Template](#dpia)
14. [Audit Trail Requirements](#audit)
15. [Vendor Management](#vendor)
16. [Internal AI Policy](#internal-policy)
17. [AI Ethics Committee](#ethics)
18. [Practical Org Structure](#org)
19. [Laravel Compliance Checklist](#laravel)
20. [Anti-Pattern-lər](#anti)

---

## 1. Niyə Governance Kritikdir <a name="why"></a>

2022-də AI governance topik akademik idi. 2025-2026-da **operational və legal məcburiyyətə** çevrildi. Bir neçə trend güclə itələyir:

### 1.1 Regulatory Pressure

- **EU AI Act**: 2024-08-01 qüvvəyə girdi, obligations 2025-2027 arasında fazalı aktivləşir
- **GDPR Article 22**: Automated decision-making — 2016-dan, amma AI-də yenidən interpret olunur
- **Colorado AI Act (2026-02)**: ABŞ-da ilk geniş AI law
- **NYC LL144**: hiring-də AI audit məcburi
- **China Generative AI Regulations (2023)**
- **UK AISI pre-deployment testing**

### 1.2 Enterprise Customer Demand

B2B sales-də RFP-lərdə standart sualllar:

- "AI sistemləri üçün governance framework-ünüz var?"
- "SOC 2 report-unuz AI scope daxil edir?"
- "Sub-processor-larınız (Anthropic, OpenAI) DPA var?"
- "Data residency təminatı?"
- "Model drift monitoring və audit trail?"

Bu suallara cavab yoxdursa — deal ölür.

### 1.3 Investor və Board

Due diligence-də AI exposure və risk. Board-level "AI committee" trend 2024-2025.

### 1.4 Reputation və Trust

Air Canada, DPD, Samsung data leak — hər biri public incident + legal + PR damage. Proaktif governance-in cost-u incident-in 1%-i.

### 1.5 Competitive Advantage

Compliance-heavy sektor-larda (finance, healthcare, government) governance-siz tətbiq imkansızdır. Governance-i "have" olan şirkətlər contract-ları qazanır.

---

## 2. Governance Piramidası <a name="pyramid"></a>

```
┌──────────────────────────────────────────────────────────┐
│            Three-Layer Governance Structure              │
├──────────────────────────────────────────────────────────┤
│                                                          │
│   REGULATORY (external)                                  │
│     - EU AI Act, GDPR, DSA                               │
│     - US: Colorado, NYC, CCPA, sector-specific           │
│     - Sector: HIPAA (health), FCRA (credit), FERPA (edu) │
│                                                          │
│        ↕ compliance mapping                              │
│                                                          │
│   ENTERPRISE POLICY (your org)                           │
│     - AI acceptable use policy                           │
│     - Approved providers list                            │
│     - Data classification × AI tier                      │
│     - Ethics review process                              │
│     - Incident response                                  │
│                                                          │
│        ↕ implementation                                  │
│                                                          │
│   PRODUCT / ENGINEERING                                  │
│     - Model selection, prompt design                     │
│     - Data pipeline with residency                       │
│     - Monitoring, audit logs                             │
│     - User disclosure, opt-out                           │
│     - Red team, eval                                     │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

Senior engineer-lər adətən "product/engineering" layer-dadır, amma enterprise policy-ni başa düşməlidirlər — bəzən aşağıdan yuxarı push etmək lazım gəlir ("mövcud policy AI üçün inadequate-dir").

---

## 3. EU AI Act Risk Tier-ləri <a name="eu-ai-act"></a>

AI Act bütün AI sistemləri 4 risk tier-inə bölür. Sizin tətbiqiniz hansı tier-dədir?

### Tier 1: Unacceptable (Qadağandır)

- Social scoring by governments
- Real-time biometric identification in public (limited exceptions)
- Subliminal manipulation
- Emotion recognition in workplace/school
- Biometric categorisation by protected attributes

Bu sistemlər **qadağandır**, obligation yoxdur — edə bilməzsiniz.

### Tier 2: High-Risk

Annex III-də siyahılanır:

- Biometric identification and categorisation
- Critical infrastructure management (water, gas, electricity)
- Education and vocational training (admission, exams)
- Employment and workforce management (hiring, promotion, firing)
- Essential private and public services (credit scoring, insurance, emergency)
- Law enforcement
- Migration and border control
- Administration of justice

Əksər enterprise AI tətbiqləri **Tier 3-dədir**, amma HR bot-u "work performance evaluation" edirsə — Tier 2 olur.

### Tier 3: Limited Risk (Transparency)

- Chatbot-lar (user bilməlidir AI-ilə ünsiyyətdədir)
- Emotion recognition (non-workplace)
- Deepfakes (disclosed)
- Generative AI output (labeled)

Obligation: **transparency** (user disclosure).

### Tier 4: Minimal Risk

- AI-enabled spam filter
- AI-in inventory management
- Video games

Obligation yoxdur, voluntary code of conduct encouraged.

### Decision Tree

```
Sizin AI sistem:
├── Social scoring / subliminal / emotion in workplace?
│   └── TIER 1: qadağan
├── Annex III domain (HR, credit, critical infra, ...)?
│   └── TIER 2: high-risk → tam compliance obligations
├── User interaction, chatbot, content gen?
│   └── TIER 3: transparency obligations
└── Internal, no user-facing AI decision?
    └── TIER 4: minimal
```

---

## 4. EU AI Act Obligation-ları <a name="obligations"></a>

Hər tier-in obligation-ları fərqlidir. Ən ağırı **Tier 2 (high-risk)**.

### 4.1 High-Risk Obligations (Article 8-17)

```
┌──────────────────────────────────────────────────────────┐
│        High-Risk AI System Obligations                   │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  1) Risk Management System (Art. 9)                      │
│     - Iterative, throughout lifecycle                    │
│     - Identify, estimate, evaluate, mitigate risks       │
│                                                          │
│  2) Data Governance (Art. 10)                            │
│     - Training data quality, representativeness          │
│     - Bias testing                                       │
│     - Data provenance documentation                      │
│                                                          │
│  3) Technical Documentation (Art. 11, Annex IV)          │
│     - System description, design choices                 │
│     - Training methodology                               │
│     - Performance metrics                                │
│     - Updates to be logged                               │
│                                                          │
│  4) Record-Keeping / Logs (Art. 12)                      │
│     - Automatic logging of events                        │
│     - Retain for accountability                          │
│                                                          │
│  5) Transparency to Users (Art. 13)                      │
│     - Instructions for use                               │
│     - Characteristics, capabilities, limitations         │
│                                                          │
│  6) Human Oversight (Art. 14)                            │
│     - Effective human oversight measures                 │
│     - Stop/override mechanism                            │
│                                                          │
│  7) Accuracy, Robustness, Cybersecurity (Art. 15)        │
│     - Appropriate levels                                 │
│     - Resilient to errors, adversarial attacks           │
│     - → red teaming implied                              │
│                                                          │
│  8) Quality Management System (Art. 17)                  │
│     - Compliance strategy                                │
│     - Design/development/testing procedures              │
│                                                          │
│  9) Conformity Assessment (Art. 43)                      │
│     - Before placing on market                           │
│     - Self-assessment or notified body                   │
│                                                          │
│ 10) Registration (Art. 71)                               │
│     - EU database of high-risk systems                   │
│                                                          │
│ 11) Post-Market Monitoring (Art. 72)                     │
│     - Active, systematic                                 │
│     - Serious incidents reportable within 15 days       │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

### 4.2 General-Purpose AI Model Obligations (Art. 53-55)

GPAI providers (Anthropic, OpenAI, Google) üçün ayrı obligations:

- Technical documentation (Annex XI)
- Training data summary
- Copyright policy
- EU Code of Practice compliance

Əgər model "systemic risk" thresholdunu keçirsə (10^25 FLOPs training — hal-hazırda GPT-4, Claude Opus, Gemini Ultra səviyyə):

- Red teaming məcburi
- Incident reporting
- Cybersecurity protection
- Energy efficiency documentation

### 4.3 Tier 3 Transparency (Art. 50)

- Chatbot: user bilməlidir AI ilə dialoqdadır (unless obvious)
- Synthetic content (text, image, audio, video): machine-readable marking
- Deepfake: disclosed
- Emotion recognition: inform user

Practical: UI-də "You are chatting with an AI assistant" banner.

### 4.4 Fines

- Prohibited practices: €35M yox ya da 7% global turnover (higher)
- High-risk non-compliance: €15M yox ya da 3%
- Incorrect info to authorities: €7.5M yox ya da 1%

SMB-lər üçün reduced fines, amma "startup-a toxunmaz" yoxdur.

---

## 5. EU AI Act Timeline <a name="timeline"></a>

```
2024-08-01: Entered into force
2025-02-02: Prohibited practices banned
2025-08-02: GPAI model rules apply (existing models)
2026-08-02: Most obligations for high-risk systems
2027-08-02: Full applicability (legacy high-risk systems)
```

### Bu nə deməkdir 2026 Aprel-də?

- **Prohibited practices**: artıq enforced
- **GPAI**: OpenAI, Anthropic, Google bir ildir compliance-dədir
- **High-risk systems**: 4 ay qalıb. Əgər siz high-risk AI operate edirsinizsə və hazır deyilsinizsə — cəld olun.

### Practical Timeline for Implementation

```
Planning phase (3 months):
  - Determine risk tier
  - Gap analysis vs obligations
  - Resource allocation (legal, engineering)

Implementation phase (6 months):
  - Technical documentation writing
  - Data governance processes
  - Audit logging build-out
  - Human oversight mechanism
  - Risk management system
  - Post-market monitoring setup

Validation phase (3 months):
  - Internal audit
  - External legal review
  - Conformity assessment
  - Registration (if high-risk)
```

Tipik high-risk compliance project: 9-12 ay, €200k-1M cost (mid-size company).

---

## 6. GDPR və AI Kəsişməsi <a name="gdpr"></a>

GDPR 2018-dən qüvvədədir, amma AI konteksində yeni interpretasiyalar.

### 6.1 Article 22 — Automated Decision-Making

"Data subject shall have the right not to be subject to a decision based solely on automated processing, including profiling, which produces legal effects concerning him or her or similarly significantly affects him or her."

Praktiki olaraq:
- Kredit rədd qərarı AI tərəfindən avtomatik? Article 22 tətbiq olunur
- HR işə qəbul decision AI-dən? Article 22
- Recommendation/suggestion (decision user-dir)? Article 22 tətbiq olunmur

Obligation:
- Meaningful human intervention required
- User-ə explain etmək üçün access
- Challenge right

### 6.2 Right to Explanation

Recital 71 — "right to obtain an explanation of the decision reached". LLM-lər black box-dur — necə explain edirsiniz?

Yanaşmalar:
- **Local explanation**: "Your application was rejected because X, Y, Z factors"
- **Global explanation**: "The system considers these factors overall"
- **Counterfactual**: "If your credit score were X+50, you would be approved"
- **SHAP / LIME**: feature importance

LLM-specific: model-in reasoning chain-ini expose etmək (transparent reasoning). Claude-un `reasoning` output-u bu purpose üçün faydalıdır.

### 6.3 DPIA (Data Protection Impact Assessment)

GDPR Article 35 — high-risk processing üçün DPIA məcburi. AI sistem əksəriyyət hallarda high-risk sayılır:

- Systematic, extensive evaluation
- Large-scale processing of sensitive data
- Systematic monitoring
- Decisions producing legal effects

### 6.4 Data Minimization

LLM context-ə nə göndərirsiniz? User-in email-i, ID-si, tam history? GDPR-in data minimization principle: "only what's necessary".

Practical: user message-də PII redact (file 53), user-ə specific-olmayan prompt template-ləri istifadə et.

### 6.5 Training Data və LLM Providers

Siz API istifadəçisisinizsə, Anthropic/OpenAI user input-u training-ə daxil etmir (default, enterprise plans). Amma:

- Check DPA (Data Processing Agreement)
- Free tiers bəzən data retain edir
- Consumer products (ChatGPT) fərqli

Enterprise deployments:
- Anthropic: API input yalnız 30 gün abuse monitoring üçün, train-də istifadə olunmur
- OpenAI: API input default "not used for training", zero data retention opt-in
- Google Vertex AI: customer data not used for training

### 6.6 Data Residency

GDPR data EU-dan çıxarılanda adequacy decision və ya safeguards lazımdır. Schrems II (2020) hökmü EU-US Privacy Shield-i ləğv etdi.

Current status:
- **EU-US Data Privacy Framework** (2023): adequacy decision, amma challenged
- **Standard Contractual Clauses (SCCs)**: əlavə safeguards
- **Regional data centers**: AWS EU, Azure EU — data EU-da qalır

Praktiki: AI provider seçəndə regional deployment soruşun. Anthropic Bedrock Frankfurt region, OpenAI Azure EU region.

---

## 7. US Regulatory Landscape <a name="us"></a>

Federal AI law yoxdur (2026-04 etibarilə), amma fragmented state + sector laws artır.

### 7.1 Executive Order 14110 (2023-10)

Biden administration EO. 2025 Trump administration tərəfindən kənar edildi, amma bəzi componentləri (AI Safety Institute) davam etdi.

### 7.2 Colorado AI Act (effective 2026-02)

ABŞ-da ilk geniş AI law. "Consequential decisions" AI-lə edilən hallar üçün:

- Developer və deployer obligations
- Impact assessment
- Bias testing
- Consumer notification
- Right to appeal

Scope: credit, employment, education, housing, insurance, healthcare, legal, government services.

### 7.3 NYC Local Law 144 (2023-07)

HR-də AI/ML tool istifadə edən şirkətlər:
- Annual bias audit (independent auditor)
- Public disclosure of audit results
- Candidate notification

NYC-də hire edən hər şirkət təsir altında.

### 7.4 California

- **AB 2013 (2024)**: generative AI training data disclosure
- **SB 1047 (2024 vetoed, 2025 reintroduced)**: frontier model safety
- **CCPA/CPRA**: GDPR-ə bənzər privacy rights
- **AB 2655**: deepfake election content

### 7.5 Sector-Specific

- **HIPAA**: healthcare AI — BAA (Business Associate Agreement) with provider
- **FCRA**: credit decisions — adverse action notice, human oversight
- **FERPA**: student data — education AI
- **GLBA**: financial services privacy
- **Fair Housing Act**: AI in housing decisions, disparate impact analysis

### 7.6 Federal Trade Commission (FTC)

FTC enforcement:
- 2023 "AI products truth in advertising"
- 2024 "Operation AI Comply" — deceptive AI claims
- Consent decrees requiring model deletion (algorithm deletion remedy)

---

## 8. ISO/IEC 42001 <a name="iso"></a>

ISO/IEC 42001:2023 — AI Management System standard. ISO 27001-ə analoq AI üçün.

### 8.1 Nə Verir

- Framework for AI governance
- Certification (external auditor) — B2B-də trust signal
- Policy, process, control structure
- Risk-based approach

### 8.2 Key Requirements

- **Context of the organization**: AI uses, stakeholders
- **Leadership**: AI policy, roles
- **Planning**: objectives, impact assessment
- **Support**: resources, awareness, documented info
- **Operation**: risk treatment, lifecycle management
- **Performance evaluation**: monitoring, internal audit, management review
- **Improvement**: non-conformity, continual improvement

### 8.3 ISO 42001 vs AI Act

- **ISO 42001**: voluntary, framework
- **AI Act**: mandatory (EU), legal obligation

Amma ISO 42001 sertifikatı AI Act compliance-ə proof-un bir hissəsi ola bilər (presumption of conformity bəzi obligations üçün).

### 8.4 Implementation Roadmap

```
Month 1-2: Gap analysis
Month 3-4: Policy and procedure writing
Month 5-8: Implementation and training
Month 9-10: Internal audit
Month 11-12: Certification audit (external)
```

Cost: €30k-150k depending on size and scope.

---

## 9. NIST AI RMF <a name="nist"></a>

NIST AI Risk Management Framework (2023-01, v1.0). Voluntary framework US-da.

### 9.1 4 Functions

```
GOVERN (cross-cutting)
  - AI risks managed within culture
  - Accountability, roles
  - Legal, regulatory compliance

MAP
  - Context categorization
  - AI system lifecycle understanding
  - Risks identified

MEASURE
  - Analyze, assess, benchmark
  - Quantitative + qualitative

MANAGE
  - Prioritize risks
  - Respond, monitor
```

### 9.2 GenAI Profile

2024-07 NIST GenAI Profile əlavə etdi — LLM-specific guidance. Burada:
- CBRN (Chemical, Biological, Radiological, Nuclear) risks
- Confabulation (hallucination)
- Dangerous/violent content
- Data privacy
- Environmental impact
- Harmful bias
- Human-AI configuration
- Information integrity
- Information security
- Intellectual property
- Obscene/degrading content
- Value chain / component integration

Hər biri üçün MEASURE actions.

### 9.3 AI Act ilə Mapping

EU AI Act obligations NIST RMF functions-ə mapping edilə bilər:

| AI Act | NIST RMF |
|--------|----------|
| Art. 9 Risk Mgmt | GOVERN + MANAGE |
| Art. 10 Data Governance | MAP |
| Art. 11 Technical Docs | MAP + GOVERN |
| Art. 12 Logging | MEASURE |
| Art. 14 Human Oversight | MANAGE |
| Art. 15 Accuracy/Robustness | MEASURE |

Dual compliance — bir framework-də gördüyünüz iş digər framework-də də kömək edir.

---

## 10. SOC 2 və AI <a name="soc2"></a>

SOC 2 (System and Organization Controls 2) — US-based audit framework. Trust Service Criteria: Security, Availability, Processing Integrity, Confidentiality, Privacy.

### 10.1 AI Scope

AICPA 2024-də SOC 2-də AI-ə aid əlavə guidance dərc etdi. Əgər şirkət AI sistem operate edirsə:

- System description AI components-i təsvir etməlidir
- Controls AI-specific risklərə toxunur (model drift, bias, data leakage)
- Sub-processors (Anthropic, OpenAI) disclosed

### 10.2 SOC 2 + AI Controls Examples

```
Control: "LLM model selection documented with rationale"
Evidence: ADR (Architecture Decision Record), model card

Control: "Prompt changes go through code review"
Evidence: Git blame, PR approvals

Control: "Model drift monitored with alerts"
Evidence: Dashboard, alert history

Control: "Red team testing conducted quarterly"
Evidence: Test reports, findings tracker

Control: "PII redaction before LLM call"
Evidence: Middleware code, audit logs

Control: "Audit trail of all LLM requests"
Evidence: 180+ day log retention, query samples
```

### 10.3 SOC 2 Type 2 Enterprise Demand

Enterprise customers soruşur: "SOC 2 Type 2 report göstərin". Type 2 = 6-12 ay period-da controls-un faktiki işlədiyini audit.

AI components daxil olmadan SOC 2 report artıq enterprise sales-də kifayət deyil (2025+).

---

## 11. Model Cards və System Cards <a name="model-cards"></a>

Model card — AI sistem haqqında structured documentation.

### 11.1 Model Card Content

```yaml
# Model Card: Support Bot v1.5
model_name: support_bot_production
version: 1.5.0
date: 2026-04-20
model_provider: Anthropic
base_model: claude-sonnet-4-5-20260220
fine_tuning: none

purpose: |
  Customer support automation for Acme Corp.
  Answers product questions, handles returns, escalates to human.

intended_use:
  - Customer service chat
  - 24/7 availability
  - English and Azerbaijani

out_of_scope:
  - Medical advice
  - Legal advice
  - Financial advice
  - Crisis / self-harm situations (auto-escalate)

training_data: |
  Base model: Anthropic proprietary (see Anthropic Claude model card).
  System prompt + tools: defined internally.
  No fine-tuning; prompt engineering only.

metrics:
  golden_suite_pass_rate: 97.8%
  user_satisfaction_thumbs_up: 84%
  escalation_rate: 12%
  hallucination_rate_measured: 2.1%
  avg_latency_p95_ms: 2400
  cost_per_interaction_usd: 0.012

risks:
  - Hallucinated policy information (mitigation: RAG + human escalation)
  - PII leakage (mitigation: redaction, file 53)
  - Prompt injection (mitigation: file 52 defenses)
  - Model drift (mitigation: weekly eval, file 59)

limitations:
  - No real-time knowledge post training cutoff
  - May refuse legitimate edge-case requests
  - Azerbaijani quality lower than English

evaluations_conducted:
  - Internal golden suite: 500 prompts
  - Red team: quarterly (file 60)
  - Bias audit: demographic subgroups
  - Safety: Anthropic built-in + OpenAI Moderation

human_oversight:
  - Escalation to human for: legal, medical, financial, billing disputes
  - Weekly manual review of 100 random interactions
  - Appeals process for blocked content

data_handling:
  - User messages: redacted before LLM call
  - Logs: 180 days, encrypted at rest
  - No user data in training

change_log:
  - 2026-04-20: v1.5 — added tool for order lookup
  - 2026-03-05: v1.4 — refund policy update
  - 2026-02-01: v1.3 — multi-language support
```

### 11.2 System Card (broader)

System card — bütün AI sisteminin dokumentasiyası. Model card birləşdir + infra + deployment + monitoring.

Anthropic "Claude Model Card", OpenAI "GPT-4 System Card" — reference nümunələr.

### 11.3 Version Control

Model card Git-də `docs/model-cards/support_bot_v1.5.md`. Hər model release ilə yenilənir, approval (reviewers).

### 11.4 External Publication

B2B-də customer model card request edə bilər. Public-facing (external):
- Redacted version
- Risks disclosed honestly
- Metrics aggregated

---

## 12. AI Bill of Materials (AIBOM) <a name="aibom"></a>

Software Bill of Materials (SBOM) software supply chain üçün. AIBOM — AI-specific. Biden Executive Order 14028 bu direction-ı push etdi.

### 12.1 AIBOM Content

```yaml
# AIBOM: Acme Customer Support v1.5
system: acme_support_bot
version: 1.5.0
generated_at: 2026-04-20

components:
  - type: foundation_model
    name: claude-sonnet-4-5
    provider: Anthropic
    version: 20260220
    license: Anthropic Commercial Terms
    usage_scope: inference_only
    data_retention_policy: 30_days_abuse_monitoring

  - type: embedding_model
    name: voyage-3
    provider: Voyage AI
    version: 20250115

  - type: moderation_classifier
    name: omni-moderation-latest
    provider: OpenAI
    version: 20240920

  - type: vector_database
    name: pgvector
    version: 0.8.0
    self_hosted: true
    deployment_region: eu-west-1

  - type: prompt
    name: support_bot_system_prompt
    sha: a3f8c2e1
    path: app/prompts/support_bot/v1.5.0.md

  - type: rag_corpus
    name: help_center_docs
    version: corpus_2026_04_10
    document_count: 2847
    last_refresh: 2026-04-10

  - type: tool
    name: order_lookup
    internal: true
    database_scope: orders.read_only

  - type: tool
    name: create_return
    internal: true
    database_scope: returns.create
    requires_approval: true

sub_processors:
  - Anthropic PBC (US, EU)
  - OpenAI (US)
  - Voyage AI (US)
  - AWS (deployment, eu-west-1)

dependencies:
  - php: 8.4
  - laravel: 11.x
  - anthropic-sdk-php: ^1.0
  - voyageai-php: ^0.3
```

### 12.2 Niyə AIBOM Lazımdır

- **Supply chain transparency**: hansı sub-processor-lar istifadə olunur?
- **Vulnerability response**: "Anthropic CVE-2026-xxxx" — siz təsir altındasızmı?
- **License compliance**: hər komponent-in terms-i
- **Regulatory**: EU AI Act, NIST RMF mapping
- **B2B sales**: customer due diligence

### 12.3 Automation

AIBOM manual saxlamaq adi pozulur. Automation:

```php
// app/Console/Commands/GenerateAibom.php

class GenerateAibom extends Command
{
    public function handle()
    {
        $aibom = [
            'system' => config('app.name'),
            'version' => config('app.version'),
            'generated_at' => now()->toIso8601String(),
            'components' => [
                [
                    'type' => 'foundation_model',
                    'name' => config('ai.model'),
                    'version' => config('ai.model_version'),
                ],
                // from prompt registry
                ...PromptRegistry::activePrompts()->map(fn ($p) => [
                    'type' => 'prompt',
                    'name' => $p->name,
                    'sha' => $p->sha,
                ]),
                // from tool registry
                ...ToolRegistry::all()->map(fn ($t) => [
                    'type' => 'tool',
                    'name' => $t->name,
                    'scope' => $t->scope,
                ]),
            ],
        ];

        Storage::put('compliance/aibom.json', json_encode($aibom, JSON_PRETTY_PRINT));
    }
}
```

Schedule daily + in CI on deploy.

---

## 13. DPIA Template <a name="dpia"></a>

DPIA (Data Protection Impact Assessment) GDPR-də high-risk processing üçün məcburi. AI sistem adətən high-risk sayılır.

### 13.1 DPIA Structure

```markdown
# DPIA: Customer Support AI Bot

## 1. Description of Processing
- What: LLM-based chatbot for customer support
- Why: automation, 24/7 availability
- Legal basis: legitimate interest (Art. 6(1)(f)) + contract performance

## 2. Data Categories
- User identifier (session ID, if logged in: user_id)
- Message content (user queries, may contain PII)
- Conversation metadata (timestamps, satisfaction rating)

## 3. Data Recipients
- Internal: engineering (debug, with redaction)
- Processors: Anthropic (US/EU), OpenAI Moderation (US)
- Cross-border: EU-US under DPF/SCCs

## 4. Retention
- Conversation logs: 180 days
- Aggregated metrics: 2 years
- Appeals: 2 years

## 5. Risk Assessment
Risk 1: PII leakage to LLM provider
  Likelihood: Medium
  Impact: High (regulatory fine, user trust)
  Mitigations: PII redaction layer (file 53), DPA with provider

Risk 2: Discriminatory responses
  Likelihood: Low
  Impact: High
  Mitigations: Bias testing, demographic subgroup eval, refusal training

Risk 3: Incorrect advice causing user harm
  Likelihood: Medium
  Impact: High (legal, Air Canada precedent)
  Mitigations: RAG grounding, escalation for high-stakes, disclaimer

Risk 4: Re-identification from anonymized data
  Likelihood: Low
  Impact: Medium
  Mitigations: k-anonymity, audit of analytics

Risk 5: Model drift causing silent degradation
  Likelihood: Medium
  Impact: Medium
  Mitigations: Weekly eval (file 59), canary deploys (file 61)

## 6. Safeguards
- Technical: redaction, moderation, audit logs, encryption at rest
- Organizational: access controls, training, incident response
- Contractual: DPA with all processors, SCCs for cross-border

## 7. User Rights Implementation
- Access: user dashboard shows conversation history
- Rectification: users can edit profile data
- Erasure: erasure request handler (with legal hold exceptions)
- Portability: JSON export
- Objection: opt-out of AI processing, routed to human

## 8. Residual Risk
After mitigations: Medium. Accepted by DPO and CTO on 2026-04-15.

## 9. Consultation
- DPO consulted: yes
- Supervisory authority consulted: no (not required unless high residual risk)
- Users consulted: focus group feedback, 2025-12

## 10. Review Schedule
- Annual or when material change
- Next review: 2027-04-15
```

### 13.2 DPIA Versioning

Git-də tracked. Hər material change (new feature, new provider, new data type) — yeni DPIA version.

---

## 14. Audit Trail Requirements <a name="audit"></a>

Audit trail — tam event log kim/nə vaxt/nə etdi. Həm regulatory həm də operational necessity.

### 14.1 Nə Log Edilməlidir

```
┌──────────────────────────────────────────────────────────┐
│          Audit Events for AI System                      │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  USER ACTIONS                                            │
│    - Query submitted                                     │
│    - Response received                                   │
│    - Thumbs up/down                                      │
│    - Appeal submitted                                    │
│                                                          │
│  SYSTEM ACTIONS                                          │
│    - Moderation decision (allow/block/flag)              │
│    - Human escalation triggered                          │
│    - Tool invocation                                     │
│    - Error occurred                                      │
│                                                          │
│  CONFIG CHANGES                                          │
│    - Prompt updated (by whom, approved by)               │
│    - Model version change                                │
│    - Feature flag toggle                                 │
│    - RAG corpus ingest                                   │
│                                                          │
│  ADMIN ACTIONS                                           │
│    - Reviewer decision                                   │
│    - User account action (suspend, unblock)              │
│    - Data access (engineer reading logs)                 │
│    - Export / deletion request handling                  │
│                                                          │
│  INCIDENTS                                               │
│    - Rollback                                            │
│    - Red team finding                                    │
│    - CSAM detection                                      │
│    - Provider outage                                     │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

### 14.2 Laravel Audit Log Schema

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->string('event_type');          // user_query, config_change, etc.
    $table->string('actor_type');           // user, admin, system
    $table->unsignedBigInteger('actor_id')->nullable();
    $table->string('subject_type')->nullable(); // Model class
    $table->unsignedBigInteger('subject_id')->nullable();
    $table->json('context');                 // what changed, from→to
    $table->string('correlation_id')->nullable();
    $table->string('source_ip')->nullable();
    $table->string('user_agent')->nullable();
    $table->timestamp('occurred_at');
    $table->index(['event_type', 'occurred_at']);
    $table->index('correlation_id');
    $table->index(['actor_type', 'actor_id']);
});
```

### 14.3 Observable Trait

```php
// app/Traits/Auditable.php

trait Auditable
{
    protected static function bootAuditable()
    {
        static::updating(function ($model) {
            AuditLog::create([
                'event_type' => 'model_updated',
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'context' => [
                    'changes' => $model->getDirty(),
                    'original' => $model->getOriginal(),
                ],
                'occurred_at' => now(),
            ]);
        });
    }
}
```

### 14.4 Retention

| Event Type | Retention |
|-----------|-----------|
| LLM interactions | 180 days (or per DPIA) |
| Admin actions | 7 years |
| CSAM-related | 7+ years (legal) |
| Config changes | Indefinite (version control) |
| Compliance reports | 10 years |

### 14.5 Tamper-Resistance

Audit log-lar writable olmalıdır amma non-modifiable. Options:
- Append-only database partition
- External audit service (AWS CloudTrail, Datadog)
- Blockchain-anchored (overkill for most)
- Hash chain: each log entry includes hash of previous

---

## 15. Vendor Management <a name="vendor"></a>

AI sistem əsasən multiple third-party provider-lərə istinad edir. Vendor management critical.

### 15.1 Vendor Evaluation Checklist

```
Anthropic (API provider):
  ✓ SOC 2 Type 2 report (current)
  ✓ ISO 27001 certified
  ✓ GDPR DPA available
  ✓ Data residency: US default, EU option
  ✓ Sub-processors disclosed
  ✓ Data retention: 30 days abuse only
  ✓ Training use: no (enterprise)
  ✓ Breach notification: contractual 72 hours
  ✓ Security contact: security@anthropic.com
  ✓ Transparency report published
  ✓ RSP (Responsible Scaling Policy) published
```

Hər vendor üçün eyni checklist. Missing items → decision: accept risk, seek alternative, compensating controls.

### 15.2 DPA (Data Processing Agreement)

GDPR Article 28 — controller (siz) və processor (Anthropic) arasında DPA məcburi. DPA-da olmalıdır:

- Subject matter and duration
- Nature and purpose of processing
- Type of personal data
- Categories of data subjects
- Controller obligations and rights
- Processor obligations
- Sub-processor management
- Technical and organizational measures
- Assistance with data subject requests
- Breach notification obligation
- Return/deletion at end of contract
- Audit rights

Major providers pre-signed DPA-ları var (online accept). Review edin.

### 15.3 Sub-Processor Management

Anthropic öz sub-processor-lər istifadə edir (AWS, Google Cloud). Siz ikinci-dərəcəli müştərisiniz. DPA-nızın müddəaları:

- Sub-processor dəyişiklikləri 30+ gün əvvəl notify
- Right to object (alternative-a keçmək imkanı)
- Sub-processor list public

### 15.4 Vendor Onboarding

Yeni AI vendor təklifi? Prosedur:

```
1. Risk assessment (type of data, criticality)
2. Security questionnaire (SIG, CAIQ)
3. Legal review (DPA, T&C, liability)
4. Compliance review (SOC 2, ISO certs)
5. Technical review (architecture, integration)
6. Pilot deployment (non-production)
7. Formal approval (CIO/DPO sign-off)
8. Production rollout with monitoring
```

Cycle: 4-12 həftə enterprise-də.

### 15.5 Vendor Risk Register

```php
// app/Models/VendorRisk.php

class VendorRisk extends Model
{
    // vendor_name, service, data_categories, risk_rating,
    // soc2_status, dpa_signed_at, last_review_at,
    // sub_processors, residency, breach_history
}
```

Quarterly review. Red flags: DPA expired, certification lapsed, breach reported, regulatory action.

---

## 16. Internal AI Policy <a name="internal-policy"></a>

Şirkət daxilində AI istifadəsi policy.

### 16.1 Approved Providers

```markdown
# Acme AI Acceptable Use Policy v2.0

## Approved Providers
The following AI providers are approved for use:

### Production
- Anthropic Claude (API, via approved DPA)
- OpenAI Moderation API (for content moderation only)

### Development
- GitHub Copilot (individual, company-approved plan)
- Claude.ai (no confidential data)
- ChatGPT (no confidential data, free tier with opt-out)

### Prohibited
- Consumer LLM apps with non-negotiated T&C
- AI tools that retain data for training without opt-out
- Locally-hosted models without security review
```

### 16.2 Data Classification × AI Tier

| Data Class | LLM Use |
|-----------|---------|
| Public | All approved |
| Internal | All approved + redaction |
| Confidential | Enterprise LLM only, with DPA, redaction mandatory |
| Restricted (CSAM, credentials, SSN) | Prohibited |

### 16.3 Use Case Categories

```
✓ Code assistance (prod code OK with Copilot)
✓ Document drafting (internal, with review)
✓ Customer support chatbot (with governance)
✓ Internal tools, admin assistance

△ Review required:
  - HR hiring decisions
  - Credit / financial decisions
  - Security analysis
  - External customer-facing content

✗ Prohibited:
  - Legal advice (without lawyer review)
  - Medical diagnosis
  - Processing of CSAM (to detect is different)
  - Fully-automated decisions with legal effect
```

### 16.4 Review Process

AI feature deployment:

```
1. Submit AI Use Case Request form
2. Risk tier classification (Level 1/2/3/4)
3. Reviews:
   - Level 1 (internal, low risk): team lead approval
   - Level 2 (user-facing, medium risk): privacy + security review
   - Level 3 (high-risk, per AI Act): full committee review
   - Level 4 (prohibited): not approved
4. Documentation: model card, DPIA (if applicable)
5. Monitoring plan established
6. Go/no-go decision
```

---

## 17. AI Ethics Committee <a name="ethics"></a>

Yüksək riskli AI use case-lər üçün cross-functional review body.

### 17.1 Composition

- Chair: CTO or Head of AI/ML
- Legal representative
- Privacy/DPO
- Security lead
- Product representative
- Ethics advisor (may be external)
- Customer-facing representative (understand user impact)

6-8 nəfər. Quarterly meeting + ad-hoc for urgent cases.

### 17.2 Mandate

- Review high-risk AI use cases (Level 3)
- Approve/reject AI policy updates
- Oversee incident postmortems with ethical dimensions
- Resource allocation for governance

### 17.3 Decision Framework

```
For each high-risk use case:

1. Necessity test
   - Does AI actually add value vs alternative?
   - Is AI proportionate to the problem?

2. Stakeholder impact
   - Users affected (who, how many)
   - Potentially vulnerable groups
   - Third parties

3. Bias assessment
   - Historical data biases
   - Subgroup performance disparities

4. Transparency
   - Users informed?
   - Decisions explainable?

5. Accountability
   - Human in the loop?
   - Override mechanism?
   - Appeal process?

6. Safeguards
   - What monitoring?
   - What rollback conditions?
   - What external reviews?

Decision: approve / approve-with-conditions / reject / defer-with-research
```

### 17.4 Meeting Minutes

Audit trail in decision-making:

```
Meeting: 2026-04-15
Attendees: [list]

Case 1: AI-assisted hiring screener
Decision: approve-with-conditions
Conditions:
  - Independent bias audit annually
  - Human review required on all decisions
  - Opt-out for candidates
  - Monitoring for disparate impact quarterly
Vote: 6 approve, 1 defer
```

---

## 18. Practical Org Structure <a name="org"></a>

Mid-size company (100-500 employees) AI governance ownership:

### 18.1 RACI Matrix

| Activity | Engineering | Product | Legal | Security | Privacy/DPO |
|----------|:-----------:|:-------:|:-----:|:--------:|:-----------:|
| AI use case approval | C | R | C | C | A |
| Model selection | R | C | I | C | I |
| Prompt design | R | A | I | C | I |
| Red team program | A | C | C | R | C |
| Incident response | R | I | C | A | C |
| Regulatory reporting | C | I | C | C | R |
| Vendor DPA | I | I | A | C | R |
| Audit log retention | R | I | C | A | C |

R=Responsible, A=Accountable, C=Consulted, I=Informed

### 18.2 Who Owns AI Governance?

Small startups (<50): CTO + part-time legal consultant.

Mid-size (50-500): Dedicated AI/ML lead + DPO + AI committee.

Enterprise (500+): Chief AI Officer + AI governance team (5-10 people).

Key insight: AI governance is **cross-functional**. Pure engineering owner → misses legal/ethics. Pure legal owner → misses technical reality. Balance.

---

## 19. Laravel Compliance Checklist <a name="laravel"></a>

Laravel-based AI application üçün praktiki checklist:

### 19.1 Code & Configuration

```
☐ Prompts in Git, SHA-versioned (file 61)
☐ Model version pinned (file 59)
☐ Provider configured with DPA acceptance (env/secrets manager)
☐ Feature flags for AI features (Pennant, LaunchDarkly)
☐ AIBOM generation automated in CI
☐ Model card maintained in repo
```

### 19.2 Data Handling

```
☐ PII redaction middleware (file 53)
☐ Data residency configured (provider region)
☐ Log retention policy (file 62)
☐ Audit log append-only partition
☐ Encryption at rest for logs
☐ Encryption in transit (TLS 1.3)
☐ Database backup with retention
☐ Erasure request handler (GDPR Art. 17)
```

### 19.3 User Disclosure

```
☐ AI chatbot disclosure ("You're chatting with an AI assistant")
☐ Privacy policy updated for AI processing
☐ Terms of service covers AI
☐ User consent for AI features where required
☐ Opt-out mechanism
```

### 19.4 Human Oversight

```
☐ Escalation mechanism for high-stakes (file 35 HITL)
☐ Appeal process for blocked content (file 62)
☐ Human review queue with SLA
☐ Override controls for admins
☐ Reviewer well-being policy
```

### 19.5 Monitoring

```
☐ Request correlation ID in every log
☐ Variant tag propagation (file 61)
☐ Moderation events logged
☐ Error rate alert
☐ Quality regression detection (file 59)
☐ Cost anomaly detection
☐ Provider outage alerts
```

### 19.6 Security

```
☐ Input validation / injection defense (file 52)
☐ Output moderation (file 62)
☐ Tool authorization (tenant scoping, file 60)
☐ Rate limiting (denial of wallet, file 60)
☐ Red team regression suite in CI (file 60)
☐ Secrets rotated regularly
```

### 19.7 Compliance Documentation

```
☐ DPIA completed and approved
☐ Model card in repository
☐ AIBOM up to date
☐ Vendor DPA on file
☐ AI policy published internally
☐ Transparency report (if user-facing, file 62)
☐ Incident response runbook
☐ Regulatory mapping (EU AI Act obligations ↔ controls)
```

### 19.8 Testing

```
☐ Golden suite (file 61)
☐ Red team tests (file 60)
☐ Bias audit (demographic subgroups)
☐ Load test for rate limits
☐ Chaos test (provider outage simulation)
☐ Penetration test (annual)
```

### 19.9 Artifact: Compliance Dashboard

```php
// Filament compliance dashboard

class ComplianceDashboard extends Page
{
    public function getWidgets(): array
    {
        return [
            DpiaStatusWidget::class,      // all DPIAs current?
            DpaStatusWidget::class,       // all vendor DPAs valid?
            AuditLogHealthWidget::class,  // logs being written?
            ModelCardVersionsWidget::class, // outdated cards?
            IncidentMetricsWidget::class, // open incidents
            RetentionComplianceWidget::class, // logs older than policy?
            RedTeamCoverageWidget::class, // categories with gaps
            VendorRenewalsWidget::class,  // DPAs expiring
        ];
    }
}
```

Quarterly review-də bu dashboard-u print edin, committee-də diskussiya.

---

## 20. Anti-Pattern-lər <a name="anti"></a>

### Anti-Pattern 1: "Compliance-i sonra edəcəyik"

Retrofit governance həmişə 5-10x bahalıdır proaktif-dən. Day 1-dən düzgün audit log, DPIA, model card işləmək sonradan panik-retrofit-dən ucuzdur.

### Anti-Pattern 2: Legal-i Tək Owner Etmək

"Legal compliance-i həll etsin" — legal engineering reality-ni bilmir, eng legal constraint-ləri bilmir. Cross-functional committee zəruri.

### Anti-Pattern 3: "ISO 42001 sertifikatı alsaq bitdi"

Sertifikat snapshot-dır. Ongoing operations davam etməlidir. Audit keçdikdən sonra controls-un real işləməsi vacib.

### Anti-Pattern 4: Provider-ə Tam Güvənmək

"Anthropic compliance-li, biz də onunla compliance-li" — yanlış. Siz controller-siniz, provider processor. Data minimization, user rights, monitoring — sizin öhdəlikdir.

### Anti-Pattern 5: Model Card-ı Static Snapshot Etmək

Model card 2 ay əvvəl yazıldı, deploy-dan sonra heç kim yeniləmədi. Prompt 3 dəfə dəyişib, card köhnədir. **Card-ı deployment-in bir hissəsi et** — prompt update-də card update required.

### Anti-Pattern 6: DPIA "Template"-ə Çevirib Kopyalamaq

Copy-paste DPIA risks-i real başa düşməmədən. Hər AI feature fərqlidir — real risk assessment lazımdır.

### Anti-Pattern 7: Audit Log-u "Nice to Have" Hesab Etmək

Incident olur, "necə oldu?" sualına cavab verə bilmirsiniz. Regulator soruşur, evidence yoxdur. **Audit log compliance evidence-dir** — Day 1 priority.

### Anti-Pattern 8: "Bizim kiçik şirkətik, bu bizə aid deyil"

EU AI Act — SMB exception yoxdur (proportional, amma tam ignore olmur). GDPR — 1 employee şirkətə də tətbiq olunur. Startup-da compliance başlamaq investisiya — sonra exit-də blocker.

### Anti-Pattern 9: Ethics Committee Olmamaq (və ya "şəkil üçün")

Committee var amma real rad edir — heç nə qadağan etmir, approver stamp-dir. Real effektiv olmaq üçün no-saying-ə hazır olmalıdır.

### Anti-Pattern 10: Governance-ni "Engineering Velocity Blocker" Görmək

"Governance yavaşladır" — qısa müddətli doğrudur, uzun müddətli yanlışdır. Incident xərci + regulatory fine + reputation damage governance cost-unu 10x keçir. Governance-i velocity ilə **trade-off kimi** deyil, risk-reduction investment kimi çərçivələ.

---

## Xülasə

AI governance 2026-da **enterprise strict requirement**-dir — legal, operational, və competitive. Saf engineering team bunu öz-özünə implement edə bilməz; cross-functional collaboration lazımdır.

Əsas mesajlar:

1. **Regulatory reality**: EU AI Act 2025-2027 aktiv, GDPR Art. 22 yenidən interpret olunur, US fragmented
2. **Risk tiers** — sizin sistem hansı tier-dədir?
3. **ISO 42001 + NIST RMF** — voluntary frameworks, amma AI Act compliance-ə kömək
4. **SOC 2 scope AI-ni əhatə edir** — enterprise RFP-lərdə standart
5. **Model card, AIBOM, DPIA** — document-based compliance evidence
6. **Audit trail** — 180+ gün standart, 7 il legal hallarda
7. **Vendor management** — DPA, SOC 2, residency; ad-hoc olmamalı
8. **Internal AI policy** — approved providers, data classification × tier
9. **Ethics committee** — cross-functional, real no-saying gücündə
10. **Laravel checklist** — 50+ maddəli, quarterly review
11. **File 35 HITL** — human oversight obligations üçün referens
12. **File 53 PII** — data minimization obligations üçün referens
13. **File 59 drift** — accuracy/robustness obligations üçün referens
14. **File 60 red team** — robustness/security obligations üçün referens
15. **File 62 moderation** — content safety obligations üçün referens

Bu fayllarla (35, 38, 41, 43, 44, 51-54, 59, 60, 61, 62, 63) senior engineer-in production LLM application üçün ehtiyac duyduğu governance + safety + operations stack-i tam əhatə olunur.
