<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The approved knowledge base the AI Advisor retrieves from. This is the
 * ONLY place workshop facts may come from — the model is never allowed to
 * answer a workshop-knowledge question from its own memory.
 */
#[Fillable([
    'workshop_id',
    'category',
    'title',
    'body',
    'translations',
    'tags',
    'image_url',
    'images',
    'details',
    'is_active',
    'sort_order',
])]
class WorkshopKnowledgeItem extends Model
{
    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_FACILITIES = 'facilities';

    public const CATEGORY_POLICIES = 'policies';

    public const CATEGORY_PRICE_LIST = 'price_list';

    public const CATEGORY_TRANSPORT = 'transport';

    public const CATEGORY_FAQ = 'faq';

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'tags' => 'array',
            'images' => 'array',
            'details' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * A short icon name for this entry, taken from its tags or title so the
     * facilities cards can show a matching symbol without another column.
     */
    public function iconName(): string
    {
        $haystack = Str::lower($this->title.' '.implode(' ', $this->tags ?? []));

        foreach ([
            'lounge' => ['lounge', 'ruang tunggu', 'waiting', 'kafe', 'cafe', '待合'],
            'bay' => ['bay', 'lift', 'booth', 'peralatan', 'equipment', '整備'],
            'parking' => ['parking', 'parkir', '駐車'],
            'price_list' => ['harga', 'price', 'paket', 'package', '料金'],
            'transport' => ['derek', 'towing', 'jemput', 'pickup', 'shuttle', 'レッカー'],
            'place' => ['nearby', 'terdekat', 'lokasi', 'location'],
            'wifi' => ['wifi', 'wi-fi', 'internet'],
        ] as $icon => $needles) {
            foreach ($needles as $needle) {
                if (Str::contains($haystack, $needle)) {
                    return $icon;
                }
            }
        }

        return 'star';
    }

    /**
     * The cover photo first, then the rest of the gallery, without repeats.
     *
     * @return list<string>
     */
    public function photoUrls(): array
    {
        return collect([$this->image_url, ...($this->images ?? [])])
            ->filter(fn ($url) => filled($url))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Opening hours as the workshop wrote them, e.g. "06:30–10:30".
     */
    public function openingHours(): ?string
    {
        $hours = $this->details['hours'] ?? null;

        return filled($hours) ? (string) $hours : null;
    }

    /**
     * The dishes or services the workshop listed for this entry.
     *
     * @return list<array{name: string, description: ?string, price: ?string}>
     */
    public function menuEntries(): array
    {
        return collect($this->details['menu'] ?? [])
            ->filter(fn ($entry) => filled($entry['name'] ?? null))
            ->map(fn ($entry) => [
                'name' => (string) $entry['name'],
                'description' => filled($entry['description'] ?? null) ? (string) $entry['description'] : null,
                'price' => filled($entry['price'] ?? null) ? (string) $entry['price'] : null,
            ])
            ->values()
            ->all();
    }

    public function translatedTitle(string $locale): string
    {
        $value = $this->translations[$locale]['title'] ?? null;

        return filled($value) ? $value : $this->title;
    }

    public function translatedBody(string $locale): string
    {
        $value = $this->translations[$locale]['body'] ?? null;

        return filled($value) ? $value : $this->body;
    }
}
