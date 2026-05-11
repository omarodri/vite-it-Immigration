<?php

namespace App\Events;

use App\Models\User;

class SessionRevoked
{
    public function __construct(
        public readonly User   $victim,
        public readonly string $revokingIp,
        public readonly string $revokingUserAgent,
        public readonly string $stopReason = 'new_login',
    ) {}
}
