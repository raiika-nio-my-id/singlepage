<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_shown_on_the_admin_host(): void
    {
        $this->get('http://admin.jadidaring.test/login')
            ->assertOk()
            ->assertSee('Sign in');
    }

    public function test_guests_are_redirected_from_the_dashboard(): void
    {
        $this->get('http://admin.jadidaring.test/')
            ->assertRedirect('http://admin.jadidaring.test/login');
    }

    public function test_admin_can_sign_in_and_receives_a_host_only_session_cookie(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);

        $response = $this->post('http://admin.jadidaring.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('http://admin.jadidaring.test');
        $this->assertAuthenticatedAs($user);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertSame('', (string) $cookie->getDomain());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', strtolower((string) $cookie->getSameSite()));
    }

    public function test_client_is_sent_to_their_workspace_after_login(): void
    {
        $client = User::factory()->client()->create([
            'email' => 'client@example.com',
        ]);
        CompanyProfile::factory()->for($client)->create();

        $this->post('http://admin.jadidaring.test/login', [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect('http://admin.jadidaring.test');

        $this->get('http://admin.jadidaring.test/')
            ->assertOk()
            ->assertSee('Your site')
            ->assertSee('Published')
            ->assertDontSee('Company profiles');
    }

    public function test_unknown_admin_paths_do_not_leak_on_other_hosts(): void
    {
        $this->get('http://jadidaring.test/login')->assertNotFound();
    }
}
