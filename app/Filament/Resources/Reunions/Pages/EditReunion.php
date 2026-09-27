<?php

namespace App\Filament\Resources\Reunions\Pages;

use App\Filament\Resources\Reunions\ReunionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReunion extends EditRecord
{
    protected static string $resource = ReunionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
