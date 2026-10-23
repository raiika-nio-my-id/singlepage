<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function show(Request $request, CompanyProfile $companyProfile): View
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $this->authorize('update', $companyProfile);

        $companyProfile->loadMissing('user');

        $days = (int) $request->input('days', 30);
        if (! in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        $from  = now()->subDays($days - 1)->toDateString();
        $today = now()->toDateString();

        // Single grouped query — one round-trip regardless of date range.
        $rows = DB::table('page_view_records')
            ->selectRaw('viewed_on, COUNT(*) as visitors')
            ->where('company_profile_id', $companyProfile->id)
            ->where('viewed_on', '>=', $from)
            ->groupBy('viewed_on')
            ->orderBy('viewed_on')
            ->get()
            ->keyBy('viewed_on');

        // Zero-fill every day in the selected range (oldest → newest).
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date          = now()->subDays($i)->toDateString();
            $series[$date] = (int) ($rows[$date]->visitors ?? 0);
        }

        $todayCount = $series[$today] ?? 0;
        $total      = array_sum($series);
        $avg        = round($days > 0 ? $total / $days : 0, 1);
        $peak       = $series ? max($series) : 0;
        $peakDay    = ($peak > 0) ? array_search($peak, $series) : null;

        return view('admin.analytics', compact(
            'companyProfile', 'days', 'series',
            'todayCount', 'total', 'avg', 'peak', 'peakDay'
        ));
    }
}
