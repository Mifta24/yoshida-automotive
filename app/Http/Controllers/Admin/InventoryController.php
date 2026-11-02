<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentWorkshop;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ServiceInventory;
use App\Services\Reservation\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    use ResolvesCurrentWorkshop;

    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $workshop = $this->currentWorkshop($request);

        $serviceTypes = $workshop->serviceTypes()->orderBy('sort_order')->get();
        $serviceType = $serviceTypes->firstWhere('id', (int) $request->query('service')) ?? $serviceTypes->first();

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? CarbonImmutable::createFromFormat('!Y-m', $request->query('month'))
            : CarbonImmutable::today()->startOfMonth();

        $days = $serviceType
            ? ServiceInventory::where('service_type_id', $serviceType->id)
                ->whereBetween('service_date', [$month->startOfDay()->toDateTimeString(), $month->endOfMonth()->startOfDay()->toDateTimeString()])
                ->orderBy('service_date')
                ->get()
                ->keyBy(fn (ServiceInventory $day) => $day->service_date->toDateString())
            : collect();

        return view('admin.inventory.index', compact('workshop', 'serviceTypes', 'serviceType', 'month', 'days'));
    }

    public function update(Request $request): RedirectResponse
    {
        $workshop = $this->currentWorkshop($request);

        $data = $request->validate([
            'service_type_id' => ['required', 'integer'],
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now($workshop->timezone)->toDateString()],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'total_units' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
        ]);

        $serviceType = $workshop->serviceTypes()->findOrFail($data['service_type_id']);

        $written = $this->inventory->apply(
            $serviceType,
            CarbonImmutable::parse($data['from']),
            CarbonImmutable::parse($data['to']),
            isset($data['total_units']) ? (int) $data['total_units'] : null,
            isset($data['price']) ? (float) $data['price'] : null,
            isset($data['weekdays']) ? array_map('intval', $data['weekdays']) : null,
        );

        AuditLog::record($workshop->id, 'inventory.updated', "Changed {$written} day(s) of {$serviceType->name} ({$data['from']} to {$data['to']})", $serviceType, $request->user(), array_filter(['total_units' => $data['total_units'] ?? null, 'price' => $data['price'] ?? null], fn ($v) => $v !== null));

        return redirect()
            ->route('admin.inventory.index', ['service' => $serviceType->id, 'month' => substr($data['from'], 0, 7)])
            ->with('status', "Updated {$written} day(s) of {$serviceType->name}.");
    }
}
