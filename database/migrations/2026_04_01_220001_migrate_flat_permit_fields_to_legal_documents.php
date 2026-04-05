<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now()->format('Y-m-d H:i:s');

        // ── Migrate Client passport/permit data ────────────────────────
        $clients = DB::table('clients')
            ->whereNotNull('passport_number')
            ->orWhereNotNull('work_permit_number')
            ->orWhereNotNull('study_permit_number')
            ->get(['id', 'tenant_id', 'passport_number', 'passport_issue_date', 'passport_country', 'passport_expiry_date', 'work_permit_number', 'study_permit_number', 'permit_expiry_date']);

        $inserts = [];

        foreach ($clients as $client) {
            if ($client->passport_number) {
                $inserts[] = [
                    'tenant_id'         => $client->tenant_id,
                    'documentable_type' => 'App\\Models\\Client',
                    'documentable_id'   => $client->id,
                    'document_type'     => 'passport',
                    'document_name'     => null,
                    'document_number'   => $client->passport_number,
                    'issue_date'        => $client->passport_issue_date,
                    'expiry_date'       => $client->passport_expiry_date,
                    'alert_days_before' => 60,
                    'alert_active'      => true,
                    'notes'             => $client->passport_country ? "Country: {$client->passport_country}" : null,
                    'sort_order'        => 0,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            if ($client->work_permit_number) {
                $inserts[] = [
                    'tenant_id'         => $client->tenant_id,
                    'documentable_type' => 'App\\Models\\Client',
                    'documentable_id'   => $client->id,
                    'document_type'     => 'work_permit',
                    'document_name'     => null,
                    'document_number'   => $client->work_permit_number,
                    'issue_date'        => null,
                    'expiry_date'       => $client->permit_expiry_date,
                    'alert_days_before' => 60,
                    'alert_active'      => true,
                    'notes'             => null,
                    'sort_order'        => 1,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            if ($client->study_permit_number) {
                $inserts[] = [
                    'tenant_id'         => $client->tenant_id,
                    'documentable_type' => 'App\\Models\\Client',
                    'documentable_id'   => $client->id,
                    'document_type'     => 'study_permit',
                    'document_name'     => null,
                    'document_number'   => $client->study_permit_number,
                    'issue_date'        => null,
                    'expiry_date'       => $client->permit_expiry_date,
                    'alert_days_before' => 60,
                    'alert_active'      => true,
                    'notes'             => null,
                    'sort_order'        => 2,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }
        }

        // ── Migrate Companion passport/permit data ─────────────────────
        $companions = DB::table('companions')
            ->whereNotNull('passport_number')
            ->orWhereNotNull('work_permit_number')
            ->orWhereNotNull('study_permit_number')
            ->get(['id', 'tenant_id', 'passport_number', 'passport_issue_date', 'passport_country', 'passport_expiry_date', 'work_permit_number', 'study_permit_number', 'permit_expiry_date']);

        foreach ($companions as $companion) {
            if ($companion->passport_number) {
                $inserts[] = [
                    'tenant_id'         => $companion->tenant_id,
                    'documentable_type' => 'App\\Models\\Companion',
                    'documentable_id'   => $companion->id,
                    'document_type'     => 'passport',
                    'document_name'     => null,
                    'document_number'   => $companion->passport_number,
                    'issue_date'        => $companion->passport_issue_date,
                    'expiry_date'       => $companion->passport_expiry_date,
                    'alert_days_before' => 60,
                    'alert_active'      => true,
                    'notes'             => $companion->passport_country ? "Country: {$companion->passport_country}" : null,
                    'sort_order'        => 0,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            if ($companion->work_permit_number) {
                $inserts[] = [
                    'tenant_id'         => $companion->tenant_id,
                    'documentable_type' => 'App\\Models\\Companion',
                    'documentable_id'   => $companion->id,
                    'document_type'     => 'work_permit',
                    'document_name'     => null,
                    'document_number'   => $companion->work_permit_number,
                    'issue_date'        => null,
                    'expiry_date'       => $companion->permit_expiry_date,
                    'alert_days_before' => 60,
                    'alert_active'      => true,
                    'notes'             => null,
                    'sort_order'        => 1,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            if ($companion->study_permit_number) {
                $inserts[] = [
                    'tenant_id'         => $companion->tenant_id,
                    'documentable_type' => 'App\\Models\\Companion',
                    'documentable_id'   => $companion->id,
                    'document_type'     => 'study_permit',
                    'document_name'     => null,
                    'document_number'   => $companion->study_permit_number,
                    'issue_date'        => null,
                    'expiry_date'       => $companion->permit_expiry_date,
                    'alert_days_before' => 60,
                    'alert_active'      => true,
                    'notes'             => null,
                    'sort_order'        => 2,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }
        }

        // Bulk insert in chunks to avoid memory issues
        foreach (array_chunk($inserts, 500) as $chunk) {
            DB::table('legal_documents')->insert($chunk);
        }
    }

    public function down(): void
    {
        // Only delete records whose document_type was created from flat permit columns.
        // Using truncate() would destroy manually-created documents — unsafe for rollback.
        DB::table('legal_documents')
            ->whereIn('document_type', [
                'passport', 'work_permit', 'study_permit',
            ])
            ->delete();
    }
};
