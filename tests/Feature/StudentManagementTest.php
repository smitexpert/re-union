<?php

namespace Tests\Feature;

use App\Filament\Resources\Students\Pages\EditStudent;
use App\Models\Student;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_the_student_option(): void
    {
        $student = Student::factory()->create(['option' => 'Honors']);

        Livewire::actingAs(User::factory()->create())
            ->test(EditStudent::class, ['record' => $student->getKey()])
            ->fillForm(['option' => 'Masters'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Masters', $student->fresh()->option);
    }

    public function test_option_field_only_offers_the_supported_options(): void
    {
        $student = Student::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(EditStudent::class, ['record' => $student->getKey()])
            ->assertFormFieldExists(
                'option',
                fn (Select $field): bool => $field->getOptions() === ['Honors' => 'Honors', 'Masters' => 'Masters'],
            );
    }
}
