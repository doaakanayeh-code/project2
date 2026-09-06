<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    // الحقول المسموح بتعبئتها جماعياً
    protected $fillable = [
        'user_id',
        'balance',
    ];

    // ضمان التعامل مع الرصيد كرقَم عشري بدقة في العمليات الحسابية
    protected $casts = [
        'balance' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | العلاقات (Relationships)
    |--------------------------------------------------------------------------
    */

    /**
     * المحفظة تنتمي إلى مستخدم واحد محدد (زبون أو مزود خدمة)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * المحفظة الواحدة تمتلك العديد من المعاملات التاريخية (كشف الحساب)
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id')->latest();
    }
}