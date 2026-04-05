<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptOauthTokens extends Command
{
    protected $signature = 'tokens:encrypt';

    protected $description = 'Encrypt existing plaintext OAuth tokens in the database';

    public function handle(): int
    {
        $tokens = DB::table('oauth_tokens')->get();
        $migrated = 0;

        foreach ($tokens as $token) {
            $updates = [];

            if ($token->access_token && ! $this->isEncrypted($token->access_token)) {
                $updates['access_token'] = Crypt::encryptString($token->access_token);
            }

            if ($token->refresh_token && ! $this->isEncrypted($token->refresh_token)) {
                $updates['refresh_token'] = Crypt::encryptString($token->refresh_token);
            }

            if (! empty($updates)) {
                DB::table('oauth_tokens')->where('id', $token->id)->update($updates);
                $migrated++;
            }
        }

        $this->info("Encrypted tokens for {$migrated} of {$tokens->count()} records.");

        return self::SUCCESS;
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
