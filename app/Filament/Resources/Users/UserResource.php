<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'User Management';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Set a new password for a user and sign out their active sessions.
     */
    public static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset password')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Reset password')
            ->modalDescription('Set a new password for this user. Their active sessions will be signed out.')
            ->schema([
                TextInput::make('password')
                    ->label('New password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->maxLength(255),
                TextInput::make('password_confirmation')
                    ->label('Confirm new password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->same('password')
                    ->maxLength(255),
            ])
            ->action(function (array $data, User $record): void {
                $record->update(['password' => $data['password']]);

                self::forgetSessions($record);

                Notification::make()
                    ->title('Password reset')
                    ->body("The password for {$record->name} has been updated.")
                    ->success()
                    ->send();
            });
    }

    /**
     * Email the user a link where they can choose their own new password.
     */
    public static function sendResetLinkAction(): Action
    {
        return Action::make('sendResetLink')
            ->label('Send reset link')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Send password reset link?')
            ->modalDescription('Email this user a link where they can choose a new password.')
            ->action(function (User $record): void {
                $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
                    ['email' => $record->email],
                    function (User $user, string $token): void {
                        $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                        $notification->url = Filament::getResetPasswordUrl($token, $user);

                        $user->notify($notification);
                    },
                );

                Notification::make()
                    ->title($status === Password::RESET_LINK_SENT ? 'Reset link sent' : 'Could not send reset link')
                    ->body($status === Password::RESET_LINK_SENT
                        ? "A password reset link was emailed to {$record->email}."
                        : 'A reset link could not be created for this user.')
                    ->status($status === Password::RESET_LINK_SENT ? 'success' : 'danger')
                    ->send();
            });
    }

    /**
     * Drop the user's active sessions so a reset password takes effect
     * everywhere immediately. Only meaningful for the database session driver.
     */
    private static function forgetSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
