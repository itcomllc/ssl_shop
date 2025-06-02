<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CertificateOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class CertificateOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function view(User $user, CertificateOrder $order)
    {
        return $user->id === $order->user_id;
    }

    public function update(User $user, CertificateOrder $order)
    {
        return $user->id === $order->user_id && $order->status === 'issued';
    }

    public function download(User $user, CertificateOrder $order)
    {
        return $user->id === $order->user_id && $order->status === 'issued';
    }
}
