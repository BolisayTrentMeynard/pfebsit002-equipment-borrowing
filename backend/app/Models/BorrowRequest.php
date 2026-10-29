<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BorrowRequest extends Model
{
    public const PENDING = 'PENDING';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';
    public const RELEASED = 'RELEASED';
    public const RETURNED = 'RETURNED';
    public const CLOSED = 'CLOSED';

    public const MAX_DAYS = 7;

    // Fields set by the server only (status, approver, dates) are not mass assignable.
    protected $fillable = ['reason', 'requested_days'];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'released_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
