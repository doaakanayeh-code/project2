<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['city_id', 'address_name', 'latitude', 'longitude'];

// الموقع ينتمي لمدينة واحدة
public function city()
{
    return $this->belongsTo(City::class, 'city_id');
}
}
