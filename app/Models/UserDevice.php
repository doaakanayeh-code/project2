<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    // السماح بالتعبئة الجماعية لهذه الحقول
    protected $fillable = [
        'user_id',
        'device_token', // التوكن الخاص بـ FCM
        'device_type',  // (اختياري) لتحديد نوع الجهاز: ios أو android
    ];

    // علاقة الجهاز بالمستخدم (الجهاز يتبع مستخدم واحد)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}