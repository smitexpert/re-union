<?php

namespace Tests\Feature;

use App\Filament\Resources\Reunions\Pages\ViewReunion;
use App\Filament\Resources\Reunions\RelationManagers\RegistrationsRelationManager;
use App\Models\Payment;
use App\Models\Reunion;
use App\Models\ReunionRegistration;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class AdminReunionRegistrationsPaymentFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function registrationFor(Reunion $reunion, string $studentName): ReunionRegistration
    {
        $student = Student::factory()->create(['full_name' => $studentName]);

        return ReunionRegistration::factory()->create([
            'reunion_id' => $reunion->id,
            'student_id' => $student->id,
        ]);
    }

    private function registrations(Reunion $reunion): array
    {
        $matched = $this->registrationFor($reunion, 'Ayesha Siddiqua');
        $unmatched = $this->registrationFor($reunion, 'Rakib Hasan');

        Payment::factory()->create([
            'registration_id' => $matched->id,
            'transaction_id' => 'TXN-ALPHA',
            'send_from_mobile_number' => '01711111111',
        ]);
        Payment::factory()->create([
            'registration_id' => $unmatched->id,
            'transaction_id' => 'TXN-BETA',
            'send_from_mobile_number' => '01722222222',
        ]);

        return [$matched, $unmatched];
    }

    private function table(Reunion $reunion): Testable
    {
        return Livewire::actingAs($this->admin())
            ->test(RegistrationsRelationManager::class, [
                'ownerRecord' => $reunion,
                'pageClass' => ViewReunion::class,
            ]);
    }

    public function test_filter_by_transaction_id_finds_the_registration_with_that_payment(): void
    {
        $reunion = Reunion::factory()->create();
        [$matched, $unmatched] = $this->registrations($reunion);

        $this->table($reunion)
            ->filterTable('payment', ['transaction_id' => 'TXN-ALPHA'])
            ->assertCanSeeTableRecords([$matched])
            ->assertCanNotSeeTableRecords([$unmatched]);
    }

    public function test_filter_by_transaction_id_matches_a_partial_value(): void
    {
        $reunion = Reunion::factory()->create();
        [$matched, $unmatched] = $this->registrations($reunion);

        $this->table($reunion)
            ->filterTable('payment', ['transaction_id' => 'ALPHA'])
            ->assertCanSeeTableRecords([$matched])
            ->assertCanNotSeeTableRecords([$unmatched]);
    }

    public function test_filter_by_send_from_mobile_number_finds_the_registration_with_that_payment(): void
    {
        $reunion = Reunion::factory()->create();
        [$matched, $unmatched] = $this->registrations($reunion);

        $this->table($reunion)
            ->filterTable('payment', ['send_from_mobile_number' => '01711111111'])
            ->assertCanSeeTableRecords([$matched])
            ->assertCanNotSeeTableRecords([$unmatched]);
    }

    public function test_filter_by_send_from_mobile_number_matches_a_partial_value(): void
    {
        $reunion = Reunion::factory()->create();
        [$matched, $unmatched] = $this->registrations($reunion);

        $this->table($reunion)
            ->filterTable('payment', ['send_from_mobile_number' => '0171111'])
            ->assertCanSeeTableRecords([$matched])
            ->assertCanNotSeeTableRecords([$unmatched]);
    }

    public function test_both_payment_filter_fields_must_be_satisfied_by_the_same_payment(): void
    {
        $reunion = Reunion::factory()->create();
        [$matched, $unmatched] = $this->registrations($reunion);

        $this->table($reunion)
            ->filterTable('payment', [
                'transaction_id' => 'TXN-ALPHA',
                'send_from_mobile_number' => '01711111111',
            ])
            ->assertCanSeeTableRecords([$matched])
            ->assertCanNotSeeTableRecords([$unmatched]);
    }

    public function test_payment_filter_fields_belonging_to_different_payments_do_not_match(): void
    {
        $reunion = Reunion::factory()->create();
        $this->registrations($reunion);

        $this->table($reunion)
            ->filterTable('payment', [
                'transaction_id' => 'TXN-ALPHA',
                'send_from_mobile_number' => '01722222222',
            ])
            ->assertCountTableRecords(0);
    }

    public function test_blank_payment_filter_lists_every_registration(): void
    {
        $reunion = Reunion::factory()->create();
        [$matched, $unmatched] = $this->registrations($reunion);

        $this->table($reunion)
            ->filterTable('payment', [
                'transaction_id' => '',
                'send_from_mobile_number' => '',
            ])
            ->assertCanSeeTableRecords([$matched, $unmatched]);
    }

    public function test_registrations_without_any_payment_stay_in_the_list(): void
    {
        $reunion = Reunion::factory()->create();
        $registration = $this->registrationFor($reunion, 'Ayesha Siddiqua');

        $this->table($reunion)
            ->assertCanSeeTableRecords([$registration]);
    }

    public function test_registrations_without_any_payment_stay_in_the_list_while_the_filter_is_blank(): void
    {
        $reunion = Reunion::factory()->create();
        $registration = $this->registrationFor($reunion, 'Ayesha Siddiqua');

        $this->table($reunion)
            ->filterTable('payment', [
                'transaction_id' => '',
                'send_from_mobile_number' => '',
            ])
            ->assertCanSeeTableRecords([$registration]);
    }

    public function test_payment_filter_does_not_match_registrations_of_other_reunions(): void
    {
        $reunion = Reunion::factory()->create();
        $otherReunion = Reunion::factory()->create();
        [$matched] = $this->registrations($reunion);

        $otherRegistration = $this->registrationFor($otherReunion, 'Tanvir Ahmed');
        Payment::factory()->create([
            'registration_id' => $otherRegistration->id,
            'transaction_id' => 'TXN-ALPHA',
            'send_from_mobile_number' => '01711111111',
        ]);

        $this->table($reunion)
            ->filterTable('payment', ['transaction_id' => 'TXN-ALPHA'])
            ->assertCanSeeTableRecords([$matched])
            ->assertCanNotSeeTableRecords([$otherRegistration]);
    }
}
