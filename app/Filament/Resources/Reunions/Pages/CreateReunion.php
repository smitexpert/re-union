<?php

namespace App\Filament\Resources\Reunions\Pages;

use App\Filament\Resources\Reunions\ReunionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReunion extends CreateRecord
{
    protected static string $resource = ReunionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
