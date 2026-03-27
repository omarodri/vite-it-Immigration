<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Services\OAuthCredentialService;
use App\Services\OAuthTokenService;

class OneDriveProvider extends MicrosoftGraphBaseProvider
{
    public function __construct(
        OAuthTokenService $tokenService,
        OAuthCredentialService $credentialService,
        ?int $tenantId = null
    ) {
        parent::__construct($tokenService, $tenantId);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDriveBasePath(): string
    {
        return '/me/drive';
    }
}
