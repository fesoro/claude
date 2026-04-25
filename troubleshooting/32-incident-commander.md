# Incident Commander (Lead)

## Problem (nə görürsən)
Incident açılır. Bir neçə mühəndis qoşulur. Kimsə otağı idarə etməlidir, yoxsa xaos olur: paralel debug, təkrarlanan iş, rollback-ı kim qərar verir, status səhifəsini kim yeniləyir — bilinmir. O adam Incident Commander-dir.

## Sürətli triage (ilk 5 dəqiqə)

### IC debug EDƏN adam DEYİL

Bu ən vacib qaydadır. Əgər IC eyni zamanda log grep etməyə və kubectl komandaları işlətməyə çalışırsa, incident-in komandiri yoxdur. IC-nin işi meta-dır: başqalarını koordinasiya etmək.

Əgər orada tək mühəndis sənsənsə, sən default olaraq IC-sən — və ilk hərəkətin dəstək çağırmaqdır ki, IC-ni ötürüb debug-a fokuslaya biləsən.

### IC məsuliyyətləri check-listi

1. Incident kanalını aç / sahibi ol
2. SEV səviyyəsini təsdiqlə
3. Rolları təyin et: scribe, comms lead, SME-lər
4. Hər 15 dəqiqə strukturlu yeniləmə post et (və ya scribe-nin etdiyinə əmin ol)
5. Qərar ver: rollback yoxsa forward-fix
6. Araşdırmalara timebox qoy ("10 dəqiqəyə leadimiz yoxdursa, rollback edirik")
7. Mühəndisləri kəsilmələrdən qoru (fokus saxlaya bilsinlər deyə exec DM-lərə cavab ver)
8. Əlavə kömək çağır
9. Mitigation və monitoring mərhələlərini elan et
10. Post-mortem-i idarə et (və ya planla)

## Diaqnoz

### Rol təyinat şablonu

IC kimi ilk hərəkət, kanalda post edilir:

```
ROLES:
IC: @orkhan (me)
Scribe: @elena
Comms lead: @david
Debug lead: @alice (payments service)
Debug support: @bob (infra)
SME on-call (DB): @carlos (not paged yet, available if needed)

If you're not on this list and not actively helping, please don't post — watch only.
```

### Qərar çərçivəsi: rollback vs forward-fix

IC bu qərarın sahibidir. Əsas qaydalar:

**Rollback nə vaxt:**
- Son 2 saatdakı deploy incident başlanğıcı ilə korrelyasiya edir
- Forward-fix ETA məlum deyil və ya > 15 dəq
- DB migration və ya schema dəyişikliyi göndərilməyib
- Data itkisiz rollback edə bilərsən

**Forward-fix nə vaxt:**
- Rollback pis bug üçün fix-i regress etdirəcək
- Deploy-dakı schema/migration dəyişikliyi rollback-ı təhlükəsiz etmir
- Əsas səbəb aşkardır və patch 5 dəqiqədir
- Rollback hədəfi də sınıqdır

Şübhə olanda: rollback. Forward-u sonra roll et.

### Timeboxing

Timebox olmadan debug əbədi genişlənir. IC açıq box-lar təyin edir:

> "Alice, səbəbi tapmaq üçün 10 dəqiqə. 14:55-ə qədər aydın cavabımız yoxdursa, hər halda rollback edirik."

Timebox-lar convergence-i məcbur edir.

## Fix (qanaxmanı dayandır)

### IC-nin ilk 10 dəqiqəsi

1. Incident-in elan edildiyini təsdiqlə (kanal, SEV, ilk yeniləmə post edilib)
2. Rolları açıq təyin et
3. Soruş: "hazırda business təsiri nədir?"
4. Soruş: "bu sistemdə son dəyişiklik nə idi?"
5. İlk timebox-u qoy
6. Leadership-ə indi xəbər verməli, yoxsa mitigation-da xəbər verməli qərar ver

### Aktiv debug zamanı IC

ETMƏ:
- Özün log grep etmə
- Kodu oxumaq üçün IDE aç
- kubectl komandaları işlətmə
- Başqalarını yayındıran engineering detalları post etmə

ET:
- Debug lead-ə aydınlaşdırıcı suallar ver
- Statusu comms lead-ə yeniləmələr üçün ötür
- Lazım olanda yeni SME-ləri cəlb et
- Exec DM-ləri / support escalation-ları idarə et
- Kanal intizamını saxla (fokuslu qalsın)

### IC monitoring mərhələsinə keçid

Fix tətbiq olanda:

```
STATUS: Mitigating → Monitoring
Fix applied at 14:58: rolled back payments-service to v2.4.1
Error rate dropping: 12% at 14:55 → 1% at 15:00 → 0.1% at 15:02
Dashboard looks clean.
Holding #incident channel open until 15:30. If no regression, we close and start post-mortem.
```

## Əsas səbəbin analizi

IC post-mortem görüşünü planlaşdırır və idarə edir (adətən SEV1/SEV2 üçün 5 iş günü içində). IC bütün post-mortem-i YAZMIR — sahib komanda yazır — amma IC kolaylaşdırır.

IC həmçinin öz performansını nəzərdən keçirir:
- Yetərincə delegate etdim?
- Debug mühəndislərini küydən qorudum?
- Uyğun timebox qoydum?
- Rollback qərarını kifayət qədər tez verdim?

## Qarşısının alınması

- IC rolu rotasiya edir — adətən həftəlik, on-call rotasiyasından ayrı
- Yeni IC-lər öz rəhbərliyindən əvvəl 2-3 incident üçün təcrübəli IC-ləri izləyir
- IC təlimi: rüblük saxta incident işlət
- IC check-listi laminate kartda və ya Slack-də pin edilmiş

## PHP/Laravel üçün qeydlər

Laravel codebase-də incident işlədərkən, IC ən azı kimin hansı domenlərə sahib olduğunu bilməlidir:

- Backend API: backend team lead
- Horizon / queues: infra və ya backend
- Database (MySQL/Postgres): DBA və ya senior backend
- Redis / cache: infra
- Frontend (Blade, Livewire, Inertia, Vue): frontend team
- Deploy pipeline: DevOps

Laravel incident zamanı, ümumi IC qərarları:
- "İndi `horizon:terminate` edirik, yoxsa fix-i gözləyirik?"
- "Rollback-ın bir hissəsi kimi `config:clear` və `cache:clear` işlədirik?"
- "Yükə töhfə verdiyi üçün Telescope-u disable edirik?"
- "Read replica-nı promote edirik?"

IC bunları icra etmir; IC qərar verir və delegate edir.

## Yadda saxlanacaq komandalar

IC nadir hallarda komanda işlədir. IC-yə lazım olan şey tez-tez:
- Rollback one-liner
- Scale-up one-liner
- Feature-flag-kill one-liner

```bash
# Team runbook for rollback (IC asks debug lead to run)
kubectl rollout undo deployment/api-service -n production

# Scale PHP-FPM
kubectl scale deployment/php-fpm --replicas=30

# Kill a feature flag (LaunchDarkly example)
ld flag off checkout-v2 --project myapp --env production

# Maintenance mode Laravel
php artisan down --secret="incident-2026-04-17"
```

## Interview sualı

"Heç Incident Commander olmusan?"

Güclü cavab strukturu:
- "Bəli, son işimdə IC-ni həftəlik rotasiya edirdik, on-call-dan ayrı."
- "IC kimi qaydam: debug etmirəm. Koordinasiya edirəm."
- "İlk mesajımda scribe, comms və debug lead təyin edirəm. Timebox-lar qoyuram. Rollback-vs-forward qərarına sahib oluram."
- Konkret nümunə ver: "Bir SEV2-də debug lead-im memory leak hipotezini araşdırmağa 15 dəqiqə vermişdi. Əlavə 5 dəqiqə timebox qoydum və sonra rollback çağırdım. Error rate dərhal düşdü. Göründü ki, leak hipotezi düzgün idi, amma forward-fix 6 saat çəkdi — rollback bizi xilas etdi."
- "Incident-dən sonra post-mortem-i planlaşdırıram, amma sahib komanda yazır. Mənim işim kolaylaşdırmaqdır."

Müsahibə siqnalı: liderliyin işi etmək deyil, işi mümkün etmək olduğunu anlayırsan. Rollback və forward-fix arasındakı trade-off-ları anlayırsan. Rol ayrılığının incident-ləri scale etdiyini anlayırsan.
