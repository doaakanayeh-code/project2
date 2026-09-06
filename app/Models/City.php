<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = ['name'];

// المدينة الواحدة تحتوي على مواقع متعددة
public function locations()
{
    return $this->hasMany(Location::class, 'city_id');
}
}
