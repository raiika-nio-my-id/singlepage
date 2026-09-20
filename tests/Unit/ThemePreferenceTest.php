<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    public function test_login_view_includes_a_dark_mode_toggle(): void
    {
        $this->withViewErrors([]);

        $html = view('auth.login')->render();

        $this->assertStringContainsString('data-theme-toggle', $html);
        $this->assertStringContainsString('Switch to dark mode', $html);
        $this->assertStringContainsString('css/admin.css', $html);
        $this->assertStringContainsString('js/theme.js', $html);
        $this->assertStringContainsString('jadidaring-theme', $html);
    }

    public function test_admin_layout_includes_a_theme_toggle(): void
    {
        $this->actingAs(new User([
            'name' => 'Administrator',
            'role' => UserRole::Admin,
        ]));

        $html = view('admin.missing-profile')->render();

        $this->assertStringContainsString('data-theme-toggle', $html);
        $this->assertStringContainsString('Switch to dark mode', $html);
        $this->assertStringContainsString('js/theme.js', $html);
        $this->assertStringContainsString('css/admin.css', $html);
    }

    public function test_theme_script_persists_the_selected_mode(): void
    {
        $script = File::get(public_path('js/theme.js'));
        $css = File::get(public_path('css/admin.css'));

        $this->assertStringContainsString('localStorage.setItem(storageKey, next)', $script);
        $this->assertStringContainsString('[data-theme="dark"]', $css);
        $this->assertStringContainsString('[data-theme="light"]', $css);
        $this->assertStringContainsString('input:not([type])', $css);
    }
}
