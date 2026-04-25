# War Stories: Famous Industry Incidents (Architect)

## Məqsəd
Texnologiyada ən böyük, ən publik outage-lərdən öyrənmək. Aşağıdakı hər incident yaxşı sənədləşdirilib; hər biri davamlı bir şey öyrədir. PHP/Laravel dev-ləri birbaşa tətbiq olunan dərslər çıxara bilərlər.

---

## 1. AWS S3 us-east-1 outage (Feb 28, 2017)

### Nə baş verdi
Bir AWS mühəndisi S3 billing subsystem-dən az sayda server silmək üçün debugging komanda işlətdi. Komandadakı bir typo çox daha böyük server dəstini — kritik indexing subsystem-ləri də daxil olmaqla — sildi. us-east-1-də S3 ~4 saat aşağı düşdü. Kaskad: S3-dən asılı olan hər şey (əslində internet) yavaşladı və ya uğursuz oldu. AWS Status Dashboard özü də S3-də host olunurdu və yenilənə bilmirdi.

### Əsas səbəb
- İnsan xətası (typo) — proksimat
- CLI-də ifrat server silməsinin qarşısını alacaq təhlükəsizlik mexanizmləri yox — struktur
- Graceful degradation edə biləcək alətlər etmədi (index subsystem restart-ı yavaş idi)
- Status page-in degrade olan xidmətə asılılığı

### PHP dev üçün dərslər
- **Təhlükəli komandalar təsdiq prompt-ları tələb edir**: destruktiv əməliyyatlar üçün `--dry-run` default-ları, `--confirm` flag-ları əlavə et
- **Dogfooding tələsi**: status page-i öz infrastrukturuna host etmə
- **Blast radius**: tək komanda regionun yarısını düşürə bilməməlidir
- **"Yalnız server silmək" üçün belə gradual rollout** — infra komandalarına deploy kimi yanaş

Praktiki Laravel ekvivalenti: prod-da geniş təsdiq olmadan `php artisan db:wipe` işlətmə. Logging xidmətini log-lanan eyni DB-də host etmə.

---

## 2. GitHub database failure (Oct 21, 2018)

### Nə baş verdi
ABŞ Şərq sahilində bir data mərkəzində adi şəbəkə baxımı GitHub-ın ABŞ East və ABŞ West arasında 43 saniyəlik partition-a səbəb oldu. Split zamanı bir replica promote olundu. Şəbəkə bərpa olunduqda, hər iki tərəf write-lər qəbul etmişdi. Data uzlaşdırıldığı üçün 24 saatlıq degraded xidmət.

### Əsas səbəb
- Şəbəkə partition + avtomatlaşdırılmış failover = split-brain
- Orchestrator çox aqressiv failover etdi (43 saniyə partition üçün qısadır; gözləmək daha yaxşıdır)
- Divergent write-lər üçün manual uzlaşdırma tələb olundu

### Dərslər
- **Failover vaxtı önəmlidir**: çox sürətli = split-brain riski, çox yavaş = istifadəçi təsiri
- **Partition ssenarilərini test et**: şəbəkə split-ləri üçün chaos engineering
- **DB failover üçün manual təsdiq** çox qısa partition-lar üçün tam avtomatlaşdırılmış-dan daha təhlükəsiz ola bilər
- **Data uzlaşdırma planları**: alətləri hazır saxla, incident zamanı icad etmə

PHP/Laravel ekvivalenti: əgər read/write primary ilə multi-region işlədirsənsə, avtomatlaşdırılmış promotion-la ehtiyatlı ol. Distributed koordinasiya əvəzinə conflict resolution ilə eventual consistency-yə üstünlük ver.

---

## 3. Cloudflare regex DDoS on itself (July 2, 2019)

### Nə baş verdi
Cloudflare yeni regex-li WAF qaydası push etdi. Regex-in müəyyən input-larda fəlakətli backtracking-i var idi, hər edge server-də 100% CPU istehlak edirdi. İnternetin böyük bir hissəsini qarşılayan Cloudflare öz infrastrukturu 27 dəqiqə ərzində tamamilə yavaşladı.

### Əsas səbəb
- Regex `.*(?:.*=.*)` (sadələşdirilmiş) eksponensial backtracking-ə sahib idi
- Qayda qlobal olaraq bir anda deploy olundu, canary yox
- Regex qiymətləndirmədə runtime CPU limiti yox

### Dərslər
- **Regex təhlükəlidir**: həmişə adversial input-larla test et
- **Hər şeyi canary et, hətta regex qaydalarını da**: qlobal push = qlobal outage
- **Resurs limitləri**: timeout-lu regex engine-lər (PCRE `pcre.backtrack_limit`)
- **Fuzzing** bu input pattern-ini tutardı

PHP/Laravel ekvivalenti: istifadəçi input-unda regex validasiyası çox yaygındır. Həmişə uzun / adversial string-lərlə test et. Məhdudlaşdırılmış input ilə `preg_match` istifadə et. `php.ini`-də `pcre.backtrack_limit` qur.

```php
// Bad: user controls both pattern and input
if (preg_match($userPattern, $userInput)) { ... }

// Better: bound the input, test the pattern against a fuzz suite in CI
if (strlen($input) > 1000) abort(413);
preg_match('/^[a-z0-9]+$/i', $input);  // simple, fast
```

---

## 4. Facebook BGP outage (Oct 4, 2021)

### Nə baş verdi
Facebook-un backbone-una adi BGP config dəyişikliyi Facebook-un DNS server-lərinə bütün route-ları geri çəkdi. Dünya DNS-i `facebook.com`-u resolve edə bilmirdi. BGP-ni fix etmək üçün istifadə olunan daxili alətlər də işləyə bilmirdi, çünki onlar (indi route-a gələ bilməyən) korporativ şəbəkəyə güvənirdilər. Təhlükəsizlik sistemləri fiziki data mərkəzlərinin qapılarını kilidlədi. 6 saatlıq qlobal outage.

### Əsas səbəb
- Nəzərdə tutulandan daha geniş təsirli config komandası
- BGP geri çəkilməsi out-of-band management-i də təsir etdi
- Recovery alətləri eyni qırıq şəbəkədə idi
- Badge reader-lər, qapı kilidləri, SSH, hər şey korporativ şəbəkədən keçirdi

### Dərslər
- **Out-of-band girişi**: recovery alətlərin qırılan şeydən asılı olmamalıdır
- **Config dəyişikliklərinin blast radius-u**: şəbəkə config-ləri üçün review alətləri
- **Fiziki giriş də**: cyber və fizikini belə sıx bir-birinə bağlama
- **"Tək şəbəkə" single point of failure-dır**

PHP/Laravel ekvivalenti: əgər deploy/rollback mexanizmin idarə etdiyi eyni cluster-də yaşayırsa, qırıq cluster-ə fix deploy edə bilməzsən. Deploy pipeline-ını müstəqil əlçatan saxla.

---

## 5. GitLab data loss (Jan 31, 2017)

### Nə baş verdi
Bir mühəndis, yorğun, axşam 11-də, replica database-i olduğunu zənn etdiyi şeyə `rm -rf` işlətdi — əslində primary idi. 300 GB production data getdi. GitLab sonra kəşf etdi ki, onların 5 backup/replication mexanizmi aylardır səssizcə uğursuz olub. 6 saat əvvəlki disk snapshot-dan restore etməli oldular, ~6 saatlıq istifadəçi data-sını itirdilər.

### Əsas səbəb
- Yorğun mühəndis + oxşar hostname-lər
- `db1.cluster` (primary) və `db2.cluster` (replica) — asan qarışdırıla bilər
- Bütün backup mexanizmləri test edilməmişdi
- Backup sağlamlığı üzrə alerting yox

### Dərslər
- **Backup-larını test et**: test edilməmiş backup-lar arzulardır, backup deyil
- **Backup notification-ı da backup et**: backup-lar səssizcə uğursuz olanda kimsə bilməlidir
- **Geri qaytarılmaz komandalar friksiya tələb edir**: multi-factor, sanity check-lər, və ya alət wrapper-ları olmadan production-da `rm -rf` yoxdur
- **Yuxu önəmlidir**: axşam 11-də tək destruktiv əməliyyatlar etmə

GitLab olduqca şəffaf idi: bərpanı YouTube-da livestream etdilər. Bu şəffaflıq özü bir mədəniyyət dərsi oldu.

PHP/Laravel ekvivalenti: DB backup-larını aylıq test et. `pg_restore` və ya `mysql <` ilə staging mühitə, data integrity-ni yoxla. Axşam 11-də ikinci mühəndis review etmədən tək deploy etmə.

---

## 6. Knight Capital (Aug 1, 2012) — 45 dəqiqədə $440M

### Nə baş verdi
Knight Capital yeni trading sistemini (SMARS) 8 server-dən 7-nə deploy etdi. 8-ci server-də "Power Peg" adlı köhnə yatmış kod yeni sistemin istifadə etdiyi eyni flag-la təsadüfən yenidən aktivləşdirildi. Power Peg 9 il istifadə olunmamışdı amma heç vaxt silinməmişdi. Geniş miqyasda baha almağa və ucuz satmağa başladı. 45 dəqiqə ərzində Knight $440 milyon itirdi. Şirkət az qala iflas etdi.

### Əsas səbəb
- Codebase-də qalmış ölü kod
- Köhnə kod yolunu başa düşmədən sistemlər arasında yenidən istifadə edilən eyni feature flag
- Deploy bütün 8 server-ə çatmadı — config uyğunsuzluğu
- Runaway avtomatlaşdırılmış trade-lər üçün kill switch yox

### Dərslər
- **Ölü kodu sil**: "hər ehtimala qarşı" saxlama
- **Flag-ları sistemlər arasında yenidən istifadə etmə**: köhnə məna qalır
- **Qismən deploy-lar təhlükəlidir**: bütün server-lərin yeniləndiyini təsdiqlə
- **Kill switch-lər**: yüksək-təsirli avtomatlaşdırılmış sistemlər manual override tələb edir
- **Canary / circuit breaker**: itkilər avtomatlaşdırılmış shutdown-u tətikləməli idi

PHP/Laravel ekvivalenti: artıq işləməməli olan köhnə Horizon job-ları və ya scheduled task-lar varsa, onları sil, disabled saxlama. Scope-lu və unikal feature flag adları istifadə et.

---

## Şərəf qeydləri

### Slack outage (Jan 2021)
Slack ~5 saat aşağı idi. Əsas səbəb: provisioning məntiqi AWS scaling zamanı infrastrukturu həddən artıq yükləyən feedback loop yaratdı. Dərs: autoscaling + feedback loop = pisdir. Autoscaling-ini sintetik yüklə test et.

### Cloudflare global outage (June 2022)
Data plane update-də BGP + ACL misconfig. 19 data mərkəzi əlçatmaz. Dərs: data plane dəyişiklikləri control plane-dən daha ciddi review tələb edir.

### Azure multi-region outage (March 2020)
Tək availability-zone soyutma uğursuzluğu zone boyunca alt hardware uğursuzluqlarına səbəb oldu. Eyni zone-dakı replica-lar da aşağı düşdü. Dərs: failure domain-ını başa düş. Multi-AZ ayrı AZ-lar deməkdir, "eyninin birdən çox nüsxəsi" deyil.

### Atlassian 14 günlük outage (April 2022)
"Deprecated" Jira instansiyalarının scripted təmizlənməsi təsadüfən aktiv müştəri data-sını sildi. Bütün 775 müştəri saytını restore etmək 14 gün çəkdi. Dərs: data silən script-lər multi-səviyyəli təsdiq, mərhələ-əsaslı rollout və ani rollback tələb edir.

### Heroku SSL outage (Sept 2018)
Router qatı yeni SSL config deploy etdi. 3 saat connection-ları rədd etdi. Dərs: SSL/TLS dəyişiklikləri xüsusilə təhlükəlidir, çünki uğursuzluq rejimləri incədir.

---

## Bütün bunlarda pattern-lər

1. **Kiçik dəyişiklik, böyük blast radius** — qeyri-kafi scoping-li config/rm/deploy
2. **Avtomatlaşdırma zərəri artırdı** — rollback alətləri, auto-failover, auto-deploy başladıqdan sonra vəziyyəti pisləşdirdi
3. **Olmamalı olan asılılıqlar** — qırıq xidmətdə status page, qırıq backbone-da management şəbəkəsi
4. **Ölü kod və ya yatmış kod yenidən oyandı** — Knight Capital, bir çox başqaları
5. **Test edilməmiş recovery yolları** — backup-lar, failover, recovery alətləri müntəzəm məşq edilmir

## PHP dev-i praktiki olaraq nə daxili edə bilər

- Destruktiv komandalar üçün həmişə əvvəlcə `--dry-run` (`php artisan db:wipe`, `rm`, SQL `DELETE`)
- Feature flag adları scope-lu və unikal
- Backup-ları test et: rüblük staging-ə restore et
- Rollback mexanizmi test olunur, fərz olunmur
- "Təhlükəsiz" dəyişikliklər üçün belə canary deploy-lar
- Məhdud input ilə regex, fuzzing ilə test olunur
- Hər şeydə resurs timeout-ları (Guzzle, DB query-ləri, regex)
- Yorğun + destruktiv = pisdir. Riskli əməliyyatlar üçün iki adam lazımdır
- Status page əsas infrastrukturdan ayrı
- Ölü kodu sil; disable etmə

## Müsahibə bucağı

"Hansı industry incident-ləri haqqında düşünürsən?"

Güclü cavab:
- "Knight Capital mənim ən sevimli müəllimimdir. Yenidən istifadə edilən feature flag ilə yenidən aktivləşdirilən ölü kod, 45 dəqiqədə $440M. Daşıdığım dərs: ölü kodu sil, feature flag adlarını scope et və avtomatlaşdırılmış sistemlər üçün kill switch saxla."
- "GitLab 2017 backup-lar üçün — test edilməmiş backup-a güvənmə. Dövri olaraq staging-i prod backup-larından restore edirəm ki, yoxlayım."
- "Cloudflare regex 2019 input idarə etməsi üçün — regex-i həmişə adversial input ilə test edirəm və məhdud input validasiyası istifadə edirəm."
- "Facebook BGP 2021 recovery planlaması üçün — fix mexanizmin qırılan şeydən asılı olmamalıdır."

Bonus: "Bunların hər biri yeni komandaya danışacağım hekayədir. Qorxutmaq üçün deyil, hər dəyişiklikdən əvvəl blast radius haqqında düşünməyə hazırlamaq üçün."
