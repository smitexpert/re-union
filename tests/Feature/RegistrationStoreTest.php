<?php

namespace Tests\Feature;

use App\Models\Reunion;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression test for the bug where the registration controller failed to
 * persist the photo upload and the student row when the frontend sent the
 * file under `student[photo]` (the actual shape the Wizard builds).
 *
 * Before the fix:
 *   - $request->file('photo') returned null, so the file was never moved
 *     from /tmp to storage/app/public/students.
 *   - The raw UploadedFile was passed through Student::updateOrCreate, so
 *     the photo column received an unstringable value and the transaction
 *     rolled back — silently.
 *
 * After the fix:
 *   - $request->file('student.photo') returns the file.
 *   - 'photo' is unset from $studentData before updateOrCreate runs.
 *   - The student row is created and the photo lands in storage.
 */
class RegistrationStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        return [
            'student' => [
                'full_name' => 'Md Shanewyz Molla',
                'phone' => '03994387497',
                'graduation_year' => 2020,
                'option' => 'Honors',
                'session' => '2016-17',
                'current_occupation' => 'Software Engineer',
                'present_address' => 'House 4, Road 7, Block C, Dhaka Uddan, Mohammadpur',
                'permanent_address' => 'Village: Mollah Bari, Upazila: Sadar',
                'office_address' => 'Level 4, BCS Tower, Dhaka',
            ],
            'tshirt_size' => 'L',
            'guest_count_adult' => 0,
            'guest_count_child' => 0,
            'notes' => null,
        ];
    }

    public function test_photo_uploaded_under_student_key_is_persisted(): void
    {
        Storage::fake('public');

        $reunion = Reunion::create([
            'title' => 'Test Reunion',
            'event_date' => now()->addDays(30)->toDateString(),
            'registration_deadline' => now()->addDays(7)->toDateString(),
            'is_active' => true,
            'registration_fee' => 500,
        ]);

        $photo = UploadedFile::fake()->image('headshot.jpg', 600, 600)->size(500);

        // Mirror exactly what the React wizard sends via buildFormData:
        // nested fields use bracket notation, including the file.
        $payload = $this->basePayload();
        $payload['student']['photo'] = $photo;

        $response = $this->post('/register', $payload);

        // The controller redirects to the payments page on success. If the
        // bug were still present, the controller's catch block would redirect
        // back with an error instead.
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $student = Student::where('phone', '03994387497')->first();
        $this->assertNotNull($student, 'Student row should have been persisted.');
        $this->assertSame('Md Shanewyz Molla', $student->full_name);
        $this->assertSame('Honors', $student->option);

        $this->assertNotEmpty(
            $student->photo,
            'Photo path should have been saved to the student row.'
        );

        Storage::disk('public')->assertExists($student->photo);
    }

    /**
     * The photo the browser uploads is resized in the browser, but the server
     * still has to accept a photo that is larger than the old 2 MB cap --
     * otherwise nginx answers the submission with a 413 before this
     * validation ever runs.
     */
    public function test_registration_accepts_a_photo_larger_than_two_megabytes(): void
    {
        $reunion = Reunion::create([
            'title' => 'Test Reunion',
            'event_date' => now()->addDays(30)->toDateString(),
            'registration_deadline' => now()->addDays(7)->toDateString(),
            'is_active' => true,
            'registration_fee' => 500,
        ]);

        $payload = $this->basePayload();
        // 3 MB: above the old 2 MB cap, inside the 5 MB the app accepts.
        $payload['student']['photo'] = UploadedFile::fake()->image('headshot.jpg', 600, 600)->size(3000);

        $response = $this->post('/register', $payload);

        $response->assertSessionDoesntHaveErrors(['student.photo']);
    }

    public function test_registration_rejects_a_photo_larger_than_five_megabytes(): void
    {
        $reunion = Reunion::create([
            'title' => 'Test Reunion',
            'event_date' => now()->addDays(30)->toDateString(),
            'registration_deadline' => now()->addDays(7)->toDateString(),
            'is_active' => true,
            'registration_fee' => 500,
        ]);

        $payload = $this->basePayload();
        $payload['student']['photo'] = UploadedFile::fake()->image('headshot.jpg', 600, 600)->size(6000);

        $response = $this->post('/register', $payload);

        $response->assertSessionHasErrors(['student.photo']);
    }

    public function test_registration_rejects_missing_photo(): void
    {
        $reunion = Reunion::create([
            'title' => 'Test Reunion',
            'event_date' => now()->addDays(30)->toDateString(),
            'registration_deadline' => now()->addDays(7)->toDateString(),
            'is_active' => true,
            'registration_fee' => 500,
        ]);

        $payload = $this->basePayload();

        $response = $this->post('/register', $payload);

        $response->assertSessionHasErrors(['student.photo']);
    }

    public function test_registration_rejects_missing_tshirt_size(): void
    {
        $reunion = Reunion::create([
            'title' => 'Test Reunion',
            'event_date' => now()->addDays(30)->toDateString(),
            'registration_deadline' => now()->addDays(7)->toDateString(),
            'is_active' => true,
            'registration_fee' => 500,
        ]);

        $payload = $this->basePayload();
        unset($payload['tshirt_size']);

        $response = $this->post('/register', $payload);

        $response->assertSessionHasErrors(['tshirt_size']);
    }

    public function test_registration_rejects_missing_required_student_fields(): void
    {
        $reunion = Reunion::create([
            'title' => 'Test Reunion',
            'event_date' => now()->addDays(30)->toDateString(),
            'registration_deadline' => now()->addDays(7)->toDateString(),
            'is_active' => true,
            'registration_fee' => 500,
        ]);

        $payload = $this->basePayload();
        $payload['student'] = array_merge($payload['student'], [
            'option' => null,
            'session' => null,
            'current_occupation' => null,
            'present_address' => null,
            'permanent_address' => null,
            'office_address' => null,
        ]);

        $response = $this->post('/register', $payload);

        // permanent_address and office_address are now optional — only the
        // other student fields are expected to fail validation here.
        $response->assertSessionHasErrors([
            'student.option',
            'student.session',
            'student.current_occupation',
            'student.present_address',
        ]);
        $response->assertSessionDoesntHaveErrors([
            'student.permanent_address',
            'student.office_address',
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function supportedOptions(): array
    {
        return [
            'honors' => ['Honors'],
            'masters' => ['Masters'],
        ];
    }

    #[DataProvider('supportedOptions')]
    public function test_registration_persists_the_selected_option(string $option): void
    {
        Storage::fake('public');

        $reunion = Reunion::create([
            'title' => 'Test Reunion',
            'event_date' => now()->addDays(30)->toDateString(),
            'registration_deadline' => now()->addDays(7)->toDateString(),
            'is_active' => true,
            'registration_fee' => 500,
        ]);

        $payload = $this->basePayload();
        $payload['student']['option'] = $option;
        $payload['student']['photo'] = UploadedFile::fake()->image('headshot.jpg', 600, 600)->size(500);

        $response = $this->post('/register', $payload);

        $response->assertSessionHasNoErrors();

        $student = Student::where('phone', '03994387497')->first();
        $this->assertNotNull($student, 'Student row should have been persisted.');
        $this->assertSame($option, $student->option);
    }

    public function test_registration_rejects_an_unsupported_option(): void
    {
        $reunion = Reunion::create([
            'title' => 'Test Reunion',
            'event_date' => now()->addDays(30)->toDateString(),
            'registration_deadline' => now()->addDays(7)->toDateString(),
            'is_active' => true,
            'registration_fee' => 500,
        ]);

        $payload = $this->basePayload();
        $payload['student']['option'] = 'PhD';

        $response = $this->post('/register', $payload);

        $response->assertSessionHasErrors(['student.option']);
    }
}
