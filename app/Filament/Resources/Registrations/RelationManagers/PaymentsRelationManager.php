<?php

namespace App\Filament\Resources\Registrations\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $recordTitleAttribute = 'transaction_id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->prefix('৳'),
                Select::make('payment_method')
                    ->options([
                        'bkash' => 'bKash',
                        'nagad' => 'Nagad',
                        'bank_transfer' => 'Bank Transfer',
                        'cash' => 'Cash',
                        'card' => 'Card',
                    ])
                    ->required(),
                TextInput::make('transaction_id')
                    ->maxLength(100),
                TextInput::make('send_from_mobile_number')
                    ->maxLength(20),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->default('pending')
                    ->required(),
                DateTimePicker::make('paid_at'),
                Textarea::make('notes')
                    ->columnSpanFull()
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('transaction_id')
            ->columns([
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
                TextColumn::make('notes')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
