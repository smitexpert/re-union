<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            UserResource::resetPasswordAction(),
            UserResource::sendResetLinkAction(),
            DeleteAction::make()
                // Deleting your own account would lock you out of the panel.
                ->visible(fn (User $record): bool => $record->getKey() !== auth()->id()),
        ];
    }
}
