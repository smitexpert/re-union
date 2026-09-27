<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Filament\Resources\Reunions\ReunionResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewRegistration extends EditRecord
{
    protected static string $resource = RegistrationResource::class;

    protected function getRedirectUrl(): string
    {
        return RegistrationResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Reunion')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->url(fn (): string => ReunionResource::getUrl('view', ['record' => $this->getRecord()->reunion])),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Student')
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('student.photo')
                            ->disk('public_uploads')
                            ->label('Photo')
                            ->imageSize(120)
                            ->circular()
                            ->columnSpanFull(),
                        TextEntry::make('student.full_name')
                            ->label('Name'),
                        TextEntry::make('student.phone')
                            ->label('Phone'),
                        TextEntry::make('student.graduation_year')
                            ->label('Graduation Year')
                            ->badge(),
                        TextEntry::make('student.department')
                            ->label('Department')
                            ->badge(),
                        TextEntry::make('student.session')
                            ->label('Session')
                            ->placeholder('—'),
                        TextEntry::make('student.current_occupation')
                            ->label('Occupation')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('student.present_address')
                            ->label('Present Address')
                            ->placeholder('—')
                            ->limit(80)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Registration')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('registration_code')
                            ->label('Code'),
                        TextEntry::make('tshirt_size')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('guest_count')
                            ->label('Number of Guests')
                            ->numeric(),
                        TextEntry::make('total_fee')
                            ->money('BDT'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Guests')
                    ->schema([
                        RepeatableEntry::make('guests')
                            ->hiddenLabel()
                            ->placeholder('No guests registered.')
                            ->schema([
                                TextEntry::make('guest_type')
                                    ->badge(),
                                TextEntry::make('fee')
                                    ->money('BDT'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull(),
                Section::make('Update Status')
                    ->schema([
                        Select::make('payment_status')
                            ->label('Payment Status')
                            ->options([
                                'unpaid' => 'Unpaid',
                                'paid' => 'Paid',
                                'refunded' => 'Refunded',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                $registrationStatus = match ($state) {
                                    'paid' => 'confirmed',
                                    'refunded' => 'cancelled',
                                    default => 'pending',
                                };
                                $set('registration_status', $registrationStatus);
                            }),
                        Select::make('registration_status')
                            ->label('Registration Status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $paymentStatus = $data['payment_status'] ?? $this->getRecord()->payment_status;

        // Auto-derive registration_status from payment_status:
        // paid -> confirmed, refunded -> cancelled, otherwise -> pending.
        $registrationStatus = match ($paymentStatus) {
            'paid' => 'confirmed',
            'refunded' => 'cancelled',
            default => 'pending',
        };

        // Keep the related Payment record(s) in sync with the chosen status.
        // 'paid'/'completed' both map to the existing `completed` payment row;
        // 'refunded' maps to `refunded` on the most recent payment.
        $paymentTableStatus = match ($paymentStatus) {
            'paid' => 'completed',
            'refunded' => 'refunded',
            default => 'pending',
        };

        $latestPayment = $this->getRecord()->payments()->latest('id')->first();

        if ($latestPayment) {
            $latestPayment->status = $paymentTableStatus;

            if ($paymentStatus === 'paid' && ! $latestPayment->paid_at) {
                $latestPayment->paid_at = now();
            }

            $latestPayment->save();
        }

        return [
            'registration_status' => $registrationStatus,
            'payment_status' => $paymentStatus,
        ];
    }
}
