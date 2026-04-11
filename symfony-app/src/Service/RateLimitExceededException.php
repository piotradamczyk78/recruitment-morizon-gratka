<?php

declare(strict_types=1);

namespace App\Service;

class RateLimitExceededException extends \RuntimeException
{
    public function __construct(
        private int $retryAfter,
    ) {
        parent::__construct('Rate limit exceeded. Retry after ' . $retryAfter . ' seconds.');
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
