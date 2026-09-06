<?php


namespace App\Console\Commands;

use App\Models\EventItem;
use Illuminate\Console\Command;

class CancelUnpaidBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cancel-unpaid-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */

// public function handle()
// {
//     // حساب المهلة: 24 ساعة قبل الوقت الحالي
//     $deadline = \Carbon\Carbon::now()->subHours(20);
    
//     // جلب العناصر المعلقة التي مر على إنشائها أكثر من 24 ساعة ولم تُدفع
//     $expiredItems = \App\Models\EventItem::where('status', 'pending_payment')
//                                          ->where('created_at', '<=', $deadline)
//                                          ->get();

//     if ($expiredItems->isEmpty()) {
//         $this->info('لا توجد حجوزات معلقة تجاوزت مهلة الـ 24 ساعة حالياً.');
//         return;
//     }

//     foreach ($expiredItems as $item) {
//         $item->status = 'cancelled';
//         $item->save(); // الحفظ المباشر
        
//         \Illuminate\Support\Facades\Log::info('تم إلغاء الخدمة تلقائياً لانتهاء مهلة الدفع: ' . $item->id);
//     }

//     $this->info('تم إلغاء ' . $expiredItems->count() . ' حجوزات منتهية الصلاحية بنجاح.');
// }


public function handle()
{
    // الحصول على الوقت الحالي كـ كائن Carbon
    $now = \Carbon\Carbon::now();
    
    // جلب الحجوزات
    $expiredItems = \App\Models\EventItem::where('payment_status', 'pending_payment')
         ->get()
         ->filter(function ($item) use ($now) {
             // التأكد من أن created_at ليس فارغاً
             if (!$item->created_at) return false;
             
             // حساب الفرق بالساعات بين وقت الإنشاء والآن
             // diffInHours تحسب الفرق بشكل دقيق بغض النظر عن التوقيت
             return $item->created_at->diffInHours($now) >= 24;
         });

    if ($expiredItems->isEmpty()) {
        $this->info('لا توجد حجوزات تجاوزت الـ 24 ساعة.');
        return;
    }

    foreach ($expiredItems as $item) {
        $item->status = 'cancelled';
        $item->payment_status = 'expired';
        $item->save();
        $this->info('تم إلغاء الحجز رقم: ' . $item->id);
    }
}


}






