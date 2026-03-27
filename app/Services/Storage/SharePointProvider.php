<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\Tenant;
use App\Services\OAuthCredentialService;
use App\Services\OAuthTokenService;

class SharePointProvider extends MicrosoftGraphBaseProvider
{
    private ?string $driveId;

    public function __construct(
        OAuthTokenService $tokenService,
        OAuthCredentialService $credentialService,
        ?int $tenantId = null,
        ?string $driveId = null
    ) {
        parent::__construct($tokenService, $tenantId);

        $this->driveId = $driveId;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDriveBasePath(): string
    {
        $driveId = $this->resolveDriveId();

        if (!$driveId) {
            throw new \RuntimeException(
                'SharePoint drive ID is not configured. An administrator must set the SharePoint drive ID in Admin > Storage Settings.'
            );
        }

        return "/drives/{$driveId}";
    }

    /**
     * Resolve the SharePoint drive ID from the explicit property or the tenant settings.
     */
    private function resolveDriveId(): ?string
    {
        if ($this->driveId !== null) {
            return $this->driveId;
        }

        $tenantId = $this->resolveTenantId();

        if (!$tenantId) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return null;
        }

        $this->driveId = $tenant->sharepoint_drive_id;

        return $this->driveId;
    }
}
