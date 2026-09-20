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

        $this->assertSame('http://acme.jadidaring.test', $profile->publicUrl());
        $this->assertSame('http://draft.jadidaring.test', CompanyProfile::publicUrlFor('draft'));
    }
}
