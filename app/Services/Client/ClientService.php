<?php

namespace App\Services\Client;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientService
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository
    ) {}

    public function listClients(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->clientRepository->paginate($filters, $perPage);
    }

    public function getClient(Client $client): Client
    {
        $client->load(['user', 'companions', 'cases.caseType']);

        return $client;
    }

    public function createClient(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            // Check for duplicate email
            if (! empty($data['email']) && $this->clientRepository->existsByEmailForTenant($data['email'])) {
                throw new \InvalidArgumentException('A client with this email already exists');
            }

            // Check for duplicate phone
            if (! empty($data['phone']) && $this->clientRepository->existsByPhoneForTenant($data['phone'])) {
                throw new \InvalidArgumentException('A client with this phone number already exists');
            }

            $client = $this->clientRepository->create($data);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($client)
                ->withProperties(['status' => $client->status])
                ->log('Created client: '.$client->full_name);

            return $client;
        });
    }

    public function updateClient(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data) {
            // Check for duplicate email (excluding current client)
            if (! empty($data['email']) && $data['email'] !== $client->email) {
                if ($this->clientRepository->existsByEmailForTenant($data['email'], $client->id)) {
                    throw new \InvalidArgumentException('A client with this email already exists');
                }
            }

            // Check for duplicate phone (excluding current client)
            if (! empty($data['phone']) && $data['phone'] !== $client->phone) {
                if ($this->clientRepository->existsByPhoneForTenant($data['phone'], $client->id)) {
                    throw new \InvalidArgumentException('A client with this phone number already exists');
                }
            }

            $this->clientRepository->update($client, $data);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($client)
                ->withProperties(['updated_fields' => array_keys($data)])
                ->log('Updated client: '.$client->full_name);

            return $client->fresh();
        });
    }

    public function deleteClient(Client $client): void
    {
        activity()
            ->causedBy(Auth::user())
            ->performedOn($client)
            ->withProperties(['deleted_client' => $client->email ?? $client->full_name])
            ->log('Deleted client: '.$client->full_name);

        $this->clientRepository->delete($client);
    }

    public function bulkDeleteClients(array $ids): int
    {
        activity()
            ->causedBy(Auth::user())
            ->withProperties(['deleted_ids' => $ids])
            ->log('Bulk deleted '.count($ids).' clients');

        return $this->clientRepository->bulkDelete($ids);
    }

    public function convertProspectToActive(Client $client): Client
    {
        if ($client->status !== 'prospect') {
            throw new \InvalidArgumentException('Only prospects can be converted to active clients');
        }

        return DB::transaction(function () use ($client) {
            $this->clientRepository->update($client, ['status' => 'active']);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($client)
                ->withProperties(['previous_status' => 'prospect', 'new_status' => 'active'])
                ->log('Converted prospect to active client: '.$client->full_name);

            return $client->fresh();
        });
    }

    public function updateStatus(Client $client, string $status): Client
    {
        $validStatuses = ['prospect', 'active', 'inactive', 'archived'];
        if (! in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid status: '.$status);
        }

        $previousStatus = $client->status;

        return DB::transaction(function () use ($client, $status, $previousStatus) {
            $this->clientRepository->update($client, ['status' => $status]);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($client)
                ->withProperties(['previous_status' => $previousStatus, 'new_status' => $status])
                ->log('Changed client status from '.$previousStatus.' to '.$status.': '.$client->full_name);

            return $client->fresh();
        });
    }

    public function getStatistics(): array
    {
        return [
            'total' => Client::count(),
            'prospect' => $this->clientRepository->countByStatus('prospect'),
            'active' => $this->clientRepository->countByStatus('active'),
            'inactive' => $this->clientRepository->countByStatus('inactive'),
            'archived' => $this->clientRepository->countByStatus('archived'),
        ];
    }
}
