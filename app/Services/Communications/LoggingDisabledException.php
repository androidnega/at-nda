<?php

namespace App\Services\Communications;

use RuntimeException;

final class LoggingDisabledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('SMS and call logging is disabled for this institution.');
    }
}
