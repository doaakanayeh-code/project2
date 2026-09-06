<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    // الحقول المعتمدة في المخطط الجديد لجدول الخدمات العام
    protected $fillable = [
        'name',          // اسم الخدمة العام (مثال: صالة أفراح، تصوير، تنظيم بوفيه)
        'service_type' ,
        'price' // نوع الخدمة
    ];

    /**
     * العلاقة: الخدمة العامة الواحدة يمكن أن يقدمها العديد من المزودين 
     * بتفاصيل أسعار ومواقع مختلفة عبر جدول `provider_services`.
     */
    public function providerServices()
    {
        return $this->hasMany(ProviderService::class, 'service_id');
    }
}