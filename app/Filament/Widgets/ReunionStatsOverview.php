<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Reunion;
use App\Models\ReunionRegistration;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class ReunionStatsOverview extends StatsOverviewWidget
{
    /**
     * Sorts ahead of Filament's default widgets (the account widget uses -3),
     * so the reunion figures lead the dashboard.
     */
    protected static ?int $sort = -4;

    protected ?string $heading = 'Reunion Overview';

    /**
     * Render the figures straight away instead of waiting for the
     * scroll-triggered lazy load, so they are visible on page load.
     */
    protected static bool $isLazy = false;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $registrations = ReunionRegistration::query()->count();
        $confirmed = ReunionRegistration::query()->where('registration_status', 'confirmed')->count();
        $pending = ReunionRegistration::query()->where('registration_status', 'pending')->count();

        $billed = (float) ReunionRegistration::query()->sum('total_fee');
        $paid = (float) Payment::query()->whereIn('status', ['completed', 'paid'])->sum('amount');
        $outstanding = max(0.0, $billed - $paid);
        $awaitingPayment = ReunionRegistration::query()->where('payment_status', 'unpaid')->count();

        $guests = (int) ReunionRegistration::query()->sum('guest_count');
        $activeReunions = Reunion::query()->where('is_active', true)->count();

        return [
            Stat::make('Total Registrations', $registrations)
                ->description($confirmed.' confirmed · '.$pending.' pending')
                ->icon(Heroicon::OutlinedUserGroup),
            Stat::make('Confirmed Registrations', $confirmed)
                ->description($this->percentage($confirmed, $registrations).'% of all registrations')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('success'),
            Stat::make('Amount Paid', $this->money($paid))
                ->description('of '.$this->money($billed).' billed')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Outstanding', $this->money($outstanding))
                ->description($awaitingPayment.' awaiting payment')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning'),
            Stat::make('Total Guests', $guests)
                ->description('guests accompanying students')
                ->icon(Heroicon::OutlinedUsers),
            Stat::make('Active Reunions', $activeReunions)
                ->description('open for registration')
                ->icon(Heroicon::OutlinedCalendarDays),
        ];
    }

    /**
     * Format an amount the same way the admin tables render money columns.
     */
    private function money(float $amount): string
    {
        return Number::currency($amount, 'BDT');
    }

    /**
     * Percentage of $part out of $total, guarding against division by zero.
     */
    private function percentage(int $part, int $total): string
    {
        if ($total === 0) {
            return '0';
        }

        return (string) round(($part / $total) * 100, 1);
    }
}
