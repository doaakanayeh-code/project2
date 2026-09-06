<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [

        'user_id',
        'provider_service_id',
        'event_item_id',
        'rating',
        'comment'

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function providerService()
    {
        return $this->belongsTo(ProviderService::class);
    }

    public function eventItem()
    {
        return $this->belongsTo(EventItem::class);
    }
}