<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CertificateSubscription;
use Illuminate\Auth\Access\HandlesAuthorization;

class CertificateSubscriptionPolicy
{
use HandlesAuthorization;

    /**
     * サブスクリプション表示権限
     */
    public function view(User $user, CertificateSubscription $subscription)
    {
        return $user->id === $subscription->user_id;
    }

    /**
     * サブスクリプション更新権限
     */
    public function update(User $user, CertificateSubscription $subscription)
    {
        return $user->id === $subscription->user_id && $subscription->status === 'active';
    }

    /**
     * サブスクリプションキャンセル権限
     */
    public function cancel(User $user, CertificateSubscription $subscription)
    {
        return $user->id === $subscription->user_id && 
               in_array($subscription->status, ['active', 'paused']);
    }

    /**
     * サブスクリプション一時停止権限
     */
    public function pause(User $user, CertificateSubscription $subscription)
    {
        return $user->id === $subscription->user_id && $subscription->status === 'active';
    }

    /**
     * サブスクリプション再開権限
     */
    public function resume(User $user, CertificateSubscription $subscription)
    {
        return $user->id === $subscription->user_id && $subscription->status === 'paused';
    }
}
