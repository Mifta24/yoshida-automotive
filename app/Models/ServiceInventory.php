<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The only source of truth for availability and price. Stands in for a real
 * PMS/booking-engine adapter for V1 — the AI must reach this table (or its
 * future adapter) through a tool call, never answer from its own memory.
 */
#[Fillable([
    'service_type_id',
    'service_date',
    'total_units',
    'booked_units',
    'price',
])]
class ServiceInventory extends Model
{
    protected $table = 'service_inventory';

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function availableUnits(): int
    {
        return max(0, $this->total_units - $this->booked_units);
    }

    public function isAvailable(): bool
    {
        return $this->availableUnits() > 0;
    }
}
