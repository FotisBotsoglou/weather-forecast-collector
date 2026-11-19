<?php

namespace App\Weather;

final readonly class CollectResult
{
    public function __construct(
        public string $provider,
        public CollectOutcome $outcome,
        public string $message,
    ) {}
}
