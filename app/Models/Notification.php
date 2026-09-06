<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;

    // 1. تحديد الحقول التي يسمح بتعبئتها (Mass Assignment)
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'data',
        'is_read',
    ];

    // 2. ميزة الـ Casting: ضرورية جداً لأن حقل data هو JSON في القاعدة
    // هذا السطر يحول الـ Array تلقائياً إلى JSON عند الحفظ، والعكس عند القراءة
    protected $casts = [
        'data'    => 'array',
        'is_read' => 'boolean',
    ];

    /**
     * علاقة الإشعار بالمستخدم
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

