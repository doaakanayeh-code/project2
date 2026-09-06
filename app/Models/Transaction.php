<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'event_item_id', 'amount', 'payment_method', 
        'reference_number','admin_commission','provider_amount', 'status', 'type', 'pdf_url'
    ];

    public function eventItem()
    {
        return $this->belongsTo(EventItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}