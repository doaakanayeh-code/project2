<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ProviderService extends Model
{
    use HasFactory;

    /**
     * الحقول القابلة للتعبئة (Mass Assignment)
     * تم دمج الحقول من الملفين معاً
     */
    protected $fillable = [
        'user_id',
        'name',
        'service_id',
        'location_id',
        'price',
        'rating',          // ✅ متوسط التقييمات (تمت الإضافة)
        'review_count',    // ✅ عدد التقييمات (تمت الإضافة)
        'description',
        'features',
        'video_path',
        'status'
    ];

    /**
     * تحويل أنواع البيانات (تم دمج الكاستينج من الملفين)
     */
    protected $casts = [
        'features'    => 'array',
        'description' => 'array',
        'price'       => 'decimal:2',
        'rating'      => 'decimal:2',
        'review_count' => 'integer',
    ];

    /**
     * الحقول التي تظهر تلقائياً في JSON (يمكن تفعيلها حسب الحاجة)
     */
    // protected $appends = ['review_count'];

    // ============================================================
    // العلاقات (تم دمجها من الملفين)
    // ============================================================

    /**
     * علاقة المزود (المستخدم)
     */
    public function provider()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * نفس العلاقة ولكن باسم user للتوافق مع بعض الكود
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * الخدمة الأساسية (نوع الخدمة)
     */
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * الموقع الجغرافي
     */
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * الصور الخاصة بالخدمة
     */
    public function images()
    {
        return $this->hasMany(ServiceImage::class, 'provider_service_id');
    }

    /**
     * الباقات المرتبطة بهذه الخدمة (علاقة many-to-many)
     */
    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_services', 'provider_service_id', 'package_id');
    }

    /**
     * التقييمات الخاصة بهذه الخدمة (تمت الإضافة من الملف الأول)
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'provider_service_id');
    }

    // ============================================================
    // دوال مساعدة للتقييمات (تمت الإضافة من الملف الأول)
    // ============================================================

    /**
     * ✅ تحديث التقييمات يدوياً (يُستدعى من ReviewController)
     * يقوم بحساب متوسط التقييمات وعددها وتحديثها في جدول provider_services
     */
    public function updateRatings()
    {
        $avg = $this->reviews()->avg('rating') ?? 0;
        $count = $this->reviews()->count();

        $this->update([
            'rating' => round($avg, 2),
            'review_count' => $count,
        ]);

        return $this;
    }

    /**
     * الحصول على متوسط التقييمات (قيمة محسوبة)
     */
    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating') ?? 0;
    }

    /**
     * الحصول على عدد التقييمات (قيمة محسوبة)
     */
    public function getReviewsCountAttribute()
    {
        return $this->reviews()->count();
    }

    // ============================================================
    // نطاقات (Scopes) للاستعلام (تمت الإضافة من الملف الأول)
    // ============================================================

    /**
     * نطاق للخدمات النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * نطاق للخدمات حسب نوع الخدمة (من جدول services)
     */
    public function scopeOfType($query, $type)
    {
        return $query->whereHas('service', function ($q) use ($type) {
            $q->where('service_type', $type);
        });
    }

    /**
     * نطاق للخدمات ذات التقييم الأعلى (ترتيب تنازلي)
     */
    public function scopeHighestRated($query)
    {
        return $query->orderBy('rating', 'desc');
    }

    /**
     * التحقق من نشاط الخدمة
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function videos()
{
    return $this->hasMany(ProviderServiceVideo::class);
}
}