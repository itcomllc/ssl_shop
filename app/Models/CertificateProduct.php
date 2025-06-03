<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $gogetssl_product_id
 * @property string $price
 * @property int $validity_period
 * @property int $domain_count
 * @property bool $wildcard_support
 * @property bool $is_active
 * @property array<array-key, mixed>|null $features
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CertificateOrder> $orders
 * @property-read int|null $orders_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct whereDomainCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct whereFeatures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct whereGogetsslProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct whereValidityPeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateProduct whereWildcardSupport($value)
 * @mixin \Eloquent
 */
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
