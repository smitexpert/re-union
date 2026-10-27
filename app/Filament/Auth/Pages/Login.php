<?php

namespace App\Filament\Auth\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Adds a "Forgot password?" link to the admin login form, which Filament's
 * stock login page does not provide on its own.
 */
class Login extends BaseLogin
{
    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('requestPasswordReset')
                ->label('Forgot password?')
                ->link()
                ->url(fn (): ?string => filament()->getRequestPasswordResetUrl()),
            ...parent::getFormActions(),
        ];
    }
}
