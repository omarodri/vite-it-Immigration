<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScanDocumentForVirus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    public function __construct(
        private readonly int $documentId
    ) {}

    public function handle(): void
    {
        $document = Document::find($this->documentId);

        if (! $document) {
            Log::warning('ScanDocumentForVirus: Document not found.', [
                'document_id' => $this->documentId,
            ]);

            return;
        }

        // Only scan locally stored documents
        if ($document->storage_type !== Document::STORAGE_LOCAL) {
            $document->update([
                'scan_status' => 'clean',
                'scanned_at' => now(),
            ]);

            Log::info('ScanDocumentForVirus: Skipped scan for non-local storage.', [
                'document_id' => $document->id,
                'storage_type' => $document->storage_type,
            ]);

            return;
        }

        $filePath = $this->resolveAbsolutePath($document);

        if (! $filePath || ! file_exists($filePath)) {
            $document->update(['scan_status' => 'error']);

            Log::warning('ScanDocumentForVirus: File not found on disk.', [
                'document_id' => $document->id,
                'storage_path' => $document->storage_path,
            ]);

            return;
        }

        $scanResult = $this->scanWithClamAV($filePath);

        match ($scanResult) {
            'clean' => $this->markClean($document),
            'infected' => $this->markInfected($document),
            'unavailable' => $this->markUnavailable($document),
            default => $this->markError($document),
        };
    }

    /**
     * Resolve the absolute filesystem path for the document.
     */
    private function resolveAbsolutePath(Document $document): ?string
    {
        $disk = Storage::disk('local');
        $path = $document->storage_path;

        if (! $disk->exists($path)) {
            return null;
        }

        return $disk->path($path);
    }

    /**
     * Attempt to scan the file with ClamAV.
     *
     * Tries clamd socket first, then falls back to clamscan binary.
     * Returns: 'clean', 'infected', 'unavailable', or 'error'.
     */
    private function scanWithClamAV(string $filePath): string
    {
        // Try clamd socket first
        $socketResult = $this->scanViaClamdSocket($filePath);
        if ($socketResult !== null) {
            return $socketResult;
        }

        // Fall back to clamscan binary
        $binaryResult = $this->scanViaClamscanBinary($filePath);
        if ($binaryResult !== null) {
            return $binaryResult;
        }

        // ClamAV not available
        return 'unavailable';
    }

    /**
     * Scan via clamd Unix socket.
     *
     * @return string|null Result or null if clamd is not available.
     */
    private function scanViaClamdSocket(string $filePath): ?string
    {
        $socketPath = config('services.clamav.socket', '/var/run/clamav/clamd.ctl');

        if (! file_exists($socketPath)) {
            return null;
        }

        try {
            $socket = @fsockopen('unix://' . $socketPath, -1, $errno, $errstr, 5);

            if (! $socket) {
                Log::debug('ScanDocumentForVirus: Could not connect to clamd socket.', [
                    'socket' => $socketPath,
                    'error' => $errstr,
                ]);

                return null;
            }

            fwrite($socket, "SCAN {$filePath}\n");

            $response = '';
            while (! feof($socket)) {
                $response .= fgets($socket, 4096);
            }
            fclose($socket);

            $response = trim($response);

            if (str_contains($response, 'OK')) {
                return 'clean';
            }

            if (str_contains($response, 'FOUND')) {
                Log::alert('ScanDocumentForVirus: Infection detected via clamd.', [
                    'file' => $filePath,
                    'response' => $response,
                ]);

                return 'infected';
            }

            Log::warning('ScanDocumentForVirus: Unexpected clamd response.', [
                'response' => $response,
            ]);

            return 'error';
        } catch (\Throwable $e) {
            Log::warning('ScanDocumentForVirus: clamd socket error.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Scan via clamscan command-line binary.
     *
     * @return string|null Result or null if clamscan is not installed.
     */
    private function scanViaClamscanBinary(string $filePath): ?string
    {
        $binaryPath = config('services.clamav.binary', 'clamscan');

        // Check if clamscan binary exists
        $whichResult = @exec("which {$binaryPath} 2>/dev/null", $output, $exitCode);
        if ($exitCode !== 0) {
            return null;
        }

        try {
            $escapedPath = escapeshellarg($filePath);
            $command = "{$binaryPath} --no-summary {$escapedPath} 2>&1";

            exec($command, $output, $exitCode);

            // clamscan exit codes: 0 = clean, 1 = infected, 2 = error
            return match ($exitCode) {
                0 => 'clean',
                1 => (function () use ($output, $filePath) {
                    Log::alert('ScanDocumentForVirus: Infection detected via clamscan.', [
                        'file' => $filePath,
                        'output' => implode("\n", $output),
                    ]);

                    return 'infected';
                })(),
                default => (function () use ($output, $exitCode) {
                    Log::warning('ScanDocumentForVirus: clamscan returned error.', [
                        'exit_code' => $exitCode,
                        'output' => implode("\n", $output),
                    ]);

                    return 'error';
                })(),
            };
        } catch (\Throwable $e) {
            Log::warning('ScanDocumentForVirus: clamscan binary error.', [
                'error' => $e->getMessage(),
            ]);

            return 'error';
        }
    }

    /**
     * Mark document as clean after successful scan.
     */
    private function markClean(Document $document): void
    {
        $document->update([
            'scan_status' => 'clean',
            'scanned_at' => now(),
        ]);

        Log::info('ScanDocumentForVirus: Document is clean.', [
            'document_id' => $document->id,
            'original_name' => $document->original_name,
        ]);
    }

    /**
     * Mark document as infected: soft-delete record and remove file from storage.
     */
    private function markInfected(Document $document): void
    {
        $document->update([
            'scan_status' => 'infected',
            'scanned_at' => now(),
        ]);

        // Remove the infected file from storage
        if ($document->storage_type === Document::STORAGE_LOCAL && $document->storage_path) {
            Storage::disk('local')->delete($document->storage_path);
        }

        // Soft-delete the document record
        $document->delete();

        activity()
            ->performedOn($document)
            ->withProperties([
                'document_id' => $document->id,
                'original_name' => $document->original_name,
                'case_id' => $document->case_id,
                'reason' => 'Virus detected during antivirus scan',
            ])
            ->log('Document quarantined: virus detected in ' . $document->original_name);

        Log::alert('ScanDocumentForVirus: Infected document quarantined.', [
            'document_id' => $document->id,
            'original_name' => $document->original_name,
            'case_id' => $document->case_id,
        ]);
    }

    /**
     * ClamAV is not installed -- graceful degradation.
     */
    private function markUnavailable(Document $document): void
    {
        $document->update([
            'scan_status' => 'pending',
        ]);

        Log::warning('ScanDocumentForVirus: ClamAV not available. Scan skipped.', [
            'document_id' => $document->id,
            'original_name' => $document->original_name,
        ]);
    }

    /**
     * Scan encountered an error -- don't block the document.
     */
    private function markError(Document $document): void
    {
        $document->update([
            'scan_status' => 'error',
            'scanned_at' => now(),
        ]);

        Log::warning('ScanDocumentForVirus: Scan failed with error.', [
            'document_id' => $document->id,
            'original_name' => $document->original_name,
        ]);
    }
}
