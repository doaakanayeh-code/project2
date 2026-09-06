<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    // Action Types
    public const ACTION_DEPOSIT    = 'deposit';
    public const ACTION_WITHDRAW   = 'withdraw';
    public const ACTION_PAYMENT    = 'payment';
    public const ACTION_REFUND     = 'refund';
    public const ACTION_COMMISSION = 'commission';
    public const ACTION_EARNING    = 'earning';

    // Status
    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'wallet_id',
        'event_id',
        'event_item_id',
        'transaction_id',
        'amount',
        'action_type',
        'status',
        'payment_method',
        'description',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id', 'reference_number');
    }

    public function eventItem(): BelongsTo
    {
        return $this->belongsTo(EventItem::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeDeposits($query)
    {
        return $query->where('action_type', self::ACTION_DEPOSIT);
    }

    public function scopePayments($query)
    {
        return $query->where('action_type', self::ACTION_PAYMENT);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isDeposit(): bool
    {
        return $this->action_type === self::ACTION_DEPOSIT;
    }

    public function isPayment(): bool
    {
        return $this->action_type === self::ACTION_PAYMENT;
    }

    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'معلق',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_FAILED    => 'فاشل',
            default                => 'غير معروف',
        };
    }

    public function getActionTextAttribute(): string
    {
        return match ($this->action_type) {
            self::ACTION_DEPOSIT    => 'إيداع',
            self::ACTION_WITHDRAW   => 'سحب',
            self::ACTION_PAYMENT    => 'دفع',
            self::ACTION_REFUND     => 'استرجاع',
            self::ACTION_COMMISSION => 'عمولة',
            self::ACTION_EARNING    => 'أرباح',
            default                 => 'غير معروف',
        };
    }
}