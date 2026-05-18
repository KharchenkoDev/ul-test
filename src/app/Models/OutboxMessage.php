<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboxMessage extends Model
{
    public $timestamps = false;

    protected $fillable = ['notification_id'];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
