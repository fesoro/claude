# Incident Communication (Junior)

## Problem (nə görürsən)
Sayt yanır. Mühəndislər başı aşağı debug edir. Bu arada: support müştərilərə nə isə demək istəyir, CEO tweet görüb, status səhifəsi 40 dəqiqədir yenilənməyib. Kommunikasiya uğursuz olursa, incident qavrayışda reallıqdan daha uzun sürür və bug düzəldiləndən sonra belə etibar aşınır.

## Sürətli triage (ilk 5 dəqiqə)

### 15 dəqiqə qaydası
Aktiv incident zamanı hər 15 dəqiqədə yeniləmə gönder, hətta yeni məlumat yoxdursa da. "Yeni info yox, hələ araşdırırıq" — etibarlı yeniləmədir. Səssizlik stakeholder-ləri ən pisini düşünməyə vadar edir.

### Üç auditoriya, üç kanal
1. **Engineering / incident kanalı** — xam, texniki, tez-tez, hər tapıntı
2. **Stakeholders / leadership** — strukturlu, hər 30 dəqiqə, business-impact çərçivəsi
3. **Müştərilər / ictimai status səhifəsi** — yüksək səviyyəli, ehtiyatlı dil, hər 15-30 dəqiqə

Bunları qarışdırma. Xam engineering kanalını leadership yeniləməsinə yapışdırma. Status səhifəsi ifadələrini engineering kanalına köçürmə — çox qeyri-müəyyəndir.

## Diaqnoz

### Standart yeniləmə şablonu

Incident Slack kanalı üçün:

```
UPDATE 14:47 UTC
Status: Investigating | Identified | Mitigating | Monitoring | Resolved
Impact: ~10% of checkout requests returning 500 since 14:32
Current hypothesis: Recent deploy of payments-service introduced null check bug
Actions in progress:
  - @alice reviewing PR diff
  - @bob running rollback now
Next update: 15:02 UTC
```

Status sözlərinin xüsusi mənaları var:
- **Investigating** — səbəbi hələ bilmirik
- **Identified** — səbəbi bilirik, fix dizayn edirik
- **Mitigating** — indi fix tətbiq edirik
- **Monitoring** — fix tətbiq olundu, dashboard-lara baxırıq
- **Resolved** — problem bitdi, daha yeniləmə lazım deyil

### Leadership yeniləmə şablonu

#eng-leadership-ə və ya birbaşa VP Eng / CTO-ya hər 30 dəqiqə:

```
SEV2 update — 14:47 UTC (started 14:32, 15 min in)

Business impact:
  - ~10% checkout failures
  - Est. revenue impact: $X/min
  - ~50 customer support tickets so far

Current state: Mitigating
ETA to mitigation: 10-15 min
Confidence: High — rollback in progress

Will update at 15:17 UTC or on status change.
```

### İctimai status səhifəsi dili

Qaydalar:
- Daxili kod adları yoxdur ("payments-service-v2" yoxdur)
- Daxili metriklərin faizləri yoxdur
- İstifadəçi təcrübəsinə fokuslan: "bəzi müştərilər checkout zamanı səhv görə bilər"
- Əmin olmadıqca konkret ETA vədi yoxdur
- 48 saat içində həmişə post-mortem xülasəsi ilə dövrü bağla

Misal inkişaf:

```
14:35 — Investigating — We are investigating reports of errors during checkout. Some customers may see an error. We are actively working on resolution.

14:50 — Identified — We have identified the cause of the checkout errors and are rolling out a fix.

15:05 — Monitoring — A fix has been deployed. We are monitoring to confirm full recovery.

15:30 — Resolved — The checkout issue has been resolved. We will share a full post-mortem within 48 hours.
```

## Fix (qanaxmanı dayandır)

### Nə DEMƏMƏLİ

Incident zamanı qadağan olunmuş ifadələr:

- "It's just a small bug" — heç vaxt kiçiltmə
- "Should be fixed in 5 minutes" — 95% əmin olmadıqca ETA-dan qaç
- "Nothing to worry about" — qoy istifadəçilər qərar versin
- "This shouldn't have happened" — əlbəttə; günahı post-mortem üçün saxla
- "Our engineers are working hard" — qeyri-müəyyən dolğunluq; konkret nə deyirsən
- İctimai yeniləmələrdə texniki jarqon
- Hələ yoxlayarkən vendora ictimai günah atmaq

### Scribe rolu

Bir adamı scribe təyin et. Onlar:
- Kanalda UTC timestamp-ları ilə yürüyən timeline yazır
- Debug etmir — yalnız müşahidə edir və qeyd edir
- Qərarları tutur, təkcə hadisələri yox ("IC 14:42-də rollback qərarı verdi çünki...")
- Post-mortem üçün ilk qaralama timeline hazırlayır

### Səssiz dövrlər

Intensiv debug zamanı belə 15 dəqiqədən artıq səssiz qalma. Scribe "hələ işləyirik" yeniləməsini göndərir ki, mühəndislər flow-larını pozmasın.

## Əsas səbəbin analizi

Kommunikasiya hər post-mortem-də nəzərdən keçirilir:
- 15 dəqiqəlik yeniləmə cadence-ni yerinə yetirdik?
- Status səhifəsi dili əsl severity-yə uyğun idi?
- Stakeholder-lər lazım olan info-nu aldı?
- Həddindən artıq və ya az kommunikasiya etdik?

Ümumi uğursuzluq: mühəndis 20 dəqiqəyə düzəldir amma status səhifəsi 2 saat sonra hələ də "investigating" yazır çünki heç kim yeniləməyib.

## Qarşısının alınması

- SEV1/SEV2 üçün IC rotasiyasına comms lead rolu əlavə edildi
- Şablonlar məlum yerdə saxlanılır (Notion səhifəsi, Slack workflow)
- Slack workflow hər 15 dəqiqə avtomatik xatırlatmalar post edir
- Status səhifəsi yeniləmələri Slack komandasından post edilə bilər (başqa alət tələb etmədən)
- Rüblük məşq: saxta incident işlət, comms-u ölç

## PHP/Laravel üçün qeydlər

Laravel-spesifik problemlər haqqında kommunikasiya edərkən:

Pis (ictimai üçün çox texniki):
> "Redis connection pool exhausted, Horizon failing to acquire locks, jobs being retried with exponential backoff."

Yaxşı (ictimai):
> "Background processing for email notifications is delayed. Your actions in the app will still work; emails may arrive later than usual."

Pis (engineering üçün çox qeyri-müəyyən):
> "Queue is slow."

Yaxşı (engineering kanalı):
> "Redis CLIENT LIST shows 4982 connections vs max 5000. Horizon supervisor restarting workers every 30s. Cause: new WebSocket feature holding connections open."

## Yadda saxlanacaq komandalar

```bash
# Statuspage.io update via CLI
curl -X PATCH "https://api.statuspage.io/v1/pages/$PAGE_ID/incidents/$INCIDENT_ID" \
  -H "Authorization: OAuth $TOKEN" \
  -d "incident[status]=monitoring&incident[body]=A fix has been deployed."

# Slack reminder every 15 min
/remind #incident-20260417-checkout "Time for next update" in 15 minutes

# Post to leadership via webhook
curl -X POST $LEADERSHIP_WEBHOOK -d '{"text":"SEV2 update ..."}'
```

## Interview sualı

"Incident zamanı necə kommunikasiya edirsən?"

Güclü cavab:
- "Üç auditoriya, üç kanal: engineering xam detalları alır, leadership hər 30 dəqiqə business-impact çərçivəsini alır, müştərilər hər 15 dəqiqə ehtiyatlı status-səhifə dili alır."
- "Yeniləmələr şablonu izləyir: status sözü, təsir, hipotez, hərəkətlər, növbəti yeniləmə vaxtı."
- "15 dəqiqə qaydasını tətbiq edirəm. Səssizlik panik yaradır."
- "Scribe təyin edirəm ki, debug edən mühəndislər fokus saxlaya bilsin. Scribe-nin timeline-ı post-mortem ilk qaralamasına çevrilir."
- "Heç vaxt kiçiltmirəm. 'Small bug' incident kommunikasiyasında yeri yoxdur."

Bonus: aydın comms-un exec escalation-ın qarşısını aldığı, ya da pis comms-un hiss olunan outage-i texniki olandan uzatdığı konkret nümunə göstər.
