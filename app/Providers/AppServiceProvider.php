<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword; // 1. استدعاء كلاس إعادة تعيين كلمة المرور
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $id = $notifiable->getKey();
            $hash = sha1($notifiable->getEmailForVerification());
            
            $frontendUrl = "http://localhost:5173/verify-email/{$id}/{$hash}";

            $temporarySignedUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                ['id' => $id, 'hash' => $hash]
            );

            $queryString = parse_url($temporarySignedUrl, PHP_URL_QUERY);

            return $frontendUrl . '?' . $queryString;
        });


        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return 'http://localhost:5173/reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}