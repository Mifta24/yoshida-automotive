<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} — {{ auth()->user()?->currentWorkshop()?->name ?? config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 text-stone-900 antialiased">
    <div class="flex min-h-screen">
        <aside class="w-56 shrink-0 border-r border-stone-200 bg-white">
            <div class="border-b border-stone-200 px-4 py-4">
                @if (auth()->user()?->currentWorkshop()?->logoUrl())<img src="{{ auth()->user()->currentWorkshop()->logoUrl() }}" alt="" class="mb-2 h-8 max-w-full object-contain">@endif
                <p class="text-sm font-semibold text-stone-900">{{ auth()->user()?->currentWorkshop()?->name }}</p>
                <p class="text-xs text-stone-500">Admin Dashboard</p>
                @php $myWorkshops = auth()->user()?->workshops()->wherePivot('status', 'active')->get() ?? collect(); @endphp
                @if ($myWorkshops->count() > 1)
                    <form method="POST" action="{{ route('admin.switch-workshop') }}" class="mt-2">
                        @csrf
                        <select name="workshop_id" onchange="this.form.submit()" aria-label="Switch workshop" class="w-full rounded-lg border border-stone-300 bg-white px-2 py-1 text-xs">
                            @foreach ($myWorkshops as $option)<option value="{{ $option->id }}" @selected($option->id === auth()->user()->currentWorkshop()?->id)>{{ $option->name }}</option>@endforeach
                        </select>
                    </form>
                @endif
            </div>
            <nav class="space-y-1 px-2 py-4 text-sm">
                @php
                    $currentWorkshop = auth()->user()?->currentWorkshop();
                    $badges = $currentWorkshop ? [
                        'admin.bookings.index' => $currentWorkshop->bookings()->where('status', 'pending')->count(),
                        'admin.handovers.index' => \App\Models\HandoverRequest::where('status', 'open')->whereHas('conversation', fn ($q) => $q->where('workshop_id', $currentWorkshop->id))->count(),
                    ] : [];
                    $navItems = [
                        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'ability' => null],
                        ['route' => 'admin.service-types.index', 'label' => 'Services', 'ability' => 'content'],
                        ['route' => 'admin.inventory.index', 'label' => 'Availability', 'ability' => 'inventory'],
                        ['route' => 'admin.knowledge-items.index', 'label' => 'Knowledge base', 'ability' => 'content'],
                        ['route' => 'admin.bookings.index', 'label' => 'Bookings', 'ability' => 'bookings.view'],
                        ['route' => 'admin.handovers.index', 'label' => 'Handovers', 'ability' => 'handovers'],
                        ['route' => 'admin.members.index', 'label' => 'Members', 'ability' => 'members', 'feature' => 'member'],
                        ['route' => 'admin.loyalty.edit', 'label' => 'Points & tiers', 'ability' => 'settings', 'feature' => 'member_plus'],
                        ['route' => 'admin.analytics.index', 'label' => 'Analytics', 'ability' => 'analytics'],
                        ['route' => 'admin.team.index', 'label' => 'Team', 'ability' => 'team'],
                        ['route' => 'admin.settings.edit', 'label' => 'Workshop settings', 'ability' => 'settings'],
                        ['route' => 'admin.activity.index', 'label' => 'Activity', 'ability' => 'activity'],
                        ['route' => 'admin.account.edit', 'label' => 'My account', 'ability' => null],
                        ['route' => 'platform.workshops.index', 'label' => 'All workshops', 'ability' => 'platform'],
                    ];
                @endphp
                @foreach ($navItems as $item)
                    @continue(isset($item['feature']) && ! $currentWorkshop?->hasFeature($item['feature']))
                    @continue($item['ability'] && ! ($item['ability'] === 'platform' ? auth()->user()?->is_platform_admin : auth()->user()?->canInWorkshop($item['ability'])))
                    <a
                        href="{{ route($item['route']) }}"
                        class="block rounded-lg px-3 py-2 {{ request()->routeIs($item['route'].'*') ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-100' }}"
                    >{{ $item['label'] }}@if (($badges[$item['route']] ?? 0) > 0)<span class="ml-2 rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">{{ $badges[$item['route']] }}</span>@endif</a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('admin.logout') }}" class="border-t border-stone-200 p-2">
                @csrf
                <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-stone-500 hover:bg-stone-100">Log out</button>
            </form>
        </aside>

        <main class="flex-1 overflow-y-auto">
            <div class="mx-auto max-w-5xl px-6 py-8">
                @if (session('status'))
                    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="mb-6 flex items-center justify-between">
                    <h1 class="text-xl font-semibold text-stone-900">{{ $title ?? 'Dashboard' }}</h1>
                    {{ $actions ?? '' }}
                </div>

                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
