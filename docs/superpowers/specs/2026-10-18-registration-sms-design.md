# Design — Registration SMS on Payment Status Change

Date: 2026-10-18
Status: Approved (pending)
Related surface: admin Filament panel, `/admin/registrations/{record}` view page

## Goal

When an admin changes a registration's `payment_status` to `paid` or `refunded` from the Filament view page, the system should send an SMS to the student's phone and record every send attempt in an audit table. Admins should also be able to re-send the confirmation SMS for a `paid` registration.

Delivery is **synchronous** (no queue) because the project runs on shared hosting without a queue worker.

## Triggers

| Old `payment_status` | New `payment_status` | Action |
|---|---|---|
| anything | `paid` | Send "Registration Confirmed" SMS; log `trigger=payment_paid` |
| anything | `refunded` | Send "Registration Cancelled — Refund issued" SMS; log `trigger=payment_refunded` |
| anything | `unpaid` | No SMS. `unpaid` is treated as an admin correction, not user-facing. |
| unchanged | unchanged | No SMS (covered by no-transition guard) |
| manual click of "Resend Confirmation SMS" action | n/a | Send same message as `payment_paid`; log `trigger=manual_resend` |

A transition is detected by comparing `$record->getOriginal('payment_status')` to the new value the admin just selected, inside `afterSave()` (after persistence, so the original column reflects the prior persisted value).

## SMS payload (bulksmsbd.net)

Endpoint: `http://bulksmsbd.net/api/smsapi`
Method: `POST` with form fields: `api_key`, `senderid`, `number`, `message`.

- `number` is sent as a comma-separated string if multiple recipients. We send one number per call (one registration = one student).
- `message` is plain text, up to the provider's segment limit. We keep messages under 160 chars to stay within a single segment.

Credentials come from `.env`:
```
BULKSMS_API_KEY=...
BULKSMS_SENDER_ID=...
```
…exposed in `config/services.php` as `services.bulksms.api_key` and `services.bulksms.sender_id`.

## Phone normalization

`Student.phone` is stored as the user entered it. The bd-bulksms API dials `8801XXXXXXXXX` (13 digits, no `+`).

Helper `App\Support\BangladeshPhone::toE164(string $raw): ?string`:

1. Strip everything that is not a digit.
2. If empty, return `null`.
3. If the result already starts with `880` and has exactly 13 digits, return it.
4. Otherwise, if it starts with `0` and the result has 11 digits, replace leading `0` with `880`.
5. Otherwise, if it has 10 digits and starts with `1` (a `1XXXXXXXXX` Banglalink/GP-style raw), prepend `880`.
6. Otherwise, return `null` (caller treats as "phone unusable").

When the helper returns `null`, the SMS is **skipped**, an `sms_logs` row is written with `status=skipped` and an `error_message`, and the registration save **still succeeds** (we never block the admin on missing phone).

## Message content

Confirmed:
```
<reunion name>: Registration Confirmed!
Code: REG-XXXXXXXX
Amount: ৳<total_fee>
Guests: <adult_count> adult, <child_count> child
Thank you.
```

Cancelled:
```
<reunion name>: Your registration REG-XXXXXXXX has been cancelled and a refund has been issued.
```

Adult/child counts are derived from the `guests` relation. If the relation is unloaded (e.g., during the re-send action outside the normal save path), the service queries `ReunionGuest::where('registration_id', …)->get()`.

`ReunionGuestFee` is queried once to check whether the `Adult` / `Child` rows exist for the active reunion, to guard against counting guests whose fee row is missing.

## Audit table — `sms_logs`

```
sms_logs
  id                       bigint pk
  registration_id          bigint nullable, FK -> reunion_registrations.id, ON DELETE SET NULL
  student_id               bigint nullable, FK -> students.id, ON DELETE SET NULL
  phone                    string(20)         — what we dialed (after normalization)
  message                  text               — exact body sent
  trigger                  string(32)         — 'payment_paid' | 'payment_refunded' | 'manual_resend'
  status                   string(16)         — 'sent' | 'failed' | 'skipped'
  http_status              int nullable       — provider HTTP status; null when skipped
  provider_response        text nullable       — first ~1024 chars of body, truncated
  error_message            text nullable       — exception message on failure
  sent_at                  timestamp nullable  — when the API returned success
  created_at, updated_at   timestamps
```

Indexes: `(registration_id, created_at)` for per-registration history; `(status, created_at)` for ops dashboards later.

Model `App\Models\SmsLog` with `belongsTo(ReunionRegistration::class)` and `belongsTo(Student::class)`.

## Re-send action on Filament page

Added to `getHeaderActions()` on `ViewRegistration`, next to the existing Back action:

- Label: "Resend Confirmation SMS"
- Icon: `Heroicon::PaperAirplane`
- Color: `gray`
- Visible only when `$record->payment_status === 'paid'`. Hides for unpaid/refunded — keeps the action list narrow and avoids accidentally "re-confirming" a refund.
- `requiresConfirmation()` with a modal that names the destination phone number.
- On confirm: calls `SendRegistrationSms` with `trigger='manual_resend'`.
- A Filament **notification** appears after save: either "SMS sent to 8801…" or "SMS failed — see logs".

## Wiring inside `ViewRegistration`

1. `form()` is unchanged. (The section already exists.)
2. `mutateFormDataBeforeSave()` is unchanged. (It still derives registration status and syncs the payment row.)
3. New `afterSave()` method:
   ```php
   protected function afterSave(): void
   {
       $record = $this->getRecord();
       $new = $record->payment_status;
       $old = $record->getOriginal('payment_status');

       if ($new === $old) {
           return;                          // no transition, no SMS
       }

       $trigger = match (true) {
           $new === 'paid'     => 'payment_paid',
           $new === 'refunded' => 'payment_refunded',
           default             => null,
       };

       if ($trigger === null) {
           return;
       }

       app(SendRegistrationSms::class)->send($record, $trigger);
   }
   ```
4. New `resendSms` action in `getHeaderActions()`.

## Service — `App\Services\Sms\SendRegistrationSms`

Single public method:

```php
public function send(ReunionRegistration $registration, string $trigger): SmsLog
```

Steps:
1. Resolve normalized phone; resolve message body for the trigger.
2. If phone is null, create `SmsLog` row with `status=skipped` + descriptive `error_message`; return.
3. Call `BulkSmsBdClient::send($phone, $message)`. Client returns `array{ok: bool, http_status: ?int, body: ?string}`.
4. Persist `SmsLog` row:
   - `status = $ok ? 'sent' : 'failed'`
   - `http_status` and `provider_response` populated from result; body truncated to 1024 chars.
   - `sent_at = now()` when `$ok`.
   - `error_message` carries any thrown exception message (we use `try/catch` around the client call so HTTP errors never bubble up to the page).
5. Return the `SmsLog`.

This service is the single audit point. Manual re-send and the auto triggers both funnel through it.

## Client — `App\Services\Sms\BulkSmsBdClient`

Thin HTTP wrapper around `Http::asForm()->timeout(5)->post($url, $payload)`.

```php
public function send(string $phone, string $message): array
{
    $url = 'http://bulksmsbd.net/api/smsapi';
    $payload = [
        'api_key'  => config('services.bulksms.api_key'),
        'senderid' => config('services.bulksms.sender_id'),
        'number'   => $phone,
        'message'  => $message,
    ];

    try {
        $response = Http::asForm()
            ->timeout(5)
            ->withOptions(['verify' => false])   // shared-hosting cert chains vary
            ->post($url, $payload);
        return [
            'ok'           => $response->successful(),
            'http_status'  => $response->status(),
            'body'         => $response->body(),
        ];
    } catch (\Throwable $e) {
        return [
            'ok'           => false,
            'http_status'  => null,
            'body'         => null,
            'exception'    => $e->getMessage(),
        ];
    }
}
```

`Http::throw()` is **not** called; failures are returned, not thrown.

A second method `isConfigured(): bool` returns true only when both `api_key` and `sender_id` are non-empty strings. When false, `SendRegistrationSms` skips the call and writes an `SmsLog` with `status=skipped` and `error_message="bulksms credentials missing"`.

## Error handling summary

| Failure | Behavior |
|---|---|
| Missing/invalid env (`BULKSMS_API_KEY` or `BULKSMS_SENDER_ID` empty) | Log row `skipped`, error describes the missing key. |
| Student phone missing/unparseable | Log row `skipped`, error describes the phone. |
| HTTP 5xx from provider | Log row `failed`, http_status populated, body truncated. |
| HTTP 4xx from provider | Log row `failed`. |
| Timeout (>5s) | Log row `failed`, error = "cURL error 28: Operation timed out". |
| Network unreachable | Log row `failed`, exception message captured. |
| Provider returns 200 but body indicates partial failure (we don't parse) | Logged as `sent` if HTTP 2xx; admin can re-send if they notice by looking at the response body. |

In all cases the registration save **already succeeded** before `afterSave()` runs, so a failing SMS **never** undoes a status change. The audit row is always written.

## Tests

`tests/Feature/AdminRegistrationSmsTest.php`. All tests use `Http::fake()` and `Notification::fake()`.

1. `test_admin_transitions_unpaid_to_paid_sends_confirmation_sms`
   - Registration has `payment_status = unpaid`, student has phone `01712345678`.
   - Visit `ViewRegistration` page, change select to `paid`, submit.
   - Assert one POST to `bulksmsbd.net/api/smsapi` with normalized `8801712345678`, message contains the registration code, amount, and "Registration Confirmed".
   - Assert `SmsLog` row exists with `trigger=payment_paid`, `status=sent`, `http_status=200`, `registration_id` matching, `phone=8801712345678`.

2. `test_admin_transitions_to_refunded_sends_cancellation_sms`
   - From `paid` → `refunded`.
   - Assert message contains "cancelled" and "refund".
   - Assert log row `trigger=payment_refunded`, `status=sent`.

3. `test_no_sms_sent_when_unchanged_payment_status_is_resaved`
   - Record is already `paid`. Re-save with no changes.
   - Assert `Http::assertNothingSent()` and no `SmsLog` rows added.

4. `test_no_sms_sent_when_transitioning_to_unpaid`
   - `pending → unpaid` (or any → unpaid). Assert `Http::assertNothingSent()`, no log rows.

5. `test_resend_action_sends_sms_with_manual_resend_trigger`
   - Record is `paid`.
   - Invoke the Filament header action.
   - Assert `Http::assertSentCount(1)` and log row `trigger=manual_resend`, status `sent`.

6. `test_resend_action_hidden_for_unpaid_and_refunded_registrations`
   - For unpaid: action not rendered in header actions collection.
   - For refunded: same.

7. `test_phone_normalization_handles_common_bd_formats` (unit, `tests/Unit/BangladeshPhoneTest.php`)
   - `01712345678` → `8801712345678`
   - `+8801712345678` (digits only) → `8801712345678`
   - `8801712345678` → unchanged
   - `1712345678` (10 digits, no leading 0) → `8801712345678`
   - `abc12` → null

8. `test_missing_phone_writes_skipped_log_and_save_succeeds`
   - Student has `phone = null`. Transition `unpaid → paid`. Assert `SmsLog` row `status=skipped` with explanatory `error_message`. Assert the registration's `payment_status` is now `paid` (save succeeded).

9. `test_http_failure_writes_failed_log_and_save_succeeds`
   - `Http::fake([...]->throw(...)` simulated. Assert `SmsLog` row `status=failed`, `http_status=500`, truncated response. Assert registration still `paid`.

10. `test_missing_api_credentials_skips_sms_writes_skipped_log`
    - In test setup, override `config('services.bulksms.api_key')` to `''`. Assert `Http::assertNothingSent()`. Log row `status=skipped`, error mentions credentials.

11. `test_admin_response_notification_appears_on_resend` (light smoke — confirm Filament notification fires for success and failure paths via `Notification::assertSent`).

## Files added / modified

| File | Change |
|---|---|
| `database/migrations/2026_10_18_000000_create_sms_logs_table.php` | NEW |
| `app/Models/SmsLog.php` | NEW |
| `app/Services/Sms/BulkSmsBdClient.php` | NEW |
| `app/Services/Sms/SendRegistrationSms.php` | NEW |
| `app/Support/BangladeshPhone.php` | NEW |
| `app/Filament/Resources/Registrations/Pages/ViewRegistration.php` | Add `afterSave()` + `resendSms` header action |
| `config/services.php` | Add `bulksms` block |
| `.env.example` | Add `BULKSMS_API_KEY=`, `BULKSMS_SENDER_ID=` |
| `tests/Feature/AdminRegistrationSmsTest.php` | NEW |
| `tests/Unit/BangladeshPhoneTest.php` | NEW |

## Out of scope

- Filament UI for browsing the `sms_logs` table (model + log rows are queryable; UI deferred).
- Webhook receiver for delivery reports from bulksmsbd.
- Rate limiting / dedupe window (the no-transition guard covers the common case).
- Per-segment Unicode handling (we keep messages ASCII under 160 chars).

## Notes

- The admin Save button will spin for up to 5s in the worst case (HTTP timeout). Accepted as a tradeoff for keeping the implementation synchronous on shared hosting without a queue worker.
- `Http::withOptions(['verify' => false])` mirrors the cURL option in the provided snippet — kept for parity on shared hosting.
