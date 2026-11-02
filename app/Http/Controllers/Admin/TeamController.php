<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentWorkshop;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Who works in the workshop's admin, and what each person may do there.
 */
class TeamController extends Controller
{
    use ResolvesCurrentWorkshop;

    private const ROLES = [WorkshopUser::ROLE_OWNER, WorkshopUser::ROLE_FRONT_DESK, WorkshopUser::ROLE_STAFF];

    public function index(Request $request): View
    {
        $workshop = $this->currentWorkshop($request);

        return view('admin.team.index', [
            'workshop' => $workshop,
            'members' => $workshop->users()->orderBy('name')->get(),
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        $user = User::where('email', $data['email'])->first();
        $password = null;

        if ($user && $workshop->users()->whereKey($user->id)->exists()) {
            return back()->withErrors(['email' => 'That person is already on the team.'])->withInput();
        }

        if (! $user) {
            $password = Str::password(14, symbols: false);
            $user = User::create(['name' => $data['name'] ?: Str::headline(Str::before($data['email'], '@')), 'email' => $data['email'], 'password' => $password]);
        }

        $workshop->users()->attach($user->id, ['role' => $data['role'], 'status' => 'active']);
        AuditLog::record($workshop->id, 'team.added', "Added {$user->email} as {$data['role']}", $workshop, $request->user(), ['user' => $user->email]);

        $message = "{$user->email} joined the team as {$data['role']}.";

        if ($password) {
            $message .= " A new account was created — temporary password: {$password} (shown only once; ask them to change it under Account).";
        }

        return back()->with('status', $message);
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);
        abort_unless($workshop->users()->whereKey($member->id)->exists(), 404);

        $data = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        if (! $this->keepsAnOwner($workshop, $member, $data['role'], $data['status'])) {
            return back()->withErrors(['role' => 'The workshop must keep at least one active owner.']);
        }

        $workshop->users()->updateExistingPivot($member->id, ['role' => $data['role'], 'status' => $data['status']]);
        AuditLog::record($workshop->id, 'team.updated', "Set {$member->email} to {$data['role']} ({$data['status']})", $workshop, $request->user(), ['user' => $member->email]);

        return back()->with('status', "{$member->name} is now {$data['role']} ({$data['status']}).");
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);
        abort_unless($workshop->users()->whereKey($member->id)->exists(), 404);

        if (! $this->keepsAnOwner($workshop, $member, null, null)) {
            return back()->withErrors(['role' => 'The workshop must keep at least one active owner.']);
        }

        $workshop->users()->detach($member->id);
        AuditLog::record($workshop->id, 'team.removed', "Removed {$member->email} from the team", $workshop, $request->user(), ['user' => $member->email]);

        return back()->with('status', "{$member->name} was removed from the team.");
    }

    /**
     * Whether an active owner would still remain after this member takes the
     * given role and status (null role means removal).
     */
    private function keepsAnOwner(Workshop $workshop, User $member, ?string $role, ?string $status): bool
    {
        $stillOwner = $role === WorkshopUser::ROLE_OWNER && $status === 'active';

        return $stillOwner || $workshop->users()
            ->wherePivot('role', WorkshopUser::ROLE_OWNER)
            ->wherePivot('status', 'active')
            ->where('users.id', '!=', $member->id)
            ->exists();
    }
}
