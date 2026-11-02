<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentWorkshop;
use App\Http\Controllers\Controller;
use App\Services\Advisor\AdvisorAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    use ResolvesCurrentWorkshop;

    private const PERIODS = [7, 30, 90];

    public function __construct(private readonly AdvisorAnalytics $analytics) {}

    public function index(Request $request): View
    {
        $workshop = $this->currentWorkshop($request);

        $days = in_array((int) $request->query('days'), self::PERIODS, true) ? (int) $request->query('days') : 30;

        return view('admin.analytics.index', [
            'workshop' => $workshop,
            'report' => $this->analytics->report($workshop, $days),
            'periods' => self::PERIODS,
        ]);
    }
}
