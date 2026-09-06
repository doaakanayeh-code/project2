<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use HasFactory;

    // 👈 تحديد الأعمدة المسموح بتعبئتها في قاعدة البيانات
    protected $fillable = [
        'name',
        'service_type',
    ];

    /**
     * علاقة الميزة بالباقات (عدّة ميزات تنتمي لعدّة باقات)
     * إذا كنتِ تستخدمين الجدول الوسيط package_features
     */
    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_features');
    }
}