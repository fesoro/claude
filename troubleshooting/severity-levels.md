# Severity Levels

## Problem (nə görürsən)
Alert işə düşdü. Onu ilk 60 saniyədə təsnif etməlisən ki, doğru insanlar oyansın və doğru kommunikasiya kanalları işə düşsün. SEV-i səhv təsnif etmək istənilən istiqamətdə bahadır — çox aşağı desən sayt yanır, insanlar yatır; çox yüksək desən kiçik kosmetik bug üçün CTO-nu oyadırsan.

## Sürətli triage (ilk 5 dəqiqə)

### SEV cədvəli

| SEV | Təsir | Cavab | Kimi oyatmaq |
|-----|--------|----------|-------------|
| SEV1 | Tam outage / data itkisi / təhlükəsizlik sındırılması / tənzimləyici | Hamı, <5 dəq ack | CEO/CTO opsional, VP Eng həmişə |
| SEV2 | Əsas funksional sıradan çıxıb, əhəmiyyətli gəlir təsiri | On-call + engineering lead, <15 dəq | Engineering manager |
| SEV3 | Degradation, workaround var, istifadəçilərin bir qismi | On-call primary | Yalnız özün |
| SEV4 | Kiçik / kosmetik / tək istifadəçi | Ticket, növbəti iş günü | Heç kim |

### 60 saniyəlik təsnifat check-listi
1. Pul dayandırır? (checkout, billing, payout) → SEV1 və ya SEV2
2. İstifadəçilər daxil ola bilir? → xeyrsə, SEV1
3. Data itkisi/korrupsiyası riski var? → SEV1
4. Təhlükəsizlik sərhədi sındırılıb? → SEV1
5. Performans pisləşir amma işləyir? → SEV3
6. Tək müştəri və ya kosmetikdir? → SEV4

## Diaqnoz

### Nümunələr

**SEV1 nümunələri:**
- Checkout bütün istifadəçilərə 500 qaytarır
- Database primary işləmir, hələ replica promote olunmayıb
- Data leak: istifadəçi A istifadəçi B-nin orderlərini görür
- Auth servis işləmir — heç kim daxil ola bilmir
- Əsas domendə sertifikat bitib

**SEV2 nümunələri:**
- Axtarış sınıb amma listinglər hələ işləyir
- Background job-lar pause edilib — email-lər göndərilmir
- Bir endpoint-də 15% error rate
- Mobil app sınıb amma web işləyir
- Payment processor səhv qaytarır, amma retry logic çoxunu örtür

**SEV3 nümunələri:**
- p99 latency ikiqat artıb amma p50 normaldır
- Bir region digərlərindən yavaşdır
- Admin dashboard sınıb (yalnız daxili)
- Kritik olmayan cron job iki dəfə fail olub

**SEV4 nümunələri:**
- Email şablonunda yazı səhvi
- Bir müştəri edge case bildirir
- Settings səhifəsində icon sınıb
- Deprecated field-dən log shum

### Düzgün təsnif etməyin yolu
- Təsir olan istifadəçiləri və ya request-ləri say, funksional sayma
- Gəlir təsiri funksional sayından üstündür
- Müştəri görə bilən daxili olanı üstələyir
- "Bu daha da pisləşə bilər" — qaldırmaq üçün etibarlı səbəbdir
- İki variant arasında qərarsızsansa, yüksəyini seç

## Fix (qanaxmanı dayandır)

SEV1/SEV2 dərhal mitigation tələb edir. SEV3 stabilləşəndən sonra adi işlə birlikdə yığıla bilər. SEV4 backlog-a gedir.

### Incident ortasında SEV qaldırılması
Bu normaldır. Ssenari: SEV3 "bəzi 5xx" kimi başlayıb → əslində checkout flow olduğu aşkarlanır → SEV1-ə qaldırılır.

Incident kanalında qaldırılmanı aydın elan et:

```
SEV UPGRADE: SEV3 → SEV1
Reason: Checkout is affected. Revenue impact confirmed.
New responders paging now.
```

### SEV-in aşağı salınması
Nadirdir amma ilkin təsir qiymətləndirilməsi yanlış olduqda haqlıdır. Aşkar elan et, səssizcə dəyişmə.

## Əsas səbəbin analizi

Post-mortem SEV səviyyəsi qərarını əhatə edir — hər mərhələdə düzgün idi? Kifayət qədər tez qaldırdıq? Çox tez aşağı saldıq və problem qayıdanda kanalı bağladıq?

Metriklləri zaman ərzində izlə:
- Mean Time To Detect (MTTD)
- Mean Time To Acknowledge (MTTA)
- Mean Time To Resolve (MTTR)
- Rüb üzrə severity distribution

## Qarşısının alınması

- SEV təriflərini engineering handbook-da sənədləşdir
- Runbook-larda onlara istinad et: "Əgər X görürsənsə, bu SEV2-dir"
- Rüblük review: ardıcıl olduq?
- Yeni on-call mühəndislərinin təlimi: 5 keçmiş incident-i göstər və onları təsnif etmələrini istə

## PHP/Laravel üçün qeydlər

SEV üzrə Laravel ssenariləri:

| Ssenari | Tipik SEV |
|----------|-------------|
| `php artisan migrate` prod-da kritik cədvəli 10 dəq lock etdi | SEV1 |
| Queue worker-ların hamısı crash oldu, `failed_jobs` cədvəli dolur | SEV2 |
| Horizon dashboard 50k pending göstərir amma hələ prosesləyir | SEV3 |
| Deploy sonrası OPcache reset olunmayıb, köhnə kod verilir | SEV2 (davranış təhlükəlidirsə SEV1 ola bilər) |
| Laravel Telescope prod-da diski yeyir | SEV3 |
| Bir Eloquent model scope bug /profile-da 500-a səbəb olur | SEV2 |
| Blade şablonunda bir hərf səhvdir | SEV4 |

## Yadda saxlanacaq komandalar

```bash
# Count affected requests in last 10 min (Datadog)
# error.rate{service:api} over last 10m

# Count unique users hit (approximate from logs)
grep "ERROR" storage/logs/laravel.log | grep "user_id" | awk -F'user_id=' '{print $2}' | awk '{print $1}' | sort -u | wc -l

# Check if checkout endpoint is in the fire
grep -E "POST /checkout|POST /payment" storage/logs/laravel.log | tail -50

# Kubernetes pods affected
kubectl get pods -n production -o wide | grep -v Running
```

## Interview sualı

"Incident severity-ni necə təsnif edirsən?"

Güclü cavab:
- "4 səviyyəli SEV şkalasından istifadə edirik. SEV1 gəlir-dayandıran və ya data-risk, SEV2 əsas funksional təsiri, SEV3 degradation, SEV4 kiçik."
- "İlk soruşduğum: pul dayandırırmı? İkinci: istifadəçilər daxil ola bilirmi? Üçüncü: data risk altındadırmı?"
- "Qərarsız olanda yüksək SEV-ə üstünlük verirəm — aşağı salmaq pulsuzdur, gec qaldırmaq etibara baha başa gəlir."
- "Son işimdə bir SEV3 vardı və checkout-u sındırdığı ortaya çıxdı. Öyrəndiyim 5 dəqiqə içində SEV1-ə qaldırdım. Executive kommunikasiya düzgün başladı çünki aydın upgrade protokollarımız vardı."

Müsahibə verən axtarır: strukturlu düşüncə, müştəri-first framing, qaldırmağa hazırlıq və severity-nin kommunikasiya əhatəsini müəyyən etdiyini başa düşmək.
