<?php

namespace App\Services;

use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;
use App\Models\UserDevice;

class FcmNotificationService
{
    /**
     * دالة لإرسال إشعار دفع لمستخدم معين على جميع أجهزته المسجلة
     * * @param int $userId
     * @param string $title
     * @param string $body
     * @return bool
     */
    public function sendPushNotification($userId, $title, $body)
    {
        // 1. جلب جميع توكنات الأجهزة الخاصة بهذا المستخدم من الجدول
        $deviceTokens = UserDevice::where('user_id', $userId)
                                  ->pluck('device_token')
                                  ->toArray();

        // إذا لم يكن لدى المستخدم أي جهاز مسجل، نوقف العملية
        if (empty($deviceTokens)) {
            Log::info("[FCM] No devices found for user ID: " . $userId);
            return false;
        }

        try {
            // 2. استدعاء خدمات إشعارات الفايربيس
            $messaging = app('firebase.messaging');

            // 3. بناء محتوى الإشعار (العنوان والنص والبيانات الإضافية)
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData([
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK', // مهمة لمطوري الموبايل
                    'sound' => 'default'
                ]);

            // 4. إرسال الإشعار لجميع الأجهزة دفعة واحدة (Multicast)
            $report = $messaging->sendMulticast($message, $deviceTokens);

            Log::info("[FCM] Notifications sent. Successes: " . $report->successes()->count() . ", Failures: " . $report->failures()->count());

            // 5. تنظيف التوكنات القديمة أو المنتهية الصلاحية تلقائياً
            if ($report->hasFailures()) {
                foreach ($report->failures()->items() as $failure) {
                    if ($failure->error()->getMessage() === 'The registration token is not a valid FCM registration token') {
                        UserDevice::where('device_token', $failure->targetIdentifier())->delete();
                    }
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('[FCM ERROR] Failed to send notification: ' . $e->getMessage());
            return false;
        }
    }
}