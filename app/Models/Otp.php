<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = [
        'phone',
        'email',
        'otp',
        'used',
        'expires_at'
    ];

    /**
     * ضبط كاست التاريخ والـ Boolean لمنع مشاكل التحقق والمنطق الزمني
     */
    protected $casts = [
        'used'       => 'boolean',
        'expires_at' => 'datetime',
    ];
}