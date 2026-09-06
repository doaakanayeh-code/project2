<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, $id, $hash): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'المستخدم غير موجود.'], 404);
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'رابط التفعيل غير صالح.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            // ✅ حتى بحالة إنو مفعّل مسبقاً، منجدد توكن ومنرجعه
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'already_verified',
                'message' => 'تم تفعيل الحساب مسبقاً.',
                'token' => $token,
            ], 200);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // ✅ هون منولّد التوكن بعد التفعيل الناجح
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'تم تفعيل حسابك بنجاح!',
            'token' => $token,
        ], 200);
    }
}