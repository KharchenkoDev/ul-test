<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasUuids;

    protected $hidden = ['idempotency_key'];

    protected $fillable = [
        'idempotency_key',
        'subscriber_id',
        'channel',
        'type',
        'message',
        'status',
        'error_message',
        'sent_at',
        'delivered_at',
    ];

    protected $casts = [
        'channel'      => NotificationChannel::class,
        'type'         => NotificationType::class,
        'status'       => NotificationStatus::class,
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // —— Scopes ————————————————————————————————————————————————

    public function scopeForSubscriber(Builder $query, string $subscriberId): Builder
    {
        return $query->where('subscriber_id', $subscriberId);
    }

    public function scopeTransactional(Builder $query): Builder
    {
        return $query->where('type', NotificationType::Transactional);
    }

    public function scopeMarketing(Builder $query): Builder
    {
        return $query->where('type', NotificationType::Marketing);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', NotificationStatus::Queued);
    }

    // —— State transitions —————————————————————————————————————

    public function markAsSent(): void
    {
        if ($this->status !== NotificationStatus::Queued) {
            return;
        }

        $this->update([
            'status'  => NotificationStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        if ($this->status !== NotificationStatus::Sent) {
            return;
        }

        $this->update([
            'status'       => NotificationStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }

    public function markAsRejected(string $reason = ''): void
    {
        if (!in_array($this->status, [NotificationStatus::Queued, NotificationStatus::Sent], true)) {
            return;
        }

        $this->update([
            'status'        => NotificationStatus::Rejected,
            'error_message' => $reason,
        ]);
    }
}
