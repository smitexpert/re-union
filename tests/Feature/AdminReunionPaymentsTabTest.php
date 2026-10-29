<?php

namespace Tests\Feature;

use App\Filament\Resources\Reunions\Pages\ViewReunion;
use App\Filament\Resources\Reunions\RelationManagers\PaymentsRelationManager;
use App\Models\Payment;
use App\Models\Reunion;
use App\Models\ReunionRegistration;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminReunionPaymentsTabTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function registrationFor(Reunion $reunion, string $studentName, ?string $registrationCode = null): ReunionRegistration
    {
        $student = Student::factory()->create(['full_name' => $studentName]);

        $registration = ReunionRegistration::factory()->create([
            'reunion_id' => $reunion->id,
            'student_id' => $student->id,
        ]);

        if ($registrationCode !== null) {
            $registration->registration_code = $registrationCode;
            $registration->save();
        }

        return $registration;
    }

    public function test_reunion_view_renders_payments_tab_beside_registrations_tab(): void
    {
        $reunion = Reunion::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ViewReunion::class, ['record' => $reunion->id])
            ->assertSee('Registrations')
            ->assertSee('Payments');
    }

    public function test_payments_tab_lists_payments_of_the_reunions_registrations(): void
    {
        $reunion = Reunion::factory()->create();
        $registration = $this->registrationFor($reunion, 'Ayesha Siddiqua');
        $payment = Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-ALPHA',
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $reunion,
                'pageClass' => ViewReunion::class,
            ])
            ->assertCanSeeTableRecords([$payment]);
    }

    public function test_payments_tab_lists_payments_of_every_registration_of_the_reunion(): void
    {
        $reunion = Reunion::factory()->create();
        $firstPayment = Payment::factory()->create([
            'registration_id' => $this->registrationFor($reunion, 'Ayesha Siddiqua')->id,
            'transaction_id' => 'TXN-ALPHA',
        ]);
        $secondPayment = Payment::factory()->create([
            'registration_id' => $this->registrationFor($reunion, 'Rakib Hasan')->id,
            'transaction_id' => 'TXN-BETA',
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $reunion,
                'pageClass' => ViewReunion::class,
            ])
            ->assertCanSeeTableRecords([$firstPayment, $secondPayment]);
    }

    public function test_payments_tab_excludes_payments_of_other_reunions(): void
    {
        $reunion = Reunion::factory()->create();
        $otherReunion = Reunion::factory()->create();
        $ownPayment = Payment::factory()->create([
            'registration_id' => $this->registrationFor($reunion, 'Ayesha Siddiqua')->id,
        ]);
        $otherPayment = Payment::factory()->create([
            'registration_id' => $this->registrationFor($otherReunion, 'Rakib Hasan')->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $reunion,
                'pageClass' => ViewReunion::class,
            ])
            ->assertCanSeeTableRecords([$ownPayment])
            ->assertCanNotSeeTableRecords([$otherPayment]);
    }

    public function test_payments_tab_searches_by_transaction_id(): void
    {
        $reunion = Reunion::factory()->create();
        $registration = $this->registrationFor($reunion, 'Ayesha Siddiqua');
        $matchingPayment = Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-ALPHA',
        ]);
        $otherPayment = Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-BETA',
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $reunion,
                'pageClass' => ViewReunion::class,
            ])
            ->searchTable('TXN-ALPHA')
            ->assertCanSeeTableRecords([$matchingPayment])
            ->assertCanNotSeeTableRecords([$otherPayment]);
    }

    public function test_payments_tab_searches_by_student_name(): void
    {
        $reunion = Reunion::factory()->create();
        $matchingPayment = Payment::factory()->create([
            'registration_id' => $this->registrationFor($reunion, 'Ayesha Siddiqua')->id,
        ]);
        $otherPayment = Payment::factory()->create([
            'registration_id' => $this->registrationFor($reunion, 'Rakib Hasan')->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $reunion,
                'pageClass' => ViewReunion::class,
            ])
            ->searchTable('Ayesha')
            ->assertCanSeeTableRecords([$matchingPayment])
            ->assertCanNotSeeTableRecords([$otherPayment]);
    }

    public function test_payments_tab_searches_by_registration_code(): void
    {
        $reunion = Reunion::factory()->create();
        $matchingRegistration = $this->registrationFor($reunion, 'Ayesha Siddiqua');
        $otherRegistration = $this->registrationFor($reunion, 'Rakib Hasan');
        $matchingPayment = Payment::factory()->create([
            'registration_id' => $matchingRegistration->id,
        ]);
        $otherPayment = Payment::factory()->create([
            'registration_id' => $otherRegistration->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $reunion,
                'pageClass' => ViewReunion::class,
            ])
            ->searchTable($matchingRegistration->registration_code)
            ->assertCanSeeTableRecords([$matchingPayment])
            ->assertCanNotSeeTableRecords([$otherPayment]);
    }

    public function test_payments_tab_sorts_by_registration_code(): void
    {
        $reunion = Reunion::factory()->create();
        $firstPayment = Payment::factory()->create([
            'registration_id' => $this->registrationFor($reunion, 'Ayesha Siddiqua', 'REG-ALPHA')->id,
        ]);
        $secondPayment = Payment::factory()->create([
            'registration_id' => $this->registrationFor($reunion, 'Rakib Hasan', 'REG-BETA')->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $reunion,
                'pageClass' => ViewReunion::class,
            ])
            ->sortTable('registration.registration_code', 'asc')
            ->assertCanSeeTableRecords([$firstPayment, $secondPayment], inOrder: true);
    }

    public function test_payments_tab_sorts_by_student_name(): void
    {
        $reunion = Reunion::factory()->create();
        $firstPayment = Payment::factory()->create([
            'registration_id' => $this->registrationFor($reunion, 'Ayesha Siddiqua')->id,
        ]);
        $secondPayment = Payment::factory()->create([
            'registration_id' => $this->registrationFor($reunion, 'Rakib Hasan')->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $reunion,
                'pageClass' => ViewReunion::class,
            ])
            ->sortTable('registration.student.full_name', 'asc')
            ->assertCanSeeTableRecords([$firstPayment, $secondPayment], inOrder: true);
    }

    public function test_payments_tab_filters_by_status(): void
    {
        $reunion = Reunion::factory()->create();
        $registration = $this->registrationFor($reunion, 'Ayesha Siddiqua');
        $completedPayment = Payment::factory()->create([
            'registration_id' => $registration->id,
            'status' => 'completed',
        ]);
        $pendingPayment = Payment::factory()->create([
            'registration_id' => $registration->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $reunion,
                'pageClass' => ViewReunion::class,
            ])
            ->filterTable('status', 'completed')
            ->assertCanSeeTableRecords([$completedPayment])
            ->assertCanNotSeeTableRecords([$pendingPayment]);
    }
}
