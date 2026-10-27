<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_admin_can_create_a_user(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Reunion Admin',
                'email' => 'admin@reunion.test',
                'password' => 'secret-password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'admin@reunion.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('Reunion Admin', $user->name);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_user_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@reunion.test']);

        Livewire::actingAs($this->admin())
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Duplicate',
                'email' => 'taken@reunion.test',
                'password' => 'secret-password',
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertSame(1, User::where('email', 'taken@reunion.test')->count());
    }

    public function test_admin_can_edit_a_user_without_touching_the_password(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $originalPassword = $user->password;

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm([
                'name' => 'New Name',
                'email' => $user->email,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('New Name', $user->name);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_reset_password_action_sets_a_new_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->callAction('resetPassword', [
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_reset_password_action_rejects_mismatched_confirmation(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->callAction('resetPassword', [
                'password' => 'brand-new-password',
                'password_confirmation' => 'something-else',
            ])
            ->assertHasFormErrors(['password_confirmation' => 'same']);

        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_send_reset_link_action_notifies_the_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->callAction('sendResetLink')
            ->assertHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $admin->getKey()])
            ->assertActionHidden('delete');

        $this->assertDatabaseHas('users', ['id' => $admin->getKey()]);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->callAction('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $user->getKey()]);
    }

    public function test_user_list_shows_users(): void
    {
        User::factory()->create(['name' => 'Listed Admin']);

        Livewire::actingAs($this->admin())
            ->test(ListUsers::class)
            ->assertCanSeeTableRecords(User::where('name', 'Listed Admin')->get());
    }

    public function test_table_row_can_reset_a_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListUsers::class)
            ->callAction(
                TestAction::make('resetPassword')->table($user),
                [
                    'password' => 'table-set-password',
                    'password_confirmation' => 'table-set-password',
                ],
            )
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('table-set-password', $user->fresh()->password));
    }

    public function test_table_row_can_send_a_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListUsers::class)
            ->callAction(TestAction::make('sendResetLink')->table($user))
            ->assertHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_admin_login_page_links_to_the_password_reset_request(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Forgot password?')
            ->assertSee(route('filament.admin.auth.password-reset.request'), escape: false);
    }
}
