<x-admin-layout title="Services">
    <x-slot name="actions">
        <a href="{{ route('admin.service-types.create') }}" class="rounded-lg bg-stone-900 px-3 py-2 text-sm font-medium text-white hover:bg-stone-700">
            Add service type
        </a>
    </x-slot>

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Base price</th>
                    <th class="px-4 py-3">Duration</th>
                    <th class="px-4 py-3">Images</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($serviceTypes as $serviceType)
                    <tr>
                        <td class="px-4 py-3 font-medium text-stone-900">{{ $serviceType->name }}</td>
                        <td class="px-4 py-3">{{ $workshop->money($serviceType->base_price) }}</td>
                        <td class="px-4 py-3">{{ $serviceType->duration_minutes ? $serviceType->duration_minutes.' min' : '–' }}</td>
                        <td class="px-4 py-3">{{ $serviceType->images_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $serviceType->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $serviceType->is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.service-types.edit', $serviceType) }}" class="text-stone-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.service-types.destroy', $serviceType) }}" class="inline" onsubmit="return confirm('Delete this service type?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400">No service types yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
