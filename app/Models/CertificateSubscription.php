<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property int $certificate_order_id
 * @property string $square_subscription_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $next_billing_date
 * @property string $billing_interval
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CertificateOrder $order
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateSubscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateSubscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateSubscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateSubscription whereBillingInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateSubscription whereCertificateOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateSubscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateSubscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateSubscription whereNextBillingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateSubscription whereSquareSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateSubscription whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateSubscription whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CertificateSubscription whereUserId($value)
 * @mixin \Eloquent
 */
class CertificateSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'certificate_order_id',
        'square_subscription_id',
        'status', // active, paused, cancelled
        'next_billing_date',
        'billing_interval' // monthly, yearly
    ];

    protected $casts = [
        'next_billing_date' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(CertificateOrder::class, 'certificate_order_id');
    }
}
