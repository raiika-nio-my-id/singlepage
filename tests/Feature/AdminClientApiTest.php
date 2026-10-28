<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\CompanyProfileAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminClientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_api_key_is_unauthorized(): void
    {
        $this->postJson($this->clientsUrl(), $this->createPayload())
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthorized.']);
    }

    public function test_wrong_api_key_is_unauthorized(): void
    {
        $this->postJson($this->clientsUrl(), $this->createPayload(), [
            'X-Admin-Api-Key' => 'not-the-secret',
        ])->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthorized.']);
    }

    public function test_empty_configured_secret_rejects_every_request(): void
    {
        Config::set('singlepage.admin_api.secret', '');

        $this->postJson($this->clientsUrl(), $this->createPayload(), $this->apiHeaders())
            ->assertUnauthorized();
    }

    public function test_admin_session_without_api_key_is_unauthorized(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson($this->clientsUrl(), $this->createPayload())
            ->assertUnauthorized();
    }

    public function test_create_client_happy_path(): void
    {
        $response = $this->postJson($this->clientsUrl(), $this->createPayload([
            'password' => 'ExplicitPass123!',
            'status' => 'published',
            'hide_footer' => true,
            'is_pinned' => true,
            'aliases' => ['acme-old'],
        ]), $this->apiHeaders());

        $response->assertCreated()
            ->assertJsonPath('message', 'Client created.')
            ->assertJsonPath('data.subdomain', 'acme')
            ->assertJsonPath('data.name', 'Acme Corp')
            ->assertJsonPath('data.email', 'acme@example.com')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.hide_footer', true)
            ->assertJsonPath('data.is_pinned', true)
            ->assertJsonPath('data.aliases', ['acme-old'])
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('company_profiles', [
            'subdomain' => 'acme',
            'status' => 'published',
            'hide_footer' => true,
            'is_pinned' => true,
        ]);

        $user = User::query()->where('email', 'acme@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('ExplicitPass123!', $user->password));
        $this->assertTrue($user->isClient());
    }

    public function test_create_without_password_sets_a_random_one(): void
    {
        $this->postJson($this->clientsUrl(), $this->createPayload(), $this->apiHeaders())
            ->assertCreated();

        $user = User::query()->where('email', 'acme@example.com')->firstOrFail();
        $this->assertNotEmpty($user->password);
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_partial_update_changes_only_sent_fields(): void
    {
        $profile = CompanyProfile::factory()->create([
            'subdomain' => 'keepme',
            'html' => '<html><body>Original</body></html>',
            'hide_footer' => false,
            'is_pinned' => false,
        ]);
        $originalName = $profile->user->name;
        $originalEmail = $profile->user->email;
        $originalExpiry = $profile->expires_at?->toDateString();

        $this->patchJson($this->clientsUrl('/'.$profile->id), [
            'html' => '<html><body>Updated snippet</body></html>',
            'status' => 'published',
        ], $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Client updated.')
            ->assertJsonPath('data.html', '<html><body>Updated snippet</body></html>')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.subdomain', 'keepme')
            ->assertJsonPath('data.name', $originalName)
            ->assertJsonPath('data.email', $originalEmail);

        $profile->refresh();
        $profile->user->refresh();

        $this->assertSame('keepme', $profile->subdomain);
        $this->assertSame($originalName, $profile->user->name);
        $this->assertSame($originalEmail, $profile->user->email);
        $this->assertSame($originalExpiry, $profile->expires_at?->toDateString());
        $this->assertFalse($profile->hide_footer);
        $this->assertFalse($profile->is_pinned);
    }

    public function test_partial_update_by_subdomain(): void
    {
        $profile = CompanyProfile::factory()->create([
            'subdomain' => 'byname',
            'is_pinned' => false,
        ]);

        $this->patchJson($this->clientsUrl('/subdomain/byname'), [
            'is_pinned' => true,
            'name' => 'Renamed By Subdomain',
        ], $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $profile->id)
            ->assertJsonPath('data.is_pinned', true)
            ->assertJsonPath('data.name', 'Renamed By Subdomain');
    }

    public function test_create_validation_errors(): void
    {
        $this->postJson($this->clientsUrl(), [
            'name' => '',
            'email' => 'not-an-email',
            'subdomain' => 'Invalid_Sub',
            'html' => '',
            'status' => 'live',
        ], $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'subdomain', 'html', 'status']);
    }

    public function test_empty_patch_is_rejected(): void
    {
        $profile = CompanyProfile::factory()->create(['subdomain' => 'empty-patch']);

        $this->patchJson($this->clientsUrl('/'.$profile->id), [], $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payload']);
    }

    public function test_unknown_client_returns_not_found(): void
    {
        $this->patchJson($this->clientsUrl('/9999'), [
            'html' => '<html><body>Nope</body></html>',
        ], $this->apiHeaders())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Client not found.']);

        $this->patchJson($this->clientsUrl('/subdomain/missing-client'), [
            'html' => '<html><body>Nope</body></html>',
        ], $this->apiHeaders())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Client not found.']);
    }

    public function test_create_rejects_taken_subdomain_and_email(): void
    {
        $existing = CompanyProfile::factory()->create(['subdomain' => 'taken']);

        $this->postJson($this->clientsUrl(), $this->createPayload([
            'subdomain' => 'taken',
            'email' => 'fresh@example.com',
        ]), $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subdomain']);

        $this->postJson($this->clientsUrl(), $this->createPayload([
            'subdomain' => 'freshname',
            'email' => $existing->user->email,
        ]), $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_rejects_subdomain_and_alias_conflicts(): void
    {
        $owner = CompanyProfile::factory()->create(['subdomain' => 'owner']);
        CompanyProfileAlias::factory()->for($owner)->create(['subdomain' => 'claimed-alias']);
        $other = CompanyProfile::factory()->create(['subdomain' => 'other']);

        $this->patchJson($this->clientsUrl('/'.$other->id), [
            'subdomain' => 'owner',
        ], $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subdomain']);

        $this->patchJson($this->clientsUrl('/'.$other->id), [
            'subdomain' => 'claimed-alias',
        ], $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subdomain']);

        $this->patchJson($this->clientsUrl('/'.$other->id), [
            'aliases' => ['owner'],
        ], $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['aliases']);

        $this->patchJson($this->clientsUrl('/'.$other->id), [
            'email' => $owner->user->email,
        ], $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_changing_primary_to_own_alias_swaps_names(): void
    {
        $profile = CompanyProfile::factory()->published()->create(['subdomain' => 'alpha']);
        CompanyProfileAlias::factory()->for($profile)->create(['subdomain' => 'beta']);

        $this->patchJson($this->clientsUrl('/'.$profile->id), [
            'subdomain' => 'beta',
        ], $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('data.subdomain', 'beta');

        $this->assertDatabaseHas('company_profiles', [
            'id' => $profile->id,
            'subdomain' => 'beta',
        ]);
        $this->assertDatabaseHas('company_profile_aliases', [
            'company_profile_id' => $profile->id,
            'subdomain' => 'alpha',
        ]);
        $this->assertDatabaseMissing('company_profile_aliases', [
            'company_profile_id' => $profile->id,
            'subdomain' => 'beta',
        ]);
    }

    public function test_reserved_subdomain_is_rejected(): void
    {
        $this->postJson($this->clientsUrl(), $this->createPayload([
            'subdomain' => 'admin',
        ]), $this->apiHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subdomain']);
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        return ['X-Admin-Api-Key' => 'testing-admin-api-secret'];
    }

    private function clientsUrl(string $suffix = ''): string
    {
        return 'http://admin.jadidaring.test/api/admin/clients'.$suffix;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Acme Corp',
            'email' => 'acme@example.com',
            'subdomain' => 'acme',
            'html' => '<!doctype html><html><body><h1>Acme</h1></body></html>',
            'expires_at' => now()->addYear()->toDateString(),
        ], $overrides);
    }
}
