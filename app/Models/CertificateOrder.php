<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
