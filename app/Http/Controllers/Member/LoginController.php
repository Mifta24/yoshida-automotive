<?php

namespace App\Http\Controllers\Member;

use App\Core\Member\Models\Member;
use App\Core\Member\Services\LoginCodeService;
use App\Core\Member\Support\MemberLabels;
use App\Http\Controllers\Concerns\ResolvesMemberPages;
use App\Http\Controllers\Controller;
use App\Models\Workshop;
use App\Services\Reservation\MemberBookings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Passwordless sign-in for a workshop's members: email in, six-digit code back.
 */
class LoginController extends Controller
{
    use ResolvesMemberPages;

    private const EMAIL_SESSION_KEY = 'member_login_email';

    public function __construct(
        private readonly LoginCodeService $codes,
        private readonly MemberBookings $memberBookings,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $workshop = $this->memberWorkshop($request);
        $locale = $this->memberLocale($request, $workshop);

        if ($this->signedInMember($workshop)) {
            return redirect()->route('member.account', ['workshopSlug' => $workshop->slug, 'lang' => $request->query('lang')]);
        }

        return view('member.login', ['workshop' => $workshop, 'locale' => $locale, 'labels' => MemberLabels::for($locale)]);
    }

    public function send(Request $request): RedirectResponse
    {
        $workshop = $this->memberWorkshop($request);
        $locale = $this->memberLocale($request, $workshop);
        $labels = MemberLabels::for($locale);

        $data = $request->validate(
            ['email' => ['required', 'email:rfc', 'max:120']],
            ['required' => $labels['invalid'], 'email' => $labels['invalid'], 'max' => $labels['invalid']],
        );

        $sent = $this->codes->issue($workshop, $data['email'], $locale);

        $request->session()->put(self::EMAIL_SESSION_KEY, Member::normalizeEmail($data['email']));

        return redirect()
            ->route('member.verify', ['workshopSlug' => $workshop->slug, 'lang' => $request->input('lang')])
            ->with('status', $sent ? MemberLabels::fill($labels['code_sent'], ['email' => Member::normalizeEmail($data['email'])]) : $labels['too_soon']);
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        $workshop = $this->memberWorkshop($request);
        $locale = $this->memberLocale($request, $workshop);
        $email = $request->session()->get(self::EMAIL_SESSION_KEY);

        if (! $email) {
            return redirect()->route('member.login', ['workshopSlug' => $workshop->slug, 'lang' => $request->query('lang')]);
        }

        return view('member.verify', ['workshop' => $workshop, 'locale' => $locale, 'labels' => MemberLabels::for($locale), 'email' => $email]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $workshop = $this->memberWorkshop($request);
        $locale = $this->memberLocale($request, $workshop);
        $labels = MemberLabels::for($locale);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:120'],
            'code' => ['required', 'digits:6'],
            'remember' => ['nullable', 'boolean'],
        ], ['required' => $labels['code_invalid'], 'digits' => $labels['code_invalid'], 'email' => $labels['invalid']]);

        $member = $this->codes->verify($workshop, $data['email'], $data['code'], $locale);

        if (! $member) {
            throw ValidationException::withMessages(['code' => $labels['code_invalid']])
                ->redirectTo(route('member.verify', ['workshopSlug' => $workshop->slug, 'lang' => $request->input('lang')]));
        }

        Auth::guard(Member::GUARD)->login($member, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->forget(self::EMAIL_SESSION_KEY);

        $this->memberBookings->adopt($member, $workshop);

        return redirect()
            ->route('member.account', ['workshopSlug' => $workshop->slug, 'lang' => $request->input('lang')])
            ->with('status', $labels['signed_in']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $workshop = $this->memberWorkshop($request);
        $labels = MemberLabels::for($this->memberLocale($request, $workshop, $request->user(Member::GUARD)));

        Auth::guard(Member::GUARD)->logout();
        $request->session()->regenerateToken();

        return redirect()
            ->route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $request->input('lang')])
            ->with('status', $labels['signed_out']);
    }

    private function signedInMember(Workshop $workshop): ?Member
    {
        $member = Auth::guard(Member::GUARD)->user();

        return $member && $member->belongsToTenant($workshop) ? $member : null;
    }
}
