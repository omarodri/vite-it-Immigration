<?php

namespace App\Services\Client;

use App\Exceptions\ClientPromotionConflictException;
use App\Exceptions\CompanionNotEligibleForPromotionException;
use App\Models\Client;
use App\Models\Companion;
use App\Models\User;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ClientPromotionService
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository
    ) {}

    /**
     * Promote a companion to a standalone client. The companion remains intact and
     * its case associations are not touched.
     *
     * @throws CompanionNotEligibleForPromotionException
     * @throws ClientPromotionConflictException
     */
    public function promote(Companion $companion, User $actor): Client
    {
        try {
            $client = DB::transaction(function () use ($companion) {
                // Lock the companion row to serialize concurrent promotion attempts.
                DB::table('companions')->where('id', $companion->id)->lockForUpdate()->first();

                $this->assertEligible($companion);

                return $this->clientRepository->create($this->buildClientData($companion));
            });
        } catch (QueryException $e) {
            // 1062 = duplicate entry — covers the case where another concurrent
            // request slipped in between the eligibility check and the insert.
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new ClientPromotionConflictException('email_or_phone_already_exists');
            }
            throw $e;
        }

        // Activity log is intentionally outside the DB::transaction to keep the audit
        // trail decoupled from the business write — a logging hiccup must never roll back the promotion.
        activity()
            ->causedBy($actor)
            ->performedOn($client)
            ->withProperties([
                'source_companion_id' => $companion->id,
                'source_companion'    => $companion->full_name,
            ])
            ->log('Promoted companion to client: ' . $companion->full_name);

        return $client;
    }

    /**
     * Lightweight pre-flight check used by the UI to enable/disable the promote action.
     */
    public function checkEligibility(Companion $companion): array
    {
        $reasons = [];

        if (empty(trim((string) $companion->first_name))) {
            $reasons[] = 'missing_first_name';
        }
        if (empty(trim((string) $companion->last_name))) {
            $reasons[] = 'missing_last_name';
        }
        if (empty(trim((string) $companion->email))) {
            $reasons[] = 'missing_email';
        }
        if (empty(trim((string) $companion->phone))) {
            $reasons[] = 'missing_phone';
        }

        if (! $companion->date_of_birth) {
            $reasons[] = 'missing_date_of_birth';
        } elseif (($companion->age ?? 0) < 18) {
            $reasons[] = 'underage';
        }

        if (! empty($companion->email)
            && $this->clientRepository->existsByEmailForTenantWithTrashed($companion->email)) {
            $reasons[] = 'email_exists_in_clients';
        }

        if (! empty($companion->phone)
            && $this->clientRepository->existsByPhoneForTenantWithTrashed($companion->phone)) {
            $reasons[] = 'phone_exists_in_clients';
        }

        $alreadyPromoted   = Client::where('promoted_from_companion_id', $companion->id)->exists();
        $existingClientId  = $alreadyPromoted
            ? Client::where('promoted_from_companion_id', $companion->id)->value('id')
            : null;

        return [
            'eligible'           => empty($reasons) && ! $alreadyPromoted,
            'reasons'            => $reasons,
            'already_promoted'   => $alreadyPromoted,
            'existing_client_id' => $existingClientId,
        ];
    }

    private function assertEligible(Companion $companion): void
    {
        $missing = [];
        if (empty(trim((string) $companion->first_name))) $missing[] = 'first_name';
        if (empty(trim((string) $companion->last_name)))  $missing[] = 'last_name';
        if (empty(trim((string) $companion->email)))      $missing[] = 'email';
        if (empty(trim((string) $companion->phone)))      $missing[] = 'phone';

        if (! empty($missing)) {
            throw new CompanionNotEligibleForPromotionException('missing_required_fields', $missing);
        }

        if (! $companion->date_of_birth) {
            throw new CompanionNotEligibleForPromotionException('missing_date_of_birth');
        }

        if (($companion->age ?? 0) < 18) {
            throw new CompanionNotEligibleForPromotionException('underage');
        }

        if (Client::where('promoted_from_companion_id', $companion->id)->exists()) {
            throw new ClientPromotionConflictException('already_promoted');
        }

        if (! empty($companion->email)
            && $this->clientRepository->existsByEmailForTenantWithTrashed($companion->email)) {
            throw new ClientPromotionConflictException('email_already_exists');
        }

        if (! empty($companion->phone)
            && $this->clientRepository->existsByPhoneForTenantWithTrashed($companion->phone)) {
            throw new ClientPromotionConflictException('phone_already_exists');
        }
    }

    private function buildClientData(Companion $companion): array
    {
        return [
            'tenant_id'                  => $companion->tenant_id,
            // Spec 64 — companions always promote to a persona-type client.
            'type'                       => Client::TYPE_PERSON,
            'first_name'                 => $companion->first_name,
            'last_name'                  => $companion->last_name,
            'email'                      => $companion->email,
            'phone'                      => $companion->phone,
            'phone_country_code'         => $companion->phone_country_code,
            'date_of_birth'              => $companion->date_of_birth,
            'gender'                     => $companion->gender,
            'marital_status'             => $companion->marital_status,
            'nationality'                => $companion->nationality,
            'iuc'                        => $companion->iuc,
            'canada_status'              => $companion->canada_status,
            'other_status_1'             => $companion->canada_status_other,
            'arrival_date'               => $companion->arrival_date,
            'description'                => $companion->notes,
            'status'                     => 'active',
            'promoted_from_companion_id' => $companion->id,
        ];
    }
}
