<?php

namespace App\Filament\Resources\Reunions\RelationManagers;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\Payment;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $recordTitleAttribute = 'transaction_id';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('transaction_id')
            ->columns([
                TextColumn::make('registration.registration_code')
                    ->label('Registration')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('registration.student.full_name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed', 'paid', 'success' => 'success',
                        'pending' => 'warning',
                        'failed', 'cancelled' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('send_from_mobile_number')
                    ->label('Send From')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options([
                        'bkash' => 'bKash',
                        'nagad' => 'Nagad',
                        'bank_transfer' => 'Bank Transfer',
                        'cash' => 'Cash',
                        'card' => 'Card',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->defaultSort('paid_at', 'desc')
            ->recordUrl(fn (Payment $record): string => RegistrationResource::getUrl('view', ['record' => $record->registration_id]))
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
