<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentWorkshop;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\HandoverRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HandoverController extends Controller
{
    use ResolvesCurrentWorkshop;

    public function index(Request $request): View
    {
        $workshop = $this->currentWorkshop($request);

        $status = $request->query('status', HandoverRequest::STATUS_OPEN);

        $handovers = HandoverRequest::whereHas('conversation', fn ($q) => $q->where('workshop_id', $workshop->id))
            ->where('status', $status)
            ->with('conversation')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.handovers.index', compact('workshop', 'handovers', 'status'));
    }

    public function show(Request $request, HandoverRequest $handover): View
    {
        $workshop = $this->currentWorkshop($request);
        abort_if($handover->conversation->workshop_id !== $workshop->id, 404);

        $handover->load(['conversation.messages', 'internalNotes.author']);

        return view('admin.handovers.show', compact('workshop', 'handover'));
    }

    public function reply(Request $request, HandoverRequest $handover): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);
        abort_if($handover->conversation->workshop_id !== $workshop->id, 404);

        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $handover->conversation->messages()->create([
            'role' => ConversationMessage::ROLE_STAFF,
            'content' => $data['message'],
        ]);

        AuditLog::record($workshop->id, 'handover.reply', 'Replied to the customer', $handover, $request->user());

        return back()->with('status', 'Reply sent to the customer.');
    }

    public function resolve(Request $request, HandoverRequest $handover): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);
        abort_if($handover->conversation->workshop_id !== $workshop->id, 404);

        $handover->update(['status' => HandoverRequest::STATUS_RESOLVED, 'resolved_at' => now(), 'assigned_to' => $request->user()->id]);
        $handover->conversation->update(['status' => Conversation::STATUS_ACTIVE]);

        AuditLog::record($workshop->id, 'handover.resolved', 'Marked the handover resolved', $handover, $request->user());

        return redirect()->route('admin.handovers.index')->with('status', 'Handover resolved — the AI Advisor is back in the conversation.');
    }
}
