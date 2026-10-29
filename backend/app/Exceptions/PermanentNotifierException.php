<?php

namespace App\Exceptions;

use RuntimeException;

/** The notifier failed in a way retrying cannot fix (no valid recipient). Goes straight to a human. */
class PermanentNotifierException extends RuntimeException {}
