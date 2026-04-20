# Monitor / Observe / Track / Log / Trace — Müşahidə Fellər

## Səviyyə
B1-B2 (DevOps / SRE interview vacib)

---

## Əsas Cədvəl

| Söz | Nə edir? | Kontekst |
|-----|----------|----------|
| **monitor** | davamlı izləmək | live sistem |
| **observe** | baxıb anlamaq | davranış |
| **track** | izləmək + qeyd | zamanla dəyişən |
| **log** | yazılı qeyd | event-lər |
| **trace** | addım-addım izləmək | debug |

> **Qısa qayda:**
> - **monitor** = canlı izlə
> - **observe** = müşahidə et
> - **track** = izlə və qeyd et
> - **log** = yaz (qeyd et)
> - **trace** = bir request-in yolunu izlə

---

## 1. Monitor — Davamlı İzləmək

Live sistemin vəziyyətini real-time izləmək.

### Nümunələr

- We **monitor** CPU and memory.
- **Monitor** the deployment.
- Set up **monitoring** with Grafana.
- 24/7 **monitoring**.
- Production **monitoring** tools.

### Tools

- Prometheus, Grafana
- Datadog, New Relic
- CloudWatch

### Monitor vs Watch

- **monitor** = sistematik (metrikalarla)
- **watch** = ümumi "bax"

---

## 2. Observe — Müşahidə Etmək

Bir şeyin necə davrandığına baxmaq. **Observability** trend termini.

### Nümunələr

- **Observe** the behavior under load.
- **Observability** platform.
- We **observed** a spike at 3 AM.
- **Observable** systems.

### 3 Pillars of Observability

1. **Logs** → tekst qeydlər
2. **Metrics** → rəqəmsal ölçülər
3. **Traces** → request-in yolu

### Monitor vs Observe

- **monitor** = mövcud problemi görmək
- **observe** = səbəbi anlamaq (why?)

---

## 3. Track — İzləmək (Zamanla)

Müəyyən metrikanı / dəyişəni zamanla izləmək.

### Nümunələr

- **Track** user signups.
- **Track** errors in Sentry.
- Performance **tracking**.
- Issue **tracking** (Jira).
- **Track** progress weekly.
- Time **tracking**.

### Bug / Issue tracking

- **Track** bugs in a system (Jira, Linear)
- **Bug tracker** tool

### Track vs Monitor

- **monitor** = live status
- **track** = zamanla dəyişmə

- **Monitor** CPU usage. (indiki an)
- **Track** signups over 6 months. (zamanla)

---

## 4. Log — Qeyd Etmək (Yazılı)

Hadisələri yazılı fayla qeyd etmək.

### Fel və isim

- Fel: We **log** every request. (qeyd edirik)
- İsim: Check the **logs**. (qeydlər)

### Nümunələr

- **Log** the error.
- Check the server **logs**.
- **Logging** framework.
- **Log** aggregation (ELK stack).
- Access **logs**, error **logs**.

### Log levels

- DEBUG → detal
- INFO → normal event
- WARN → xəbərdarlıq
- ERROR → səhv
- FATAL → kritik

### Log vs Monitor

- **log** = yaz (geride qeyd)
- **monitor** = real-time bax

---

## 5. Trace — Addım-addım İzləmək

Bir request-in və ya əməliyyatın bütün yolunu izləmək.

### Nümunələr

- **Trace** the request through microservices.
- Distributed **tracing** with Jaeger.
- **Stack trace** (error debug).
- **Tracing** a performance issue.

### Distributed tracing

- Jaeger, Zipkin
- OpenTelemetry
- Request ID / Trace ID

### Trace vs Log

- **log** = bir nöqtədə qeyd
- **trace** = bir request-in bütün keçdiyi yol

---

## Kontekstual Nümunələr

### DevOps daily

- "**Monitor** the service during deploy."
- "I'll **check** the **logs**."
- "We **track** error rates with Sentry."

### SRE / Observability

- "**Observability** covers **logs**, **metrics**, and **traces**."
- "**Trace** the slow request through the system."
- "**Observe** how the system behaves under load."

### Product

- "**Track** user engagement over time."
- "**Monitor** conversion rates."

---

## Test

Hansı söz daha uyğun?

1. We use Grafana to ______ production metrics. (davamlı)
2. I ______ every error in Sentry. (izlə)
3. Check the server ______ for the error message.
4. Let's ______ the request through all services. (addım-addım)
5. We need better ______ to debug this. (3 pillars)

**Cavablar:** 1. monitor, 2. track, 3. logs, 4. trace, 5. observability

---

## İnterview Nümunələri

- "We use Prometheus to **monitor** metrics and ELK to aggregate **logs**."
- "**Distributed tracing** helps us debug microservices."
- "I **tracked** user behavior to identify bottlenecks."
- "**Observability** tools improved our incident response."

---

## Related Expressions

- **alert** = xəbərdarlıq (monitor → alert → action)
- **metric** = rəqəmsal ölçü
- **dashboard** = paneli
- **incident** = hadisə (alert səbəb olur)

---

## Azərbaycanlı Səhvləri

- ✗ I'll log the progress weekly. (daha çox track)
- ✓ I'll **track** the progress weekly.

- ✗ Please monitor the error. (daha çox log)
- ✓ Please **log** the error.

---

## Xatırlatma

| Söz | Bir sözdə |
|-----|-----------|
| **monitor** | canlı izlə |
| **observe** | anla (niyə?) |
| **track** | izlə + qeyd |
| **log** | yaz |
| **trace** | addım-addım izlə |

→ Related: [bug-defect-issue-incident.md](bug-defect-issue-incident.md)
