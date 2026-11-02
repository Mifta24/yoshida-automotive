<?php

namespace App\Http\Controllers\Concerns;

use App\Core\Member\Models\Member;
use App\Core\Member\Support\MemberLabels;
use App\Http\Middleware\EnsureMembersEnabled;
use App\Models\Workshop;
use Illuminate\Http\Request;

trait ResolvesMemberPages
{
    /**
     * The workshop the `member.enabled` middleware already vetted for this request.
     */
    protected function memberWorkshop(Request $request): Workshop
    {
        return $request->attributes->get(EnsureMembersEnabled::WORKSHOP_ATTRIBUTE);
    }

    /**
     * An explicit ?lang= wins, then the member's own choice, then the workshop's language.
     */
    protected function memberLocale(Request $request, Workshop $workshop, ?Member $member = null): string
    {
        $requested = $request->input('lang', $request->query('lang'));

        if (MemberLabels::supports($requested)) {
            return $requested;
        }

        return MemberLabels::supports($member?->locale) ? $member->locale : $workshop->memberDefaultLocale();
    }
}
