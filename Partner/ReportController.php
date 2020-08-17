<?php

namespace App\Http\Controllers\Partner;

use App\Support\Report;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ReportController extends Controller
{

    public function index(Request $request)
    {
        $partners = [];
        foreach (\App\Models\User::orderBy('name', 'asc')->get() as $partner) {
            $partners[$partner->id] = '[ ID: ' . $partner->id . '] ' . $partner->name;
        }

        $period = [
            Carbon::now()->subWeek(1)->toDateTimeString(),
            Carbon::now()->toDateTimeString(),
        ];

        $filter = [
            'period' => $request->input('filter.period', implode(' - ', $period)),
            'partner_id' => $request->input('filter.partner_id'),
        ];

        $reports = new Report($filter['period'], $filter['partner_id']);

        $reports_data = $reports->get();

        return view('partner.reports.index', ['filter' => $filter, 'partners' => $partners, 'reports_data' => $reports_data]);
    }
}
