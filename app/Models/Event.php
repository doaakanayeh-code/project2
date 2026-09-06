<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = ['user_id', 'event_date','name',       
    'budget',  'status',  'location_id',
    'type','total_amount' ,'start_time', 
'latitude',    
    'longitude',
            'end_time', ];

    // العلاقة: الفعالية لها عناصر متعددة
    public function eventItems()
    {
        return $this->hasMany(EventItem::class);
    }


    // العلاقة: الفعالية تخص مستخدم واحد
    public function user()
    {
        return $this->belongsTo(User::class);
    }
public function location()
{
    return $this->belongsTo(Location::class, 'location_id');
}
    public function events() { return $this->hasMany(Event::class, 'user_id'); }

    }