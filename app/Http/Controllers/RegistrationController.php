<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegistrationRequest;
use App\Models\Reunion;
use App\Models\ReunionRegistration;
use App\Models\Student;
use App\Services\RegistrationFeeCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RegistrationController extends Controller
{
    public function show(): Response
    {
        $reunion = Reunion::currentOpenForRegistration();

        if (! $reunion) {
            return Inertia::render('Register', [
                'mode' => 'closed',
                'banglaMessage' => 'দুঃখিত, বর্তমানে কোনো রেজিস্ট্রেশন চলছে না। নতুন ইভেন্টের ঘোষণা এখানে প্রকাশ করা হবে।',
                'paymentLookupUrl' => '/payments',
            ]);
        }

        return Inertia::render('Register', [
            'mode' => 'open',
            'reunion' => [
                'id' => $reunion->id,
                'title' => $reunion->title,
                'event_date' => optional($reunion->event_date)->toDateString(),
                'registration_deadline' => optional($reunion->registration_deadline)->toDateString(),
                'registration_fee' => (float) $reunion->registration_fee,
            ],
            'feeBreakdown' => [
                'base' => (float) $reunion->registration_fee,
                'perHead' => RegistrationFeeCalculator::perHeadFees($reunion),
            ],
            'paymentLookupUrl' => '/payments',
        ]);
    }

    public function store(StoreRegistrationRequest $request): RedirectResponse
    {
        logger('Storing registration START', [
            'all' => $request->all(),
            'files' => array_keys($request->allFiles()),
        ]);
        $data = $request->validated();
        $studentData = $data['student'];

        // The frontend submits the photo as `student[photo]` (see Wizard's
        // buildFormData). PHP/Symfony translate the bracketed key into a
        // nested UploadedFile accessible via dot notation, so the file lives
        // at `student.photo` — not at the top-level `photo` key. We also
        // strip it out of `$studentData` below so the raw UploadedFile
        // doesn't end up in a string column via Student::updateOrCreate.
        $photoFile = $request->file('student.photo');
        unset($studentData['photo']);

        logger('Storing registration AFTER', [
            'photoFile_present' => (bool) $photoFile,
            'photoFile_class' => $photoFile ? get_class($photoFile) : null,
            'studentData_keys' => array_keys($studentData),
        ]);

        $reunion = Reunion::currentOpenForRegistration();

        if (! $reunion) {
            return back()->withErrors([
                'reunion' => 'The reunion is no longer accepting registrations.',
            ]);
        }

        // If this phone number already has a registration for the current
        // reunion, send the user straight to the status/payment page instead
        // of creating a duplicate registration.
        $existingStudent = Student::query()->where('phone', $studentData['phone'])->first();

        if ($existingStudent) {
            $existingRegistration = ReunionRegistration::query()
                ->where('reunion_id', $reunion->id)
                ->where('student_id', $existingStudent->id)
                ->first();

            if ($existingRegistration) {
                return redirect()->route('payments.show', [
                    'registration' => $existingRegistration->registration_code,
                ]);
            }
        }

        try {
            $registration = DB::transaction(function () use ($reunion, $studentData, $data, $photoFile) {
                $student = Student::updateOrCreate(
                    ['phone' => $studentData['phone']],
                    $studentData
                );

                if ($photoFile) {
                    $previousPhoto = $student->photo;
                    // $path = $photoFile->store('students', 'public');
                    $path = time() . '_' . $photoFile->getClientOriginalName();
                    logger("Storing photo for student {$student->id} to {$path}");
                    $photoFile->move(public_path('uploads'), $path);
                    $student->photo = $path;
                    $student->save();

                    logger("Uploaded photo for student {$student->id} to {$path}");

                    // Clean up the old file so we don't accumulate orphans on
                    // disk when a student re-submits the form.
                    if ($previousPhoto && $previousPhoto !== $path) {
                        Storage::disk('public')->delete($previousPhoto);
                    }
                }

                return ReunionRegistration::createFor(
                    reunion: $reunion,
                    student: $student,
                    payload: [
                        'guest_count_adult' => $data['guest_count_adult'] ?? 0,
                        'guest_count_child' => $data['guest_count_child'] ?? 0,
                    ],
                    registrationStatus: 'pending',
                    tshirtSize: $data['tshirt_size'] ?? null,
                    notes: $data['notes'] ?? null,
                );
            });
        } catch (Throwable $e) {
            report($e);
            logger('Registration FAILED', [
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return back()->withErrors([
                'form' => 'Registration could not be completed. Please try again.',
            ])->withInput();
        }

        return redirect()->route('payments.show', ['registration' => $registration->registration_code]);
    }
}
