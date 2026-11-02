<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentWorkshop;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WorkshopKnowledgeItem;
use App\Services\ImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class KnowledgeItemController extends Controller
{
    use ResolvesCurrentWorkshop;

    public function __construct(private readonly ImageUploader $images) {}

    public function index(Request $request): View
    {
        $workshop = $this->currentWorkshop($request);

        $items = $workshop->knowledgeItems()->orderBy('category')->orderBy('sort_order')->get();

        return view('admin.knowledge-items.index', compact('workshop', 'items'));
    }

    public function create(Request $request): View
    {
        $workshop = $this->currentWorkshop($request);

        return view('admin.knowledge-items.form', [
            'workshop' => $workshop,
            'item' => new WorkshopKnowledgeItem(['category' => WorkshopKnowledgeItem::CATEGORY_GENERAL, 'is_active' => true]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);

        $item = $workshop->knowledgeItems()->create($this->validated($request));
        AuditLog::record($workshop->id, 'knowledge.created', "Created knowledge item {$item->title}", $item, $request->user());

        return redirect()->route('admin.knowledge-items.index')->with('status', "Knowledge item \"{$item->title}\" created.");
    }

    public function edit(Request $request, WorkshopKnowledgeItem $knowledgeItem): View
    {
        $workshop = $this->currentWorkshop($request);
        abort_if($knowledgeItem->workshop_id !== $workshop->id, 404);

        return view('admin.knowledge-items.form', [
            'workshop' => $workshop,
            'item' => $knowledgeItem,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, WorkshopKnowledgeItem $knowledgeItem): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);
        abort_if($knowledgeItem->workshop_id !== $workshop->id, 404);

        $knowledgeItem->update($this->validated($request));
        AuditLog::record($workshop->id, 'knowledge.updated', "Updated knowledge item {$knowledgeItem->title}", $knowledgeItem, $request->user());

        return redirect()->route('admin.knowledge-items.index')->with('status', "Knowledge item \"{$knowledgeItem->title}\" updated.");
    }

    public function destroy(Request $request, WorkshopKnowledgeItem $knowledgeItem): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);
        abort_if($knowledgeItem->workshop_id !== $workshop->id, 404);

        $knowledgeItem->delete();
        AuditLog::record($workshop->id, 'knowledge.deleted', "Deleted knowledge item {$knowledgeItem->title}", $knowledgeItem, $request->user());

        return redirect()->route('admin.knowledge-items.index')->with('status', 'Knowledge item deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', $this->categories())],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'translations.en.title' => ['nullable', 'string'],
            'translations.en.body' => ['nullable', 'string'],
            'translations.ja.title' => ['nullable', 'string'],
            'translations.ja.body' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'gallery' => ['nullable', 'string', 'max:4000'],
            'image_file' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.ImageUploader::MAX_KILOBYTES],
            'gallery_files' => ['nullable', 'array', 'max:8'],
            'gallery_files.*' => ['image', 'mimes:jpeg,png,webp', 'max:'.ImageUploader::MAX_KILOBYTES],
            'hours' => ['nullable', 'string', 'max:255'],
            'menu' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'category' => $data['category'],
            'title' => $data['title'],
            'body' => $data['body'],
            'translations' => [
                'en' => ['title' => $data['translations']['en']['title'] ?? null, 'body' => $data['translations']['en']['body'] ?? null],
                'ja' => ['title' => $data['translations']['ja']['title'] ?? null, 'body' => $data['translations']['ja']['body'] ?? null],
            ],
            'tags' => collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($t) => trim($t))->filter()->values()->all(),
            'image_url' => $request->file('image_file') ? $this->upload($request->file('image_file')) : ($data['image_url'] ?? null),
            'images' => collect($this->galleryUrls((string) ($data['gallery'] ?? '')))
                ->merge(collect($request->file('gallery_files', []))->map(fn ($file) => $this->upload($file)))
                ->unique()
                ->take(8)
                ->values()
                ->all(),
            'details' => $this->details($data),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function upload(UploadedFile $file): string
    {
        return $this->images->url($this->images->store($file, 'knowledge'));
    }

    /**
     * One photo address per line; anything that is not an http(s) URL is dropped.
     *
     * @return list<string>
     */
    private function galleryUrls(string $lines): array
    {
        return collect(preg_split('/\R/', $lines))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => filter_var($line, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $line))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Opening hours, plus a menu typed as one "Name | Description | Price" per line.
     *
     * @param  array<string, mixed>  $data
     * @return array{hours: ?string, menu: list<array{name: string, description: ?string, price: ?string}>}|null
     */
    private function details(array $data): ?array
    {
        $menu = collect(preg_split('/\R/', (string) ($data['menu'] ?? '')))
            ->map(fn ($line) => array_map('trim', explode('|', $line)))
            ->filter(fn ($parts) => ($parts[0] ?? '') !== '')
            ->map(fn ($parts) => ['name' => $parts[0], 'description' => $parts[1] ?? null, 'price' => $parts[2] ?? null])
            ->take(30)
            ->values()
            ->all();

        $hours = filled($data['hours'] ?? null) ? trim($data['hours']) : null;

        return $hours === null && $menu === [] ? null : ['hours' => $hours, 'menu' => $menu];
    }

    private function categories(): array
    {
        return [
            WorkshopKnowledgeItem::CATEGORY_GENERAL,
            WorkshopKnowledgeItem::CATEGORY_FACILITIES,
            WorkshopKnowledgeItem::CATEGORY_POLICIES,
            WorkshopKnowledgeItem::CATEGORY_PRICE_LIST,
            WorkshopKnowledgeItem::CATEGORY_TRANSPORT,
            WorkshopKnowledgeItem::CATEGORY_FAQ,
        ];
    }
}
