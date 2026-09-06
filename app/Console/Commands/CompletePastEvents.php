<?php

namespace App\Console\Commands;
use App\Models\EventItem;
use Illuminate\Console\Command;

class CompletePastEvents extends Command
{
    /** 
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:complete-past-events';

    /** 
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
public function handle()
{
    // الحالات المقبولة التي يُسمح لها بالانتقال إلى "مكتملة" عند انتهاء الوقت
    // (قومي بتعديل هذه المصفوفة حسب مسميات الحالات المعتمدة عندك في السيستم)
    $allowedStatuses = ['approved', 'confirmed', 'paid'];

    // جلب كافة عناصر الحجوزات التي انتهى وقتها وكانت بحالة مقبولة/مدفوعة ولم تكتمل بعد
    $pastItems = EventItem::whereHas('event', function ($query) {
        $query->where('event_date', '<', now()); 
    })
    ->whereIn('status', $allowedStatuses) // شرط أن تكون الحالة الحالية مدفوعة أو موافق عليها
    ->get();

    if ($pastItems->isEmpty()) {
        $this->info('لا توجد حجوزات مؤكدة ومنتهية الصلاحية لتحديثها حالياً.');
        return Command::SUCCESS;
    }

    $count = 0;
    foreach ($pastItems as $item) {
        $item->update([
            'status' => 'completed'
        ]);
        $count++;
    }

    $this->info("تم بنجاح تحديث ({$count}) حجز مؤكد ومقبول إلى حالة مكتملة.");
    
    return Command::SUCCESS;
}
}