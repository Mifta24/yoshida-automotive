<?php

namespace App\Services\Reservation;

use App\Models\ServiceInventory;
use App\Models\ServiceType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lets the workshop decide how many services it sells and at what price per day.
 * Everything the advisor and the reservation form quote comes from these rows.
 */
class InventoryService
{
    public const MAX_RANGE_DAYS = 366;

    /**
     * Sets the number of services for sale and/or the daily price on every day
     * of the range (optionally only on some weekdays). Days that do not exist
     * yet are created, which needs both values. Services already booked are never
     * taken away.
     *
     * @param  list<int>|null  $weekdays  0 (Sunday) to 6 (Saturday); null means every day
     * @return int how many days were written
     *
     * @throws ValidationException
     */
    public function apply(ServiceType $serviceType, CarbonImmutable $from, CarbonImmutable $to, ?int $totalUnits, ?float $price, ?array $weekdays = null): int
    {
        if ($totalUnits === null && $price === null) {
            throw ValidationException::withMessages(['total_units' => 'Enter the services for sale, the price, or both.']);
        }

        if ($from->diffInDays($to) >= self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => 'A range can cover at most '.self::MAX_RANGE_DAYS.' days.']);
        }

        $dates = collect();
        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            if ($weekdays === null || in_array($date->dayOfWeek, $weekdays, true)) {
                $dates->push($date);
            }
        }

        if ($dates->isEmpty()) {
            throw ValidationException::withMessages(['weekdays' => 'None of the selected weekdays falls inside that range.']);
        }

        return DB::transaction(function () use ($serviceType, $dates, $totalUnits, $price): int {
            $existing = ServiceInventory::where('service_type_id', $serviceType->id)
                ->whereIn('service_date', $dates->map(fn (CarbonImmutable $date) => $date->startOfDay()->toDateTimeString())->all())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (ServiceInventory $day) => $day->service_date->toDateString());

            $missing = $dates->reject(fn (CarbonImmutable $date) => $existing->has($date->toDateString()));

            if ($missing->isNotEmpty() && ($totalUnits === null || $price === null)) {
                throw ValidationException::withMessages(['price' => 'Some of those days have no availability yet, so both the services for sale and the price are needed (first: '.$missing->first()->toDateString().').']);
            }

            if ($totalUnits !== null) {
                $tooLow = $existing->filter(fn (ServiceInventory $day) => $day->booked_units > $totalUnits);

                if ($tooLow->isNotEmpty()) {
                    $list = $tooLow->take(3)->map(fn (ServiceInventory $day) => $day->service_date->toDateString().' ('.$day->booked_units.' booked)')->implode(', ');

                    throw ValidationException::withMessages(['total_units' => "Services for sale cannot be lower than the services already booked: {$list}."]);
                }
            }

            foreach ($dates as $date) {
                $day = $existing->get($date->toDateString()) ?? new ServiceInventory([
                    'service_type_id' => $serviceType->id,
                    'service_date' => $date->toDateString(),
                    'booked_units' => 0,
                ]);

                $day->fill(array_filter(['total_units' => $totalUnits, 'price' => $price], fn ($value) => $value !== null))->save();
            }

            return $dates->count();
        });
    }

    /**
     * Opens days up to $days ahead for every service type that already sells,
     * repeating the services for sale and the price of the same weekday a week
     * earlier. Returns how many days were added.
     */
    public function extend(ServiceType $serviceType, int $days): int
    {
        $last = ServiceInventory::where('service_type_id', $serviceType->id)->orderByDesc('service_date')->first();

        if (! $last) {
            return 0;
        }

        $horizon = CarbonImmutable::today()->addDays($days);
        $prices = ServiceInventory::where('service_type_id', $serviceType->id)
            ->whereDate('service_date', '>', $last->service_date->subDays(7)->toDateString())
            ->pluck('price', 'service_date')
            ->mapWithKeys(fn ($price, $date) => [CarbonImmutable::parse($date)->toDateString() => (float) $price])
            ->all();

        $rows = [];
        for ($date = CarbonImmutable::parse($last->service_date)->addDay(); $date->lte($horizon); $date = $date->addDay()) {
            $price = $prices[$date->subDays(7)->toDateString()] ?? (float) $last->price;
            $prices[$date->toDateString()] = $price;

            $rows[] = [
                'service_type_id' => $serviceType->id,
                'service_date' => $date->startOfDay()->toDateTimeString(),
                'total_units' => $last->total_units,
                'booked_units' => 0,
                'price' => $price,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            ServiceInventory::insertOrIgnore($chunk);
        }

        return count($rows);
    }
}
