<?php

namespace App\Filament\Resources\Reunions;

use App\Filament\Resources\Reunions\Pages\CreateReunion;
use App\Filament\Resources\Reunions\Pages\EditReunion;
use App\Filament\Resources\Reunions\Pages\ListReunions;
use App\Filament\Resources\Reunions\Pages\ViewReunion;
use App\Filament\Resources\Reunions\RelationManagers\GuestFeesRelationManager;
use App\Filament\Resources\Reunions\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Reunions\RelationManagers\RegistrationsRelationManager;
use App\Filament\Resources\Reunions\Schemas\ReunionForm;
use App\Filament\Resources\Reunions\Tables\ReunionsTable;
use App\Models\Reunion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReunionResource extends Resource
{
    protected static ?string $model = Reunion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ReunionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReunionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RegistrationsRelationManager::class,
            PaymentsRelationManager::class,
            GuestFeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReunions::route('/'),
            'create' => CreateReunion::route('/create'),
            'view' => ViewReunion::route('/{record}'),
            'edit' => EditReunion::route('/{record}/edit'),
        ];
    }
}
