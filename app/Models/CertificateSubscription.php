<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
