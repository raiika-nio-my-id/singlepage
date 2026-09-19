<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\CompanyProfile;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $this->authorize('manageSettings', CompanyProfile::class);

        return view('admin.settings', [
            'reservedSubdomains' => Setting::getValue('reserved_subdomains', ''),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Setting::query()->updateOrCreate(
            ['key' => 'reserved_subdomains'],
            ['value' => implode(PHP_EOL, $request->reservedLines())],
        );

        return back()->with('success', 'Settings saved.');
    }
}
