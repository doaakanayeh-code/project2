<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class WalletController extends Controller
{
    /**
     * عرض رصيد المحفظة
     */
    public function balance()
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => Auth::id()],
            ['balance' => 0]
        );

        return response()->json([
            'balance' => $wallet->balance
        ]);
    }

    /**
     * كشف الحساب (المعاملات)
     */
 
public function transactions()
{
    $user = Auth::user();

    $wallet = Wallet::where('user_id', $user->id)->firstOrFail();

    $stripeCount = $wallet->transactions()
        ->where('payment_method', 'stripe')
        ->count();

    $walletCount = $wallet->transactions()
        ->where('payment_method', 'wallet')
        ->count();

    $transactions = $wallet->transactions()
        ->with([
            'event.user:id,username,email,phone',
            'eventItem.providerService' // جلب الخدمة المزودة المرتبطة بالـ EventItem
        ])
        ->latest()
        ->get()
        ->map(function ($transaction) {
            $payer = $transaction->event->user ?? null;
            $eventItem = $transaction->eventItem;

            // استخراج اسم الخدمة بحسب مكان تخزين العنوان (عنوان الخدمة أو اسم عنصر الفعالية)
            $serviceName = $eventItem->providerService->title 
                ?? $eventItem->providerService->name 
                ?? $eventItem->title 
                ?? $eventItem->name 
                ?? null;

            return [
               
                'service_name'   => $serviceName,
                'amount'         => $transaction->amount,
                'action_type'    => $transaction->action_type,
                'status'         => $transaction->status,
                'payment_method' => $transaction->payment_method,
                'description'    => $transaction->description,
                'paid_by_user'   => $payer ? [
                    'id'       => $payer->id,
                    'username' => $payer->username ?? 'مستخدم #' . $payer->id,
                    'email'    => $payer->email ?? 'غير محدد',
                    'phone'    => $payer->phone ?? null,
                ] : null,
            ];
        });

    return response()->json([
      
        'wallet' => [
            'id'           => $wallet->id,
            'balance'      => $wallet->balance,
            'stripe_count' => $stripeCount,
            'wallet_count' => $walletCount,
            'total_count'  => $stripeCount + $walletCount,
            'created_at'   => $wallet->created_at,
            'updated_at'   => $wallet->updated_at,
        ],
        'transactions' => $transactions
    ]);
}
    /**
     * شحن المحفظة عبر Stripe
     */
    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $user = Auth::user();
        $amount = round($request->amount, 2);

        // إنشاء معاملة معلقة
        $walletTransaction = DB::transaction(function () use ($user, $amount) {
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0]
            );

            return WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'event_id'       => null,
                'event_item_id'  => null,
                'transaction_id' => null,
                'amount'         => $amount,
                'action_type'    => WalletTransaction::ACTION_DEPOSIT,
                'status'         => WalletTransaction::STATUS_PENDING,
                'payment_method' => 'stripe',
                'description'    => 'شحن رصيد المحفظة عبر Stripe',
            ]);
        });

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $session = Session::create([
                'payment_method_types' => ['card'],
                'mode' => 'payment',
                'metadata' => [
                    'wallet_transaction_id' => $walletTransaction->id,
                    'user_id'              => $user->id,
                    'type'                 => 'wallet_deposit',
                ],
                'success_url' => config('app.frontend_url') . '/wallet-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => config('app.frontend_url') . '/wallet-cancel',
                'line_items'  => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'شحن رصيد المحفظة - Royal Moments',
                        ],
                        'unit_amount' => (int) round($amount * 100),
                    ],
                    'quantity' => 1,
                ]],
            ]);

            // تحديث المعاملة بـ session_id
            $walletTransaction->update([
                'transaction_id' => $session->id
            ]);

            return response()->json([
                'message'      => 'تم إنشاء جلسة الدفع بنجاح',
                'checkout_url' => $session->url,
                'session_id'   => $session->id,
                'amount'       => $amount,
            ], 200);

        } catch (\Exception $e) {
            $walletTransaction->update([
                'status' => WalletTransaction::STATUS_FAILED
            ]);

            Log::error('[WALLET DEPOSIT ERROR] ' . $e->getMessage());

            return response()->json([
                'message' => 'فشل إنشاء جلسة الدفع',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}