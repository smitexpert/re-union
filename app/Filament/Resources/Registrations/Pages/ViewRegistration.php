<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Filament\Resources\Reunions\ReunionResource;
use App\Models\ReunionRegistration;
use App\Services\Sms\SendRegistrationSms;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewRegistration extends EditRecord
{
    protected static string $resource = RegistrationResource::class;

    /**
     * Snapshot of the registration's payment_status captured before save
     * runs, so afterSave() can detect transitions. Captured in
     * mutateFormDataBeforeSave() because Eloquent syncs the model's
     * `original` array once $record->update() has been called.
     */
    private ?string $previousPaymentStatus = null;

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
            Action::make('resendSms')
                ->label('Resend Confirmation SMS')
                ->icon(Heroicon::PaperAirplane)
                ->color('gray')
                ->visible(fn () => $this->getRecord()->payment_status === 'paid')
                ->requiresConfirmation()
                ->modalHeading('Resend confirmation SMS?')
                ->modalDescription(fn () => 'Send the registration-confirmed SMS again to '.($this->getRecord()->student?->phone ?? '(no phone on file)').'.')
                ->action(function () {
                    /** @var ReunionRegistration $record */
                    $record = $this->getRecord();
                    $log = app(SendRegistrationSms::class)->send(
                        $record->loadMissing(['reunion', 'student', 'guests']),
                        'manual_resend'
                    );

                    Notification::make()
                        ->title($log->status === 'sent' ? "SMS sent to {$log->phone}" : 'SMS send failed')
                        ->body($log->status === 'sent' ? null : $log->error_message)
                        ->status($log->status === 'sent' ? 'success' : 'warning')
                        ->send();
                }),
        ];
    }

    protected function afterSave(): void
    {
        /** @var ReunionRegistration $record */
        $record = $this->getRecord()->loadMissing(['reunion', 'student', 'guests']);

        $new = $record->payment_status;
        $old = $this->previousPaymentStatus;

        if ($new === $old) {
            return;
        }

        $trigger = match (true) {
            $new === 'paid' => 'payment_paid',
            $new === 'refunded' => 'payment_refunded',
            default => null,
        };

        if ($trigger === null) {
            return;
        }

        app(SendRegistrationSms::class)->send($record, $trigger);
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
                        TextEntry::make('student.option')
                            ->label('Option')
                            ->badge()
                            ->placeholder('—'),
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
        // Snapshot the prior payment_status before Eloquent syncs `original`
        // on update, so afterSave() can detect a transition.
        $this->previousPaymentStatus = $this->getRecord()->payment_status;

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
