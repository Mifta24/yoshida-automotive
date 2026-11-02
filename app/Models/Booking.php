<?php

namespace App\Models;

use App\Core\Member\Models\Member;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'reference',
    'workshop_id',
    'service_type_id',
    'conversation_id',
    'member_id',
    'customer_name',
    'customer_email',
    'customer_phone',
    'contact_type',
    'drop_off_date',
    'pickup_date',
    'vehicle_model',
    'license_plate',
    'vehicle_count',
    'towing',
    'total_price',
    'discount_percent',
    'discount_amount',
    'status',
    'notes',
])]
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The visit took place: set by the system the day after a confirmed booking's pickup day.
     */
    public const STATUS_COMPLETED = 'completed';

    protected function casts(): array
    {
        return [
            'drop_off_date' => 'date',
            'pickup_date' => 'date',
            'towing' => 'boolean',
            'total_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class)->withTrashed();
    }

    public function internalNotes(): MorphMany
    {
        return $this->morphMany(InternalNote::class, 'notable')->latest();
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * A short, unambiguous, non-sequential code customers can quote to staff.
     */
    public static function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $reference = 'BK-'.collect(range(1, 6))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function days(): int
    {
        return (int) $this->drop_off_date->diffInDays($this->pickup_date) + 1;
    }
}
