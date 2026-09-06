<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\CanResetPassword as AuthCanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements MustVerifyEmail, AuthCanResetPassword
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'username',
        'email',
        'phone',
        'id_img_front',
        'id_img_back',
        'password',
        'role',     
        'google_id',
        'google_token',
        'status',
        'avatar',
         // تم إلغاء التعليق وتفعيله لحل مشكلة الـ MassAssignment
    ];

    /**
     * الحقول المخفية عند التحويل لنصوص أو JSON
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * تحويل الحقول والأنواع تلقائياً
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * علاقة المستخدم بالخدمات
     */
    public function services()
    {
        return $this->hasMany(Service::class, 'user_id');
    }

    /**
     * علاقة المستخدم بأجهزته (تفيد في إشعارات الفايربيس)
     */
    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }

    // علاقة المستخدم بالخدمات التي يقدمها (إذا كان دورة مزود خدمة)
    public function providerServices()
    {
        return $this->hasMany(ProviderService::class, 'user_id');
    }

    // علاقة مزود الخدمة بالباقات التي أنشأها
    public function packages()
    {
      return $this->hasMany(Package::class, 'user_id');
    }
    /**
     * علاقة المستخدم بسجل إشعاراته
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }
    protected static function booted()
    {
        // حدث يُنفذ فوراً بعد حفظ المستخدم الجديد في قاعدة البيانات
        static::created(function ($user) {
            
            // تحديد إذا كان المدخل رقم هاتف لكي نخزنه في البروفايل
            $identifier = request()->input('identifier');
            $isPhone = $identifier && !filter_var($identifier, FILTER_VALIDATE_EMAIL);

            $user->profile()->create([
                'phone' => $isPhone ? $identifier : null,
                // يمكنكِ ترك بقية الحقول نال (nullable) مثل الصورة وكلمة المرور ليعدلها من البروفايل لاحقاً
            ]);
        });
    }
 public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}