<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event; // تأكدي أن المسار صحيح
use App\Services\FcmNotificationService; // الـ Service الخاصة بكِ

class SendEventReminders extends Command
{
    protected $signature = 'app:send-event-reminders';
    protected $description = 'إرسال إشعارات تذكير بالفعاليات التي ستبدأ غداً';

    public function handle()
    {
        // 1. تحديد تاريخ الغد
        $tomorrow = now()->addDay()->toDateString();

        // 2. جلب الفعاليات التي موعدها غداً
        $events = Event::whereDate('event_date', $tomorrow)->get();

        if ($events->isEmpty()) {
            $this->info('لا توجد فعاليات غداً لإرسال تذكيرات.');
            return;
        }

        // 3. التكرار عبر الفعاليات لإرسال الإشعارات للمشاركين
        foreach ($events as $event) {
            // نفترض أن عندك علاقة eventItems داخل موديل الـ Event
            foreach ($event->eventItems as $item) {
                
                // التأكد من وجود مستخدم للحجز
                if ($item->user_id) {
                    app(FcmNotificationService::class)->sendPushNotification(
                        $item->user_id,
                        'تذكير بفعاليتك غداً 🗓️',
                        'مرحباً! تذكير: لديك فعالية "' . $event->name . '" غداً. ننتظر حضورك!'
                    );
                }
            }
        }

        $this->info('تم إرسال التذكيرات بنجاح لجميع المستخدمين!');
    }
}