# Step 1: Remove Email, Upsert Student by Phone — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Drop the email field from Step 1 of the registration wizard and make `RegistrationController@store` upsert the `Student` row by phone (update if the phone exists, otherwise create).

**Architecture:** Frontend edits remove the email `<TextInput>` from `Step1Student.jsx`, drop `email` from the wizard's `INITIAL_DATA`, drop the email clause from `isStep1Valid`, and drop the Email row from `Step4Review.jsx`. Backend edits drop the `student.email` rule and the `student.phone` unique rule from `StoreRegistrationRequest`, then replace `Student::create($studentData)` in `RegistrationController@store` with a `Student::firstOrNew(['phone' => …]) → fill → save` upsert. No migration changes; no new routes; no automated tests (matches the prior spec's "tests skipped unless explicitly requested" decision).

**Tech Stack:** Laravel 13.17, Inertia 3.4 + React 19, Tailwind 4.

---

## File Structure

### Modified frontend files

| File | Change |
|---|---|
| `resources/js/components/registration/Step1Student.jsx` | Delete the `student.email` `TextInput` block. |
| `resources/js/components/registration/Wizard.jsx` | Drop `email: ''` from `INITIAL_DATA.student`; drop the `s.email.trim() !== ''` clause from `isStep1Valid`. |
| `resources/js/components/registration/Step4Review.jsx` | Delete the `<div>` row whose `<dt>` is `Email`. |

### Modified backend files

| File | Change |
|---|---|
| `app/Http/Requests/StoreRegistrationRequest.php` | Delete the `student.email` rule; drop `Rule::unique(...)` from the `student.phone` rule. |
| `app/Http/Controllers/RegistrationController.php` | Replace `Student::create($studentData)` with `Student::firstOrNew(['phone' => $studentData['phone']])` + `fill($studentData)` + `save()`. |

### Unchanged (but worth being aware of)

- `app/Models/Student.php` — `$fillable` already lists every field the upsert will write; no change.
- `database/migrations/*_students_*.php` — the `phone` column already exists with no DB-level unique constraint; the `email` column stays in place.
- `routes/web.php` — no change.

---

## Task 1: Remove the email field from Step 1

**Files:**
- Modify: `resources/js/components/registration/Step1Student.jsx`

- [ ] **Step 1: Delete the email `<TextInput>` block**

In `resources/js/components/registration/Step1Student.jsx`, delete this entire block (currently lines 25–34):

```jsx
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
```

The file should start with the `TextInput` for `student.full_name`, then `TextInput` for `student.phone`, then the grid with graduation year / department. Nothing else changes in this file.

- [ ] **Step 2: Verify by reading the file**

Open `resources/js/components/registration/Step1Student.jsx` and confirm:
- The `student.email` `TextInput` block is gone.
- The `student.phone` `TextInput` (id `student.phone`, type `tel`, label `Phone`) is still present and still required.
- The component still imports `TextInput` and `TextAreaInput` from `@/components/registration/fields/...`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/registration/Step1Student.jsx
git commit -m "feat(registration): remove email field from Step 1"
```

---

## Task 2: Drop email from Wizard initial state and step-1 validator

**Files:**
- Modify: `resources/js/components/registration/Wizard.jsx`

- [ ] **Step 1: Drop `email: ''` from `INITIAL_DATA.student`**

In `resources/js/components/registration/Wizard.jsx`, in the `INITIAL_DATA` constant (currently lines 9–21), delete this line:

```js
        email: '',
```

The remaining `student` initializer becomes:

```js
    student: {
        full_name: '',
        phone: '',
        graduation_year: '',
        department: '',
        session: '',
        current_occupation: '',
        present_address: '',
        permanent_address: '',
        office_address: '',
    },
```

- [ ] **Step 2: Drop the email clause from `isStep1Valid`**

In the same file, replace the `isStep1Valid` function body (currently lines 28–37) with:

```js
function isStep1Valid(data) {
    const s = data.student;
    return (
        s.full_name.trim() !== '' &&
        s.phone.trim() !== '' &&
        String(s.graduation_year).trim() !== '' &&
        s.department.trim() !== ''
    );
}
```

No other changes to this file. The `setField`, `onBack`, `onNext`, `onSubmit`, `canAdvance`, and the Inertia `POST /register` call remain untouched.

- [ ] **Step 3: Verify by reading the file**

Open `resources/js/components/registration/Wizard.jsx` and confirm:
- `INITIAL_DATA.student` has no `email` key.
- `isStep1Valid` checks exactly `full_name`, `phone`, `graduation_year`, `department`.
- The submit flow (`onSubmit`, `router.post('/register', data, {...})`) is unchanged.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/registration/Wizard.jsx
git commit -m "feat(registration): drop email from wizard state and step-1 validator"
```

---

## Task 3: Remove the Email row from the Step 4 review

**Files:**
- Modify: `resources/js/components/registration/Step4Review.jsx`

- [ ] **Step 1: Delete the Email `<div>` row**

In `resources/js/components/registration/Step4Review.jsx`, delete this entire `<div>` (currently lines 24–27):

```jsx
                <div className="flex justify-between gap-3">
                    <dt className="font-semibold text-gray-700">Email</dt>
                    <dd className="text-right text-gray-900">{data.student.email}</dd>
                </div>
```

The remaining review shows: Name, Phone, Graduation year, Department, Session (when present), then the reunion / guests / total block. Nothing else changes in this file.

- [ ] **Step 2: Verify by reading the file**

Open `resources/js/components/registration/Step4Review.jsx` and confirm:
- No `<dt>Email</dt>` row exists in the student-info `<dl>`.
- The reunion/guests/total `<dl>` and the Notes textarea are still present.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/registration/Step4Review.jsx
git commit -m "feat(registration): drop email row from Step 4 review"
```

---

## Task 4: Drop email and phone-unique rules from the form request

**Files:**
- Modify: `app/Http/Requests/StoreRegistrationRequest.php`

- [ ] **Step 1: Delete the `student.email` rule**

In `app/Http/Requests/StoreRegistrationRequest.php`, delete this entire array entry (currently lines 20–23):

```php
            'student.email' => [
                'required', 'email', 'max:150',
                Rule::unique((new Student)->getTable(), 'email'),
            ],
```

- [ ] **Step 2: Drop `Rule::unique(...)` from the `student.phone` rule**

In the same file, replace the `student.phone` rule (currently lines 24–27):

```php
            'student.phone' => [
                'required', 'string', 'max:20',
                Rule::unique((new Student)->getTable(), 'phone'),
            ],
```

with:

```php
            'student.phone' => ['required', 'string', 'max:20'],
```

- [ ] **Step 3: Verify by reading the file**

Open `app/Http/Requests/StoreRegistrationRequest.php` and confirm:
- There is no `'student.email' =>` key.
- `'student.phone'` is `['required', 'string', 'max:20']` — no `Rule::unique`.
- All other rules are present and unchanged: `student.full_name`, `student.graduation_year`, `student.department`, the four `nullable` student rules, the three address `nullable` rules, and the registration / guests / notes rules.

The `use Illuminate\Validation\Rule;` and `use App\Models\Student;` imports can stay — `Rule` is still imported but unused, and `Student` is still imported but unused. Leaving them is harmless and avoids an extra diff line. (Optional cleanup: remove them. Either is fine.)

- [ ] **Step 4: Commit**

```bash
git add app/Http/Requests/StoreRegistrationRequest.php
git commit -m "feat(registration): drop email rule and phone-unique rule"
```

---

## Task 5: Upsert Student by phone in the controller

**Files:**
- Modify: `app/Http/Controllers/RegistrationController.php`

- [ ] **Step 1: Replace `Student::create` with an upsert**

In `app/Http/Controllers/RegistrationController.php`, inside the `DB::transaction(...)` closure (currently lines 61–75), replace this line:

```php
                $student = Student::create($studentData);
```

with:

```php
                $student = Student::firstOrNew(['phone' => $studentData['phone']]);
                $student->fill($studentData);
                $student->save();
```

The rest of the transaction body is unchanged: the next call still creates a `ReunionRegistration` via `ReunionRegistration::createFor(...)` with `registrationStatus: 'pending'`.

The `catch (Throwable $e)` block (currently lines 76–82) and the final `redirect()->route('payments.show', ...)` (line 84) are unchanged.

- [ ] **Step 2: Verify by reading the file**

Open `app/Http/Controllers/RegistrationController.php` and confirm:
- The transaction body uses `firstOrNew(['phone' => $studentData['phone']])` followed by `fill($studentData)` and `save()`.
- `ReunionRegistration::createFor(...)` is still called with the same arguments.
- The `catch` block and the redirect are unchanged.

- [ ] **Step 3: Run PHP lint on the modified files**

Run:

```bash
php -l app/Http/Controllers/RegistrationController.php
php -l app/Http/Requests/StoreRegistrationRequest.php
```

Expected: `No syntax errors detected in ...` for both files.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/RegistrationController.php
git commit -m "feat(registration): upsert Student by phone on submit"
```

---

## Task 6: Manual end-to-end verification

**Files:** none — read-only / browser interaction.

- [ ] **Step 1: Rebuild frontend assets (only if production mode)**

If you are running in production mode (`public/build/manifest.json` exists and `public/hot` does not), run:

```bash
npm run build
```

If you are running in dev mode (HMR), the running `npm run dev` server already picks up frontend changes. Skip this step.

For backend changes, no `composer dump-autoload` or `php artisan optimize` is needed — they are simple PHP class changes and Laravel reloads them on each request.

- [ ] **Step 2: Verify "new phone" path**

1. Open `/register` in the browser.
2. Confirm Step 1 no longer renders an Email input.
3. Fill Step 1 with a brand-new phone number (one not present in the `students` table).
4. Walk through Step 2 → Step 3 → Step 4.
5. Confirm Step 4 review shows Name, Phone, Graduation year, Department, (Session) — no Email row.
6. Click "Submit registration".
7. Confirm you are redirected to `/payments/{registration_code}`.
8. In a DB tool (`php artisan tinker` or `DB::table('students')->where('phone', '...')->first()`), confirm a new `Student` row exists with the submitted details, and a new `ReunionRegistration` row points to it.

- [ ] **Step 3: Verify "existing phone" path (upsert)**

1. From Step 2's last action, you now have a Student row for phone `X` and a Registration row tied to it.
2. Open `/register` again (or refresh).
3. Fill Step 1 with the **same phone `X`** but **different name, graduation year, department, addresses**.
4. Walk through Step 2 → Step 3 → Step 4.
5. Click "Submit registration".
6. Confirm you are redirected to `/payments/{new_registration_code}`.
7. In the DB, confirm:
   - The `students` row for phone `X` has the **same `id`** as before (not a new row), but its `full_name`, `graduation_year`, `department`, `session`, `current_occupation`, `present_address`, `permanent_address`, `office_address` reflect the **new** submission.
   - A **new** `ReunionRegistration` row exists for this student.

- [ ] **Step 4: Verify "missing required field" path still surfaces inline errors**

1. Open `/register`.
2. On Step 1, leave `full_name` empty (or another required field) and click "Continue".
3. Confirm the Continue button is disabled (Continue requires `step1Ok`).
4. Fill Step 1 with valid data and proceed.
5. On Step 4, click "Submit registration" with an empty `guest_count_adult` (or another required non-student field) and confirm the Inertia error handler jumps back to the appropriate step and shows the inline message.

(No code change — this confirms Task 2's `isStep1Valid` rewrite did not regress the existing field validation contract.)

- [ ] **Step 5: Verify no Email-related errors appear**

1. From `/register`, submit a registration with no email field at all.
2. Confirm there is **no** validation error mentioning `student.email` (the rule was removed).
3. Confirm the new `Student` row has `email = NULL` (the column is nullable and we no longer send it).

- [ ] **Step 6: Final commit (only if any verification step required a tweak)**

If Steps 2–5 all pass without code changes, no commit is needed.

If a tweak was required, commit it with a message such as:

```bash
git add -A
git commit --allow-empty -m "chore(registration): verified upsert-by-phone flow"
```

(Adjust to whatever the tweak actually was.)
