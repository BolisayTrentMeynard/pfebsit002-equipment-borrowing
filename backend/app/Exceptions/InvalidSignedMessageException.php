<?php

namespace App\Exceptions;

use RuntimeException;

/** A signed message was rejected. `reason` is a short code that is safe to log and return. */
class InvalidSignedMessageException extends RuntimeException
{
    public const MALFORMED = 'malformed';
    public const BAD_SIGNATURE = 'bad_signature';
    public const EXPIRED = 'expired';
    public const REPLAYED = 'replayed';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    public function httpStatus(): int
    {
        return match ($this->reason) {
            self::MALFORMED => 400,
            self::REPLAYED => 409,
            default => 401, // bad signature and expired look the same to the caller on purpose
        };
    }
}
