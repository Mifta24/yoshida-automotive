<?php

namespace App\Http\Middleware;

use App\Core\Member\Models\Member;
use App\Models\Workshop;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a signed-in member through only on the workshop they belong to. A member
 * of another workshop — the guard is shared by the whole browser session — is
 * sent to this workshop's own sign-in page, never into someone else's account.
 */
class AuthenticateMember
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Workshop $workshop */
        $workshop = $request->attributes->get(EnsureMembersEnabled::WORKSHOP_ATTRIBUTE);
        $member = Auth::guard(Member::GUARD)->user();

        if (! $member || ! $member->belongsToTenant($workshop)) {
            return redirect()->route('member.login', ['workshopSlug' => $workshop->slug, 'lang' => $request->query('lang')]);
        }

        return $next($request);
    }
}
