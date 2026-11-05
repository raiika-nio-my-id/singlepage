<?php

namespace Tests\Unit;

use App\Models\CompanyProfile;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    public function test_public_url_uses_the_tenant_subdomain_and_app_scheme(): void
    {
        $profile = new CompanyProfile([
            'subdomain' => 'acme',
        ]);

        $this->assertSame('http://www.jadidaring.test/acme', $profile->publicUrl());
        $this->assertSame('www.jadidaring.test/acme', $profile->publicLabel());
        $this->assertSame('http://www.jadidaring.test/draft', CompanyProfile::publicUrlFor('draft'));
        $this->assertSame('http://www.jadidaring.test/{subdomain}', CompanyProfile::publicUrlFor('{subdomain}'));

        $hub = new CompanyProfile([
            'subdomain' => 'gamehub',
        ]);

        $this->assertSame('http://www.jadidaring.test/gamehub', $hub->publicUrl());
        $this->assertSame('www.jadidaring.test/gamehub', $hub->publicLabel());
    }
}
