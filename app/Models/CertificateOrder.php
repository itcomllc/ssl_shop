<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property int $certificate_product_id
 * @property string|null $square_payment_id
 * @property string|null $gogetssl_order_id
 * @property string $domain_name
 * @property string $status
 * @property string|null $csr
 * @property string|null $private_key
 * @property string|null $certificate_content
 * @property string|null $ca_bundle
 * @property string $total_amount
 * @property string $currency
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string|null $approver_email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CertificateProduct $product
 * @property-read \App\Models\CertificateSubscription|null $subscription
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereApproverEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereCaBundle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereCertificateContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereCertificateProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereCsr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereDomainName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereGogetsslOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder wherePrivateKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereSquarePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateOrder whereUserId($value)
 * @mixin \Eloquent
 */
class CertificateOrder extends Model
{
    protected $fillable = [
        'user_id',
        'certificate_product_id',
        'square_payment_id',
        'gogetssl_order_id',
        'domain_name',
        'status', // pending, processing, issued, failed, expired
        'csr',
        'private_key',
        'certificate_content',
        'ca_bundle',
        'total_amount',
        'currency',
        'expires_at',
        'approver_email'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(CertificateProduct::class, 'certificate_product_id');
    }

    public function subscription()
    {
        return $this->hasOne(CertificateSubscription::class);
    }
}
