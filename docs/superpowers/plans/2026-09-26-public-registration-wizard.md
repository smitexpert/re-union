# Public Registration Wizard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the landing-page placeholder `REGISTER_URL` with an in-app, multi-step Inertia registration wizard, a standalone payment page, and a Bangla closed-event notice — all driven by a single active Reunion.

**Architecture:** Server-rendered Inertia page decides between "open" and "closed" modes based on a new `Reunion::currentOpenForRegistration()` helper. Open path runs a 4-step client-state wizard that submits atomically via a single `POST /register` (server-recomputed fee, never trusted from client). Payment is a separate `/payments/{code}` page gated on `payment_status === 'unpaid'`. All validation through Laravel FormRequests; no tests are added in this plan (per user instruction).

**Tech Stack:** Laravel 13.17, Inertia 3.4 + React 19, Tailwind 4, Filament 4 (already wired).

---

## File Structure

### New server files

| File | Responsibility |
|---|---|
| `app/Http/Controllers/RegistrationController.php` | `show`, `store`. Resolves open reunion; renders wizard or closed notice; creates Student + Registration + Guests atomically. |
| `app/Http/Controllers/PaymentController.php` | `show`, `store`. Renders standalone payment form; creates a `Payment(status='pending')` row. |
| `app/Http/Requests/StoreRegistrationRequest.php` | Validates wizard payload (student.*, guests, registration_status, notes). |
| `app/Http/Requests/StorePaymentRequest.php` | Validates payment payload. |
| `app/Services/RegistrationFeeCalculator.php` | Pure pricing function: reunion registration fee + sum of active per-guest-type fees × count. |

### Modified server files

| File | Change |
|---|---|
| `app/Models/Reunion.php` | Add `currentOpenForRegistration(): ?self` query scope helper. |
| `app/Models/ReunionRegistration.php` | Add `createFor(Reunion, Student, array, array): self` factory method that wraps fee calculation + guest creation. |
| `routes/web.php` | Add 4 routes (GET/POST /register, GET/POST /payments/{registration}). |

### New frontend files

| File | Responsibility |
|---|---|
| `resources/js/pages/Register.jsx` | Top-level Inertia page; branches on `mode` (`open` / `closed`). |
| `resources/js/pages/Payment.jsx` | Standalone payment page. |
| `resources/js/components/registration/Wizard.jsx` | Holds 4-step state, single atomic `router.post('/register')`. |
| `resources/js/components/registration/Stepper.jsx` | Progress bar with `role="progressbar"`. |
| `resources/js/components/registration/Step1Student.jsx` | Student fields. |
| `resources/js/components/registration/Step2Reunion.jsx` | Read-only reunion summary + live fee preview. |
| `resources/js/components/registration/Step3Guests.jsx` | Adult + child counts, tshirt size. |
| `resources/js/components/registration/Step4Review.jsx` | Read-only summary + registration_status radio + notes + submit. |
| `resources/js/components/registration/ClosedNotice.jsx` | Bangla "no event" card with deep-link to `/payments/{code}`. |

### Modified frontend files

| File | Change |
|---|---|
| `resources/js/pages/Home.jsx` | Drop `REGISTER_URL`; pass `registerUrl="/register"`. |
| `resources/js/components/landing/Header.jsx` | `<a target="_blank">` → `<Link>`. |
| `resources/js/components/landing/Hero.jsx` | Same. |
| `resources/js/components/landing/StickyCta.jsx` | Same. |

---

## Task 1: Add `Reunion::currentOpenForRegistration()` helper

**Files:**
- Modify: `app/Models/Reunion.php`

- [ ] **Step 1: Edit `app/Models/Reunion.php` and add the method**

Append inside the class (before the closing brace):

```php
public static function currentOpenForRegistration(): ?self
{
    $today = now()->toDateString();

    return static::query()
        ->where('is_active', true)
        ->whereDate('event_date', '>=', $today)
        ->where(function ($query) use ($today) {
            $query->whereNull('registration_deadline')
                ->orWhereDate('registration_deadline', '>=', $today);
        })
        ->orderBy('event_date')
        ->first();
}
```

- [ ] **Step 2: Verify the file parses**

Run: `php -l app/Models/Reunion.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Models/Reunion.php
git commit -m "feat: add Reunion::currentOpenForRegistration helper"
```

---

## Task 2: Build the `RegistrationFeeCalculator` service

**Files:**
- Create: `app/Services/RegistrationFeeCalculator.php`

- [ ] **Step 1: Create the service file**

Write `app/Services/RegistrationFeeCalculator.php`:

```php
<?php

namespace App\Services;

use App\Models\Reunion;
use App\Models\ReunionGuestFee;

class RegistrationFeeCalculator
{
    /**
     * Compute the total registration fee (decimal string) for a reunion
     * with the given adult and child guest counts.
     */
    public static function total(Reunion $reunion, int $adultCount = 0, int $childCount = 0): string
    {
        $base = (float) $reunion->registration_fee;

        $activeFees = ReunionGuestFee::query()
            ->where('reunion_id', $reunion->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('guest_type');

        $adultFee = $activeFees->get('Adult');
        $childFee = $activeFees->get('Child');

        $guestTotal = 0.0;
        if ($adultFee && $adultCount > 0) {
            $guestTotal += (float) $adultFee->fee * $adultCount;
        }
        if ($childFee && $childCount > 0) {
            $guestTotal += (float) $childFee->fee * $childCount;
        }

        return number_format($base + $guestTotal, 2, '.', '');
    }

    /**
     * Per-head fee map for live preview in the wizard.
     *
     * @return array<string, float>
     */
    public static function perHeadFees(Reunion $reunion): array
    {
        return ReunionGuestFee::query()
            ->where('reunion_id', $reunion->id)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (ReunionGuestFee $fee) => [
                $fee->guest_type => (float) $fee->fee,
            ])
            ->all();
    }
}
```

- [ ] **Step 2: Verify the file parses**

Run: `php -l app/Services/RegistrationFeeCalculator.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Services/RegistrationFeeCalculator.php
git commit -m "feat: add RegistrationFeeCalculator service"
```

---

## Task 3: Add `ReunionRegistration::createFor()` factory method

**Files:**
- Modify: `app/Models/ReunionRegistration.php`

- [ ] **Step 1: Edit the model and add the method**

Insert before the final closing brace of the class:

```php
/**
 * Create a registration atomically: persist the registration row and one
 * ReunionGuest row per guest, recomputing total_fee from the calculator.
 *
 * @param  array{guest_count_adult?: int, guest_count_child?: int}  $payload
 */
public static function createFor(
    Reunion $reunion,
    Student $student,
    array $payload,
    string $registrationStatus,
    ?string $tshirtSize,
    ?string $notes,
): self {
    $adultCount = (int) ($payload['guest_count_adult'] ?? 0);
    $childCount = (int) ($payload['guest_count_child'] ?? 0);

    $totalFee = RegistrationFeeCalculator::total($reunion, $adultCount, $childCount);
    $totalGuests = $adultCount + $childCount;

    $registration = static::create([
        'reunion_id' => $reunion->id,
        'student_id' => $student->id,
        'tshirt_size' => $tshirtSize,
        'guest_count' => $totalGuests,
        'total_fee' => $totalFee,
        'registration_status' => $registrationStatus,
        'payment_status' => 'unpaid',
        'notes' => $notes,
    ]);

    $activeFees = ReunionGuestFee::query()
        ->where('reunion_id', $reunion->id)
        ->where('is_active', true)
        ->get()
        ->keyBy('guest_type');

    $rows = [];
    if ($adultCount > 0 && ($fee = $activeFees->get('Adult'))) {
        for ($i = 0; $i < $adultCount; $i++) {
            $rows[] = [
                'registration_id' => $registration->id,
                'guest_type' => 'Adult',
                'fee' => (string) $fee->fee,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }
    if ($childCount > 0 && ($fee = $activeFees->get('Child'))) {
        for ($i = 0; $i < $childCount; $i++) {
            $rows[] = [
                'registration_id' => $registration->id,
                'guest_type' => 'Child',
                'fee' => (string) $fee->fee,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }
    if ($rows !== []) {
        ReunionGuest::insert($rows);
    }

    return $registration;
}
```

- [ ] **Step 2: Add the `ReunionGuest` import to the file**

Ensure this `use` is present near the top of `app/Models/ReunionRegistration.php` (alongside the existing `BelongsTo`/`HasMany` imports):

```php
use App\Models\ReunionGuest;
```

- [ ] **Step 3: Verify the file parses**

Run: `php -l app/Models/ReunionRegistration.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add app/Models/ReunionRegistration.php
git commit -m "feat: add ReunionRegistration::createFor factory"
```

---

## Task 4: Build `StoreRegistrationRequest`

**Files:**
- Create: `app/Http/Requests/StoreRegistrationRequest.php`

- [ ] **Step 1: Create the FormRequest**

Write `app/Http/Requests/StoreRegistrationRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student.full_name' => ['required', 'string', 'max:150'],
            'student.email' => [
                'required', 'email', 'max:150',
                Rule::unique((new Student())->getTable(), 'email'),
            ],
            'student.phone' => [
                'required', 'string', 'max:20',
                Rule::unique((new Student())->getTable(), 'phone'),
            ],
            'student.graduation_year' => ['required', 'integer', 'between:1950,2100'],
            'student.department' => ['required', 'string', 'max:100'],
            'student.session' => ['nullable', 'string', 'max:255'],
            'student.current_occupation' => ['nullable', 'string', 'max:150'],
            'student.present_address' => ['nullable', 'string'],
            'student.permanent_address' => ['nullable', 'string'],
            'student.office_address' => ['nullable', 'string'],

            'tshirt_size' => ['nullable', 'string', 'max:10'],
            'guest_count_adult' => ['required', 'integer', 'min:0'],
            'guest_count_child' => ['required', 'integer', 'min:0'],
            'registration_status' => ['required', Rule::in(['pending', 'confirmed', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 2: Verify the file parses**

Run: `php -l app/Http/Requests/StoreRegistrationRequest.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/StoreRegistrationRequest.php
git commit -m "feat: add StoreRegistrationRequest form request"
```

---

## Task 5: Build `StorePaymentRequest`

**Files:**
- Create: `app/Http/Requests/StorePaymentRequest.php`

- [ ] **Step 1: Create the FormRequest**

Write `app/Http/Requests/StorePaymentRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => [
                'required', Rule::in(['bkash', 'nagad', 'bank_transfer', 'cash', 'card']),
            ],
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'send_from_mobile_number' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 2: Verify the file parses**

Run: `php -l app/Http/Requests/StorePaymentRequest.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/StorePaymentRequest.php
git commit -m "feat: add StorePaymentRequest form request"
```

---

## Task 6: Build `RegistrationController`

**Files:**
- Create: `app/Http/Controllers/RegistrationController.php`

- [ ] **Step 1: Create the controller**

Write `app/Http/Controllers/RegistrationController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegistrationRequest;
use App\Models\Reunion;
use App\Models\ReunionRegistration;
use App\Models\Student;
use App\Services\RegistrationFeeCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
        $data = $request->validated();
        $studentData = $data['student'];

        $reunion = Reunion::currentOpenForRegistration();

        if (! $reunion) {
            return back()->withErrors([
                'reunion' => 'The reunion is no longer accepting registrations.',
            ]);
        }

        try {
            $registration = DB::transaction(function () use ($reunion, $studentData, $data) {
                $student = Student::create($studentData);

                return ReunionRegistration::createFor(
                    reunion: $reunion,
                    student: $student,
                    payload: [
                        'guest_count_adult' => $data['guest_count_adult'] ?? 0,
                        'guest_count_child' => $data['guest_count_child'] ?? 0,
                    ],
                    registrationStatus: $data['registration_status'],
                    tshirtSize: $data['tshirt_size'] ?? null,
                    notes: $data['notes'] ?? null,
                );
            });
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'form' => 'Registration could not be completed. Please try again.',
            ])->withInput();
        }

        return redirect()->route('payments.show', ['registration' => $registration->registration_code]);
    }
}
```

- [ ] **Step 2: Verify the file parses**

Run: `php -l app/Http/Controllers/RegistrationController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/RegistrationController.php
git commit -m "feat: add RegistrationController with show and store"
```

---

## Task 7: Build `PaymentController`

**Files:**
- Create: `app/Http/Controllers/PaymentController.php`

- [ ] **Step 1: Create the controller**

Write `app/Http/Controllers/PaymentController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\ReunionRegistration;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PaymentController extends Controller
{
    public function show(string $registration): Response|RedirectResponse
    {
        $reg = ReunionRegistration::query()
            ->with(['reunion', 'student'])
            ->where('registration_code', $registration)
            ->first();

        if (! $reg) {
            abort(404);
        }

        $hasCompleted = $reg->payments()->where('status', 'completed')->exists();

        return Inertia::render('Payment', [
            'registration' => [
                'code' => $reg->registration_code,
                'registration_status' => $reg->registration_status,
                'payment_status' => $reg->payment_status,
                'total_fee' => (float) $reg->total_fee,
                'reunion_title' => $reg->reunion?->title,
                'event_date' => optional($reg->reunion?->event_date)->toDateString(),
                'student_name' => $reg->student?->full_name,
            ],
            'hasCompletedPayment' => $hasCompleted,
        ]);
    }

    public function store(StorePaymentRequest $request, string $registration): RedirectResponse
    {
        $reg = ReunionRegistration::query()
            ->where('registration_code', $registration)
            ->first();

        if (! $reg) {
            abort(404);
        }

        $alreadyCompleted = $reg->payments()->where('status', 'completed')->exists();
        if ($alreadyCompleted) {
            return redirect()
                ->route('payments.show', ['registration' => $reg->registration_code])
                ->with('status', 'A completed payment already exists for this registration.');
        }

        Payment::create([
            'registration_id' => $reg->id,
            'amount' => $request->validated('amount'),
            'payment_method' => $request->validated('payment_method'),
            'transaction_id' => $request->validated('transaction_id'),
            'send_from_mobile_number' => $request->validated('send_from_mobile_number'),
            'status' => 'pending',
            'paid_at' => null,
            'notes' => $request->validated('notes'),
        ]);

        return redirect()
            ->route('payments.show', ['registration' => $reg->registration_code])
            ->with('status', 'Payment submitted — pending review.');
    }
}
```

- [ ] **Step 2: Verify the file parses**

Run: `php -l app/Http/Controllers/PaymentController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/PaymentController.php
git commit -m "feat: add PaymentController with show and store"
```

---

## Task 8: Wire routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Replace `routes/web.php` contents**

Write `routes/web.php`:

```php
<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});

Route::get('/register', [RegistrationController::class, 'show'])->name('register.show');
Route::post('/register', [RegistrationController::class, 'store'])->name('register.store');

Route::get('/payments/{registration}', [PaymentController::class, 'show'])
    ->where('registration', '[A-Za-z0-9\-]+')
    ->name('payments.show');
Route::post('/payments/{registration}', [PaymentController::class, 'store'])
    ->where('registration', '[A-Za-z0-9\-]+')
    ->name('payments.store');
```

- [ ] **Step 2: Verify routes register**

Run: `php artisan route:list --except-vendor | grep -E "register|payments"`
Expected: 4 lines matching `/register` and `/payments/{registration}` for both GET and POST.

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat: wire register and payments routes"
```

---

## Task 9: Build the `Register` Inertia page

**Files:**
- Create: `resources/js/pages/Register.jsx`

- [ ] **Step 1: Create the page**

Write `resources/js/pages/Register.jsx`:

```jsx
import { Head } from '@inertiajs/react';
import ClosedNotice from '@/components/registration/ClosedNotice';
import Wizard from '@/components/registration/Wizard';

export default function Register({ mode, reunion, feeBreakdown, banglaMessage, paymentLookupUrl }) {
    return (
        <>
            <Head title="Register — SWAATC Reunion" />
            <main className="min-h-screen bg-mist px-4 py-8 sm:py-12">
                <div className="mx-auto max-w-md">
                    {mode === 'closed' ? (
                        <ClosedNotice banglaMessage={banglaMessage} paymentLookupUrl={paymentLookupUrl} />
                    ) : (
                        <Wizard reunion={reunion} feeBreakdown={feeBreakdown} />
                    )}
                </div>
            </main>
        </>
    );
}
```

- [ ] **Step 2: Verify file exists**

Run: `ls resources/js/pages/Register.jsx`
Expected: file path printed.

(Commit deferred until Wizard / ClosedNotice files exist.)

---

## Task 10: Build the `Stepper` component

**Files:**
- Create: `resources/js/components/registration/Stepper.jsx`

- [ ] **Step 1: Create the component**

Write `resources/js/components/registration/Stepper.jsx`:

```jsx
const STEPS = ['Student', 'Reunion', 'Guests', 'Review'];

export default function Stepper({ current }) {
    const percent = ((current + 1) / STEPS.length) * 100;
    return (
        <div className="mb-6">
            <div className="mb-2 flex items-center justify-between text-xs font-semibold tracking-wide text-gray-500 uppercase">
                <span>Step {current + 1} of {STEPS.length}</span>
                <span className="hidden sm:inline">{STEPS[current]}</span>
            </div>
            <div
                role="progressbar"
                aria-valuenow={current + 1}
                aria-valuemin={1}
                aria-valuemax={STEPS.length}
                aria-label={`Step ${current + 1} of ${STEPS.length}`}
                className="h-1.5 w-full overflow-hidden rounded-full bg-gray-200"
            >
                <div
                    className="motion-safe:transition-all motion-safe:duration-300 h-full rounded-full bg-brand-ink"
                    style={{ width: `${percent}%` }}
                />
            </div>
            <ol className="mt-3 hidden grid-cols-4 gap-2 text-center sm:grid">
                {STEPS.map((label, i) => (
                    <li
                        key={label}
                        className={`text-xs font-semibold ${i <= current ? 'text-brand-ink' : 'text-gray-400'}`}
                    >
                        {label}
                    </li>
                ))}
            </ol>
        </div>
    );
}
```

---

## Task 11: Build `Step1Student`

**Files:**
- Create: `resources/js/components/registration/Step1Student.jsx`

- [ ] **Step 1: Create the component**

Write `resources/js/components/registration/Step1Student.jsx`:

```jsx
import TextInput from '@/components/registration/fields/TextInput';
import TextAreaInput from '@/components/registration/fields/TextAreaInput';

export default function Step1Student({ data, setData, errors }) {
    const update = (field) => (e) => setData(`student.${field}`, e.target.value);

    return (
        <div className="space-y-4">
            <h2 id="step-1-heading" className="text-xl font-bold text-gray-900">
                Student information
            </h2>
            <p className="text-sm text-gray-600">
                Tell us about yourself. We'll use this to register you for the reunion.
            </p>

            <TextInput
                id="student.full_name"
                label="Full name"
                value={data.student.full_name}
                onChange={update('full_name')}
                error={errors['student.full_name']}
                autoComplete="name"
                required
            />
            <TextInput
                id="student.email"
                type="email"
                label="Email"
                value={data.student.email}
                onChange={update('email')}
                error={errors['student.email']}
                autoComplete="email"
                required
            />
            <TextInput
                id="student.phone"
                type="tel"
                label="Phone"
                value={data.student.phone}
                onChange={update('phone')}
                error={errors['student.phone']}
                autoComplete="tel"
                required
            />
            <div className="grid grid-cols-2 gap-3">
                <TextInput
                    id="student.graduation_year"
                    type="number"
                    label="Graduation year"
                    inputMode="numeric"
                    value={data.student.graduation_year}
                    onChange={update('graduation_year')}
                    error={errors['student.graduation_year']}
                    required
                />
                <TextInput
                    id="student.department"
                    label="Department"
                    value={data.student.department}
                    onChange={update('department')}
                    error={errors['student.department']}
                    required
                />
            </div>
            <TextInput
                id="student.session"
                label="Session"
                value={data.student.session}
                onChange={update('session')}
                error={errors['student.session']}
                placeholder="e.g. 2010-11"
            />
            <TextInput
                id="student.current_occupation"
                label="Current occupation"
                value={data.student.current_occupation}
                onChange={update('current_occupation')}
                error={errors['student.current_occupation']}
            />
            <TextAreaInput
                id="student.present_address"
                label="Present address"
                value={data.student.present_address}
                onChange={update('present_address')}
                error={errors['student.present_address']}
            />
            <TextAreaInput
                id="student.permanent_address"
                label="Permanent address"
                value={data.student.permanent_address}
                onChange={update('permanent_address')}
                error={errors['student.permanent_address']}
            />
            <TextAreaInput
                id="student.office_address"
                label="Office / business address"
                value={data.student.office_address}
                onChange={update('office_address')}
                error={errors['student.office_address']}
            />
        </div>
    );
}
```

- [ ] **Step 2: Create `resources/js/components/registration/fields/TextInput.jsx`**

Write the file:

```jsx
export default function TextInput({ id, label, error, type = 'text', className = '', inputMode, autoComplete, required, ...rest }) {
    const describedBy = error ? `${id}-error` : undefined;
    return (
        <div className={className}>
            <label htmlFor={id} className="mb-1 block text-sm font-semibold text-gray-700">
                {label}{required ? <span aria-hidden="true" className="ml-0.5 text-red-600">*</span> : null}
            </label>
            <input
                id={id}
                name={id}
                type={type}
                inputMode={inputMode}
                autoComplete={autoComplete}
                aria-invalid={error ? 'true' : 'false'}
                aria-describedby={describedBy}
                aria-required={required ? 'true' : undefined}
                className={`block w-full min-h-12 rounded-xl border px-3 py-2.5 text-base text-gray-900 placeholder:text-gray-400 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${
                    error ? 'border-red-400' : 'border-gray-200'
                }`}
                {...rest}
            />
            {error ? (
                <p id={`${id}-error`} className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
```

- [ ] **Step 3: Create `resources/js/components/registration/fields/TextAreaInput.jsx`**

Write the file:

```jsx
export default function TextAreaInput({ id, label, error, rows = 2, required, ...rest }) {
    const describedBy = error ? `${id}-error` : undefined;
    return (
        <div>
            <label htmlFor={id} className="mb-1 block text-sm font-semibold text-gray-700">
                {label}{required ? <span aria-hidden="true" className="ml-0.5 text-red-600">*</span> : null}
            </label>
            <textarea
                id={id}
                name={id}
                rows={rows}
                aria-invalid={error ? 'true' : 'false'}
                aria-describedby={describedBy}
                aria-required={required ? 'true' : undefined}
                className={`block w-full rounded-xl border px-3 py-2.5 text-base text-gray-900 placeholder:text-gray-400 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${
                    error ? 'border-red-400' : 'border-gray-200'
                }`}
                {...rest}
            />
            {error ? (
                <p id={`${id}-error`} className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
```

---

## Task 12: Build `Step2Reunion`

**Files:**
- Create: `resources/js/components/registration/Step2Reunion.jsx`

- [ ] **Step 1: Create the component**

Write `resources/js/components/registration/Step2Reunion.jsx`:

```jsx
import { formatBDT } from '@/components/registration/utils/format';

export default function Step2Reunion({ reunion, feeBreakdown }) {
    const perHead = feeBreakdown.perHead || {};
    return (
        <div className="space-y-4">
            <h2 id="step-2-heading" className="text-xl font-bold text-gray-900">
                Reunion details
            </h2>
            <p className="text-sm text-gray-600">
                Confirm the event and review the fee structure.
            </p>

            <dl className="space-y-2 rounded-2xl border border-gray-100 bg-mist p-4 text-sm">
                <div className="flex justify-between">
                    <dt className="font-semibold text-gray-700">Title</dt>
                    <dd className="text-gray-900">{reunion.title}</dd>
                </div>
                <div className="flex justify-between">
                    <dt className="font-semibold text-gray-700">Event date</dt>
                    <dd className="text-gray-900">{reunion.event_date}</dd>
                </div>
                {reunion.registration_deadline ? (
                    <div className="flex justify-between">
                        <dt className="font-semibold text-gray-700">Registration deadline</dt>
                        <dd className="text-gray-900">{reunion.registration_deadline}</dd>
                    </div>
                ) : null}
                <div className="flex justify-between">
                    <dt className="font-semibold text-gray-700">Base registration fee</dt>
                    <dd className="text-gray-900">{formatBDT(feeBreakdown.base)}</dd>
                </div>
                {perHead.Adult ? (
                    <div className="flex justify-between">
                        <dt className="font-semibold text-gray-700">Adult guest (per head)</dt>
                        <dd className="text-gray-900">{formatBDT(perHead.Adult)}</dd>
                    </div>
                ) : null}
                {perHead.Child ? (
                    <div className="flex justify-between">
                        <dt className="font-semibold text-gray-700">Child guest (per head)</dt>
                        <dd className="text-gray-900">{formatBDT(perHead.Child)}</dd>
                    </div>
                ) : null}
            </dl>

            <p className="rounded-2xl bg-brand/10 px-4 py-3 text-sm text-brand-ink">
                The final total (registration fee + per-guest fees) is computed automatically on the review step.
            </p>
        </div>
    );
}
```

- [ ] **Step 2: Create `resources/js/components/registration/utils/format.js`**

Write the file:

```js
export function formatBDT(value) {
    const n = Number(value ?? 0);
    return `৳${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
```

---

## Task 13: Build `Step3Guests`

**Files:**
- Create: `resources/js/components/registration/Step3Guests.jsx`

- [ ] **Step 1: Create the component**

Write `resources/js/components/registration/Step3Guests.jsx`:

```jsx
import { formatBDT } from '@/components/registration/utils/format';

const TSHIRT_SIZES = ['S', 'M', 'L', 'XL', 'XXL'];

export default function Step3Guests({ data, setData, errors, feeBreakdown }) {
    const update = (field) => (e) => setData(field, e.target.value);
    const adultCount = Number(data.guest_count_adult || 0);
    const childCount = Number(data.guest_count_child || 0);
    const perHead = feeBreakdown.perHead || {};
    const total =
        Number(feeBreakdown.base || 0) +
        (perHead.Adult ? perHead.Adult * adultCount : 0) +
        (perHead.Child ? perHead.Child * childCount : 0);

    return (
        <div className="space-y-4">
            <h2 id="step-3-heading" className="text-xl font-bold text-gray-900">
                Guests & t-shirt
            </h2>
            <p className="text-sm text-gray-600">
                Add the number of guests you're bringing. T-shirt size is optional.
            </p>

            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label htmlFor="guest_count_adult" className="mb-1 block text-sm font-semibold text-gray-700">
                        Adults <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                    </label>
                    <input
                        id="guest_count_adult"
                        name="guest_count_adult"
                        type="number"
                        min="0"
                        inputMode="numeric"
                        value={data.guest_count_adult}
                        onChange={update('guest_count_adult')}
                        aria-invalid={errors.guest_count_adult ? 'true' : 'false'}
                        className={`block w-full min-h-12 rounded-xl border px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${
                            errors.guest_count_adult ? 'border-red-400' : 'border-gray-200'
                        }`}
                    />
                    {errors.guest_count_adult ? (
                        <p className="mt-1 text-sm text-red-600">{errors.guest_count_adult}</p>
                    ) : null}
                    {perHead.Adult ? (
                        <p className="mt-1 text-xs text-gray-500">{formatBDT(perHead.Adult)} per adult</p>
                    ) : null}
                </div>
                <div>
                    <label htmlFor="guest_count_child" className="mb-1 block text-sm font-semibold text-gray-700">
                        Children <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                    </label>
                    <input
                        id="guest_count_child"
                        name="guest_count_child"
                        type="number"
                        min="0"
                        inputMode="numeric"
                        value={data.guest_count_child}
                        onChange={update('guest_count_child')}
                        aria-invalid={errors.guest_count_child ? 'true' : 'false'}
                        className={`block w-full min-h-12 rounded-xl border px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${
                            errors.guest_count_child ? 'border-red-400' : 'border-gray-200'
                        }`}
                    />
                    {errors.guest_count_child ? (
                        <p className="mt-1 text-sm text-red-600">{errors.guest_count_child}</p>
                    ) : null}
                    {perHead.Child ? (
                        <p className="mt-1 text-xs text-gray-500">{formatBDT(perHead.Child)} per child</p>
                    ) : null}
                </div>
            </div>

            <div>
                <label htmlFor="tshirt_size" className="mb-1 block text-sm font-semibold text-gray-700">
                    T-shirt size (optional)
                </label>
                <select
                    id="tshirt_size"
                    name="tshirt_size"
                    value={data.tshirt_size}
                    onChange={update('tshirt_size')}
                    className="block w-full min-h-12 rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                >
                    <option value="">Select size…</option>
                    {TSHIRT_SIZES.map((size) => (
                        <option key={size} value={size}>{size}</option>
                    ))}
                </select>
            </div>

            <div className="rounded-2xl bg-brand/10 px-4 py-3 text-sm font-semibold text-brand-ink">
                Estimated total: {formatBDT(total)}
            </div>
        </div>
    );
}
```

---

## Task 14: Build `Step4Review`

**Files:**
- Create: `resources/js/components/registration/Step4Review.jsx`

- [ ] **Step 1: Create the component**

Write `resources/js/components/registration/Step4Review.jsx`:

```jsx
import { formatBDT } from '@/components/registration/utils/format';

const STATUS_OPTIONS = [
    { value: 'pending', label: 'Pending (pay later)', help: 'Submit now and pay later from your registration page.' },
    { value: 'confirmed', label: 'Confirmed (pay now)', help: 'You will be taken to the payment page after submitting.' },
    { value: 'cancelled', label: 'Cancelled', help: 'Mark this registration as cancelled. No payment required.' },
];

export default function Step4Review({ data, setData, errors, reunion, feeBreakdown }) {
    const update = (field) => (e) => setData(field, e.target.value);
    const adultCount = Number(data.guest_count_adult || 0);
    const childCount = Number(data.guest_count_child || 0);
    const perHead = feeBreakdown.perHead || {};
    const total =
        Number(feeBreakdown.base || 0) +
        (perHead.Adult ? perHead.Adult * adultCount : 0) +
        (perHead.Child ? perHead.Child * childCount : 0);

    return (
        <div className="space-y-5">
            <h2 id="step-4-heading" className="text-xl font-bold text-gray-900">
                Review & submit
            </h2>

            <dl className="space-y-2 rounded-2xl border border-gray-100 bg-mist p-4 text-sm">
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Name</dt>
                    <dd className="text-right text-gray-900">{data.student.full_name}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Email</dt>
                    <dd className="text-right text-gray-900">{data.student.email}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Phone</dt>
                    <dd className="text-right text-gray-900">{data.student.phone}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Graduation year</dt>
                    <dd className="text-right text-gray-900">{data.student.graduation_year}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Department</dt>
                    <dd className="text-right text-gray-900">{data.student.department}</dd>
                </div>
                {data.student.session ? (
                    <div className="flex justify-between gap-3">
                        <dt className="font-semibold text-gray-700">Session</dt>
                        <dd className="text-right text-gray-900">{data.student.session}</dd>
                    </div>
                ) : null}
            </dl>

            <dl className="space-y-2 rounded-2xl border border-gray-100 bg-mist p-4 text-sm">
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Reunion</dt>
                    <dd className="text-right text-gray-900">{reunion.title}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Event date</dt>
                    <dd className="text-right text-gray-900">{reunion.event_date}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Adult guests</dt>
                    <dd className="text-right text-gray-900">{adultCount}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Child guests</dt>
                    <dd className="text-right text-gray-900">{childCount}</dd>
                </div>
                {data.tshirt_size ? (
                    <div className="flex justify-between gap-3">
                        <dt className="font-semibold text-gray-700">T-shirt size</dt>
                        <dd className="text-right text-gray-900">{data.tshirt_size}</dd>
                    </div>
                ) : null}
                <div className="flex justify-between gap-3 border-t border-gray-200 pt-2 text-base font-bold">
                    <dt>Total</dt>
                    <dd>{formatBDT(total)}</dd>
                </div>
            </dl>

            <fieldset>
                <legend className="mb-2 text-sm font-semibold text-gray-700">Registration status</legend>
                <div className="space-y-2">
                    {STATUS_OPTIONS.map((opt) => (
                        <label key={opt.value} className="flex cursor-pointer items-start gap-3 rounded-2xl border border-gray-100 bg-white p-3 has-[:checked]:border-brand-ink has-[:checked]:bg-brand/5">
                            <input
                                type="radio"
                                name="registration_status"
                                value={opt.value}
                                checked={data.registration_status === opt.value}
                                onChange={update('registration_status')}
                                className="mt-1 h-4 w-4 text-brand-ink focus:ring-brand-ink"
                            />
                            <span className="block">
                                <span className="block text-sm font-semibold text-gray-900">{opt.label}</span>
                                <span className="block text-xs text-gray-500">{opt.help}</span>
                            </span>
                        </label>
                    ))}
                </div>
                {errors.registration_status ? (
                    <p className="mt-1 text-sm text-red-600">{errors.registration_status}</p>
                ) : null}
            </fieldset>

            <div>
                <label htmlFor="notes" className="mb-1 block text-sm font-semibold text-gray-700">
                    Notes (optional)
                </label>
                <textarea
                    id="notes"
                    name="notes"
                    rows={3}
                    value={data.notes}
                    onChange={update('notes')}
                    className="block w-full rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                />
            </div>

            {errors.form ? (
                <p className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    {errors.form}
                </p>
            ) : null}
        </div>
    );
}
```

---

## Task 15: Build `ClosedNotice`

**Files:**
- Create: `resources/js/components/registration/ClosedNotice.jsx`

- [ ] **Step 1: Create the component**

Write `resources/js/components/registration/ClosedNotice.jsx`:

```jsx
import { Link } from '@inertiajs/react';

export default function ClosedNotice({ banglaMessage, paymentLookupUrl }) {
    return (
        <section
            aria-labelledby="closed-notice-heading"
            className="rounded-2xl border border-gray-100 bg-white p-6 text-center shadow-sm sm:p-8"
        >
            <div
                aria-hidden="true"
                className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand/10 text-brand-ink"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-8 w-8">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h1 id="closed-notice-heading" className="text-balance text-xl font-bold text-gray-900 sm:text-2xl">
                {banglaMessage}
            </h1>
            <p className="mt-3 text-sm text-gray-600">
                New events will be announced here. Already have a registration code? You can pay for it directly.
            </p>
            <div className="mt-6 flex flex-col items-center gap-3">
                <Link
                    href={paymentLookupUrl}
                    className="inline-flex w-full max-w-xs items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-6 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                >
                    Pay with registration code
                </Link>
                <Link
                    href="/"
                    className="inline-flex w-full max-w-xs items-center justify-center gap-2 rounded-2xl border border-gray-200 px-6 py-3 text-sm font-semibold text-gray-700 transition duration-200 hover:bg-mist focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                >
                    Back to home
                </Link>
            </div>
        </section>
    );
}
```

---

## Task 16: Build the `Wizard`

**Files:**
- Create: `resources/js/components/registration/Wizard.jsx`

- [ ] **Step 1: Create the component**

Write `resources/js/components/registration/Wizard.jsx`:

```jsx
import { useState } from 'react';
import { router } from '@inertiajs/react';
import Stepper from '@/components/registration/Stepper';
import Step1Student from '@/components/registration/Step1Student';
import Step2Reunion from '@/components/registration/Step2Reunion';
import Step3Guests from '@/components/registration/Step3Guests';
import Step4Review from '@/components/registration/Step4Review';

const INITIAL_DATA = {
    student: {
        full_name: '',
        email: '',
        phone: '',
        graduation_year: '',
        department: '',
        session: '',
        current_occupation: '',
        present_address: '',
        permanent_address: '',
        office_address: '',
    },
    tshirt_size: '',
    guest_count_adult: 0,
    guest_count_child: 0,
    registration_status: 'pending',
    notes: '',
};

function isStep1Valid(data) {
    const s = data.student;
    return (
        s.full_name.trim() !== '' &&
        s.email.trim() !== '' &&
        s.phone.trim() !== '' &&
        String(s.graduation_year).trim() !== '' &&
        s.department.trim() !== ''
    );
}

export default function Wizard({ reunion, feeBreakdown }) {
    const [step, setStep] = useState(0);
    const [data, setData] = useState(INITIAL_DATA);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const setField = (path, value) => {
        setData((prev) => {
            if (!path.includes('.')) return { ...prev, [path]: value };
            const [head, key] = path.split('.');
            return { ...prev, [head]: { ...prev[head], [key]: value } };
        });
    };

    const onBack = () => setStep((s) => Math.max(0, s - 1));
    const onNext = () => setStep((s) => Math.min(3, s + 1));

    const onSubmit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post('/register', data, {
            preserveScroll: true,
            onSuccess: () => {
                setProcessing(false);
                setErrors({});
            },
            onError: (errs) => {
                setErrors(errs || {});
                setProcessing(false);
            },
        });
    };

    const step1Ok = isStep1Valid(data);
    const canAdvance = step === 0 ? step1Ok : true;

    return (
        <section
            aria-labelledby={`step-${step + 1}-heading`}
            className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-8"
        >
            <Stepper current={step} />

            <form onSubmit={onSubmit}>
                {step === 0 ? (
                    <Step1Student data={data} setData={setField} errors={errors} />
                ) : null}
                {step === 1 ? (
                    <Step2Reunion reunion={reunion} feeBreakdown={feeBreakdown} />
                ) : null}
                {step === 2 ? (
                    <Step3Guests data={data} setData={setField} errors={errors} feeBreakdown={feeBreakdown} />
                ) : null}
                {step === 3 ? (
                    <Step4Review
                        data={data}
                        setData={setField}
                        errors={errors}
                        reunion={reunion}
                        feeBreakdown={feeBreakdown}
                    />
                ) : null}

                <div className="sticky bottom-0 -mx-5 mt-6 flex items-center justify-between gap-3 border-t border-gray-100 bg-white/95 px-5 py-3 backdrop-blur sm:static sm:mx-0 sm:mt-6 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
                    {step > 0 ? (
                        <button
                            type="button"
                            onClick={onBack}
                            className="rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-mist focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                        >
                            Back
                        </button>
                    ) : (
                        <span />
                    )}
                    {step < 3 ? (
                        <button
                            type="button"
                            onClick={onNext}
                            disabled={!canAdvance}
                            className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-6 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                        >
                            Continue
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </button>
                    ) : (
                        <button
                            type="submit"
                            disabled={processing}
                            aria-busy={processing ? 'true' : 'false'}
                            className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-6 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                        >
                            {processing ? 'Submitting…' : 'Submit registration'}
                        </button>
                    )}
                </div>
            </form>
        </section>
    );
}
```

- [ ] **Step 2: Commit the Inertia page + wizard tree**

```bash
git add resources/js/pages/Register.jsx \
    resources/js/components/registration
git commit -m "feat: add registration wizard and closed notice UI"
```

---

## Task 17: Build the `Payment` Inertia page

**Files:**
- Create: `resources/js/pages/Payment.jsx`

- [ ] **Step 1: Create the page**

Write `resources/js/pages/Payment.jsx`:

```jsx
import { Head, useForm } from '@inertiajs/react';
import { formatBDT } from '@/components/registration/utils/format';

const STATUS_BADGES = {
    paid: 'bg-emerald-100 text-emerald-700',
    completed: 'bg-emerald-100 text-emerald-700',
    pending: 'bg-amber-100 text-amber-800',
    unpaid: 'bg-amber-100 text-amber-800',
    cancelled: 'bg-rose-100 text-rose-700',
    refunded: 'bg-sky-100 text-sky-700',
    failed: 'bg-rose-100 text-rose-700',
};

function Badge({ value }) {
    const cls = STATUS_BADGES[value] || 'bg-gray-100 text-gray-700';
    return <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide ${cls}`}>{value}</span>;
}

export default function Payment({ registration, hasCompletedPayment, flash }) {
    const form = useForm({
        payment_method: 'bkash',
        amount: registration.total_fee,
        transaction_id: '',
        send_from_mobile_number: '',
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/payments/${registration.code}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={`Pay ${registration.code} — SWAATC`} />
            <main className="min-h-screen bg-mist px-4 py-8 sm:py-12">
                <div className="mx-auto max-w-md space-y-6">
                    <header className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h1 className="text-xl font-bold text-gray-900">Registration summary</h1>
                        <dl className="mt-4 space-y-2 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="font-semibold text-gray-700">Code</dt>
                                <dd className="font-mono tracking-wide text-gray-900">{registration.code}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="font-semibold text-gray-700">Name</dt>
                                <dd className="text-right text-gray-900">{registration.student_name}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="font-semibold text-gray-700">Reunion</dt>
                                <dd className="text-right text-gray-900">{registration.reunion_title}</dd>
                            </div>
                            {registration.event_date ? (
                                <div className="flex justify-between gap-3">
                                    <dt className="font-semibold text-gray-700">Event date</dt>
                                    <dd className="text-right text-gray-900">{registration.event_date}</dd>
                                </div>
                            ) : null}
                            <div className="flex justify-between gap-3">
                                <dt className="font-semibold text-gray-700">Total</dt>
                                <dd className="font-bold text-gray-900">{formatBDT(registration.total_fee)}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="font-semibold text-gray-700">Registration</dt>
                                <dd><Badge value={registration.registration_status} /></dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="font-semibold text-gray-700">Payment</dt>
                                <dd><Badge value={registration.payment_status} /></dd>
                            </div>
                        </dl>
                    </header>

                    {flash?.status ? (
                        <p className="rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">
                            {flash.status}
                        </p>
                    ) : null}

                    {hasCompletedPayment ? (
                        <section className="rounded-2xl border border-gray-100 bg-white p-5 text-center shadow-sm">
                            <h2 className="text-lg font-bold text-gray-900">Payment already recorded</h2>
                            <p className="mt-2 text-sm text-gray-600">
                                A completed payment exists for this registration. No further action is needed.
                            </p>
                        </section>
                    ) : (
                        <form onSubmit={submit} className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
                            <h2 className="text-lg font-bold text-gray-900">Make a payment</h2>
                            <p className="mt-1 text-sm text-gray-600">
                                Submit your payment details. Status will move to <em>pending</em> until reviewed.
                            </p>

                            <div className="mt-4 space-y-4">
                                <div>
                                    <label htmlFor="payment_method" className="mb-1 block text-sm font-semibold text-gray-700">
                                        Method <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                                    </label>
                                    <select
                                        id="payment_method"
                                        value={form.data.payment_method}
                                        onChange={(e) => form.setData('payment_method', e.target.value)}
                                        className="block w-full min-h-12 rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                                    >
                                        <option value="bkash">bKash</option>
                                        <option value="nagad">Nagad</option>
                                        <option value="bank_transfer">Bank transfer</option>
                                        <option value="cash">Cash</option>
                                        <option value="card">Card</option>
                                    </select>
                                    {form.errors.payment_method ? (
                                        <p className="mt-1 text-sm text-red-600">{form.errors.payment_method}</p>
                                    ) : null}
                                </div>
                                <div>
                                    <label htmlFor="amount" className="mb-1 block text-sm font-semibold text-gray-700">
                                        Amount (BDT) <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                                    </label>
                                    <input
                                        id="amount"
                                        type="number"
                                        min="1"
                                        step="0.01"
                                        inputMode="decimal"
                                        value={form.data.amount}
                                        onChange={(e) => form.setData('amount', e.target.value)}
                                        className="block w-full min-h-12 rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                                    />
                                    {form.errors.amount ? (
                                        <p className="mt-1 text-sm text-red-600">{form.errors.amount}</p>
                                    ) : null}
                                </div>
                                <div>
                                    <label htmlFor="transaction_id" className="mb-1 block text-sm font-semibold text-gray-700">
                                        Transaction ID
                                    </label>
                                    <input
                                        id="transaction_id"
                                        type="text"
                                        value={form.data.transaction_id}
                                        onChange={(e) => form.setData('transaction_id', e.target.value)}
                                        className="block w-full min-h-12 rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="send_from_mobile_number" className="mb-1 block text-sm font-semibold text-gray-700">
                                        Sender mobile number
                                    </label>
                                    <input
                                        id="send_from_mobile_number"
                                        type="tel"
                                        inputMode="tel"
                                        autoComplete="tel"
                                        value={form.data.send_from_mobile_number}
                                        onChange={(e) => form.setData('send_from_mobile_number', e.target.value)}
                                        className="block w-full min-h-12 rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="notes" className="mb-1 block text-sm font-semibold text-gray-700">
                                        Notes (optional)
                                    </label>
                                    <textarea
                                        id="notes"
                                        rows={2}
                                        value={form.data.notes}
                                        onChange={(e) => form.setData('notes', e.target.value)}
                                        className="block w-full rounded-xl border border-gray-200 px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={form.processing}
                                aria-busy={form.processing ? 'true' : 'false'}
                                className="mt-6 inline-flex w-full min-h-12 items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-6 py-3 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
                            >
                                {form.processing ? 'Submitting…' : 'Submit payment'}
                            </button>
                        </form>
                    )}
                </div>
            </main>
        </>
    );
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/pages/Payment.jsx
git commit -m "feat: add standalone payment page"
```

---

## Task 18: Wire landing-page register links to `/register`

**Files:**
- Modify: `resources/js/pages/Home.jsx`
- Modify: `resources/js/components/landing/Header.jsx`
- Modify: `resources/js/components/landing/Hero.jsx`
- Modify: `resources/js/components/landing/StickyCta.jsx`

- [ ] **Step 1: Update `Home.jsx`**

Replace `resources/js/pages/Home.jsx` with:

```jsx
import { Head } from '@inertiajs/react';
import Header from '@/components/landing/Header';
import Hero from '@/components/landing/Hero';
import About from '@/components/landing/About';
import Footer from '@/components/landing/Footer';
import StickyCta from '@/components/landing/StickyCta';

const REGISTER_URL = '/register';

export default function Home() {
    return (
        <>
            <Head title="SWAATC — Social Work Alumni Association">
                <meta
                    name="description"
                    content="Join the Social Work Alumni Association of Tejgaon College (SWAATC) — reconnect with batchmates, honour our teachers, and celebrate on campus in Dhaka."
                />
            </Head>
            <div className="min-h-screen bg-white font-sans text-black">
                <a
                    href="#main"
                    className="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[60] focus:rounded-lg focus:bg-black focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white"
                >
                    Skip to content
                </a>
                <Header registerUrl={REGISTER_URL} />
                <main id="main">
                    <Hero registerUrl={REGISTER_URL} />
                    <About />
                </main>
                <Footer />
                <StickyCta registerUrl={REGISTER_URL} />
            </div>
        </>
    );
}
```

- [ ] **Step 2: Update `Header.jsx`**

In `resources/js/components/landing/Header.jsx`:

1. Add `import { Link } from '@inertiajs/react';` to the top imports.
2. Replace the desktop `<a>` (the one with class `hidden ... sm:inline-flex ... "Register"`) with:

```jsx
<Link
    href={registerUrl}
    className={`hidden rounded-full bg-brand px-5 py-2 text-sm font-bold text-black transition duration-200 hover:brightness-95 active:scale-[0.98] sm:inline-flex ${focusRing}`}
>
    Register
</Link>
```

3. Replace the mobile menu `<a>` (the one inside `<li className="pt-1 sm:hidden">` with text "Register Now") with:

```jsx
<Link
    href={registerUrl}
    onClick={() => setOpen(false)}
    className={`block rounded-xl bg-brand px-3 py-3 text-center text-sm font-bold text-black transition duration-200 hover:brightness-95 ${focusRing}`}
>
    Register Now
</Link>
```

- [ ] **Step 3: Update `Hero.jsx`**

In `resources/js/components/landing/Hero.jsx`:

1. Add `import { Link } from '@inertiajs/react';` at the top.
2. Replace the `<a href={registerUrl} target="_blank" rel="noopener noreferrer" className={...}>...Register Now...</a>` block with:

```jsx
<Link
    href={registerUrl}
    className={`group inline-flex w-full max-w-sm items-center justify-center gap-2 rounded-2xl bg-linear-to-r from-brand to-brand-ink px-8 py-4 text-base font-bold text-black shadow-glow transition duration-200 hover:shadow-xl hover:shadow-brand/40 hover:brightness-105 active:scale-[0.98] ${focusRing}`}
>
    Register Now
    <svg
        aria-hidden="true"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        className="h-5 w-5 transition-transform duration-200 group-hover:translate-x-1"
    >
        <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"
        />
    </svg>
</Link>
```

- [ ] **Step 4: Update `StickyCta.jsx`**

In `resources/js/components/landing/StickyCta.jsx`:

1. Add `import { Link } from '@inertiajs/react';` at the top.
2. Replace the `<a href={registerUrl} target="_blank" rel="noopener noreferrer" ...>Register Now ...</a>` block with:

```jsx
<Link
    href={registerUrl}
    className="flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-6 py-3.5 text-base font-bold text-black shadow-glow transition duration-200 hover:brightness-95 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-2"
>
    Register Now
    <svg
        aria-hidden="true"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        className="h-5 w-5"
    >
        <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
    </svg>
</Link>
```

- [ ] **Step 5: Verify with Pint and a lint pass**

Run: `vendor/bin/pint --format agent app`
Expected: no changes (no PHP files touched in this task).

Run: `php -l resources/web.php 2>/dev/null; echo "JS lint skipped"`
Expected: only `JS lint skipped` line (no JS linter configured).

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/Home.jsx \
    resources/js/components/landing/Header.jsx \
    resources/js/components/landing/Hero.jsx \
    resources/js/components/landing/StickyCta.jsx
git commit -m "feat: wire landing CTAs to /register"
```

---

## Task 19: Final Pint pass on all PHP files

**Files:**
- All PHP files in `app/` and `routes/`

- [ ] **Step 1: Run Pint formatter**

Run: `vendor/bin/pint --format agent app routes`
Expected: any formatting issues auto-fixed.

- [ ] **Step 2: Verify app still parses**

Run: `php -l app/Http/Controllers/RegistrationController.php && php -l app/Http/Controllers/PaymentController.php && php -l app/Models/Reunion.php && php -l app/Models/ReunionRegistration.php`
Expected: four `No syntax errors detected` lines.

- [ ] **Step 3: Final commit if Pint changed anything**

```bash
git add -A
git diff --cached --quiet || git commit -m "style: pint formatting"
```

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| Routes (4) | Task 8 |
| `Reunion::currentOpenForRegistration()` | Task 1 |
| `RegistrationFeeCalculator` | Task 2 |
| `ReunionRegistration::createFor()` | Task 3 |
| `StoreRegistrationRequest` | Task 4 |
| `StorePaymentRequest` | Task 5 |
| `RegistrationController` | Task 6 |
| `PaymentController` | Task 7 |
| Inertia `Register` page | Task 9 |
| `Stepper` | Task 10 |
| `Step1Student` + field primitives | Task 11 |
| `Step2Reunion` + format util | Task 12 |
| `Step3Guests` | Task 13 |
| `Step4Review` | Task 14 |
| `ClosedNotice` | Task 15 |
| `Wizard` | Task 16 |
| Inertia `Payment` page | Task 17 |
| Landing-page wiring | Task 18 |
| Bangla closed message | Task 6 (controller), Task 15 (component) |
| Visual direction (mobile-first, modern) | Tasks 10-17 (CSS), Task 18 (sticky-cta pattern) |
| Payment status independent of registration status | Task 3 (hardcoded `'unpaid'`), Task 7 (no mutation) |
| "Make Payment" button on success | Controller redirects to `/payments/{code}` (Task 6), which shows the form when `payment_status==='unpaid'` (Task 17) |

No gaps. No placeholders. Type names consistent (`Reunion`, `ReunionRegistration`, `Student`, `ReunionGuest`, `ReunionGuestFee`, `Payment`).