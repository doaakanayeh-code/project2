<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventItem;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Log;

class ShowTicketController extends Controller
{
    /**
     * استعراض التذكرة وتوليد الـ QR Code لخدمة الصالة فقط
     */
    public function showTicket($itemId)
    {
        try {
            // 1. جلب بيانات الحجز
            $item = EventItem::with(['providerService.service', 'event'])->findOrFail($itemId);

            // 2. تأمين: التأكد أن العميل هو صاحب الحجز
            if ($item->event->user_id !== Auth::id()) {
                return response()->json([
                    'status'  => false,
                    'message' => ('messages.unauthorized_ticket')
                ], 403);
            }

            // 3. التحقق إذا كانت الخدمة صالة وتوليد الـ QR الخاص بها فقط
            $isHall = ($item->providerService->service->service_type ?? null) === 'hall';
            $qrCodeSvg = null;

            if ($isHall) {
                // نتحقق من وجود الـ token فقط إذا كانت الخدمة صالة
                if (!$item->qr_token) {
                    return response()->json([
                        'status'  => false,
                        'message' => ('messages.ticket_not_issued')
                    ], 400);
                }

                // [التعديل هنا] تحويل الكائن صراحة إلى String أو Base64
                // طريقة الـ String المباشرة (SVG Raw):
                $qrCodeSvg = (string) QrCode::size(250)->generate($item->qr_token);

                // إذا كان فرونت الموبايل يحتاج Base64، استخدمي هذا السطر بدلاً من السابق:
                // $qrCodeSvg = 'data:image/svg+xml;base64,' . base64_encode(QrCode::format('svg')->size(250)->generate($item->qr_token));
            }

            // 4. إرجاع الاستجابة بنجاح لكافة الخدمات
            return response()->json([
                'status'  => true,
                'message' => ('messages.ticket_fetched_success'),
                'data'    => [
                    'booking_details' => [
                        'item_id'      => $item->id,
                        'service_name' => $item->providerService->service->name ?? ('messages.event_service_default'),
                        'price'        => $item->price,
                        'status'       => $item->status,
                    ],
                    'qr_code' => $qrCodeSvg // سيُرجع نص SVG أو Base64 بدلاً من {}
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status'  => false,
                'message' => ('messages.service_not_found')
            ], 404);
        } catch (\Exception $e) {
            Log::error('[SHOW TICKET ERROR] ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => ('messages.general_error')
            ], 500);
        }
    }
    
}