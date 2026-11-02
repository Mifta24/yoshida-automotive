<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkshopSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['workshop_id' => ['required', 'integer']]);

        $belongs = $request->user()->workshops()->wherePivot('status', 'active')->where('workshops.id', $data['workshop_id'])->exists();
        abort_unless($belongs, 403);

        $request->session()->put('current_workshop_id', (int) $data['workshop_id']);

        return redirect()->route('admin.dashboard');
    }
}
