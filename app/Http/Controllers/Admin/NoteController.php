<?php

namespace App\Http\Controllers\Admin;

use App\Core\Member\Models\Member;
use App\Http\Controllers\Concerns\ResolvesCurrentWorkshop;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\HandoverRequest;
use App\Models\Workshop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    use ResolvesCurrentWorkshop;

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);

        $subject = match ($type) {
            'booking' => $this->booking($request, $workshop->id, $id),
            'handover' => $this->handover($request, $workshop->id, $id),
            'member' => $this->member($request, $workshop, $id),
            default => abort(404),
        };

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $subject->internalNotes()->create(['workshop_id' => $workshop->id, 'user_id' => $request->user()->id, 'body' => $data['body']]);
        AuditLog::record($workshop->id, 'note.added', 'Added an internal note', $subject, $request->user());

        return back()->with('status', 'Note saved. Customers cannot see it.');
    }

    private function booking(Request $request, int $workshopId, int $id): Booking
    {
        abort_unless($request->user()->canInWorkshop('bookings.view'), 403);

        return Booking::where('workshop_id', $workshopId)->findOrFail($id);
    }

    private function member(Request $request, Workshop $workshop, int $id): Member
    {
        abort_unless($request->user()->canInWorkshop('members') && $workshop->supportsMembers(), 403);

        return $workshop->members()->findOrFail($id);
    }

    private function handover(Request $request, int $workshopId, int $id): HandoverRequest
    {
        abort_unless($request->user()->canInWorkshop('handovers'), 403);

        return HandoverRequest::whereHas('conversation', fn ($q) => $q->where('workshop_id', $workshopId))->findOrFail($id);
    }
}
