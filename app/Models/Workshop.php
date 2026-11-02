<?php

namespace App\Models;

use App\Core\Member\Contracts\MemberTenant;
use App\Core\Member\Models\Member;
use App\Core\Member\Support\LoyaltyProgram;
use App\Core\Member\Support\LoyaltyTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'custom_domain',
    'description',
    'translations',
    'address',
    'city',
    'country',
    'latitude',
    'longitude',
    'phone',
    'whatsapp',
    'email',
    'timezone',
    'currency',
    'default_locale',
    'opening_time',
    'closing_time',
    'logo_path',
    'cover_path',
    'public_status',
    'settings',
    'features',
])]
class Workshop extends Model implements MemberTenant
{
    /**
     * A price as customers read it: ¥5,000 for yen, otherwise the currency code with dots.
     */
    public function money(float|string|null $amount): string
    {
        return $this->currency === 'JPY'
            ? '¥'.number_format((float) $amount, 0, '.', ',')
            : $this->currency.' '.number_format((float) $amount, 0, ',', '.');
    }

    use HasFactory, SoftDeletes;

    /**
     * Optional add-ons a workshop can be given; the platform admin switches them on.
     */
    public const FEATURE_MEMBER = 'member';

    /**
     * Points, tiers and member prices; only works together with the member add-on.
     */
    public const FEATURE_MEMBER_PLUS = 'member_plus';

    public const FEATURES = [self::FEATURE_MEMBER, self::FEATURE_MEMBER_PLUS];

    /**
     * How much a customer spends for one point until the workshop sets its own, by currency.
     */
    private const DEFAULT_SPEND_PER_POINT = ['IDR' => 10000, 'JPY' => 1000];

    private const FALLBACK_SPEND_PER_POINT = 10;

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'settings' => 'array',
            'features' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workshop_users')
            ->using(WorkshopUser::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * The people who answer for this workshop in the admin, and are told when a customer needs them.
     */
    public function notifiableStaff(): Collection
    {
        return $this->users()->wherePivot('status', 'active')->get();
    }

    public function serviceTypes(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }

    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(WorkshopKnowledgeItem::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function members(): MorphMany
    {
        return $this->morphMany(Member::class, 'tenant');
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function supportsMembers(): bool
    {
        return $this->hasFeature(self::FEATURE_MEMBER);
    }

    public function supportsLoyalty(): bool
    {
        return $this->supportsMembers() && $this->hasFeature(self::FEATURE_MEMBER_PLUS);
    }

    public function loyaltyProgram(): ?LoyaltyProgram
    {
        if (! $this->supportsLoyalty()) {
            return null;
        }

        return $this->loyaltySettings();
    }

    /**
     * The program as the workshop configured it, or the starter program if it has not yet.
     */
    public function loyaltySettings(): LoyaltyProgram
    {
        $configured = $this->setting('loyalty');
        $spend = self::DEFAULT_SPEND_PER_POINT[$this->currency] ?? self::FALLBACK_SPEND_PER_POINT;

        if (is_array($configured) && ($configured['tiers'] ?? []) !== []) {
            return LoyaltyProgram::fromArray($configured, $spend);
        }

        return new LoyaltyProgram((float) ($configured['spend_per_point'] ?? $spend), [
            new LoyaltyTier('Silver', 200, 5),
            new LoyaltyTier('Gold', 1000, 10),
        ]);
    }

    public function memberBrandName(): string
    {
        return $this->name;
    }

    public function memberDefaultLocale(): string
    {
        return $this->default_locale;
    }

    public function memberReplyTo(): ?string
    {
        return filled($this->email) ? $this->email : null;
    }

    public function translatedDescription(string $locale): ?string
    {
        $value = $this->translations[$locale]['description'] ?? null;

        return filled($value) ? $value : $this->description;
    }

    /**
     * A per-workshop option kept in the settings column (ai_model, ai_instructions).
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        $value = $this->settings[$key] ?? null;

        return filled($value) ? $value : $default;
    }

    public function logoUrl(): ?string
    {
        return filled($this->logo_path) ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function coverUrl(): ?string
    {
        return filled($this->cover_path) ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function isPublished(): bool
    {
        return $this->public_status === 'published';
    }

    public function publicUrl(): string
    {
        return $this->custom_domain
            ? 'https://'.$this->custom_domain
            : url('/'.$this->slug);
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workshop';
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()->where('slug', $slug)->exists() || in_array($slug, static::reservedSlugs(), true)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public static function reservedSlugs(): array
    {
        return [
            'admin', 'login', 'logout', 'register', 'pricing', 'dashboard',
            'api', 'terms', 'privacy', 'forgot-password', 'reset-password',
            'verify-email', 'confirm-password', 'storage', 'build', 'account',
        ];
    }
}
