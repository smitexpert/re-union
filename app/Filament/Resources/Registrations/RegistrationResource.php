<?php

namespace App\Filament\Resources\Registrations;

use App\Filament\Resources\Registrations\Pages\ViewRegistration;
use App\Filament\Resources\Registrations\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Reunions\ReunionResource;
use App\Models\ReunionRegistration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class RegistrationResource extends Resource
{
    protected static ?string $model = ReunionRegistration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $recordTitleAttribute = 'registration_code';

    protected static bool $shouldRegisterNavigation = false;

    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return ReunionResource::getUrl('index', [], $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewRegistration::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }
}
