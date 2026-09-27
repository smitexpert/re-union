<?php

namespace App\Filament\Resources\Reunions\Pages;

use App\Filament\Resources\Reunions\ReunionResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewReunion extends ViewRecord
{
    protected static string $resource = ReunionResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('event_date')
                    ->date(),
                TextEntry::make('registration_deadline')
                    ->date(),
                TextEntry::make('registration_fee')
                    ->money('BDT'),
                IconEntry::make('is_active')
                    ->boolean(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
