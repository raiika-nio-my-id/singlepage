<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OptionalPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_client_without_supplying_a_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('http://admin.jadidaring.test/clients', [
                'name' => 'New Client',
                'email' => 'newclient@example.com',
                'subdomain' => 'newclient',
                'html' => '<html><body>Hello</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $user = User::where('email', 'newclient@example.com')->firstOrFail();
        $this->assertNotEmpty($user->password, 'A random password should be set when none is supplied.');

        $profile = CompanyProfile::where('subdomain', 'newclient')->firstOrFail();
        $this->assertSame($user->id, $profile->user_id);
    }

    public function test_admin_can_create_client_with_an_explicit_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('http://admin.jadidaring.test/clients', [
                'name' => 'Explicit Client',
                'email' => 'explicit@example.com',
                'password' => 'ExplicitPass123!',
                'subdomain' => 'explicitclient',
                'html' => '<html><body>Hi</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $user = User::where('email', 'explicit@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('ExplicitPass123!', $user->password));
    }

    public function test_blank_password_on_edit_keeps_existing_password(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create([
            'subdomain' => 'keeppass',
        ]);
        $original = $profile->user->password;

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->put('http://admin.jadidaring.test/clients/'.$profile->id, [
                'subdomain' => 'keeppass',
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'html' => '<html><body>Same site</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame($original, $profile->user->fresh()->password, 'Password must be unchanged when the field is left blank on edit.');
    }
}
