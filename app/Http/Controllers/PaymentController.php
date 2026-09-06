<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventItem;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\FcmNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PaymentController extends Controller
{
    /**
     * نقطة الدخول الموحدة للدفع (يدعم cash, wallet, stripe)
     */
    public function pay(Request $request, $itemId)
    {
        $item = EventItem::findOrFail($itemId);
        $request->merge(['event_id' => $item->event_id]);
        return match ($request->payment_method) {
            'cash'   => $this->payWithCash($request),
            'stripe' => $this->payEvent($request),
            'wallet' => $this->payWallet($request),
        };
    }

    public function payweb(Request $request)
    {
        $request->validate([
            'event_id'       => 'required|exists:events,id',
            'payment_method' => 'required|in:cash,wallet,stripe',
        ]);

        \Log::info('Payment dispatch started', [
            'event_id'       => $request->event_id,
            'payment_method' => $request->payment_method,
            'user_id'        => Auth::id(),
        ]);

        try {
            $response = match ($request->payment_method) {
                'cash'   => $this->payWithCash($request),
                'stripe' => $this->newpayEvent($request),
                'wallet' => $this->payWallet($request),
            };

            \Log::info('Payment dispatch finished', [
                'event_id'       => $request->event_id,
                'payment_method' => $request->payment_method,
                'status_code'    => $response->getStatusCode(),
            ]);

            return $response;
        } catch (\Throwable $e) {
            \Log::error('Payment dispatch failed: ' . $e->getMessage(), [
                'event_id'       => $request->event_id,
                'payment_method' => $request->payment_method,
                'trace'          => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'حصل خطأ أثناء معالجة الدفع.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

      public function generateVerificationTokenIfMissing(EventItem $item): void
    {
        if (! $item->verification_token) {
            $item->update([
                'verification_token' => Str::random(40),
            ]);
        }
    }

    /**
     * الدفع النقدي (Cash)
     */
    public function payWithCash(Request $request)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
        ]);

        return DB::transaction(function () use ($request) {

            // ✅ التعديل: قبول status = approved أو confirmed
            $event = Event::with(['eventItems' => function ($q) {
                $q->whereIn('status', ['approved', 'confirmed'])
                    ->whereNotIn('payment_status', ['paid', 'pending_cash']);
            }])->findOrFail($request->event_id);

            if ($event->user_id != Auth::id()) {
                return response()->json(['message' => __('unauthorized')], 403);
            }

            if ($event->payment_status == 'paid' || $event->payment_status == 'pending_cash') {
                return response()->json(['message' => __('event_already_covered')], 422);
            }

            if ($event->eventItems->isEmpty()) {
                return response()->json(['message' => __('no_services_pending')], 422);
            }

            $total           = $event->eventItems->sum('price');
            $referenceNumber = 'CASH-' . time() . '-' . uniqid();

            foreach ($event->eventItems as $item) {

                $commissionPercentage = config('services.platform_commission', 10.00);
                $adminCommission      = round($item->price * ($commissionPercentage / 100), 2);
                $providerAmount       = round($item->price - $adminCommission, 2);

                Transaction::create([
                    'user_id'               => Auth::id(),
                    'event_item_id'         => $item->id,
                    'amount'                => $item->price,
                    'admin_commission'      => $adminCommission,
                    'provider_amount'       => $providerAmount,
                    'commission_percentage' => $commissionPercentage,
                    'payment_method'        => 'cash',
                    'reference_number'      => $referenceNumber,
                    'status'                => 'pending',
                    'type'                  => 'payment',
                ]);

                $item->update([
                    'payment_method' => 'cash',
                    'payment_status' => 'pending_cash',
                    'transaction_id' => $referenceNumber,
                    'qr_token'       => Str::uuid()->toString(),
                ]);
            }

            $event->update([
                'payment_status' => 'pending_cash',
            ]);

            $userId = $event->user_id ?? null;
            if ($userId) {
                app(FcmNotificationService::class)->sendPushNotification(
                    $userId,
                    __('notify_cash_title'),
                    __('notify_cash_body') . $event->name
                );
            }

            return response()->json([
                'message'        => __('cash_payment_processed'),
                'event_id'       => $event->id,
                'total_amount'   => $total,
                'payment_status' => 'pending_cash',
            ], 200);
        });
    }

    /**
     * ✅ WEBHOOK - يستقبل أحداث Stripe (مع Logging كامل)
     */
    public function stripeWebhook(Request $request)
    {
        \Log::info('🔔 ===== STRIPE WEBHOOK CALLED =====');
        \Log::info('📥 Full URL: ' . $request->fullUrl());
        \Log::info('📥 Method: ' . $request->method());
        \Log::info('📥 Headers: ' . json_encode($request->headers->all(), JSON_PRETTY_PRINT));
        \Log::info('📥 Payload: ' . $request->getContent());

        $endpointSecret = config('services.stripe.webhook_secret');
        $payload        = $request->getContent();
        $signature      = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $endpointSecret);
        } catch (\Throwable $e) {
            \Log::error('❌ Webhook signature failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }

        \Log::info('✅ Webhook verified. Type: ' . $event->type);

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                \Log::info('💳 PaymentIntent succeeded: ' . $paymentIntent->id);
                $this->handlePaymentIntentSucceeded($paymentIntent);
                break;

            case 'checkout.session.completed':
                $session = $event->data->object;
                \Log::info('🛒 Checkout session completed: ' . $session->id);
                \Log::info('📦 Metadata: ' . json_encode($session->metadata));

                $metadataType = $session->metadata->type ?? null;

                // ===== شحن المحفظة (Wallet Deposit) =====
                if ($metadataType === 'wallet_deposit') {
                    $walletTxId = $session->metadata->wallet_transaction_id ?? null;
                    \Log::info('💰 Wallet TX ID: ' . $walletTxId);

                    if ($walletTxId) {
                        try {
                            DB::transaction(function () use ($walletTxId, $session) {
                                $walletTx = WalletTransaction::where('id', $walletTxId)->first();

                                if ($walletTx && $walletTx->status === WalletTransaction::STATUS_PENDING) {
                                    $walletTx->update([
                                        'status'         => WalletTransaction::STATUS_COMPLETED,
                                        'transaction_id' => $session->payment_intent,
                                    ]);
                                    \Log::info('✅ Status updated to completed');

                                    $wallet = Wallet::find($walletTx->wallet_id);
                                    if ($wallet) {
                                        $wallet->increment('balance', $walletTx->amount);
                                        \Log::info('✅ Balance updated. New balance: ' . $wallet->balance);
                                    } else {
                                        \Log::error('❌ Wallet not found: ' . $walletTx->wallet_id);
                                    }
                                } else {
                                    \Log::warning('⚠️ Transaction not found or not pending');
                                }
                            });
                        } catch (\Exception $e) {
                            \Log::error('❌ Wallet deposit webhook error: ' . $e->getMessage());
                            return response()->json(['message' => 'Error processing deposit'], 500);
                        }
                    } else {
                        \Log::warning('⚠️ No wallet_transaction_id in metadata');
                    }
                    break;
                }

                // ===== دفع الفعالية (Checkout) =====
                $this->handleCheckoutCompleted($session);
                break;

            case 'checkout.session.expired':
                $session      = $event->data->object;
                $metadataType = $session->metadata->type ?? null;

                if ($metadataType === 'wallet_deposit') {
                    $walletTxId = $session->metadata->wallet_transaction_id ?? null;
                    if ($walletTxId) {
                        $walletTx = WalletTransaction::find($walletTxId);
                        if ($walletTx && $walletTx->status === WalletTransaction::STATUS_PENDING) {
                            $walletTx->update(['status' => WalletTransaction::STATUS_FAILED]);
                            \Log::info('⏰ Wallet deposit expired: ' . $walletTxId);
                        }
                    }
                    break;
                }
                $bookingEvent = Event::with('eventItems')->find($session->client_reference_id);
                if ($bookingEvent) {
                    foreach ($bookingEvent->eventItems as $item) {
                        if ($item->payment_status == 'pending') {
                            $item->update(['payment_status' => 'expired']);
                        }
                    }
                }
                break;
        }

        return response()->json(['success' => true]);
    }

    /**
     * معالجة PaymentIntent الناجح (دفع من التطبيق)
     */
  private function handlePaymentIntentSucceeded($paymentIntent)
{
    $eventId = $paymentIntent->metadata->event_id ?? null;
    if (!$eventId) return;

    DB::transaction(function () use ($paymentIntent, $eventId) {
        $bookingEvent = Event::with(['eventItems.providerService'])->findOrFail($eventId);

        // 👇 جلب/قفل محفظة الأدمن مرة واحدة قبل اللوب
        $adminUser = \App\Models\User::where('role', 'admin')->first();
        if (!$adminUser) {
            throw new \Exception(__('admin_user_missing'));
        }
        $adminWallet = Wallet::firstOrCreate(
            ['user_id' => $adminUser->id],
            ['balance' => 0]
        );
        $adminWallet = Wallet::where('id', $adminWallet->id)->lockForUpdate()->first();

        foreach ($bookingEvent->eventItems as $item) {
            if ($item->payment_status == 'paid') continue;

            $amount = round($item->price, 2);
            $commissionPercent = config('services.platform_commission', 10);
            $adminCommission = round($amount * ($commissionPercent / 100), 2);
            $providerAmount = round($amount - $adminCommission, 2);

            $item->update([
                'payment_status' => 'paid',
                'payment_method' => 'stripe',
                'transaction_id' => $paymentIntent->id,
                'qr_token'       => Str::uuid()->toString(),
                'status'         => 'completed',
            ]);

            $transaction = Transaction::create([
                'user_id' => $bookingEvent->user_id,
                'event_item_id' => $item->id,
                'amount' => $amount,
                'payment_method' => 'stripe',
                'reference_number' => $paymentIntent->id,
                'status' => 'completed',
                'type' => 'payment',
                'admin_commission' => $adminCommission,
                'provider_amount' => $providerAmount,
                'commission_percentage' => $commissionPercent,
            ]);

            $providerWallet = Wallet::firstOrCreate(
                ['user_id' => $item->providerService->user_id],
                ['balance' => 0]
            );
            $providerWallet = Wallet::where('id', $providerWallet->id)->lockForUpdate()->first();

            $balanceBeforeProvider = $providerWallet->balance;
            $providerWallet->increment('balance', $providerAmount);

            WalletTransaction::create([
                'wallet_id' => $providerWallet->id,
                'event_id' => $bookingEvent->id,
                'event_item_id' => $item->id,
                'transaction_id' => $paymentIntent->id,
                'amount' => $providerAmount,
                'balance_before' => $balanceBeforeProvider,
                'balance_after' => $providerWallet->balance,
                'action_type' => WalletTransaction::ACTION_EARNING,
                'status' => WalletTransaction::STATUS_COMPLETED,
                'payment_method' => 'stripe',
                'description' => 'Earning from booking #' . $item->id,
            ]);

            // 👇 تحويل العمولة فعلياً لمحفظة الأدمن
            $balanceBeforeAdmin = $adminWallet->balance;
            $adminWallet->increment('balance', $adminCommission);

            WalletTransaction::create([
                'wallet_id'      => $adminWallet->id,
                'event_id'       => $bookingEvent->id,
                'event_item_id'  => $item->id,
                'transaction_id' => $paymentIntent->id,
                'amount'         => $adminCommission,
                'balance_before' => $balanceBeforeAdmin,
                'balance_after'  => $adminWallet->balance,
                'action_type'    => 'commission',
                'status'         => WalletTransaction::STATUS_COMPLETED,
                'payment_method' => 'stripe',
                'reference_type' => Transaction::class,
                'reference_id'   => $transaction->id,
                'description'    => 'Platform commission from booking #' . $item->id,
            ]);
        }

        $bookingEvent->update([
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'transaction_id' => $paymentIntent->id,
            'qr_token' => Str::uuid()->toString()
        ]);

        $userId = $bookingEvent->user_id ?? null;
        if ($userId) {
            app(FcmNotificationService::class)->sendPushNotification(
                $userId,
                __('notify_payment_success_title'),
                __('notify_payment_success_body') . ($bookingEvent->name ?? 'الفعالية')
            );
        }
    });
}
    /**
     * معالجة Checkout Session الناجح (دفع من المتصفح)
     */
  private function handleCheckoutCompleted($session)
{
    DB::transaction(function () use ($session) {
        $bookingEvent = Event::with(['eventItems.providerService'])->findOrFail($session->client_reference_id);

        // 👇 جلب/قفل محفظة الأدمن مرة واحدة قبل اللوب
        $adminUser = \App\Models\User::where('role', 'admin')->first();
        if (!$adminUser) {
            throw new \Exception(__('admin_user_missing'));
        }
        $adminWallet = Wallet::firstOrCreate(
            ['user_id' => $adminUser->id],
            ['balance' => 0]
        );
        $adminWallet = Wallet::where('id', $adminWallet->id)->lockForUpdate()->first();

        foreach ($bookingEvent->eventItems as $item) {
            if ($item->payment_status == 'paid') continue;

            $amount = round($item->price, 2);
            $commissionPercent = config('services.platform_commission', 10);
            $adminCommission = round($amount * ($commissionPercent / 100), 2);
            $providerAmount = round($amount - $adminCommission, 2);

            $item->update([
                'payment_status' => 'paid',
                'payment_method' => 'stripe',
                'transaction_id' => $session->payment_intent,
                'qr_token' => Str::uuid()->toString(),
                'status'         => 'completed',
            ]);

            $transaction = Transaction::create([
                'user_id' => $bookingEvent->user_id,
                'event_item_id' => $item->id,
                'amount' => $amount,
                'payment_method' => 'stripe',
                'reference_number' => $session->payment_intent,
                'status' => 'completed',
                'type' => 'payment',
                'admin_commission' => $adminCommission,
                'provider_amount' => $providerAmount,
                'commission_percentage' => $commissionPercent,
            ]);

            $providerWallet = Wallet::firstOrCreate(
                ['user_id' => $item->providerService->user_id],
                ['balance' => 0]
            );
            $providerWallet = Wallet::where('id', $providerWallet->id)->lockForUpdate()->first();

            $balanceBeforeProvider = $providerWallet->balance;
            $providerWallet->increment('balance', $providerAmount);

            WalletTransaction::create([
                'wallet_id' => $providerWallet->id,
                'event_id' => $bookingEvent->id,
                'event_item_id' => $item->id,
                'transaction_id' => $transaction->reference_number,
                'amount' => $providerAmount,
                'balance_before' => $balanceBeforeProvider,
                'balance_after' => $providerWallet->balance,
                'action_type' => WalletTransaction::ACTION_EARNING,
                'status' => WalletTransaction::STATUS_COMPLETED,
                'payment_method' => 'stripe',
                'description' => 'Earning from booking #' . $item->id,
            ]);

            // 👇 تحويل العمولة فعلياً لمحفظة الأدمن
            $balanceBeforeAdmin = $adminWallet->balance;
            $adminWallet->increment('balance', $adminCommission);

            WalletTransaction::create([
                'wallet_id'      => $adminWallet->id,
                'event_id'       => $bookingEvent->id,
                'event_item_id'  => $item->id,
                'transaction_id' => $transaction->reference_number,
                'amount'         => $adminCommission,
                'balance_before' => $balanceBeforeAdmin,
                'balance_after'  => $adminWallet->balance,
                'action_type'    => 'commission',
                'status'         => WalletTransaction::STATUS_COMPLETED,
                'payment_method' => 'stripe',
                'reference_type' => Transaction::class,
                'reference_id'   => $transaction->id,
                'description'    => 'Platform commission from booking #' . $item->id,
            ]);
        }

        $bookingEvent->update([
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'transaction_id' => $session->payment_intent,
            'qr_token' => Str::uuid()->toString()
        ]);

        $userId = $bookingEvent->user_id ?? null;
        if ($userId) {
            app(FcmNotificationService::class)->sendPushNotification(
                $userId,
                __('notify_payment_success_title'),
                __('notify_payment_success_body') . ($bookingEvent->name ?? 'الفعالية')
            );
        }
    });
}
    /**
     * ✅ الدفع عبر Stripe (Checkout - متصفح) - للتوافق مع الإصدارات القديمة
     */
    public function payEvent(Request $request)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
        ]);

        $event = Event::with([
            'eventItems' => function ($query) {
                // ✅ التعديل: قبول status = approved أو confirmed
                $query->whereIn('status', ['approved', 'confirmed'])
                    ->where('payment_status', 'pending_payment');
            },
        ])->findOrFail($request->event_id);

        if ($event->user_id != Auth::id()) {
            return response()->json(['message' => __('unauthorized')], 403);
        }

        if ($event->payment_status === 'paid') {
            return response()->json(['message' => __('event_already_paid')], 422);
        }

        if ($event->eventItems->isEmpty()) {
            return response()->json(['message' => __('no_bookings_pending')], 422);
        }

        $totalAmount = round($event->eventItems->sum('price'), 2);

        if ($totalAmount <= 0) {
            return response()->json(['message' => __('invalid_total_amount')], 422);
        }

        $event->update(['total_amount' => $totalAmount]);

        Stripe::setApiKey(config('services.stripe.secret'));

        $session = Session::create([
            'payment_method_types' => ['card'],
            'mode'                 => 'payment',
            'customer_email'       => Auth::user()->email,
            'client_reference_id'  => $event->id,
            'metadata'             => [
                'event_id'     => $event->id,
                'user_id'      => Auth::id(),
                'event_name'   => $event->name,
                'total_amount' => $totalAmount,
                'type'         => 'web_checkout',
            ],
            'expires_at'           => now()->addMinutes(30)->timestamp,
            'success_url'          => config('app.frontend_url') . '/profile?payment_status=success',
            'cancel_url'           => config('app.frontend_url') . '/payment-cancel?event_id=' . $event->id,
            'line_items'           => [[
                'price_data' => [
                    'currency'     => 'usd',
                    'product_data' => ['name' => 'Payment for ' . $event->name],
                    'unit_amount'  => (int) round($totalAmount * 100),
                ],
                'quantity'   => 1,
            ]],
        ]);

        return response()->json([
            'message'      => __('stripe_session_created'),
            'checkout_url' => $session->url,
            'session_id'   => $session->id,
            'total_amount' => $totalAmount,
        ], 200);
    }

    /**
     * ✅ الدفع عبر Stripe داخل التطبيق (Mobile) - بدون متصفح
     */
    public function createMobilePaymentIntent(Request $request)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
        ]);

        $event = Event::with([
            'eventItems' => function ($query) {
                // ✅ التعديل: قبول status = approved أو confirmed
                $query->whereIn('status', ['approved', 'confirmed'])
                    ->where('payment_status', 'pending_payment');
            },
        ])->findOrFail($request->event_id);

        if ($event->user_id != Auth::id()) {
            return response()->json(['message' => __('unauthorized')], 403);
        }

        if ($event->payment_status === 'paid') {
            return response()->json(['message' => __('event_already_paid')], 422);
        }

        if ($event->eventItems->isEmpty()) {
            return response()->json(['message' => __('no_bookings_pending')], 422);
        }

        $totalAmount = round($event->eventItems->sum('price'), 2);

        if ($totalAmount <= 0) {
            return response()->json(['message' => __('invalid_total_amount')], 422);
        }

        $event->update(['total_amount' => $totalAmount]);

        Stripe::setApiKey(config('services.stripe.secret'));

        $paymentIntent = PaymentIntent::create([
            'amount'               => (int) round($totalAmount * 100),
            'currency'             => 'usd',
            'metadata'             => [
                'event_id'   => $event->id,
                'user_id'    => Auth::id(),
                'event_name' => $event->name,
                'type'       => 'mobile_payment',
            ],
            'payment_method_types' => ['card'],
        ]);

        return response()->json([
            'client_secret'     => $paymentIntent->client_secret,
            'payment_intent_id' => $paymentIntent->id,
            'amount'            => $totalAmount,
        ], 200);
    }

    /**
     * الدفع عبر المحفظة (Wallet)
     */
  public function payWallet(Request $request)
{
    $request->validate([
        'event_id' => 'required|exists:events,id'
    ]);

    return DB::transaction(function () use ($request) {

        $event = Event::with(['eventItems' => function ($q) {
            $q->whereIn('status', ['approved', 'confirmed'])
                ->where('payment_status', '!=', 'paid');
        }, 'eventItems.providerService'])->findOrFail($request->event_id);

        if ($event->user_id != Auth::id()) {
            return response()->json(['message' => __('unauthorized')], 403);
        }

        if ($event->payment_status == 'paid') {
            return response()->json(['message' => __('event_already_paid')], 422);
        }

        if ($event->eventItems->isEmpty()) {
            return response()->json(['message' => __('no_services_pending')], 422);
        }

        $total = $event->eventItems->sum('price');
        if ($total <= 0) {
            return response()->json(['message' => __('invalid_total_amount')], 422);
        }

        $wallet = Wallet::where('user_id', Auth::id())->lockForUpdate()->first();
        if (!$wallet || $wallet->balance < $total) {
            return response()->json(['message' => __('wallet_balance_insufficient')], 422);
        }

        $balanceBeforeCustomer = $wallet->balance;
        $wallet->balance -= $total;
        $wallet->save();

        $referenceNumber = 'WALL-' . time() . '-' . uniqid();

        WalletTransaction::create([
            'wallet_id'      => $wallet->id,
            'action_type'    => 'payment',
            'event_id'       => $event->id,
            'event_item_id'  => null,
            'payment_method' => 'wallet',
            'amount'         => $total,
            'balance_before' => $balanceBeforeCustomer,
            'balance_after'  => $wallet->balance,
            'reference_id'   => $event->id,
            'description'    => 'دفع كامل الفعالية رقم #' . $event->id,
            'status'         => 'completed'
        ]);

        // 👇 جلب/قفل محفظة الأدمن مرة واحدة قبل اللوب لتفادي تكرار الاستعلام
        $adminUser = \App\Models\User::where('role', 'admin')->first();
        if (!$adminUser) {
            throw new \Exception(__('admin_user_missing'));
        }
        $adminWallet = Wallet::firstOrCreate(
            ['user_id' => $adminUser->id],
            ['balance' => 0]
        );
        $adminWallet = Wallet::where('id', $adminWallet->id)->lockForUpdate()->first();

        foreach ($event->eventItems as $item) {

            $commissionPercentage = config('services.platform_commission', 10.00);
            $adminCommission      = round($item->price * ($commissionPercentage / 100), 2);
            $providerAmount       = round($item->price - $adminCommission, 2);

            $providerWallet = Wallet::firstOrCreate(
                ['user_id' => $item->providerService->user_id],
                ['balance' => 0]
            );
            $providerWallet = Wallet::where('id', $providerWallet->id)->lockForUpdate()->first();

            $balanceBeforeProvider = $providerWallet->balance;
            $providerWallet->increment('balance', $providerAmount);

            $transaction = Transaction::create([
                'user_id'               => Auth::id(),
                'event_item_id'         => $item->id,
                'amount'                => $item->price,
                'admin_commission'      => $adminCommission,
                'provider_amount'       => $providerAmount,
                'commission_percentage' => $commissionPercentage,
                'payment_method'        => 'wallet',
                'reference_number'      => $referenceNumber,
                'status'                => 'completed',
                'type'                  => 'payment',
            ]);

            WalletTransaction::create([
                'wallet_id'      => $providerWallet->id,
                'action_type'    => 'earning',
                'event_id'       => $event->id,
                'event_item_id'  => $item->id,
                'payment_method' => 'wallet',
                'amount'         => $providerAmount,
                'balance_before' => $balanceBeforeProvider,
                'balance_after'  => $providerWallet->balance,
                'description'    => 'أرباح الخدمة رقم #' . $item->id . ' من الفعالية ' . $event->id,
                'status'         => 'completed'
            ]);

            // 👇 تحويل العمولة فعلياً لمحفظة الأدمن
            $balanceBeforeAdmin = $adminWallet->balance;
            $adminWallet->increment('balance', $adminCommission);

            WalletTransaction::create([
                'wallet_id'      => $adminWallet->id,
                'action_type'    => 'commission',
                'event_id'       => $event->id,
                'event_item_id'  => $item->id,
                'payment_method' => 'wallet',
                'amount'         => $adminCommission,
                'balance_before' => $balanceBeforeAdmin,
                'balance_after'  => $adminWallet->balance,
                'reference_type' => Transaction::class,
                'reference_id'   => $transaction->id,
                'description'    => 'عمولة المنصة من الخدمة رقم #' . $item->id . ' - الفعالية ' . $event->id,
                'status'         => 'completed'
            ]);

            $item->update([
                'payment_status' => 'paid',
                'payment_method' => 'wallet',
                'transaction_id' => $referenceNumber,
                'status'         => 'completed',
                'qr_token'       => Str::uuid()->toString(),
            ]);
        }

        $event->update(['payment_status' => 'paid']);

        $userId = $event->user_id ?? null;
        if ($userId) {
            app(FcmNotificationService::class)->sendPushNotification(
                $userId,
                __('notify_wallet_title'),
                __('notify_wallet_body') . $event->name
            );
        }

        return response()->json([
            'message'        => __('wallet_payment_success'),
            'wallet_balance' => $wallet->balance,
            'event_id'       => $event->id,
            'paid_amount'    => $total
        ]);
    });
}
    /**
     * استرجاع المبلغ (Refund)
     */
   public function refund(EventItem $item, float $penaltyRate = 0.00)
{
    try {
        return DB::transaction(function () use ($item, $penaltyRate) {

            $item->load(['event', 'providerService']);

            if ($item->payment_status === 'refunded') {
                throw new \Exception(__('already_refunded'));
            }
            if ($item->payment_status !== 'paid') {
                throw new \Exception(__('not_paid_refund_error'));
            }

            $transaction = Transaction::where('event_item_id', $item->id)
                ->where('type', 'payment')
                ->where('status', 'completed')
                ->first();

            if (!$transaction) {
                throw new \Exception(__('original_transaction_missing'));
            }

            $providerId = $item->providerService->user_id;
            $providerWallet = Wallet::where('user_id', $providerId)->lockForUpdate()->first();
            if (!$providerWallet) {
                throw new \Exception(__('provider_wallet_missing'));
            }

            // 👇 جلب محفظة الأدمن وقفلها
            $adminUser = \App\Models\User::where('role', 'admin')->first();
            if (!$adminUser) {
                throw new \Exception(__('admin_user_missing'));
            }
            $adminWallet = Wallet::where('user_id', $adminUser->id)->lockForUpdate()->first();
            if (!$adminWallet) {
                throw new \Exception(__('admin_wallet_missing'));
            }

            $totalPaid     = round($transaction->amount, 2);
            $penaltyAmount = round($totalPaid * $penaltyRate, 2);
            $refundAmount  = round($totalPaid - $penaltyAmount, 2);

            $adminPenaltyShare    = round($transaction->admin_commission * $penaltyRate, 2);
            $providerPenaltyShare = round($transaction->provider_amount * $penaltyRate, 2);

            $amountToDeductFromProvider = max(0, round($transaction->provider_amount - $providerPenaltyShare, 2));
            // 👇 المقابل عند الأدمن: كامل عمولته الأصلية ناقص حصته من الغرامة
            $amountToDeductFromAdmin = max(0, round($transaction->admin_commission - $adminPenaltyShare, 2));

            // خصم من محفظة المزود
            if ($amountToDeductFromProvider > 0) {
                if ($providerWallet->balance < $amountToDeductFromProvider) {
                    throw new \Exception(__('insufficient_provider_balance'));
                }

                $balanceBeforeProvider = $providerWallet->balance;
                $providerWallet->decrement('balance', $amountToDeductFromProvider);

                WalletTransaction::create([
                    'wallet_id'      => $providerWallet->id,
                    'event_id'       => $item->event_id,
                    'event_item_id'  => $item->id,
                    'amount'         => $amountToDeductFromProvider,
                    'balance_before' => $balanceBeforeProvider,
                    'balance_after'  => $providerWallet->balance,
                    'action_type'    => WalletTransaction::ACTION_REFUND,
                    'status'         => WalletTransaction::STATUS_COMPLETED,
                    'description'    => "خصم استرجاع خدمة للعميل. غرامة مطبقة: " . ($penaltyRate * 100) . "%",
                ]);
            }

            // 👇 خصم من محفظة الأدمن
            if ($amountToDeductFromAdmin > 0) {
                if ($adminWallet->balance < $amountToDeductFromAdmin) {
                    throw new \Exception(__('insufficient_admin_balance'));
                }

                $balanceBeforeAdmin = $adminWallet->balance;
                $adminWallet->decrement('balance', $amountToDeductFromAdmin);

                WalletTransaction::create([
                    'wallet_id'      => $adminWallet->id,
                    'event_id'       => $item->event_id,
                    'event_item_id'  => $item->id,
                    'amount'         => $amountToDeductFromAdmin,
                    'balance_before' => $balanceBeforeAdmin,
                    'balance_after'  => $adminWallet->balance,
                    'action_type'    => 'refund_commission',
                    'status'         => WalletTransaction::STATUS_COMPLETED,
                    'reference_type' => Transaction::class,
                    'reference_id'   => $transaction->id,
                    'description'    => "خصم استرجاع عمولة المنصة. غرامة مطبقة: " . ($penaltyRate * 100) . "%",
                ]);
            }

            // إرجاع المبلغ للعميل
            if ($refundAmount > 0) {
                if ($transaction->payment_method === 'wallet') {
                    $customerWallet = Wallet::firstOrCreate(
                        ['user_id' => $transaction->user_id],
                        ['balance' => 0]
                    );
                    $customerWallet = Wallet::where('id', $customerWallet->id)->lockForUpdate()->first();

                    $balanceBeforeCustomer = $customerWallet->balance;
                    $customerWallet->increment('balance', $refundAmount);

                    WalletTransaction::create([
                        'wallet_id'      => $customerWallet->id,
                        'event_id'       => $item->event_id,
                        'event_item_id'  => $item->id,
                        'amount'         => $refundAmount,
                        'balance_before' => $balanceBeforeCustomer,
                        'balance_after'  => $customerWallet->balance,
                        'action_type'    => WalletTransaction::ACTION_DEPOSIT,
                        'status'         => WalletTransaction::STATUS_COMPLETED,
                        'payment_method' => 'wallet',
                        'description'    => "استرجاع مبلغ الحجز الملغى",
                    ]);
                } else {
                    try {
                        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

                        $stripeRefund = $stripe->refunds->create([
                            'payment_intent' => $transaction->reference_number,
                            'amount'         => (int) ($refundAmount * 100),
                        ]);

                        \Log::info('Stripe Refund Successful: ' . $stripeRefund->id);

                    } catch (\Stripe\Exception\ApiErrorException $e) {
                        throw new \Exception(__('stripe_refund_failed') . $e->getMessage());
                    }
                }
            }

            Transaction::create([
                'user_id'          => $transaction->user_id,
                'event_item_id'    => $item->id,
                'amount'           => $refundAmount,
                'payment_method'   => $transaction->payment_method,
                'reference_number' => $transaction->reference_number,
                'admin_commission' => -$adminPenaltyShare, // توثيق: كم رجع الأدمن فعلياً (سالب = خرج من محفظته)
                'provider_amount'  => -$providerPenaltyShare,
                'status'           => 'completed',
                'type'             => 'refund',
            ]);

            $item->update([
                'payment_status' => 'refunded'
            ]);

            return response()->json(['message' => __('refund_success')], 200);
        });

    } catch (\Throwable $e) {
        \Log::error('Refund Error: ' . $e->getMessage());
        return response()->json([
            'message' => __('refund_failed'),
            'error'   => $e->getMessage(),
        ], 500);
    }
}
 public function strip(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Stripe Webhook Hit: ', [
        'headers' => $request->header('Stripe-Signature'),
        'content' => $request->getContent()
    ]);
        $endpointSecret = config('services.stripe.webhook_secret');

        $payload   = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {

            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $endpointSecret
            );

        } catch (\Throwable $e) {

            return response()->json([
                'message' => $e->getMessage(),
            ], 400);

        }

        switch ($event->type) {

            /*
        |--------------------------------------------------------------------------
        | نجاح الدفع
        |--------------------------------------------------------------------------
        */
            case 'checkout.session.completed':

                $session = $event->data->object;

                // 1. تعريف المتغير أولاً لقراءة نوع العملية
                $metadataType = $session->metadata->type ?? null;

                /*
            |--------------------------------------------------------------------------
            | القسم الأول: معالجة شحن المحفظة (منفصل تماماً عن الفعاليات)
            |--------------------------------------------------------------------------
            */
                if ($metadataType === 'wallet_deposit') {
                    $walletTxId = $session->metadata->wallet_transaction_id ?? null;

                    if ($walletTxId) {
                        try {
                            DB::transaction(function () use ($walletTxId, $session) {
                                // جلب حركة المحفظة المعلقة وقفل السجل لمنع التزامن العشوائي
                                $walletTx = WalletTransaction::where('id', $walletTxId)
                                    ->lockForUpdate()
                                    ->first();

                                // التأكد من أن المعاملة ما زالت معلقة (pending) لتجنب التكرار
                                if ($walletTx && $walletTx->status === WalletTransaction::STATUS_PENDING) {

                                    // تحديث حالة المعاملة إلى مكتملة وحفظ المعرف المالي من سترايب
                                    $walletTx->update([
                                        'status'         => WalletTransaction::STATUS_COMPLETED,
                                        'transaction_id' => $session->payment_intent,
                                    ]);

                                    // جلب محفظة العميل المرتبطة بالحركة وقفلها لزيادة الرصيد
                                    $wallet = Wallet::where('id', $walletTx->wallet_id)
                                        ->lockForUpdate()
                                        ->first();

                                    if ($wallet) {
                                        $wallet->increment('balance', $walletTx->amount);
                                    }
                                }
                            });
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Wallet Deposit Error: ' . $e->getMessage());
                            return response()->json(['message' => 'خطأ في تحديث بيانات المحفظة'], 500);
                        }
                    }

                    // هنا الـ break الصحيح للخروج من الـ case بالكامل بعد نجاح الشحن
                    break;
                }

                DB::transaction(function () use ($event) {

                    $session = $event->data->object;

                    $bookingEvent = Event::with(['eventItems.providerService'])
                        ->findOrFail($session->client_reference_id);

                    // 👇 جلب/قفل محفظة الأدمن مرة واحدة
                    $adminUser = \App\Models\User::where('role', 'admin')->first();
                    if (! $adminUser) {
                        throw new \Exception('لا يوجد مستخدم أدمن لاستلام العمولة.');
                    }
                    $adminWallet = Wallet::firstOrCreate(
                        ['user_id' => $adminUser->id],
                        ['balance' => 0]
                    );
                    $adminWallet = Wallet::where('id', $adminWallet->id)->lockForUpdate()->first();

                    foreach ($bookingEvent->eventItems as $item) {

                        if ($item->payment_status == 'paid') {
                            continue;
                        }

                        $amount            = round($item->price, 2);
                        $commissionPercent = config('services.platform_commission', 10);
                        $adminCommission   = round($amount * ($commissionPercent / 100), 2);
                        $providerAmount    = round($amount - $adminCommission, 2);

                        $item->update([
                            'payment_status' => 'paid',
                            'payment_method' => 'stripe',
                            'transaction_id' => $session->payment_intent,
                        ]);
                        $this->generateVerificationTokenIfMissing($item);

                        $transaction = Transaction::create([
                            'user_id'               => $bookingEvent->user_id,
                            'event_item_id'         => $item->id,
                            'amount'                => $amount,
                            'payment_method'        => 'stripe',
                            'reference_number'      => $session->payment_intent,
                            'status'                => 'completed',
                            'type'                  => 'payment',
                            'admin_commission'      => $adminCommission,
                            'provider_amount'       => $providerAmount,
                            'commission_percentage' => $commissionPercent,
                        ]);

                        $providerWallet = Wallet::firstOrCreate(
                            ['user_id' => $item->providerService->user_id],
                            ['balance' => 0]
                        );
                        $providerWallet = Wallet::where('id', $providerWallet->id)->lockForUpdate()->first();

                        $balanceBeforeProvider = $providerWallet->balance;
                        $providerWallet->increment('balance', $providerAmount);

                        WalletTransaction::create([
                            'wallet_id'      => $providerWallet->id,
                            'event_id'       => $bookingEvent->id,
                            'event_item_id'  => $item->id,
                            'transaction_id' => $transaction->reference_number,
                            'amount'         => $providerAmount,
                            'balance_before' => $balanceBeforeProvider,
                            'balance_after'  => $providerWallet->balance,
                            'action_type'    => WalletTransaction::ACTION_EARNING,
                            'status'         => WalletTransaction::STATUS_COMPLETED,
                            'payment_method' => 'stripe',
                            'description'    => 'Earning from booking #' . $item->id,
                        ]);

                        // 👇 الإضافة: عمولة الأدمن
                        $balanceBeforeAdmin = $adminWallet->balance;
                        $adminWallet->increment('balance', $adminCommission);

                        WalletTransaction::create([
                            'wallet_id'      => $adminWallet->id,
                            'event_id'       => $bookingEvent->id,
                            'event_item_id'  => $item->id,
                            'transaction_id' => $transaction->reference_number,
                            'amount'         => $adminCommission,
                            'balance_before' => $balanceBeforeAdmin,
                            'balance_after'  => $adminWallet->balance,
                            'action_type'    => 'commission',
                            'status'         => WalletTransaction::STATUS_COMPLETED,
                            'payment_method' => 'stripe',
                            'reference_type' => Transaction::class,
                            'reference_id'   => $transaction->id,
                            'description'    => 'Platform commission from booking #' . $item->id,
                        ]);
                    }

                    $bookingEvent->update([
                        'payment_status' => 'paid',
                        'payment_method' => 'stripe',
                        'transaction_id' => $session->payment_intent,
                    ]);
                });

                break;

            /*
        |--------------------------------------------------------------------------
        | انتهاء صلاحية الدفع
        |--------------------------------------------------------------------------
        */

            case 'checkout.session.expired':
                $session      = $event->data->object;
                $metadataType = $session->metadata->type ?? null;

                if ($metadataType === 'wallet_deposit') {
                    // ... نفس الكود الموجود
                    break;
                }

                $bookingEvent = Event::with('eventItems')->find($session->client_reference_id);

                if ($bookingEvent) {
                    DB::transaction(function () use ($bookingEvent) {
                        foreach ($bookingEvent->eventItems as $item) {
                            if ($item->payment_status == 'pending') {
                                $item->update(['payment_status' => 'expired']);
                            }
                        }
                        if ($bookingEvent->payment_status == 'pending') {
                            $bookingEvent->update(['payment_status' => 'expired']); // 👈 مهم
                        }
                    });
                }
                break;

        }

        return response()->json([
            'success' => true,
        ]);
    }

     public function newpayEvent(Request $request)
    {
        $request->validate(['event_id' => 'required|exists:events,id']);

        return DB::transaction(function () use ($request) {

            $event = Event::with(['eventItems' => function ($query) {
                $query->where('status', 'approved')
                    ->whereNotIn('payment_status', ['paid', 'pending_cash', 'pending', 'refunded', 'expired']);
            }])->lockForUpdate()->findOrFail($request->event_id);

            if ($event->user_id != Auth::id()) {
                return response()->json(['message' => 'غير مصرح.'], 403);
            }

            if (in_array($event->payment_status, ['paid', 'pending_cash', 'pending'])) {
                return response()->json(['message' => 'هذه الفعالية مدفوعة مسبقاً أو قيد المعالجة بطريقة دفع أخرى.'], 422);
            }

            if ($event->eventItems->isEmpty()) {
                return response()->json(['message' => 'لا توجد حجوزات معتمدة تحتاج للدفع.'], 422);
            }

            $totalAmount = round($event->eventItems->sum('price'), 2);
            if ($totalAmount <= 0) {
                return response()->json(['message' => 'إجمالي الفعالية غير صالح للدفع.'], 422);
            }

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'mode'                 => 'payment',
                'customer_email'       => Auth::user()->email,
                'client_reference_id'  => $event->id,
                'metadata'             => [
                    'event_id'     => $event->id,
                    'user_id'      => Auth::id(),
                    'event_name'   => $event->name,
                    'total_amount' => $totalAmount,
                ],
                'expires_at'           => now()->addMinutes(30)->timestamp,
                'success_url'          => config('app.frontend_url') . '/profile?payment_status=success',
                'cancel_url'           => config('app.frontend_url') . '/payment-cancel?event_id=' . $event->id,
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => 'usd',
                        'product_data' => ['name' => 'Payment for ' . $event->name],
                        'unit_amount'  => (int) round($totalAmount * 100),
                    ],
                    'quantity'   => 1,
                ]],
            ]);

            // 👈 هون الإضافة الأساسية: قفل الحالة فور إنشاء الجلسة
            $event->eventItems()->update([
                'payment_status' => 'pending',
                'payment_method' => 'stripe',
            ]);

            $event->update([
                'total_amount'   => $totalAmount,
                'payment_status' => 'pending',
                'payment_method' => 'stripe',
                'transaction_id' => $session->id,
            ]);

            return response()->json([
                'message'      => 'Stripe Checkout Session Created.',
                'checkout_url' => $session->url,
                'session_id'   => $session->id,
                'total_amount' => $totalAmount,
            ], 200);
        });
    }


}
