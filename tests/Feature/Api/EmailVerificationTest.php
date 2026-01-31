<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_can_request_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/email/verification-notification');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Verification link sent',
            ]);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_verified_user_cannot_request_verification_email(): void
    {
        $user = User::factory()->create(); // verified by default

        $response = $this->actingAs($user)
            ->postJson('/api/email/verification-notification');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Email already verified',
            ]);
    }

    public function test_user_can_verify_email_with_valid_link(): void
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $response = $this->getJson($verificationUrl);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Email verified successfully',
                'verified' => true,
            ]);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_user_cannot_verify_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1('wrong-email@example.com'),
            ]
        );

        $response = $this->getJson($verificationUrl);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Invalid verification link',
            ]);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_user_cannot_verify_with_expired_link(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->subMinutes(10), // expired
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $response = $this->getJson($verificationUrl);

        // Laravel's signed middleware throws InvalidSignatureException for expired links
        $response->assertStatus(403);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_user_cannot_verify_with_invalid_signature(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->getJson("/api/email/verify/{$user->id}/".sha1($user->email).'?signature=invalid');

        $response->assertStatus(403);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_already_verified_user_gets_appropriate_response(): void
    {
        $user = User::factory()->create(); // verified by default

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $response = $this->getJson($verificationUrl);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Email already verified',
                'verified' => true,
            ]);
    }

    public function test_verification_status_returns_correct_status_for_verified_user(): void
    {
        $user = User::factory()->create(); // verified

        $response = $this->actingAs($user)
            ->getJson('/api/email/verification-status');

        $response->assertStatus(200)
            ->assertJson([
                'verified' => true,
                'email' => $user->email,
            ]);
    }

    public function test_verification_status_returns_correct_status_for_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/email/verification-status');

        $response->assertStatus(200)
            ->assertJson([
                'verified' => false,
                'email' => $user->email,
            ]);
    }

    public function test_unauthenticated_user_cannot_request_verification_email(): void
    {
        $response = $this->postJson('/api/email/verification-notification');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_check_verification_status(): void
    {
        $response = $this->getJson('/api/email/verification-status');

        $response->assertStatus(401);
    }

    public function test_verification_email_is_rate_limited(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        // Send multiple requests
        for ($i = 0; $i < 4; $i++) {
            $response = $this->actingAs($user)
                ->postJson('/api/email/verification-notification');
        }

        // Fourth request should be rate limited
        $response->assertStatus(429);
    }

    public function test_verified_middleware_blocks_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();

        // Test using the middleware directly via a mock request
        $middleware = new \App\Http\Middleware\EnsureEmailIsVerified;

        $request = \Illuminate\Http\Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->headers->set('Accept', 'application/json');

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Your email address is not verified.', $data['message']);
        $this->assertEquals('email_not_verified', $data['error']);
    }

    public function test_verified_middleware_allows_verified_user(): void
    {
        $user = User::factory()->create(); // verified by default

        // Test using the middleware directly
        $middleware = new \App\Http\Middleware\EnsureEmailIsVerified;

        $request = \Illuminate\Http\Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->headers->set('Accept', 'application/json');

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
