<?php

namespace Tests\Unit\Models;

use App\Models\InvitationCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationCodeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $this->creator = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function createCode(array $overrides = []): InvitationCode
    {
        return InvitationCode::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'code' => 'TEST-' . uniqid(),
            'email' => null,
            'uses_remaining' => 1,
            'expires_at' => null,
            'created_by' => $this->creator->id,
        ], $overrides));
    }

    public function test_is_valid_returns_true_for_fresh_code(): void
    {
        $code = $this->createCode();

        $this->assertTrue($code->isValid());
    }

    public function test_is_valid_returns_false_when_no_uses_remaining(): void
    {
        $code = $this->createCode(['uses_remaining' => 0]);

        $this->assertFalse($code->isValid());
    }

    public function test_is_valid_returns_false_when_expired(): void
    {
        $code = $this->createCode(['expires_at' => now()->subHour()]);

        $this->assertFalse($code->isValid());
    }

    public function test_is_valid_returns_true_when_not_yet_expired(): void
    {
        $code = $this->createCode(['expires_at' => now()->addHour()]);

        $this->assertTrue($code->isValid());
    }

    public function test_is_valid_returns_true_for_matching_email(): void
    {
        $code = $this->createCode(['email' => 'user@example.com']);

        $this->assertTrue($code->isValid('user@example.com'));
    }

    public function test_is_valid_returns_false_for_non_matching_email(): void
    {
        $code = $this->createCode(['email' => 'user@example.com']);

        $this->assertFalse($code->isValid('other@example.com'));
    }

    public function test_is_valid_email_comparison_is_case_insensitive(): void
    {
        $code = $this->createCode(['email' => 'User@Example.COM']);

        $this->assertTrue($code->isValid('user@example.com'));
    }

    public function test_is_valid_returns_false_when_email_restricted_but_no_email_provided(): void
    {
        $code = $this->createCode(['email' => 'user@example.com']);

        $this->assertFalse($code->isValid(null));
    }

    public function test_is_valid_returns_true_when_no_email_restriction(): void
    {
        $code = $this->createCode(['email' => null]);

        $this->assertTrue($code->isValid('any@example.com'));
    }

    public function test_redeem_decrements_uses_remaining(): void
    {
        $code = $this->createCode(['uses_remaining' => 3]);

        $code->redeem();

        $code->refresh();
        $this->assertEquals(2, $code->uses_remaining);
    }

    public function test_redeem_multiple_times(): void
    {
        $code = $this->createCode(['uses_remaining' => 3]);

        $code->redeem();
        $code->redeem();

        $code->refresh();
        $this->assertEquals(1, $code->uses_remaining);
    }

    public function test_belongs_to_tenant(): void
    {
        $code = $this->createCode();

        $this->assertInstanceOf(Tenant::class, $code->tenant);
        $this->assertEquals($this->tenant->id, $code->tenant->id);
    }

    public function test_belongs_to_creator(): void
    {
        $code = $this->createCode();

        $this->assertInstanceOf(User::class, $code->creator);
        $this->assertEquals($this->creator->id, $code->creator->id);
    }

    public function test_code_must_be_unique(): void
    {
        $this->createCode(['code' => 'UNIQUE-CODE']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->createCode(['code' => 'UNIQUE-CODE']);
    }
}
