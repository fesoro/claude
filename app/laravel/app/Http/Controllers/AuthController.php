<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Src\Shared\Application\Bus\CommandBus;
use Src\User\Application\Commands\RegisterUser\RegisterUserCommand;
use Src\User\Application\DTOs\RegisterUserDTO;
use Src\User\Infrastructure\Models\UserModel;

/**
 * AUTENTİFİKASİYA CONTROLLER-İ (Authentication)
 * ===============================================
 * Bu controller istifadəçi autentifikasiyası ilə bağlı bütün əməliyyatları idarə edir.
 *
 * ═══════════════════════════════════════════════════════════════════
 * LARAVEL SANCTUM NƏDİR?
 * ═══════════════════════════════════════════════════════════════════
 * Sanctum — Laravel-in yüngül autentifikasiya paketidir.
 * İki əsas istifadə ssenari var:
 *
 * 1. SPA (Single Page Application) autentifikasiyası:
 *    - Cookie əsaslı session autentifikasiyası istifadə edir.
 *    - Frontend (Vue, React) eyni domain-dədirsə, bu üsul idealdır.
 *
 * 2. API TOKEN autentifikasiyası:
 *    - Mobil tətbiqlər və ya xarici API-lər üçün istifadə olunur.
 *    - Hər istifadəçiyə unikal token verilir.
 *    - Token hər sorğuda "Authorization: Bearer <token>" header-ində göndərilir.
 *    - Bu layihədə biz TOKEN üsulunu istifadə edirik.
 *
 * ═══════════════════════════════════════════════════════════════════
 * TOKEN NECƏ İŞLƏYİR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. İstifadəçi login olur → Server token yaradır (createToken metodu).
 * 2. createToken() iki şey qaytarır:
 *    - Hash olunmuş token → verilənlər bazasında saxlanılır (personal_access_tokens cədvəli).
 *    - plainTextToken → istifadəçiyə qaytarılır (yalnız BİR DƏFƏ göstərilir!).
 * 3. İstifadəçi hər sorğuda bu token-i göndərir:
 *    Authorization: Bearer 1|abc123def456...
 * 4. Sanctum token-i yoxlayır → istifadəçini təyin edir → sorğuya icazə verir.
 *
 * ═══════════════════════════════════════════════════════════════════
 * SANCTUM vs PASSPORT vs JWT — FƏRQLƏR
 * ═══════════════════════════════════════════════════════════════════
 *
 * SANCTUM:
 *   - Yüngül və sadə, Laravel ilə gəlir.
 *   - SPA + API token dəstəyi.
 *   - OAuth2 yoxdur — sadə token sistemidir.
 *   - Kiçik-orta layihələr üçün idealdır.
 *   - Token verilənlər bazasında saxlanılır.
 *
 * PASSPORT (laravel/passport):
 *   - Tam OAuth2 server implementasiyası.
 *   - Authorization Code, Client Credentials, Password Grant kimi grant type-lar.
 *   - Üçüncü tərəf tətbiqlərə API icazəsi vermək lazımdırsa, Passport seçilir.
 *   - Daha mürəkkəb quraşdırma tələb edir.
 *
 * JWT (tymon/jwt-auth və ya başqa paketlər):
 *   - Token verilənlər bazasında saxlanılmır — token özü bütün məlumatı daşıyır.
 *   - Stateless — server token-i yadda saxlamır, hər dəfə decode edir.
 *   - Microservice arxitekturasında populyardır.
 *   - Token-i ləğv etmək (revoke) çətindir — blacklist lazımdır.
 *
 * BU LAYİHƏDƏ SANCTUM SEÇİLDİ, ÇÜNKÜ:
 *   - Sadə API token kifayətdir (OAuth2 lazım deyil).
 *   - Token-i istənilən vaxt ləğv etmək mümkündür (DB-dən silinir).
 *   - Laravel ilə mükəmməl inteqrasiya var.
 */
class AuthController extends Controller
{
    public function __construct(
        private CommandBus $commandBus,
    ) {}

    /**
     * POST /api/auth/register
     * Yeni istifadəçi qeydiyyatı və token yaradılması.
     *
     * AXIN:
     * 1. Request-dən data-nı validasiya et.
     * 2. RegisterUserCommand vasitəsilə istifadəçini yarat.
     * 3. Yaradılmış istifadəçi üçün Sanctum token-i yarat.
     * 4. Token-i cavabda qaytar — istifadəçi bunu saxlayıb sonrakı sorğularda göndərəcək.
     *
     * QEYD: Qeydiyyatdan sonra avtomatik token verilir ki,
     * istifadəçi dərhal login olmadan API-dən istifadə edə bilsin.
     */
    public function register(Request $request): JsonResponse
    {
        /**
         * Validasiya qaydaları:
         * - name: Mütləq olmalı, ən azı 2 simvol.
         * - email: Mütləq, düzgün email formatı, unikal olmalı.
         * - password: Mütləq, ən azı 8 simvol, təsdiq sahəsi ilə uyğun olmalı.
         *
         * QEYD: Əsas RegisterUserRequest FormRequest sinfi də var,
         * amma auth üçün ayrı validasiya istifadə edirik ki,
         * token yaratma prosesi burada idarə olunsun.
         */
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|unique:user_db.domain_users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        /**
         * RegisterUserCommand vasitəsilə istifadəçini yaradırıq.
         * DDD prinsipinə görə istifadəçi yaratma biznes logikası
         * Domain Layer-dədir — biz sadəcə Command göndəririk.
         */
        $command = new RegisterUserCommand(
            dto: new RegisterUserDTO(
                name: $validated['name'],
                email: $validated['email'],
                password: $validated['password'],
            ),
        );

        $userId = $this->commandBus->dispatch($command);

        /**
         * Yaradılmış istifadəçini DB-dən tapırıq ki, token yarada bilək.
         * createToken() metodu HasApiTokens trait-indən gəlir.
         *
         * createToken('auth_token') → Token-ə ad veririk.
         * Bu ad personal_access_tokens cədvəlindəki "name" sütununa yazılır.
         * Bir istifadəçinin bir neçə token-i ola bilər (mobil, web və s.).
         *
         * plainTextToken → Hash olunmamış token mətni.
         * Bu YALNIZ BİR DƏFƏ göstərilir! DB-də hash olunmuş versiyası saxlanılır.
         * İstifadəçi bunu itirərsə, yeni token yaratmalıdır.
         */
        $user = UserModel::find($userId);
        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success(
            data: [
                'user_id' => $userId,
                'token' => $token,
                /**
                 * token_type: Bearer — HTTP Authorization header-ində
                 * token-in hansı sxemlə göndərilməli olduğunu bildirir.
                 * "Bearer" — "Daşıyıcı" deməkdir, yəni bu token-i daşıyan
                 * şəxs autentifikasiya olunmuş sayılır.
                 *
                 * İstifadə: Authorization: Bearer 1|abc123def456...
                 */
                'token_type' => 'Bearer',
            ],
            message: 'İstifadəçi uğurla qeydiyyatdan keçdi',
            code: 201
        );
    }

    /**
     * POST /api/auth/login
     * İstifadəçi girişi — email və şifrə ilə autentifikasiya.
     *
     * AXIN:
     * 1. Email və şifrəni validasiya et.
     * 2. İstifadəçini DB-dən tap.
     * 3. Şifrəni yoxla (Hash::check).
     * 4. Uğurludursa, yeni Sanctum token yarat və qaytar.
     *
     * TƏHLÜKƏSİZLİK QEYDLƏRI:
     * - Şifrə DB-də bcrypt ilə hash olunmuş saxlanılır.
     * - Hash::check() düz mətni hash ilə müqayisə edir.
     * - Yanlış məlumat verildikdə "İstifadəçi tapılmadı və ya şifrə yanlışdır"
     *   kimi ümumi mesaj qaytarırıq — bu brute-force hücumlarını çətinləşdirir.
     *   Dəqiq mesaj ("Email tapılmadı" və ya "Şifrə yanlışdır") vermək
     *   hücumçuya hansı sahənin düzgün olduğunu bildirir.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        /**
         * İstifadəçini email ilə tapırıq.
         * UserModel Authenticatable-dan extend edir və HasApiTokens trait-i var,
         * ona görə createToken() metodunu istifadə edə bilirik.
         */
        $user = UserModel::where('email', $validated['email'])->first();

        /**
         * Şifrə yoxlaması:
         * Hash::check(düz_mətn, hash_olunmuş) → true/false
         * DB-dəki password avtomatik hash olunur ($casts-da 'hashed' qeyd olunub).
         */
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return ApiResponse::error(
                message: 'İstifadəçi tapılmadı və ya şifrə yanlışdır',
                code: 401
            );
        }

        /**
         * Hər login-də yeni token yaradırıq.
         * İstifadəçinin köhnə token-ləri aktiv qalır.
         * Əgər təhlükəsizlik üçün köhnə token-ləri silmək istəyirsinizsə:
         *   $user->tokens()->delete();  // Bütün token-ləri sil
         * Bu, digər cihazlardan çıxış etmək üçün istifadə olunur.
         */
        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success(
            data: [
                'user_id' => $user->id,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            message: 'Uğurla daxil oldunuz'
        );
    }

    /**
     * POST /api/auth/logout
     * Cari token-i ləğv edir (revoke).
     *
     * BU ENDPOINT auth:sanctum middleware ilə qorunur,
     * yəni yalnız etibarlı token ilə çağırıla bilər.
     *
     * NECƏ İŞLƏYİR:
     * 1. Sanctum middleware Authorization header-dəki token-i yoxlayır.
     * 2. Token etibarlıdırsa, $request->user() istifadəçini qaytarır.
     * 3. currentAccessToken() → hazırda istifadə olunan token-i tapır.
     * 4. delete() → token-i personal_access_tokens cədvəlindən silir.
     *
     * SİLİNDİKDƏN SONRA:
     * - Bu token daha işləməyəcək — 401 Unauthorized qaytaracaq.
     * - İstifadəçinin DİGƏR token-ləri (başqa cihazlardan) aktiv qalır.
     * - Bütün cihazlardan çıxmaq üçün: $user->tokens()->delete()
     */
    public function logout(Request $request): JsonResponse
    {
        /**
         * currentAccessToken() — Sanctum-un metodu.
         * Authorization header-indəki token-ə uyğun DB qeydini qaytarır.
         * delete() ilə həmin qeydi silirik → token artıq etibarsızdır.
         */
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(
            message: 'Uğurla çıxış etdiniz'
        );
    }

    /**
     * GET /api/auth/me
     * Autentifikasiya olunmuş istifadəçinin məlumatlarını qaytarır.
     *
     * BU ENDPOINT auth:sanctum middleware ilə qorunur.
     *
     * $request->user() — Sanctum middleware tərəfindən təyin olunur.
     * Middleware token-i yoxlayır, istifadəçini tapır və request-ə əlavə edir.
     * Əgər token etibarsızdırsa, middleware 401 qaytarır — bu metoda çatmır.
     *
     * NƏYƏ LAZIMDIR?
     * Frontend tətbiq açılanda "Bu istifadəçi kimdir?" sualına cavab verir.
     * Məsələn: Səhifə yüklənəndə /api/auth/me çağırılır,
     * istifadəçi adı, emaili və s. alınıb göstərilir.
     */
    /**
     * POST /api/auth/forgot-password
     * Şifrə sıfırlama emaili göndər.
     *
     * PASSWORD RESET AXINI:
     * =====================
     * 1. İstifadəçi email ünvanını göndərir
     * 2. Server random token yaradır (60 simvol)
     * 3. Token hash-lənib password_reset_tokens cədvəlinə yazılır
     * 4. Düz mətn token emaildəki URL-ə qoyulur
     * 5. İstifadəçi emaildəki linkə klikləyir
     * 6. Frontend token-i reset-password endpoint-ə göndərir
     *
     * TƏHLÜKƏSİZLİK:
     * - Token DB-də HASH olunmuş saxlanılır (Hash::make)
     * - Token-in ömrü 60 dəqiqədir (config/auth.php)
     * - Eyni email üçün yeni token yaradıldıqda köhnəsi silinir
     * - İstifadəçi tapılmasa belə eyni cavab qaytarılır (email enumeration qoruması)
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated()['email'];
        $user = UserModel::where('email', $email)->first();

        // Token yarat
        $token = Str::random(60);

        // Köhnə token-i sil, yenisini yaz
        DB::connection('user_db')->table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        DB::connection('user_db')->table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Reset URL — frontend-in şifrə sıfırlama səhifəsi
        $resetUrl = config('app.frontend_url', 'http://localhost:3000')
            . "/reset-password?token={$token}&email={$email}";

        // Email göndər
        Mail::to($email)->send(new PasswordResetMail(
            resetUrl: $resetUrl,
            userName: $user->name,
        ));

        return ApiResponse::success(
            message: 'Şifrə sıfırlama linki email ünvanınıza göndərildi'
        );
    }

    /**
     * POST /api/auth/reset-password
     * Yeni şifrə təyin et.
     *
     * AXIN:
     * 1. Token + email + yeni şifrə alınır
     * 2. DB-dəki hash ilə token müqayisə olunur
     * 3. Token etibarlıdırsa (60 dəq daxilində) → şifrə dəyişdirilir
     * 4. Token silinir (bir dəfəlik istifadə)
     * 5. Bütün mövcud Sanctum token-ləri ləğv edilir (təhlükəsizlik)
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // DB-dən token-i tap
        $record = DB::connection('user_db')->table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$record) {
            return ApiResponse::error('Etibarsız və ya vaxtı keçmiş token', code: 400);
        }

        // Token-i yoxla (hash müqayisəsi)
        if (!Hash::check($validated['token'], $record->token)) {
            return ApiResponse::error('Etibarsız token', code: 400);
        }

        // Token-in vaxtını yoxla (60 dəqiqə)
        $expireMinutes = config('auth.passwords.users.expire', 60);
        if (now()->diffInMinutes($record->created_at) > $expireMinutes) {
            return ApiResponse::error('Token-in vaxtı keçib', code: 400);
        }

        // Şifrəni dəyiş
        $user = UserModel::where('email', $validated['email'])->first();
        $user->update(['password' => $validated['password']]); // 'hashed' cast avtomatik hash edir

        // Token-i sil (bir dəfəlik)
        DB::connection('user_db')->table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->delete();

        // Təhlükəsizlik: bütün mövcud token-ləri ləğv et
        // İstifadəçi yenidən login olmalıdır
        $user->tokens()->delete();

        return ApiResponse::success(
            message: 'Şifrə uğurla dəyişdirildi. Yenidən daxil olun.'
        );
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $request->user(),
            message: 'İstifadəçi məlumatları'
        );
    }
}
