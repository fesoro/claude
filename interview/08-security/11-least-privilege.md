# Principle of Least Privilege (Senior ⭐⭐⭐)

## İcmal
Principle of Least Privilege (PoLP) — hər istifadəçiyə, prosesə, sisteme yalnız öz işini görəcəyi qədər icazə vermək prinsipidir. Bu prinsip NIST SP 800-53, ISO 27001, CIS Controls, OWASP kimi beynəlxalq standartların təməlini təşkil edir. Interview-da bu mövzu Senior developer-ın sistemin daha geniş security arxitekturasını düşünüb-düşünmədiyini ortaya çıxarır.

## Niyə Vacibdir
Breach zamanı "blast radius"-u məhdudlaşdıran ən effektiv yanaşmalardan biridir. Bir komponent pozulsa belə, onun az icazəsi varsa sistem ziyanı minimaldır. İnterviewerlər bu prinsipin database icazələrindən tutmuş Kubernetes RBAC-ına, API key scope-larına qədər necə tətbiq edildiyini soruşur. Senior developer yalnız konsepti yox, real sistemdə bu prinsipin hər qatında necə tətbiq olunduğunu bilməlidir.

## Əsas Anlayışlar

- **Need-to-know basis**: Komponent yalnız öz funksiyası üçün lazım olan resurslara çata bilər. Payment service-i user profile-ı oxumamalıdır.
- **Blast radius**: Bir komponent compromised olduqda zərər sahəsi — az icazə = az zərər. Attacker application layer-ə girəndə laterally haraya gedə bilər?
- **Separation of Duties (SoD)**: Tək şəxs/proses kritik əməliyyatı başlanğıcdan sona tamamlaya bilməsin — hər addım başqa kimə tərəfindən yoxlanılmalıdır.
- **Database icazələri**: Application user-i yalnız lazım olan cədvəllərdə `SELECT/INSERT/UPDATE/DELETE` icazəsinə malik olmalı. `DROP TABLE`, `CREATE`, `GRANT`, `TRUNCATE` kimi DDL icazələri olmamalıdır.
- **File system icazələri**: Web server prosesi yalnız `public/` direktoriyasını oxuya bilsin. `/etc`, `/root`, SSH key-lərinə çata bilməsin.
- **API key scope-ları**: Stripe, AWS, third-party API key-lərinin yalnız lazım olan operation-lara icazəsi olsun. Read-only key write əməliyyatı etməsin.
- **AWS IAM PoLP**: IAM role-larında `*:*` deyil, konkret `s3:GetObject`, `s3:PutObject`. Specific ARN üzrə məhdudlaşdır.
- **Kubernetes RBAC**: ServiceAccount-a yalnız özünün namespace-indəki resurslara access. Cluster-wide admin verməyin.
- **Linux file permissions**: `chmod 640`, `chown www-data`. Prinsip Unix icazə modelinin özəyidir.
- **Environment-based separation**: Production secret-lərinə yalnız production service-inin çatması. Bütün developer-lar production database-ə birbaşa çata bilməsin.
- **Zero Trust model**: "Trust but verify" deyil — "Never trust, always verify". Hər request kimlik yoxlamasından keçsin, daxili network-də belə.
- **Privilege escalation attack**: Az icazəli account-dan daha çox icazə qazanmaq cəhdi. PoLP bunu çətinləşdirir, istismar edilə biləcək yolları azaldır.
- **Service accounts vs personal accounts**: Avtomatik proseslər ayrı service account-larla işləsin. İnsan hesabı automated prosesə bağlanmamalıdır.
- **Token expiry**: Qısa müddətli credential-lar — long-lived token-lar daha böyük risk. JWT-nin `exp` claim-i məcburi olmalıdır.
- **Just-in-time (JIT) access**: Daimi icazə vermək əvəzinə, lazım olduqda müvəqqəti icazə vermək. AWS SSO, HashiCorp Vault dynamic secrets bu yanaşmanı dəstəkləyir.
- **Audit trail ilə birlikdə**: Kim, nə zaman, hansı resursa çatdı — PoLP icazəni məhdudlaşdırır, audit kim çatdığını qeyd edir. Birlikdə güclüdür.
- **Principle of Fail-Safe Defaults**: Default olaraq access deny — explicit olaraq icazə verilir. "Allow all, block known bad" deyil, "Deny all, allow known good".
- **Scope creep**: Zamanla icazələr artır, kimsə azaltmır. Periodic access review vacibdir.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Bu mövzunu abstract prinsipdən konkret texniki nümunələrə aparmaq çox vacibdir. Database icazəsindən başlayın, oradan AWS IAM-a, Kubernetes-ə, application code-a qədər hər qatda necə tətbiq etdiyinizi izah edin. "Blast radius" anlayışını istifadə etmək güclü cavab əlamətidir.

**Hansı konkret nümunələr gətirmək:**
- "Bizim Laravel app-imiz database-ə `app_readonly` və `app_write` adlı iki ayrı user ilə qoşulur — read replica-da yalnız readonly user"
- "S3 bucket-imizin public access tamamilə bağlıdır, yalnız specific IAM role ondan oxuya bilir"
- "CI/CD pipeline üçün ayrı service account yaratdıq — production database-ə birbaşa çatışı yoxdur"
- "API key-lərimizin hər biri minimal scope ilə yaradılır, rotation policy aktivdir"

**Follow-up suallar interviewerlər soruşur:**
- "Mövcud sistemdə artıq verilmiş icazələri necə audit edərdiniz?"
- "PoLP ilə developer productivity arasındakı tarazlığı necə qurursunuz?"
- "Microservices-də service-lər arası communication üçün bu prinsipi necə tətbiq edərdiniz?"
- "JIT access nədir, onu harada tətbiq edərdiniz?"
- "Zero Trust model PoLP ilə necə əlaqəlidir?"

**Red flags — pis cavab əlamətləri:**
- Database-ə `root` user ilə qoşulmaq — "development-da fərq etmir" demək
- Bütün service-lərə eyni API key vermək
- Prinsipə dair ümumi söhbət etmək, amma real tətbiqetmə nümunəsi verə bilməmək
- "Bu DevOps-un işidir" demək — developer tətbiq kodunda da bu məsuliyyəti daşıyır
- `GRANT ALL PRIVILEGES ON *.* TO 'app'@'%'` — application database user-i üçün

## Nümunələr

### Tipik Interview Sualı
"Laravel tətbiqiniz pozulur (compromised). Principle of Least Privilege-i düzgün tətbiq etmişdinizmi, bunun breach-in nəticəsinə necə təsiri olar?"

### Güclü Cavab
"Əgər PoLP-u düzgün tətbiq etmişiksə, breach-in blast radius-u əhəmiyyətli dərəcədə azalır.

Database qatında — application user-imizin yalnız lazım olan cədvəllərdə `SELECT`, `INSERT`, `UPDATE`, `DELETE` icazəsi var. `DROP`, `CREATE`, `GRANT`, `TRUNCATE` icazəsi yoxdur. Beləcə attacker database strukturunu dəyişdirə bilməz, başqa cədvəllərə, başqa database-lərə çata bilməz.

File system qatında — PHP prosesi `www-data` user kimi işləyir, yalnız `/var/www/html` direktoriyasına lazım olan icazə var. `/etc/passwd`, SSH key-lər, `/root` kimi kritik fayllara çatışı yoxdur.

AWS/cloud qatında — application-ın IAM role-u yalnız lazım olan S3 bucket-ə, SQS queue-ya çata bilir. RDS admin, IAM management, CloudFormation — bunlara icazəsi yoxdur.

Secrets qatında — Laravel-in `.env`-ı yalnız application prosesi tərəfindən oxunur. CI/CD pipeline-ın production secret-lərə birbaşa çatışı yoxdur.

Application qatında — Policy class-lar vasitəsilə user yalnız öz datasına çata bilir. Admin endpoint-lərə role check var.

Nəticə: Attacker application layer-dən daxil olsa belə, lateral movement — başqa sistemlərə keçmək — çox çətin olur. Bu PoLP-un əsas dəyəridir — breached component-dən kənara çıxmaq çətinləşir."

### Kod Nümunəsi — Database İcazələri PostgreSQL

```sql
-- Application üçün minimal icazəli user
CREATE USER app_user WITH PASSWORD 'strong_password_here';
CREATE USER app_readonly WITH PASSWORD 'readonly_password_here';

-- Yalnız lazım olan cədvəllərə icazə ver
GRANT SELECT, INSERT, UPDATE, DELETE
    ON users, orders, products, order_items
    TO app_user;

GRANT USAGE ON SEQUENCE
    users_id_seq, orders_id_seq, products_id_seq
    TO app_user;

-- Read replica üçün yalnız SELECT
GRANT SELECT ON ALL TABLES IN SCHEMA public TO app_readonly;
GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO app_readonly;

-- Sistem cədvəllərinə icazə yox
-- pg_catalog, information_schema — default olaraq məhdudlaşdırılmış

-- ❌ Əsla belə etmə — application üçün DDL icazəsi
-- GRANT ALL PRIVILEGES ON DATABASE myapp TO app_user;

-- Migration üçün ayrı user — yalnız migration zamanı istifadə olunur
CREATE USER app_migrator WITH PASSWORD 'migrator_password';
GRANT ALL PRIVILEGES ON DATABASE myapp TO app_migrator;
-- CI/CD bitdikdən sonra bu connection bağlanır
```

### Kod Nümunəsi — AWS IAM Minimal Policy

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "S3UploadsAccess",
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::my-app-bucket/uploads/*"
    },
    {
      "Sid": "S3ListUploads",
      "Effect": "Allow",
      "Action": "s3:ListBucket",
      "Resource": "arn:aws:s3:::my-app-bucket",
      "Condition": {
        "StringLike": {
          "s3:prefix": "uploads/*"
        }
      }
    },
    {
      "Sid": "SQSWorkerQueue",
      "Effect": "Allow",
      "Action": [
        "sqs:ReceiveMessage",
        "sqs:DeleteMessage",
        "sqs:GetQueueAttributes"
      ],
      "Resource": "arn:aws:sqs:us-east-1:123456789:my-app-worker-queue"
    }
  ]
}

// ❌ Pis nümunə — admin key
{
  "Effect": "Allow",
  "Action": "*",
  "Resource": "*"
}
```

### Kod Nümunəsi — Laravel Policy (Application Layer PoLP)

```php
// app/Policies/OrderPolicy.php
class OrderPolicy
{
    // İstifadəçi yalnız öz sifarişini görə bilər
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            || $user->hasRole('admin');
    }

    // Yalnız admin ləğv edə bilər (özü deyil)
    // customer özü yalnız "pending" statusu zamanı cancel edə bilər
    public function cancel(User $user, Order $order): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->id === $order->user_id
            && $order->status === 'pending';
    }

    // Yalnız finance rolu export edə bilər
    public function export(User $user): bool
    {
        return $user->hasPermission('orders.export');
    }

    // Admin note-u yalnız admin yazır
    public function updateAdminNote(User $user): bool
    {
        return $user->hasRole('admin');
    }
}

// Controller-da
class OrderController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order); // PoLP tətbiqi
        return response()->json($order);
    }

    public function cancel(Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);
        $order->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Cancelled']);
    }
}
```

### Kod Nümunəsi — Linux File Permissions (Laravel)

```bash
# Laravel üçün tövsiyə olunan icazə strukturu
# www-data prosesi lazım olan qədər icazə alır

# Ownership: root sahiblik edir, www-data oxuya bilər
chown -R root:www-data /var/www/html

# Fayl icazəsi: owner=rw, group=r, other=nothing
find /var/www/html -type f -exec chmod 640 {} \;

# Qovluq icazəsi: owner=rwx, group=rx, other=nothing
find /var/www/html -type d -exec chmod 750 {} \;

# Storage — www-data yazmalıdır (logs, cache, uploads)
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# .env — çox məhdud icazə
# www-data oxuya bilir, başqası xeyr
chmod 640 /var/www/html/.env
chown root:www-data /var/www/html/.env

# SSH keys — yalnız sahibi oxuya bilir
chmod 600 ~/.ssh/id_rsa
chmod 644 ~/.ssh/id_rsa.pub

# Php-fpm worker adı: www-data
# Heç vaxt root kimi işləməsin
# /etc/php-fpm.d/www.conf:
# user = www-data
# group = www-data
```

### Attack Nümunəsi — PoLP Olmadan Breach

```
Ssenari: Laravel application-da file upload zəifliyi var

PoLP YOX:
1. Attacker PHP shell yükləyir — çalışdırır
2. PHP prosesi root kimi işləyir
3. Attacker:
   - /etc/passwd oxuyur — user adları
   - /etc/shadow oxuyur — şifrə hash-ləri
   - ~/.ssh/authorized_keys-ə öz key-ini əlavə edir
   - Database-ə GRANT ALL edir, yeni admin user yaradır
   - AWS metadata: curl http://169.254.169.254/ → IAM token alır
   - S3 bütün bucket-lara yazır
   - Digər server-lərə lateral movement
4. Tam sistem kompromis olunur

PoLP İLƏ:
1. Attacker PHP shell yükləyir — çalışdırır
2. PHP prosesi www-data kimi işləyir
3. Attacker yalnız:
   - /var/www/html altındakı fayllara çata bilər (storage/)
   - Database-ə app_user icazəsi ilə bağlanır — DDL icazəsi yox
   - Başqa serverlərə çatmaq üçün credential tapammır
4. Blast radius məhdudlaşır — ciddi ziyan, amma tam sistem deyil
```

## Praktik Tapşırıqlar

- Mövcud Laravel layihənizdə database user-in hansı icazələri var? `SELECT * FROM information_schema.role_table_grants WHERE grantee = 'app_user';` ilə yoxlayın
- AWS IAM console-da `*:*` wildcard istifadə edən policy tapın — minimal versiyasını yazın
- CI/CD pipeline-ınız production database-ə birbaşa çata bilirmi? Bunu izole edin
- Service account-larınız personal developer account-lar ilə eyni icazə səviyyəsindədirmi? Ayırın
- Laravel Policy class-larında bütün sensitive əməliyyatların `authorize()` çağrısı olub-olmadığını yoxlayın
- Token-larınızın `expiry` müddəti nədir? Long-lived token-lar var? `exp` claim-i məcburi edin
- Zero Trust yanaşması ilə internal API-larınızı necə qoruyarsınız? mTLS, API key?

## Ətraflı Qeydlər

**Spanner-model icazə review**: Zamanla icazələr artır — scope creep. Minimum quarterly review lazımdır. Kimsə getdikdə icazəsi silinməlidir (offboarding checklist).

**IAM Access Analyzer (AWS)**: Potensial artıq icazəli policy-ləri avtomatik tapır. "Public access" kimi kritik konfiqurasiyaları alert edir.

**Kubernetes RBAC minimal nümunəsi**:
```yaml
# Pod-un yalnız öz namespace-indəki secret-ləri oxuması
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  namespace: my-app
  name: secret-reader
rules:
- apiGroups: [""]
  resources: ["secrets"]
  resourceNames: ["app-secret", "db-secret"] # Yalnız bu secret-lər
  verbs: ["get"]
---
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: app-secret-reader
  namespace: my-app
subjects:
- kind: ServiceAccount
  name: my-app-sa
roleRef:
  kind: Role
  apiGroupL: rbac.authorization.k8s.io
  name: secret-reader
```

**PHP-FPM process isolation**: Fərqli müştərilərin application-larını ayrı user-lərlə çalışdır — bir müştərinin kodu digərinin fayllarına çata bilməsin.

## Əlaqəli Mövzular
- `04-authentication-authorization.md` — PoLP authorization modelinin özəyidir
- `08-secrets-management.md` — Secret scope-larını minimal saxlamaq PoLP-un bir hissəsidir
- `12-audit-logging.md` — Kim hansı resursa çatdı — audit log PoLP-u tamamlayır
- `14-security-in-cicd.md` — Pipeline icazələrinin PoLP prinsipi ilə qurulması
- `15-threat-modeling.md` — Threat model-də privilege escalation vektoru PoLP ilə məhdudlaşdırılır
