# Rollback Strategies (Middle)

## Problem (nəyə baxırsan)
Pis bir şey deploy etdin. İstifadəçilər təsirlənib. Tez geri qaytarmalısan. Sual "rollback etməliyik?" deyil — sual "hansı rollback mexanizmi ən sürətli VƏ təhlükəsizdir?"-dir. Bəzi mexanizmlər saniyələrlədir; digərləri orijinal bug-dan daha çox ziyan verir.

Rollback tələb edən simptomlar:
- Deploy timestamp-i ilə korrelyasiya edən error rate spike
- Deploy-dan sonra p95 latency iki qat olub
- Yeni feature idarə olunmayan exception-lar atır
- Config dəyişikliyi bir subsystem-i partladıb
- Canary metrikləri yeni versiyanı flag etdi

## Sürətli triage (ilk 5 dəqiqə)

### Rollback nərdivanı (ən sürətli → ən yavaş, ən təhlükəsiz → ən riskli)

1. **Feature flag flip** — saniyələr, sıfır risk
2. **Config rollback** — 1-2 dəqiqə, aşağı risk (əgər versiyalanıbsa)
3. **Əvvəlki container/image redeploy** — 2-10 dəqiqə
4. **Git revert + deploy** — 10-30 dəqiqə
5. **Database rollback** — qaçın; adətən əvəzində roll-forward

Əvvəl nərdivanın yuxarısını cəhd et.

### Rollback edə bilərsən?

Dayan və soruş:
- Deploy DB schema dəyişikliyi daxil etdimi? Geri qaytarıla bilməzdirmi?
- Yeni kod köhnə kodun oxuya bilməyəcəyi formatda data yazırmı?
- Üçüncü tərəf inteqrasiyaları yeni webhook URL-i barədə məlumatlandırılıbmı?

Əgər hər hansı birinə "bəli" olsa, rollback yeni problemlər yarada bilər. Əvəzində roll-forward-u nəzərdən keçir.

## Diaqnoz

### Feature flag flip

Ən sürətli rollback. Deploy yoxdur. Qərardan təsirə qədər saniyələr.

```bash
# LaunchDarkly
ld flag off new-checkout-flow --project myapp --env production

# Unleash
curl -X POST https://unleash.example.com/api/admin/features/new-checkout/off

# Split.io
split set-off new-checkout --env production
```

Laravel daxili flag cədvəli:
```sql
UPDATE feature_flags SET enabled = 0 WHERE name = 'new_checkout_flow';
```

Tələb edir: feature əvvəldən flag arxasında idi. Deploy-dan sonra feature-i retroaktiv olaraq flag-guard edə bilməzsən.

### Əvvəlki image/container redeploy

Kubernetes:
```bash
# Most recent known-good revision
kubectl rollout undo deployment/api-service -n production

# Specific revision
kubectl rollout undo deployment/api-service --to-revision=12 -n production

# Watch
kubectl rollout status deployment/api-service -n production
```

ArgoCD:
```bash
argocd app rollback my-app 12
```

Laravel Envoyer / Forge — əvvəlki deployment-a klik et → redeploy.

Məsləhət: container image tag-ları immutable olmalıdır. `:latest` bir rollback tələsidir. `:v2.4.1` və ya git SHA istifadə et.

### Git revert + redeploy

```bash
git revert <bad-commit-sha>
git push origin main
# CI/CD picks it up, deploys
```

Nə vaxt istifadə et:
- Image rollback mümkün deyil (köhnə infrastruktur, image registry retention yoxdur)
- Gələcək üçün rollback-ın git tarixçəsində izlənməsini istəyirsən
- Seçməli revert lazımdır (yalnız pis commit-i revert et, ondan sonra gələn yaxşıları saxla)

Tradeoff: bütün CI pipeline işlədiyi üçün daha uzun çəkir.

### Blue-green swap

İki mühit eyni anda işləyir: blue (köhnə), green (yeni). Green-ə deploy et, test et, trafiki swap et. Rollback üçün: geri swap et.

```bash
# AWS ALB target group switch
aws elbv2 modify-listener --listener-arn ... \
  --default-actions Type=forward,TargetGroupArn=$BLUE_TG
```

Demək olar ki, ani rollback. Amma: 2x infrastruktur xərci, DB eyni schema-ya yazan hər iki versiyanı idarə etməlidir.

### Canary reverse

Canary deploy-lar kiçik faizi yeni versiyaya yönəldir. Əgər yeni versiya pisdirsə:

```bash
# Argo Rollouts
kubectl argo rollouts abort api-service
# Traffic routes back to stable version instantly
```

Ən yaxşı praktika: metric regressiyasında avtomatik abort edən avtomatlaşdırılmış canary analizi (Flagger, Argo Rollouts).

## Fix (bleeding-i dayandır)

### İncident zamanı qərar ağacı

```
Deploy-correlated issue → 

  Is there a feature flag for the change?
    YES → flip flag, done (seconds)
    NO → continue
    
  Is it a config change?
    YES → revert config, redeploy config-only (1-2 min)
    NO → continue
    
  Does the deploy include DB schema change?
    YES → roll-forward, not rollback
    NO → image rollback (2-10 min)
```

### Rollback elanı

```
ROLLBACK INITIATED at 14:42 UTC
Rolling back deployment abc123 → previous def456
Method: kubectl rollout undo
ETA to effect: 3 minutes
IC: @orkhan
```

## Əsas səbəbin analizi

İncident-dən sonra:
- Bu niyə production-a çatdı?
- Canary / staging tutdumu?
- Əgər yox, niyə?
- Feature-flagged ola bilərdimi?
- Rollback təmiz uğur qazandı, yoxsa kollateral zərər verdi?

## Qarşısının alınması

- Hər riskli dəyişiklik feature flag arxasında
- Immutable image tag-ları (prod-da `:latest` yox)
- Hər ay test edilən rollback düyməsi (yalnız nəzəri deyil)
- N-1 və N-2 image versiyalarını saxla (90 gün)
- Metrik regressiyasında avtomatik abort ilə canary deploy-ları
- DB migration-ları kod-dan ayrı deploy olunur (əvvəl), kod rollback-ı schema rollback olmadan mümkün olsun

## PHP/Laravel xüsusi qeydlər

### Laravel feature flag pattern-i

`laravel/pennant` ilə:
```php
use Laravel\Pennant\Feature;

if (Feature::active('new-checkout')) {
    return $this->newFlow();
}
return $this->oldFlow();
```

Flag 100% stabil müddət ərzində (1-2 həftə) açıq olana qədər KÖHNƏ kod yolunu saxla.

### Laravel deploy rollback

Envoyer:
- Web UI → Deployments → Previous → Redeploy
- Əvvəlki release-ə symlink swap

Forge:
- Sites → Deployments → Rollback düyməsi

Deployer (`dep rollback`):
```bash
dep rollback production
```

Homebrew deploy (rsync/ssh):
```bash
# Keep releases/ directory with last N deploys
ssh prod "cd /var/www/myapp && ln -sfn releases/v2.4.1 current && php-fpm reload"
```

### DB-migration rollback tələsindən qaçmaq

Anti-pattern:
```php
// Deploy A: add column + write to it
Schema::table('users', fn($t) => $t->string('phone'));
// code writes $user->phone

// Deploy B: bad code ships
// ROLLBACK to A: column still there, code writes to it, fine

// Deploy B: adds another migration
Schema::table('users', fn($t) => $t->dropColumn('email'));  
// ROLLBACK to A: code references $user->email which is gone → errors
```

Qayda: **kod rollback-ı cari schema vəziyyətinə qarşı təhlükəsiz olmalıdır**. Schema-qıran migration-lar forward-only strategiya tələb edir.

## Yadda saxlanmalı real komandalar

```bash
# Kubernetes
kubectl rollout undo deployment/api
kubectl rollout status deployment/api
kubectl rollout history deployment/api
kubectl rollout undo deployment/api --to-revision=5

# ArgoCD
argocd app history my-app
argocd app rollback my-app 5
argocd app sync my-app --revision=abc123

# AWS ECS
aws ecs update-service --cluster prod --service api \
  --task-definition api:42

# Docker Compose
docker compose pull api:v2.4.1
docker compose up -d --no-deps api

# Laravel
php artisan deploy:rollback             # if using deployer
php artisan migrate:rollback --step=1   # DB migration only

# Envoyer
# Web UI only

# Git revert
git revert <sha>
git push origin main
git revert --no-commit <sha1> <sha2>    # multiple commits
```

## Müsahibə bucağı

"Prod-da pis deploy. Rollback prosesini izah et."

Güclü cavab:
- "Mənim bir rollback nərdivanım var. Birinci: feature flag. Dəyişiklik flag-guard olunubsa, flag-ı flip etmək saniyələrlədir və geri qaytarılabilir. Həmişə ilk hərəkətim."
- "İkinci: image rollback. `kubectl rollout undo` və ya ArgoCD rollback son bilinən yaxşı revision-a. Yalnız kod olsa sürətli və təmizdir."
- "Üçüncü: image rollback mövcud deyilsə, git revert + redeploy. Tam CI olduğu üçün daha yavaş, amma tarixçədə izlənir."
- "Kritik: rollback etməzdən əvvəl schema dəyişikliklərini yoxlayıram. Əgər deploy təhlükəsiz şəkildə geri qaytarılmayan migration daxil edibsə, rollback yeni problem yaradır. Bu halda fix ilə roll forward edirəm."
- "Bunu edərkən kommunikasiya: incident kanalında rollback-ın getdiyini, ETA və metodu post et."
- "İncident-dən sonra: pis dəyişiklik niyə keçdiyini soruş. Cavab adətən canary çatışmazlığı, test çatışmazlığı, və ya riskli dəyişiklikdə feature flag yoxluğudur."

Bonus: "Son işimdə qayda qoyduq: payment və ya auth axınına toxunan hər dəyişiklik feature flag arxasında olmalı VƏ 10% canary-dən keçməlidir. Hər payment-bağlı regressiya üçün rollback vaxtı ~15 dəq (image rollback)-dan ~30 saniyə (flag flip)-ə düşdü. Bu bizim MTTR-imizi əhəmiyyətli dərəcədə azaltdı."
