<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderNotification extends Model
{
    public const PENDING = 'PENDING';
    public const SENT = 'SENT';
    public const SENT_AFTER_RETRY = 'SENT_AFTER_RETRY';
    public const FAILED_NEEDS_HUMAN = 'FAILED_NEEDS_HUMAN';
    public const HANDLED_MANUALLY = 'HANDLED_MANUALLY';

    public const TYPE_REMINDER = 'reminder';
    public const TYPE_OVERDUE = 'overdue';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'handled_at' => 'datetime'];
    }

    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
