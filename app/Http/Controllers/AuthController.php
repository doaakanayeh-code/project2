<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Otp;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * تنسيق رقم الهاتف السوري بإضافة 963 بدلاً من الصفر الأول
     */
    private function formatSyrianPhone($phone)
    {
        if (str_starts_with($phone, '0')) {
            return '963' . substr($phone, 1);
        }
        return $phone;
    }

    /**
     * البحث عن مستخدم باستخدام المعرف (بريد أو هاتف) مع مرونة في تنسيق الهاتف
     */
    private function findUserByIdentifier($identifier)
    {
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        
        if ($isEmail) {
            return User::where('email', $identifier)->first();
        }
        
        // البحث بالرقم كما هو أو بالصيغة المنسقة
        $formatted = $this->formatSyrianPhone($identifier);
        return User::where('phone', $identifier)
                    ->orWhere('phone', $formatted)
                    ->first();
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string' 
        ]);

        $identifier = $request->input('phone');

        $otpService = app(OtpService::class);
        $isSent = $otpService->sendOtp($identifier);

        if (!$isSent) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.failed_send_otp')
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('messages.otp_sent_successfully')
        ]);
    }

    public function register(Request $request, \App\Services\GoogleIdentityService $identityService)
    {
        try {
            $data = $request->validate([
                'username'     => ['required', 'string', 'regex:/^[\p{Arabic}\s]+$/u'],
                'identifier'   => [
                    'required', 
                    'string',
                    function ($attribute, $value, $fail) {
                        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            if (!str_ends_with(strtolower($value), '@gmail.com')) {
                                $fail(__('messages.email_must_be_gmail'));
                            }
                        } else {
                            if (!preg_match('/^(09\d{8}|9639\d{8}|\+9639\d{8})$/', $value)) {
                                $fail(__('messages.invalid_syrian_phone'));
                            }
                        }
                    }
                ],
                'id_img_front' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'id_img_back'  => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'role'         => 'required|in:user,provider',
                'password'     => 'required|string|min:8|confirmed',
            ], [
                'username.regex' => __('messages.username_arabic_only'),
            ]);

            $identifierType = filter_var($data['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

            $exists = User::where($identifierType, $data['identifier'])->exists();
            if ($exists) {
                $errorMessage = $identifierType === 'email' 
                    ? __('messages.email_already_registered') 
                    : __('messages.phone_already_registered');
                    
                return response()->json(['message' => $errorMessage], 422);
            }

            $pathFront = $request->file('id_img_front')->store('ids/front', 'public');
            $pathBack  = $request->file('id_img_back')->store('ids/back', 'public');
 
            $frontImagePath = storage_path(str_replace('/', DIRECTORY_SEPARATOR, 'app/public/' . $pathFront));

            Log::info('جاري إرسال الصورة المعالجة إلى Google OCR من المسار: ' . $frontImagePath);

            $ocrResult = $identityService->verifyId($frontImagePath);

            if (!$ocrResult['success']) {
                Log::error('فشل الـ OCR من طرف سيرفس جوجل:', [
                    'error_message' => $ocrResult['message'] ?? __('messages.unknown_ocr_error'),
                    'image_path'    => $frontImagePath,
                    'input_data'    => $request->except(['password', 'password_confirmation', 'id_img_front', 'id_img_back'])
                ]);

                return response()->json([
                    'message' => __('messages.ocr_read_failed'), 
                    'error'   => $ocrResult['message'] ?? __('messages.unknown_ocr_service_error')
                ], 500);
            }

            $ocrText = $ocrResult['text'];

            $extractedName = '';
            $lines = explode("\n", $ocrText);
            
            foreach ($lines as $index => $line) {
                $cleanLine = trim($line);
                
                if (mb_strpos($cleanLine, 'الاسم') !== false || mb_strpos($cleanLine, 'الاسسيم') !== false || mb_strpos($cleanLine, 'الاسسم') !== false) {
                    $extractedName = $line;
                    break;
                }
                
                if ((mb_strpos($cleanLine, 'الاب') !== false || mb_strpos($cleanLine, 'النسبه') !== false) && $index > 0) {
                    $extractedName = $lines[$index - 1];
                    break;
                }
            }

            if (empty($extractedName)) {
                foreach ($lines as $line) {
                    $cleanLine = trim(preg_replace('/[a-zA-Z\d\W_]+/u', ' ', $line));
                    if (mb_strlen($cleanLine) > 3 && mb_strpos($line, 'الجمهوريه') === false && mb_strpos($line, 'وزاره') === false && mb_strpos($line, 'الداخلية') === false) {
                        $extractedName = $line;
                        break;
                    }
                }
            }
            
            $cleanInputName = $identityService->cleanText($data['username']);
            $cleanOcrName = $identityService->cleanText($extractedName);

            if (strpos($cleanOcrName, ':') !== false) {
                $parts = explode(':', $cleanOcrName);
                $cleanOcrName = end($parts); 
            }

            $stopWords = ['الاسم', 'الاسسيم', 'الاسسمسم', 'الاسسم', 'الاسس', 'النسبه', 'النسسبه', 'اسم', 'الاب', 'ام', 'ونسبه', 'محل', 'وتاريخ', 'الولادة', 'الجمهوريه', 'العربيه', 'السوريه', 'وزاره', 'الداخليه', 'سويريه'];
            foreach ($stopWords as $word) {
                $cleanOcrName = mb_ereg_replace($word, '', $cleanOcrName);
            }

            $cleanOcrName = preg_replace('/[a-zA-Z\d\W_]+/u', ' ', $cleanOcrName);
            $cleanOcrName = trim(preg_replace('/[ \t]+/u', ' ', $cleanOcrName));

            similar_text($cleanInputName, $cleanOcrName, $percent);

            if ($percent < 70) {
                return response()->json([
                    'message'          => __('messages.name_mismatch_with_id'),
                    'match_percent'    => $percent,
                    'input_name_clean' => $cleanInputName, 
                    'ocr_name_clean'   => $cleanOcrName,   
                    'full_ocr_text'    => $lines          
                ], 422);
            }

            $userData = [
                'username'     => $data['username'],
                'id_img_front' => $pathFront,
                'id_img_back'  => $pathBack,
                'role'         => $data['role'],
                'password'     => Hash::make($data['password']),
            ];

            $userData[$identifierType] = $data['identifier'];

            $user = User::create($userData);

            if ($identifierType === 'email') {
                event(new \Illuminate\Auth\Events\Registered($user));
            }
            
            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0.00,
            ]);

            $token = $user->createToken('UserToken')->plainTextToken;

            $chatToken = null;
            try {
                $firebaseAuth = app('firebase.auth');
                $customToken = $firebaseAuth->createCustomToken((string)$user->id);
                $chatToken = $customToken->toString();
            } catch (\Exception $e) {
                Log::error('[FIREBASE REGISTER TOKEN] Failed to create custom token: ' . $e->getMessage());
            }

            return response()->json([
                'user'       => $user,
                'token'      => $token,
                'chat_token' => $chatToken,
                'message'    => __('messages.account_created_successfully')
            ], 201);

        } catch (\Exception $e) {
            Log::critical('خطأ غير متوقع أثناء عملية التسجيل (Exception):', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => __('messages.internal_server_error'),
                'exception_message' => $e->getMessage()
            ], 500);
        }
    }
    
    // ================================================================
    // ✅ دالة login الأصلية
    // ================================================================
    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string', 
            'password'   => 'required|string',
        ], [
            'identifier.required' => __('messages.identifier_required'),
            'password.required'   => __('messages.password_required'),
        ]);

        $identifier = $request->identifier;
        $user = $this->findUserByIdentifier($identifier);

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => __('messages.account_not_found'),
                'data'    => []
            ], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => __('messages.invalid_credentials'),
                'data'    => []
            ], 401);
        }

        $token = $user->createToken('UserToken')->plainTextToken;

        $chatToken = null;
        try {
            $firebaseAuth = app('firebase.auth');
            $customToken = $firebaseAuth->createCustomToken((string)$user->id);
            $chatToken = $customToken->toString();
        } catch (\Exception $e) {
            Log::error('[FIREBASE LOGIN TOKEN] Failed to create custom token: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => true,
            'message' => __('messages.login_success'),
            'data'    => [
                'user'       => $user,
                'token'      => $token,
                'chat_token' => $chatToken 
            ]
        ]);
    }

    // ================================================================
    // ✅ دالة login2 (مكررة لتوافق التوجيه القديم) لحل خطأ undefined method
    // ================================================================
    public function login2(Request $request)
    {
        return $this->login($request);
    }

    public function logout()
    {
        if (Auth::check()) {
            Auth::user()->currentAccessToken()->delete();
            return response()->json([
                'status'  => 1,
                'message' => __('messages.logout_success'),
                'data'    => [],
            ]);
        }

        return response()->json([
            'status'  => 0,
            'message' => __('messages.unauthenticated'),
            'data'    => [],
        ], 401);
    }

    // ================================================================
    // ✅ الدالة المعدلة: showProfile (تمت إضافة profile_image)
    // ================================================================
    public function showProfile()
    {
        $user = Auth::user();

        // جلب الصورة الشخصية من جدول الـ Profile
        $profileImage = null;
        if ($user->profile && $user->profile->image) {
            $profileImage = asset('storage/' . $user->profile->image);
        }

        return response()->json([
            'status'  => 1,
            'message' => __('messages.your_profile'),
            'data'    => [
                'username'      => $user->username,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'id_img_front'  => $user->id_img_front,
                'id_img_back'   => $user->id_img_back,
                'status'        => $user->status,
                'role'          => $user->role,
                'profile_image' => $profileImage, // ✅ تمت الإضافة
            ]
        ]);
    }

      public function verifyOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string', 
            'otp'        => 'required|string',
        ], [
            'identifier.required' => __('messages.identifier_required'),
            'otp.required'        => __('messages.otp_required'),
        ]);
        
        $identifier = $request->identifier;
        if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $identifier = $this->formatSyrianPhone($identifier);
        }

        $result = $this->otpService->verifyOtp($identifier, $request->otp);
        return response()->json($result);
    }

   public function resetPassword(Request $request)
{
    $request->validate([
        'identifier' => 'required|string',
        'password'   => 'required|string|min:8|confirmed',
    ]);

    $identifier = $request->identifier;
    $formattedIdentifier = $identifier;
    if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $formattedIdentifier = $this->formatSyrianPhone($identifier);
    }

    $user = User::where(function($query) use ($identifier, $formattedIdentifier) {
                $query->where('phone', $identifier)
                      ->orWhere('phone', $formattedIdentifier)
                      ->orWhere('email', $identifier);
            })->first();

    if (!$user) {
        return response()->json(['message' => __('messages.phone_not_registered')], 404);
    }

    $user->update([
        'password' => Hash::make($request->password),
    ]);

    return response()->json(['message' => __('messages.password_reset_successfully')]);
}

   public function verifyForgotOtp(Request $request)
{
    $request->validate([
        'identifier' => 'required|string', 
        'otp'        => 'required|string',
    ]);

    $identifier = $request->identifier;
    $formattedIdentifier = $identifier;
    if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $formattedIdentifier = $this->formatSyrianPhone($identifier);
    }

    $otp = Otp::where(function($query) use ($identifier, $formattedIdentifier) {
                $query->where('phone', $identifier)
                      ->orWhere('phone', $formattedIdentifier)
                      ->orWhere('email', $identifier);
            })
            ->where('otp', $request->otp)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

    if (!$otp) {
        return response()->json(['message' => __('messages.Verification_code_is_invalid_or_expired')], 400);
    }

    $otp->update(['used' => true]);

    return response()->json([
        'message' => __('messages.Verification_successful_You_can_now_set_اa_new_password'),
        'verified' => true,
    ]);
}

 public function forgotPassword(Request $request)
{
    $request->validate([
        'identifier' => 'required|string',
    ]);

    $identifier = $request->identifier;
    $formattedIdentifier = $identifier;
    if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $formattedIdentifier = $this->formatSyrianPhone($identifier);
    }

    $user = User::where(function($query) use ($identifier, $formattedIdentifier) {
                $query->where('phone', $identifier)
                      ->orWhere('phone', $formattedIdentifier)
                      ->orWhere('email', $identifier);
            })->first();

    if (!$user) {
        return response()->json(['message' => __('messages.phone_not_registered')], 404);
    }

    try {
        $receiver = $user->phone ?? $user->email;
        $otp = $this->otpService->createOtp($receiver);
        $this->otpService->attemptSendOtp($receiver, $otp);

        return response()->json([
            'success' => true,
            'message' => __('messages.A_verification_code_has_been_sent_to_reset_the_password'),
        ], 200);

    } catch (\Exception $e) {
        Log::error('[FORGOT PASSWORD] Error: '.$e->getMessage());
        return response()->json(['success' => false, 'message' => __('messages.An_unexpected_error_occurred')], 500);
    }
}

   public function resendOtp(Request $request)
{
    $request->validate([
        'identifier' => 'required|string',
    ], [
        'identifier.required' => __('messages.identifier_required'),
    ]);

    $identifier = $request->identifier;
    $formattedIdentifier = $identifier;
    if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $formattedIdentifier = $this->formatSyrianPhone($identifier);
    }

    $user = User::where(function($query) use ($identifier, $formattedIdentifier) {
                $query->where('phone', $identifier)
                      ->orWhere('phone', $formattedIdentifier)
                      ->orWhere('email', $identifier);
            })->first();

    if (!$user) {
        return response()->json([
            'status'  => false,
            'message' => __('messages.account_not_found')
        ], 404);
    }

   try {
    $receiver = $user->phone ?? $user->email;
    $otp = $this->otpService->createOtp($receiver);
    $this->otpService->attemptSendOtp($receiver, $otp);

    return response()->json([
        'status'  => true,
        'message' => __('messages.otp_resent_successfully'), 
    ], 200);

} catch (\Exception $e) {
    Log::error('[RESEND OTP] Error: ' . $e->getMessage());
    return response()->json([
        'status'  => false, 
        'message' => __('messages.An_unexpected_error_occurred')
    ], 500);
}
     catch (\Exception $e) {
        Log::error('[RESEND OTP] Error: ' . $e->getMessage());
        return response()->json([
            'status'  => false, 
            'message' => __('messages.An_unexpected_error_occurred')
        ], 500);
    }}


    public function updateProfile(Request $request)
    {
        $user = Auth::user(); 

        // 1. إضافة التحقق من الصورة الشخصية وكلمة المرور
        $data = $request->validate([
            'username'      => ['sometimes', 'string', 'max:255', 'regex:/^[\p{Arabic}a-zA-Z\s]+$/u'],
            'identifier'    => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) {
                    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        if (!str_ends_with(strtolower($value), '@gmail.com')) {
                            $fail(('messages.email_must_be_gmail'));
                        }
                    } else {
                        if (!preg_match('/^(09\d{8}|9639\d{8}|\+9639\d{8})$/', $value)) {
                            $fail(('messages.invalid_syrian_phone'));
                        }
                    }
                }
            ],
            'password'      => ['sometimes', 'nullable', 'string', 'min:8'],
            'profile_image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ], [
            'username.regex' => ('messages.username_arabic_only_no_numbers'),
        ]);

        // 2. تحديث الاسم
        if ($request->has('username')) {
            $user->username = $data['username'];
        }

        // 3. تحديث كلمة المرور (مع التشفير)
        if ($request->filled('password')) {
            $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
        }

        // 4. تحديث الهاتف أو الإيميل
        if ($request->has('identifier')) {
            $identifier = $request->identifier;
            $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
            $field = $isEmail ? 'email' : 'phone';

            if (!$isEmail) {
                $identifier = $this->formatSyrianPhone($identifier);
            }

            $exists = \App\Models\User::where($field, $identifier)
                          ->where('id', '!=', $user->id)
                          ->exists();

            if ($exists) {
                return response()->json([
                    'status'  => false,
                    'message' => $isEmail ? ('messages.email_used_by_another_account') : ('messages.phone_used_by_another_account')
                ], 422);
            }

            // تفريغ الحقل الآخر إذا تم التبديل بين الهاتف والإيميل
            if ($isEmail) {
                $user->email = $identifier;
                $user->phone = null;
            } else {
                $user->phone = $identifier;
                $user->email = null;
            }
        }

        $user->save();

        // 5. تحديث الصورة الشخصية في جدول Profiles
        $profile = $user->profile ?? new \App\Models\Profile(['user_id' => $user->id]);

        if ($request->hasFile('profile_image')) {
            // حذف الصورة القديمة من السيرفر لتوفير المساحة
            if ($profile->image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->image);
            }
            // حفظ الصورة الجديدة
            $profile->image = $request->file('profile_image')->store('profiles/avatars', 'public');
            $profile->save();
        }

        // 6. إرجاع النتيجة للفرونت إند
        return response()->json([
            'status'  => true,
            'message' => ('messages.profile_updated_successfully'),
            'data'    => [
                'user' => [
                    'id'            => $user->id,
                    'username'      => $user->username,
                    'email'         => $user->email,
                    'phone'         => $user->phone,
                    'profile_image' => $profile->image ? asset('storage/' . $profile->image) : null,
                ]
            ]
        ], 200);
    }
}