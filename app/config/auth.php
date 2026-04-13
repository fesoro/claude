<?php

use Src\User\Infrastructure\Models\UserModel;

/**
 * AUTENTİFİKASİYA KONFİQURASİYASI
 * ==================================
 * Bu fayl Laravel-in autentifikasiya sistemini konfiqurasiya edir.
 *
 * ÖNƏMLİ DƏYİŞİKLİK:
 * Default App\Models\User əvəzinə Src\User\Infrastructure\Models\UserModel
 * istifadə olunur. Çünki DDD arxitekturasında User modeli
 * User Bounded Context-in Infrastructure layer-indədir.
 *
 * Sanctum bu konfiqurasiyanı istifadə edir ki:
 * - Token-in hansı istifadəçiyə aid olduğunu təyin etsin.
 * - $request->user() çağırıldıqda düzgün modeli qaytarsın.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Sanctum avtomatik olaraq "sanctum" guard-ını əlavə edir.
    | auth:sanctum middleware istifadə edildikdə, Sanctum guard işləyir.
    | Sanctum guard əvvəlcə session-a baxır (SPA üçün),
    | tapılmasa Bearer token-i yoxlayır (API üçün).
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    /**
     * İSTİFADƏÇİ PROVAYDERLƏRİ (User Providers)
     *
     * Provider Laravel-ə deyir: "İstifadəçiləri haradan tapmalısan?"
     * Biz Eloquent driver istifadə edirik — yəni UserModel vasitəsilə DB-dən oxuyur.
     *
     * model: UserModel::class — Sanctum və Auth sistemi bu modeli istifadə edir.
     * DDD layihəsində model standart App\Models\User yox,
     * Src\User\Infrastructure\Models\UserModel-dir.
     *
     * connection: UserModel-də $connection = 'user_db' təyin olunub,
     * ona görə auth sistemi avtomatik user_db verilənlər bazasına qoşulur.
     */
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', UserModel::class),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
