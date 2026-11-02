<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentWorkshop;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    use ResolvesCurrentWorkshop;

    public function index(Request $request): View
    {
        $workshop = $this->currentWorkshop($request);

        $logs = AuditLog::where('workshop_id', $workshop->id)
            ->with('user')
            ->latest('id')
            ->paginate(40);

        return view('admin.activity.index', compact('workshop', 'logs'));
    }
}
