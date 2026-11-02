<?php

namespace Tests\Feature;

use App\Models\ServiceInventory;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Workshop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private Workshop $workshop;

    private ServiceType $service;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->service = $this->workshop->serviceTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1000000, 'is_active' => true]);
        $this->staff = User::factory()->create();
        $this->workshop->users()->attach($this->staff->id, ['role' => 'owner', 'status' => 'active']);
    }

    private function day(CarbonImmutable $date): ?ServiceInventory
    {
        return ServiceInventory::where('service_type_id', $this->service->id)->whereDate('service_date', $date->toDateString())->first();
    }

    public function test_staff_can_open_days_for_sale_and_the_calendar_shows_them(): void
    {
        $from = CarbonImmutable::today()->addDays(2);

        $this->actingAs($this->staff)
            ->put(route('admin.inventory.update'), ['service_type_id' => $this->service->id, 'from' => $from->toDateString(), 'to' => $from->addDays(2)->toDateString(), 'total_units' => 4, 'price' => 1250000])
            ->assertRedirect();

        $this->assertSame(3, ServiceInventory::count());
        $this->assertSame(4, $this->day($from)->total_units);
        $this->assertSame(1250000.0, (float) $this->day($from->addDay())->price);

        $this->actingAs($this->staff)
            ->get(route('admin.inventory.index', ['service' => $this->service->id, 'month' => $from->format('Y-m')]))
            ->assertOk()
            ->assertSee('1.250.000');
    }

    public function test_only_the_chosen_weekdays_change_and_booked_services_are_never_taken_away(): void
    {
        $monday = CarbonImmutable::today()->next('monday');
        foreach (range(0, 6) as $offset) {
            ServiceInventory::create(['service_type_id' => $this->service->id, 'service_date' => $monday->addDays($offset)->toDateString(), 'total_units' => 5, 'booked_units' => 2, 'price' => 1000000]);
        }

        $this->actingAs($this->staff)
            ->put(route('admin.inventory.update'), ['service_type_id' => $this->service->id, 'from' => $monday->toDateString(), 'to' => $monday->addDays(6)->toDateString(), 'price' => 1500000, 'weekdays' => [5, 6]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1500000.0, (float) $this->day($monday->addDays(4))->price);
        $this->assertSame(1500000.0, (float) $this->day($monday->addDays(5))->price);
        $this->assertSame(1000000.0, (float) $this->day($monday)->price);

        $this->actingAs($this->staff)
            ->put(route('admin.inventory.update'), ['service_type_id' => $this->service->id, 'from' => $monday->toDateString(), 'to' => $monday->toDateString(), 'total_units' => 1])
            ->assertSessionHasErrors('total_units');

        $this->assertSame(5, $this->day($monday)->total_units);
    }

    public function test_new_days_need_both_values_and_staff_cannot_touch_another_workshops_services(): void
    {
        $day = CarbonImmutable::today()->addDays(3)->toDateString();

        $this->actingAs($this->staff)
            ->put(route('admin.inventory.update'), ['service_type_id' => $this->service->id, 'from' => $day, 'to' => $day, 'total_units' => 2])
            ->assertSessionHasErrors('price');

        $other = Workshop::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $foreign = $other->serviceTypes()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'is_active' => true]);

        $this->actingAs($this->staff)
            ->put(route('admin.inventory.update'), ['service_type_id' => $foreign->id, 'from' => $day, 'to' => $day, 'total_units' => 2, 'price' => 1])
            ->assertNotFound();

        $this->assertSame(0, ServiceInventory::count());
    }

    public function test_the_extend_command_opens_more_days_repeating_the_weekly_price_pattern(): void
    {
        $today = CarbonImmutable::today();
        foreach (range(0, 6) as $offset) {
            ServiceInventory::create(['service_type_id' => $this->service->id, 'service_date' => $today->addDays($offset)->toDateString(), 'total_units' => 3, 'booked_units' => 2, 'price' => 1000000 + $offset * 10000]);
        }

        $this->artisan('inventory:extend', ['--days' => 20])->assertSuccessful();
        $this->artisan('inventory:extend', ['--days' => 20])->assertSuccessful();

        $this->assertSame(21, ServiceInventory::count());
        $later = $this->day($today->addDays(9));
        $this->assertSame(3, $later->total_units);
        $this->assertSame(0, $later->booked_units);
        $this->assertSame(1020000.0, (float) $later->price);
    }
}
