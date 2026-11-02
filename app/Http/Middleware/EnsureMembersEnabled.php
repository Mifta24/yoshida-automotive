<?php

namespace App\Http\Middleware;

use App\Models\Workshop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The member pages exist only for a published workshop that was given the member
 * add-on; for every other workshop they are simply not there.
 */
class EnsureMembersEnabled
{
    public const WORKSHOP_ATTRIBUTE = 'member_workshop';

    public function handle(Request $request, Closure $next): Response
    {
        $workshop = Workshop::where('slug', $request->route('workshopSlug'))->first();

        abort_if(! $workshop || ! $workshop->isPublished() || ! $workshop->supportsMembers(), 404);

        $request->attributes->set(self::WORKSHOP_ATTRIBUTE, $workshop);

        return $next($request);
    }
}
