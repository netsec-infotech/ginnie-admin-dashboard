<?php

namespace App\Exceptions;

use Exception;

class SharedCartMutationException extends Exception
{
    /** @param array<string, mixed> $result */
    public function __construct(public readonly array $result)
    {
        parent::__construct((string) ($result['message'] ?? 'Shared cart mutation failed.'));
    }
}
