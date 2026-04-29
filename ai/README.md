# AI / LLM Developer Bələdçisi — Senior Backend Mühəndisi üçün

Bu folder senior PHP/Laravel (və Java/Spring, Go) developer-ləri üçün **LLM-ləri real produksiya sistemlərində istifadə etmək** üzərinə qurulmuş biliyin cəmidir. Nə saf teoriya, nə də "hello world" tutorial — real arxitektura, real kod, real cost/latency/failure tradeoff-ları.

> **Fayl sayı:** 111 fayl + README, ~115,000+ sətir. 10 alt-folder. Hər fayl müstəqil oxuna bilər, amma aşağıdakı oxuma yolları ilə ardıcıl getdikdə bilik **asandan çətinə** yığılır.

---

## Oxuma yolları

Müxtəlif məqsədlər üçün müxtəlif ardıcıllıqlar. Sıradakı rəqəmlər **folder içindəki fayl nömrəsi deyil** — oxuma sırasıdır (bəzi folder-lər qlobal numerasiya ilə başlanmış olduğu üçün fayl nömrələri folder-lər arası təkrarlana bilər; burada **filename** göstərilib).

### Yol 1 — Sıfırdan LLM Developer (3-4 həftə)

1. [01-fundamentals/01-how-ai-works.md](01-fundamentals/01-how-ai-works.md) — transformer, attention, inference
2. [01-fundamentals/02-models-overview.md](01-fundamentals/02-models-overview.md) — Claude/GPT/Gemini/Llama landşaftı
3. [01-fundamentals/03-tokens-context-window.md](01-fundamentals/03-tokens-context-window.md) — token, tokenizer, context window
4. [01-fundamentals/04-temperature-parameters.md](01-fundamentals/04-temperature-parameters.md) — sampling parametrləri
5. [01-fundamentals/05-hallucinations.md](01-fundamentals/05-hallucinations.md) — niyə LLM yalan danışır, necə ölçmək
6. [01-fundamentals/06-embedding-vs-generative-models.md](01-fundamentals/06-embedding-vs-generative-models.md) — encoder vs decoder fərqi
7. [01-fundamentals/07-multimodal-ai.md](01-fundamentals/07-multimodal-ai.md) — vision/audio modal
8. [01-fundamentals/08-reasoning-models.md](01-fundamentals/08-reasoning-models.md) — o1/R1/Claude extended thinking (2026 paradiqma)
9. [01-fundamentals/09-llm-provider-comparison.md](01-fundamentals/09-llm-provider-comparison.md) — provider müqayisəsi
10. [01-fundamentals/10-model-selection-decision.md](01-fundamentals/10-model-selection-decision.md) — hansı modeli seçmək
11. [01-fundamentals/11-llm-pricing-economics.md](01-fundamentals/11-llm-pricing-economics.md) — pricing math, unit economics
12. [02-claude-api/01-claude-api-guide.md](02-claude-api/01-claude-api-guide.md) — Anthropic Messages API tam reference
13. [02-claude-api/02-prompt-engineering.md](02-claude-api/02-prompt-engineering.md) — sistemli prompt dizaynı
14. [02-claude-api/03-structured-output.md](02-claude-api/03-structured-output.md) — JSON schema, tool use ilə struktur
15. [02-claude-api/04-tool-use.md](02-claude-api/04-tool-use.md) — function calling / tool use
16. [02-claude-api/05-streaming-responses.md](02-claude-api/05-streaming-responses.md) — SSE streaming
17. [02-claude-api/06-vision-pdf-support.md](02-claude-api/06-vision-pdf-support.md) — image və PDF input
18. [02-claude-api/07-extended-thinking.md](02-claude-api/07-extended-thinking.md) — Claude reasoning mode
19. [02-claude-api/08-files-api-citations.md](02-claude-api/08-files-api-citations.md) — Files API + verbatim citations
20. [02-claude-api/09-prompt-caching.md](02-claude-api/09-prompt-caching.md) — 90% cost qənaəti
21. [02-claude-api/10-batch-api.md](02-claude-api/10-batch-api.md) — batch processing 50% endirim
22. [02-claude-api/11-rate-limits-retry-php.md](02-claude-api/11-rate-limits-retry-php.md) — 429, exponential backoff, Laravel
23. [02-claude-api/12-computer-use.md](02-claude-api/12-computer-use.md) — ekran vizyonu + mouse/keyboard
24. [02-claude-api/13-claude-agent-sdk.md](02-claude-api/13-claude-agent-sdk.md) — Agent SDK giriş
25. [02-claude-api/14-context-engineering.md](02-claude-api/14-context-engineering.md) — kontekst pəncərəsi dizaynı, token büdcəsi, prefilling

### Yol 2 — RAG Developer (2 həftə, Yol 1-dən sonra)

1. [04-rag-embeddings/01-embeddings-vector-search.md](04-rag-embeddings/01-embeddings-vector-search.md) — cosine, dot, vektorlar
2. [04-rag-embeddings/02-vector-databases.md](04-rag-embeddings/02-vector-databases.md) — pgvector, Pinecone, Qdrant, Weaviate
3. [04-rag-embeddings/03-rag-architecture.md](04-rag-embeddings/03-rag-architecture.md) — retrieve → augment → generate
4. [04-rag-embeddings/04-chunking-strategies.md](04-rag-embeddings/04-chunking-strategies.md) — fixed/semantic/recursive chunking
5. [04-rag-embeddings/05-query-transformation-hyde.md](04-rag-embeddings/05-query-transformation-hyde.md) — HyDE, multi-query, step-back, RAG-Fusion
6. [04-rag-embeddings/06-reranking-hybrid-search.md](04-rag-embeddings/06-reranking-hybrid-search.md) — BM25 + vektor + reranker
7. [04-rag-embeddings/07-contextual-retrieval.md](04-rag-embeddings/07-contextual-retrieval.md) — Anthropic 2024 texnikası, 49% dəqiqlik artımı
8. [04-rag-embeddings/08-knowledge-graph-ai.md](04-rag-embeddings/08-knowledge-graph-ai.md) — Neo4j + RAG
9. [04-rag-embeddings/09-long-context-vs-rag.md](04-rag-embeddings/09-long-context-vs-rag.md) — 1M kontekstdə RAG-a hələ ehtiyac var?
10. [04-rag-embeddings/10-agentic-rag.md](04-rag-embeddings/10-agentic-rag.md) — retrieval tool kimi, CRAG, Self-RAG
11. [04-rag-embeddings/11-rag-evaluation-rerank.md](04-rag-embeddings/11-rag-evaluation-rerank.md) — Ragas, groundedness, A/B
12. [04-rag-embeddings/12-multimodal-rag.md](04-rag-embeddings/12-multimodal-rag.md) — şəkil+mətn retrieval, ColPali, PDF visual

### Yol 3 — Agent Developer (2-3 həftə, Yol 1 + RAG-dan sonra)

1. [05-agents/01-ai-agents-overview.md](05-agents/01-ai-agents-overview.md) — agent nədir, nə deyil
2. [05-agents/02-agent-reasoning-patterns.md](05-agents/02-agent-reasoning-patterns.md) — ReAct, Reflexion, ToT, Plan-Execute
3. [05-agents/03-agent-tool-design-principles.md](05-agents/03-agent-tool-design-principles.md) — tool schema, idempotency
4. [05-agents/04-agent-memory-systems.md](05-agents/04-agent-memory-systems.md) — short-term/long-term/episodic
5. [05-agents/05-build-custom-agent-laravel.md](05-agents/05-build-custom-agent-laravel.md) — Laravel-də agent qurmaq
6. [05-agents/06-claude-agent-sdk-deep.md](05-agents/06-claude-agent-sdk-deep.md) — Claude Agent SDK dərin
7. [05-agents/07-multi-agent-systems.md](05-agents/07-multi-agent-systems.md) — multi-agent giriş
8. [05-agents/08-agent-orchestration-patterns.md](05-agents/08-agent-orchestration-patterns.md) — supervisor, hierarchical, swarm
9. [05-agents/09-human-in-the-loop.md](05-agents/09-human-in-the-loop.md) — approval gate, async handoff
10. [05-agents/10-claude-code-skills-hooks.md](05-agents/10-claude-code-skills-hooks.md) — Claude Code skills/hooks
11. [05-agents/11-agent-evaluation-evals.md](05-agents/11-agent-evaluation-evals.md) — agent eval giriş
12. [05-agents/12-ai-agent-evaluation-patterns.md](05-agents/12-ai-agent-evaluation-patterns.md) — trajectory, end-state, benchmark
13. [05-agents/13-agent-security.md](05-agents/13-agent-security.md) — prompt injection, tool sandboxing, audit logging

### Yol 4 — MCP Developer (1 həftə, Yol 1-dən sonra)

1. [03-mcp/01-mcp-what-is.md](03-mcp/01-mcp-what-is.md) — protokol giriş
2. [03-mcp/02-mcp-resources-tools-prompts.md](03-mcp/02-mcp-resources-tools-prompts.md) — primitiv-lər
3. [03-mcp/03-mcp-transports-deep.md](03-mcp/03-mcp-transports-deep.md) — stdio, HTTP, SSE, WebSocket
4. [03-mcp/04-mcp-server-build-node.md](03-mcp/04-mcp-server-build-node.md) — Node server
5. [03-mcp/05-mcp-server-build-php.md](03-mcp/05-mcp-server-build-php.md) — PHP server
6. [03-mcp/06-mcp-client-integration.md](03-mcp/06-mcp-client-integration.md) — client-i tətbiqə qoşmaq
7. [03-mcp/07-mcp-clients-compared.md](03-mcp/07-mcp-clients-compared.md) — Claude Desktop, Cursor, Cline
8. [03-mcp/08-mcp-oauth-auth.md](03-mcp/08-mcp-oauth-auth.md) — OAuth 2.1 auth flow
9. [03-mcp/09-mcp-security-patterns.md](03-mcp/09-mcp-security-patterns.md) — threat model, tool poisoning
10. [03-mcp/10-mcp-testing-debugging.md](03-mcp/10-mcp-testing-debugging.md) — MCP Inspector, test
11. [03-mcp/11-mcp-for-company-laravel.md](03-mcp/11-mcp-for-company-laravel.md) — şirkət daxili MCP (capstone)

### Yol 5 — Fine-Tuning Path (2-3 həftə — təkrar ehtiyac olmadıqda OPSİYONAL)

1. [06-fine-tuning/01-fine-tuning-overview.md](06-fine-tuning/01-fine-tuning-overview.md) — fine-tuning nədir
2. [06-fine-tuning/02-fine-tuning-vs-rag.md](06-fine-tuning/02-fine-tuning-vs-rag.md) — qərar çərçivəsi
3. [06-fine-tuning/03-open-source-models-ollama.md](06-fine-tuning/03-open-source-models-ollama.md) — Llama, Qwen, Ollama
4. [06-fine-tuning/04-lora-qlora-peft.md](06-fine-tuning/04-lora-qlora-peft.md) — LoRA, QLoRA, PEFT dərin
5. [06-fine-tuning/05-create-custom-model-finetune.md](06-fine-tuning/05-create-custom-model-finetune.md) — praktik fine-tune
6. [06-fine-tuning/06-distillation-small-models.md](06-fine-tuning/06-distillation-small-models.md) — distillation, kiçik sürətli modellər
7. [06-fine-tuning/07-rlhf-dpo-alignment.md](06-fine-tuning/07-rlhf-dpo-alignment.md) — RLHF, DPO, ORPO, KTO alignment training
8. [06-fine-tuning/08-ft-dataset-curation.md](06-fine-tuning/08-ft-dataset-curation.md) — dataset hazırlama, keyfiyyət, synthetic data
9. [06-fine-tuning/09-vllm-model-serving.md](06-fine-tuning/09-vllm-model-serving.md) — vLLM, GGUF, llama.cpp serving

### Yol 6 — Workflows və Integration (1-2 həftə)

1. [07-workflows/01-ai-pipeline-laravel.md](07-workflows/01-ai-pipeline-laravel.md) — pipeline pattern
2. [07-workflows/02-ai-workflow-patterns.md](07-workflows/02-ai-workflow-patterns.md) — prompt chaining, routing, parallelization
3. [07-workflows/03-laravel-queue-ai-patterns.md](07-workflows/03-laravel-queue-ai-patterns.md) — Horizon + AI job patterns
4. [07-workflows/04-ai-idempotency-circuit-breaker.md](07-workflows/04-ai-idempotency-circuit-breaker.md) — idempotency key, CB
5. [07-workflows/05-webhook-async-ai.md](07-workflows/05-webhook-async-ai.md) — webhook-driven inference
6. [07-workflows/06-ai-streaming-ui.md](07-workflows/06-ai-streaming-ui.md) — SSE, WebSocket, Server-Sent UI
7. [07-workflows/07-ai-ab-testing.md](07-workflows/07-ai-ab-testing.md) — eksperimentlər
8. [07-workflows/08-semantic-caching.md](07-workflows/08-semantic-caching.md) — semantic caching, xərc azaltma
9. [07-workflows/09-event-driven-ai.md](07-workflows/09-event-driven-ai.md) — Kafka + pub/sub AI workflow-lar

### Yol 7 — Production Engineering (2-3 həftə — **ən kritik**)

1. [08-production/01-ai-system-design.md](08-production/01-ai-system-design.md) — AI-native sistem dizaynı
2. [08-production/02-observability-logging.md](08-production/02-observability-logging.md) — log struktura, trace
3. [08-production/03-llm-observability.md](08-production/03-llm-observability.md) — LangSmith, Helicone, Langfuse
4. [08-production/04-cost-optimization.md](08-production/04-cost-optimization.md) — FinOps LLM
5. [08-production/05-latency-optimization.md](08-production/05-latency-optimization.md) — p50/p99, streaming, routing
6. [08-production/06-ai-testing-strategies.md](08-production/06-ai-testing-strategies.md) — unit/e2e/eval
7. [08-production/07-model-drift-quality-monitoring.md](08-production/07-model-drift-quality-monitoring.md) — silent degradation
8. [08-production/08-safety-guardrails.md](08-production/08-safety-guardrails.md) — guardrail dizaynı
9. [08-production/09-ai-security.md](08-production/09-ai-security.md) — OWASP LLM Top 10
10. [08-production/10-prompt-injection-defenses.md](08-production/10-prompt-injection-defenses.md) — direct/indirect injection
11. [08-production/11-pii-data-redaction.md](08-production/11-pii-data-redaction.md) — AZ PII, GDPR, redaction
12. [08-production/12-red-teaming-adversarial.md](08-production/12-red-teaming-adversarial.md) — adversarial test
13. [08-production/13-content-moderation.md](08-production/13-content-moderation.md) — toxic/CSAM/NSFW filter
14. [08-production/14-canary-shadow-llm-deploy.md](08-production/14-canary-shadow-llm-deploy.md) — safe rollout
15. [08-production/15-multi-provider-failover.md](08-production/15-multi-provider-failover.md) — Anthropic + OpenAI + Bedrock
16. [08-production/16-ai-governance-compliance.md](08-production/16-ai-governance-compliance.md) — EU AI Act, ISO 42001, SOC 2

### Yol 8 — Hands-on Laravel Layihələri (seçici, hər biri müstəqil)

Hər faylı real, işlək Laravel tətbiq skeleti — migration, controller, queue, test daxil.

- [09-projects/01-laravel-chatbot.md](09-projects/01-laravel-chatbot.md) — söhbət bot, session, streaming
- [09-projects/04-laravel-rag-app.md](09-projects/04-laravel-rag-app.md) — sənəd Q&A (pgvector)
- [09-projects/09-laravel-mcp-server.md](09-projects/09-laravel-mcp-server.md) — Laravel-dən MCP server expose
- [09-projects/06-laravel-ai-code-reviewer.md](09-projects/06-laravel-ai-code-reviewer.md) — PR-a AI review botu
- [09-projects/02-laravel-ai-data-extractor.md](09-projects/02-laravel-ai-data-extractor.md) — invoice/CV-dən struktur çıxarma
- [09-projects/08-laravel-voice-ai.md](09-projects/08-laravel-voice-ai.md) — STT → LLM → TTS pipeline
- [09-projects/10-laravel-customer-support-bot.md](09-projects/10-laravel-customer-support-bot.md) — CS botu (ən böyük)
- [09-projects/07-laravel-internal-hr-bot.md](09-projects/07-laravel-internal-hr-bot.md) — HR Q&A (policy docs)
- [09-projects/03-laravel-email-classifier.md](09-projects/03-laravel-email-classifier.md) — inbox-da təsnifat
- [09-projects/05-laravel-ai-sql-assistant.md](09-projects/05-laravel-ai-sql-assistant.md) — NL → SQL assistant

### Yol 9 — Product Thinking (1-2 gün, manager-lə söhbət üçün)

1. [10-product-thinking/01-ai-mvp-playbook.md](10-product-thinking/01-ai-mvp-playbook.md) — MVP addımları
2. [10-product-thinking/02-build-vs-buy-ai.md](10-product-thinking/02-build-vs-buy-ai.md) — build/buy qərarı
3. [10-product-thinking/03-ai-feature-economics.md](10-product-thinking/03-ai-feature-economics.md) — unit economics, margin
4. [10-product-thinking/04-ai-product-ux-patterns.md](10-product-thinking/04-ai-product-ux-patterns.md) — UX pattern kataloqu
5. [10-product-thinking/05-measuring-ai-success.md](10-product-thinking/05-measuring-ai-success.md) — AI KPI-lar, metric framework, regression detection
6. [10-product-thinking/06-responsible-ai-for-product.md](10-product-thinking/06-responsible-ai-for-product.md) — bias/fairness, şəffaflıq, consent, incident response

---

## Folder baxışı

| Folder | Fayl sayı | Səviyyələr | Məzmun |
|---|---|---|---|
| [01-fundamentals/](01-fundamentals/) | 11 | ⭐×3 ⭐⭐×4 ⭐⭐⭐×4 | LLM necə işləyir, token, hallucination, reasoning modellər, provider müqayisəsi, pricing |
| [02-claude-api/](02-claude-api/) | 14 | ⭐×1 ⭐⭐×4 ⭐⭐⭐×7 ⭐⭐⭐⭐×2 | Messages API, prompt eng, tool use, structured out, streaming, vision/PDF, extended thinking, Files+citations, prompt caching, batch, computer use, rate limits, **context engineering** |
| [03-mcp/](03-mcp/) | 11 | ⭐×1 ⭐⭐×5 ⭐⭐⭐×4 ⭐⭐⭐⭐×1 | Model Context Protocol tam — server (Node+PHP), client, OAuth, security, testing, transports, company Laravel |
| [04-rag-embeddings/](04-rag-embeddings/) | 12 | ⭐×1 ⭐⭐×3 ⭐⭐⭐×4 ⭐⭐⭐⭐×4 | Embedding, vektor DB, RAG arxitektura, chunking, query transform (HyDE), reranking, contextual retrieval, knowledge graph, long-context vs RAG, agentic RAG, eval, **multimodal RAG** |
| [05-agents/](05-agents/) | 13 | ⭐×1 ⭐⭐⭐×5 ⭐⭐⭐⭐×7 | Agent tərif, reasoning pattern (ReAct/Reflexion/ToT), tool dizayn, memory, multi-agent, orchestration, HITL, Claude SDK dərin, Claude Code skills, eval, **agent security** |
| [06-fine-tuning/](06-fine-tuning/) | 9 | ⭐⭐×3 ⭐⭐⭐×3 ⭐⭐⭐⭐×3 | FT overview, FT vs RAG, open-source/Ollama, LoRA/QLoRA, custom FT, distillation, **DPO/ORPO alignment**, **dataset curation**, **vLLM serving** |
| [07-workflows/](07-workflows/) | 9 | ⭐⭐×2 ⭐⭐⭐×5 ⭐⭐⭐⭐×2 | Pipeline, workflow pattern, queue, idempotency+CB, webhook, streaming UI, A/B, **semantic caching**, **event-driven Kafka** |
| [08-production/](08-production/) | 16 | ⭐⭐⭐×7 ⭐⭐⭐⭐×7 ⭐⭐⭐⭐⭐×2 | System design, observability (ümumi + LLM), cost/latency, testing, drift, guardrail, security, prompt injection, PII, red team, content moderation, canary/shadow, multi-provider failover, governance (EU AI Act/ISO 42001) |
| [09-projects/](09-projects/) | 10 | ⭐⭐×3 ⭐⭐⭐×4 ⭐⭐⭐⭐×3 | 10 işlək Laravel AI tətbiqi — chatbot, RAG, MCP, code review, extractor, voice, CS bot, HR bot, email classifier, SQL assistant |
| [10-product-thinking/](10-product-thinking/) | 6 | ⭐⭐×2 ⭐⭐⭐×3 ⭐⭐⭐⭐×1 | Feature economics, UX pattern, MVP playbook, build-vs-buy, **AI KPI framework**, **responsible AI** |

Toplam: **111 fayl, ~115,000+ sətir** (README daxil deyil).

**Səviyyə işarələri:** ⭐ Junior · ⭐⭐ Middle · ⭐⭐⭐ Senior · ⭐⭐⭐⭐ Lead · ⭐⭐⭐⭐⭐ Architect

---

## Nömrələmə qeydi

**Hər folder-də fayllar `01`-dən başlayır və folder daxilində asandan çətinə sıralanıb.** Nömrələmə globallıq yox — folder-local. Beləliklə `01-fundamentals/01-how-ai-works.md` və `02-claude-api/01-claude-api-guide.md` paralel fayllar kimi mövcuddur, hər biri öz folder-inin giriş nöqtəsidir.

Yeni fayl əlavə edərkən:
- Yalnız həmin folder daxilindəki nömrələməyə diqqət et
- Yeni mövzu asanlıq səviyyəsinə görə **harda yerləşməlidir** — ona uyğun nömrə ver və sonrakı faylları bir-bir irəli sürüşdür (və bu README-dəki "Yol"-u yenilə)
- Cross-folder istinad edərkən **folder prefix-i ilə birlikdə** yaz (məs. `02-claude-api/04-tool-use.md`), təkcə nömrə yox

---

## Əlaqəli folder-lər

- [php/topics/](../php/topics/) — Laravel Octane, queue, async (AI tətbiqlərdə istifadə olunur)
- [system-design/](../system-design/) — general distributed system dizaynı (AI sistem dizaynı üçün baza)
- [docker/](../docker/) — AI app-ları Docker-də yerləşdirmək
- [sql/](../sql/) — pgvector, vektor index (RAG folder-i ilə paralel)
- [case-studies/](../case-studies/) — real şirkət arxitekturaları (bəzilərində AI var)

---

## Senior müsahibə üçün minimum oxuma

Vaxt azdır, amma "Claude ilə nə etmişəm?" sualına cavab vermək lazımdır:

**1 gün (sprint):**
- [01-fundamentals/02-models-overview.md](01-fundamentals/02-models-overview.md)
- [02-claude-api/02-prompt-engineering.md](02-claude-api/02-prompt-engineering.md)
- [02-claude-api/04-tool-use.md](02-claude-api/04-tool-use.md)
- [04-rag-embeddings/03-rag-architecture.md](04-rag-embeddings/03-rag-architecture.md)
- [05-agents/01-ai-agents-overview.md](05-agents/01-ai-agents-overview.md)
- [08-production/01-ai-system-design.md](08-production/01-ai-system-design.md)
- [08-production/10-prompt-injection-defenses.md](08-production/10-prompt-injection-defenses.md)
- [10-product-thinking/03-ai-feature-economics.md](10-product-thinking/03-ai-feature-economics.md)

**3 gün (orta hazırlıq):** Yuxarıdakı + Yol 1 + Yol 7 (production).

**1 həftə (yaxşı hazırlıq):** Yol 1 + Yol 2 (RAG) + Yol 7 (production) + bir Yol 8 layihəsi ilə hands-on.

**2-3 həftə (senior səviyyədə):** Yol 1 → 2 → 3 → 7 ardıcıl + 2 layihə hands-on.
