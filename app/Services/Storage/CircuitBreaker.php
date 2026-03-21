<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CircuitBreaker
{
    private const FAILURE_THRESHOLD = 5;

    private const FAILURE_WINDOW_SECONDS = 60;

    private const OPEN_DURATION_SECONDS = 120;

    private const CACHE_PREFIX = 'circuit_breaker';

    /**
     * Check if the circuit is open (blocking requests) for a given provider.
     */
    public function isOpen(string $provider): bool
    {
        $openUntil = Cache::get($this->openUntilKey($provider));

        if ($openUntil === null) {
            return false;
        }

        if (now()->timestamp >= (int) $openUntil) {
            // Transition to half-open: allow one request through
            $this->transitionToHalfOpen($provider);

            return false;
        }

        return true;
    }

    /**
     * Check if the circuit is in half-open state (testing recovery).
     */
    public function isHalfOpen(string $provider): bool
    {
        return (bool) Cache::get($this->halfOpenKey($provider), false);
    }

    /**
     * Record a failure for a provider. Opens circuit if threshold is exceeded.
     */
    public function recordFailure(string $provider): void
    {
        if ($this->isHalfOpen($provider)) {
            // Half-open test failed: re-open the circuit
            $this->openCircuit($provider);
            Cache::forget($this->halfOpenKey($provider));

            Log::warning("CircuitBreaker: half-open test failed for [{$provider}], re-opening circuit.");

            return;
        }

        $failuresKey = $this->failuresKey($provider);
        $currentFailures = (int) Cache::get($failuresKey, 0);
        $currentFailures++;

        Cache::put($failuresKey, $currentFailures, self::FAILURE_WINDOW_SECONDS);

        if ($currentFailures >= self::FAILURE_THRESHOLD) {
            $this->openCircuit($provider);

            Log::warning("CircuitBreaker: threshold reached for [{$provider}], opening circuit for " . self::OPEN_DURATION_SECONDS . 's.');
        }
    }

    /**
     * Record a successful call for a provider. Resets circuit to closed state.
     */
    public function recordSuccess(string $provider): void
    {
        if ($this->isHalfOpen($provider)) {
            Log::info("CircuitBreaker: half-open test succeeded for [{$provider}], closing circuit.");
        }

        $this->reset($provider);
    }

    /**
     * Reset the circuit breaker for a provider (close the circuit).
     */
    public function reset(string $provider): void
    {
        Cache::forget($this->failuresKey($provider));
        Cache::forget($this->openUntilKey($provider));
        Cache::forget($this->halfOpenKey($provider));
    }

    /**
     * Get the number of recorded failures for a provider.
     */
    public function getFailureCount(string $provider): int
    {
        return (int) Cache::get($this->failuresKey($provider), 0);
    }

    /**
     * Open the circuit for a provider.
     */
    private function openCircuit(string $provider): void
    {
        $openUntil = now()->addSeconds(self::OPEN_DURATION_SECONDS)->timestamp;

        Cache::put(
            $this->openUntilKey($provider),
            $openUntil,
            self::OPEN_DURATION_SECONDS
        );

        // Reset failure count since the circuit is now open
        Cache::forget($this->failuresKey($provider));
    }

    /**
     * Transition the circuit to half-open state.
     */
    private function transitionToHalfOpen(string $provider): void
    {
        Cache::put($this->halfOpenKey($provider), true, self::OPEN_DURATION_SECONDS);
        Cache::forget($this->openUntilKey($provider));

        Log::info("CircuitBreaker: transitioning [{$provider}] to half-open state.");
    }

    private function failuresKey(string $provider): string
    {
        return self::CACHE_PREFIX . ":{$provider}:failures";
    }

    private function openUntilKey(string $provider): string
    {
        return self::CACHE_PREFIX . ":{$provider}:open_until";
    }

    private function halfOpenKey(string $provider): string
    {
        return self::CACHE_PREFIX . ":{$provider}:half_open";
    }
}
