<?php

namespace App\Exceptions;

use RuntimeException;

class PosPromoValidationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $promoCode,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($message);
    }
}
