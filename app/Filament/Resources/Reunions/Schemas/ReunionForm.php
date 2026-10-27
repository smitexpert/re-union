<?php

namespace App\Filament\Resources\Reunions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReunionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('event_date')
                    ->required(),
                DatePicker::make('registration_deadline'),
                TextInput::make('registration_fee')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('৳'),
                Section::make('Service Charge')
                    ->schema([
                        Select::make('charge_type')
                            ->label('Charge type')
                            ->options([
                                null => 'No service charge',
                                'fixed' => 'Fixed amount (BDT)',
                                'percentage' => 'Percentage of subtotal',
                            ])
                            ->default(null)
                            ->live()
                            ->required(),
                        TextInput::make('charge_value')
                            ->label('Charge value')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn ($get) => $get('charge_type') !== null && $get('charge_type') !== '')
                            ->required(fn ($get) => $get('charge_type') !== null && $get('charge_type') !== '')
                            ->prefix(fn ($get) => $get('charge_type') === 'fixed' ? '৳' : null)
                            ->suffix(fn ($get) => $get('charge_type') === 'percentage' ? '%' : null),
                    ])
                    ->columns(2),
                Toggle::make('is_active')
                    ->required()
                    ->default(true),
            ]);
    }
}
