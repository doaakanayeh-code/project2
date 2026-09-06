<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private string $apiUrl = 'http://192.168.2.107:8082/';
    private string $token = '114afab2-8ce6-4371-8ba6-b0e0c3bebc01';

    /**
     * دالة مساعدة لتوحيد صيغة الرقم السوري داخل السيرفس
     */
    private function formatSyrianPhone(string $phone): string
    {
        if (str_starts_with($phone, '0')) {
            return '963' . substr($phone, 1);
        }
        return $phone;
    }

    /**
     * الدالة الأساسية لإرسال الرسالة عبر الـ SMS API
     */
    public function sendOtp($to, $message)
    {
        Log::info('[SMS][sendOtp] Sending OTP.', ['to' => $to, 'api_url' => $this->apiUrl]);

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->post($this->apiUrl, [
                'to' => $to,
                'message' => $message,
            ]);

            Log::info('[SMS][sendOtp] Response.', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('[SMS][sendOtp] Exception.', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * توليد وإرسال OTP دفعة وحدة (تدعم إيميل أو هاتف)
     */
    public function sendOtpToIdentifier(string $identifier): bool
    {
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        if (!$isEmail) {
            $identifier = $this->formatSyrianPhone($identifier);
        }

        $otp = (string) rand(100000, 999999);
        $field = $isEmail ? 'email' : 'phone';

        Otp::create([
            $field       => $identifier,
            'otp'        => $otp,
            'used'       => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        if ($isEmail) {
            Log::info("[OTP EMAIL] Generated for: $identifier | Code: $otp");
            // مستقبلاً: Mail::to($identifier)->send(new OtpMail($otp));
            return true;
        }

        return $this->attemptSendOtp($identifier, $otp);
    }

    public function verifyOtp(string $identifier, string $code): array|string
    {
        if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $identifier = $this->formatSyrianPhone($identifier);
        }

        $otp = Otp::where(function($query) use ($identifier) {
                    $query->where('phone', $identifier)
                          ->orWhere('email', $identifier);
                })
                ->where('otp', $code)
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->latest()
                ->first();

        if (!$otp) {
            return 'OTP is invalid or expired';
        }

        $otp->update(['used' => true]);

        $user = User::where(function($query) use ($identifier) {
                    $query->where('phone', $identifier)
                          ->orWhere('email', $identifier);
                })->first();

        if (!$user) {
            return 'User not found';
        }

        return [
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user'  => $user,
        ];
    }

    public function sendMessage(string $phone, string $message): bool
    {
        return $this->sendOtp($phone, $message);
    }

    public function createOtp(string $identifier): string
    {
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        if (!$isEmail) {
            $identifier = $this->formatSyrianPhone($identifier);
        }

        $otp = (string) rand(100000, 999999);
        $field = $isEmail ? 'email' : 'phone';

        Otp::create([
            $field       => $identifier,
            'otp'        => $otp,
            'used'       => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        Log::channel('single')->info('[OTP] OTP created.', [
            $field => $identifier,
        ]);

        return $otp;
    }

    public function attemptSendOtp(string $phone, string $otp): bool
    {
        $phone = $this->formatSyrianPhone($phone);
        $message = "كود التحقق الخاص بك هو: $otp. يرجى عدم مشاركته مع أي شخص.";
        return $this->sendOtp($phone, $message);
    }
}