# Step 1: Remove Email, Upsert Student by Phone — Design Spec

**Date:** 2026-09-27
**Status:** Approved
**Scope:** Step 1 of the public registration wizard no longer collects an email; on final submit the backend upserts the `Student` row by phone (update if the phone exists, otherwise create). The `ReunionRegistration` flow stays unchanged.

## Goal

Today the registration form rejects a submission with a validation error if the phone number is already associated with an existing `Student`. We want alumni to be able to re-submit their details and have their record updated in place, keyed by phone.

Concretely:

1. Remove the **email** field from Step 1 of the registration wizard (`resources/js/components/registration/Step1Student.jsx`), from the wizard's initial state, from the step-1 validity check, and from the Step 4 review.
2. On final submit, if a `Student` with the submitted phone already exists, **update** that student's fields with the submitted data; otherwise **create** a new `Student`. Either way, a new `ReunionRegistration` is created and the user is redirected to the payment page.

## Decisions (locked in)

| Decision | Choice |
|---|---|
| Phone is the natural key for `Student` | Yes — phone uniqueness becomes a soft business rule, no longer enforced at validation or DB layer |
| `Student::email` column | Remains in the DB (nullable, harmless); no longer collected, no longer validated |
| Step 1 phone lookup / early feedback | None — no new endpoint, no Continue-click lookup, no warning UI |
| One registration per student per reunion | Out of scope — re-submitting with the same phone creates a new `ReunionRegistration` row |
| Tests | Skipped unless explicitly requested (matches prior spec) |

## Architecture

No new routes, controllers, or middleware. Existing route table is unchanged:

| Method | URL | Action |
|---|---|---|
| GET | `/register` | `RegistrationController@show` |
| POST | `/register` | `RegistrationController@store` |
| GET | `/payments/{registration}` | `PaymentController@show` |
| POST | `/payments/{registration}` | `PaymentController@store` |

### Frontend

#### `resources/js/components/registration/Step1Student.jsx`

- **Remove** the `TextInput` block for `student.email` (the block whose `id="student.email"`, `type="email"`, `label="Email"`).
- The component still imports `TextInput` and `TextAreaInput`; no other rendering changes.

#### `resources/js/components/registration/Wizard.jsx`

- In `INITIAL_DATA.student`, **drop** `email: ''`.
- In `isStep1Valid(data)`, **drop** the `s.email.trim() !== ''` clause. The check becomes: `full_name`, `phone`, `graduation_year` (stringified), and `department` all non-empty.
- No changes to `setField`, `onBack`, `onNext`, `onSubmit`, or the `canAdvance` derivation.
- No changes to the Inertia `POST /register` call or its `onSuccess` / `onError` handlers.

#### `resources/js/components/registration/Step4Review.jsx`

- **Remove** the `<div>` row whose `<dt>` is `Email` and `<dd>` renders `data.student.email`. The review still shows Name, Phone, Graduation year, Department, Session (when present), and the reunion/guests/total block.

### Backend

#### `app/Http/Requests/StoreRegistrationRequest.php`

- **Remove** the `'student.email' => [...]` rule entirely.
- In the `'student.phone'` rule, **remove** the `Rule::unique((new Student)->getTable(), 'phone')` clause. The rule becomes `['required', 'string', 'max:20']`.

All other rules stay as-is, including:

- `student.full_name`: required, string, max 150.
- `student.graduation_year`: required, integer, between 1950, 2100.
- `student.department`: required, string, max 100.
- All nullable address / occupation / session / tshirt_size / guests / notes rules.
- `guest_count_adult` and `guest_count_child`: required, integer, min 0.

#### `app/Http/Controllers/RegistrationController.php`

Inside the `try` block's transaction, replace:

```php
$student = Student::create($studentData);
```

with an upsert-by-phone:

```php
$student = Student::firstOrNew(['phone' => $studentData['phone']]);
$student->fill($studentData);
$student->save();
```

Everything else in `store()` stays as-is:

- `Reunion::currentOpenForRegistration()` lookup and the "no longer accepting" branch.
- `DB::transaction` wrapping.
- `ReunionRegistration::createFor(...)` call with `registrationStatus: 'pending'`.
- The `catch (Throwable $e)` block that reports and returns the generic `form` error.
- The final `redirect()->route('payments.show', ['registration' => $registration->registration_code])`.

### Data model

No migration changes. The `students` table already stores:

- `phone` (string, indexed by app code, no DB-level unique constraint today).
- `email` (nullable string with a DB-level unique constraint that remains untouched).

Existing data is preserved. Any historical students with NULL phones are unreachable via upsert (they require `phone` to be present in the submitted payload, which the validation rule already requires).

## Behavior

### Happy path (new student)

1. User fills Step 1 (name, phone, graduation year, department, etc.) → Step 2 (reunion) → Step 3 (guests) → Step 4 (review, notes).
2. Clicks "Submit registration".
3. Backend: no `Student` matches `phone`. `firstOrNew` returns a new, unpersisted `Student`. `fill + save` creates the row.
4. Backend creates a new `ReunionRegistration` and redirects to `/payments/{registration_code}`.

### Re-submit path (existing student)

1. Returning user fills all steps with their updated info and the same phone.
2. Clicks "Submit registration".
3. Backend: `Student::firstOrNew(['phone' => …])` finds the existing student. `fill($studentData)` overwrites all submitted fields (name, graduation year, department, session, occupation, addresses) on the existing row. `save()` issues an UPDATE.
4. Backend creates a new `ReunionRegistration` for this student and redirects to `/payments/{registration_code}`.

### Validation failures

- All existing field-level validations continue to behave the same: required fields, integer ranges, max lengths, etc.
- The only differences: email is never required, never validated; phone is never required to be unique.

### Concurrency

Two near-simultaneous submits from the same phone could each hit `firstOrNew` and create two registrations. This is acceptable — the registration model itself is a per-submit event (one ticket per submission). Locking or upsert on registration is intentionally out of scope.

## Error handling

No change. The `try/catch (Throwable $e)` in `RegistrationController::store` continues to report and return a generic `form` error if anything throws. The Inertia `onError` handler in `Wizard.jsx` continues to jump to the earliest step containing errors (Step 0 for `student.*` keys).

## Out of scope

- One registration per student per reunion.
- Per-field audit log of what changed on update.
- Removing the `students.email` column or its unique index from the database.
- A `/register/check-phone` early-warning endpoint.
- Phone number format validation (e.g., Bangladeshi phone regex).
- Confirmation UI ("you are about to update an existing record").

## Test plan

No new automated tests (matches the prior spec's "skipped unless explicitly requested" decision). Manual verification:

1. New phone: submit → row created in `students`, registration in `reunion_registrations`, redirect to payment.
2. Same phone, second submit: row updated in `students` (same `id`, new field values), new registration created, redirect to payment.
3. Missing required field (e.g., empty `full_name`): Inertia shows field error on Step 0.
4. Existing form regression: Step 1 no longer renders an email input, and Step 4 review no longer shows an Email row.
