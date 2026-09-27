<?php

namespace App\Filament\Resources\Reunions\RelationManagers;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\ReunionRegistration;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $recordTitleAttribute = 'registration_code';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->relationship('student', 'full_name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('tshirt_size')
                    ->maxLength(10),
                TextInput::make('guest_count')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                TextInput::make('total_fee')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('৳'),
                Select::make('registration_status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending')
                    ->required(),
                Select::make('payment_status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                        'refunded' => 'Refunded',
                    ])
                    ->default('unpaid')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull()
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('registration_code')
            ->columns([
                TextColumn::make('registration_code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('tshirt_size')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('guest_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_fee')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('registration_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'warning',
                        'refunded' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('registration_status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('payment_status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordUrl(fn (ReunionRegistration $record): string => RegistrationResource::getUrl('view', ['record' => $record]))
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
