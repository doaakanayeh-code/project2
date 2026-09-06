<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class SocialiteController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

 public function handleGoogleCallback()
{
    try {
       //ء $googleUser = Socialite::driver('google')->stateless()->user();
         $googleUser = Socialite::driver('google')->user();
        // 1. البحث باستخدام حقل google_id الموجود في الميغريشن عندك
        $user = User::where('google_id', $googleUser->id)->first();

        if (!$user) {
            // 2. إذا لم يوجد، نبحث عن طريق الإيميل
            $user = User::where('email', $googleUser->email)->first();

            if (!$user) {
                // 3. إنشاء مستخدم جديد (تم حذف سطر الـ google_token لتفادي مشكلة الطول)
                $user = User::create([
                    'username'     => $googleUser->name, 
                    'email'        => $googleUser->email,
                    'google_id'    => $googleUser->id,
                    'password'     => Hash::make(uniqid()), // كلمة مرور عشوائية
                     'id_img_front' => null,
                    'id_img_back'  => null,
                    'phone'        => null,
                    'role'         => null,
                    'status'       => 'approved', // تفعيل تلقائي بما أنه مسجل بجوجل وموثق
                ]);
            } else {
                // 4. إذا الحساب موجود بالإيميل، نحدث حقل المعرف فقط
                $user->update([
                    'google_id' => $googleUser->id,
                ]);
            }
        }

        // توليد التوكن الخاص بـ Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // التوجيه للفرونت إند
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        return redirect($frontendUrl . "/google-callback?token=" . $token);

    } catch (\Exception $e) {
        \Log::error('Google Error: ' . $e->getMessage());
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        return redirect($frontendUrl . "/login?error=auth_failed");
    }
}
}
