<x-admin-layout :title="'Booking '.$booking->reference">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_340px]">
        <div class="space-y-4">
            <div class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                <div class="flex items-center justify-between">
                    <p class="font-mono text-xs">{{ $booking->reference }}</p>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs',
                        'bg-amber-100 text-amber-700' => $booking->status === 'pending',
                        'bg-emerald-100 text-emerald-700' => in_array($booking->status, ['confirmed', 'completed'], true),
                        'bg-stone-100 text-stone-500' => $booking->status === 'cancelled',
                    ])>{{ ucfirst($booking->status) }}</span>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-3">
                    <div><dt class="text-xs text-stone-400">Customer</dt><dd>{{ $booking->customer_name }}</dd></div>
                    <div><dt class="text-xs text-stone-400">Contact</dt><dd>{{ $booking->contact_type ? ucfirst($booking->contact_type).': ' : '' }}{{ $booking->customer_phone ?? $booking->customer_email }}</dd></div>
                    <div><dt class="text-xs text-stone-400">Service</dt><dd>{{ $booking->vehicle_count }} × {{ $booking->serviceType->name }}</dd></div>
                    <div><dt class="text-xs text-stone-400">Vehicle</dt><dd>{{ $booking->vehicle_model }} · {{ $booking->license_plate }}{{ $booking->towing ? ' · towing' : '' }}</dd></div>
                    <div><dt class="text-xs text-stone-400">Visit</dt><dd>{{ $booking->drop_off_date->toFormattedDateString() }} → {{ $booking->pickup_date->toFormattedDateString() }} ({{ $booking->days() }} days)</dd></div>
                    @if ((float) $booking->discount_percent > 0)<div><dt class="text-xs text-stone-400">Member discount</dt><dd>{{ rtrim(rtrim(number_format((float) $booking->discount_percent, 2, '.', ''), '0'), '.') }}% (−{{ $workshop->money($booking->discount_amount) }})@if ($booking->member) · <a href="{{ route('admin.members.show', $booking->member_id) }}" class="underline">{{ $booking->member->displayName() }}</a>@endif</dd></div>@endif
                    <div><dt class="text-xs text-stone-400">Total</dt><dd>{{ $workshop->money($booking->total_price) }}</dd></div>
                </dl>
                @if ($booking->notes)<p class="mt-3 rounded-lg bg-stone-50 px-3 py-2 italic text-stone-500">Customer request: {{ $booking->notes }}</p>@endif
                @if ($booking->conversation_id)<p class="mt-3 text-xs text-stone-400">Made through the AI Advisor chat.</p>@endif
            </div>

            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <p class="text-sm font-medium text-stone-900">History</p>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($history as $log)
                        <li class="flex justify-between gap-3"><span>{{ $log->summary }} <span class="text-stone-400">— {{ $log->user?->name ?? ucfirst($log->actor) }}</span></span><span class="shrink-0 text-xs text-stone-400">{{ $log->created_at->format('j M H:i') }}</span></li>
                    @empty
                        <li class="text-stone-400">No changes yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <x-admin.notes :subject="$booking" type="booking" />
    </div>
</x-admin-layout>
