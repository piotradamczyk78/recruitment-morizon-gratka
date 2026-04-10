<?php

declare(strict_types=1);

namespace App\Service;

class InvalidPhoenixTokenException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid Phoenix API token');
    }
}
