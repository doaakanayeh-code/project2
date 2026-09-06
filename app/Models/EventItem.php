<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventItem extends Model
{
    /**
     * الحقول القابلة للتعبئة (Mass Assignment)
     * تم دمج الحقول من الملفين مع تصحيح اسم qr_token
     */
    protected $fillable = [
        'event_id',
        'option',
        'provider_service_id',
        'package_id',
        'price',
        'qr_token',              // ✅ تم التصحيح: qr_token (بدلاً من qr-token)
        'payment_status',
        'status',                // ✅ pending, accepted, completed, cancelled
        'start_time',
        'end_time',
        'proposed_changes',  
       ' delivery_type', 
        'notes', 
         'cancellation_reason',
        'rejection_reason',
         'transaction_id', 
         'cancelled_at',
    ];

    /**
     * تحويل أنواع البيانات
     * تم دمج الكاستينج من الملفين
     */
    protected $casts = [
        'option'           => 'array',
        'proposed_changes' => 'array',
        'price'            => 'decimal:2',
    ];

    /**
     * الحقول المخفية من JSON
     * تم التأكد أن status ليس مخفياً
     */
    protected $hidden = [
        // 'status', // ❌ لا تخفِ status
    ];

    // ===================== العلاقات =====================

    /**
     * العلاقة مع Event (الحدث الرئيسي)
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * العلاقة مع ProviderService (الخدمة المقدمة)
     */
    public function providerService()
    {
        return $this->belongsTo(ProviderService::class);
    }

    /**
     * العلاقة مع Package (الباقة إن وجدت)
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * العلاقة مع Transaction (المعاملة المالية)
     */
    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    /**
     * العلاقة مع Review (التقييم)
     */
    public function review()
    {
        return $this->hasOne(Review::class);
    }

    // ===================== دوال مساعدة =====================

    /**
     * التحقق مما إذا كان الحجز مكتملاً
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * التحقق مما إذا كان الحجز ملغى
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * التحقق مما إذا كان الحجز معلقاً (pending)
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * التحقق مما إذا كان الحجز مؤكداً (confirmed)
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed' || $this->status === 'approved';
    }
    public function user() {
    return $this->belongsTo(\App\Models\User::class);
}


    public function service() {
    return $this->belongsTo(\App\Models\Service::class);
}
}