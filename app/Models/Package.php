<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = ['user_id', 'name', 'description', 'price', 'status'];

    // الباقة تنتمي لمزود خدمة معين
    public function provider()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // الباقة تحتوي على العديد من خدمات المزود (Many-to-Many)
    public function services()
    {
        return $this->belongsToMany(ProviderService::class, 'package_services', 'package_id', 'provider_service_id');
    }
    public function features()
    {
        return $this->belongsToMany(Feature::class, 'package_features');
    }
    public function images()
    {
        return $this->belongsToMany(
            ServiceImage::class,
            'package_images',
            'package_id',
            'service_image_id'
        );
    }
}