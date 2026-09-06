<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ServiceImage extends Model
{
    use HasFactory;

    protected $fillable = ['provider_service_id', 'image_path', 'price'];

    // إضافة هذا السطر ليتم تضمين الرابط في كل استدعاء للـ JSON
    protected $appends = ['image_url'];

    // دالة الـ Accessor التي ستقوم بتوليد الرابط الكامل
    public function getImageUrlAttribute()
    {
        return asset('storage/' . $this->image_path);
    }

    public function providerService()
    {
        return $this->belongsTo(ProviderService::class, 'provider_service_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}