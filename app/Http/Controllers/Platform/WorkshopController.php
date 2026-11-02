<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Workshop;
use App\Services\WorkshopOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkshopController extends Controller
{
    public function __construct(private readonly WorkshopOnboarding $onboarding) {}

    public function index(): View
    {
        $workshops = Workshop::withCount(['serviceTypes', 'bookings'])->with('users')->orderBy('name')->get();

        return view('platform.workshops.index', compact('workshops'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(WorkshopOnboarding::RESERVED_SLUGS), Rule::unique('workshops', 'slug')],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'default_locale' => ['required', Rule::in(['id', 'en', 'ja'])],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->onboarding->create($data, $data['owner_email'], $data['owner_name'] ?? null);

        $message = "Workshop \"{$result['workshop']->name}\" created at /{$result['workshop']->slug}. It stays hidden until it is published.";

        if ($result['password']) {
            $message .= " A new owner account was created for {$result['owner']->email} — temporary password: {$result['password']} (shown only once).";
        }

        return redirect()->route('platform.workshops.index')->with('status', $message);
    }

    public function updateAiModel(Request $request, Workshop $workshop): RedirectResponse
    {
        $data = $request->validate(['ai_model' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:\/-]+$/']]);

        $workshop->update(['settings' => [...($workshop->settings ?? []), 'ai_model' => filled($data['ai_model'] ?? null) ? $data['ai_model'] : null]]);
        AuditLog::record($workshop->id, 'workshop.ai_model', 'Changed the AI model of the advisor', $workshop, $request->user(), ['ai_model' => $data['ai_model'] ?? null]);

        return back()->with('status', "{$workshop->name} now uses ".($workshop->setting('ai_model') ?? 'the default AI model').'.');
    }

    public function updateFeature(Request $request, Workshop $workshop): RedirectResponse
    {
        $data = $request->validate([
            'feature' => ['required', Rule::in(Workshop::FEATURES)],
            'enabled' => ['required', 'boolean'],
        ]);

        $features = collect($workshop->features ?? [])->reject(fn (string $feature) => $feature === $data['feature']);

        if ($request->boolean('enabled')) {
            $features->push($data['feature']);
        }

        $workshop->update(['features' => $features->unique()->values()->all()]);

        $state = $request->boolean('enabled') ? 'on' : 'off';
        AuditLog::record($workshop->id, 'workshop.feature', "Switched the {$data['feature']} add-on {$state}", $workshop, $request->user(), ['feature' => $data['feature'], 'enabled' => $request->boolean('enabled')]);

        return back()->with('status', "The {$data['feature']} add-on is now {$state} for {$workshop->name}.");
    }

    public function updateStatus(Request $request, Workshop $workshop): RedirectResponse
    {
        $data = $request->validate(['public_status' => ['required', Rule::in(['draft', 'published'])]]);

        $workshop->update(['public_status' => $data['public_status']]);
        AuditLog::record($workshop->id, 'workshop.status', "Workshop is now {$data['public_status']}", $workshop, $request->user());

        return back()->with('status', "{$workshop->name} is now {$data['public_status']}.");
    }
}
