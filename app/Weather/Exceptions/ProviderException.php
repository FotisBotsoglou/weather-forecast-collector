<?php

namespace App\Weather\Exceptions;

use RuntimeException;

class ProviderException extends RuntimeException
{
    public static function unexpectedResponse(string $provider, string $detail): self
    {
        return new self("Unexpected response from {$provider}: {$detail}");
    }
}
