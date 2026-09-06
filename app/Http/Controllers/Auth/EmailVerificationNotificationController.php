<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request)
    {
        // 1. التحقق من أن الإيميل ممرر في الطلب وبصيغة صحيحة
        $request->validate([
            'identifier' => 'required|email'
        ]);

        // 2. البحث عن المستخدم في الداتابيز عن طريق الإيميل
        $user = User::where('email', $request->identifier)->first();

        // 3. إذا لم نجد المستخدم
        if (!$user) {
            return response()->json([
                'message' => 'لم نتمكن من العثور على هذا المستخدم في النظام.'
            ], 404);
        }

        // 4. إذا كان الحساب مفعلاً مسبقاً (تم تعديل الـ 400 هنا)
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'هذا الحساب مفعل بالفعل.'
            ], 400); 
        }

        // 5. إرسال الرابط فوراً عالايميل
        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'تم إرسال رابط التحقق إلى إيميلك بنجاح.'
        ], 200);
    }
}