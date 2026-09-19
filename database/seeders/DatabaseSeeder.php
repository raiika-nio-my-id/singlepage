<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Administrator',
            'email' => 'admin@jadidaring.test',
        ]);

        $client = User::factory()->client()->create([
            'name' => 'Demo Client',
            'email' => 'client@jadidaring.test',
        ]);

        CompanyProfile::factory()->for($client)->published()->create([
            'subdomain' => 'demo',
            'html' => <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Demo Company</title>
</head>
<body>
    <h1>Demo Company</h1>
    <p>This published profile is served on the client subdomain.</p>
</body>
</html>
HTML,
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'reserved_subdomains'],
            ['value' => implode(PHP_EOL, config('singlepage.reserved_subdomains'))],
        );
    }
}
