<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentWorkshop;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\HandoverRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesCurrentWorkshop;

    public function index(Request $request): View
    {
        $workshop = $this->currentWorkshop($request);

        $stats = [
            'service_types' => $workshop->serviceTypes()->count(),
            'knowledge_items' => $workshop->knowledgeItems()->count(),
            'pending_bookings' => $workshop->bookings()->where('status', Booking::STATUS_PENDING)->count(),
            'open_handovers' => HandoverRequest::whereHas('conversation', fn ($q) => $q->where('workshop_id', $workshop->id))
                ->where('status', HandoverRequest::STATUS_OPEN)
                ->count(),
        ];

        $recentBookings = $workshop->bookings()->latest()->take(5)->with('serviceType')->get();

        $openHandovers = HandoverRequest::whereHas('conversation', fn ($q) => $q->where('workshop_id', $workshop->id))
            ->where('status', HandoverRequest::STATUS_OPEN)
            ->latest()
            ->take(5)
            ->with('conversation')
            ->get();

        return view('admin.dashboard', [
            'workshop' => $workshop,
            'stats' => $stats,
            'recentBookings' => $recentBookings,
            'openHandovers' => $openHandovers,
        ]);
    }
}
