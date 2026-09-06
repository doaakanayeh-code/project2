<?php

namespace App\Http\Controllers;

use App\Models\EventItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Services\FcmNotificationService;

class ProviderApprovalController extends Controller
{
    /**
     * العلاقات المطلوب تحميلها لإعادة تفاصيل الحجز الكاملة في كافة الاستجابات
     */
    protected array $bookingRelations = [
        'event.user:id,username,phone,email',
        'event.location.city',
        'providerService.service',
        'providerService.images',
        'package',
    ];

    /**
     * قبول (موافقة) مزود الخدمة على طلب الحجز
     */
    public function accept($itemId, FcmNotificationService $fcmService)
    {
        DB::beginTransaction();
        try {
            $item = EventItem::with('providerService')->findOrFail($itemId);

            if ($item->providerService->user_id !== Auth::id()) {
                throw new HttpException(403, __('messages.unauthorized_service'));
            }

            if ($item->status !== 'pending') {
                throw new HttpException(422, __('messages.cannot_modify_processed_booking') . ' ' . $item->status);
            }

            $item->update([
                'status' => 'approved',
                'payment_status' => 'pending_payment'
            ]);

            $customer = $item->event->user;
            if ($customer) {
                $fcmService->sendPushNotification(
                    $customer->id,
                    __('messages.booking_accepted_notification'),
                    __('messages.booking_accepted_notification_body')
                );
            }

            DB::commit();

            return response()->json([
                'message' => __('messages.booking_accepted_success'),
                'item' => $item->load($this->bookingRelations)
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return response()->json([
                'message' => $status == 500 ? __('messages.error_accepting_booking') : $e->getMessage(),
                'error' => $e->getMessage()
            ], $status);
        }
    }

    /**
     * رفض مزود الخدمة لطلب الحجز
     */
    public function reject(Request $request, $itemId, FcmNotificationService $fcmService)
    {
        $request->validate([
            'rejection_reason' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            $item = EventItem::with(['providerService', 'event'])->findOrFail($itemId);

            if ($item->providerService->user_id !== Auth::id()) {
                throw new HttpException(403, __('messages.unauthorized_reject'));
            }

            if ($item->status !== 'pending') {
                throw new HttpException(422, __('messages.cannot_reject_processed_booking'));
            }

            $item->update([
                'status' => 'rejected',
                'payment_status' => 'failed',
            ]);

            $event = $item->event;
            $event->decrement('total_amount', $item->price);

            $customer = $event->user;
            $reason = $request->input('rejection_reason', __('messages.no_reason_provided'));

            if ($customer) {
                $fcmService->sendPushNotification(
                    $customer->id,
                    __('messages.booking_rejected_notification'),
                    __('messages.booking_rejected_notification_body') . ' ' . $reason
                );
            }

            DB::commit();

            return response()->json([
                'message' => __('messages.booking_rejected_success'),
                'item' => $item->load($this->bookingRelations)
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return response()->json([
                'message' => $status == 500 ? __('messages.error_rejecting_booking') : $e->getMessage(),
                'error' => $e->getMessage()
            ], $status);
        }
    }

    /**
     * عرض قائمة الحجوزات الخاصة بالمزود
     */
    public function index(Request $request)
    {
        $providerId = Auth::id();

        $query = EventItem::query()
            ->with($this->bookingRelations)
            ->whereHas('providerService', function ($q) use ($providerId) {
                $q->where('user_id', $providerId);
            });

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'status' => true,
            'data'   => $bookings,
        ]);
    }

    /**
     * موافقة مزود الخدمة على "التعديل المقترح" من قِبل العميل
     */
    public function acceptModification($itemId, FcmNotificationService $fcmService)
    {
        DB::beginTransaction();
        try {
            $item = EventItem::with(['providerService', 'event.user'])->findOrFail($itemId);

            if ($item->providerService->user_id !== Auth::id()) {
                throw new HttpException(403, __('messages.unauthorized_action'));
            }

            if ($item->status !== 'pending_modification') {
                throw new HttpException(422, __('messages.no_pending_modification'));
            }

            $item->update([
                'status' => 'approved'
            ]);

            $customer = $item->event->user;
            if ($customer) {
                $fcmService->sendPushNotification(
                    $customer->id,
                    __('messages.modification_accepted_notification'),
                    __('messages.modification_accepted_notification_body')
                );
            }

            DB::commit();

            return response()->json([
                'message' => __('messages.modification_accepted_success'),
                'item' => $item->load($this->bookingRelations)
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return response()->json([
                'message' => $status == 500 ? __('messages.error_accepting_modification') : $e->getMessage(),
                'error' => $e->getMessage()
            ], $status);
        }
    }

    /**
     * رفض مزود الخدمة لـ "التعديل المقترح" من قِبل العميل
     */
    public function rejectModification(Request $request, $itemId, FcmNotificationService $fcmService)
    {
        $request->validate([
            'rejection_reason' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            $item = EventItem::with(['providerService', 'event.user'])->findOrFail($itemId);

            if ($item->providerService->user_id !== Auth::id()) {
                throw new HttpException(403, __('messages.unauthorized_action'));
            }

            if ($item->status !== 'pending_modification') {
                throw new HttpException(422, __('messages.no_pending_modification_to_reject'));
            }

            $item->update([
                'status' => 'rejected'
            ]);

            $customer = $item->event->user;
            $reason = $request->input('rejection_reason', __('messages.no_reason_provided'));

            if ($customer) {
                $fcmService->sendPushNotification(
                    $customer->id,
                    __('messages.modification_rejected_notification'),
                    __('messages.modification_rejected_notification_body') . ' ' . $reason
                );
            }

            DB::commit();

            return response()->json([
                'message' => __('messages.modification_rejected_success'),
                'item' => $item->load($this->bookingRelations)
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return response()->json([
                'message' => $status == 500 ? __('messages.error_rejecting_modification') : $e->getMessage(),
                'error' => $e->getMessage()
            ], $status);
        }
    }

    /**
     * التحقق من الـ QR واكتمال الحجز
     */
    public function verifyQrCode(Request $request, FcmNotificationService $fcmService)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        DB::beginTransaction();
        try {
            $item = EventItem::with(['providerService.service', 'event.user'])
                ->where('qr_token', $request->token)
                ->first();

            if (!$item) {
                throw new HttpException(404, __('messages.invalid_qr_code'));
            }

            if ($item->providerService->user_id !== Auth::id()) {
                throw new HttpException(403, __('messages.unauthorized_qr_scan'));
            }

            if ($item->status === 'completed') {
                throw new HttpException(422, __('messages.booking_already_completed'));
            }

            if (in_array($item->status, ['cancelled', 'rejected'])) {
                throw new HttpException(422, __('messages.cannot_scan_qr_cancelled'));
            }

            $item->update([
                'status' => 'completed'
            ]);

            $customer = $item->event->user;
            if ($customer) {
                $serviceName = $item->providerService->service->name ?? __('messages.reserved_service');

                $fcmService->sendPushNotification(
                    $customer->id,
                    __('messages.qr_verified_notification'),
                    __('messages.qr_verified_notification_body') . " (" . $serviceName . ")."
                );
            }

            DB::commit();

            return response()->json([
                'message' => __('messages.qr_verified_success'),
                'item' => $item->load($this->bookingRelations)
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return response()->json([
                'message' => $status == 500 ? __('messages.error_verifying_qr') : $e->getMessage(),
                'error' => $e->getMessage()
            ], $status);
        }
    }

    /**
     * عرض كافة تفاصيل حجز معين للمزود
     */
    public function show($itemId)
    {
        $providerId = Auth::id();

        $item = EventItem::with($this->bookingRelations)
            ->whereHas('providerService', function ($q) use ($providerId) {
                $q->where('user_id', $providerId);
            })
            ->findOrFail($itemId);

        return response()->json([
            'status' => true,
            'data'   => $item,
        ], 200);
    }
    
         public function pendingModifications()
{
    $items = EventItem::with(['event.user', 'providerService.service'])
        ->whereHas('providerService', fn($q) => $q->where('user_id', Auth::id()))
        ->where('status', 'pending_modification')
        ->latest()
        ->get();

    return response()->json(['data' => $items]);
}

}