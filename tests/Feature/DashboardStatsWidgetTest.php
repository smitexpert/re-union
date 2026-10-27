<?php

namespace Tests\Feature;

use App\Filament\Widgets\ReunionStatsOverview;
use App\Models\Payment;
use App\Models\Reunion;
use App\Models\ReunionRegistration;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Number;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_reports_registration_and_payment_totals(): void
    {
        $reunion = Reunion::factory()->create(['is_active' => true]);
        Reunion::factory()->create(['is_active' => false]);

        $confirmed = ReunionRegistration::factory()->create([
            'reunion_id' => $reunion->getKey(),
            'registration_status' => 'confirmed',
            'payment_status' => 'paid',
            'total_fee' => 500,
            'guest_count' => 2,
        ]);

        $pending = ReunionRegistration::factory()->create([
            'reunion_id' => $reunion->getKey(),
            'registration_status' => 'pending',
            'payment_status' => 'unpaid',
            'total_fee' => 700,
            'guest_count' => 3,
        ]);

        Payment::factory()->completed()->create([
            'registration_id' => $confirmed->getKey(),
            'amount' => 400,
        ]);

        // A failed payment must not count towards the amount paid.
        Payment::factory()->create([
            'registration_id' => $pending->getKey(),
            'amount' => 100,
            'status' => 'failed',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(ReunionStatsOverview::class)
            ->assertSee('Total Registrations')
            ->assertSee('1 confirmed · 1 pending')
            ->assertSee('Confirmed Registrations')
            ->assertSee('50% of all registrations')
            ->assertSee('Amount Paid')
            ->assertSee(Number::currency(400, 'BDT'))
            ->assertSee('of '.Number::currency(1200, 'BDT').' billed')
            ->assertSee('Outstanding')
            ->assertSee(Number::currency(800, 'BDT'))
            ->assertSee('1 awaiting payment')
            ->assertSee('Total Guests')
            ->assertSee('Active Reunions')
            ->assertSee('open for registration');
    }

    public function test_widget_handles_an_empty_database(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ReunionStatsOverview::class)
            ->assertSee('Total Registrations')
            ->assertSee('0 confirmed · 0 pending')
            ->assertSee('0% of all registrations')
            ->assertSee(Number::currency(0, 'BDT'))
            ->assertSee('0 awaiting payment')
            ->assertSee('open for registration');
    }

    public function test_dashboard_renders_the_stats_widget(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Dashboard::class)
            ->assertSee('Reunion Overview')
            ->assertSee('Total Registrations')
            ->assertSee('Amount Paid');
    }
}
