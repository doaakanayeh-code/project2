<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| ProviderServiceVideo
|--------------------------------------------------------------------------
| يمثّل فيديو إضافي مرتبط بخدمة (ProviderService)، بالإضافة للفيديو
| الأساسي المخزّن بعمود video_path داخل provider_services.
|
| ملاحظة: انسخ هاد الملف لمجلد app/Models بمشروعك.
| وبعدها لازم تضيف بملف app/Models/ProviderService.php العلاقة التالية
| (بس ضيفها، ما تحذف شي موجود عندك بالملف):
|
|   public function videos()
|   {
|       return $this->hasMany(ProviderServiceVideo::class);
|   }
*/
class ProviderServiceVideo extends Model
{
    protected $fillable = [
        'provider_service_id',
        'video_path',
        'title',
    ];

    public function providerService()
    {
        return $this->belongsTo(ProviderService::class);
    }
}