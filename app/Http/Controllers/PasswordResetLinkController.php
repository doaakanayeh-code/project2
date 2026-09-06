<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class PasswordResetLinkController extends Controller
{
    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('[PASSWORD RESET LINK] بدء عملية طلب رابط إعادة التعيين', [
            'email' => $request->email,
            'ip'    => $request->ip(),
        ]);

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Log::info('[PASSWORD RESET LINK] تم التحقق من البريد الإلكتروني', [
            'email' => $request->email,
        ]);

        // We will send the password reset link to this user.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        Log::info('[PASSWORD RESET LINK] نتيجة محاولة الإرسال', [
            'status' => $status,
            'email'  => $request->email,
        ]);

        if ($status != Password::RESET_LINK_SENT) {
            Log::error('[PASSWORD RESET LINK] فشل إرسال رابط إعادة التعيين', [
                'status' => $status,
                'email'  => $request->email,
            ]);

            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        Log::info('[PASSWORD RESET LINK] ✅ تم إرسال رابط إعادة التعيين بنجاح', [
            'email' => $request->email,
        ]);

        return response()->json(['status' => __($status)]);
    }
}