# Declaring an Incident (Middle)

## Problem (nə görürsən)
Nə isə sınıb. Qərar verməlisən: bunu sakitcə düzəltmi, yoxsa kanal, IC, status səhifəsi və comms ilə formal incident elan etməli? Bu qərar vacibdir çünki elan etmək əlavə iş deməkdir, amma elan etməmək o deməkdir ki, qeyd yoxdur, koordinasiya yoxdur, post-mortem yoxdur.

## Sürətli triage (ilk 5 dəqiqə)

### Formal olaraq nə vaxt elan etmək lazımdır

ƏGƏR aşağıdakılardan BİRİ doğrudursa elan et:
- Müştəri görə bilən təsir təsdiqlənib (hətta bir müştəri, əgər gəlir vacibdirsə)
- Diaqnoz üçün bir neçə mühəndis lazımdır
- Gözlənilən müddət > 15 dəqiqə
- Status səhifəsi yeniləməsi lazımdır
- Executive xəbərdarlığı lazımdır
- Tənzimləyici/hüquqi/təhlükəsizlik tərəfi
- Data itkisi, korrupsiya və ya leak mümkündür

### Sadəcə düzəltməyin vaxtı

Bunlarda elan etmə:
- Yalnız daxili alət, müştəri təsiri yoxdur
- Tək sadə hotfix, < 5 dəqiqə, bir mühəndis tərəfindən edilir
- Sənədləşdirilmiş workaround-u olan məlum flaky sistem
- Alert false positive idi

### Qara zona default: ELAN ET

Tərəddüd edirsənsə, elan etməyə meyl et. Real olmayanı geri çəkmək ucuzdur. Real olanı elan etməmək bahadır.

## Diaqnoz

### Alətlər (birini seç, sadiq ol)

**PagerDuty Incidents**
- Yaxşı: alerting ilə inteqrasiya olunub
- Pis: məhdud kollaborasiya xüsusiyyətləri

**FireHydrant**
- Yaxşı: xüsusi hazırlanıb, yaxşı post-mortem flow
- Pis: ayrıca ödəniş məhsulu

**Incident.io**
- Yaxşı: Slack-native, əla UX, status səhifəsini avtomatlaşdırır
- Pis: yenidir, pulsuz tier-də bəzi scale limitləri

**Custom Slack bot**
- Yaxşı: pulsuz, tam customizable
- Pis: sən saxlayırsan, xüsusiyyətlər təbii artır

**Jira Service Management / Opsgenie**
- Yaxşı: əgər artıq Atlassian-dasansa
- Pis: gecə 3-də triage üçün ağır UI

### Incident kanal şablonu

Kanal adı: `#incident-YYYYMMDD-short-slug`

Nümunə: `#incident-20260417-checkout-500s`

Pin edilən ilk mesaj şablonu:

```
INCIDENT — SEV2 — DECLARED 14:32 UTC

Summary: Checkout endpoint returning 500 for ~10% of requests
IC: @orkhan
Scribe: @elena
Comms lead: @david
SMEs paged: @payments-oncall

Links:
- Dashboard: [grafana URL]
- Initial alert: [pagerduty URL]
- Status page: [statuspage URL]
- Post-mortem doc (placeholder): [notion URL]

Next update: 14:47 UTC (every 15 min)
```

## Fix (qanaxmanı dayandır)

### Status səhifəsi yeniləmə vaxtı

| SEV | Status səhifəsini nə vaxt yeniləmək |
|-----|---------------------------|
| SEV1 | Elan anında dərhal, sonra hər 15 dəqiqə |
| SEV2 | Müştəri-görünəndirsə 10 dəqiqə içində, hər 20 dəqiqə |
| SEV3 | Opsional; müştəri ticket-ləri gəlirsə yenilə |
| SEV4 | Lazım deyil |

İlk ictimai yeniləmə şablonu (StatusPage.io, Atlassian Statuspage, Instatus):

```
Investigating — We are investigating reports of errors when completing checkout. Some customers may see a 500 error. We are actively working on resolution.

Posted: 14:35 UTC
Next update: 14:55 UTC
```

Dil qaydaları:
- Bilinən üçün keçmiş zaman, edilən üçün indiki zaman
- Nə sındığı haqqında konkret, niyə sındığı haqqında qeyri-müəyyən
- Heç vaxt "just" və ya "small" deyil
- Həmişə növbəti yeniləmə vaxtı ver

### Müştəri kommunikasiyası vaxtı

- Daxili Slack (engineering, support): dərhal
- Status səhifəsi: yuxarıdakı SEV cədvəlinə görə
- Təsir olan müştərilərə email: mitigate olandan sonra, izahatla
- Tweet / social: yalnız SEV1, PR/marketing ilə koordinasiya
- Executive xülasə email-i: incident-in sonunda, 2 saat içində

### Incident-i bağlamaq

Bağlamadan əvvəl:
1. Dashboard-ların ən azı 15 dəqiqə təmiz qaldığını təsdiqlə
2. Support komandası yeni ticket olmadığını təsdiqləsin
3. Status səhifəsi: "Resolved"
4. Timeline xülasəsi ilə son kanal mesajı
5. Post-mortem sənədi, təyin edilmiş owner + son tarix
6. Kanal 24 saat açıq qalsın gec gələn info üçün, sonra arxivlənsin

## Əsas səbəbin analizi

Elan özü post-mortem üçün data nöqtəsidir. Nəzərdən keçir:
- İlk siqnaldan sonra nə qədər tez elan etdik?
- Düzgün SEV-də elan etdik?
- Düzgün insanlar page edildimi?
- Status səhifəsi yeniləmələri vaxtında çatdırıldı?

## Qarşısının alınması

- On-call üçün yazılı "elan et və ya etmə" qərar ağacı
- Runbook şablonları elan addımını açıq daxil edir
- Elan edilməli olan, az qala elan olunmayan hallar üçün rüblük review

## PHP/Laravel üçün qeydlər

Həmişə elan edilməli olan Laravel incident-ləri:
- `php artisan migrate` prod-a toxundu və nə isə yavaş/locked
- Deploy zamanı OPcache və ya config cache təmizlənməyib, pis kod verilir
- Queue worker-ları dayandı, kritik job-lar yığılır
- Redis primary işləmir — session itkisi
- Database primary işləmir — irəli hərəkət dayanır

Adətən elan olunmasına ehtiyac olmayan Laravel incident-ləri:
- Tək job retry ilə fail oldu
- Bir istifadəçi edge case bildirir
- Dev environment problemləri
- Horizon özü təmiz restart oldu

## Yadda saxlanacaq komandalar

```bash
# Create Slack channel via API
curl -X POST https://slack.com/api/conversations.create \
  -H "Authorization: Bearer $SLACK_TOKEN" \
  -d "name=incident-20260417-checkout-500s"

# Incident.io create incident via CLI
incident create --severity=sev2 --summary="Checkout 500s"

# Statuspage.io create incident
curl -X POST "https://api.statuspage.io/v1/pages/$PAGE_ID/incidents" \
  -H "Authorization: OAuth $STATUSPAGE_TOKEN" \
  -d "incident[name]=Checkout errors&incident[status]=investigating&incident[impact]=major"
```

## Interview sualı

"Production incident-i necə elan edir və strukturlaşdırırsan, mənə göstər."

Güclü cavab strukturu:
- "Aydın qərar ağacı istifadə edirəm: müştəri-görünən, multi-mühəndis və ya exec-xəbərdarlıq lazımdırsa → elan et."
- "Şübhə olanda elan etməyə default qoyuram."
- "Standart adlandırma sxemi ilə ayrıca Slack kanalı açıram, IC və scribe rollarını təyin edirəm və SEV, xülasə və növbəti yeniləmə vaxtı ilə strukturlu ilk mesaj göndərirəm."
- "Status səhifəsi yeniləmələri SEV cadence-ni izləyir. SEV1 üçün 5 dəqiqə içində. SEV2 üçün müştəri-görünən olsa 10 dəqiqə."
- "Həll olandan sonra kanalı 24 saat açıq saxlayıram gec info üçün, sonra post-mortem linki ilə arxivləyirəm."

Müsahibə verənə ötürüləcək siqnal: proses intizamı, müştəri-first düşüncə və elanın həddindən artıq olduğunu bilmək yetkinliyi.
