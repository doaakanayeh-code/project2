<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureStripeWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        // يمكنكِ وضع أي شرط هنا، مثلاً التأكد من وجود هيدر معين
        if (!$request->hasHeader('Stripe-Signature')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}