<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateProduct extends Model
{
    protected $fillable = [
        'name',
        'description', 
        'gogetssl_product_id',
        'price',
        'validity_period', // months
        'domain_count',
        'wildcard_support',
        'is_active',
        'features' // JSON
    ];

    protected $casts = [
        'features' => 'array',
        'wildcard_support' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function orders()
    {
        return $this->hasMany(CertificateOrder::class);
    }
}
