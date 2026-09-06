<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1️⃣ إذا كان الطلب يحتوي على Header الخاص باللغة
        if ($request->hasHeader('X-Locale')) {
            $lang = $request->header('X-Locale');
        }
        // 2️⃣ غير ذلك نأخذ من السيشن لطلبات الـ Web
        elseif (session()->has('locale')) {
            $lang = session('locale');
        }
        // 3️⃣ اللغة الافتراضية للنظام
        else {
            $lang = 'en';
        }

        // 4️⃣ التحقق من أن اللغة من اللغات المدعومة داخل النظام
        if (!in_array($lang, ['en', 'ar'])) {
            $lang = 'en';
        }

        // تطبيق اللغة على النظام
        App::setLocale($lang);

        return $next($request);
    }
}