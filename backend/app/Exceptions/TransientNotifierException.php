<?php

namespace App\Exceptions;

use RuntimeException;

/** The notifier failed in a way that may work next time (timeout, 503). The job retries. */
class TransientNotifierException extends RuntimeException {}
