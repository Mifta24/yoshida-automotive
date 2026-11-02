<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentWorkshop;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ServiceType;
use App\Services\ImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceTypeController extends Controller
{
    use ResolvesCurrentWorkshop;

    public function __construct(private readonly ImageUploader $images) {}

    public function index(Request $request): View
    {
        $workshop = $this->currentWorkshop($request);

        $serviceTypes = $workshop->serviceTypes()->withCount('images')->orderBy('sort_order')->get();

        return view('admin.service-types.index', compact('workshop', 'serviceTypes'));
    }

    public function create(Request $request): View
    {
        $workshop = $this->currentWorkshop($request);

        return view('admin.service-types.form', [
            'workshop' => $workshop,
            'serviceType' => new ServiceType(['is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);
        $data = $this->validated($request);

        $serviceType = $workshop->serviceTypes()->create($data);
        $this->syncImages($serviceType, $request);
        AuditLog::record($workshop->id, 'service_type.created', "Created service type {$serviceType->name}", $serviceType, $request->user());

        return redirect()->route('admin.service-types.index')->with('status', "Service type \"{$serviceType->name}\" created.");
    }

    public function edit(Request $request, ServiceType $serviceType): View
    {
        $workshop = $this->currentWorkshop($request);
        abort_if($serviceType->workshop_id !== $workshop->id, 404);

        $serviceType->load('images');

        return view('admin.service-types.form', compact('workshop', 'serviceType'));
    }

    public function update(Request $request, ServiceType $serviceType): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);
        abort_if($serviceType->workshop_id !== $workshop->id, 404);

        $serviceType->update($this->validated($request, $serviceType));
        $this->syncImages($serviceType, $request);
        AuditLog::record($workshop->id, 'service_type.updated', "Updated service type {$serviceType->name}", $serviceType, $request->user());

        return redirect()->route('admin.service-types.index')->with('status', "Service type \"{$serviceType->name}\" updated.");
    }

    public function destroy(Request $request, ServiceType $serviceType): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);
        abort_if($serviceType->workshop_id !== $workshop->id, 404);

        $serviceType->delete();
        AuditLog::record($workshop->id, 'service_type.deleted', "Deleted service type {$serviceType->name}", $serviceType, $request->user());

        return redirect()->route('admin.service-types.index')->with('status', 'Service type deleted.');
    }

    private function validated(Request $request, ?ServiceType $serviceType = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'translations.en.name' => ['nullable', 'string'],
            'translations.en.description' => ['nullable', 'string'],
            'translations.ja.name' => ['nullable', 'string'],
            'translations.ja.description' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'vehicle_class' => ['nullable', 'string', 'max:50'],
            'warranty_included' => ['sometimes', 'boolean'],
            'towing_available' => ['sometimes', 'boolean'],
            'towing_price' => ['nullable', 'numeric', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'inclusions' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'new_images' => ['nullable', 'array', 'max:20'],
            'new_images.*.url' => ['nullable', 'url:http,https', 'max:2048'],
            'new_images.*.file' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.ImageUploader::MAX_KILOBYTES],
            'new_images.*.alt' => ['nullable', 'string', 'max:255'],
            'new_images.*.tags' => ['nullable', 'string', 'max:255'],
        ]);

        $inclusions = collect(explode(',', (string) ($data['inclusions'] ?? '')))
            ->map(fn ($a) => Str::of($a)->trim()->slug('_')->toString())
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $data['name'],
            'slug' => $serviceType?->slug ?? $this->uniqueSlug($request->user()->currentWorkshop(), $data['name']),
            'description' => $data['description'] ?? null,
            'translations' => [
                'en' => ['name' => $data['translations']['en']['name'] ?? null, 'description' => $data['translations']['en']['description'] ?? null],
                'ja' => ['name' => $data['translations']['ja']['name'] ?? null, 'description' => $data['translations']['ja']['description'] ?? null],
            ],
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'vehicle_class' => $data['vehicle_class'] ?? null,
            'warranty_included' => $request->boolean('warranty_included'),
            'towing_available' => $request->boolean('towing_available'),
            'towing_price' => $data['towing_price'] ?? null,
            'base_price' => $data['base_price'],
            'inclusions' => $inclusions,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function uniqueSlug($workshop, string $name): string
    {
        $base = Str::slug($name) ?: 'service';
        $slug = $base;
        $suffix = 1;

        while ($workshop->serviceTypes()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function syncImages(ServiceType $serviceType, Request $request): void
    {
        $deleteIds = collect($request->input('delete_images', []))->map(fn ($id) => (int) $id);
        if ($deleteIds->isNotEmpty()) {
            $serviceType->images()->whereIn('id', $deleteIds)->get()->each(function ($image) {
                $this->images->delete($image->image_path);
                $image->delete();
            });
        }

        $rows = collect($request->input('new_images', []));
        $files = collect($request->file('new_images', []));
        $sort = $serviceType->images()->max('sort_order') + 1;

        foreach ($rows->keys()->merge($files->keys())->unique() as $index) {
            $row = $rows->get($index, []);
            $file = $files->get($index)['file'] ?? null;

            if (! $file && ! filled($row['url'] ?? null)) {
                continue;
            }

            $serviceType->images()->create([
                'image_url' => $file ? null : $row['url'],
                'image_path' => $file ? $this->images->store($file, 'services') : null,
                'alt_text' => ($row['alt'] ?? null) ?: $serviceType->name,
                'tags' => collect(explode(',', $row['tags'] ?? ''))->map(fn ($t) => trim($t))->filter()->values()->all(),
                'sort_order' => $sort++,
            ]);
        }
    }
}
