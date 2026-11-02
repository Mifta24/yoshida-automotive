<x-admin-layout title="Availability & prices">
    @if ($serviceTypes->isEmpty())
        <p class="rounded-xl border border-stone-200 bg-white px-4 py-8 text-center text-sm text-stone-400">Add a service type first.</p>
    @else
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
            <div class="flex flex-wrap gap-2">
                @foreach ($serviceTypes as $type)
                    <a href="{{ route('admin.inventory.index', ['service' => $type->id, 'month' => $month->format('Y-m')]) }}"
                        class="rounded-full px-3 py-1 {{ $type->id === $serviceType->id ? 'bg-stone-900 text-white' : 'border border-stone-300 bg-white text-stone-600' }}">{{ $type->name }}</a>
                @endforeach
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.inventory.index', ['service' => $serviceType->id, 'month' => $month->subMonth()->format('Y-m')]) }}" class="text-stone-600 hover:underline">‹ Previous</a>
                <span class="font-medium">{{ $month->format('F Y') }}</span>
                <a href="{{ route('admin.inventory.index', ['service' => $serviceType->id, 'month' => $month->addMonth()->format('Y-m')]) }}" class="text-stone-600 hover:underline">Next ›</a>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.inventory.update') }}" class="mb-6 rounded-xl border border-stone-200 bg-white p-4 text-sm">
            @csrf @method('PUT')
            <input type="hidden" name="service_type_id" value="{{ $serviceType->id }}">
            <p class="mb-3 font-medium text-stone-900">Change {{ $serviceType->name }} for a range of days</p>
            @if ($errors->any())
                <ul class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-red-700">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif
            <div class="grid gap-3 sm:grid-cols-4">
                <label class="block">From<input type="date" name="from" value="{{ old('from') }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
                <label class="block">To (last day)<input type="date" name="to" value="{{ old('to') }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
                <label class="block">Services for sale<input type="number" min="0" name="total_units" value="{{ old('total_units') }}" placeholder="unchanged" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
                <label class="block">Price per day ({{ $workshop->currency }})<input type="number" min="0" step="1000" name="price" value="{{ old('price') }}" placeholder="unchanged" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></label>
            </div>
            <fieldset class="mt-3 flex flex-wrap items-center gap-3 text-stone-600">
                <legend class="sr-only">Only on these days</legend>
                <span class="text-xs uppercase text-stone-400">Only on</span>
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $number => $day)
                    <label class="flex items-center gap-1"><input type="checkbox" name="weekdays[]" value="{{ $number }}" @checked(in_array($number, (array) old('weekdays', [])))> {{ $day }}</label>
                @endforeach
                <span class="text-xs text-stone-400">(none ticked = every day)</span>
            </fieldset>
            <p class="mt-3 text-xs text-stone-400">Set services for sale to 0 to close those days. It cannot go below the services already booked. Days not opened yet need both the services and the price.</p>
            <button type="submit" class="mt-3 rounded-lg bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700">Apply</button>
        </form>

        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                    <tr>
                        <th class="px-4 py-3">Day</th>
                        <th class="px-4 py-3">For sale</th>
                        <th class="px-4 py-3">Booked</th>
                        <th class="px-4 py-3">Free</th>
                        <th class="px-4 py-3">Price</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @for ($day = $month; $day->lte($month->endOfMonth()); $day = $day->addDay())
                        @php $slot = $days->get($day->toDateString()); @endphp
                        <tr @class(['bg-stone-50/60' => $day->isWeekend()])>
                            <td class="px-4 py-2">{{ $day->format('D, j M') }}</td>
                            @if ($slot)
                                <td class="px-4 py-2">{{ $slot->total_units }}</td>
                                <td class="px-4 py-2">{{ $slot->booked_units }}</td>
                                <td class="px-4 py-2 {{ $slot->availableUnits() === 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $slot->availableUnits() }}</td>
                                <td class="px-4 py-2">{{ number_format((float) $slot->price, 0, ',', '.') }}</td>
                            @else
                                <td colspan="4" class="px-4 py-2 text-stone-300">Not opened for sale</td>
                            @endif
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    @endif
</x-admin-layout>
