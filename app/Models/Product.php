<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'title', 'sku', 'description'
    ];

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variantPrices()
    {
        return $this->hasMany(ProductVariantPrice::class);
    }

    public function productVariants()
    {
        return $this->hasMany(ProductVariant::class);
    }

}
