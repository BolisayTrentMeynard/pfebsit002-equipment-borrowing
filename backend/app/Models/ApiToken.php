<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    public const LIFETIME_HOURS = 8;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Creates a token for the user and returns the PLAIN text once. Only its hash is stored. */
    public static function issue(User $user): string
    {
        $plain = Str::random(60);
        static::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHours(self::LIFETIME_HOURS),
        ]);

        return $plain;
    }

    public static function findValid(string $plain): ?self
    {
        return static::with('user')
            ->where('token_hash', hash('sha256', $plain))
            ->where('expires_at', '>', now())
            ->first();
    }

    public function touchUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }
}
