<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate a new secret key for two-factor authentication.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Generate QR code as SVG for two-factor authentication setup.
     */
    public function generateQrCodeSvg(User $user, string $secret): string
    {
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            'VristoPOC',
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($qrCodeUrl);
    }

    /**
     * Verify a two-factor authentication code.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code, 1);
    }

    /**
     * Generate recovery codes for two-factor authentication.
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(Str::random(10));
        }

        return $codes;
    }

    /**
     * Verify a recovery code and remove it from the user's recovery codes.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $recoveryCodes = $user->two_factor_recovery_codes ?? [];

        $key = array_search(strtoupper($code), array_map('strtoupper', $recoveryCodes));

        if ($key === false) {
            return false;
        }

        // Remove the used recovery code
        unset($recoveryCodes[$key]);
        $user->two_factor_recovery_codes = array_values($recoveryCodes);
        $user->save();

        return true;
    }
}
