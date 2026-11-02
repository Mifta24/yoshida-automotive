<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens a new workshop on the platform: the workshop itself (hidden from customers
 * until its owner publishes it) and the owner account that will fill it in.
 */
class WorkshopOnboarding
{
    /**
     * Slugs that already mean something else in the address bar.
     */
    public const RESERVED_SLUGS = ['up', 'admin', 'platform', 'narration', 'storage', 'build', 'images', 'api'];

    /**
     * @param  array{name: string, slug?: ?string, city?: ?string, country?: ?string, currency?: ?string, default_locale?: ?string, timezone?: ?string}  $workshop
     * @return array{workshop: Workshop, owner: User, password: ?string} the password is set only when a new account had to be created
     */
    public function create(array $workshop, string $ownerEmail, ?string $ownerName = null): array
    {
        return DB::transaction(function () use ($workshop, $ownerEmail, $ownerName): array {
            $created = Workshop::create([
                'name' => $workshop['name'],
                'slug' => $this->uniqueSlug($workshop['slug'] ?? $workshop['name']),
                'city' => $workshop['city'] ?? null,
                'country' => $workshop['country'] ?? null,
                'currency' => strtoupper($workshop['currency'] ?? 'IDR'),
                'default_locale' => $workshop['default_locale'] ?? 'id',
                'timezone' => $workshop['timezone'] ?? 'Asia/Jakarta',
                'public_status' => 'draft',
            ]);

            $owner = User::where('email', $ownerEmail)->first();
            $password = null;

            if (! $owner) {
                $password = Str::password(14, symbols: false);
                $owner = User::create(['name' => $ownerName ?: Str::headline(Str::before($ownerEmail, '@')), 'email' => $ownerEmail, 'password' => $password]);
            }

            $created->users()->syncWithoutDetaching([$owner->id => ['role' => WorkshopUser::ROLE_OWNER, 'status' => 'active']]);

            AuditLog::record($created->id, 'workshop.created', "Workshop {$created->name} was opened on the platform", $created, null, ['owner' => $owner->email]);

            return ['workshop' => $created, 'owner' => $owner, 'password' => $password];
        });
    }

    public function uniqueSlug(string $base): string
    {
        $base = Str::slug($base) ?: 'workshop';
        $slug = $base;
        $suffix = 2;

        while (in_array($slug, self::RESERVED_SLUGS, true) || Workshop::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
